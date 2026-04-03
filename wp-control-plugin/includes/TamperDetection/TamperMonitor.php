<?php
/**
 * Monitor per la rilevazione di manomissioni.
 * Controlla l'integrità dei file, le opzioni del database e le modifiche sospette.
 *
 * @package WPControl\TamperDetection
 */

namespace WPControl\TamperDetection;

use WPControl\Security\RequestValidator;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TamperMonitor {

    private RequestValidator $request_validator;

    /** Opzioni critiche da monitorare. */
    private const MONITORED_OPTIONS = [
        'siteurl',
        'home',
        'admin_email',
        'blogname',
        'active_plugins',
        'template',
        'stylesheet',
        'users_can_register',
        'default_role',
    ];

    public function __construct( RequestValidator $request_validator ) {
        $this->request_validator = $request_validator;
    }

    /**
     * Inizializza gli hook di monitoraggio.
     */
    public function init(): void {
        // Cron per il controllo periodico dell'integrità dei file.
        add_action( 'wpc_tamper_check_cron', [ $this, 'run_integrity_check' ] );

        // Monitora le modifiche alle opzioni critiche.
        foreach ( self::MONITORED_OPTIONS as $option ) {
            add_action( "update_option_{$option}", [ $this, 'on_critical_option_changed' ], 10, 3 );
        }

        // Monitora i tentativi di modifica delle opzioni del plugin.
        add_action( 'update_option', [ $this, 'on_any_option_update' ], 10, 3 );

        // Hook per l'invio degli alert di manomissione.
        add_action( 'wpc_send_tamper_alert', [ $this, 'send_tamper_alert' ], 10, 3 );
    }

    /**
     * Esegue il controllo di integrità dei file del plugin.
     *
     * @return array Risultato del controllo.
     */
    public function run_integrity_check(): array {
        $stored_hashes = get_option( WPC_OPTION_PREFIX . 'file_hashes', [] );
        if ( empty( $stored_hashes ) ) {
            return [ 'status' => 'no_baseline', 'message' => 'Nessun hash di riferimento trovato.' ];
        }

        $current_hashes = $this->calculate_current_hashes();
        $issues = [];

        // Controlla file modificati.
        foreach ( $stored_hashes as $file => $expected_hash ) {
            if ( ! isset( $current_hashes[ $file ] ) ) {
                $issues[] = [
                    'type'    => 'file_missing',
                    'file'    => $file,
                    'message' => "File mancante: {$file}",
                ];
            } elseif ( $current_hashes[ $file ] !== $expected_hash ) {
                $issues[] = [
                    'type'    => 'file_modified',
                    'file'    => $file,
                    'message' => "File modificato: {$file}",
                ];
            }
        }

        // Controlla file nuovi (potenzialmente iniettati).
        foreach ( $current_hashes as $file => $hash ) {
            if ( ! isset( $stored_hashes[ $file ] ) ) {
                $issues[] = [
                    'type'    => 'file_added',
                    'file'    => $file,
                    'message' => "File non previsto trovato: {$file}",
                ];
            }
        }

        if ( ! empty( $issues ) ) {
            foreach ( $issues as $issue ) {
                $this->create_alert( $issue['type'], 'high', $issue['message'], $issue );
            }

            // Notifica il pannello remoto.
            $this->notify_control_panel( 'file_integrity_violation', $issues );
        }

        return [
            'status'      => empty( $issues ) ? 'clean' : 'issues_found',
            'issues'      => $issues,
            'checked_at'  => current_time( 'mysql' ),
            'files_count' => count( $stored_hashes ),
        ];
    }

    /**
     * Callback quando un'opzione critica viene modificata.
     */
    public function on_critical_option_changed( mixed $old_value, mixed $new_value, string $option ): void {
        // Ignora se la modifica è fatta dal plugin stesso.
        if ( $this->is_internal_change() ) {
            return;
        }

        $current_user = wp_get_current_user();
        $details = [
            'option'    => $option,
            'old_value' => is_array( $old_value ) ? wp_json_encode( $old_value ) : (string) $old_value,
            'new_value' => is_array( $new_value ) ? wp_json_encode( $new_value ) : (string) $new_value,
            'user'      => $current_user->user_login ?? 'sconosciuto',
            'ip'        => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        ];

        $this->create_alert(
            'critical_option_changed',
            'high',
            "Opzione critica modificata: {$option}",
            $details
        );

        $this->notify_control_panel( 'critical_option_changed', $details );
    }

    /**
     * Monitora le modifiche alle opzioni del plugin WP Control.
     */
    public function on_any_option_update( string $option, mixed $old_value, mixed $new_value ): void {
        if ( ! str_starts_with( $option, WPC_OPTION_PREFIX ) ) {
            return;
        }

        if ( $this->is_internal_change() ) {
            return;
        }

        // Opzioni sensibili del plugin.
        $sensitive_options = [
            WPC_OPTION_PREFIX . 'api_token_encrypted',
            WPC_OPTION_PREFIX . 'uninstall_code_hash',
            WPC_OPTION_PREFIX . 'master_unlock_hash',
            WPC_OPTION_PREFIX . 'control_panel_url',
            WPC_OPTION_PREFIX . 'site_id',
        ];

        if ( in_array( $option, $sensitive_options, true ) ) {
            $this->create_alert(
                'plugin_option_tampered',
                'critical',
                "Tentativo di modifica dell'opzione sensibile del plugin: {$option}",
                [
                    'option' => $option,
                    'user'   => wp_get_current_user()->user_login ?? 'sconosciuto',
                    'ip'     => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                ]
            );

            $this->notify_control_panel( 'plugin_option_tampered', [ 'option' => $option ] );
        }
    }

    /**
     * Invia un alert di manomissione al pannello remoto (chiamato dal cron).
     */
    public function send_tamper_alert( string $event_type, int $timestamp, string $ip ): void {
        $this->request_validator->send_to_control_panel( '/api/tamper.php', [
            'event_type' => $event_type,
            'site_id'    => get_option( WPC_OPTION_PREFIX . 'site_id', '' ),
            'timestamp'  => $timestamp,
            'ip_address' => $ip,
            'site_url'   => get_site_url(),
        ] );
    }

    /**
     * Ottieni gli alert di manomissione recenti.
     *
     * @param int $limit Numero massimo di alert.
     * @return array Lista degli alert.
     */
    public function get_recent_alerts( int $limit = 50 ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'wpc_tamper_alerts';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Aggiorna gli hash di riferimento (dopo un aggiornamento legittimo).
     */
    public function refresh_baseline(): void {
        $hashes = $this->calculate_current_hashes();
        update_option( WPC_OPTION_PREFIX . 'file_hashes', $hashes );
        $this->log_event( 'baseline_refreshed', 'Hash di riferimento aggiornati.' );
    }

    // --- Metodi privati ---

    /**
     * Calcola gli hash correnti dei file del plugin.
     */
    private function calculate_current_hashes(): array {
        $hashes = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( WPC_PLUGIN_DIR, \RecursiveDirectoryIterator::SKIP_DOTS )
        );

        foreach ( $iterator as $file ) {
            if ( $file->isFile() && $file->getExtension() === 'php' ) {
                $relative = str_replace( WPC_PLUGIN_DIR, '', $file->getPathname() );
                $hashes[ $relative ] = hash_file( 'sha256', $file->getPathname() );
            }
        }

        return $hashes;
    }

    /**
     * Crea un alert nel database.
     */
    private function create_alert( string $type, string $severity, string $description, array $details = [] ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'wpc_tamper_alerts';

        $wpdb->insert( $table, [
            'alert_type'  => $type,
            'severity'    => $severity,
            'description' => $description,
            'details'     => wp_json_encode( $details ),
        ] );
    }

    /**
     * Notifica il pannello di controllo remoto.
     */
    private function notify_control_panel( string $event_type, array $data ): void {
        $this->request_validator->send_to_control_panel( '/api/tamper.php', array_merge( $data, [
            'event_type' => $event_type,
            'site_id'    => get_option( WPC_OPTION_PREFIX . 'site_id', '' ),
            'site_url'   => get_site_url(),
            'timestamp'  => time(),
        ] ) );
    }

    /**
     * Verifica se la modifica è interna al plugin.
     */
    private function is_internal_change(): bool {
        $trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
        foreach ( $trace as $frame ) {
            if ( isset( $frame['file'] ) && str_contains( $frame['file'], 'wp-control-plugin' ) ) {
                return true;
            }
        }
        return false;
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
            'actor'             => 'sistema',
            'ip_address'        => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        ] );
    }
}
