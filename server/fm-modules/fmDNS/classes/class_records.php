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
		
		$return = null;
		
		if (!$result) {
			$return = sprintf('<p id="table_edits" class="noresult">%s</p>', sprintf(__('There are no %s records.'), $record_type));
		} else {
			$results = $fmdb->last_result;
			$start = $_SESSION['user']['record_count'] * ($page - 1);
			$end = $_SESSION['user']['record_count'] * $page > $fmdb->num_rows ? $fmdb->num_rows : $_SESSION['user']['record_count'] * $page;

			$table_info = array('class' => 'display_results sortable');

			$return = displayTableHeader($table_info, $this->getHeader(strtoupper($record_type)));
			
			for ($x=$start; $x<$end; $x++) {
				$return .= $this->getInputForm(strtoupper($record_type), false, $domain_id, $results[$x]);
			}
			
			$return .= "</tbody>\n</table>\n";
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
			if ($record_type == 'NAPTR') {
				$query .= " AND record_params='{$new_array['record_params']}' AND record_regex='{$new_array['record_regex']}'";
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
				$result = $fmdb->query($query);
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
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
		
		$domain_name = displayFriendlyDomainName(getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name'));
		$record_name = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', 'record_', 'record_id', 'record_name');
		$log_message = "Updated a record ($record_name) with the following details:\nDomain: $domain_name\nType: $record_type\n";

		$table = ($record_type == 'SOA') ? 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'soa' : 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records';
		$field = ($record_type == 'SOA') ? 'soa_id' : 'record_id';
		
		$record_type_sql = ($record_type != 'SOA') ? ",record_type='$record_type'" : null;
		
		$excluded_keys = array('record_skipped', 'PTR');
		$null_keys = array('record_key_tag');
		
		$sql_edit = null;
		
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
				$result = $fmdb->query($query);
			}
		}
		$sql_edit = rtrim($sql_edit, ', ');
		
		/** Update the record */
		if ($skipped_record) {
			$table .= '_skipped';
			$query = "SELECT * FROM `$table` WHERE account_id={$_SESSION['user']['account_id']} AND domain_id=$domain_id AND record_id=$id";
			$result = $fmdb->query($query);
			if ($fmdb->num_rows) {
				$query = "UPDATE `$table` SET domain_id=$domain_id, record_id=$id, record_status='{$array['record_status']}' WHERE account_id={$_SESSION['user']['account_id']} AND domain_id=$domain_id AND record_id=$id";
			} else {
				$query = "INSERT INTO `$table` VALUES(NULL, {$_SESSION['user']['account_id']}, $domain_id, $id, '{$array['record_status']}')";
			}
			$data = $array['record_status'] == 'active' ? 'no' : 'yes';
			$log_message .= formatLogKeyData(null, 'Included', $data);
		} else {
			$query = "UPDATE `$table` SET $sql_edit $record_type_sql WHERE `$field`='$id' AND `account_id`='{$_SESSION['user']['account_id']}'";
		}
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) return false;

		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		$domain_id_array = array($domain_id);
		if ($record_type == 'SOA' && !$domain_id) {
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
		if ($record_type == 'PTR' && $array['record_status'] == 'deleted') {
			basicUpdate('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $id, 'record_ptr_id', 0, 'record_ptr_id');
		}

		addLogEntry($log_message);
		return $result;
	}
	
	
	/**
	 * Displays the form to add new record
	 */
	function printRecordsForm($data = '', $action = 'create', $record_type, $domain_id) {
		$ucf_action = ucfirst($action);
		
		if (!empty($_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		$table_info = array('class' => 'display_results');

		$return = displayTableHeader($table_info, $this->getHeader(strtoupper($record_type)), 'more_records');
		$return .= $this->getInputForm(strtoupper($record_type), true, $domain_id);
		$return .= sprintf('</tbody>
		</table>
		<p class="add_more add_records"><a id="add_records" href="#">+ %s</a></p>', __('Add more records'));
		
		return $return;
	}

	function getHeader($type) {
		global $zone_access_allowed;
		
		$show_value = true;
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
			$title_array[] = array('title' => __('Name Servers'), 'rel' => $type . '_name_servers');
			$title_array[] = array('title' => __('Views'), 'rel' => $type . '_views');
			$title_array[] = array('title' => __('Map'), 'rel' => $type . '_mapping');
			$title_array[] = array('title' => __('Type'), 'rel' => $type . '_type');
		}
		if (!in_array($type, array('SOA', 'DOMAIN'))) {
			$title_array[] = array('title' => __('Record'), 'rel' => 'record_name');
			$title_array[] = array('title' => __('TTL'), 'rel' => 'record_ttl');
			$title_array[] = array('title' => __('Class'), 'rel' => 'record_class');
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
			$title_array[] = array('title' => __('Type'), 'rel' => 'record_params');
		}
		
		if ($type == 'TLSA') {
			$title_array[] = array('title' => __('Usage'), 'rel' => 'record_priority');
			$title_array[] = array('title' => __('Selector'), 'rel' => 'record_weight');
			$title_array[] = array('title' => __('Type'), 'rel' => 'record_port');
		}
		
		if ($show_value) $title_array[] = array('title' => __('Value'), 'rel' => 'record_value');
				
		if ($type == 'RP' ) {
			$title_array[] = array('title' => __('Text'), 'rel' => 'record_text');
		}
		
		$append = array('CNAME', 'NS', 'MX', 'SRV', 'DNAME', 'CERT', 'RP', 'NAPTR');
		$priority = array('MX', 'SRV', 'KX');
		
		if (in_array($type, $priority)) $title_array[] = array('title' => __('Priority'), 'rel' => 'record_priority');
		
		if ($type == 'SRV') {
			$title_array[] = array('title' => __('Weight'), 'rel' => 'record_weight');
			$title_array[] = array('title' => __('Port'), 'rel' => 'record_port');
		}
		
		if (!in_array($type, array('SOA', 'DOMAIN'))) {
			$title_array[] = array('title' => _('Comment'), 'rel' => 'record_comment');
		}
		
		if (in_array($type, $append)) $title_array[] = array('title' => __('Append Domain'), 'class' => 'header-nosort', 'style' => 'text-align: center;', 'nowrap' => null, 'rel' => 'record_append');
		
		if (!in_array($type, array('SOA', 'DOMAIN'))) $title_array[] = array('title' => __('Status'), 'rel' => 'record_status');
		if (empty($_POST) || $type == 'DOMAIN') {
			if ((currentUserCan('manage_records', $_SESSION['module']) || currentUserCan('manage_zones', $_SESSION['module'])) && $zone_access_allowed) $title_array[] = array('title' => __('Actions'), 'class' => 'header-actions header-nosort');
		} else {
			array_unshift($title_array, __('Action'));
			array_unshift($title_array, array('title' => __('Valid'), 'style' => 'text-align: center;'));
		}
		
		return $title_array;
	}

	function getInputForm($type, $new, $parent_domain_id, $results = null, $start = 1) {
		global $__FM_CONFIG, $zone_access_allowed;
		
		$form = $record_status = $record_class = $record_name = $record_ttl = null;
		$record_value = $record_comment = $record_priority = $record_weight = $record_port = null;
		$action = ($new) ? 'create' : 'update';
		$end = ($new) ? $start + 3 : 1;
		$show_value = true;
		$value_textarea = false;
		
		$append = array('CNAME', 'NS', 'MX', 'SRV', 'DNAME', 'CERT', 'RP', 'NAPTR');
		$priority = array('MX', 'SRV', 'KX');

		if ($results) {
			$results = get_object_vars($results);
			extract($results);
		}
		$yeschecked = (isset($record_append) && $record_append == 'yes') ? 'checked' : '';
		$nochecked = (isset($record_append) && $record_append == 'no') ? 'checked' : '';
		
		$statusopt[0][] = __('Active');
		$statusopt[0][] = 'active';
		$statusopt[1][] = __('Disabled');
		$statusopt[1][] = 'disabled';
		$status = BuildSelect($action . '[_NUM_][record_status]', 'status__NUM_', $statusopt, $record_status);
		$field_values['class'] = $record_status;
		
		$class = buildSelect($action . '[_NUM_][record_class]', 'class__NUM_', enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', 'record_class'), $record_class);

		if ($type == 'PTR') {
			$domain_map = getNameFromID($parent_domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_mapping');
		}
		$domain = getNameFromID($parent_domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name');
		
		if ((currentUserCan('manage_records', $_SESSION['module']) || currentUserCan('manage_zones', $_SESSION['module'])) && $zone_access_allowed && ($new || $domain_id == $parent_domain_id)) {
			if ($type == 'PTR') {
				$input_box = '<input ';
				$input_box .= ($domain_map == 'forward') ? 'size="40"' : 'style="width: 40px;" size="4"';
				$input_box .= ' type="text" name="' . $action . '[_NUM_][record_name]" value="' . $record_name . '" />';
				if (strpos($domain, 'arpa')) {
					$field_values['data']['Record'] = '>' . $input_box . ' .' . $domain;
				} elseif ($domain_map == 'forward') {
					$field_values['data']['Record'] = '>' . $input_box;
				} else {
					$field_values['data']['Record'] = '>' . $domain . '. ' . $input_box;
				}
			} else {
				$field_values['data']['Record'] = '><input size="40" type="text" name="' . $action . '[_NUM_][record_name]" value="' . $record_name . '" />';
			}
			$field_values['data']['TTL'] = '><input style="width: 35px;" type="text" name="' . $action . '[_NUM_][record_ttl]" value="' . $record_ttl . '" onkeydown="return validateTimeFormat(event, this)" />';
			$field_values['data']['Class'] = '>' . $class;
			
			if ($type == 'CAA') {
				$field_values['data']['Flags'] = '>' . buildSelect($action . '[_NUM_][record_params]', '_NUM_', $__FM_CONFIG['records']['caa_flags'], $record_params);
			}
			
			if ($type == 'CERT') {
				$field_values['data']['Type'] = '>' . buildSelect($action . '[_NUM_][record_cert_type]', '_NUM_', $__FM_CONFIG['records']['cert_types'], $record_cert_type);
				$field_values['data']['Key Tag'] = '><input style="width: 45px;" type="text" name="' . $action . '[_NUM_][record_key_tag]" value="' . $record_key_tag . '" onkeydown="return validateNumber(event)" />';
				$field_values['data']['Algorithm'] = '>' . buildSelect($action . '[_NUM_][record_algorithm]', '_NUM_', $__FM_CONFIG['records']['cert_algorithms'], $record_algorithm);
				$value_textarea = true;
			}
			
			if ($type == 'SSHFP') {
				$field_values['data']['Algorithm'] = '>' . buildSelect($action . '[_NUM_][record_algorithm]', '_NUM_', $__FM_CONFIG['records']['sshfp_algorithms'], $record_algorithm);
				$field_values['data']['Type'] = '>' . buildSelect($action . '[_NUM_][record_cert_type]', '_NUM_', $__FM_CONFIG['records']['digest_types'], $record_cert_type);
				$value_textarea = true;
			}
			
			if ($type == 'HINFO') {
				$field_values['data']['Hardware'] = '><input maxlength="255" type="text" name="' . $action . '[_NUM_][record_value]" value="' . $record_value . '" />';
				$field_values['data']['OS'] = '><input maxlength="255" type="text" name="' . $action . '[_NUM_][record_os]" value="' . $record_os . '" />';
				$show_value = false;
			}
			
			if (in_array($type, array('DNSKEY', 'KEY'))) {
				$flags = $__FM_CONFIG['records']['flags'];
				$algorithms = $__FM_CONFIG['records']['cert_algorithms'];
				$value_textarea = true;
				
				if ($type == 'KEY') {
					array_pop($flags);
					for ($i=1; $i<=4; $i++) {
						array_pop($algorithms);
					}
				}
				
				$field_values['data']['Flags'] = '>' . buildSelect($action . '[_NUM_][record_flags]', '_NUM_', $flags, $record_flags);
				$field_values['data']['Algorithm'] = '>' . buildSelect($action . '[_NUM_][record_algorithm]', '_NUM_', $algorithms, $record_algorithm);
			}
			
			if ($type == 'NAPTR') {
				$field_values['data']['Order'] = '><input maxlength="5" style="width: 35px;" type="text" name="' . $action . '[_NUM_][record_weight]" value="' . $record_weight . '" onkeydown="return validateNumber(event)" />';
				$field_values['data']['Pref'] = '><input maxlength="5" style="width: 35px;" type="text" name="' . $action . '[_NUM_][record_priority]" value="' . $record_priority . '" onkeydown="return validateNumber(event)" />';
				$field_values['data']['Flags'] = '>' . buildSelect($action . '[_NUM_][record_flags]', '_NUM_', $__FM_CONFIG['records']['naptr_flags'], $record_flags);
				$field_values['data']['Params'] = '><input maxlength="255" style="width: 100px;" type="text" name="' . $action . '[_NUM_][record_params]" value="' . $record_params . '" />';
				$field_values['data']['Regex'] = '><input maxlength="255" style="width: 100px;" type="text" name="' . $action . '[_NUM_][record_regex]" value="' . $record_regex . '" />';
				$field_values['data']['Replace'] = '><input maxlength="255" type="text" name="' . $action . '[_NUM_][record_value]" value="' . $record_value . '" />';
				$show_value = false;
			}
			
			if ($type == 'TLSA') {
				$tmp_flags1 = $__FM_CONFIG['records']['tlsa_flags'];
				array_pop($tmp_flags1);
				$tmp_flags2 = $tmp_flags1;
				array_pop($tmp_flags1);
				$field_values['data']['Priority'] = '>' . buildSelect($action . '[_NUM_][record_priority]', '_NUM_', $__FM_CONFIG['records']['tlsa_flags'], $record_priority);
				$field_values['data']['Weight'] = '>' . buildSelect($action . '[_NUM_][record_weight]', '_NUM_', $tmp_flags1, $record_weight);
				$field_values['data']['Port'] = '>' . buildSelect($action . '[_NUM_][record_port]', '_NUM_', $tmp_flags2, $record_port);
			}
			
			if ($type == 'OPENPGPKEY') {
				$value_textarea = true;
			}
			
			if (in_array($type, array('DHCID', 'DLV', 'DS'))) {
				if (in_array($type, array('DLV', 'DS'))) {
					$field_values['data']['Key Tag'] = '><input style="width: 45px;" type="text" name="' . $action . '[_NUM_][record_key_tag]" value="' . $record_key_tag . '" onkeydown="return validateNumber(event)" />';
					$field_values['data']['Algorithm'] = '>' . buildSelect($action . '[_NUM_][record_algorithm]', '_NUM_', $__FM_CONFIG['records']['cert_algorithms'], $record_algorithm);
					if ($type == 'DS') {
						$field_values['data']['Type'] = '>' . buildSelect($action . '[_NUM_][record_cert_type]', '_NUM_', $__FM_CONFIG['records']['digest_types'], $record_cert_type);
					}
				}
				$value_textarea = true;
			}
			
			if ($show_value) {
				if ($value_textarea) {
					$field_values['data']['Value'] = '><textarea rows="2" name="' . $action . '[_NUM_][record_value]">' . $record_value . '</textarea>';
				} else {
					$field_values['data']['Value'] = '><input size="40" type="text" name="' . $action . '[_NUM_][record_value]" value="' . $record_value . '" />';
				}

				/** Linked PTR */
				if (isset($record_ptr_id) && $record_ptr_id) {
					$field_values['data']['Value'] .= ' <a href="#" class="tooltip-right" data-tooltip="' . __('Linked PTR exists') . '"><i class="mini-icon fa fa-exchange"></i></a>';
				}
			}
			
			if ($type == 'RP') {
				$field_values['data']['Text'] = '><input maxlength="255" type="text" name="' . $action . '[_NUM_][record_text]" value="' . $record_text . '" />';
			}
			
			if (in_array($type, $priority)) $field_values['data']['Priority'] = '><input style="width: 35px;" type="text" name="' . $action . '[_NUM_][record_priority]" value="' . $record_priority . '" onkeydown="return validateNumber(event)" />';
	
			if ($type == 'SRV') {
				$field_values['data']['Weight'] = '><input style="width: 35px;" type="text" name="' . $action . '[_NUM_][record_weight]" value="' . $record_weight . '" onkeydown="return validateNumber(event)" />';
				$field_values['data']['Port'] = '><input style="width: 35px;" type="text" name="' . $action . '[_NUM_][record_port]" value="' . $record_port . '" onkeydown="return validateNumber(event)" />';
			}
		
			$field_values['data']['Comment'] = '><input maxlength="200" type="text" name="' . $action . '[_NUM_][record_comment]" value="' . $record_comment . '" />';
			
			if (in_array($type, $append)) $field_values['data']['Append Domain'] = ' align="center"><input ' . $yeschecked . ' type="radio" id="record_append[_NUM_][0]" name="' . $action . '[_NUM_][record_append]" value="yes" /><label class="radio" for="record_append[_NUM_][0]"> ' . __('yes') . '</label> <input ' . $nochecked . ' type="radio" id="record_append[_NUM_][1]" name="' . $action . '[_NUM_][record_append]" value="no" /><label class="radio" for="record_append[_NUM_][1]"> ' . __('no') . '</label>';
			
			$field_values['data']['Status'] = '>' . $status;

			if ($new) {
				$field_values['data']['Actions'] = in_array($type, array('A')) ? ' align="center"><label><input type="checkbox" name="' . $action . '[_NUM_][PTR]" />' . __('Create PTR') . '</label>' : null;
			} else {
				$field_values['data']['Actions'] = ' align="center">';
				if (in_array($type, array('A'))) {
					$field_values['data']['Actions'] .= '<label><input type="checkbox" name="' . $action . '[_NUM_][PTR]" />';
					$field_values['data']['Actions'] .= ($record_ptr_id) ? __('Update PTR') : __('Create PTR');
					$field_values['data']['Actions'] .= '</label><br />';
				}
				$field_values['data']['Actions'] .= '<label><input type="checkbox" id="record_delete_' . $record_id . '" name="' . $action . '[_NUM_][Delete]" />' . _('Delete') . '</label>';
			}
		} else {
			$domain = strlen($domain) > 23 ? substr($domain, 0, 20) . '...' : $domain;
			$field_values['data']['Record'] = '>' . $record_name . '<span class="grey">.' . $domain . '</span>';
			$field_values['data']['TTL'] = '>' . $record_ttl;
			$field_values['data']['Class'] = '>' . $record_class;
			
			if ($type == 'NAPTR') {
				$field_values['data']['Order'] = '>' . $record_weight;
				$field_values['data']['Pref'] = '>' . $record_priority;
				$field_values['data']['Flags'] = '>' . $record_flags;
				$field_values['data']['Params'] = '>' . $record_params;
				$field_values['data']['Regex'] = '>' . $record_regex;
			}
			
			if ($type == 'TLSA') {
				$field_values['data']['Priority'] = '>' . $record_priority;
				$field_values['data']['Weight'] = '>' . $record_weight;
				$field_values['data']['Port'] = '>' . $record_port;
			}
			
			if ($show_value) $field_values['data']['Value'] = '>' . $record_value;
			
			if (in_array($type, $priority)) $field_values['data']['Priority'] = '>' . $record_priority;
			
			if ($type == 'SRV') {
				$field_values['data']['Weight'] = '>' . $record_weight;
				$field_values['data']['Port'] = '>' . $record_port;
			}
		
			$field_values['data']['Comment'] = '>' . $record_comment;
			
			if (in_array($type, $append)) $field_values['data']['Append Domain'] = ' style="text-align: center;">' . $record_append;
		
			$field_values['data']['Status'] = '>' . $record_status;
			
			if ((currentUserCan('manage_records', $_SESSION['module']) || currentUserCan('manage_zones', $_SESSION['module'])) && $zone_access_allowed && $domain_id != $parent_domain_id) {
				$field_values['data']['Actions'] = ' align="center"><input type="hidden" name="' . $action . '[_NUM_][record_skipped]" value="off" /><label><input type="checkbox" name="' . $action . '[_NUM_][record_skipped]" ';
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
		
		for ($i=$start; $i<=$end; $i++) {
			$form .= '<tr class="' . $field_values['class'] . '">' . "\n";
			foreach ($field_values['data'] as $key => $val) {
				$val = (!$val) ? '>' : $val;
				$num = ($new) ? $i : $record_id;
				$val = str_replace('_NUM_', $num, $val);
				$form .= "\t<td$val</td>\n";
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
			<th>%2$s</th>
			<td><input type="text" name="%3$s[%7$d][soa_name]" size="25" value="%4$s" /><br />
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
			<th>{$labels[0]}</th>
			<td><input type="text" name="{$action}[$soa_id][soa_master_server]" size="25" value="$soa_master_server" $disabled /></td>
		</tr>
		<tr>
			<th>{$labels[1]}</th>
			<td><input type="text" name="{$action}[$soa_id][soa_email_address]" size="25" value="$soa_email_address" $disabled /></td>
		</tr>
		<tr>
			<th>{$labels[2]}</th>
			<td><input type="text" name="{$action}[$soa_id][soa_refresh]" size="25" value="$soa_refresh" onkeydown="return validateTimeFormat(event, this)" $disabled /></td>
		</tr>
		<tr>
			<th>{$labels[3]}</th>
			<td><input type="text" name="{$action}[$soa_id][soa_retry]" size="25" value="$soa_retry" onkeydown="return validateTimeFormat(event, this)" $disabled /></td>
		</tr>
		<tr>
			<th>{$labels[4]}</th>
			<td><input type="text" name="{$action}[$soa_id][soa_expire]" size="25" value="$soa_expire" onkeydown="return validateTimeFormat(event, this)" $disabled /></td>
		</tr>
		<tr>
			<th>{$labels[5]}</th>
			<td><input type="text" name="{$action}[$soa_id][soa_ttl]" size="25" value="$soa_ttl" onkeydown="return validateTimeFormat(event, this)" $disabled /></td>
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
		$result = $fmdb->query($query);
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
	 * @param id $domain_id Domain ID to check
	 * @return array
	 */
	function getSkippedRecordIDs($domain_id) {
		global $fmdb, $__FM_CONFIG;
		
		$skipped_records = null;
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
	 * @param id $soa_id SOA ID to assign
	 * @param id $domain_id Domain ID to assign to
	 * @return boolean
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
	 * @param id $domain_id domain_id to set
	 * @param id $record_type Record type to check
	 * @param id $action Add or update
	 * @return null
	 */
	function processSOAUpdates($domain_id, $record_type, $action) {
		global $fm_dns_zones;
		
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
		}
	}
	
	
	/**
	 * Gets the zone data from the servers
	 *
	 * @since 3.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param id $domain_id domain_id to get
	 * @return null
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
			
			if (version_compare($server_client_version, '3.0-alpha1', '<')) continue;
			
			$file_ext = ($domain_mapping == 'forward') ? 'hosts' : 'rev';
			
			/** Get zone data via ssh */
			if ($server_update_method == 'ssh') {
				$server_remote = runRemoteCommand($server_name, 'sudo php /usr/local/facileManager/fmDNS/client.php dump-zone -D ' . $domain_name . ' -f ' . $server_chroot_dir . $server_zones_dir . '/master/db.' . $domain_name . '.' . $file_ext, 'return', $server_update_port);
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
						'command_args' => 'dump-zone -D ' . $domain_name . ' -f ' . $server_chroot_dir . $server_zones_dir . '/master/db.' . $domain_name . '.' . $file_ext
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

		$module_tools_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class_tools.php';
		if (file_exists($module_tools_file) && !class_exists('fm_module_tools')) {
			include($module_tools_file);
		}
		
		return $fm_module_tools->zoneImportWizard($domain_id);
	}
	
}

if (!isset($fm_dns_records))
	$fm_dns_records = new fm_dns_records();

?>
