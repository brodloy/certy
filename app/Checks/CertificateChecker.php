<?php
/**
 * CERTIFICATE CHECKER — opens a raw TLS socket to a host, captures the peer
 * certificate, and parses its expiry with OpenSSL. No library, no third party.
 *
 * Two modes:
 *   - default ($verify = false): grab the cert even if it is expired or invalid
 *     (verify_peer => false), because reading the expiry is the core feature.
 *   - strict ($verify = true): ALSO require the cert to pass full chain +
 *     hostname verification. If it doesn't (self-signed, wrong host, untrusted
 *     root, expired-by-CA-rules), the check is reported as FAILED — so "your
 *     certificate is broken right now" surfaces, not just "expiring soon".
 *
 * It returns the SHARED RESULT SHAPE (see DomainChecker for the same shape):
 *   success: ['ok'=>true, 'type'=>'ssl', 'host'=>, 'expires_at'=>unix,
 *             'days_left'=>int, 'issuer'=>?, 'subject'=>?, 'checked_at'=>unix]
 *   failure: ['ok'=>false,'type'=>'ssl','host'=>, 'error'=>str, 'checked_at'=>unix]
 *
 * Persistence, alerting and rendering are someone else's job — this just does
 * the network work and hands back a plain array.
 */
class CertificateChecker
{
    public function check(string $host, int $port = 443, int $timeout = 8, bool $verify = false): array
    {
        $host = trim($host);

        // Pass 1 — always read the cert with verification OFF, so we can report
        // expiry/issuer even for an expired or otherwise invalid certificate.
        [$client, $err] = $this->connect($host, $port, $timeout, false);
        if ($client === false) {
            return $this->fail($host, $err !== '' ? $err : 'connection failed');
        }

        $params = stream_context_get_params($client);
        fclose($client);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        if ($cert === null) {
            return $this->fail($host, 'no certificate returned');
        }

        $parsed = openssl_x509_parse($cert);
        if ($parsed === false || empty($parsed['validTo_time_t'])) {
            return $this->fail($host, 'could not parse certificate');
        }

        $expiresAt = (int) $parsed['validTo_time_t'];

        // Pass 2 — strict targets only: a second handshake WITH verification.
        // Pass 1 already proved the host is reachable and served a cert, so a
        // failure here is specifically a validation problem (untrusted chain,
        // wrong hostname, or expired), not an unreachable host.
        if ($verify) {
            [$vClient, $vErr] = $this->connect($host, $port, $timeout, true);
            if ($vClient === false) {
                return $this->fail(
                    $host,
                    'certificate failed validation'
                        . ($vErr !== '' ? ': ' . $vErr : ' (untrusted, wrong host, or expired)'),
                );
            }
            fclose($vClient);
        }

        return [
            'ok'         => true,
            'type'       => 'ssl',
            'host'       => $host,
            'expires_at' => $expiresAt,
            'days_left'  => (int) floor(($expiresAt - time()) / 86400),
            'issuer'     => $parsed['issuer']['O'] ?? ($parsed['issuer']['CN'] ?? null),
            'subject'    => $parsed['subject']['CN'] ?? null,
            'checked_at' => time(),
        ];
    }

    /**
     * Open a TLS socket to the host. Returns [resource|false, errstr].
     * With $verify the chain + hostname are enforced; without, any cert is taken.
     */
    private function connect(string $host, int $port, int $timeout, bool $verify): array
    {
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer'       => $verify,
            'verify_peer_name'  => $verify,
            'peer_name'         => $host,  // hostname to match against in strict mode
            'SNI_enabled'       => true,   // required for hosts serving several certs
        ]]);

        $errno  = 0;
        $errstr = '';
        $client = @stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        return [$client, $errstr];
    }

    private function fail(string $host, string $error): array
    {
        return [
            'ok'         => false,
            'type'       => 'ssl',
            'host'       => $host,
            'error'      => $error,
            'checked_at' => time(),
        ];
    }
}
