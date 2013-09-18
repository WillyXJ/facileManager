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

include(ABSPATH . 'fm-modules/fmDNS/classes/class_servers.php');
include(ABSPATH . 'fm-modules/fmDNS/classes/class_views.php');
include(ABSPATH . 'fm-modules/fmDNS/classes/class_acls.php');
include(ABSPATH . 'fm-modules/fmDNS/classes/class_keys.php');
include(ABSPATH . 'fm-modules/fmDNS/classes/class_options.php');
include(ABSPATH . 'fm-modules/fmDNS/classes/class_zones.php');
include(ABSPATH . 'fm-modules/fmDNS/classes/class_logging.php');

if (is_array($_POST) && count($_POST) && $allowed_to_manage_zones) {
	$table = 'dns_' . $_POST['item_type'];
	$item_type = $_POST['item_type'];
	$prefix = substr($item_type, 0, -1) . '_';

//	$table = $_POST['item_type'];
//	$item_type = substr($table, 0, -1);
//	$prefix = substr($table, 0, -1) . '_';
	$field = $prefix . 'id';
	$type_map = null;
	$id = sanitize($_POST['item_id']);
	$server_serial_no = isset($_POST['server_serial_no']) ? sanitize($_POST['server_serial_no']) : null;
	$type = isset($_POST['item_sub_type']) ? sanitize($_POST['item_sub_type']) : null;

	/* Determine which class we need to deal with */
	switch($_POST['item_type']) {
		case 'servers':
			$post_class = $fm_dns_servers;
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
			$table = 'config';
			$prefix = 'cfg_';
			$field = $prefix . 'id';
			$type_map = 'global';
			$item_type = 'option';
			break;
		case 'domains':
			$post_class = $fm_dns_zones;
			$type_map = isset($_POST['item_sub_type']) ? $_POST['item_sub_type'] : null;
			$action = 'create';
			break;
		case 'logging':
			$post_class = $fm_dns_logging;
			$table = 'config';
			$prefix = 'cfg_';
			$field = $prefix . 'id';
			if (isset($_POST['item_sub_type'])) $item_type = $_POST['item_sub_type'] . ' ';
			$type = sanitize($_POST['log_type']);
			break;
	}

	switch ($_POST['action']) {
		case 'add':
			if (!empty($_POST[$table . '_name'])) {
				if (!$post_class->add($_POST)) {
					echo '<div class="error"><p>This ' . $table . ' could not be added.</p></div>'. "\n";
					$form_data = $_POST;
				} else echo 'Success';
			}
			break;
		case 'delete':
			if (isset($id)) {
				$delete_status = $post_class->delete(sanitize($id), $server_serial_no, $type);
				if ($delete_status !== true) {
					echo $delete_status;
				} else {
					echo 'Success';
				}
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
				if (!updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', $_GET['id'], 'view_', $_GET['status'], 'view_id')) {
					$response = '<div class="error"><p>This ' . $table . ' could not be '. $_GET['status'] .'.</p></div>'. "\n";
				} else header('Location: ' . $GLOBALS['basename']);
			}
			if (!isset($_POST['id']) && isset($_GET['id'])) {
				basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', $_GET['id'], 'view_', 'view_id');
				if (!$fmdb->num_rows) {
					$response = '<div class="error"><p>This ' . $table . ' is not found in the database.</p></div>'. "\n";
				} else {
					$form_data = $fmdb->last_result;
				}
			}
	}

//if (!empty($_POST['view_name'])) {
//echo '<pre>';
//print_r($_POST);
//echo '</pre>';

	exit;
//}
}

echo 'You do not have sufficient privileges.';

?>