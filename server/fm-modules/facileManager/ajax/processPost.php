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
 | Processes form posts                                                    |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

define('AJAX', true);
require_once('../../../fm-init.php');

include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $fm_name . DIRECTORY_SEPARATOR . 'ajax' . DIRECTORY_SEPARATOR . 'functions.php');

/** Handle user password change */
if (is_array($_POST) && array_key_exists('user_id', $_POST)) {
	include(ABSPATH . 'fm-modules/'. $fm_name . '/classes/class_users.php');
	$update_status = $fm_users->update($_POST);
	if ($update_status !== true) {
		echo $update_status;
	} else {
		echo 'Success';
	}
/** Handle fM settings */
} elseif (is_array($_POST) && array_key_exists('item_type', $_POST) && $_POST['item_type'] == 'fm_settings') {
	if (!$allowed_to_manage_settings) returnUnAuth(false);

	include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $fm_name . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class_settings.php');
	
	if (isset($_POST['gen_ssh']) && $_POST['gen_ssh'] == true) {
		$save_result = $fm_settings->generateSSHKeyPair();
		echo ($save_result !== true) ? '<p class="error">' . $save_result . '</p>'. "\n" : 'Success';
	} else {
		$save_result = $fm_settings->save();
		echo ($save_result !== true) ? '<p class="error">' . $save_result . '</p>'. "\n" : '<p>These settings have been saved.</p>'. "\n";
	}

/** Handle module settings */
} elseif (is_array($_POST) && array_key_exists('item_type', $_POST) && $_POST['item_type'] == 'module_settings') {
	if (!$allowed_to_manage_settings) returnUnAuth(false);

	include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class_settings.php');
	$save_result = $fm_module_settings->save();
	echo ($save_result !== true) ? '<p class="error">' . $save_result . '</p>'. "\n" : '<p>These settings have been saved.</p>'. "\n";

/** Handle client upgrades */
} elseif (is_array($_POST) && array_key_exists('action', $_POST) && $_POST['action'] == 'bulk' &&
	array_key_exists('bulk_action', $_POST) && $_POST['bulk_action'] == 'upgrade') {
	if (is_array($_POST['item_id'])) {
		foreach ($_POST['item_id'] as $serial_no) {
			if (!is_numeric($serial_no)) continue;
			
			echo $fm_shared_module_servers->doClientUpgrade($serial_no);
			echo "\n";
		}
		echo "\n" . ucfirst($_POST['bulk_action']) . ' is complete.';
	}

/** Handle everything else */
} elseif (is_array($_POST) && array_key_exists('item_type', $_POST) && $_POST['item_type'] == 'users') {
	if (!$allowed_to_manage_users) returnUnAuth();
	
	if (isset($_POST['item_id'])) {
		$id = sanitize($_POST['item_id']);
	} else returnError();

	include(ABSPATH . 'fm-modules/'. $fm_name . '/classes/class_users.php');
	
	switch ($_POST['action']) {
		case 'delete':
			if (isset($id)) {
				$delete_status = $fm_users->delete(sanitize($id));
				if ($delete_status !== true) {
					echo $delete_status;
				} else {
					echo 'Success';
				}
			}
			break;
	}
} else {
	$include_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'ajax' . DIRECTORY_SEPARATOR . 'processPost.php';
	if (file_exists($include_file)) {
		include($include_file);
	}
}

?>