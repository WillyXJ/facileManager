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
 | fmFirewall: Easily manage one or more software firewalls                |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmfirewall/                        |
 +-------------------------------------------------------------------------+
*/

class fm_module_services {
	
	/**
	 * Displays the service list
	 */
	function rows($result, $type) {
		global $fmdb;
		
		if (!$result) {
			echo '<p id="table_edits" class="noresult" name="services">There are no ' . strtoupper($type) . ' services defined.</p>';
		} else {
			$num_rows = $fmdb->num_rows;
			$results = $fmdb->last_result;
			
			$table_info = array(
							'class' => 'display_results',
							'id' => 'table_edits',
							'name' => 'services'
						);

			$title_array = ($type == 'icmp') ? array('Service Name', 'ICMP Type', 'ICMP Code', 'Comment') : array('Service Name', 'Source Ports', 'Dest Ports', 'Flags', 'Comment');
			if (currentUserCan('manage_services', $_SESSION['module'])) $title_array[] = array('title' => 'Actions', 'class' => 'header-actions');

			echo displayTableHeader($table_info, $title_array);
			
			for ($x=0; $x<$num_rows; $x++) {
				$this->displayRow($results[$x]);
			}
			
			echo "</tbody>\n</table>\n";
		}
	}

	/**
	 * Adds the new service
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}services`";
		$sql_fields = '(';
		$sql_values = null;
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		
		$exclude = array('submit', 'action', 'service_id', 'compress', 'AUTHKEY', 'module_name', 'module_type', 'config', 'port_src', 'port_dest');

		foreach ($post as $key => $data) {
			$clean_data = sanitize($data);
			if (($key == 'service_name') && empty($clean_data)) return 'No service name defined.';
			if (!in_array($key, $exclude)) {
				$sql_fields .= $key . ',';
				$sql_values .= "'$clean_data',";
			}
		}
		$sql_fields = rtrim($sql_fields, ',') . ')';
		$sql_values = rtrim($sql_values, ',');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if (!$fmdb->result) return 'Could not add the service because a database error occurred.';

		addLogEntry("Added service:\nName: {$post['service_name']}\nType: {$post['service_type']}\n" .
				"Update Method: {$post['service_update_method']}\nConfig File: {$post['service_config_file']}");
		return true;
	}

	/**
	 * Updates the selected service
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		$exclude = array('submit', 'action', 'service_id', 'compress', 'AUTHKEY', 'module_name', 'module_type', 'config', 'SERIALNO', 'port_src', 'port_dest');

		$sql_edit = null;
		
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . sanitize($data) . "',";
			}
		}
		$sql = rtrim($sql_edit, ',');
		
		// Update the service
		$old_name = getNameFromID($post['service_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', 'service_', 'service_id', 'service_name');
		$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}services` SET $sql WHERE `service_id`={$post['service_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		if (!$fmdb->result) return 'Could not update the service because a database error occurred.';
		
		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

//		setBuildUpdateConfigFlag(getServerSerial($post['service_id'], $_SESSION['module']), 'yes', 'build');
		
		addLogEntry("Updated service '$old_name' to:\nName: {$post['service_name']}\nType: {$post['service_type']}\n" .
					"Update Method: {$post['service_update_method']}\nConfig File: {$post['service_config_file']}");
		return true;
	}
	
	/**
	 * Deletes the selected service
	 */
	function delete($service_id) {
		global $fmdb, $__FM_CONFIG;
		
		/** Does the service_id exist for this account? */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', $service_id, 'service_', 'service_id');
		if ($fmdb->num_rows) {
			/** Is the service_id present in a policy? */
			if (isItemInPolicy($service_id, 'service')) return 'This service could not be deleted because it is associated with one or more policies.';
			
			/** Delete service */
			$tmp_name = getNameFromID($service_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', 'service_', 'service_id', 'service_name');
			if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', $service_id, 'service_', 'deleted', 'service_id')) {
				addLogEntry("Deleted service '$tmp_name'.");
				return true;
			}
		}
		
		return 'This service could not be deleted.';
	}


	function displayRow($row) {
		global $__FM_CONFIG;
		
		$disabled_class = ($row->service_status == 'disabled') ? ' class="disabled"' : null;
		
		$edit_status = null;
		
		if (currentUserCan('manage_services', $_SESSION['module'])) {
			$edit_status = '<a class="edit_form_link" name="' . $row->service_type . '" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			if (!isItemInPolicy($row->service_id, 'service')) $edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			$edit_status = '<td id="edit_delete_img">' . $edit_status . '</td>';
		}
		
		$edit_name = $row->service_name;
		
		/** Process TCP Flags */
		if ($row->service_type == 'tcp') {
			$service_tcp_flags = $this->getTCPFlags($row->service_tcp_flags);
		} else $service_tcp_flags = null;
		
		echo <<<HTML
			<tr id="$row->service_id"$disabled_class>
				<td>$row->service_name</td>

HTML;
		if ($row->service_type == 'icmp') {
			$icmp_type = ($row->service_icmp_type == -1) ? 'any' : $row->service_icmp_type;
			$icmp_code = ($row->service_icmp_code == -1) ? 'any' : $row->service_icmp_code;
			echo <<<HTML
				<td>$icmp_type</td>
				<td>$icmp_code</td>

HTML;
		} else {
			$src_ports = ($row->service_src_ports) ? str_replace(':', ' &rarr; ', $row->service_src_ports) : 'any';
			$dest_ports = ($row->service_dest_ports) ? str_replace(':', ' &rarr; ', $row->service_dest_ports) : 'any';
			
			echo <<<HTML
				<td>$src_ports</td>
				<td>$dest_ports</td>
				<td>$service_tcp_flags</td>

HTML;
		}
		echo <<<HTML
				<td>$row->service_comment</td>
				$edit_status
			</tr>

HTML;
	}

	/**
	 * Displays the form to add new service
	 */
	function printForm($data = '', $action = 'add', $type = 'icmp') {
		global $__FM_CONFIG;
		
		$service_id = 0;
		$service_name = $service_tcp_flags = $service_comment = null;
		$service_icmp_type = $service_icmp_code = null;
		$ucaction = ucfirst($action);
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}

		/** Show/hide divs */
		if ($type == 'icmp') {
			$icmp_option = 'block';
			$tcpudp_option = $tcp_option = 'none';
		} elseif ($type == 'tcp') {
			$icmp_option = 'none';
			$tcpudp_option = $tcp_option = 'block';
		} else {
			$icmp_option = $tcp_option = 'none';
			$tcpudp_option = 'block';
		}

		$service_name_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', 'service_name');
		$service_type = buildSelect('service_type', 'service_type', enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', 'service_type'), $type, 1);
		
		@list($port_src_start, $port_src_end) = explode(':', $service_src_ports);
		@list($port_dest_start, $port_dest_end) = explode(':', $service_dest_ports);
		
		/** Process TCP Flags */
		@list($tcp_flag_mask, $tcp_flag_settings) = explode(':', $service_tcp_flags);
		$tcp_flags_mask_form = $tcp_flags_settings_form = $tcp_flags_head = null;
		foreach ($__FM_CONFIG['tcp_flags'] as $flag => $bit) {
			$tcp_flags_head .= '<th title="' . $flag .'">' . $flag[0] . "</th>\n";
			
			$tcp_flags_mask_form .= '<td><input type="checkbox" name="service_tcp_flags[mask][' . $bit . ']" ';
			if ($bit & $tcp_flag_mask) $tcp_flags_mask_form .= 'checked';
			$tcp_flags_mask_form .= "/></td>\n";

			$tcp_flags_settings_form .= '<td><input type="checkbox" name="service_tcp_flags[settings][' . $bit . ']" ';
			if ($bit & $tcp_flag_settings) $tcp_flags_settings_form .= 'checked';
			$tcp_flags_settings_form .= "/></td>\n";
		}
		
		$return_form = <<<FORM
		<form name="manage" id="manage" method="post" action="?type=$type">
			<input type="hidden" name="action" value="$action" />
			<input type="hidden" name="service_id" value="$service_id" />
			<table class="form-table">
				<tr>
					<th width="33%" scope="row"><label for="service_name">Service Name</label></th>
					<td width="67%"><input name="service_name" id="service_name" type="text" value="$service_name" size="40" placeholder="http" maxlength="$service_name_length" /></td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="service_type">Service Type</label></th>
					<td width="67%">
						$service_type
						<div id="icmp_option" style="display: $icmp_option;">
							<label for="service_icmp_type">Type</label> <input type="number" name="service_icmp_type" value="$service_icmp_type" style="width: 5em;" onkeydown="return validateNumber(event)" placeholder="0" max="40" /><br />
							<label for="service_icmp_code">Code</label> <input type="number" name="service_icmp_code" value="$service_icmp_code" style="width: 5em;" onkeydown="return validateNumber(event)" placeholder="0" max="15" />
						</div>
						<div id="tcpudp_option" style="display: $tcpudp_option;">
							<h4>Source Port Range</h4>
							<label for="port_src_start">Start</label> <input type="number" name="port_src[]" value="$port_src_start" placeholder="0" style="width: 5em;" onkeydown="return validateNumber(event)" max="65535" /> 
							<label for="port_src_end">End</label> <input type="number" name="port_src[]" value="$port_src_end" placeholder="0" style="width: 5em;" onkeydown="return validateNumber(event)" max="65535" />
							<h4>Destination Port Range</h4>
							<label for="port_dest_start">Start</label> <input type="number" name="port_dest[]" value="$port_dest_start" placeholder="0" style="width: 5em;" onkeydown="return validateNumber(event)" max="65535" /> 
							<label for="port_dest_end">End</label> <input type="number" name="port_dest[]" value="$port_dest_end" placeholder="0" style="width: 5em;" onkeydown="return validateNumber(event)" max="65535" />
						</div>
						<div id="tcp_option" style="display: $tcp_option;">
							<h4>TCP Flags</h4>
							<table class="form-table tcp-flags">
								<tbody>
									<tr>
										<th></th>
										$tcp_flags_head
									</tr>
									<tr>
										<th style="text-align: right;" title="Only iptables uses the Mask bit">Mask</th>
										$tcp_flags_mask_form
									</tr>
									<tr>
										<th style="text-align: right;">Settings</th>
										$tcp_flags_settings_form
									</tr>
								</tbody>
							</table>
						</div>
					</td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="service_comment">Comment</label></th>
					<td width="67%"><textarea id="service_comment" name="service_comment" rows="4" cols="30">$service_comment</textarea></td>
				</tr>
			</table>
			<input type="submit" name="submit" value="$ucaction Service" class="button" />
			<input type="button" value="Cancel" class="button" id="cancel_button" />
		</form>
FORM;

		return $return_form;
	}
	
	
	function validatePost($post) {
		global $fmdb, $__FM_CONFIG;
		
		if (empty($post['service_name'])) return 'No service name defined.';
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', 'service_name');
		if ($field_length !== false && strlen($post['service_name']) > $field_length) return 'Service name is too long (maximum ' . $field_length . ' characters).';
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', $post['service_name'], 'service_', 'service_name', "AND service_type='{$post['service_type']}' AND service_id!={$post['service_id']}");
		if ($fmdb->num_rows) return 'This service name already exists.';
		
		/** Set ports */
		if ($post['service_type'] != 'icmp') {
			foreach ($post['port_src'] as $port) {
				if (!empty($port) && !verifyNumber($port, 0, 65535, false)) return 'Source ports must be a valid ' . strtoupper($post['service_type']) . ' port range.';
				if (empty($port) || $port == 0) {
					$post['port_src'] = array('', '');
					break;
				}
			}
			sort($post['port_src']);
			$post['service_src_ports'] = implode(':', $post['port_src']);
			if ($post['service_src_ports'] == ':') $post['service_src_ports'] = null;
			
			foreach ($post['port_dest'] as $port) {
				if (!empty($port) && !verifyNumber($port, 0, 65535, false)) return 'Destination ports must be a valid ' . strtoupper($post['service_type']) . ' port range.';
				if (empty($port) || $port == 0) {
					$post['port_dest'] = array('', '');
					break;
				}
			}
			sort($post['port_dest']);
			$post['service_dest_ports'] = implode(':', $post['port_dest']);
			if ($post['service_dest_ports'] == ':') $post['service_dest_ports'] = null;
			
			unset($post['service_icmp_code']);
			unset($post['service_icmp_type']);
		} else {
			if (!empty($post['service_icmp_type']) && !verifyNumber($post['service_icmp_type'], -1, 40, false)) return 'ICMP type is invalid.';
			if (empty($post['service_icmp_type'])) $post['service_icmp_type'] = 0;
			
			if (!empty($post['service_icmp_code']) && !verifyNumber($post['service_icmp_code'], -1, 15, false)) return 'ICMP code is invalid.';
			if (empty($post['service_icmp_code'])) $post['service_icmp_code'] = 0;
		}
		
		/** Process TCP Flags */
		if (@is_array($post['service_tcp_flags']) && $post['service_type'] == 'tcp') {
			$decimals['settings'] = $decimals['mask'] = 0;
			foreach ($post['service_tcp_flags'] as $type_array => $dec_array) {
				foreach ($dec_array as $dec => $checked) {
					$decimals[$type_array] += $dec;
				}
			}
			$post['service_tcp_flags'] = implode(':', $decimals);
		} else $post['service_tcp_flags'] = null;
		
		return $post;
	}
	
	
	function getTCPFlags($flag_bits, $type = 'display') {
		global $__FM_CONFIG;
		
		@list($tcp_flag_mask, $tcp_flag_settings) = explode(':', $flag_bits);
		$tcp_flags_mask_form = $tcp_flags_settings_form = $tcp_flags_head = null;
		$service_tcp_flags['mask'] = $service_tcp_flags['settings'] = null;
		
		foreach ($__FM_CONFIG['tcp_flags'] as $flag => $bit) {
			if (in_array($type, array('iptables', 'display')) && ($bit & $tcp_flag_mask)) $service_tcp_flags['mask'] .= $flag . ',';
			if ($type == 'ipfw' && (($bit & $tcp_flag_mask) && !($bit & $tcp_flag_settings))) $service_tcp_flags['settings'] .= '!' . strtolower($flag) . ',';
			if ($type == 'ipfilter' && ($bit & $tcp_flag_mask)) $service_tcp_flags['mask'] .= substr($flag, 0, 1);
			if ($bit & $tcp_flag_settings) {
				switch ($type) {
					case 'iptables':
					case 'display':
						$service_tcp_flags['settings'] .= $flag . ',';
						break;
					case 'ipfw':
						$service_tcp_flags['settings'] .= strtolower($flag) . ',';
						break;
					case 'ipfilter':
						$service_tcp_flags['settings'] .= substr($flag, 0, 1);
						break;
				}
			}

			if (!$tcp_flag_settings) {
				$service_tcp_flags['settings'] = in_array($type, array('iptables', 'display')) ? 'NONE' : null;
			}

			if (in_array($type, array('iptables', 'display'))) {
				if (!$tcp_flag_mask) $service_tcp_flags['mask'] = 'NONE';
				if ($tcp_flag_mask == array_sum($__FM_CONFIG['tcp_flags'])) $service_tcp_flags['mask'] = 'ALL';
				if ($tcp_flag_settings == array_sum($__FM_CONFIG['tcp_flags'])) $service_tcp_flags['settings'] = 'ALL';
			}
		}
		
		$service_tcp_flags['mask'] = rtrim($service_tcp_flags['mask'], ',');
		$service_tcp_flags['settings'] = rtrim($service_tcp_flags['settings'], ',');
		if ($type == 'ipfilter') krsort($service_tcp_flags);
		else ksort($service_tcp_flags);
		
		$service_tcp_flags = trim(implode(' ', $service_tcp_flags));

		switch ($type) {
			case 'iptables':
				return (substr_count($service_tcp_flags, 'NONE') != 2) ? ' --tcp-flags ' . $service_tcp_flags : null;
			case 'ipfw':
				$service_tcp_flags = str_replace(' ', ',', $service_tcp_flags);
				if (in_array($service_tcp_flags, array('!ack,syn', 'syn,!ack'))) return ' setup';
				return ' tcpflags ' . $service_tcp_flags;
			case 'ipfilter':
				$service_tcp_flags = str_replace(' ', '/', $service_tcp_flags);
				return ' flags ' . $service_tcp_flags;
			default:
				return $service_tcp_flags;
		}
	}
	
}

if (!isset($fm_module_services))
	$fm_module_services = new fm_module_services();

?>
