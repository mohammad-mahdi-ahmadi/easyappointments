<?php
/**
 * Outbound-email transport resolution (ADDITIVE — multi-tenancy). The SINGLE source of truth, shared by
 * application/config/production/email.php (what EA actually sends with) and deploy/tenancy/smtp-test.php
 * (the platform's "does our mailer work?" check), so the two can never drift.
 *
 * Two optional JSON files in the email dir, written on the host by `provision-tenant` and mounted
 * READ-ONLY into the container:
 *   <dir>/_platform.json  the platform relay:
 *       { enabled, smtp_host, smtp_port, smtp_secure: none|tls|ssl, smtp_auth, smtp_user, smtp_pass,
 *         from_address }
 *   <dir>/<slug>.json     one business's own SMTP (same keys, minus enabled/from_address)
 * JSON (not PHP) on purpose: nothing operator-supplied is ever turned into executable code.
 *
 * From-identity policy (design D5) — EA resolves identity as
 *   from_name  = config('from_name')    ?: setting('company_name')
 *   from_addr  = config('from_address') ?: setting('company_email')
 *   reply_to   = config('reply_to')     ?: setting('company_email')
 * (application/libraries/Email_messages.php:273-275). Therefore:
 *   - platform relay: we set ONLY from_address (our domain, so SPF/DKIM pass). from_name and reply_to
 *     stay unset, so every business's mail shows ITS name and replies go to ITS address.
 *   - own SMTP: we set NO identity key, so the mail is fully from the business's own address, sent
 *     through the business's own server.
 */

if (!function_exists('ea_email_crypto')) {
    /** PHPMailer's SMTPSecure: '' (none), 'tls' or 'ssl'. */
    function ea_email_crypto(string $secure): string
    {
        return $secure === 'tls' || $secure === 'ssl' ? $secure : '';
    }

    /** Read one JSON config file. A missing or malformed file is treated as absent (never fatal). */
    function ea_email_load(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : [];
    }

    /** Map one config source onto CI's email transport keys. Empty when it carries no host. */
    function ea_email_transport(array $src): array
    {
        if (empty($src['smtp_host'])) {
            return [];
        }

        $user = (string) ($src['smtp_user'] ?? '');

        return [
            'protocol' => 'smtp',
            'mailtype' => 'html',
            'smtp_host' => (string) $src['smtp_host'],
            'smtp_port' => (int) ($src['smtp_port'] ?? 587),
            // CI's base email.php leaves smtp_auth commented out, so PHPMailer would see null. Always
            // decide it here: authenticate only when a username exists (an anonymous relay must not).
            'smtp_auth' => $user !== '' && ($src['smtp_auth'] ?? true),
            'smtp_user' => $user,
            'smtp_pass' => (string) ($src['smtp_pass'] ?? ''),
            'smtp_crypto' => ea_email_crypto((string) ($src['smtp_secure'] ?? 'none')),
        ];
    }

    /**
     * Resolve the effective email config for one tenant ('' = no tenant, i.e. the platform relay only).
     * Returns [] when email is not configured — the base config then stands (protocol='mail').
     */
    function ea_email_config(string $slug, string $dir): array
    {
        $dir = rtrim($dir, '/');

        $override = $slug !== '' ? ea_email_load($dir . '/' . $slug . '.json') : [];

        if (!empty($override['smtp_host'])) {
            return ea_email_transport($override); // identity falls back to the business's own settings
        }

        $platform = ea_email_load($dir . '/_platform.json');

        if (empty($platform['enabled']) || empty($platform['smtp_host'])) {
            return [];
        }

        $config = ea_email_transport($platform);

        if ($config && !empty($platform['from_address'])) {
            $config['from_address'] = (string) $platform['from_address'];
        }

        return $config;
    }
}
