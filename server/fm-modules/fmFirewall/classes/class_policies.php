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
			$title_array[] = array('class' => 'header-tiny');
		}
		$title_array = array_merge((array) $title_array, array(array('class' => 'header-tiny'), __('Name'), __('Location'), __('Source'), __('Destination'), __('Service'), __('Interface'),
								__('Direction'), __('Time'), array('title' => _('Comment'), 'style' => 'width: 20%;')));
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
				$this->displayRow($results[$x], $type);
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
			</div></div>
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
				$sql_fields .= $key . ', ';
				$sql_values .= "'$clean_data', ";
				if ($clean_data && !in_array($key, array('account_id', 'server_serial_no'))) {
					if (in_array($key, array('policy_source', 'policy_destination', 'policy_services'))) {
						$clean_data = str_replace("<br />\n", ', ', $this->formatPolicyIDs($clean_data));
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
			/** Make new order in array */
			$new_sort_order = explode(';', rtrim($post['sort_order'], ';'));
			
			$template_id_sql = null;
			if ($post['server_serial_no'][0] == 't') {
				$template_id = preg_replace('/\D/', null, $post['server_serial_no']);
				$template_id_sql = " AND policy_template_id=$template_id";
				$post['server_serial_no'] = 0;
			}
			
			/** Get policy listing for server */
			basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_order_id', 'policy_', 'AND server_serial_no=' . $post['server_serial_no'] . $template_id_sql);
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
				if ($clean_data && !in_array($key, array('account_id', 'server_serial_no'))) {
					if (in_array($key, array('policy_source', 'policy_destination', 'policy_services'))) {
						$clean_data = str_replace("<br />\n", ', ', $this->formatPolicyIDs($clean_data));
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


	function displayRow($row, $type) {
		global $__FM_CONFIG;
		
		$row_title = $options = null;
		if ($row->policy_status == 'disabled') {
			$class[] = 'disabled';
			$row_title = sprintf('title="%s"', __('Rule is disabled'));
		}
		if ($row->policy_from_template) $class[] = 'notice';
		
		$edit_status = $edit_actions = $checkbox = $grab_bars = null;
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
				$grab_bars = '<td><i class="fa fa-bars mini-icon" title="' . $bars_title . '"></i></td>';
			} else {
				$checkbox = $grab_bars = '<td></td>';
				$class[] = 'no-grab';
			}
			$edit_status = '<td id="row_actions">' . $edit_status . '</td>';
		}
		
		$log = ($row->policy_options & $__FM_CONFIG['fw']['policy_options']['log']['bit']) ? str_replace(array('__action__', '__Action__'), array('log', 'Log'), $__FM_CONFIG['icons']['action']['log']) : null;
		$action = str_replace(array('__action__', '__Action__'), array($row->policy_action, ucfirst($row->policy_action)), $__FM_CONFIG['icons']['action'][$row->policy_action]);
		$source = ($row->policy_source) ? $this->formatPolicyIDs($row->policy_source) : 'any';
		$destination = ($row->policy_destination) ? $this->formatPolicyIDs($row->policy_destination) : 'any';
		$services = ($row->policy_services) ? $this->formatPolicyIDs($row->policy_services) : 'any';
		$interface = ($row->policy_interface) ? $row->policy_interface : 'any';
		$policy_time = ($row->policy_time) ? getNameFromID($row->policy_time, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'time', 'time_', 'time_id', 'time_name') : 'any';
		
		$source = str_replace('!', $__FM_CONFIG['module']['icons']['negated'], $row->policy_source_not) . ' ' . $source;
		$destination = str_replace('!', $__FM_CONFIG['module']['icons']['negated'], $row->policy_destination_not) . ' ' . $destination;
		$services = str_replace('!', $__FM_CONFIG['module']['icons']['negated'], $row->policy_services_not) . ' ' . $services;
		
		if ($row->policy_targets) $options[] = sprintf('<a href="JavaScript:void(0);" class="tooltip-bottom" data-tooltip="%s"><i class="fa fa-dot-circle-o" aria-hidden="true"></i></a>', __('Policy targets defined'));
		if ($row->policy_packet_state) $options[] = sprintf('<a href="JavaScript:void(0);" class="tooltip-bottom" data-tooltip="%s"><i class="fa fa-exchange" aria-hidden="true"></i></a>', str_replace(',', ', ', $row->policy_packet_state));
		if ($row->policy_options & $__FM_CONFIG['fw']['policy_options']['frag']['bit']) $options[] = sprintf('<a href="JavaScript:void(0);" class="tooltip-bottom" data-tooltip="%s"><i class="fa fa-chain-broken" aria-hidden="true"></i></a>', __('Matching fragment packets'));
		if ($row->policy_options & $__FM_CONFIG['fw']['policy_options']['quick']['bit']) $options[] = sprintf('<a href="JavaScript:void(0);" class="tooltip-bottom" data-tooltip="%s"><i class="fa fa-bolt" aria-hidden="true"></i></a>', __('Quick processing cancels further rule processing upon match'));
		
		$comments = nl2br($row->policy_comment);
		if ($row->server_serial_no) {
			$location = getNameFromID($row->server_serial_no, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name');
		} elseif ($row->policy_from_template) {
			$location = '<a href="?server_serial_no=t_' . $row->policy_template_id . '">' . getNameFromID($row->policy_template_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_', 'policy_id', 'policy_name') . '</a>';
		} else {
			$location = getNameFromID($row->policy_template_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_', 'policy_id', 'policy_name');
		}

		if ($class) $class = 'class="' . join(' ', $class) . '"';
		if ($options) $options = join('&nbsp;&nbsp;', $options);
		
		echo <<<HTML
		<tr id="$row->policy_id" name="$row->policy_name" $class $row_title>
			$checkbox
			$grab_bars
			<td class="options">$options $log $action</td>
			<td>$row->policy_name</td>
			<td>$location</td>
			<td>$source</td>
			<td>$destination</td>
			<td>$services</td>
			<td>$interface</td>
			<td>$row->policy_direction</td>
			<td>$policy_time</td>
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
		$policy_packet_state_form = $policy_packet_state = $policy_time_form = null;
		$target_tab = null;
		$ucaction = ucfirst($action);
		
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
		<form name="manage" id="manage" method="post" action="?server_serial_no={$_REQUEST['server_serial_no']}">
		$popup_header
			<input type="hidden" name="action" value="$action" />
			<input type="hidden" name="policy_id" value="$policy_id" />
			<input type="hidden" name="policy_order_id" value="$policy_order_id" />
FORM;

		if ($type == 'filter') {
			$server_firewall_type = getNameFromID($_POST['server_serial_no'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_type');
			$available_policy_actions = enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_action');
			if ($server_firewall_type == 'ipfilter') array_pop($available_policy_actions);

			$policy_interface = buildSelect('policy_interface', 'policy_interface', $this->availableInterfaces($_REQUEST['server_serial_no']), $policy_interface);
			$policy_direction = buildSelect('policy_direction', 'policy_direction', enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_direction'), $policy_direction, 1);
			$policy_action = buildSelect('policy_action', 'policy_action', $available_policy_actions, $policy_action, 1);

			$source_items_assigned = getGroupItems($policy_source);
			$source_items = buildSelect('source_items', 'source_items', availableGroupItems('object', 'available'), $source_items_assigned, 1, null, true, null, null, __('Select one or more objects'));

			$destination_items_assigned = getGroupItems($policy_destination);
			$destination_items = buildSelect('destination_items', 'destination_items', availableGroupItems('object', 'available'), $destination_items_assigned, 1, null, true, null, null, __('Select one or more objects'));

			$services_items_assigned = getGroupItems($policy_services);
			$services_items = buildSelect('services_items', 'services_items', availableGroupItems('service', 'available'), $services_items_assigned, 1, null, true, null, null, __('Select one or more services'));

			$source_not_check = ($policy_source_not) ? 'checked' : null;
			$destination_not_check = ($policy_destination_not) ? 'checked' : null;
			$service_not_check = ($policy_services_not) ? 'checked' : null;

			/** Policy targets should only be available for template policies */
			if ($_POST['server_serial_no'][0] == 't' || (is_object($data[0]) && $data[0]->server_serial_no[0] == 0 && $data[0]->policy_template_id)) {
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

			if ($server_firewall_type == 'iptables' || $_POST['server_serial_no'][0] == 't') {
				$policy_time = buildSelect('policy_time', 'policy_time', $this->availableTimes(), $policy_time);
				$supported_firewalls = ($_POST['server_serial_no'][0] == 't') ? sprintf('<a href="JavaScript:void(0);" class="tooltip-left" data-tooltip="%s %s"><i class="fa fa-question-circle"></i></a>',
						__('Supported firewalls:'), 'iptables'
					) : null;
				$policy_time_form .= sprintf('
				<tr>
					<th width="33&#37;" scope="row"><label for="policy_time">%s</label></th>
					<td width="67&#37;">%s %s</td>
				</tr>',
						__('Time Restriction'), $policy_time, $supported_firewalls
					);
			}

			$policy_packet_state_options = ($server_firewall_type) ? $__FM_CONFIG['fw']['policy_states'][$server_firewall_type] : null;
			if ($_POST['server_serial_no'][0] == 't' || (is_object($data[0]) && $data[0]->server_serial_no[0] == 0 && $data[0]->policy_template_id)) {
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

			/** Parse options */
			$options = null;
			foreach ($__FM_CONFIG['fw']['policy_options'] as $opt => $opt_array) {
				if (in_array($server_firewall_type, $opt_array['firewalls']) || $_POST['server_serial_no'][0] == 't') {
					$checked = ($policy_options & $opt_array['bit']) ? 'checked' : null;
					$supported_firewalls = ($_POST['server_serial_no'][0] == 't') ? sprintf('<a href="JavaScript:void(0);" class="tooltip-bottom" style="color: unset;" data-tooltip="%s %s">%s</a>',
							__('Supported firewalls:'), join(', ', $opt_array['firewalls']), $opt_array['desc']
						) : $opt_array['desc'];
					$options .= sprintf('<input name="policy_options[]" id="policy_options[%s]" value="%s" type="checkbox" %s /><label for="policy_options[%s]" style="white-space: unset">%s</label><br />' . "\n",
							$opt_array['bit'], $opt_array['bit'], $checked, $opt_array['bit'],
							$supported_firewalls
						);
				}
			}

			$return_form .= sprintf('
			<input type="hidden" name="policy_source_not" value="" />
			<input type="hidden" name="policy_destination_not" value="" />
			<input type="hidden" name="policy_services_not" value="" />
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
			<input type="radio" name="tab-group-1" id="tab-2" />
			<label for="tab-2">%s</label>
			<div id="tab-content">
			<table class="form-table">
				<tr>
					<th width="33&#37;" scope="row">%s</th>
					<td width="67&#37;">
						%s<br />
						<input name="policy_source_not" id="policy_source_not" value="!" type="checkbox" %s /><label for="policy_source_not">%s</label> <a href="JavaScript:void(0);" class="tooltip-top" data-tooltip="%s"><i class="fa fa-question-circle"></i></a>
					</td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row">%s</th>
					<td width="67&#37;">
						%s<br />
						<input name="policy_destination_not" id="policy_destination_not" value="!" type="checkbox" %s /><label for="policy_destination_not">%s</label> <a href="JavaScript:void(0);" class="tooltip-top" data-tooltip="%s"><i class="fa fa-question-circle"></i></a>
					</td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row">%s</th>
					<td width="67&#37;">
						%s<br />
						<input name="policy_services_not" id="policy_services_not" value="!" type="checkbox" %s /><label for="policy_services_not">%s</label> <a href="JavaScript:void(0);" class="tooltip-top" data-tooltip="%s"><i class="fa fa-question-circle"></i></a>
					</td>
				</tr>
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
				<tr>
					<th width="33&#37;" scope="row">%s</th>
					<td width="67&#37;">
						%s
					</td>
				</tr>
			</table>
			</div>
		</div>
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
		</div>
		%s
	</div>',
					__('Basic'),
					__('Policy Name'), $policy_name,
					_('Comment'), $policy_comment,
					__('Policy'),
					__('Source'), $source_items, $source_not_check, __('Negate'), __('Use this option to invert the match'),
					__('Destination'), $destination_items, $destination_not_check, __('Negate'), __('Use this option to invert the match'),
					__('Services'), $services_items, $service_not_check, __('Negate'), __('Use this option to invert the match'),
					__('Action'), $policy_action,
					__('Options'),
					$policy_time_form, $policy_packet_state_form,
					__('Options'), $options,
					__('Interface'),
					__('Interface'), $policy_interface,
					__('Direction'), $policy_direction,
					$target_tab
				);
		}
		
		$return_form .= <<<FORM
		$popup_footer
		</form>
		<script>
			$(document).ready(function() {
				$("#manage select").select2({
					width: '230px',
					minimumResultsForSearch: 10
				});
			});
		</script>
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
		if ($post['server_serial_no'][0] == 't') {
			$post['policy_template_id'] = preg_replace('/\D/', null, $post['server_serial_no']);
			$post['server_serial_no'] = 0;
		}
		$post['policy_source'] = implode(';', $post['source_items']);
		$post['policy_destination'] = implode(';', $post['destination_items']);
		$post['policy_services'] = implode(';', $post['services_items']);
		$post['policy_packet_state'] = implode(',', $post['policy_packet_state']);
		$post['policy_targets'] = implode(';', $post['policy_targets']);
		if (!$post['policy_targets']) $post['policy_targets'] = 0;
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
		
		/** Remove tabs */
		unset($post['tab-group-1']);
		
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
		
		$return[0][] = __('none');
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

	function formatServerIDs($ids) {
		global $__FM_CONFIG;
		
		$names = null;
		foreach (explode(';', trim($ids, ';')) as $temp_id) {
			$names[] = ($temp_id == 0) ? _('All Servers') : getNameFromID(preg_replace('/\D/', null, $temp_id), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
		}
		
		return implode("<br />\n", $names);
	}
	
}

if (!isset($fm_module_policies))
	$fm_module_policies = new fm_module_policies();

?>
