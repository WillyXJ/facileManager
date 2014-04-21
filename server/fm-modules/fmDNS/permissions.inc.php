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
 | fmDNS: Easily manage one or more ISC BIND servers                       |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdns/                             |
 +-------------------------------------------------------------------------+
*/

/**
 * Contains permission ACLs for fmDNS
 *
 * @package fmDNS
 *
 */

/** Constants */
if (!defined('PERM_MODULE_ACCESS_DENIED'))		define('PERM_MODULE_ACCESS_DENIED', 1);
if (!defined('PERM_DNS_SERVER_MANAGEMENT'))		define('PERM_DNS_SERVER_MANAGEMENT', 2);
if (!defined('PERM_DNS_BUILD_SERVER_CONFIGS'))	define('PERM_DNS_BUILD_SERVER_CONFIGS', 4);
if (!defined('PERM_DNS_ZONE_MANAGEMENT'))		define('PERM_DNS_ZONE_MANAGEMENT', 8);
if (!defined('PERM_DNS_RECORD_MANAGEMENT'))		define('PERM_DNS_RECORD_MANAGEMENT', 16);
if (!defined('PERM_DNS_RELOAD_ZONES'))			define('PERM_DNS_RELOAD_ZONES', 32);
if (!defined('PERM_DNS_MANAGE_SETTINGS'))		define('PERM_DNS_MANAGE_SETTINGS', 64);

/** Get permissions */
if (isset($_SESSION['user']['module_perms'])) {
	$allowed_to_manage_servers = ($_SESSION['user']['module_perms']['perm_value'] & PERM_DNS_SERVER_MANAGEMENT) || ($_SESSION['user']['fm_perms'] & PERM_FM_SUPER_ADMIN);
	$allowed_to_build_configs = ($_SESSION['user']['module_perms']['perm_value'] & PERM_DNS_BUILD_SERVER_CONFIGS) || ($_SESSION['user']['fm_perms'] & PERM_FM_SUPER_ADMIN);
	$allowed_to_manage_zones = ($_SESSION['user']['module_perms']['perm_value'] & PERM_DNS_ZONE_MANAGEMENT) || ($_SESSION['user']['fm_perms'] & PERM_FM_SUPER_ADMIN);
	$allowed_to_manage_records = ($_SESSION['user']['module_perms']['perm_value'] & PERM_DNS_RECORD_MANAGEMENT) || ($_SESSION['user']['fm_perms'] & PERM_FM_SUPER_ADMIN);
	$allowed_to_reload_zones = ($_SESSION['user']['module_perms']['perm_value'] & PERM_DNS_RELOAD_ZONES) || ($_SESSION['user']['fm_perms'] & PERM_FM_SUPER_ADMIN);
	$allowed_to_manage_module_settings = ($_SESSION['user']['module_perms']['perm_value'] & PERM_DNS_MANAGE_SETTINGS) || ($_SESSION['user']['fm_perms'] & PERM_FM_SUPER_ADMIN);
}
require(ABSPATH . 'fm-modules/facileManager/permissions.inc.php');

?>