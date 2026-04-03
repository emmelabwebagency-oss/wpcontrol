<?php
/**
 * WP Control Center - Logout.
 */

define( 'WPC_ROOT', __DIR__ );
require_once WPC_ROOT . '/includes/bootstrap.php';

WPC_Audit::log( 'user_logout', 'Logout effettuato' );
WPC_Auth::logout();

header( 'Location: login.php' );
exit;
