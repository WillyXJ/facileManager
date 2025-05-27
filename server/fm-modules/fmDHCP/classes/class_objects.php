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
 | fmDHCP: Easily manage one or more ISC DHCP servers                      |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdhcp/                            |
 +-------------------------------------------------------------------------+
*/

class fm_dhcp_objects {
	
	/**
	 * Displays the item list
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmDHCP
	 *
	 * @param array $result Record rows of all items
	 * @return null
	 */
	function rows($result, $page, $total_pages, $type) {
		global $fmdb;
		
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;
		
		$permission_type = (in_array($type, array('subnets', 'shared'))) ? 'networks' : $type;
		if (currentUserCan('manage_' . $permission_type, $_SESSION['module'])) {
			$bulk_actions_list = array(_('Enable'), _('Disable'), _('Delete'));
		}

		$fmdb->num_rows = $num_rows;

		$start = $_SESSION['user']['record_count'] * ($page - 1);
		echo displayPagination($page, $total_pages, @buildBulkActionMenu($bulk_actions_list));

		$table_info = array(
						'class' => 'display_results sortable',
						'id' => 'table_edits',
						'name' => $type
					);

		if (is_array($bulk_actions_list)) {
			$title_array[] = array(
								'title' => '<input type="checkbox" class="tickall" onClick="toggle(this, \'bulk_list[]\')" />',
								'class' => 'header-tiny header-nosort'
							);
		} else {
			$title_array[] = array(
				'class' => 'header-tiny header-nosort'
			);
		}
		$title_array = array_merge((array) $title_array, $this->getTableHeader());
		$title_array[] = array('title' => _('Comment'), 'class' => 'header-nosort');
		if (is_array($bulk_actions_list)) $title_array[] = array('title' => _('Actions'), 'class' => 'header-actions header-nosort');

		echo '<div class="overflow-container">';
		echo displayTableHeader($table_info, $title_array);

		if ($result) {
			$y = 0;
			for ($x=$start; $x<$num_rows; $x++) {
				if ($y == $_SESSION['user']['record_count']) break;
				$this->displayRow($results[$x]);
				$y++;
			}
		}

		echo "</tbody>\n</table>\n";
		if (!$result) {
			printf('<p id="table_edits" class="noresult" name="%s">%s</p>', $type, __('There are no items.'));
		}
	}

	/**
	 * Adds the new object
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmDHCP
	 *
	 * @param array $post $_POST data
	 * @return boolean|string
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		/** Insert the parent */
		$sql_start = "INSERT INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config`";
		$sql_fields = '(';
		$sql_values = $options_log_message = '';
		
		$server_serial_no = (isset($_REQUEST['server_serial_no'])) ? sanitize($_REQUEST['server_serial_no']) : 0;

		$post['account_id'] = $_SESSION['user']['account_id'];
		$post['config_is_parent'] = 'yes';
		$post['config_data'] = $name = $post['config_name'];
		$post['config_name'] = $post['config_type'] = rtrim($post['config_type'], 's');
		
		if (empty($name)) return __('No name defined.');
		
		$include = array_merge(array('account_id', 'server_serial_no', 'config_is_parent', 'config_data', 'config_type', 'config_name', 'config_comment', 'config_parent_id'), $this->getIncludedFields());
		$log_exclude = array('account_id', 'config_is_parent', 'config_parent_id', 'config_type', 'config_children', 'config_data', 'server_serial_no');
		$log_message = sprintf(__('Add a %s with the following details:'), $post['config_type']) . "\n";

		/** Insert the category parent */
		foreach ($post as $key => $data) {
			if (in_array($key, $include)) {
				if ($data) {
					$sql_fields .= $key . ', ';
					$sql_values .= "'$data', ";
				}
				if (!in_array($key, $log_exclude)) {
					if ($key == 'config_name') $clean_data = $post['config_data'];
					$log_message .= formatLogKeyData('config_', $key, $clean_data);
				}
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_start $sql_fields VALUES ($sql_values)";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the item because a database error occurred.'), 'sql');
		}

		
		/** Insert config children */
		$child['config_is_parent'] = 'no';
		$child['config_parent_id'] = $fmdb->insert_id;
		$child['config_data'] = $child['config_name'] = null;
		$child['account_id'] = $post['account_id'];
		$child['config_type'] = rtrim($post['config_type'], 's');
		
		if (isset($post['hardware-type'])) {
			$post['hardware'] = $post['hardware-type'] . ' ' . $post['hardware'];
			unset($post['hardware-type']);
		}
		
		$include = array_diff(array_keys($post), $include, array('config_id', 'action', 'tab-group-1', 'submit', 'config_children', 'item_type', 'uri_params'));
		
		$sql_start = "INSERT INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config`";
		$sql_fields = '(';
		$sql_values = '(';
		
		$i = 1;
		foreach ($include as $handler) {
			$child['config_name'] = $handler;
			$child['config_data'] = $post[$handler];
			
			foreach ($child as $key => $data) {
				if ($i) $sql_fields .= $key . ', ';
				
				$sql_values .= "'$data', ";
			}
			$i = 0;
			$sql_values = rtrim($sql_values, ', ') . '), (';
			if ($child['config_data'] && !in_array($handler, $log_exclude)) $options_log_message .= formatLogKeyData('config_', $handler, $child['config_data']);
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', (');
		
		$query = "$sql_start $sql_fields VALUES $sql_values";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the item because a database error occurred.'), 'sql');
		}
		
		if ($post['config_parent_id']) {
			// Log parent
			$log_message .= formatLogKeyData('config_', __('Member of'), getNameFromID($post['config_parent_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_', 'config_id', 'config_data', $post['account_id']));
		}

		if (isset($post['config_children']) && is_array($post['config_children'])) {
			$sql_start = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config` SET ";
			$query = "$sql_start config_parent_id={$child['config_parent_id']} WHERE config_id IN (" . join(',', $post['config_children']) . ")";
			$fmdb->query($query);

			// Log children
			foreach ($post['config_children'] as $child_id) {
				$children[] = getNameFromID($child_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_', 'config_id', 'config_data', $post['account_id']);
			}
			$log_message .= formatLogKeyData('config_', __('Child Objects'), join(',', $children));
		}

		setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');

		addLogEntry(str_replace('\"', '', $log_message . $options_log_message));
		return true;
	}

	/**
	 * Updates the selected host
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmDHCP
	 *
	 * @param array $post $_POST data
	 * @return boolean|string
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		/** Update the parent */
		$sql_start = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config` SET ";
		$sql_values = $options_log_message = '';
		
		$server_serial_no = (isset($_REQUEST['server_serial_no'])) ? sanitize($_REQUEST['server_serial_no']) : 0;

		$post['account_id'] = $_SESSION['user']['account_id'];
		$post['config_is_parent'] = 'yes';
		$post['config_data'] = $name = $post['config_name'];
		$post['config_name'] = $post['config_type'] = rtrim($post['config_type'], 's');
		
		if (empty($name)) return __('No name defined.');
		
		$include = array_merge(array('account_id', 'server_serial_no', 'config_is_parent', 'config_data', 'config_type', 'config_name', 'config_comment', 'config_parent_id'), $this->getIncludedFields());
		$log_exclude = array('account_id', 'config_is_parent', 'config_parent_id', 'config_type', 'config_children', 'config_data', 'server_serial_no');
		$log_message = sprintf(__('Updated a %s (%s) with the following details:'), $post['config_type'], getNameFromID($post['config_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_', 'config_id', 'config_data', $post['account_id'])) . "\n";
		
		/** Insert the category parent */
		foreach ($post as $key => $data) {
			if (in_array($key, $include)) {
				$sql_values .= "$key='$data', ";
				if (!in_array($key, $log_exclude)) {
					if ($key == 'config_name') $data = $post['config_data'];
					$log_message .= formatLogKeyData('config_', $key, $data);
				}
			}
		}
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_start $sql_values WHERE config_id={$post['config_id']} LIMIT 1";
		$fmdb->query($query);
		$rows_affected = $fmdb->rows_affected;
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the item because a database error occurred.'), 'sql');
		}
		
		/** Update config children */
		$child['config_is_parent'] = 'no';
		$child['config_data'] = $child['config_name'] = null;
		$child['config_type'] = rtrim($post['config_type'], 's');
		
		if (isset($post['hardware-type'])) {
			$post['hardware'] = $post['hardware-type'] . ' ' . $post['hardware'];
			unset($post['hardware-type']);
		}
		
		$include = array_diff(array_keys($post), $include, array('config_id', 'action', 'tab-group-1', 'submit', 'account_id', 'config_children', 'item_type', 'uri_params'));
		$sql_start = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config` SET ";
		
		foreach ($include as $handler) {
			$sql_values = '';
			$child['config_name'] = $handler;
			$child['config_data'] = $post[$handler];
			
			foreach ($child as $key => $data) {
				$sql_values .= "$key='$data', ";
			}
			$sql_values = rtrim($sql_values, ', ');
			if ($child['config_data'] && !in_array($handler, $log_exclude)) {
				if (in_array($handler, array('address', 'peer-address'))) {
					$child['config_data'] = getNameFromID($child['config_data'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name', $post['account_id']);
				}
				$options_log_message .= formatLogKeyData('config_', $handler, $child['config_data']);
			}

			$query = "$sql_start $sql_values WHERE config_parent_id={$post['config_id']} AND config_name='$handler' LIMIT 1";
			$fmdb->query($query);
			$rows_affected += $fmdb->rows_affected;

			if ($fmdb->sql_errors) {
				return formatError(__('Could not update the item because a database error occurred.'), 'sql');
			}
		}
		
		if ($post['config_parent_id']) {
			// Log parent
			$log_message .= formatLogKeyData('config_', __('Member of'), getNameFromID($post['config_parent_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_', 'config_id', 'config_data', $post['account_id']));
		}

		/** Reassigned children */
		$query = "$sql_start config_parent_id=0 WHERE config_parent_id={$post['config_id']} AND config_is_parent='yes'";
		$fmdb->query($query);
		if (isset($post['config_children']) && is_array($post['config_children'])) {
			$query = "$sql_start config_parent_id={$post['config_id']} WHERE config_id IN (" . join(',', $post['config_children']) . ")";
			$fmdb->query($query);
			$rows_affected += $fmdb->rows_affected;

			// Log children
			foreach ($post['config_children'] as $child_id) {
				$children[] = getNameFromID($child_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_', 'config_id', 'config_data', $post['account_id']);
			}
			$log_message .= formatLogKeyData('config_', __('Child Objects'), join(',', $children));
		}

		setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');

		if ($rows_affected) addLogEntry(str_replace('\"', '', $log_message . $options_log_message));
		return true;
	}
	
	/**
	 * Deletes the selected server
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmDHCP
	 *
	 * @param integer $id ID to delete
	 * @return boolean|string
	 */
	function delete($id, $server_serial_no = 0) {
		global $fmdb, $__FM_CONFIG;
		
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_', 'config_id', 'config_data');
		$tmp_type = getNameFromID($id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_', 'config_id', 'config_name');

		/** Delete associated children */
		updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $id, 'config_', 'deleted', 'config_parent_id');
		
		/** Delete item */
		if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $id, 'config_', 'deleted', 'config_id') === false) {
			return formatError(sprintf(__('This %s could not be deleted because a database error occurred.'), $tmp_type), 'sql');
		} else {
			setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
			addLogEntry(sprintf(__("Deleted %s '%s.'"), $tmp_type, $tmp_name));
			return true;
		}
	}


	/**
	 * Displays the server entry table row
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmDHCP
	 *
	 * @param object $row Single data row from $results
	 * @return null
	 */
	function displayRow($row) {
		global $__FM_CONFIG;
		
		$class = ($row->config_status == 'disabled') ? 'disabled' : null;
		
		$edit_status = '';
		$icons = array();
		
		$checkbox = (currentUserCan(array('manage_servers', 'build_server_configs'), $_SESSION['module'])) ? '<td><input type="checkbox" name="bulk_list[]" value="' . $row->config_id .'" /></td>' : null;
		
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$edit_status = '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a class="status_form_link" href="#" rel="';
			$edit_status .= ($row->config_status == 'active') ? 'disabled' : 'active';
			$edit_status .= '">';
			$edit_status .= ($row->config_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
			$edit_status .= '</a>';
			$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			$icons[] = sprintf('<a href="config-options.php?item_id=%d" class="tooltip-bottom icons" data-tooltip="%s"><i class="icons fa fa-sliders" aria-hidden="true"></i></a>', $row->config_id, __('Configure Additional Options'));
		}
		
		if ($class) $class = 'class="' . $class . '"';
		if (is_array($icons)) {
			$icons = implode(' ', $icons);
		}
		
		echo <<<HTML
		<tr id="$row->config_id" name="$row->config_data" $class>
			$checkbox
			<td>$row->config_data $icons</td>
			<td></td>
			<td>$row->config_comment</td>
			<td class="column-actions">$edit_status</td>
		</tr>

HTML;
	}

	/**
	 * Displays the add/edit form
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmDHCP
	 *
	 * @param array $data Either $_POST data or returned SQL results
	 * @param string $action Add or edit
	 * @return string
	 */
	function printForm($data = '', $action = 'add', $type = 'host', $addl_vars = null) {
		global $fmdb, $__FM_CONFIG;
		
		$allow_deny_ignore = array('', 'allow', 'deny', 'ignore');
		$on_off = array('', 'on', 'off');
		$yes_no = array('', 'yes', 'no');
		$slp_directory_agent = $slp_service_scope = null;
		$slp_service_scope_only_checked = $slp_directory_agent_only_checked = null;
		
		$unique_form = $this->printObjectForm($data, $action, $type, array_merge((array) $addl_vars, array('on_off' => $on_off, 'allow_deny_ignore' => $allow_deny_ignore, 'yes_no' => $yes_no)));
		
		$config_id = $config_parent_id = 0;
		$config_name = $config_comment = $children = $parents = null;
		$config_children = array();
		$server_serial_no = (isset($_REQUEST['request_uri']['server_serial_no']) && (intval($_REQUEST['request_uri']['server_serial_no']) > 0 || $_REQUEST['request_uri']['server_serial_no'][0] == 'g')) ? sanitize($_REQUEST['request_uri']['server_serial_no']) : 0;
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		/** Get child elements */
		$lease_time = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'default-lease-time'));
		$max_lease_time = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'max-lease-time'));
		$boot_filename = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'filename'));
		$boot_file_server = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'next-server'));
		$server_name = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'server-name'));
		$bootp_lease_length = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'dynamic-bootp-lease-length'));
		$bootp_lease_cutoff = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'dynamic-bootp-lease-cutoff'));
		$ddns = buildSelect('ddns-updates', 'ddns-updates', $on_off, $this->getConfig($config_id, 'ddns-updates'));
		$ddns_domain_name = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'ddns-domainname'));
		$ddns_rev_domain_name = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'ddns-rev-domainname'));
		$ddns_hostname = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'ddns-hostname'));
		$unknown_clients = buildSelect('unknown-clients', 'unknown-clients', $allow_deny_ignore, $this->getConfig($config_id, 'unknown-clients'));
		$client_updates = buildSelect('client-updates', 'client-updates', $allow_deny_ignore, $this->getConfig($config_id, 'client-updates'));

		$host_name = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'host-name'));
		$routers = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'routers'));
		$subnet_mask = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'subnet-mask'));
		$domain_name_servers = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'domain-name-servers'));
		$broadcast_address = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'broadcast-address'));
		$domain_name = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'domain-name'));
		$domain_search = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'domain-search'));
		$time_servers = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'time-servers'));
		$log_servers = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'log-servers'));
		$swap_server = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'swap-server'));
		$root_path = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'root-path'));
		$nis_domain = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'nis-domain'));
		$nis_servers = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'nis-servers'));
		$font_servers = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'font-servers'));
		$x_display_manager = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'x-display-manager'));
		$ntp_servers = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'ntp-servers'));
		$netbios_name_servers = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'netbios-name-servers'));
		$netbios_scope = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'netbios-scope'));
		$netbios_node_type = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'netbios-node-type'));
		$time_offset = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'time-offset'));
		$dhcp_server_identifier = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'dhcp-server-identifier'));
		$slp_directory_agent_entry = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'slp-directory-agent'));
		if ($slp_directory_agent_entry) {
			list($slp_directory_agent_only, $slp_directory_agent) = explode(' ', $slp_directory_agent_entry);
			$slp_directory_agent_only_checked = ($slp_directory_agent_only == 'true') ? 'checked' : null;
		}
		$slp_service_scope_entry = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'slp-service-scope'));
		if ($slp_service_scope_entry) {
			list($slp_service_scope_only, $slp_service_scope) = explode(' ', $slp_service_scope_entry);
			$slp_service_scope_only_checked = ($slp_service_scope_only == 'true') ? 'checked' : null;
		}

		if ($type != 'shared') {
			$config_parent_id = buildSelect('config_parent_id', 'config_parent_id', $this->getAssignedOptions($type, 'parents'), $config_parent_id);
			$parents = sprintf('<tr>
								<th width="33&#37;" scope="row">%s</th>
								<td width="67&#37;">%s</td>
							</tr>',
					__('Member of'), $config_parent_id
				);
		}
		if (in_array($type, array('groups', 'subnets', 'shared'))) {
			if ($config_id) {
				basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_id', 'config_', "AND config_is_parent='yes' AND config_parent_id=$config_id");
				if ($fmdb->num_rows) {
					for ($i=0; $i<$fmdb->num_rows; $i++) {
						$config_children[] = $fmdb->last_result[$i]->config_id;
					}
				}
			}
			$config_children = buildSelect('config_children', 'config_children', $this->getAssignedOptions($type, 'children'), $config_children, 1, null, true);

			$children = sprintf('<tr>
								<th width="33&#37;" scope="row">%s</th>
								<td width="67&#37;">%s</td>
							</tr>',
					__('Child Objects'), $config_children
				);
		}
		
		$popup_title = ($action == 'add') ? __('Add Item') : __('Edit Item');
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		$return_form = sprintf('
		%s
		<form name="manage" id="manage">
			<input type="hidden" name="action" value="%s" />
			<input type="hidden" name="config_id" value="%d" />
			<input type="hidden" name="config_type" value="%s" />
			<input type="hidden" name="server_serial_no" value="%s" />
			<div id="tabs">
				<div id="tab">
					<input type="radio" name="tab-group-1" id="tab-1" checked />
					<label for="tab-1">%s</label>
					<div id="tab-content">
						<table class="form-table">
							%s
							%s
							%s
							<tr>
								<th width="33&#37;" scope="row"><label for="config_comment">%s</label></th>
								<td width="67&#37;"><textarea id="config_comment" name="config_comment" rows="4" cols="30">%s</textarea></td>
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
								<th width="33&#37;" scope="row"><label for="default-lease-time">%s</label></th>
								<td width="67&#37;"><input name="default-lease-time" id="default-lease-time" type="text" value="%s" style="width: 5em;" onkeydown="return validateNumber(event)" /> %s</td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="max-lease-time">%s</label></th>
								<td width="67&#37;"><input name="max-lease-time" id="max-lease-time" type="text" value="%s" style="width: 5em;" onkeydown="return validateNumber(event)" /> %s</td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="filename">%s</label></th>
								<td width="67&#37;"><input name="filename" id="filename" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="next-server">%s</label></th>
								<td width="67&#37;"><input name="next-server" id="next-server" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="server-name">%s</label></th>
								<td width="67&#37;"><input name="server-name" id="server-name" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="dynamic-bootp-lease-length">%s</label></th>
								<td width="67&#37;"><input name="dynamic-bootp-lease-length" id="dynamic-bootp-lease-length" type="text" value="%s" style="width: 5em;" onkeydown="return validateNumber(event)" /> %s</td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="dynamic-bootp-lease-cutoff">%s</label></th>
								<td width="67&#37;"><input name="dynamic-bootp-lease-cutoff" id="dynamic-bootp-lease-cutoff" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="ddns-updates">%s</label></th>
								<td width="67&#37;">%s</td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="ddns-domainname">%s</label></th>
								<td width="67&#37;"><input name="ddns-domainname" id="ddns-domainname" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="ddns-rev-domainname">%s</label></th>
								<td width="67&#37;"><input name="ddns-rev-domainname" id="ddns-rev-domainname" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="ddns-hostname">%s</label></th>
								<td width="67&#37;"><input name="ddns-hostname" id="ddns-hostname" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="unknown-clients">%s</label></th>
								<td width="67&#37;">%s</td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="client-updates">%s</label></th>
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
							<tr>
								<th width="33&#37;" scope="row"><label for="host-name">%s</label></th>
								<td width="67&#37;"><input name="host-name" id="host-name" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="routers">%s</label></th>
								<td width="67&#37;"><input name="routers" id="routers" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="subnet-mask">%s</label></th>
								<td width="67&#37;"><input name="subnet-mask" id="subnet-mask" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="broadcast-address">%s</label></th>
								<td width="67&#37;"><input name="broadcast-address" id="broadcast-address" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="domain-name">%s</label></th>
								<td width="67&#37;"><input name="domain-name" id="domain-name" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="domain-name-servers">%s</label></th>
								<td width="67&#37;"><input name="domain-name-servers" id="domain-name-servers" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="domain-search">%s</label> <a href="#" class="tooltip-top" data-tooltip="%s"><i class="fa fa-question-circle"></i></a></th>
								<td width="67&#37;"><input name="domain-search" id="domain-search" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="time-servers">%s</label></th>
								<td width="67&#37;"><input name="time-servers" id="time-servers" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="ntp-servers">%s</label></th>
								<td width="67&#37;"><input name="ntp-servers" id="ntp-servers" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="log-servers">%s</label></th>
								<td width="67&#37;"><input name="log-servers" id="log-servers" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="swap-server">%s</label></th>
								<td width="67&#37;"><input name="swap-server" id="swap-server" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="root-path">%s</label></th>
								<td width="67&#37;"><input name="root-path" id="root-path" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="nis-domain">%s</label></th>
								<td width="67&#37;"><input name="nis-domain" id="nis-domain" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="nis-servers">%s</label></th>
								<td width="67&#37;"><input name="nis-servers" id="nis-servers" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="font-servers">%s</label></th>
								<td width="67&#37;"><input name="font-servers" id="font-servers" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="x-display-manager">%s</label></th>
								<td width="67&#37;"><input name="x-display-manager" id="x-display-manager" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="netbios-name-servers">%s</label></th>
								<td width="67&#37;"><input name="netbios-name-servers" id="netbios-name-servers" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="netbios-scope">%s</label></th>
								<td width="67&#37;"><input name="netbios-scope" id="netbios-scope" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="netbios-node-type">%s</label></th>
								<td width="67&#37;"><input name="netbios-node-type" id="netbios-node-type" type="text" value="%s" style="width: 5em;" onkeydown="return validateNumber(event)" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="time-offset">%s</label></th>
								<td width="67&#37;"><input name="time-offset" id="time-offset" type="text" value="%s" style="width: 5em;" onkeydown="return validateNumber(event)" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="dhcp-server-identifier">%s</label></th>
								<td width="67&#37;"><input name="dhcp-server-identifier" id="dhcp-server-identifier" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="slp-directory-agent">%s</label></th>
								<td width="67&#37;" nowrap><input name="slp-directory-agent" id="slp-directory-agent" type="text" value="%s" /><br /><input name="slp-directory-agent-only" id="slp-directory-agent-only" type="checkbox" value="on" %s /> <label for="slp-directory-agent-only">%s</label></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="slp-service-scope">%s</label></th>
								<td width="67&#37;" nowrap><input name="slp-service-scope" id="slp-service-scope" type="text" value="%s" /><br /><input name="slp-service-scope-only" id="slp-service-scope-only" type="checkbox" value="on" %s /> <label for="slp-service-scope-only">%s</label></td>
							</tr>
						</table>
					</div>
				</div>
			</div>
		%s
		</form>
		<script>
			$(document).ready(function() {
				$("#manage select").select2({
					width: "200px",
					minimumResultsForSearch: 10
				});
			});
		</script>',
				$popup_header, $action, $config_id, $type, $server_serial_no,
				__('Basic'),
				$unique_form,
				$parents, $children,
				_('Comment'), $config_comment,
				__('Advanced'),
				__('Default lease time'), $lease_time, __('seconds'),
				__('Maximum lease time'), $max_lease_time, __('seconds'),
				__('Boot filename'), $boot_filename,
				__('Boot file server'), $boot_file_server,
				__('Server name'), $server_name,
				__('Lease length for BOOTP clients'), $bootp_lease_length, __('seconds'),
				__('Lease end for BOOTP clients'), $bootp_lease_cutoff,
				__('Dynamic DNS enabled?'), $ddns,
				__('Dynamic DNS domain name'), $ddns_domain_name,
				__('Dynamic DNS reverse domain'), $ddns_rev_domain_name,
				__('Dynamic DNS hostname'), $ddns_hostname,
				__('Allow unknown clients'), $unknown_clients,
				__('Client updates'), $client_updates,
				__('Options'),
				__('Host name'), $host_name,
				__('Default routers'), $routers,
				__('Subnet mask'), $subnet_mask,
				__('Broadcast address'), $broadcast_address,
				__('Domain name'), $domain_name,
				__('Domain name servers'), $domain_name_servers,
				__('Domains to search'), __('List the domains to search comma-delimited'), $domain_search,
				__('Time servers'), $time_servers,
				__('NTP servers'), $ntp_servers,
				__('Log servers'), $log_servers,
				__('Swap servers'), $swap_server,
				__('Root disk path'), $root_path,
				__('NIS domain'), $nis_domain,
				__('NIS servers'), $nis_servers,
				__('Font servers'), $font_servers,
				__('XDM servers'), $x_display_manager,
				__('NetBIOS name servers'), $netbios_name_servers,
				__('NetBIOS scope'), $netbios_scope,
				__('NetBIOS node type'), $netbios_node_type,
				__('Time offset'), $time_offset,
				__('DHCP server identifier'), $dhcp_server_identifier,
				__('SLP directory agent IPs'), $slp_directory_agent, $slp_directory_agent_only_checked, __('Only these IPs?'),
				__('SLP service scope'), $slp_service_scope, $slp_service_scope_only_checked, __('Only this scope?'),
				$popup_footer
			);

		return $return_form;
	}
	
	/**
	 * Gets config item data from key
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmDHCP
	 *
	 * @param integer $config_id Config parent ID to retrieve children for
	 * @param string $config_opt Config option to retrieve
	 * @return string
	 */
	function getConfig($config_id, $config_opt = null) {
		global $fmdb, $__FM_CONFIG;
		
		$return = '';
		
		/** Get the data from $config_opt */
		$query = "SELECT config_id,config_data FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config WHERE account_id='{$_SESSION['user']['account_id']}' AND config_status!='deleted' AND (config_parent_id='{$config_id}' AND config_parent_id!='0') AND config_name='$config_opt' ORDER BY config_id ASC";
		$result = $fmdb->get_results($query);
		if (!$fmdb->sql_errors && $fmdb->num_rows) {
			$results = $fmdb->last_result;
			$return = $results[0]->config_data;
		}
		
		return $return;
	}
	
	/**
	 * Builds array of available items for assignment
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmDHCP
	 *
	 * @param string $type Type of config item to exclude
	 * @param string $relation Get children or parents
	 * @return array
	 */
	function getAssignedOptions($type, $relation) {
		if ($relation == 'parents') {
			$members = array('shared');
			if ($type != 'subnets') {
				$members[] = 'subnet';
			}
			if (in_array($type, array('hosts', 'host'))) {
				$members = array('group');
			}
		} elseif ($relation == 'children') {
			if ($type == 'groups') {
				$members = array('host');
			} else {
				$members[] = 'group';
			}
			if (in_array($type, array('subnets', 'shared'))) {
				$members[] = 'pool';
			}
			if ($type == 'shared') {
				$members[] = 'subnet';
			}
		} elseif ($relation == 'peers') {
			$members = array('peer');
		}
		
		return $this->availableMembers($members);
	}
	
	
	/**
	 * Returns an array of parents or children
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmDHCP
	 *
	 * @param array $members Array of objects to get members for
	 * @return array
	 */
	private function availableMembers($members) {
		global $fmdb, $__FM_CONFIG;

		$array[0][] = null;
		$array[0][0][] = null;
		$array[0][0][] = '0';

		/** Peers */
		if (in_array('peer', $members)) {
			$j = 0;
			$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_data', 'config_', 'AND config_type="peer" AND config_is_parent="yes"');
			if ($fmdb->num_rows && !$fmdb->sql_errors) {
				$array[__('Failover Peers')][] = null;
				$results = $fmdb->last_result;
				for ($i=0; $i<$fmdb->num_rows; $i++) {
					$array[__('Failover Peers')][$j][] = $results[$i]->config_data;
					$array[__('Failover Peers')][$j][] = $results[$i]->config_id;
					$j++;
				}
			}
		}
		/** Hosts */
		if (in_array('host', $members)) {
			$j = 0;
			$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_data', 'config_', 'AND config_type="host" AND config_is_parent="yes"');
			if ($fmdb->num_rows && !$fmdb->sql_errors) {
				$array[__('Hosts')][] = null;
				$results = $fmdb->last_result;
				for ($i=0; $i<$fmdb->num_rows; $i++) {
					$array[__('Hosts')][$j][] = $results[$i]->config_data;
					$array[__('Hosts')][$j][] = $results[$i]->config_id;
					$j++;
				}
			}
		}
		/** Groups */
		if (in_array('group', $members)) {
			$j = 0;
			$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_data', 'config_', 'AND config_type="group" AND config_is_parent="yes"');
			if ($fmdb->num_rows && !$fmdb->sql_errors) {
				$array[__('Groups')][] = null;
				$results = $fmdb->last_result;
				for ($i=0; $i<$fmdb->num_rows; $i++) {
					$array[__('Groups')][$j][] = $results[$i]->config_data;
					$array[__('Groups')][$j][] = $results[$i]->config_id;
					$j++;
				}
			}
		}
		/** Pools */
		if (in_array('pool', $members)) {
			$j = 0;
			$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_data', 'config_', 'AND config_type="pool" AND config_is_parent="yes"');
			if ($fmdb->num_rows && !$fmdb->sql_errors) {
				$array[_('Pools')][] = null;
				$results = $fmdb->last_result;
				for ($i=0; $i<$fmdb->num_rows; $i++) {
					$array[_('Pools')][$j][] = $results[$i]->config_data;
					$array[_('Pools')][$j][] = $results[$i]->config_id;
					$j++;
				}
			}
		}
		/** Subnets */
		if (in_array('subnet', $members)) {
			$j = 0;
			$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_data', 'config_', 'AND config_type="subnet" AND config_is_parent="yes"');
			if ($fmdb->num_rows && !$fmdb->sql_errors) {
				$array[_('Subnets')][] = null;
				$results = $fmdb->last_result;
				for ($i=0; $i<$fmdb->num_rows; $i++) {
					$array[_('Subnets')][$j][] = $results[$i]->config_data;
					$array[_('Subnets')][$j][] = $results[$i]->config_id;
					$j++;
				}
			}
		}
		/** Shared networks */
		if (in_array('shared', $members)) {
			$j = 0;
			$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_data', 'config_', 'AND config_type="shared" AND config_is_parent="yes"');
			if ($fmdb->num_rows && !$fmdb->sql_errors) {
				$array[_('Shared Networks')][] = null;
				$results = $fmdb->last_result;
				for ($i=0; $i<$fmdb->num_rows; $i++) {
					$array[_('Shared Networks')][$j][] = $results[$i]->config_data;
					$array[_('Shared Networks')][$j][] = $results[$i]->config_id;
					$j++;
				}
			}
		}

		return $array;
	}
	
	
	/**
	 * Displays the subnet range input form
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmDHCP
	 *
	 * @param integer $number Number to start counting at
	 * @param array $values Array containing existing values
	 * @return string
	 */
	function getRangeInputForm($number = 1, $values = null) {
		@list($start, $end, $checked) = $values;
		
		$form = sprintf('<div class="range_input"><input name="range[__NUM__][start]" id="range[__NUM__][]" type="text" value="%s" style="width: 7em;" placeholder="10.1.2.1" /> - 
									<input name="range[__NUM__][end]" id="range[__NUM__][]" type="text" value="%s" style="width: 7em;" placeholder="10.1.2.100" />
									<br /><input name="range[__NUM__][dynamic_bootp]" id="range[__NUM__][dynamic_bootp]" type="checkbox" value="dynamic-bootp" %s /> <label for="range[__NUM__][dynamic_bootp]">%s</label></div>',
				$start, $end, $checked, __('Dynamic BOOTP')
			);
		
		$form = str_replace('__NUM__', $number, $form);
		
		return $form;
	}
	

	/**
	 * Validates the user-submitted data (for add and edit)
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmDHCP
	 *
	 * @param array $post Posted data to validate
	 * @return array|string
	 */
	function validateObjectPost($post) {
		global $__FM_CONFIG, $fm_module_options;
		
		include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_options.php');
		if (array_key_exists('config_name', $post)) {
			$post_tmp['config_name'] = $post['config_name'];
		}
		if (array_key_exists('config_data', $post)) {
			$post_tmp['config_data'] = $post['config_data'];
		}
		
		foreach ($post as $key => $val) {
			if (!$val) continue;
			if (in_array($key, array('slp-directory-agent-only', 'slp-service-scope-only'))) {
				unset($post[$key]);
				continue;
			}
			$post['config_name'] = $key;
			$post['config_data'] = $val;
			$post2 = $fm_module_options->validateDefType($post);
			if (!is_array($post2)) {
				return $post2;
			} else {
				if ($key == 'slp-directory-agent') {
					$true_false = (array_key_exists('slp-directory-agent-only', $post)) ? 'true' : 'false';
					$post[$key] = $true_false . ' ' . $post2['config_data'];
				} elseif ($key == 'slp-service-scope') {
					$true_false = (array_key_exists('slp-service-scope-only', $post)) ? 'true' : 'false';
					$post[$key] = $true_false . ' ' . $post2['config_data'];
				} elseif ($key == 'domain-search') {
					$post[$key] = str_replace(',', '","', $post2['config_data']);
				} else {
					$post[$key] = $post2['config_data'];
				}
			}
		}
		if (array_key_exists('config_name', $post_tmp)) {
			$post['config_name'] = $post_tmp['config_name'];
		} else {
			unset($post['config_name']);
		}
		if (array_key_exists('config_data', $post_tmp)) {
			$post['config_data'] = $post_tmp['config_data'];
		} else {
			unset($post['config_data']);
		}

		if (empty($post['config_name'])) return __('No name is defined.');
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_name');
		if ($field_length !== false && strlen($post['config_name']) > $field_length) return sprintf(dngettext($_SESSION['module'], 'Name is too long (maximum %d character).', 'Name is too long (maximum %d characters).', $field_length), $field_length);
		
		return $post;
	}
	
	
}

if (!isset($fm_dhcp_objects))
	$fm_dhcp_objects = new fm_dhcp_objects();
