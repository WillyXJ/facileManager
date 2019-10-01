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
 | fmSQLPass: Change database user passwords across multiple servers.      |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmsqlpass/                         |
 +-------------------------------------------------------------------------+
*/

require_once(ABSPATH . 'fm-modules/shared/classes/class_servers.php');

class fm_module_servers extends fm_shared_module_servers {
	
	/**
	 * Displays the server list
	 */
	function rows($result, $page, $total_pages) {
		global $fmdb;
		
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;

		$start = $_SESSION['user']['record_count'] * ($page - 1);
		echo displayPagination($page, $total_pages);

		if (!$result) {
			printf('<p id="table_edits" class="noresult" name="servers">%s</p>', __('There are no servers defined.'));
		} else {
			$table_info = array(
							'class' => 'display_results',
							'id' => 'table_edits',
							'name' => 'servers'
						);

			$title_array = array(__('Hostname'), __('Type'), __('Groups'));
			if (currentUserCan('manage_servers', $_SESSION['module'])) $title_array[] = array('title' => __('Actions'), 'class' => 'header-actions');

			echo displayTableHeader($table_info, $title_array);
			
			$y = 0;
			for ($x=$start; $x<$num_rows; $x++) {
				if ($y == $_SESSION['user']['record_count']) break;
				$this->displayRow($results[$x]);
				$y++;
			}
			
			echo "</tbody>\n</table>\n";
		}
	}

	/**
	 * Adds the new server
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG;
		
		extract($post, EXTR_SKIP);
		
		$server_name = sanitize($server_name);
		
		if (empty($server_name)) return __('No server name defined.');
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'servers', 'server_name');
		if ($field_length !== false && strlen($server_name) > $field_length) return sprintf(__('Server name is too long (maximum %d characters).'), $field_length);
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'servers', $server_name, 'server_', 'server_name');
		if ($fmdb->num_rows) return __('This server name already exists.');
		
		$sql_insert = "REPLACE INTO `fm_{$__FM_CONFIG['fmSQLPass']['prefix']}servers`";
		$sql_fields = '(';
		$sql_values = null;
		
		$log_message = "Added a database server with the following details:\n";

		$post['account_id'] = $_SESSION['user']['account_id'];
		
		/** Set default ports */
		if (!empty($post['server_port']) && !verifyNumber($post['server_port'], 1, 65535, false)) return __('Server port must be a valid TCP port.');
		if (empty($post['server_port'])) {
			$post['server_port'] = $__FM_CONFIG['fmSQLPass']['default']['ports'][$post['server_type']];
		}
		
		$module = isset($post['module_name']) ? $post['module_name'] : $_SESSION['module'];

		/** Get a valid and unique serial number */
		$post['server_serial_no'] = (isset($post['server_serial_no'])) ? $post['server_serial_no'] : generateSerialNo($module);

		$exclude = array('submit', 'action', 'server_id', 'compress', 'AUTHKEY', 'module_name', 'module_type', 'config');

		/** Convert groups and policies arrays into strings */
		if (isset($post['server_groups']) && is_array($post['server_groups'])) {
			$temp_var = null;
			foreach ($post['server_groups'] as $id) {
				$temp_var .= $id . ';';
			}
			$post['server_groups'] = rtrim($temp_var, ';');
		}
		
		/** Handle credentials */
		if (is_array($post['server_credentials'])) {
			$post['server_credentials'] = serialize($post['server_credentials']);
		}
		
		foreach ($post as $key => $data) {
			$clean_data = sanitize($data);
			if (!in_array($key, $exclude)) {
				$sql_fields .= $key . ', ';
				$sql_values .= "'$clean_data', ";
				if ($key == 'server_credentials') {
					$clean_data = str_repeat('*', 7);
				}
				if ($key == 'server_groups') {
					if ($post['server_groups']) {
						$group_array = explode(';', $post['server_group']);
						$clean_data = null;
						foreach ($group_array as $group_id) {
							$clean_data .= getNameFromID($group_id, 'fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'groups', 'group_', 'group_id', 'group_name') . '; ';
						}
						$clean_data = rtrim($clean_data, '; ');
					} else $clean_data = 'None';
				}
				$log_message .= ($clean_data && $key != 'account_id') ? formatLogKeyData('server_', $key, $clean_data) : null;
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the server because a database error occurred.'), 'sql');
		}

		addLogEntry($log_message);
		return true;
	}

	/**
	 * Updates the selected server
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		if (empty($post['server_name'])) return __('No server name defined.');

		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'servers', 'server_name');

		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'servers', sanitize($post['server_name']), 'server_', 'server_name');
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			if ($result[0]->server_id != $post['server_id']) return __('This server name already exists.');
		}
		
		/** Set default ports */
		if (!empty($post['server_port']) && !verifyNumber($post['server_port'], 1, 65535, false)) return __('Server port must be a valid TCP port.');
		if (empty($post['server_port'])) {
			$post['server_port'] = $__FM_CONFIG['fmSQLPass']['default']['ports'][$post['server_type']];
		}
		
		$exclude = array('submit', 'action', 'server_id', 'page');

		$sql_edit = null;
		
		$old_name = getNameFromID($post['server_id'], 'fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
		$log_message = "Updated a database server ($old_name) with the following details:\n";
		
		/** Convert groups and policies arrays into strings */
		if (isset($post['server_groups']) && is_array($post['server_groups'])) {
			$temp_var = null;
			foreach ($post['server_groups'] as $id) {
				$temp_var .= $id . ';';
			}
			$post['server_groups'] = rtrim($temp_var, ';');
		}
		
		/** Handle credentials */
		if (is_array($post['server_credentials'])) {
			$post['server_credentials'] = serialize($post['server_credentials']);
		}
		
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . sanitize($data) . "', ";
				if ($key == 'server_credentials') {
					$data = str_repeat('*', 7);
				}
				if ($key == 'server_groups') {
					if ($data) {
						$group_array = explode(';', $data);
						$clean_data = null;
						foreach ($group_array as $group_id) {
							$clean_data .= getNameFromID($group_id, 'fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'groups', 'group_', 'group_id', 'group_name') . '; ';
						}
						$data = rtrim($clean_data, '; ');
					} else $data = 'None';
				}
				$log_message .= $data ? formatLogKeyData('server_', $key, $data) : null;
			}
		}
		$sql = rtrim($sql_edit, ', ');
		
		// Update the server
		$query = "UPDATE `fm_{$__FM_CONFIG['fmSQLPass']['prefix']}servers` SET $sql WHERE `server_id`={$post['server_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the server because a database error occurred.'), 'sql');
		}
		
		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		addLogEntry($log_message);
		return true;
	}
	
	
	/**
	 * Deletes the selected server
	 */
	function delete($id) {
		global $fmdb, $__FM_CONFIG;
		
		// Delete corresponding configs
//		if (!updateStatus('fm_config', $id, 'cfg_', 'deleted', 'cfg_server')) {
//			return 'This backup server could not be deleted.'. "\n";
//		}
		
		// Delete server
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
		if (!updateStatus('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'servers', $id, 'server_', 'deleted', 'server_id')) {
			return formatError(__('This database server could not be deleted.') . "\n");
		} else {
			addLogEntry("Deleted database server '$tmp_name'.");
			return true;
		}
	}


	function displayRow($row) {
		global $__FM_CONFIG;
		
		$disabled_class = ($row->server_status == 'disabled') ? ' class="disabled"' : null;
		
		$timezone = date("T");
		
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$edit_status = '<td id="row_actions">';
			$edit_status .= '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a class="status_form_link" href="#" rel="';
			$edit_status .= ($row->server_status == 'active') ? 'disabled' : 'active';
			$edit_status .= '">';
			$edit_status .= ($row->server_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
			$edit_status .= '</a>';
			$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			$edit_status .= '</td>';
		} else {
			$edit_status = null;
		}
		
		/** Get some options */
		$server_backup_credentials = getServerCredentials($_SESSION['user']['account_id'], $row->server_serial_no);
		if (!empty($server_backup_credentials[0])) {
			list($backup_username, $backup_password) = $server_backup_credentials;
		} else {
			$backup_username = getOption('backup_username', $_SESSION['user']['account_id'], $_SESSION['module']);
			$backup_password = getOption('backup_password', $_SESSION['user']['account_id'], $_SESSION['module']);
		}
		
		/** Get group associations */
		$groups_array = explode(';', $row->server_groups);
		$groups = null;
		foreach ($groups_array as $group_id) {
			$group_name = getNameFromID($group_id, 'fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'groups', 'group_', 'group_id', 'group_name');
			$groups .= "$group_name\n";
		}
		$groups = nl2br(trim($groups));
		
		if (empty($groups)) $groups = 'None';

		echo <<<HTML
		<tr id="$row->server_id" name="$row->server_name"$disabled_class>
			<td>{$row->server_name}</td>
			<td>{$row->server_type} (tcp/{$row->server_port})</td>
			<td>$groups</td>
			$edit_status
		</tr>
HTML;
	}

	/**
	 * Displays the form to add new server
	 */
	function printForm($data = '', $action = 'add') {
		global $fmdb, $__FM_CONFIG;
		
		$server_id = 0;
		$server_name = $server_groups = $server_type = $server_port = null;
		$server_cred_user = $server_cred_password = $server_credentials = null;
		$server_type = 'MySQL';
		$ucaction = ucfirst($action);
		
		/** Build groups options */
		basicGetList('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'groups', 'group_name', 'group_');
		$group_options = null;
		if ($fmdb->num_rows) {
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$group_options[$i][] = $fmdb->last_result[$i]->group_name;
				$group_options[$i][] = $fmdb->last_result[$i]->group_id;
			}
		}
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($data))
				extract($data);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		/** Check name field length */
		$server_name_length = getColumnLength('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'servers', 'server_name');

		$server_types = buildSelect('server_type', 'server_type', $this->getServerTypes(), $server_type);
		$server_port = ($server_port) ? $server_port : $__FM_CONFIG['fmSQLPass']['default']['ports'][$server_type];
		$groups = (is_array($group_options)) ? buildSelect('server_groups', 1, $group_options, $server_groups, 4, null, true) : __('Server Groups need to be defined first.');
		
		/** Handle credentials */
		if (isSerialized($server_credentials)) {
			$server_credentials = unserialize($server_credentials);
			list($server_cred_user, $server_cred_password) = $server_credentials;
			unset($server_credentials);
		}
		
		$popup_title = $action == 'add' ? __('Add Server') : __('Edit Server');
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		$return_form = sprintf('<form name="manage" id="manage" method="post" action="">
		%s
			<input type="hidden" name="action" id="action" value="%s" />
			<input type="hidden" name="server_type" id="server_type" value="%s" />
			<input type="hidden" name="server_id" id="server_id" value="%d" />
			<table class="form-table">
				<tr>
					<th width="33&#37;" scope="row"><label for="server_name">%s</label></th>
					<td width="67&#37;"><input name="server_name" id="server_name" type="text" value="%s" size="40" maxlength="%s" /></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="server_type">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="server_port">%s</label></th>
					<td width="67&#37;"><input type="number" name="server_port" id="server_port" value="%d" placeholder="3306" onkeydown="return validateNumber(event)" maxlength="5" max="65535" /></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="server_groups">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="server_cred_user">%s</label></th>
					<td width="67&#37;"><input name="server_credentials[]" id="server_cred_user" type="text" value="%s" size="40" /></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="server_cred_password">%s</label></th>
					<td width="67&#37;"><input name="server_credentials[]" id="server_cred_password" type="password" value="%s" size="40" /></td>
				</tr>
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
				$popup_header,
				$action, $server_type, $server_id,
				__('Hostname'), $server_name, $server_name_length,
				__('Server Type'), $server_types,
				__('Server Port'), $server_port,
				__('Groups'), $groups,
				__('Username'), $server_cred_user,
				__('Password'), $server_cred_password,
				$popup_footer
			);

		return $return_form;
	}
	
	
	function getServerTypes() {
		global $__FM_CONFIG;
		
		$fm_supported_servers = null;
		$db_support = enumMYSQLSelect('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'servers', 'server_type');
		
		foreach ($db_support as $db_type) {
			$php_function = (strtolower($db_type) == 'postgresql') ? 'pg' : strtolower($db_type);
			if ($php_function == 'mysql' && useMySQLi()) $php_function .= 'i';
			if (function_exists($php_function . '_connect') && function_exists('change' . $db_type . 'UserPassword')) $fm_supported_servers[] = $db_type;
		}
		
		return $fm_supported_servers;
	}
	
}

if (!isset($fm_module_servers))
	$fm_module_servers = new fm_module_servers();

?>
