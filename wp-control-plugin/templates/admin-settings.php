<?php
/**
 * Template: Impostazioni di WP Control.
 *
 * @package WPControl
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$saved = isset( $_GET['saved'] ) && $_GET['saved'] === '1';
?>

<div class="wrap wpc-settings">
    <h1>&#9881; WP Control — Impostazioni</h1>

    <?php if ( $saved ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Impostazioni salvate con successo.', 'wp-control' ); ?></p>
        </div>
    <?php endif; ?>

    <table class="form-table">
        <tr>
            <th><?php esc_html_e( 'Site ID', 'wp-control' ); ?></th>
            <td><code><?php echo esc_html( $settings['site_id'] ); ?></code></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'URL Pannello di Controllo', 'wp-control' ); ?></th>
            <td><code><?php echo esc_html( $settings['control_panel_url'] ); ?></code></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Stato Connessione', 'wp-control' ); ?></th>
            <td>
                <?php
                $last_ack = (int) get_option( WPC_OPTION_PREFIX . 'last_heartbeat_ack', 0 );
                if ( $last_ack > 0 ) :
                    $ago = human_time_diff( $last_ack, time() );
                ?>
                    <span style="color:#00a32a;">&#9679;</span> <?php printf( esc_html__( 'Connesso (ultimo heartbeat: %s fa)', 'wp-control' ), esc_html( $ago ) ); ?>
                <?php else : ?>
                    <span style="color:#d63638;">&#9679;</span> <?php esc_html_e( 'Mai connesso', 'wp-control' ); ?>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Stato Blocco', 'wp-control' ); ?></th>
            <td>
                <?php if ( get_option( WPC_OPTION_PREFIX . 'lock_mode', false ) ) : ?>
                    <span style="color:#d63638;"><strong><?php esc_html_e( 'Bloccato', 'wp-control' ); ?></strong></span>
                <?php else : ?>
                    <span style="color:#00a32a;"><?php esc_html_e( 'Attivo', 'wp-control' ); ?></span>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <p class="description"><?php esc_html_e( 'Per modificare la configurazione, disattiva il plugin e rifai il setup. Il blocco/sblocco del sito si gestisce esclusivamente dal pannello WP Control Center.', 'wp-control' ); ?></p>
</div>
