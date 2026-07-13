<?php
/**
 * De-branded override of application/views/errors/html/error_404.php (ADDITIVE).
 *
 * CI renders error pages by including them directly (system/core/Exceptions.php) — they never reach the
 * display_override hook — so they are redirected here with $config['error_views_path'] in
 * application/config/production/config.php. Upstream's own template is rendered and filtered; its markup
 * is not copied.
 */
require_once __DIR__ . '/../../../debrand.php';

ob_start();
include APPPATH . 'views/errors/html/error_404.php';

echo ea_debrand_html((string) ob_get_clean());
