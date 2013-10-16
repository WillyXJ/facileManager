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

class fm_module_policies {
	
	/**
	 * Displays the policy list
	 */
	function rows($result, $type) {
		global $fmdb, $__FM_CONFIG, $allowed_to_manage_servers;
		
		echo '			<table class="display_results';
		if ($allowed_to_manage_servers) echo ' grab';
		echo '" id="table_edits" name="policies">' . "\n";
		if (!$result) {
			echo '<p id="noresult">There are no firewall policies.</p>';
		} else {
			echo <<<HTML
				<thead>
					<tr>
						<th width="20"></th>
						<th>Source</th>
						<th>Destination</th>
						<th>Service</th>
						<th>Interface</th>
						<th>Direction</th>
						<th>Time</th>
						<th style="width: 10%;">Comment</th>
						<th width="110" style="text-align: center;">Actions</th>
					</tr>
				</thead>
				<tbody>

HTML;
					$num_rows = $fmdb->num_rows;
					$results = $fmdb->last_result;
					for ($x=0; $x<$num_rows; $x++) {
						$this->displayRow($results[$x], $type);
					}
			echo "</tbody>\n";
		}
		
		$action_icons = enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_action');
		$action_icons[] = 'log';
		
		foreach ($action_icons as $action) {
			$action_active[] = '<td>' . str_replace(array('__action__', '__Action__'), array($action, ucfirst($action)), $__FM_CONFIG['icons']['action']['active']) . "<span>$action</span></td>\n";
			$action_disabled[] = '<td>' . str_replace(array('__action__', '__Action__'), array($action, ucfirst($action)), $__FM_CONFIG['icons']['action']['disabled']) . "<span>$action (disabled)</span></td>\n";
		}
		
		$action_active = implode("\n", $action_active);
		$action_disabled = implode("\n", $action_disabled);
		
		echo <<<HTML
			</table>
			<table class="legend">
				<tbody>
					<tr>
						$action_active
					</tr>
					<tr>
						$action_disabled
					</tr>
				</tbody>
			</table>

HTML;
	}

	/**
	 * Adds the new policy
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		$module = ($post['module_name']) ? $post['module_name'] : $_SESSION['module'];

		/** Get a valid and unique serial number */
		$post['policy_serial_no'] = (isset($post['policy_serial_no'])) ? $post['policy_serial_no'] : generateSerialNo($module);

		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}policies`";
		$sql_fields = '(';
		$sql_values = null;
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		
		$exclude = array('submit', 'action', 'policy_id', 'compress', 'AUTHKEY', 'module_name', 'module_type', 'config');

		foreach ($post as $key => $data) {
			$clean_data = sanitize($data);
			if (($key == 'policy_name') && empty($clean_data)) return 'No policy name defined.';
			if (!in_array($key, $exclude)) {
				$sql_fields .= $key . ',';
				$sql_values .= "'$clean_data',";
			}
		}
		$sql_fields = rtrim($sql_fields, ',') . ')';
		$sql_values = rtrim($sql_values, ',');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if (!$result) return 'Could not add the policy because a database error occurred.';

		addLogEntry("Added policy:\nName: {$post['policy_name']} ({$post['policy_serial_no']})\nType: {$post['policy_type']}\n" .
				"Update Method: {$post['policy_update_method']}\nConfig File: {$post['policy_config_file']}");
		return true;
	}

	/**
	 * Updates the selected policy
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Update sort order */
		if ($post['action'] == 'update_sort') {
			/** Make new order in array */
			$new_sort_order = explode(';', rtrim($post['sort_order'], ';'));
			
			/** Get policy listing for server */
			basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_order_id', 'policy_', 'AND server_serial_no=' . $post['server_serial_no']);
			$count = $fmdb->num_rows;
			$policy_result = $fmdb->last_result;
			for ($i=0; $i<$count; $i++) {
				$order_id = array_search($policy_result[$i]->policy_id, $new_sort_order);
				if ($order_id === false) return 'The sort order could not be updated due to an invalid request.';
				$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}policies` SET `policy_order_id`=$order_id WHERE `policy_id`={$policy_result[$i]->policy_id} AND `server_serial_no`={$post['server_serial_no']} AND `account_id`='{$_SESSION['user']['account_id']}'";
				$result = $fmdb->query($query);
				if ($result === false) return 'Could not update the policy order because a database error occurred.';
			}
			addLogEntry('Updated policy order for ' . getNameFromID($post['server_serial_no'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name'));
			return true;
		}
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		$exclude = array('submit', 'action', 'policy_id', 'compress', 'AUTHKEY', 'module_name', 'module_type', 'config', 'SERIALNO');

		$sql_edit = null;
		
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . sanitize($data) . "',";
			}
		}
		$sql = rtrim($sql_edit, ',');
		
		// Update the policy
//		$old_name = getNameFromID($post['policy_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_', 'policy_id', 'policy_name');
		$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}policies` SET $sql WHERE `policy_id`={$post['policy_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		if (!$result) return 'Could not update the policy because a database error occurred.';

//		setBuildUpdateConfigFlag(getPolicySerial($post['policy_id'], $_SESSION['module']), 'yes', 'build');
		
		addLogEntry("Updated policy '$old_name' to:\nName: {$post['policy_name']}\nType: {$post['policy_type']}\n" .
					"Update Method: {$post['policy_update_method']}\nConfig File: {$post['policy_config_file']}");
		return true;
	}
	
	/**
	 * Deletes the selected policy
	 */
	function delete($policy_id) {
		global $fmdb, $__FM_CONFIG;
		
		/** Does the policy_id exist for this account? */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', $policy_id, 'policy_', 'policy_id');
		if ($fmdb->num_rows) {
			/** Delete service */
//			$tmp_name = getNameFromID($service_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_', 'policy_id', 'service_name');
			if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', $policy_id, 'policy_', 'deleted', 'policy_id')) {
				addLogEntry("Deleted policy.");
				return true;
			}
		}
		
		return 'This policy could not be deleted.';
	}


	function displayRow($row, $type) {
		global $__FM_CONFIG, $allowed_to_manage_servers, $allowed_to_build_configs;
		
		$disabled_class = ($row->policy_status == 'disabled') ? ' class="disabled"' : null;
		
		$edit_status = $edit_actions = null;
//		$edit_actions = $row->policy_status == 'active' ? '<a href="preview.php" onclick="javascript:void window.open(\'preview.php?policy_serial_no=' . $row->policy_serial_no . '\',\'1356124444538\',\'width=700,height=500,toolbar=0,menubar=0,location=0,status=0,scrollbars=1,resizable=1,left=0,top=0\');return false;">' . $__FM_CONFIG['icons']['preview'] . '</a>' : null;
		
		if ($allowed_to_build_configs && $row->policy_installed == 'yes') {
			if ($row->policy_build_config == 'yes' && $row->policy_status == 'active' && $row->policy_installed == 'yes') {
				$edit_actions .= $__FM_CONFIG['icons']['build'];
			}
		}
		if ($allowed_to_manage_servers) {
			$edit_status = '<a id="plus" href="#" title="Add New" name="' . $type . '">' . $__FM_CONFIG['icons']['add'] . '</a>';
			$edit_status .= '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a href="' . $GLOBALS['basename'] . '?action=edit&id=' . $row->policy_id . '&status=';
			$edit_status .= ($row->policy_status == 'active') ? 'disabled' : 'active';
			$edit_status .= '&server_serial_no=' . $row->server_serial_no . '">';
			$edit_status .= ($row->policy_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
			$edit_status .= '</a>';
			$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
		}
		
		$edit_status = $edit_actions . $edit_status;
		
		$log = ($row->policy_options & $__FM_CONFIG['fw']['policy_options']['log']) ? str_replace(array('__action__', '__Action__'), array('log', 'Log'), $__FM_CONFIG['icons']['action'][$row->policy_status]) : null;
		$action = str_replace(array('__action__', '__Action__'), array($row->policy_action, ucfirst($row->policy_action)), $__FM_CONFIG['icons']['action'][$row->policy_status]);
		$source = ($row->policy_source) ? $this->formatPolicyIDs($row->policy_source) : 'any';
		$destination = ($row->policy_destination) ? $this->formatPolicyIDs($row->policy_destination) : 'any';
		$services = ($row->policy_services) ? $this->formatPolicyIDs($row->policy_services) : 'any';
		$interface = ($row->policy_interface) ? $row->policy_interface : 'any';
		$policy_time = ($row->policy_time) ? getNameFromID($row->policy_time, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'time', 'time_', 'time_id', 'time_name') : 'any';
		
		echo <<<HTML
		<tr id="$row->policy_id"$disabled_class>
			<td style="white-space: nowrap; text-align: right;">$log $action</td>
			<td>$source</td>
			<td>$destination</td>
			<td>$services</td>
			<td>$interface</td>
			<td>$row->policy_direction</td>
			<td>$policy_time</td>
			<td>$row->policy_comment</td>
			<td id="edit_delete_img">$edit_status</td>
		</tr>

HTML;
	}

	/**
	 * Displays the form to add new policy
	 */
	function printForm($data = '', $action = 'add', $type = 'rules') {
		global $__FM_CONFIG;
		
		$policy_id = 0;
		$policy_interface = $policy_direction = $policy_time = $policy_update_port = null;
		$services_items_assigned = $services_items_assigned = $policy_comment = null;
		$ucaction = ucfirst($action);
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		echo '<pre>';
		print_r($data);
		echo '</pre>';

//		$policy_type = buildSelect('policy_type', 'policy_type', enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_type'), $policy_type, 1);
//		$policy_update_method = buildSelect('policy_update_method', 'policy_update_method', enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_update_method'), $policy_update_method, 1);
		$policy_direction = buildSelect('policy_direction', 'policy_direction', enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_direction'), $policy_direction, 1);
		$policy_time = buildSelect('policy_time', 'policy_time', $this->availableTimes(), $policy_time);

		$source_items_assigned = getGroupItems($policy_source);
		$source_assigned_list = buildSelect(null, 'source_items_assigned', availableGroupItems('object', 'assigned', $source_items_assigned), null, 7, null, true);
		$source_available_list = buildSelect(null, 'source_items_available', availableGroupItems('object', 'available', $source_items_assigned), null, 7, null, true);
		
		$destination_items_assigned = getGroupItems($policy_destination);
		$destination_assigned_list = buildSelect(null, 'destination_items_assigned', availableGroupItems('object', 'assigned', $destination_items_assigned), null, 7, null, true);
		$destination_available_list = buildSelect(null, 'destination_items_available', availableGroupItems('object', 'available', $destination_items_assigned), null, 7, null, true);
		
		$services_items_assigned = getGroupItems($policy_services);
		$services_assigned_list = buildSelect(null, 'services_items_assigned', availableGroupItems('service', 'assigned', $services_items_assigned), null, 7, null, true);
		$services_available_list = buildSelect(null, 'services_items_available', availableGroupItems('service', 'available', $services_items_assigned), null, 7, null, true);
		
		$log_check = ($policy_options & $__FM_CONFIG['fw']['policy_options']['log']) ? 'checked' : null;

		$return_form = <<<FORM
		<form name="manage" id="manage" method="post" action="config-policies">
			<input type="hidden" name="action" value="$action" />
			<input type="hidden" name="policy_id" value="$policy_id" />
FORM;
		if ($type == 'rules') {
			$return_form .= <<<FORM
			<table class="form-table">
				<tr>
					<th width="33%" scope="row"><label for="policy_interface">Interface</label></th>
					<td width="67%">$policy_interface</td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="policy_direction">Direction</label></th>
					<td width="67%">$policy_direction</td>
				</tr>
				<tr>
					<th width="33%" scope="row">Source</th>
					<td width="67%">
						<table class="form-table list-toggle">
							<tbody>
								<tr>
									<th>Assigned</th>
									<th style="width: 50px;"></th>
									<th>Available</th>
								</tr>
								<tr>
									<td>$source_assigned_list</td>
									<td>
										<input type="button" id="buttonLeft" class="source" value="<" /><br />
										<input type="button" id="buttonRight" class="source" value=">" />
									</td>
									<td>$source_available_list</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<th width="33%" scope="row">Destination</th>
					<td width="67%">
						<table class="form-table list-toggle">
							<tbody>
								<tr>
									<th>Assigned</th>
									<th style="width: 50px;"></th>
									<th>Available</th>
								</tr>
								<tr>
									<td>$destination_assigned_list</td>
									<td>
										<input type="button" id="buttonLeft" class="destination" value="<" /><br />
										<input type="button" id="buttonRight" class="destination" value=">" />
									</td>
									<td>$destination_available_list</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<th width="33%" scope="row">Services</th>
					<td width="67%">
						<table class="form-table list-toggle">
							<tbody>
								<tr>
									<th>Assigned</th>
									<th style="width: 50px;"></th>
									<th>Available</th>
								</tr>
								<tr>
									<td>$services_assigned_list</td>
									<td>
										<input type="button" id="buttonLeft" class="services" value="<" /><br />
										<input type="button" id="buttonRight" class="services" value=">" />
									</td>
									<td>$services_available_list</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="policy_time">Time Restriction</label></th>
					<td width="67%">$policy_time</td>
				</tr>
				<tr>
					<th width="33%" scope="row">Options</th>
					<td width="67%">
						<label><input style="height: 10px;" name="user_force_pwd_change" id="user_force_pwd_change" value="yes" type="checkbox" $log_check />Log packets processed by this rule</label>
					</td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="policy_comment">Comment</label></th>
					<td width="67%"><textarea id="policy_comment" name="policy_comment" rows="4" cols="30">$policy_comment</textarea></td>
				</tr>
			</table>
			<input type="submit" name="submit" value="$ucaction Rule" class="button" />

FORM;
		}
		
		$return_form .= <<<FORM
			<input value="Cancel" class="button cancel" id="cancel_button" />
		</form>
FORM;

		return $return_form;
	}
	
	function buildPolicyConfig($serial_no) {
		global $fmdb, $__FM_CONFIG;
		
		/** Check serial number */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', sanitize($serial_no), 'policy_', 'policy_serial_no');
		if (!$fmdb->num_rows) return '<p class="error">This policy is not found.</p>';

		$policy_details = $fmdb->last_result;
		extract(get_object_vars($policy_details[0]), EXTR_SKIP);
		
		if (getOption('enable_named_checks', $_SESSION['user']['account_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'options') == 'yes') {
			include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_buildconf.php');
			
			$data['SERIALNO'] = $policy_serial_no;
			$data['compress'] = 0;
			$data['dryrun'] = true;
		
			basicGet('fm_accounts', $_SESSION['user']['account_id'], 'account_', 'account_id');
			$account_result = $fmdb->last_result;
			$data['AUTHKEY'] = $account_result[0]->account_key;
		
			$raw_data = $fm_module_buildconf->buildPolicyConfig($data);
		
			$response = $fm_module_buildconf->namedSyntaxChecks($raw_data);
			if (strpos($response, 'error') !== false) return $response;
		} else $response = null;
		
		switch($policy_update_method) {
			case 'cron':
				/* set the policy_update_config flag */
				setBuildUpdateConfigFlag($serial_no, 'yes', 'update');
				$response .= '<p>This policy will be updated on the next cron run.</p>'. "\n";
				break;
			case 'http':
			case 'https':
				/** Test the port first */
				$port = ($policy_update_method == 'https') ? 443 : 80;
				if (!socketTest($policy_name, $port, 30)) {
					return $response . '<p class="error">Failed: could not access ' . $policy_name . ' using ' . $policy_update_method . ' (tcp/' . $port . ').</p>'. "\n";
				}
				
				/** Remote URL to use */
				$url = $policy_update_method . '://' . $policy_name . '/' . $_SESSION['module'] . '/reload.php';
				
				/** Data to post to $url */
				$post_data = array('action'=>'buildconf', 'serial_no'=>$policy_serial_no);
				
				$post_result = unserialize(getPostData($url, $post_data));
				
				if (!is_array($post_result)) {
					/** Something went wrong */
					if (empty($post_result)) {
						$post_result = 'It appears ' . $policy_name . ' does not have php configured properly within httpd.';
					}
					return $response . '<p class="error">' . $post_result . '</p>'. "\n";
				} else {
					if (count($post_result) > 1) {
						$response .= '<textarea rows="4" cols="100">';
						
						/** Loop through and format the output */
						foreach ($post_result as $line) {
							$response .= "[$policy_name] $line\n";
						}
						
						$response .= "</textarea>\n";
					} else {
						$response .= "<p>[$policy_name] " . $post_result[0] . '</p>';
					}
				}
		}
		
		/* reset the policy_build_config flag */
		if (!strpos($response, strtolower('failed'))) {
			setBuildUpdateConfigFlag($serial_no, 'no', 'build');
		}

		$tmp_name = getNameFromID($serial_no, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_', 'policy_serial_no', 'policy_name');
		addLogEntry("Built the configuration for policy '$tmp_name'.");

		return $response;
	}
	
	function validatePost($post) {
		global $fmdb, $__FM_CONFIG;
		
		if (empty($post['policy_name'])) return 'No policy name defined.';
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_name');
		if ($field_length !== false && strlen($post['policy_name']) > $field_length) return 'Policy name is too long (maximum ' . $field_length . ' characters).';
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', $post['policy_name'], 'policy_', 'policy_name');
		if ($fmdb->num_rows) return 'This policy name already exists.';
		
		if (empty($post['policy_config_file'])) $post['policy_config_file'] = $__FM_CONFIG['fw']['config_file'][$post['policy_type']];
		
		/** Set default ports */
		if ($post['policy_update_method'] == 'cron') {
			$post['policy_update_port'] = 0;
		}
		if (!empty($post['policy_update_port']) && !verifyNumber($post['policy_update_port'], 1, 65535, false)) return 'Policy update port must be a valid TCP port.';
		if (empty($post['policy_update_port'])) {
			if ($post['policy_update_method'] == 'http') $post['policy_update_port'] = 80;
			elseif ($post['policy_update_method'] == 'https') $post['policy_update_port'] = 443;
		}
		
		return $post;
	}
	
	
	function formatPolicyIDs($ids) {
		global $__FM_CONFIG;
		
		$names = null;
		foreach (explode(';', trim($ids, ';')) as $temp_id) {
			if ($temp_id[0] == 's') {
				$names[] = getNameFromID(substr($temp_id, 1), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', 'service_', 'service_id', 'service_name');
			} elseif ($temp_id[0] == 'o') {
				$names[] = getNameFromID(substr($temp_id, 1), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', 'object_', 'object_id', 'object_name');
			} else {
				$names[] = getNameFromID(substr($temp_id, 1), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'groups', 'group_', 'group_id', 'group_name');
			}
		}
		
		return implode("<br />\n", $names);
	}
	
	function availableTimes() {
		global $fmdb, $__FM_CONFIG;
		
		$return[0][] = 'None';
		$return[0][] = '';
		
		$query = "SELECT time_id,time_name FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}time WHERE account_id='{$_SESSION['user']['account_id']}' AND time_status='active' ORDER BY time_name ASC";
		$result = $fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$results = $fmdb->last_result;
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$return[$i+1][] = $results[$i]->time_name;
				$return[$i+1][] = $results[$i]->time_id;
			}
		}
		
		return $return;
	}

}

if (!isset($fm_module_policies))
	$fm_module_policies = new fm_module_policies();

?>
