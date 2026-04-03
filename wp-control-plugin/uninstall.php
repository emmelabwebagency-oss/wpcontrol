<?php
/**
 * Gestione della disinstallazione del plugin WP Control.
 * Questo file viene eseguito solo quando il plugin viene cancellato da WordPress.
 *
 * @package WPControl
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Verifica che la disinstallazione sia stata autorizzata.
// Nota: se si arriva qui, il plugin è già stato disattivato (con codice valido).

global $wpdb;

// Rimuovi le opzioni del plugin.
$options = $wpdb->get_col(
    "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'wpc_%'"
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// Rimuovi i transient del plugin.
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpc_%' OR option_name LIKE '_transient_timeout_wpc_%'"
);

// Rimuovi le tabelle personalizzate.
$tables = [
    $wpdb->prefix . 'wpc_audit_log',
    $wpdb->prefix . 'wpc_backups',
    $wpdb->prefix . 'wpc_tamper_alerts',
];

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Rimuovi i cron schedulati.
wp_clear_scheduled_hook( 'wpc_heartbeat_cron' );
wp_clear_scheduled_hook( 'wpc_tamper_check_cron' );

// Nota: i file di backup in wp-content/wpc-backups/ NON vengono rimossi
// per sicurezza. L'amministratore può rimuoverli manualmente.
