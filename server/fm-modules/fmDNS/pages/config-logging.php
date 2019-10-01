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
 | fmDNS: Easily manage one or more ISC BIND servers                       |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdns/                             |
 +-------------------------------------------------------------------------+
*/

if (!currentUserCan(array('manage_servers', 'view_all'), $_SESSION['module'])) unAuth();

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_logging.php');

$type = (isset($_GET['type'])) ? sanitize(strtolower($_GET['type'])) : 'channel';
$display_type = $__FM_CONFIG['logging']['avail_types'][$type];
$channel_category = ($type == 'channel') ? 'channel' : 'category';
$server_serial_no = (isset($_REQUEST['server_serial_no'])) ? sanitize($_REQUEST['server_serial_no']) : 0;

/* Ensure proper type is defined */
if (!array_key_exists($type, $__FM_CONFIG['logging']['avail_types'])) {
	header('Location: ' . $GLOBALS['basename']);
	exit;
}

if (currentUserCan('manage_servers', $_SESSION['module'])) {
	$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'add';
	$server_serial_no_uri = (array_key_exists('server_serial_no', $_REQUEST) && $server_serial_no) ? '&server_serial_no=' . $server_serial_no : null;
	switch ($action) {
	case 'add':
		if (!empty($_POST)) {
			$result = ($_POST['sub_type'] == 'channel') ? $fm_module_logging->addChannel($_POST) : $fm_module_logging->addCategory($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else {
				setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
				header('Location: ' . $GLOBALS['basename'] . '?type=' . $type . $server_serial_no_uri);
				exit;
			}
		}
		break;
	case 'edit':
		if (!empty($_POST)) {
			$result = $fm_module_logging->update($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else {
				setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
				header('Location: ' . $GLOBALS['basename'] . '?type=' . $_POST['sub_type'] . $server_serial_no_uri);
				exit;
			}
		}
	}
} $server_serial_no_uri = null;

printHeader();
@printMenu();

$avail_types = buildSubMenu($type, $__FM_CONFIG['logging']['avail_types']);
$avail_servers = buildServerSubMenu($server_serial_no);

echo printPageHeader((string) $response, getPageTitle() . ' ' . $display_type, currentUserCan('manage_servers', $_SESSION['module']), $type);
echo <<<HTML
<div id="pagination_container" class="submenus">
	<div>
	<div class="stretch"></div>
	$avail_types
	$avail_servers
	</div>
</div>

HTML;
	
$sort_direction = null;
$sort_field = 'cfg_data';
if (isset($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']])) {
	extract($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']], EXTR_OVERWRITE);
}

$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', array($sort_field, 'cfg_data'), 'cfg_', 'AND cfg_type="logging" AND cfg_name="' . $channel_category . '" AND server_serial_no="' . $server_serial_no. '"', null, false, $sort_direction);
$total_pages = ceil($fmdb->num_rows / $_SESSION['user']['record_count']);
if ($page > $total_pages) $page = $total_pages;
$fm_module_logging->rows($result, $channel_category, $page, $total_pages);

printFooter();

?>
