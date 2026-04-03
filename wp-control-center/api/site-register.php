<?php
/**
 * WP Control Center - API: Site Registration.
 * POST /api/site-register.php
 * Registra un nuovo sito WordPress (richiede auth HMAC o token di setup).
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

$body = file_get_contents( 'php://input' );
$data = json_decode( $body, true );

if ( ! $data ) {
    $data = $_POST;
}

$site_name = $data['site_name'] ?? '';
$site_url  = $data['site_url'] ?? '';
$api_token = $data['api_token'] ?? '';

if ( empty( $site_name ) || empty( $site_url ) || empty( $api_token ) ) {
    http_response_code( 400 );
    echo json_encode( [ 'error' => 'Campi obbligatori: site_name, site_url, api_token.' ] );
    exit;
}

// Verifica che il sito non sia gia' registrato.
$db = WPC_Database::get_instance();
$existing = $db->fetch_one( "SELECT id FROM sites WHERE site_url = ? LIMIT 1", [ $site_url ] );

if ( $existing ) {
    http_response_code( 409 );
    echo json_encode( [ 'error' => 'Sito gia\' registrato.' ] );
    exit;
}

$result = WPC_Sites::register( $site_name, $site_url, $api_token );

echo json_encode( [
    'success' => true,
    'message' => 'Sito registrato con successo.',
    'site_id' => $result['site_id'],
] );
