<?php
/**
 * WP Control Center - API: Heartbeat.
 * POST /api/heartbeat.php
 * Riceve il battito cardiaco dai siti WordPress.
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
    // Riprova con i dati POST.
    $data = $_POST;
}

// Aggiorna i dati del sito.
$update_data = [
    'wp_version'   => $data['wp_version'] ?? null,
    'php_version'  => $data['php_version'] ?? null,
    'active_theme' => $data['active_theme'] ?? null,
    'plugin_count' => $data['plugin_count'] ?? null,
    'is_locked'    => $data['is_locked'] ?? null,
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
