<?php
/**
 * Template: Pagina di blocco del frontend.
 * Mostrata quando il sito è in Lock Mode.
 *
 * @package WPControl
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$site_name = get_bloginfo( 'name' );
$show_emergency_login = (bool) get_option( WPC_OPTION_PREFIX . 'show_emergency_login', false );
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html( $site_name ); ?> — Sito Protetto</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f0f2f5;
            color: #1d2327;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .wpc-lock-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
            padding: 60px 40px;
            max-width: 560px;
            width: 100%;
            text-align: center;
        }
        .wpc-lock-icon {
            width: 80px;
            height: 80px;
            background: #f0f2f5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 36px;
        }
        .wpc-lock-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 12px;
            color: #1d2327;
        }
        .wpc-lock-message {
            font-size: 16px;
            color: #50575e;
            line-height: 1.6;
            margin-bottom: 32px;
        }
        .wpc-lock-badge {
            display: inline-block;
            background: #f0f2f5;
            color: #50575e;
            font-size: 12px;
            padding: 6px 16px;
            border-radius: 20px;
            letter-spacing: 0.5px;
        }
        .wpc-emergency-link {
            display: block;
            margin-top: 24px;
            color: #2271b1;
            text-decoration: none;
            font-size: 13px;
        }
        .wpc-emergency-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="wpc-lock-container">
        <div class="wpc-lock-icon">&#128274;</div>
        <h1 class="wpc-lock-title">Sito Temporaneamente Protetto</h1>
        <p class="wpc-lock-message">
            L'accesso a questo sito è attualmente limitato per motivi di sicurezza.<br>
            Il contenuto e i dati sono preservati e protetti.
        </p>
        <span class="wpc-lock-badge">Protetto da WP Control</span>

        <?php if ( $show_emergency_login ) : ?>
            <a href="<?php echo esc_url( wp_login_url() ); ?>?wpc_emergency=1" class="wpc-emergency-link">
                Accesso amministratore di emergenza
            </a>
        <?php endif; ?>
    </div>
</body>
</html>
