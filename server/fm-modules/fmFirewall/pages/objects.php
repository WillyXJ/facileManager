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
 | fmFirewall: Easily manage one or more software firewalls                |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmfirewall/                        |
 +-------------------------------------------------------------------------+
*/

if (!currentUserCan(array('manage_objects', 'view_all'), $_SESSION['module'])) unAuth();

define('FM_INCLUDE_SEARCH', true);

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_objects.php');

if (currentUserCan('manage_objects', $_SESSION['module'])) {
	$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'add';
	$uri_params = generateURIParams(array('type', 'q', 'p'), 'include');

	switch ($action) {
	case 'add':
	case 'edit':
		if (!empty($_POST)) {
			$result = ($action == 'add') ? $fm_module_objects->add($_POST) : $fm_module_objects->update($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else {
				header('Location: ' . $GLOBALS['basename'] . $uri_params);
				exit;
			}
		}
		break;
	}
}

printHeader();
@printMenu();

$search_query = createSearchSQL(array('name', 'type', 'address', 'mask', 'comment'), 'object_');

echo printPageHeader((string) $response, null, currentUserCan('manage_objects', $_SESSION['module']), 'host', null, 'noscroll');

$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', 'object_name', 'object_', $search_query);
$total_pages = ceil($fmdb->num_rows / $_SESSION['user']['record_count']);
if ($page > $total_pages) $page = $total_pages;
$fm_module_objects->rows($result, $page, $total_pages);

printFooter();
