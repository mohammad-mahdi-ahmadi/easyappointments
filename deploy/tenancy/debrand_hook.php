<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * De-branding hooks (ADDITIVE). Registered by application/config/production/hooks.php.
 * What "de-branded" means is defined once, in deploy/tenancy/debrand.php.
 */
require_once __DIR__ . '/debrand.php';

if (!function_exists('ea_debrand_register_views')) {
    /**
     * post_controller_constructor: prepend our view path, so `emails/appointment_saved_email` (and the
     * three other templates) resolve to the shims in deploy/tenancy/debrand/views/emails/ instead of
     * upstream's. Every other view name is absent from that path and falls through to upstream unchanged.
     *
     * This is the only way to reach outgoing email: EA renders it with $this->load->view(…, TRUE) and
     * hands the string straight to PHPMailer, so it never passes through the output class (which the
     * display_override hook below covers) nor through the nginx proxy.
     */
    function ea_debrand_register_views(): void
    {
        get_instance()->load->add_package_path(FCPATH . 'deploy/tenancy/debrand/');
    }
}

if (!function_exists('ea_debrand_display')) {
    /**
     * display_override: CodeIgniter.php calls this INSTEAD of $OUT->_display(), so we must display the
     * output ourselves — filtered when it is HTML, untouched otherwise (EA's AJAX endpoints declare
     * `application/json` via json_response(), http_helper.php:119).
     */
    function ea_debrand_display(): void
    {
        $output = get_instance()->output;
        $body = $output->get_output();

        if (stripos($output->get_content_type(), 'html') !== false) {
            // The tab icon and the admin header cannot simply be emptied — something has to be shown —
            // so they carry the business's own logo and name. setting() needs the tenant's DB; a page
            // rendered before it is up (an early error) has no business identity anyway, so both fall
            // back to empty and the filter just strips.
            $favicon = '';
            $company = '';

            try {
                $company_logo = (string) setting('company_logo');
                $favicon = strpos($company_logo, 'data:image/') === 0 ? $company_logo : '';
                $company = (string) setting('company_name');
            } catch (Throwable $exception) {
                $favicon = '';
                $company = '';
            }

            $body = ea_debrand_html($body, $favicon, $company);
        }

        $output->_display($body);
    }
}
