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
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/                                           |
 +-------------------------------------------------------------------------+
 | Processes user management page                                          |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

/** Don't display if there's no authentication service */
if (!getOption('auth_method')) unAuth();

if (!currentUserCan('manage_users')) unAuth();

include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . 'facileManager' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class_users.php');

$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'add';

$form_data = null;
$response = isset($response) ? $response : null;

$type = (isset($_GET['type']) && array_key_exists(sanitize(strtolower($_GET['type'])), $__FM_CONFIG['users']['avail_types'])) ? sanitize(strtolower($_GET['type'])) : 'users';
$display_type = $__FM_CONFIG['users']['avail_types'][$type];

switch ($action) {
case 'add':
	if (!empty($_POST)) {
		$response = ($_POST['type'] == 'users') ? $fm_users->addUser($_POST) : $fm_users->addGroup($_POST);
		if ($response !== true) {
			$form_data = $_POST;
		} else header('Location: ' . $GLOBALS['basename'] . '?type=' . $_POST['type']);
	}
	break;
case 'edit':
	if (!empty($_POST)) {
		$response = ($_POST['type'] == 'users') ? $fm_users->updateUser($_POST) : $fm_users->updateGroup($_POST);
		if ($response !== true) {
			$form_data = $_POST;
		} else header('Location: ' . $GLOBALS['basename'] . '?type=' . $_POST['type']);
	}
	if (isset($_GET['status']) && $_POST['type'] == 'users') {
		if ($_GET['id'] == 1) $_GET['id'] = 0;
		$user_info = getUserInfo($_GET['id']);
		if ($user_info) {
			if ($user_info['user_template_only'] == 'no') {
				if (updateStatus('fm_users', $_GET['id'], 'user_', $_GET['status'], 'user_id')) {
					addLogEntry(sprintf(_("Set user '%s' status to %s."), $user_info['user_login'], $_GET['status']), $fm_name);
					header('Location: ' . $GLOBALS['basename'] . '?type=' . $_POST['type']);
				}
			}
		}
		$response = sprintf(_('This user could not be set to %s.') . "\n", $_GET['status']);
	}
}

printHeader();
@printMenu();

$avail_types = buildSubMenu($type);
echo printPageHeader($response, $display_type, currentUserCan('manage_users'), $type);

$sort_field = ($type == 'users') ? 'user_login' : 'group_name';
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

$result = ($type == 'users') ? basicGetList('fm_users', $sort_field, 'user_', null, null, false, $sort_direction) : basicGetList('fm_groups', $sort_field, 'group_', null, null, false, $sort_direction);
$fm_users->rows($result, $type);

printFooter();


function buildSubMenu($option_type = 'users') {
	global $__FM_CONFIG;
	
	$menu_selects = $uri_params = null;
	
	foreach ($GLOBALS['URI'] as $param => $val) {
		if (in_array($param, array('type', 'action', 'id', 'status'))) continue;
		$uri_params .= "&$param=$val";
	}
	
	foreach ($__FM_CONFIG['users']['avail_types'] as $general => $type) {
		$select = ($option_type == $general) ? ' class="selected"' : '';
		$menu_selects .= "<span$select><a$select href=\"{$GLOBALS['basename']}?type=$general$uri_params\">" . ucfirst($type) . "</a></span>\n";
	}
	
	return '<div id="configtypesmenu">' . $menu_selects . '</div>';
}

?>