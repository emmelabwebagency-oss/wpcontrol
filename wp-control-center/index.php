<?php
/**
 * WP Control Center - Dashboard.
 */

define( 'WPC_ROOT', __DIR__ );
require_once WPC_ROOT . '/includes/bootstrap.php';
WPC_Auth::require_auth();

$page_title = 'Dashboard';

// Raccogli statistiche.
$site_counts    = WPC_Sites::count_by_status();
$alert_counts   = WPC_Alerts::count_by_severity();
$unack_alerts   = WPC_Alerts::count_unacknowledged();
$total_backups  = WPC_Backups::count_all();
$backup_size    = WPC_Backups::total_size();
$total_users    = WPC_Users::count_all();

// Ultimi alert.
$recent_alerts = WPC_Alerts::find_all( 5, 0 );

// Ultimi log di audit.
$recent_audit = WPC_Audit::get_logs( 10, 0 );

// Siti recenti.
$sites = WPC_Sites::find_all();

// Funzione helper per formattare i bytes.
function format_bytes( int $bytes, int $decimals = 2 ): string {
    if ( $bytes === 0 ) return '0 B';
    $k = 1024;
    $sizes = [ 'B', 'KB', 'MB', 'GB', 'TB' ];
    $i = (int) floor( log( $bytes ) / log( $k ) );
    return round( $bytes / pow( $k, $i ), $decimals ) . ' ' . $sizes[ $i ];
}

include WPC_ROOT . '/templates/header.php';
?>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                    <i class="bi bi-globe"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $site_counts['total']; ?></div>
                    <div class="stat-label">Siti Totali</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $site_counts['active']; ?></div>
                    <div class="stat-label">Siti Attivi</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3">
                    <i class="bi bi-lock"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $site_counts['locked']; ?></div>
                    <div class="stat-label">Siti Bloccati</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                    <i class="bi bi-bell"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $unack_alerts; ?></div>
                    <div class="stat-label">Alert Attivi</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Secondary Stats -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-info bg-opacity-10 text-info me-3">
                    <i class="bi bi-cloud-arrow-up"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $total_backups; ?></div>
                    <div class="stat-label">Backup Totali</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-secondary bg-opacity-10 text-secondary me-3">
                    <i class="bi bi-hdd"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo format_bytes( $backup_size ); ?></div>
                    <div class="stat-label">Spazio Backup</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-dark bg-opacity-10 text-dark me-3">
                    <i class="bi bi-people"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $total_users; ?></div>
                    <div class="stat-label">Utenti</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-secondary bg-opacity-10 text-secondary me-3">
                    <i class="bi bi-wifi-off"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $site_counts['offline']; ?></div>
                    <div class="stat-label">Siti Offline</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Sites Overview -->
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-globe me-2"></i>Siti WordPress</h6>
                <a href="sites.php" class="btn btn-sm btn-outline-primary">Gestisci</a>
            </div>
            <div class="card-body p-0">
                <?php if ( empty( $sites ) ): ?>
                    <div class="empty-state">
                        <i class="bi bi-globe d-block"></i>
                        <p>Nessun sito registrato</p>
                        <a href="sites.php?action=add" class="btn btn-primary btn-sm">Aggiungi sito</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-wpc table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Sito</th>
                                    <th>Stato</th>
                                    <th>WP</th>
                                    <th>Ultimo Heartbeat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( array_slice( $sites, 0, 10 ) as $site ): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars( $site['site_name'], ENT_QUOTES, 'UTF-8' ); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars( $site['domain'] ?? '', ENT_QUOTES, 'UTF-8' ); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge badge-status-<?php echo htmlspecialchars( $site['status'], ENT_QUOTES, 'UTF-8' ); ?>">
                                            <?php echo htmlspecialchars( ucfirst( $site['status'] ), ENT_QUOTES, 'UTF-8' ); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars( $site['wp_version'] ?? '-', ENT_QUOTES, 'UTF-8' ); ?></td>
                                    <td>
                                        <?php if ( $site['last_heartbeat_at'] ): ?>
                                            <small><?php echo htmlspecialchars( $site['last_heartbeat_at'], ENT_QUOTES, 'UTF-8' ); ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">Mai</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Alerts -->
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-bell me-2"></i>Alert Recenti</h6>
                <a href="alerts.php" class="btn btn-sm btn-outline-danger">Tutti</a>
            </div>
            <div class="card-body p-0">
                <?php if ( empty( $recent_alerts ) ): ?>
                    <div class="empty-state">
                        <i class="bi bi-shield-check d-block"></i>
                        <p>Nessun alert</p>
                    </div>
                <?php else: ?>
                    <?php foreach ( $recent_alerts as $alert ): ?>
                        <div class="alert-feed-item severity-<?php echo htmlspecialchars( $alert['severity'], ENT_QUOTES, 'UTF-8' ); ?> border-bottom">
                            <div class="d-flex justify-content-between">
                                <span class="badge badge-severity-<?php echo htmlspecialchars( $alert['severity'], ENT_QUOTES, 'UTF-8' ); ?> me-2">
                                    <?php echo htmlspecialchars( $alert['severity'], ENT_QUOTES, 'UTF-8' ); ?>
                                </span>
                                <small class="text-muted"><?php echo htmlspecialchars( $alert['created_at'], ENT_QUOTES, 'UTF-8' ); ?></small>
                            </div>
                            <small class="d-block mt-1">
                                <strong><?php echo htmlspecialchars( $alert['event_type'], ENT_QUOTES, 'UTF-8' ); ?></strong>
                                <?php if ( ! empty( $alert['site_name'] ) ): ?>
                                    - <?php echo htmlspecialchars( $alert['site_name'], ENT_QUOTES, 'UTF-8' ); ?>
                                <?php endif; ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Audit Logs -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-journal-text me-2"></i>Attivita' Recenti</h6>
                <a href="audit.php" class="btn btn-sm btn-outline-secondary">Tutti i log</a>
            </div>
            <div class="card-body p-0">
                <?php if ( empty( $recent_audit ) ): ?>
                    <div class="empty-state">
                        <i class="bi bi-journal d-block"></i>
                        <p>Nessuna attivita' registrata</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-wpc table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Azione</th>
                                    <th>Descrizione</th>
                                    <th>Utente</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $recent_audit as $log ): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars( $log['action'], ENT_QUOTES, 'UTF-8' ); ?></code></td>
                                    <td><?php echo htmlspecialchars( $log['description'] ?? '', ENT_QUOTES, 'UTF-8' ); ?></td>
                                    <td><small><?php echo htmlspecialchars( $log['user_email'] ?? '-', ENT_QUOTES, 'UTF-8' ); ?></small></td>
                                    <td><small><?php echo htmlspecialchars( $log['created_at'], ENT_QUOTES, 'UTF-8' ); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include WPC_ROOT . '/templates/footer.php'; ?>
