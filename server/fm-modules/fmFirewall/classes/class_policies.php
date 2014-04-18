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
		
		if (!$result) {
			echo '<p id="table_edits" class="noresult" name="policies">There are no firewall policies.</p>';
		} else {
			$table_info = array(
							'class' => 'display_results',
							'id' => 'table_edits',
							'name' => 'policies'
						);
			if ($allowed_to_manage_servers && $fmdb->num_rows > 1) $table_info['class'] .= ' grab';

			$title_array = array(array('class' => 'header-tiny'), 'Source', 'Destination', 'Service', 'Interface',
									'Direction', 'Time', array('title' => 'Comment', 'style' => 'width: 20%;'));
			if ($allowed_to_manage_servers) $title_array[] = array('title' => 'Actions', 'class' => 'header-actions');

			echo displayTableHeader($table_info, $title_array);
			
			$num_rows = $fmdb->num_rows;
			$results = $fmdb->last_result;
			for ($x=0; $x<$num_rows; $x++) {
				$this->displayRow($results[$x], $type);
			}
			
			echo "</tbody>\n";
		}
		
		$server_firewall_type = getNameFromID($_REQUEST['server_serial_no'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_type');
		if (array_key_exists($server_firewall_type, $__FM_CONFIG['fw']['notes'])) {
			$policy_note = <<<NOTE
			<br />
			<div id="shadow_box" class="fullwidthbox">
				<div id="shadow_container" class="fullwidthbox note">
				<p><b>Note:</b><br />{$__FM_CONFIG['fw']['notes'][$server_firewall_type]}</p>
				</div>
			</div>

NOTE;
		} else $policy_note = null;
		
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
			$policy_note
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
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}policies`";
		$sql_fields = '(';
		$sql_values = null;
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		
		$exclude = array('submit', 'action', 'policy_id', 'compress', 'AUTHKEY', 'module_name', 'module_type', 'config');

		$log_message = "Added a firewall policy for " . getNameFromID($post['server_serial_no'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name', $post['account_id']) . " with the following details:\n";

		foreach ($post as $key => $data) {
			$clean_data = sanitize($data);
			if (!in_array($key, $exclude)) {
				$sql_fields .= $key . ',';
				$sql_values .= "'$clean_data',";
				if ($clean_data && !in_array($key, array('account_id', 'server_serial_no'))) {
					if (in_array($key, array('policy_source', 'policy_destination', 'policy_services'))) {
						$clean_data = str_replace("<br />\n", ', ', $this->formatPolicyIDs($clean_data));
					} elseif ($key == 'policy_time') {
						$clean_data = getNameFromID($clean_data, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'time', 'time_', 'time_id', 'time_name');
					}
					$log_message .= formatLogKeyData('policy_', $key, $clean_data);
				}
			}
		}
		$sql_fields = rtrim($sql_fields, ',') . ')';
		$sql_values = rtrim($sql_values, ',');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if (!$fmdb->result) return 'Could not add the policy because a database error occurred.';

		setBuildUpdateConfigFlag($post['server_serial_no'], 'yes', 'build');
		
		addLogEntry($log_message);
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
			
			setBuildUpdateConfigFlag($post['server_serial_no'], 'yes', 'build');
		
			addLogEntry('Updated firewall policy order for ' . getNameFromID($post['server_serial_no'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name'));
			return true;
		}
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		$exclude = array('submit', 'action', 'policy_id', 'compress', 'AUTHKEY', 'module_name', 'module_type', 'config', 'SERIALNO');

		$sql_edit = null;
		
		$log_message = "Updated a firewall policy for " . getNameFromID($post['server_serial_no'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name') . " with the following details:\n";

		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$clean_data = sanitize($data);
				$sql_edit .= $key . "='" . $clean_data . "',";
				if ($clean_data && !in_array($key, array('account_id', 'server_serial_no'))) {
					if (in_array($key, array('policy_source', 'policy_destination', 'policy_services'))) {
						$clean_data = str_replace("<br />\n", ', ', $this->formatPolicyIDs($clean_data));
					}
					$log_message .= formatLogKeyData('policy_', $key, $clean_data);
				}
			}
		}
		$sql = rtrim($sql_edit, ',');
		
		/** Update the policy */
		$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}policies` SET $sql WHERE `policy_id`={$post['policy_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		if (!$fmdb->result) return 'Could not update the firewall policy because a database error occurred.';
		
		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		setBuildUpdateConfigFlag($post['server_serial_no'], 'yes', 'build');
		
		addLogEntry($log_message);
		return true;
	}
	
	/**
	 * Deletes the selected policy
	 */
	function delete($policy_id, $server_serial_no) {
		global $fmdb, $__FM_CONFIG;
		
		/** Does the policy_id exist for this account? */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', $policy_id, 'policy_', 'policy_id');
		if ($fmdb->num_rows) {
			/** Delete service */
			if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', $policy_id, 'policy_', 'deleted', 'policy_id')) {
				setBuildUpdateConfigFlag($_REQUEST['server_serial_no'], 'yes', 'build');
				
				addLogEntry('Deleted policy from ' . getNameFromID($server_serial_no, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name') . '.');
				return true;
			}
		}
		
		return 'This policy could not be deleted.';
	}


	function displayRow($row, $type) {
		global $__FM_CONFIG, $allowed_to_manage_servers, $allowed_to_build_configs;
		
		$disabled_class = ($row->policy_status == 'disabled') ? ' class="disabled"' : null;
		
		$edit_status = $edit_actions = null;
		
		if ($allowed_to_manage_servers) {
//			$edit_status = '<a id="plus" href="#" title="Add New" name="' . $type . '">' . $__FM_CONFIG['icons']['add'] . '</a>';
			$edit_status = '<a class="edit_form_link" name="' . $type . '" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a href="' . $GLOBALS['basename'] . '?action=edit&id=' . $row->policy_id . '&status=';
			$edit_status .= ($row->policy_status == 'active') ? 'disabled' : 'active';
			$edit_status .= '&server_serial_no=' . $row->server_serial_no . '">';
			$edit_status .= ($row->policy_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
			$edit_status .= '</a>';
			$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
		}
		
		$edit_status = $edit_actions . $edit_status;
		
		$log = ($row->policy_options & $__FM_CONFIG['fw']['policy_options']['log']['bit']) ? str_replace(array('__action__', '__Action__'), array('log', 'Log'), $__FM_CONFIG['icons']['action'][$row->policy_status]) : null;
		$action = str_replace(array('__action__', '__Action__'), array($row->policy_action, ucfirst($row->policy_action)), $__FM_CONFIG['icons']['action'][$row->policy_status]);
		$source = ($row->policy_source) ? $this->formatPolicyIDs($row->policy_source) : 'any';
		$destination = ($row->policy_destination) ? $this->formatPolicyIDs($row->policy_destination) : 'any';
		$services = ($row->policy_services) ? $this->formatPolicyIDs($row->policy_services) : 'any';
		$interface = ($row->policy_interface) ? $row->policy_interface : 'any';
		$policy_time = ($row->policy_time) ? getNameFromID($row->policy_time, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'time', 'time_', 'time_id', 'time_name') : 'any';
		
		$source_not = ($row->policy_source_not) ? '!' : null;
		$destination_not = ($row->policy_destination_not) ? '!' : null;
		$service_not = ($row->policy_services_not) ? '!' : null;

		echo <<<HTML
		<tr id="$row->policy_id"$disabled_class>
			<td style="white-space: nowrap; text-align: right;">$log $action</td>
			<td>$source_not $source</td>
			<td>$destination_not $destination</td>
			<td>$service_not $services</td>
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
		
		$policy_id = $policy_order_id = 0;
		$policy_interface = $policy_direction = $policy_time = $policy_comment = $policy_options = null;
		$policy_services = $policy_source = $policy_destination = $policy_action = null;
		$source_items = $destination_items = $services_items = null;
		$policy_source_not = $policy_destination_not = $policy_services_not = null;
		$ucaction = ucfirst($action);
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		$server_firewall_type = getNameFromID($_POST['server_serial_no'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_type');
		$available_policy_actions = enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_action');
		if ($server_firewall_type == 'ipfilter') array_pop($available_policy_actions);
		
		$policy_interface = buildSelect('policy_interface', 'policy_interface', $this->availableInterfaces($_REQUEST['server_serial_no']), $policy_interface);
		$policy_direction = buildSelect('policy_direction', 'policy_direction', enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_direction'), $policy_direction, 1);
		$policy_action = buildSelect('policy_action', 'policy_action', $available_policy_actions, $policy_action, 1);

		$source_items_assigned = getGroupItems($policy_source);
		$source_assigned_list = buildSelect(null, 'source_items_assigned', availableGroupItems('object', 'assigned', $source_items_assigned), null, 7, null, true);
		$source_available_list = buildSelect(null, 'source_items_available', availableGroupItems('object', 'available', $source_items_assigned), null, 7, null, true);
		
		$destination_items_assigned = getGroupItems($policy_destination);
		$destination_assigned_list = buildSelect(null, 'destination_items_assigned', availableGroupItems('object', 'assigned', $destination_items_assigned), null, 7, null, true);
		$destination_available_list = buildSelect(null, 'destination_items_available', availableGroupItems('object', 'available', $destination_items_assigned), null, 7, null, true);
		
		$services_items_assigned = getGroupItems($policy_services);
		$services_assigned_list = buildSelect(null, 'services_items_assigned', availableGroupItems('service', 'assigned', $services_items_assigned), null, 7, null, true);
		$services_available_list = buildSelect(null, 'services_items_available', availableGroupItems('service', 'available', $services_items_assigned), null, 7, null, true);
		
		$source_not_check = ($policy_source_not) ? 'checked' : null;
		$destination_not_check = ($policy_destination_not) ? 'checked' : null;
		$service_not_check = ($policy_services_not) ? 'checked' : null;

		$return_form = <<<FORM
		<form name="manage" id="manage" method="post" action="?server_serial_no={$_REQUEST['server_serial_no']}">
			<input type="hidden" name="action" value="$action" />
			<input type="hidden" name="policy_id" value="$policy_id" />
			<input type="hidden" name="policy_order_id" value="$policy_order_id" />
			<input type="hidden" name="policy_source_not" id="policy_source_not" value="0" />
			<input type="hidden" name="source_items" id="source_items" value="$source_items" />
			<input type="hidden" name="policy_destination_not" id="policy_destination_not" value="0" />
			<input type="hidden" name="destination_items" id="destination_items" value="$destination_items" />
			<input type="hidden" name="policy_services_not" id="policy_services_not" value="0" />
			<input type="hidden" name="services_items" id="services_items" value="$services_items" />
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
						<label><input style="height: 10px;" name="policy_source_not" id="policy_source_not" value="1" type="checkbox" $source_not_check /><b>not</b></label>
						<p class="checkbox_desc">Use this option to invert the match</p>
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
						<label><input style="height: 10px;" name="policy_destination_not" id="policy_destination_not" value="1" type="checkbox" $destination_not_check /><b>not</b></label>
						<p class="checkbox_desc">Use this option to invert the match</p>
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
						<label><input style="height: 10px;" name="policy_services_not" id="policy_services_not" value="1" type="checkbox" $service_not_check /><b>not</b></label>
						<p class="checkbox_desc">Use this option to invert the match</p>
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

FORM;
			if ($server_firewall_type == 'iptables') {
				$policy_time = buildSelect('policy_time', 'policy_time', $this->availableTimes(), $policy_time);
				$return_form .= <<<FORM
				<tr>
					<th width="33%" scope="row"><label for="policy_time">Time Restriction</label></th>
					<td width="67%">$policy_time</td>
				</tr>

FORM;
			}
			
			/** Parse options */
			$options = null;
			if ($server_firewall_type == 'pf') {
				array_pop($__FM_CONFIG['fw']['policy_options']);
				array_pop($__FM_CONFIG['fw']['policy_options']);
			}
			foreach ($__FM_CONFIG['fw']['policy_options'] as $opt => $opt_array) {
				$checked = ($policy_options & $opt_array['bit']) ? 'checked' : null;
				$options .= '<label><input style="height: 10px;" name="policy_options[]" id="policy_options" value="' . $opt_array['bit'] . '" type="checkbox" ' . $checked . ' />' . $opt_array['desc'] . "</label><br />\n";
			}
			
			$return_form .= <<<FORM
				<tr>
					<th width="33%" scope="row"><label for="policy_action">Action</label></th>
					<td width="67%">$policy_action</td>
				</tr>
				<tr>
					<th width="33%" scope="row">Options</th>
					<td width="67%">
						$options
					</td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="policy_comment">Comment</label></th>
					<td width="67%"><textarea id="policy_comment" name="policy_comment" rows="4" cols="30">$policy_comment</textarea></td>
				</tr>
			</table>
			<input type="submit" name="submit" id="submit_items" value="$ucaction Rule" class="button" />

FORM;
		}
		
		$return_form .= <<<FORM
			<input type="button" value="Cancel" class="button" id="cancel_button" />
		</form>
FORM;

		return $return_form;
	}
	

	function validatePost($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Process weekdays */
		if (@is_array($post['policy_options'])) {
			$decimals = 0;
			foreach ($post['policy_options'] as $dec) {
				$decimals += $dec;
			}
			$post['policy_options'] = $decimals;
		} else $post['policy_options'] = 0;
		
		$post['server_serial_no'] = isset($post['server_serial_no']) ? $post['server_serial_no'] : $_REQUEST['server_serial_no'];
		$post['policy_source'] = $post['source_items'];
		$post['policy_destination'] = $post['destination_items'];
		$post['policy_services'] = $post['services_items'];
		unset($post['source_items']);
		unset($post['destination_items']);
		unset($post['services_items']);
		
		/** Get policy_order_id */
		if (!isset($post['policy_order_id']) || $post['policy_order_id'] == 0) {
			basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', $post['server_serial_no'], 'policy_', 'server_serial_no', 'ORDER BY policy_order_id DESC LIMIT 1');
			if ($fmdb->num_rows) {
				$result = $fmdb->last_result[0];
				$post['policy_order_id'] = $result->policy_order_id + 1;
			} else $post['policy_order_id'] = 1;
		}
		
		/** ipfilter does not support reject */
		if (getNameFromID($post['server_serial_no'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_type') == 'ipfilter' && $post['policy_action'] == 'reject') {
			$post['policy_action'] = 'block';
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
		
		$return[0][] = 'none';
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

	function availableInterfaces($server_serial_no) {
		global $fmdb, $__FM_CONFIG;
		
		$return[] = 'any';
		
		if (!is_numeric($server_serial_no)) return $return;
		
		$query = "SELECT server_interfaces FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}servers WHERE account_id='{$_SESSION['user']['account_id']}' AND server_status!='deleted' AND server_serial_no=$server_serial_no ORDER BY server_name ASC";
		$fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$results = $fmdb->last_result[0];
			
			if (trim($results->server_interfaces, ';')) {
				$return = array_merge($return, explode(';', trim($results->server_interfaces, ';')));
			}
		}
		
		return $return;
	}

}

if (!isset($fm_module_policies))
	$fm_module_policies = new fm_module_policies();

?>
