<?php

class fm_dns_records {
	
	/**
	 * Displays the record list
	 */
	function rows($result, $record_type, $domain_id) {
		global $fmdb;
		
		if (!$result) {
			echo '<p id="noresult">There are no records.</p>';
		} else {
			$header = $this->getHeader(strtoupper($record_type));
			?>
			<table class="display_results">
				<thead>
					<tr>
						<?php echo $header; ?>
					</tr>
				</thead>
				<tbody>
					<?php
					$num_rows = $fmdb->num_rows;
					$results = $fmdb->last_result;
					for ($x=0; $x<$num_rows; $x++) {
						echo $this->getInputForm(strtoupper($record_type), false, $domain_id, $results[$x]);
					}
					?>
				</tbody>
			</table>
			<?php
		}
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
		$sql_fields = '(domain_id,';
		$sql_values = "$domain_id,";
		if ($record_type != 'SOA' && $record_type) {
			$sql_fields .= 'record_type,';
			$sql_values .= "'$record_type',";
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
				$log_message .= $data ? ucwords(str_replace('_', ' ', str_replace('record_', '', $key))) . ": $data\n" : null;
			}
		}
		$sql_fields = rtrim($sql_fields, ',') . ')';
		$sql_values = rtrim($sql_values, ',');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if (!$result) return false;

		/** Update the SOA serial number */
		$this->updateSOAReload($domain_id);

		addLogEntry($log_message);
		return true;
	}

	/**
	 * Updates the selected record
	 */
	function update($domain_id, $id, $record_type, $array) {
		global $fmdb, $__FM_CONFIG;
		
		$domain_name = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name');
		$record_name = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', 'record_', 'record_id', 'record_name');
		$log_message = "Updated a record ($record_name) with the following details:\nDomain: $domain_name\nType: $record_type\n";

		$table = ($record_type == 'SOA') ? 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'soa' : 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records';
		$field = ($record_type == 'SOA') ? 'soa_id' : 'record_id';
		
		$record_type_sql = ($record_type != 'SOA') ? ",record_type='$record_type'" : null;
		
//		if (empty($array['record_ttl'])) $array['record_ttl'] = 300;
		
		$sql_edit = null;
		
		foreach ($array as $key => $data) {
			$sql_edit .= $key . "='" . mysql_real_escape_string($data) . "',";
			$log_message .= $data ? ucwords(str_replace('_', ' ', str_replace('record_', '', $key))) . ": $data\n" : null;
		}
		$sql_edit = rtrim($sql_edit, ',');
		
		// Update the record
		$query = "UPDATE `$table` SET $sql_edit $record_type_sql WHERE `$field`='$id' AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		if (!$result) return false;

		/** Update the SOA serial number */
		$this->updateSOAReload($domain_id);

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
		
		$header = $this->getHeader(strtoupper($record_type));
		$body = $this->getInputForm(strtoupper($record_type), true, $domain_id);
		?>
		<table class="display_results">
			<thead>
				<tr>
					<?php echo $header; ?>
				</tr>
			</thead>
			<tbody id="more_records">
				<?php echo $body; ?>
				<tr>
					<td>
						<p><a id="add_records" href="#">Add more records</a></p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	function getHeader($type) {
		$header = null;
		$head_values['Record'] = null;
		if ($type != 'SOA') {
			$head_values['Class'] = $head_values['TTL'] = null;
		}
		$head_values['Value'] = null;
				
		$append = array('CNAME', 'NS', 'MX', 'SRV');
		$priority = array('MX', 'SRV');
		
		if (in_array($type, $priority)) $head_values['Priority'] = null;
		
		if ($type == 'SRV') {
			$head_values['Weight'] = null;
			$head_values['Port'] = null;
		}
		
		$head_values['Comment'] = null;
		
		if (in_array($type, $append)) $head_values['Append Domain'] = ' style="text-align: center;" nowrap';
		
		if ($type != 'SOA') $head_values['Status'] = ' style="text-align: center;"';
		if (empty($_POST)) {
			$head_values['Actions'] = ' style="text-align: center;"';
		} else {
			$head_values['Valid'] = ' style="text-align: center;"';
			$head_values = array_merge(array('Action' => null), $head_values);
		}
		
		foreach ($head_values as $key => $val) {
			$header .= "<th$val>$key</th>\n";
		}
		
		return $header;
	}

	function getInputForm($type, $new, $domain_id, $results = null, $start = 1) {
		global $allowed_to_manage_records, $allowed_to_manage_zones, $__FM_CONFIG, $zone_access_allowed;
		
		$form = $record_status = $record_class = $record_name = $record_ttl = null;
		$record_value = $record_comment = $record_priority = $record_weight = $record_port = null;
		$action = ($new) ? 'create' : 'update';
		$end = ($new) ? $start + 3 : 1;
				
		$append = array('CNAME', 'NS', 'MX', 'SRV');
		$priority = array('MX', 'SRV');

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
		
		$class = buildSelect($action . '[_NUM_][record_class]', '_NUM_', enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', 'record_class'), $record_class);

		if (($allowed_to_manage_records || $allowed_to_manage_zones) && $zone_access_allowed) {
			if ($type == 'PTR') {
				$input_box = '<input style="width: 40px;" size="4" type="text" name="' . $action . '[_NUM_][record_name]" value="' . $record_name . '" />';
				$domain = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name');
				if (strpos($domain, 'arpa')) {
					$field_values['Record'] = '>' . $input_box . ' .' . $domain;
				} else {
					$field_values['Record'] = '>' . $domain . '. ' . $input_box;
				}
			} else {
				$field_values['Record'] = '><input size="40" type="text" name="' . $action . '[_NUM_][record_name]" value="' . $record_name . '" />';
			}
			$field_values['TTL'] = '><input style="width: 35px;" type="text" name="' . $action . '[_NUM_][record_ttl]" value="' . $record_ttl . '" />';
			$field_values['Class'] = '>' . $class;
			$field_values['Value'] = '><input size="40" type="text" name="' . $action . '[_NUM_][record_value]" value="' . $record_value . '" />';
			
			if (in_array($type, $priority)) $field_values['Priority'] = '><input style="width: 35px;" type="text" name="' . $action . '[_NUM_][record_priority]" value="' . $record_priority . '" />';
	
			if ($type == 'SRV') {
				$field_values['Weight'] = '><input style="width: 35px;" type="text" name="' . $action . '[_NUM_][record_weight]" value="' . $record_weight . '" />';
				$field_values['Port'] = '><input style="width: 35px;" type="text" name="' . $action . '[_NUM_][record_port]" value="' . $record_port . '" />';
			}
		
			$field_values['Comment'] = '><input maxlength="200" type="text" name="' . $action . '[_NUM_][record_comment]" value="' . $record_comment . '" />';
			
			if (in_array($type, $append)) $field_values['Append Domain'] = ' align="center"><label><input ' . $yeschecked . ' type="radio" name="' . $action . '[_NUM_][record_append]" value="yes" /> yes</label> <label><input ' . $nochecked . ' type="radio" name="' . $action . '[_NUM_][record_append]" value="no" /> no</label>';
			
			$field_values['Status'] = ' align="center">' . $status;

			if ($new) {
				$field_values['Actions'] = ($type == 'A') ? ' align="center"><label><input style="height: 10px;" type="checkbox" name="' . $action . '[_NUM_][PTR]" />Create PTR</label>' : null;
			} else {
				$field_values['Actions'] = ' align="center"><label><input style="height: 10px;" type="checkbox" name="' . $action . '[_NUM_][Delete]" />Delete</label>';
			}
		} else {
			$field_values['Record'] = '>' . $record_name;
			$field_values['TTL'] = '>' . $record_ttl;
			$field_values['Class'] = '>' . $record_class;
			$field_values['Value'] = '>' . $record_value;
			$field_values['Comment'] = '>' . $record_comment;
			
			if (in_array($type, $priority)) $field_values['Priority'] = '>' . $record_priority;
			
			if ($type == 'SRV') {
				$field_values['Weight'] = '>' . $record_weight;
				$field_values['Port'] = '>' . $record_port;
			}
		
			if (in_array($type, $append)) $field_values['Append Domain'] = ' style="text-align: center;">' . $record_append;
		
			$field_values['Status'] = ' style="text-align: center;">' . $record_status;
			$field_values['Actions'] = ' style="text-align: center;">N/A';
		}
		
		for ($i=$start; $i<=$end; $i++) {
			$form .= "<tr>\n";
			foreach ($field_values as $key => $val) {
				$val = (!$val) ? '>' : $val;
				$num = ($new) ? $i : $record_id;
				$val = str_replace('_NUM_', $num, $val);
				$form .= "\t<td$val</td>\n";
			}
			$form .= "</tr>\n";
		}
		
		return $form;
	}
	
	function buildSOA($result, $soa_id) {
		global $__FM_CONFIG, $disabled;
		
		if ($result) {
			$action = 'update';
			extract(get_object_vars($result[0]));
			$yeschecked = ($soa_append == 'yes') ? 'checked' : '';
			$nochecked = ($soa_append == 'no') ? 'checked' : '';
		} else {
			$action = 'create';
			$SOAID = 0;
			$yeschecked = 'checked';
			$nochecked = '';
			extract($__FM_CONFIG['soa']);
		}
	
		$SerialForm = "<span id=\"serial1\" onclick=\"exchange(this);\" style=\"cursor: pointer;\">AutoGen</span><input maxlength=\"9\" onblur=\"exchange(this);\" name=\"{$action}[$soa_id][soa_serial_no]\" id=\"serial1b\" class=\"replace\" type=\"text\" value=\"\">";
				
		echo <<<HTML
	<table class="form-table">
		<tr>
			<th width="120">Serial Number</th>
			<td>$SerialForm</td>
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
			<td><label><input type="radio" name="{$action}[$soa_id][soa_append]" value="yes" $yeschecked /> yes</label> <label><input type="radio" name="{$action}[$soa_id][soa_append]" value="no" $nochecked /> no</label></td>
		</tr>
	</table>
HTML;
	}
	
	
	function updateSOAReload($domain_id, $status = 'yes') {
		global $fmdb, $fm_dns_zones, $__FM_CONFIG;
		
		/** Check domain_id and soa */
		$query = "select * from fm_{$__FM_CONFIG['fmDNS']['prefix']}domains d, fm_{$__FM_CONFIG['fmDNS']['prefix']}soa s where domain_status='active' and d.account_id='{$_SESSION['user']['account_id']}' and s.domain_id=d.domain_id and d.domain_id=$domain_id";
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
}

if (!isset($fm_dns_records))
	$fm_dns_records = new fm_dns_records();

?>
