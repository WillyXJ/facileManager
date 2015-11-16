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
 | Processes zone templates management page                                |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

$template_type = 'domain';
$table = 'domains';

if (!empty($_POST)) {
	include(ABSPATH . 'fm-modules/fmDNS/classes/class_zones.php');

	if (currentUserCan('manage_zones', $_SESSION['module'])) {
		$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'create';
		switch ($action) {
		case 'create':
			$insert_id = $fm_dns_zones->add($_POST);
			if (!is_numeric($insert_id)) {
				$response = '<p class="error">' . $insert_id . '</p>'. "\n";
			} else header('Location: ' . $GLOBALS['basename']);
			break;
		case 'update':
			$zone_update_status = $fm_dns_zones->update();
			if ($zone_update_status !== true) {
				$response = '<p class="error">' . $zone_update_status . '</p>'. "\n";
			} else header('Location: ' . $GLOBALS['basename']);
			break;
		}
	}
}

$tpl_extra_perm = 'manage_records';

/** Get zones based on access */
$user_capabilities = getUserCapabilities($_SESSION['user']['id'], 'all');
$limited_domain_ids = (array_key_exists('access_specific_zones', $user_capabilities[$_SESSION['module']]) && $user_capabilities[$_SESSION['module']]['access_specific_zones'][0]) ? "AND domain_id IN (" . implode(',', $user_capabilities[$_SESSION['module']]['access_specific_zones']) . ")" : null;

include(dirname(__FILE__) . '/templates.php');

?>
