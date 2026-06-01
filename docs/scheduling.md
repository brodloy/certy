# certy — Scheduling (unattended scans)

> **Keep this current.** Update in the same task as any change to `monitor:run`
> or its flags. Last verified against the code: 2026-05-31.

This is how to run certy's checks **unattended on a timer**, instead of only
when someone clicks "Scan". The work itself is done by the
`monitor:run` CLI command (see `architecture.md`); this doc is operations only.

## The command

```
php console monitor:run          # force-check EVERY active target (manual/full run)
php console monitor:run --due     # check only targets DUE per scan_interval_minutes
```

`--due` is the mode you schedule. It checks an active target only if it has never
been checked or its `LastCheckedAt` is older than `scan_interval_minutes`
(`config.php`, default **720 = 12h**). So you can fire the task often without
re-scanning fresh targets. Both modes share the exact data path as the dashboard
"Scan all" button (`MonitorService`).

**Alerts fire here.** After the checks run, `monitor:run` calls `AlertDispatcher`,
which emails verified owners on each expiry tier (30/14/7/1 days) and once when a
target transitions into a failed check. The dashboard "Scan all" deliberately does
**not** alert (you're watching the screen). Set `alerts_enabled => false` in
`config.php` to silence all alerting; the run summary reports how many were sent.

**Flap handling.** A check that fails in the scheduled run is **retried** a few
times (`scan_retries`, default 2; `scan_retry_delay_ms` between attempts) before
the failure is accepted — so a momentary network blip can't flip a healthy target
to `failed` and fire a false alert. The interactive dashboard scan skips retries
(it never alerts, and favours a fast response).

It prints a one-line summary and exits **non-zero on failure**, so a scheduler can
detect a broken run:

```
Due run (interval 720m): 2 target(s) due.
Checked 2 target(s): 2 ok, 0 failed.
```

## Prerequisite — `openssl` MUST be enabled in the CLI PHP ⚠

MAMP ships **two** PHP builds: the one Apache loads (which serves the web app) and
the **command-line** one. They have **separate `php.ini` files**, and MAMP's CLI
build has `openssl` **disabled by default**. Without it, every SSL check fails with
`Unable to find the socket transport "ssl"` and the whole run aborts — even though
the web "Scan" button works fine.

**Check** (use the same `php.exe` your scheduled task will call):

```
C:\MAMP\bin\php\php8.3.1\php.exe -m | findstr /I openssl
```

If nothing prints, **enable it**: open that build's `php.ini`
(`C:\MAMP\bin\php\php8.3.1\php.ini`), find `;extension=openssl`, remove the
leading `;`, save. (Already done on this dev machine; a backup was left at
`php.ini.certy-bak`.) Re-run the check above to confirm `openssl` now lists.

> Match the version number to **your** installed MAMP PHP — `php8.3.1` here is an
> example. Whatever PHP the scheduled task invokes is the one that needs `openssl`.

## Windows — Task Scheduler

> These are the steps to create the task **yourself when ready**. Nothing is
> registered automatically.

**GUI:**
1. Open **Task Scheduler** → **Create Task…** (not "Basic Task").
2. **General:** name `certy monitor:run`; select **Run whether user is logged on
   or not**; tick **Run with highest privileges** if needed.
3. **Triggers → New:** **Daily**, repeat task every **1 hour** for a duration of
   **1 day** (i.e. hourly, indefinitely). Hourly is fine — `--due` throttles the
   actual work to once per `scan_interval_minutes`.
4. **Actions → New → Start a program:**
   - **Program/script:** `C:\MAMP\bin\php\php8.3.1\php.exe`
   - **Add arguments:** `console monitor:run --due`
   - **Start in:** `C:\MAMP\htdocs\certy`  ← required, so the app finds its files.
5. **Conditions:** untick "Start the task only if the computer is on AC power" if
   it's a laptop you want scanning on battery.
6. Save (you'll be prompted for your Windows password if running while logged off).

**Equivalent one-liner** (`schtasks`, run in an elevated prompt) — for reference,
not run for you:

```
schtasks /Create /TN "certy monitor:run" /SC HOURLY ^
  /TR "C:\MAMP\bin\php\php8.3.1\php.exe C:\MAMP\htdocs\certy\console monitor:run --due" ^
  /RL HIGHEST /F
```

> `schtasks` has no "Start in" field, so the action uses **absolute paths** for
> both the PHP binary and the `console` script.

## Linux — systemd timer (production)

certy runs in production on a Linux VPS, where the scheduled scan is a **systemd
timer** (`deploy/certy-monitor.{service,timer}`, fired hourly) rather than cron.
Install steps are in [`deployment.md`](deployment.md). The plain-cron equivalent,
for a host without systemd:

```cron
0 * * * * cd /var/www/certy && /usr/bin/php console monitor:run --due >> storage/logs/monitor.log 2>&1
```

Use a PHP CLI binary that has `openssl` (standard on Linux). `cd` into the
project first (or the app can't resolve its paths).

## Recommended frequency

- **Fire the task hourly**; leave `scan_interval_minutes` at **720 (12h)** so each
  target is actually checked ~twice a day. Certs and domain registrations change
  slowly — sub-daily checking is plenty, and `--due` keeps load minimal.
- Want tighter or looser cadence? Change `scan_interval_minutes` in `config.php`;
  no need to touch the scheduler. To check more often than the interval, also
  raise the trigger frequency.
- **Safe to run often and overlapping.** A run that finds nothing due exits
  immediately; a run that re-scans a fresh target just writes another history row.

## Scaling out: queue + workers

`monitor:run` checks every due target **sequentially in one process** — fine for
modest numbers, but wall-clock grows with the target count. To scale, split
*discovery* from *execution* with the built-in DB-backed queue (`ScanJob`):

```
php console monitor:enqueue --due   # fast: finds due targets, queues them
php console monitor:work            # claims a batch and runs it; exits when drained
php console monitor:queue           # pending / running / failed depth
```

Throughput = **number of `monitor:work` processes**. Each worker is sequential
internally but they don't collide — jobs are claimed atomically by a per-worker
token (safe on MySQL 5.7+, no `SKIP LOCKED` needed). So you scale by running more
workers, on more cores or more machines, all pointed at the same database.

**Scheduling it:** run `monitor:enqueue --due` on a timer (e.g. every few minutes),
and run several `monitor:work` frequently (each exits when the queue is empty, so
it's safe to fire often and to overlap). Flags: `--batch=N` (jobs per claim),
`--max=N` (jobs per invocation), `--seconds=N` (max runtime) bound each worker so
a cron-launched worker returns promptly.

`monitor:work` does everything `monitor:run` does per target — writes a
`CheckResult`, refreshes the `Last*` snapshot, and fires alerts via
`AlertDispatcher` — just spread across workers. `monitor:run` stays as the simple
all-in-one for small deployments. (Completed jobs are deleted; `db:cleanup` prunes
any parked `failed` ones.)

## Caveats

- **WHOIS / port 43.** Domain checks make an outbound connection on **port 43**.
  Some networks (and some home ISPs) block it, so domain checks can fail locally
  even though the code is correct — the SSL checks (port 443) are unaffected. If
  scheduled domain checks fail but manual ones from another network succeed,
  suspect a port-43 block on the scheduler's host.
- **Visibility.** Every `monitor:run` records one row in the **`MonitorRun`** table
  (`StartedAt`, `Mode`, `DueCount`, `CheckedCount`, `OkCount`, `FailedCount`,
  `DurationMs`) — including "nothing due" runs, so the row's mere presence proves
  the scheduler fired. Check the most recent runs with:

  ```sql
  SELECT * FROM `MonitorRun` ORDER BY `PK_MonitorRunID` DESC LIMIT 20;
  ```

  The stdout summary is still emitted too; redirect it to a file if you also want a
  plain-text trail (e.g. append `>> storage/logs/monitor.log 2>&1` to the cron line,
  or point the Task Scheduler action at a wrapper `.cmd` that redirects).

## Verifying it works

1. Run the command **manually** first, exactly as the task will:
   `C:\MAMP\bin\php\php8.3.1\php.exe console monitor:run --due` from
   `C:\MAMP\htdocs\certy`. Confirm a clean summary and exit code 0.
2. After the scheduled task fires, check the dashboard — `Last checked` times
   should advance, or query `CheckResult` for fresh rows.
3. In Task Scheduler, the task's **Last Run Result** should be `0x0` (success).
