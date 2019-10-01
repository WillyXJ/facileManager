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
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/                                           |
 +-------------------------------------------------------------------------+
 | Displays forms                                                          |
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
			printf(_('<p>Password reset email has been sent to %s.</p>'), $_POST['user_id']);
		} else {
			echo displayResponseClose($result);
		}
		
		exit;
	}
	
	if ($_POST['user_id'] != $_SESSION['user']['id']) returnUnAuth();
	
	include(ABSPATH . 'fm-modules/'. $fm_name . '/classes/class_users.php');
	
	$form_bits = array('user_login', 'user_comment', 'user_email', 'user_module');
	if (getNameFromID($_SESSION['user']['id'], 'fm_users', 'user_', 'user_id', 'user_auth_type') == 1) {
		$form_bits[] = 'user_password';
	}
	$edit_form = '<div id="popup_response" style="display: none;"></div>' . "\n";
	basicGet('fm_users', $_SESSION['user']['id'], 'user_', 'user_id');
	$results = $fmdb->last_result;
	if (!$fmdb->num_rows || $fmdb->sql_errors) returnError($fmdb->last_error);
	
	$edit_form_data[] = $results[0];
	$edit_form .= $fm_users->printUsersForm($edit_form_data, 'edit', $form_bits, 'users', 'Save', 'update_user_profile', null, false);

	exit($edit_form);
}

if (is_array($_POST) && array_key_exists('item_type', $_POST) && $_POST['item_type'] == 'users') {
	if (!currentUserCan('manage_users')) returnUnAuth();
	
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
		if (currentUserCan('manage_users')) {
			$form_bits = ($_POST['item_sub_type'] == 'users') ? array('user_login', 'user_comment', 'user_email', 'user_auth_method', 'user_password', 'user_options', 'user_perms', 'user_module', 'user_groups') : array('group_name', 'comment', 'group_users', 'user_perms');
		} else {
			$form_bits = array('user_password');
		}

		$form_data = null;
		if ($id) {
			basicGet('fm_users', $id, 'user_', 'user_id');
			$results = $fmdb->last_result;
			if (!$fmdb->num_rows || $fmdb->sql_errors) returnError($fmdb->last_error);

			$form_data[] = $results[0];
			$form_data[0]->user_login = null;
			$form_data[0]->user_template_only = false;
		}
		$edit_form = $fm_users->printUsersForm($form_data, 'add', $form_bits, $_POST['item_sub_type']);
	} else {
		$functionCan = rtrim($_POST['item_sub_type'], 's') . 'Can';
		
		if ((!currentUserCan('do_everything') && $functionCan($id, 'do_everything'))) {
			returnUnAuth();
		}
		if ($_POST['item_sub_type'] == 'users') {
			if (currentUserCan('manage_users')) {
				basicGet('fm_users', $id, 'user_', 'user_id');
				$form_bits = ($fmdb->last_result[0]->user_auth_type == 2) ? array('user_login', 'user_comment', 'user_email', 'user_perms', 'user_module', 'user_groups') : array('user_login', 'user_comment', 'user_email', 'user_options', 'user_perms', 'user_module', 'user_groups');
				if ($fmdb->last_result[0]->user_auth_type != 2) {
					$form_bits[] = 'user_password';
				}
			} else {
				$form_bits = array('user_password');
			}
			basicGet('fm_users', $id, 'user_', 'user_id');
		} elseif ($_POST['item_sub_type'] == 'groups') {
			if (currentUserCan('manage_users')) {
				$form_bits = array('group_name', 'comment', 'group_users', 'user_perms');
				basicGet('fm_groups', $id, 'group_', 'group_id');
			} else {
				return returnUnAuth();
			}
		}

		$results = $fmdb->last_result;
		if (!$fmdb->num_rows || $fmdb->sql_errors) returnError($fmdb->last_error);
		
		$edit_form_data[] = $results[0];
		$edit_form = $fm_users->printUsersForm($edit_form_data, 'edit', $form_bits, $_POST['item_sub_type']);
	}
	
	echo $edit_form;
} elseif (isset($_SESSION['module']) && $_SESSION['module'] != $fm_name) {
	$include_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'ajax' . DIRECTORY_SEPARATOR . 'getData.php';
	if (file_exists($include_file)) {
		include($include_file);
	}
}

?>
