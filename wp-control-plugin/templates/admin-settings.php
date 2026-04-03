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

    <form method="post" action="">
        <?php wp_nonce_field( 'wpc_settings', 'wpc_settings_nonce' ); ?>

        <h2><?php esc_html_e( 'Informazioni Connessione', 'wp-control' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Site ID', 'wp-control' ); ?></th>
                <td><code><?php echo esc_html( $settings['site_id'] ); ?></code></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'URL Pannello di Controllo', 'wp-control' ); ?></th>
                <td><code><?php echo esc_html( $settings['control_panel_url'] ); ?></code></td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'Sicurezza Lock Mode', 'wp-control' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="allowed_ips"><?php esc_html_e( 'IP Autorizzati per Emergenza', 'wp-control' ); ?></label>
                </th>
                <td>
                    <input type="text" id="allowed_ips" name="allowed_ips" class="regular-text"
                           value="<?php echo esc_attr( $settings['allowed_ips'] ); ?>"
                           placeholder="192.168.1.1, 10.0.0.1" />
                    <p class="description"><?php esc_html_e( 'IP che mantengono l\'accesso admin anche durante il blocco.', 'wp-control' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Disabilita XML-RPC in Lock Mode', 'wp-control' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="disable_xmlrpc" value="1" <?php checked( $settings['disable_xmlrpc'] ); ?> />
                        <?php esc_html_e( 'Disabilita XML-RPC quando il sito è bloccato', 'wp-control' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Limita REST API in Lock Mode', 'wp-control' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="restrict_rest_api" value="1" <?php checked( $settings['restrict_rest_api'] ); ?> />
                        <?php esc_html_e( 'Limita l\'accesso alla REST API pubblica quando il sito è bloccato', 'wp-control' ); ?>
                    </label>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'Ownership Enforcement', 'wp-control' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Abilita Ownership Enforcement', 'wp-control' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="ownership_enforcement" value="1" <?php checked( $settings['ownership_enforcement'] ); ?> />
                        <?php esc_html_e( 'Attiva la verifica periodica della proprietà tramite heartbeat', 'wp-control' ); ?>
                    </label>
                    <p class="description"><?php esc_html_e( 'Se abilitato, il sito entrerà in modalità ristretta se non riesce a comunicare con il pannello di controllo.', 'wp-control' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="grace_period_hours"><?php esc_html_e( 'Periodo di Grazia (ore)', 'wp-control' ); ?></label>
                </th>
                <td>
                    <input type="number" id="grace_period_hours" name="grace_period_hours" class="small-text"
                           value="<?php echo esc_attr( $settings['grace_period_hours'] ); ?>" min="24" max="720" />
                    <p class="description"><?php esc_html_e( 'Ore di tolleranza prima di attivare la modalità ristretta (minimo 24).', 'wp-control' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="heartbeat_interval_hours"><?php esc_html_e( 'Intervallo Heartbeat (ore)', 'wp-control' ); ?></label>
                </th>
                <td>
                    <input type="number" id="heartbeat_interval_hours" name="heartbeat_interval_hours" class="small-text"
                           value="<?php echo esc_attr( $settings['heartbeat_interval'] ); ?>" min="1" max="24" />
                    <p class="description"><?php esc_html_e( 'Frequenza di invio del ping al pannello di controllo.', 'wp-control' ); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Salva Impostazioni', 'wp-control' ); ?>" />
        </p>
    </form>
</div>
