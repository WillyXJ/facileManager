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
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/                                           |
 +-------------------------------------------------------------------------+
*/

/** Don't display if there's no authentication service */
if (!getOption('auth_method')) unAuth();

/** Don't display keys if the setting is disabled */
if (!count($__FM_CONFIG['users']['avail_types'])) unAuth();

include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . 'facileManager' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class_users.php');

$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'add';

$form_data = null;
$response = isset($response) ? $response : null;

$type = (isset($_GET['type']) && array_key_exists(sanitize(strtolower($_GET['type'])), $__FM_CONFIG['users']['avail_types'])) ? sanitize(strtolower($_GET['type'])) : array_key_first($__FM_CONFIG['users']['avail_types']);
$display_type = $__FM_CONFIG['users']['avail_types'][$type];

switch ($action) {
case 'add':
	if (!empty($_POST)) {
		$response = ($_POST['type'] == 'users') ? $fm_users->addUser($_POST) : $fm_users->addGroup($_POST);
		if ($response !== true) {
			$form_data = $_POST;
		} else {
			header('Location: ' . $GLOBALS['basename'] . '?type=' . $_POST['type']);
			exit;
		}
	}
	break;
case 'edit':
	if (!empty($_POST)) {
		$response = ($_POST['type'] == 'users') ? $fm_users->editUser($_POST) : $fm_users->editGroup($_POST);
		if ($response !== true) {
			$form_data = $_POST;
		} else {
			header('Location: ' . $GLOBALS['basename'] . '?type=' . $_POST['type']);
			exit;
		}
	}
	if (isset($_GET['status']) && $_POST['type'] == 'users') {
		$user_id = sanitize($_GET['id']);
		$user_status = sanitize($_GET['status']);
		
		if ($user_id == 1) $user_id = 0;
		$user_info = getUserInfo($user_id);
		if ($user_info) {
			if ($user_info['user_template_only'] == 'no') {
				if (updateStatus('fm_users', $user_id, 'user_', $user_status, 'user_id')) {
					addLogEntry(sprintf(_("Set user '%s' status to %s."), $user_info['user_login'], $user_status), $fm_name);
					header('Location: ' . $GLOBALS['basename'] . '?type=' . $_POST['type']);
					exit;
				}
			}
		}
		$response = sprintf(_('This user could not be set to %s.') . "\n", $user_status);
	}
}

printHeader();
@printMenu();

$can_add = currentUserCan('manage_users');
if ($type == 'users') {
	$default_sort_field = $sort_field = 'user_login';
} elseif ($type == 'groups') {
	$default_sort_field = $sort_field = 'group_name';
} elseif ($type == 'keys') {
	$default_sort_field = $sort_field = 'key_id';
	$can_add = true;
}
$sort_direction = null;

$addl_title_blocks[] = buildSubMenu($type, $__FM_CONFIG['users']['avail_types']);
echo printPageHeader($response, $display_type, $can_add, $type, null, 'noscroll', $addl_title_blocks);

if (isset($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']])) {
	extract($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']], EXTR_OVERWRITE);
}

if ($type == 'users') {
	$sql = (!currentUserCan('manage_users')) ? 'AND user_id=' . $_SESSION['user']['id'] : null;
	$result = basicGetList('fm_users', array($sort_field, $default_sort_field), 'user_', $sql, null, false, $sort_direction);
} elseif ($type == 'groups') {
	$result = basicGetList('fm_groups', array($sort_field, $default_sort_field), 'group_', null, null, false, $sort_direction);
} elseif ($type == 'keys') {
	$sql = (!currentUserCan('manage_users')) ? 'AND user_id=' . $_SESSION['user']['id'] : null;
	$result = basicGetList('fm_keys', array($sort_field, $default_sort_field), 'key_', $sql, null, false, $sort_direction);
}
$total_pages = ceil($fmdb->num_rows / $_SESSION['user']['record_count']);
if ($page > $total_pages) $page = $total_pages;
$fm_users->rows($result, $type, $page, $total_pages);

printFooter();
