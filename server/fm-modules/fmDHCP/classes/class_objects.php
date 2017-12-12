<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2013-2018 The facileManager Team                               |
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
						'class' => 'display_results',
						'id' => 'table_edits',
						'name' => $type
					);

		if (is_array($bulk_actions_list)) {
			$title_array[] = array(
								'title' => '<input type="checkbox" class="tickall" onClick="toggle(this, \'bulk_list[]\')" />',
								'class' => 'header-tiny header-nosort'
							);
		}
		$title_array = array_merge((array) $title_array, $this->getTableHeader());
		if (is_array($bulk_actions_list)) $title_array[] = array('title' => _('Actions'), 'class' => 'header-actions');

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
	 * @param string $type Object type
	 * @return boolean or string
	 */
	function add($post, $type) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
//		echo '<pre>';print_r($post); exit;
		
		/** Insert the parent */
		$sql_start = "INSERT INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config`";
		$sql_fields = '(';
		$sql_values = null;
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		$post['config_is_parent'] = 'yes';
		$post['config_data'] = $name = $post['config_name'];
		$post['config_name'] = $post['config_type'] = rtrim($post['config_type'], 's');
		$post['config_comment'] = trim($post['config_comment']);
		
		if (empty($name)) return __('No name defined.');
		
//		/** Ensure unique channel names */
//		if (!$this->validateChannel($post)) return __('This channel already exists.');
		
//		if ($post['config_destination'] == 'file') {
//			if (empty($post['config_file_path'][0])) return __('No file path defined.');
//		}
		$include = array_merge(array('account_id', 'server_serial_no', 'config_is_parent', 'config_data', 'config_type', 'config_name', 'config_comment', 'config_parent_id'), $this->getIncludedFields());
		
		/** Insert the category parent */
		foreach ($post as $key => $data) {
			if (in_array($key, $include)) {
				$clean_data = sanitize($data);
				if ($clean_data) {
					$sql_fields .= $key . ', ';
					$sql_values .= "'$clean_data', ";
				}
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_start $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
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
		
		$include = array_diff(array_keys($post), $include, array('config_id', 'action', 'tab-group-1', 'submit'));
		
		$sql_start = "INSERT INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config`";
		$sql_fields = '(';
		$sql_values = '(';
		
		$i = 1;
		foreach ($include as $handler) {
//			$post['config_data'] = $post[$handler];
//			/** Logic checking */
//			if ($handler == 'config_destination' && $post[$handler] == 'syslog') {
//				$post['config_data'] = $post['config_syslog'];
//			} elseif ($handler == 'config_destination' && $post[$handler] == 'file') {
//				list($file_path, $file_versions, $file_size, $file_size_spec) = $post['config_file_path'];
//				$filename = str_replace('"', '', $file_path);
//				$post['config_data'] = '"' . $filename . '"';
//				if ($file_versions) $post['config_data'] .= ' versions ' . $file_versions;
//				if (!empty($file_size) && $file_size > 0) $post['config_data'] .= ' size ' . $file_size . $file_size_spec;
//			}
//			if ($handler == 'config_destination') {
//				$post['config_name'] = $post['config_destination'];
//			} elseif (in_array($handler, array('print-category', 'print-severity', 'print-time')) && !sanitize($post['config_data'])) {
//				continue;
//			} else {
				$child['config_name'] = $handler;
				$child['config_data'] = $post[$handler];
//			}
			
			foreach ($child as $key => $data) {
				$clean_data = sanitize($data);
				if ($i) $sql_fields .= $key . ', ';
				
				$sql_values .= "'$clean_data', ";
			}
			$i = 0;
			$sql_values = rtrim($sql_values, ', ') . '), (';
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', (');
		
		$query = "$sql_start $sql_fields VALUES $sql_values";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the item because a database error occurred.'), 'sql');
		}
		
		$log_message = "Added host:\nName: $name\nHardware Address: {$post['hardware_address']}\nFixed Address: {$post['fixed_address']}";
		$log_message .= "\nComment: {$post['config_comment']}";
		addLogEntry($log_message);
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
	 * @return boolean or string
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
//		echo '<pre>';print_r($post);exit;
		
		/** Update the parent */
		$sql_start = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config` SET ";
		$sql_values = null;
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		$post['config_is_parent'] = 'yes';
		$post['config_data'] = $name = $post['config_name'];
		$post['config_name'] = $post['config_type'] = rtrim($post['config_type'], 's');
		$post['config_comment'] = trim($post['config_comment']);
		
		if (empty($name)) return __('No name defined.');
		
		$include = array_merge(array('account_id', 'server_serial_no', 'config_is_parent', 'config_data', 'config_type', 'config_name', 'config_comment', 'config_parent_id'), $this->getIncludedFields());
		
		/** Insert the category parent */
		foreach ($post as $key => $data) {
			if (in_array($key, $include)) {
				$clean_data = sanitize($data);
				if ($clean_data) {
					$sql_values .= "$key='$clean_data', ";
				}
			}
		}
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_start $sql_values WHERE config_id={$post['config_id']} LIMIT 1";
		$result = $fmdb->query($query);
		
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
		
		$include = array_diff(array_keys($post), $include, array('config_id', 'action', 'tab-group-1', 'submit', 'account_id', 'config_children'));
		$sql_start = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config` SET ";
		
		foreach ($include as $handler) {
			$sql_values = null;
//			$post['config_data'] = $post[$handler];
//			/** Logic checking */
//			if ($handler == 'config_destination' && $post[$handler] == 'syslog') {
//				$post['config_data'] = $post['config_syslog'];
//			} elseif ($handler == 'config_destination' && $post[$handler] == 'file') {
//				list($file_path, $file_versions, $file_size, $file_size_spec) = $post['config_file_path'];
//				$filename = str_replace('"', '', $file_path);
//				$post['config_data'] = '"' . $filename . '"';
//				if ($file_versions) $post['config_data'] .= ' versions ' . $file_versions;
//				if (!empty($file_size) && $file_size > 0) $post['config_data'] .= ' size ' . $file_size . $file_size_spec;
//			}
//			if ($handler == 'config_destination') {
//				$post['config_name'] = $post['config_destination'];
//			} elseif (in_array($handler, array('print-category', 'print-severity', 'print-time')) && !sanitize($post['config_data'])) {
//				continue;
//			} else {
				$child['config_name'] = $handler;
				$child['config_data'] = $post[$handler];
//			}
			
			foreach ($child as $key => $data) {
				$clean_data = sanitize($data);
				$sql_values .= "$key='$clean_data', ";
			}
			$sql_values = rtrim($sql_values, ', ');
			
			$query = "$sql_start $sql_values WHERE config_parent_id={$post['config_id']} AND config_name='$handler' LIMIT 1";
			$result = $fmdb->query($query);

			if ($fmdb->sql_errors) {
				return formatError(__('Could not update the item because a database error occurred.'), 'sql');
			}
		}
		
		/** Reassigned children */
		$query = "$sql_start config_parent_id=0 WHERE config_parent_id={$post['config_id']} AND config_is_parent='yes'";
		$result = $fmdb->query($query);
		$query = "$sql_start config_parent_id={$post['config_id']} WHERE config_id IN (" . join(',', $post['config_children']) . ")";
		$result = $fmdb->query($query);

		return true;
		exit;
		
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the item because a database error occurred.'), 'sql');
		}
		
		
		
		
		
		$exclude = array('submit', 'action', 'server_id', 'compress', 'AUTHKEY', 'module_name', 'module_type', 'config', 'SERIALNO', 'update_from_client', 'dryrun');

		$sql_edit = null;
		
		/** Loop through all posted keys and values to build SQL statement */
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . sanitize($data) . "',";
			}
		}
		$sql = rtrim($sql_edit, ',');
		
		/** Update the server */
		$old_name = getNameFromID($post['server_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
		$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config` SET $sql WHERE `server_id`={$post['server_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not update the server because a database error occurred.'), 'sql');
		}
		
		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		/** Server changed so configuration needs to be built */
		setBuildUpdateConfigFlag(getServerSerial($post['server_id'], $_SESSION['module']), 'yes', 'build');
		
		/** Add entry to audit log */
		addLogEntry("Updated server '$old_name' to:\nName: {$post['server_name']}\nType: {$post['server_type']}\n" .
					"Update Method: {$post['server_update_method']}\nConfig File: {$post['server_config_file']}");
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
	 * @return boolean or string
	 */
	function delete($id, $server_serial_no = 0) {
		global $fmdb, $__FM_CONFIG;
		
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_', 'config_id', 'config_data');

		/** Delete associated children */
		updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $id, 'config_', 'deleted', 'config_parent_id');
		
		/** Delete item */
		if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $id, 'config_', 'deleted', 'config_id') === false) {
			return formatError(__('This host could not be deleted because a database error occurred.'), 'sql');
		} else {
			setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
			addLogEntry(sprintf(__("Host '%s' was deleted."), $tmp_name));
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
		
		$edit_status = $edit_actions = $icons = null;
		
		$checkbox = (currentUserCan(array('manage_servers', 'build_server_configs'), $_SESSION['module'])) ? '<td><input type="checkbox" name="bulk_list[]" value="' . $row->config_id .'" /></td>' : null;
		
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$edit_status = '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a class="status_form_link" href="#" rel="';
			$edit_status .= ($row->config_status == 'active') ? 'disabled' : 'active';
			$edit_status .= '">';
			$edit_status .= ($row->config_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
			$edit_status .= '</a>';
			$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			$icons[] = sprintf('<a href="config-options.php?item_id=%d" class="icons"><i class="icons fa fa-sliders" title="%s" aria-hidden="true"></i></a>', $row->config_id, __('Configure Additional Options'));
		}
		
		$edit_status = $edit_actions . $edit_status;
		
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
			<td id="edit_delete_img">$edit_status</td>
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
	function printForm($data = '', $action = 'add', $type = 'host') {
		global $fmdb, $__FM_CONFIG;
		
		$allow_deny_ignore = array('', 'allow', 'deny', 'ignore');
		$on_off = array('', 'on', 'off');
		$yes_no = array('', 'yes', 'no');
		
		$unique_form = $this->printObjectForm($data, $action, $type, array('on_off' => $on_off, 'allow_deny_ignore' => $allow_deny_ignore, 'yes_no' => $yes_no));
		
		$config_id = $config_parent_id = 0;
		$config_name = $config_comment = $children = $config_children = $parents = null;
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
		
		$return_form = sprintf('<form name="manage" id="manage" method="post" action="">
		%s
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
				__('Comment'), $config_comment,
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
		
		$return = null;
		
		/** Get the data from $config_opt */
		$query = "SELECT config_id,config_data FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config WHERE account_id='{$_SESSION['user']['account_id']}' AND config_status!='deleted' AND config_parent_id='{$config_id}' AND config_name='$config_opt' ORDER BY config_id ASC";
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
	function getAssignedOptions($type = 'host', $relation) {
		global $fmdb, $__FM_CONFIG;
		
		if ($relation == 'parents') {
			$members = array('shared');
			if ($type != 'subnets') {
				$members[] = 'subnet';
			}
			if ($type == 'hosts') {
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
	 * @param string $values Array containing existing values
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
	 * @return array
	 */
	function validateObjectPost($post) {
		global $__FM_CONFIG;
		
		include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_options.php');
		if (array_key_exists('config_name', $post)) {
			$post_tmp['config_name'] = $post['config_name'];
		}
		if (array_key_exists('config_data', $post)) {
			$post_tmp['config_data'] = $post['config_data'];
		}
		foreach ($post as $key => $val) {
			if (!$val) continue;
			$post['config_name'] = $key;
			$post['config_data'] = $val;
			$post2 = $fm_module_options->validateDefType($post);
			if (!is_array($post2)) {
				return $post2;
			} else {
				$post[$key] = $post2['config_data'];
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
		if ($field_length !== false && strlen($post['config_name']) > $field_length) return sprintf(dngettext($_SESSION['module'], 'Group name is too long (maximum %d character).', 'Host name is too long (maximum %d characters).', $field_length), $field_length);
		
		return $post;
	}
	
	
}

if (!isset($fm_dhcp_objects))
	$fm_dhcp_objects = new fm_dhcp_objects();

?>
