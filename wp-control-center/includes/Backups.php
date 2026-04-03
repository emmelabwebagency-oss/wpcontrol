<?php
/**
 * WP Control Center - Gestione Backup.
 */

if ( ! defined( 'WPC_ROOT' ) ) {
    exit( 'Accesso diretto non consentito.' );
}

class WPC_Backups {

    /**
     * Registra un nuovo backup.
     */
    public static function register( string $site_id, string $backup_id, array $meta = [] ): string {
        $db = WPC_Database::get_instance();
        $id = WPC_Database::generate_uuid();

        $db->insert( 'backups', [
            'id'           => $id,
            'backup_id'    => $backup_id,
            'site_id'      => $site_id,
            'file_size'    => $meta['file_size'] ?? 0,
            'checksum'     => $meta['checksum'] ?? null,
            'wp_version'   => $meta['wp_version'] ?? null,
            'php_version'  => $meta['php_version'] ?? null,
            'active_theme' => $meta['active_theme'] ?? null,
            'plugin_list'  => isset( $meta['plugin_list'] ) ? json_encode( $meta['plugin_list'] ) : null,
            'encrypted'    => isset( $meta['encrypted'] ) ? 1 : 0,
            'status'       => 'pending',
            'created_at'   => date( 'Y-m-d H:i:s' ),
            'updated_at'   => date( 'Y-m-d H:i:s' ),
        ] );

        return $id;
    }

    /**
     * Aggiorna lo stato del backup come salvato.
     */
    public static function mark_as_stored( string $backup_id, string $file_path, int $file_size, string $checksum ): bool {
        $db = WPC_Database::get_instance();
        return $db->update( 'backups', [
            'file_path'  => $file_path,
            'file_size'  => $file_size,
            'checksum'   => $checksum,
            'status'     => 'stored',
            'updated_at' => date( 'Y-m-d H:i:s' ),
        ], 'backup_id = ?', [ $backup_id ] ) > 0;
    }

    /**
     * Trova backup per sito.
     */
    public static function find_by_site_id( string $site_id ): array {
        $db = WPC_Database::get_instance();
        return $db->fetch_all(
            "SELECT * FROM backups WHERE site_id = ? ORDER BY created_at DESC",
            [ $site_id ]
        );
    }

    /**
     * Trova un backup per backup_id.
     */
    public static function find_by_backup_id( string $backup_id ): ?array {
        $db = WPC_Database::get_instance();
        return $db->fetch_one(
            "SELECT * FROM backups WHERE backup_id = ? LIMIT 1",
            [ $backup_id ]
        );
    }

    /**
     * Restituisce tutti i backup con paginazione.
     */
    public static function find_all( int $limit = 50, int $offset = 0 ): array {
        $db = WPC_Database::get_instance();
        return $db->fetch_all(
            "SELECT b.*, s.site_name, s.site_url FROM backups b LEFT JOIN sites s ON b.site_id = s.site_id ORDER BY b.created_at DESC LIMIT ? OFFSET ?",
            [ $limit, $offset ]
        );
    }

    /**
     * Segna un backup come richiesta di ripristino.
     */
    public static function mark_restore_requested( string $backup_id ): bool {
        $db = WPC_Database::get_instance();
        $affected = $db->update( 'backups', [
            'status'     => 'restore_requested',
            'updated_at' => date( 'Y-m-d H:i:s' ),
        ], 'backup_id = ?', [ $backup_id ] );

        if ( $affected > 0 ) {
            $backup = self::find_by_backup_id( $backup_id );
            WPC_Audit::log( 'backup_restore_requested', "Ripristino richiesto per backup {$backup_id}", null, null, $backup['site_id'] ?? null );
        }

        return $affected > 0;
    }

    /**
     * Conta i backup totali.
     */
    public static function count_all(): int {
        $db = WPC_Database::get_instance();
        return (int) $db->fetch_value( "SELECT COUNT(*) FROM backups" );
    }

    /**
     * Restituisce la dimensione totale dei backup.
     */
    public static function total_size(): int {
        $db = WPC_Database::get_instance();
        return (int) $db->fetch_value( "SELECT COALESCE(SUM(file_size), 0) FROM backups" );
    }

    /**
     * Elimina un backup (record + file).
     */
    public static function delete( string $backup_id ): bool {
        $db = WPC_Database::get_instance();
        $backup = self::find_by_backup_id( $backup_id );
        if ( ! $backup ) {
            return false;
        }

        // Elimina il file fisico se esiste.
        if ( ! empty( $backup['file_path'] ) && file_exists( $backup['file_path'] ) ) {
            unlink( $backup['file_path'] );
        }

        $db->query( "DELETE FROM backups WHERE backup_id = ?", [ $backup_id ] );
        WPC_Audit::log( 'backup_deleted', "Backup eliminato: {$backup_id}", null, null, $backup['site_id'] );

        return true;
    }
}
