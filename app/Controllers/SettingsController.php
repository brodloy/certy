<?php
/**
 * SETTINGS — let the logged-in user edit their own profile and password.
 *
 * This is the reference example for the new per-field validation pattern:
 *   1. collect errors into an array keyed by FIELD name
 *   2. if any, redirect_errors() back to the form (it remembers errors + input)
 *   3. the view shows each message under its field via field_error('name')
 */
class SettingsController
{
    public function show(): string
    {
        require_login();
        $user = current_user();
        $uid  = (int) $user['PK_UserID'];

        return view('settings/index', [
            'title'        => 'Account settings',
            'user'         => $user,
            'providers'    => auth()->connectedProviders($uid),
            'hasPassword'  => auth()->hasPassword($uid),
            'targetCount'  => (int) (db()->first(
                'SELECT COUNT(*) AS c FROM `MonitoredTarget` WHERE `FK_UserID` = ?', [$uid])['c'] ?? 0),
        ], 'app');
    }

    /** POST /settings/profile — update name + email. */
    public function updateProfile(): string
    {
        require_login();
        $user  = current_user();
        $name  = input('name');
        $email = input('email');

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Please enter your name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        } elseif ($this->emailTakenByOther($email, (int) $user['PK_UserID'])) {
            $errors['email'] = 'That email is already in use.';
        }

        if ($errors) {
            return redirect_errors('/settings', $errors, ['name' => $name, 'email' => $email]);
        }

        auth()->updateProfile((int) $user['PK_UserID'], $name, $email);
        return redirect_with('/settings', 'success', 'Profile updated.');
    }

    /** POST /settings/password — change password (current one required). */
    public function updatePassword(): string
    {
        require_login();
        $user    = current_user();
        $current = input('current_password');
        $new     = input('new_password');
        $confirm = input('new_password_confirm');

        $errors = [];
        if (strlen($new) < 8) {
            $errors['new_password'] = 'New password must be at least 8 characters.';
        }
        if ($new !== $confirm) {
            $errors['new_password_confirm'] = 'Passwords do not match.';
        }

        if ($errors) {
            return redirect_errors('/settings', $errors, []);
        }

        if (!auth()->changePassword((int) $user['PK_UserID'], $current, $new)) {
            return redirect_errors('/settings', ['current_password' => 'That is not your current password.'], []);
        }

        return redirect_with('/settings', 'success', 'Password changed.');
    }

    /** POST /settings/disconnect — unlink an OAuth provider (guarded). */
    public function disconnect(): string
    {
        require_login();
        $user     = current_user();
        $provider = input('provider') === 'github' ? 'github' : 'google';

        if (!auth()->disconnectProvider((int) $user['PK_UserID'], $provider)) {
            return redirect_with('/settings', 'error',
                'You can\'t disconnect your only sign-in method. Set a password first, or connect another provider.');
        }

        return redirect_with('/settings', 'success', ucfirst($provider) . ' disconnected.');
    }

    /** POST /settings/delete — permanently delete the account (guarded). */
    public function deleteAccount(): string
    {
        require_login();
        $user = current_user();
        $uid  = (int) $user['PK_UserID'];

        // Require an explicit typed confirmation, and re-auth where possible.
        if (input('confirm') !== 'DELETE') {
            return redirect_with('/settings', 'error', 'Type DELETE to confirm account deletion.');
        }

        // If the account has a password, require it to confirm. OAuth-only
        // accounts (no password) skip this — the typed confirmation stands.
        if (auth()->hasPassword($uid)) {
            if (!auth()->attempt($user['Email'], input('password'))) {
                return redirect_with('/settings', 'error', 'That password is incorrect.');
            }
        }

        auth()->deleteAccount($uid);
        return redirect_with('/', 'success', 'Your account has been deleted.');
    }

    /** True if another user (not this one) already has this email. */
    private function emailTakenByOther(string $email, int $userId): bool
    {
        $row = db()->first(
            'SELECT `PK_UserID` FROM `User` WHERE `Email` = ? AND `PK_UserID` <> ?',
            [$email, $userId],
        );
        return $row !== null;
    }
}
