<?php
/**
 * WP Control Center - Gestione Siti.
 */

if ( ! defined( 'WPC_ROOT' ) ) {
    exit( 'Accesso diretto non consentito.' );
}

class WPC_Sites {

    /**
     * Registra un nuovo sito.
     */
    public static function register( string $site_name, string $site_url, string $api_token ): array {
        $db = WPC_Database::get_instance();

        $site_id = WPC_Database::generate_uuid();
        $domain  = parse_url( $site_url, PHP_URL_HOST ) ?: $site_url;
        $uninstall_code = bin2hex( random_bytes( 16 ) );

        $db->insert( 'sites', [
            'id'         => WPC_Database::generate_uuid(),
            'site_id'    => $site_id,
            'site_name'  => $site_name,
            'site_url'   => $site_url,
            'domain'     => $domain,
            'api_token_hash' => $api_token,
            'uninstall_code' => $uninstall_code,
            'status'     => 'active',
            'is_locked'  => 0,
            'grace_period_hours' => 72,
            'ownership_enforcement' => 1,
            'created_at' => date( 'Y-m-d H:i:s' ),
            'updated_at' => date( 'Y-m-d H:i:s' ),
        ] );

        WPC_Audit::log( 'site_registered', "Sito registrato: {$site_name} ({$site_url})", null, null, $site_id );

        return [
            'site_id'        => $site_id,
            'api_token'      => $api_token,
            'uninstall_code' => $uninstall_code,
        ];
    }

    /**
     * Restituisce tutti i siti.
     */
    public static function find_all(): array {
        $db = WPC_Database::get_instance();
        return $db->fetch_all( "SELECT * FROM sites ORDER BY created_at DESC" );
    }

    /**
     * Cerca un sito per site_id.
     */
    public static function find_by_site_id( string $site_id ): ?array {
        $db = WPC_Database::get_instance();
        return $db->fetch_one( "SELECT * FROM sites WHERE site_id = ? LIMIT 1", [ $site_id ] );
    }

    /**
     * Cerca un sito per id.
     */
    public static function find_by_id( string $id ): ?array {
        $db = WPC_Database::get_instance();
        return $db->fetch_one( "SELECT * FROM sites WHERE id = ? LIMIT 1", [ $id ] );
    }

    /**
     * Aggiorna lo stato di blocco di un sito.
     */
    public static function update_lock_status( string $site_id, bool $locked, ?string $locked_by = null ): bool {
        $db = WPC_Database::get_instance();

        $data = [
            'is_locked'  => $locked ? 1 : 0,
            'status'     => $locked ? 'locked' : 'active',
            'updated_at' => date( 'Y-m-d H:i:s' ),
        ];

        if ( $locked ) {
            $data['locked_at'] = date( 'Y-m-d H:i:s' );
            $data['locked_by'] = $locked_by;
        } else {
            $data['locked_at'] = null;
            $data['locked_by'] = null;
        }

        $affected = $db->update( 'sites', $data, 'site_id = ?', [ $site_id ] );

        $action = $locked ? 'site_locked' : 'site_unlocked';
        WPC_Audit::log( $action, "Sito {$site_id} " . ( $locked ? 'bloccato' : 'sbloccato' ), $locked_by, null, $site_id );

        return $affected > 0;
    }

    /**
     * Aggiorna i dati dell'heartbeat.
     */
    public static function update_heartbeat( string $site_id, array $data ): bool {
        $db = WPC_Database::get_instance();

        $update = [
            'last_heartbeat_at' => date( 'Y-m-d H:i:s' ),
            'status'            => 'active',
            'updated_at'        => date( 'Y-m-d H:i:s' ),
        ];

        if ( isset( $data['wp_version'] ) )   $update['wp_version']   = $data['wp_version'];
        if ( isset( $data['php_version'] ) )   $update['php_version']  = $data['php_version'];
        if ( isset( $data['active_theme'] ) )  $update['active_theme'] = $data['active_theme'];
        if ( isset( $data['plugin_count'] ) )  $update['plugin_count'] = (int) $data['plugin_count'];
        if ( isset( $data['is_locked'] ) )     $update['is_locked']    = $data['is_locked'] ? 1 : 0;

        return $db->update( 'sites', $update, 'site_id = ?', [ $site_id ] ) > 0;
    }

    /**
     * Ruota le credenziali API di un sito.
     */
    public static function rotate_credentials( string $site_id ): ?string {
        $db = WPC_Database::get_instance();
        $new_token = bin2hex( random_bytes( 32 ) );

        $affected = $db->update( 'sites', [
            'api_token_hash' => $new_token,
            'updated_at'     => date( 'Y-m-d H:i:s' ),
        ], 'site_id = ?', [ $site_id ] );

        if ( $affected > 0 ) {
            WPC_Audit::log( 'credentials_rotated', "Credenziali ruotate per sito {$site_id}", null, null, $site_id );
            return $new_token;
        }

        return null;
    }

    /**
     * Rimuove un sito.
     */
    public static function remove( string $site_id ): bool {
        $db = WPC_Database::get_instance();
        WPC_Audit::log( 'site_removed', "Sito rimosso: {$site_id}", null, null, $site_id );
        $db->query( "DELETE FROM sites WHERE site_id = ?", [ $site_id ] );
        return true;
    }

    /**
     * Conta i siti per stato.
     */
    public static function count_by_status(): array {
        $db = WPC_Database::get_instance();
        $results = $db->fetch_all( "SELECT status, COUNT(*) as count FROM sites GROUP BY status" );
        $counts = [ 'total' => 0, 'active' => 0, 'locked' => 0, 'offline' => 0, 'pending' => 0, 'restricted' => 0 ];
        foreach ( $results as $row ) {
            $counts[ $row['status'] ] = (int) $row['count'];
            $counts['total'] += (int) $row['count'];
        }
        return $counts;
    }

    /**
     * Invia un comando a un sito WordPress tramite HTTP.
     */
    public static function send_command( string $site_id, string $command, array $params = [] ): array {
        $site = self::find_by_site_id( $site_id );
        if ( ! $site ) {
            return [ 'success' => false, 'message' => 'Sito non trovato.' ];
        }

        $endpoint_map = [
            'lock'              => '/wp-json/wp-control/v1/lock',
            'unlock'            => '/wp-json/wp-control/v1/unlock',
            'backup'            => '/wp-json/wp-control/v1/backup/create',
            'restore'           => '/wp-json/wp-control/v1/backup/restore',
            'integrity_check'   => '/wp-json/wp-control/v1/integrity-check',
            'rotate_credentials'=> '/wp-json/wp-control/v1/rotate-credentials',
        ];

        $path = $endpoint_map[ $command ] ?? null;
        if ( ! $path ) {
            return [ 'success' => false, 'message' => 'Comando non riconosciuto.' ];
        }

        $url  = rtrim( $site['site_url'], '/' ) . $path;
        $body = json_encode( $params );

        // Firma la richiesta.
        $hmac_headers = WPC_HmacAuth::sign_request( $site['api_token_hash'], $body );

        // Invio con cURL.
        $ch = curl_init( $url );
        curl_setopt_array( $ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => array_merge(
                [
                    'Content-Type: application/json',
                    'X-WPC-Site-ID: ' . $site['site_id'],
                ],
                array_map(
                    fn( $k, $v ) => "{$k}: {$v}",
                    array_keys( $hmac_headers ),
                    array_values( $hmac_headers )
                )
            ),
        ] );

        $response = curl_exec( $ch );
        $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $error = curl_error( $ch );
        curl_close( $ch );

        if ( $error ) {
            return [ 'success' => false, 'message' => "Errore cURL: {$error}" ];
        }

        WPC_Audit::log( "command_{$command}", "Comando {$command} inviato a {$site_id}, risposta HTTP {$http_code}", null, null, $site_id );

        return [
            'success'   => $http_code >= 200 && $http_code < 300,
            'http_code' => $http_code,
            'response'  => json_decode( $response, true ) ?: $response,
        ];
    }
}
