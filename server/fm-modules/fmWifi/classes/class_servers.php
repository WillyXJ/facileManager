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
 | fmWifi: Easily manage one or more access points                         |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmwifi/                            |
 +-------------------------------------------------------------------------+
*/

require_once(ABSPATH . 'fm-modules/shared/classes/class_servers.php');

class fm_module_servers extends fm_shared_module_servers {
	
	/**
	 * Displays the server list
	 *
	 * @since 1.0
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param array $result Record rows of all servers
	 * @return null
	 */
	function rows($result, $type, $page, $total_pages) {
		global $fmdb;
		
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;
		
		$bulk_actions_list = null;
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$bulk_actions_list[] = __('Upgrade');
		}
		if (currentUserCan('build_server_configs', $_SESSION['module'])) {
			$bulk_actions_list[] = __('Build Config');
		}
		if (is_array($bulk_actions_list)) {
			$title_array[] = array(
								'title' => '<input type="checkbox" class="tickall" onClick="toggle(this, \'' . rtrim($type, 's') . '_list[]\')" />',
								'class' => 'header-tiny'
							);
		}
		
		$fmdb->num_rows = $num_rows;

		$start = $_SESSION['user']['record_count'] * ($page - 1);
		echo displayPagination($page, $total_pages, @buildBulkActionMenu($bulk_actions_list, 'server_id_list'));

		$table_info = array(
						'class' => 'display_results',
						'id' => 'table_edits',
						'name' => 'servers'
					);

		if ($type == 'servers') {
			$title_array[] = array('class' => 'header-tiny header-nosort');
			$title_array = array_merge($title_array, array(array('title' => __('Hostname'), 'rel' => 'server_name'),
				array('title' => __('Method'), 'rel' => 'server_update_method'),
				array('title' => __('Software'), 'class' => 'header-nosort'),
				array('title' => __('Version'), 'class' => 'header-nosort'),
				array('title' => __('Config File'), 'rel' => 'server_config_file'),
				array('title' => __('Status'), 'class' => 'header-nosort'),
				));
		} elseif ($type == 'groups') {
			$title_array = array_merge((array)$title_array, array(array('title' => __('Group Name'), 'rel' => 'group_name'),
				array('title' => __('Members'), 'class' => 'header-nosort'),
				));
		}
		$title_array[] = array(
							'title' => __('Actions'),
							'class' => 'header-actions header-nosort'
						);

		echo displayTableHeader($table_info, $title_array);

		if ($result) {
			$y = 0;
			for ($x=$start; $x<$num_rows; $x++) {
				if ($y == $_SESSION['user']['record_count']) break;
				$this->displayRow($results[$x], $type);
				$y++;
			}
		}
			
		echo "</tbody>\n</table>\n";
		if (!$result) {
			$message = $type == 'servers' ? __('There are no access points.') : __('There are no access point groups.');
			printf('<p id="table_edits" class="noresult" name="servers">%s</p>', $message);
		}
	}

	/**
	 * Adds the new server
	 *
	 * @since 1.0
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param array $post $_POST data
	 * @return boolean or string
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG, $fm_name;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		$module = (isset($post['module_name'])) ? $post['module_name'] : $_SESSION['module'];

		/** Handle Groups */
		if (array_key_exists('group_name', $post)) {
			/** Server groups */
			$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}server_groups`";
			$sql_fields = '(';
			$sql_values = null;

			$post['account_id'] = $_SESSION['user']['account_id'];
			
			$exclude = array('submit', 'action', 'group_id', 'log_message_member_servers');
		
			foreach ($post as $key => $data) {
				if (!in_array($key, $exclude)) {
					$sql_fields .= $key . ', ';
					$sql_values .= "'" . sanitize($data) . "', ";
				}
			}
			$sql_fields = rtrim($sql_fields, ', ') . ')';
			$sql_values = rtrim($sql_values, ', ');

			$query = "$sql_insert $sql_fields VALUES ($sql_values)";
			$result = $fmdb->query($query);

			if ($fmdb->sql_errors) {
				return formatError(__('Could not add the access point group because a database error occurred.'), 'sql');
			}

			$insert_id = $fmdb->insert_id;
			
			addLogEntry(__('Added an access point group with the following details') . ":\n" . __('Name') . ": {$post['group_name']}\n" . __('Associated APs') . ": ${post['log_message_member_servers']}\n" . _('Comment') . ": {$post['group_comment']}");
			
			return true;
		}
		
		/** Get a valid and unique serial number */
		$post['server_serial_no'] = (isset($post['server_serial_no'])) ? $post['server_serial_no'] : generateSerialNo($module);

		$sql_insert = "REPLACE INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}servers`";
		$sql_fields = '(';
		$sql_values = null;
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		
		$exclude = array('submit', 'action', 'server_id', 'compress', 'AUTHKEY', 'module_name', 'module_type', 'config', 'update_from_client', 'dryrun');

		/** Loop through all posted keys and values to build SQL statement */
		foreach ($post as $key => $data) {
			$clean_data = sanitize($data);
			if (($key == 'server_name') && empty($clean_data)) return __('No server name defined.');
			if (!in_array($key, $exclude)) {
				$sql_fields .= $key . ', ';
				$sql_values .= "'$clean_data', ";
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', ');
		
		/** Add the server */
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the server because a database error occurred.'), 'sql');
		}
		
		/** Add entry to audit log */
		addLogEntry("Added server:\nName: {$post['server_name']} ({$post['server_serial_no']})\n" .
				"Update Method: {$post['server_update_method']}");
		return true;
	}

	/**
	 * Updates the selected server
	 *
	 * @since 1.0
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param array $post $_POST data
	 * @return boolean or string
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		if (array_key_exists('group_name', $_POST)) {
			$new_domain_ids = $post['group_domain_ids'];
			
			/** Get current domain_ids for group */
			$current_domain_ids = $this->getZoneGroupMembers($post['group_id']);
			
			/** Remove group from domain_ids for group */
			$remove_domain_ids = array_diff($current_domain_ids, $new_domain_ids);
			$retval = $this->setZoneGroupMembers($post['group_id'], $remove_domain_ids, 'remove');
			if ($retval !== true) {
				return $retval;
			}
			
			/** Add group to domain_ids */
			$add_domain_ids = array_diff($new_domain_ids, $current_domain_ids);
			$retval = $this->setZoneGroupMembers($post['group_id'], $add_domain_ids);
			if ($retval !== true) {
				return $retval;
			}
			
			/** Update group_name */
			$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domain_groups` SET `group_name`='" . $post['group_name'] . "', `group_comment`='" . $post['group_comment'] . "' WHERE account_id='{$_SESSION['user']['account_id']}' AND `group_id`='" . $post['group_id'] . "'";
			$fmdb->query($query);
			if ($fmdb->sql_errors) {
				return formatError(__('Could not update the zone group because a database error occurred.') . $fmdb->last_query, 'sql');
			}

			$old_name = getNameFromID($post['group_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domain_groups', 'group_', 'group_id', 'group_name');
			$log_message = sprintf(__('Updated a zone group (%s) with the following details'), $old_name) . "\n";
			addLogEntry($log_message . __('Name') . ": {$post['group_name']}\n" . __('Associated Zones') . ": " . $this->getZoneLogDomainNames($new_domain_ids) . "\n" . _('Comment') . ": {$post['group_comment']}");
			return true;
		}

		$exclude = array('submit', 'action', 'server_id', 'compress', 'AUTHKEY', 'module_name', 'module_type', 'config', 'SERIALNO', 'update_from_client', 'dryrun');

		$sql_edit = null;
		
		/** Loop through all posted keys and values to build SQL statement */
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . sanitize($data) . "', ";
			}
		}
		$sql = rtrim($sql_edit, ', ');
		
		/** Update the server */
		$old_name = getNameFromID($post['server_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
		$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}servers` SET $sql WHERE `server_id`={$post['server_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not update the server because a database error occurred.'), 'sql');
		}
		
		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		/** Server changed so configuration needs to be built */
		setBuildUpdateConfigFlag(getServerSerial($post['server_id'], $_SESSION['module']), 'yes', 'build');
		
		/** Add entry to audit log */
		addLogEntry("Updated server '$old_name' to:\nName: {$post['server_name']}\nConfig File: {$post['server_config_file']}");
		return true;
	}
	
	/**
	 * Deletes the selected server
	 *
	 * @since 1.0
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param integer $server_id Server ID to delete
	 * @return boolean or string
	 */
	function delete($server_id, $type) {
		global $fmdb, $__FM_CONFIG;
		
		/** Does the server_id exist for this account? */
		if ($type == 'servers') {
			$server_serial_no = getNameFromID($server_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_id', 'server_serial_no');
			basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', $server_serial_no, 'server_', 'server_serial_no');
			if ($fmdb->num_rows) {
				/** Delete server */
				$tmp_name = getNameFromID($server_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
				if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', $server_id, 'server_', 'deleted', 'server_id')) {
					/** Add entry to audit log */
					addLogEntry(sprintf(__("AP '%s' (%s) was deleted"), $tmp_name, $server_serial_no));
					return true;
				}
			}

			return formatError(__('This access point could not be deleted.'), 'sql');
		} else {
			basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'server_groups', $server_id, 'group_', 'group_id');
			if ($fmdb->num_rows) {
//				/** Delete associated config options */
//				$delete_status = $this->deleteServerSpecificConfigs('g_' . $server_id, 'g_' . $server_id);
//				if ($delete_status !== true) return $delete_status;

				/** Delete group */
				$tmp_name = getNameFromID($server_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'server_groups', 'group_', 'group_id', 'group_name');
				if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'server_groups', $server_id, 'group_', 'deleted', 'group_id')) {
					addLogEntry(sprintf(__("AP group '%s' was deleted."), $tmp_name));
					return true;
				}
			}

			return formatError(__('This access point group could not be deleted.'), 'sql');
		}
	}


	/**
	 * Displays the server entry table row
	 *
	 * @since 1.0
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param object $row Single data row from $results
	 * @param strong $type Type of form row to display
	 * @return null
	 */
	function displayRow($row, $type) {
		global $__FM_CONFIG;
		
		$class = ($row->server_status == 'disabled') ? 'disabled' : null;
		
		$os_image = setOSIcon($row->server_os_distro);
		
		$edit_status = $edit_actions = null;
		$edit_actions = $row->server_status == 'active' ? '<a href="preview.php" onclick="javascript:void window.open(\'preview.php?server_serial_no=' . $row->server_serial_no . '\',\'1356124444538\',\'width=700,height=500,toolbar=0,menubar=0,location=0,status=0,scrollbars=1,resizable=1,left=0,top=0\');return false;">' . $__FM_CONFIG['icons']['preview'] . '</a>' : null;
		
		$checkbox = (currentUserCan(array('manage_servers', 'build_server_configs'), $_SESSION['module'])) ? '<td><input type="checkbox" name="server_list[]" value="' . $row->server_serial_no .'" /></td>' : null;
		
		if ($type == 'servers') {
			if (currentUserCan('build_server_configs', $_SESSION['module']) && $row->server_installed == 'yes') {
				if ($row->server_build_config == 'yes' && $row->server_status == 'active' && $row->server_installed == 'yes') {
					$edit_actions .= $__FM_CONFIG['icons']['build'];
					$class = 'build';
				}
			}
			if (currentUserCan('manage_servers', $_SESSION['module'])) {
				$edit_status = '<a class="edit_form_link" name="' . $type . '" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
				if ($row->server_installed == 'yes') {
					$edit_status .= '<a class="status_form_link" href="#" rel="';
					$edit_status .= ($row->server_status == 'active') ? 'disabled' : 'active';
					$edit_status .= '">';
					$edit_status .= ($row->server_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
					$edit_status .= '</a>';
				}
				$edit_status .= '<a href="#" class="delete" name="' . $type . '">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			}

			if (isset($row->server_client_version) && version_compare($row->server_client_version, getOption('client_version', 0, $_SESSION['module']), '<')) {
				$edit_actions = __('Client Upgrade Available') . '<br />';
				$class = 'attention';
			}
			if ($row->server_installed != 'yes') {
				$edit_actions = __('Client Install Required') . '<br />';
			}
			$edit_status = $edit_actions . $edit_status;

			$port = ($row->server_update_method != 'cron') ? '(tcp/' . $row->server_update_port . ')' : null;
			
			/** AP status */
			if ($row->server_installed == 'yes' && $row->server_status == 'active') {
				$ap_status = sprintf('<i class="fa fa-circle-o-notch fa-spin grey" title="%s" aria-hidden="true"></i>', __('Retrieving status'));
			} else {
				$ap_status = null;
			}

			if ($class) $class = 'class="' . $class . '"';

			echo <<<HTML
		<tr id="$row->server_id" name="$row->server_name" $class>
			$checkbox
			<td>$os_image</td>
			<td title="$row->server_serial_no">$row->server_name</td>
			<td>$row->server_update_method $port</td>
			<td>$row->server_type</td>
			<td>$row->server_version</td>
			<td>$row->server_config_file</td>
			<td id="ap_status">$ap_status</td>
			<td id="row_actions">$edit_status</td>
		</tr>

HTML;
		} elseif ($type == 'groups') {
			$checkbox = (currentUserCan(array('manage_servers', 'build_server_configs'), $_SESSION['module'])) ? '<td><input type="checkbox" name="group_list[]" value="g' . $row->group_id .'" /></td>' : null;

			if (currentUserCan('manage_servers', $_SESSION['module'])) {
				$edit_status = '<a class="edit_form_link" name="' . $type . '" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
				$edit_status .= '<a class="status_form_link" href="#" rel="';
				$edit_status .= ($row->group_status == 'active') ? 'disabled' : 'active';
				$edit_status .= '">';
				$edit_status .= ($row->group_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
				$edit_status .= '</a>';
//				$query = "SELECT domain_id FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domains WHERE account_id='{$_SESSION['user']['account_id']}' AND domain_status!='deleted' AND 
//					(domain_name_servers='g_{$row->group_id}' OR domain_name_servers LIKE 'g_{$row->group_id};%' OR domain_name_servers LIKE '%;g_{$row->group_id};%' OR domain_name_servers LIKE '%;g_{$row->group_id}')";
//				$result = $fmdb->get_results($query);
//				if (!$fmdb->num_rows) {
					$edit_status .= '<a href="#" class="delete" name="' . $type . '">' . $__FM_CONFIG['icons']['delete'] . '</a>';
//				}
			}
			
			/** Process group members */
			foreach (explode(';', $row->group_members) as $server_id) {
				$members[] = getNameFromID(str_replace('s_', '', $server_id), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
			}
			
			$group_members = implode('; ', array_map(function($value) {
				return $value == null ? sprintf('<i>%s</i>', __('missing')) : $value;
			}, $members));
			if (empty($group_members) || !count($members) || (count($members) == 1 && empty($members[0]))) $group_members = __('None');
			
			if ($class) $class = 'class="' . $class . '"';
			
			$group_members = wordwrap($group_members);

			echo <<<HTML
		<tr id="$row->group_id" name="$row->group_name" $class>
			$checkbox
			<td>$row->group_name</td>
			<td>$group_members</td>
			<td id="row_actions">$edit_status</td>
		</tr>

HTML;
		}
	}

	/**
	 * Displays the server add/edit form
	 *
	 * @since 1.0
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param array $data Either $_POST data or returned SQL results
	 * @param string $action Add or edit
	 * @param string $type
	 * @return string
	 */
	function printForm($data = '', $action = 'add', $type = 'servers') {
		global $__FM_CONFIG;
		
		$server_id = $group_id = 0;
		$server_name = $runas = $server_type = $server_update_port = null;
		$server_update_method = $server_config_file = $server_os = null;
		$group_name = $group_members = $group_comment = $server_mode = null;
		$server_wlan_driver = $server_wlan_interface = $wlan_interface = null;
		$ucaction = ucfirst($action);
		$server_installed = false;
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}

		/** Show/hide divs */
		if (isset($server_run_as_predefined) && $server_run_as_predefined == 'as defined:') {
			$runashow = 'block';
		} else {
			$runashow = 'none';
			$server_run_as = null;
		}
		$server_update_port_style = ($server_update_method == 'cron') ? 'style="display: none;"' : 'style="display: block;"';
		
		$disabled = ($server_installed == 'yes') ? 'disabled' : null;
		
		if ($server_installed == 'yes') {
			if (strpos($server_update_method, 'http') === false) {
				$server_update_method_choices = array($server_update_method);
			} else {
				$server_update_method_choices = array('http', 'https');
			}
		} else {
			$server_update_method_choices = enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_update_method');
		}
		
		$server_update_method = buildSelect('server_update_method', 'server_update_method', $server_update_method_choices, $server_update_method, 1);
		$server_mode_dropdown = buildSelect('server_mode', 'server_mode', array(array(__('Router'), 'router'), array(__('Bridged'), 'bridge')), $server_mode, 1, $disabled);
		
		if ($type == 'groups') $server_id = $group_id;

		if ($action == 'add') {
			$popup_title = $type == 'servers' ? __('Add Access Point') : __('Add Access Point Group');
		} else {
			$popup_title = $type == 'servers' ? __('Edit Access Point') : __('Edit Access Point Group');
		}
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		$alternative_help = ($action == 'add' && getOption('client_auto_register')) ? sprintf('<p><b>%s</b> %s</p>', __('Note:'), __('The client installer can automatically generate this entry.')) : null;
		$server_name_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_name');

		$return_form = sprintf('<form name="manage" id="manage" method="post" action="">
		%s
			<input type="hidden" name="action" value="%s" />
			<input type="hidden" name="%s_id" value="%d" />',
				$popup_header, $action, rtrim($type, 's'), $server_id
		);
		
		if ($type == 'servers') {
			if ($action == 'edit') {
				$available_interfaces = explode(';', $server_interfaces);
				$bridge_interfaces = buildSelect('server_bridge_interface', 'server_bridge_interface', $available_interfaces, $server_bridge_interface);
				$wlan_interfaces = buildSelect('server_wlan_interface', 'server_wlan_interface', $available_interfaces, $server_wlan_interface);
				$server_wlan_driver = buildSelect('server_wlan_driver', 'server_wlan_driver', enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_wlan_driver'), $server_wlan_driver);

				$bridge_if = ($server_mode == 'bridge') ? sprintf('<tr>
					<th width="33&#37;" scope="row"><label for="server_bridge_interface">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
				', __('Bridge Interface'), $bridge_interfaces) : sprintf('<tr>
					<th width="33&#37;" scope="row"><label for="server_wlan_driver">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
				', __('Driver'), $server_wlan_driver);
				
				$wlan_interface = sprintf('<tr>
					<th width="33&#37;" scope="row"><label for="server_wlan_interface">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
				%s
				',
					__('WLAN Interface'), $wlan_interfaces, $bridge_if);
			}
			
			$return_form .= sprintf('%s
			<table class="form-table">
				<tr>
					<th width="33&#37;" scope="row"><label for="server_name">%s</label></th>
					<td width="67&#37;"><input name="server_name" id="server_name" type="text" value="%s" size="40" placeholder="placeholder" maxlength="%d" /></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="server_update_method">%s</label></th>
					<td width="67&#37;">%s<div id="server_update_port_option" %s><input type="number" name="server_update_port" value="%s" placeholder="80" onkeydown="return validateNumber(event)" maxlength="5" max="65535" /></div></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="server_config_file">%s</label></th>
					<td width="67&#37;"><input name="server_config_file" id="server_config_file" type="text" value="%s" size="40" /></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="server_mode">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
				%s
			</table>
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
				$alternative_help,
				__('Server Name'), $server_name, $server_name_length,
				__('Update Method'), $server_update_method, $server_update_port_style, $server_update_port,
				__('Config File'), $server_config_file,
				__('AP Mode'), $server_mode_dropdown, $wlan_interface,
				$popup_footer
			);
		} elseif ($type == 'groups') {
			$group_members = (isset($group_members)) ? explode(';', $group_members) : null;
			
			$group_name_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'server_groups', 'group_name');
			$group_members = buildSelect('group_members', 'group_members', availableServers('id', 'servers'), $group_members, 1, null, true, null, null, __('Select group members'));

			$return_form .= sprintf('
			<table class="form-table">
				<tr>
					<th width="33&#37;" scope="row"><label for="group_name">%s</label></th>
					<td width="67&#37;"><input name="group_name" id="group_name" type="text" value="%s" size="40" maxlength="%d" /></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="group_masters">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="group_comment">%s</label></th>
					<td width="67&#37;"><textarea id="group_comment" name="group_comment" rows="4" cols="30">%s</textarea></td>
				</tr>
			</table>
		%s
		</form>
		<script>
			$(document).ready(function() {
				$("#manage select").select2({
					minimumResultsForSearch: 10,
					allowClear: true,
					width: "230px"
				});
			});
		</script>', 
					__('Group Name'), $group_name, $group_name_length,
					__('Member APs'), $group_members, 
					_('Comment'), $group_comment,
					$popup_footer);
		} else {
			$return_form = buildPopup('header', _('Error'));
			$return_form .= sprintf('<h3>%s</h3><p>%s</p>', __('Oops!'), __('Invalid request.'));
			$return_form .= buildPopup('footer', _('OK'), array('cancel'));
		}

		return $return_form;
	}
	
	/**
	 * Validates the user-submitted data (for add and edit)
	 *
	 * @since 1.0
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param array $post Posted data to validate
	 * @return array
	 */
	function validatePost($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Server groups */
		if (array_key_exists('group_name', $post)) {
			/** Empty domain names are not allowed */
			$post['group_name'] = sanitize($post['group_name']);
			if (empty($post['group_name'])) return __('No group name defined.');
			
			/** Check if the group name already exists */
			basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'server_groups', sanitize($post['group_name']), 'group_', 'group_name', "AND group_id!={$post['group_id']}");
			if ($fmdb->num_rows) return __('Access point group already exists.');
			
			/** Check name field length */
			$field_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'server_groups', 'group_name');
			if ($field_length !== false && strlen($post['group_name']) > $field_length) return sprintf(dngettext($_SESSION['module'], 'Group name is too long (maximum %d character).', 'Group name is too long (maximum %d characters).', $field_length), $field_length);
			
			if ($post['group_comment']) {
				$post['group_comment'] = sanitize($post['group_comment']);
			}
			
			/** Process group masters */
			$log_message_member_servers = null;
			foreach ($post['group_members'] as $val) {
				if (!$val) {
					$group_members = 0;
					break;
				}
				$group_members .= $val . ';';
				$server_name = getNameFromID(str_replace('s_', '', $val), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
				$log_message_member_servers .= $val ? "$server_name; " : null;
			}
			$post['log_message_member_servers'] = rtrim ($log_message_member_servers, '; ');
			$post['group_members'] = rtrim($group_members, ';');
			if (!isset($post['group_members'])) $post['group_members'] = 0;
			
			return $post;
		}
		
		if (empty($post['server_name'])) return __('No server name defined.');
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_name');
		if ($field_length !== false && strlen($post['server_name']) > $field_length) return sprintf(dngettext($_SESSION['module'], 'Server name is too long (maximum %d character).', 'Server name is too long (maximum %d characters).', $field_length), $field_length);
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', $post['server_name'], 'server_', 'server_name', "AND server_id!='{$post['server_id']}'");
		if ($fmdb->num_rows) return __('This server name already exists.');
		
		if (empty($post['server_config_file'])) {
			unset($post['server_config_file']);
		}
		
		/** Set default ports */
		if (empty($post['server_update_port']) || (isset($post['server_update_port']) && $post['server_update_method'] == 'cron')) {
			$post['server_update_port'] = 0;
		}
		if (!empty($post['server_update_port']) && !verifyNumber($post['server_update_port'], 1, 65535, false)) return __('Server update port must be a valid TCP port.');
		if (empty($post['server_update_port']) && isset($post['server_update_method'])) {
			if ($post['server_update_method'] == 'http') $post['server_update_port'] = 80;
			elseif ($post['server_update_method'] == 'https') $post['server_update_port'] = 443;
			elseif ($post['server_update_method'] == 'ssh') $post['server_update_port'] = 22;
		}
		
		return $post;
	}
	
	/**
	 * Looks up the server group IDs
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param integer $server_id Server ID to lookup
	 * @param string $field WHat field to return
	 * @return array
	 */
	function getServerGroups($server_id, $field = 'group_id') {
		global $fmdb, $__FM_CONFIG;
		
		$array = false;
		
		$query = "SELECT * FROM `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}server_groups` WHERE `account_id`='{$_SESSION['user']['account_id']}' AND `group_status`='active'
			AND (group_members='s_$server_id' OR group_members LIKE 's_$server_id;%' OR group_members LIKE '%;s_$server_id;%' OR group_members LIKE '%;s_$server_id')";
		$fmdb->get_results($query);
		if ($fmdb->num_rows) {
			foreach ($fmdb->last_result as $group_info) {
				$array[] = $group_info->$field;
			}
		}
		
		return $array;
	}

	/**
	 * Looks up the server information
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param integer $server_serial_no Server serial number to lookup
	 * @return array
	 */
	function getServerInfo($server_serial_no) {
		global $fmdb, $__FM_CONFIG;
		$data = null;
		
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', $server_serial_no, 'server_', 'server_serial_no');
		if ($fmdb->num_rows) {
			$server_result = $fmdb->last_result;
			$data = $server_result[0];
			extract(get_object_vars($data), EXTR_SKIP);
			
			/** Disabled server */
			if ($server_status != 'active') {
				return "Server is $server_status.\n";
			}
			
			return get_object_vars($data);
		}
		
		/** Bad server */
		return "Server is not found.\n";
	}

	/**
	 * Gets the AP status
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param integer $server_id Server ID to query
	 * @return array
	 */
	function getAPStats($server_id, $command_args) {
		global $fmdb, $__FM_CONFIG;
		$data = null;
		
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', $server_id, 'server_', 'server_id');
		if ($fmdb->num_rows) {
			return autoRunRemoteCommand($fmdb->last_result[0], $command_args, 'return');
		}
	}

	/**
	 * Looks up the server names for a WLAN
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param integer $server_id Server ID to lookup
	 * @return array
	 */
	function getServerNames($server_id) {
		global $fmdb, $__FM_CONFIG;
		
		$ap_names = array();
		
		if ($server_id[0] == 'g') {
			$group_members = getNameFromID(str_replace('g_', '', $server_id), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'server_groups', 'group_', 'group_id', 'group_members');
			foreach (explode(';', $group_members) as $server_id) {
				$ap_names = array_merge($ap_names, $this->getServerNames($server_id));
			}
			return $ap_names;
		} else {
			$table = 'servers';
			$prefix = 'server_';
		}
		
		$ap_names[] = getNameFromID(str_replace('s_', '', $server_id), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
		
		return $ap_names;
	}

}

if (!isset($fm_module_servers))
	$fm_module_servers = new fm_module_servers();

?>
