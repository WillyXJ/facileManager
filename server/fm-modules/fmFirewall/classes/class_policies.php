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
 | fmFirewall: Easily manage one or more software firewalls                |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmfirewall/                        |
 +-------------------------------------------------------------------------+
*/

class fm_module_policies {
	
	/**
	 * Displays the policy list
	 */
	function rows($result, $type, $page, $total_pages) {
		global $fmdb, $__FM_CONFIG;
		
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;

		$table_info = array(
						'class' => 'display_results',
						'id' => 'table_edits',
						'name' => 'policies'
					);
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			if ($num_rows > 1) $table_info['class'] .= ' grab';

			$bulk_actions_list = array(_('Enable'), _('Disable'), _('Delete'));
		}

		$fmdb->num_rows = $num_rows;
		
		$start = $_SESSION['user']['record_count'] * ($page - 1);
		echo displayPagination($page, $total_pages, @buildBulkActionMenu($bulk_actions_list));
		echo '<div class="overflow-container">';

		if (is_array($bulk_actions_list)) {
			$title_array[] = array(
								'title' => '<input type="checkbox" class="tickall" onClick="toggle(this, \'bulk_list[]\')" />',
								'class' => 'header-tiny header-nosort'
							);
			if ($num_rows > 1) $title_array[] = array('class' => 'header-tiny');
		}
		$title_array = array_merge((array) $title_array, array(array('class' => 'header-tiny'), __('Name'), __('Location'), __('Source'), __('Destination'), __('Service'), __('Interface')));
		if ($type == 'filter') {
			$title_array = array_merge($title_array, array(__('Direction'), __('Time'), __('User')));
		} elseif ($type == 'nat') {
			$title_array = array_merge($title_array, array(__('Source Translation'), __('Destination Translation')));
		}
		$title_array[] = array('title' => _('Comment'), 'style' => 'width: 20%;');
		if (is_array($bulk_actions_list)) $title_array[] = array('title' => _('Actions'), 'class' => 'header-actions');

		echo '<div class="existing-container" style="bottom: 10em;">';
		echo displayTableHeader($table_info, $title_array);

		if ($total_pages) {
			$grabbable = true;
			$y = 0;
			for ($x=$start; $x<$num_rows; $x++) {
				if ($y == $_SESSION['user']['record_count']) break;
				if ($results[$x]->policy_from_template && $grabbable) {
					echo '</tbody><tbody class="no-grab">';
					$grabbable = false;
				}
				if (!$results[$x]->policy_from_template && !$grabbable) {
					echo '</tbody><tbody>';
					$grabbable = true;
				}
				$this->displayRow($results[$x], $type, $num_rows);
				$y++;
			}
		}

		echo "</tbody>\n</table>\n";
		
		if (!$total_pages) {
			printf('<p id="table_edits" class="noresult" name="policies">%s</p>', __('There are no firewall rules.'));
		}
		
		$server_firewall_type = getNameFromID($_REQUEST['server_serial_no'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_type');
		if (array_key_exists($server_firewall_type, $__FM_CONFIG['fw']['notes'])) {
			$policy_note = sprintf('<br />
			<div id="shadow_box" class="fullwidthbox">
				<div id="shadow_container" class="fullwidthbox note">
				<p><b>%s</b><br />%s</p>
				</div>
			</div>',
					__('Note:'), $__FM_CONFIG['fw']['notes'][$server_firewall_type]
				);
		} else $policy_note = null;
		
		$action_icons = enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_action');
		$action_icons[] = 'log';
		
		if ($type == 'filter') {
			foreach ($action_icons as $action) {
				$action_active[] = '<td>' . str_replace(array('__action__', '__Action__'), array($action, ucfirst($action)), $__FM_CONFIG['icons']['action'][$action]) . "<span>$action</span></td>\n";
			}
			
			$action_active = implode("\n", $action_active);
			
			echo <<<HTML
			$policy_note
			<table class="legend">
				<tbody>
					<tr>
						$action_active
					</tr>
				</tbody>
			</table>
HTML;
		}
		echo '</div></div>';
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
				$sql_fields .= $key . ', ';
				$sql_values .= "'$clean_data', ";
				if ($clean_data && !in_array($key, array('account_id', 'server_serial_no'))) {
					if (in_array($key, array('policy_source', 'policy_destination', 'policy_services'))) {
						$clean_data = str_replace("<br />\n", ', ', $this->formatPolicyIDs($clean_data, 'names-only'));
					} elseif ($key == 'policy_time') {
						$clean_data = getNameFromID($clean_data, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'time', 'time_', 'time_id', 'time_name');
					} elseif ($key == 'policy_targets') {
						$clean_data = str_replace("<br />\n", ', ', $this->formatServerIDs($clean_data));
					} elseif ($key == 'policy_template_id') {
						$clean_data = getNameFromID($clean_data, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_', 'policy_id', 'policy_name');
					}
					$log_message .= formatLogKeyData('policy_', $key, $clean_data);
				}
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the policy because a database error occurred.'), 'sql');
		}

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
			/** Ensure policy_type is set */
			$post['policy_type'] = (isset($post['policy_type']) && array_key_exists(sanitize(strtolower($post['policy_type'])), $__FM_CONFIG['policy']['avail_types'])) ? sanitize(strtolower($post['policy_type'])) : 'filter';

			/** Make new order in array */
			$new_sort_order = explode(';', rtrim($post['sort_order'], ';'));
			
			$template_id_sql = null;
			if ($post['server_serial_no'][0] == 't') {
				$template_id = preg_replace('/\D/', '', $post['server_serial_no']);
				$template_id_sql = " AND policy_template_id=$template_id";
				$post['server_serial_no'] = 0;
			}
			
			/** Get policy listing for server */
			basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_order_id', 'policy_', 'AND policy_type="' . $post['policy_type'] . '" AND server_serial_no=' . $post['server_serial_no'] . $template_id_sql);
			$count = $fmdb->num_rows;
			$policy_result = $fmdb->last_result;
			for ($i=0; $i<$count; $i++) {
				$order_id = array_search($policy_result[$i]->policy_id, $new_sort_order);
				if ($order_id === false) return __('The sort order could not be updated due to an invalid request.');
				$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}policies` SET `policy_order_id`=$order_id WHERE `policy_id`={$policy_result[$i]->policy_id} AND `server_serial_no`={$post['server_serial_no']} AND `account_id`='{$_SESSION['user']['account_id']}'";
				$result = $fmdb->query($query);
				if ($fmdb->sql_errors) {
					return formatError(__('Could not update the policy order because a database error occurred.'), 'sql');
				}
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
				$sql_edit .= $key . "='" . $clean_data . "', ";
				if ($clean_data && !in_array($key, array('account_id', 'server_serial_no', 'policy_source_not', 'policy_destination_not', 'policy_services_not'))) {
					if (in_array($key, array('policy_source', 'policy_destination', 'policy_services'))) {
						$not = (isset($post[$key . '_not']) && $post[$key . '_not']) ? $__FM_CONFIG['module']['icons']['negated'] . ' ' : '';
						$clean_data = str_replace("<br />\n", ', ', $this->formatPolicyIDs($clean_data, 'names-only', $not));
					} elseif ($key == 'policy_targets') {
						$clean_data = str_replace("<br />\n", ', ', $this->formatServerIDs($clean_data));
					} elseif ($key == 'policy_template_id') {
						$clean_data = getNameFromID($clean_data, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_', 'policy_id', 'policy_name');
					}
					$log_message .= formatLogKeyData('policy_', $key, $clean_data);
				}
			}
		}
		$sql = rtrim($sql_edit, ', ');
		
		/** Update the policy */
		$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}policies` SET $sql WHERE `policy_id`={$post['policy_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not update the firewall policy because a database error occurred.'), 'sql');
		}
		
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
		
		return formatError(__('This policy could not be deleted.'), 'sql');
	}


	function displayRow($row, $type, $num_rows) {
		global $__FM_CONFIG;
		
		$row_title = $options = null;
		if ($row->policy_status == 'disabled') {
			$class[] = 'disabled';
			$row_title = sprintf('title="%s"', __('Rule is disabled'));
		}
		if ($row->policy_from_template) $class[] = 'notice';
		
		$edit_status = $checkbox = $grab_bars = null;
		$bars_title = __('Click and drag to reorder');
		
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$edit_status = '<a class="edit_form_link" name="' . $type . '" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			if (!$row->policy_from_template) {
				$edit_status .= '<a class="status_form_link" href="#" rel="';
				$edit_status .= ($row->policy_status == 'active') ? 'disabled' : 'active';
				$edit_status .= '">';
				$edit_status .= ($row->policy_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
				$edit_status .= '</a>';
				$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
				$checkbox = '<td><input type="checkbox" name="bulk_list[]" value="' . $row->policy_id .'" /></td>';
				$grab_bars = ($num_rows > 1) ? '<td><i class="fa fa-bars mini-icon" title="' . $bars_title . '"></i></td>' : null;
			} else {
				$checkbox = $grab_bars = '<td></td>';
				$class[] = 'no-grab';
			}
			$edit_status = '<td id="row_actions">' . $edit_status . '</td>';
		}
		
		$source = ($row->policy_source) ? $this->formatPolicyIDs($row->policy_source) : 'any';
		$destination = ($row->policy_destination) ? $this->formatPolicyIDs($row->policy_destination) : 'any';
		$services = ($row->policy_services) ? $this->formatPolicyIDs($row->policy_services) : 'any';
		$interface = ($row->policy_interface) ? $row->policy_interface : 'any';
		$policy_time = ($type == 'filter' && $row->policy_time) ? $this->formatPolicyIDs($row->policy_time) : 'any';
		$policy_uid = ($row->policy_uid) ? $row->policy_uid : 'any';

		$source_translated = ($row->policy_source_translated) ? $this->formatPolicyIDs($row->policy_source_translated) : 'any';
		$destination_translated = ($row->policy_destination_translated) ? $this->formatPolicyIDs($row->policy_destination_translated) : 'any';
		// $services_translated = ($row->policy_services_translated) ? $this->formatPolicyIDs($row->policy_services_translated) : 'any';

		$source = str_replace('!', $__FM_CONFIG['module']['icons']['negated'], $row->policy_source_not) . ' ' . $source;
		$destination = str_replace('!', $__FM_CONFIG['module']['icons']['negated'], $row->policy_destination_not) . ' ' . $destination;
		$services = str_replace('!', $__FM_CONFIG['module']['icons']['negated'], $row->policy_services_not) . ' ' . $services;
		
		if ($row->policy_targets) $options[] = sprintf('<span class="tooltip-bottom mini-icon" data-tooltip="%s"><i class="fa fa-dot-circle-o" aria-hidden="true"></i></span>', __('Policy targets defined'));
		if ($row->policy_packet_state) $options[] = sprintf('<span class="tooltip-bottom mini-icon" data-tooltip="%s"><i class="fa fa-exchange" aria-hidden="true"></i></span>', str_replace(',', ', ', $row->policy_packet_state));
		if ($row->policy_options & $__FM_CONFIG['fw']['policy_options']['frag']['bit']) $options[] = sprintf('<span class="tooltip-bottom mini-icon" data-tooltip="%s"><i class="fa fa-chain-broken" aria-hidden="true"></i></span>', __('Matching fragment packets'));
		if ($row->policy_options & $__FM_CONFIG['fw']['policy_options']['quick']['bit']) $options[] = sprintf('<span class="tooltip-bottom mini-icon" data-tooltip="%s"><i class="fa fa-bolt" aria-hidden="true"></i></span>', __('Quick processing cancels further rule processing upon match'));
		if ($row->policy_uid) $options[] = sprintf('<span class="tooltip-bottom mini-icon" data-tooltip="%s"><i class="fa fa-user" aria-hidden="true"></i></span>', __('User ID defined'));
		if ($row->policy_tcp_flags) $options[] = sprintf('<span class="tooltip-bottom mini-icon" data-tooltip="%s"><i class="fa fa-flag" aria-hidden="true"></i></span>', __('TCP flags defined'));
		if ($row->policy_nat_bidirectional == 'yes' && $row->policy_snat_type == 'static') $options[] = sprintf('<span class="tooltip-bottom mini-icon" data-tooltip="%s"><i class="fa fa-arrows-h" aria-hidden="true"></i></span>', __('1:1 NAT'));
		
		if ($row->policy_options & $__FM_CONFIG['fw']['policy_options']['log']['bit']) $options[] = str_replace(array('__action__', '__Action__'), array('log', 'Log'), $__FM_CONFIG['icons']['action']['log']);
		if ($row->policy_type == 'filter') $options[] = str_replace(array('__action__', '__Action__'), array($row->policy_action, ucfirst($row->policy_action)), $__FM_CONFIG['icons']['action'][$row->policy_action]);

		/** Mark search terms */
		$comments = nl2br($row->policy_comment);
		if (isset($_GET['q'])) {
			$comments = preg_replace_callback("/{$_GET['q']}/", 'markGlobalSearchMatch', $comments);
		}

		if ($row->server_serial_no) {
			$location = getNameFromID($row->server_serial_no, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name');
		} elseif ($row->policy_from_template) {
			$location = '<a href="?type=' . $type . '&server_serial_no=t_' . $row->policy_template_id . '">' . getNameFromID($row->policy_template_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_', 'policy_id', 'policy_name') . '</a>';
		} else {
			$location = getNameFromID($row->policy_template_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_', 'policy_id', 'policy_name');
		}

		if ($class) $class = 'class="' . join(' ', $class) . '"';
		if ($options) {
			if (count($options) > 4) {
				$options[2] .= '<br />';
			}
			$options = join(' ', $options);
		}

		switch ($type) {
			case 'filter':
				$type_lines = <<<HTML
			<td>$row->policy_direction</td>
			<td><span rel="t$row->policy_time">$policy_time</span></td>
			<td>$policy_uid</td>
HTML;
				break;
			case 'nat':
				$type_lines = <<<HTML
			<td>$source_translated</td>
			<td>$destination_translated</td>
HTML;
				break;
		}
		
		echo <<<HTML
		<tr id="$row->policy_id" name="$row->policy_name" $class $row_title>
			$checkbox
			$grab_bars
			<td class="options">$options</td>
			<td>$row->policy_name</td>
			<td>$location</td>
			<td>$source</td>
			<td>$destination</td>
			<td>$services</td>
			<td>$interface</td>
			$type_lines
			<td>$comments</td>
			$edit_status
		</tr>

HTML;
	}

	/**
	 * Displays the form to add new policy
	 */
	function printForm($data = '', $action = 'add', $type = 'filter') {
		global $__FM_CONFIG;
		
		$policy_id = $policy_order_id = $policy_targets = $policy_template_id = 0;
		$policy_name = $policy_interface = $policy_direction = $policy_time = $policy_comment = $policy_options = null;
		$policy_services = $policy_source = $policy_destination = $policy_action = null;
		$source_items = $destination_items = $services_items = null;
		$policy_source_not = $policy_destination_not = $policy_services_not = null;
		$policy_packet_state_form = $policy_time_form = null;
		$policy_packet_state = '';
		$target_tab = $policy_uid = $policy_uid_form = $policy_tcp_flags = null;
		$policy_snat_type = $policy_services_translated = $policy_source_translated = $policy_destination_translated = null;
		$policy_nat_bidirectional = null;
		$source_translated_elements = $destination_translated_elements = $services_translated_elements = json_encode(null);
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		/** Build form */
		$popup_title = $action == 'add' ? __('Add Rule') : __('Edit Rule');
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = ($action != 'add' && ($policy_template_id && 't_' . $policy_template_id != $_POST['server_serial_no'])) ? buildPopup('footer', _('Cancel'), array('cancel_button' => 'cancel')) : buildPopup('footer');
		
		$return_form = <<<FORM
		<form name="manage" id="manage" method="post" action="?type=$type&server_serial_no={$_REQUEST['server_serial_no']}">
		$popup_header
			<input type="hidden" name="action" value="$action" />
			<input type="hidden" name="policy_id" value="$policy_id" />
			<input type="hidden" name="policy_order_id" value="$policy_order_id" />
			<input type="hidden" name="policy_type" value="$type" />
FORM;

		$server_firewall_type = getNameFromID($_POST['server_serial_no'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_type');
		$available_policy_actions = enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_action');
		if ($server_firewall_type == 'ipfilter') array_pop($available_policy_actions);

		$policy_interface = buildSelect('policy_interface', 'policy_interface', $this->availableInterfaces($_REQUEST['server_serial_no']), $policy_interface);
		$policy_direction = buildSelect('policy_direction', 'policy_direction', enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_direction'), $policy_direction, 1);
		$policy_action = buildSelect('policy_action', 'policy_action', $available_policy_actions, $policy_action, 1);

		$available_objects = availableGroupItems('object', 'available');

		$source_items_assigned = getGroupItems($policy_source);
		$source_items = buildSelect('source_items', 'source_items', $available_objects, $source_items_assigned, 1, null, true, null, null, __('Select one or more objects'));

		$destination_items_assigned = getGroupItems($policy_destination);
		$destination_items = buildSelect('destination_items', 'destination_items', $available_objects, $destination_items_assigned, 1, null, true, null, null, __('Select one or more objects'));

		$services_items_assigned = getGroupItems($policy_services);
		$services_items = buildSelect('services_items', 'services_items', availableGroupItems('service', 'available'), $services_items_assigned, 1, null, true, null, null, __('Select one or more services'));

		$source_not_check = ($policy_source_not) ? 'checked' : null;
		$destination_not_check = ($policy_destination_not) ? 'checked' : null;
		$service_not_check = ($policy_services_not) ? 'checked' : null;


		/** Start of common form */
		$tab_two_label = ($type == 'nat') ? __('Original Packet') : __('Policy');
		$return_form .= sprintf('
		<div id="tabs">
			<div id="tab">
				<input type="radio" name="tab-group-1" id="tab-1" checked />
				<label for="tab-1">%s</label>
				<div id="tab-content">
				<table class="form-table policy-form">
					<tr>
						<th width="33&#37;" scope="row"><label for="policy_name">%s</label></th>
						<td width="67&#37;"><input name="policy_name" id="policy_name" type="text" value="%s" /></td>
					</tr>
					<tr>
						<th width="33&#37;" scope="row"><label for="policy_comment">%s</label></th>
						<td width="67&#37;"><textarea id="policy_comment" name="policy_comment" rows="4" cols="30">%s</textarea></td>
					</tr>
				</table>
				</div>
			</div>
			<div id="tab">
				<input type="hidden" name="policy_source_not" value="" />
				<input type="hidden" name="policy_destination_not" value="" />
				<input type="hidden" name="policy_services_not" value="" />
				<input type="radio" name="tab-group-1" id="tab-2" />
				<label for="tab-2">%s</label>
				<div id="tab-content">
				<table class="form-table">
					<tr>
						<th width="33&#37;" scope="row">%s</th>
						<td width="67&#37;">
							<input name="policy_source_not" id="policy_source_not" value="!" type="checkbox" %s />%s %s
						</td>
					</tr>
					<tr>
						<th width="33&#37;" scope="row">%s</th>
						<td width="67&#37;">
							<input name="policy_destination_not" id="policy_destination_not" value="!" type="checkbox" %s />%s %s
						</td>
					</tr>
					<tr>
						<th width="33&#37;" scope="row">%s</th>
						<td width="67&#37;">
							<input name="policy_services_not" id="policy_services_not" value="!" type="checkbox" %s />%s %s
						</td>
					</tr>
				',
				__('General'),
				__('Policy Name'), $policy_name,
				_('Comment'), $policy_comment,
				$tab_two_label,
				__('Source'), $source_not_check, $__FM_CONFIG['module']['icons']['negated'], $source_items,
				__('Destination'), $destination_not_check, $__FM_CONFIG['module']['icons']['negated'], $destination_items,
				__('Services'), $service_not_check, $__FM_CONFIG['module']['icons']['negated'], $services_items
		);
	
		
		if ($type == 'filter') {
			/** Time restriction */
			if ($server_firewall_type == 'iptables' || $_POST['server_serial_no'][0] == 't') {
				$policy_time = buildSelect('policy_time', 'policy_time', $this->availableTimes(), $policy_time);
				$supported_firewalls = ($_POST['server_serial_no'][0] == 't') ? sprintf('<span class="tooltip-top mini-icon" data-tooltip="%s %s"><i class="fa fa-question-circle" aria-hidden="true"></i></span>',
						__('Supported firewalls:'), 'iptables'
					) : null;
				$policy_time_form .= sprintf('
				<tr>
					<th width="33&#37;" scope="row"><label for="policy_time">%s</label> %s</th>
					<td width="67&#37;">%s</td>
				</tr>',
						__('Time Restriction'), $supported_firewalls, $policy_time
					);
			}

			/** UID support */
			if (in_array($server_firewall_type, array('iptables', 'ipfw', 'pf')) || $_POST['server_serial_no'][0] == 't') {
				$supported_firewalls = ($_POST['server_serial_no'][0] == 't') ? sprintf('<span class="tooltip-top mini-icon" data-tooltip="%s %s"><i class="fa fa-question-circle" aria-hidden="true"></i></span>',
						__('Supported firewalls:'), 'iptables, ipfw, pf'
					) : null;
				$policy_uid_form = sprintf('
				<tr>
					<th width="33&#37;" scope="row"><label for="policy_uid">%s</label> %s</th>
					<td width="67&#37;"><input name="policy_uid" id="policy_uid" type="text" value="%s" /></td>
				</tr>',
						__('User ID'), $supported_firewalls, $policy_uid
					);
			}

			/** Packet state */
			$policy_packet_state_options = ($server_firewall_type) ? $__FM_CONFIG['fw']['policy_states'][$server_firewall_type] : null;
			if ($_POST['server_serial_no'][0] == 't' || (isset($data) && is_object($data[0]) && $data[0]->server_serial_no[0] == 0 && $data[0]->policy_template_id)) {
				$policy_packet_state_options = array();
				foreach ($__FM_CONFIG['fw']['policy_states'] as $fw => $values) {
					$i = 0;
					foreach ($values as $state) {
						$policy_packet_state_options[$fw][$i][0] = $state;
						$policy_packet_state_options[$fw][$i][1] = $state;
						$i++;
					}
				}
			}
			$policy_packet_state_form .= sprintf('
			<tr>
				<th width="33&#37;" scope="row"><label for="policy_packet_state">%s</label></th>
				<td width="67&#37;">%s</td>
			</tr>',
					__('Packet State'), buildSelect('policy_packet_state', 'policy_packet_state', $policy_packet_state_options, explode(',', trim($policy_packet_state, ',')), 1, null, true, null, null, __('Select one or more packet states'))
				);

			/** Process TCP Flags */
			@list($tcp_flag_mask, $tcp_flag_settings) = explode(':', $policy_tcp_flags);
			$tcp_flags_mask_form = $tcp_flags_settings_form = $tcp_flags_head = null;
			foreach ($__FM_CONFIG['tcp_flags'] as $flag => $bit) {
				$tcp_flags_head .= '<th title="' . $flag .'">' . $flag . "</th>\n";
				
				$tcp_flags_mask_form .= '<td><input type="checkbox" name="policy_tcp_flags[mask][' . $bit . ']" ';
				if ($bit & (integer) $tcp_flag_mask) $tcp_flags_mask_form .= 'checked';
				$tcp_flags_mask_form .= "/></td>\n";

				$tcp_flags_settings_form .= '<td><input type="checkbox" name="policy_tcp_flags[settings][' . $bit . ']" ';
				if ($bit & $tcp_flag_settings) $tcp_flags_settings_form .= 'checked';
				$tcp_flags_settings_form .= "/></td>\n";
			}
			
			/** Parse options */
			$options = null;
			foreach ($__FM_CONFIG['fw']['policy_options'] as $opt => $opt_array) {
				if (in_array($server_firewall_type, $opt_array['firewalls']) || $_POST['server_serial_no'][0] == 't') {
					$checked = ($policy_options & $opt_array['bit']) ? 'checked' : null;
					$supported_firewalls = ($_POST['server_serial_no'][0] == 't') ? sprintf('<span class="tooltip-bottom" style="color: unset;" data-tooltip="%s %s">%s</span>',
							__('Supported firewalls:'), join(', ', $opt_array['firewalls']), $opt_array['desc']
						) : $opt_array['desc'];
					$options .= sprintf('<input name="policy_options[]" id="policy_options[%s]" value="%s" type="checkbox" %s /><label for="policy_options[%s]" style="white-space: unset">%s</label><br />' . "\n",
							$opt_array['bit'], $opt_array['bit'], $checked, $opt_array['bit'],
							$supported_firewalls
						);
				}
			}

			$return_form .= sprintf('
				<tr>
					<th width="33&#37;" scope="row"><label for="policy_action">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
			</table>
			</div>
		</div>
		<div id="tab">
			<input type="radio" name="tab-group-1" id="tab-3" />
			<label for="tab-3">%s</label>
			<div id="tab-content">
			<table class="form-table">
				%s
				%s
				%s
				<tr>
					<th width="33&#37;" scope="row">%s</th>
					<td width="67&#37;">
						%s
					</td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row">%s</th>
					<td width="67&#37;">
						<table class="form-table tcp-flags">
						<tbody>
							<tr>
								<th></th>
								%s
							</tr>
							<tr>
								<th style="text-align: right;">%s <span class="tooltip-top mini-icon" data-tooltip="%s"><i class="fa fa-question-circle" aria-hidden="true"></i></a></th>
								%s
							</tr>
							<tr>
								<th style="text-align: right;">%s</th>
								%s
							</tr>
						</tbody>
						</table>
					</td>
				</tr>
			</table>
			</div>
		</div>',
					__('Action'), $policy_action,
					__('Options'),
					$policy_time_form, $policy_uid_form, $policy_packet_state_form,
					__('Options'), $options,
					__('TCP Flags'), $tcp_flags_head, __('Mask'), __('Only iptables uses the Mask bit. Use this for the negated bit on other firewalls.'),
					$tcp_flags_mask_form, __('Settings'), $tcp_flags_settings_form
				);
		} elseif ($type == 'nat') {
			$policy_dnat_checked = $policy_snat_checked = null;
			$dnat_style = $snat_style = 'style="display: none;"';
			$static_snat_style = null;

			if ($policy_snat_type == 'hide') {
				$static_snat_style = 'style="display: none;"';
			}
			if ($policy_source_translated || $policy_snat_type == 'hide') {
				$policy_snat_checked = 'checked';
				$snat_style = null;
			}

			$policy_snat_type = buildSelect('policy_snat_type', 'policy_snat_type', enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_snat_type'), $policy_snat_type, 1);
			$policy_nat_bidirectional = buildSelect('policy_nat_bidirectional', 'policy_nat_bidirectional', enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_nat_bidirectional'), $policy_nat_bidirectional, 1);

			/** Build source address translation elements */
			$source_translated_elements = $destination_translated_elements = $this->getObjectList('hosts');
			$found = false;
			foreach ($source_translated_elements as $source_group => $item_array) {
				foreach ($item_array['children'] as $item) {
					if ($policy_source_translated == $item['id']) {
						$found = true;
						break;
					}
				}
			}
			if (!$found && $policy_source_translated) $source_translated_elements = array_merge(array(array('text' => __('Other'), 'children' => array(array('id' => $policy_source_translated, 'text' => $policy_source_translated)))), $source_translated_elements);
			$source_translated_elements = json_encode($source_translated_elements);
	
			/** Build destination address translation elements */
			$found = false;
			foreach ($destination_translated_elements as $source_group => $item_array) {
				foreach ($item_array['children'] as $item) {
					if ($policy_destination_translated == $item['id']) {
						$found = true;
						break;
					}
				}
			}
			if (!$found && $policy_destination_translated) $destination_translated_elements = array_merge(array(array('text' => __('Other'), 'children' => array(array('id' => $policy_destination_translated, 'text' => $policy_destination_translated)))), $destination_translated_elements);
			$destination_translated_elements = json_encode($destination_translated_elements);
			if ($policy_destination_translated) {
				$policy_dnat_checked = 'checked';
				$dnat_style = null;
			}
	
			/** Build service translation elements */
			$services_translated_elements = $this->getObjectList('services');
			$found = false;
			foreach ($services_translated_elements as $item) {
				if ($policy_services_translated == $item['id']) {
					$found = true;
					break;
				}
			}
			if (!$found && $policy_services_translated) $services_translated_elements = array_merge(array(array('id' => $policy_services_translated, 'text' => $policy_services_translated)), $services_translated_elements);
			$services_translated_elements = json_encode($services_translated_elements);
	
			$return_form .= sprintf('
			</table>
			</div>
		</div>
		<div id="tab">
			<input type="radio" name="tab-group-1" id="tab-3" />
			<label for="tab-3">%s</label>
			<div id="tab-content">
			<h4><input name="policy_snat" id="policy_snat" type="checkbox" %s /> <label for="policy_snat">%s</label></h4>
			<table class="form-table snat_showhide" %s>
				<tr>
					<th width="33&#37;" scope="row"><label for="policy_snat_type">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
				<tr class="static_snat" %s>
					<th width="33&#37;" scope="row"><label for="policy_source_translated">%s</label></th>
					<td width="67&#37;"><input type="hidden" name="policy_source_translated" class="source_address_match_element" value="%s" /></td>
				</tr>
				<tr class="static_snat" %s>
					<th width="33&#37;" scope="row"><label for="policy_nat_bidirectional">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
			</table>
			<br />
			<h4><input name="policy_dnat" id="policy_dnat" type="checkbox" %s /> <label for="policy_dnat">%s</label></h4>
			<table class="form-table dnat_showhide" %s>
				<tr>
					<th width="33&#37;" scope="row"><label for="policy_destination_translated">%s</label></th>
					<td width="67&#37;"><input type="hidden" name="policy_destination_translated" class="destination_address_match_element" value="%s" /></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="policy_services_translated">%s</label></th>
					<td width="67&#37;"><input type="hidden" name="policy_services_translated" class="services_match_element" value="%s" /></td>
				</tr>
			</table>
			</div>
		</div>',
				__('Translated Packet'),
				$policy_snat_checked, __('Source Address Translation'), $snat_style,
				__('Translation Type'), $policy_snat_type,
				$static_snat_style, __('Translated Address'), $policy_source_translated,
				$static_snat_style, __('Bi Directional'), $policy_nat_bidirectional,
				$policy_dnat_checked, __('Destination Address Translation'), $dnat_style,
				__('Translated Address'), $policy_destination_translated,
				__('Translated Port'), $policy_services_translated
			);
		}

		/** Interfaces tab for filter/nat types */
		$interfaces_tab = sprintf('
		<div id="tab">
			<input type="radio" name="tab-group-1" id="tab-4" />
			<label for="tab-4">%s</label>
			<div id="tab-content">
			<table class="form-table">
				<tr>
					<th width="33&#37;" scope="row"><label for="policy_interface">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="policy_direction">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
			</table>
			</div>
		</div>',
				__('Interface'),
				__('Interface'), $policy_interface,
				__('Direction'), $policy_direction
			);

		/** Policy targets should only be available for template policies */
		if ($_POST['server_serial_no'][0] == 't' || (@is_object($data[0]) && $data[0]->server_serial_no[0] == 0 && $data[0]->policy_template_id)) {
			/** Process multiple policy targets */
			if (strpos($policy_targets, ';')) {
				$policy_targets = explode(';', rtrim($policy_targets, ';'));
				if (in_array('0', $policy_targets)) $policy_targets = 0;
			}

			$targets = buildSelect('policy_targets', 'policy_targets', availableServers('id'), $policy_targets, 1, null, true);
			
			$target_tab = sprintf('
		<div id="tab">
			<input type="radio" name="tab-group-1" id="tab-5" />
			<label for="tab-5">%s</label>
			<div id="tab-content">
			<table class="form-table">
				<tr>
					<th width="33&#37;" scope="row"><label for="policy_targets">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
			</table>
			</div>
		</div>',
				__('Targets'), __('Firewalls'), $targets
			);
		}
		
		/** Complete the form */
		$return_form .= <<<FORM
		$interfaces_tab
		$target_tab
		</div>
		$popup_footer
		</form>
		<script>
			$(document).ready(function() {
				$("#manage select").select2({
					width: '230px',
					minimumResultsForSearch: 10
				});
			});
			$(".source_address_match_element").select2({
				createSearchChoice:function(term, data) { 
					if ($(data).filter(function() { 
						return this.text.localeCompare(term)===0; 
					}).length===0) 
					{return {id:term, text:term};} 
				},
				multiple: true,
				maximumSelectionSize: 1,
				width: "200px",
				tokenSeparators: [",", " ", ";"],
				data: $source_translated_elements
			});
			$(".destination_address_match_element").select2({
				createSearchChoice:function(term, data) { 
					if ($(data).filter(function() { 
						return this.text.localeCompare(term)===0; 
					}).length===0) 
					{return {id:term, text:term};} 
				},
				multiple: true,
				maximumSelectionSize: 1,
				width: "200px",
				tokenSeparators: [",", " ", ";"],
				data: $destination_translated_elements
			});
			$(".services_match_element").select2({
				createSearchChoice:function(term, data) { 
					if ($(data).filter(function() { 
						return this.text.localeCompare(term)===0; 
					}).length===0) 
					{return {id:term, text:term};} 
				},
				multiple: true,
				maximumSelectionSize: 1,
				width: "200px",
				tokenSeparators: [",", " ", ";"],
				data: $services_translated_elements
			});
			$("#policy_dnat").click(function(e) {
				if ($(this).is(":checked")) {
					$(".dnat_showhide").show("slow");
				} else {
					$(".dnat_showhide").slideUp();
				}
			});
			$("#policy_snat").click(function(e) {
				if ($(this).is(":checked")) {
					$(".snat_showhide").show("slow");
				} else {
					$(".snat_showhide").slideUp();
				}
			});
			$("#policy_snat_type").change(function(e) {
				if ($(this).val() == "hide") {
					$(".static_snat").slideUp();
				} else {
					$(".static_snat").show("slow");
				}
			});
</script>
FORM;

		return $return_form;
	}
	

	function validatePost($post) {
		global $fmdb, $__FM_CONFIG;
		// echo '<pre>';print_r($post);
		
		/** Process options */
		if (@is_array($post['policy_options'])) {
			$decimals = 0;
			foreach ($post['policy_options'] as $dec) {
				$decimals += $dec;
			}
			$post['policy_options'] = $decimals;
			unset($decimals);
			unset($dec);
		} else $post['policy_options'] = 0;
		
		$post['server_serial_no'] = isset($post['server_serial_no']) ? $post['server_serial_no'] : $_REQUEST['server_serial_no'];
		if ($post['server_serial_no'][0] == 't') {
			$post['policy_template_id'] = preg_replace('/\D/', '', $post['server_serial_no']);
			$post['server_serial_no'] = 0;
		}
		$post['policy_source'] = implode(';', (array) $post['source_items']);
		$post['policy_destination'] = implode(';', (array) $post['destination_items']);
		$post['policy_services'] = implode(';', (array) $post['services_items']);
		$post['policy_packet_state'] = implode(',', (array) $post['policy_packet_state']);
		$post['policy_targets'] = implode(';', (array) $post['policy_targets']);
		if (!$post['policy_targets']) $post['policy_targets'] = 0;
		unset($post['source_items']);
		unset($post['destination_items']);
		unset($post['services_items']);

		if (!isset($post['policy_snat'])) {
			$post['policy_snat_type'] = 'static';
			$post['policy_source_translated'] = null;
			$post['policy_nat_bidirectional'] = 'no';
		}
		unset($post['policy_snat']);

		if (!isset($post['policy_dnat'])) {
			$post['policy_destination_translated'] = null;
			$post['policy_services_translated'] = null;
		}
		unset($post['policy_dnat']);
		
		/** Get policy_order_id */
		if (!isset($post['policy_order_id']) || $post['policy_order_id'] == 0) {
			basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', $post['server_serial_no'], 'policy_', 'server_serial_no', 'AND policy_type="' . sanitize($post['policy_type']) . '" ORDER BY policy_order_id DESC LIMIT 1');
			if ($fmdb->num_rows) {
				$result = $fmdb->last_result[0];
				$post['policy_order_id'] = $result->policy_order_id + 1;
			} else $post['policy_order_id'] = 1;
		}
		
		/** ipfilter does not support reject */
		if (getNameFromID($post['server_serial_no'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_type') == 'ipfilter' && $post['policy_action'] == 'reject') {
			$post['policy_action'] = 'block';
		}
		
		/** Process TCP Flags */
		if (@is_array($post['policy_tcp_flags'])) {
			$decimals['settings'] = $decimals['mask'] = 0;
			foreach ($post['policy_tcp_flags'] as $type_array => $dec_array) {
				foreach ($dec_array as $dec => $checked) {
					$decimals[$type_array] += $dec;
				}
			}
			$post['policy_tcp_flags'] = implode(':', $decimals);
		} else $post['policy_tcp_flags'] = null;
		
		/** Remove tabs */
		unset($post['tab-group-1']);

		// echo '<pre>';print_r($post);exit;
		
		return $post;
	}
	
	
	function formatPolicyIDs($ids, $display = 'global-search', $not = '') {
		global $__FM_CONFIG;
		
		$names = null;
		foreach (explode(';', trim($ids, ';')) as $temp_id) {
			$tooltip_objects = $addl_search_terms = array();

			if (in_array($temp_id[0], array('s', 'o', 'g'))) {
				$tmp_info = $this->getObjectInfo($temp_id);
				if (!count($tmp_info)) {
					return null;
				}
			}

			if ($temp_id[0] == 's') {
				$tmp_name = $tmp_info[0]->service_name;
				if (in_array($tmp_info[0]->service_type, array('tcp', 'udp'))) {
					$tmp_object = $tmp_info[0]->service_type . '/';
					list($tmp_src_port, $tmp_dest_port) = explode(':', $tmp_info[0]->service_dest_ports);
					$tooltip_objects[] = ($tmp_src_port == $tmp_dest_port) ? $tmp_object . $tmp_src_port : $tmp_object . $tmp_src_port . '-' . $tmp_dest_port;
				}
			} elseif ($temp_id[0] == 'o') {
				$tmp_name = $tmp_info[0]->object_name;
				$tooltip_objects[] = $tmp_info[0]->object_address . '/' . mask2cidr($tmp_info[0]->object_mask);
			} elseif ($temp_id[0] == 'g') {
				$tmp_name = $tmp_info[0]->group_name;
				foreach (explode(';', $tmp_info[0]->group_items) as $object_id) {
					$tmp_child_name = $this->getObjectInfo($object_id, 'name');
					$tooltip_objects[] = $tmp_child_name;
					$addl_search_terms[] = $tmp_child_name;
					if ($object_id[0] == 'g') {
						$addl_search_terms = array_merge($addl_search_terms, $this->getObjectChildren($object_id, $addl_search_terms));
					}
				}
			} elseif ($temp_id[0] == 't') {
				$tmp_name = getNameFromID(substr($temp_id, 1), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'time', 'time_', 'time_id', 'time_name');
			} else {
				$tmp_name = $temp_id;
			}
			if (isset($_GET['q']) && $display == 'global-search') {
				foreach (explode('" "', str_replace('%20', ' ', trim($_GET['q'], '"'))) as $absolute_q) {
					if ("\"$absolute_q\"" == "\"$tmp_name\"") {
						$tmp_name = preg_replace_callback("/\b{$absolute_q}\b/", 'markGlobalSearchMatch', $tmp_name);
					} elseif (in_array($absolute_q, $addl_search_terms)) {
						$tmp_name = preg_replace_callback("/\b{$tmp_name}\b/", 'markGlobalSearchMatch', $tmp_name);
					} else {
						$tmp_name = preg_replace_callback("/{$_GET['q']}/", 'markGlobalSearchMatch', $tmp_name);
					}
				}
			}
			if (count($tooltip_objects)) $tmp_name = sprintf('<span class="tooltip-bottom" data-tooltip="%s">%s</span>', implode("\n", $tooltip_objects), $tmp_name);

			$names[] = ($display == 'global-search') ? sprintf('<span rel="%s">%s %s</span>', $temp_id, $tmp_name, $__FM_CONFIG['module']['icons']['search']) : $not . $tmp_name;
		}
		
		return implode("<br />\n", $names);
	}
	
	function availableTimes() {
		global $fmdb, $__FM_CONFIG;
		
		$return[0][] = __('none');
		$return[0][] = '';
		
		$query = "SELECT time_id,time_name FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}time WHERE account_id='{$_SESSION['user']['account_id']}' AND time_status='active' ORDER BY time_name ASC";
		$result = $fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$results = $fmdb->last_result;
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$return[$i+1][] = $results[$i]->time_name;
				$return[$i+1][] = 't' . $results[$i]->time_id;
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
			
			if ($results->server_interfaces && trim($results->server_interfaces, ';')) {
				$return = array_merge($return, explode(';', trim($results->server_interfaces, ';')));
			}
		}
		
		return $return;
	}

	function formatServerIDs($ids) {
		global $__FM_CONFIG;
		
		$names = null;
		foreach (explode(';', trim($ids, ';')) as $temp_id) {
			$names[] = ($temp_id == 0) ? _('All Servers') : getNameFromID(preg_replace('/\D/', '', $temp_id), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
		}
		
		return implode("<br />\n", $names);
	}
	
	/**
	 * Builds array of objects for drop-down lists
	 * 
	 * @since 3.0
	 * @package fmFirewall
	 * 
	 * @param string $include What types of objects to include
	 * @return array
	 */
	function getObjectList($include = 'all') {
		global $__FM_CONFIG, $fmdb;
		
		if ($include == 'none') return array();
		
		$list = array();
		$i = 0;
		
		if (in_array($include, array('all', 'hosts'))) {
			basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', 'object_id', 'object_', 'active');
			if ($fmdb->num_rows) {
				$last_result = $fmdb->last_result;
				$list_network_count = $list_host_count = 0;
				for ($j=0; $j<$fmdb->num_rows; $j++) {
					if ($last_result[$j]->object_type == 'network') {
						$list_group_name = __('Networks');
						$i = $list_network_count;
						$list_network_count++;
					} else {
						$list_group_name = __('Hosts');
						$i = $list_host_count;
						$list_host_count++;
					}
					$list[$list_group_name][$i]['id'] = 'o' . $last_result[$j]->object_id;
					$list[$list_group_name][$i]['text'] = $last_result[$j]->object_name;
				}
				$i = 0;
				foreach ($list as $group => $children) {
					$tmp_list[$i]['text'] = $group;
					$tmp_list[$i]['children'] = $children;
					$i++;
				}
				$list = $tmp_list;
			}
		}
		
		if (in_array($include, array('all', 'services'))) {
			basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', 'service_id', 'service_', 'active');
			if ($fmdb->num_rows) {
				$last_result = $fmdb->last_result;
				for ($j=0; $j<$fmdb->num_rows; $j++) {
					$list[$i]['id'] = 's' . $last_result[$j]->service_id;
					$list[$i]['text'] = $last_result[$j]->service_name . ' (' . $last_result[$j]->service_type . ')';
					$i++;
				}
			}
		}
		
		return $list;
	}


	/**
	 * Gets the object's info from its type and ID
	 * 
	 * @since 3.0
	 * @package fmFirewall
	 * 
	 * @param string $id Object ID with type
	 * @param string $include What info to retrieve
	 * @return array
	 */
	function getObjectInfo($id, $include = 'all') {
		global $__FM_CONFIG;

		$tmp_db_table = array('s' => 'service',
								'o' => 'object',
								'g' => 'group');
		$tmp_object_type = substr($id, 0, 1);

		return ($include == 'all')
			? basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . $tmp_db_table[$tmp_object_type] . 's', substr($id, 1), $tmp_db_table[$tmp_object_type] . '_', $tmp_db_table[$tmp_object_type] . '_id')
			: getNameFromID(substr($id, 1), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . $tmp_db_table[$tmp_object_type] . 's', $tmp_db_table[$tmp_object_type] . '_', $tmp_db_table[$tmp_object_type] . '_id', $tmp_db_table[$tmp_object_type] . '_' . $include);
	}


	/**
	 * Gets the objects name from its type and ID
	 * 
	 * @since 3.0
	 * @package fmFirewall
	 * 
 	 * @param string $item_id Item ID to query
	 * @param string $results Empty array to append to
	 * @return array
	 */
	function getObjectChildren($item_id, $results) {
		global $__FM_CONFIG, $fmdb;

		/** Get group results */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'groups', substr($item_id, 1), 'group_', 'group_id');
		if ($fmdb->num_rows) {
			foreach (explode(';', $fmdb->last_result[0]->group_items) as $child_id) {
				$results[] = $this->getObjectInfo($child_id, 'name');
				if ($child_id[0] == 'g') {
					$nested_results = $this->getObjectChildren($child_id, $results);
				}
				if (count((array) $nested_results)) {
					$results = array_merge($results, $nested_results);
				}
			}
			return $results;
		}

		return array();
	}
}

if (!isset($fm_module_policies))
	$fm_module_policies = new fm_module_policies();

?>
