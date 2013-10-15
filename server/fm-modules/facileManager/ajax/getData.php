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
 | Displays forms                                                          |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

define('AJAX', true);
require_once('../../../fm-init.php');

include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $fm_name . DIRECTORY_SEPARATOR . 'ajax' . DIRECTORY_SEPARATOR . 'functions.php');

/** Process account settings */
if (is_array($_POST) && array_key_exists('user_id', $_POST)) {
	/** Password reset */
	if (array_key_exists('reset_pwd', $_POST)) {
		$result = $fm_login->processUserPwdResetForm($_POST['user_id']);
		if ($result === true) {
			echo '<p>Password reset email has been sent to ' . $_POST['user_id'] . '.</p>';
		} else {
			echo '<p class="error">' . $result . '</p>';
		}
		
		exit;
	}
	
	if ($_POST['user_id'] != $_SESSION['user']['id']) returnUnAuth();
	
	include(ABSPATH . 'fm-modules/'. $fm_name . '/classes/class_users.php');
	
	$form_bits = array('user_login', 'user_password');
	$edit_form = '<h2>Change Password</h2>' . "\n";
	basicGet('fm_users', $_SESSION['user']['id'], 'user_', 'user_id');
	$results = $fmdb->last_result;
	if (!$fmdb->num_rows) returnError();
	
	$edit_form_data[] = $results[0];
	$edit_form .= $fm_users->printUsersForm($edit_form_data, 'edit', $form_bits);

	exit($edit_form);
}

if (is_array($_POST) && array_key_exists('item_type', $_POST) && $_POST['item_type'] == 'users') {
	if (!$allowed_to_manage_users) returnUnAuth();
	
	if (array_key_exists('add_form', $_POST)) {
		$id = isset($_POST['item_id']) ? sanitize($_POST['item_id']) : null;
		$add_new = true;
	} elseif (array_key_exists('item_id', $_POST)) {
		$id = sanitize($_POST['item_id']);
		$view_id = isset($_POST['view_id']) ? sanitize($_POST['view_id']) : null;
		$add_new = false;
	} else returnError();

	$item_type = $_POST['item_type'];
	include(ABSPATH . 'fm-modules/'. $fm_name . '/classes/class_users.php');
	
	if ($add_new) {
		$form_bits = ($allowed_to_manage_users) ? array('user_login', 'user_email', 'user_password', 'user_options', 'user_perms') : array('user_password');

		$edit_form = '<h2>Add ' . substr(ucfirst($item_type), 0, -1) . '</h2>' . "\n";
		$edit_form .= $fm_users->printUsersForm(null, 'add', $form_bits);
	} else {
		$form_bits = ($allowed_to_manage_users) ? array('user_login', 'user_email', 'user_options', 'user_perms') : array('user_password');

		$edit_form = '<h2>Edit ' . substr(ucfirst($item_type), 0, -1) . '</h2>' . "\n";
		basicGet('fm_users', $id, 'user_', 'user_id');
		$results = $fmdb->last_result;
		if (!$fmdb->num_rows) returnError();
		
		$edit_form_data[] = $results[0];
		if ($allowed_to_manage_users && $edit_form_data[0]->user_auth_type == 2) $form_bits = array('user_login', 'user_email', 'user_perms');
		$edit_form .= $fm_users->printUsersForm($edit_form_data, 'edit', $form_bits);
	}
	
	echo $edit_form;
} else {
	$include_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'ajax' . DIRECTORY_SEPARATOR . 'getData.php';
	if (file_exists($include_file)) {
		include($include_file);
	}
}

?>
