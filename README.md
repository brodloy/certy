# certy.io

A hosted, multi-tenant **SSL certificate & domain expiry monitor**. Users sign
up, add the hosts and domains they care about (up to 10 each), and certy.io
checks them **from the outside** — reading each certificate over a raw TLS
handshake and each domain's registration over raw WHOIS (port 43) — then surfaces
a colour-coded dashboard. Tiered email alerts are the next phase.

Built on a deliberately small, **no-dependency PHP 8** base: no Composer, no
namespaces, no framework. One front controller, a tiny router, plain-PHP views,
and a thin PDO wrapper. SQL lives right in the controller methods.

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

## What works today

Email/password auth (argon2id, rate-limited, remember-me, verification, reset) ·
optional Google & GitHub sign-in (off by default) · per-user targets capped at 10
with active/paused toggle · colour-coded dashboard + KPI tally · on-demand "Scan"
and "Scan all" (live, no reload) · flat scans history and per-target timeline ·
**scheduled scanning** via `php console monitor:run --due` (Task Scheduler / cron).
Each user only ever sees their own targets.

**Next phase:** tiered email alerts (`AlertDispatcher`, 30/14/7/1-day thresholds).
The database is already shaped for it (`AlertLog`, `LK_AlertThreshold`), and the
scheduled scanner is the trigger it will hang off.

## The CLI

```bash
php console db:migrate          # run any new migrations
php console db:install          # migrate, then load demo data
php console db:install --fresh  # DROP all tables, then migrate + seed (DEV ONLY)
php console db:seed             # load demo data
php console db:cleanup          # prune expired tokens + old login attempts
php console monitor:run [--due] # run checks (all active, or only those due)
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

## Going to production

In `config.php`: set `debug => false`, a real `app_url` (https), real DB creds,
and a real `mail_driver`. Serve over HTTPS, point the document root at `public/`,
make `storage/logs/` writable, run `php console db:migrate` on deploy, and
schedule `db:cleanup` + `monitor:run --due` (see [docs/scheduling.md](docs/scheduling.md)).
</content>
</invoke>
