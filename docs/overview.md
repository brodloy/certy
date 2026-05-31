# certy — Overview

> **Keep this current.** When a feature ships, changes, or is removed, update this
> file in the same task. A stale doc is worse than none — it confidently misleads.
> Last verified against the code: 2026-05-31.

## What it is

certy is a **hosted, multi-tenant SSL certificate & domain expiry monitor**.
Users sign up, add the hosts and domains they care about (up to 10 each), and
certy checks them **from the outside** — reading each certificate over a raw
TLS handshake, and each domain's registration over raw WHOIS (port 43) — then
shows a colour-coded dashboard and emails alerts before anything lapses.

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
  Same data path as manual scans. Each run is recorded to `MonitorRun`, and the
  run is what fires email alerts (below). For scale, a DB-backed queue
  (`monitor:enqueue` + concurrent `monitor:work`) spreads the same work across
  many worker processes. See `scheduling.md`.
- **Email alerts**: `AlertDispatcher` (run only by `monitor:run`, never the
  dashboard scan) emails the target's owner — **HTML + plain-text** — on each
  expiry tier (`LK_AlertThreshold`: 30/14/7/1 days) and once when a check
  transitions into **failed**. Dedup via `AlertLog` keyed by the expiry it fired
  against, so a renewal re-arms every tier. Verified emails only; toggle with
  `alerts_enabled` in `config.php`.
- **Dashboard**: the home page — colour-coded status table + KPI tally, with
  per-row Scan / Edit / Delete and result/host filters. The single list of targets,
  sorted worst-first. Status is derived (never stored): healthy / expiring soon /
  critical (≤7 days) / expired (past) / failed (check errored) / unchecked.
- **Per-target history**: click a host for the timeline of its past checks.
- **Dark mode**: sidebar toggle; follows the OS preference by default and is
  persisted per browser (signed-in app only).
- **Settings**: profile, password (incl. set-password for OAuth-only users),
  connected accounts (link/unlink Google/GitHub), delete account.
- **Admin** (admin role only): a system-wide overview at `/admin` — user/target
  totals, system health tally, and scanner activity (last scheduled + manual
  `monitor:run`, recent run history) — plus the user list at `/admin/users`.
  Admins land here instead of the user dashboard.

- **Data export (CSV)**: the dashboard exports all your targets + current status;
  a target's detail page exports its full check history; the admin page exports
  the `MonitorRun` log. All via the `csv_download()` helper, ownership-scoped.

**Deferred (not built yet):**
- **Reports / richer visualisation** on top of the scan data.
- **Data import** (CSV bulk-add of targets).
- **Webhook / Slack notifications** as an alternative to email.

## Known caveats

- **Table-name casing**: MySQL on Windows stores table names lowercase
  (`lower_case_table_names=1`) and compares case-insensitively. The code queries
  PascalCase (`User`, `MonitoredTarget`). This works on Windows but would break on
  a case-sensitive Linux server. See `database.md`. (Deliberately left as-is until
  a Linux deploy is on the table.)
- Two unused tables (`Example`, `Upload`) remain from the starter; harmless, kept
  to avoid rewriting migration history.
- **The SSL check does not verify the chain** (`verify_peer => false` in
  `CertificateChecker`) — it deliberately reads the cert even if invalid, so it can
  detect expiry on any cert. Consequence: self-signed / wrong-host / untrusted-root
  certs still read as **healthy** (only expiry + reachability are judged). A genuine
  `failed` status comes from a connection/handshake error, not an invalid cert.
