<?php
/**
 * Plain-PHP assertions for the additive email resolver (no framework, no DB).
 * Run: php deploy/tenancy/tests/email-config.test.php
 */
require_once __DIR__ . '/../email-config.php';

$dir = sys_get_temp_dir() . '/ea-email-test-' . getmypid();
@mkdir($dir, 0700, true);

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

function write_cfg(string $file, array $data): void
{
    file_put_contents($file, json_encode($data));
}

// 1) Nothing configured -> empty, so the base config (protocol='mail') stands untouched.
check('no config -> empty', ea_email_config('acme', $dir), []);

// 2) A disabled platform relay is the same as none.
write_cfg("$dir/_platform.json", ['enabled' => false, 'smtp_host' => 'relay.local', 'smtp_port' => 25]);
check('platform disabled -> empty', ea_email_config('acme', $dir), []);

// 3) Platform relay, anonymous (Mailpit): no auth, no crypto, from_address set, identity keys UNSET so
//    EA falls back to the tenant's company_name/company_email (design D5).
write_cfg("$dir/_platform.json", [
    'enabled' => true,
    'smtp_host' => 'mailpit',
    'smtp_port' => 1025,
    'smtp_secure' => 'none',
    'smtp_auth' => false,
    'smtp_user' => '',
    'smtp_pass' => '',
    'from_address' => 'no-reply@cyprusinfo.dev',
]);
$cfg = ea_email_config('acme', $dir);
check('platform protocol', $cfg['protocol'] ?? null, 'smtp');
check('platform mailtype', $cfg['mailtype'] ?? null, 'html');
check('platform host', $cfg['smtp_host'] ?? null, 'mailpit');
check('platform port', $cfg['smtp_port'] ?? null, 1025);
check('platform crypto none -> empty string', $cfg['smtp_crypto'] ?? null, '');
check('platform auth off when no user', $cfg['smtp_auth'] ?? null, false);
check('platform from_address', $cfg['from_address'] ?? null, 'no-reply@cyprusinfo.dev');
check('platform leaves from_name unset', array_key_exists('from_name', $cfg), false);
check('platform leaves reply_to unset', array_key_exists('reply_to', $cfg), false);

// 4) Platform relay with credentials -> auth on, crypto passed through.
write_cfg("$dir/_platform.json", [
    'enabled' => true,
    'smtp_host' => 'smtp.relay.io',
    'smtp_port' => 587,
    'smtp_secure' => 'tls',
    'smtp_auth' => true,
    'smtp_user' => 'apikey',
    'smtp_pass' => 'not-a-real-password',
    'from_address' => 'no-reply@cyprusinfo.dev',
]);
$cfg = ea_email_config('acme', $dir);
check('platform auth on', $cfg['smtp_auth'] ?? null, true);
check('platform crypto tls', $cfg['smtp_crypto'] ?? null, 'tls');
check('platform user', $cfg['smtp_user'] ?? null, 'apikey');

// 5) A per-business override WINS and sets NO identity key (EA falls back to the business's own
//    company_email, so the mail leaves the business's own server from its own address).
write_cfg("$dir/acme.json", [
    'smtp_host' => 'smtp.acme.com',
    'smtp_port' => 465,
    'smtp_secure' => 'ssl',
    'smtp_auth' => true,
    'smtp_user' => 'bookings@acme.com',
    'smtp_pass' => 'not-a-real-password',
]);
$cfg = ea_email_config('acme', $dir);
check('override host wins', $cfg['smtp_host'] ?? null, 'smtp.acme.com');
check('override crypto ssl', $cfg['smtp_crypto'] ?? null, 'ssl');
check('override auth on', $cfg['smtp_auth'] ?? null, true);
check('override leaves from_address unset', array_key_exists('from_address', $cfg), false);

// 6) Another business is unaffected and still uses the platform relay.
check('other tenant -> platform', ea_email_config('other', $dir)['smtp_host'] ?? null, 'smtp.relay.io');

// 7) No tenant (the platform test-send path) -> the relay, never an override.
check('no tenant -> platform', ea_email_config('', $dir)['smtp_host'] ?? null, 'smtp.relay.io');

// 8) A malformed config file must not fatal — treat it as absent.
file_put_contents("$dir/broken.json", '{not json');
check('malformed override -> platform', ea_email_config('broken', $dir)['smtp_host'] ?? null, 'smtp.relay.io');

array_map('unlink', glob("$dir/*.json"));
@rmdir($dir);

echo $failures === 0 ? "\nall passed\n" : "\n{$failures} failure(s)\n";
exit($failures === 0 ? 0 : 1);
