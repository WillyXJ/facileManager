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
 | fmDNS: Easily manage one or more ISC BIND servers                       |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdns/                             |
 +-------------------------------------------------------------------------+
 | Displays module forms                                                   |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

if (!defined('AJAX')) define('AJAX', true);
require_once('../../../fm-init.php');

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_options.php');
include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_acls.php');
include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_keys.php');

if (is_array($_POST) && array_key_exists('get_option_placeholder', $_POST) && currentUserCan('manage_servers', $_SESSION['module'])) {
	$cfg_data = isset($_POST['option_value']) ? $_POST['option_value'] : null;
	$server_serial_no = isset($_POST['server_serial_no']) ? $_POST['server_serial_no'] : 0;
	$query = "SELECT def_type,def_dropdown FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}functions WHERE def_option = '{$_POST['option_name']}'";
	$fmdb->get_results($query);
	if ($fmdb->num_rows) {
		$result = $fmdb->last_result;
		if (strpos($result[0]->def_type, 'address_match_element') !== false) {
			$available_acls = $fm_dns_acls->buildACLJSON($cfg_data, $server_serial_no);

			echo <<<HTML
					<th width="33%" scope="row"><label for="cfg_data">Option Value</label></th>
					<td width="67%"><input type="hidden" name="cfg_data" class="address_match_element" value="$cfg_data" /><br />
					{$result[0]->def_type}</td>
					<script>
					$(".address_match_element").select2({
						createSearchChoice:function(term, data) { 
							if ($(data).filter(function() { 
								return this.text.localeCompare(term)===0; 
							}).length===0) 
							{return {id:term, text:term};} 
						},
						multiple: true,
						width: '200px',
						tokenSeparators: [",", " ", ";"],
						data: $available_acls
					});
					</script>

HTML;
		} elseif ($result[0]->def_dropdown == 'no') {
			echo <<<HTML
					<th width="33%" scope="row"><label for="cfg_data">Option Value</label></th>
					<td width="67%"><input name="cfg_data" id="cfg_data" type="text" value='$cfg_data' size="40" /><br />
					{$result[0]->def_type}</td>

HTML;
		} else {
			/** Build array of possible values */
			$raw_def_type_array = explode(')', str_replace('(', '', $result[0]->def_type));
			$saved_data = explode(' ', $cfg_data);
			$i = 0;
			$dropdown = null;
			foreach ($raw_def_type_array as $raw_def_type) {
				$def_type_items = null;
				if (strlen(trim($raw_def_type))) {
					$raw_items = explode('|', $raw_def_type);
					foreach ($raw_items as $item) {
						$def_type_items[] = trim($item);
					}
					$dropdown .= buildSelect('cfg_data[]', 'cfg_data', $def_type_items, $saved_data[$i], 1);
				}
				$i++;
			}
			echo <<<HTML
					<th width="33%" scope="row"><label for="cfg_data">Option Value</label></th>
					<td width="67%">$dropdown</td>

HTML;
		}
	}
	exit;
} elseif (is_array($_POST) && array_key_exists('get_available_clones', $_POST) && currentUserCan('manage_zones', $_SESSION['module'])) {
	include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_zones.php');
	echo buildSelect('domain_clone_domain_id', 'domain_clone_domain_id', $fm_dns_zones->availableCloneDomains($_POST['map'], 0), 0);
	exit;
} elseif (is_array($_POST) && array_key_exists('get_available_options', $_POST) && currentUserCan('manage_servers', $_SESSION['module'])) {
	$cfg_type = isset($_POST['cfg_type']) ? sanitize($_POST['cfg_type']) : 'global';
	$server_serial_no = isset($_POST['server_serial_no']) ? $_POST['server_serial_no'] : 0;
	$avail_options_array = $fm_module_options->availableOptions('add', $server_serial_no, $cfg_type);
	echo buildSelect('cfg_name', 'cfg_name', $avail_options_array, sanitize($_POST['cfg_name']), 1, null, false, 'displayOptionPlaceholder()');
	exit;
}

if (is_array($_GET) && array_key_exists('action', $_GET) && $_GET['action'] = 'display-process-all') {
	$update_count = countServerUpdates();
	$update_count += getZoneReloads('count');
	
	echo $update_count;
	exit;
}

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_servers.php');
include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_views.php');
include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_zones.php');
include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_logging.php');
include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_controls.php');
include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_templates.php');

/** Edits */
$checks_array = array('servers' => 'manage_servers',
					'views' => 'manage_servers',
					'acls' => 'manage_servers',
					'keys' => 'manage_servers',
					'options' => 'manage_servers',
					'logging' => 'manage_servers',
					'controls' => 'manage_servers',
					'domains' => 'manage_zones',
					'soa' => 'manage_zones'
				);

if (is_array($_POST) && count($_POST) && currentUserCan(array_unique($checks_array), $_SESSION['module'])) {
	if (!checkUserPostPerms($checks_array, $_POST['item_type'])) {
		returnUnAuth();
		exit;
	}
	
	if (array_key_exists('add_form', $_POST)) {
		$id = isset($_POST['item_id']) ? sanitize($_POST['item_id']) : null;
		$add_new = true;
	} elseif (array_key_exists('item_id', $_POST)) {
		$id = sanitize($_POST['item_id']);
		$item_id = isset($_POST['view_id']) ? sanitize($_POST['view_id']) : null;
		$item_id = isset($_POST['domain_id']) ? sanitize($_POST['domain_id']) : $item_id;
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
		case 'options':
			$post_class = $fm_module_options;
			$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config';
			$prefix = 'cfg_';
			$field = $prefix . 'id';
			$type_map = @isset($_POST['request_uri']['option_type']) ? sanitize($_POST['request_uri']['option_type']) : 'global';
			break;
		case 'domains':
			$post_class = $fm_dns_zones;
			$type_map = isset($_POST['item_sub_type']) ? $_POST['item_sub_type'] : null;
			$action = 'create';
			break;
		case 'logging':
			$post_class = $fm_module_logging;
			$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config';
			$prefix = 'cfg_';
			$field = $prefix . 'id';
			$item_type = $_POST['item_sub_type'] . ' ';
			break;
		case 'soa':
			$post_class = $fm_module_templates;
			$prefix = 'soa_';
			$field = $prefix . 'id';
			break;
		default:
			$post_class = ${"fm_dns_${_POST['item_type']}"};
	}
	
	if ($add_new) {
		if ($_POST['item_type'] == 'logging') {
			$edit_form = $post_class->printForm(null, $action, $_POST['item_sub_type']);
		} else {
			$edit_form = $post_class->printForm(null, $action, $type_map, $id);
		}
	} else {
		basicGet('fm_' . $table, $id, $prefix, $field);
		$results = $fmdb->last_result;
		if (!$fmdb->num_rows) returnError();
		
		$edit_form_data[] = $results[0];
		if ($_POST['item_type'] == 'logging') {
			$edit_form = $post_class->printForm($edit_form_data, 'edit', $_POST['item_sub_type']);
		} else {
			$edit_form = $post_class->printForm($edit_form_data, 'edit', $type_map, $item_id);
		}
	}
	
	echo $edit_form;
} else returnUnAuth();

?>
