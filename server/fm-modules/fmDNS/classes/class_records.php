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
*/

class fm_dns_records {
	
	/**
	 * Displays the record list
	 */
	function rows($result, $record_type, $domain_id, $page) {
		global $fmdb, $__FM_CONFIG;
		
		$results = $fmdb->last_result;
		$start = $_SESSION['user']['record_count'] * ($page - 1);
		$end = $_SESSION['user']['record_count'] * $page > $fmdb->num_rows ? $fmdb->num_rows : $_SESSION['user']['record_count'] * $page;

		$table_info = array('class' => 'display_results sortable');

		$return = displayTableHeader($table_info, $this->getHeader(strtoupper($record_type)));
		
		if ($result) {
			for ($x=$start; $x<$end; $x++) {
				$return .= $this->getInputForm(strtoupper($record_type), false, $domain_id, $results[$x]);
			}
		}
			
		$return .= "</tbody>\n</table>\n";
		if (!$result) {
			$message = ($record_type == 'ALL') ? __('There are no records.') : sprintf(__('There are no %s records.'), $record_type);
			$return .= sprintf('<p id="table_edits" class="noresult">%s</p>', $message);
		}
		
		return $return;
	}

	/**
	 * Adds the new record
	 */
	function add($domain_id, $record_type, $new_array, $operation = 'insert') {
		global $fmdb, $__FM_CONFIG, $fm_dns_zones;
		
		$domain_name = displayFriendlyDomainName(getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name'));
		$log_message = "Added a record with the following details:\nDomain: $domain_name\nType: $record_type\n";

		$table = ($record_type == 'SOA') ? 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'soa' : 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records';
		
		/** Check if record already exists */
		if ($record_type != 'SOA') {
			$query = "SELECT * FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}records WHERE domain_id=$domain_id AND record_name='{$new_array['record_name']}'
					AND record_value='{$new_array['record_value']}' AND record_type='$record_type' AND record_status != 'deleted'";
			if (in_array($record_type, array('NAPTR', 'CAA'))) {
				$query .= " AND record_params='{$new_array['record_params']}'";
			}
			if (in_array($record_type, array('NAPTR'))) {
				$query .= " AND record_regex='{$new_array['record_regex']}'";
			}
			if (in_array($record_type, array('CAA'))) {
				$query .= " AND record_flags='{$new_array['record_flags']}'";
			}
			$result = $fmdb->get_results($query);
			
			if ($fmdb->num_rows) {
				return;
			}
		}
		
		$new_array['account_id'] = $_SESSION['user']['account_id'];
		
		/** Replacing? */
		if ($operation == 'replace' && $record_type == 'PTR') {
			$query = "UPDATE fm_{$__FM_CONFIG['fmDNS']['prefix']}records SET record_status='deleted' WHERE account_id='{$_SESSION['user']['account_id']}'
				AND domain_id=$domain_id AND record_name='{$new_array['record_name']}' AND record_status!='deleted' LIMIT 1";
			$fmdb->query($query);
		}
		
		$sql_insert = "INSERT INTO `$table`";
		$sql_fields = '(';
		$sql_values = '';
		if ($record_type != 'SOA' && $record_type) {
			$sql_fields .= 'domain_id, record_type, ';
			$sql_values .= "$domain_id, '$record_type', ";
		}
		
		/** Process default integers */
		if (array_key_exists('record_priority', $new_array) && !is_numeric($new_array['record_priority'])) $new_array['record_priority'] = 0;
		if (array_key_exists('record_weight', $new_array) && !is_numeric($new_array['record_weight'])) $new_array['record_weight'] = 0;
		if (array_key_exists('record_port', $new_array) && !is_numeric($new_array['record_port'])) $new_array['record_port'] = 0;
		if (array_key_exists('record_ttl', $new_array) && !is_numeric($new_array['record_ttl'])) $new_array['record_ttl'] = null;

		foreach ($new_array as $key => $data) {
			if ($key == 'PTR') continue;
			if ($key == 'record_type') continue;
			if ($record_type == 'SOA' && in_array($key, array('record_type', 'record_comment'))) continue;
			$sql_fields .= $key . ', ';
			$sql_values .= "'" . sanitize($data) . "', ";
			if ($key != 'account_id') {
				if ($record_type == 'TLSA') {
					$key = str_replace(array('priority', 'weight', 'port'), array('usage', 'selector', 'type'), $key);
				}
				$log_message .= $data ? formatLogKeyData('record_', $key, $data) : null;
			}
			if ($key == 'soa_default' && $data == 'yes') {
				$query = "UPDATE `$table` SET $key = 'no' WHERE `account_id`='{$_SESSION['user']['account_id']}'";
				$fmdb->query($query);
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) return false;
		$insert_id = $fmdb->insert_id;
		
		/** Update domain with SOA ID */
		if ($record_type == 'SOA') {
			$this->assignSOA($insert_id, $domain_id);
		}

		/** Update the SOA serial number */
		$this->processSOAUpdates($domain_id, $record_type, 'add');

		addLogEntry($log_message);
		
		$fmdb->insert_id = $insert_id;
		return true;
	}

	/**
	 * Updates the selected record
	 */
	function update($domain_id, $id, $record_type, $array, $skipped_record = false) {
		global $fmdb, $__FM_CONFIG, $fm_dns_zones;
		
		$_domain_id = 0;
		
		/** Get correct domain name */
		if ($record_type == 'SOA') {
			$soa_name = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'soa', 'soa_', 'soa_id', 'soa_name');
			$log_message = sprintf(__('Updated a SOA template (%s) with the following details'), $soa_name) . ":\n";
			$domain_name = _('None');
		} else {
			$record_domain_id = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', 'record_', 'record_id', 'domain_id');
			$_domain_id = ($record_domain_id == $domain_id) ? $domain_id : $record_domain_id;

			$domain_name = displayFriendlyDomainName(getNameFromID($_domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name'));
			$record_name = ($record_type == 'SOA') ? $record_type : getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', 'record_', 'record_id', 'record_name');
			$log_message = sprintf(__('Updated a record (%s) with the following details'), $record_name) . ":\n";
			$log_message .= ($_domain_id) ? formatLogKeyData('', 'domain', $domain_name) : null;
		}

		$table = ($record_type == 'SOA') ? 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'soa' : 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records';
		$field = ($record_type == 'SOA') ? 'soa_id' : 'record_id';
		
		$record_type_sql = ($record_type != 'SOA') ? ",record_type='$record_type'" : null;
		
		$excluded_keys = array('record_skipped', 'PTR');
		$null_keys = array('record_key_tag');
		
		$sql_edit = '';
		
		foreach ($array as $key => $data) {
			if (in_array($key, $excluded_keys)) continue;
			if (in_array($key, $null_keys) && empty($data)) {
				$sql_edit .= $key . '=NULL,';
			} else {
				$sql_edit .= $key . "='" . sanitize(str_replace("\r\n", "\n", $data)) . "', ";
			}
			if ($record_type == 'TLSA') {
				$key = str_replace(array('priority', 'weight', 'port'), array('usage', 'selector', 'type'), $key);
			}
			if (!$skipped_record) $log_message .= $data ? formatLogKeyData('record_', $key, $data) : null;
			if ($key == 'soa_default' && $data == 'yes') {
				$query = "UPDATE `$table` SET $key = 'no' WHERE `account_id`='{$_SESSION['user']['account_id']}'";
				$fmdb->query($query);
			}
		}
		$sql_edit = rtrim($sql_edit, ', ');
		
		/** Update the record */
		if ($skipped_record) {
			$table .= '_skipped';
			$query = "SELECT * FROM `$table` WHERE account_id={$_SESSION['user']['account_id']} AND domain_id=$domain_id AND record_id=$id";
			$fmdb->query($query);
			if ($fmdb->num_rows) {
				$query = "UPDATE `$table` SET domain_id=$domain_id, record_id=$id, record_status='{$array['record_status']}' WHERE account_id={$_SESSION['user']['account_id']} AND domain_id=$domain_id AND record_id=$id";
			} else {
				$query = "INSERT INTO `$table` VALUES(NULL, {$_SESSION['user']['account_id']}, $domain_id, $id, '{$array['record_status']}')";
			}
			$data = $array['record_status'] == 'active' ? 'no' : 'yes';
			$log_message .= formatLogKeyData('', 'Included', $data);
		} else {
			$query = "UPDATE `$table` SET $sql_edit $record_type_sql WHERE `$field`='$id' AND `account_id`='{$_SESSION['user']['account_id']}'";
		}
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) return false;

		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		$domain_id_array = array($_domain_id);
		if ($record_type == 'SOA' && !$_domain_id) {
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_id', 'domain_', "AND soa_id='$id'");
			if ($fmdb->num_rows) {
				unset($domain_id_array);
				for ($i=0; $i < $fmdb->num_rows; $i++) {
					$domain_id_array[] = $fmdb->last_result[$i]->domain_id;
				}
			}
		}
		
		/** Update the SOA serial number */
		foreach ($domain_id_array as $domain_id) {
			$this->processSOAUpdates($domain_id, $record_type, 'update');
		}
		
		/** Unlink PTR */
		if ($record_type == 'PTR' && isset($array['record_status']) && $array['record_status'] == 'deleted') {
			basicUpdate('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $id, 'record_ptr_id', 0, 'record_ptr_id');
		}

		/** Set the server_update_config flag for existing servers */
		if ($record_type == 'URL') {
			resetURLServerConfigStatus('update');
		}

		addLogEntry($log_message);
		return $result;
	}
	
	function getHeader($type, $include = 'actions') {
		global $zone_access_allowed;
		
		$show_value = true;
		$rr_with_actions = array('A');

		if ($type == 'SOA') {
			$show_value = false;
			$title_array[] = array('title' => __('Name'), 'rel' => 'soa_name');
			$title_array[] = array('title' => __('Master'), 'rel' => 'soa_master_server');
			$title_array[] = array('title' => __('E-mail'), 'rel' => 'soa_email_address');
			$title_array[] = array('title' => __('Refresh'), 'rel' => 'soa_refresh');
			$title_array[] = array('title' => __('Retry'), 'rel' => 'soa_retry');
			$title_array[] = array('title' => __('Expire'), 'rel' => 'soa_expire');
			$title_array[] = array('title' => __('TTL'), 'rel' => 'soa_ttl');
		}
		if ($type == 'DOMAIN') {
			$show_value = false;
			$title_array[] = array('title' => __('Name'), 'rel' => $type . '_name');
			$title_array[] = array('title' => __('TTL'), 'rel' => $type . '_ttl');
			$title_array[] = array('title' => __('Name Servers'), 'rel' => $type . '_name_servers');
			$title_array[] = array('title' => __('Views'), 'rel' => $type . '_views');
			$title_array[] = array('title' => __('Map'), 'rel' => $type . '_mapping');
			$title_array[] = array('title' => __('Type'), 'rel' => $type . '_type');
			$title_array[] = array('title' => __('Comment'), 'rel' => $type . '_comment');
		}
		if (!in_array($type, array('SOA', 'DOMAIN', 'CUSTOM'))) {
			$title_array[] = array('title' => __('Record'), 'rel' => 'record_name');
			$title_array[] = array('title' => __('TTL'), 'rel' => 'record_ttl');
			if ($type == 'ALL') $title_array[] = array('title' => __('Type'), 'rel' => 'record_type');
		}
		if ($type == 'CERT' ) {
			$title_array[] = array('title' => __('Type'), 'rel' => 'record_cert_type');
		}
		if (in_array($type, array('CERT', 'DLV', 'DS'))) {
			$title_array[] = array('title' => __('Key Tag'), 'rel' => 'record_key_tag');
		}
		if (in_array($type, array('CERT', 'SSHFP', 'DLV', 'DS'))) {
			$title_array[] = array('title' => __('Algorithm'), 'rel' => 'record_algorithm');
		}
		if ($type == 'SSHFP' ) {
			$title_array[] = array('title' => __('Type'), 'rel' => 'record_cert_type');
		}
		if ($type == 'DS') {
			$title_array[] = array('title' => __('Type'), 'rel' => 'record_cert_type');
		}
		if ($type == 'HINFO') {
			$title_array[] = array('title' => __('Hardware'), 'rel' => 'record_value');
			$title_array[] = array('title' => __('OS'), 'rel' => 'record_os');
			$show_value = false;
		}
		if (in_array($type, array('DNSKEY', 'KEY'))) {
			$title_array[] = array('title' => __('Flags'), 'rel' => 'record_flags');
			$title_array[] = array('title' => __('Algorithm'), 'rel' => 'record_algorithm');
		}
		
		if ($type == 'NAPTR') {
			$show_value = false;
			$title_array[] = array('title' => __('Order'), 'rel' => 'record_weight');
			$title_array[] = array('title' => __('Pref'), 'rel' => 'record_priority');
			$title_array[] = array('title' => __('Flags'), 'rel' => 'record_flags');
			$title_array[] = array('title' => __('Params'), 'rel' => 'record_params');
			$title_array[] = array('title' => __('Regex'), 'rel' => 'record_regex');
			$title_array[] = array('title' => __('Replace'), 'rel' => 'record_value');
		}
		
		if ($type == 'CAA') {
			$title_array[] = array('title' => __('Flags'), 'rel' => 'record_flags');
			$title_array[] = array('title' => __('Tags'), 'rel' => 'record_params');
		}
		
		if (in_array($type, array('SMIMEA', 'TLSA'))) {
			$title_array[] = array('title' => __('Usage'), 'rel' => 'record_priority');
			$title_array[] = array('title' => __('Selector'), 'rel' => 'record_weight');
			$title_array[] = array('title' => __('Type'), 'rel' => 'record_port');
		}
		
		if ($type == 'RP' ) {
			$title_array[] = array('title' => __('Text'), 'rel' => 'record_text');
		}
		
		$priority = array('MX', 'SRV', 'KX', 'URI');
		$weight = array('SRV', 'URI');
		
		if (in_array($type, $priority)) $title_array[] = array('title' => __('Priority'), 'rel' => 'record_priority');
		if (in_array($type, $weight)) $title_array[] = array('title' => __('Weight'), 'rel' => 'record_weight');
		
		if ($type == 'SRV') {
			$title_array[] = array('title' => __('Port'), 'rel' => 'record_port');
		}

		if ($show_value) $title_array[] = array('title' => __('Value'), 'rel' => 'record_value');

		if (!in_array($type, array('SOA', 'DOMAIN', 'CUSTOM'))) {
			$title_array[] = array('title' => _('Comment'), 'rel' => 'record_comment');
		}
		
		if (currentUserCan('manage_records', $_SESSION['module']) && $zone_access_allowed && !in_array($type, array('SOA', 'DOMAIN', 'CUSTOM'))) $title_array[] = array('title' => __('Status'), 'rel' => 'record_status');
		if (empty($_POST) || $type == 'DOMAIN') {
			if ((in_array($type, $rr_with_actions) || $include == 'actions') && (currentUserCan('manage_records', $_SESSION['module']) || currentUserCan('manage_zones', $_SESSION['module'])) && $zone_access_allowed) $title_array[] = array('title' => __('Actions'), 'class' => 'header-actions header-nosort');
		} else {
			array_unshift($title_array, __('Action'));
			array_unshift($title_array, array('title' => __('Valid'), 'style' => 'text-align: center;'));
		}
		
		return $title_array;
	}

	function getInputForm($selected_type, $new, $parent_domain_id, $results = null, $include = 'actions', $start = 1) {
		global $__FM_CONFIG, $zone_access_allowed;
		
		$form = $record_status = $record_name = $record_ttl = null;
		$record_value = $record_comment = $record_priority = $record_weight = $record_port = null;
		$record_params = $record_cert_type = $record_key_tag = $record_algorithm = $record_flags = null;
		$record_os = $record_regex = $record_text = null;
		$record_type = ($selected_type == 'ALL') ? 'A' : $selected_type;
		$action = ($new) ? 'create' : 'update';
		$end = ($new) ? $start : 1;
		$show_value = true;
		$value_textarea = false;

		$rr_with_actions = array('A');
		$append = array('CNAME', 'NS', 'MX', 'SRV', 'DNAME', 'RP', 'NAPTR');
		$priority = array('MX', 'SRV', 'KX', 'URI');
		$weight = array('SRV', 'URI');

		if ($results) {
			$results = get_object_vars($results);
			extract($results);
		}
		if (!isset($page_record_type)) $page_record_type = null;

		if ($record_type == 'CUSTOM') return null;

		$yeschecked = (isset($record_append) && $record_append == 'yes') ? 'checked' : '';
		
		$statusopt[0] = array(__('Active'), 'active');
		$statusopt[1] = array(__('Disabled'), 'disabled');
		$status = BuildSelect($action . '[_NUM_][record_status]', 'status__NUM_', $statusopt, $record_status);
		$field_values['class'] = $record_status;
		
		if ($record_type == 'PTR') {
			$domain_map = getNameFromID($parent_domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_mapping');
		}
		$domain = getNameFromID($parent_domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name');
		
		if ($new || ((currentUserCan('manage_records', $_SESSION['module']) || currentUserCan('manage_zones', $_SESSION['module'])) && $zone_access_allowed && ($new || $domain_id == $parent_domain_id))) {
			if ($record_type == 'PTR') {
				$input_box = '<input ';
				$input_box .= ($domain_map == 'forward') ? 'size="40"' : 'style="width: 40px;" size="4"';
				$input_box .= ' type="text" name="' . $action . '[_NUM_][record_name]" value="' . $record_name . '" />';
				if (strpos($domain, 'arpa')) {
					$field_values['data']['Record'] = $input_box . ' .' . $domain;
				} elseif ($domain_map == 'forward') {
					$field_values['data']['Record'] = $input_box;
				} else {
					$field_values['data']['Record'] = $domain . '. ' . $input_box;
				}
			} else {
				$field_values['data']['Record'] = '<input type="text" name="' . $action . '[_NUM_][record_name]" value="' . $record_name . '" />';
			}
			$field_values['data']['TTL'] = '<input style="width: 35px;" type="text" name="' . $action . '[_NUM_][record_ttl]" value="' . $record_ttl . '" onkeydown="return validateTimeFormat(event, this)" />';
			
			/** Resource Record types */
			if ($selected_type == 'ALL') {
				if ($new) {
					$supported_record_types = enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', 'record_type');
					unset($supported_record_types[array_search('CUSTOM', $supported_record_types)]);
					sort($supported_record_types);
					$field_values['data']['Type'] = buildSelect($action . '[_NUM_][record_type]', '_NUM_', $supported_record_types, $record_type, 1, null, false, null, 'record-type');
				} else {
					$field_values['data']['Type'] = $this->getRRTypeLink($record_type);
				}
			}

			if ($record_type == 'CAA') {
				$field_values['data']['Value']['subgroup-1']['Flags'] = buildSelect($action . '[_NUM_][record_flags]', '_NUM_', $__FM_CONFIG['records']['caa_flags'], $record_flags);
				$field_values['data']['Value']['subgroup-1']['Tags'] = buildSelect($action . '[_NUM_][record_params]', '_NUM_', $__FM_CONFIG['records']['caa_tags'], $record_params);
			}
			
			if ($record_type == 'CERT') {
				$field_values['data']['Value']['subgroup-1']['Type'] = buildSelect($action . '[_NUM_][record_cert_type]', '_NUM_', $__FM_CONFIG['records']['cert_types'], $record_cert_type);
				$field_values['data']['Value']['subgroup-1']['Key Tag'] = '<input style="width: 45px;" type="text" name="' . $action . '[_NUM_][record_key_tag]" value="' . $record_key_tag . '" onkeydown="return validateNumber(event)" />';
				$field_values['data']['Value']['subgroup-1']['Algorithm'] = buildSelect($action . '[_NUM_][record_algorithm]', '_NUM_', $__FM_CONFIG['records']['cert_algorithms'], $record_algorithm);
				$value_textarea = true;
			}
			
			if ($record_type == 'SSHFP') {
				$field_values['data']['Value']['subgroup-1']['Algorithm'] = buildSelect($action . '[_NUM_][record_algorithm]', '_NUM_', $__FM_CONFIG['records']['sshfp_algorithms'], $record_algorithm);
				$field_values['data']['Value']['subgroup-1']['Type'] = buildSelect($action . '[_NUM_][record_cert_type]', '_NUM_', $__FM_CONFIG['records']['digest_types'], $record_cert_type);
				$value_textarea = true;
			}
			
			if ($record_type == 'HINFO') {
				$field_values['data']['Value']['Hardware'] = '<input maxlength="255" type="text" name="' . $action . '[_NUM_][record_value]" value="' . $record_value . '" />';
				$field_values['data']['Value']['OS'] = '<input maxlength="255" type="text" name="' . $action . '[_NUM_][record_os]" value="' . $record_os . '" />';
				$show_value = false;
			}
			
			if (in_array($record_type, array('DNSKEY', 'KEY'))) {
				$flags = $__FM_CONFIG['records']['flags'];
				$algorithms = $__FM_CONFIG['records']['cert_algorithms'];
				$value_textarea = true;
				
				if ($record_type == 'KEY') {
					array_pop($flags);
					for ($i=1; $i<=4; $i++) {
						array_pop($algorithms);
					}
				}
				
				$field_values['data']['Value']['subgroup-1']['Flags'] = buildSelect($action . '[_NUM_][record_flags]', '_NUM_', $flags, $record_flags);
				$field_values['data']['Value']['subgroup-1']['Algorithm'] = buildSelect($action . '[_NUM_][record_algorithm]', '_NUM_', $algorithms, $record_algorithm);
			}
			
			if ($record_type == 'NAPTR') {
				$field_values['data']['Value']['subgroup-1']['Order'] = '<input maxlength="5" style="width: 35px;" type="text" name="' . $action . '[_NUM_][record_weight]" value="' . $record_weight . '" onkeydown="return validateNumber(event)" />';
				$field_values['data']['Value']['subgroup-1']['Pref'] = '<input maxlength="5" style="width: 35px;" type="text" name="' . $action . '[_NUM_][record_priority]" value="' . $record_priority . '" onkeydown="return validateNumber(event)" />';
				$field_values['data']['Value']['subgroup-1']['Flags'] = buildSelect($action . '[_NUM_][record_flags]', '_NUM_', $__FM_CONFIG['records']['naptr_flags'], $record_flags);
				$field_values['data']['Value']['subgroup-2']['Params'] = '<input maxlength="255" style="width: 100px;" type="text" name="' . $action . '[_NUM_][record_params]" value="' . $record_params . '" />';
				$field_values['data']['Value']['subgroup-2']['Regex'] = '<input maxlength="255" style="width: 100px;" type="text" name="' . $action . '[_NUM_][record_regex]" value="' . $record_regex . '" />';
				$field_values['data']['Value']['subgroup-2']['Replace'] = '<input maxlength="255" type="text" name="' . $action . '[_NUM_][record_value]" value="' . $record_value . '" />';
				$show_value = false;
			}
			
			if (in_array($record_type, array('SMIMEA', 'TLSA'))) {
				$tmp_flags1 = $__FM_CONFIG['records']['tlsa_flags'];
				array_pop($tmp_flags1);
				$tmp_flags2 = $tmp_flags1;
				array_pop($tmp_flags1);
				$field_values['data']['Value']['subgroup-1']['Priority'] = buildSelect($action . '[_NUM_][record_priority]', '_NUM_', $__FM_CONFIG['records']['tlsa_flags'], $record_priority);
				$field_values['data']['Value']['subgroup-1']['Weight'] = buildSelect($action . '[_NUM_][record_weight]', '_NUM_', $tmp_flags1, $record_weight);
				$field_values['data']['Value']['subgroup-1']['Port'] = buildSelect($action . '[_NUM_][record_port]', '_NUM_', $tmp_flags2, $record_port);
			}
			
			if (in_array($record_type, array('SMIMEA', 'OPENPGPKEY'))) {
				$value_textarea = true;
			}
			
			if (in_array($record_type, array('DHCID', 'DLV', 'DS'))) {
				if (in_array($record_type, array('DLV', 'DS'))) {
					$field_values['data']['Value']['subgroup-1']['Key Tag'] = '<input style="width: 45px;" type="text" name="' . $action . '[_NUM_][record_key_tag]" value="' . $record_key_tag . '" onkeydown="return validateNumber(event)" />';
					$field_values['data']['Value']['subgroup-1']['Algorithm'] = buildSelect($action . '[_NUM_][record_algorithm]', '_NUM_', $__FM_CONFIG['records']['cert_algorithms'], $record_algorithm);
					if ($record_type == 'DS') {
						$field_values['data']['Value']['subgroup-1']['Type'] = buildSelect($action . '[_NUM_][record_cert_type]', '_NUM_', $__FM_CONFIG['records']['digest_types'], $record_cert_type);
					}
				}
				$value_textarea = true;
			}
			
			if ($record_type == 'RP') {
				$field_values['data']['Value']['Text'] = '<input maxlength="255" type="text" name="' . $action . '[_NUM_][record_text]" value="' . $record_text . '" />';
			}
			
			if (in_array($record_type, $priority) || in_array($selected_type, $priority)) $field_values['data']['Value']['subgroup-1']['Priority'] = '<input style="width: 35px;" type="text" name="' . $action . '[_NUM_][record_priority]" value="' . $record_priority . '" onkeydown="return validateNumber(event)" />';
			if (in_array($record_type, $weight)) $field_values['data']['Value']['subgroup-1']['Weight'] = '<input style="width: 35px;" type="text" name="' . $action . '[_NUM_][record_weight]" value="' . $record_weight . '" onkeydown="return validateNumber(event)" />';
	
			if ($record_type == 'SRV') {
				$field_values['data']['Value']['subgroup-1']['Port'] = '<input style="width: 35px;" type="text" name="' . $action . '[_NUM_][record_port]" value="' . $record_port . '" onkeydown="return validateNumber(event)" />';
			}

			if ($selected_type != 'ALL' && $include != 'record-value-group-only' && isset($field_values['data']['Value']) && is_array($field_values['data']['Value'])) {
				foreach ($field_values['data']['Value'] as $key => $val) {
					$field_values['data'][$key] = $val;
					unset($field_values['data']['Value'][$key]);
				}
				if (count($field_values['data']['Value']) || empty($field_values['data']['Value'])) {
					unset($field_values['data']['Value']);
				}
			}
		
			if ($show_value) {
				if ($value_textarea) {
					$field_values['data']['Value']['Value'] = '<textarea rows="2" name="' . $action . '[_NUM_][record_value]">' . $record_value . '</textarea>';
				} else {
					$field_values['data']['Value']['Value'] = '<input class="input-long-wide" type="text" name="' . $action . '[_NUM_][record_value]" value="' . $record_value . '" />';
				}

				/** Linked PTR */
				if (isset($record_ptr_id) && $record_ptr_id) {
					$field_values['data']['Value']['Value'] .= ' <a href="#" class="tooltip-right" data-tooltip="' . __('Linked PTR exists') . '"><i class="mini-icon fa fa-exchange"></i></a>';
				}

				/** Append */
				if (in_array($record_type, $append)) $field_values['data']['Value']['Append'] = '<p class="record-sub-value"><label><input ' . $yeschecked . ' type="checkbox" id="record_append[_NUM_][0]" name="' . $action . '[_NUM_][record_append]" value="yes" /> ' . __('Append Domain') . '</label></p>';
			}

			if (in_array($record_type, $rr_with_actions)) {
				if ($new) {
					$field_values['data']['Value']['Actions'] = '<p class="record-sub-value"><label><input type="checkbox" name="' . $action . '[_NUM_][PTR]" />' . __('Create PTR') . '</label></p>';
				} else {
					$field_values['data']['Value']['Actions'] .= '<p class="record-sub-value"><label><input type="checkbox" name="' . $action . '[_NUM_][PTR]" />';
					$field_values['data']['Value']['Actions'] .= ($record_ptr_id) ? __('Update PTR') : __('Create PTR');
					$field_values['data']['Value']['Actions'] .= '</label></p>';
				}
			}

			if (isset($field_values['data']['Value'])) {
				$value_count = count($field_values['data']['Value']);
				if ($value_count <= 1 && $show_value) {
					$field_values['data']['Value'] = $field_values['data']['Value']['Value'];
				}
			}

			$field_values['data']['Comment'] = '<input class="input-long-wide" maxlength="200" type="text" name="' . $action . '[_NUM_][record_comment]" value="' . $record_comment . '" />';
			
			$field_values['data']['Status'] = $status;

			if (!$new) {
				$field_values['data']['Actions'] = '<label><input type="checkbox" id="record_delete_' . $record_id . '" name="' . $action . '[_NUM_][Delete]" style="display: none;"/>' . $__FM_CONFIG['icons']['delete'] . '</label>';
			}
		} else {
			$domain = strlen($domain) > 23 ? substr($domain, 0, 20) . '...' : $domain;
			$field_values['data']['Record'] = $record_name . '<span class="grey">.' . $domain . '</span>';
			$field_values['data']['TTL'] = $record_ttl;
			
			/** Resource Record types */
			if ($selected_type == 'ALL') {
				$field_values['data']['Type'] = $this->getRRTypeLink($record_type);
			}

			if ($record_type == 'CAA') {
				$field_values['data']['Value']['subgroup-1']['Flags'] = $record_flags;
				$field_values['data']['Value']['subgroup-1']['Tags'] = $record_params;
			}
			
			if ($record_type == 'CERT') {
				$field_values['data']['Value']['subgroup-1']['Type'] = $record_cert_type;
				$field_values['data']['Value']['subgroup-1']['Key Tag'] = $record_key_tag;
				$field_values['data']['Value']['subgroup-1']['Algorithm'] = $record_algorithm;
				$value_textarea = true;
			}
			
			if ($record_type == 'SSHFP') {
				$field_values['data']['Value']['subgroup-1']['Algorithm'] = $record_algorithm;
				$field_values['data']['Value']['subgroup-1']['Type'] = $record_cert_type;
				$value_textarea = true;
			}
			
			if ($record_type == 'HINFO') {
				$field_values['data']['Value']['Hardware'] = $record_value;
				$field_values['data']['Value']['OS'] = $record_os;
				$show_value = false;
			}

			if ($record_type == 'NAPTR') {
				$field_values['data']['Value']['subgroup-1']['Order'] = $record_weight;
				$field_values['data']['Value']['subgroup-1']['Pref'] = $record_priority;
				$field_values['data']['Value']['subgroup-1']['Flags'] = $record_flags;
				$field_values['data']['Value']['subgroup-2']['Params'] = $record_params;
				$field_values['data']['Value']['subgroup-2']['Regex'] = $record_regex;
				$field_values['data']['Value']['subgroup-2']['Replace'] = $record_value;
				$show_value = false;
			}
			
			if (in_array($record_type, array('SMIMEA', 'TLSA'))) {
				$field_values['data']['Value']['subgroup-1']['Priority'] = $record_priority;
				$field_values['data']['Value']['subgroup-1']['Weight'] = $record_weight;
				$field_values['data']['Value']['subgroup-1']['Port'] = $record_port;
			}
			
			if (in_array($record_type, $priority) || in_array($selected_type, $priority)) $field_values['data']['Value']['subgroup-1']['Priority'] = $record_priority;
			if (in_array($record_type, $weight)) $field_values['data']['Value']['subgroup-1']['Weight'] = $record_weight;
			
			if ($record_type == 'SRV') {
				$field_values['data']['Value']['subgroup-1']['Weight'] = $record_weight;
				$field_values['data']['Value']['subgroup-1']['Port'] = $record_port;
			}
		
			if ($show_value) {
				if (strpos($record_value, "\n") === false) {
					$record_value = strlen($record_value) > 80 ? wordwrap($record_value, 80, ' \<br />', true) : $record_value;
				} else {
					$record_value = nl2br($record_value);
				}
				
				$field_values['data']['Value']['Value'] = $record_value;
				if ((in_array($record_type, $append) || in_array($selected_type, $append)) && $record_append == 'yes') $field_values['data']['Value']['Value'] .= '<span class="grey">.' . $domain . '</span>';
			}
			
			$field_values['data']['Comment'] = $record_comment;
			if (currentUserCan('manage_records', $_SESSION['module']) && $zone_access_allowed && !in_array($selected_type, array('SOA', 'DOMAIN', 'CUSTOM'))) $field_values['data']['Status'] = '';
			
			if ((currentUserCan('manage_records', $_SESSION['module']) || currentUserCan('manage_zones', $_SESSION['module'])) && $zone_access_allowed && $domain_id != $parent_domain_id) {
				$field_values['data']['Actions'] = '<input type="hidden" name="' . $action . '[_NUM_][record_skipped]" value="off" /><label><input type="checkbox" name="' . $action . '[_NUM_][record_skipped]" ';
				if (in_array($record_id, $this->getSkippedRecordIDs($parent_domain_id))) {
					$field_values['data']['Actions'] .= ' checked';
					$field_values['class'] = 'disabled';
				} else {
					$field_values['data']['Actions'] .= null;
				}
				$field_values['data']['Actions'] .= '/>' . __('Skip Import') . '</label>';
			} elseif (!currentUserCan('manage_records', $_SESSION['module']) && $zone_access_allowed && $domain_id != $parent_domain_id) {
				if (in_array($record_id, $this->getSkippedRecordIDs($parent_domain_id))) {
					return null;
				}
			}
		}

		if ($new) {
			$field_values['class'] = 'new-record build notice';
			if (!isset($field_values['data']['Actions'])) $field_values['data']['Actions'] = '';
		}
		if (isset($field_values['data']['Actions'])) $field_values['data']['Actions'] .= sprintf('<div class="inline-record-actions" style="display: none;"><a href="#" class="inline-record-validate"><i class="fa fa-check" title="%s" aria-hidden="true"></i></a><a href="#" class="inline-record-cancel"><i class="fa fa-undo" title="%s" aria-hidden="true"></i></a></div>', __('Validate'), _('Cancel'));
		
		for ($i=$start; $i<=$end; $i++) {
			$form .= '<tr class="' . $field_values['class'] . '">' . "\n";
			foreach ($field_values['data'] as $key => $val) {
				$num = ($new) ? $i : $record_id;
				if (is_array($val)) {
					$tmp_val = '';
					foreach ($val as $subtitle => $subval) {
						if (is_array($subval)) $subgroup = $subtitle;

						if (in_array($subtitle, array('Append', 'Actions')) ||
							($subtitle == 'Value' &&
								$value_count == 2 &&
								(array_key_exists('Append', $val) || array_key_exists('Actions', $val)) &&
								array_key_exists('Value', $val)
							)
						) {
							$subtitle = '';
						}

						if ($subtitle) $subtitle = sprintf('<p class="record-sub-value"><strong>%s</strong></p>', __($subtitle));

						if (is_array($subval)) {
							if ($new || $selected_type == 'ALL') {
								$tmp_val .= sprintf('<div class="record-value-%s">', $subgroup);
								foreach ($subval as $s => $v) {
									if ($s) $subtitle = sprintf('<p class="record-sub-value"><strong>%s</strong></p>', __($s));
									$tmp_val .= sprintf('<div>%s%s</div>', $subtitle, $v);
								}
								$tmp_val .= "</div>\n";
							} else {
								foreach ($subval as $s => $v) {
									$tmp_val .= sprintf('%s</td><td>', $v);
								}
							}
						} else {
							$tmp_val .= (($new && $page_record_type == 'ALL') || !$subtitle || $selected_type == 'ALL') ? sprintf('<div>%s%s</div>', $subtitle, $subval) : sprintf('%s</td><td>', $subval);
						}
					}
					$val = $tmp_val;
					unset($tmp_val);
				}

				$val = str_replace('_NUM_', $num, $val);

				if (substr($val, -4) == '<td>') {
					$val = substr($val, 0, -4);
				}

				if ($key == 'Value') {
					if ($include == 'record-value-group-only') return $val;
					$val = sprintf('<div class="record-value-group">%s</div>', $val);
				}

				$form .= ($key == 'Actions') ? "\t<td class=\"column-actions\">$val</td>\n" : "\t<td>$val</td>\n";
			}
			$form .= "</tr>\n";
		}
		
		return $form;
	}
	
	function buildSOA($result, $show = array('template_menu', 'create_template', 'template_name'), $force_action = 'auto') {
		global $__FM_CONFIG, $disabled;
		
		$soa_id = 0;
		$soa_name = $soa_templates = $create_template = $template_name = null;
		$map = isset($_GET['map']) ? $_GET['map'] : 'forward';
		
		if ($result) {
			extract(get_object_vars($result[0]));
			$yeschecked = ($soa_append == 'yes') ? 'checked' : '';
			$nochecked = ($soa_append == 'no') ? 'checked' : '';
			$action = $soa_template == 'yes' ? 'create' : 'update';
		} else {
			$action = 'create';
			$yeschecked = ($map == 'forward') ? 'checked' : '';
			$nochecked = ($yeschecked) ? '' : 'checked';
			extract($__FM_CONFIG['soa']);
		}
		
		if ($force_action != 'auto') {
			$action = $force_action;
		}
		
		if (array_search('template_menu', $show) !== false) {
			$soa_templates = buildSelect("{$action}[soa_template_chosen]", 'soa_template_chosen', $this->availableSOATemplates(sanitize($_GET['map'])), $soa_id);
			$soa_templates = sprintf('<div class="soa-template-dropdown">
		<strong>%s</strong>
		%s
	</div>', __('Select a Template'), $soa_templates);
			$soa_name = null;
		}
	
		if (array_search('create_template', $show) !== false) {
			$template_name_show_hide = 'none';
			$create_template = sprintf('<tr>
			<th>%s</th>
			<td><input type="checkbox" id="soa_create_template" name="%s[%d][soa_template]" value="yes" /><label for="soa_create_template"> %s</label></td>
		</tr>', __('Create Template'), $action, $soa_id, __('yes'));
		} else {
			$template_name_show_hide = 'table-row';
			$create_template = <<<HTML
			<input type="hidden" id="soa_create_template" name="{$action}[$soa_id][soa_template]" value="yes" />
			<input type="hidden" name="{$action}[$soa_id][soa_default]" value="no" />
HTML;
		}
	
		if (array_search('template_name', $show) !== false) {
			$soa_default_checked = ($soa_id == $this->getDefaultSOA()) ? 'checked' : null;
			$template_name = sprintf('<tr id="soa_template_name" style="display: %1$s">
			<th><label>%2$s</label></th>
			<td><input type="text" name="%3$s[%7$d][soa_name]" size="25" value="%4$s" class="required" /><br />
			<input type="checkbox" id="soa_default" name="%3$s[%7$d][soa_default]" value="yes" %5$s /><label for="soa_default"> %6$s</label></td>
		</tr>', $template_name_show_hide, __('Template Name'), $action, $soa_name,
					$soa_default_checked, __('Make Default Template'), $soa_id);
		}
		
		if (array_key_exists('map', $_GET) && sanitize($_GET['map']) == 'reverse') {
			$template_append = null;
		} else {
			$template_append = sprintf('<tr>
			<th>%s</th>
			<td><input type="radio" id="append[0]" name="%2$s[%3$s][soa_append]" value="yes" %4$s /><label class="radio" for="append[0]"> %6$s</label> <input type="radio" id="append[1]" name="%2$s[%3$s][soa_append]" value="no" %5$s /><label class="radio" for="append[1]"> %7$s</label></td>
		</tr>', __('Append Domain'), $action, $soa_id, $yeschecked, $nochecked, __('yes'), __('no'));
		}
		
		$labels = array(
			__('Master Server'),
			__('Email Address'),
			__('Refresh'),
			__('Retry'),
			__('Expire'),
			__('TTL'),
		);
	
		return <<<HTML
		$soa_templates
	<div id="custom-soa-form">
	<table class="form-table">
		<tr>
			<th><label>{$labels[0]}</label></th>
			<td><input type="text" name="{$action}[$soa_id][soa_master_server]" size="25" value="$soa_master_server" class="required" $disabled /></td>
		</tr>
		<tr>
			<th><label>{$labels[1]}</label></th>
			<td><input type="text" name="{$action}[$soa_id][soa_email_address]" size="25" value="$soa_email_address" class="required" $disabled /></td>
		</tr>
		<tr>
			<th><label>{$labels[2]}</label></th>
			<td><input type="text" name="{$action}[$soa_id][soa_refresh]" size="25" value="$soa_refresh" class="required" onkeydown="return validateTimeFormat(event, this)" $disabled /></td>
		</tr>
		<tr>
			<th><label>{$labels[3]}</label></th>
			<td><input type="text" name="{$action}[$soa_id][soa_retry]" size="25" value="$soa_retry" class="required" onkeydown="return validateTimeFormat(event, this)" $disabled /></td>
		</tr>
		<tr>
			<th><label>{$labels[4]}</label></th>
			<td><input type="text" name="{$action}[$soa_id][soa_expire]" size="25" value="$soa_expire" class="required" onkeydown="return validateTimeFormat(event, this)" $disabled /></td>
		</tr>
		<tr>
			<th><label>{$labels[5]}</label></th>
			<td><input type="text" name="{$action}[$soa_id][soa_ttl]" size="25" value="$soa_ttl" class="required" onkeydown="return validateTimeFormat(event, this)" $disabled /></td>
		</tr>
		$template_append
		$create_template
		$template_name
	</table>
	</div>
HTML;
	}
	
	
	function updateSOAReload($domain_id, $status = 'yes', $associated = 'single') {
		global $fmdb, $fm_dns_zones, $__FM_CONFIG;
		
		/** Check domain_id and soa */
		$parent_domain_ids = getZoneParentID($domain_id);
		if (isset($parent_domain_ids[2])) {
			$query = "SELECT * FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}domains d, fm_{$__FM_CONFIG['fmDNS']['prefix']}soa s WHERE domain_status='active' AND d.account_id='{$_SESSION['user']['account_id']}' AND
				s.soa_id=(SELECT soa_id FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}domains WHERE domain_id={$parent_domain_ids[2]})";
		} else {
			$query = "SELECT * FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}domains d, fm_{$__FM_CONFIG['fmDNS']['prefix']}soa s WHERE domain_status='active' AND d.account_id='{$_SESSION['user']['account_id']}' AND 
				s.soa_id=d.soa_id AND d.domain_id IN (" . join(',', $parent_domain_ids) . ')';
		}
		$fmdb->query($query);
		if (!$fmdb->num_rows) return false;
		
		$domain_reload = getNameFromID($domain_id, "fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", 'domain_', 'domain_id', 'domain_reload');

		if (!$domain_reload) return false;

		/** Update the SOA serial number */
		if ($domain_reload == 'no') {
			if (!isset($fm_dns_zones)) {
				include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_zones.php');
			}
			$fm_dns_zones->updateSOASerialNo($domain_id, getNameFromID($domain_id, "fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", 'domain_', 'domain_id', 'soa_serial_no'));
		}

		$id = ($domain_clone_domain_id = getNameFromID($domain_id, "fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", 'domain_', 'domain_id', 'domain_clone_domain_id')) ? $domain_clone_domain_id : $domain_id;
		reloadZoneSQL($id, $status, $associated);
	}
	
	
	/**
	 * Builds an array of skipped record IDs
	 *
	 * @since 1.2
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param int $domain_id Domain ID to check
	 * @return array
	 */
	function getSkippedRecordIDs($domain_id) {
		global $fmdb, $__FM_CONFIG;
		
		$skipped_records = array();
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records_skipped', $domain_id, 'record_', 'domain_id');
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$skipped_records[] = $result[$i]->record_id;
			}
		}
		
		return $skipped_records;
	}
	
	
	/**
	 * Builds an array of available SOA templates
	 *
	 * @since 1.3
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @return array
	 */
	function availableSOATemplates($map) {
		global $fmdb, $__FM_CONFIG;
		
		$return[0][] = __('Custom');
		$return[0][] = '0';
		
		if ($map == 'forward') {
			$query = "SELECT soa_id,soa_name FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}soa WHERE account_id='{$_SESSION['user']['account_id']}' 
				AND soa_status='active' AND soa_template='yes' ORDER BY soa_name ASC";
		} else {
			$query = "SELECT soa_id,soa_name FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}soa WHERE account_id='{$_SESSION['user']['account_id']}' 
				AND soa_status='active' AND soa_template='yes' AND soa_append='no' ORDER BY soa_name ASC";
		}
		$result = $fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$results = $fmdb->last_result;
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$return[$i+1][] = $results[$i]->soa_name;
				$return[$i+1][] = $results[$i]->soa_id;
			}
		}
		return $return;
	}
	
	
	/**
	 * Assigns SOA to domain_id
	 *
	 * @since 1.3
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param int $soa_id SOA ID to assign
	 * @param int $domain_id Domain ID to assign to
	 */
	function assignSOA($soa_id, $domain_id) {
		global $__FM_CONFIG, $fm_dns_zones;
		
		$old_soa_id = getNameFromID($domain_id, "fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", 'domain_', 'domain_id', 'soa_id');
		
		if (basicUpdate("fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", $domain_id, 'soa_id', $soa_id, 'domain_id')) {
			/** Delete old custom SOA */
			if (getNameFromID($old_soa_id, "fm_{$__FM_CONFIG['fmDNS']['prefix']}soa", 'soa_', 'soa_id', 'soa_template') == 'no') {
				updateStatus("fm_{$__FM_CONFIG['fmDNS']['prefix']}soa", $old_soa_id, 'soa_', 'deleted', 'soa_id');
			}

			if (!isset($fm_dns_zones)) {
				include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_zones.php');
			}
			/** Update the SOA serial number */
			foreach ($fm_dns_zones->getZoneTemplateChildren($domain_id) as $child_id) {
				$domain_id = getParentDomainID($child_id);
				if (reloadAllowed($domain_id) && getSOACount($domain_id) && getNSCount($domain_id)) {
					$this->updateSOAReload($child_id, 'yes');
				}
			}

			/** Log it */
			$domain_name = displayFriendlyDomainName(getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name'));
			$soa_name = getNameFromID($soa_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'soa', 'soa_', 'soa_id', 'soa_name');
			$old_soa_name = getNameFromID($old_soa_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'soa', 'soa_', 'soa_id', 'soa_name');
			if (!$old_soa_name) $old_soa_name = __('Custom');
			$log_message = __('Assigned a SOA template') . ":\n";
			$log_message .= ($domain_id) ? formatLogKeyData('', 'domain', $domain_name) : null;
			$log_message .= formatLogKeyData('', 'old soa', $old_soa_name);
			$log_message .= formatLogKeyData('', 'new soa', $soa_name);

			addLogEntry($log_message);
		}
	}
	
	
	/**
	 * Returns the default SOA ID
	 *
	 * @since 1.3.1
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @return integer
	 */
	function getDefaultSOA() {
		global $fmdb, $__FM_CONFIG;
		
		$query = "SELECT soa_id FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}soa WHERE account_id='{$_SESSION['user']['account_id']}' 
			AND soa_status='active' AND soa_default='yes' LIMIT 1";
		$result = $fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$results = $fmdb->last_result;
			return $results[0]->soa_id;
		}
		return false;
	}
	
	
	/**
	 * Sets SOA serials and reload flags per domain_id
	 *
	 * @since 2.1
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param int $domain_id domain_id to set
	 * @param string $record_type Record type to check
	 * @param string $action Add or update
	 * @return null
	 */
	function processSOAUpdates($domain_id, $record_type, $action) {
		global $fm_dns_zones, $__FM_CONFIG;
		
		if (!$fm_dns_zones) include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_zones.php');
		
		$possible_domain_ids = $fm_dns_zones->getZoneTemplateChildren($domain_id);
		if (count($possible_domain_ids)) {
			foreach($possible_domain_ids as $parent_id) {
				$possible_domain_ids = array_merge($possible_domain_ids, $fm_dns_zones->getZoneCloneChildren($parent_id));
			}
		}
		$possible_domain_ids = array_unique(array_merge($possible_domain_ids, $fm_dns_zones->getZoneCloneChildren($domain_id)));

		foreach ($possible_domain_ids as $child_id) {
			$domain_id = getParentDomainID($child_id);
			$soa_count = getSOACount($domain_id);
			$ns_count = getNSCount($domain_id);
			if (reloadAllowed($domain_id) && $soa_count && $ns_count) {
				$this->updateSOAReload($child_id, 'yes');
			}

			if ($action == 'add') {
				if (in_array($record_type, array('SOA', 'NS')) && $soa_count && $ns_count) {
					/** Update all associated DNS servers */
					setBuildUpdateConfigFlag(getZoneServers($child_id), 'yes', 'build');
				}
			}

			/** Set the domain_check_config flag */
			basicUpdate('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $child_id, 'domain_check_config', 'yes', 'domain_id');
		}
	}
	
	
	/**
	 * Gets the zone data from the servers
	 *
	 * @since 3.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param int $domain_id domain_id to get
	 * @return string
	 */
	function getServerZoneData($domain_id) {
		global $__FM_CONFIG, $fmdb;
		
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $domain_id, 'domain_', 'domain_id');
		if (!$fmdb->num_rows) {
			return sprintf('<p>%s</p>', __('An error occured retrieving the zone from the database.'));
		}
		extract(get_object_vars($fmdb->last_result[0]));
		
		/** Ensure this zone is setup for dynamic support */
		if ($domain_dynamic != 'yes') {
			return sprintf('<p>%s</p>', __('This zone does not support dynamic updates.'));
		}
		
		/** Get zone data from server */
		foreach (explode(',', getZoneServers($domain_id)) as $server_serial_no) {
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', $server_serial_no, 'server_', 'server_serial_no');
			if (!$fmdb->num_rows) {
				return sprintf('<p>%s</p>', sprintf(__('An error occured retrieving the associated servers from the database.  Could not find any active <a href="%s">name servers</a> for this zone.'), getMenuURL('Servers')));
			}
			extract(get_object_vars($fmdb->last_result[0]));
			
			if (@version_compare($server_client_version, '3.0-alpha1', '<')) continue;
			
			$file_ext = ($domain_mapping == 'forward') ? 'hosts' : 'rev';

			/** Set master/primary nomenclature */
			$master_dir = (version_compare($server_version, '9.13.0', '<')) ? 'master' : 'primary';
			
			/** Get zone data via ssh */
			if ($server_update_method == 'ssh') {
				$server_remote = runRemoteCommand($server_name, 'sudo php /usr/local/facileManager/fmDNS/client.php dump-zone -D ' . $domain_name . ' -f ' . $server_chroot_dir . $server_zones_dir . '/' . $master_dir . '/db.' . $domain_name . '.' . $file_ext, 'return', $server_update_port, 'include', 'window');
			} elseif (in_array($server_update_method, array('http', 'https'))) {
				/** Get zone data via http(s) */
				/** Test the port first */
				if (socketTest($server_name, $server_update_port, 10)) {
					/** Remote URL to use */
					$url = $server_update_method . '://' . $server_name . ':' . $server_update_port . '/fM/reload.php';

					/** Data to post to $url */
					$post_data = array('action' => 'get_zone_contents',
						'serial_no' => $server_serial_no,
						'domain_id' => $domain_id,
						'module' => $_SESSION['module'],
						'command_args' => 'dump-zone -D ' . $domain_name . ' -f ' . $server_chroot_dir . $server_zones_dir . '/' . $master_dir . '/db.' . $domain_name . '.' . $file_ext
					);

					$server_remote = getPostData($url, $post_data);
					if (isSerialized($server_remote)) {
						$server_remote = unserialize($server_remote);
					}
				}
			}
			
			if (isset($server_remote)) {
				if (is_array($server_remote)) {
					if (array_key_exists('output', $server_remote) && !count($server_remote['output'])) {
						unset($server_remote);
						continue;
					}

					if (isset($server_remote['failures']) && $server_remote['failures']) {
						return join("\n", $server_remote['output']);
					}
				} else {
					return '<pre>' . $server_remote . '</pre>';
				}
			}
		}
		
		/** Return if the zone did not get dumped from the server */
		if (!isset($server_remote['output'])) {
			$return = sprintf('<p>%s</p>', __('The zone could not be retrieved from the DNS servers. Possible causes include:'));
			$return .= sprintf('<ul><li>%s</li><li>%s</li><li>%s</li><li>%s</li></ul>',
					__('There are no active name servers configured for this zone'),
					__('The update ports on the active name servers are not accessible'),
					__('All name servers configured for this zone are not upgraded to at least 3.0-alpha1'),
					__('All name servers configured for this zone are updated via cron (only SSH and http/https are supported)'));
			return $return;
		}
		
		$_FILES['import-file']['tmp_name'] = $_FILES['import-file']['name'] = '/tmp/db.' . $domain_name;
		file_put_contents($_FILES['import-file']['tmp_name'], join("\n", $server_remote['output']));

		global $fm_module_tools;
		$module_tools_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class_tools.php';
		if (file_exists($module_tools_file) && !class_exists('fm_module_tools')) {
			include($module_tools_file);
		}
		
		return $fm_module_tools->zoneImportWizard($domain_id);
	}


	/**
	 * Creates a link for the RR type
	 *
	 * @since 5.0.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param string $record_type Record type to link to
	 * @return string
	 */
	private function getRRTypeLink($record_type) {
		$uri = array();
		foreach ($GLOBALS['URI'] as $k => $v) {
			if ($k == 'p') continue;
			if ($k == 'record_type') $v = $record_type;
			$uri[] = sprintf('%s=%s', $k, $v);
		}
		return sprintf('<a href="%s?%s">%s</s>', $GLOBALS['basename'], join('&', $uri), $record_type);
	}
	

	/**
	 * Validates the record for saving
	 *
	 * @since 7.0.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param string $output Output format to return
	 */
	function validateRecordUpdates($output = 'json') {
		global $__FM_CONFIG, $append;

		extract($_POST);
		if (isset($_POST['uri_params'])) {
			extract($_POST['uri_params'], EXTR_OVERWRITE);
		}

		/* RR types that allow record append */
		$append = array('CNAME', 'NS', 'MX', 'SRV', 'DNAME', 'RP', 'NAPTR');

		$create_update = 'update';
		$GLOBALS['new_cname_rrs'] = array();

		if (isset($_POST['create'])) {
			$create_update = 'create';
		}

		/** Get real record_type */
		if ($record_type == 'ALL') {
			$record_type = ($create_update == 'update') ? getNameFromID(array_keys($_POST[$create_update])[0], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', 'record_', 'record_id', 'record_type') : $_POST[$create_update][array_keys($_POST[$create_update])[0]]['record_type'];
		}

		if (isset($_POST[$create_update]['soa_template_chosen']) && $_POST[$create_update]['soa_template_chosen']) {
			/** Save the soa_template_chosen in domains table and end */
			$this->assignSOA($_POST[$create_update]['soa_template_chosen'], $domain_id);
			return 'Success';
		}
		unset($_POST[$create_update]['soa_template_chosen']);
	
		if (isset($_POST['update'])) {
			$_POST[$create_update] = $this->buildUpdateArray($domain_id, $record_type, $_POST[$create_update], $append);
		}

		/** Fix this! */
		$domain_info['id']          = $domain_id;
		$domain_info['name']        = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name');
		// $domain_info['map']         = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_mapping');
		// $domain_info['clone_of']    = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_clone_domain_id');
		// $domain_info['template_id'] = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_template_id');

		foreach ($_POST[$create_update] as $id => $data) {
			list($valid_data, $input_error) = $this->validateEntry($create_update, $id, $data, $record_type, $append, $_POST[$create_update], $domain_info);
			if (!isset($input_error)) unset($input_error);
		}

		if ($output == 'json') {
			header("Content-type: application/json");
			return json_encode(array($valid_data, $input_error));
		} else {
			return array($valid_data, $input_error);
		}
	}


	function buildUpdateArray($domain_id, $record_type, $data_array, $append) {
		$exclude_keys = array('record_skipped');
		if (!in_array($record_type, $append)) {
			$exclude_keys[] = 'record_append';
		}
		
		$sql_records = buildSQLRecords($record_type, $domain_id);
		if (!count($sql_records) && $record_type == 'SOA') {
			return $data_array;
		}
		$raw_changes = compareValues($data_array, $sql_records);
		if (count($raw_changes)) {
			foreach ($raw_changes as $i => $data_array) {
				foreach ($exclude_keys as $key) {
					if (!array_key_exists($key, $data_array)) {
						unset($sql_records[$i][$key]);
					}
				}
				$changes[$i] = array_merge((array) $sql_records[$i], (array) $raw_changes[$i]);
			}
		} else {
			return $data_array;
		}
		unset($sql_records, $raw_changes);
		
		return $changes;
	}


	function validateEntry($action, $id, $data, $record_type, $append, $data_array, $domain_info) {
		global $map, $__FM_CONFIG, $fmdb;

		$messages = array();
		
		if (!isset($data['record_append']) && in_array($record_type, $append)) {
			$data['record_append'] = 'no';
		}
		if (isset($data['Delete'])) {
			$data['record_status'] = 'deleted';
			unset($data['Delete']);
		}
		if ($record_type != 'SOA') {
			if (!empty($data['record_value'])) {
				$data['record_value'] = str_replace(array('"', "'"), '', $data['record_value']);
				if (strpos($data['record_value'], "\n")) {
					foreach (explode("\n", $data['record_value']) as $line) {
						$tmp_value[] = trim($line);
					}
					$data['record_value'] = join("\n", $tmp_value);
				}
			} elseif ($record_type != 'HINFO' && (!isset($data['record_value']) || empty($data['record_value']))) {
				$messages['errors']['record_value'] = __('This field cannot be empty.');
			}
			if ($record_type != 'PTR' && (!isset($data['record_name']) || empty($data['record_name']))) {
				$data['record_name'] = '@';
			}
			foreach ($data as $key => $val) {
				$data[$key] = trim($val, '"\'');
				
				if ($key == 'record_name' && $record_type != 'PTR') {
					if ($data['record_status'] == 'active' && !$this->verifyName($domain_info['id'], $val, $id, true, $record_type, $data_array)) {
						$messages['errors'][$key] = __('Names can only contain letters, numbers, hyphens (-), underscores (_), or @. However, @ is not valid for CNAME records. Additionally, the same name cannot exist for A and CNAME records.');
						continue;
					}
				}
				
				if (in_array($key, array('record_priority', 'record_weight', 'record_port'))) {
					if (!empty($val) && verifyNumber($val) === false) {
						$messages['errors'][$key] = __('This field must be a number.');
						continue;
					}
				}
				
				if ($key == 'record_ttl') {
					if (!empty($val) && $this->verifyTTL($val) === false) {
						$messages['errors'][$key] = __('This field must be in TTL format which consists of numbers and time-based letters (s, m, h, d, w, y). Examples include 300, 5d, or 1y4w9d.');
						continue;
					}
				}
				
				if ($record_type == 'A') {
					if ($key == 'record_value') {
						if (verifyIPAddress($val) === false) {
							$messages['errors'][$key] = __('IP address must be in valid IPv4 or IPv6 format.');
							continue;
						}
					}
					if ($key == 'PTR' && !isset($messages['errors']['record_value'])) {
						$retval = checkPTRZone($data['record_value'], $domain_info['id']);
						list($val, $error_msg) = $retval;
						if ($val == null) {
							$messages['errors']['record_value'] = $error_msg;
						} else {
							$data[$key] = $val;
						}
					}
				}
				
				if ($record_type == 'PTR') {
					if ($key == 'record_name') {
						if ($map == 'reverse') {
							if (verifyIPAddress(buildFullIPAddress($data['record_name'], $domain_info['name'])) === false) {
								$messages['errors'][$key] = __('Invalid record');
								continue;
							}
						} else {
							if (!$this->verifyCNAME('yes', $data['record_name'], false, true)) {
								$messages['errors'][$key] = __('Invalid record');
								continue;
							}
						}
					}
				}
				
				if ((in_array($record_type, array('CNAME', 'DNAME', 'MX', 'NS', 'SRV', 'NAPTR'))) || 
						$record_type == 'PTR' && $key == 'record_value') {
					if ($key == 'record_value') {
						$val = ((isset($data['record_append']) && $data['record_append'] == 'yes') || $val == '@') ? trim($val, '.') : trim($val, '.') . '.';
						$data[$key] = $val;
						if ((isset($data['record_append']) && !$this->verifyCNAME($data['record_append'], $val)) || ($record_type == 'NS' && !$this->validateHostname($val))) {
							$messages['errors'][$key] = __('Invalid value');
							continue;
						}
					}
				}
	
				if ($key == 'record_key_tag') {
					if ((!isset($data[$key])) || empty($data[$key])) {
						$messages['errors'][$key] = __('The Key Tag may not be empty.');
						continue;
					}
				}
			}
		} elseif ($record_type == 'SOA') {
			if (!isset($data['soa_append'])) {
				$data['soa_append'] = 'no';
			}
			foreach ($data as $key => $val) {
				if (in_array($key, array('domain_id', 'soa_status'))) continue;
				if ($key == 'soa_email_address') {
					if (!isEmailAddressValid($val) && !$this->verifyCNAME($data['soa_append'], $val, false, true)) {
						$messages['errors'][$key] = __('Invalid email address format');
						continue;
					}
					$val = strpos($val, '@') ? str_replace('@', '.', rtrim($val, '.') . '.') : $val ;
					$data[$key] = $val;
				}
				if (in_array($key, array('soa_master_server', 'soa_email_address'))) {
					$val = rtrim($val, '.');
					if (isset($_POST['update']) && strpos($_POST['update'][$id]['soa_master_server'], $domain_info['name']) && strpos($_POST['update'][$id]['soa_email_address'], $domain_info['name'])) {
						$new_val = rtrim(str_replace($domain_info['name'], '', $val), '.');
						if ($new_val != rtrim($val, '.')) {
							$data['soa_append'] = 'yes';
						}
						$val = $new_val;
					}
					if ($data['soa_append'] == 'no') {
						$val .= '.';
					}
				}
				if ($key != 'soa_append') {
					if (in_array($key, array('soa_master_server', 'soa_email_address'))) {
						$val = $data['soa_append'] == 'yes' ? trim($val, '.') : trim($val, '.') . '.';
						$data[$key] = $val;
						if (!$this->verifyCNAME($data['soa_append'], $val, false) || ($key == 'soa_master_server' && !$this->validateHostname($val))) {
							$messages['errors'][$key] = __('Invalid1');
							continue;
						}
					} else {
						if (in_array($key, array('soa_refresh', 'soa_retry', 'soa_expire', 'soa_ttl'))) {
							if (!empty($val) && $this->verifyTTL($val) === false) {
								$messages['errors'][$key] = __('Invalid');
								continue;
							}
						// } elseif (array_key_exists('soa_template', $data) && $data['soa_template'] == 'yes') {
							// if (!$this->verifyNAME($val, $id, false)) {
							// 	$messages['errors'][$key] = __('Invalid');
							// }
						}
					}
				}
				if ($key == 'soa_name') {
					/** Does the record already exist for this account? */
					basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'soa', $data['soa_name'], 'soa_', 'soa_name', "AND soa_template='yes' AND soa_id!='$id'");
					if ($fmdb->num_rows) $messages['errors'][$key] = __('This name already exists.');
				}
				
				// if (!isset($messages['errors']) || !count($messages['errors'])) {
				// 	$html .= buildInputReturn($action, $id, $key, $val);
				// } else $html = null;
			}
		} else {
			unset($data);
		}
		
		return array($data, $messages);
	}


	function verifyName($domain_id, $record_name, $id, $allow_null = true, $record_type = null, $data_array = null) {
		global $fmdb, $__FM_CONFIG;
		
		if (!$allow_null && !strlen($record_name)) return false;
		
		/** Ensure singleton RR type from existing records */
		$sql = ($record_type != 'CNAME') ? " AND record_type='CNAME'" : " AND record_id!=$id";
		basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', 'record_id', 'record_', "AND record_name='$record_name' AND domain_id=$domain_id $sql AND record_status='active'", null, false, 'ASC');
		if ($fmdb->num_rows) {
			foreach ($fmdb->last_result as $res_array) {
				if (isset($_POST['update']) && is_array($_POST['update']) 
					&& array_key_exists($res_array->record_id, $_POST['update']) 
					&& (array_key_exists('Delete', $_POST['update'][$res_array->record_id]) 
						|| $_POST['update'][$res_array->record_id]['record_status'] != 'active')) {
					continue;
				}
				if (array_key_exists($res_array->record_id, $data_array)) {
					if ($data_array[$res_array->record_id]['record_status'] == 'active') {
						return false;
					}
				} else {
					return false;
				}
			}
		}
		/** Ensure no updates create duplicate CNAME RR */
		// if (is_array($_POST['update']) && is_array($_POST['create']) && $record_type == 'CNAME') {
		// 	$tmp_array = array();
		// 	foreach ($_POST['update'] as $k => $v) {
		// 		if ($v['record_name'] == $record_name && $v['record_status'] == 'active' && !array_key_exists('Delete', $v)) $tmp_array[] = $_POST['update'][$k];
		// 	}
		// 	foreach ($_POST['create'] as $k => $v) {
		// 		if ($v['record_name'] == $record_name && $v['record_status'] == 'active') $tmp_array[] = $_POST['create'][$k];
		// 	}
		// 	if (count(array_column($tmp_array, 'record_status')) > 1) return false;
		// 	unset($tmp_array);
		// }

		/** Ensure singleton RR type from new records */
		if (in_array($record_name, $GLOBALS['new_cname_rrs'])) {
			return false;
		} elseif ($record_type == 'CNAME') {
			$GLOBALS['new_cname_rrs'][] = $record_name;
		}
		
		if (substr($record_name, 0, 1) == '*' && substr_count($record_name, '*') < 2) {
			return true;
		} elseif (preg_match('/^[a-z0-9_\-.]+$/i', $record_name) == true
				&& preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $record_name) == true) {
			if (in_array($record_type, array('A', 'MX'))) {
				return $this->validateHostname($record_name);
			}
			return true;
		} elseif ($record_name == '@' && $record_type != 'CNAME') {
			return true;
		}
		
		return false;
	}

	function verifyCNAME($append, $record, $allow_null = true, $allow_underscore = false) {
		if (!$allow_null && !strlen($record)) return false;
		
		if (preg_match('/^[a-z0-9_\-.]+$/i', $record) == true) {
			if ($append == 'yes') {
				if (strstr($record, '.') == false) {
					return true;
				} else {
					if (preg_match('/\d{1,3}\.\d{1,3}\-\d{1,3}/', $record)) return true;
				}
			} else {
				return substr($record, -1, 1) == '.';
			}
			return true;
		} else if (in_array($record, array('@', '*.'))) {
			return true;
		}
		return false;
	}

	/**
	 * Returns whether record hostname is valid or not
	 *
	 * @since 2.1
	 * @package fmDNS
	 *
	 * @param string $hostname Hostname to check
	 * @return boolean
	 */
	function validateHostname($hostname) {
		if ($hostname[0] == '-' || strpos($hostname, '_') !== false) {
			return false;
		}
		
		return true;
	}

	/**
	 * Returns whether record TTL is valid or not
	 *
	 * @since 3.0
	 * @package fmDNS
	 *
	 * @param string $ttl TTL to check
	 * @return boolean
	 */
	function verifyTTL($ttl) {
		/** Return true if $ttl is a number */
		if (verifyNumber($ttl)) return true;

		/** Ensure first character is a number */
		if (!verifyNumber(substr($ttl, 0, 1))) return false;
		
		/** Check if last character is a-z */
		if (!preg_match('/[a-z]/i', substr($ttl, -1))) return false;
		
		/** Check for s, m, h, d, w */
		preg_match_all('/\d+[a-z]/i', $ttl, $matches);
		
		/** Something is wrong */
		if (count($matches) > 1) return false;
		
		foreach ($matches[0] as $match) {
			$split = preg_split('/[smhdwy]/i', $match);
			if (!verifyNumber($split[0])) return false;
		}
		
		return true;
	}
}

if (!isset($fm_dns_records))
	$fm_dns_records = new fm_dns_records();
