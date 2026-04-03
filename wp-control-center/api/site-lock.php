<?php
/**
 * WP Control Center - API: Lock/Unlock Sito.
 * POST /api/site-lock.php?action=lock|unlock&site_id=xxx
 */

ob_start();
define( 'WPC_ROOT', dirname( __DIR__ ) );
require_once WPC_ROOT . '/includes/bootstrap.php';
ob_end_clean();

header( 'Content-Type: application/json; charset=utf-8' );

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    echo json_encode( [ 'error' => 'Metodo non consentito.' ] );
    exit;
}

// Leggi il body una sola volta.
$raw_body = file_get_contents( 'php://input' );

// Autenticazione HMAC.
$headers = WPC_HmacAuth::extract_headers();
if ( ! $headers ) {
    http_response_code( 401 );
    echo json_encode( [ 'error' => 'Header HMAC mancanti.' ] );
    exit;
}
$auth_result = WPC_HmacAuth::verify_request( $headers['site_id'], $headers['signature'], $headers['timestamp'], $headers['nonce'], $raw_body );
if ( ! $auth_result['valid'] ) {
    http_response_code( 403 );
    echo json_encode( [ 'error' => $auth_result['message'] ] );
    exit;
}
$site = $auth_result['site'];

$data = json_decode( $raw_body, true ) ?: $_POST;

$lock_action = $data['action'] ?? ( $_GET['action'] ?? '' );
$site_id     = $site['site_id'];

if ( ! in_array( $lock_action, [ 'lock', 'unlock' ], true ) ) {
    http_response_code( 400 );
    echo json_encode( [ 'error' => 'Azione non valida. Usa lock o unlock.' ] );
    exit;
}

$is_lock = $lock_action === 'lock';
WPC_Sites::update_lock_status( $site_id, $is_lock );

$status_msg = $is_lock ? 'bloccato' : 'sbloccato';
WPC_Audit::log( "site_{$lock_action}", "Sito {$site_id} {$status_msg} tramite API", null, null, $site_id );

echo json_encode( [
    'success' => true,
    'message' => "Sito {$status_msg} con successo.",
    'site_id' => $site_id,
    'locked'  => $is_lock,
] );
