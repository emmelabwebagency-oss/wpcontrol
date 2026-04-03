<?php
/**
 * WP Control Center - Header Template.
 */
if ( ! defined( 'WPC_ROOT' ) ) {
    exit( 'Accesso diretto non consentito.' );
}

$current_page   = basename( $_SERVER['SCRIPT_NAME'], '.php' );
$unack_alerts   = 0;
try {
    $unack_alerts = WPC_Alerts::count_unacknowledged();
} catch ( Exception $e ) {
    // Ignora errori se la tabella non esiste ancora.
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars( $page_title ?? 'Dashboard', ENT_QUOTES, 'UTF-8' ); ?> - <?php echo WPC_APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="d-flex" id="wrapper">
    <!-- Sidebar -->
    <div class="bg-dark text-white" id="sidebar" style="min-width:250px;max-width:250px;min-height:100vh;">
        <div class="p-3 border-bottom border-secondary">
            <h5 class="mb-0"><i class="bi bi-shield-lock me-2"></i><?php echo WPC_APP_NAME; ?></h5>
            <small class="text-muted">v<?php echo WPC_APP_VERSION; ?></small>
        </div>
        <nav class="p-3">
            <ul class="nav flex-column">
                <li class="nav-item mb-1">
                    <a class="nav-link text-white <?php echo $current_page === 'index' ? 'active bg-primary rounded' : ''; ?>" href="index.php">
                        <i class="bi bi-speedometer2 me-2"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item mb-1">
                    <a class="nav-link text-white <?php echo $current_page === 'sites' ? 'active bg-primary rounded' : ''; ?>" href="sites.php">
                        <i class="bi bi-globe me-2"></i>Siti
                    </a>
                </li>
                <li class="nav-item mb-1">
                    <a class="nav-link text-white <?php echo $current_page === 'backups' ? 'active bg-primary rounded' : ''; ?>" href="backups.php">
                        <i class="bi bi-cloud-arrow-up me-2"></i>Backup
                    </a>
                </li>
                <li class="nav-item mb-1">
                    <a class="nav-link text-white <?php echo $current_page === 'alerts' ? 'active bg-primary rounded' : ''; ?>" href="alerts.php">
                        <i class="bi bi-bell me-2"></i>Alert
                        <?php if ( $unack_alerts > 0 ): ?>
                            <span class="badge bg-danger ms-1"><?php echo $unack_alerts; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item mb-1">
                    <a class="nav-link text-white <?php echo $current_page === 'audit' ? 'active bg-primary rounded' : ''; ?>" href="audit.php">
                        <i class="bi bi-journal-text me-2"></i>Audit Log
                    </a>
                </li>
                <?php if ( WPC_Auth::is_admin() ): ?>
                <li class="nav-item mb-1">
                    <a class="nav-link text-white <?php echo $current_page === 'users' ? 'active bg-primary rounded' : ''; ?>" href="users.php">
                        <i class="bi bi-people me-2"></i>Utenti
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <div class="p-3 mt-auto border-top border-secondary position-absolute bottom-0 w-100" style="max-width:250px;">
            <div class="d-flex align-items-center">
                <i class="bi bi-person-circle me-2"></i>
                <div class="flex-grow-1 text-truncate">
                    <small class="d-block text-truncate"><?php echo htmlspecialchars( WPC_Auth::current_user_name() ?? '', ENT_QUOTES, 'UTF-8' ); ?></small>
                    <small class="text-muted text-truncate d-block"><?php echo htmlspecialchars( WPC_Auth::current_user_role() ?? '', ENT_QUOTES, 'UTF-8' ); ?></small>
                </div>
                <a href="logout.php" class="text-white" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
            </div>
        </div>
    </div>

    <!-- Page Content -->
    <div id="page-content" class="flex-grow-1">
        <nav class="navbar navbar-light bg-white shadow-sm px-4">
            <span class="navbar-text">
                <i class="bi bi-calendar me-1"></i><?php echo date( 'd/m/Y H:i' ); ?>
            </span>
            <div class="d-flex align-items-center">
                <?php if ( $unack_alerts > 0 ): ?>
                    <a href="alerts.php" class="btn btn-outline-danger btn-sm me-2">
                        <i class="bi bi-bell-fill"></i> <?php echo $unack_alerts; ?> alert
                    </a>
                <?php endif; ?>
                <span class="text-muted small"><?php echo htmlspecialchars( WPC_Auth::current_user_email() ?? '', ENT_QUOTES, 'UTF-8' ); ?></span>
            </div>
        </nav>
        <div class="p-4">
