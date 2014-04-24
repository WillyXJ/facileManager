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
 | Processes groups management page                                        |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

$page_name = 'Config';
$page_name_sub = 'Server Groups';

include(ABSPATH . 'fm-modules/fmSQLPass/classes/class_groups.php');
$response = isset($response) ? $response : null;

$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'add';
if (currentUserCan('manage_servers', $_SESSION['module'])) {
	switch ($action) {
	case 'add':
		if (!empty($_POST)) {
			$result = $fm_sqlpass_groups->add($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else header('Location: ' . $GLOBALS['basename']);
		}
		break;
	case 'delete':
		if (isset($_GET['id'])) {
			$delete_status = $fm_sqlpass_groups->delete(sanitize($_GET['id']));
			if ($delete_status !== true) {
				$response = $delete_status;
			} else header('Location: ' . $GLOBALS['basename']);
		}
		break;
	case 'edit':
		if (!empty($_POST)) {
			$result = $fm_sqlpass_groups->update($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else header('Location: ' . $GLOBALS['basename']);
		}
		if (isset($_GET['status'])) {
			if (!updateStatus('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'groups', $_GET['id'], 'group_', $_GET['status'], 'group_id')) {
				$response = 'This backup group could not be '. $_GET['status'] .'.'. "\n";
			} else {
				$tmp_name = getNameFromID($_GET['id'], 'fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'groups', 'group_', 'group_id', 'group_name');
				addLogEntry("Set server group '$tmp_name' status to " . $_GET['status'] . '.');
				header('Location: ' . $GLOBALS['basename']);
			}
		}
	}
}

printHeader($page_name_sub . ' &lsaquo; ' . $_SESSION['module']);
@printMenu($page_name, $page_name_sub);

echo printPageHeader($response, 'Server Groups', currentUserCan('manage_servers', $_SESSION['module']));
	
$result = basicGetList('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'groups', 'group_id', 'group_');
$fm_sqlpass_groups->rows($result);

printFooter();

?>
