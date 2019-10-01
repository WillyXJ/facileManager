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
 | fmModule: Brief module description                                      |
 +-------------------------------------------------------------------------+
 | http://URL                                                              |
 +-------------------------------------------------------------------------+
*/

require_once(ABSPATH . 'fm-modules/shared/classes/class_servers.php');

class fm_module_servers extends fm_shared_module_servers {
	
	/**
	 * Displays the server list
	 *
	 * @since 1.0
	 * @package facileManager
	 * @subpackage fmModule
	 *
	 * @param array $result Record rows of all servers
	 * @return null
	 */
	function rows($result, $page, $total_pages) {
		global $fmdb;
		
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;
		
		if (currentUserCan('build_server_configs', $_SESSION['module'])) {
			$bulk_actions_list = array(__('Upgrade'), __('Build Config'));
			$title_array[] = array(
								'title' => '<input type="checkbox" class="tickall" onClick="toggle(this, \'server_list[]\')" />',
								'class' => 'header-tiny'
							);
		} else {
			$bulk_actions_list = null;
		}

		echo @buildBulkActionMenu($bulk_actions_list, 'server_id_list');

		$start = $_SESSION['user']['record_count'] * ($page - 1);
		echo displayPagination($page, $total_pages, @buildBulkActionMenu($bulk_actions_list, 'server_id_list'));

		if (!$result) {
			printf('<p id="table_edits" class="noresult" name="servers">%s</p>', __('There are no servers.'));
		} else {
			$table_info = array(
							'class' => 'display_results',
							'id' => 'table_edits',
							'name' => 'servers'
						);

			$title_array[] = array('class' => 'header-tiny');
			$title_array = array_merge($title_array, array(__('Hostname'), __('Method'), __('Version'), __('Config File')));
			$title_array[] = array(
								'title' => __('Actions'),
								'class' => 'header-actions'
							);

			echo displayTableHeader($table_info, $title_array);
			
			for ($x=0; $x<$num_rows; $x++) {
				$this->displayRow($results[$x]);
			}
			
			echo "</tbody>\n</table>\n";
		}
	}

	/**
	 * Adds the new server
	 *
	 * @since 1.0
	 * @package facileManager
	 * @subpackage fmModule
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
	 * @subpackage fmModule
	 *
	 * @param array $post $_POST data
	 * @return boolean or string
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
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
		addLogEntry("Updated server '$old_name' to:\nName: {$post['server_name']}\nType: {$post['server_type']}\n" .
					"Update Method: {$post['server_update_method']}\nConfig File: {$post['server_config_file']}");
		return true;
	}
	
	/**
	 * Deletes the selected server
	 *
	 * @since 1.0
	 * @package facileManager
	 * @subpackage fmModule
	 *
	 * @param integer $server_id Server ID to delete
	 * @return boolean or string
	 */
	function delete($server_id) {
		global $fmdb, $__FM_CONFIG;
		
		/** Does the server_id exist for this account? */
		$server_serial_no = getNameFromID($server_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_id', 'server_serial_no');
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', $server_serial_no, 'server_', 'server_serial_no');
		if ($fmdb->num_rows) {
			/** Delete associated records from other tables */
			if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'table1', $server_serial_no, 'prefix_', 'deleted', 'server_serial_no') === false) {
				return __('The associated table records could not be removed because a database error occurred.');
			}
			
			/** Delete server */
			$tmp_name = getNameFromID($server_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
			if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', $server_id, 'server_', 'deleted', 'server_id')) {
				/** Add entry to audit log */
				addLogEntry(sprintf(__("Server '%s' (%s) was deleted"), $tmp_name, $server_serial_no));
				return true;
			}
		}
		
		return formatError(__('This server could not be deleted.'), 'sql');
	}


	/**
	 * Displays the server entry table row
	 *
	 * @since 1.0
	 * @package facileManager
	 * @subpackage fmModule
	 *
	 * @param object $row Single data row from $results
	 * @return null
	 */
	function displayRow($row) {
		global $__FM_CONFIG;
		
		$class = ($row->server_status == 'disabled') ? 'disabled' : null;
		
		$os_image = setOSIcon($row->server_os_distro);
		
		$edit_status = $edit_actions = null;
		$edit_actions = $row->server_status == 'active' ? '<a href="preview.php" onclick="javascript:void window.open(\'preview.php?server_serial_no=' . $row->server_serial_no . '\',\'1356124444538\',\'width=700,height=500,toolbar=0,menubar=0,location=0,status=0,scrollbars=1,resizable=1,left=0,top=0\');return false;">' . $__FM_CONFIG['icons']['preview'] . '</a>' : null;
		
		$checkbox = (currentUserCan(array('manage_servers', 'build_server_configs'), $_SESSION['module'])) ? '<td><input type="checkbox" name="server_list[]" value="' . $row->server_serial_no .'" /></td>' : null;
		
		if (currentUserCan('build_server_configs', $_SESSION['module']) && $row->server_installed == 'yes') {
			if ($row->server_build_config == 'yes' && $row->server_status == 'active' && $row->server_installed == 'yes') {
				$edit_actions .= $__FM_CONFIG['icons']['build'];
				$class = 'build';
			}
		}
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$edit_status = '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			if ($row->server_installed == 'yes') {
				$edit_status .= '<a class="status_form_link" href="#" rel="';
				$edit_status .= ($row->server_status == 'active') ? 'disabled' : 'active';
				$edit_status .= '">';
				$edit_status .= ($row->server_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
				$edit_status .= '</a>';
			}
			$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
		}
		$edit_name = currentUserCan(array('manage_policies', 'view_all'), $_SESSION['module']) ? '<a href="config-policy.php?server_serial_no=' . $row->server_serial_no . '">' . $row->server_name . '</a>' : $row->server_name;
		
		if (isset($row->server_client_version) && version_compare($row->server_client_version, getOption('client_version', 0, $_SESSION['module']), '<')) {
			$edit_actions = __('Client Upgrade Available') . '<br />';
			$class = 'attention';
		}
		if ($row->server_installed != 'yes') {
			$edit_actions = __('Client Install Required') . '<br />';
			$edit_name = $row->server_name;
		}
		$edit_status = $edit_actions . $edit_status;
		
		$port = ($row->server_update_method != 'cron') ? '(tcp/' . $row->server_update_port . ')' : null;
		
		if ($class) $class = 'class="' . $class . '"';
		
		echo <<<HTML
		<tr id="$row->server_id" $class>
			$checkbox
			<td>$os_image</td>
			<td title="$row->server_serial_no">$edit_name</td>
			<td>$row->server_update_method $port</td>
			<td>$row->server_version</td>
			<td>$row->server_config_file</td>
			<td id="row_actions">$edit_status</td>
		</tr>

HTML;
	}

	/**
	 * Displays the server add/edit form
	 *
	 * @since 1.0
	 * @package facileManager
	 * @subpackage fmModule
	 *
	 * @param array $data Either $_POST data or returned SQL results
	 * @param string $action Add or edit
	 * @return string
	 */
	function printForm($data = '', $action = 'add') {
		global $__FM_CONFIG;
		
		$server_id = 0;
		$server_name = $runas = $server_type = $server_update_port = null;
		$server_update_method = $server_config_file = $server_os = null;
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
		
		$popup_title = $action == 'add' ? __('Add Server') : __('Edit Server');
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		$alternative_help = ($action == 'add' && getOption('client_auto_register')) ? sprintf('<p><b>%s</b> %s</p>', __('Note:'), __('The client installer can automatically generate this entry.')) : null;
		$server_name_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_name');

		$return_form = sprintf('<form name="manage" id="manage" method="post" action="">
		%s
			<input type="hidden" name="action" value="%s" />
			<input type="hidden" name="server_id" value="%d" />
			%s
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
				$popup_header, $action, $server_id, $alternative_help,
				__('Server Name'), $server_name, $server_name_length,
				__('Update Method'), $server_update_method, $server_update_port_style, $server_update_port,
				__('Config File'), $server_config_file,
				$popup_footer
			);

		return $return_form;
	}
	
	/**
	 * Validates the user-submitted data (for add and edit)
	 *
	 * @since 1.0
	 * @package facileManager
	 * @subpackage fmModule
	 *
	 * @param array $post Posted data to validate
	 * @return array
	 */
	function validatePost($post) {
		global $fmdb, $__FM_CONFIG;
		
		if (empty($post['server_name'])) return __('No server name defined.');
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_name');
		if ($field_length !== false && strlen($post['server_name']) > $field_length) return sprintf(dngettext($_SESSION['module'], 'Server name is too long (maximum %d character).', 'Server name is too long (maximum %d characters).', $field_length), $field_length);
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', $post['server_name'], 'server_', 'server_name', "AND server_id!='{$post['server_id']}'");
		if ($fmdb->num_rows) return __('This server name already exists.');
		
		if (empty($post['server_config_file'])) {
			$post['server_config_file'] = $__FM_CONFIG['fw']['config_file']['default'];
			if (!is_array($__FM_CONFIG['fw']['config_file'][$post['server_type']]) && $__FM_CONFIG['fw']['config_file'][$post['server_type']]) {
				$post['server_config_file'] = $__FM_CONFIG['fw']['config_file'][$post['server_type']];
			} elseif (is_array($__FM_CONFIG['fw']['config_file'][$post['server_type']])) {
				if (isset($post['server_os_distro'])) $distro = $post['server_os_distro'];
				else {
					if ($post['action'] == 'edit') {
						$distro = getNameFromID($post['server_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_id', 'server_os_distro');
					}
				}
				if (isset($distro) && array_key_exists($distro, $__FM_CONFIG['fw']['config_file'][$post['server_type']])) $post['server_config_file'] = $__FM_CONFIG['fw']['config_file'][$post['server_type']][$distro];
			}
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
	
}

if (!isset($fm_module_servers))
	$fm_module_servers = new fm_module_servers();

?>
