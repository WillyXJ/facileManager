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

/** Ensure user can use this page */
if (!currentUserCan(array('manage_leases', 'view_all'), $_SESSION['module'])) unAuth();

$server_serial_no = (isset($_REQUEST['server_serial_no'])) ? sanitize($_REQUEST['server_serial_no']) : 0;

if (currentUserCan('manage_leases', $_SESSION['module'])) {
	$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'add';
	$uri_params = generateURIParams(array('server_serial_no'), 'include');
	
	switch ($action) {
	case 'add':
		if (!empty($_POST)) {
			if (!isset($fm_dhcp_item)) {
				if (!class_exists('fm_dhcp_hosts')) {
					include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_hosts.php');
				}

				$fm_dhcp_item = new fm_dhcp_hosts();
			}

			$result = $fm_dhcp_item->add($_POST, 'host');
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
//	case 'edit':
//		if (!empty($_POST)) {
//			$result = $fm_dhcp_item->update($_POST, $type);
//			if ($result !== true) {
//				$response = $result;
//				$form_data = $_POST;
//			} else header('Location: ' . $GLOBALS['basename'] . $uri_params);
//		}
//		break;
	}
}

$placeholder = sprintf('<p id="table_edits" class="noresult">%s</p>', __('You must select a server to view leases from.'));

printHeader();
@printMenu();

echo printPageHeader((string) $response);

$avail_servers = buildServerSubMenu($server_serial_no);
echo <<<HTML
<div id="pagination_container" class="submenus">
	<div>
	<div class="stretch"></div>
	$avail_servers
	</div>
</div>

HTML;

if ($server_serial_no) {
	include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_leases.php');
	$placeholder = $fm_dhcp_leases->getServerLeases(sanitize($server_serial_no));
}

echo $placeholder;

printFooter();

?>
