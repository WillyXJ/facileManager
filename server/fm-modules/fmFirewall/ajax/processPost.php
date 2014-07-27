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
 | fmFirewall: Easily manage one or more software firewalls                |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmfirewall/                        |
 +-------------------------------------------------------------------------+
 | Processes form posts                                                    |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

if (!defined('AJAX')) define('AJAX', true);
require_once('../../../fm-init.php');

$class_dir = ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/';
foreach (scandir($class_dir) as $class_file) {
	if (in_array($class_file, array('.', '..'))) continue;
	include($class_dir . $class_file);
}

$unpriv_message = 'You do not have sufficient privileges.';
$checks_array = @array('servers' => 'manage_servers',
					'services' => 'manage_services',
					'objects' => 'manage_objects',
					'groups' => 'manage_' . $_POST['item_sub_type'] . 's',
					'time' => 'manage_time',
					'policies' => 'manage_policies'
				);
$allowed_capabilities = array_unique($checks_array);

if (is_array($_POST) && count($_POST) && currentUserCan($allowed_capabilities, $_SESSION['module'])) {
	if (!checkUserPostPerms($checks_array, $_POST['item_type'])) {
		echo $unpriv_message;
		exit;
	}
	
	$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . $_POST['item_type'];
	$item_type = $_POST['item_type'];
	$prefix = substr($item_type, 0, -1) . '_';

	$field = $prefix . 'id';
	$type_map = null;
	$id = sanitize($_POST['item_id']);
	$server_serial_no = isset($_POST['server_serial_no']) ? sanitize($_POST['server_serial_no']) : null;
	$type = isset($_POST['item_sub_type']) ? sanitize($_POST['item_sub_type']) : null;

	/* Determine which class we need to deal with */
	switch($_POST['item_type']) {
		case 'servers':
			$post_class = $fm_module_servers;
			break;
		case 'services':
			$post_class = $fm_module_services;
			break;
		case 'objects':
			$post_class = $fm_module_objects;
			break;
		case 'groups':
			$post_class = $fm_module_groups;
			break;
		case 'time':
			$post_class = $fm_module_time;
			$prefix = 'time_';
			$field = $prefix . 'id';
			$item_type .= ' ';
			break;
		case 'policies':
			$post_class = $fm_module_policies;
			$prefix = 'policy_';
			$field = $prefix . 'id';
			$item_type = 'policys';
			break;
	}

	switch ($_POST['action']) {
		case 'add':
			if (!empty($_POST[$table . '_name'])) {
				if (!$post_class->add($_POST)) {
					echo '<div class="error"><p>This ' . $table . ' could not be added.</p></div>'. "\n";
					$form_data = $_POST;
				} else exit('Success');
			}
			break;
		case 'delete':
			if (isset($id)) {
				exit(parseAjaxOutput($post_class->delete(sanitize($id), $server_serial_no, $type)));
			}
			break;
		case 'edit':
			if (!empty($_POST)) {
				if (!$post_class->update($_POST)) {
					$response = '<div class="error"><p>This ' . $table . ' could not be updated.</p></div>'. "\n";
					$form_data = $_POST;
				} else header('Location: ' . $GLOBALS['basename']);
			}
			if (isset($_GET['status'])) {
				if (!updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'views', $_GET['id'], 'view_', $_GET['status'], 'view_id')) {
					$response = '<div class="error"><p>This ' . $table . ' could not be '. $_GET['status'] .'.</p></div>'. "\n";
				} else header('Location: ' . $GLOBALS['basename']);
			}
			if (!isset($_POST['id']) && isset($_GET['id'])) {
				basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'views', $_GET['id'], 'view_', 'view_id');
				if (!$fmdb->num_rows) {
					$response = '<div class="error"><p>This ' . $table . ' is not found in the database.</p></div>'. "\n";
				} else {
					$form_data = $fmdb->last_result;
				}
			}
			break;
		case 'update_sort':
			if (!empty($_POST)) {
				$result = $post_class->update($_POST);
				if ($result !== true) {
					exit($result);
				}
				exit('Success');
			}
			exit('The sort order could not be updated due to an invalid request.');
	}

	exit;
}

echo $unpriv_message;

?>