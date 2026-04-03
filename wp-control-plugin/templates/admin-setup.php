<?php
/**
 * Template: Setup iniziale.
 *
 * @package LicenseTemplateKit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap ltk-setup">
    <h1>&#128737; WP License Template KIT — Setup Iniziale</h1>

    <div class="notice notice-warning">
        <p><strong><?php esc_html_e( 'Prima di procedere:', 'wp-ltk' ); ?></strong>
        <?php esc_html_e( 'Registra questo sito nel pannello Pannello di Gestione (sezione Siti > Aggiungi Sito) e copia il Site ID e l\'API Token generati.', 'wp-ltk' ); ?></p>
    </div>

    <?php
    $setup_error = get_transient( 'ltk_setup_error' );
    if ( $setup_error ) :
        delete_transient( 'ltk_setup_error' );
    ?>
        <div class="notice notice-error">
            <p><?php echo esc_html( $setup_error ); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field( 'ltk_setup', 'ltk_setup_nonce' ); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="site_id"><?php esc_html_e( 'Site ID', 'wp-ltk' ); ?></label>
                </th>
                <td>
                    <input type="text" id="site_id" name="site_id" class="regular-text" required
                           placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" />
                    <p class="description"><?php esc_html_e( 'Il Site ID univoco generato dal pannello Pannello di Gestione.', 'wp-ltk' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="api_token"><?php esc_html_e( 'API Token', 'wp-ltk' ); ?></label>
                </th>
                <td>
                    <input type="text" id="api_token" name="api_token" class="large-text" required
                           placeholder="<?php esc_attr_e( 'Token esadecimale di 64 caratteri', 'wp-ltk' ); ?>" />
                    <p class="description"><?php esc_html_e( 'L\'API Token segreto generato dal pannello. Conservalo in un luogo sicuro, non verra\' mostrato di nuovo.', 'wp-ltk' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="control_panel_url"><?php esc_html_e( 'URL Pannello di Controllo', 'wp-ltk' ); ?></label>
                </th>
                <td>
                    <input type="url" id="control_panel_url" name="control_panel_url" class="regular-text" required
                           placeholder="https://panel.example.com" />
                    <p class="description"><?php esc_html_e( 'L\'URL del pannello di controllo remoto Pannello di Gestione.', 'wp-ltk' ); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" class="button button-primary button-hero" value="<?php esc_attr_e( 'Completa Setup', 'wp-ltk' ); ?>" />
        </p>
    </form>
</div>
