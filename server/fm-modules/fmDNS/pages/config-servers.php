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
 | Processes servers management page                                       |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

if (!currentUserCan(array('manage_servers', 'build_server_configs', 'view_all'), $_SESSION['module'])) unAuth();

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_servers.php');
$response = isset($response) ? $response : null;

$type = (isset($_GET['type']) && array_key_exists(sanitize(strtolower($_GET['type'])), $__FM_CONFIG['servers']['avail_types'])) ? sanitize(strtolower($_GET['type'])) : 'servers';
$display_type = ($type == 'servers') ? __('Name Servers') : __('Name Server Groups');

if (currentUserCan('manage_servers', $_SESSION['module'])) {
	$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'add';
	switch ($action) {
	case 'add':
		if (!empty($_POST)) {
			if ($_POST['sub_type'] == 'servers') {
				$result = $fm_module_servers->addServer($_POST);
			} elseif ($_POST['sub_type'] == 'groups') {
				$result = $fm_module_servers->addGroup($_POST);
			}
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else header('Location: ' . $GLOBALS['basename'] . '?type=' . $_POST['sub_type']);
		}
		break;
	case 'edit':
		if (!empty($_POST)) {
			if ($_POST['sub_type'] == 'servers') {
				$result = $fm_module_servers->updateServer($_POST);
			} elseif ($_POST['sub_type'] == 'groups') {
				$result = $fm_module_servers->updateGroup($_POST);
			}
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else header('Location: ' . $GLOBALS['basename'] . '?type=' . $_POST['sub_type']);
		}
		if (isset($_GET['status'])) {
			if ($_GET['type'] == 'servers') {
				if (!updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', $_GET['id'], 'server_', $_GET['status'], 'server_id')) {
					$response = sprintf(__('This server could not be set to %s.') . "\n", $_GET['status']);
				} else {
					/* set the server_build_config flag */
					$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` SET `server_build_config`='yes' WHERE `server_id`=" . sanitize($_GET['id']);
					$result = $fmdb->query($query);

					$tmp_name = getNameFromID($_GET['id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
					addLogEntry(sprintf(__('Set server (%s) status to %s.'), $tmp_name, $_GET['status']));
					header('Location: ' . $GLOBALS['basename'] . '?type=' . $_GET['type']);
				}
			} elseif ($_GET['type'] == 'groups') {
				if (!updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'server_groups', $_GET['id'], 'group_', $_GET['status'], 'group_id')) {
					$response = sprintf(__('This server group could not be set to %s.') . "\n", $_GET['status']);
				} else {
					$tmp_name = getNameFromID($_GET['id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'server_groups', 'group_', 'group_id', 'group_name');
					addLogEntry(sprintf(__('Set server group (%s) status to %s.'), $tmp_name, $_GET['status']));
					header('Location: ' . $GLOBALS['basename'] . '?type=' . $_GET['type']);
				}
			}
		}
		break;
	}
}

printHeader();
@printMenu();

$avail_types = buildSubMenu($type, $__FM_CONFIG['servers']['avail_types']);
echo printPageHeader($response, $display_type, currentUserCan('manage_servers', $_SESSION['module']), $type);
	
$sort_direction = null;
if (isset($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']])) {
	extract($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']], EXTR_OVERWRITE);
}

echo <<<HTML
<div id="pagination_container" class="submenus">
	<div>
	<div class="stretch"></div>
	$avail_types
	</div>
</div>

HTML;

if ($type == 'groups') {
	$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'server_groups', 'group_name', 'group_', null, null, false, $sort_direction);
} else {
	$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_name', 'server_', null, null, false, $sort_direction);
}
$total_pages = ceil($fmdb->num_rows / $_SESSION['user']['record_count']);
if ($page > $total_pages) $page = $total_pages;
$fm_module_servers->rows($result, $type, $page, $total_pages);

printFooter();


?>
