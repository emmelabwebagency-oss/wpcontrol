<?php
/**
 * Controller REST API per WP Control.
 * Espone gli endpoint sicuri per la comunicazione con il pannello di controllo remoto.
 *
 * @package WPControl\Api
 */

namespace WPControl\Api;

use WPControl\Security\RequestValidator;
use WPControl\Lockdown\LockdownEngine;
use WPControl\Backup\BackupManager;
use WPControl\TamperDetection\TamperMonitor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RestController {

    private const NAMESPACE = 'wp-control/v1';

    private RequestValidator $validator;
    private LockdownEngine $lockdown;
    private BackupManager $backup;
    private TamperMonitor $tamper;

    public function __construct(
        RequestValidator $validator,
        LockdownEngine $lockdown,
        BackupManager $backup,
        TamperMonitor $tamper
    ) {
        $this->validator = $validator;
        $this->lockdown  = $lockdown;
        $this->backup    = $backup;
        $this->tamper    = $tamper;
    }

    /**
     * Registra tutti gli endpoint REST.
     */
    public function init(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Registra le route REST.
     */
    public function register_routes(): void {
        // Stato del sito.
        register_rest_route( self::NAMESPACE, '/status', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_status' ],
            'permission_callback' => [ $this, 'verify_remote_request' ],
        ] );

        // Attiva il blocco.
        register_rest_route( self::NAMESPACE, '/lock', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'activate_lock' ],
            'permission_callback' => [ $this, 'verify_remote_request' ],
        ] );

        // Disattiva il blocco.
        register_rest_route( self::NAMESPACE, '/unlock', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'deactivate_lock' ],
            'permission_callback' => [ $this, 'verify_remote_request' ],
        ] );

        // Sblocco di emergenza con master code.
        register_rest_route( self::NAMESPACE, '/emergency-unlock', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'emergency_unlock' ],
            'permission_callback' => '__return_true', // Autenticazione tramite master code nel body.
        ] );

        // Richiedi backup.
        register_rest_route( self::NAMESPACE, '/backup/create', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'create_backup' ],
            'permission_callback' => [ $this, 'verify_remote_request' ],
        ] );

        // Lista backup.
        register_rest_route( self::NAMESPACE, '/backup/list', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'list_backups' ],
            'permission_callback' => [ $this, 'verify_remote_request' ],
        ] );

        // Ripristina backup.
        register_rest_route( self::NAMESPACE, '/backup/restore', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'restore_backup' ],
            'permission_callback' => [ $this, 'verify_remote_request' ],
        ] );

        // Controllo integrità.
        register_rest_route( self::NAMESPACE, '/integrity-check', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'integrity_check' ],
            'permission_callback' => [ $this, 'verify_remote_request' ],
        ] );

        // Alert di manomissione.
        register_rest_route( self::NAMESPACE, '/tamper-alerts', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_tamper_alerts' ],
            'permission_callback' => [ $this, 'verify_remote_request' ],
        ] );

        // Rotazione credenziali API.
        register_rest_route( self::NAMESPACE, '/rotate-credentials', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'rotate_credentials' ],
            'permission_callback' => [ $this, 'verify_remote_request' ],
        ] );
    }

    /**
     * Verifica che la richiesta provenga dal pannello di controllo remoto.
     */
    public function verify_remote_request( \WP_REST_Request $request ): bool {
        $signature = $request->get_header( 'X-WPC-Signature' );
        $timestamp = (int) $request->get_header( 'X-WPC-Timestamp' );
        $nonce     = $request->get_header( 'X-WPC-Nonce' );
        $body      = $request->get_body();

        if ( empty( $signature ) || empty( $timestamp ) || empty( $nonce ) ) {
            return false;
        }

        return $this->validator->validate_incoming_request( $body, $signature, $timestamp, $nonce );
    }

    /**
     * GET /status - Restituisce lo stato del sito.
     */
    public function get_status( \WP_REST_Request $request ): \WP_REST_Response {
        $heartbeat_mgr = \WPControl\Core\Plugin::get_instance()->get_heartbeat();

        $data = [
            'site_id'        => get_option( WPC_OPTION_PREFIX . 'site_id', '' ),
            'site_url'       => get_site_url(),
            'site_name'      => get_bloginfo( 'name' ),
            'wp_version'     => get_bloginfo( 'version' ),
            'php_version'    => phpversion(),
            'active_theme'   => get_stylesheet(),
            'plugin_count'   => count( get_option( 'active_plugins', [] ) ),
            'lock_status'    => $this->lockdown->is_locked(),
            'lock_timestamp' => get_option( WPC_OPTION_PREFIX . 'lock_timestamp', null ),
            'heartbeat'      => $heartbeat_mgr->get_status(),
            'plugin_version' => WPC_VERSION,
            'timestamp'      => time(),
        ];

        return new \WP_REST_Response( $data, 200 );
    }

    /**
     * POST /lock - Attiva la modalità di blocco.
     */
    public function activate_lock( \WP_REST_Request $request ): \WP_REST_Response {
        $params = $request->get_json_params();
        $create_backup = $params['create_backup'] ?? true;

        $backup_result = null;

        // Crea un backup prima del blocco (se richiesto).
        if ( $create_backup ) {
            try {
                $backup_result = $this->backup->create_full_backup();

                // Carica il backup sul server remoto.
                if ( isset( $backup_result['backup_id'] ) ) {
                    $this->backup->upload_backup( $backup_result['backup_id'] );
                }
            } catch ( \Exception $e ) {
                return new \WP_REST_Response( [
                    'success' => false,
                    'message' => 'Errore durante la creazione del backup: ' . $e->getMessage(),
                ], 500 );
            }
        }

        // Attiva il blocco.
        $actor = $params['actor'] ?? 'pannello_remoto';
        $this->lockdown->activate_lock( $actor );

        return new \WP_REST_Response( [
            'success'       => true,
            'message'       => 'Modalità di blocco attivata con successo.',
            'lock_status'   => true,
            'backup_result' => $backup_result,
            'timestamp'     => time(),
        ], 200 );
    }

    /**
     * POST /unlock - Disattiva la modalità di blocco.
     */
    public function deactivate_lock( \WP_REST_Request $request ): \WP_REST_Response {
        $params = $request->get_json_params();
        $actor = $params['actor'] ?? 'pannello_remoto';

        $this->lockdown->deactivate_lock( $actor );

        return new \WP_REST_Response( [
            'success'     => true,
            'message'     => 'Modalità di blocco disattivata con successo.',
            'lock_status' => false,
            'timestamp'   => time(),
        ], 200 );
    }

    /**
     * POST /emergency-unlock - Sblocco di emergenza con master code.
     */
    public function emergency_unlock( \WP_REST_Request $request ): \WP_REST_Response {
        $params = $request->get_json_params();
        $master_code = $params['master_code'] ?? '';

        if ( empty( $master_code ) ) {
            return new \WP_REST_Response( [
                'success' => false,
                'message' => 'Codice master di sblocco richiesto.',
            ], 400 );
        }

        // Verifica il master code.
        $stored_hash = get_option( WPC_OPTION_PREFIX . 'master_unlock_hash', '' );
        if ( empty( $stored_hash ) || ! password_verify( $master_code, $stored_hash ) ) {
            // Registra il tentativo fallito.
            $this->log_event( 'emergency_unlock_failed', 'Tentativo di sblocco di emergenza con codice non valido.' );
            return new \WP_REST_Response( [
                'success' => false,
                'message' => 'Codice master non valido.',
            ], 403 );
        }

        // Sblocca il sito.
        $this->lockdown->deactivate_lock( 'sblocco_emergenza' );

        // Disattiva anche la modalità ristretta se attiva.
        $heartbeat = \WPControl\Core\Plugin::get_instance()->get_heartbeat();
        if ( $heartbeat->is_restricted() ) {
            $heartbeat->exit_restricted_mode();
        }

        return new \WP_REST_Response( [
            'success'   => true,
            'message'   => 'Sito sbloccato con successo tramite sblocco di emergenza.',
            'timestamp' => time(),
        ], 200 );
    }

    /**
     * POST /backup/create - Crea un nuovo backup.
     */
    public function create_backup( \WP_REST_Request $request ): \WP_REST_Response {
        try {
            $result = $this->backup->create_full_backup();

            // Upload automatico.
            if ( isset( $result['backup_id'] ) ) {
                $this->backup->upload_backup( $result['backup_id'] );
            }

            return new \WP_REST_Response( [
                'success' => true,
                'message' => 'Backup creato e caricato con successo.',
                'backup'  => $result,
            ], 200 );
        } catch ( \Exception $e ) {
            return new \WP_REST_Response( [
                'success' => false,
                'message' => 'Errore: ' . $e->getMessage(),
            ], 500 );
        }
    }

    /**
     * GET /backup/list - Lista dei backup disponibili.
     */
    public function list_backups( \WP_REST_Request $request ): \WP_REST_Response {
        $backups = $this->backup->get_backups();
        return new \WP_REST_Response( [
            'success' => true,
            'backups' => $backups,
            'count'   => count( $backups ),
        ], 200 );
    }

    /**
     * POST /backup/restore - Ripristina un backup.
     */
    public function restore_backup( \WP_REST_Request $request ): \WP_REST_Response {
        $params = $request->get_json_params();
        $backup_id = $params['backup_id'] ?? '';
        $dry_run = $params['dry_run'] ?? false;

        if ( empty( $backup_id ) ) {
            return new \WP_REST_Response( [
                'success' => false,
                'message' => 'ID del backup richiesto.',
            ], 400 );
        }

        $result = $this->backup->restore_backup( $backup_id, $dry_run );

        return new \WP_REST_Response( $result, $result['success'] ? 200 : 500 );
    }

    /**
     * POST /integrity-check - Esegue il controllo di integrità.
     */
    public function integrity_check( \WP_REST_Request $request ): \WP_REST_Response {
        $result = $this->tamper->run_integrity_check();
        return new \WP_REST_Response( [
            'success' => true,
            'result'  => $result,
        ], 200 );
    }

    /**
     * GET /tamper-alerts - Ottieni gli alert di manomissione.
     */
    public function get_tamper_alerts( \WP_REST_Request $request ): \WP_REST_Response {
        $limit = (int) ( $request->get_param( 'limit' ) ?? 50 );
        $alerts = $this->tamper->get_recent_alerts( $limit );

        return new \WP_REST_Response( [
            'success' => true,
            'alerts'  => $alerts,
            'count'   => count( $alerts ),
        ], 200 );
    }

    /**
     * POST /rotate-credentials - Ruota le credenziali API.
     */
    public function rotate_credentials( \WP_REST_Request $request ): \WP_REST_Response {
        $crypto = \WPControl\Core\Plugin::get_instance()->get_crypto();

        // Genera un nuovo token API.
        $new_token = $crypto->generate_api_token();
        $encrypted_token = $crypto->encrypt( $new_token );

        update_option( WPC_OPTION_PREFIX . 'api_token_encrypted', $encrypted_token );

        $this->log_event( 'credentials_rotated', 'Credenziali API ruotate dal pannello remoto.' );

        return new \WP_REST_Response( [
            'success'   => true,
            'message'   => 'Credenziali API ruotate con successo.',
            'new_token' => $new_token, // Inviato una sola volta, il pannello deve salvarlo.
            'timestamp' => time(),
        ], 200 );
    }

    /**
     * Registra un evento nel log.
     */
    private function log_event( string $type, string $description ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'wpc_audit_log';
        $wpdb->insert( $table, [
            'event_type'        => $type,
            'event_description' => $description,
            'actor'             => 'api',
            'ip_address'        => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        ] );
    }
}
