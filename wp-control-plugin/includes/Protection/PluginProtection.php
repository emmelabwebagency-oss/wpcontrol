<?php
/**
 * Protezione del plugin contro la disattivazione e la disinstallazione non autorizzata.
 *
 * @package WPControl\Protection
 */

namespace WPControl\Protection;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PluginProtection {

    /**
     * Inizializza gli hook di protezione.
     */
    public function init(): void {
        // Intercetta i tentativi di disattivazione.
        add_action( 'admin_init', [ $this, 'intercept_deactivation' ] );

        // Rimuovi il link "Disattiva" dalla lista plugin (sostituiscilo con uno protetto).
        add_filter( 'plugin_action_links_' . WPC_PLUGIN_BASENAME, [ $this, 'modify_action_links' ] );

        // Aggiungi la modale di conferma disattivazione.
        add_action( 'admin_footer-plugins.php', [ $this, 'render_deactivation_modal' ] );

        // Gestisci la richiesta AJAX di verifica codice.
        add_action( 'wp_ajax_wpc_verify_uninstall_code', [ $this, 'ajax_verify_uninstall_code' ] );

        // Impedisci la cancellazione del plugin.
        add_filter( 'pre_delete_plugin', [ $this, 'prevent_deletion' ], 10, 2 );

        // Impedisci la disattivazione via WP-CLI senza codice.
        add_action( 'deactivate_' . WPC_PLUGIN_BASENAME, [ $this, 'check_deactivation_permission' ] );
    }

    /**
     * Intercetta i tentativi di disattivazione dal pannello plugin.
     */
    public function intercept_deactivation(): void {
        if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'deactivate' ) {
            return;
        }

        if ( ! isset( $_GET['plugin'] ) || $_GET['plugin'] !== WPC_PLUGIN_BASENAME ) {
            return;
        }

        // Verifica se il codice di sblocco è stato fornito nella sessione.
        if ( ! $this->is_deactivation_authorized() ) {
            // Registra il tentativo.
            $this->log_failed_attempt( 'deactivation_attempt' );

            // Notifica il pannello remoto.
            $this->notify_tampering( 'deactivation_attempt' );

            // Reindirizza alla pagina plugin con un messaggio di errore.
            wp_safe_redirect( admin_url( 'plugins.php?wpc_error=unauthorized_deactivation' ) );
            exit;
        }
    }

    /**
     * Modifica i link di azione del plugin nella lista.
     */
    public function modify_action_links( array $links ): array {
        // Sostituisci il link di disattivazione standard.
        if ( isset( $links['deactivate'] ) ) {
            $links['deactivate'] = '<a href="#" class="wpc-protected-deactivate" style="color:#d63638;">'
                . esc_html__( 'Disattiva (protetto)', 'wp-control' )
                . '</a>';
        }

        // Aggiungi link alle impostazioni.
        $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=wp-control' ) ) . '">'
            . esc_html__( 'Impostazioni', 'wp-control' )
            . '</a>';
        array_unshift( $links, $settings_link );

        return $links;
    }

    /**
     * Renderizza la modale di conferma per la disattivazione.
     */
    public function render_deactivation_modal(): void {
        $nonce = wp_create_nonce( 'wpc_uninstall_verify' );
        ?>
        <div id="wpc-deactivation-modal" style="display:none;">
            <div style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:999999;display:flex;align-items:center;justify-content:center;">
                <div style="background:#fff;padding:30px;border-radius:8px;max-width:450px;width:90%;box-shadow:0 4px 20px rgba(0,0,0,0.3);">
                    <h2 style="margin-top:0;color:#1d2327;">&#128274; <?php esc_html_e( 'Disattivazione Protetta', 'wp-control' ); ?></h2>
                    <p><?php esc_html_e( 'Per disattivare WP Control è necessario inserire il codice di disinstallazione configurato durante il setup.', 'wp-control' ); ?></p>
                    <div id="wpc-deactivation-error" style="display:none;color:#d63638;margin-bottom:10px;padding:8px;background:#fef0f0;border-radius:4px;"></div>
                    <input type="password" id="wpc-uninstall-code" placeholder="<?php esc_attr_e( 'Codice di disinstallazione', 'wp-control' ); ?>"
                           style="width:100%;padding:10px;margin-bottom:15px;border:1px solid #8c8f94;border-radius:4px;font-size:14px;" />
                    <div style="display:flex;gap:10px;justify-content:flex-end;">
                        <button id="wpc-cancel-deactivation" class="button button-secondary"><?php esc_html_e( 'Annulla', 'wp-control' ); ?></button>
                        <button id="wpc-confirm-deactivation" class="button button-primary" style="background:#d63638;border-color:#d63638;">
                            <?php esc_html_e( 'Conferma Disattivazione', 'wp-control' ); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <script>
        (function() {
            const modal = document.getElementById('wpc-deactivation-modal');
            const errorDiv = document.getElementById('wpc-deactivation-error');
            const codeInput = document.getElementById('wpc-uninstall-code');

            // Intercetta il click sul link di disattivazione protetta.
            document.querySelectorAll('.wpc-protected-deactivate').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    modal.style.display = 'block';
                    codeInput.focus();
                });
            });

            // Annulla.
            document.getElementById('wpc-cancel-deactivation').addEventListener('click', function() {
                modal.style.display = 'none';
                codeInput.value = '';
                errorDiv.style.display = 'none';
            });

            // Conferma.
            document.getElementById('wpc-confirm-deactivation').addEventListener('click', function() {
                const code = codeInput.value.trim();
                if (!code) {
                    errorDiv.textContent = '<?php echo esc_js( __( 'Inserisci il codice di disinstallazione.', 'wp-control' ) ); ?>';
                    errorDiv.style.display = 'block';
                    return;
                }

                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=wpc_verify_uninstall_code&code=' + encodeURIComponent(code) + '&_wpnonce=<?php echo esc_js( $nonce ); ?>'
                })
                .then(r => r.json())
                .then(function(response) {
                    if (response.success) {
                        // Codice valido: procedi con la disattivazione.
                        window.location.href = response.data.redirect_url;
                    } else {
                        errorDiv.textContent = response.data.message || '<?php echo esc_js( __( 'Codice non valido.', 'wp-control' ) ); ?>';
                        errorDiv.style.display = 'block';
                        codeInput.value = '';
                    }
                })
                .catch(function() {
                    errorDiv.textContent = '<?php echo esc_js( __( 'Errore di comunicazione.', 'wp-control' ) ); ?>';
                    errorDiv.style.display = 'block';
                });
            });

            // Chiudi con ESC.
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    modal.style.display = 'none';
                    codeInput.value = '';
                    errorDiv.style.display = 'none';
                }
            });
        })();
        </script>
        <?php
    }

    /**
     * Gestisce la verifica AJAX del codice di disinstallazione.
     */
    public function ajax_verify_uninstall_code(): void {
        check_ajax_referer( 'wpc_uninstall_verify' );

        if ( ! current_user_can( 'activate_plugins' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permessi insufficienti.', 'wp-control' ) ] );
        }

        $code = sanitize_text_field( $_POST['code'] ?? '' );
        $stored_hash = get_option( WPC_OPTION_PREFIX . 'uninstall_code_hash', '' );

        if ( empty( $stored_hash ) || ! password_verify( $code, $stored_hash ) ) {
            $this->log_failed_attempt( 'invalid_uninstall_code' );
            $this->notify_tampering( 'invalid_uninstall_code' );
            wp_send_json_error( [ 'message' => __( 'Codice di disinstallazione non valido.', 'wp-control' ) ] );
        }

        // Codice valido: imposta un transient di autorizzazione temporanea.
        set_transient( 'wpc_deactivation_authorized_' . get_current_user_id(), true, 60 );

        // Genera l'URL di disattivazione standard.
        $deactivate_url = wp_nonce_url(
            admin_url( 'plugins.php?action=deactivate&plugin=' . urlencode( WPC_PLUGIN_BASENAME ) ),
            'deactivate-plugin_' . WPC_PLUGIN_BASENAME
        );

        $this->log_event( 'authorized_deactivation', 'Disattivazione autorizzata dall\'utente: ' . wp_get_current_user()->user_login );

        wp_send_json_success( [ 'redirect_url' => $deactivate_url ] );
    }

    /**
     * Impedisce la cancellazione del plugin senza autorizzazione.
     */
    public function prevent_deletion( ?bool $delete, string $plugin ): ?bool {
        if ( $plugin !== WPC_PLUGIN_BASENAME ) {
            return $delete;
        }

        if ( ! $this->is_deactivation_authorized() ) {
            $this->log_failed_attempt( 'deletion_attempt' );
            $this->notify_tampering( 'deletion_attempt' );
            return false; // Impedisci la cancellazione.
        }

        return $delete;
    }

    /**
     * Verifica se la disattivazione è stata autorizzata.
     */
    private function is_deactivation_authorized(): bool {
        $user_id = get_current_user_id();
        return (bool) get_transient( 'wpc_deactivation_authorized_' . $user_id );
    }

    /**
     * Verifica il permesso di disattivazione (hook).
     */
    public function check_deactivation_permission(): void {
        if ( ! $this->is_deactivation_authorized() ) {
            $this->log_failed_attempt( 'deactivation_blocked' );
            wp_die(
                __( 'La disattivazione di WP Control richiede il codice di autorizzazione.', 'wp-control' ),
                __( 'Disattivazione Bloccata', 'wp-control' ),
                [ 'response' => 403 ]
            );
        }
    }

    /**
     * Registra un tentativo fallito.
     */
    private function log_failed_attempt( string $type ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'wpc_audit_log';

        $current_user = wp_get_current_user();
        $wpdb->insert( $table, [
            'event_type'        => $type,
            'event_description' => "Tentativo non autorizzato: {$type}",
            'actor'             => $current_user->user_login ?? 'sconosciuto',
            'ip_address'        => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'metadata'          => wp_json_encode( [
                'timestamp' => time(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ] ),
        ] );
    }

    /**
     * Notifica il pannello remoto di un tentativo di manomissione.
     */
    private function notify_tampering( string $event_type ): void {
        $panel_url = get_option( WPC_OPTION_PREFIX . 'control_panel_url', '' );
        if ( empty( $panel_url ) ) {
            return;
        }

        // Invia la notifica in modo asincrono tramite cron.
        wp_schedule_single_event( time(), 'wpc_send_tamper_alert', [
            $event_type,
            time(),
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        ] );
    }

    /**
     * Registra un evento nel log.
     */
    private function log_event( string $type, string $description ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'wpc_audit_log';

        $wpdb->insert( $table, [
            'event_type'        => $type,
            'event_description' => $description,
            'actor'             => wp_get_current_user()->user_login ?? 'sistema',
            'ip_address'        => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        ] );
    }
}
