<?php
/**
 * DOMAIN CHECKER — raw WHOIS over port 43. No API, no key, no quota.
 *
 * There is no single WHOIS server: each TLD has its own, and response formats
 * differ by registrar. So this does two things:
 *   1. picks the right WHOIS server from a small TLD map (falls back to a
 *      generic discovery via whois.iana.org when the TLD is unknown);
 *   2. parses the expiry date tolerantly, normalising the many date labels
 *      registrars use ("Registry Expiry Date", "Expiry date", "paid-till", ...).
 *
 * Returns the SHARED RESULT SHAPE (same as CertificateChecker, type=>'domain'):
 *   success: ['ok'=>true,'type'=>'domain','host'=>,'expires_at'=>unix,
 *             'days_left'=>int,'issuer'=>null,'subject'=>null,'checked_at'=>unix]
 *   failure: ['ok'=>false,'type'=>'domain','host'=>,'error'=>str,'checked_at'=>unix]
 *
 * GDPR note: many WHOIS responses now redact registrant *contact* details, but
 * the expiry date is almost always still present — so the core feature works.
 */
class DomainChecker
{
    /**
     * TLD => WHOIS server. Covers the common cases for the MVP; unknown TLDs
     * fall back to IANA discovery. Longest-suffix match wins (so 'co.uk' beats
     * 'uk'). Expandable: add a row.
     */
    private array $servers = [
        'com'   => 'whois.verisign-grs.com',
        'net'   => 'whois.verisign-grs.com',
        'org'   => 'whois.pir.org',
        'io'    => 'whois.nic.io',
        'co'    => 'whois.nic.co',
        'dev'   => 'whois.nic.google',
        'app'   => 'whois.nic.google',
        'co.uk' => 'whois.nic.uk',
        'org.uk'=> 'whois.nic.uk',
        'uk'    => 'whois.nic.uk',
    ];

    /** Date labels registrars use for the expiry, in rough order of frequency. */
    private array $expiryLabels = [
        'Registry Expiry Date',
        'Registrar Registration Expiration Date',
        'Expiration Date',
        'Expiry Date',
        'Expiry date',
        'Expires On',
        'Expires',
        'paid-till',
        'renewal date',
        'expire',
    ];

    public function check(string $host, int $timeout = 10): array
    {
        $domain = $this->normaliseDomain($host);
        if ($domain === '') {
            return $this->fail($host, 'not a valid domain');
        }

        $server = $this->serverFor($domain);
        if ($server === null) {
            return $this->fail($domain, 'no WHOIS server for this TLD');
        }

        $raw = $this->query($server, $domain, $timeout);
        if ($raw === null) {
            return $this->fail($domain, "WHOIS query to {$server} failed");
        }

        // Thin registries (e.g. .com) return a referral to the registrar's
        // WHOIS, which carries the dates. Follow one hop if present.
        $referral = $this->findReferral($raw);
        if ($referral !== null && $referral !== $server) {
            $deep = $this->query($referral, $domain, $timeout);
            if ($deep !== null && $this->parseExpiry($deep) !== null) {
                $raw = $deep;
            }
        }

        $expiresAt = $this->parseExpiry($raw);
        if ($expiresAt === null) {
            return $this->fail($domain, 'could not find an expiry date in WHOIS response');
        }

        return [
            'ok'         => true,
            'type'       => 'domain',
            'host'       => $domain,
            'expires_at' => $expiresAt,
            'days_left'  => (int) floor(($expiresAt - time()) / 86400),
            'issuer'     => null,   // not applicable to domains
            'subject'    => null,
            'checked_at' => time(),
        ];
    }

    // --- internals -----------------------------------------------------------

    /** Lower-case, strip scheme/path/leading www. so "https://www.X.com/" -> "x.com". */
    private function normaliseDomain(string $host): string
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return '';
        }
        if (str_contains($host, '://')) {
            $host = (string) parse_url($host, PHP_URL_HOST);
        }
        $host = preg_replace('#[/:].*$#', '', $host);   // drop any path/port leftovers
        $host = preg_replace('#^www\.#', '', (string) $host);
        return (string) $host;
    }

    /** Choose the WHOIS server by longest matching TLD suffix. */
    private function serverFor(string $domain): ?string
    {
        $parts = explode('.', $domain);
        // Try longest suffix first ("a.example.co.uk" -> "example.co.uk" -> ...
        // -> "co.uk" -> "uk"), including the bare TLD as the final attempt.
        for ($i = 0; $i < count($parts); $i++) {
            $suffix = implode('.', array_slice($parts, $i));
            if (isset($this->servers[$suffix])) {
                return $this->servers[$suffix];
            }
        }
        // Unknown TLD: ask IANA which server is authoritative for it.
        return $this->discoverViaIana(end($parts));
    }

    /** Ask whois.iana.org for the authoritative WHOIS server of a TLD. */
    private function discoverViaIana(string $tld): ?string
    {
        $raw = $this->query('whois.iana.org', $tld, 8);
        if ($raw === null) {
            return null;
        }
        if (preg_match('/^whois:\s*(\S+)/mi', $raw, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /** Open a port-43 socket, send "<query>\r\n", read the whole text reply. */
    private function query(string $server, string $query, int $timeout): ?string
    {
        $errno  = 0;
        $errstr = '';
        $fp = @fsockopen($server, 43, $errno, $errstr, $timeout);
        if ($fp === false) {
            return null;
        }
        stream_set_timeout($fp, $timeout);
        fwrite($fp, $query . "\r\n");

        $response = '';
        while (!feof($fp)) {
            $chunk = fread($fp, 4096);
            if ($chunk === false) {
                break;
            }
            $response .= $chunk;
            $info = stream_get_meta_data($fp);
            if ($info['timed_out']) {
                break;
            }
        }
        fclose($fp);

        return $response === '' ? null : $response;
    }

    /** Find a registrar WHOIS referral line, if the registry gave one. */
    private function findReferral(string $raw): ?string
    {
        if (preg_match('/Registrar WHOIS Server:\s*(\S+)/i', $raw, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /** Pull the first parseable expiry date out of a WHOIS text body. */
    private function parseExpiry(string $raw): ?int
    {
        foreach ($this->expiryLabels as $label) {
            // Match "<label>: <value>" case-insensitively, value to end of line.
            $pattern = '/^\s*' . preg_quote($label, '/') . '\s*:?\s*(.+)$/mi';
            if (preg_match($pattern, $raw, $m)) {
                $ts = $this->toTimestamp(trim($m[1]));
                if ($ts !== null) {
                    return $ts;
                }
            }
        }
        return null;
    }

    /** Parse a registrar date string into a unix timestamp, or null. */
    private function toTimestamp(string $value): ?int
    {
        // Trim trailing junk some registrars append after the date.
        $value = preg_replace('/\s+\(.*$/', '', $value);
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        // Normalise dotted dates (e.g. "2027.05.01" used by some ccTLDs) to ISO.
        if (preg_match('/^\d{4}\.\d{2}\.\d{2}/', $value)) {
            $value = str_replace('.', '-', substr($value, 0, 10)) . substr($value, 10);
        }
        $ts = strtotime($value);
        return $ts !== false ? $ts : null;
    }

    private function fail(string $host, string $error): array
    {
        return [
            'ok'         => false,
            'type'       => 'domain',
            'host'       => $host,
            'error'      => $error,
            'checked_at' => time(),
        ];
    }
}
