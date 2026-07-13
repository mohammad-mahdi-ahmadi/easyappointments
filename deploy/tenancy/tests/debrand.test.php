<?php
/**
 * Plain-PHP assertions for the additive de-branding filter (no framework, no DB).
 * Run: php deploy/tenancy/tests/debrand.test.php
 *
 * The fixtures below are copied VERBATIM from the upstream templates, so a change in upstream's markup
 * that our filter no longer matches shows up here as a failing test rather than as Easy!Appointments
 * marketing on a customer's screen.
 */
require_once __DIR__ . '/../debrand.php';

$failures = 0;

function check(string $name, $actual, $expected): void
{
    global $failures;

    if ($actual === $expected) {
        echo "ok   - {$name}\n";

        return;
    }

    $failures++;
    echo "FAIL - {$name}\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n";
}

function gone(string $name, string $html): void
{
    $out = ea_debrand_html($html);
    $clean = stripos($out, 'easyappointments') === false
        && stripos($out, 'Powered by') === false
        && stripos($out, 'Alex Tselegidis') === false;
    check($name, $clean, true);
}

function kept(string $name, string $html, string $needle): void
{
    check($name, strpos(ea_debrand_html($html), $needle) !== false, true);
}

// --- 1) components/booking_footer.php (the public booking page) ------------------------------------
$booking_footer = <<<'HTML'
<div id="frame-footer" class="p-3 text-center border-top">
    <small class="d-block d-md-flex">
        <span class="footer-powered-by small d-block w-100 w-md-50 text-center text-md-start p-1 pe-md-0">
            Powered By
            <a href="https://easyappointments.org" target="_blank">Easy!Appointments</a>

                <span>|</span>
                <a href="https://acme.test/legal" target="_blank">Legal Notice</a>
        </span>
    </small>
</div>
HTML;
gone('booking footer: no EA marketing', $booking_footer);
kept('booking footer: the business legal notice survives', $booking_footer, 'https://acme.test/legal');

// --- 2) layouts/message_layout.php (the "your appointment is booked" page — what the operator saw) --
$message_layout = <<<'HTML'
    <title>Success | Easy!Appointments</title>
            <div class="mt-2">
                <small>
                    Powered by
                    <a href="https://easyappointments.org">Easy!Appointments</a>

                        <span class="mx-1">|</span>
                        <a href="https://acme.test/legal" target="_blank">Legal Notice</a>
                </small>
            </div>
HTML;
gone('confirmation page: no EA marketing', $message_layout);
kept('confirmation page: the business legal notice survives', $message_layout, 'https://acme.test/legal');
check(
    'confirmation page: the title loses the EA suffix',
    strpos(ea_debrand_html($message_layout), '<title>Success</title>') !== false,
    true,
);

// --- 3) layouts/account_layout.php (the business admin's login page) -------------------------------
$account_layout = <<<'HTML'
    <title>Login | Easy!Appointments</title>
        <div class="card-footer text-center py-3">
            <small>
                Powered by
                <a href="https://easyappointments.org">Easy!Appointments</a>
            </small>
        </div>
HTML;
gone('admin login page: no EA marketing', $account_layout);

// --- 4) components/backend_footer.php (the business admin's footer) --------------------------------
$backend_footer = <<<'HTML'
<div id="footer" class="d-lg-flex justify-content-lg-start align-items-lg-center p-2">
    <div class="mb-3 me-lg-5 mb-lg-0">
        <img class="me-1" src="/booking/assets/img/logo-16x16.png" alt="Easy!Appointments Logo">
        <a href="https://easyappointments.org" target="_blank">Easy!Appointments</a>
        <span>v1.5.1</span>
    </div>
    <div class="mb-3 me-lg-5 mb-lg-0">
        <img class="me-1" src="/booking/assets/img/alextselegidis-logo-16x16.png" alt="Alex Tselegidis Logo">
        <a href="https://alextselegidis.com" target="_blank">Alex Tselegidis</a>
    </div>
</div>
HTML;
gone('admin footer: no EA / no author marketing', $backend_footer);

// --- 5) errors/html/error_404.php ------------------------------------------------------------------
$error_page = <<<'HTML'
    <p>
        <small>
            Powered by
            <a href="https://easyappointments.org">Easy!Appointments</a>
        </small>
    </p>
HTML;
gone('error page: no EA marketing', $error_page);

// --- 6) emails/appointment_saved_email.php (what a real customer receives) -------------------------
$email = <<<'HTML'
<img src="cid:logo.png" alt="Logo" style="display:block;max-width:67px; margin: auto auto 24px;">
<td class="content-block powered-by">
    Powered by
    <a href="https://easyappointments.org" style="text-decoration: none;">
        Easy!Appointments
    </a>
    |
    <a href="https://acme.test" style="text-decoration: none;">
        Acme Bakery
    </a>
</td>
HTML;
gone('confirmation email: no EA marketing', $email);
kept('confirmation email: the business name/link survive', $email, 'Acme Bakery');
check(
    'confirmation email: the embedded EA logo is dropped',
    strpos(ea_debrand_html($email), 'cid:logo.png') === false,
    true,
);
check(
    'confirmation email: the dangling separator is dropped',
    (bool) preg_match('/powered-by">\s*<a href="https:\/\/acme\.test"/', ea_debrand_html($email)),
    true,
);

// --- 7) safety: content that merely mentions the word must not be mangled --------------------------
check(
    'a customer note containing "powered by" is untouched',
    strpos(ea_debrand_html('<p>Our espresso is powered by love.</p>'), 'powered by love') !== false,
    true,
);
check(
    'JSON is never touched (the filter is only applied to HTML by the hook)',
    ea_debrand_html('{"ok":true}'),
    '{"ok":true}',
);

// --- 8) the injected stylesheet hides what regex should not touch ----------------------------------
$page = "<html><head><title>x | Easy!Appointments</title></head><body>hi</body></html>";
$out = ea_debrand_html($page);
check('a full page gets the de-brand stylesheet injected once', substr_count($out, 'ea-debrand'), 1);
check('the stylesheet lands inside <head>', strpos($out, '</head>') > strpos($out, 'ea-debrand'), true);

// --- 8b) the places with NO wrapping link or block --------------------------------------------------
// The login page's logo alt text (pages/login.php:7).
gone('login page: the logo alt text carries no brand', '<img src="/x/logo.png" alt="Easy!Appointments" width="72">');
// The booking page's Open Graph title (layouts/booking_layout.php:10).
check(
    'booking page: the og:title loses the EA suffix',
    ea_debrand_html('<meta property="og:title" content="Book Appointment With Acme | Easy!Appointments"/>'),
    '<meta property="og:title" content="Book Appointment With Acme"/>',
);
// The backend header's "Premium" upsell (components/backend_header.php:111).
gone(
    'admin menu: the Premium upsell is gone',
    '<a class="dropdown-item text-danger" href="https://easyappointments.org/premium" target="_blank">Premium</a>',
);
// The About page's body text (pages/about.php) — routed away in production/routes.php, filtered anyway.
gone('about page: body text carries no brand', '<h1>Easy!Appointments</h1><p>By Alex Tselegidis</p>');

// --- 8c) the admin header: upstream hard-codes the name in UPPERCASE (backend_header.php:14-15) ------
// A case-sensitive pass missed this one, and it is the single biggest piece of branding a business owner
// sees — every time they open their own admin.
$backend_header = <<<'HTML'
<div id="header-logo" class="navbar-brand p-1 lh-1">
    <img src="/booking/assets/img/logo.png" alt="logo" class="float-start me-2" style="width: 45px;">
    <h6 class="mb-1 mt-1 fw-bold text-white" style="font-size: 15px;">EASY!APPOINTMENTS</h6>
    <small class="d-block text-white-50" style="font-size: 12px;">Online Appointment Scheduler</small>
</div>
HTML;
gone('admin header: the UPPERCASE brand name and tagline are gone', $backend_header);
check(
    'admin header: it carries the business name instead',
    strpos(ea_debrand_html($backend_header, '', 'Acme Bakery'), '>Acme Bakery</h6>') !== false,
    true,
);
check(
    'admin header: a business name with HTML in it is escaped, not injected',
    strpos(ea_debrand_html($backend_header, '', 'A<script>x</script>'), '<script>') === false,
    true,
);
check(
    'admin header: with no business name it is simply blank (never the product name)',
    stripos(ea_debrand_html($backend_header), 'appointments'),
    false,
);

// --- 9) the browser tab carries the business's own logo, not EA's ----------------------------------
$icons = '<head>'
    . '<link rel="icon" type="image/x-icon" href="/booking/assets/img/favicon.ico">'
    . '<link rel="icon" sizes="192x192" href="/booking/assets/img/logo.png">'
    . '</head>';
$logo = 'data:image/png;base64,iVBORw0KGgo=';
$out = ea_debrand_html($icons, $logo);
check('both icon links point at the business logo', substr_count($out, $logo), 2);
check('no EA icon asset is left referenced', strpos($out, 'assets/img/favicon.ico'), false);
check(
    'without a business logo the icon links are left alone (the image ships a neutral icon)',
    strpos(ea_debrand_html($icons), 'assets/img/favicon.ico') !== false,
    true,
);
check(
    'a non-data favicon value is ignored (never inject an arbitrary URL)',
    strpos(ea_debrand_html($icons, 'https://evil.test/x.png'), 'evil.test'),
    false,
);

echo $failures === 0 ? "\nall passed\n" : "\n{$failures} failure(s)\n";
exit($failures === 0 ? 0 : 1);
