<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Production email overrides — ADDITIVE. CI3 includes config/<file>.php and then
 * config/<ENVIRONMENT>/<file>.php, array_merging the second OVER the first
 * (system/core/Config.php:132-168), so this file declares only the keys that change. With no tenant, or
 * with email unconfigured, it declares an EMPTY array and upstream behaviour (protocol='mail') stands.
 *
 * IMPORTANT — why this builds $config instead of appending to it: CI_Config::load() sets `$config = NULL`
 * after each file it includes (system/core/Config.php:170) and then rejects the next file if it does not
 * define its own array. (The main config.php behaves differently only because it is loaded by
 * get_config() in system/core/Common.php, which requires both files into ONE scope without unsetting.)
 * Appending here would fatal with "does not appear to contain a valid configuration array".
 *
 * This is the ENTIRE production email wiring: the base application/config/email.php is untouched and
 * the runtime image needs no MTA.
 */
require_once __DIR__ . '/../../../deploy/tenancy/email-config.php';

$ea_email_dir = defined('EA_EMAIL_DIR')
    ? EA_EMAIL_DIR
    : (getenv('EA_EMAIL_DIR') ?: __DIR__ . '/../../../deploy/tenancy/email');

$ea_email_slug = defined('EA_TENANT') ? EA_TENANT : (string) getenv('EA_TENANT');

$config = ea_email_config((string) $ea_email_slug, (string) $ea_email_dir);
