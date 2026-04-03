<?php
/**
 * WP Control Center - Bootstrap.
 * Caricato da tutti gli script per inizializzare l'ambiente.
 */

if ( ! defined( 'WPC_ROOT' ) ) {
    define( 'WPC_ROOT', dirname( __DIR__ ) );
}

// Carica la configurazione.
require_once WPC_ROOT . '/config/config.php';

// Imposta timezone.
date_default_timezone_set( WPC_TIMEZONE );

// Gestione errori.
if ( WPC_DEBUG ) {
    error_reporting( E_ALL );
    ini_set( 'display_errors', '1' );
} else {
    error_reporting( 0 );
    ini_set( 'display_errors', '0' );
}

// Carica le classi.
require_once WPC_ROOT . '/includes/Database.php';
require_once WPC_ROOT . '/includes/Auth.php';
require_once WPC_ROOT . '/includes/Audit.php';
require_once WPC_ROOT . '/includes/HmacAuth.php';
require_once WPC_ROOT . '/includes/Sites.php';
require_once WPC_ROOT . '/includes/Backups.php';
require_once WPC_ROOT . '/includes/Alerts.php';
require_once WPC_ROOT . '/includes/Users.php';

// Inizializza la sessione per le pagine web (non per le API).
$is_api_request = str_contains( $_SERVER['REQUEST_URI'] ?? '', '/api/' );
if ( ! $is_api_request ) {
    WPC_Auth::init_session();
}
