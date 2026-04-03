<?php
/**
 * WP Control Center - Classe Database (PDO MySQL).
 */

if ( ! defined( 'WPC_ROOT' ) ) {
    exit( 'Accesso diretto non consentito.' );
}

class WPC_Database {

    private static ?WPC_Database $instance = null;
    private ?PDO $pdo = null;

    private function __construct() {
        $dsn = 'mysql:host=' . WPC_DB_HOST . ';dbname=' . WPC_DB_NAME . ';charset=' . WPC_DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $this->pdo = new PDO( $dsn, WPC_DB_USER, WPC_DB_PASSWORD, $options );
    }

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_pdo(): PDO {
        return $this->pdo;
    }

    /**
     * Esegue una query preparata e restituisce lo statement.
     */
    public function query( string $sql, array $params = [] ): PDOStatement {
        $stmt = $this->pdo->prepare( $sql );
        $stmt->execute( $params );
        return $stmt;
    }

    /**
     * Restituisce tutte le righe.
     */
    public function fetch_all( string $sql, array $params = [] ): array {
        return $this->query( $sql, $params )->fetchAll();
    }

    /**
     * Restituisce una singola riga.
     */
    public function fetch_one( string $sql, array $params = [] ): ?array {
        $row = $this->query( $sql, $params )->fetch();
        return $row ?: null;
    }

    /**
     * Restituisce un singolo valore scalare.
     */
    public function fetch_value( string $sql, array $params = [] ) {
        return $this->query( $sql, $params )->fetchColumn();
    }

    /**
     * Inserisce una riga e restituisce l'ID.
     */
    public function insert( string $table, array $data ): string {
        $columns = implode( ', ', array_keys( $data ) );
        $placeholders = implode( ', ', array_fill( 0, count( $data ), '?' ) );
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query( $sql, array_values( $data ) );
        return $this->pdo->lastInsertId();
    }

    /**
     * Aggiorna righe e restituisce il numero di righe aggiornate.
     */
    public function update( string $table, array $data, string $where, array $where_params = [] ): int {
        $set_parts = [];
        $values = [];
        foreach ( $data as $column => $value ) {
            $set_parts[] = "{$column} = ?";
            $values[] = $value;
        }
        $set_clause = implode( ', ', $set_parts );
        $sql = "UPDATE {$table} SET {$set_clause} WHERE {$where}";
        $stmt = $this->query( $sql, array_merge( $values, $where_params ) );
        return $stmt->rowCount();
    }

    /**
     * Genera un UUID v4.
     */
    public static function generate_uuid(): string {
        $data = random_bytes( 16 );
        $data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 );
        $data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 );
        return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
    }
}
