<?php
/**
 * Plugin Name:       WP License Template KIT
 * Plugin URI:        https://example.com/wp-ltk
 * Description:       Kit di gestione licenze e template per WordPress.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            LTK Team
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-ltk
 * Domain Path:       /languages
 *
 * @package LicenseTemplateKit
 */

namespace LicenseTemplateKit;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Impedisci accesso diretto.
}

// Costanti del plugin.
define( 'LTK_VERSION', '1.0.0' );
define( 'LTK_PLUGIN_FILE', __FILE__ );
define( 'LTK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LTK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LTK_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'LTK_OPTION_PREFIX', 'ltk_' );

// Autoloader per le classi del plugin.
spl_autoload_register( function ( $class ) {
    $prefix = 'LicenseTemplateKit\\';
    $base_dir = LTK_PLUGIN_DIR . 'includes/';

    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, $len );
    $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

    if ( file_exists( $file ) ) {
        require $file;
    }
} );

// Migrazione automatica delle opzioni da wpc_ a ltk_ (per aggiornamento da versione precedente).
function ltk_migrate_options(): void {
    // Se già migrato o installazione fresca, salta.
    if ( get_option( 'ltk_migrated_from_wpc', false ) ) {
        return;
    }

    // Controlla se esistono vecchie opzioni wpc_.
    $old_configured = get_option( 'wpc_configured', null );
    if ( null === $old_configured ) {
        // Installazione fresca, nessuna migrazione necessaria.
        return;
    }

    global $wpdb;

    // Migra tutte le opzioni wpc_ a ltk_.
    $old_options = $wpdb->get_results(
        "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'wpc_%'",
        ARRAY_A
    );

    foreach ( $old_options as $opt ) {
        $new_name = 'ltk_' . substr( $opt['option_name'], 4 ); // Rimuovi 'wpc_' e aggiungi 'ltk_'.
        if ( ! get_option( $new_name, null ) ) {
            update_option( $new_name, $opt['option_value'] );
        }
    }

    // Migra anche i cron vecchi.
    wp_clear_scheduled_hook( 'wpc_heartbeat_cron' );
    wp_clear_scheduled_hook( 'wpc_tamper_check_cron' );

    // Segna come migrato.
    update_option( 'ltk_migrated_from_wpc', true );
}

// Inizializzazione del plugin.
function ltk_init(): void {
    // Migra le opzioni dalla versione precedente se necessario.
    ltk_migrate_options();

    // Carica le traduzioni.
    load_plugin_textdomain( 'wp-ltk', false, dirname( LTK_PLUGIN_BASENAME ) . '/languages' );

    // Avvia il core del plugin.
    $plugin = Core\Plugin::get_instance();
    $plugin->init();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\ltk_init' );

// Hook di attivazione.
register_activation_hook( __FILE__, function () {
    Core\Activator::activate();
} );

// Hook di disattivazione (protetto).
register_deactivation_hook( __FILE__, function () {
    Core\Deactivator::deactivate();
} );
