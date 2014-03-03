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
 | Processes services management page                                      |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

$type = (isset($_GET['type'])) ? strtolower($_GET['type']) : 'custom';

$page_name = 'Services';
$page_name_sub = ($type == 'custom') ? 'Custom' : strtoupper($type);

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_services.php');
$response = isset($response) ? $response : null;

if ($allowed_to_manage_services) {
	$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'add';
	switch ($action) {
	case 'add':
		if (!empty($_POST)) {
			$result = $fm_module_services->add($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else header('Location: ' . $GLOBALS['basename'] . '?type=' . $_POST['service_type']);
		}
		break;
	case 'delete':
		if (isset($_GET['id']) && !empty($_GET['id'])) {
			$service_delete_status = $fm_module_services->delete(sanitize($_GET['id']));
			if ($service_delete_status !== true) {
				$response = $service_delete_status;
				$action = 'add';
			} else header('Location: ' . $GLOBALS['basename'] . '?type=' . $_POST['service_type']);
		}
		break;
	case 'edit':
		if (!empty($_POST)) {
			$result = $fm_module_services->update($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else header('Location: ' . $GLOBALS['basename'] . '?type=' . $_POST['service_type']);
		}
		if (isset($_GET['status'])) {
			if (!updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', $_GET['id'], 'service_', $_GET['status'], 'service_id')) {
				$response = 'This service could not be ' . $_GET['status'] . '.';
			} else {
				/* set the service_build_config flag */
				$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}services` SET `service_build_config`='yes' WHERE `service_id`=" . sanitize($_GET['id']);
				$result = $fmdb->query($query);
				
				$tmp_name = getNameFromID($_GET['id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', 'service_', 'service_id', 'service_name');
				addLogEntry("Set service '$tmp_name' status to " . $_GET['status'] . '.');
				header('Location: ' . $GLOBALS['basename']);
			}
		}
		break;
	}
}

printHeader($page_name_sub . ' &lsaquo; ' . $_SESSION['module']);
@printMenu($page_name, $page_name_sub);

//$allowed_to_add = ($type == 'custom' && $allowed_to_manage_services) ? true : false;
echo printPageHeader($response, $page_name_sub . ' Services', $allowed_to_manage_services, $type);

$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', 'service_name', 'service_', "AND service_type='$type'");
$fm_module_services->rows($result, $type);

printFooter();

?>
