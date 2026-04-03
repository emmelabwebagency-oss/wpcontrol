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

echo json_encode( [
    'success'   => true,
    'message'   => 'Heartbeat ricevuto.',
    'timestamp' => time(),
    'site_id'   => $site['site_id'],
] );
