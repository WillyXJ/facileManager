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
 | fmSQLPass: Change database user passwords across multiple servers.      |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmsqlpass/                         |
 +-------------------------------------------------------------------------+
 | Processes form posts                                                    |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

if (!defined('AJAX')) define('AJAX', true);
require_once('../../../fm-init.php');

include(ABSPATH . 'fm-modules/fmSQLPass/classes/class_groups.php');
include(ABSPATH . 'fm-modules/fmSQLPass/classes/class_servers.php');

if (!function_exists('returnUnAuth')) {
	include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $fm_name . DIRECTORY_SEPARATOR . 'ajax' . DIRECTORY_SEPARATOR . 'functions.php');
}

/** Handle password changes */
if (is_array($_POST) && array_key_exists('item_type', $_POST) && $_POST['item_type'] == 'set_mysql_password') {
	if (!$allowed_to_manage_passwords) returnUnAuth(true);

	include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class_passwords.php');
	echo $fm_sqlpass_passwords->setPassword();

/** Handle everything else */
} elseif (is_array($_POST) && count($_POST) && ($allowed_to_manage_servers || $allowed_to_manage_backups)) {
	$table = 'sqlpass_' . $_POST['item_type'];
	$item_type = $_POST['item_type'];
	$prefix = substr($item_type, 0, -1) . '_';

	$field = $prefix . 'id';
	$type_map = null;
	$id = sanitize($_POST['item_id']);
	$type = isset($_POST['item_sub_type']) ? sanitize($_POST['item_sub_type']) : null;

	/* Determine which class we need to deal with */
	switch($_POST['item_type']) {
		case 'groups':
			$post_class = $fm_sqlpass_groups;
			break;
		case 'servers':
			$post_class = $fm_module_servers;
			break;
	}

	switch ($_POST['action']) {
		case 'delete':
			if (isset($id)) {
				$delete_status = $post_class->delete(sanitize($id), $type);
				if ($delete_status !== true) {
					echo $delete_status;
				} else {
					echo 'Success';
				}
			}
			break;
		case 'edit':
			if (!empty($_POST) && $allowed_to_manage_servers) {
				if (!$post_class->update($_POST)) {
					$response = '<div class="error"><p>This ' . $item_type . ' could not be updated.</p></div>'. "\n";
					$form_data = $_POST;
				} else header('Location: ' . $GLOBALS['basename']);
			}
			break;
	}

	exit;
}

?>