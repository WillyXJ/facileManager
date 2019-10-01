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
 | fmModule: Brief module description                                      |
 +-------------------------------------------------------------------------+
 | http://URL                                                              |
 +-------------------------------------------------------------------------+
*/

if (!defined('AJAX')) define('AJAX', true);
require_once('../../../fm-init.php');

$class_dir = ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/';
foreach (scandir($class_dir) as $class_file) {
	if (in_array($class_file, array('.', '..'))) continue;
	include($class_dir . $class_file);
}

if (is_array($_GET) && array_key_exists('action', $_GET) && $_GET['action'] == 'display-process-all') {
	$update_count = countServerUpdates();
	
	echo $update_count;
	exit;
}

/** Array based on permissions in capabilities.inc.php */
$checks_array = @array('servers' => 'manage_servers');

$allowed_capabilities = array_unique($checks_array);

if (is_array($_POST) && count($_POST) && currentUserCan($allowed_capabilities, $_SESSION['module'])) {
	if (!checkUserPostPerms($checks_array, $_POST['item_type'])) {
		returnUnAuth();
		exit;
	}
	
	if (array_key_exists('add_form', $_POST)) {
		$id = isset($_POST['item_id']) ? sanitize($_POST['item_id']) : null;
		$add_new = true;
	} elseif (array_key_exists('item_id', $_POST)) {
		$id = sanitize($_POST['item_id']);
		$view_id = isset($_POST['view_id']) ? sanitize($_POST['view_id']) : null;
		$add_new = false;
	} else returnError();
	
	$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . $_POST['item_type'];
	$item_type = $_POST['item_type'];
	$prefix = substr($item_type, 0, -1) . '_';
	$field = $prefix . 'id';
	$type_map = null;
	$action = 'add';
	
	/* Determine which class we need to deal with */
	switch($_POST['item_type']) {
		case 'servers':
			$post_class = $fm_module_servers;
			break;
	}
	
	if ($add_new) {
		$edit_form = $post_class->printForm(null, $action, $type_map, $id);
	} else {
		basicGet('fm_' . $table, $id, $prefix, $field);
		$results = $fmdb->last_result;
		if (!$fmdb->num_rows || $fmdb->sql_errors) returnError($fmdb->last_error);
		
		$edit_form_data[] = $results[0];
		$edit_form = $post_class->printForm($edit_form_data, 'edit', $type_map, $view_id);
	}
	
	echo $edit_form;
} else returnUnAuth();

?>
