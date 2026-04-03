<?php
/**
 * WP Control Center - Gestione Utenti.
 */

if ( ! defined( 'WPC_ROOT' ) ) {
    exit( 'Accesso diretto non consentito.' );
}

class WPC_Users {

    /**
     * Crea un nuovo utente.
     */
    public static function create( string $email, string $password, string $role = 'viewer', string $first_name = '', string $last_name = '' ): string {
        $db = WPC_Database::get_instance();
        $id = WPC_Database::generate_uuid();

        $db->insert( 'users', [
            'id'            => $id,
            'email'         => $email,
            'password_hash' => password_hash( $password, PASSWORD_BCRYPT, [ 'cost' => 12 ] ),
            'role'          => $role,
            'first_name'    => $first_name,
            'last_name'     => $last_name,
            'is_active'     => 1,
            'mfa_enabled'   => 0,
            'mfa_secret'    => null,
            'created_at'    => date( 'Y-m-d H:i:s' ),
            'updated_at'    => date( 'Y-m-d H:i:s' ),
        ] );

        WPC_Audit::log( 'user_created', "Utente creato: {$email} (ruolo: {$role})", null, $email );

        return $id;
    }

    /**
     * Trova utente per email.
     */
    public static function find_by_email( string $email ): ?array {
        $db = WPC_Database::get_instance();
        return $db->fetch_one( "SELECT * FROM users WHERE email = ? LIMIT 1", [ $email ] );
    }

    /**
     * Trova utente per ID.
     */
    public static function find_by_id( string $id ): ?array {
        $db = WPC_Database::get_instance();
        return $db->fetch_one( "SELECT * FROM users WHERE id = ? LIMIT 1", [ $id ] );
    }

    /**
     * Restituisce tutti gli utenti.
     */
    public static function find_all(): array {
        $db = WPC_Database::get_instance();
        return $db->fetch_all( "SELECT id, email, role, first_name, last_name, is_active, mfa_enabled, last_login_at, last_login_ip, created_at FROM users ORDER BY created_at DESC" );
    }

    /**
     * Aggiorna un utente.
     */
    public static function update( string $id, array $data ): bool {
        $db = WPC_Database::get_instance();
        $allowed = [ 'email', 'role', 'first_name', 'last_name', 'is_active' ];
        $update = [];
        foreach ( $allowed as $field ) {
            if ( array_key_exists( $field, $data ) ) {
                $update[ $field ] = $data[ $field ];
            }
        }

        if ( ! empty( $data['password'] ) ) {
            $update['password_hash'] = password_hash( $data['password'], PASSWORD_BCRYPT, [ 'cost' => 12 ] );
        }

        if ( empty( $update ) ) {
            return false;
        }

        $update['updated_at'] = date( 'Y-m-d H:i:s' );
        return $db->update( 'users', $update, 'id = ?', [ $id ] ) > 0;
    }

    /**
     * Elimina un utente (disattiva).
     */
    public static function deactivate( string $id ): bool {
        $db = WPC_Database::get_instance();
        $user = self::find_by_id( $id );
        if ( $user ) {
            WPC_Audit::log( 'user_deactivated', "Utente disattivato: {$user['email']}", null, $user['email'] );
        }
        return $db->update( 'users', [ 'is_active' => 0, 'updated_at' => date( 'Y-m-d H:i:s' ) ], 'id = ?', [ $id ] ) > 0;
    }

    /**
     * Riattiva un utente.
     */
    public static function activate( string $id ): bool {
        $db = WPC_Database::get_instance();
        return $db->update( 'users', [ 'is_active' => 1, 'updated_at' => date( 'Y-m-d H:i:s' ) ], 'id = ?', [ $id ] ) > 0;
    }

    /**
     * Conta gli utenti.
     */
    public static function count_all(): int {
        $db = WPC_Database::get_instance();
        return (int) $db->fetch_value( "SELECT COUNT(*) FROM users WHERE is_active = 1" );
    }
}
