<?php
/**
 * WP Control Center - API: Backup Upload.
 * POST /api/backup-upload.php
 * Riceve file di backup dai siti WordPress.
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

// Verifica che ci sia un file.
if ( empty( $_FILES['backup'] ) || $_FILES['backup']['error'] !== UPLOAD_ERR_OK ) {
    http_response_code( 400 );
    echo json_encode( [ 'error' => 'File di backup mancante o non valido.' ] );
    exit;
}

$file = $_FILES['backup'];
$backup_id = WPC_Database::generate_uuid();

// Crea la directory di backup se non esiste.
$backup_dir = WPC_BACKUP_DIR . $site['site_id'] . '/';
if ( ! is_dir( $backup_dir ) ) {
    mkdir( $backup_dir, 0750, true );
}

// Salva il file.
$extension = pathinfo( $file['name'], PATHINFO_EXTENSION ) ?: 'tar.gz';
$file_name = $backup_id . '.' . $extension;
$file_path = $backup_dir . $file_name;

if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
    http_response_code( 500 );
    echo json_encode( [ 'error' => 'Errore nel salvataggio del file.' ] );
    exit;
}

// Calcola il checksum.
$checksum  = hash_file( 'sha256', $file_path );
$file_size = filesize( $file_path );

// Metadati dal body.
$meta = [
    'wp_version'   => $_POST['wp_version'] ?? null,
    'php_version'  => $_POST['php_version'] ?? null,
    'active_theme' => $_POST['active_theme'] ?? null,
    'plugin_list'  => isset( $_POST['plugin_list'] ) ? json_decode( $_POST['plugin_list'], true ) : null,
    'encrypted'    => isset( $_POST['encrypted'] ),
    'file_size'    => $file_size,
    'checksum'     => $checksum,
];

// Registra il backup.
$id = WPC_Backups::register( $site['site_id'], $backup_id, $meta );

// Segna come salvato.
WPC_Backups::mark_as_stored( $backup_id, $file_path, $file_size, $checksum );

WPC_Audit::log( 'backup_uploaded', "Backup caricato da {$site['site_name']}: {$backup_id}", null, null, $site['site_id'] );

echo json_encode( [
    'success'   => true,
    'message'   => 'Backup caricato con successo.',
    'backup_id' => $backup_id,
    'checksum'  => $checksum,
    'file_size' => $file_size,
] );
