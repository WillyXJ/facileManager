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
 | fmFirewall: Easily manage one or more software firewalls                |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmfirewall/                        |
 +-------------------------------------------------------------------------+
*/

/**
 * Contains permission ACLs for fmSQLPass
 *
 * @package fmSQLPass
 *
 */

/** Constants */
if (!defined('PERM_MODULE_ACCESS_DENIED'))			define('PERM_MODULE_ACCESS_DENIED', 1);
if (!defined('PERM_FW_SERVER_MANAGEMENT'))			define('PERM_FW_SERVER_MANAGEMENT', 2);
if (!defined('PERM_FW_OBJECT_MANAGEMENT'))			define('PERM_FW_OBJECT_MANAGEMENT', 4);
if (!defined('PERM_FW_SERVICE_MANAGEMENT'))			define('PERM_FW_SERVICE_MANAGEMENT', 8);
if (!defined('PERM_FW_TIME_MANAGEMENT'))			define('PERM_FW_TIME_MANAGEMENT', 16);

/** Get permissions */
if (isset($_SESSION['user']['module_perms'])) {
	$allowed_to_manage_servers = ($_SESSION['user']['module_perms']['perm_value'] & PERM_FW_SERVER_MANAGEMENT) || ($_SESSION['user']['fm_perms'] & PERM_FM_SUPER_ADMIN);
	$allowed_to_manage_objects = ($_SESSION['user']['module_perms']['perm_value'] & PERM_FW_OBJECT_MANAGEMENT) || ($_SESSION['user']['fm_perms'] & PERM_FM_SUPER_ADMIN);
	$allowed_to_manage_services = ($_SESSION['user']['module_perms']['perm_value'] & PERM_FW_SERVICE_MANAGEMENT) || ($_SESSION['user']['fm_perms'] & PERM_FM_SUPER_ADMIN);
	$allowed_to_manage_time = ($_SESSION['user']['module_perms']['perm_value'] & PERM_FW_TIME_MANAGEMENT) || ($_SESSION['user']['fm_perms'] & PERM_FM_SUPER_ADMIN);
	
	$allowed_to_manage_module_settings = $allowed_to_manage_servers;
}
require(ABSPATH . 'fm-modules/facileManager/permissions.inc.php');

?>