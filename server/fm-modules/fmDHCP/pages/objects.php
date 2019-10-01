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
 | fmDHCP: Easily manage one or more ISC DHCP servers                      |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdhcp/                            |
 +-------------------------------------------------------------------------+
*/

if (!isset($type)) {
	header('Location: object-hosts.php');
	exit;
}
//if (isset($_GET['type'])) header('Location: config-' . sanitize(strtolower($_GET['type'])) . '.php');

/** Ensure user can use this page */
if (!currentUserCan(array_merge($required_permission, array('view_all')), $_SESSION['module'])) unAuth();

$server_serial_no = (isset($_REQUEST['server_serial_no'])) ? sanitize($_REQUEST['server_serial_no']) : 0;
if (!isset($display_type)) $display_type = null;
if (!isset($avail_types)) $avail_types = null;
if (!isset($include_submenus)) $include_submenus = true;

if (currentUserCan($required_permission, $_SESSION['module'])) {
	$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'add';
	$uri_params = generateURIParams(array('type', 'server_serial_no'), 'include');
	
	switch ($action) {
	case 'add':
		if (!empty($_POST)) {
			$result = $fm_dhcp_item->add($_POST, $type);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else {
				setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
				header('Location: ' . $GLOBALS['basename'] . $uri_params);
				exit;
			}
		}
		break;
	case 'edit':
		if (!empty($_POST)) {
			$result = $fm_dhcp_item->update($_POST, $type);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else {
				setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
				header('Location: ' . $GLOBALS['basename'] . $uri_params);
				exit;
			}
		}
		break;
	}
}

printHeader();
@printMenu();

echo printPageHeader((string) $response, $display_type, currentUserCan($required_permission, $_SESSION['module']), $type);

if ($include_submenus === true) {
//	$avail_servers = buildServerSubMenu($server_serial_no);
	echo <<<HTML
<div id="pagination_container" class="submenus">
	<div>
	<div class="stretch"></div>
	$avail_types
	$avail_servers
	</div>
</div>

HTML;
}
	
/** Get server listing */
$sort_direction = null;
$sort_field = 'config_data';
$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', array($sort_field, 'config_data'), 'config_', 'AND config_type="' . rtrim($type, 's') . '" AND config_name="' . rtrim($type, 's') . '" AND server_serial_no="' . $server_serial_no. '"', null, false, $sort_direction);
$total_pages = ceil($fmdb->num_rows / $_SESSION['user']['record_count']);
if ($page > $total_pages) $page = $total_pages;
$fm_dhcp_item->rows($result, $page, $total_pages, $type);

printFooter();

?>
