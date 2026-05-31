# certy.io

A hosted **SSL certificate & domain expiry monitor**. Users sign up, add the
hosts and domains they care about (up to 10), and certy.io checks them from the
outside — reading each certificate over a raw TLS handshake and each domain's
registration over raw WHOIS (port 43) — then surfaces a colour-coded dashboard
and (soon) emails tiered alerts before anything lapses.

Built on the small no-dependency PHP 8 starter described further below.

## Quick start (WAMP / MAMP / `php -S`)

1. `cp config.example.php config.php` (a ready `config.php` is already included).
   On **WAMP/standard MySQL** the defaults are right (`127.0.0.1:3306`, `root`/`root`).
2. Create an empty database called **`certy`** (utf8mb4) in phpMyAdmin.
3. Build the schema + demo data:
   ```bash
   php console db:install
   ```
4. Serve the **`public/`** folder:
   ```bash
   php -S localhost:8000 -t public
   ```
   then open http://localhost:8000.
5. Sign in with the seed account **`demo@example.com` / `password`**, go to the
   dashboard, **Add target** (try an SSL host like `github.com` and a domain like
   `bbc.co.uk`), then hit **Check now** to populate status.

> **Note on local checks:** the SSL check needs PHP's `openssl` extension
> (on by default in WAMP). The WHOIS check makes outbound connections on
> **port 43** — if your network/firewall blocks that, domain checks will show
> as failed locally even though the code is correct.

### What works today
Sign-in (email/password + Google & GitHub if configured) · per-user targets with a
10-target cap · colour-coded dashboard · per-target history · on-demand
"Check now" (live, no page reload). Each user only ever sees their own targets.

### Not built yet (next phase)
Tiered email alerts (`AlertDispatcher`) + the scheduled `monitor:run` command.
The database is already shaped for the alerting (the
`AlertLog` + `LK_AlertThreshold` tables exist). GitHub & Google sign-in are
already in (optional, off by default).

---

# PHP Lite Starter

A deliberately small, **no-dependency** PHP 8 starter. One entry point, a tiny
router, plain-PHP views, and a thin safe wrapper around the database. It keeps
the security that matters (prepared statements, CSRF, argon2id passwords,
sessions, security headers) but stays close to a classic
"dispatcher → controller → view" layout you can hold in your head.

- **No Composer, no dependencies.** A 6-line autoloader loads your classes. Clone, configure, run.
- **No namespaces.** Classes are global — add a file under `app/`, use it anywhere.
- **A new page is one route line + one method.** SQL is written right in the controller, where you can see it.

---

## What's where (the whole map)

```
certy/
├─ public/
│  ├─ index.php        ← THE front door. Every request starts here.
│  ├─ .htaccess        ← rewrites all URLs to index.php
│  └─ assets/          ← app.css (the look) + app.js
├─ routes.php          ← THE url map. Add a page = add a line here.
├─ console             ← CLI: migrations, seeding, cleanup  (php console …)
├─ bootstrap.php       ← startup: autoloader, config, session, errors
├─ config.php          ← your local settings (copied from config.example.php)
├─ app/
│  ├─ helpers.php      ← global functions: e(), view(), db(), auth(), redirect()…
│  ├─ Router.php       ← matches a URL to a controller method
│  ├─ Database.php     ← db() — safe PDO wrapper (you write the SQL)
│  ├─ Auth.php         ← auth() — login, register, remember-me, verification, OAuth
│  └─ Controllers/     ← one class per area (Home, Auth, Dashboard, Target, Scan, Settings, Admin, Google/GitHubAuth)
├─ views/              ← plain PHP templates (layouts, partials, pages)
├─ database/
│  ├─ migrations/      ← numbered .sql files, run in order
│  └─ seed.sql         ← demo data
├─ storage/
│  ├─ logs/            ← app.log + mail.log
└─ tests.php           ← `php tests.php`
```

**Where do I look when something's wrong?**

| Symptom | Look here |
|---|---|
| URL gives 404 | `routes.php` (is the route declared? right order?) |
| Page logic wrong | the controller method in `app/Controllers/` |
| Page looks wrong | the view in `views/` |
| Data wrong | the SQL — it's right there in the controller method |
| Error / blank page | `storage/logs/app.log` |
| Login behaving oddly | `app/Auth.php` |

---

## Setup (MAMP / WAMP)

You need **PHP 8.2+** and **MySQL** — both come with MAMP/WAMP. No Composer.

1. **Copy the config:** `cp config.example.php config.php`
   (a ready `config.php` with MAMP defaults is already included, so on MAMP you can skip this.)
2. **Check the DB settings** in `config.php`. MAMP uses port **8889** / `root`/`root`
   (already set). WAMP/standard MySQL uses **3306**.
3. **Create an empty database** called `certy` (utf8mb4) in phpMyAdmin.
4. **Build the tables and demo data** — from the project root:
   ```bash
   php console db:install
   ```
   (No CLI handy? In phpMyAdmin, import each file in `database/migrations/` in
   number order, then `database/seed.sql`.)
5. **Serve the `public/` folder:**
   - Easiest: `php -S localhost:8000 -t public`, then open `http://localhost:8000`
   - Or point MAMP/WAMP's document root at this project's **`public/`** folder.

**Logins (from the seed):**
`demo@example.com` / `password` · `admin@example.com` / `password`

Password reset and email verification need **no email setup** locally — the
messages (with links) are written to `storage/logs/mail.log`. Open it and copy the link.

> **Important:** the web server's document root must be `public/`, never the
> project root. Everything sensitive lives outside `public/`. If the page loads
> unstyled, the doc root is wrong — see the deploy notes below.

---

## The CLI

```bash
php console db:migrate          # run any new migrations
php console db:install          # migrate, then load demo data
php console db:install --fresh  # DROP all tables, then migrate + seed (DEV ONLY)
php console db:seed             # load demo data
php console db:cleanup          # prune expired tokens + old login attempts
```

**Changing the schema later** is the migration workflow: add the next numbered
file in `database/migrations/` (e.g. `009_add_widget_table.sql`) and run
`php console db:migrate`. Each file runs once and is recorded, so migrate only
ever runs the new ones.

**Cron:** schedule `php console db:cleanup` daily, e.g.
`0 3 * * * cd /path/to/app && php console db:cleanup`.

---

## How to add a new section (e.g. "Widgets")

Three steps.

**1. Add routes** in `routes.php` (mirror the Examples block; declare
`/widgets/create` *before* `/widgets/{id}`):

```php
$router->get('/widgets',            [WidgetController::class, 'index']);
$router->get('/widgets/create',     [WidgetController::class, 'create']);
$router->post('/widgets',           [WidgetController::class, 'store']);
$router->get('/widgets/{id}',       [WidgetController::class, 'show']);
$router->get('/widgets/{id}/edit',  [WidgetController::class, 'edit']);
$router->post('/widgets/{id}',      [WidgetController::class, 'update']);
$router->post('/widgets/{id}/delete', [WidgetController::class, 'destroy']);
```

**2. Create the controller** `app/Controllers/WidgetController.php` — copy
`TargetController.php` and rename. Every method calls `require_login()` and
scopes its queries to the owner (`WHERE FK_UserID = ?`).

**3. Create the views** in `views/widgets/` — copy from `views/targets/`.

**4. Add a migration** `database/migrations/0NN_create_widget.sql`, run `php console db:migrate`.

That's the loop. No model class, no repository — the SQL lives in the method.

---

## What's included beyond the basics

- **Account settings** — `/settings`: the logged-in user can change their name,
  email, and password (current password required).
- **Per-field form validation** — controllers collect `['field' => 'message']`
  errors and `redirect_errors()` back to the form; views show each message
  under its input with `field_error('name')` + `invalid_class('name')`. Used by
  register, settings, and the add-target form — copy that pattern for your forms.
- **Auth:** email/password (argon2id), rate-limited login, **remember me**,
  **email verification** (banner + resend; links go to `mail.log` locally),
  password reset.
- **Optional Google & GitHub sign-in** — set `google_enabled` / `github_enabled`
  => true in `config.php` with the client id/secret (redirect URIs
  `{app_url}/auth/google/callback` and `{app_url}/auth/github/callback`).
  Off by default; when off the buttons hide and the routes 404. OAuth is
  provider-agnostic (`OAuthIdentity` + `auth()->loginWithOAuth()`), so a third
  provider is just another thin controller.
- **Roles** — `require_admin()` gates a page; demo admin user list at `/admin/users`.
- **Pagination** — `db()->paginate(...)` + `pagination_links(...)`.
- **Dates** — `format_date()` shows UTC values in your `config('timezone')`.
- **Migrations + CLI**, a styled 500 page, and `db:cleanup` for cron.

> The original starter shipped demo **Examples** (CRUD), **Uploads**, and a
> JSON API endpoint as learning references. They've been removed here to keep
> certy.io focused — the **Targets** section is now the copy-me CRUD reference.

---

## The handful of rules that keep it safe

1. **Print dynamic values with `e()`** — `<?= e($row['Title']) ?>`. Stops XSS.
2. **Pass values as params, never paste into SQL** — `db()->all('… WHERE x = ?', [$x])`. Stops SQL injection.
3. **Put `<?= csrf_field() ?>` in every `<form>`.** The router rejects any POST without it.
4. **Scope queries to the user** — `WHERE FK_UserID = ?` — and call `require_login()` (or `require_admin()`) on protected pages.

---

## Going to production

- In `config.php`: set `debug => false`, a real `app_url` (https), real DB creds,
  and `mail_driver => 'mail'` (or wire up a real mailer).
- Serve over **HTTPS** (session + remember-me cookies switch to `secure` automatically).
- Point the document root at **`public/`**.
- Ensure `storage/logs/` is writable by the web server.
- Run `php console db:migrate` on deploy; schedule `db:cleanup` via cron.

**Can't set the document root** (some cheap shared hosts)? Drop this `.htaccess`
in the project root to funnel requests into `public/`:

```apache
RewriteEngine On
RewriteRule ^$ public/ [L]
RewriteRule (.*) public/$1 [L]
```

Pointing the doc root at `public/` is still preferred — this is the fallback.

---

## Commands

```bash
php -S localhost:8000 -t public   # run it
php console db:install            # set up the database + demo data
php tests.php                     # run the tiny test suite
```

## When to move on

When you outgrow this — multiple developers, lots of related tables, queues,
billing — that's the time for the structured "full" starter or a framework like
Laravel. Building on this first is the best way to understand what those bigger
tools do for you.
