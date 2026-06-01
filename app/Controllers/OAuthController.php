<?php
/**
 * OAUTH BASE — the bits the Google and GitHub sign-in controllers share, so
 * each provider's controller only holds what's genuinely provider-specific
 * (its URLs, scopes, and how it yields an email). No library, just cURL.
 *
 * Subclasses call:
 *   $this->verifyState()          at the top of callback() (CSRF for the round-trip)
 *   $this->httpJson($url, $opts)  to make a request and get decoded JSON back
 */
abstract class OAuthController
{
    /**
     * Verify the returned `state` matches the one-time value we stored before
     * the redirect (blocks forged callbacks), then clear it. On mismatch this
     * redirects back to /login and never returns.
     */
    protected function verifyState(): void
    {
        $state = input('state');
        if ($state === '' || !hash_equals($_SESSION['oauth_state'] ?? '', $state)) {
            redirect_with('/login', 'error', 'Sign-in could not be verified. Please try again.');
        }
        unset($_SESSION['oauth_state']);
    }

    /**
     * Make a cURL request and return the decoded JSON body (or [] on any
     * failure). $opts are extra CURLOPT_* options merged over sane defaults —
     * pass CURLOPT_POST/POSTFIELDS for a POST, CURLOPT_HTTPHEADER for headers.
     */
    protected function httpJson(string $url, array $opts = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, $opts + [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return is_string($body) ? (json_decode($body, true) ?: []) : [];
    }
}
