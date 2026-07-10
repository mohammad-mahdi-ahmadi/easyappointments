<?php defined('BASEPATH') or exit('No direct script access allowed');
/**
 * Production environment overrides — additive, loaded ONLY when ENVIRONMENT=production
 * (APP_ENV=production) AND merged AFTER application/config/config.php.
 * Guarded by defined('EA_TENANT') so, without a tenant, behaviour equals upstream.
 */
if (defined('EA_TENANT')) {
    $tenant = EA_TENANT;

    // Per-tenant writable roots (created by provision-tenant). Base paths end at storage/<type>.
    $config['sess_save_path']  = rtrim($config['sess_save_path'], '/')  . '/' . $tenant;
    $config['cache_path']      = rtrim($config['cache_path'], '/')      . '/' . $tenant . '/';
    $config['log_path']        = rtrim($config['log_path'], '/')        . '/' . $tenant . '/';

    // Distinct cookie name so a session id cannot collide across businesses in one browser.
    $config['sess_cookie_name'] = 'ea_session_' . $tenant;

    // Per-tenant encryption key (also mixes the per-tenant DB password already used by the base config).
    $config['encryption_key'] = hash('sha256', $config['encryption_key'] . '|' . $tenant);

    // Mount the app under /booking for web requests (overrides the auto-derived base_url).
    if (!empty($_SERVER['HTTP_HOST'])) {
        $proto = (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
            || ($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http';
        $config['base_url'] = $proto . '://' . $_SERVER['HTTP_HOST'] . '/booking';
    }
}
