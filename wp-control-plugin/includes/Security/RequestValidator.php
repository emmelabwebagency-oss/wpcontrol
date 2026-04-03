<?php
/**
 * Validatore delle richieste remote.
 * Verifica firme HMAC, timestamp e protezione replay.
 *
 * @package LicenseTemplateKit\Security
 */

namespace LicenseTemplateKit\Security;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RequestValidator {

    private CryptoManager $crypto;

    /** @var array Cache dei nonce usati per protezione replay. */
    private const NONCE_TRANSIENT_PREFIX = 'ltk_nonce_';
    private const NONCE_EXPIRY = 600; // 10 minuti.

    public function __construct( CryptoManager $crypto ) {
        $this->crypto = $crypto;
    }

    /**
     * Valida una richiesta in ingresso dal pannello di controllo remoto.
     *
     * @param string $payload   Il corpo della richiesta (JSON).
     * @param string $signature La firma HMAC dall'header.
     * @param int    $timestamp Il timestamp dall'header.
     * @param string $nonce     Il nonce univoco dall'header.
     * @return bool True se la richiesta è valida.
     */
    public function validate_incoming_request(
        string $payload,
        string $signature,
        int $timestamp,
        string $nonce
    ): bool {
        // 1. Verifica che il nonce non sia già stato usato (protezione replay).
        if ( $this->is_nonce_used( $nonce ) ) {
            $this->log_security_event( 'replay_attempt', 'Tentativo di replay rilevato con nonce: ' . $nonce );
            return false;
        }

        // 2. Ottieni il token API condiviso.
        $api_token = $this->get_api_token();
        if ( empty( $api_token ) ) {
            return false;
        }

        // 3. Verifica la firma HMAC e il timestamp.
        $is_valid = $this->crypto->verify_request_signature(
            $payload,
            $api_token,
            $timestamp,
            $signature,
            $nonce
        );

        if ( $is_valid ) {
            // Segna il nonce come usato.
            $this->mark_nonce_used( $nonce );
        } else {
            $this->log_security_event( 'invalid_signature', 'Firma non valida per la richiesta ricevuta.' );
        }

        return $is_valid;
    }

    /**
     * Prepara gli header per una richiesta in uscita verso il pannello di controllo.
     *
     * @param string $payload Il corpo della richiesta.
     * @return array Gli header da includere nella richiesta.
     */
    public function prepare_outgoing_headers( string $payload ): array {
        $api_token = $this->get_api_token();
        $timestamp = time();
        $nonce = bin2hex( random_bytes( 16 ) );
        $site_id = get_option( LTK_OPTION_PREFIX . 'site_id', '' );

        $signature = $this->crypto->sign_request( $payload, $api_token, $timestamp, $nonce );

        return [
            'X-LTK-Site-ID'    => $site_id,
            'X-LTK-Timestamp'  => (string) $timestamp,
            'X-LTK-Nonce'      => $nonce,
            'X-LTK-Signature'  => $signature,
            'Content-Type'     => 'application/json',
        ];
    }

    /**
     * Invia una richiesta firmata al pannello di controllo remoto.
     *
     * @param string $endpoint L'endpoint relativo (es. /api/sites/heartbeat).
     * @param array  $data     I dati da inviare.
     * @param string $method   Il metodo HTTP (default POST).
     * @return array|\WP_Error La risposta o un errore.
     */
    public function send_to_control_panel( string $endpoint, array $data, string $method = 'POST' ): array|\WP_Error {
        $panel_url = get_option( LTK_OPTION_PREFIX . 'control_panel_url', '' );
        if ( empty( $panel_url ) ) {
            return new \WP_Error( 'no_panel_url', 'URL del pannello di controllo non configurato.' );
        }

        $url = rtrim( $panel_url, '/' ) . $endpoint;
        $payload = wp_json_encode( $data );
        $headers = $this->prepare_outgoing_headers( $payload );

        $args = [
            'method'  => $method,
            'headers' => $headers,
            'body'    => $payload,
            'timeout' => 30,
            'sslverify' => true,
        ];

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            $this->log_security_event( 'communication_error', 'Errore comunicazione con il pannello: ' . $response->get_error_message() );
        }

        return $response;
    }

    /**
     * Verifica se un nonce è già stato usato.
     */
    private function is_nonce_used( string $nonce ): bool {
        return (bool) get_transient( self::NONCE_TRANSIENT_PREFIX . $nonce );
    }

    /**
     * Segna un nonce come usato.
     */
    private function mark_nonce_used( string $nonce ): void {
        set_transient( self::NONCE_TRANSIENT_PREFIX . $nonce, 1, self::NONCE_EXPIRY );
    }

    /**
     * Ottieni il token API decrittografato.
     */
    private function get_api_token(): string {
        $encrypted_token = get_option( LTK_OPTION_PREFIX . 'api_token_encrypted', '' );
        if ( empty( $encrypted_token ) ) {
            return '';
        }

        try {
            return $this->crypto->decrypt( $encrypted_token );
        } catch ( \RuntimeException $e ) {
            $this->log_security_event( 'token_decrypt_error', $e->getMessage() );
            return '';
        }
    }

    /**
     * Registra un evento di sicurezza nel log di audit.
     */
    private function log_security_event( string $type, string $description ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'ltk_tpl_log';

        $wpdb->insert( $table, [
            'event_type'        => $type,
            'event_description' => $description,
            'actor'             => 'sistema',
            'ip_address'        => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'metadata'          => wp_json_encode( [ 'timestamp' => time() ] ),
        ] );
    }
}
