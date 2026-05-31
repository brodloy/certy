<?php
/**
 * GOOGLE SIGN-IN — optional, and written without any library (just cURL).
 *
 * Turn it on in config.php: set google_enabled => true and fill in the client
 * id/secret from https://console.cloud.google.com/apis/credentials. Set the
 * authorised redirect URI there to {app_url}/auth/google/callback.
 *
 * When disabled, both routes 404 (see the guard) and the login button hides.
 *
 * Flow (standard OAuth2):
 *   redirect()  → send the user to Google with a one-time "state" value
 *   callback()  → Google returns ?code&state; we verify state, swap the code
 *                 for a token, fetch the profile, then log the user in.
 */
class GoogleAuthController
{
    public function redirect(): string
    {
        $this->ensureEnabled();

        // CSRF protection for the round-trip: a random state we check on return.
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;

        $params = http_build_query([
            'client_id'     => config('google_client_id'),
            'redirect_uri'  => url('/auth/google/callback'),
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'access_type'   => 'online',
            'prompt'        => 'select_account',
        ]);

        header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
        exit;
    }

    public function callback(): string
    {
        $this->ensureEnabled();

        // Verify the state matches what we sent (blocks forged callbacks).
        $state = input('state');
        if ($state === '' || !hash_equals($_SESSION['oauth_state'] ?? '', $state)) {
            return redirect_with('/login', 'error', 'Sign-in could not be verified. Please try again.');
        }
        unset($_SESSION['oauth_state']);

        $code = input('code');
        if ($code === '') {
            return redirect_with('/login', 'error', 'Google sign-in was cancelled.');
        }

        // 1. Exchange the code for an access token.
        $token = $this->post('https://oauth2.googleapis.com/token', [
            'code'          => $code,
            'client_id'     => config('google_client_id'),
            'client_secret' => config('google_client_secret'),
            'redirect_uri'  => url('/auth/google/callback'),
            'grant_type'    => 'authorization_code',
        ]);
        if (empty($token['access_token'])) {
            return redirect_with('/login', 'error', 'Could not complete Google sign-in.');
        }

        // 2. Fetch the user's profile.
        $profile = $this->get('https://openidconnect.googleapis.com/v1/userinfo', $token['access_token']);
        if (empty($profile['sub']) || empty($profile['email'])) {
            return redirect_with('/login', 'error', 'Google did not return your details.');
        }

        // 3. Find-or-create-or-link, then log in.
        auth()->loginWithOAuth([
            'provider' => 'google',
            'id'       => $profile['sub'],
            'email'    => $profile['email'],
            'name'     => $profile['name'] ?? '',
        ]);

        return redirect('/dashboard');
    }

    // --- helpers ---------------------------------------------------------

    private function ensureEnabled(): void
    {
        if (!config('google_enabled')) {
            abort(404, 'Not found.');
        }
    }

    /** POST form-encoded, return decoded JSON. */
    private function post(string $url, array $data): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_TIMEOUT        => 15,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return is_string($body) ? (json_decode($body, true) ?: []) : [];
    }

    /** GET with a bearer token, return decoded JSON. */
    private function get(string $url, string $accessToken): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return is_string($body) ? (json_decode($body, true) ?: []) : [];
    }
}
