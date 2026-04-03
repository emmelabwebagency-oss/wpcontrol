<?php
/**
 * WP Control Center - Gestione Utenti (solo admin).
 */

define( 'WPC_ROOT', __DIR__ );
require_once WPC_ROOT . '/includes/bootstrap.php';
WPC_Auth::require_admin();

$page_title = 'Gestione Utenti';
$action     = $_GET['action'] ?? 'list';
$message    = '';
$error      = '';

// Azioni POST.
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && WPC_Auth::verify_csrf( $_POST['csrf_token'] ?? '' ) ) {
    $post_action = $_POST['action'] ?? '';

    switch ( $post_action ) {
        case 'create':
            $email      = trim( $_POST['email'] ?? '' );
            $password   = $_POST['password'] ?? '';
            $role       = $_POST['role'] ?? 'viewer';
            $first_name = trim( $_POST['first_name'] ?? '' );
            $last_name  = trim( $_POST['last_name'] ?? '' );

            if ( empty( $email ) || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
                $error = 'Email non valida.';
            } elseif ( strlen( $password ) < 8 ) {
                $error = 'La password deve essere di almeno 8 caratteri.';
            } elseif ( WPC_Users::find_by_email( $email ) ) {
                $error = 'Un utente con questa email esiste gia\'.';
            } elseif ( ! in_array( $role, [ 'admin', 'operator', 'viewer' ], true ) ) {
                $error = 'Ruolo non valido.';
            } else {
                WPC_Users::create( $email, $password, $role, $first_name, $last_name );
                $message = "Utente {$email} creato con successo.";
            }
            break;

        case 'update':
            $user_id    = $_POST['user_id'] ?? '';
            $data       = [
                'email'      => trim( $_POST['email'] ?? '' ),
                'role'       => $_POST['role'] ?? 'viewer',
                'first_name' => trim( $_POST['first_name'] ?? '' ),
                'last_name'  => trim( $_POST['last_name'] ?? '' ),
            ];
            if ( ! empty( $_POST['password'] ) ) {
                if ( strlen( $_POST['password'] ) < 8 ) {
                    $error = 'La password deve essere di almeno 8 caratteri.';
                    break;
                }
                $data['password'] = $_POST['password'];
            }
            if ( WPC_Users::update( $user_id, $data ) ) {
                $message = 'Utente aggiornato.';
            } else {
                $error = 'Errore nell\'aggiornamento.';
            }
            break;

        case 'deactivate':
            $user_id = $_POST['user_id'] ?? '';
            if ( $user_id === WPC_Auth::current_user_id() ) {
                $error = 'Non puoi disattivare te stesso.';
            } elseif ( WPC_Users::deactivate( $user_id ) ) {
                $message = 'Utente disattivato.';
            } else {
                $error = 'Errore nella disattivazione.';
            }
            break;

        case 'activate':
            $user_id = $_POST['user_id'] ?? '';
            if ( WPC_Users::activate( $user_id ) ) {
                $message = 'Utente riattivato.';
            } else {
                $error = 'Errore nella riattivazione.';
            }
            break;
    }
}

$users = WPC_Users::find_all();

include WPC_ROOT . '/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-people me-2"></i>Utenti</h4>
    <a href="users.php?action=add" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i>Nuovo Utente
    </a>
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

<?php if ( $action === 'add' ): ?>
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0">Nuovo Utente</h6>
    </div>
    <div class="card-body">
        <form method="POST" action="users.php">
            <?php echo WPC_Auth::csrf_field(); ?>
            <input type="hidden" name="action" value="create">

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="first_name" class="form-label">Nome</label>
                    <input type="text" class="form-control" id="first_name" name="first_name">
                </div>
                <div class="col-md-6">
                    <label for="last_name" class="form-label">Cognome</label>
                    <input type="text" class="form-control" id="last_name" name="last_name">
                </div>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email *</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password * (minimo 8 caratteri)</label>
                <input type="password" class="form-control" id="password" name="password" required minlength="8">
            </div>

            <div class="mb-3">
                <label for="role" class="form-label">Ruolo *</label>
                <select class="form-select" id="role" name="role">
                    <option value="viewer">Viewer - Solo lettura</option>
                    <option value="operator">Operator - Gestione siti e backup</option>
                    <option value="admin">Admin - Accesso completo</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Crea</button>
            <a href="users.php" class="btn btn-secondary ms-2">Annulla</a>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ( $action === 'edit' && isset( $_GET['id'] ) ):
    $edit_user = WPC_Users::find_by_id( $_GET['id'] );
    if ( $edit_user ):
?>
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0">Modifica Utente</h6>
    </div>
    <div class="card-body">
        <form method="POST" action="users.php">
            <?php echo WPC_Auth::csrf_field(); ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars( $edit_user['id'], ENT_QUOTES, 'UTF-8' ); ?>">

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="first_name" class="form-label">Nome</label>
                    <input type="text" class="form-control" id="first_name" name="first_name"
                        value="<?php echo htmlspecialchars( $edit_user['first_name'] ?? '', ENT_QUOTES, 'UTF-8' ); ?>">
                </div>
                <div class="col-md-6">
                    <label for="last_name" class="form-label">Cognome</label>
                    <input type="text" class="form-control" id="last_name" name="last_name"
                        value="<?php echo htmlspecialchars( $edit_user['last_name'] ?? '', ENT_QUOTES, 'UTF-8' ); ?>">
                </div>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email *</label>
                <input type="email" class="form-control" id="email" name="email" required
                    value="<?php echo htmlspecialchars( $edit_user['email'], ENT_QUOTES, 'UTF-8' ); ?>">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Nuova Password (lascia vuoto per non cambiare)</label>
                <input type="password" class="form-control" id="password" name="password" minlength="8">
            </div>

            <div class="mb-3">
                <label for="role" class="form-label">Ruolo *</label>
                <select class="form-select" id="role" name="role">
                    <option value="viewer" <?php echo $edit_user['role'] === 'viewer' ? 'selected' : ''; ?>>Viewer</option>
                    <option value="operator" <?php echo $edit_user['role'] === 'operator' ? 'selected' : ''; ?>>Operator</option>
                    <option value="admin" <?php echo $edit_user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Salva</button>
            <a href="users.php" class="btn btn-secondary ms-2">Annulla</a>
        </form>
    </div>
</div>
<?php
    endif;
endif;
?>

<!-- Lista Utenti -->
<?php if ( $action === 'list' ): ?>
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-wpc table-hover mb-0">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Ruolo</th>
                        <th>Stato</th>
                        <th>Ultimo Login</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $users as $user ): ?>
                    <tr>
                        <td><?php echo htmlspecialchars( trim( $user['first_name'] . ' ' . $user['last_name'] ) ?: '-', ENT_QUOTES, 'UTF-8' ); ?></td>
                        <td><?php echo htmlspecialchars( $user['email'], ENT_QUOTES, 'UTF-8' ); ?></td>
                        <td>
                            <span class="badge <?php echo $user['role'] === 'admin' ? 'bg-danger' : ( $user['role'] === 'operator' ? 'bg-warning text-dark' : 'bg-secondary' ); ?>">
                                <?php echo htmlspecialchars( ucfirst( $user['role'] ), ENT_QUOTES, 'UTF-8' ); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ( $user['is_active'] ): ?>
                                <span class="badge bg-success">Attivo</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Disattivato</span>
                            <?php endif; ?>
                        </td>
                        <td><small><?php echo htmlspecialchars( $user['last_login_at'] ?? 'Mai', ENT_QUOTES, 'UTF-8' ); ?></small></td>
                        <td>
                            <a href="users.php?action=edit&id=<?php echo urlencode( $user['id'] ); ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if ( $user['id'] !== WPC_Auth::current_user_id() ): ?>
                                <?php if ( $user['is_active'] ): ?>
                                    <form method="POST" action="" class="d-inline">
                                        <?php echo WPC_Auth::csrf_field(); ?>
                                        <input type="hidden" name="action" value="deactivate">
                                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars( $user['id'], ENT_QUOTES, 'UTF-8' ); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Disattivare questo utente?">
                                            <i class="bi bi-person-x"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="" class="d-inline">
                                        <?php echo WPC_Auth::csrf_field(); ?>
                                        <input type="hidden" name="action" value="activate">
                                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars( $user['id'], ENT_QUOTES, 'UTF-8' ); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-person-check"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include WPC_ROOT . '/templates/footer.php'; ?>
