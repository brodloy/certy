<?php
/**
 * AUTH — everything about who is logged in.
 *
 * Passwords are hashed with argon2id (PHP's strongest built-in). Login is
 * rate-limited so someone can't brute-force a password, sessions are
 * regenerated on login to stop fixation, and disabled accounts are refused.
 *
 * Use the global auth() accessor:
 *   auth()->attempt($email, $password)   // try to log in; returns bool
 *   auth()->check()                       // is someone logged in?
 *   auth()->user()                        // the logged-in user row, or null
 *   auth()->logout()
 */
class Auth
{
    private ?array $cachedUser = null;

    /** Create a new account and return its id. */
    public function register(string $name, string $email, string $password): int
    {
        return db()->insert('User', [
            'Name'         => $name,
            'Email'        => $email,
            'PasswordHash' => password_hash($password, PASSWORD_ARGON2ID),
            'Role'         => 'user',
            'Active'       => 1,
            'CreatedAt'    => gmdate('Y-m-d H:i:s'),
            'UpdatedAt'    => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Try to log in. Returns true on success. Records every attempt so logins
     * can be throttled (see tooManyAttempts).
     */
    public function attempt(string $email, string $password): bool
    {
        db()->insert('LoginAttempt', [
            'Identifier' => $email,
            'IPAddress'  => client_ip(),
            'CreatedAt'  => gmdate('Y-m-d H:i:s'),
        ]);

        $user = db()->first('SELECT * FROM `User` WHERE `Email` = ?', [$email]);

        // Same generic failure for "no such user", "wrong password", "disabled"
        // so an attacker can't tell which accounts exist.
        if ($user === null || (int) $user['Active'] !== 1) {
            return false;
        }
        if (!password_verify($password, $user['PasswordHash'])) {
            return false;
        }

        // If the hashing cost has increased since they signed up, upgrade quietly.
        if (password_needs_rehash($user['PasswordHash'], PASSWORD_ARGON2ID)) {
            db()->run(
                'UPDATE `User` SET `PasswordHash` = ? WHERE `PK_UserID` = ?',
                [password_hash($password, PASSWORD_ARGON2ID), $user['PK_UserID']],
            );
        }

        $this->login($user);
        return true;
    }

    /** Mark a user row as logged in (new session id defeats session fixation). */
    public function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['PK_UserID'];
        $this->cachedUser = null;
    }

    /** Update the logged-in user's name/email. */
    public function updateProfile(int $userId, string $name, string $email): void
    {
        db()->run(
            'UPDATE `User` SET `Name` = ?, `Email` = ?, `UpdatedAt` = ? WHERE `PK_UserID` = ?',
            [$name, $email, gmdate('Y-m-d H:i:s'), $userId],
        );
        $this->cachedUser = null; // force a fresh read next time
    }

    /** Change the logged-in user's password (verifies the current one first). */
    public function changePassword(int $userId, string $current, string $new): bool
    {
        $user = db()->first('SELECT * FROM `User` WHERE `PK_UserID` = ?', [$userId]);
        // Accounts created via OAuth may have no password set yet — allow setting one.
        if ($user === null) {
            return false;
        }
        if ($user['PasswordHash'] !== '' && !password_verify($current, $user['PasswordHash'])) {
            return false;
        }
        db()->run(
            'UPDATE `User` SET `PasswordHash` = ?, `UpdatedAt` = ? WHERE `PK_UserID` = ?',
            [password_hash($new, PASSWORD_ARGON2ID), gmdate('Y-m-d H:i:s'), $userId],
        );
        return true;
    }

    public function logout(): void
    {
        unset($_SESSION['user_id']);
        $this->cachedUser = null;
        session_regenerate_id(true);
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    /** The logged-in user row (cached for the request), re-checking Active each time. */
    public function user(): ?array
    {
        if ($this->cachedUser !== null) {
            return $this->cachedUser;
        }
        if (empty($_SESSION['user_id'])) {
            return null;
        }

        $user = db()->first('SELECT * FROM `User` WHERE `PK_UserID` = ?', [$_SESSION['user_id']]);

        // If the account was disabled mid-session, treat them as logged out.
        if ($user === null || (int) $user['Active'] !== 1) {
            $this->logout();
            return null;
        }

        return $this->cachedUser = $user;
    }

    /** True if this email has had too many login attempts from this IP recently. */
    public function tooManyAttempts(string $email, int $max = 5, int $minutes = 15): bool
    {
        $since = gmdate('Y-m-d H:i:s', time() - $minutes * 60);
        $row = db()->first(
            'SELECT COUNT(*) AS c FROM `LoginAttempt`
             WHERE `Identifier` = ? AND `IPAddress` = ? AND `CreatedAt` >= ?',
            [$email, client_ip(), $since],
        );
        return (int) ($row['c'] ?? 0) >= $max;
    }

    // ---- "Remember me" ----------------------------------------------------
    // On login we can store a long random token (hashed) and drop a cookie. On
    // a later visit with no session, attemptRememberLogin() validates it and
    // logs the user back in. The cookie holds "id:rawToken"; we store only the
    // hash, compare with hash_equals, and rotate the token on each use.

    private const REMEMBER_COOKIE = 'remember';

    public function remember(int $userId): void
    {
        $raw = bin2hex(random_bytes(32));
        $id = db()->insert('RememberToken', [
            'FK_UserID' => $userId,
            'TokenHash' => hash('sha256', $raw),
            'ExpiresAt' => gmdate('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30), // 30 days
            'CreatedAt' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->setRememberCookie($id . ':' . $raw, time() + 60 * 60 * 24 * 30);
    }

    public function attemptRememberLogin(): void
    {
        if ($this->check() || empty($_COOKIE[self::REMEMBER_COOKIE])) {
            return;
        }
        [$id, $raw] = array_pad(explode(':', (string) $_COOKIE[self::REMEMBER_COOKIE], 2), 2, '');

        $token = db()->first(
            'SELECT * FROM `RememberToken` WHERE `PK_RememberTokenID` = ? AND `ExpiresAt` >= ?',
            [(int) $id, gmdate('Y-m-d H:i:s')],
        );
        if ($token === null || !hash_equals($token['TokenHash'], hash('sha256', $raw))) {
            $this->forgetRemember();
            return;
        }

        $user = db()->first('SELECT * FROM `User` WHERE `PK_UserID` = ?', [$token['FK_UserID']]);
        if ($user === null || (int) $user['Active'] !== 1) {
            $this->forgetRemember();
            return;
        }

        // Rotate the token (one-time use) and log them in.
        db()->run('DELETE FROM `RememberToken` WHERE `PK_RememberTokenID` = ?', [$token['PK_RememberTokenID']]);
        $this->login($user);
        $this->remember((int) $user['PK_UserID']);
    }

    public function forgetRemember(): void
    {
        if (!empty($_COOKIE[self::REMEMBER_COOKIE])) {
            $id = (int) explode(':', (string) $_COOKIE[self::REMEMBER_COOKIE], 2)[0];
            db()->run('DELETE FROM `RememberToken` WHERE `PK_RememberTokenID` = ?', [$id]);
        }
        $this->setRememberCookie('', time() - 3600);
    }

    private function setRememberCookie(string $value, int $expires): void
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(self::REMEMBER_COOKIE, $value, [
            'expires'  => $expires,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $https,
        ]);
    }

    // ---- Email verification ----------------------------------------------

    /** Create a verification token and email the link. */
    public function sendVerification(int $userId, string $email): void
    {
        $token = bin2hex(random_bytes(32));
        db()->insert('EmailVerification', [
            'FK_UserID' => $userId,
            'TokenHash' => hash('sha256', $token),
            'ExpiresAt' => gmdate('Y-m-d H:i:s', time() + 60 * 60 * 24),
            'CreatedAt' => gmdate('Y-m-d H:i:s'),
        ]);
        $link = url('/verify?token=' . $token . '&email=' . urlencode($email));
        send_mail($email, 'Verify your email',
            "Confirm your email address (valid 24 hours):\n\n{$link}\n");
    }

    /** Consume a verification token and mark the account verified. Returns bool. */
    public function verify(string $email, string $token): bool
    {
        $user = db()->first('SELECT * FROM `User` WHERE `Email` = ?', [$email]);
        if ($user === null) {
            return false;
        }
        $row = db()->first(
            'SELECT * FROM `EmailVerification`
             WHERE `FK_UserID` = ? AND `TokenHash` = ? AND `UsedAt` IS NULL AND `ExpiresAt` >= ?
             ORDER BY `PK_EmailVerificationID` DESC LIMIT 1',
            [$user['PK_UserID'], hash('sha256', $token), gmdate('Y-m-d H:i:s')],
        );
        if ($row === null) {
            return false;
        }
        db()->run('UPDATE `User` SET `VerifiedAt` = ? WHERE `PK_UserID` = ?',
            [gmdate('Y-m-d H:i:s'), $user['PK_UserID']]);
        db()->run('UPDATE `EmailVerification` SET `UsedAt` = ? WHERE `PK_EmailVerificationID` = ?',
            [gmdate('Y-m-d H:i:s'), $row['PK_EmailVerificationID']]);
        $this->cachedUser = null;
        return true;
    }

    // ---- OAuth (e.g. Google) ---------------------------------------------

    /**
     * Find a user by their external identity, or create/link one, then log in.
     * $profile = ['provider'=>'google', 'id'=>'123', 'email'=>..., 'name'=>...].
     */
    public function loginWithOAuth(array $profile): void
    {
        $identity = db()->first(
            'SELECT * FROM `OAuthIdentity` WHERE `Provider` = ? AND `ProviderUserID` = ?',
            [$profile['provider'], $profile['id']],
        );

        if ($identity !== null) {
            $user = db()->first('SELECT * FROM `User` WHERE `PK_UserID` = ?', [$identity['FK_UserID']]);
        } else {
            // No identity yet — match an existing account by email, or create one.
            $user = db()->first('SELECT * FROM `User` WHERE `Email` = ?', [$profile['email']]);
            if ($user === null) {
                $id = db()->insert('User', [
                    'Name'         => $profile['name'] ?: $profile['email'],
                    'Email'        => $profile['email'],
                    'PasswordHash' => '',                       // OAuth-only account
                    'Role'         => 'user',
                    'Active'       => 1,
                    'VerifiedAt'   => gmdate('Y-m-d H:i:s'),    // email proven by Google
                    'CreatedAt'    => gmdate('Y-m-d H:i:s'),
                    'UpdatedAt'    => gmdate('Y-m-d H:i:s'),
                ]);
                $user = db()->first('SELECT * FROM `User` WHERE `PK_UserID` = ?', [$id]);
            }
            db()->insert('OAuthIdentity', [
                'FK_UserID'      => $user['PK_UserID'],
                'Provider'       => $profile['provider'],
                'ProviderUserID' => $profile['id'],
                'CreatedAt'      => gmdate('Y-m-d H:i:s'),
            ]);
        }

        if ($user === null || (int) $user['Active'] !== 1) {
            throw new RuntimeException('That account is disabled.');
        }
        $this->login($user);
    }

    // --- Connected accounts & deletion --------------------------------------

    /** The OAuth providers linked to a user, e.g. ['google', 'github']. */
    public function connectedProviders(int $userId): array
    {
        $rows = db()->all(
            'SELECT `Provider` FROM `OAuthIdentity` WHERE `FK_UserID` = ? ORDER BY `Provider`',
            [$userId],
        );
        return array_map(fn ($r) => $r['Provider'], $rows);
    }

    /** True if the user can log in with an email + password (has a real hash). */
    public function hasPassword(int $userId): bool
    {
        $row = db()->first('SELECT `PasswordHash` FROM `User` WHERE `PK_UserID` = ?', [$userId]);
        return $row !== null && $row['PasswordHash'] !== '';
    }

    /**
     * Disconnect an OAuth provider — but ONLY if the user would still have a
     * way to sign in (a password, or another linked provider). Returns false
     * if disconnecting would lock them out, so the caller can warn them.
     */
    public function disconnectProvider(int $userId, string $provider): bool
    {
        $providers = $this->connectedProviders($userId);
        if (!in_array($provider, $providers, true)) {
            return true; // nothing to do
        }

        $otherLogin = $this->hasPassword($userId) || count($providers) > 1;
        if (!$otherLogin) {
            return false; // would lock the user out — refuse
        }

        db()->run(
            'DELETE FROM `OAuthIdentity` WHERE `FK_UserID` = ? AND `Provider` = ?',
            [$userId, $provider],
        );
        return true;
    }

    /** Permanently delete a user and (via FK cascade) all their data. */
    public function deleteAccount(int $userId): void
    {
        db()->run('DELETE FROM `User` WHERE `PK_UserID` = ?', [$userId]);
        $this->logout();
    }
}
