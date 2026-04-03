<?php
/**
 * WP Control Center - Gestione Alert.
 */

define( 'WPC_ROOT', __DIR__ );
require_once WPC_ROOT . '/includes/bootstrap.php';
WPC_Auth::require_auth();

$page_title = 'Alert';
$message    = '';
$error      = '';

// Azioni POST.
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && WPC_Auth::verify_csrf( $_POST['csrf_token'] ?? '' ) ) {
    $post_action = $_POST['action'] ?? '';

    if ( $post_action === 'acknowledge' ) {
        $alert_id = $_POST['alert_id'] ?? '';
        if ( WPC_Alerts::acknowledge( $alert_id, WPC_Auth::current_user_id() ) ) {
            $message = 'Alert riconosciuto.';
        } else {
            $error = 'Errore nel riconoscimento dell\'alert.';
        }
    } elseif ( $post_action === 'acknowledge_all' ) {
        $unack = WPC_Alerts::find_unacknowledged();
        $count = 0;
        foreach ( $unack as $alert ) {
            if ( WPC_Alerts::acknowledge( $alert['id'], WPC_Auth::current_user_id() ) ) {
                $count++;
            }
        }
        $message = "{$count} alert riconosciuti.";
    }
}

$filter = $_GET['filter'] ?? 'all';
$page   = max( 1, (int) ( $_GET['page'] ?? 1 ) );
$limit  = 30;
$offset = ( $page - 1 ) * $limit;

if ( $filter === 'unacknowledged' ) {
    $alerts = WPC_Alerts::find_unacknowledged();
    $total  = count( $alerts );
} else {
    $alerts = WPC_Alerts::find_all( $limit, $offset );
    $total  = WPC_Alerts::count_all();
}
$pages = max( 1, (int) ceil( $total / $limit ) );

$severity_counts = WPC_Alerts::count_by_severity();

include WPC_ROOT . '/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-bell me-2"></i>Alert</h4>
    <div>
        <?php if ( WPC_Alerts::count_unacknowledged() > 0 ): ?>
            <form method="POST" action="" class="d-inline">
                <?php echo WPC_Auth::csrf_field(); ?>
                <input type="hidden" name="action" value="acknowledge_all">
                <button type="submit" class="btn btn-sm btn-outline-success" data-confirm="Riconoscere tutti gli alert?">
                    <i class="bi bi-check-all me-1"></i>Riconosci Tutti
                </button>
            </form>
        <?php endif; ?>
    </div>
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

<!-- Severity Summary -->
<div class="row g-3 mb-4">
    <div class="col-sm-3">
        <div class="card border-danger">
            <div class="card-body text-center py-2">
                <div class="fw-bold text-danger fs-4"><?php echo $severity_counts['critical']; ?></div>
                <small class="text-muted">Critici</small>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card border-warning">
            <div class="card-body text-center py-2">
                <div class="fw-bold text-warning fs-4"><?php echo $severity_counts['high']; ?></div>
                <small class="text-muted">Alti</small>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card border-info">
            <div class="card-body text-center py-2">
                <div class="fw-bold text-info fs-4"><?php echo $severity_counts['medium']; ?></div>
                <small class="text-muted">Medi</small>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card">
            <div class="card-body text-center py-2">
                <div class="fw-bold fs-4"><?php echo $severity_counts['low']; ?></div>
                <small class="text-muted">Bassi</small>
            </div>
        </div>
    </div>
</div>

<!-- Filtri -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?php echo $filter === 'all' ? 'active' : ''; ?>" href="alerts.php?filter=all">Tutti (<?php echo $total; ?>)</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $filter === 'unacknowledged' ? 'active' : ''; ?>" href="alerts.php?filter=unacknowledged">
            Non Riconosciuti (<?php echo WPC_Alerts::count_unacknowledged(); ?>)
        </a>
    </li>
</ul>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <?php if ( empty( $alerts ) ): ?>
            <div class="empty-state">
                <i class="bi bi-shield-check d-block"></i>
                <p>Nessun alert</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-wpc table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Severita'</th>
                            <th>Tipo Evento</th>
                            <th>Sito</th>
                            <th>Descrizione</th>
                            <th>Data</th>
                            <th>Stato</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $alerts as $alert ): ?>
                        <tr>
                            <td>
                                <span class="badge badge-severity-<?php echo htmlspecialchars( $alert['severity'], ENT_QUOTES, 'UTF-8' ); ?>">
                                    <?php echo htmlspecialchars( ucfirst( $alert['severity'] ), ENT_QUOTES, 'UTF-8' ); ?>
                                </span>
                            </td>
                            <td><code><?php echo htmlspecialchars( $alert['event_type'], ENT_QUOTES, 'UTF-8' ); ?></code></td>
                            <td><?php echo htmlspecialchars( $alert['site_name'] ?? $alert['site_id'], ENT_QUOTES, 'UTF-8' ); ?></td>
                            <td><small><?php echo htmlspecialchars( $alert['description'] ?? '', ENT_QUOTES, 'UTF-8' ); ?></small></td>
                            <td><small><?php echo htmlspecialchars( $alert['created_at'], ENT_QUOTES, 'UTF-8' ); ?></small></td>
                            <td>
                                <?php if ( $alert['acknowledged'] ): ?>
                                    <span class="badge bg-success">Riconosciuto</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Attivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( ! $alert['acknowledged'] ): ?>
                                    <form method="POST" action="" class="d-inline">
                                        <?php echo WPC_Auth::csrf_field(); ?>
                                        <input type="hidden" name="action" value="acknowledge">
                                        <input type="hidden" name="alert_id" value="<?php echo htmlspecialchars( $alert['id'], ENT_QUOTES, 'UTF-8' ); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Riconosci">
                                            <i class="bi bi-check"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ( $filter === 'all' && $pages > 1 ): ?>
                <nav class="p-3">
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <?php for ( $i = 1; $i <= $pages; $i++ ): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?filter=all&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include WPC_ROOT . '/templates/footer.php'; ?>
