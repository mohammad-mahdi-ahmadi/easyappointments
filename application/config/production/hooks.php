<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Production hooks — ADDITIVE. CI3 includes application/config/hooks.php and then
 * application/config/<ENVIRONMENT>/hooks.php into the SAME scope (system/core/Hooks.php:101-108), so
 * this file APPENDS to the `$hook` array upstream already populated (add_security_headers,
 * storage_cleanup). It must never reassign `$hook`.
 *
 * (Note the contrast with application/config/production/email.php, which must BUILD its own $config:
 * CI_Config::load() nulls $config between files, CI_Hooks does not.)
 *
 * `enable_hooks` is already true upstream (application/config/config.php:225).
 *
 * Two hooks, both for de-branding — see deploy/tenancy/debrand.php for what "de-branded" means:
 *
 *   display_override            — filter the rendered HTML of EVERY page (booking, confirmation, the
 *                                 business admin's login + backend, error pages).
 *   post_controller_constructor — prepend our view path so the four email templates resolve to the
 *                                 shims in deploy/tenancy/debrand/views/emails/, which render upstream's
 *                                 template and pass its output through the same filter. Email never goes
 *                                 through the nginx proxy, so this is the only place it can be caught.
 */

$hook['post_controller_constructor'][] = [
    'class' => '',
    'function' => 'ea_debrand_register_views',
    'filename' => 'debrand_hook.php',
    'filepath' => '../deploy/tenancy',
    'params' => [],
];

$hook['display_override'][] = [
    'class' => '',
    'function' => 'ea_debrand_display',
    'filename' => 'debrand_hook.php',
    'filepath' => '../deploy/tenancy',
    'params' => [],
];
