<?php
/**
 * Template: Pagina di blocco del frontend.
 * Mostrata quando il sito è in Lock Mode.
 *
 * @package LicenseTemplateKit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$site_name = get_bloginfo( 'name' );
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
        .ltk-lock-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
            padding: 60px 40px;
            max-width: 560px;
            width: 100%;
            text-align: center;
        }
        .ltk-lock-icon {
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
        .ltk-lock-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 12px;
            color: #1d2327;
        }
        .ltk-lock-message {
            font-size: 16px;
            color: #50575e;
            line-height: 1.6;
            margin-bottom: 32px;
        }
    </style>
</head>
<body>
    <div class="ltk-lock-container">
        <div class="ltk-lock-icon">&#128274;</div>
        <h1 class="ltk-lock-title">Sito Temporaneamente Protetto</h1>
        <p class="ltk-lock-message">
            L'accesso a questo sito è attualmente limitato per motivi di sicurezza.
        </p>
    </div>
</body>
</html>
