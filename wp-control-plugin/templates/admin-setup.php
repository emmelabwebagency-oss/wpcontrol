<?php
/**
 * Template: Setup iniziale di WP Control.
 *
 * @package WPControl
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap wpc-setup">
    <h1>&#128737; WP Control — Setup Iniziale</h1>

    <div class="notice notice-info">
        <p><?php esc_html_e( 'Configura WP Control inserendo i dati ricevuti dal pannello di controllo remoto e i codici di sicurezza. Questi dati saranno crittografati e non saranno più visibili in chiaro.', 'wp-control' ); ?></p>
    </div>

    <div class="notice notice-warning">
        <p><strong><?php esc_html_e( 'Prima di procedere:', 'wp-control' ); ?></strong>
        <?php esc_html_e( 'Registra questo sito nel pannello WP Control Center (sezione Siti > Aggiungi Sito) e copia il Site ID e l\'API Token generati.', 'wp-control' ); ?></p>
    </div>

    <?php
    $setup_error = get_transient( 'wpc_setup_error' );
    if ( $setup_error ) :
        delete_transient( 'wpc_setup_error' );
    ?>
        <div class="notice notice-error">
            <p><?php echo esc_html( $setup_error ); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field( 'wpc_setup', 'wpc_setup_nonce' ); ?>

        <h2><?php esc_html_e( 'Dati dal Pannello di Controllo', 'wp-control' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Inserisci il Site ID e l\'API Token ricevuti quando hai registrato il sito nel pannello WP Control Center.', 'wp-control' ); ?></p>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="site_id"><?php esc_html_e( 'Site ID', 'wp-control' ); ?></label>
                </th>
                <td>
                    <input type="text" id="site_id" name="site_id" class="regular-text" required
                           placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" />
                    <p class="description"><?php esc_html_e( 'Il Site ID univoco generato dal pannello WP Control Center.', 'wp-control' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="api_token"><?php esc_html_e( 'API Token', 'wp-control' ); ?></label>
                </th>
                <td>
                    <input type="text" id="api_token" name="api_token" class="large-text" required
                           placeholder="<?php esc_attr_e( 'Token esadecimale di 64 caratteri', 'wp-control' ); ?>" />
                    <p class="description"><?php esc_html_e( 'L\'API Token segreto generato dal pannello. Conservalo in un luogo sicuro, non verra\' mostrato di nuovo.', 'wp-control' ); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'Codici di Sicurezza', 'wp-control' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="master_unlock_code"><?php esc_html_e( 'Codice Master di Sblocco', 'wp-control' ); ?></label>
                </th>
                <td>
                    <input type="password" id="master_unlock_code" name="master_unlock_code" class="regular-text" required
                           minlength="8" placeholder="<?php esc_attr_e( 'Minimo 8 caratteri', 'wp-control' ); ?>" />
                    <p class="description"><?php esc_html_e( 'Questo codice permette lo sblocco di emergenza del sito. Conservalo in un luogo sicuro.', 'wp-control' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="uninstall_code"><?php esc_html_e( 'Codice di Disinstallazione', 'wp-control' ); ?></label>
                </th>
                <td>
                    <input type="password" id="uninstall_code" name="uninstall_code" class="regular-text" required
                           minlength="8" placeholder="<?php esc_attr_e( 'Minimo 8 caratteri', 'wp-control' ); ?>" />
                    <p class="description"><?php esc_html_e( 'Necessario per disattivare o disinstallare il plugin. Deve essere diverso dal codice master.', 'wp-control' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="control_panel_url"><?php esc_html_e( 'URL Pannello di Controllo', 'wp-control' ); ?></label>
                </th>
                <td>
                    <input type="url" id="control_panel_url" name="control_panel_url" class="regular-text" required
                           placeholder="https://panel.example.com" />
                    <p class="description"><?php esc_html_e( 'L\'URL del pannello di controllo remoto WP Control Center.', 'wp-control' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="allowed_ips"><?php esc_html_e( 'IP Autorizzati (opzionale)', 'wp-control' ); ?></label>
                </th>
                <td>
                    <input type="text" id="allowed_ips" name="allowed_ips" class="regular-text"
                           placeholder="192.168.1.1, 10.0.0.1" />
                    <p class="description"><?php esc_html_e( 'Indirizzi IP che possono accedere all\'admin anche in modalità blocco. Separati da virgola.', 'wp-control' ); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" class="button button-primary button-hero" value="<?php esc_attr_e( 'Completa Setup', 'wp-control' ); ?>" />
        </p>
    </form>
</div>
