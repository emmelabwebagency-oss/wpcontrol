<?php
/**
 * Template: Dashboard principale di WP Control.
 *
 * @package WPControl
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$setup_complete = isset( $_GET['setup'] ) && $_GET['setup'] === 'complete';
$api_token_display = get_transient( 'wpc_setup_api_token' );
$site_id_display = get_transient( 'wpc_setup_site_id' );
$unlocked = isset( $_GET['unlocked'] ) && $_GET['unlocked'] === '1';
?>

<div class="wrap wpc-dashboard">
    <h1>&#128737; WP Control — Dashboard</h1>

    <?php if ( $setup_complete && $api_token_display ) : ?>
        <div class="notice notice-success">
            <p><strong><?php esc_html_e( 'Setup completato con successo!', 'wp-control' ); ?></strong></p>
            <p><?php esc_html_e( 'Salva queste informazioni in un luogo sicuro. Il token API non sarà più visibile.', 'wp-control' ); ?></p>
            <table class="widefat" style="max-width:600px;">
                <tr><th><?php esc_html_e( 'Site ID', 'wp-control' ); ?></th><td><code><?php echo esc_html( $site_id_display ); ?></code></td></tr>
                <tr><th><?php esc_html_e( 'Token API', 'wp-control' ); ?></th><td><code style="word-break:break-all;"><?php echo esc_html( $api_token_display ); ?></code></td></tr>
            </table>
        </div>
        <?php
        delete_transient( 'wpc_setup_api_token' );
        delete_transient( 'wpc_setup_site_id' );
        ?>
    <?php endif; ?>

    <?php if ( $unlocked ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><strong><?php esc_html_e( 'Sito sbloccato con successo!', 'wp-control' ); ?></strong></p>
        </div>
    <?php endif; ?>

    <!-- Stato Generale -->
    <div class="wpc-cards">
        <div class="wpc-card <?php echo $is_locked ? 'wpc-card-danger' : 'wpc-card-success'; ?>">
            <h3><?php esc_html_e( 'Stato Blocco', 'wp-control' ); ?></h3>
            <div class="wpc-card-value">
                <?php if ( $is_locked ) : ?>
                    <span class="wpc-status-locked">&#128274; <?php esc_html_e( 'BLOCCATO', 'wp-control' ); ?></span>
                <?php else : ?>
                    <span class="wpc-status-unlocked">&#128275; <?php esc_html_e( 'ATTIVO', 'wp-control' ); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="wpc-card">
            <h3><?php esc_html_e( 'Connessione', 'wp-control' ); ?></h3>
            <div class="wpc-card-value">
                <?php
                $last_ack = $hb_status['last_ack'] ?? 0;
                if ( $last_ack > 0 ) {
                    $diff = time() - $last_ack;
                    if ( $diff < 7200 ) {
                        echo '<span style="color:#00a32a;">&#9679; ' . esc_html__( 'Connesso', 'wp-control' ) . '</span>';
                    } else {
                        echo '<span style="color:#dba617;">&#9679; ' . esc_html__( 'Ultimo ACK: ', 'wp-control' ) . esc_html( human_time_diff( $last_ack ) ) . ' fa</span>';
                    }
                } else {
                    echo '<span style="color:#999;">&#9679; ' . esc_html__( 'Non ancora connesso', 'wp-control' ) . '</span>';
                }
                ?>
            </div>
        </div>

        <div class="wpc-card <?php echo ( $hb_status['restricted_mode'] ?? false ) ? 'wpc-card-warning' : ''; ?>">
            <h3><?php esc_html_e( 'Ownership Enforcement', 'wp-control' ); ?></h3>
            <div class="wpc-card-value">
                <?php if ( $hb_status['enforcement_enabled'] ?? false ) : ?>
                    <?php if ( $hb_status['restricted_mode'] ?? false ) : ?>
                        <span style="color:#d63638;">&#9888; <?php esc_html_e( 'Modalità Ristretta', 'wp-control' ); ?></span>
                    <?php else : ?>
                        <span style="color:#00a32a;">&#10003; <?php esc_html_e( 'Attivo', 'wp-control' ); ?></span>
                    <?php endif; ?>
                <?php else : ?>
                    <span style="color:#999;"><?php esc_html_e( 'Disabilitato', 'wp-control' ); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Informazioni Sito -->
    <h2><?php esc_html_e( 'Informazioni Sito', 'wp-control' ); ?></h2>
    <table class="widefat striped">
        <tbody>
            <tr><th><?php esc_html_e( 'Site ID', 'wp-control' ); ?></th><td><code><?php echo esc_html( $site_id ); ?></code></td></tr>
            <tr><th><?php esc_html_e( 'URL Pannello Remoto', 'wp-control' ); ?></th><td><?php echo esc_html( $panel_url ); ?></td></tr>
            <tr><th><?php esc_html_e( 'Versione WordPress', 'wp-control' ); ?></th><td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
            <tr><th><?php esc_html_e( 'Versione PHP', 'wp-control' ); ?></th><td><?php echo esc_html( phpversion() ); ?></td></tr>
            <tr><th><?php esc_html_e( 'Tema Attivo', 'wp-control' ); ?></th><td><?php echo esc_html( get_stylesheet() ); ?></td></tr>
            <tr><th><?php esc_html_e( 'Plugin Attivi', 'wp-control' ); ?></th><td><?php echo count( get_option( 'active_plugins', [] ) ); ?></td></tr>
            <tr><th><?php esc_html_e( 'Versione WP Control', 'wp-control' ); ?></th><td><?php echo esc_html( WPC_VERSION ); ?></td></tr>
        </tbody>
    </table>

    <!-- Alert Recenti -->
    <h2><?php esc_html_e( 'Alert di Sicurezza Recenti', 'wp-control' ); ?></h2>
    <?php if ( empty( $recent_alerts ) ) : ?>
        <p class="description"><?php esc_html_e( 'Nessun alert di sicurezza recente.', 'wp-control' ); ?></p>
    <?php else : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Tipo', 'wp-control' ); ?></th>
                    <th><?php esc_html_e( 'Gravità', 'wp-control' ); ?></th>
                    <th><?php esc_html_e( 'Descrizione', 'wp-control' ); ?></th>
                    <th><?php esc_html_e( 'Data', 'wp-control' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $recent_alerts as $alert ) : ?>
                    <tr>
                        <td><code><?php echo esc_html( $alert['alert_type'] ); ?></code></td>
                        <td>
                            <?php
                            $severity_colors = [ 'low' => '#999', 'medium' => '#dba617', 'high' => '#d63638', 'critical' => '#8b0000' ];
                            $color = $severity_colors[ $alert['severity'] ] ?? '#999';
                            ?>
                            <span style="color:<?php echo esc_attr( $color ); ?>;font-weight:bold;">
                                <?php echo esc_html( strtoupper( $alert['severity'] ) ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( $alert['description'] ); ?></td>
                        <td><?php echo esc_html( $alert['created_at'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
