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
 | fmWifi: Easily manage one or more access points                         |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmwifi/                            |
 +-------------------------------------------------------------------------+
*/

if (!defined('AJAX')) define('AJAX', true);
require_once('../../../fm-init.php');

/** Handle mass updates */
if (is_array($_POST) && array_key_exists('action', $_POST) && $_POST['action'] == 'process-all-updates') {
	return;
}

$class_dir = ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/';
foreach (scandir($class_dir) as $class_file) {
	if (in_array($class_file, array('.', '..'))) continue;
	include($class_dir . $class_file);
}

$unpriv_message = __('You do not have sufficient privileges.');
/** Array based on permissions in capabilities.inc.php */
$checks_array = @array('servers' => 'manage_servers',
					'wlans' => 'manage_wlans',
					'wlan_users' => 'manage_wlan_users',
					'acls' => 'manage_wlan_wlan_users',
					'options' => 'manage_servers'
				);

$allowed_capabilities = array_unique($checks_array);

if (is_array($_POST) && count($_POST) && currentUserCan($allowed_capabilities, $_SESSION['module'])) {
	if (!checkUserPostPerms($checks_array, $_POST['item_type'])) {
		returnUnAuth();
		exit;
	}
	
	$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . $_POST['item_type'];
	$item_type = $_POST['item_type'];
	$prefix = substr($item_type, 0, -1) . '_';

	$field = $prefix . 'id';
	$type_map = null;
	$id = sanitize($_POST['item_id']);
	$server_serial_no = isset($_POST['server_serial_no']) ? sanitize($_POST['server_serial_no']) : null;
	$type = array_key_exists('item_sub_type', $_POST) ? sanitize($_POST['item_sub_type']) : null;

	/* Determine which class we need to deal with */
	switch($_POST['item_type']) {
		case 'servers':
			$post_class = $fm_module_servers;
			$object = __('server');
			if (isset($_POST['url_var_type']) && sanitize($_POST['url_var_type']) == 'groups') {
				$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'server_groups';
				$prefix = 'group_';
				$object = __('server group');
			}
			$field_data = $prefix . 'name';
			$server_serial_no = $type;
			break;
		case 'wlans':
			$item = rtrim($_POST['item_type'], 's') . 's';
			$class_name = "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}$item";
			$post_class = new $class_name();
			$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config';
			$prefix = 'config_';
			$type = $_POST['item_type'];
			$object = rtrim($item, 's');
			$field = $prefix . 'id';
			$field_data = $prefix . 'data';
			$name = 'data';
			break;
		case 'wlan_users':
			$post_class = ${"fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}{$_POST['item_type']}"};
			$field_data = $prefix . 'login';
			$item = rtrim($_POST['item_type'], 's') . 's';
			$object = rtrim($item, 's');
			$name = 'login';
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
			break;
		case 'acls':
			$post_class = ${"fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}{$_POST['item_type']}"};
			$object = substr($item_type, 0, -1);
			$field_data = $prefix . 'name';
			$name = 'mac';
			break;
		default:
			$post_class = ${"fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}{$_POST['item_type']}"};
			$object = substr($item_type, 0, -1);
			$name = 'name';
			$field_data = $prefix . 'name';
	}

	switch ($_POST['action']) {
		case 'add':
			if (!empty($_POST[$table . '_name'])) {
				if (!$post_class->add($_POST)) {
					printf('<div class="error"><p>%s</p></div>' . "\n", __('This item could not be added.'));
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
			if (isset($_POST['item_status'])) {
				if (!updateStatus('fm_' . $table, $id, $prefix, sanitize($_POST['item_status']), $prefix . 'id')) {
					exit(sprintf(__('This item could not be set to %s.') . "\n", $_POST['item_status']));
				} else {
					setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
					$tmp_name = getNameFromID($id, 'fm_' . $table, $prefix, $prefix . 'id', $field_data);
					addLogEntry(sprintf(__('Set %s (%s) status to %s.'), $object, $tmp_name, sanitize($_POST['item_status'])));
					exit('Success');
				}
			}
			break;
		case 'bulk':
			if (array_key_exists('bulk_action', $_POST) && in_array($_POST['bulk_action'], array('enable', 'disable', 'delete'))) {
				switch($_POST['bulk_action']) {
					case 'enable':
					case 'disable':
					case 'delete':
						$status = sanitize($_POST['bulk_action']) . 'd';
						if ($status == 'enabled') $status = 'active';
						foreach ((array) $_POST['item_id'] as $id) {
							$tmp_name = getNameFromID($id, 'fm_' . $table, $prefix, $field, $prefix . $name);
							if (updateStatus('fm_' . $table, $id, $prefix, $status, $prefix . 'id')) {
								setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
								addLogEntry(sprintf(__('Set %s (%s) status to %s.'), $object, $tmp_name, $status));
							}
						}

						echo buildPopup('header', __('Bulk Action Results'));
						echo '<p>' . sprintf('%s action is complete.', ucfirst($_POST['bulk_action'])) . '</p>';
						echo $fmdb->last_error;
						echo buildPopup('footer', _('OK'), array('cancel_button' => 'cancel'), sanitize($_POST['rel_url']));
						break;
				}
			}
			break;
		case 'block-wifi-client':
			echo $fm_wifi_acls->blockClient(sanitize($_POST['ssid']), sanitize($_POST['item_id']));
			break;
	}

	exit;
}

echo $unpriv_message;

?>