<?php
/**
 * Gestione della disinstallazione del plugin.
 * Questo file viene eseguito solo quando il plugin viene cancellato da WordPress.
 *
 * @package LicenseTemplateKit
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Verifica che la disinstallazione sia stata autorizzata.
// Nota: se si arriva qui, il plugin è già stato disattivato (con codice valido).

global $wpdb;

// Rimuovi le opzioni del plugin.
$options = $wpdb->get_col(
    "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'ltk_%'"
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// Rimuovi i transient del plugin.
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ltk_%' OR option_name LIKE '_transient_timeout_ltk_%'"
);

// Rimuovi le tabelle personalizzate.
$tables = [
    $wpdb->prefix . 'ltk_tpl_log',
    $wpdb->prefix . 'ltk_tpl_data',
    $wpdb->prefix . 'ltk_tpl_meta',
];

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Rimuovi i cron schedulati.
wp_clear_scheduled_hook( 'ltk_sync_cron' );
wp_clear_scheduled_hook( 'ltk_check_cron' );

// Nota: i file di backup in wp-content/ltk-templates/ NON vengono rimossi
// per sicurezza. L'amministratore può rimuoverli manualmente.
