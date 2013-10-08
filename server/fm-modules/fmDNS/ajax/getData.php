<?php

/**
 * Processes form posts
 *
 * @author		Jon LaBass
 * @version		$Id:$
 * @copyright	2013
 *
 */

if (!defined('AJAX')) define('AJAX', true);
require_once('../../../fm-init.php');

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_options.php');

if (is_array($_POST) && array_key_exists('get_option_placeholder', $_POST) && $allowed_to_manage_servers) {
	$query = "SELECT def_type FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}functions WHERE def_option = '{$_POST['option_name']}'";
	$fmdb->get_results($query);
	if ($fmdb->num_rows) {
		$result = $fmdb->last_result;
		echo $result[0]->def_type;
	}
	exit;
}

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_servers.php');
include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_views.php');
include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_acls.php');
include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_keys.php');
include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_zones.php');
include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_logging.php');

/** Edits */
if (is_array($_POST) && count($_POST) && $allowed_to_manage_zones) {
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
		case 'views':
			$post_class = $fm_dns_views;
			break;
		case 'acls':
			$post_class = $fm_dns_acls;
			break;
		case 'keys':
			$post_class = $fm_dns_keys;
			break;
		case 'options':
			$post_class = $fm_dns_options;
			$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config';
			$prefix = 'cfg_';
			$field = $prefix . 'id';
			$type_map = 'global';
			break;
		case 'domains':
			$post_class = $fm_dns_zones;
			$type_map = $_POST['item_sub_type'];
			$action = 'create';
			break;
		case 'logging':
			$post_class = $fm_dns_logging;
			$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config';
			$prefix = 'cfg_';
			$field = $prefix . 'id';
			$item_type = $_POST['item_sub_type'] . ' ';
			break;
	}
	
//	if ($post_class != $fm_dns_zones && !$super_admin) returnUnAuth();
	
	if ($add_new) {
		$edit_form = '<h2>Add ' . substr(ucfirst($item_type), 0, -1) . '</h2>' . "\n";
		if ($_POST['item_type'] == 'logging') {
			$edit_form .= $post_class->printForm(null, $action, $_POST['item_sub_type']);
		} else {
			$edit_form .= $post_class->printForm(null, $action, $type_map, $id);
		}
	} else {
		$edit_form = '<h2>Edit ' . substr(ucfirst($item_type), 0, -1) . '</h2>' . "\n";
		basicGet('fm_' . $table, $id, $prefix, $field);
		$results = $fmdb->last_result;
		if (!$fmdb->num_rows) returnError();
		
		$edit_form_data[] = $results[0];
		if ($_POST['item_type'] == 'logging') {
			$edit_form .= $post_class->printForm($edit_form_data, 'edit', $_POST['item_sub_type']);
		} else {
			$edit_form .= $post_class->printForm($edit_form_data, 'edit', $type_map, $view_id);
		}
	}
	
	echo $edit_form;
}

?>
