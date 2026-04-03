<?php
/**
 * WP Control Center - Gestione Siti.
 */

define( 'WPC_ROOT', __DIR__ );
require_once WPC_ROOT . '/includes/bootstrap.php';
WPC_Auth::require_auth();

$page_title = 'Gestione Siti';
$action     = $_GET['action'] ?? 'list';
$message    = '';
$error      = '';

// --- Azioni POST ---
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    if ( ! WPC_Auth::verify_csrf( $_POST['csrf_token'] ?? '' ) ) {
        $error = 'Token CSRF non valido.';
    } else {
        $post_action = $_POST['action'] ?? '';

        switch ( $post_action ) {
            case 'register':
                $site_name = trim( $_POST['site_name'] ?? '' );
                $site_url  = trim( $_POST['site_url'] ?? '' );

                if ( empty( $site_name ) || empty( $site_url ) ) {
                    $error = 'Nome e URL del sito sono obbligatori.';
                } else {
                    $api_token = bin2hex( random_bytes( 32 ) );
                    $result = WPC_Sites::register( $site_name, $site_url, $api_token );
                    $message = "Sito registrato con successo! Site ID: <code>{$result['site_id']}</code><br>API Token: <code>{$result['api_token']}</code><br><strong>Conserva il token, non verra' mostrato di nuovo.</strong>";
                }
                break;

            case 'lock':
                $site_id = $_POST['site_id'] ?? '';
                if ( WPC_Sites::update_lock_status( $site_id, true, WPC_Auth::current_user_id() ) ) {
                    $message = 'Comando di blocco inviato. Il sito sara\' bloccato al prossimo heartbeat del plugin (entro pochi minuti).';
                } else {
                    $error = 'Errore nel blocco del sito.';
                }
                break;

            case 'unlock':
                $site_id = $_POST['site_id'] ?? '';
                if ( WPC_Sites::update_lock_status( $site_id, false, WPC_Auth::current_user_id() ) ) {
                    $message = 'Comando di sblocco inviato. Il sito sara\' sbloccato al prossimo heartbeat del plugin (entro pochi minuti).';
                } else {
                    $error = 'Errore nello sblocco del sito.';
                }
                break;

            case 'delete':
                if ( ! WPC_Auth::is_admin() ) {
                    $error = 'Solo gli admin possono eliminare i siti.';
                } else {
                    $site_id = $_POST['site_id'] ?? '';
                    WPC_Sites::remove( $site_id );
                    $message = 'Sito eliminato con successo.';
                }
                break;

            case 'rotate_credentials':
                $site_id = $_POST['site_id'] ?? '';
                $new_token = WPC_Sites::rotate_credentials( $site_id );
                if ( $new_token ) {
                    $message = "Credenziali ruotate. Nuovo token: <code>{$new_token}</code><br><strong>Aggiorna il token nel plugin WordPress.</strong>";
                } else {
                    $error = 'Errore nella rotazione delle credenziali.';
                }
                break;
        }
    }
}

$sites = WPC_Sites::find_all();

include WPC_ROOT . '/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-globe me-2"></i>Siti WordPress</h4>
    <a href="sites.php?action=add" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Aggiungi Sito
    </a>
</div>

<?php if ( $message ): ?>
    <div class="alert alert-success alert-dismissible fade show"><?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ( $error ): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?php echo htmlspecialchars( $error, ENT_QUOTES, 'UTF-8' ); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ( $action === 'add' ): ?>
<!-- Form Aggiunta Sito -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0">Registra Nuovo Sito</h6>
    </div>
    <div class="card-body">
        <form method="POST" action="sites.php">
            <?php echo WPC_Auth::csrf_field(); ?>
            <input type="hidden" name="action" value="register">

            <div class="mb-3">
                <label for="site_name" class="form-label">Nome Sito *</label>
                <input type="text" class="form-control" id="site_name" name="site_name" required placeholder="Il Mio Sito WordPress">
            </div>

            <div class="mb-3">
                <label for="site_url" class="form-label">URL Sito *</label>
                <input type="url" class="form-control" id="site_url" name="site_url" required placeholder="https://www.example.com">
                <small class="form-text text-muted">L'URL completo del sito WordPress con protocollo HTTPS.</small>
            </div>

            <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Registra</button>
            <a href="sites.php" class="btn btn-secondary ms-2">Annulla</a>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ( $action === 'view' && isset( $_GET['id'] ) ):
    $site = WPC_Sites::find_by_site_id( $_GET['id'] );
    if ( $site ):
        $site_backups = WPC_Backups::find_by_site_id( $site['site_id'] );
        $site_alerts  = WPC_Alerts::find_by_site_id( $site['site_id'] );
?>
<!-- Dettaglio Sito -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><?php echo htmlspecialchars( $site['site_name'], ENT_QUOTES, 'UTF-8' ); ?></h6>
        <span class="badge badge-status-<?php echo htmlspecialchars( $site['status'], ENT_QUOTES, 'UTF-8' ); ?>">
            <?php echo htmlspecialchars( ucfirst( $site['status'] ), ENT_QUOTES, 'UTF-8' ); ?>
        </span>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr><th>Site ID</th><td><code><?php echo htmlspecialchars( $site['site_id'], ENT_QUOTES, 'UTF-8' ); ?></code></td></tr>
                    <tr><th>URL</th><td><a href="<?php echo htmlspecialchars( $site['site_url'], ENT_QUOTES, 'UTF-8' ); ?>" target="_blank"><?php echo htmlspecialchars( $site['site_url'], ENT_QUOTES, 'UTF-8' ); ?></a></td></tr>
                    <tr><th>Dominio</th><td><?php echo htmlspecialchars( $site['domain'] ?? '-', ENT_QUOTES, 'UTF-8' ); ?></td></tr>
                    <tr><th>WordPress</th><td><?php echo htmlspecialchars( $site['wp_version'] ?? '-', ENT_QUOTES, 'UTF-8' ); ?></td></tr>
                    <tr><th>PHP</th><td><?php echo htmlspecialchars( $site['php_version'] ?? '-', ENT_QUOTES, 'UTF-8' ); ?></td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr><th>Tema Attivo</th><td><?php echo htmlspecialchars( $site['active_theme'] ?? '-', ENT_QUOTES, 'UTF-8' ); ?></td></tr>
                    <tr><th>Plugin</th><td><?php echo (int) $site['plugin_count']; ?></td></tr>
                    <tr><th>Ultimo Heartbeat</th><td><?php echo htmlspecialchars( $site['last_heartbeat_at'] ?? 'Mai', ENT_QUOTES, 'UTF-8' ); ?></td></tr>
                    <tr><th>Creato</th><td><?php echo htmlspecialchars( $site['created_at'], ENT_QUOTES, 'UTF-8' ); ?></td></tr>
                    <tr><th>Bloccato</th><td><?php echo $site['is_locked'] ? '<span class="text-danger">Si</span>' : '<span class="text-success">No</span>'; ?></td></tr>
                </table>
            </div>
        </div>

        <!-- Azioni -->
        <div class="mt-3">
            <?php if ( ! $site['is_locked'] ): ?>
                <form method="POST" action="sites.php" class="d-inline">
                    <?php echo WPC_Auth::csrf_field(); ?>
                    <input type="hidden" name="action" value="lock">
                    <input type="hidden" name="site_id" value="<?php echo htmlspecialchars( $site['site_id'], ENT_QUOTES, 'UTF-8' ); ?>">
                    <button type="submit" class="btn btn-danger btn-sm" data-confirm="Sei sicuro di voler bloccare questo sito?">
                        <i class="bi bi-lock me-1"></i>Blocca Sito
                    </button>
                </form>
            <?php else: ?>
                <form method="POST" action="sites.php" class="d-inline">
                    <?php echo WPC_Auth::csrf_field(); ?>
                    <input type="hidden" name="action" value="unlock">
                    <input type="hidden" name="site_id" value="<?php echo htmlspecialchars( $site['site_id'], ENT_QUOTES, 'UTF-8' ); ?>">
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="bi bi-unlock me-1"></i>Sblocca Sito
                    </button>
                </form>
            <?php endif; ?>

            <form method="POST" action="sites.php" class="d-inline ms-2">
                <?php echo WPC_Auth::csrf_field(); ?>
                <input type="hidden" name="action" value="rotate_credentials">
                <input type="hidden" name="site_id" value="<?php echo htmlspecialchars( $site['site_id'], ENT_QUOTES, 'UTF-8' ); ?>">
                <button type="submit" class="btn btn-warning btn-sm" data-confirm="Ruotare le credenziali? Il plugin dovra' essere aggiornato.">
                    <i class="bi bi-arrow-repeat me-1"></i>Ruota Credenziali
                </button>
            </form>

            <?php if ( WPC_Auth::is_admin() ): ?>
                <form method="POST" action="sites.php" class="d-inline ms-2">
                    <?php echo WPC_Auth::csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="site_id" value="<?php echo htmlspecialchars( $site['site_id'], ENT_QUOTES, 'UTF-8' ); ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm" data-confirm="ATTENZIONE: Eliminare definitivamente questo sito?">
                        <i class="bi bi-trash me-1"></i>Elimina
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Backup del sito -->
<?php if ( ! empty( $site_backups ) ): ?>
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0"><i class="bi bi-cloud-arrow-up me-2"></i>Backup (<?php echo count( $site_backups ); ?>)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-wpc table-hover mb-0">
                <thead><tr><th>Backup ID</th><th>Stato</th><th>Dimensione</th><th>Data</th></tr></thead>
                <tbody>
                    <?php foreach ( $site_backups as $bk ): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars( substr( $bk['backup_id'], 0, 8 ), ENT_QUOTES, 'UTF-8' ); ?>...</code></td>
                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars( $bk['status'], ENT_QUOTES, 'UTF-8' ); ?></span></td>
                        <td><?php echo format_bytes( (int) $bk['file_size'] ); ?></td>
                        <td><?php echo htmlspecialchars( $bk['created_at'], ENT_QUOTES, 'UTF-8' ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Alert del sito -->
<?php if ( ! empty( $site_alerts ) ): ?>
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0"><i class="bi bi-bell me-2"></i>Alert (<?php echo count( $site_alerts ); ?>)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-wpc table-hover mb-0">
                <thead><tr><th>Tipo</th><th>Severita'</th><th>Descrizione</th><th>Data</th></tr></thead>
                <tbody>
                    <?php foreach ( array_slice( $site_alerts, 0, 10 ) as $al ): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars( $al['event_type'], ENT_QUOTES, 'UTF-8' ); ?></code></td>
                        <td><span class="badge badge-severity-<?php echo htmlspecialchars( $al['severity'], ENT_QUOTES, 'UTF-8' ); ?>"><?php echo htmlspecialchars( $al['severity'], ENT_QUOTES, 'UTF-8' ); ?></span></td>
                        <td><?php echo htmlspecialchars( $al['description'] ?? '', ENT_QUOTES, 'UTF-8' ); ?></td>
                        <td><?php echo htmlspecialchars( $al['created_at'], ENT_QUOTES, 'UTF-8' ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
    endif; // if ( $site )
endif; // if view
?>

<!-- Lista Siti -->
<?php if ( $action === 'list' ): ?>
<div class="card shadow-sm">
    <div class="card-body p-0">
        <?php if ( empty( $sites ) ): ?>
            <div class="empty-state">
                <i class="bi bi-globe d-block"></i>
                <p>Nessun sito registrato</p>
                <a href="sites.php?action=add" class="btn btn-primary btn-sm">Aggiungi il primo sito</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-wpc table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>URL</th>
                            <th>Stato</th>
                            <th>WP</th>
                            <th>PHP</th>
                            <th>Heartbeat</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $sites as $site ): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars( $site['site_name'], ENT_QUOTES, 'UTF-8' ); ?></strong></td>
                            <td><a href="<?php echo htmlspecialchars( $site['site_url'], ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" class="text-truncate d-inline-block" style="max-width:200px;"><?php echo htmlspecialchars( $site['domain'] ?? $site['site_url'], ENT_QUOTES, 'UTF-8' ); ?></a></td>
                            <td><span class="badge badge-status-<?php echo htmlspecialchars( $site['status'], ENT_QUOTES, 'UTF-8' ); ?>"><?php echo htmlspecialchars( ucfirst( $site['status'] ), ENT_QUOTES, 'UTF-8' ); ?></span></td>
                            <td><?php echo htmlspecialchars( $site['wp_version'] ?? '-', ENT_QUOTES, 'UTF-8' ); ?></td>
                            <td><?php echo htmlspecialchars( $site['php_version'] ?? '-', ENT_QUOTES, 'UTF-8' ); ?></td>
                            <td><small><?php echo $site['last_heartbeat_at'] ? htmlspecialchars( $site['last_heartbeat_at'], ENT_QUOTES, 'UTF-8' ) : '<span class="text-muted">Mai</span>'; ?></small></td>
                            <td>
                                <a href="sites.php?action=view&id=<?php echo urlencode( $site['site_id'] ); ?>" class="btn btn-sm btn-outline-primary" title="Dettagli">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php
// Helper per format_bytes se non definito.
if ( ! function_exists( 'format_bytes' ) ) {
    function format_bytes( int $bytes, int $decimals = 2 ): string {
        if ( $bytes === 0 ) return '0 B';
        $k = 1024;
        $sizes = [ 'B', 'KB', 'MB', 'GB', 'TB' ];
        $i = (int) floor( log( $bytes ) / log( $k ) );
        return round( $bytes / pow( $k, $i ), $decimals ) . ' ' . $sizes[ $i ];
    }
}

include WPC_ROOT . '/templates/footer.php';
?>
