/**
 * WP Control - Script Admin
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Conferma azioni critiche.
        $('.wpc-confirm-action').on('click', function(e) {
            if (!confirm(wpcAdmin.i18n.confirm)) {
                e.preventDefault();
                return false;
            }
        });

        // Auto-hide delle notifiche dopo 5 secondi.
        setTimeout(function() {
            $('.wpc-dashboard .notice.is-dismissible').fadeOut(500);
        }, 5000);
    });

})(jQuery);
