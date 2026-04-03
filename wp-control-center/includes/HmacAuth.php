<?php
/**
 * WP Control Center - Autenticazione HMAC-SHA256 per comunicazione server-to-server.
 * Verifica le richieste provenienti dai siti WordPress con il plugin WP Control.
 */

if ( ! defined( 'WPC_ROOT' ) ) {
    exit( 'Accesso diretto non consentito.' );
}

class WPC_HmacAuth {

    /**
     * Verifica la firma HMAC di una richiesta proveniente da un sito WordPress.
     *
     * @param string $site_id   ID del sito.
     * @param string $signature Firma HMAC ricevuta nell'header.
     * @param string $timestamp Timestamp della richiesta.
     * @param string $nonce     Nonce univoco della richiesta.
     * @param string $body      Corpo della richiesta (raw).
     * @return array ['valid' => bool, 'message' => string, 'site' => ?array]
     */
    public static function verify_request( string $site_id, string $signature, string $timestamp, string $nonce, string $body ): array {
        $db = WPC_Database::get_instance();

        // 1. Verifica timestamp (tolleranza configurata).
        $time_diff = abs( time() - (int) $timestamp );
        if ( $time_diff > WPC_HMAC_TOLERANCE ) {
            return [
                'valid'   => false,
                'message' => 'Richiesta scaduta: timestamp fuori tolleranza.',
                'site'    => null,
            ];
        }

        // 2. Cerca il sito nel database.
        $site = $db->fetch_one(
            "SELECT * FROM sites WHERE site_id = ? LIMIT 1",
            [ $site_id ]
        );

        if ( ! $site ) {
            return [
                'valid'   => false,
                'message' => 'Sito non trovato.',
                'site'    => null,
            ];
        }

        // 3. Verifica replay del nonce.
        $existing_nonce = $db->fetch_value(
            "SELECT COUNT(*) FROM used_nonces WHERE nonce = ? AND site_id = ?",
            [ $nonce, $site_id ]
        );

        if ( (int) $existing_nonce > 0 ) {
            return [
                'valid'   => false,
                'message' => 'Nonce gia\' utilizzato (replay detected).',
                'site'    => null,
            ];
        }

        // 4. Calcola la firma attesa.
        $payload = $timestamp . ':' . $nonce . ':' . $body;
        $expected_signature = hash_hmac( 'sha256', $payload, $site['api_token_hash'] );

        // 5. Confronto timing-safe.
        if ( ! hash_equals( $expected_signature, $signature ) ) {
            return [
                'valid'   => false,
                'message' => 'Firma HMAC non valida.',
                'site'    => null,
            ];
        }

        // 6. Salva il nonce per prevenire replay.
        $db->insert( 'used_nonces', [
            'nonce'      => $nonce,
            'site_id'    => $site_id,
            'created_at' => date( 'Y-m-d H:i:s' ),
        ] );

        // 7. Pulizia vecchi nonce (piu' vecchi di 10 minuti).
        $cutoff = date( 'Y-m-d H:i:s', time() - 600 );
        $db->query( "DELETE FROM used_nonces WHERE created_at < ?", [ $cutoff ] );

        return [
            'valid'   => true,
            'message' => 'Firma verificata.',
            'site'    => $site,
        ];
    }

    /**
     * Estrae e valida gli header HMAC dalla richiesta HTTP corrente.
     * Restituisce i valori o null se mancanti.
     */
    public static function extract_headers(): ?array {
        $headers = self::get_all_headers();

        $site_id   = $headers['X-WPC-Site-ID']   ?? $headers['X-WPC-Site-Id']   ?? $headers['x-wpc-site-id']   ?? $headers['X-WPC-SITE-ID'] ?? null;
        $signature = $headers['X-WPC-Signature']  ?? $headers['x-wpc-signature']  ?? $headers['X-WPC-SIGNATURE'] ?? null;
        $timestamp = $headers['X-WPC-Timestamp']  ?? $headers['x-wpc-timestamp']  ?? $headers['X-WPC-TIMESTAMP'] ?? null;
        $nonce     = $headers['X-WPC-Nonce']      ?? $headers['x-wpc-nonce']      ?? $headers['X-WPC-NONCE']     ?? null;

        if ( ! $site_id || ! $signature || ! $timestamp || ! $nonce ) {
            return null;
        }

        return [
            'site_id'   => $site_id,
            'signature' => $signature,
            'timestamp' => $timestamp,
            'nonce'     => $nonce,
        ];
    }

    /**
     * Middleware: verifica la richiesta HMAC e restituisce il sito autenticato.
     * Termina con errore 401/403 se non valida.
     */
    public static function require_site_auth(): array {
        $headers = self::extract_headers();

        if ( ! $headers ) {
            http_response_code( 401 );
            echo json_encode( [ 'error' => 'Header HMAC mancanti.' ] );
            exit;
        }

        $body = file_get_contents( 'php://input' );

        $result = self::verify_request(
            $headers['site_id'],
            $headers['signature'],
            $headers['timestamp'],
            $headers['nonce'],
            $body
        );

        if ( ! $result['valid'] ) {
            http_response_code( 403 );
            echo json_encode( [ 'error' => $result['message'] ] );
            exit;
        }

        return $result['site'];
    }

    /**
     * Firma una richiesta in uscita verso un sito WordPress.
     */
    public static function sign_request( string $api_token, string $body = '' ): array {
        $timestamp = (string) time();
        $nonce     = bin2hex( random_bytes( 16 ) );
        $payload   = $timestamp . ':' . $nonce . ':' . $body;
        $signature = hash_hmac( 'sha256', $payload, $api_token );

        return [
            'X-WPC-Signature' => $signature,
            'X-WPC-Timestamp' => $timestamp,
            'X-WPC-Nonce'     => $nonce,
        ];
    }

    /**
     * Recupera tutti gli header HTTP in modo cross-platform.
     */
    private static function get_all_headers(): array {
        if ( function_exists( 'getallheaders' ) ) {
            return getallheaders() ?: [];
        }

        // Fallback per server non-Apache.
        $headers = [];
        foreach ( $_SERVER as $key => $value ) {
            if ( str_starts_with( $key, 'HTTP_' ) ) {
                $header_name = str_replace( '_', '-', substr( $key, 5 ) );
                $headers[ $header_name ] = $value;
            }
        }
        return $headers;
    }
}
