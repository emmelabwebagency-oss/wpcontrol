<?php
/**
 * WP Control Center - API: Heartbeat.
 * POST /api/heartbeat.php
 * Riceve il battito cardiaco dai siti WordPress.
 */

// Cattura qualsiasi output spurio (warning PHP, BOM, etc.) per non corrompere il JSON.
ob_start();

define( 'WPC_ROOT', dirname( __DIR__ ) );
require_once WPC_ROOT . '/includes/bootstrap.php';

// Pulisci qualsiasi output generato durante il bootstrap.
ob_end_clean();

header( 'Content-Type: application/json; charset=utf-8' );

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    echo json_encode( [ 'error' => 'Metodo non consentito.' ] );
    exit;
}

// Leggi il body UNA SOLA VOLTA prima di qualsiasi altra operazione.
// Su alcuni hosting (CGI/FastCGI) php://input puo' essere letto solo una volta.
$raw_body = file_get_contents( 'php://input' );

// Autenticazione HMAC (usa il body gia' letto).
$headers = WPC_HmacAuth::extract_headers();

if ( ! $headers ) {
    http_response_code( 401 );
    echo json_encode( [ 'error' => 'Header HMAC mancanti.' ] );
    exit;
}

$auth_result = WPC_HmacAuth::verify_request(
    $headers['site_id'],
    $headers['signature'],
    $headers['timestamp'],
    $headers['nonce'],
    $raw_body
);

if ( ! $auth_result['valid'] ) {
    http_response_code( 403 );
    echo json_encode( [ 'error' => $auth_result['message'] ] );
    exit;
}

$site = $auth_result['site'];

// Decodifica il body JSON.
$data = json_decode( $raw_body, true );

if ( ! $data ) {
    $data = $_POST;
}

// Aggiorna i dati del sito (NON sovrascrivere is_locked - quello e' gestito solo dal pannello).
$update_data = [
    'wp_version'   => $data['wp_version'] ?? null,
    'php_version'  => $data['php_version'] ?? null,
    'active_theme' => $data['active_theme'] ?? null,
    'plugin_count' => $data['plugin_count'] ?? null,
];

WPC_Sites::update_heartbeat( $site['site_id'], $update_data );

// Ricarica i dati del sito dal DB per restituire lo stato aggiornato (es. lock/unlock dal pannello).
$current_site = WPC_Sites::find_by_site_id( $site['site_id'] );

$response_data = [
    'success'    => true,
    'message'    => 'Heartbeat ricevuto.',
    'timestamp'  => time(),
    'site_id'    => $site['site_id'],
    'commands'   => [
        'lock' => (bool) ( $current_site['is_locked'] ?? false ),
    ],
];

// Include il codice di disinstallazione se presente.
if ( ! empty( $current_site['uninstall_code'] ) ) {
    $response_data['uninstall_code'] = $current_site['uninstall_code'];
}

echo json_encode( $response_data );
