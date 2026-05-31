<?php
/**
 * CERTIFICATE CHECKER — opens a raw TLS socket to a host, captures the peer
 * certificate, and parses its expiry with OpenSSL. No library, no third party.
 *
 * Note `verify_peer => false`: we deliberately want the certificate even if it
 * is expired or otherwise invalid, because an expired cert is exactly what we
 * are trying to detect.
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
    public function check(string $host, int $port = 443, int $timeout = 8): array
    {
        $host = trim($host);

        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer'       => false,
            'verify_peer_name'  => false,
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

        if ($client === false) {
            return $this->fail($host, $errstr !== '' ? $errstr : 'connection failed');
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
