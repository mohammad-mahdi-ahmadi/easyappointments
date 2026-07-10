<?php
/**
 * Multi-tenant bootstrap for Easy!Appointments (additive; loaded by the git-ignored root config.php).
 * Resolves the tenant from the request Host (web) or the EA_TENANT env var (CLI), loads that tenant's
 * registry entry, and defines the single `class Config` EA expects — plus the EA_TENANT constant used by
 * application/config/production/config.php for per-tenant isolation.
 */

$ea_registry_dir = getenv('EA_REGISTRY_DIR') ?: (__DIR__ . '/registry');

/** Extract the first DNS label (the business slug) from a host, or '' if none. */
function ea_slug_from_host(string $host): string
{
    $host = strtolower(preg_replace('/:\d+$/', '', trim($host))); // strip port
    if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
        return '';
    }
    $label = explode('.', $host)[0];
    return preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $label) ? $label : '';
}

$ea_slug = getenv('EA_TENANT') ?: ea_slug_from_host($_SERVER['HTTP_HOST'] ?? '');

$ea_entry_file = $ea_registry_dir . '/' . $ea_slug . '.php';

if ($ea_slug === '' || !is_file($ea_entry_file)) {
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, "Unknown tenant '{$ea_slug}'. Set EA_TENANT to a provisioned slug.\n");
        exit(2);
    }
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Unknown booking tenant.";
    exit;
}

$ea_entry = require $ea_entry_file;

if (($ea_entry['status'] ?? 'active') !== 'active') {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "This booking site is temporarily unavailable.";
    exit;
}

define('EA_TENANT', $ea_slug);

// PHP `const` cannot read runtime variables, so define intermediate constants from the
// registry entry BEFORE the class, then reference them inside `class Config`.
define('EA_TENANT_DB_HOST', (string) $ea_entry['db_host']);
define('EA_TENANT_DB_NAME', (string) $ea_entry['db_name']);
define('EA_TENANT_DB_USER', (string) $ea_entry['db_username']);
define('EA_TENANT_DB_PASS', (string) $ea_entry['db_password']);

class Config
{
    const BASE_URL   = 'http://localhost'; // web base_url is auto-derived; overridden to /booking in production config
    const LANGUAGE   = 'english';
    const DEBUG_MODE = false;
    const DB_HOST     = EA_TENANT_DB_HOST;
    const DB_NAME     = EA_TENANT_DB_NAME;
    const DB_USERNAME = EA_TENANT_DB_USER;
    const DB_PASSWORD = EA_TENANT_DB_PASS;
}
