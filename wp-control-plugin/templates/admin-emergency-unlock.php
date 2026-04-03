<?php
/**
 * Template: Sblocco di Emergenza di WP Control.
 *
 * @package WPControl
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$error = get_transient( 'wpc_emergency_error' );
$success = get_transient( 'wpc_emergency_success' );
delete_transient( 'wpc_emergency_error' );
delete_transient( 'wpc_emergency_success' );
?>

<div class="wrap wpc-emergency">
    <h1>&#128272; WP Control — Sblocco di Emergenza</h1>

    <div class="notice notice-warning">
        <p><?php esc_html_e( 'Utilizza questa funzione solo se hai perso l\'accesso al pannello di controllo remoto. Inserisci il codice master di sblocco configurato durante il setup iniziale.', 'wp-control' ); ?></p>
    </div>

    <?php if ( $error ) : ?>
        <div class="notice notice-error">
            <p><?php echo esc_html( $error ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( $success ) : ?>
        <div class="notice notice-success">
            <p><?php echo esc_html( $success ); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="" style="max-width:500px;">
        <?php wp_nonce_field( 'wpc_emergency_unlock', 'wpc_emergency_nonce' ); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="master_code"><?php esc_html_e( 'Codice Master di Sblocco', 'wp-control' ); ?></label>
                </th>
                <td>
                    <input type="password" id="master_code" name="master_code" class="regular-text" required
                           placeholder="<?php esc_attr_e( 'Inserisci il codice master', 'wp-control' ); ?>" />
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" class="button button-primary button-hero" value="<?php esc_attr_e( 'Sblocca Sito', 'wp-control' ); ?>" />
        </p>
    </form>

    <hr />
    <h3><?php esc_html_e( 'Metodi Alternativi di Sblocco', 'wp-control' ); ?></h3>
    <ul>
        <li><?php esc_html_e( 'Accedi al pannello di controllo remoto e utilizza la funzione "Unlock".', 'wp-control' ); ?></li>
        <li><?php esc_html_e( 'Invia una richiesta POST all\'endpoint REST /wp-json/wp-control/v1/emergency-unlock con il master code.', 'wp-control' ); ?></li>
        <li><?php esc_html_e( 'Contatta il supporto tecnico per la procedura di recovery key.', 'wp-control' ); ?></li>
    </ul>
</div>
