# certy — Deployment (VPS)

> **Keep this current.** Update in the same task as any change to how certy is
> deployed (config keys, the deploy script, the scheduler unit, the web block).
> This doc is **certy-specific**. The shared, one-time **host** setup (the VPS
> itself: PHP, MySQL, Caddy, firewall, Cloudflare DNS) lives in the separate
> infrastructure runbook — see that for prerequisites.

certy runs as one site on a shared VPS: **Caddy** serves it over HTTPS at
`certy.bradleyboothman.dev`, **php-fpm** runs the PHP, **MySQL** holds the data,
and a **systemd timer** runs the scheduled scans. Pushing to `main` auto-deploys.

Supporting files all live in [`deploy/`](../deploy/):
| File | Goes where on the server |
|---|---|
| `deploy/Caddyfile` | a site block appended to `/etc/caddy/Caddyfile` |
| `deploy/config.production.php` | copied to the project root as `config.php` |
| `deploy/certy-monitor.service` / `.timer` | `/etc/systemd/system/` |
| `deploy/deploy.sh` | runs in place from `/var/www/certy/deploy/` |
| `.github/workflows/deploy.yml` | GitHub Actions (push-to-deploy) |

## Prerequisites (from the host runbook)
- Ubuntu 24.04 VPS, hardened, with a non-root `deploy` user.
- PHP 8.3 (`cli` + `fpm`) with `openssl`, `curl`, `pdo_mysql`, `mbstring`.
- MySQL 8 running locally.
- Caddy installed and running, its user in the `www-data` group (so it can reach
  the php-fpm socket).

> **Table-name casing:** install certy's schema with a **fresh** `db:migrate` on
> the VPS (below). Do **not** import a MySQL dump from the Windows dev box — on
> Windows MySQL stores table names lowercase, and restoring that on case-sensitive
> Linux breaks every PascalCase query. A fresh migrate creates the tables as
> `User`, `MonitoredTarget`, … exactly as the code expects. (See `database.md`.)

## First deploy

### 1. Clone
```bash
sudo mkdir -p /var/www && sudo chown deploy:deploy /var/www
git clone https://github.com/brodloy/certy.git /var/www/certy
cd /var/www/certy
```
(Private repo? Use a deploy key or a PAT for the clone — see the host runbook.)

### 2. Config
```bash
cp deploy/config.production.php config.php
nano config.php          # set app_url, a strong db_pass, timezone, mail_from
```
`config.php` is gitignored, so it is never committed and a deploy never overwrites it.

### 3. Database
```bash
sudo mysql <<'SQL'
CREATE DATABASE IF NOT EXISTS certy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'certy'@'127.0.0.1' IDENTIFIED BY 'the-password-from-config.php';
GRANT ALL PRIVILEGES ON certy.* TO 'certy'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

php console db:migrate     # schema + lookup data. NOT db:install — no demo accounts in prod.
```

### 4. Permissions (php-fpm must write logs + the favicon cache)
```bash
sudo chown -R www-data:www-data storage
sudo chmod -R g+ws storage           # setgid: new files inherit the group
sudo usermod -aG www-data deploy      # let the deploy user write storage too
```

### 5. Web (Caddy)
Append certy's block to the Caddyfile, then reload:
```bash
sudo sh -c 'cat /var/www/certy/deploy/Caddyfile >> /etc/caddy/Caddyfile'
sudo systemctl reload caddy
```
(Edit the version in `php_fastcgi unix//run/php/php8.3-fpm.sock` if your PHP-FPM
socket differs.)

### 6. DNS + TLS
In Cloudflare, add an **A record**: `certy` → your VPS IPv4, **Proxy status:
DNS only (grey cloud)** to start. Caddy will then issue the Let's Encrypt cert
automatically on first request. Verify:
```bash
curl -I https://certy.bradleyboothman.dev      # expect HTTP/2 200
```
Once it's working you can *optionally* turn on Cloudflare's proxy (orange cloud)
— but then set SSL/TLS mode to **Full (strict)** or you'll get a redirect loop.

### 7. Scheduler (scans)
```bash
sudo cp deploy/certy-monitor.{service,timer} /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now certy-monitor.timer
systemctl list-timers certy-monitor.timer      # confirm next run
```
This is the Linux equivalent of the Windows Task Scheduler job in
[`scheduling.md`](scheduling.md). Logs: `journalctl -u certy-monitor.service`.

### 8. Make yourself an admin
Register at `https://certy.bradleyboothman.dev/register`. While `mail_driver` is
`'log'`, your verification link is in `storage/logs/mail.log`:
```bash
tail -n 40 storage/logs/mail.log
```
Then promote your account:
```bash
mysql -u certy -p certy -e "UPDATE \`User\` SET \`Role\`='admin' WHERE \`Email\`='you@bradleyboothman.dev';"
```

## Auto-deploy (push-to-deploy)

On every push to `main`, GitHub Actions SSHes into the VPS and runs
`deploy/deploy.sh` (`git reset --hard origin/main` + `php console db:migrate`).
PHP opcache picks up changed files automatically, so no reload is needed.

**One-time setup:**
1. On the VPS, as the `deploy` user, create a key the workflow will use and
   authorise it:
   ```bash
   ssh-keygen -t ed25519 -f ~/.ssh/certy_deploy -N '' -C 'github-actions'
   cat ~/.ssh/certy_deploy.pub >> ~/.ssh/authorized_keys
   cat ~/.ssh/certy_deploy           # copy the PRIVATE key for the secret below
   ```
2. In GitHub → repo **Settings → Secrets and variables → Actions**, add:
   - `DEPLOY_HOST` — the VPS IP or hostname
   - `DEPLOY_USER` — `deploy`
   - `DEPLOY_SSH_KEY` — the **private** key printed above
3. Push to `main` (or run the workflow from the **Actions** tab). Watch it run.

> Migrations run on every deploy and are idempotent (only new files apply). Keep
> migrations additive so a deploy can't break a running site.

## Email (Phase 6 — pending a small code change)

certy's `send_mail()` currently supports only `'log'` (writes to
`storage/logs/mail.log`) and `'mail'` (PHP `mail()`). For reliable delivery from
a subdomain we'll add an **`'smtp'`** driver and point it at a relay
(Resend / Postmark / Mailgun / SES), then set `mail_driver => 'smtp'` and the
`smtp_*` keys in `config.php`. You'll also add SPF + DKIM records (the relay
gives you these) in Cloudflare for the sending domain. Until then, leave
`mail_driver` as `'log'` — sign-up/reset still work via the log file.

## Updating the server stack / adding more projects
Both are **host-level** concerns — see the infrastructure runbook, not this doc.
