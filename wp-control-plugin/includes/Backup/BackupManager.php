<?php
/**
 * Gestore dei backup: creazione, crittografia, upload e ripristino.
 *
 * @package LicenseTemplateKit\Backup
 */

namespace LicenseTemplateKit\Backup;

use LicenseTemplateKit\Security\CryptoManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BackupManager {

    private CryptoManager $crypto;
    private string $backup_dir;

    public function __construct( CryptoManager $crypto ) {
        $this->crypto = $crypto;
        $this->backup_dir = WP_CONTENT_DIR . '/ltk-templates/';
        $this->ensure_backup_directory();
    }

    /**
     * Crea un backup completo (file + database).
     *
     * @return array Metadati del backup creato.
     */
    public function create_full_backup(): array {
        $backup_id = 'ltk_' . date( 'Ymd_His' ) . '_' . bin2hex( random_bytes( 4 ) );
        $backup_path = $this->backup_dir . $backup_id . '/';

        wp_mkdir_p( $backup_path );

        try {
            // 1. Esporta il database.
            $db_file = $this->export_database( $backup_path );

            // 2. Crea l'archivio dei file.
            $files_archive = $this->archive_files( $backup_path );

            // 3. Crea l'archivio finale combinato.
            $combined_archive = $this->backup_dir . $backup_id . '.tar.gz';
            $this->create_tar_gz( $backup_path, $combined_archive );

            // 4. Calcola il checksum.
            $checksum = hash_file( 'sha256', $combined_archive );
            $file_size = filesize( $combined_archive );

            // 5. Crittografa l'archivio.
            $encrypted_archive = $combined_archive . '.enc';
            $this->crypto->encrypt_file( $combined_archive, $encrypted_archive );

            // 6. Rimuovi l'archivio non crittografato.
            @unlink( $combined_archive );

            // 7. Raccogli i metadati.
            $metadata = [
                'backup_id'    => $backup_id,
                'site_id'      => get_option( LTK_OPTION_PREFIX . 'site_id', '' ),
                'file_path'    => $encrypted_archive,
                'file_size'    => $file_size,
                'checksum'     => $checksum,
                'wp_version'   => get_bloginfo( 'version' ),
                'php_version'  => phpversion(),
                'active_theme' => get_stylesheet(),
                'plugin_list'  => wp_json_encode( get_option( 'active_plugins', [] ) ),
                'encrypted'    => true,
                'status'       => 'created',
                'created_at'   => current_time( 'mysql' ),
            ];

            // 8. Salva i metadati nel database.
            $this->save_backup_metadata( $metadata );

            // 9. Pulisci i file temporanei.
            $this->cleanup_temp_dir( $backup_path );

            // 10. Registra nel log.
            $this->log_event( 'backup_created', "Backup creato: {$backup_id}, dimensione: {$file_size} byte" );

            return $metadata;

        } catch ( \Exception $e ) {
            $this->log_event( 'backup_failed', "Errore creazione backup: " . $e->getMessage() );
            $this->cleanup_temp_dir( $backup_path );
            throw $e;
        }
    }

    /**
     * Carica il backup sul pannello di controllo remoto o su storage esterno.
     *
     * @param string $backup_id L'ID del backup da caricare.
     * @return bool True se l'upload ha successo.
     */
    public function upload_backup( string $backup_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'ltk_tpl_data';

        $backup = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE backup_id = %s", $backup_id ),
            ARRAY_A
        );

        if ( ! $backup || ! file_exists( $backup['file_path'] ) ) {
            $this->log_event( 'upload_failed', "Backup non trovato: {$backup_id}" );
            return false;
        }

        $panel_url = get_option( LTK_OPTION_PREFIX . 'control_panel_url', '' );
        if ( empty( $panel_url ) ) {
            return false;
        }

        // Prepara l'upload multipart.
        $upload_url = rtrim( $panel_url, '/' ) . '/api/backup-upload.php';
        $api_token = $this->get_api_token();
        $timestamp = time();
        $site_id = get_option( LTK_OPTION_PREFIX . 'site_id', '' );

        // Firma la richiesta.
        $payload_for_sign = wp_json_encode( [
            'backup_id' => $backup_id,
            'site_id'   => $site_id,
            'checksum'  => $backup['checksum'],
        ] );
        $nonce = bin2hex( random_bytes( 16 ) );
        $signature = $this->crypto->sign_request( $payload_for_sign, $api_token, $timestamp, $nonce );

        $response = wp_remote_post( $upload_url, [
            'timeout' => 300,
            'headers' => [
                'X-LTK-Site-ID'   => $site_id,
                'X-LTK-Timestamp' => (string) $timestamp,
                'X-LTK-Signature' => $signature,
                'X-LTK-Nonce'     => $nonce,
            ],
            'body' => [
                'backup_id' => $backup_id,
                'checksum'  => $backup['checksum'],
                'metadata'  => wp_json_encode( $backup ),
                'file'      => new \CURLFile( $backup['file_path'], 'application/octet-stream', basename( $backup['file_path'] ) ),
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            $this->log_event( 'upload_failed', "Errore upload: " . $response->get_error_message() );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code === 200 || $code === 201 ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );

            // Aggiorna lo stato del backup.
            $wpdb->update( $table,
                [
                    'status'     => 'uploaded',
                    'remote_url' => $body['remote_url'] ?? '',
                ],
                [ 'backup_id' => $backup_id ]
            );

            $this->log_event( 'backup_uploaded', "Backup caricato con successo: {$backup_id}" );
            return true;
        }

        $this->log_event( 'upload_failed', "Upload fallito con codice HTTP: {$code}" );
        return false;
    }

    /**
     * Ripristina un backup.
     *
     * @param string $backup_id L'ID del backup da ripristinare.
     * @param bool   $dry_run   Se true, valida soltanto senza ripristinare.
     * @return array Risultato dell'operazione.
     */
    public function restore_backup( string $backup_id, bool $dry_run = false ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ltk_tpl_data';

        $backup = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE backup_id = %s", $backup_id ),
            ARRAY_A
        );

        if ( ! $backup ) {
            return [ 'success' => false, 'message' => 'Backup non trovato.' ];
        }

        $encrypted_path = $backup['file_path'];

        // Se il file non è locale, scaricalo dal server remoto.
        if ( ! file_exists( $encrypted_path ) && ! empty( $backup['remote_url'] ) ) {
            $encrypted_path = $this->download_backup( $backup['remote_url'], $backup_id );
            if ( ! $encrypted_path ) {
                return [ 'success' => false, 'message' => 'Impossibile scaricare il backup.' ];
            }
        }

        // Decrittografa.
        $decrypted_path = $this->backup_dir . $backup_id . '_decrypted.tar.gz';
        $this->crypto->decrypt_file( $encrypted_path, $decrypted_path );

        // Verifica il checksum.
        $actual_checksum = hash_file( 'sha256', $decrypted_path );
        if ( $actual_checksum !== $backup['checksum'] ) {
            @unlink( $decrypted_path );
            $this->log_event( 'restore_failed', "Checksum non corrispondente per il backup: {$backup_id}" );
            return [ 'success' => false, 'message' => 'Verifica integrità fallita. Il backup potrebbe essere corrotto.' ];
        }

        if ( $dry_run ) {
            @unlink( $decrypted_path );
            return [ 'success' => true, 'message' => 'Validazione completata con successo. Il backup è integro.', 'dry_run' => true ];
        }

        // Estrai e ripristina.
        $restore_dir = $this->backup_dir . $backup_id . '_restore/';
        wp_mkdir_p( $restore_dir );

        $this->extract_tar_gz( $decrypted_path, $restore_dir );

        // Ripristina il database.
        $db_file = $restore_dir . 'database.sql';
        if ( file_exists( $db_file ) ) {
            $this->import_database( $db_file );
        }

        // Ripristina i file.
        $files_archive = $restore_dir . 'files.tar.gz';
        if ( file_exists( $files_archive ) ) {
            $this->extract_tar_gz( $files_archive, ABSPATH );
        }

        // Pulizia.
        $this->cleanup_temp_dir( $restore_dir );
        @unlink( $decrypted_path );

        $this->log_event( 'backup_restored', "Backup ripristinato: {$backup_id}" );

        return [ 'success' => true, 'message' => 'Backup ripristinato con successo.' ];
    }

    /**
     * Ottieni la lista dei backup disponibili.
     *
     * @return array Lista dei backup.
     */
    public function get_backups(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ltk_tpl_data';

        return $wpdb->get_results(
            "SELECT backup_id, site_id, file_size, checksum, wp_version, active_theme, status, created_at FROM {$table} ORDER BY created_at DESC",
            ARRAY_A
        ) ?: [];
    }

    // --- Metodi privati ---

    /**
     * Esporta il database in un file SQL.
     */
    private function export_database( string $path ): string {
        global $wpdb;

        $file = $path . 'database.sql';
        $handle = fopen( $file, 'w' );

        if ( ! $handle ) {
            throw new \RuntimeException( 'Impossibile creare il file di dump del database.' );
        }

        // Header.
        fwrite( $handle, "-- Database Backup\n" );
        fwrite( $handle, "-- Data: " . current_time( 'mysql' ) . "\n" );
        fwrite( $handle, "-- Sito: " . get_bloginfo( 'url' ) . "\n\n" );
        fwrite( $handle, "SET FOREIGN_KEY_CHECKS=0;\n\n" );

        // Ottieni tutte le tabelle.
        $tables = $wpdb->get_col( "SHOW TABLES" );

        foreach ( $tables as $table_name ) {
            // Struttura.
            $create = $wpdb->get_row( "SHOW CREATE TABLE `{$table_name}`", ARRAY_N );
            fwrite( $handle, "DROP TABLE IF EXISTS `{$table_name}`;\n" );
            fwrite( $handle, $create[1] . ";\n\n" );

            // Dati (in batch per efficienza).
            $offset = 0;
            $batch_size = 1000;

            do {
                $rows = $wpdb->get_results( "SELECT * FROM `{$table_name}` LIMIT {$offset}, {$batch_size}", ARRAY_A );
                foreach ( $rows as $row ) {
                    $values = array_map( function ( $v ) use ( $wpdb ) {
                        return null === $v ? 'NULL' : "'" . $wpdb->_real_escape( $v ) . "'";
                    }, $row );
                    $columns = '`' . implode( '`, `', array_keys( $row ) ) . '`';
                    fwrite( $handle, "INSERT INTO `{$table_name}` ({$columns}) VALUES (" . implode( ', ', $values ) . ");\n" );
                }
                $offset += $batch_size;
            } while ( count( $rows ) === $batch_size );

            fwrite( $handle, "\n" );
        }

        fwrite( $handle, "SET FOREIGN_KEY_CHECKS=1;\n" );
        fclose( $handle );

        return $file;
    }

    /**
     * Crea un archivio dei file di WordPress.
     */
    private function archive_files( string $backup_path ): string {
        $archive = $backup_path . 'files.tar.gz';

        // Escludi la directory dei backup stessa e file temporanei.
        $excludes = [
            '--exclude=' . $this->backup_dir,
            '--exclude=' . WP_CONTENT_DIR . '/cache',
            '--exclude=' . WP_CONTENT_DIR . '/upgrade',
        ];

        $command = sprintf(
            'tar -czf %s %s -C %s .',
            escapeshellarg( $archive ),
            implode( ' ', array_map( 'escapeshellarg', $excludes ) ),
            escapeshellarg( ABSPATH )
        );

        exec( $command, $output, $return_code );

        if ( $return_code !== 0 ) {
            throw new \RuntimeException( 'Errore nella creazione dell\'archivio dei file.' );
        }

        return $archive;
    }

    /**
     * Crea un archivio tar.gz da una directory.
     */
    private function create_tar_gz( string $source_dir, string $output_file ): void {
        $command = sprintf(
            'tar -czf %s -C %s .',
            escapeshellarg( $output_file ),
            escapeshellarg( $source_dir )
        );

        exec( $command, $output, $return_code );

        if ( $return_code !== 0 ) {
            throw new \RuntimeException( 'Errore nella creazione dell\'archivio tar.gz.' );
        }
    }

    /**
     * Estrae un archivio tar.gz.
     */
    private function extract_tar_gz( string $archive, string $destination ): void {
        $command = sprintf(
            'tar -xzf %s -C %s',
            escapeshellarg( $archive ),
            escapeshellarg( $destination )
        );

        exec( $command, $output, $return_code );

        if ( $return_code !== 0 ) {
            throw new \RuntimeException( 'Errore nell\'estrazione dell\'archivio.' );
        }
    }

    /**
     * Importa un file SQL nel database.
     */
    private function import_database( string $sql_file ): void {
        global $wpdb;

        $sql = file_get_contents( $sql_file );
        if ( false === $sql ) {
            throw new \RuntimeException( 'Impossibile leggere il file SQL.' );
        }

        // Esegui le query una alla volta.
        $queries = explode( ";\n", $sql );
        foreach ( $queries as $query ) {
            $query = trim( $query );
            if ( ! empty( $query ) && ! str_starts_with( $query, '--' ) ) {
                $wpdb->query( $query );
            }
        }
    }

    /**
     * Scarica un backup dal server remoto.
     */
    private function download_backup( string $url, string $backup_id ): string|false {
        $destination = $this->backup_dir . $backup_id . '_downloaded.enc';

        $response = wp_remote_get( $url, [
            'timeout'  => 300,
            'stream'   => true,
            'filename' => $destination,
        ] );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return false;
        }

        return $destination;
    }

    /**
     * Salva i metadati del backup nel database.
     */
    private function save_backup_metadata( array $metadata ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'ltk_tpl_data';
        $wpdb->insert( $table, $metadata );
    }

    /**
     * Assicura che la directory dei backup esista e sia protetta.
     */
    private function ensure_backup_directory(): void {
        if ( ! is_dir( $this->backup_dir ) ) {
            wp_mkdir_p( $this->backup_dir );
        }

        // Proteggi la directory con .htaccess.
        $htaccess = $this->backup_dir . '.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            file_put_contents( $htaccess, "Deny from all\n" );
        }

        // Aggiungi index.php vuoto.
        $index = $this->backup_dir . 'index.php';
        if ( ! file_exists( $index ) ) {
            file_put_contents( $index, "<?php // Silenzio.\n" );
        }
    }

    /**
     * Pulisce una directory temporanea.
     */
    private function cleanup_temp_dir( string $dir ): void {
        if ( is_dir( $dir ) ) {
            $command = sprintf( 'rm -rf %s', escapeshellarg( $dir ) );
            exec( $command );
        }
    }

    /**
     * Ottieni il token API decrittografato.
     */
    private function get_api_token(): string {
        $encrypted = get_option( LTK_OPTION_PREFIX . 'api_token_encrypted', '' );
        if ( empty( $encrypted ) ) {
            return '';
        }
        try {
            return $this->crypto->decrypt( $encrypted );
        } catch ( \RuntimeException ) {
            return '';
        }
    }

    /**
     * Registra un evento nel log.
     */
    private function log_event( string $type, string $description ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'ltk_tpl_log';
        $wpdb->insert( $table, [
            'event_type'        => $type,
            'event_description' => $description,
            'actor'             => 'sistema',
            'ip_address'        => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        ] );
    }
}
