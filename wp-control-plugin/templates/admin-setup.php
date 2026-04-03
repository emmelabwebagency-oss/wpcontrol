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
        </table>

        <p class="submit">
            <input type="submit" class="button button-primary button-hero" value="<?php esc_attr_e( 'Completa Setup', 'wp-control' ); ?>" />
        </p>
    </form>
</div>
