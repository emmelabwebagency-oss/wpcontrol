<?php
/**
 * WP Control Center - Sistema di autenticazione.
 * Gestisce login, sessioni, CSRF e rate limiting.
 */

if ( ! defined( 'WPC_ROOT' ) ) {
    exit( 'Accesso diretto non consentito.' );
}

class WPC_Auth {

    /**
     * Inizializza la sessione PHP.
     */
    public static function init_session(): void {
        if ( session_status() === PHP_SESSION_NONE ) {
            ini_set( 'session.cookie_httponly', '1' );
            ini_set( 'session.cookie_secure', '1' );
            ini_set( 'session.use_strict_mode', '1' );
            ini_set( 'session.gc_maxlifetime', (string) WPC_SESSION_LIFETIME );
            session_start();
        }
    }

    /**
     * Verifica se l'utente e' autenticato.
     */
    public static function is_logged_in(): bool {
        return ! empty( $_SESSION['wpc_user_id'] ) && ! empty( $_SESSION['wpc_user_email'] );
    }

    /**
     * Richiede l'autenticazione. Reindirizza al login se non autenticato.
     */
    public static function require_auth(): void {
        if ( ! self::is_logged_in() ) {
            header( 'Location: login.php' );
            exit;
        }
        // Verifica scadenza sessione.
        if ( isset( $_SESSION['wpc_last_activity'] ) ) {
            if ( time() - $_SESSION['wpc_last_activity'] > WPC_SESSION_LIFETIME ) {
                self::logout();
                header( 'Location: login.php?expired=1' );
                exit;
            }
        }
        $_SESSION['wpc_last_activity'] = time();
    }

    /**
     * Verifica le credenziali e effettua il login.
     */
    public static function login( string $email, string $password, string $ip ): array {
        // Rate limiting.
        if ( self::is_rate_limited( $ip ) ) {
            return [ 'success' => false, 'message' => 'Troppi tentativi. Riprova tra qualche minuto.' ];
        }

        $db = WPC_Database::get_instance();

        $user = $db->fetch_one(
            "SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1",
            [ $email ]
        );

        if ( ! $user || ! password_verify( $password, $user['password_hash'] ) ) {
            self::record_failed_attempt( $ip );
            WPC_Audit::log( 'login_failed', "Tentativo di login fallito per: {$email}", null, $email, null, $ip );
            return [ 'success' => false, 'message' => 'Email o password non validi.' ];
        }

        // Login riuscito: crea la sessione.
        session_regenerate_id( true );
        $_SESSION['wpc_user_id']       = $user['id'];
        $_SESSION['wpc_user_email']    = $user['email'];
        $_SESSION['wpc_user_role']     = $user['role'];
        $_SESSION['wpc_user_name']     = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['wpc_last_activity'] = time();

        // Aggiorna ultimo login.
        $db->update( 'users', [
            'last_login_at' => date( 'Y-m-d H:i:s' ),
            'last_login_ip' => $ip,
        ], 'id = ?', [ $user['id'] ] );

        // Resetta i tentativi falliti.
        self::clear_failed_attempts( $ip );

        WPC_Audit::log( 'user_login', "Login effettuato da {$email}", $user['id'], $user['email'], null, $ip );

        return [ 'success' => true ];
    }

    /**
     * Effettua il logout.
     */
    public static function logout(): void {
        $_SESSION = [];
        if ( ini_get( 'session.use_cookies' ) ) {
            $params = session_get_cookie_params();
            setcookie( session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    /**
     * Ottieni l'ID dell'utente corrente.
     */
    public static function current_user_id(): ?string {
        return $_SESSION['wpc_user_id'] ?? null;
    }

    /**
     * Ottieni l'email dell'utente corrente.
     */
    public static function current_user_email(): ?string {
        return $_SESSION['wpc_user_email'] ?? null;
    }

    /**
     * Ottieni il ruolo dell'utente corrente.
     */
    public static function current_user_role(): ?string {
        return $_SESSION['wpc_user_role'] ?? null;
    }

    /**
     * Ottieni il nome dell'utente corrente.
     */
    public static function current_user_name(): ?string {
        return $_SESSION['wpc_user_name'] ?? null;
    }

    /**
     * Verifica se l'utente ha un certo ruolo.
     */
    public static function has_role( string $role ): bool {
        return self::current_user_role() === $role;
    }

    /**
     * Verifica se l'utente e' admin.
     */
    public static function is_admin(): bool {
        return self::has_role( 'admin' );
    }

    /**
     * Richiede il ruolo admin. Mostra errore 403 se non autorizzato.
     */
    public static function require_admin(): void {
        self::require_auth();
        if ( ! self::is_admin() ) {
            http_response_code( 403 );
            exit( 'Accesso non autorizzato. Ruolo admin richiesto.' );
        }
    }

    // --- CSRF ---

    /**
     * Genera un token CSRF.
     */
    public static function generate_csrf_token(): string {
        if ( empty( $_SESSION['wpc_csrf_token'] ) ) {
            $_SESSION['wpc_csrf_token'] = bin2hex( random_bytes( 32 ) );
        }
        return $_SESSION['wpc_csrf_token'];
    }

    /**
     * Restituisce il campo hidden HTML per il CSRF.
     */
    public static function csrf_field(): string {
        $token = self::generate_csrf_token();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars( $token, ENT_QUOTES, 'UTF-8' ) . '">';
    }

    /**
     * Verifica il token CSRF.
     */
    public static function verify_csrf( string $token ): bool {
        if ( empty( $_SESSION['wpc_csrf_token'] ) || empty( $token ) ) {
            return false;
        }
        return hash_equals( $_SESSION['wpc_csrf_token'], $token );
    }

    // --- Rate Limiting ---

    private static function is_rate_limited( string $ip ): bool {
        $db = WPC_Database::get_instance();
        $since = date( 'Y-m-d H:i:s', time() - ( WPC_LOGIN_LOCKOUT_MINUTES * 60 ) );
        $count = (int) $db->fetch_value(
            "SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > ?",
            [ $ip, $since ]
        );
        return $count >= WPC_LOGIN_MAX_ATTEMPTS;
    }

    private static function record_failed_attempt( string $ip ): void {
        $db = WPC_Database::get_instance();
        $db->insert( 'login_attempts', [
            'ip_address'   => $ip,
            'attempted_at' => date( 'Y-m-d H:i:s' ),
        ] );
    }

    private static function clear_failed_attempts( string $ip ): void {
        $db = WPC_Database::get_instance();
        $db->query( "DELETE FROM login_attempts WHERE ip_address = ?", [ $ip ] );
    }
}
