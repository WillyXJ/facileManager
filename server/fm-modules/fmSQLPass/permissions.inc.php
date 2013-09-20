<?php

/**
 * Contains permission ACLs for fmSQLPass
 *
 * @package fmSQLPass
 *
 */

/** Constants */
if (!defined('PERM_MODULE_ACCESS_DENIED'))		define('PERM_MODULE_ACCESS_DENIED', 1);
if (!defined('PERM_SQLPASS_SERVER_MANAGEMENT'))	define('PERM_SQLPASS_SERVER_MANAGEMENT', 2);
if (!defined('PERM_SQLPASS_PASSWORD_MANAGEMENT'))	define('PERM_SQLPASS_PASSWORD_MANAGEMENT', 4);

/** Get permissions */
if (isset($_SESSION['user']['module_perms'])) {
	$allowed_to_manage_servers = ($_SESSION['user']['module_perms']['perm_value'] & PERM_SQLPASS_SERVER_MANAGEMENT) || ($_SESSION['user']['fm_perms'] & PERM_FM_SUPER_ADMIN);
	$allowed_to_manage_passwords = ($_SESSION['user']['module_perms']['perm_value'] & PERM_SQLPASS_PASSWORD_MANAGEMENT) || ($_SESSION['user']['fm_perms'] & PERM_FM_SUPER_ADMIN);
}
require(ABSPATH . 'fm-modules/facileManager/permissions.inc.php');

?>