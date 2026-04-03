<?php
/**
 * WP Control Center - API: Tamper Alert.
 * POST /api/tamper.php
 * Riceve alert di manomissione dai siti WordPress.
 */

define( 'WPC_ROOT', dirname( __DIR__ ) );
require_once WPC_ROOT . '/includes/bootstrap.php';

header( 'Content-Type: application/json; charset=utf-8' );

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    echo json_encode( [ 'error' => 'Metodo non consentito.' ] );
    exit;
}

// Autenticazione HMAC.
$site = WPC_HmacAuth::require_site_auth();

// Leggi il body della richiesta.
$body = file_get_contents( 'php://input' );
$data = json_decode( $body, true );

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
