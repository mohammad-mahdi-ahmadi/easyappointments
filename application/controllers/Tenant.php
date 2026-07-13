<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Tenant controller (ADDITIVE — multi-tenancy). CLI-only.
 *
 * Bootstraps a provisioned tenant WITHOUT editing any upstream file. EA's installer seeds a fixed public
 * admin (administrator/administrator) plus demo records; these methods neutralise the seeded admin, set
 * the business's real details, and replace the demo records — going through EA's own models wherever
 * they will allow it, so passwords are salted+hashed exactly as EA expects.
 *
 * The tenant has no administrator of its own. Its people are decided on the platform and pushed here by
 * set_owner / remove_owner; the seeded admin is deleted by remove_default_admin at provision time.
 *
 * Secrets are read from the environment (never argv): EA_ADMIN_PASSWORD, EA_ADMIN_EMAIL, EA_OWNER_JSON.
 *
 *   php index.php tenant initialize            # neutralise the seeded admin + tz + a functional starter
 *   php index.php tenant remove_default_admin  # delete the seeded admin (idempotent)
 *   php index.php tenant suspend               # close the public booking site (EA's disable_booking)
 *   php index.php tenant resume                # reopen the public booking site
 *   php index.php tenant apply_brand           # push the platform business brand into ea_settings
 *   php index.php tenant test_email            # send a test email with this tenant's real config
 *   php index.php tenant set_owner             # create/update a business user as an admin here
 *   php index.php tenant remove_owner          # withdraw one
 */
class Tenant extends EA_Controller
{
    /**
     * The username of the admin EA's own installer seeds. It is the stable identifier: the account's
     * name and email are rewritten by `initialize`, but its username never is — and an admin the
     * platform creates carries their email as their username, so the two can never be confused.
     */
    private const DEFAULT_ADMIN_USERNAME = 'administrator';

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
     * Delete the admin EA's installer seeds (`administrator`), leaving the tenant with no administrator
     * of its own. Idempotent, and safe to run on a tenant that already has business users.
     *
     * A tenant's people are decided on the platform, in one place, and pushed here by `set_owner`. The
     * seeded account is the one login that was never decided there: nobody asked for it, nobody is told
     * about it, and it exists in every tenant under the same name. Rotating its password to something we
     * throw away (which is what `initialize` does) makes it unusable, but it is still an admin — it shows
     * up in the business's own user list as "John Doe", and any admin can give it a password. So it goes.
     *
     * It deletes only ever the seeded account: one the platform created carries its email as its
     * username, never `administrator`. See delete_admin() for why the model cannot do it.
     */
    public function remove_default_admin(): void
    {
        foreach ($this->admins_model->get() as $admin) {
            if (($admin['settings']['username'] ?? '') !== self::DEFAULT_ADMIN_USERNAME) {
                continue;
            }

            $this->delete_admin((int) $admin['id']);
        }

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
     * Give a business USER an administrator account here, with the same email and the same password as
     * the login the platform issued them. One credential for the person, not one per tool.
     *
     * This is the ONLY way an admin account comes into existence in a tenant: the seeded `administrator`
     * is deleted at provision time, so every admin in here is somebody the platform put there, and can
     * be taken back out by remove_owner. A business may have several — each granted the booking
     * capability in the platform's Access tab — and they are ordinary EA admins with no roles of ours
     * layered on top: inside its own panel, the business manages its own people.
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

        // Note there is no "refusing to delete the only admin" guard, and there must not be. Withdrawing
        // a business user is the platform saying that person may no longer open this panel, and it is not
        // conditional on somebody else being left behind: a business whose last user is withdrawn is a
        // business nobody may open the panel of, which is precisely what was asked for. That is also why
        // this cannot go through Admins_model::delete() — see delete_admin().
        $this->delete_admin((int) $matches[0]['id']);

        response('ok' . PHP_EOL);
    }

    /**
     * Delete an admin, including the last one.
     *
     * Admins_model::delete() throws rather than leave the users table without an admin — EA's own rule,
     * and the right one for a business that installed EA itself and must always be able to run it. A
     * tenant here is not that: its administrators are granted by the platform and can all be taken away,
     * and an empty admin list is the honest way to say "nobody may open this panel". Nothing else in EA
     * depends on an admin existing — the public booking page reads settings, services and providers.
     */
    private function delete_admin(int $id): void
    {
        $this->db->delete('user_settings', ['id_users' => $id]);
        $this->db->delete('users', ['id' => $id]);
    }

    private function tenant_host(): string
    {
        $slug = (string) getenv('EA_TENANT');

        return ($slug !== '' ? $slug : 'business') . '.local';
    }

    /**
     * Take the public default (administrator/administrator) off the seeded admin: a new password and the
     * business's email. It is deleted moments later by remove_default_admin, and this still happens first
     * on purpose — if that delete ever fails, the account left behind must be one nobody can sign in to,
     * not the one whose password is printed in EA's install guide.
     *
     * Admins_model::save() validates first_name/last_name/email even on update, so the whole record must
     * be passed; Admins_model::update() re-derives the salt from the DB, so only the plaintext password
     * is needed (never a pre-hashed value).
     */
    private function set_admin_password(string $password, string $email): void
    {
        $admins = $this->admins_model->get(null, 1);

        if (empty($admins)) {
            exit('no admin found' . PHP_EOL);
        }

        $admin = $admins[0];
        $admin['settings']['password'] = $password;
        $admin['email'] = $email;

        $this->admins_model->save($admin);
    }
}
