<?php
/**
 * Pagine di amministrazione del plugin WP Control.
 *
 * @package WPControl\Admin
 */

namespace WPControl\Admin;

use WPControl\Core\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AdminPage {

    private Plugin $plugin;

    public function __construct( Plugin $plugin ) {
        $this->plugin = $plugin;
    }

    /**
     * Inizializza le pagine admin.
     */
    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_init', [ $this, 'handle_setup_form' ] );
        add_action( 'admin_init', [ $this, 'handle_emergency_unlock_form' ] );
        add_action( 'admin_init', [ $this, 'handle_settings_form' ] );

        // Mostra avviso se non configurato.
        if ( ! get_option( WPC_OPTION_PREFIX . 'configured', false ) ) {
            add_action( 'admin_notices', [ $this, 'setup_required_notice' ] );
        }

        // Mostra avviso se il sito è bloccato.
        if ( $this->plugin->get_lockdown()->is_locked() ) {
            add_action( 'admin_notices', [ $this, 'lock_mode_notice' ] );
        }

        // Mostra errore di disattivazione non autorizzata.
        add_action( 'admin_notices', [ $this, 'deactivation_error_notice' ] );
    }

    /**
     * Registra i menu nell'admin.
     */
    public function register_menus(): void {
        // Menu principale.
        add_menu_page(
            __( 'WP Control', 'wp-control' ),
            __( 'WP Control', 'wp-control' ),
            'manage_options',
            'wp-control',
            [ $this, 'render_dashboard' ],
            'dashicons-shield-alt',
            3
        );

        // Sottomenu: Dashboard.
        add_submenu_page(
            'wp-control',
            __( 'Dashboard', 'wp-control' ),
            __( 'Dashboard', 'wp-control' ),
            'manage_options',
            'wp-control',
            [ $this, 'render_dashboard' ]
        );

        // Sottomenu: Impostazioni.
        add_submenu_page(
            'wp-control',
            __( 'Impostazioni', 'wp-control' ),
            __( 'Impostazioni', 'wp-control' ),
            'manage_options',
            'wpc-settings',
            [ $this, 'render_settings' ]
        );

        // Sottomenu: Backup.
        add_submenu_page(
            'wp-control',
            __( 'Backup', 'wp-control' ),
            __( 'Backup', 'wp-control' ),
            'manage_options',
            'wpc-backups',
            [ $this, 'render_backups' ]
        );

        // Sottomenu: Log di Sicurezza.
        add_submenu_page(
            'wp-control',
            __( 'Log di Sicurezza', 'wp-control' ),
            __( 'Log di Sicurezza', 'wp-control' ),
            'manage_options',
            'wpc-security-log',
            [ $this, 'render_security_log' ]
        );

        // Sottomenu: Sblocco di Emergenza (visibile anche in lock mode).
        add_submenu_page(
            'wp-control',
            __( 'Sblocco di Emergenza', 'wp-control' ),
            __( 'Sblocco di Emergenza', 'wp-control' ),
            'manage_options',
            'wpc-emergency-unlock',
            [ $this, 'render_emergency_unlock' ]
        );

        // Sottomenu: Setup (solo se non configurato).
        if ( ! get_option( WPC_OPTION_PREFIX . 'configured', false ) ) {
            add_submenu_page(
                'wp-control',
                __( 'Setup Iniziale', 'wp-control' ),
                __( 'Setup Iniziale', 'wp-control' ),
                'manage_options',
                'wpc-setup',
                [ $this, 'render_setup' ]
            );
        }
    }

    /**
     * Carica gli asset CSS e JS.
     */
    public function enqueue_assets( string $hook ): void {
        if ( ! str_contains( $hook, 'wp-control' ) && ! str_contains( $hook, 'wpc-' ) ) {
            return;
        }

        wp_enqueue_style(
            'wpc-admin',
            WPC_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WPC_VERSION
        );

        wp_enqueue_script(
            'wpc-admin',
            WPC_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            WPC_VERSION,
            true
        );

        wp_localize_script( 'wpc-admin', 'wpcAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'wpc_admin_nonce' ),
            'i18n'    => [
                'confirm' => __( 'Sei sicuro?', 'wp-control' ),
                'success' => __( 'Operazione completata.', 'wp-control' ),
                'error'   => __( 'Si è verificato un errore.', 'wp-control' ),
            ],
        ] );
    }

    /**
     * Renderizza la dashboard principale.
     */
    public function render_dashboard(): void {
        $lockdown = $this->plugin->get_lockdown();
        $heartbeat = $this->plugin->get_heartbeat();
        $tamper = $this->plugin->get_tamper();

        $site_id = get_option( WPC_OPTION_PREFIX . 'site_id', 'Non configurato' );
        $panel_url = get_option( WPC_OPTION_PREFIX . 'control_panel_url', 'Non configurato' );
        $is_locked = $lockdown->is_locked();
        $hb_status = $heartbeat->get_status();
        $recent_alerts = $tamper->get_recent_alerts( 10 );

        include WPC_PLUGIN_DIR . 'templates/admin-dashboard.php';
    }

    /**
     * Renderizza la pagina impostazioni.
     */
    public function render_settings(): void {
        $settings = [
            'site_id'              => get_option( WPC_OPTION_PREFIX . 'site_id', '' ),
            'control_panel_url'    => get_option( WPC_OPTION_PREFIX . 'control_panel_url', '' ),
            'allowed_ips'          => get_option( WPC_OPTION_PREFIX . 'allowed_ips', '' ),
            'disable_xmlrpc'       => get_option( WPC_OPTION_PREFIX . 'disable_xmlrpc', false ),
            'restrict_rest_api'    => get_option( WPC_OPTION_PREFIX . 'restrict_rest_api', false ),
            'ownership_enforcement' => get_option( WPC_OPTION_PREFIX . 'ownership_enforcement', false ),
            'grace_period_hours'   => get_option( WPC_OPTION_PREFIX . 'grace_period_hours', 72 ),
            'heartbeat_interval'   => get_option( WPC_OPTION_PREFIX . 'heartbeat_interval_hours', 1 ),
        ];

        include WPC_PLUGIN_DIR . 'templates/admin-settings.php';
    }

    /**
     * Renderizza la pagina backup.
     */
    public function render_backups(): void {
        $backups = $this->plugin->get_backup()->get_backups();
        include WPC_PLUGIN_DIR . 'templates/admin-backups.php';
    }

    /**
     * Renderizza il log di sicurezza.
     */
    public function render_security_log(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'wpc_audit_log';
        $page_num = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
        $per_page = 50;
        $offset = ( $page_num - 1 ) * $per_page;

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        $logs = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ),
            ARRAY_A
        );

        $total_pages = ceil( $total / $per_page );

        include WPC_PLUGIN_DIR . 'templates/admin-security-log.php';
    }

    /**
     * Renderizza la pagina di sblocco di emergenza.
     */
    public function render_emergency_unlock(): void {
        include WPC_PLUGIN_DIR . 'templates/admin-emergency-unlock.php';
    }

    /**
     * Renderizza la pagina di setup iniziale.
     */
    public function render_setup(): void {
        include WPC_PLUGIN_DIR . 'templates/admin-setup.php';
    }

    /**
     * Gestisce il form di setup iniziale.
     */
    public function handle_setup_form(): void {
        if ( ! isset( $_POST['wpc_setup_nonce'] ) || ! wp_verify_nonce( $_POST['wpc_setup_nonce'], 'wpc_setup' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $crypto = $this->plugin->get_crypto();

        // Site ID dal pannello di controllo.
        $site_id = sanitize_text_field( $_POST['site_id'] ?? '' );
        if ( empty( $site_id ) ) {
            set_transient( 'wpc_setup_error', __( 'Il Site ID è obbligatorio. Registra il sito nel pannello WP Control Center per ottenerne uno.', 'wp-control' ), 30 );
            wp_safe_redirect( admin_url( 'admin.php?page=wpc-setup&error=1' ) );
            exit;
        }
        update_option( WPC_OPTION_PREFIX . 'site_id', $site_id );

        // API Token dal pannello di controllo.
        $api_token = sanitize_text_field( $_POST['api_token'] ?? '' );
        if ( empty( $api_token ) ) {
            set_transient( 'wpc_setup_error', __( 'L\'API Token è obbligatorio. Lo trovi nel pannello WP Control Center dopo aver registrato il sito.', 'wp-control' ), 30 );
            wp_safe_redirect( admin_url( 'admin.php?page=wpc-setup&error=1' ) );
            exit;
        }
        update_option( WPC_OPTION_PREFIX . 'api_token_encrypted', $crypto->encrypt( $api_token ) );

        // Master unlock code.
        $master_code = sanitize_text_field( $_POST['master_unlock_code'] ?? '' );
        if ( ! empty( $master_code ) ) {
            update_option( WPC_OPTION_PREFIX . 'master_unlock_hash', $crypto->hash_value( $master_code ) );
        }

        // Uninstall code.
        $uninstall_code = sanitize_text_field( $_POST['uninstall_code'] ?? '' );
        if ( ! empty( $uninstall_code ) ) {
            update_option( WPC_OPTION_PREFIX . 'uninstall_code_hash', $crypto->hash_value( $uninstall_code ) );
        }

        // Control panel URL.
        $panel_url = esc_url_raw( $_POST['control_panel_url'] ?? '' );
        if ( ! empty( $panel_url ) ) {
            update_option( WPC_OPTION_PREFIX . 'control_panel_url', $panel_url );
        }

        // IP autorizzati.
        $allowed_ips = sanitize_text_field( $_POST['allowed_ips'] ?? '' );
        update_option( WPC_OPTION_PREFIX . 'allowed_ips', $allowed_ips );

        // Segna come configurato.
        update_option( WPC_OPTION_PREFIX . 'configured', true );

        // Registra nel log.
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'wpc_audit_log', [
            'event_type'        => 'initial_setup',
            'event_description' => 'Setup iniziale completato.',
            'actor'             => wp_get_current_user()->user_login,
            'ip_address'        => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'metadata'          => wp_json_encode( [
                'site_id'   => $site_id,
                'panel_url' => $panel_url,
            ] ),
        ] );

        // Salva il site_id in un transient temporaneo per mostrarlo all'utente.
        set_transient( 'wpc_setup_site_id', $site_id, 300 );

        wp_safe_redirect( admin_url( 'admin.php?page=wp-control&setup=complete' ) );
        exit;
    }

    /**
     * Gestisce il form di sblocco di emergenza.
     */
    public function handle_emergency_unlock_form(): void {
        if ( ! isset( $_POST['wpc_emergency_nonce'] ) || ! wp_verify_nonce( $_POST['wpc_emergency_nonce'], 'wpc_emergency_unlock' ) ) {
            return;
        }

        $master_code = sanitize_text_field( $_POST['master_code'] ?? '' );
        $stored_hash = get_option( WPC_OPTION_PREFIX . 'master_unlock_hash', '' );

        if ( empty( $stored_hash ) || ! password_verify( $master_code, $stored_hash ) ) {
            set_transient( 'wpc_emergency_error', __( 'Codice master non valido.', 'wp-control' ), 30 );
            wp_safe_redirect( admin_url( 'admin.php?page=wpc-emergency-unlock&error=1' ) );
            exit;
        }

        // Sblocca.
        $this->plugin->get_lockdown()->deactivate_lock( 'sblocco_emergenza_locale' );

        // Disattiva la modalità ristretta.
        $this->plugin->get_heartbeat()->exit_restricted_mode();

        set_transient( 'wpc_emergency_success', __( 'Sito sbloccato con successo.', 'wp-control' ), 30 );
        wp_safe_redirect( admin_url( 'admin.php?page=wp-control&unlocked=1' ) );
        exit;
    }

    /**
     * Gestisce il form delle impostazioni.
     */
    public function handle_settings_form(): void {
        if ( ! isset( $_POST['wpc_settings_nonce'] ) || ! wp_verify_nonce( $_POST['wpc_settings_nonce'], 'wpc_settings' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Aggiorna le impostazioni.
        $settings_map = [
            'allowed_ips'           => 'sanitize_text_field',
            'disable_xmlrpc'        => 'boolval',
            'restrict_rest_api'     => 'boolval',
            'ownership_enforcement' => 'boolval',
            'grace_period_hours'    => 'intval',
            'heartbeat_interval_hours' => 'intval',
        ];

        foreach ( $settings_map as $key => $sanitizer ) {
            $value = $_POST[ $key ] ?? '';
            if ( $sanitizer === 'boolval' ) {
                $value = isset( $_POST[ $key ] ) ? true : false;
            } else {
                $value = $sanitizer( $value );
            }
            update_option( WPC_OPTION_PREFIX . $key, $value );
        }

        set_transient( 'wpc_settings_saved', true, 30 );
        wp_safe_redirect( admin_url( 'admin.php?page=wpc-settings&saved=1' ) );
        exit;
    }

    // --- Avvisi admin ---

    public function setup_required_notice(): void {
        echo '<div class="notice notice-warning"><p>';
        printf(
            '<strong>WP Control</strong> — %s <a href="%s">%s</a>',
            esc_html__( 'Il plugin richiede la configurazione iniziale.', 'wp-control' ),
            esc_url( admin_url( 'admin.php?page=wpc-setup' ) ),
            esc_html__( 'Configura ora', 'wp-control' )
        );
        echo '</p></div>';
    }

    public function lock_mode_notice(): void {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>&#128274; WP Control</strong> — ';
        esc_html_e( 'Il sito è attualmente in modalità di blocco. L\'accesso è limitato.', 'wp-control' );
        echo ' <a href="' . esc_url( admin_url( 'admin.php?page=wpc-emergency-unlock' ) ) . '">';
        esc_html_e( 'Sblocco di emergenza', 'wp-control' );
        echo '</a>';
        echo '</p></div>';
    }

    public function deactivation_error_notice(): void {
        if ( isset( $_GET['wpc_error'] ) && $_GET['wpc_error'] === 'unauthorized_deactivation' ) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>WP Control</strong> — ';
            esc_html_e( 'La disattivazione del plugin richiede il codice di autorizzazione.', 'wp-control' );
            echo '</p></div>';
        }
    }
}
