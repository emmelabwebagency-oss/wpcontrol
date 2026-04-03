<?php
/**
 * Gestore della disattivazione del plugin.
 * La disattivazione è protetta e richiede il codice di sblocco.
 *
 * @package LicenseTemplateKit\Core
 */

namespace LicenseTemplateKit\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Deactivator {

    /**
     * Eseguito alla disattivazione del plugin.
     * Rimuove i cron schedulati ma NON cancella i dati.
     */
    public static function deactivate(): void {
        // Rimuovi i cron.
        wp_clear_scheduled_hook( 'ltk_sync_cron' );
        wp_clear_scheduled_hook( 'ltk_check_cron' );

        // Registra l'evento nel log di audit.
        self::log_deactivation();
    }

    /**
     * Registra la disattivazione nel log.
     */
    private static function log_deactivation(): void {
        global $wpdb;

        $current_user = wp_get_current_user();
        $table = $wpdb->prefix . 'ltk_tpl_log';

        $wpdb->insert( $table, [
            'event_type'        => 'plugin_deactivated',
            'event_description' => 'Il plugin è stato disattivato.',
            'actor'             => $current_user->user_login ?? 'sistema',
            'ip_address'        => self::get_client_ip(),
            'metadata'          => wp_json_encode( [
                'timestamp' => current_time( 'mysql' ),
                'method'    => 'deactivation_hook',
            ] ),
        ] );
    }

    /**
     * Ottieni l'IP del client.
     */
    private static function get_client_ip(): string {
        $ip_keys = [ 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ];
        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) );
                return trim( $ip[0] );
            }
        }
        return '0.0.0.0';
    }
}
