<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Tenant controller (ADDITIVE — multi-tenancy). CLI-only.
 *
 * Bootstraps/rotates a provisioned tenant's EA admin WITHOUT editing any upstream file. EA's installer
 * seeds a fixed public admin (administrator/administrator) plus demo records; these methods rotate the
 * password, set the real email, and delete the demo records — going through EA's own models so the
 * password is salted+hashed exactly as EA expects.
 *
 * Secrets are read from the environment (never argv): EA_ADMIN_PASSWORD and EA_ADMIN_EMAIL.
 *
 *   php index.php tenant initialize      # rotate admin + set tz + seed a functional neutral starter
 *   php index.php tenant reset_password  # rotate ONLY the admin password (on-demand reset)
 *   php index.php tenant suspend         # close the public booking site (EA-native disable_booking)
 *   php index.php tenant resume          # reopen the public booking site
 *   php index.php tenant apply_brand     # push the platform business brand into ea_settings
 *   php index.php tenant test_email      # send a test email with this tenant's real config
 */
class Tenant extends EA_Controller
{
    public function __construct()
    {
        if (!is_cli()) {
            exit('No direct script access allowed');
        }

        parent::__construct();

        $this->load->model('admins_model');
        $this->load->model('providers_model');
        $this->load->model('customers_model');
        $this->load->model('services_model');
        $this->load->model('service_categories_model');
    }

    /**
     * Bootstrap a freshly installed tenant: rotate the admin password, set its email + company_email +
     * a valid business timezone, replace EA's demo records with a minimal FUNCTIONAL neutral starter (1
     * category, 2 services, 1 provider linked to both with a working plan) so the public booking page
     * works out of the box and is an editable template. Runs exactly once, right after `console install`.
     */
    public function initialize(): void
    {
        $password = (string) getenv('EA_ADMIN_PASSWORD');
        $email = (string) getenv('EA_ADMIN_EMAIL');

        if ($password === '' || $email === '') {
            exit('EA_ADMIN_PASSWORD and EA_ADMIN_EMAIL are required' . PHP_EOL);
        }

        // 1) Admin credential + business email.
        $this->set_admin_password($password, $email);
        setting(['company_email' => $email]);

        // 2) A valid business timezone. EA's default_timezone defaults to the SERVER tz; an invalid/empty
        //    provider timezone throws in Availability.php. The wizard collects it; Cyprus is the default.
        $timezone = (string) getenv('EA_TENANT_TIMEZONE');

        if ($timezone === '' || !in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = 'Asia/Nicosia';
        }

        setting(['default_timezone' => $timezone]);

        // 3) Remove EA's seeded demo (Instance::seed): provider jane@, customer james@, service "Service".
        foreach ($this->providers_model->get(['email' => 'jane@example.org']) as $provider) {
            $this->providers_model->delete($provider['id']);
        }
        foreach ($this->customers_model->get(['email' => 'james@example.org']) as $customer) {
            $this->customers_model->delete($customer['id']);
        }
        foreach ($this->services_model->get(['name' => 'Service']) as $service) {
            $this->services_model->delete($service['id']);
        }

        // 4) Seed a minimal FUNCTIONAL starter (the evidence-based minimum for a bookable page: a
        //    non-private service LINKED to a non-private provider that has a working plan + valid tz).
        $category_id = $this->service_categories_model->save([
            'name' => 'Services',
            'description' => 'Sample category. Rename or replace it in the admin.',
        ]);

        $service_1 = $this->services_model->save([
            'name' => 'Consultation',
            'duration' => '30',
            'price' => 0,
            'currency' => '',
            'slot_interval' => 15,
            'attendants_number' => '1',
            'id_service_categories' => $category_id,
        ]);
        $service_2 = $this->services_model->save([
            'name' => 'Follow-up',
            'duration' => '15',
            'price' => 0,
            'currency' => '',
            'slot_interval' => 15,
            'attendants_number' => '1',
            'id_service_categories' => $category_id,
        ]);

        $this->providers_model->save([
            'first_name' => 'Team',
            'last_name' => 'Member',
            'email' => 'team.member@' . $this->tenant_host(),
            'phone_number' => '',
            'timezone' => $timezone,
            'services' => [$service_1, $service_2], // creates the services_providers links
            'settings' => [
                'username' => 'team.member',
                'password' => bin2hex(random_bytes(9)), // never surfaced; staff are managed in the EA admin
                'working_plan' => setting('company_working_plan'),
                'working_plan_exceptions' => '{}',
                'notifications' => true,
                'google_sync' => false,
                'sync_past_days' => 30,
                'sync_future_days' => 90,
                'calendar_view' => CALENDAR_VIEW_DEFAULT,
            ],
        ]);

        response('ok' . PHP_EOL);
    }

    /**
     * Rotate ONLY the admin password (used by the on-demand Reset action). No other change.
     */
    public function reset_password(): void
    {
        $password = (string) getenv('EA_ADMIN_PASSWORD');

        if ($password === '') {
            exit('EA_ADMIN_PASSWORD is required' . PHP_EOL);
        }

        $this->set_admin_password($password, null);

        response('ok' . PHP_EOL);
    }

    /**
     * Close the public booking site (EA-native `disable_booking`). Keeps ALL data + the admin backend.
     * Idempotent — used by the platform's reversible Suspend action.
     */
    public function suspend(): void
    {
        setting(['disable_booking' => '1']);
        response('ok' . PHP_EOL);
    }

    /**
     * Reopen the public booking site (clears `disable_booking`). Idempotent — used by Resume.
     */
    public function resume(): void
    {
        setting(['disable_booking' => '0']);
        response('ok' . PHP_EOL);
    }

    /**
     * Apply the platform-level business brand to this tenant's EA settings. Idempotent; called by the
     * brand reconciler for every capability the business has enabled, so the owner never sets branding
     * inside a provider's own admin panel.
     *
     * Values arrive in the environment (never argv). The LOGO arrives base64-encoded on STDIN, because
     * it can be hundreds of KB — past the argv limit — and argv is world-readable via /proc/<pid>/cmdline.
     * Empty stdin clears the logo (EA then renders its default, which the platform's proxy CSS hides).
     *
     * Deliberately does NOT touch the admin ACCOUNT's login email: rewriting it from the dashboard would
     * silently lock the business owner out of their own booking admin. That email is set once, at
     * provision, and changed thereafter only from EA's admin.
     */
    public function apply_brand(): void
    {
        $name = trim((string) getenv('EA_BRAND_NAME'));
        $email = trim((string) getenv('EA_BRAND_EMAIL'));
        $link = trim((string) getenv('EA_BRAND_LINK'));
        $color = trim((string) getenv('EA_BRAND_COLOR'));
        $logo_mime = trim((string) getenv('EA_BRAND_LOGO_MIME'));

        if ($name !== '') {
            setting(['company_name' => $name]);
        }

        if ($email !== '') {
            setting(['company_email' => $email]);
        }

        if ($link !== '') {
            setting(['company_link' => $link]);
        }

        if (preg_match('/^#[0-9a-f]{6}$/i', $color)) {
            setting(['company_color' => strtolower($color)]);
        }

        // EA stores company_logo as a base64 data URL directly in ea_settings.value (LONGTEXT) and
        // renders it straight into <img src> (views/components/booking_header.php:11).
        $base64 = trim((string) stream_get_contents(STDIN));

        if ($base64 === '') {
            setting(['company_logo' => '']);
        } elseif (preg_match('#^image/(png|jpeg|webp)$#', $logo_mime)) {
            setting(['company_logo' => 'data:' . $logo_mime . ';base64,' . $base64]);
        }

        response('ok' . PHP_EOL);
    }

    /**
     * Send a test email through THIS tenant's effective configuration — the transport from the merged
     * production email config, and the From/Reply-To from EA's own fallback chain — so the result proves
     * exactly what a customer would receive.
     *
     * Mirrors Email_messages::get_php_mailer() (private, upstream-tracked, so it cannot be reused or
     * edited). Prints ONE JSON line; never prints the SMTP password, and scrubs it from any error.
     */
    public function test_email(): void
    {
        $recipient = trim((string) getenv('EA_TEST_EMAIL_TO'));

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            response(json_encode(['ok' => false, 'error' => 'invalid recipient']) . PHP_EOL);

            return;
        }

        $password = (string) config('smtp_pass');

        try {
            $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->CharSet = 'UTF-8';

            if (config('protocol') === 'smtp') {
                $mailer->isSMTP();
                $mailer->Host = config('smtp_host');
                $mailer->SMTPAuth = config('smtp_auth');
                $mailer->Username = config('smtp_user');
                $mailer->Password = $password;
                $mailer->SMTPSecure = config('smtp_crypto');
                $mailer->Port = config('smtp_port');
            }

            $from_address = config('from_address') ?: setting('company_email');
            $from_name = config('from_name') ?: setting('company_name');

            $mailer->setFrom($from_address, $from_name);
            $mailer->addReplyTo(config('reply_to') ?: setting('company_email'));
            $mailer->addAddress($recipient);
            $mailer->Subject = 'Test email from ' . ($from_name ?: 'your booking page');
            $mailer->isHTML();
            $mailer->Body =
                '<p>This is a test message from your online booking page. ' .
                'If you can read it, email delivery is working.</p>';
            $mailer->AltBody =
                'This is a test message from your online booking page. ' .
                'If you can read it, email delivery is working.';
            $mailer->send();

            response(
                json_encode([
                    'ok' => true,
                    'from' => $from_address,
                    'protocol' => (string) config('protocol'),
                ]) . PHP_EOL,
            );
        } catch (Throwable $exception) {
            $message = $exception->getMessage();

            if ($password !== '') {
                $message = str_replace($password, '***', $message);
            }

            response(json_encode(['ok' => false, 'error' => substr($message, 0, 300)]) . PHP_EOL);
        }
    }

    /**
     * A neutral host for the seeded placeholder provider email (never example.org). Uses the tenant slug.
     */
    /**
     * Give the business's OWNER an administrator account here, with the same email and the same
     * password as the login the platform issued them. One credential for the person, not one per tool.
     *
     * A SECOND admin, alongside the platform's own `administrator`. Rewriting the existing admin's email
     * instead — which set_admin_password() can do, and which reset_password() deliberately does not —
     * would quietly lock the platform out of the tenant it provisioned.
     *
     * The owner's email is also their USERNAME here: EA's login accepts `@` and `.` (Login.php), so
     * there is no second name to remember and no way for the two to drift apart.
     *
     * The payload arrives as JSON in the ENVIRONMENT, not argv: it carries a password, and argv is
     * world-readable through /proc/<pid>/cmdline while the environment of a process is not.
     */
    public function set_owner(): void
    {
        $data = json_decode((string) getenv('EA_OWNER_JSON'), true);

        if (!is_array($data) || empty($data['email'])) {
            fwrite(STDERR, 'an owner email is required' . PHP_EOL);
            exit(1);
        }

        $email = trim((string) $data['email']);
        $password = (string) ($data['password'] ?? '');
        $name = trim((string) ($data['name'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            fwrite(STDERR, 'invalid owner email' . PHP_EOL);
            exit(1);
        }

        // EA validates first_name and last_name as required, and a one-word name is the common case.
        $parts = preg_split('/\s+/', $name !== '' ? $name : explode('@', $email)[0], 2);
        $first = $parts[0] !== '' ? $parts[0] : 'Business';
        $last = $parts[1] ?? 'Owner';

        $existing = $this->admins_model->get(['email' => $email], 1);

        if (!empty($existing)) {
            $admin = $existing[0];
            $admin['first_name'] = $first;
            $admin['last_name'] = $last;

            // An empty password means "keep the one they already have" — the platform sends one only
            // when it has just minted it.
            if ($password !== '') {
                $admin['settings']['password'] = $password;
            } else {
                unset($admin['settings']['password']);
            }

            $this->admins_model->save($admin);
            response('ok' . PHP_EOL);

            return;
        }

        if ($password === '') {
            fwrite(STDERR, 'a password is required to create an owner' . PHP_EOL);
            exit(1);
        }

        $this->admins_model->save([
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'settings' => [
                'username' => $email,
                'password' => $password,
                'notifications' => false,
                'calendar_view' => CALENDAR_VIEW_DEFAULT,
            ],
        ]);

        response('ok' . PHP_EOL);
    }

    /**
     * Withdraw an owner's administrator account. This is what an expiry MEANS: without it, the day after
     * a demo login expires its holder can still open this admin panel, because a business that is not
     * private has no gate in front of it.
     *
     * Refuses to delete the last admin standing. An empty admins table is a tenant nobody can administer
     * ever again, and no expiry is worth that.
     */
    public function remove_owner(): void
    {
        $data = json_decode((string) getenv('EA_OWNER_JSON'), true);

        if (!is_array($data) || empty($data['email'])) {
            fwrite(STDERR, 'an owner email is required' . PHP_EOL);
            exit(1);
        }

        $email = trim((string) $data['email']);
        $matches = $this->admins_model->get(['email' => $email], 1);

        // Already gone. Say so and succeed: the caller is asking for a state, not an event, and a
        // reconciler that retries must not fail for ever on a job it has already finished.
        if (empty($matches)) {
            response('ok' . PHP_EOL);

            return;
        }

        if (count($this->admins_model->get()) < 2) {
            fwrite(STDERR, 'refusing to delete the only admin' . PHP_EOL);
            exit(1);
        }

        $this->admins_model->delete((int) $matches[0]['id']);

        response('ok' . PHP_EOL);
    }

    private function tenant_host(): string
    {
        $slug = (string) getenv('EA_TENANT');

        return ($slug !== '' ? $slug : 'business') . '.local';
    }

    /**
     * Load the (single) admin and save it back with a new password (and email when provided).
     * Admins_model::save() validates first_name/last_name/email even on update, so the whole record must
     * be passed; Admins_model::update() re-derives the salt from the DB, so only the plaintext password
     * is needed (never a pre-hashed value).
     */
    private function set_admin_password(string $password, ?string $email): void
    {
        $admins = $this->admins_model->get(null, 1);

        if (empty($admins)) {
            exit('no admin found' . PHP_EOL);
        }

        $admin = $admins[0];
        $admin['settings']['password'] = $password;

        if ($email !== null) {
            $admin['email'] = $email;
        }

        $this->admins_model->save($admin);
    }
}
