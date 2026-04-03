<?php
/**
 * Gestore della crittografia e delle operazioni di sicurezza.
 *
 * @package LicenseTemplateKit\Security
 */

namespace LicenseTemplateKit\Security;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CryptoManager {

    private const CIPHER = 'aes-256-cbc';
    private const HMAC_ALGO = 'sha256';

    /**
     * Ottieni la chiave di crittografia.
     * Usa AUTH_KEY di WordPress come base, combinata con un salt specifico del plugin.
     */
    private function get_encryption_key(): string {
        $base_key = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'ltk-default-key-change-me';
        $plugin_salt = get_option( LTK_OPTION_PREFIX . 'encryption_salt', '' );

        if ( empty( $plugin_salt ) ) {
            $plugin_salt = bin2hex( random_bytes( 32 ) );
            update_option( LTK_OPTION_PREFIX . 'encryption_salt', $plugin_salt );
        }

        return hash( 'sha256', $base_key . $plugin_salt, true );
    }

    /**
     * Crittografa un valore.
     *
     * @param string $plaintext Il testo in chiaro da crittografare.
     * @return string Il testo crittografato (base64).
     */
    public function encrypt( string $plaintext ): string {
        $key = $this->get_encryption_key();
        $iv = random_bytes( openssl_cipher_iv_length( self::CIPHER ) );
        $ciphertext = openssl_encrypt( $plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );

        if ( false === $ciphertext ) {
            throw new \RuntimeException( 'Errore durante la crittografia.' );
        }

        $hmac = hash_hmac( self::HMAC_ALGO, $iv . $ciphertext, $key, true );

        return base64_encode( $hmac . $iv . $ciphertext );
    }

    /**
     * Decrittografa un valore.
     *
     * @param string $encrypted Il testo crittografato (base64).
     * @return string Il testo in chiaro.
     */
    public function decrypt( string $encrypted ): string {
        $key = $this->get_encryption_key();
        $decoded = base64_decode( $encrypted, true );

        if ( false === $decoded ) {
            throw new \RuntimeException( 'Dati crittografati non validi.' );
        }

        $hmac_length = 32; // SHA-256 produce 32 byte.
        $iv_length = openssl_cipher_iv_length( self::CIPHER );

        $hmac = substr( $decoded, 0, $hmac_length );
        $iv = substr( $decoded, $hmac_length, $iv_length );
        $ciphertext = substr( $decoded, $hmac_length + $iv_length );

        // Verifica l'HMAC.
        $expected_hmac = hash_hmac( self::HMAC_ALGO, $iv . $ciphertext, $key, true );
        if ( ! hash_equals( $expected_hmac, $hmac ) ) {
            throw new \RuntimeException( 'Verifica HMAC fallita. Dati potenzialmente manomessi.' );
        }

        $plaintext = openssl_decrypt( $ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );

        if ( false === $plaintext ) {
            throw new \RuntimeException( 'Errore durante la decrittografia.' );
        }

        return $plaintext;
    }

    /**
     * Genera un hash sicuro di una password/codice.
     *
     * @param string $value Il valore da hashare.
     * @return string L'hash risultante.
     */
    public function hash_value( string $value ): string {
        return password_hash( $value, PASSWORD_BCRYPT, [
            'cost' => 12,
        ] );
    }

    /**
     * Verifica un valore contro il suo hash.
     *
     * @param string $value Il valore in chiaro.
     * @param string $hash  L'hash da verificare.
     * @return bool True se corrisponde.
     */
    public function verify_hash( string $value, string $hash ): bool {
        return password_verify( $value, $hash );
    }

    /**
     * Genera una firma HMAC per una richiesta.
     *
     * @param string $payload   Il payload da firmare.
     * @param string $secret    Il segreto condiviso.
     * @param int    $timestamp Il timestamp della richiesta.
     * @param string $nonce     Il nonce univoco della richiesta.
     * @return string La firma HMAC.
     */
    public function sign_request( string $payload, string $secret, int $timestamp, string $nonce = '' ): string {
        $data = $timestamp . ':' . $nonce . ':' . $payload;
        return hash_hmac( self::HMAC_ALGO, $data, $secret );
    }

    /**
     * Verifica la firma HMAC di una richiesta.
     *
     * @param string $payload   Il payload ricevuto.
     * @param string $secret    Il segreto condiviso.
     * @param int    $timestamp Il timestamp ricevuto.
     * @param string $signature La firma da verificare.
     * @param int    $tolerance Tolleranza temporale in secondi (default 300 = 5 minuti).
     * @return bool True se la firma è valida e il timestamp è nel range.
     */
    public function verify_request_signature(
        string $payload,
        string $secret,
        int $timestamp,
        string $signature,
        string $nonce = '',
        int $tolerance = 300
    ): bool {
        // Protezione replay: verifica che il timestamp sia recente.
        if ( abs( time() - $timestamp ) > $tolerance ) {
            return false;
        }

        $expected = $this->sign_request( $payload, $secret, $timestamp, $nonce );
        return hash_equals( $expected, $signature );
    }

    /**
     * Genera un token API sicuro.
     *
     * @return string Token casuale di 64 caratteri esadecimali.
     */
    public function generate_api_token(): string {
        return bin2hex( random_bytes( 32 ) );
    }

    /**
     * Genera un Site ID univoco.
     *
     * @return string UUID v4.
     */
    public function generate_site_id(): string {
        $data = random_bytes( 16 );
        $data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 ); // Versione 4.
        $data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 ); // Variante RFC 4122.

        return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
    }

    /**
     * Crittografa un file con AES-256.
     *
     * @param string $source_path      Percorso del file sorgente.
     * @param string $destination_path  Percorso del file crittografato.
     * @return bool True se l'operazione ha successo.
     */
    public function encrypt_file( string $source_path, string $destination_path ): bool {
        $key = $this->get_encryption_key();
        $iv = random_bytes( openssl_cipher_iv_length( self::CIPHER ) );

        $source = fopen( $source_path, 'rb' );
        $dest = fopen( $destination_path, 'wb' );

        if ( ! $source || ! $dest ) {
            return false;
        }

        // Scrivi l'IV all'inizio del file.
        fwrite( $dest, $iv );

        while ( ! feof( $source ) ) {
            $chunk = fread( $source, 8192 );
            $encrypted_chunk = openssl_encrypt( $chunk, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );
            // Scrivi la lunghezza del chunk crittografato + il chunk.
            fwrite( $dest, pack( 'N', strlen( $encrypted_chunk ) ) );
            fwrite( $dest, $encrypted_chunk );
        }

        fclose( $source );
        fclose( $dest );

        return true;
    }

    /**
     * Decrittografa un file.
     *
     * @param string $source_path      Percorso del file crittografato.
     * @param string $destination_path  Percorso del file decrittografato.
     * @return bool True se l'operazione ha successo.
     */
    public function decrypt_file( string $source_path, string $destination_path ): bool {
        $key = $this->get_encryption_key();
        $iv_length = openssl_cipher_iv_length( self::CIPHER );

        $source = fopen( $source_path, 'rb' );
        $dest = fopen( $destination_path, 'wb' );

        if ( ! $source || ! $dest ) {
            return false;
        }

        // Leggi l'IV.
        $iv = fread( $source, $iv_length );

        while ( ! feof( $source ) ) {
            $size_data = fread( $source, 4 );
            if ( strlen( $size_data ) < 4 ) {
                break;
            }
            $size = unpack( 'N', $size_data )[1];
            $encrypted_chunk = fread( $source, $size );
            $decrypted_chunk = openssl_decrypt( $encrypted_chunk, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );
            fwrite( $dest, $decrypted_chunk );
        }

        fclose( $source );
        fclose( $dest );

        return true;
    }
}
