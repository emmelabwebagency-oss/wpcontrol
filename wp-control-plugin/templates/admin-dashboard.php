<?php
/**
 * Template: Dashboard principale.
 *
 * @package LicenseTemplateKit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$setup_complete = isset( $_GET['setup'] ) && $_GET['setup'] === 'complete';
$api_token_display = get_transient( 'ltk_setup_api_token' );
$site_id_display = get_transient( 'ltk_setup_site_id' );
$unlocked = isset( $_GET['unlocked'] ) && $_GET['unlocked'] === '1';
?>

<div class="wrap ltk-dashboard">
    <h1>&#128737; WP License Template KIT — Dashboard</h1>

    <?php if ( $setup_complete && $api_token_display ) : ?>
        <div class="notice notice-success">
            <p><strong><?php esc_html_e( 'Setup completato con successo!', 'wp-ltk' ); ?></strong></p>
            <p><?php esc_html_e( 'Salva queste informazioni in un luogo sicuro. Il token API non sarà più visibile.', 'wp-ltk' ); ?></p>
            <table class="widefat" style="max-width:600px;">
                <tr><th><?php esc_html_e( 'Site ID', 'wp-ltk' ); ?></th><td><code><?php echo esc_html( $site_id_display ); ?></code></td></tr>
                <tr><th><?php esc_html_e( 'Token API', 'wp-ltk' ); ?></th><td><code style="word-break:break-all;"><?php echo esc_html( $api_token_display ); ?></code></td></tr>
            </table>
        </div>
        <?php
        delete_transient( 'ltk_setup_api_token' );
        delete_transient( 'ltk_setup_site_id' );
        ?>
    <?php endif; ?>

    <?php if ( $unlocked ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><strong><?php esc_html_e( 'Sito sbloccato con successo!', 'wp-ltk' ); ?></strong></p>
        </div>
    <?php endif; ?>

    <!-- Stato Generale -->
    <div class="ltk-cards">
        <div class="ltk-card <?php echo $is_locked ? 'ltk-card-danger' : 'ltk-card-success'; ?>">
            <h3><?php esc_html_e( 'Stato', 'wp-ltk' ); ?></h3>
            <div class="ltk-card-value">
                <?php if ( $is_locked ) : ?>
                    <span class="ltk-status-locked">&#128274; <?php esc_html_e( 'BLOCCATO', 'wp-ltk' ); ?></span>
                <?php else : ?>
                    <span class="ltk-status-unlocked">&#128275; <?php esc_html_e( 'ATTIVO', 'wp-ltk' ); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="ltk-card">
            <h3><?php esc_html_e( 'Connessione', 'wp-ltk' ); ?></h3>
            <div class="ltk-card-value">
                <?php
                $last_ack = $hb_status['last_ack'] ?? 0;
                if ( $last_ack > 0 ) {
                    $diff = time() - $last_ack;
                    if ( $diff < 7200 ) {
                        echo '<span style="color:#00a32a;">&#9679; ' . esc_html__( 'Connesso', 'wp-ltk' ) . '</span>';
                    } else {
                        echo '<span style="color:#dba617;">&#9679; ' . esc_html__( 'Ultimo ACK: ', 'wp-ltk' ) . esc_html( human_time_diff( $last_ack ) ) . ' fa</span>';
                    }
                } else {
                    echo '<span style="color:#999;">&#9679; ' . esc_html__( 'Non ancora connesso', 'wp-ltk' ) . '</span>';
                }
                ?>
            </div>
        </div>

    </div>

    <!-- Informazioni Sito -->
    <h2><?php esc_html_e( 'Informazioni Sito', 'wp-ltk' ); ?></h2>
    <table class="widefat striped">
        <tbody>
            <tr><th><?php esc_html_e( 'Site ID', 'wp-ltk' ); ?></th><td><code><?php echo esc_html( $site_id ); ?></code></td></tr>
            <tr><th><?php esc_html_e( 'Versione WordPress', 'wp-ltk' ); ?></th><td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
            <tr><th><?php esc_html_e( 'Versione PHP', 'wp-ltk' ); ?></th><td><?php echo esc_html( phpversion() ); ?></td></tr>
            <tr><th><?php esc_html_e( 'Tema Attivo', 'wp-ltk' ); ?></th><td><?php echo esc_html( get_stylesheet() ); ?></td></tr>
            <tr><th><?php esc_html_e( 'Plugin Attivi', 'wp-ltk' ); ?></th><td><?php echo count( get_option( 'active_plugins', [] ) ); ?></td></tr>
            <tr><th><?php esc_html_e( 'Versione Plugin', 'wp-ltk' ); ?></th><td><?php echo esc_html( LTK_VERSION ); ?></td></tr>
        </tbody>
    </table>

</div>
