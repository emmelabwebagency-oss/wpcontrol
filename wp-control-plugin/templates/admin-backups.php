<?php
/**
 * Template: Pagina Backup di WP Control.
 *
 * @package WPControl
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap wpc-backups">
    <h1>&#128190; WP Control — Backup</h1>

    <p class="description">
        <?php esc_html_e( 'I backup vengono creati automaticamente prima di ogni operazione di blocco. Puoi anche creare backup manuali dal pannello di controllo remoto.', 'wp-control' ); ?>
    </p>

    <?php if ( empty( $backups ) ) : ?>
        <div class="notice notice-info">
            <p><?php esc_html_e( 'Nessun backup disponibile. I backup saranno creati automaticamente prima delle operazioni di blocco.', 'wp-control' ); ?></p>
        </div>
    <?php else : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'ID Backup', 'wp-control' ); ?></th>
                    <th><?php esc_html_e( 'Dimensione', 'wp-control' ); ?></th>
                    <th><?php esc_html_e( 'WP Version', 'wp-control' ); ?></th>
                    <th><?php esc_html_e( 'Tema', 'wp-control' ); ?></th>
                    <th><?php esc_html_e( 'Stato', 'wp-control' ); ?></th>
                    <th><?php esc_html_e( 'Data', 'wp-control' ); ?></th>
                    <th><?php esc_html_e( 'Checksum', 'wp-control' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $backups as $backup ) : ?>
                    <tr>
                        <td><code><?php echo esc_html( $backup['backup_id'] ); ?></code></td>
                        <td><?php echo esc_html( size_format( $backup['file_size'] ?? 0 ) ); ?></td>
                        <td><?php echo esc_html( $backup['wp_version'] ?? '-' ); ?></td>
                        <td><?php echo esc_html( $backup['active_theme'] ?? '-' ); ?></td>
                        <td>
                            <?php
                            $status_labels = [
                                'created'  => '<span style="color:#dba617;">&#9679; Creato</span>',
                                'uploaded' => '<span style="color:#00a32a;">&#9679; Caricato</span>',
                                'pending'  => '<span style="color:#999;">&#9679; In attesa</span>',
                                'failed'   => '<span style="color:#d63638;">&#9679; Fallito</span>',
                            ];
                            echo $status_labels[ $backup['status'] ] ?? esc_html( $backup['status'] );
                            ?>
                        </td>
                        <td><?php echo esc_html( $backup['created_at'] ); ?></td>
                        <td><code title="<?php echo esc_attr( $backup['checksum'] ?? '' ); ?>"><?php echo esc_html( substr( $backup['checksum'] ?? '', 0, 16 ) . '...' ); ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
