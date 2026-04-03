/**
 * WP Control Center - JavaScript principale.
 */

document.addEventListener('DOMContentLoaded', function () {
    // Conferma azioni distruttive.
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(el.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

    // Auto-dismiss alert dopo 5 secondi.
    document.querySelectorAll('.alert-auto-dismiss').forEach(function (el) {
        setTimeout(function () {
            var bsAlert = bootstrap.Alert.getOrCreateInstance(el);
            bsAlert.close();
        }, 5000);
    });

    // Toggle sidebar su mobile.
    var sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            document.getElementById('sidebar').classList.toggle('show');
        });
    }

    // Tooltips Bootstrap.
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (el) {
        new bootstrap.Tooltip(el);
    });
});

/**
 * Formatta i bytes in formato leggibile.
 */
function formatBytes(bytes, decimals) {
    if (bytes === 0) return '0 Bytes';
    var k = 1024;
    var dm = decimals || 2;
    var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    var i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

/**
 * Formatta una data relativa.
 */
function timeAgo(dateString) {
    var date = new Date(dateString);
    var now = new Date();
    var seconds = Math.floor((now - date) / 1000);

    if (seconds < 60) return 'poco fa';
    if (seconds < 3600) return Math.floor(seconds / 60) + ' min fa';
    if (seconds < 86400) return Math.floor(seconds / 3600) + ' ore fa';
    return Math.floor(seconds / 86400) + ' giorni fa';
}
