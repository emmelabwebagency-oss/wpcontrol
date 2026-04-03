<?php
/**
 * WP Control Center - Script di installazione.
 * Crea le tabelle MySQL e l'utente admin iniziale.
 *
 * IMPORTANTE: Elimina questo file dopo l'installazione!
 */

define( 'WPC_ROOT', __DIR__ );
require_once WPC_ROOT . '/config/config.php';
require_once WPC_ROOT . '/includes/Database.php';

$errors  = [];
$success = false;

// Gestione del form di installazione.
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $admin_email    = trim( $_POST['admin_email'] ?? '' );
    $admin_password = $_POST['admin_password'] ?? '';
    $admin_confirm  = $_POST['admin_confirm'] ?? '';
    $admin_first    = trim( $_POST['admin_first'] ?? '' );
    $admin_last     = trim( $_POST['admin_last'] ?? '' );

    // Validazione.
    if ( empty( $admin_email ) || ! filter_var( $admin_email, FILTER_VALIDATE_EMAIL ) ) {
        $errors[] = 'Email non valida.';
    }
    if ( strlen( $admin_password ) < 8 ) {
        $errors[] = 'La password deve essere di almeno 8 caratteri.';
    }
    if ( $admin_password !== $admin_confirm ) {
        $errors[] = 'Le password non coincidono.';
    }

    if ( empty( $errors ) ) {
        try {
            $db  = WPC_Database::get_instance();
            $pdo = $db->get_pdo();

            // Crea le tabelle.
            $pdo->exec( "
                CREATE TABLE IF NOT EXISTS users (
                    id VARCHAR(36) PRIMARY KEY,
                    email VARCHAR(255) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    role ENUM('admin','operator','viewer') NOT NULL DEFAULT 'viewer',
                    first_name VARCHAR(100) DEFAULT '',
                    last_name VARCHAR(100) DEFAULT '',
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    mfa_enabled TINYINT(1) NOT NULL DEFAULT 0,
                    mfa_secret VARCHAR(255) DEFAULT NULL,
                    last_login_at DATETIME DEFAULT NULL,
                    last_login_ip VARCHAR(45) DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_users_email (email),
                    INDEX idx_users_role (role)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            " );

            $pdo->exec( "
                CREATE TABLE IF NOT EXISTS sites (
                    id VARCHAR(36) PRIMARY KEY,
                    site_id VARCHAR(36) NOT NULL UNIQUE,
                    site_name VARCHAR(255) NOT NULL,
                    site_url VARCHAR(500) NOT NULL,
                    domain VARCHAR(255) DEFAULT NULL,
                    api_token_hash VARCHAR(255) NOT NULL,
                    status ENUM('active','locked','restricted','offline','pending') NOT NULL DEFAULT 'pending',
                    is_locked TINYINT(1) NOT NULL DEFAULT 0,
                    locked_at DATETIME DEFAULT NULL,
                    locked_by VARCHAR(36) DEFAULT NULL,
                    wp_version VARCHAR(20) DEFAULT NULL,
                    php_version VARCHAR(20) DEFAULT NULL,
                    active_theme VARCHAR(255) DEFAULT NULL,
                    plugin_count INT DEFAULT 0,
                    last_heartbeat_at DATETIME DEFAULT NULL,
                    ownership_enforcement TINYINT(1) NOT NULL DEFAULT 1,
                    grace_period_hours INT NOT NULL DEFAULT 72,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_sites_site_id (site_id),
                    INDEX idx_sites_status (status),
                    INDEX idx_sites_domain (domain)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            " );

            $pdo->exec( "
                CREATE TABLE IF NOT EXISTS backups (
                    id VARCHAR(36) PRIMARY KEY,
                    backup_id VARCHAR(36) NOT NULL UNIQUE,
                    site_id VARCHAR(36) NOT NULL,
                    file_path VARCHAR(500) DEFAULT NULL,
                    file_size BIGINT DEFAULT 0,
                    checksum VARCHAR(128) DEFAULT NULL,
                    wp_version VARCHAR(20) DEFAULT NULL,
                    php_version VARCHAR(20) DEFAULT NULL,
                    active_theme VARCHAR(255) DEFAULT NULL,
                    plugin_list JSON DEFAULT NULL,
                    encrypted TINYINT(1) NOT NULL DEFAULT 0,
                    status ENUM('pending','uploading','stored','restore_requested','restored','failed','deleted') NOT NULL DEFAULT 'pending',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_backups_site_id (site_id),
                    INDEX idx_backups_backup_id (backup_id),
                    INDEX idx_backups_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            " );

            $pdo->exec( "
                CREATE TABLE IF NOT EXISTS alerts (
                    id VARCHAR(36) PRIMARY KEY,
                    site_id VARCHAR(36) NOT NULL,
                    event_type VARCHAR(100) NOT NULL,
                    severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
                    description TEXT DEFAULT NULL,
                    details JSON DEFAULT NULL,
                    ip_address VARCHAR(45) DEFAULT NULL,
                    acknowledged TINYINT(1) NOT NULL DEFAULT 0,
                    acknowledged_by VARCHAR(36) DEFAULT NULL,
                    acknowledged_at DATETIME DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_alerts_site_id (site_id),
                    INDEX idx_alerts_severity (severity),
                    INDEX idx_alerts_acknowledged (acknowledged),
                    INDEX idx_alerts_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            " );

            $pdo->exec( "
                CREATE TABLE IF NOT EXISTS audit_logs (
                    id VARCHAR(36) PRIMARY KEY,
                    action VARCHAR(100) NOT NULL,
                    description TEXT DEFAULT NULL,
                    user_id VARCHAR(36) DEFAULT NULL,
                    user_email VARCHAR(255) DEFAULT NULL,
                    site_id VARCHAR(36) DEFAULT NULL,
                    ip_address VARCHAR(45) DEFAULT NULL,
                    metadata JSON DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_audit_action (action),
                    INDEX idx_audit_user_id (user_id),
                    INDEX idx_audit_site_id (site_id),
                    INDEX idx_audit_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            " );

            $pdo->exec( "
                CREATE TABLE IF NOT EXISTS login_attempts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ip_address VARCHAR(45) NOT NULL,
                    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_login_ip (ip_address),
                    INDEX idx_login_time (attempted_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            " );

            $pdo->exec( "
                CREATE TABLE IF NOT EXISTS used_nonces (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nonce VARCHAR(64) NOT NULL,
                    site_id VARCHAR(36) NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE INDEX idx_nonce_unique (nonce, site_id),
                    INDEX idx_nonce_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            " );

            // Crea l'utente admin.
            $admin_id = bin2hex( random_bytes( 18 ) );
            $admin_id = substr( $admin_id, 0, 8 ) . '-' . substr( $admin_id, 8, 4 ) . '-' . substr( $admin_id, 12, 4 ) . '-' . substr( $admin_id, 16, 4 ) . '-' . substr( $admin_id, 20, 12 );

            $stmt = $pdo->prepare( "INSERT INTO users (id, email, password_hash, role, first_name, last_name, is_active, created_at, updated_at) VALUES (?, ?, ?, 'admin', ?, ?, 1, NOW(), NOW())" );
            $stmt->execute( [
                $admin_id,
                $admin_email,
                password_hash( $admin_password, PASSWORD_BCRYPT, [ 'cost' => 12 ] ),
                $admin_first,
                $admin_last,
            ] );

            // Log di audit per l'installazione.
            $audit_id = bin2hex( random_bytes( 18 ) );
            $audit_id = substr( $audit_id, 0, 8 ) . '-' . substr( $audit_id, 8, 4 ) . '-' . substr( $audit_id, 12, 4 ) . '-' . substr( $audit_id, 16, 4 ) . '-' . substr( $audit_id, 20, 12 );

            $stmt = $pdo->prepare( "INSERT INTO audit_logs (id, action, description, user_email, ip_address, created_at) VALUES (?, 'system_installed', 'WP Control Center installato', ?, ?, NOW())" );
            $stmt->execute( [
                $audit_id,
                $admin_email,
                $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            ] );

            $success = true;

        } catch ( PDOException $e ) {
            $errors[] = 'Errore database: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installazione - WP Control Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; }
        .install-card { max-width: 600px; margin: 60px auto; }
    </style>
</head>
<body>
<div class="container">
    <div class="install-card">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h2 class="text-center mb-4">
                    <i class="bi bi-shield-lock"></i> WP Control Center
                </h2>
                <h5 class="text-center text-muted mb-4">Installazione</h5>

                <?php if ( $success ): ?>
                    <div class="alert alert-success">
                        <h5>Installazione completata!</h5>
                        <p>Il database e' stato configurato e l'utente admin e' stato creato.</p>
                        <p><strong>IMPORTANTE:</strong> Elimina il file <code>install.php</code> per motivi di sicurezza.</p>
                        <a href="login.php" class="btn btn-primary">Vai al Login</a>
                    </div>
                <?php else: ?>

                    <?php if ( ! empty( $errors ) ): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ( $errors as $err ): ?>
                                    <li><?php echo htmlspecialchars( $err, ENT_QUOTES, 'UTF-8' ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="alert alert-info">
                        <strong>Prerequisiti:</strong>
                        <ul class="mb-0">
                            <li>PHP 8.1+ con estensioni PDO, pdo_mysql, mbstring, json, curl</li>
                            <li>Database MySQL 5.7+ / MariaDB 10.3+</li>
                            <li>Configurare <code>config/config.php</code> con i dati del database</li>
                        </ul>
                    </div>

                    <form method="POST" action="">
                        <h6 class="mb-3">Crea utente amministratore</h6>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="admin_first" class="form-label">Nome</label>
                                <input type="text" class="form-control" id="admin_first" name="admin_first"
                                    value="<?php echo htmlspecialchars( $_POST['admin_first'] ?? '', ENT_QUOTES, 'UTF-8' ); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="admin_last" class="form-label">Cognome</label>
                                <input type="text" class="form-control" id="admin_last" name="admin_last"
                                    value="<?php echo htmlspecialchars( $_POST['admin_last'] ?? '', ENT_QUOTES, 'UTF-8' ); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="admin_email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="admin_email" name="admin_email" required
                                value="<?php echo htmlspecialchars( $_POST['admin_email'] ?? '', ENT_QUOTES, 'UTF-8' ); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="admin_password" class="form-label">Password * (minimo 8 caratteri)</label>
                            <input type="password" class="form-control" id="admin_password" name="admin_password" required minlength="8">
                        </div>

                        <div class="mb-3">
                            <label for="admin_confirm" class="form-label">Conferma Password *</label>
                            <input type="password" class="form-control" id="admin_confirm" name="admin_confirm" required minlength="8">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Installa</button>
                    </form>

                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
