-- Opt-in strict TLS validation for SSL targets. When 1, the certificate must
-- also pass full chain + hostname verification (not just be unexpired), or the
-- check is reported as FAILED — so a self-signed / wrong-host / untrusted cert
-- surfaces as a problem instead of reading "healthy". Default 0 keeps the
-- existing expiry-only behaviour. Ignored for domain targets.
ALTER TABLE `MonitoredTarget`
    ADD COLUMN `VerifyTls` TINYINT(1) NOT NULL DEFAULT 0 AFTER `Port`;
