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
 | fmDNS: Easily manage one or more ISC BIND servers                       |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdns/                             |
 +-------------------------------------------------------------------------+
 | Displays module forms                                                   |
 +-------------------------------------------------------------------------+
*/

if (!defined('AJAX')) define('AJAX', true);
require_once('../../../fm-init.php');

foreach (glob(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_*.php') as $filename) {
    include_once($filename);
}

if (is_array($_POST) && array_key_exists('get_option_placeholder', $_POST)) {
	$cfg_data = isset($_POST['option_value']) ? sanitize($_POST['option_value']) : '';
	if (isset($_POST['option_name'])) $_POST['option_name'] = sanitize($_POST['option_name']);
	$server_serial_no = isset($_POST['server_serial_no']) ? intval($_POST['server_serial_no']) : 0;
	$query = "SELECT def_option,def_type,def_multiple_values,def_dropdown,def_minimum_version FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}functions WHERE def_option = '{$_POST['option_name']}'";
	if (array_key_exists('domain_id', $_POST)) {
		$query .= " AND def_clause_support LIKE '%Z%'";
	} elseif (array_key_exists('view_id', $_POST)) {
		$query .= " AND def_clause_support LIKE '%V%'";
	} else {
		$query .= " AND def_clause_support LIKE '%O%'";
	}
	$result = $fmdb->get_results($query);
	if ($fmdb->num_rows) {
		if (strpos($result[0]->def_type, 'address_match_element') !== false) {
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
		} elseif (strpos($result[0]->def_type, 'domain_select') !== false) {
			$temp_addl_zones = array();

			/** Check for non-hosted zones */
			foreach (explode(',', $cfg_data) as $temp_zone_id) {
				if (is_numeric($temp_zone_id)) continue;

				$temp_addl_zones[] = array($temp_zone_id, $temp_zone_id);
			}
			$available_domains = $fm_dns_zones->buildZoneJSON('all', null, $temp_addl_zones);

			printf('<th width="33&#37;" scope="row"><label for="cfg_data">%s</label></th>
					<td width="67&#37;"><input type="hidden" name="cfg_data" class="domain_select" value="%s" /><br />
					%s
					<script>
					$(".domain_select").select2({
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
					</script>', __('Option Value'), $cfg_data, $result[0]->def_type, $available_domains);
		} elseif (strpos($result[0]->def_type, 'rrset_order_spec') !== false) {
			$cfg_data = ($cfg_data) ? explode(' ', $cfg_data) : array(null, null, null, null);
			
			$available_classes = buildSelect('cfg_data[]', 'cfg_data', array_merge(array('any'), enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', 'record_class')), $cfg_data[0]);
			$available_types = buildSelect('cfg_data[]', 'cfg_data', array_merge(array('any'), enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', 'record_type')), $cfg_data[1]);
			$available_domains = buildSelect('cfg_data[]', 'cfg_data_zones', $fm_dns_zones->availableZones('no-templates', null, 'all', 'all'), $cfg_data[2]);
			$available_orders = $fm_module_options->populateDefTypeDropdown('( random | cyclic | fixed )', $cfg_data[3]);

			printf('<th width="33&#37;" scope="row"><label for="cfg_data">%s</label></th>
					<td width="67&#37;">class %s<br />type %s<br />name %s<br />order %s
					<script>
					$("#cfg_data_zones").select2({
						width: "250px",
					});
					</script>',
					__('Option Value'), $available_classes, $available_types, $available_domains, $available_orders);
		} elseif (in_array($result[0]->def_option, array('primaries', 'also-notify'))) {
			$cfg_data_array = explode('{', rtrim($cfg_data, '}'));
			$cfg_data_port = $cfg_data_dscp = null;
			if (count($cfg_data_array) > 1) {
				$cfg_data = trim($cfg_data_array[1]);
				$cfg_data_array = explode(' ', $cfg_data_array[0]);
				$port_key = array_search('port', $cfg_data_array);
				$cfg_data_port = ($port_key !== false && isset($cfg_data_array[$port_key + 1])) ? $cfg_data_array[$port_key + 1] : null;
				$dscp_key = array_search('dscp', $cfg_data_array);
				$cfg_data_dscp = ($dscp_key !== false && isset($cfg_data_array[$dscp_key + 1])) ? $cfg_data_array[$dscp_key + 1] : null;
				unset($port_key, $dscp_key);
			}
			$cfg_data = str_replace(array('{', '}'), '', $cfg_data);
			// This section would be to allow keys to be selected, but an IP also needs to be defined
			// $available_acls = $fm_dns_acls->buildACLJSON($cfg_data, $server_serial_no);
			// $available_masters = $fm_dns_masters->getMasterList($server_serial_no, 'all');
			// $available_masters = array_merge($available_masters, $fm_dns_acls->getACLList($server_serial_no, 'tsig-keys'));
			$available_masters = $fm_dns_masters->buildMasterJSON($cfg_data, $server_serial_no);

			printf('<th width="33&#37;" scope="row"><label for="cfg_data">%s</label></th>
					<td width="67&#37;">
					<label for="cfg_data_port"><b>Port</b></label> <a href="#" class="tooltip-top" data-tooltip="%s"><i class="fa fa-question-circle"></i></a> <input type="text" id="cfg_data_port" name="cfg_data_port" value="%s" maxlength="5" style="width: 5em;" onkeydown="return validateNumber(event)" />
					<label for="cfg_data_dscp"><b>DSCP</b></label> <a href="#" class="tooltip-top" data-tooltip="%s"><i class="fa fa-question-circle"></i></a> <input type="text" id="cfg_data_dscp" name="cfg_data_dscp" value="%s" maxlength="2" style="width: 5em;" onkeydown="return validateNumber(event)" /><br />
					<input type="hidden" name="cfg_data" class="address_match_element" value="%s" /><br />
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
						tokenSeparators: [",", ";"],
						data: %s
					});
					</script>', __('Option Value'), sprintf(__('This option requires BIND %s or later.'), '9.9'), $cfg_data_port, sprintf(__('This option requires BIND %s or later.'), '9.9'), $cfg_data_dscp, $cfg_data, $result[0]->def_type, $available_masters);
		} elseif (strpos($result[0]->def_type, 'key_id') !== false) {
			$available_items = $fm_dns_acls->buildACLJSON($cfg_data, $server_serial_no, 'tsig-keys');
			$multiple = ($result[0]->def_multiple_values == 'yes') ? 'true' : 'false';

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
						multiple: %s,
						width: "200px",
						tokenSeparators: [",", " ", ";"],
						data: %s
					});
					</script>', __('Option Value'), $cfg_data, $result[0]->def_type, $multiple, $available_items);
		} elseif (in_array($result[0]->def_option, array('listen-on', 'listen-on-v6'))) {
			$cfg_add_match_elem = explode('{', rtrim($cfg_data, '}'));
			$listen_params = array('port', 'proxy', 'http', 'tls');
			$cfg_data_array = array_fill_keys($listen_params, null);
			if (count($cfg_add_match_elem) > 1) {
				$cfg_data = trim($cfg_add_match_elem[1]);
				$cfg_add_match_elem = explode(' ', $cfg_add_match_elem[0]);
				foreach ($listen_params as $param) {
					$key = array_search($param, $cfg_add_match_elem);
					if ($key !== false) {
						$cfg_data_array[$param] = $cfg_add_match_elem[$key + 1];
					}
				}
			}
			$cfg_data = str_replace(array('{', '}', '; ', ';'), array('', '', ',', ','), $cfg_data);
			$available_acls = $fm_dns_acls->buildACLJSON($cfg_data, $server_serial_no, 'none');
			$tls_connections = buildSelect('cfg_data_params[tls]', 'cfg_data_params[tls]', $fm_module_options->availableParents('tls', 'tls_', $server_serial_no, array('blank', 'tls-default')), $cfg_data_array['tls'], 1, null, false, null, 'cfg_drop_down', __('Select a connection'));
			$http_endpoints = buildSelect('cfg_data_params[http]', 'cfg_data_params[http]', $fm_module_options->availableParents('http', 'http_', $server_serial_no, 'blank'), $cfg_data_array['http'], 1, null, false, null, 'cfg_drop_down', __('Select an endpoint'));

			printf('<th width="33&#37;" scope="row"><label for="cfg_data">%s</label></th>
					<td width="67&#37;">
					<label for="cfg_data_params[port]"><b>Port</b></label> <input type="text" id="cfg_data_params[port]" name="cfg_data_params[port]" value="%s" maxlength="5" style="width: 5em;" onkeydown="return validateNumber(event)" /><br />
					<label for="cfg_data_params[proxy]"><b>Proxy</b></label>&nbsp;  <a href="#" class="tooltip-top" data-tooltip="%s"><i class="fa fa-question-circle"></i></a>&nbsp; <input type="text" id="cfg_data_params[proxy]" name="cfg_data_params[proxy]" value="%s" /><br />
					<label><b>TLS</b></label>&nbsp;  <a href="#" class="tooltip-top" data-tooltip="%s"><i class="fa fa-question-circle"></i></a>&nbsp; %s<br />
					<label><b>HTTP</b></label>&nbsp;  <a href="#" class="tooltip-top" data-tooltip="%s"><i class="fa fa-question-circle"></i></a>&nbsp; %s<br />
					<label for="cfg_data"><b>Address</b></label> <input type="hidden" name="cfg_data" class="address_match_element" value="%s" /><br />
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
						tokenSeparators: [",", ";"],
						data: %s
					});
					$(".cfg_drop_down").select2({
						width: "200px",
						allowClear: true
					});
					</script>', __('Option Value'),
						$cfg_data_array['port'], sprintf(__('This option requires BIND %s or later.'), '9.19.19'), $cfg_data_array['proxy'],
						sprintf(__('This option requires BIND %s or later.'), '9.18.0'), $tls_connections,
						sprintf(__('This option requires BIND %s or later.'), '9.18.0'), $http_endpoints,
						$cfg_data,
						$available_acls);
		} elseif (in_array($result[0]->def_option, array('include'))) {
			$cfg_data = str_replace(array('\"', '"', "'"), '', $cfg_data);
			$domain_id = (array_key_exists('domain_id', $_POST)) ? intval($_POST['domain_id']) : 0;
			$available_files = $fm_dns_files->buildJSON($cfg_data, $server_serial_no);

			$checkbox = $tooltip = null;
			if (strtolower($_POST['cfg_type']) == 'global' && !array_key_exists('view_id', $_POST) && !array_key_exists('domain_id', $_POST)) {
				$checked = getNameFromID($_POST['cfg_id'], "fm_{$__FM_CONFIG['fmDNS']['prefix']}config", 'cfg_', 'cfg_id', 'cfg_in_clause') == 'no' ? 'checked' : null;
				$checkbox = sprintf('<br /><input name="cfg_in_clause" id="cfg_in_clause" type="checkbox" value="no" %s /><label for="cfg_in_clause">%s</label>', $checked, __('Define outside of global options clause'));
			} elseif (array_key_exists('domain_id', $_POST)) {
				$tooltip = sprintf(' <a href="#" class="tooltip-top" data-tooltip="%s"><i class="fa fa-question-circle"></i></a>', __('This file will be appended to the zone file as an $INCLUDE statement.'));
			}

			printf('<th width="33&#37;" scope="row"><label for="cfg_data">%s</label>%s</th>
					<td width="67&#37;"><input type="hidden" name="cfg_data" class="address_match_element" value="%s" />
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
						maximumSelectionSize: 1,
						width: "200px",
						tokenSeparators: [",", ";"],
						data: %s
					});
					</script>', __('Option Value'), $tooltip,
						$cfg_data,
						$checkbox,
						$available_files);
		} elseif ($result[0]->def_option == 'dnssec-policy') {
			$dropdown = buildSelect('cfg_data[]', 'cfg_data', $fm_module_dnssec->getDNSSECPolicies($cfg_data), $cfg_data, 1);
			printf('<th width="33&#37;" scope="row"><label for="cfg_data">%s</label></th>
					<td width="67&#37;">%s', __('Option Value'), $dropdown);
		} elseif ($result[0]->def_dropdown == 'no') {
			printf('<th width="33&#37;" scope="row"><label for="cfg_data">%s</label></th>
					<td width="67&#37;"><input name="cfg_data" id="cfg_data" type="text" value="%s" size="40" /><br />
					%s', __('Option Value'), str_replace(array('\"', '"', "'"), '', $cfg_data), $result[0]->def_type);
		} else {
			/** Build array of possible values */
			$dropdown = $fm_module_options->populateDefTypeDropdown($result[0]->def_type, $cfg_data);
			printf('<th width="33&#37;" scope="row"><label for="cfg_data">%s</label></th>
					<td width="67&#37;">%s', __('Option Value'), $dropdown);
		}
		if ($result[0]->def_minimum_version) printf('<br /><span class="note">%s</span></td>', sprintf(__('This option requires BIND %s or later.'), $result[0]->def_minimum_version));
	}
	exit;
} elseif (is_array($_POST) && array_key_exists('get_available_clones', $_POST) && currentUserCan('manage_zones', $_SESSION['module'])) {
	echo buildSelect('domain_clone_domain_id', 'domain_clone_domain_id', $fm_dns_zones->availableCloneDomains($_POST['map'], 0), 0);
	exit;
} elseif (is_array($_POST) && array_key_exists('get_available_options', $_POST) && currentUserCan('manage_servers', $_SESSION['module'])) {
	$cfg_type = isset($_POST['cfg_type']) ? sanitize($_POST['cfg_type']) : 'global';
	$server_serial_no = isset($_POST['server_serial_no']) ? $_POST['server_serial_no'] : 0;
	$avail_options_array = $fm_module_options->availableOptions('add', $server_serial_no, $cfg_type);
	echo buildSelect('cfg_name', 'cfg_name', $avail_options_array, sanitize($_POST['cfg_name']), 1, null, false, 'displayOptionPlaceholder()');
	exit;
} elseif (is_array($_POST) && array_key_exists('get_dynamic_zone_data', $_POST) && currentUserCan('manage_records', $_SESSION['module']) && zoneAccessIsAllowed(array($_POST['domain_id']))) {
	$server_zone_data = $fm_dns_records->getServerZoneData(sanitize($_POST['domain_id']));
	
	/** Add popup header and footer if missing */
	if (strpos($server_zone_data, 'popup-header') === false) {
		$server_zone_data = buildPopup('header', _('Error')) . '<p>' . $server_zone_data . '</p>' . buildPopup('footer', _('OK'), array('cancel_button' => 'cancel'));
	}
	
	exit($server_zone_data);
}

if (is_array($_GET) && array_key_exists('action', $_GET) && $_GET['action'] == 'display-process-all') {
	$update_count = countServerUpdates();
	$update_count += getZoneReloads('count');
	
	echo $update_count;
	exit;
}

/** Edits */
$checks_array = array('servers' => 'manage_servers',
					'views' => 'manage_servers',
					'acls' => 'manage_servers',
					'keys' => 'manage_servers',
					'options' => 'manage_servers',
					'logging' => 'manage_servers',
					'controls' => 'manage_servers',
					'masters' => 'manage_servers',
					'domains' => 'manage_zones',
					'domain' => 'manage_zones',
					'soa' => 'manage_zones',
					'rpz' => 'manage_zones',
					'http' => 'manage_servers',
					'tls' => 'manage_servers',
					'files' => 'manage_servers',
					'dnssec-policy' => 'manage_servers'
				);

if (is_array($_POST) && count($_POST) && currentUserCan(array_unique($checks_array), $_SESSION['module'])) {
	$perms = checkUserPostPerms($checks_array, $_POST['item_type']);
	
	if ($_POST['item_type'] == 'options' && !$perms) {
		if (array_key_exists('item_sub_type', $_POST) && $_POST['item_sub_type'] == 'domain_id') {
			$perms = zoneAccessIsAllowed(array($_POST['item_id']), 'manage_zones');
		} elseif ($_POST['item_type'] == 'options') {
			$perms = zoneAccessIsAllowed(array(getNameFromID(sanitize($_POST['item_id']), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_', 'cfg_id', 'domain_id')), 'manage_zones');
		}
	}
	if (!$perms) {
		returnUnAuth();
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
	$type_map = null;
	$action = 'add';
	
	/* Determine which class we need to deal with */
	switch ($_POST['item_type']) {
		case 'servers':
			$post_class = $fm_module_servers;
			if (isset($_POST['item_sub_type']) && sanitize($_POST['item_sub_type']) == 'groups') {
				$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'server_groups';
				$prefix = 'group_';
			}
			break;
		case 'options':
			$post_class = $fm_module_options;
			$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config';
			$prefix = 'cfg_';
			$type_map = @isset($_POST['request_uri']['type']) ? sanitize($_POST['request_uri']['type']) : 'global';
			break;
		case 'domains':
			$post_class = $fm_dns_zones;
			$type_map = isset($_POST['item_sub_type']) ? sanitize($_POST['item_sub_type']) : null;
			$action = 'create';
			if (!$add_new) $item_id = array('popup', 'template_menu');
			
			if ($type_map == 'groups') {
				$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domain_groups';
				$prefix = 'group_';
			}
			break;
		case 'logging':
			$post_class = $fm_module_logging;
			$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config';
			$prefix = 'cfg_';
			$item_type = sanitize($_POST['item_sub_type']) . ' ';
			break;
		case 'rpz':
		case 'http':
		case 'tls':
		case 'dnssec-policy':
			$post_class = (in_array($_POST['item_type'], array('dnssec-policy'))) ? $fm_module_dnssec : ${"fm_module_{$_POST['item_type']}"};
			$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config';
			$prefix = 'cfg_';
			$type_map = sanitize($_POST['item_type']);
			break;
		case 'soa':
			$post_class = $fm_module_templates;
			$prefix = 'soa_';
			$type_map = sanitize($_POST['item_type']);
			break;
		case 'domain':
			$post_class = $fm_module_templates;
			$prefix = 'domain_';
			$type_map = sanitize($_POST['item_type']);
			$table .= 's';
			break;
		default:
			$post_class = ${"fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}{$_POST['item_type']}"};
	}
	
	if ($add_new) {
		if (in_array($_POST['item_type'], array('logging', 'servers', 'controls', 'keys', 'dnssec'))) {
			$edit_form = $post_class->printForm(null, $action, sanitize($_POST['item_sub_type']));
		} elseif ($_POST['item_type'] == 'domains') {
			$edit_form = $post_class->printForm(null, $action, $type_map);
		} else {
			$edit_form = $post_class->printForm(null, $action, $type_map, $id);
		}
	} else {
		if ($_POST['item_type'] == 'domains' && !zoneAccessIsAllowed(array($id))) returnUnAuth();
		
		basicGet('fm_' . $table, $id, $prefix, $prefix . 'id');
		if (!$fmdb->num_rows || $fmdb->sql_errors) returnError($fmdb->last_error);
		
		$edit_form_data[] = $fmdb->last_result[0];
		if (in_array($_POST['item_type'], array('logging', 'servers', 'controls', 'keys', 'dnssec'))) {
			$edit_form = $post_class->printForm($edit_form_data, 'edit', sanitize($_POST['item_sub_type']));
		} else {
			$edit_form = $post_class->printForm($edit_form_data, 'edit', $type_map, $item_id);
		}
	}
	
	echo $edit_form;
} else returnUnAuth();
