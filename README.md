# certy

A hosted, multi-tenant **SSL certificate & domain expiry monitor**. Users sign
up, add the hosts and domains they care about (up to 10 each), and certy
checks them **from the outside** — reading each certificate over a raw TLS
handshake and each domain's registration over raw WHOIS (port 43) — then surfaces
a colour-coded dashboard.

Built on a deliberately small, **no-dependency PHP 8** base: no Composer, no
namespaces, no framework. One front controller, a tiny router, plain-PHP views,
and a thin PDO wrapper. SQL lives right in the controller methods.

## For reviewers — architecture & rationale

**What it is:** a multi-tenant monitor that checks SSL certificates (a raw TLS
handshake, parsed with OpenSSL) and domain registrations (raw WHOIS on port 43)
from the outside, then warns owners before either lapses. Live, with a one-click
demo.

**Why no framework / no Composer:** a deliberate constraint — to build at the level
a framework usually hides (routing, request lifecycle, auth, CSRF, a query layer)
and keep the whole thing legible end to end (~5k lines you can read in an
afternoon). *Trade-off:* more wheels reinvented and no ecosystem to lean on; in
return, zero dependency/supply-chain risk, no upgrade treadmill, and nothing
hidden. For a commercial product I'd reach for a framework — this project is
deliberately about the fundamentals.

**Security model:** multi-tenant isolation is the core invariant — every query is
scoped by owner and returns 404 on a miss (so there's no IDOR, and "not yours" is
indistinguishable from "doesn't exist"). CSRF is enforced centrally on every POST;
passwords are **argon2id**; output is escaped by default; and the scanner has an
SSRF guard (resolve the host, reject private/reserved IPs, connect to the pinned
public IP). Full write-up in [docs/security.md](docs/security.md).

**What I'd change at scale:** lift SQL out of controllers into a repository/service
layer; swap the DB-backed `ScanJob` queue for a real broker (e.g. Redis);
externalise sessions; cache check results; and run the scanner as a separately
scalable worker pool (the seam already exists via `monitor:work`).

## Quick start (MAMP / WAMP / `php -S`)

You need **PHP 8.2+** and **MySQL** — both come with MAMP/WAMP.

1. `cp config.example.php config.php` — a ready `config.php` with local defaults
   (`127.0.0.1:3306`, `root`/`root`) is already included, so on this machine you
   can skip this. (Classic MAMP defaults to port `8889`; adjust if yours does.)
2. Create an empty database called **`certy`** (utf8mb4) in phpMyAdmin.
3. Build the schema + demo data, from the project root:
   ```bash
   php console db:install
   ```
4. Serve the **`public/`** folder (never the project root):
   ```bash
   php -S localhost:8000 -t public
   ```
   then open http://localhost:8000. Or point MAMP/WAMP's document root at `public/`.
5. Sign in with a seed account, **Add target** (try an SSL host like `github.com`
   and a domain like `bbc.co.uk`), then hit **Scan** to populate status.

**Seed logins:** `demo@example.com` / `password` · `admin@example.com` / `password`

Password reset and email verification need no mail setup locally — the messages
(with links) are written to `storage/logs/mail.log`.

> **Local check notes:** the SSL check needs PHP's `openssl` extension. The WHOIS
> check makes outbound connections on **port 43** — if your network blocks that,
> domain checks show as failed locally even though the code is correct. For the
> CLI scanner, `openssl` must be enabled in MAMP's *command-line* PHP too — see
> [docs/scheduling.md](docs/scheduling.md).

## Features

- Email/password auth — argon2id hashing, rate-limited, remember-me, email
  verification, and password reset
- Optional Google & GitHub sign-in (off by default)
- Per-user targets capped at 10, with an active/paused toggle, all managed from
  the dashboard
- Two check types — **SSL certificate** expiry (raw TLS handshake) and **domain**
  registration expiry (raw WHOIS, port 43)
- **Strict TLS validation** (opt-in per SSL target) — also flags certs that are
  *invalid* (wrong host, self-signed, untrusted root), not just expiring
- Colour-coded dashboard — KPI tally, per-row Scan / Edit / Delete, result/host
  filters, sorted worst-first. Derived statuses: healthy / expiring soon /
  critical / expired / failed / unchecked
- On-demand **Scan** and **Scan all** (live, no page reload)
- Per-target history timeline (click a host), with each host's favicon
- **Scheduled scanning** via `php console monitor:run --due` (Task Scheduler / cron
  / systemd timer), with transient-failure **retries** so a blip can't fire a false alert
- **Scales out** — optional DB-backed job queue (`monitor:enqueue`) + parallel
  `monitor:work` workers run the same scans across many processes
- **Email alerts** — HTML + plain-text, expiry tiers (30/14/7/1 days) and check
  failures, sent from the scheduled run to verified users. Delivery via the
  configured mail driver: `log` (local), PHP `mail()`, or an **SMTP relay**
- **CSV export** — your targets + status, a target's history, and (admin) the
  scan-run log
- **Admin overview** (`/admin`) — system-wide users / targets / scanner metrics,
  plus the user list
- **Account settings** — profile, change/set password (incl. OAuth-only accounts),
  connect / disconnect Google & GitHub, delete account
- **Dark mode** — sidebar toggle, follows your OS preference
- Strict per-user isolation — each user only ever sees their own targets

## The CLI

```bash
php console db:migrate          # run any new migrations
php console db:install          # migrate, then load demo data
php console db:install --fresh  # DROP all tables, then migrate + seed (DEV ONLY)
php console db:seed             # load demo data
php console db:cleanup          # prune expired tokens, old login attempts, dead jobs
php console monitor:run [--due] # run checks (all active, or only those due)
```

To **scale the scanner**, split discovery from execution with the built-in queue
and run workers in parallel (see [docs/scheduling.md](docs/scheduling.md)):

```bash
php console monitor:enqueue [--due]   # queue targets for the worker pool
php console monitor:work              # claim + run queued jobs (run MANY at once)
php console monitor:queue             # pending / running / failed depth
```

On Windows, use the full MAMP CLI path, e.g.
`C:\MAMP\bin\php\php8.3.1\php.exe console db:install`.

## Where things live

```
certy/
├─ public/index.php   ← front controller; every request starts here
├─ public/assets/     ← app.css, app.js
├─ routes.php         ← the URL map (add a page = add a line)
├─ console            ← CLI entry point
├─ bootstrap.php      ← startup: autoloader, config, session, headers
├─ config.php         ← local settings (gitignored)
├─ app/               ← helpers, Router, Database, Auth, Controllers, Checks, Services
├─ views/             ← plain-PHP templates
├─ database/          ← numbered migrations + seed.sql
├─ storage/logs/      ← app.log + mail.log
└─ tests.php          ← `php tests.php`
```

## Docs

The reference docs in [`docs/`](docs/) are the source of truth for how the app
works — read the relevant one before a change, and keep it current:

- [overview.md](docs/overview.md) — what it is, feature status, caveats
- [architecture.md](docs/architecture.md) — request flow, conventions, file layout, how to add things
- [database.md](docs/database.md) — schema, conventions, status derivation
- [security.md](docs/security.md) — ownership, CSRF, OAuth, the "never break" rules
- [scheduling.md](docs/scheduling.md) — running the scanner unattended
- [deployment.md](docs/deployment.md) — deploying to a Linux VPS (config, web, scheduler, auto-deploy)

## Going to production

certy runs in production on a Linux VPS (Ubuntu + Caddy + php-fpm + MySQL) with
push-to-deploy. The full runbook is in **[docs/deployment.md](docs/deployment.md)**;
in short: set `debug => false`, a real `app_url` (https), real DB creds, an
`smtp` mail driver, serve `public/` over HTTPS, run `php console db:migrate` on
deploy, and schedule `monitor:run --due` + `db:cleanup`. Two pre-launch flags
(`search_indexable`, `signup_code`) keep a not-yet-public deploy noindexed and
invite-only.
</content>
</invoke>
