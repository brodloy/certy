# certy — Security

> **Keep this current, and treat it as rules — not suggestions.** These are the
> invariants that keep tenants isolated and accounts safe. If a task would break
> one of these, stop and flag it. Last verified against the code: 2026-06-01.

## 1. Per-user ownership (multi-tenant isolation) — the most important rule

certy is multi-tenant: every user must see and touch **only their own**
targets and scan results. There is no shared data.

- **Every query that reads or writes a target is scoped by `FK_UserID`.** The
  canonical pattern is `findOwned($id)` in `TargetController`: it selects
  `WHERE PK_MonitoredTargetID = ? AND FK_UserID = ?` and returns a **404** if no
  row matches. This means "not yours" and "doesn't exist" are indistinguishable
  to an attacker — correct behaviour.
- Scan results are scoped indirectly: queries join `CheckResult` → `MonitoredTarget`
  and filter `t.FK_UserID = ?`.
- **Never** fetch a target (or its history) by primary key alone. Always also
  bind the current user's id.
- The 10-target cap is enforced per-user in the controller.

When adding any feature that touches targets/scans: scope by `current_user()['PK_UserID']`,
and 404 on a miss. No exceptions.

## 2. CSRF protection

- Every `<form>` must include `<?= csrf_field() ?>`.
- The **router verifies the token on every POST** (`csrf_verify()` in
  `Router::dispatch`). A missing/invalid token is rejected before the controller
  runs.
- The token lives in the session (`$_SESSION['_csrf']`, 32 random bytes).
- The live-scan JS reads the token from a `<meta name="csrf-token">` tag and sends
  it as the `_csrf` field — so AJAX POSTs are covered by the same check.
- **Rule:** any new POST route needs a form with `csrf_field()` (or, for fetch,
  the meta token). Don't add GET routes that mutate state.

## 3. Passwords

- Hashed with **argon2id** (`PASSWORD_ARGON2ID`) — PHP's strongest built-in.
- Verified with `password_verify`; rehashed automatically if the cost params
  change (`password_needs_rehash`).
- Login is **rate-limited** (`tooManyAttempts`, 5 per 15 min per email via the
  `LoginAttempt` table).
- Changing a password requires the current one (unless the account has no
  password yet — see OAuth below).
- **Rule:** never store, log, or echo a plaintext password; never weaken the
  hashing algorithm.

## 4. OAuth (Google / GitHub) and the lockout guard

- OAuth is provider-agnostic: one `OAuthIdentity` table (`Provider` +
  `ProviderUserID`) and one `auth()->loginWithOAuth()` path. Adding a provider is
  a thin controller, not new core logic.
- OAuth-only accounts are created with **`PasswordHash = ''`** (empty) — they have
  no password and sign in purely via the provider.
- **Critical guard — never remove:** `disconnectProvider()` refuses to unlink a
  provider if doing so would leave the user with **no way to sign in** (i.e. no
  password AND no other linked provider). Without this guard, an OAuth-only user
  could lock themselves out permanently. The settings UI surfaces this as an error.
- The OAuth `state` parameter is verified on callback (CSRF for the OAuth flow).
- **Rule:** any change to disconnect/delete/login flows must preserve the
  "user always retains at least one working sign-in method" invariant.

## 5. Account deletion

- `deleteAccount()` deletes the `User` row; **FK cascades** remove all the user's
  targets, check results, OAuth identities, etc. automatically.
- The settings flow requires typing `DELETE` to confirm, **and** the current
  password if the account has one. It's irreversible by design.

## 6. Sessions & headers

- Session-based auth; `require_login()` gates every signed-in page and bounces
  guests to `/login`. `require_admin()` gates admin pages.
- Session id is regenerated on login (prevents fixation).
- **Disabled accounts** (`User.Active = 0`, toggled by admins at `/admin/users`)
  are refused at login (password *and* OAuth) and bounced **mid-session** —
  `Auth::user()` re-checks `Active` on every request and logs them out, and their
  remember-me tokens are dropped on disable. Admins can't disable themselves.
- Remember-me uses a hashed token in `RememberToken`, not the password.
- Response headers (set in `public/index.php`): `X-Frame-Options`, nosniff,
  a referrer policy, a CSP whose `img-src` is `'self' data:` only, and **HSTS**
  (`Strict-Transport-Security`, 1 year) sent over HTTPS.
- Target favicons are served same-origin by `FaviconController` (`/favicon`),
  which fetches the icon from Google's S2 service server-side and caches it under
  `storage/cache/`. This keeps the CSP tight, stops privacy blockers from
  hiding the icons, and means the user's browser never tells Google which hosts
  they monitor. The proxy only ever connects to a fixed `www.google.com` URL
  (the host is a query value, not a connect target), so there's no SSRF surface.

## 7. Output & input

- **All output is escaped** with `e()` (htmlspecialchars) in views — prevents XSS.
- **All SQL uses bound parameters** via `db()` — never string-concatenate user
  input into SQL.
- Host input is cleaned/validated (`clean_host()`, `looksLikeHost`) before storage.
- **SSRF guard (outbound scans):** both checkers route every outbound connection
  through `resolve_public_ip()`, which resolves the host and **refuses any
  private/reserved/CGNAT IP** (loopback, RFC1918, `169.254/16` cloud-metadata,
  `100.64/10`) via `FILTER_FLAG_NO_PRIV_RANGE | NO_RES_RANGE` plus an explicit
  CGNAT check, then connects to the **pinned public IP** (the SSL path keeps
  SNI/verification aimed at the hostname) so DNS rebinding can't swap in an
  internal address. The WHOIS path guards its server connections the same way,
  **including registry referral hops**. **Rule:** any new feature that opens an
  outbound connection to user-influenced input must go through `resolve_public_ip()`.
- **CSV exports** are sanitised against spreadsheet **formula injection**
  (`csv_safe_cell()` quotes cells starting with `= @` or a non-numeric `+`/`-`),
  because exported fields include attacker-influenced cert issuer/subject + error text.
- **Rate limiting:** login throttling (`Auth::tooManyAttempts`) plus a generic
  per-IP `rate_limit()` on the expensive/public endpoints — on-demand scans, demo
  login, demo reset, and registration — to blunt scripted abuse of the scanner and
  the signup gate. (Reuses the `LoginAttempt` table; `db:cleanup` prunes it.)

## 8. Registration gate (private beta)

- When `signup_code` is set in `config.php`, the email/password register form
  requires that shared code (`AuthController::register`, compared with
  `hash_equals`). Empty/unset = open registration. This is an **access gate for
  who can create accounts**, not an auth mechanism — it doesn't protect existing
  accounts or data (per-user ownership above does that).
- **Caveat:** the code gates only the email/password form. If you ever enable
  Google/GitHub sign-in, OAuth sign-up would bypass it — gate OAuth too if you
  need the beta fully closed.
- `search_indexable => false` (the default) emits a `noindex` meta on every page
  so a pre-launch deploy stays out of search engines. Set `true` at launch.

## Things never to break (quick checklist)

1. Target/scan queries always scoped by `FK_UserID`; 404 on a miss.
2. Every POST has CSRF (form field or meta token).
3. argon2id stays; passwords never logged/echoed.
4. A user always keeps ≥1 working sign-in method (disconnect guard).
5. Output escaped with `e()`; SQL always parameterised.
