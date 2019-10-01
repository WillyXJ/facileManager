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
 | Processes form posts                                                    |
 +-------------------------------------------------------------------------+
*/

define('AJAX', true);
require_once('../../../fm-init.php');

include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $fm_name . DIRECTORY_SEPARATOR . 'ajax' . DIRECTORY_SEPARATOR . 'functions.php');

/** Handle user password change */
if (is_array($_POST) && array_key_exists('user_id', $_POST)) {
	include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $fm_name . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class_users.php');
	$update_status = $fm_users->updateUser($_POST);
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
		echo ($save_result !== true) ? displayResponseClose($save_result) : 'Success';
	} else {
		$save_result = $fm_settings->save();
		echo ($save_result !== true) ? displayResponseClose($save_result) : sprintf("<p>%s</p>\n", _('These settings have been saved.'));
	}

/** Handle module settings */
} elseif (is_array($_POST) && array_key_exists('item_type', $_POST) && $_POST['item_type'] == 'module_settings') {
	if (!currentUserCan('manage_settings', $_SESSION['module'])) returnUnAuth(false);

	include_once(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class_settings.php');
	$save_result = $fm_module_settings->save();
	echo ($save_result !== true) ? displayResponseClose($save_result) : sprintf("<p>%s</p>\n", _('These settings have been saved.'));

/** Handle bulk actions */
} elseif (is_array($_POST) && array_key_exists('action', $_POST) && $_POST['action'] == 'bulk' &&
	array_key_exists('bulk_action', $_POST) && in_array($_POST['bulk_action'], array('upgrade', 'build config', 'activate', 'deactivate', 'update'))) {
	switch($_POST['bulk_action']) {
		/** Handle module activate/deactivate */
		case 'activate':
		case 'deactivate':
		case 'uninstall':
		case 'update':
			/** Check permissions */
			if (!currentUserCan('manage_modules')) {
				returnUnAuth();
			}
			
			include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $fm_name . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class_tools.php');

			$bulk_class = $fm_tools;
			$bulk_function = 'manageModule';
			$page = _('Modules');
			
			break;
		/** Handle client upgrades */
		case 'upgrade':
			/** Check permissions */
			if (!currentUserCan('manage_servers', $_SESSION['module'])) {
				returnUnAuth();
			}
			
			if (!class_exists('fm_module_servers')) {
				include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_servers.php');
			}
			$bulk_class = $fm_module_servers;
			$bulk_function = 'doClientUpgrade';
			$page = _('Servers');
			break;
		/** Handle client server config builds */
		case 'build config':
			/** Check permissions */
			if (!currentUserCan('build_server_configs', $_SESSION['module'])) {
				returnUnAuth();
			}
			
			if (!class_exists('fm_module_servers')) {
				include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_servers.php');
			}
			$bulk_class = $fm_module_servers;
			$bulk_function = 'doBulkServerBuild';
			$page = _('Servers');
			break;
	}
	$output = null;
	if (is_array($_POST['item_id'])) {
		foreach ($_POST['item_id'] as $id) {
			$result = $bulk_class->$bulk_function($id, $_POST['bulk_action']);
			if (!is_int($result)) $output .= $result . "\n";
		}
	}
	if (isset($output)) $output = "<pre>$output</pre>\n";
	$output .= "<p class=\"complete\">" . _('Complete') . '.</p>';
	echo buildPopup('header', ucwords($_POST['bulk_action']) . ' Results') . $output . buildPopup('footer', _('OK'), array('cancel_button' => 'cancel'), getMenuURL($page));

/** Handle mass updates */
} elseif (is_array($_POST) && array_key_exists('action', $_POST) && $_POST['action'] == 'process-all-updates') {
	$result = "<pre>\n";
	
	/** Server config builds */
	if (currentUserCan('build_server_configs', $_SESSION['module'])) {
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_', 'AND server_status="active" AND server_installed="yes"');
		$server_count = $fmdb->num_rows;
		$server_results = $fmdb->last_result;
		if (!class_exists('fm_module_servers')) {
			include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_servers.php');
		}
		for ($i=0; $i<$server_count; $i++) {
			if (isset($server_results[$i]->server_client_version) && $server_results[$i]->server_client_version != getOption('client_version', 0, $_SESSION['module'])) {
				$result .= $fm_module_servers->doClientUpgrade($server_results[$i]->server_serial_no);
				$result .= "\n";
			} elseif ($server_results[$i]->server_build_config != 'no') {
				$result .= $fm_module_servers->doBulkServerBuild($server_results[$i]->server_serial_no);
				$result .= "\n";
			}
		}
	}
	
	/** Module mass updates */
	$include_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'ajax' . DIRECTORY_SEPARATOR . 'processPost.php';
	if (file_exists($include_file)) {
		include($include_file);
	}
	
	$result .= "</pre>\n<p class=\"complete\">" . _('All updates have been processed.') . "</p>\n";
	session_start();
	unset($_SESSION['display-rebuild-all']);
	session_write_close();
	echo buildPopup('header', _('Updates Results')) . $result . buildPopup('footer', _('OK'), array('cancel_button' => 'cancel'));

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
				$delete_status = $fm_users->delete(sanitize($id), substr(sanitize($_POST['item_sub_type']), 0, -1));
				if ($delete_status !== true) {
					echo $delete_status;
				} else {
					exit('Success');
				}
			}
			break;
		case 'edit':
			if (isset($_POST['item_status'])) {
				if ((!currentUserCan('do_everything') && userCan($id, 'do_everything')) || $id == getDefaultAdminID()) {
					exit(_('You do not have permission to modify the status of this user.'));
				}
				if (!updateStatus('fm_users', $id, 'user_', sanitize($_POST['item_status']), 'user_id')) {
					exit(sprintf(_('This user could not be set to %s.') . "\n", $_POST['item_status']));
				} else {
					$tmp_name = getNameFromID($id, 'fm_users', 'user_', 'user_id', 'user_login');
					addLogEntry(sprintf(_('Set user (%s) status to %s.'), $tmp_name, sanitize($_POST['item_status'])));
					exit('Success');
				}
			}
			break;
	}
/** Handle everything else */
} elseif (isset($_SESSION['module']) && $_SESSION['module'] != $fm_name) {
	$include_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'ajax' . DIRECTORY_SEPARATOR . 'processPost.php';
	if (file_exists($include_file)) {
		include($include_file);
	}
}

?>