<?php
/**
 * Gestore dell'attivazione del plugin.
 *
 * @package WPControl\Core
 */

namespace WPControl\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Activator {

    /**
     * Eseguito all'attivazione del plugin.
     * Crea le tabelle personalizzate e imposta le opzioni iniziali.
     */
    public static function activate(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Tabella log di audit.
        $table_audit = $wpdb->prefix . 'wpc_audit_log';
        $sql_audit = "CREATE TABLE IF NOT EXISTS {$table_audit} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type VARCHAR(100) NOT NULL,
            event_description TEXT NOT NULL,
            actor VARCHAR(255) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            metadata LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_event_type (event_type),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        // Tabella metadati backup.
        $table_backups = $wpdb->prefix . 'wpc_backups';
        $sql_backups = "CREATE TABLE IF NOT EXISTS {$table_backups} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            backup_id VARCHAR(64) NOT NULL,
            site_id VARCHAR(64) NOT NULL,
            file_path TEXT DEFAULT NULL,
            remote_url TEXT DEFAULT NULL,
            file_size BIGINT UNSIGNED DEFAULT 0,
            checksum VARCHAR(128) DEFAULT NULL,
            wp_version VARCHAR(20) DEFAULT NULL,
            php_version VARCHAR(20) DEFAULT NULL,
            active_theme VARCHAR(255) DEFAULT NULL,
            plugin_list LONGTEXT DEFAULT NULL,
            encrypted TINYINT(1) DEFAULT 1,
            status VARCHAR(50) DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_backup_id (backup_id),
            KEY idx_site_id (site_id),
            KEY idx_status (status)
        ) {$charset_collate};";

        // Tabella alert di manomissione.
        $table_tamper = $wpdb->prefix . 'wpc_tamper_alerts';
        $sql_tamper = "CREATE TABLE IF NOT EXISTS {$table_tamper} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            alert_type VARCHAR(100) NOT NULL,
            severity VARCHAR(20) DEFAULT 'medium',
            description TEXT NOT NULL,
            details LONGTEXT DEFAULT NULL,
            acknowledged TINYINT(1) DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_alert_type (alert_type),
            KEY idx_severity (severity)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_audit );
        dbDelta( $sql_backups );
        dbDelta( $sql_tamper );

        // Imposta la versione del plugin nel database.
        update_option( WPC_OPTION_PREFIX . 'version', WPC_VERSION );

        // Genera l'hash di integrità dei file del plugin.
        self::generate_file_integrity_hash();

        // Pianifica il cron per l'heartbeat.
        if ( ! wp_next_scheduled( 'wpc_heartbeat_cron' ) ) {
            wp_schedule_event( time(), 'hourly', 'wpc_heartbeat_cron' );
        }

        // Pianifica il cron per il tamper check.
        if ( ! wp_next_scheduled( 'wpc_tamper_check_cron' ) ) {
            wp_schedule_event( time(), 'twicedaily', 'wpc_tamper_check_cron' );
        }
    }

    /**
     * Genera e salva l'hash di integrità dei file del plugin.
     */
    private static function generate_file_integrity_hash(): void {
        $plugin_dir = WPC_PLUGIN_DIR;
        $hashes = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $plugin_dir, \RecursiveDirectoryIterator::SKIP_DOTS )
        );

        foreach ( $iterator as $file ) {
            if ( $file->isFile() && $file->getExtension() === 'php' ) {
                $relative_path = str_replace( $plugin_dir, '', $file->getPathname() );
                $hashes[ $relative_path ] = hash_file( 'sha256', $file->getPathname() );
            }
        }

        update_option( WPC_OPTION_PREFIX . 'file_hashes', $hashes );
    }
}
