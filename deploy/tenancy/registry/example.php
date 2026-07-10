<?php
/**
 * Tenant registry entry — SHAPE ONLY (no real values).
 * The provision-tenant CLI writes one real `<slug>.php` per tenant (git-ignored, 0640).
 * bootstrap.php reads the matching file and turns it into the EA `class Config`.
 */
return [
    'slug'        => 'example',
    'db_host'     => 'db',
    'db_name'     => 'ea_example',
    'db_username' => 'ea_example',
    'db_password' => '__CHANGE_ME__',
    'status'      => 'active', // active | suspended
];
