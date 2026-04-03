<?php
/**
 * WP Control Center - Pagina di Login.
 */

define( 'WPC_ROOT', __DIR__ );
require_once WPC_ROOT . '/includes/bootstrap.php';

// Se gia' autenticato, vai alla dashboard.
if ( WPC_Auth::is_logged_in() ) {
    header( 'Location: index.php' );
    exit;
}

$error   = '';
$expired = isset( $_GET['expired'] );

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $email    = trim( $_POST['email'] ?? '' );
    $password = $_POST['password'] ?? '';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $result = WPC_Auth::login( $email, $password, $ip );

    if ( $result['success'] ) {
        header( 'Location: index.php' );
        exit;
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo WPC_APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="login-container">
    <div class="login-card card">
        <div class="card-body text-center">
            <div class="login-logo mb-3">
                <i class="bi bi-shield-lock"></i>
            </div>
            <h4 class="mb-1"><?php echo WPC_APP_NAME; ?></h4>
            <p class="text-muted mb-4">Accedi al pannello di controllo</p>

            <?php if ( $error ): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars( $error, ENT_QUOTES, 'UTF-8' ); ?></div>
            <?php endif; ?>

            <?php if ( $expired ): ?>
                <div class="alert alert-warning">La sessione e' scaduta. Effettua di nuovo il login.</div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3 text-start">
                    <label for="email" class="form-label">Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" required autofocus
                            value="<?php echo htmlspecialchars( $_POST['email'] ?? '', ENT_QUOTES, 'UTF-8' ); ?>"
                            placeholder="admin@example.com">
                    </div>
                </div>

                <div class="mb-4 text-start">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required
                            placeholder="La tua password">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Accedi
                </button>
            </form>

            <p class="text-muted small mt-3 mb-0">
                <?php echo WPC_APP_NAME; ?> v<?php echo WPC_APP_VERSION; ?>
            </p>
        </div>
    </div>
</div>
</body>
</html>
