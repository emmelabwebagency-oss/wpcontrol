<?php
/**
 * WP Control Center - Gestione Alert.
 */

if ( ! defined( 'WPC_ROOT' ) ) {
    exit( 'Accesso diretto non consentito.' );
}

class WPC_Alerts {

    /**
     * Crea un nuovo alert.
     */
    public static function create( string $site_id, string $event_type, string $description = '', ?array $details = null, ?string $ip_address = null ): string {
        $db = WPC_Database::get_instance();
        $id = WPC_Database::generate_uuid();

        $severity = self::determine_severity( $event_type );

        $db->insert( 'alerts', [
            'id'          => $id,
            'site_id'     => $site_id,
            'event_type'  => $event_type,
            'severity'    => $severity,
            'description' => $description,
            'details'     => $details ? json_encode( $details ) : null,
            'ip_address'  => $ip_address ?: ( $_SERVER['REMOTE_ADDR'] ?? null ),
            'acknowledged'    => 0,
            'acknowledged_by' => null,
            'acknowledged_at' => null,
            'created_at'  => date( 'Y-m-d H:i:s' ),
        ] );

        return $id;
    }

    /**
     * Restituisce tutti gli alert con paginazione.
     */
    public static function find_all( int $limit = 50, int $offset = 0 ): array {
        $db = WPC_Database::get_instance();
        return $db->fetch_all(
            "SELECT a.*, s.site_name, s.site_url FROM alerts a LEFT JOIN sites s ON a.site_id = s.site_id ORDER BY a.created_at DESC LIMIT ? OFFSET ?",
            [ $limit, $offset ]
        );
    }

    /**
     * Trova alert per sito.
     */
    public static function find_by_site_id( string $site_id ): array {
        $db = WPC_Database::get_instance();
        return $db->fetch_all(
            "SELECT * FROM alerts WHERE site_id = ? ORDER BY created_at DESC",
            [ $site_id ]
        );
    }

    /**
     * Trova alert non riconosciuti.
     */
    public static function find_unacknowledged(): array {
        $db = WPC_Database::get_instance();
        return $db->fetch_all(
            "SELECT a.*, s.site_name, s.site_url FROM alerts a LEFT JOIN sites s ON a.site_id = s.site_id WHERE a.acknowledged = 0 ORDER BY a.created_at DESC"
        );
    }

    /**
     * Riconosci un alert.
     */
    public static function acknowledge( string $id, string $user_id ): bool {
        $db = WPC_Database::get_instance();
        $affected = $db->update( 'alerts', [
            'acknowledged'    => 1,
            'acknowledged_by' => $user_id,
            'acknowledged_at' => date( 'Y-m-d H:i:s' ),
        ], 'id = ?', [ $id ] );

        if ( $affected > 0 ) {
            $alert = $db->fetch_one( "SELECT * FROM alerts WHERE id = ?", [ $id ] );
            WPC_Audit::log( 'alert_acknowledged', "Alert riconosciuto: {$id}", $user_id, null, $alert['site_id'] ?? null );
        }

        return $affected > 0;
    }

    /**
     * Conta gli alert non riconosciuti.
     */
    public static function count_unacknowledged(): int {
        $db = WPC_Database::get_instance();
        return (int) $db->fetch_value( "SELECT COUNT(*) FROM alerts WHERE acknowledged = 0" );
    }

    /**
     * Conta gli alert totali.
     */
    public static function count_all(): int {
        $db = WPC_Database::get_instance();
        return (int) $db->fetch_value( "SELECT COUNT(*) FROM alerts" );
    }

    /**
     * Conta gli alert per severita'.
     */
    public static function count_by_severity(): array {
        $db = WPC_Database::get_instance();
        $results = $db->fetch_all( "SELECT severity, COUNT(*) as count FROM alerts WHERE acknowledged = 0 GROUP BY severity" );
        $counts = [ 'low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0 ];
        foreach ( $results as $row ) {
            $counts[ $row['severity'] ] = (int) $row['count'];
        }
        return $counts;
    }

    /**
     * Determina la severita' in base al tipo di evento.
     */
    private static function determine_severity( string $event_type ): string {
        $severity_map = [
            'file_modified'         => 'high',
            'file_added'            => 'medium',
            'file_deleted'          => 'high',
            'core_file_modified'    => 'critical',
            'plugin_deactivation'   => 'critical',
            'plugin_deletion'       => 'critical',
            'option_changed'        => 'medium',
            'unauthorized_access'   => 'high',
            'brute_force'           => 'high',
            'heartbeat_missed'      => 'medium',
            'site_unreachable'      => 'high',
            'integrity_check_failed'=> 'critical',
            'backup_failed'         => 'high',
            'tamper_detected'       => 'critical',
        ];

        return $severity_map[ $event_type ] ?? 'medium';
    }
}
