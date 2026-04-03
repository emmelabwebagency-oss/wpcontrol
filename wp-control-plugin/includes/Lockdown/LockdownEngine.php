<?php
/**
 * Motore di blocco (Lock Mode) del sito.
 * Gestisce l'attivazione/disattivazione del blocco e l'intercettazione delle richieste.
 *
 * @package WPControl\Lockdown
 */

namespace WPControl\Lockdown;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LockdownEngine {

    private const LOCK_OPTION = WPC_OPTION_PREFIX . 'lock_mode';
    private const LOCK_TIMESTAMP = WPC_OPTION_PREFIX . 'lock_timestamp';
    private const LOCK_ACTOR = WPC_OPTION_PREFIX . 'lock_actor';

    /**
     * Inizializza gli hook del lockdown.
     */
    public function init(): void {
        // Controlla il lock mode ad ogni richiesta.
        add_action( 'template_redirect', [ $this, 'enforce_frontend_lock' ] );
        add_action( 'admin_init', [ $this, 'enforce_admin_lock' ] );
        add_action( 'login_init', [ $this, 'enforce_login_lock' ] );

        // Blocca le modifiche ai plugin/temi quando in lock mode.
        add_filter( 'user_has_cap', [ $this, 'restrict_capabilities' ], 999, 3 );

        // Disabilita l'editor di file se in lock mode.
        if ( $this->is_locked() ) {
            if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
                define( 'DISALLOW_FILE_EDIT', true );
            }
            if ( ! defined( 'DISALLOW_FILE_MODS' ) ) {
                define( 'DISALLOW_FILE_MODS', true );
            }
        }

        // Blocca XML-RPC se configurato.
        if ( $this->is_locked() && $this->is_option_enabled( 'disable_xmlrpc' ) ) {
            add_filter( 'xmlrpc_enabled', '__return_false' );
        }

        // Blocca REST API pubblica se configurato.
        if ( $this->is_locked() && $this->is_option_enabled( 'restrict_rest_api' ) ) {
            add_filter( 'rest_authentication_errors', [ $this, 'restrict_rest_api' ] );
        }
    }

    /**
     * Verifica se il sito è in modalità blocco.
     */
    public function is_locked(): bool {
        return (bool) get_option( self::LOCK_OPTION, false );
    }

    /**
     * Attiva la modalità di blocco.
     *
     * @param string $actor Chi ha attivato il blocco.
     * @return bool True se l'operazione ha successo.
     */
    public function activate_lock( string $actor = 'pannello_remoto' ): bool {
        // Invalida tutte le sessioni utente.
        $this->invalidate_all_sessions();

        // Attiva il lock.
        update_option( self::LOCK_OPTION, true );
        update_option( self::LOCK_TIMESTAMP, current_time( 'mysql' ) );
        update_option( self::LOCK_ACTOR, $actor );

        // Registra nel log.
        $this->log_event( 'lock_activated', "Modalità blocco attivata da: {$actor}" );

        return true;
    }

    /**
     * Disattiva la modalità di blocco.
     *
     * @param string $actor Chi ha disattivato il blocco.
     * @return bool True se l'operazione ha successo.
     */
    public function deactivate_lock( string $actor = 'pannello_remoto' ): bool {
        update_option( self::LOCK_OPTION, false );
        delete_option( self::LOCK_TIMESTAMP );
        delete_option( self::LOCK_ACTOR );

        // Registra nel log.
        $this->log_event( 'lock_deactivated', "Modalità blocco disattivata da: {$actor}" );

        return true;
    }

    /**
     * Blocca il frontend mostrando la pagina di cortesia.
     */
    public function enforce_frontend_lock(): void {
        if ( ! $this->is_locked() ) {
            return;
        }

        // Permetti l'accesso agli IP autorizzati.
        if ( $this->is_ip_allowed() ) {
            return;
        }

        // Permetti l'accesso all'endpoint REST del plugin.
        if ( $this->is_wpc_api_request() ) {
            return;
        }

        // Mostra la pagina di blocco.
        $this->show_lock_page();
    }

    /**
     * Blocca l'accesso all'admin.
     */
    public function enforce_admin_lock(): void {
        if ( ! $this->is_locked() ) {
            return;
        }

        // Permetti l'accesso agli IP autorizzati.
        if ( $this->is_ip_allowed() ) {
            return;
        }

        // Permetti AJAX per il plugin stesso.
        if ( wp_doing_ajax() && isset( $_REQUEST['action'] ) && str_starts_with( $_REQUEST['action'], 'wpc_' ) ) {
            return;
        }

        // Blocca l'accesso.
        wp_die(
            '<h1>' . esc_html__( 'Accesso Limitato', 'wp-control' ) . '</h1>' .
            '<p>' . esc_html__( 'L\'accesso all\'area di amministrazione è temporaneamente limitato. Per sbloccare il sito, accedi al pannello WP Control Center.', 'wp-control' ) . '</p>',
            esc_html__( 'Sito Protetto', 'wp-control' ),
            [ 'response' => 503 ]
        );
    }

    /**
     * Blocca i nuovi login.
     */
    public function enforce_login_lock(): void {
        if ( ! $this->is_locked() ) {
            return;
        }

        if ( $this->is_ip_allowed() ) {
            return;
        }

        wp_die(
            '<h1>' . esc_html__( 'Accesso Bloccato', 'wp-control' ) . '</h1>' .
            '<p>' . esc_html__( 'L\'accesso al sito è temporaneamente sospeso per motivi di sicurezza.', 'wp-control' ) . '</p>',
            esc_html__( 'Login Bloccato - WP Control', 'wp-control' ),
            [ 'response' => 503 ]
        );
    }

    /**
     * Limita le capability degli utenti quando in lock mode.
     */
    public function restrict_capabilities( array $allcaps, array $caps, array $args ): array {
        if ( ! $this->is_locked() ) {
            return $allcaps;
        }

        if ( $this->is_ip_allowed() ) {
            return $allcaps;
        }

        // Rimuovi le capability di modifica plugin/temi/file.
        $restricted = [
            'install_plugins', 'activate_plugins', 'delete_plugins', 'update_plugins',
            'install_themes', 'switch_themes', 'delete_themes', 'update_themes',
            'edit_plugins', 'edit_themes', 'edit_files',
            'update_core',
        ];

        foreach ( $restricted as $cap ) {
            $allcaps[ $cap ] = false;
        }

        return $allcaps;
    }

    /**
     * Limita l'accesso alla REST API pubblica.
     */
    public function restrict_rest_api( ?\WP_Error $result ): ?\WP_Error {
        if ( ! empty( $result ) ) {
            return $result;
        }

        // Permetti le richieste autenticate e quelle del plugin.
        if ( is_user_logged_in() || $this->is_wpc_api_request() ) {
            return $result;
        }

        return new \WP_Error(
            'rest_disabled',
            __( 'L\'accesso alla REST API è temporaneamente disabilitato.', 'wp-control' ),
            [ 'status' => 503 ]
        );
    }

    /**
     * Mostra la pagina di blocco personalizzata.
     */
    private function show_lock_page(): void {
        $template = WPC_PLUGIN_DIR . 'templates/lock-page.php';

        if ( file_exists( $template ) ) {
            status_header( 503 );
            header( 'Retry-After: 3600' );
            include $template;
            exit;
        }

        // Fallback: pagina di blocco generica.
        status_header( 503 );
        header( 'Retry-After: 3600' );
        header( 'Content-Type: text/html; charset=utf-8' );
        echo '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>Sito Protetto</title>';
        echo '<style>body{font-family:system-ui,sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;background:#f5f5f5;color:#333}';
        echo '.container{text-align:center;padding:2rem;max-width:600px}.icon{font-size:4rem;margin-bottom:1rem}h1{margin-bottom:0.5rem}p{color:#666}</style></head>';
        echo '<body><div class="container"><div class="icon">&#128274;</div>';
        echo '<h1>Sito Temporaneamente Protetto</h1>';
        echo '<p>L\'accesso a questo sito è attualmente limitato da WP Control.</p>';
        echo '<p>Se sei il proprietario, accedi tramite il pannello di controllo remoto.</p>';
        echo '</div></body></html>';
        exit;
    }

    /**
     * Verifica se l'IP corrente è nella lista degli IP autorizzati.
     */
    private function is_ip_allowed(): bool {
        $allowed_ips = get_option( WPC_OPTION_PREFIX . 'allowed_ips', '' );
        if ( empty( $allowed_ips ) ) {
            return false;
        }

        $ip_list = array_map( 'trim', explode( ',', $allowed_ips ) );
        $client_ip = $this->get_client_ip();

        return in_array( $client_ip, $ip_list, true );
    }

    /**
     * Verifica se la richiesta è diretta all'API del plugin.
     */
    private function is_wpc_api_request(): bool {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        return str_contains( $request_uri, '/wp-json/wp-control/v1/' );
    }

    /**
     * Invalida tutte le sessioni utente attive.
     */
    private function invalidate_all_sessions(): void {
        $users = get_users( [ 'fields' => 'ID' ] );
        foreach ( $users as $user_id ) {
            $sessions = \WP_Session_Tokens::get_instance( $user_id );
            $sessions->destroy_all();
        }
    }

    /**
     * Ottieni l'IP del client.
     */
    private function get_client_ip(): string {
        $ip_keys = [ 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ];
        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) );
                return trim( $ip[0] );
            }
        }
        return '0.0.0.0';
    }

    /**
     * Verifica se un'opzione booleana del plugin è attiva.
     */
    private function is_option_enabled( string $key ): bool {
        return (bool) get_option( WPC_OPTION_PREFIX . $key, false );
    }

    /**
     * Registra un evento nel log di audit.
     */
    private function log_event( string $type, string $description ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'wpc_audit_log';

        $wpdb->insert( $table, [
            'event_type'        => $type,
            'event_description' => $description,
            'actor'             => 'sistema',
            'ip_address'        => $this->get_client_ip(),
            'metadata'          => wp_json_encode( [ 'timestamp' => time() ] ),
        ] );
    }
}
