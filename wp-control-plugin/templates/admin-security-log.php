<?php
/**
 * Template: Log di Sicurezza.
 *
 * @package LicenseTemplateKit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap ltk-security-log">
    <h1>&#128220; WP License Template KIT — Log di Sicurezza</h1>

    <?php if ( empty( $logs ) ) : ?>
        <p class="description"><?php esc_html_e( 'Nessun evento registrato.', 'wp-ltk' ); ?></p>
    <?php else : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'ID', 'wp-ltk' ); ?></th>
                    <th><?php esc_html_e( 'Tipo Evento', 'wp-ltk' ); ?></th>
                    <th><?php esc_html_e( 'Descrizione', 'wp-ltk' ); ?></th>
                    <th><?php esc_html_e( 'Attore', 'wp-ltk' ); ?></th>
                    <th><?php esc_html_e( 'IP', 'wp-ltk' ); ?></th>
                    <th><?php esc_html_e( 'Data', 'wp-ltk' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $logs as $log ) : ?>
                    <tr>
                        <td><?php echo esc_html( $log['id'] ); ?></td>
                        <td><code><?php echo esc_html( $log['event_type'] ); ?></code></td>
                        <td><?php echo esc_html( $log['event_description'] ); ?></td>
                        <td><?php echo esc_html( $log['actor'] ?? '-' ); ?></td>
                        <td><code><?php echo esc_html( $log['ip_address'] ?? '-' ); ?></code></td>
                        <td><?php echo esc_html( $log['created_at'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Paginazione -->
        <?php if ( $total_pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links( [
                        'base'    => add_query_arg( 'paged', '%#%' ),
                        'format'  => '',
                        'current' => $page_num,
                        'total'   => $total_pages,
                    ] );
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
