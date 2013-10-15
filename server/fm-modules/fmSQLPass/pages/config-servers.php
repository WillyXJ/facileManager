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
 | Processes server management page                                        |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

$page_name = 'Config';
$page_name_sub = 'Servers';

include(ABSPATH . 'fm-modules/fmSQLPass/classes/class_servers.php');
$response = isset($response) ? $response : null;

$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'add';
if ($allowed_to_manage_servers) {
	switch ($action) {
	case 'add':
		if (!empty($_POST)) {
			$result = $fm_module_servers->add($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else header('Location: ' . $GLOBALS['basename']);
		}
		break;
	case 'delete':
		if (isset($_GET['id'])) {
			$server_delete_status = $fm_module_servers->delete(sanitize($_GET['id']));
			if ($server_delete_status !== true) {
				$response = $server_delete_status;
				$action = 'add';
			} else header('Location: ' . $GLOBALS['basename']);
		}
		break;
	case 'edit':
		if (!empty($_POST)) {
			$result = $fm_module_servers->update($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else header('Location: ' . $GLOBALS['basename']);
		}
		if (isset($_GET['status'])) {
			if (!updateStatus('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'servers', $_GET['id'], 'server_', $_GET['status'], 'server_id')) {
				$response = 'This database server could not be '. $_GET['status'] .'.'. "\n";
			} else {
				$tmp_name = getNameFromID($_GET['id'], 'fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
				addLogEntry("Set database server '$tmp_name' status to " . $_GET['status'] . '.');
				header('Location: ' . $GLOBALS['basename']);
			}
		}
	}
}

printHeader();
@printMenu($page_name, $page_name_sub);

echo printPageHeader($response, 'Database Servers', $allowed_to_manage_servers);

$result = basicGetList('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'servers', 'server_name', 'server_');
$fm_module_servers->rows($result);

printFooter();

?>
