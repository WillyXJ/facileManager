<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2013 The facileManager Team                               |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | facileManager: Easy System Administration                               |
 | fmSQLPass: Change database user passwords across multiple servers.      |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmsqlpass/                         |
 +-------------------------------------------------------------------------+
*/

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
if (!defined('PERM_SQLPASS_MANAGE_SETTINGS'))		define('PERM_SQLPASS_MANAGE_SETTINGS', 8);

/** Get permissions */
if (isset($_SESSION['user']['module_perms'])) {
	$allowed_to_manage_servers = ($_SESSION['user']['module_perms']['perm_value'] & PERM_SQLPASS_SERVER_MANAGEMENT) || ($_SESSION['user']['fm_perms'] & PERM_FM_SUPER_ADMIN);
	$allowed_to_manage_passwords = ($_SESSION['user']['module_perms']['perm_value'] & PERM_SQLPASS_PASSWORD_MANAGEMENT) || ($_SESSION['user']['fm_perms'] & PERM_FM_SUPER_ADMIN);
	$allowed_to_manage_module_settings = ($_SESSION['user']['module_perms']['perm_value'] & PERM_SQLPASS_MANAGE_SETTINGS) || ($_SESSION['user']['fm_perms'] & PERM_FM_SUPER_ADMIN);
}
require(ABSPATH . 'fm-modules/facileManager/permissions.inc.php');

?>