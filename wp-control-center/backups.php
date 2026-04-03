<?php
/**
 * WP Control Center - Gestione Backup.
 */

define( 'WPC_ROOT', __DIR__ );
require_once WPC_ROOT . '/includes/bootstrap.php';
WPC_Auth::require_auth();

$page_title = 'Gestione Backup';
$message    = '';
$error      = '';

// Azioni POST.
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && WPC_Auth::verify_csrf( $_POST['csrf_token'] ?? '' ) ) {
    $post_action = $_POST['action'] ?? '';

    if ( $post_action === 'restore' ) {
        $backup_id = $_POST['backup_id'] ?? '';
        if ( WPC_Backups::mark_restore_requested( $backup_id ) ) {
            $backup = WPC_Backups::find_by_backup_id( $backup_id );
            if ( $backup ) {
                WPC_Sites::send_command( $backup['site_id'], 'restore', [ 'backup_id' => $backup_id ] );
            }
            $message = 'Ripristino richiesto con successo.';
        } else {
            $error = 'Errore nella richiesta di ripristino.';
        }
    } elseif ( $post_action === 'delete' && WPC_Auth::is_admin() ) {
        $backup_id = $_POST['backup_id'] ?? '';
        if ( WPC_Backups::delete( $backup_id ) ) {
            $message = 'Backup eliminato.';
        } else {
            $error = 'Errore nell\'eliminazione del backup.';
        }
    }
}

$page    = max( 1, (int) ( $_GET['page'] ?? 1 ) );
$limit   = 20;
$offset  = ( $page - 1 ) * $limit;
$backups = WPC_Backups::find_all( $limit, $offset );
$total   = WPC_Backups::count_all();
$pages   = (int) ceil( $total / $limit );

function format_bytes_bk( int $bytes, int $decimals = 2 ): string {
    if ( $bytes === 0 ) return '0 B';
    $k = 1024;
    $sizes = [ 'B', 'KB', 'MB', 'GB', 'TB' ];
    $i = (int) floor( log( $bytes ) / log( $k ) );
    return round( $bytes / pow( $k, $i ), $decimals ) . ' ' . $sizes[ $i ];
}

include WPC_ROOT . '/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-cloud-arrow-up me-2"></i>Backup</h4>
    <span class="text-muted"><?php echo $total; ?> backup totali</span>
</div>

<?php if ( $message ): ?>
    <div class="alert alert-success alert-dismissible fade show"><?php echo htmlspecialchars( $message, ENT_QUOTES, 'UTF-8' ); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ( $error ): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?php echo htmlspecialchars( $error, ENT_QUOTES, 'UTF-8' ); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <?php if ( empty( $backups ) ): ?>
            <div class="empty-state">
                <i class="bi bi-cloud-arrow-up d-block"></i>
                <p>Nessun backup presente</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-wpc table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Backup ID</th>
                            <th>Sito</th>
                            <th>Stato</th>
                            <th>Dimensione</th>
                            <th>Crittografato</th>
                            <th>Data</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $backups as $backup ): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars( substr( $backup['backup_id'], 0, 12 ), ENT_QUOTES, 'UTF-8' ); ?>...</code></td>
                            <td>
                                <?php echo htmlspecialchars( $backup['site_name'] ?? $backup['site_id'], ENT_QUOTES, 'UTF-8' ); ?>
                            </td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars( $backup['status'], ENT_QUOTES, 'UTF-8' ); ?></span></td>
                            <td><?php echo format_bytes_bk( (int) $backup['file_size'] ); ?></td>
                            <td><?php echo $backup['encrypted'] ? '<i class="bi bi-lock text-success"></i>' : '<i class="bi bi-unlock text-muted"></i>'; ?></td>
                            <td><small><?php echo htmlspecialchars( $backup['created_at'], ENT_QUOTES, 'UTF-8' ); ?></small></td>
                            <td>
                                <?php if ( $backup['status'] === 'stored' ): ?>
                                    <form method="POST" action="" class="d-inline">
                                        <?php echo WPC_Auth::csrf_field(); ?>
                                        <input type="hidden" name="action" value="restore">
                                        <input type="hidden" name="backup_id" value="<?php echo htmlspecialchars( $backup['backup_id'], ENT_QUOTES, 'UTF-8' ); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning" data-confirm="Ripristinare questo backup?">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if ( WPC_Auth::is_admin() ): ?>
                                    <form method="POST" action="" class="d-inline">
                                        <?php echo WPC_Auth::csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="backup_id" value="<?php echo htmlspecialchars( $backup['backup_id'], ENT_QUOTES, 'UTF-8' ); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Eliminare definitivamente questo backup?">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ( $pages > 1 ): ?>
                <nav class="p-3">
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <?php for ( $i = 1; $i <= $pages; $i++ ): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include WPC_ROOT . '/templates/footer.php'; ?>
