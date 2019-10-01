<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2013-2019 The facileManager Team                          |
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

if (!currentUserCan(array('manage_policies', 'view_all'), $_SESSION['module'])) unAuth();

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_policies.php');
include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_templates.php');

if (!empty($_POST)) {
	if (currentUserCan('manage_policies', $_SESSION['module'])) {
		$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'add';
		switch ($action) {
		case 'add':
			$result = $fm_module_templates->add($_POST);
			if ($result !== true) {
				$response = displayResponseClose($result);
			} else {
				header('Location: ' . $GLOBALS['basename']);
				exit;
			}
			break;
		case 'edit':
			$update_status = $fm_module_templates->update($_POST);
			if ($update_status !== true) {
				$response = displayResponseClose($update_status);
			} else {
				header('Location: ' . $GLOBALS['basename']);
				exit;
			}
			break;
		}
	}
}

printHeader();
@printMenu();

echo printPageHeader((string) $response, null, currentUserCan('manage_policies', $_SESSION['module']), null, null, 'noscroll');

$sort_direction = null;

$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_order_id', 'policy_', "AND policy_type='template'", null, false, $sort_direction);
$total_pages = ceil($fmdb->num_rows / $_SESSION['user']['record_count']);
if ($page > $total_pages) $page = $total_pages;
$fm_module_templates->rows($result, 'policy', $page, $total_pages);

printFooter();

?>
