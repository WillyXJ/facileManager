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

$class_dir = ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/';
foreach (scandir($class_dir) as $class_file) {
	if (in_array($class_file, array('.', '..'))) continue;
	include($class_dir . $class_file);
}

if (is_array($_POST) && array_key_exists('get_option_placeholder', $_POST) && currentUserCan('manage_servers', $_SESSION['module'])) {
	$cfg_data = isset($_POST['option_value']) ? $_POST['option_value'] : null;
	$server_serial_no = isset($_POST['server_serial_no']) ? $_POST['server_serial_no'] : 0;
	$query = "SELECT def_type,def_dropdown,def_minimum_version FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}functions WHERE def_option = '{$_POST['option_name']}'";
	$result = $fmdb->get_results($query);
	if ($fmdb->num_rows) {
		if (strpos($result[0]->def_type, 'address_match_element_broken') !== false) {
			$available_acls = $fm_dns_acls->buildACLJSON($cfg_data, $server_serial_no);

			printf('<th width="33&#37;" scope="row"><label for="cfg_data">%s</label></th>
					<td width="67&#37;"><input type="hidden" name="cfg_data" class="address_match_element" value="%s" /><br />
					%s
					<script>
					$(".address_match_element").select2({
						createSearchChoice:function(term, data) { 
							if ($(data).filter(function() { 
								return this.text.localeCompare(term)===0; 
							}).length===0) 
							{return {id:term, text:term};} 
						},
						multiple: true,
						width: "200px",
						tokenSeparators: [",", " ", ";"],
						data: %s
					});
					</script>', __('Option Value'), $cfg_data, $result[0]->def_type, $available_acls);
		} elseif ($result[0]->def_dropdown == 'no') {
			printf('<th width="33&#37;" scope="row"><label for="config_data">%s</label></th>
					<td width="67&#37;"><input name="config_data" id="config_data" type="text" value="%s" size="40" /><br />
					%s', __('Option Value'), str_replace(array('"', "'"), '', $cfg_data), $result[0]->def_type);
		} else {
			/** Build array of possible values */
			$dropdown = $fm_module_options->populateDefTypeDropdown($result[0]->def_type, $cfg_data);
			printf('<th width="33&#37;" scope="row"><label for="config_data">%s</label></th>
					<td width="67&#37;">%s', __('Option Value'), $dropdown);
		}
		if ($result[0]->def_minimum_version) printf('<br /><span class="note">%s</span></td>', sprintf(__('This option requires DHCPD %s or later.'), $result[0]->def_minimum_version));
	}
	exit;
} elseif (is_array($_GET) && array_key_exists('action', $_GET) && $_GET['action'] == 'display-process-all') {
	$update_count = countServerUpdates();
	
	echo $update_count;
	exit;
} elseif (is_array($_POST) && array_key_exists('get_ap_status', $_POST)) {
	if (!class_exists('fm_module_servers')) {
		include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_servers.php');
	}
	$ap_status = $fm_module_servers->getAPStats(sanitize($_POST['ap_id']), 'status -o web');
	if (is_int($ap_status) && $ap_status == true) {
		exit(str_replace(_('OK'), __('AP is up'), $__FM_CONFIG['module']['icons']['ok']));
	} elseif ($ap_status == false) {
		exit(str_replace(_('Failed'), __('AP is down'), $__FM_CONFIG['module']['icons']['fail']));
	}
	
	exit(str_replace(_('OK'), __('Cannot determine the state'), $__FM_CONFIG['module']['icons']['notice']));
} elseif (is_array($_GET) && array_key_exists('action', $_GET) && $_GET['action'] == 'get-dashboard') {
	exit(buildModuleDashboard());
}

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
			if (isset($_POST['item_sub_type']) && sanitize($_POST['item_sub_type']) == 'groups') {
				$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'server_groups';
				$prefix = 'group_';
			}
			break;
		case 'wlans':
			$class_name = "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}{$_POST['item_type']}";
			$post_class = new $class_name();
			$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config';
			$prefix = 'config_';
			$field = 'config_id';
			$field_data = $prefix . 'data';
			$type_map = $_POST['item_type'];
			break;
		case 'wlan_users':
			$post_class = $fm_wifi_wlan_users;
			break;
		case 'acls':
			$post_class = $fm_wifi_acls;
			break;
		case 'options':
			$post_class = $fm_module_options;
			$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config';
			$prefix = 'config_';
			$field = 'config_id';
			$field_data = $prefix . 'data';
			$type_map = $_POST['item_type'];
			$type_map = @isset($_POST['request_uri']['option_type']) ? sanitize($_POST['request_uri']['option_type']) : 'global';
			break;
	}
	
	if ($add_new) {
		if (in_array($_POST['item_type'], array('servers'))) {
			$edit_form = $post_class->printForm(null, $action, sanitize($_POST['item_sub_type']));
		} else {
			$edit_form = $post_class->printForm(null, $action, $type_map, $id);
		}
	} else {
		basicGet('fm_' . $table, $id, $prefix, $prefix . 'id');
		if (!$fmdb->num_rows || $fmdb->sql_errors) returnError($fmdb->last_error);
		
		$edit_form_data[] = $fmdb->last_result[0];
		if (in_array($_POST['item_type'], array('servers'))) {
			$edit_form = $post_class->printForm($edit_form_data, 'edit', sanitize($_POST['item_sub_type']));
		} else {
			$edit_form = $post_class->printForm($edit_form_data, 'edit', $type_map);
		}
	}
	
	echo $edit_form;
} else returnUnAuth();

?>
