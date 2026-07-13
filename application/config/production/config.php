<?php defined('BASEPATH') or exit('No direct script access allowed');
/**
 * Production environment overrides — additive, loaded ONLY when ENVIRONMENT=production
 * (APP_ENV=production) AND merged AFTER application/config/config.php.
 * Guarded by defined('EA_TENANT') so, without a tenant, behaviour equals upstream.
 *
 * (Unlike every OTHER config file, this one may append to $config: the main config.php is loaded by
 * get_config() in system/core/Common.php, which requires both files into one scope. CI_Config::load()
 * — which handles email.php and friends — nulls $config between files, so those must build their own.)
 */

// De-branding: CI renders error pages by including them directly (system/core/Exceptions.php:158,195,245)
// so they never reach the display_override hook. Point CI at our filtered copies instead. Outside a
// tenant this is harmless — the templates render upstream's own output.
$config['error_views_path'] = FCPATH . 'deploy/tenancy/debrand/errors/';

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
