<?php
/**
 * De-branding (ADDITIVE — multi-tenancy). ONE definition of what "no Easy!Appointments marketing" means,
 * applied to BOTH every rendered page (via the display_override hook) and every outgoing email (via the
 * view overrides in deploy/tenancy/debrand/views/). See application/config/production/hooks.php.
 *
 * Why here and not in nginx: the earlier approach injected CSS through a `sub_filter` on the /booking
 * proxy. It only ever knew about `.footer-powered-by`, which is the ONE upstream template that carries a
 * class (components/booking_footer.php). The confirmation page, the business admin's login page, its
 * backend footer and the error pages all render the same marketing with NO class — and email never goes
 * through the proxy at all, so a customer's confirmation email carried the Easy!Appointments logo and
 * "Powered by Easy!Appointments" in full.
 *
 * The upstream views (application/views/**) are upstream-tracked, so they cannot be edited (ADR 0008).
 * They are filtered instead — a single pass over the rendered HTML.
 *
 * Two mechanisms, deliberately:
 *   1. REMOVE the marketing text/links outright, so it is not merely hidden but absent from the source.
 *   2. HIDE, with an injected stylesheet, the structural blocks that regex should not try to unpick
 *      (the backend footer's nested divs, EA's default logo images, the "About" menu entry). These are
 *      containers, not text, so CSS is the right and safe tool.
 */

if (!function_exists('ea_debrand_html')) {
    /**
     * The stylesheet for the structural blocks. Marked with an `ea-debrand` id so the filter can tell
     * whether a page has already been processed.
     */
    function ea_debrand_style(): string
    {
        return '<style id="ea-debrand">'
            // The public booking page footer's "Powered By" span (the business's legal/imprint links
            // live in the same span and are preserved by the text pass, so this is belt-and-braces).
            . '.footer-powered-by:empty{display:none!important}'
            // The backend footer: EA's logo + link + version, and the author's logo + link.
            . '#footer{display:none!important}'
            // EA's DEFAULT logo, on the booking page and in the backend. A business that uploaded its own
            // logo has a data: URL here, which this selector deliberately does not match.
            . '#company-logo[src*="assets/img/logo.png"],#header-logo img[src*="assets/img/logo.png"]'
            . '{display:none!important}'
            // The backend's "About" menu entry — an Easy!Appointments marketing page.
            . '.dropdown-item[href$="/about"]{display:none!important}'
            . '</style>';
    }

    /**
     * Strip every trace of Easy!Appointments (and its author's) marketing from a rendered document.
     * Safe to call on any string; only a document that actually looks like HTML gets the stylesheet.
     *
     * Two places cannot simply be emptied — something has to be shown — so the business's own identity
     * goes there instead:
     *   $favicon — the business's logo as a data: URL (ea_settings company_logo), for the browser tab.
     *              Without one, the image ships a neutral transparent icon.
     *   $company — the business's name (ea_settings company_name), for the admin header, where upstream
     *              hard-codes "EASY!APPOINTMENTS" and its tagline (components/backend_header.php:14-15).
     */
    function ea_debrand_html(string $html, string $favicon = '', string $company = ''): string
    {
        // 0) The admin header's brand block. The owner opens their own admin: it should carry their own
        //    name, not a blank where a product name used to be.
        $html = preg_replace_callback(
            '#(<div id="header-logo"[^>]*>.*?<h6[^>]*>)(.*?)(</h6>\s*<small[^>]*>)(.*?)(</small>)#is',
            static fn(array $m): string => $m[1] . htmlspecialchars($company, ENT_QUOTES, 'UTF-8') . $m[3] . $m[5],
            $html,
        );

        // 1) "Powered by <a …easyappointments.org…>Easy!Appointments</a>", plus the separator that
        //    upstream puts between it and the business's own link (booking footer, message layout,
        //    account layout, error pages, and every email template).
        $html = preg_replace(
            '#Powered\s*By\s*<a[^>]*easyappointments\.org[^>]*>.*?</a>\s*(?:<span[^>]*>\s*\|\s*</span>|\|)?#is',
            '',
            $html,
        );

        // 2) Any remaining link to easyappointments.org (the backend footer's, which has no "Powered by"
        //    in front of it) and to the author's site, together with the logo image next to each.
        $html = preg_replace(
            '#<img[^>]*(?:alt="Easy!Appointments Logo"|alt="Alex Tselegidis Logo")[^>]*>#i',
            '',
            $html,
        );
        $html = preg_replace(
            '#<a[^>]*(?:easyappointments\.org|alextselegidis\.com)[^>]*>.*?</a>#is',
            '',
            $html,
        );

        // 3) The "| Easy!Appointments" suffix every <title> (and the booking page's og:title) carries.
        $html = str_replace(' | Easy!Appointments', '', $html);

        // 4) The safety net. The brand also appears where no link or block wraps it: the login page's
        //    `alt="Easy!Appointments"`, the About page's body text, and the admin header's tagline.
        //    CASE-INSENSITIVE on purpose — upstream writes the name in at least two casings
        //    (`Easy!Appointments` in 28 places, `EASY!APPOINTMENTS` in the admin header), and a
        //    case-sensitive pass silently missed the one that a business owner sees on every visit.
        $html = str_ireplace(
            [
                'Easy!Appointments',
                'easyappointments.org',
                'Alex Tselegidis',
                'alextselegidis.com',
                'Online Appointment Scheduler', // upstream's tagline, next to the name in the admin header
            ],
            '',
            $html,
        );

        // 5) EA's logo, embedded into every outgoing email as a cid: attachment
        //    (Email_messages.php:301 hard-codes assets/img/logo.png, so the attachment itself cannot be
        //    changed additively — the reference to it is removed instead).
        $html = preg_replace('#<img[^>]*src="cid:logo\.png"[^>]*>#i', '', $html);

        // 6) The browser tab. Point every icon link at the business's own logo when it has one.
        if ($favicon !== '' && strpos($favicon, 'data:image/') === 0) {
            $html = preg_replace(
                '#(<link[^>]*rel="icon"[^>]*href=")[^"]*(")#i',
                '$1' . str_replace('$', '\$', $favicon) . '$2',
                $html,
            );
        }

        // 7) The stylesheet, for the structural blocks regex must not touch. Pages only — an email has no
        //    business carrying a <style> we did not author, and it does not need one.
        if (
            stripos($html, 'ea-debrand') === false &&
            stripos($html, '</head>') !== false &&
            stripos($html, 'cid:') === false
        ) {
            $html = preg_replace('#</head>#i', ea_debrand_style() . '</head>', $html, 1);
        }

        return $html;
    }
}
