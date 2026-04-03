<?php
/**
 * Classe principale del plugin WP Control.
 *
 * @package WPControl\Core
 */

namespace WPControl\Core;

use WPControl\Admin\AdminPage;
use WPControl\Api\RestController;
use WPControl\Backup\BackupManager;
use WPControl\Lockdown\LockdownEngine;
use WPControl\Protection\PluginProtection;
use WPControl\Security\CryptoManager;
use WPControl\Security\RequestValidator;
use WPControl\TamperDetection\TamperMonitor;
use WPControl\Heartbeat\HeartbeatManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {

    private static ?Plugin $instance = null;

    private CryptoManager $crypto;
    private RequestValidator $request_validator;
    private LockdownEngine $lockdown;
    private PluginProtection $protection;
    private BackupManager $backup;
    private TamperMonitor $tamper;
    private HeartbeatManager $heartbeat;
    private AdminPage $admin_page;
    private RestController $rest_controller;

    private function __construct() {}

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inizializza tutti i moduli del plugin.
     */
    public function init(): void {
        // Verifica se il setup iniziale è stato completato.
        $is_configured = (bool) get_option( WPC_OPTION_PREFIX . 'configured', false );

        // Moduli di sicurezza (sempre attivi).
        $this->crypto           = new CryptoManager();
        $this->request_validator = new RequestValidator( $this->crypto );

        // Protezione plugin (sempre attiva dopo la configurazione).
        $this->protection = new PluginProtection();
        $this->protection->init();

        // Lockdown engine (sempre attivo per intercettare le richieste).
        $this->lockdown = new LockdownEngine();
        $this->lockdown->init();

        // Backup manager.
        $this->backup = new BackupManager( $this->crypto );

        // Tamper detection.
        $this->tamper = new TamperMonitor( $this->request_validator );
        $this->tamper->init();

        // Heartbeat.
        $this->heartbeat = new HeartbeatManager( $this->request_validator );
        $this->heartbeat->init();

        // Admin pages (solo nel backend).
        if ( is_admin() ) {
            $this->admin_page = new AdminPage( $this );
            $this->admin_page->init();
        }

        // REST API endpoints.
        $this->rest_controller = new RestController(
            $this->request_validator,
            $this->lockdown,
            $this->backup,
            $this->tamper
        );
        $this->rest_controller->init();
    }

    // Getter per i moduli.
    public function get_crypto(): CryptoManager { return $this->crypto; }
    public function get_lockdown(): LockdownEngine { return $this->lockdown; }
    public function get_protection(): PluginProtection { return $this->protection; }
    public function get_backup(): BackupManager { return $this->backup; }
    public function get_tamper(): TamperMonitor { return $this->tamper; }
    public function get_heartbeat(): HeartbeatManager { return $this->heartbeat; }
}
