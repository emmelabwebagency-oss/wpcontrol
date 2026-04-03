<?php
/**
 * Gestore dell'heartbeat e dell'Ownership Enforcement.
 * Invia ping periodici al pannello di controllo e gestisce la modalità ristretta.
 *
 * @package LicenseTemplateKit\Heartbeat
 */

namespace LicenseTemplateKit\Heartbeat;

use LicenseTemplateKit\Security\RequestValidator;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HeartbeatManager {

    private RequestValidator $request_validator;

    private const LAST_HEARTBEAT_OPTION = LTK_OPTION_PREFIX . 'last_heartbeat';
    private const LAST_ACK_OPTION = LTK_OPTION_PREFIX . 'last_heartbeat_ack';
    private const RESTRICTED_MODE_OPTION = LTK_OPTION_PREFIX . 'restricted_mode';
    private const ENFORCEMENT_ENABLED = LTK_OPTION_PREFIX . 'ownership_enforcement';
    private const GRACE_PERIOD_OPTION = LTK_OPTION_PREFIX . 'grace_period_hours';
    private const HEARTBEAT_INTERVAL_OPTION = LTK_OPTION_PREFIX . 'heartbeat_interval_hours';
    private const LOCK_CHECK_INTERVAL = 300; // Controlla lo stato di lock ogni 5 minuti.
    private const LOCK_CHECK_TRANSIENT = 'ltk_last_lock_check';

    public function __construct( RequestValidator $request_validator ) {
        $this->request_validator = $request_validator;
    }

    /**
     * Inizializza gli hook dell'heartbeat.
     */
    public function init(): void {
        // Cron per l'invio dell'heartbeat.
        add_action( 'ltk_sync_cron', [ $this, 'send_heartbeat' ] );

        // Controlla lo stato di lock dal pannello ad ogni caricamento pagina (con cache).
        add_action( 'init', [ $this, 'check_lock_status_from_panel' ] );

        // Verifica la modalità ristretta ad ogni caricamento admin.
        add_action( 'admin_init', [ $this, 'check_restricted_mode' ] );

        // Aggiungi un avviso admin se in modalità ristretta.
        add_action( 'admin_notices', [ $this, 'restricted_mode_notice' ] );
    }

    /**
     * Controlla lo stato di lock dal pannello di controllo.
     * Usa un transient per evitare di chiamare il pannello ad ogni caricamento pagina.
     */
    public function check_lock_status_from_panel(): void {
        // Non verificare se il plugin non è configurato.
        $site_id = get_option( LTK_OPTION_PREFIX . 'site_id', '' );
        if ( empty( $site_id ) ) {
            return;
        }

        // Controlla se è passato abbastanza tempo dall'ultimo check.
        $last_check = get_transient( self::LOCK_CHECK_TRANSIENT );
        if ( false !== $last_check ) {
            return; // Troppo presto, usa la cache.
        }

        // Segna il timestamp del check (scade dopo LOCK_CHECK_INTERVAL secondi).
        set_transient( self::LOCK_CHECK_TRANSIENT, time(), self::LOCK_CHECK_INTERVAL );

        // Invia un heartbeat leggero per ricevere i comandi.
        $this->send_heartbeat();
    }

    /**
     * Invia l'heartbeat al pannello di controllo.
     */
    public function send_heartbeat(): void {
        $site_id = get_option( LTK_OPTION_PREFIX . 'site_id', '' );
        if ( empty( $site_id ) ) {
            return;
        }

        $payload = [
            'site_id'       => $site_id,
            'site_url'      => get_site_url(),
            'wp_version'    => get_bloginfo( 'version' ),
            'php_version'   => phpversion(),
            'active_theme'  => get_stylesheet(),
            'plugin_count'  => count( get_option( 'active_plugins', [] ) ),
            'lock_status'   => (bool) get_option( LTK_OPTION_PREFIX . 'lock_mode', false ),
            'restricted'    => $this->is_restricted(),
            'timestamp'     => time(),
        ];

        $response = $this->request_validator->send_to_control_panel( '/api/heartbeat.php', $payload );

        // Aggiorna il timestamp dell'ultimo heartbeat inviato.
        update_option( self::LAST_HEARTBEAT_OPTION, time() );

        if ( ! is_wp_error( $response ) ) {
            $code = wp_remote_retrieve_response_code( $response );
            if ( $code === 200 ) {
                // ACK ricevuto: aggiorna il timestamp.
                update_option( self::LAST_ACK_OPTION, time() );

                // Se eravamo in modalità ristretta, rimuovila.
                if ( $this->is_restricted() ) {
                    $this->exit_restricted_mode();
                }

                // Processa i comandi dal pannello di controllo.
                $body = wp_remote_retrieve_body( $response );
                $response_data = json_decode( $body, true );
                if ( isset( $response_data['commands'] ) ) {
                    $this->process_commands( $response_data['commands'] );
                }

                // Processa il codice di disinstallazione dal pannello.
                if ( ! empty( $response_data['uninstall_code'] ) ) {
                    $this->process_uninstall_code( $response_data['uninstall_code'] );
                }
            }
        }

        // Controlla se è necessario entrare in modalità ristretta.
        $this->evaluate_enforcement();
    }

    /**
     * Valuta se attivare l'Ownership Enforcement (modalità ristretta).
     */
    public function evaluate_enforcement(): void {
        if ( ! $this->is_enforcement_enabled() ) {
            return;
        }

        $last_ack = (int) get_option( self::LAST_ACK_OPTION, 0 );
        if ( $last_ack === 0 ) {
            return; // Nessun ACK ancora ricevuto, probabilmente prima configurazione.
        }

        $grace_period = $this->get_grace_period_seconds();
        $elapsed = time() - $last_ack;

        if ( $elapsed > $grace_period && ! $this->is_restricted() ) {
            $this->enter_restricted_mode();
        }
    }

    /**
     * Verifica se la modalità ristretta è attiva.
     */
    public function is_restricted(): bool {
        return (bool) get_option( self::RESTRICTED_MODE_OPTION, false );
    }

    /**
     * Attiva la modalità ristretta.
     */
    public function enter_restricted_mode(): void {
        update_option( self::RESTRICTED_MODE_OPTION, true );
        $this->log_event( 'restricted_mode_activated', 'Modalità ristretta attivata per mancato heartbeat.' );
    }

    /**
     * Disattiva la modalità ristretta.
     */
    public function exit_restricted_mode(): void {
        update_option( self::RESTRICTED_MODE_OPTION, false );
        $this->log_event( 'restricted_mode_deactivated', 'Modalità ristretta disattivata dopo verifica proprietà.' );
    }

    /**
     * Controlla la modalità ristretta e applica le limitazioni.
     */
    public function check_restricted_mode(): void {
        if ( ! $this->is_restricted() ) {
            return;
        }

        // In modalità ristretta: limita alcune funzionalità ma non blocca completamente.
        // Disabilita l'installazione di nuovi plugin/temi.
        add_filter( 'user_has_cap', function ( array $allcaps ) {
            $restricted_caps = [
                'install_plugins', 'install_themes',
                'update_plugins', 'update_themes', 'update_core',
                'edit_plugins', 'edit_themes',
            ];
            foreach ( $restricted_caps as $cap ) {
                $allcaps[ $cap ] = false;
            }
            return $allcaps;
        }, 999 );
    }

    /**
     * Mostra un avviso nell'admin se in modalità ristretta.
     */
    public function restricted_mode_notice(): void {
        if ( ! $this->is_restricted() ) {
            return;
        }

        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>&#9888; Modalità Ristretta</strong></p>';
        echo '<p>' . esc_html__( 'Il sito è in modalità ristretta perché la verifica di proprietà non è riuscita. Alcune funzionalità sono limitate fino alla riconnessione con il pannello di controllo.', 'wp-ltk' ) . '</p>';
        echo '</div>';
    }

    /**
     * Verifica se l'Ownership Enforcement è abilitato.
     */
    public function is_enforcement_enabled(): bool {
        return (bool) get_option( self::ENFORCEMENT_ENABLED, false );
    }

    /**
     * Ottieni il periodo di grazia in secondi.
     */
    private function get_grace_period_seconds(): int {
        $hours = (int) get_option( self::GRACE_PERIOD_OPTION, 72 ); // Default: 72 ore (3 giorni).
        return max( $hours, 24 ) * 3600; // Minimo 24 ore per sicurezza.
    }

    /**
     * Ottieni l'intervallo dell'heartbeat in secondi.
     */
    public function get_heartbeat_interval(): int {
        return self::LOCK_CHECK_INTERVAL; // 5 minuti fissi.
    }

    /**
     * Ottieni le informazioni sullo stato dell'heartbeat.
     */
    public function get_status(): array {
        return [
            'enforcement_enabled' => $this->is_enforcement_enabled(),
            'restricted_mode'     => $this->is_restricted(),
            'last_heartbeat'      => (int) get_option( self::LAST_HEARTBEAT_OPTION, 0 ),
            'last_ack'            => (int) get_option( self::LAST_ACK_OPTION, 0 ),
            'grace_period_hours'  => (int) get_option( self::GRACE_PERIOD_OPTION, 72 ),
            'interval_hours'      => (int) get_option( self::HEARTBEAT_INTERVAL_OPTION, 1 ),
        ];
    }

    /**
     * Processa i comandi ricevuti dal pannello di controllo nella risposta heartbeat.
     *
     * @param array $commands Array di comandi dal pannello.
     */
    private function process_commands( array $commands ): void {
        // Comando lock/unlock.
        if ( isset( $commands['lock'] ) ) {
            $should_lock = (bool) $commands['lock'];
            $lockdown = \LicenseTemplateKit\Core\Plugin::get_instance()->get_lockdown();

            if ( $should_lock && ! $lockdown->is_locked() ) {
                $lockdown->activate_lock( 'pannello_remoto' );
            } elseif ( ! $should_lock && $lockdown->is_locked() ) {
                $lockdown->deactivate_lock( 'pannello_remoto' );
            }
        }
    }

    /**
     * Processa il codice di disinstallazione ricevuto dal pannello.
     */
    private function process_uninstall_code( string $code ): void {
        $crypto = \LicenseTemplateKit\Core\Plugin::get_instance()->get_crypto();
        $stored_hash = get_option( LTK_OPTION_PREFIX . 'uninstall_code_hash', '' );

        // Salva l'hash solo se non esiste ancora o se il codice e' cambiato.
        if ( empty( $stored_hash ) || ! password_verify( $code, $stored_hash ) ) {
            update_option( LTK_OPTION_PREFIX . 'uninstall_code_hash', $crypto->hash_value( $code ) );
        }
    }

    /**
     * Registra un evento nel log.
     */
    private function log_event( string $type, string $description ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'ltk_tpl_log';
        $wpdb->insert( $table, [
            'event_type'        => $type,
            'event_description' => $description,
            'actor'             => 'sistema',
            'ip_address'        => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        ] );
    }
}
