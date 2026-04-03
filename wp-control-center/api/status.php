<?php
/**
 * WP Control Center - API: Status.
 * GET /api/status.php
 * Endpoint pubblico per verificare che il pannello sia online.
 */

ob_start();
define( 'WPC_ROOT', dirname( __DIR__ ) );
require_once WPC_ROOT . '/config/config.php';
ob_end_clean();

header( 'Content-Type: application/json; charset=utf-8' );

echo json_encode( [
    'status'  => 'online',
    'app'     => WPC_APP_NAME,
    'version' => WPC_APP_VERSION,
    'time'    => time(),
] );
