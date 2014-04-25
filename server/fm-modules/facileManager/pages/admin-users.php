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

switch ($action) {
case 'add':
	if (!empty($_POST)) {
		$response = $fm_users->add($_POST);
		if ($response !== true) {
			$form_data = $_POST;
		} else header('Location: ' . $GLOBALS['basename']);
	}
	break;
case 'edit':
	if (!empty($_POST)) {
		$response = $fm_users->update($_POST);
		if ($response !== true) {
			$form_data = $_POST;
		} else header('Location: ' . $GLOBALS['basename']);
	}
	if (isset($_GET['status'])) {
		if ($_GET['id'] == 1) $_GET['id'] = 0;
		$user_info = getUserInfo($_GET['id']);
		if ($user_info) {
			if ($user_info['user_template_only'] == 'no') {
				if (updateStatus('fm_users', $_GET['id'], 'user_', $_GET['status'], 'user_id')) {
					addLogEntry("Set user '" . $user_info['user_login'] . "' status to " . $_GET['status'] . '.', $fm_name);
					header('Location: ' . $GLOBALS['basename']);
				}
			}
		}
		$response = 'This user could not be set to '. $_GET['status'] .'.'. "\n";
	}
}

printHeader();
@printMenu();

echo printPageHeader($response, null, currentUserCan('manage_users'));

$sort_field = 'user_login';
$sort_direction = null;

if (isset($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']])) {
	extract($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']], EXTR_OVERWRITE);
}

$result = basicGetList('fm_users', $sort_field, 'user_', null, null, false, $sort_direction);
$fm_users->rows($result);

printFooter();

?>