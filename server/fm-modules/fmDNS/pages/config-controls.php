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
 | Processes server controls management page                               |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

if (!currentUserCan(array('manage_servers', 'view_all'), $_SESSION['module'])) unAuth();

include(ABSPATH . 'fm-modules/fmDNS/classes/class_controls.php');

$server_serial_no = (isset($_REQUEST['server_serial_no'])) ? sanitize($_REQUEST['server_serial_no']) : 0;
$response = isset($response) ? $response : null;

$type = (isset($_GET['type']) && array_key_exists(sanitize(strtolower($_GET['type'])), $__FM_CONFIG['operations']['avail_types'])) ? sanitize(strtolower($_GET['type'])) : 'controls';
$display_type = ($type == 'controls') ? __('Controls') : __('Statistics Channels');
$server_serial_no = (isset($_REQUEST['server_serial_no'])) ? sanitize($_REQUEST['server_serial_no']) : 0;

if (currentUserCan('manage_servers', $_SESSION['module'])) {
	$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'add';
	$server_serial_no_uri = (array_key_exists('server_serial_no', $_REQUEST) && $server_serial_no) ? '&server_serial_no=' . $server_serial_no : null;
	switch ($action) {
	case 'add':
		if (!empty($_POST)) {
			$result = $fm_dns_controls->add($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else {
				setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
				header('Location: ' . $GLOBALS['basename'] . '?type=' . $type . $server_serial_no_uri);
			}
		}
		break;
	case 'edit':
		if (!empty($_POST)) {
			$result = $fm_dns_controls->update($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else {
				setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
				header('Location: ' . $GLOBALS['basename'] . '?type=' . $type . $server_serial_no_uri);
			}
		}
	}
}

printHeader();
@printMenu();

$avail_types = buildSubMenu($type);
echo printPageHeader($response, $display_type, currentUserCan('manage_servers', $_SESSION['module']), $type);

$avail_servers = buildServerSubMenu($server_serial_no);

echo <<<HTML
<div id="pagination_container" class="submenus">
	<div>
	<div class="stretch"></div>
	$avail_types
	$avail_servers
	</div>
</div>

HTML;
	
$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'controls', 'control_id', 'control_', "AND control_type='$type' AND server_serial_no='$server_serial_no'");
$fm_dns_controls->rows($result, $type);

printFooter();

function buildSubMenu($option_type = 'controls') {
	global $__FM_CONFIG;
	
	$menu_selects = $uri_params = null;
	
	foreach ($GLOBALS['URI'] as $param => $val) {
		if (in_array($param, array('type', 'action', 'id', 'status'))) continue;
		$uri_params .= "&$param=$val";
	}
	
	foreach ($__FM_CONFIG['operations']['avail_types'] as $general => $type) {
		$select = ($option_type == $general) ? ' class="selected"' : '';
		$menu_selects .= "<span$select><a$select href=\"{$GLOBALS['basename']}?type=$general$uri_params\">" . ucfirst($type) . "</a></span>\n";
	}
	
	return '<div id="configtypesmenu">' . $menu_selects . '</div>';
}


?>
