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

include(ABSPATH . 'fm-modules/fmDNS/classes/class_servers.php');
$response = isset($response) ? $response : null;

$type = (isset($_GET['type']) && array_key_exists(sanitize(strtolower($_GET['type'])), $__FM_CONFIG['servers']['avail_types'])) ? sanitize(strtolower($_GET['type'])) : 'servers';
$display_type = ucfirst($__FM_CONFIG['servers']['avail_types'][$type]);

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
			$result = $fm_module_servers->update($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else header('Location: ' . $GLOBALS['basename'] . '?type=' . $_POST['sub_type']);
		}
		if (isset($_GET['status'])) {
			if (!updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', $_GET['id'], 'server_', $_GET['status'], 'server_id')) {
				$response = 'This server could not be ' . $_GET['status'] . '.';
			} else {
				/* set the server_build_config flag */
				$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` SET `server_build_config`='yes' WHERE `server_id`=" . sanitize($_GET['id']);
				$result = $fmdb->query($query);
				
				$tmp_name = getNameFromID($_GET['id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
				addLogEntry("Set server '$tmp_name' status to " . $_GET['status'] . '.');
				header('Location: ' . $GLOBALS['basename'] . '?type=' . $_POST['sub_type']);
			}
		}
		break;
	}
}

printHeader();
@printMenu();

$avail_types = buildSubMenu($type);
echo printPageHeader($response, getPageTitle() . ' ' . $display_type, currentUserCan('manage_servers', $_SESSION['module']), $type);
	
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
$fm_module_servers->rows($result, $type);

printFooter();


function buildSubMenu($option_type = 'servers') {
	global $__FM_CONFIG;
	
	$menu_selects = $uri_params = null;
	
	foreach ($GLOBALS['URI'] as $param => $val) {
		if ($param == 'type') continue;
		$uri_params .= "&$param=$val";
	}
	
	foreach ($__FM_CONFIG['servers']['avail_types'] as $general => $type) {
		$select = ($option_type == $general) ? ' class="selected"' : '';
		$menu_selects .= "<span$select><a$select href=\"{$GLOBALS['basename']}?type=$general$uri_params\">" . ucfirst($type) . "</a></span>\n";
	}
	
	return '<div id="configtypesmenu">' . $menu_selects . '</div>';
}


?>
