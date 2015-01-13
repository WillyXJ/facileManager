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
	include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $fm_name . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class_users.php');
	$update_status = $fm_users->update($_POST);
	if ($update_status !== true) {
		echo $update_status;
	} else {
		echo 'Success';
	}
/** Handle fM settings */
} elseif (is_array($_POST) && array_key_exists('item_type', $_POST) && $_POST['item_type'] == 'fm_settings') {
	if (!currentUserCan('manage_settings')) returnUnAuth(false);

	include_once(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $fm_name . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class_settings.php');
	
	if (isset($_POST['gen_ssh']) && $_POST['gen_ssh'] == true) {
		$save_result = $fm_settings->generateSSHKeyPair();
		echo ($save_result !== true) ? '<p class="error">' . $save_result . '</p>'. "\n" : 'Success';
	} else {
		$save_result = $fm_settings->save();
		echo ($save_result !== true) ? '<p class="error">' . $save_result . '</p>'. "\n" : '<p>These settings have been saved.</p>'. "\n";
	}

/** Handle module settings */
} elseif (is_array($_POST) && array_key_exists('item_type', $_POST) && $_POST['item_type'] == 'module_settings') {
	if (!currentUserCan('manage_settings', $_SESSION['module'])) returnUnAuth(false);

	include_once(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class_settings.php');
	$save_result = $fm_module_settings->save();
	echo ($save_result !== true) ? '<p class="error">' . $save_result . '</p>'. "\n" : '<p>These settings have been saved.</p>'. "\n";

/** Handle bulk actions */
} elseif (is_array($_POST) && array_key_exists('action', $_POST) && $_POST['action'] == 'bulk' &&
	array_key_exists('bulk_action', $_POST) && in_array($_POST['bulk_action'], array('upgrade', 'build config'))) {
	switch($_POST['bulk_action']) {
		/** Handle client upgrades */
		case 'upgrade':
			/** Check permissions */
			if (!currentUserCan('manage_servers', $_SESSION['module'])) {
				returnUnAuth();
			}
			
			$bulk_function = 'doClientUpgrade';
			break;
		/** Handle client server config builds */
		case 'build config':
			/** Check permissions */
			if (!currentUserCan('build_server_configs', $_SESSION['module'])) {
				returnUnAuth();
			}
			
			$bulk_function = 'doBulkServerBuild';
			break;
	}
	$result = "<pre>\n";
	if (is_array($_POST['item_id'])) {
		foreach ($_POST['item_id'] as $serial_no) {
			if (!is_numeric($serial_no)) continue;
			
			$result .= $fm_shared_module_servers->$bulk_function($serial_no);
			$result .= "\n";
		}
	}
	$result .= "</pre>\n<p class=\"complete\">" . ucwords($_POST['bulk_action']) . ' is complete.</p>';
	echo buildPopup('header', ucwords($_POST['bulk_action']) . ' Results') . $result . buildPopup('footer', 'OK', array('cancel_button' => 'cancel'), getMenuURL('Servers'));

/** Handle mass updates */
} elseif (is_array($_POST) && array_key_exists('action', $_POST) && $_POST['action'] == 'process-all-updates') {
	$result = "<pre>\n";
	
	/** Server config builds */
	if (currentUserCan('build_server_configs', $_SESSION['module'])) {
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_', 'AND server_status="active" AND server_installed="yes"');
		$server_count = $fmdb->num_rows;
		$server_results = $fmdb->last_result;
		for ($i=0; $i<$server_count; $i++) {
			if (isset($server_results[$i]->server_client_version) && $server_results[$i]->server_client_version != getOption('client_version', 0, $_SESSION['module'])) {
				$result .= $fm_shared_module_servers->doClientUpgrade($server_results[$i]->server_serial_no);
				$result .= "\n";
			} elseif ($server_results[$i]->server_build_config != 'no') {
				$result .= $fm_shared_module_servers->doBulkServerBuild($server_results[$i]->server_serial_no);
				$result .= "\n";
			}
		}
	}
	
	/** Module mass updates */
	$include_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'ajax' . DIRECTORY_SEPARATOR . 'processPost.php';
	if (file_exists($include_file)) {
		include($include_file);
	}
	
	$result .= "</pre>\n<p class=\"complete\">All updates have been processed.</p>\n";
	unset($_SESSION['display-rebuild-all']);
	echo buildPopup('header', 'Updates Results') . $result . buildPopup('footer', 'OK', array('cancel_button' => 'cancel'));

/** Handle users */
} elseif (is_array($_POST) && array_key_exists('item_type', $_POST) && $_POST['item_type'] == 'users') {
	if (!currentUserCan('manage_users')) returnUnAuth();
	
	if (isset($_POST['item_id'])) {
		$id = sanitize($_POST['item_id']);
	} else returnError();

	include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $fm_name . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class_users.php');
	
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
/** Handle everything else */
} else {
	$include_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'ajax' . DIRECTORY_SEPARATOR . 'processPost.php';
	if (file_exists($include_file)) {
		include($include_file);
	}
}

?>