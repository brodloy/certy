# certy.io — Security

> **Keep this current, and treat it as rules — not suggestions.** These are the
> invariants that keep tenants isolated and accounts safe. If a task would break
> one of these, stop and flag it. Last verified against the code: 2026-05-31.

## 1. Per-user ownership (multi-tenant isolation) — the most important rule

certy.io is multi-tenant: every user must see and touch **only their own**
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
- Remember-me uses a hashed token in `RememberToken`, not the password.
- Response headers (set in `public/index.php`): `X-Frame-Options`, nosniff,
  a referrer policy, and a CSP whose `img-src` is `'self' data:` only.
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
- Host input is cleaned/validated (`cleanHost`, `looksLikeHost`) before storage.

## Things never to break (quick checklist)

1. Target/scan queries always scoped by `FK_UserID`; 404 on a miss.
2. Every POST has CSRF (form field or meta token).
3. argon2id stays; passwords never logged/echoed.
4. A user always keeps ≥1 working sign-in method (disconnect guard).
5. Output escaped with `e()`; SQL always parameterised.
