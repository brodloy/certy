<?php
/**
 * AUTH CONTROLLER — login, register, logout, and password reset, all together.
 *
 * Notice the shape of every "handle a form" method:
 *   1. read input()
 *   2. validate (plain ifs into an $errors array)
 *   3. on error: remember_old() + redirect_with(...) back to the form
 *   4. on success: do the thing, then redirect
 * That same shape is all you need for any form in the app.
 */
class AuthController
{
    // ---- Login ------------------------------------------------------------

    public function showLogin(): string
    {
        return view('auth/login', ['title' => 'Sign in']);
    }

    public function login(): string
    {
        $email    = input('email');
        $password = input('password');

        if (auth()->tooManyAttempts($email)) {
            return redirect_with('/login', 'error', 'Too many attempts. Please wait a few minutes.');
        }

        if (auth()->attempt($email, $password)) {
            if (input('remember') !== '') {
                auth()->remember((int) current_user()['PK_UserID']);
            }
            return redirect('/dashboard');
        }

        remember_old(['email' => $email]);
        return redirect_with('/login', 'error', 'Those credentials do not match our records.');
    }

    public function logout(): string
    {
        auth()->forgetRemember();
        auth()->logout();
        return redirect('/');
    }

    // ---- Register ---------------------------------------------------------

    public function showRegister(): string
    {
        return view('auth/register', ['title' => 'Create account']);
    }

    public function register(): string
    {
        $name     = input('name');
        $email    = input('email');
        $password = input('password');
        $confirm  = input('password_confirm');

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Please enter your name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email.';
        } elseif (db()->first('SELECT 1 FROM `User` WHERE `Email` = ?', [$email])) {
            $errors['email'] = 'That email is already registered.';
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirm) {
            $errors['password_confirm'] = 'Passwords do not match.';
        }

        if ($errors) {
            return redirect_errors('/register', $errors, ['name' => $name, 'email' => $email]);
        }

        // The UNIQUE index on Email is the real safety net if two people race.
        try {
            $id = auth()->register($name, $email, $password);
        } catch (PDOException) {
            return redirect_errors('/register', ['email' => 'That email is already registered.'],
                ['name' => $name, 'email' => $email]);
        }

        auth()->login(['PK_UserID' => $id]);
        auth()->sendVerification($id, $email);
        return redirect_with('/dashboard', 'success', 'Welcome aboard! Check your email to verify your address.');
    }

    // ---- Forgot / reset password -----------------------------------------

    public function showForgot(): string
    {
        return view('auth/forgot', ['title' => 'Reset password']);
    }

    public function sendReset(): string
    {
        $email = input('email');
        $user  = db()->first('SELECT * FROM `User` WHERE `Email` = ?', [$email]);

        // Only act if the user exists — but ALWAYS show the same message, so this
        // page can't be used to discover which emails have accounts.
        if ($user !== null) {
            $token = bin2hex(random_bytes(32));
            db()->insert('PasswordReset', [
                'FK_UserID' => $user['PK_UserID'],
                'TokenHash' => hash('sha256', $token),       // store only the hash
                'ExpiresAt' => gmdate('Y-m-d H:i:s', time() + 3600),
                'CreatedAt' => gmdate('Y-m-d H:i:s'),
            ]);

            $link = url('/reset?token=' . $token . '&email=' . urlencode($email));
            send_mail($email, 'Reset your password',
                "Click to choose a new password (valid 1 hour):\n\n{$link}\n");
        }

        return redirect_with('/login', 'success',
            'If that email exists, a reset link has been sent. (Locally: see storage/logs/mail.log)');
    }

    public function showReset(): string
    {
        return view('auth/reset', [
            'title' => 'Choose a new password',
            'token' => input('token'),
            'email' => input('email'),
        ]);
    }

    public function reset(): string
    {
        $token    = input('token');
        $email    = input('email');
        $password = input('password');
        $confirm  = input('password_confirm');

        if (strlen($password) < 8 || $password !== $confirm) {
            return redirect_with(
                '/reset?token=' . urlencode($token) . '&email=' . urlencode($email),
                'error', 'Passwords must match and be at least 8 characters.',
            );
        }

        $user = db()->first('SELECT * FROM `User` WHERE `Email` = ?', [$email]);
        $reset = $user ? db()->first(
            'SELECT * FROM `PasswordReset`
             WHERE `FK_UserID` = ? AND `TokenHash` = ? AND `UsedAt` IS NULL AND `ExpiresAt` >= ?
             ORDER BY `PK_PasswordResetID` DESC LIMIT 1',
            [$user['PK_UserID'], hash('sha256', $token), gmdate('Y-m-d H:i:s')],
        ) : null;

        if ($reset === null) {
            return redirect_with('/forgot', 'error', 'That reset link is invalid or has expired.');
        }

        // Update the password and burn the token, together.
        db()->run('UPDATE `User` SET `PasswordHash` = ?, `UpdatedAt` = ? WHERE `PK_UserID` = ?', [
            password_hash($password, PASSWORD_ARGON2ID), gmdate('Y-m-d H:i:s'), $user['PK_UserID'],
        ]);
        db()->run('UPDATE `PasswordReset` SET `UsedAt` = ? WHERE `PK_PasswordResetID` = ?', [
            gmdate('Y-m-d H:i:s'), $reset['PK_PasswordResetID'],
        ]);

        return redirect_with('/login', 'success', 'Password updated — please sign in.');
    }

    // ---- Email verification ----------------------------------------------

    /** GET /verify?token=&email= — consume the link from the verification email. */
    public function verify(): string
    {
        if (auth()->verify(input('email'), input('token'))) {
            return redirect_with('/dashboard', 'success', 'Email verified — thank you!');
        }
        return redirect_with('/dashboard', 'error', 'That verification link is invalid or has expired.');
    }

    /** POST /verify/resend — send a fresh verification email to the logged-in user. */
    public function resendVerification(): string
    {
        require_login();
        $user = current_user();
        if (empty($user['VerifiedAt'])) {
            auth()->sendVerification((int) $user['PK_UserID'], $user['Email']);
        }
        return redirect_with('/dashboard', 'success', 'Verification email sent. (Locally: see storage/logs/mail.log)');
    }
}
