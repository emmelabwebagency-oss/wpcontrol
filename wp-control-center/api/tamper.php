<?php
/**
 * WP Control Center - API: Tamper Alert.
 * POST /api/tamper.php
 * Riceve alert di manomissione dai siti WordPress.
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

$data = json_decode( $raw_body, true );

if ( ! $data ) {
    $data = $_POST;
}

$event_type  = $data['event_type'] ?? 'tamper_detected';
$description = $data['description'] ?? '';
$details     = $data['details'] ?? null;
$ip_address  = $data['ip_address'] ?? ( $_SERVER['REMOTE_ADDR'] ?? null );

$alert_id = WPC_Alerts::create( $site['site_id'], $event_type, $description, $details, $ip_address );

WPC_Audit::log( 'tamper_alert_received', "Alert tamper ricevuto da {$site['site_name']}: {$event_type}", null, null, $site['site_id'] );

echo json_encode( [
    'success'  => true,
    'message'  => 'Alert registrato.',
    'alert_id' => $alert_id,
] );
