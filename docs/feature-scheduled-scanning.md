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

### Phase 2 — "due" filtering + interval  ☐
- [ ] Add a check interval (config value, e.g. `scan_interval_minutes`).
- [ ] `monitor:run --due` scans only active targets whose `LastCheckedAt` is null
      or older than the interval.
- [ ] Plain `monitor:run` still scans all active (manual/forced full run).
- [ ] Lint; test that fresh targets are skipped with `--due` and stale ones run.

### Phase 3 — scheduler setup + docs  ☐
- [ ] Write `docs/scheduling.md`: how to run it on a timer on Windows (Task
      Scheduler calling the MAMP PHP + `console monitor:run --due`) and the cron
      equivalent for Linux.
- [ ] Note recommended frequency and the port-43 caveat for domain checks.
- [ ] Update `docs/overview.md`: move "scheduled scanning" from Deferred to Built.
- [ ] Update `docs/architecture.md` CLI section with the new command.

### Phase 4 (optional) — run visibility  ☐
- [ ] Lightweight record of scheduled runs (count, duration, when) — either a log
      line to `storage/logs/` or a small table — so the user can confirm the
      scheduler is firing. Decide log-vs-table before building.

## Current status

**Phase 1 done & verified.** Next: Phase 2 (`--due` filtering + interval).

| Phase | State |
|---|---|
| 1 — monitor:run (all active) | ☑ done, verified on MAMP |
| 2 — --due filtering | ☐ not started |
| 3 — scheduler setup + docs | ☐ not started |
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
