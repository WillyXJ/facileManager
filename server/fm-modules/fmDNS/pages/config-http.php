<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) The facileManager Team                                    |
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
 | https://www.facilemanager.com/modules/fmdns/                             |
 +-------------------------------------------------------------------------+
*/

if (!currentUserCan(array('manage_servers', 'view_all'), $_SESSION['module'])) unAuth();

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_http.php');

$server_serial_no = (isset($_REQUEST['server_serial_no'])) ? sanitize($_REQUEST['server_serial_no']) : 0;
$display_option_type_sql = 'http';

if (currentUserCan('manage_servers', $_SESSION['module'])) {
	$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'add';
	$server_serial_no_uri = (array_key_exists('server_serial_no', $_REQUEST) && $server_serial_no) ? '?server_serial_no=' . $server_serial_no : null;
	switch ($action) {
	case 'add':
		if (!empty($_POST)) {
			$result = $fm_module_http->add($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else {
				setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
				header('Location: ' . $GLOBALS['basename'] . $server_serial_no_uri);
				exit;
			}
		}
		break;
	case 'edit':
		if (!empty($_POST)) {
			$result = $fm_module_http->update($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else {
				setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
				header('Location: ' . $GLOBALS['basename'] . $server_serial_no_uri);
				exit;
			}
		}
	}
}

printHeader();
@printMenu();

$avail_servers = buildServerSubMenu($server_serial_no);

echo printPageHeader((string) $response, null, currentUserCan('manage_servers', $_SESSION['module']));
echo <<<HTML
<div id="pagination_container" class="submenus">
	<div>
	<div class="stretch"></div>
	$avail_servers
	</div>
</div>

HTML;
	
$sort_direction = null;
$sort_field = 'master_name';
if (isset($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']])) {
	extract($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']], EXTR_OVERWRITE);
}

$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', array('cfg_order_id'), 'cfg_', "AND cfg_type='$display_option_type_sql' AND server_serial_no='$server_serial_no' AND cfg_name='http-endpoint' AND domain_id=0 AND cfg_isparent='yes'", null, false, $sort_direction);
$total_pages = ceil($fmdb->num_rows / $_SESSION['user']['record_count']);
if ($page > $total_pages) $page = $total_pages;
$fm_module_http->rows($result, $page, $total_pages);

printFooter();
