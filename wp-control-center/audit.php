<?php
/**
 * WP Control Center - Audit Log.
 */

define( 'WPC_ROOT', __DIR__ );
require_once WPC_ROOT . '/includes/bootstrap.php';
WPC_Auth::require_auth();

$page_title = 'Audit Log';

$page    = max( 1, (int) ( $_GET['page'] ?? 1 ) );
$site_id = $_GET['site_id'] ?? null;
$limit   = 50;
$offset  = ( $page - 1 ) * $limit;

$logs  = WPC_Audit::get_logs( $limit, $offset, $site_id );
$total = WPC_Audit::count_logs( $site_id );
$pages = max( 1, (int) ceil( $total / $limit ) );

include WPC_ROOT . '/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-journal-text me-2"></i>Audit Log</h4>
    <span class="text-muted"><?php echo $total; ?> eventi totali</span>
</div>

<?php if ( $site_id ): ?>
    <div class="alert alert-info">
        Filtro attivo: sito <code><?php echo htmlspecialchars( $site_id, ENT_QUOTES, 'UTF-8' ); ?></code>
        <a href="audit.php" class="ms-2">Rimuovi filtro</a>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <?php if ( empty( $logs ) ): ?>
            <div class="empty-state">
                <i class="bi bi-journal d-block"></i>
                <p>Nessun evento registrato</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-wpc table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Azione</th>
                            <th>Descrizione</th>
                            <th>Utente</th>
                            <th>Sito</th>
                            <th>IP</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $logs as $log ): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars( $log['action'], ENT_QUOTES, 'UTF-8' ); ?></code></td>
                            <td><small><?php echo htmlspecialchars( $log['description'] ?? '', ENT_QUOTES, 'UTF-8' ); ?></small></td>
                            <td><small><?php echo htmlspecialchars( $log['user_email'] ?? '-', ENT_QUOTES, 'UTF-8' ); ?></small></td>
                            <td>
                                <?php if ( $log['site_id'] ): ?>
                                    <a href="audit.php?site_id=<?php echo urlencode( $log['site_id'] ); ?>">
                                        <code><?php echo htmlspecialchars( substr( $log['site_id'], 0, 8 ), ENT_QUOTES, 'UTF-8' ); ?>...</code>
                                    </a>
                                <?php else: ?>
                                    <small class="text-muted">-</small>
                                <?php endif; ?>
                            </td>
                            <td><small><?php echo htmlspecialchars( $log['ip_address'] ?? '-', ENT_QUOTES, 'UTF-8' ); ?></small></td>
                            <td><small><?php echo htmlspecialchars( $log['created_at'], ENT_QUOTES, 'UTF-8' ); ?></small></td>
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
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $site_id ? '&site_id=' . urlencode( $site_id ) : ''; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include WPC_ROOT . '/templates/footer.php'; ?>
