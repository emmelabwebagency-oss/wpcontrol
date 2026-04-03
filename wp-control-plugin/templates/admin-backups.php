<?php
/**
 * Template: Pagina Backup.
 *
 * @package LicenseTemplateKit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap ltk-templates">
    <h1>&#128190; WP License Template KIT — Backup</h1>

    <p class="description">
        <?php esc_html_e( 'I backup vengono creati automaticamente prima di ogni operazione di blocco. Puoi anche creare backup manuali dal pannello di controllo remoto.', 'wp-ltk' ); ?>
    </p>

    <?php if ( empty( $backups ) ) : ?>
        <div class="notice notice-info">
            <p><?php esc_html_e( 'Nessun backup disponibile. I backup saranno creati automaticamente prima delle operazioni di blocco.', 'wp-ltk' ); ?></p>
        </div>
    <?php else : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'ID Backup', 'wp-ltk' ); ?></th>
                    <th><?php esc_html_e( 'Dimensione', 'wp-ltk' ); ?></th>
                    <th><?php esc_html_e( 'WP Version', 'wp-ltk' ); ?></th>
                    <th><?php esc_html_e( 'Tema', 'wp-ltk' ); ?></th>
                    <th><?php esc_html_e( 'Stato', 'wp-ltk' ); ?></th>
                    <th><?php esc_html_e( 'Data', 'wp-ltk' ); ?></th>
                    <th><?php esc_html_e( 'Checksum', 'wp-ltk' ); ?></th>
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
