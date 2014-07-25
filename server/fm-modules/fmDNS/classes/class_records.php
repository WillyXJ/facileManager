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
*/

class fm_dns_records {
	
	/**
	 * Displays the record list
	 */
	function rows($result, $record_type, $domain_id, $page) {
		global $fmdb, $__FM_CONFIG;
		
		$return = null;
		
		if (!$result) {
			$return = '<p id="table_edits" class="noresult">There are no ' . $record_type . ' records.</p>';
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
	function add($domain_id, $record_type, $new_array) {
		global $fmdb, $__FM_CONFIG;
		
		$domain_name = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name');
		$log_message = "Added a record with the following details:\nDomain: $domain_name\nType: $record_type\n";

		$table = ($record_type == 'SOA') ? 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'soa' : 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records';
		
		/** Check if record already exists */
		if ($record_type != 'SOA') {
			$query = "SELECT * FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}records WHERE domain_id=$domain_id AND record_name='{$new_array['record_name']}'
					AND record_value='{$new_array['record_value']}' AND record_type='$record_type' AND record_status != 'deleted'";
			$result = $fmdb->get_results($query);
			
			if ($fmdb->num_rows) {
				return;
			}
		}
		
		$sql_insert = "INSERT INTO `$table`";
		$sql_fields = '(';
		if ($record_type != 'SOA' && $record_type) {
			$sql_fields .= 'domain_id,record_type,';
			$sql_values .= "$domain_id,'$record_type',";
		}
		
		$new_array['account_id'] = $_SESSION['user']['account_id'];
		
		/** Process default integers */
		if (array_key_exists('record_priority', $new_array) && !is_numeric($new_array['record_priority'])) $new_array['record_priority'] = 0;
		if (array_key_exists('record_weight', $new_array) && !is_numeric($new_array['record_weight'])) $new_array['record_weight'] = 0;
		if (array_key_exists('record_port', $new_array) && !is_numeric($new_array['record_port'])) $new_array['record_port'] = 0;
		if (array_key_exists('record_ttl', $new_array) && !is_numeric($new_array['record_ttl'])) $new_array['record_ttl'] = null;

		foreach ($new_array as $key => $data) {
			if ($key == 'PTR') continue;
			if ($key == 'record_type') continue;
			if ($record_type == 'SOA' && in_array($key, array('record_type', 'record_comment'))) continue;
			$sql_fields .= $key . ',';
			$sql_values .= "'" . mysql_real_escape_string($data) . "',";
			if ($key != 'account_id') {
				$log_message .= $data ? formatLogKeyData('record_', $key, $data) : null;
			}
		}
		$sql_fields = rtrim($sql_fields, ',') . ')';
		$sql_values = rtrim($sql_values, ',');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
//		echo $query; exit;
		$result = $fmdb->query($query);
		
		if (!$fmdb->result) return false;
		
		/** Update domain with SOA ID */
		if ($record_type == 'SOA') {
			$this->assignSOA($fmdb->insert_id, $domain_id);
		}

		/** Update the SOA serial number */
		$soa_count = getSOACount($domain_id);
		$ns_count = getNSCount($domain_id);
		if (reloadAllowed($domain_id) && $soa_count && $ns_count) {
			$this->updateSOAReload($domain_id);
		}

		if (in_array($record_type, array('SOA', 'NS')) && $soa_count && $ns_count) {
			/** Update all associated DNS servers */
			setBuildUpdateConfigFlag(getZoneServers($domain_id), 'yes', 'build');
		}

		addLogEntry($log_message);
		return true;
	}

	/**
	 * Updates the selected record
	 */
	function update($domain_id, $id, $record_type, $array, $skipped_record = false) {
		global $fmdb, $__FM_CONFIG;
		
		$domain_name = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name');
		$record_name = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', 'record_', 'record_id', 'record_name');
		$log_message = "Updated a record ($record_name) with the following details:\nDomain: $domain_name\nType: $record_type\n";

		$table = ($record_type == 'SOA') ? 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'soa' : 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records';
		$field = ($record_type == 'SOA') ? 'soa_id' : 'record_id';
		
		$record_type_sql = ($record_type != 'SOA') ? ",record_type='$record_type'" : null;
		
		$null_keys = array('record_key_tag');
		
		$sql_edit = null;
		
		foreach ($array as $key => $data) {
			if ($key == 'record_skipped') continue;
			if (in_array($key, $null_keys) && empty($data)) {
				$sql_edit .= $key . '=NULL,';
			} else {
				$sql_edit .= $key . "='" . mysql_real_escape_string(str_replace("\r\n", "\n", $data)) . "',";
			}
			if ($key != 'record_status' && !$skipped_record) $log_message .= $data ? formatLogKeyData('record_', $key, $data) : null;
		}
		$sql_edit = rtrim($sql_edit, ',');
		
		/** Update the record */
		if ($skipped_record) {
			$table .= '_skipped';
			$query = "SELECT * FROM `$table` WHERE account_id={$_SESSION['user']['account_id']} AND domain_id=$domain_id AND record_id=$id";
			$result = $fmdb->query($query);
			if ($fmdb->num_rows) {
				$query = "UPDATE `$table` SET domain_id=$domain_id, record_id=$id, record_status='{$array['record_status']}' WHERE account_id={$_SESSION['user']['account_id']} AND domain_id=$domain_id AND record_id=$id";
			} else {
				$query = "INSERT INTO `$table` VALUES({$_SESSION['user']['account_id']}, $domain_id, $id, '{$array['record_status']}')";
			}
			$data = $array['record_status'] == 'active' ? 'no' : 'yes';
			$log_message .= formatLogKeyData(null, 'Included', $data);
		} else {
			$query = "UPDATE `$table` SET $sql_edit $record_type_sql WHERE `$field`='$id' AND `account_id`='{$_SESSION['user']['account_id']}'";
		}
		$result = $fmdb->query($query);
		
		if (!$fmdb->result) return false;

		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		/** Update the SOA serial number */
		if (reloadAllowed($domain_id) && getSOACount($domain_id) && getNSCount($domain_id)) {
			$this->updateSOAReload($domain_id);
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
		$return .= <<<HTML
			</tbody>
		</table>
		<p class="add_records"><a id="add_records" href="#">+ Add more records</a></p>
HTML;
		return $return;
	}

	function getHeader($type) {
		global $zone_access_allowed;
		
		$show_value = true;
		if ($type == 'SOA') {
			$show_value = false;
			$title_array[] = array('title' => 'Name', 'rel' => 'soa_name');
			$title_array[] = array('title' => 'Master', 'rel' => 'soa_master_server');
			$title_array[] = array('title' => 'E-mail', 'rel' => 'soa_email_address');
			$title_array[] = array('title' => 'Refresh', 'rel' => 'soa_refresh');
			$title_array[] = array('title' => 'Retry', 'rel' => 'soa_retry');
			$title_array[] = array('title' => 'Expire', 'rel' => 'soa_expire');
			$title_array[] = array('title' => 'TTL', 'rel' => 'soa_ttl');
		}
		if ($type != 'SOA') {
			$title_array[] = array('title' => 'Record', 'rel' => 'record_name');
			$title_array[] = array('title' => 'TTL', 'rel' => 'record_ttl');
			$title_array[] = array('title' => 'Class', 'rel' => 'record_class');
		}
		if ($type == 'CERT' ) {
			$title_array[] = array('title' => 'Type', 'rel' => 'record_cert_type');
			$title_array[] = array('title' => 'Key Tag', 'rel' => 'record_key_tag');
		}
		if (in_array($type, array('CERT', 'SSHFP'))) {
			$title_array[] = array('title' => 'Algorithm', 'rel' => 'record_algorithm');
		}
		if ($type == 'HINFO') {
			$title_array[] = array('title' => 'Hardware', 'rel' => 'record_value');
			$title_array[] = array('title' => 'OS', 'rel' => 'record_os');
			$show_value = false;
		}
		if (in_array($type, array('DNSKEY', 'KEY'))) {
			$title_array[] = array('title' => 'Flags', 'rel' => 'record_flags');
			$title_array[] = array('title' => 'Algorithm', 'rel' => 'record_algorithm');
		}
		
		if ($show_value) $title_array[] = array('title' => 'Value', 'rel' => 'record_value');
				
		if ($type == 'RP' ) {
			$title_array[] = array('title' => 'Text', 'rel' => 'record_text');
		}
		
		$append = array('CNAME', 'NS', 'MX', 'SRV', 'DNAME', 'CERT', 'RP');
		$priority = array('MX', 'SRV', 'KX');
		
		if (in_array($type, $priority)) $title_array[] = array('title' => 'Priority', 'rel' => 'record_priority');
		
		if ($type == 'SRV') {
			$title_array[] = array('title' => 'Weight', 'rel' => 'record_weight');
			$title_array[] = array('title' => 'Port', 'rel' => 'record_port');
		}
		
		if ($type != 'SOA') {
			$title_array[] = array('title' => 'Comment', 'rel' => 'record_comment');
		}
		
		if (in_array($type, $append)) $title_array[] = array('title' => 'Append Domain', 'class' => 'header-nosort', 'style' => 'text-align: center;', 'nowrap' => null, 'rel' => 'record_append');
		
		if ($type != 'SOA') $title_array[] = array('title' => 'Status', 'rel' => 'record_status');
		if (empty($_POST)) {
			if ((currentUserCan('manage_records', $_SESSION['module']) || currentUserCan('manage_zones', $_SESSION['module'])) && $zone_access_allowed) $title_array[] = array('title' => 'Actions', 'class' => 'header-actions header-nosort');
		} else {
			$title_array[] = array('title' => 'Valid', 'style' => 'text-align: center;');
			array_unshift($title_array, 'Action');
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
		
		$append = array('CNAME', 'NS', 'MX', 'SRV', 'DNAME', 'CERT', 'RP');
		$priority = array('MX', 'SRV', 'KX');

		if ($results) {
			$results = get_object_vars($results);
			extract($results);
		}
		$yeschecked = (isset($record_append) && $record_append == 'yes') ? 'checked' : '';
		$nochecked = (isset($record_append) && $record_append == 'no') ? 'checked' : '';
		
		$statusopt[0][] = 'Active';
		$statusopt[0][] = 'active';
		$statusopt[1][] = 'Disabled';
		$statusopt[1][] = 'disabled';
		$status = BuildSelect($action . '[_NUM_][record_status]', '_NUM_', $statusopt, $record_status);
		$field_values['class'] = $record_status;
		
		$class = buildSelect($action . '[_NUM_][record_class]', '_NUM_', enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', 'record_class'), $record_class);

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
			$field_values['data']['TTL'] = '><input style="width: 35px;" type="text" name="' . $action . '[_NUM_][record_ttl]" value="' . $record_ttl . '" onkeydown="return validateNumber(event)" />';
			$field_values['data']['Class'] = '>' . $class;
			
			if ($type == 'CERT') {
				$field_values['data']['Type'] = '>' . buildSelect($action . '[_NUM_][record_cert_type]', '_NUM_', $__FM_CONFIG['records']['cert_types'], $record_cert_type);
				$field_values['data']['Key Tag'] = '><input style="width: 45px;" type="text" name="' . $action . '[_NUM_][record_key_tag]" value="' . $record_key_tag . '" onkeydown="return validateNumber(event)" />';
				$field_values['data']['Algorithm'] = '>' . buildSelect($action . '[_NUM_][record_algorithm]', '_NUM_', $__FM_CONFIG['records']['cert_algorithms'], $record_algorithm);
				$value_textarea = true;
			}
			
			if ($type == 'SSHFP') {
				$field_values['data']['Algorithm'] = '>' . buildSelect($action . '[_NUM_][record_algorithm]', '_NUM_', $__FM_CONFIG['records']['sshfp_algorithms'], $record_algorithm);
				$value_textarea = true;
			}
			
			if ($type == 'HINFO') {
				$field_values['data']['Hardware'] = '><input maxlength="255" type="text" name="' . $action . '[_NUM_][record_value]" value="' . $record_value . '" />';
				$field_values['data']['OS'] = '><input maxlength="255" type="text" name="' . $action . '[_NUM_][record_os]" value="' . $record_os . '" />';
				$show_value = false;
			}
			
			if (in_array($type, array('DNSKEY', 'KEY'))) {
				$flags = enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', 'record_flags');
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
			
			if ($show_value) {
				if ($value_textarea) {
					$field_values['data']['Value'] = '><textarea rows="2" name="' . $action . '[_NUM_][record_value]">' . $record_value . '</textarea>';
				} else {
					$field_values['data']['Value'] = '><input size="40" type="text" name="' . $action . '[_NUM_][record_value]" value="' . $record_value . '" />';
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
			
			if (in_array($type, $append)) $field_values['data']['Append Domain'] = ' align="center"><input ' . $yeschecked . ' type="radio" id="record_append[_NUM_][0]" name="' . $action . '[_NUM_][record_append]" value="yes" /><label class="radio" for="record_append[_NUM_][0]"> yes</label> <input ' . $nochecked . ' type="radio" id="record_append[_NUM_][1]" name="' . $action . '[_NUM_][record_append]" value="no" /><label class="radio" for="record_append[_NUM_][1]"> no</label>';
			
			$field_values['data']['Status'] = '>' . $status;

			if ($new) {
				$field_values['data']['Actions'] = in_array($type, array('A', 'AAAA')) ? ' align="center"><input type="checkbox" id="record_ptr[_NUM_]" name="' . $action . '[_NUM_][PTR]" /><label for="record_ptr[_NUM_]">Create PTR</label>' : null;
			} else {
				$field_values['data']['Actions'] = ' align="center"><input type="checkbox" id="record_delete[' . $record_id . ']" name="' . $action . '[_NUM_][Delete]" /><label for="record_delete[' . $record_id . ']">Delete</label>';
			}
		} else {
			$domain = strlen($domain) > 23 ? substr($domain, 0, 20) . '...' : $domain;
			$field_values['data']['Record'] = '>' . $record_name . '<span class="grey">.' . $domain . '</span>';
			$field_values['data']['TTL'] = '>' . $record_ttl;
			$field_values['data']['Class'] = '>' . $record_class;
			if ($show_value) $field_values['data']['Value'] = '>' . $record_value;
			$field_values['data']['Comment'] = '>' . $record_comment;
			
			if (in_array($type, $priority)) $field_values['data']['Priority'] = '>' . $record_priority;
			
			if ($type == 'SRV') {
				$field_values['data']['Weight'] = '>' . $record_weight;
				$field_values['data']['Port'] = '>' . $record_port;
			}
		
			if (in_array($type, $append)) $field_values['data']['Append Domain'] = ' style="text-align: center;">' . $record_append;
		
			$field_values['data']['Status'] = '>' . $record_status;
			
			if ((currentUserCan('manage_records', $_SESSION['module']) || currentUserCan('manage_zones', $_SESSION['module'])) && $zone_access_allowed && $domain_id != $parent_domain_id) {
				$field_values['data']['Actions'] = ' align="center"><input type="hidden" name="' . $action . '[_NUM_][record_skipped]" value="off" /><label><input type="checkbox" name="' . $action . '[_NUM_][record_skipped]" ';
				$field_values['data']['Actions'] .= in_array($record_id, $this->getSkippedRecordIDs($parent_domain_id)) ? ' checked' : null;
				$field_values['data']['Actions'] .= '/>Skip Import</label>';
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
	
	function buildSOA($result) {
		global $__FM_CONFIG, $disabled;
		
		$soa_id = 0;
		
		if ($result) {
			extract(get_object_vars($result[0]));
			$yeschecked = ($soa_append == 'yes') ? 'checked' : '';
			$nochecked = ($soa_append == 'no') ? 'checked' : '';
			$action = $soa_template == 'yes' ? 'create' : 'update';
		} else {
			$action = 'create';
			$yeschecked = ($_GET['map'] == 'forward') ? 'checked' : '';
			$nochecked = ($yeschecked) ? '' : 'checked';
			extract($__FM_CONFIG['soa']);
		}
		
		$soa_templates = buildSelect("{$action}[soa_template_chosen]", 'soa_template_chosen', $this->availableSOATemplates(), $soa_id);
	
		return <<<HTML
	<div class="soa-template-dropdown">
		<strong>Select a Template</strong>
		$soa_templates
	</div>
	<div id="custom-soa-form">
	<table class="form-table">
		<tr>
			<th width="120">Serial Number</th>
			<td><span style="position: relative; top: -2px;">Auto Generated</span></td>
		</tr>
		<tr>
			<th>Master Server</th>
			<td><input type="text" name="{$action}[$soa_id][soa_master_server]" size="25" value="$soa_master_server" $disabled /></td>
		</tr>
		<tr>
			<th>Email Address</th>
			<td><input type="text" name="{$action}[$soa_id][soa_email_address]" size="25" value="$soa_email_address" $disabled /></td>
		</tr>
		<tr>
			<th>Refresh</th>
			<td><input type="text" name="{$action}[$soa_id][soa_refresh]" size="25" value="$soa_refresh" $disabled /></td>
		</tr>
		<tr>
			<th>Retry</th>
			<td><input type="text" name="{$action}[$soa_id][soa_retry]" size="25" value="$soa_retry" $disabled /></td>
		</tr>
		<tr>
			<th>Expire</th>
			<td><input type="text" name="{$action}[$soa_id][soa_expire]" size="25" value="$soa_expire" $disabled /></td>
		</tr>
		<tr>
			<th>TTL</th>
			<td><input type="text" name="{$action}[$soa_id][soa_ttl]" size="25" value="$soa_ttl" $disabled /></td>
		</tr>
		<tr>
			<th>Append Domain</th>
			<td><input type="radio" id="append[0]" name="{$action}[$soa_id][soa_append]" value="yes" $yeschecked /><label class="radio" for="append[0]"> yes</label> <input type="radio" id="append[1]" name="{$action}[$soa_id][soa_append]" value="no" $nochecked /><label class="radio" for="append[1]"> no</label></td>
		</tr>
		<tr>
			<th>Create Template</th>
			<td><input type="checkbox" id="soa_create_template" name="{$action}[$soa_id][soa_template]" value="yes" checked /><label for="soa_create_template"> yes</label></td>
		</tr>
		<tr id="soa_template_name">
			<th>Template Name</th>
			<td><input type="text" name="{$action}[$soa_id][soa_name]" size="25" /></td>
		</tr>
	</table>
	</div>
HTML;
	}
	
	
	function updateSOAReload($domain_id, $status = 'yes') {
		global $fmdb, $fm_dns_zones, $__FM_CONFIG;
		
		/** Check domain_id and soa */
		$query = "select * from fm_{$__FM_CONFIG['fmDNS']['prefix']}domains d, fm_{$__FM_CONFIG['fmDNS']['prefix']}soa s where domain_status='active' and d.account_id='{$_SESSION['user']['account_id']}' and s.soa_id=d.soa_id and d.domain_id=$domain_id";
		$result = $fmdb->query($query);
		if (!$fmdb->num_rows) return false;

		$domain_details = $fmdb->last_result;
		extract(get_object_vars($domain_details[0]), EXTR_SKIP);

		/** Update the SOA serial number */
		if ($domain_reload == 'no') {
			if (!isset($fm_dns_zones)) {
				include_once(ABSPATH . 'fm-modules/fmDNS/classes/class_zones.php');
			}
			$fm_dns_zones->updateSOASerialNo($domain_id, $soa_serial_no);
		}

		reloadZoneSQL($domain_id, $status);
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
	function availableSOATemplates() {
		global $fmdb, $__FM_CONFIG;
		
		$return[0][] = 'Custom';
		$return[0][] = '0';
		
		$query = "SELECT soa_id,soa_name FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}soa WHERE account_id='{$_SESSION['user']['account_id']}' 
			AND soa_status='active' AND soa_template='yes' ORDER BY soa_name ASC";
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
	 * Builds an array of available SOA templates
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
		global $__FM_CONFIG;
		
		$old_soa_id = getNameFromID($domain_id, "fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", 'domain_', 'domain_id', 'soa_id');
		
		if (basicUpdate("fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", $domain_id, 'soa_id', $soa_id, 'domain_id')) {
			/** Delete old custom SOA */
			if (getNameFromID($old_soa_id, "fm_{$__FM_CONFIG['fmDNS']['prefix']}soa", 'soa_', 'soa_id', 'soa_template') == 'no') {
				updateStatus("fm_{$__FM_CONFIG['fmDNS']['prefix']}soa", $old_soa_id, 'soa_', 'deleted', 'soa_id');
			}
		}
	}
	
	
}

if (!isset($fm_dns_records))
	$fm_dns_records = new fm_dns_records();

?>
