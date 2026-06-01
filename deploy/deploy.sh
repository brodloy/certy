#!/usr/bin/env bash
#
# certy — server-side deploy. Pull the latest main, apply any new migrations.
# Idempotent and safe to run repeatedly.
#
# Run by the GitHub Actions workflow over SSH (.github/workflows/deploy.yml),
# or by hand on the box:  /var/www/certy/deploy/deploy.sh
#
# Why `git reset --hard` and not `git pull`: it can never hit a merge conflict,
# and it only touches TRACKED files — your gitignored config.php and storage/
# (logs, favicon cache) are left exactly as they are.

set -euo pipefail

cd /var/www/certy

echo "==> Fetching origin/main"
git fetch --quiet origin main
git reset --hard origin/main

echo "==> Applying migrations"
php console db:migrate

# PHP opcache picks up changed files automatically (validate_timestamps=1 by
# default), so no php-fpm reload is needed for plain code changes.

echo "==> Deployed $(git rev-parse --short HEAD) ($(git log -1 --format=%s))"
