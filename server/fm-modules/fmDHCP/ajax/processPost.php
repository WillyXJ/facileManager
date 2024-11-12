<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) The facileManager Team                                    |
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
 | fmDHCP: Easily manage one or more ISC DHCP servers                      |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdhcp/                            |
 +-------------------------------------------------------------------------+
*/

if (!defined('AJAX')) define('AJAX', true);
require_once('../../../fm-init.php');

/** Handle mass updates */
if (is_array($_POST) && array_key_exists('action', $_POST) && $_POST['action'] == 'process-all-updates') {
	return;
}

foreach (glob(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_*.php') as $filename) {
    include_once($filename);
}

$unpriv_message = __('You do not have sufficient privileges.');
/** Array based on permissions in capabilities.inc.php */
$checks_array = @array('servers' => 'manage_servers',
					'hosts' => 'manage_hosts',
					'groups' => 'manage_groups',
					'pools' => 'manage_pools',
					'peers' => 'manage_peers',
					'subnets' => 'manage_networks',
					'shared' => 'manage_networks',
					'leases' => 'manage_leases',
					'purge-leases' => 'manage_leases',
					'options' => 'manage_servers'
				);

$allowed_capabilities = array_unique($checks_array);

if (is_array($_POST) && count($_POST) && currentUserCan($allowed_capabilities, $_SESSION['module'])) {
	if (isset($_POST['page']) && !isset($_POST['item_type'])) {
		$_POST['item_type'] = $_POST['page'];
	}
	if (isset($_POST['config_type']) && !isset($_POST['item_type'])) {
		$_POST['item_type'] = $_POST['config_type'];
	}

	if (!checkUserPostPerms($checks_array, $_POST['item_type'])) {
		returnUnAuth();
	}
	
	if (is_array($_POST) && array_key_exists('item_type', $_POST) && $_POST['item_type'] == 'purge-leases') {
		$delete_lease_response = $fm_dhcp_leases->delete('all||', intval($_POST['server_serial_no']));
		if ($delete_lease_response !== true) {
			echo buildPopup('header', _('Error'));
			echo '<p>' . $delete_lease_response . '</p>';
			echo buildPopup('footer', _('OK'), array('cancel_button' => 'cancel'));
		}
		return;
	}

	$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . $_POST['item_type'];
	$item_type = $_POST['item_type'];
	$prefix = substr($item_type, 0, -1) . '_';

	$field = $prefix . 'id';
	$type_map = null;
	$id = (isset($_POST['item_id'])) ? $_POST['item_id'] : null;
	if (!$id && isset($_POST[$field])) $id = $_POST[$field];
	$server_serial_no = isset($_POST['server_serial_no']) ? $_POST['server_serial_no'] : null;
	$type = isset($_POST['item_sub_type']) ? $_POST['item_sub_type'] : null;
	$field_data = $prefix . 'name';

	/* Determine which class we need to deal with */
	switch($_POST['item_type']) {
		case 'servers':
			$post_class = $fm_module_servers;
			$object = __('server');
			break;
		case 'host':
		case 'hosts':
		case 'group':
		case 'groups':
		case 'pool':
		case 'pools':
		case 'peer':
		case 'peers':
			$item = rtrim($_POST['item_type'], 's') . 's';
			$class_name = "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}$item";
			$post_class = new $class_name();
			$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config';
			$prefix = 'config_';
			$type = $_POST['item_type'];
			$object = rtrim($item, 's');
			$field = $prefix . 'id';
			$field_data = $prefix . 'data';
			break;
		case 'subnet':
		case 'subnets':
		case 'shared':
			$item = rtrim($_POST['item_type'], 's') . 's';
			$class_name = "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}networks";
			$post_class = new $class_name();
			$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config';
			$prefix = 'config_';
			$type = $_POST['item_type'];
			$object = rtrim($item, 's');
			$field = $prefix . 'id';
			$field_data = $prefix . 'data';
			break;
		case 'options':
			$post_class = $fm_module_options;
			$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config';
			$prefix = 'config_';
			$field = 'config_id';
			$field_data = $prefix . 'name';
			$type_map = $_POST['item_type'];
			$item = rtrim($_POST['item_type'], 's') . 's';
			$object = rtrim($item, 's');
			if (!$id) $id = $_POST[$field];
			break;
		default:
			$post_class = ${"fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}{$_POST['item_type']}"};
			$object = substr($item_type, 0, -1);
	}

	switch ($_POST['action']) {
		case 'add':
		case 'create':
			$response = $post_class->add($_POST);
			echo ($response !== true) ? $response : 'Success';
			break;
		case 'delete':
			if (isset($id)) {
				exit(parseAjaxOutput($post_class->delete($id, $server_serial_no, $type)));
			}
			break;
		case 'edit':
		case 'update':
			if (isset($_POST['item_status'])) {
				if (!updateStatus('fm_' . $table, $id, $prefix, $_POST['item_status'], $prefix . 'id')) {
					exit(sprintf(__('This item could not be set to %s.') . "\n", $_POST['item_status']));
				} else {
					setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
					list($tmp_type, $tmp_name, $tmp_parent_id) = getNameFromID($id, 'fm_' . $table, $prefix, $field, array($prefix . 'type', $field_data, $prefix . 'parent_id'));
					$tmp_parent_name = (isset($tmp_parent_id)) ? getNameFromID($tmp_parent_id, 'fm_' . $table, $prefix, $prefix . 'id', $prefix . 'data') : null;
					addLogEntry(sprintf(__('Set %s %s %s (%s) status to %s.'), $tmp_parent_name, $tmp_type, $object, $tmp_name, $_POST['item_status']));
					exit('Success');
				}
			} else {
				$response = $post_class->update($_POST);
				echo ($response !== true) ? $response : 'Success';
			}
			break;
		case 'bulk':
			if (array_key_exists('bulk_action', $_POST) && in_array($_POST['bulk_action'], array('enable', 'disable', 'delete'))) {
				switch($_POST['bulk_action']) {
					case 'enable':
					case 'disable':
					case 'delete':
						$status = $_POST['bulk_action'] . 'd';
						if ($status == 'enabled') $status = 'active';
						foreach ((array) $_POST['item_id'] as $id) {
							$tmp_name = getNameFromID($id, 'fm_' . $table, $prefix, $field, $prefix . 'name');
							if (updateStatus('fm_' . $table, $id, $prefix, $status, $prefix . 'id')) {
								setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
								addLogEntry(sprintf(__('Set %s (%s) status to %s.'), $object, $tmp_name, $status));
							}
						}

						echo buildPopup('header', __('Bulk Action Results'));
						echo '<p>' . sprintf('%s action is complete.', ucfirst($_POST['bulk_action'])) . '</p>';
						echo $fmdb->last_error;
						echo buildPopup('footer', _('OK'), array('cancel_button' => 'cancel'), $_POST['rel_url']);
						break;
				}
			}
			break;
	}

	exit;
}

echo $unpriv_message;
