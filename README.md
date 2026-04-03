# WP Control System

Sistema di sicurezza e gestione remota per siti WordPress, composto da due componenti:

1. **wp-control-center** - Pannello di controllo web (PHP + MySQL) per la gestione centralizzata
2. **wp-control-plugin** - Plugin WordPress da installare sui siti da gestire

## Requisiti

### WP Control Center (Pannello di Controllo)
- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.3+
- Apache con mod_rewrite
- Estensioni PHP: PDO, pdo_mysql, mbstring, json, curl, openssl
- Compatibile con hosting Netsons

### WP Control Plugin
- WordPress 6.0+
- PHP 8.0+

## Installazione del Pannello di Controllo

### 1. Upload dei file
Carica la cartella `wp-control-center/` nella root del tuo hosting.

### 2. Configurazione
Modifica il file `wp-control-center/config/config.php` con i dati del tuo database:

```php
define('WPC_DB_HOST', 'localhost');
define('WPC_DB_NAME', 'nome_database');
define('WPC_DB_USER', 'utente_database');
define('WPC_DB_PASSWORD', 'password_database');
define('WPC_SECRET_KEY', 'chiave-segreta-lunga-e-casuale');
```

In alternativa, puoi impostare le variabili d'ambiente corrispondenti.

### 3. Installazione database
Accedi a `https://tuosito.com/wp-control-center/install.php` dal browser per creare le tabelle del database e l'utente amministratore.

### 4. Accesso
Dopo l'installazione, accedi a `https://tuosito.com/wp-control-center/login.php` con le credenziali create durante l'installazione.

## Installazione del Plugin WordPress

### 1. Upload
Carica la cartella `wp-control-plugin/` nella directory `wp-content/plugins/` del sito WordPress.

### 2. Attivazione
Attiva il plugin "WP Control" dalla pagina Plugin di WordPress.

### 3. Configurazione
Dalla pagina di setup del plugin, inserisci:
- **URL del Pannello di Controllo**: `https://tuosito.com/wp-control-center/`
- **API Token**: il token generato durante la registrazione del sito nel pannello

## Funzionalita

### Pannello di Controllo
- **Dashboard**: panoramica con statistiche aggregate di tutti i siti
- **Gestione Siti**: registrazione, monitoraggio, lock/unlock, rotazione credenziali
- **Backup**: visualizzazione, ripristino e download dei backup
- **Alert**: gestione alert di sicurezza con filtri per severita
- **Audit Log**: registro completo di tutte le azioni
- **Gestione Utenti**: ruoli admin, operator, viewer con RBAC

### Plugin WordPress
- **Heartbeat**: invio periodico dello stato del sito al pannello
- **Lock Mode**: blocco completo del sito (frontend, admin, login)
- **Backup**: creazione backup crittografati (file + database)
- **Tamper Detection**: monitoraggio integrita file e opzioni critiche
- **Plugin Protection**: auto-protezione del plugin da disattivazione/rimozione
- **Ownership Enforcement**: blocco automatico se il pannello non risponde

## Sicurezza

- Autenticazione HMAC-SHA256 per tutte le comunicazioni plugin-pannello
- Protezione anti-replay con nonce e timestamp (tolleranza 5 minuti)
- Password hashate con bcrypt (cost 12)
- Protezione CSRF su tutti i form
- Rate limiting sui tentativi di login (5 tentativi / 15 minuti)
- Prepared statements PDO per tutte le query SQL
- Headers di sicurezza (X-Content-Type-Options, X-Frame-Options, etc.)
- Backup crittografati con AES-256-CBC

## Struttura del Progetto

```
wpcontrol/
├── wp-control-center/          # Pannello di controllo (PHP)
│   ├── api/                    # Endpoint API per il plugin
│   │   ├── heartbeat.php
│   │   ├── tamper.php
│   │   ├── backup-upload.php
│   │   ├── site-register.php
│   │   ├── site-lock.php
│   │   └── status.php
│   ├── assets/                 # CSS e JavaScript
│   ├── backups/                # Storage backup (protetto da .htaccess)
│   ├── config/                 # Configurazione
│   ├── includes/               # Classi PHP
│   │   ├── Database.php
│   │   ├── Auth.php
│   │   ├── HmacAuth.php
│   │   ├── Sites.php
│   │   ├── Backups.php
│   │   ├── Alerts.php
│   │   ├── Audit.php
│   │   ├── Users.php
│   │   └── bootstrap.php
│   ├── templates/              # Header e footer
│   ├── index.php               # Dashboard
│   ├── login.php               # Pagina di login
│   ├── sites.php               # Gestione siti
│   ├── backups.php             # Gestione backup
│   ├── alerts.php              # Gestione alert
│   ├── audit.php               # Log di audit
│   ├── users.php               # Gestione utenti
│   ├── install.php             # Script di installazione
│   └── .htaccess               # Configurazione Apache
│
└── wp-control-plugin/          # Plugin WordPress
    ├── includes/
    │   ├── Core/               # Inizializzazione plugin
    │   ├── Security/           # HMAC, crittografia, validazione
    │   ├── Heartbeat/          # Heartbeat manager
    │   ├── Lockdown/           # Motore di blocco
    │   ├── Backup/             # Gestore backup
    │   ├── TamperDetection/    # Monitoraggio integrita
    │   ├── Protection/         # Auto-protezione plugin
    │   ├── Admin/              # Pagine admin WordPress
    │   └── Api/                # REST API endpoints
    ├── templates/              # Template admin e lock page
    ├── wp-control.php          # File principale del plugin
    └── uninstall.php           # Pulizia disinstallazione
```

## Ruoli Utente (Pannello di Controllo)

| Ruolo | Descrizione |
|-------|-------------|
| **Admin** | Accesso completo: gestione utenti, eliminazione siti/backup |
| **Operator** | Gestione siti: lock/unlock, backup, alert |
| **Viewer** | Solo lettura: visualizzazione dashboard, siti, alert |

## Licenza

Proprietary - Emmelab Web Agency
