<?php
/**
 * Template: Impostazioni.
 *
 * @package LicenseTemplateKit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$saved = isset( $_GET['saved'] ) && $_GET['saved'] === '1';
?>

<div class="wrap ltk-settings">
    <h1>&#9881; WP License Template KIT — Impostazioni</h1>

    <?php if ( $saved ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Impostazioni salvate con successo.', 'wp-ltk' ); ?></p>
        </div>
    <?php endif; ?>

    <table class="form-table">
        <tr>
            <th><?php esc_html_e( 'Site ID', 'wp-ltk' ); ?></th>
            <td><code><?php echo esc_html( $settings['site_id'] ); ?></code></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'URL Pannello di Controllo', 'wp-ltk' ); ?></th>
            <td><code><?php echo esc_html( $settings['control_panel_url'] ); ?></code></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Stato Connessione', 'wp-ltk' ); ?></th>
            <td>
                <?php
                $last_ack = (int) get_option( LTK_OPTION_PREFIX . 'last_heartbeat_ack', 0 );
                if ( $last_ack > 0 ) :
                    $ago = human_time_diff( $last_ack, time() );
                ?>
                    <span style="color:#00a32a;">&#9679;</span> <?php printf( esc_html__( 'Connesso (ultimo heartbeat: %s fa)', 'wp-ltk' ), esc_html( $ago ) ); ?>
                <?php else : ?>
                    <span style="color:#d63638;">&#9679;</span> <?php esc_html_e( 'Mai connesso', 'wp-ltk' ); ?>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Stato Blocco', 'wp-ltk' ); ?></th>
            <td>
                <?php if ( get_option( LTK_OPTION_PREFIX . 'lock_mode', false ) ) : ?>
                    <span style="color:#d63638;"><strong><?php esc_html_e( 'Bloccato', 'wp-ltk' ); ?></strong></span>
                <?php else : ?>
                    <span style="color:#00a32a;"><?php esc_html_e( 'Attivo', 'wp-ltk' ); ?></span>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <p class="description"><?php esc_html_e( 'Per modificare la configurazione, disattiva il plugin e rifai il setup. Il blocco/sblocco del sito si gestisce esclusivamente dal pannello Pannello di Gestione.', 'wp-ltk' ); ?></p>
</div>
