<?php
/**
 * WP Control Center - Configurazione principale.
 * 
 * Copia questo file come config.php e modifica i valori.
 * Su Netsons: usa i dati del tuo database MySQL forniti dal pannello cPanel/Plesk.
 */

if ( ! defined( 'WPC_ROOT' ) ) {
    exit( 'Accesso diretto non consentito.' );
}

// --- Database MySQL ---
define( 'WPC_DB_HOST',     getenv('WPC_DB_HOST')     ?: 'localhost' );
define( 'WPC_DB_NAME',     getenv('WPC_DB_NAME')     ?: 'wp_control_center' );
define( 'WPC_DB_USER',     getenv('WPC_DB_USER')     ?: 'wpc_user' );
define( 'WPC_DB_PASSWORD', getenv('WPC_DB_PASSWORD') ?: '' );
define( 'WPC_DB_CHARSET',  'utf8mb4' );

// --- Applicazione ---
define( 'WPC_APP_NAME',    'WP Control Center' );
define( 'WPC_APP_VERSION', '1.0.0' );
define( 'WPC_APP_URL',     getenv('WPC_APP_URL') ?: 'https://panel.example.com' );
define( 'WPC_TIMEZONE',    'Europe/Rome' );

// --- Sicurezza ---
// Chiave segreta per firme CSRF e sessioni. Deve essere una stringa casuale lunga almeno 64 caratteri.
define( 'WPC_SECRET_KEY', getenv('WPC_SECRET_KEY') ?: 'CHANGE-ME-genera-una-stringa-casuale-di-almeno-64-caratteri-qui-1234567890abcdef' );

// Durata sessione in secondi (default: 8 ore).
define( 'WPC_SESSION_LIFETIME', 28800 );

// Rate limiting: tentativi di login massimi per IP in 15 minuti.
define( 'WPC_LOGIN_MAX_ATTEMPTS', 5 );
define( 'WPC_LOGIN_LOCKOUT_MINUTES', 15 );

// --- Backup Storage ---
// Directory locale per i backup ricevuti dai siti.
define( 'WPC_BACKUP_DIR', __DIR__ . '/../backups/' );

// --- HMAC ---
// Tolleranza timestamp per la verifica delle firme HMAC (secondi).
define( 'WPC_HMAC_TOLERANCE', 300 ); // 5 minuti.

// --- Debug ---
define( 'WPC_DEBUG', getenv('WPC_DEBUG') === 'true' );
