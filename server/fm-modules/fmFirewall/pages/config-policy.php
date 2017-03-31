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
 | http://www.facilemanager.com/modules/fmdns/                             |
 +-------------------------------------------------------------------------+
 | Processes zone records page                                             |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

if (!currentUserCan(array('manage_policies', 'view_all'), $_SESSION['module'])) unAuth();

/** Include module variables */
if (isset($_SESSION['module'])) include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/variables.inc.php');

$server_config_page = $GLOBALS['RELPATH'] . $menu[getParentMenuKey()][4];
$type = (isset($_GET['type']) && array_key_exists(sanitize(strtolower($_GET['type'])), $__FM_CONFIG['policy']['avail_types'])) ? sanitize(strtolower($_GET['type'])) : 'filter';
$server_serial_no = (isset($_GET['server_serial_no'])) ? sanitize($_GET['server_serial_no']) : header('Location: ' . $server_config_page);
if (!$server_id = getServerID($server_serial_no, $_SESSION['module'])) header('Location: ' . $server_config_page);

/** Should not be here if the client has not been installed */
if (getNameFromID($server_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_id', 'server_installed') != 'yes') header('Location: ' . $server_config_page);

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_policies.php');

if (currentUserCan('manage_servers', $_SESSION['module'])) {
	$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'add';
	switch ($action) {
	case 'add':
		if (!empty($_POST)) {
			$result = $fm_module_policies->add($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else header('Location: ' . $GLOBALS['basename'] . "?type=$type&server_serial_no=$server_serial_no");
		}
		break;
	case 'edit':
		if (!empty($_POST)) {
			$result = $fm_module_policies->update($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else header('Location: ' . $GLOBALS['basename'] . "?type=$type&server_serial_no=$server_serial_no");
		}
		break;
	}
}

printHeader();
@printMenu();

$avail_types = buildSubMenu($type, $__FM_CONFIG['policy']['avail_types']);

echo printPageHeader((string) $response, null, currentUserCan('manage_servers', $_SESSION['module']), $type);
/*
echo <<<HTML
<div id="pagination_container" class="submenus">
	<div>
	<div class="stretch"></div>
	$avail_types
	</div>
</div>

HTML;
*/

$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_order_id', 'policy_', "AND server_serial_no=$server_serial_no AND policy_type='$type'");
$total_pages = ceil($fmdb->num_rows / $_SESSION['user']['record_count']);
if ($page > $total_pages) $page = $total_pages;
$fm_module_policies->rows($result, $type, $page, $total_pages);

printFooter();

?>
