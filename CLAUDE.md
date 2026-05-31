# CLAUDE.md — certy.io

This file is read automatically at the start of every Claude Code session.
It tells you how this project works and how I want you to work in it.

## What this project is

certy.io — a hosted, multi-tenant SSL-certificate & domain-expiry monitor, built
on a no-dependency PHP 8 starter (no Composer, no namespaces, no framework).
Runs locally on MAMP (Windows, Apache, MySQL, PHP 8.3).

## Read the docs first

Before starting any task, read the relevant file(s) in `docs/`:
- `docs/overview.md` — what it is, feature status, caveats
- `docs/architecture.md` — request flow, conventions, file layout, how to add things
- `docs/database.md` — full schema, key conventions, status derivation
- `docs/security.md` — ownership, CSRF, OAuth, the "never break" rules

These docs are the source of truth. **If a task changes how something works,
update the matching doc in the same task.** A stale doc is worse than none.

## Working style (important — applies to every session)

1. **Plan features in a doc.** For any non-trivial feature, create
   `docs/feature-<name>.md` with the full task list broken into phases and a
   progress tracker. Work one phase at a time.
2. **Track progress in the doc.** After completing a phase, update its checklist
   and the "Current status" line in that feature doc — before stopping.
3. **At the end of each phase, confirm the doc is updated** and summarise what's
   done + what's next, so the next session can resume cleanly from the doc.
4. **When resuming**, read the feature doc's progress section first and continue
   from the next unchecked phase.

## Code conventions (see docs/architecture.md for detail)

- SQL lives in controller methods (no models/repositories). Use `db()` with bound
  params — never string-built SQL.
- Every target/scan query is scoped by `FK_UserID`; 404 on a miss. (docs/security.md)
- Every form has `csrf_field()`; every POST is CSRF-checked.
- Output escaped with `e()`. Timestamps stored UTC via `gmdate()`.
- New routes: literal paths before `{id}` paths. New tables: numbered migration
  in `database/migrations/`, then `php console db:migrate`.

## Verifying work

- Lint PHP (`php -l`) and JS (`node --check`) before considering a task done.
- The sandbox can run MariaDB for functional tests; on the user's machine it's
  MAMP MySQL on port 3306, DB name `certy`, user/pass root/root.
- Don't trust live TLS/WHOIS values in a sandbox — verify checker *logic* with
  fixtures; trust real values only on the user's machine.

## Don't

- Don't add dependencies/Composer/namespaces — keep the no-dep style.
- Don't break the "never break" checklist in docs/security.md.
- Don't change table-name casing (Windows/Linux caveat in docs/database.md) unless
  explicitly doing the pre-Linux-deploy task.
