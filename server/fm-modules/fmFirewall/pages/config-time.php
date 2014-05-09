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
 | Processes time management page                                          |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

if (!currentUserCan(array('manage_time', 'view_all'), $_SESSION['module'])) unAuth();

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_time.php');
$response = isset($response) ? $response : null;

if (currentUserCan('manage_time', $_SESSION['module'])) {
	$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'add';
	switch ($action) {
	case 'add':
		if (!empty($_POST)) {
			$result = $fm_module_time->add($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else header('Location: ' . $GLOBALS['basename']);
		}
		break;
	case 'delete':
		if (isset($_GET['id']) && !empty($_GET['id'])) {
			$time_delete_status = $fm_module_time->delete(sanitize($_GET['id']));
			if ($time_delete_status !== true) {
				$response = $time_delete_status;
				$action = 'add';
			} else header('Location: ' . $GLOBALS['basename']);
		}
		break;
	case 'edit':
		if (!empty($_POST)) {
			$result = $fm_module_time->update($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else header('Location: ' . $GLOBALS['basename']);
		}
		if (isset($_GET['status'])) {
			if (!updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'time', $_GET['id'], 'time_', $_GET['status'], 'time_id')) {
				$response = 'This time could not be ' . $_GET['status'] . '.';
			} else {
				/* set the time_build_config flag */
				$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}time` SET `time_build_config`='yes' WHERE `time_id`=" . sanitize($_GET['id']);
				$result = $fmdb->query($query);
				
				$tmp_name = getNameFromID($_GET['id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'time', 'time_', 'time_id', 'time_name');
				addLogEntry("Set time '$tmp_name' status to " . $_GET['status'] . '.');
				header('Location: ' . $GLOBALS['basename']);
			}
		}
		break;
	}
}

printHeader();
@printMenu();

echo printPageHeader($response, null, currentUserCan('manage_time', $_SESSION['module']));

$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'time', 'time_name', 'time_');
$fm_module_time->rows($result);

printFooter();

?>
