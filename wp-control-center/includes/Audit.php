<?php
/**
 * WP Control Center - Audit Log.
 */

if ( ! defined( 'WPC_ROOT' ) ) {
    exit( 'Accesso diretto non consentito.' );
}

class WPC_Audit {

    /**
     * Registra un evento nel log di audit.
     */
    public static function log(
        string  $action,
        string  $description = '',
        ?string $user_id     = null,
        ?string $user_email  = null,
        ?string $site_id     = null,
        ?string $ip_address  = null,
        ?array  $metadata    = null
    ): void {
        $db = WPC_Database::get_instance();

        if ( null === $user_id && isset( $_SESSION['wpc_user_id'] ) ) {
            $user_id    = $_SESSION['wpc_user_id'];
            $user_email = $_SESSION['wpc_user_email'] ?? null;
        }

        if ( null === $ip_address ) {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        }

        $db->insert( 'audit_logs', [
            'id'          => WPC_Database::generate_uuid(),
            'action'      => $action,
            'description' => $description,
            'user_id'     => $user_id,
            'user_email'  => $user_email,
            'site_id'     => $site_id,
            'ip_address'  => $ip_address,
            'metadata'    => $metadata ? json_encode( $metadata ) : null,
            'created_at'  => date( 'Y-m-d H:i:s' ),
        ] );
    }

    /**
     * Recupera i log di audit con paginazione.
     */
    public static function get_logs( int $limit = 50, int $offset = 0, ?string $site_id = null ): array {
        $db = WPC_Database::get_instance();

        $where = '';
        $params = [];

        if ( $site_id ) {
            $where = 'WHERE site_id = ?';
            $params[] = $site_id;
        }

        $params[] = $limit;
        $params[] = $offset;

        return $db->fetch_all(
            "SELECT * FROM audit_logs {$where} ORDER BY created_at DESC LIMIT ? OFFSET ?",
            $params
        );
    }

    /**
     * Conta il totale dei log.
     */
    public static function count_logs( ?string $site_id = null ): int {
        $db = WPC_Database::get_instance();

        if ( $site_id ) {
            return (int) $db->fetch_value(
                "SELECT COUNT(*) FROM audit_logs WHERE site_id = ?",
                [ $site_id ]
            );
        }

        return (int) $db->fetch_value( "SELECT COUNT(*) FROM audit_logs" );
    }
}
