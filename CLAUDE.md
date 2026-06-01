# CLAUDE.md — certy

Read automatically at the start of every session. How this project works and how
I want you to work in it. **Keep it short.**

## What this project is

certy — a hosted, multi-tenant SSL-certificate & domain-expiry monitor, built
on a no-dependency PHP 8 starter (no Composer, no namespaces, no framework).
Runs locally on MAMP (Windows, Apache, MySQL, PHP 8.3).

## The docs

`docs/` holds the reference docs — the source of truth for how the app works:
- `overview.md` — what it is, feature status, caveats
- `architecture.md` — request flow, conventions, file layout, how to add things
- `database.md` — schema, conventions, status derivation
- `security.md` — ownership, CSRF, OAuth, the "never break" rules
- `scheduling.md` — running the scanner unattended
- `deployment.md` — deploying certy to the VPS (config, web, scheduler, auto-deploy)

Read the relevant one before a task. **If a change alters how something works,
update the matching doc in the same change** — a stale doc misleads.

## Working style — keep it simple

- Most changes need no planning doc: make the change, lint/test it, commit with a
  clear message. The git history is the record.
- For a genuinely large, multi-session feature you may jot a short scratch plan,
  but it's optional and not a process to maintain. Don't add phase trackers.
- The only standing doc obligation is the one above: keep `docs/` honest.

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
- DB is MAMP MySQL on port 3306, name `certy`, user/pass root/root.
- The CLI PHP needs `openssl` enabled for SSL checks (see docs/scheduling.md).

## Don't

- Don't add dependencies/Composer/namespaces — keep the no-dep style.
- Don't break the "never break" checklist in docs/security.md.
- Don't change table-name casing (Windows/Linux caveat in docs/database.md) unless
  explicitly doing the pre-Linux-deploy task.
