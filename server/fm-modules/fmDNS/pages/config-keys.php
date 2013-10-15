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
 | Processes server keys management page                                   |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

$page_name = 'Config';
$page_name_sub = 'Keys';

include(ABSPATH . 'fm-modules/fmDNS/classes/class_keys.php');

$server_serial_no = (isset($_REQUEST['server_serial_no'])) ? sanitize($_REQUEST['server_serial_no']) : 0;
$response = isset($response) ? $response : null;

if ($allowed_to_manage_servers) {
	$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'add';
	$server_serial_no_uri = (array_key_exists('server_serial_no', $_REQUEST)) ? '?server_serial_no=' . $server_serial_no : null;
	switch ($action) {
	case 'add':
		if (!empty($_POST)) {
			$result = $fm_dns_keys->add($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else {
				setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
				header('Location: ' . $GLOBALS['basename'] . $server_serial_no_uri);
			}
		}
		break;
	case 'delete':
		if (isset($_GET['id']) && !empty($_GET['id'])) {
			$delete_status = $fm_dns_keys->delete(sanitize($_GET['id']), $server_serial_no);
			if ($delete_status !== true) {
				$response = $delete_status;
			} else header('Location: ' . $GLOBALS['basename'] . $server_serial_no_uri);
		}
		break;
	case 'edit':
		if (!empty($_POST)) {
			$result = $fm_dns_keys->update($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else {
				setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
				header('Location: ' . $GLOBALS['basename'] . $server_serial_no_uri);
			}
		}
		if (isset($_GET['status'])) {
			if (!updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', $_GET['id'], 'key_', $_GET['status'], 'key_id')) {
				$response = 'This item could not be '. $_GET['status'] . '.';
			} else {
				/* set the key_build_config flag */
				$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}keys` SET `key_build_config`='yes' WHERE `key_id`=" . sanitize($_GET['id']);
				$result = $fmdb->query($query);
				
				setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
				$tmp_name = getNameFromID($_GET['id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_', 'key_id', 'key_name');
				addLogEntry("Set key '$tmp_name' status to " . $_GET['status'] . '.');
				header('Location: ' . $GLOBALS['basename'] . $server_serial_no_uri);
			}
		}
		break;
	case 'build':
		if (isset($_GET['id']) && !empty($_GET['id'])) {
			$build_status = buildKeyConfig(sanitize($_GET['id']));
			
			if ($build_status) {
				/* reset the key_build_config flag */
				$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}keys` SET `key_build_config`='no',`key_update_config`='yes' WHERE `key_id`=" . sanitize($_GET['id']);
				$result = $fmdb->query($query);
	
				$response = 'This is not yet implemented.' . "\n";
			} else $response = 'Building key configs failed.' . "\n";
			} else {
				setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
				header('Location: ' . $GLOBALS['basename'] . $server_serial_no_uri);
			}
	}
}

printHeader();
@printMenu($page_name, $page_name_sub);

echo printPageHeader($response, 'Keys', $allowed_to_manage_servers);
	
$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_name', 'key_');
$fm_dns_keys->rows($result);

printFooter();

?>
