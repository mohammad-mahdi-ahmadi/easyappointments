<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Production route overrides — ADDITIVE. CI includes application/config/routes.php and then
 * application/config/<ENVIRONMENT>/routes.php into the SAME scope (system/core/Router.php:162-169), so
 * this file APPENDS to the `$route` array. It must never reassign it.
 *
 * `/about` is an Easy!Appointments marketing page — its logo, its version, a "Premium" upsell and links
 * to the project's site and issue tracker. The de-brand filter empties it of the brand, but the page has
 * no reason to exist in a business's admin at all, so it is routed away. (Its menu entry is hidden by the
 * de-brand stylesheet; this closes the URL a curious owner could still type.)
 */
// Routed to a controller that does not exist, so CI answers with its (de-branded) 404 page.
$route['about'] = 'not_found';
$route['about/(:any)'] = 'not_found';
