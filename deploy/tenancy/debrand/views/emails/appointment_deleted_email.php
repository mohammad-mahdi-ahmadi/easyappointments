<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * De-branded override of application/views/emails/appointment_deleted_email.php (ADDITIVE).
 *
 * Found by CI's loader because the debrand hook prepends this package path
 * (deploy/tenancy/debrand_hook.php). It does NOT copy upstream's markup — it renders upstream's own
 * template (which inherits this scope, so it sees the same view variables) and passes the result through
 * the single de-branding filter, so an upstream change to the template still reaches the customer.
 */
require_once FCPATH . 'deploy/tenancy/debrand.php';

ob_start();
include APPPATH . 'views/emails/appointment_deleted_email.php';

echo ea_debrand_html((string) ob_get_clean());
