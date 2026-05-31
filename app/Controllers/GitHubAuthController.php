<?php
/**
 * GITHUB SIGN-IN — optional, no library (just cURL), mirroring the Google flow.
 *
 * Turn it on in config.php: set github_enabled => true and fill in the client
 * id/secret from https://github.com/settings/developers (a new OAuth App). Set
 * the Authorization callback URL there to {app_url}/auth/github/callback.
 *
 * When disabled, both routes 404 (see the guard) and the login button hides.
 *
 * Two GitHub-specific wrinkles vs. Google:
 *   1. Every GitHub API request MUST send a User-Agent header, or it 403s.
 *   2. The /user endpoint often returns a null email (users hide it), so we
 *      fall back to /user/emails and pick the primary, verified address.
 *
 * Find-or-create-or-link + login is handled by the SAME provider-agnostic
 * auth()->loginWithOAuth() the Google controller uses — just provider 'github'.
 */
class GitHubAuthController
{
    private const UA = 'certy-oauth';

    public function redirect(): string
    {
        $this->ensureEnabled();

        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;

        $params = http_build_query([
            'client_id'     => config('github_client_id'),
            'redirect_uri'  => url('/auth/github/callback'),
            'scope'         => 'read:user user:email',
            'state'         => $state,
            'allow_signup'  => 'true',
        ]);

        header('Location: https://github.com/login/oauth/authorize?' . $params);
        exit;
    }

    public function callback(): string
    {
        $this->ensureEnabled();

        // Verify the state we sent (blocks forged callbacks).
        $state = input('state');
        if ($state === '' || !hash_equals($_SESSION['oauth_state'] ?? '', $state)) {
            return redirect_with('/login', 'error', 'Sign-in could not be verified. Please try again.');
        }
        unset($_SESSION['oauth_state']);

        $code = input('code');
        if ($code === '') {
            return redirect_with('/login', 'error', 'GitHub sign-in was cancelled.');
        }

        // 1. Exchange the code for an access token (ask for JSON back).
        $token = $this->postJson('https://github.com/login/oauth/access_token', [
            'client_id'     => config('github_client_id'),
            'client_secret' => config('github_client_secret'),
            'code'          => $code,
            'redirect_uri'  => url('/auth/github/callback'),
            'state'         => $state,
        ]);
        if (empty($token['access_token'])) {
            return redirect_with('/login', 'error', 'Could not complete GitHub sign-in.');
        }
        $accessToken = $token['access_token'];

        // 2. Fetch the profile.
        $profile = $this->getJson('https://api.github.com/user', $accessToken);
        if (empty($profile['id'])) {
            return redirect_with('/login', 'error', 'GitHub did not return your details.');
        }

        // 3. Get a usable email — /user may hide it, so fall back to /user/emails.
        $email = $profile['email'] ?? null;
        if (empty($email)) {
            $email = $this->primaryVerifiedEmail($accessToken);
        }
        if (empty($email)) {
            return redirect_with('/login', 'error',
                'GitHub did not share a verified email. Add or verify one on GitHub, or use another sign-in method.');
        }

        // 4. Find-or-create-or-link, then log in (same path as Google).
        auth()->loginWithOAuth([
            'provider' => 'github',
            'id'       => (string) $profile['id'],
            'email'    => $email,
            'name'     => $profile['name'] ?? ($profile['login'] ?? ''),
        ]);

        return redirect('/dashboard');
    }

    // --- helpers -------------------------------------------------------------

    private function ensureEnabled(): void
    {
        if (!config('github_enabled')) {
            abort(404, 'Not found.');
        }
    }

    /** Pick the user's primary, verified email from /user/emails. */
    private function primaryVerifiedEmail(string $accessToken): ?string
    {
        $emails = $this->getJson('https://api.github.com/user/emails', $accessToken);
        if (!is_array($emails)) {
            return null;
        }
        // Prefer primary+verified; otherwise any verified.
        foreach ($emails as $e) {
            if (!empty($e['primary']) && !empty($e['verified']) && !empty($e['email'])) {
                return $e['email'];
            }
        }
        foreach ($emails as $e) {
            if (!empty($e['verified']) && !empty($e['email'])) {
                return $e['email'];
            }
        }
        return null;
    }

    /** POST form-encoded, request JSON back, return decoded array. */
    private function postJson(string $url, array $data): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Accept: application/json', 'User-Agent: ' . self::UA],
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return is_string($body) ? (json_decode($body, true) ?: []) : [];
    }

    /** GET with a bearer token + required User-Agent, return decoded array. */
    private function getJson(string $url, string $accessToken): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/vnd.github+json',
                'User-Agent: ' . self::UA,   // GitHub 403s without this
            ],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return is_string($body) ? (json_decode($body, true) ?: []) : [];
    }
}
