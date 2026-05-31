# Feature: Scheduled Scanning (`monitor:run`)

> **How to use this doc (read every session):** This is the single source of
> truth for this feature's progress. Work **one phase at a time**. After finishing
> a phase, tick its boxes and update **Current status** below — before stopping.
> When resuming, read Current status, then start the next unchecked phase.
> If the design changes mid-build, update this doc in the same task.

## Goal

Let certy.io run checks **unattended** on a schedule, instead of only when a user
clicks "Scan". A new CLI command `php console monitor:run` checks all active
targets (or a due subset) by calling the existing `MonitorService`, so the data
path is identical to the manual scan. A scheduler (Windows Task Scheduler / cron)
calls it periodically. This is the groundwork the deferred email-alerts feature
will build on, but **alerts are out of scope here** — this feature only runs
checks on a timer and records results.

## Design notes / constraints

- **Reuse, don't duplicate.** `MonitorService::runChecks(?array $targetIds)`
  already loads targets, runs the right checker, writes a `CheckResult`, and
  updates the `Last*` snapshot. The command is essentially a thin CLI wrapper
  that selects which targets are "due" and calls it. (See docs/architecture.md.)
- **Active only.** Only scan `MonitoredTarget` rows with `IsActive = 1`.
- **"Due" logic.** v1 can scan *all* active targets each run. A `--due` refinement
  (skip targets checked within an interval) is a later phase, optional.
- **Console style.** Extend the `match($command)` dispatch in `console`; add a
  help line. Mirror the existing command functions' shape.
- **Safe to run often & overlapping.** Keep it idempotent; a run that scans
  already-fresh targets just writes new CheckResult rows (acceptable in v1).
- **No alerts.** Do not send email or write `AlertLog` here. That's a separate
  future feature; just leave the seam.
- Follow docs/security.md + docs/database.md conventions throughout.

## Phases

### Phase 1 — `monitor:run` command (scan all active targets)  ☑
- [x] Add `monitor:run` to the `match()` dispatch in `console`.
- [x] Add a `monitorRun()` function: load all `IsActive = 1` targets across ALL
      users (this is a system job, not user-scoped), call
      `MonitorService::runChecks()` for them, print a summary
      (e.g. "Checked N targets: X ok, Y failed"). — `runChecks(null)` already
      loads every active target system-wide, so the function is a thin wrapper
      that tallies `ok` vs `failed` from the returned results.
- [x] Add a help line for it in `help()`.
- [x] Exit non-zero if the service throws — handled by the existing top-level
      `try/catch` around `match()` in `console` (no extra code needed; the
      function deliberately does not swallow exceptions).
- [x] Lint; functional test on the user's MAMP machine: 3 active SSL targets,
      `Checked 3 target(s): 3 ok, 0 failed.` exit 0, `CheckResult` 6→9, all three
      `Last*` snapshots refreshed.

### Phase 2 — "due" filtering + interval  ☑
- [x] Add a check interval (config value `scan_interval_minutes`, default 720 =
      12h) to `config.example.php` and `config.php`. Read via
      `config('scan_interval_minutes', 720)`.
- [x] `monitor:run --due` scans only active targets whose `LastCheckedAt` is null
      or older than the interval. The due-set is selected in `console` (house
      style: SQL via `db()`), then its ids are passed to `runChecks($ids)` — no
      change to `MonitorService`. Prints `Due run (interval Nm): K target(s) due`,
      or a "nothing due" line when all targets are fresh.
- [x] Plain `monitor:run` still scans all active (`runChecks(null)`).
- [x] Lint; verified on MAMP — A: all-fresh → nothing due; B: one aged target →
      only it re-checked (others untouched, CheckResult +1); C: plain run
      force-checked all three (CheckResult +3, one shared timestamp).

### Phase 3 — scheduler setup + docs  ☑
- [x] Write `docs/scheduling.md`: Windows Task Scheduler (GUI steps + `schtasks`
      one-liner) and the Linux cron equivalent, both calling the MAMP PHP +
      `console monitor:run --due`. Includes the `openssl`-in-CLI prerequisite and
      a "verifying it works" section. **No task was registered** — the user asked
      that nothing be set to run; the doc is steps for them to do when ready.
- [x] Note recommended frequency (fire hourly, let `--due` + the 12h interval
      throttle) and the port-43/WHOIS caveat for domain checks.
- [x] Update `docs/overview.md`: scheduled scanning moved Deferred → Built.
- [x] Update `docs/architecture.md` CLI section with `monitor:run [--due]` and the
      openssl note.

### Phase 4 (optional) — run visibility  ☐
- [ ] Lightweight record of scheduled runs (count, duration, when) — either a log
      line to `storage/logs/` or a small table — so the user can confirm the
      scheduler is firing. Decide log-vs-table before building.

## Current status

**Phases 1–3 done.** Core feature complete: `monitor:run [--due]` works and is
documented for unattended use. Only the **optional** Phase 4 (run visibility)
remains, if wanted.

| Phase | State |
|---|---|
| 1 — monitor:run (all active) | ☑ done, verified on MAMP |
| 2 — --due filtering | ☑ done, verified on MAMP |
| 3 — scheduler setup + docs | ☑ done (docs only; no task registered) |
| 4 — run visibility (optional) | ☐ not started |

## Decisions log
- **Phase 1 is a thin wrapper.** `MonitorService::runChecks(null)` already loads
  all active targets system-wide and persists results, so `monitorRun()` only
  tallies the returned results. Non-zero exit on failure is already provided by
  `console`'s top-level `try/catch`.
- **Found & fixed: error handler ignored `@`.** `bootstrap.php`'s `set_error_handler`
  rethrew *every* warning as an `ErrorException`, defeating the `@stream_socket_client`
  silencer in the checkers — so one unreachable host aborted the whole run (and
  500'd the web "Scan all"). Added an `error_reporting() & $severity` guard so
  suppressed warnings return the checker's graceful `fail()` result. Verified: an
  unreachable host now yields `ok=false` instead of throwing.
- **Setup requirement for Phase 3: CLI `openssl`.** MAMP's Apache PHP has `openssl`
  enabled but the CLI PHP (`C:\MAMP\bin\php\php8.3.x\php.ini`) ships with
  `;extension=openssl` commented out — so SSL checks fail from the command line
  until it's uncommented. Enabled on this machine (backup at `php.ini.certy-bak`).
  **The Phase 3 scheduler docs must call this out** as a prerequisite for whichever
  PHP the scheduled task invokes.
- **Phase 2: due-selection lives in `console`, not `MonitorService`.** The service
  stays a pure "check these ids (or all active)" engine; deciding *which* are due
  is a CLI concern, so the `IsActive=1 AND (LastCheckedAt IS NULL OR < cutoff)`
  query sits in `monitorRun()` and feeds `runChecks($ids)`. Keeps the service
  unchanged and the trigger-specific policy at the edge.
- **Interval default = 720 min (12h).** Cert/domain expiry moves slowly; a 12h
  due-window means a scheduler firing hourly checks each target ~twice a day.
  Tunable per machine via `scan_interval_minutes` in `config.php`.
