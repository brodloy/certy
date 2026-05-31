# certy.io — Architecture

> **Keep this current.** Update in the same task as any structural change.
> Last verified against the code: 2026-05-31.

## Stack & philosophy

- **PHP 8.3, no Composer, no namespaces, no framework.** A tiny hand-rolled
  router + global helper functions are the entire "framework surface."
- **No ORM, no repository layer, no models.** SQL lives directly in controller
  methods (the house style). A thin PDO wrapper (`db()`) handles queries.
- Classes are global; the autoloader finds them by filename.

## Request lifecycle

1. Every request hits the front controller: **`public/index.php`**.
2. `bootstrap.php` runs: loads config, registers the autoloader, starts the
   session, sets security headers, attempts remember-me login.
3. The **`Router`** (`app/Router.php`) regex-matches the request method + path
   against `routes.php`. `{id}`-style placeholders are passed to the controller
   method as string arguments.
4. The matched **controller method** runs, does its work, and returns a string
   (usually from `view(...)`). POST routes have CSRF verified by the router.
5. `index.php` echoes the returned string.

## File layout

```
certy/
├─ public/
│  ├─ index.php              ← front controller (entry point)
│  └─ assets/{css,js,img}/   ← app.css, app.js, favicons
├─ app/
│  ├─ Router.php             ← regex router
│  ├─ Database.php           ← PDO wrapper exposed via db()
│  ├─ Auth.php               ← auth, OAuth, sessions, account ops
│  ├─ helpers.php            ← global helper functions (the "framework")
│  ├─ Controllers/           ← one class per area
│  ├─ Checks/                ← CertificateChecker, DomainChecker
│  └─ Services/              ← MonitorService (scan orchestrator)
├─ views/
│  ├─ layout/               ← public.php (marketing) + app.php (signed-in shell)
│  ├─ partials/             ← nav, sidebar, flash, filter-bar
│  └─ <area>/               ← per-feature templates (plain PHP)
├─ database/
│  ├─ migrations/           ← numbered .sql files, run in order
│  └─ seed.sql              ← demo users + demo targets
├─ docs/                     ← THIS folder
├─ routes.php
├─ bootstrap.php
├─ config.php                ← local config (gitignored); copy of config.example.php
└─ console                   ← CLI: db:install, db:migrate, db:cleanup, --fresh
```

## Autoloader

`bootstrap.php` registers an autoloader that resolves a class name to a file by
scanning, in order: `/app/`, `/app/Controllers/`, `/app/Checks/`, `/app/Services/`.
**To add a new class folder, add it to that list** or the class won't load.

## Layers (certy-specific)

The scanning feature deliberately separates concerns:

- **Checkers** (`app/Checks/`): `CertificateChecker`, `DomainChecker`. Each takes
  a target, does the network work, returns a plain result array of a shared shape
  (`ok`, `type`, `host`, `expires_at`, `days_left`, `issuer`, `subject`,
  `error`, `checked_at`). They are trigger-agnostic and know nothing about the DB.
- **`MonitorService`** (`app/Services/`): the ONE place scans are generated. Loads
  targets, runs the right checker, persists a `CheckResult` row, and updates the
  denormalised `Last*` snapshot on `MonitoredTarget`. Does NOT send alerts.
- **Controllers**: read/write via `db()`, render views. The scan endpoint
  (`POST /targets/check`, all-or-one) is the only thing that calls `MonitorService`.

This separation is why "Scan" and "Scan all" share identical code, and why a
future scheduled trigger is just another caller of `MonitorService`.

## Adding things

- **A page**: one line in `routes.php` + one controller method + one view.
  Declare literal routes (`/targets/create`) BEFORE `{id}` routes.
- **A table**: a new numbered migration in `database/migrations/`, then
  `php console db:migrate`. Copy `TargetController` as the CRUD reference.

## Console / CLI

`php console <command>` — `db:install` (migrate + seed), `db:migrate`,
`db:cleanup`, and `--fresh` (drop all tables and rebuild). On Windows use the
full MAMP PHP path, e.g. `C:\MAMP\bin\php\php8.3.1\php.exe console db:install`.
