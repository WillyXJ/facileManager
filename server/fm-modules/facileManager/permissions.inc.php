<?php

/**
 * Contains permission ACLs for facileManager
 *
 * @package facileManager
 *
 */

if (!defined('PERM_FM_SUPER_ADMIN'))		define('PERM_FM_SUPER_ADMIN', 1);
if (!defined('PERM_FM_MODULE_MANAGEMENT'))	define('PERM_FM_MODULE_MANAGEMENT', 2);
if (!defined('PERM_FM_USER_MANAGEMENT'))	define('PERM_FM_USER_MANAGEMENT', 4);
if (!defined('PERM_FM_RUN_TOOLS'))			define('PERM_FM_RUN_TOOLS', 8);
if (!defined('PERM_FM_MANAGE_SETTINGS'))	define('PERM_FM_MANAGE_SETTINGS', 16);

/** Get permissions */
if (isset($_SESSION['user'])) {
	$super_admin = $_SESSION['user']['fm_perms'] & PERM_FM_SUPER_ADMIN;
	$allowed_to_manage_modules = ($_SESSION['user']['fm_perms'] & PERM_FM_MODULE_MANAGEMENT) || ($_SESSION['user']['fm_perms'] & PERM_FM_SUPER_ADMIN);
	$allowed_to_run_tools = ($_SESSION['user']['fm_perms'] & PERM_FM_RUN_TOOLS) || ($_SESSION['user']['fm_perms'] & PERM_FM_SUPER_ADMIN);
	$allowed_to_manage_users = ($_SESSION['user']['fm_perms'] & PERM_FM_USER_MANAGEMENT) || ($_SESSION['user']['fm_perms'] & PERM_FM_SUPER_ADMIN);
	$allowed_to_manage_settings = ($_SESSION['user']['fm_perms'] & PERM_FM_MANAGE_SETTINGS) || ($_SESSION['user']['fm_perms'] & PERM_FM_SUPER_ADMIN);
}

?>