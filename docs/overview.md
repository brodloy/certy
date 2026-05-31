# certy — Overview

> **Keep this current.** When a feature ships, changes, or is removed, update this
> file in the same task. A stale doc is worse than none — it confidently misleads.
> Last verified against the code: 2026-05-31.

## What it is

certy is a **hosted, multi-tenant SSL certificate & domain expiry monitor**.
Users sign up, add the hosts and domains they care about (up to 10 each), and
certy checks them **from the outside** — reading each certificate over a raw
TLS handshake, and each domain's registration over raw WHOIS (port 43) — then
shows a colour-coded dashboard and (planned) emails alerts before anything lapses.

It is built on a small, **no-dependency PHP 8 starter** (no Composer, no
namespaces, no framework). See `architecture.md`.

## Deployment model

- **Hosted SaaS**, not self-hosted-per-user. One central server runs the app and
  performs all checks. Each user only sees their own targets.
- Runs locally on **WAMP / MAMP (Windows + Apache + MySQL + PHP 8.3)**.
- The two checkers make outbound connections: TLS (443) for SSL, and WHOIS
  (port 43) for domains. Some networks block port 43 — domain checks then fail
  locally even though the code is correct.

## Feature status (verified 2026-05-31)

**Built and working:**
- Email/password auth (argon2id, rate-limited, remember-me, email verification,
  password reset) — inherited from the starter.
- Optional **Google and GitHub** sign-in (off by default; enable in `config.php`).
- **Targets**: add / edit / delete, per-user, capped at 10. Active/paused toggle.
  Managed directly from the dashboard (no separate list page).
- **Scanning**: on-demand "Scan" (one target) and "Scan all" — live, no reload.
  Generates data via one path (`MonitorService`). SSL + domain checkers both work.
- **Scheduled scanning**: `php console monitor:run [--due]` runs checks unattended
  on a timer (Task Scheduler / cron), `--due` honouring `scan_interval_minutes`.
  Same data path as manual scans; no alerts yet. Each run is recorded to
  `MonitorRun`. See `scheduling.md`.
- **Dashboard**: the home page — colour-coded status table + KPI tally, with
  per-row Scan / Edit / Delete and result/host filters. The single list of targets.
- **Per-target history**: click a host for the timeline of its past checks.
- **Settings**: profile, password (incl. set-password for OAuth-only users),
  connected accounts (link/unlink Google/GitHub), delete account.
- **Admin** (admin role only): a system-wide overview at `/admin` — user/target
  totals, system health tally, and scanner activity (last scheduled + manual
  `monitor:run`, recent run history) — plus the user list at `/admin/users`.
  Admins land here instead of the user dashboard.

**Deferred (not built yet):**
- **Email alerts** — `AlertDispatcher` + tiered thresholds (30/14/7/1 days) with
  dedup. The DB is already shaped for it (`AlertLog`, `LK_AlertThreshold`).
  Scheduled scanning (above) is the trigger this will hang off.
- **Reports / richer visualisation** on top of the scan data.

## Known caveats

- **Table-name casing**: MySQL on Windows stores table names lowercase
  (`lower_case_table_names=1`) and compares case-insensitively. The code queries
  PascalCase (`User`, `MonitoredTarget`). This works on Windows but would break on
  a case-sensitive Linux server. See `database.md`. (Deliberately left as-is until
  a Linux deploy is on the table.)
- Two unused tables (`Example`, `Upload`) remain from the starter; harmless, kept
  to avoid rewriting migration history.
