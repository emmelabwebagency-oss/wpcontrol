<?php
/**
 * Plugin Name:       WP Control
 * Plugin URI:        https://example.com/wp-control
 * Description:       Plugin di sicurezza e controllo proprietà per WordPress. Protezione lock/unlock remoto, backup, recovery e tamper detection.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            WP Control Team
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-control
 * Domain Path:       /languages
 *
 * @package WPControl
 */

namespace WPControl;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Impedisci accesso diretto.
}

// Costanti del plugin.
define( 'WPC_VERSION', '1.0.0' );
define( 'WPC_PLUGIN_FILE', __FILE__ );
define( 'WPC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WPC_OPTION_PREFIX', 'wpc_' );

// Autoloader per le classi del plugin.
spl_autoload_register( function ( $class ) {
    $prefix = 'WPControl\\';
    $base_dir = WPC_PLUGIN_DIR . 'includes/';

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

// Inizializzazione del plugin.
function wpc_init(): void {
    // Carica le traduzioni.
    load_plugin_textdomain( 'wp-control', false, dirname( WPC_PLUGIN_BASENAME ) . '/languages' );

    // Avvia il core del plugin.
    $plugin = Core\Plugin::get_instance();
    $plugin->init();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\wpc_init' );

// Hook di attivazione.
register_activation_hook( __FILE__, function () {
    Core\Activator::activate();
} );

// Hook di disattivazione (protetto).
register_deactivation_hook( __FILE__, function () {
    Core\Deactivator::deactivate();
} );
