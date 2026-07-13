<?php
/**
 * Platform-relay test send (ADDITIVE, CLI-only). Uses the SAME resolver as
 * application/config/production/email.php, so a pass here proves the relay EA will actually send with.
 * No tenant and no database are involved — this answers the operator's "does our mailer work at all?".
 *
 * Env: EA_TEST_EMAIL_TO (required), EA_EMAIL_DIR (optional), EA_EMAIL_FROM_NAME (optional).
 * Prints ONE JSON line. The SMTP password is never printed and is scrubbed from any error.
 */
require_once __DIR__ . '/email-config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

$recipient = trim((string) getenv('EA_TEST_EMAIL_TO'));
$dir = getenv('EA_EMAIL_DIR') ?: __DIR__ . '/email';

if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'invalid recipient']) . PHP_EOL;
    exit(1);
}

$config = ea_email_config('', (string) $dir); // '' = no tenant, so never a per-business override

if (empty($config['smtp_host'])) {
    echo json_encode(['ok' => false, 'error' => 'the platform mailer is not configured']) . PHP_EOL;
    exit(1);
}

$password = (string) ($config['smtp_pass'] ?? '');
$from = (string) ($config['from_address'] ?? '');

try {
    $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
    $mailer->CharSet = 'UTF-8';
    $mailer->isSMTP();
    $mailer->Host = $config['smtp_host'];
    $mailer->Port = $config['smtp_port'];
    $mailer->SMTPAuth = $config['smtp_auth'];
    $mailer->Username = $config['smtp_user'];
    $mailer->Password = $password;
    $mailer->SMTPSecure = $config['smtp_crypto'];
    $mailer->setFrom($from, getenv('EA_EMAIL_FROM_NAME') ?: 'Business platform');
    $mailer->addAddress($recipient);
    $mailer->Subject = 'Platform mailer test';
    $mailer->isHTML();
    $mailer->Body =
        '<p>The platform mailer is working. This message went out through the configured SMTP relay.</p>';
    $mailer->AltBody = 'The platform mailer is working.';
    $mailer->send();

    echo json_encode(['ok' => true, 'from' => $from]) . PHP_EOL;
} catch (Throwable $exception) {
    $message = $exception->getMessage();

    if ($password !== '') {
        $message = str_replace($password, '***', $message);
    }

    echo json_encode(['ok' => false, 'error' => substr($message, 0, 300)]) . PHP_EOL;
    exit(1);
}
