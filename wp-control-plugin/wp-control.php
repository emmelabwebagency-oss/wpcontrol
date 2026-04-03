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

// Inizializzazione del plugin.
function ltk_init(): void {
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
