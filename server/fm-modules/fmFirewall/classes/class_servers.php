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
 | fmFirewall: Easily manage one or more software firewalls                |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmfirewall/                        |
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

		$bulk_actions_list = array();
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$bulk_actions_list[] = __('Upgrade');
		}
		if (currentUserCan('build_server_configs', $_SESSION['module'])) {
			$bulk_actions_list[] = __('Build Config');
		}
		if (count($bulk_actions_list)) {
			$title_array[] = array(
								'title' => '<input type="checkbox" class="tickall" onClick="toggle(this, \'server_list[]\')" />',
								'class' => 'header-tiny header-nosort'
							);
		}

		$start = $_SESSION['user']['record_count'] * ($page - 1);
		$fmdb->num_rows = $num_rows;
		echo displayPagination($page, $total_pages, @buildBulkActionMenu($bulk_actions_list, 'server_id_list'));
		echo '<div class="overflow-container">';
			
		$table_info = array(
						'class' => 'display_results sortable',
						'id' => 'table_edits',
						'name' => 'servers'
					);

		$title_array[] = array('class' => 'header-tiny header-nosort');
		$title_array = array_merge($title_array, array(
			array('title' => __('Hostname'), 'rel' => 'server_name'),
			array('title' => __('Method'), 'rel' => 'server_update_method'),
			array('title' => __('Firewall Type'), 'rel' => 'server_type'),
			array('title' => __('Version'), 'rel' => 'server_version'),
			array('title' => __('Config File'), 'rel' => 'server_config_file')
		));
		$title_array[] = array(
							'title' => __('Actions'),
							'class' => 'header-actions header-nosort'
						);

		echo '<div class="table-results-container">';
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
			printf('<p id="table_edits" class="noresult" name="servers">%s</p>', __('There are no firewall servers.'));
		}
	}

	/**
	 * Adds the new server
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG, $global_form_field_excludes, $fm_name, $fm_module_policies;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		$module = (isset($post['module_name'])) ? $post['module_name'] : $_SESSION['module'];

		/** Get a valid and unique serial number */
		$post['server_serial_no'] = (isset($post['server_serial_no'])) ? $post['server_serial_no'] : generateSerialNo($module);

		$sql_insert = "REPLACE INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}servers`";
		$sql_fields = '(';
		$sql_values = '';
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		
		$exclude = array_merge($global_form_field_excludes, array('server_id'));

		$log_message = __("Added server with the following") . ":\n";
		$logging_excluded_fields = array('account_id');

		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_fields .= $key . ', ';
				$sql_values .= "'$data', ";
				$log_message .= ($data && !in_array($key, $logging_excluded_fields)) ? formatLogKeyData('server_', $key, $data) : null;
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the server because a database error occurred.'), 'sql');
		}
		
		/** Add default fM interaction rules */
		$account_id = (isset($post['AUTHKEY'])) ? getAccountID($post['AUTHKEY']) : $_SESSION['user']['account_id'];
		include_once(ABSPATH . 'fm-modules/' . $module . '/classes/class_policies.php');
		$fm_host_id = getNameFromID($fm_name, 'fm_' . $__FM_CONFIG[$module]['prefix'] . 'objects', 'object_', 'object_name', 'object_id', $account_id);
		
		/** Get server->client interaction services */
		$fm_in_service_id = array();
		$web_service_id = 'g' . getNameFromID('Web Server', 'fm_' . $__FM_CONFIG[$module]['prefix'] . 'groups', 'group_', 'group_name', 'group_id', $account_id);
		$fm_out_service_id[] = $web_service_id;
		switch ($post['server_update_method']) {
			case 'http':
			case 'https':
				$fm_in_service_id[] = $web_service_id;
				break;
			case 'ssh':
				$fm_in_service_id[] = 's' . getNameFromID('ssh', 'fm_' . $__FM_CONFIG[$module]['prefix'] . 'services', 'service_', 'service_name', 'service_id', $account_id);
				break;
		}
		if ($post['server_type'] == 'iptables') $fm_out_service_id[] = 's' . getNameFromID('High TCP Ports', 'fm_' . $__FM_CONFIG[$module]['prefix'] . 'services', 'service_', 'service_name', 'service_id', $account_id);
		if (count($fm_in_service_id)) {
			$default_rules[] = array(
				'account_id' => $account_id,
				'server_serial_no' => $post['server_serial_no'],
				'source_items' => array('o' . $fm_host_id),
				'destination_items' => '',
				'services_items' => $fm_in_service_id,
				'policy_type' => 'filter',
				'policy_options' => array($__FM_CONFIG['fw']['policy_options']['established']['bit']),
				'policy_comment' => sprintf(__('Required for %s client interaction.'), $fm_name)
			);
		}
		$default_rules[] = array(
			'account_id' => $account_id,
			'server_serial_no' => $post['server_serial_no'],
			'policy_direction' => 'out',
			'source_items' => '',
			'destination_items' => array('o' . $fm_host_id),
			'services_items' => $fm_out_service_id,
			'policy_type' => 'filter',
			'policy_options' => array($__FM_CONFIG['fw']['policy_options']['established']['bit']),
			'policy_comment' => sprintf(__('Required for %s client interaction.'), $fm_name)
		);

		foreach ($default_rules as $rule) {
			$fm_module_policies->add($rule);
		}

		addLogEntry($log_message);
		return true;
	}

	/**
	 * Updates the selected server
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG, $global_form_field_excludes;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		$exclude = array_merge($global_form_field_excludes, array('server_id'));

		$sql_edit = '';
		$old_name = getNameFromID($post['server_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
		
		$log_message = sprintf(__("Updated server '%s' to the following"), $old_name) . ":\n";

		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . $data . "', ";
				$log_message .= ($data) ? formatLogKeyData('server_', $key, $data) : null;
			}
		}
		$sql = rtrim($sql_edit, ', ');
		
		// Update the server
		$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}servers` SET $sql WHERE `server_id`={$post['server_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not update the server because a database error occurred.'), 'sql');
		}
		
		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		setBuildUpdateConfigFlag(getServerSerial($post['server_id'], $_SESSION['module']), 'yes', 'build');
		
		addLogEntry($log_message);
		return true;
	}
	
	/**
	 * Deletes the selected server
	 */
	function delete($server_id) {
		global $fmdb, $__FM_CONFIG;
		
		/** Does the server_id exist for this account? */
		$server_serial_no = getNameFromID($server_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_id', 'server_serial_no');
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', $server_serial_no, 'server_', 'server_serial_no');
		if ($fmdb->num_rows) {
			/** Delete associated policies */
			if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', $server_serial_no, 'policy_', 'deleted', 'server_serial_no') === false) {
				return __('The associated policies could not be removed because a database error occurred.');
			}
			
			/** Delete server */
			$tmp_name = getNameFromID($server_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
			if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', $server_id, 'server_', 'deleted', 'server_id')) {
				addLogEntry(sprintf(__("Server '%s' (%s) was deleted"), $tmp_name, $server_serial_no));
				return true;
			}
		}
		
		return formatError(__('This server could not be deleted.'), 'sql');
	}


	function displayRow($row) {
		global $__FM_CONFIG;
		
		$class = ($row->server_status == 'disabled') ? 'disabled' : null;
		
		$os_image = setOSIcon($row->server_os_distro);
		
		$edit_status = $edit_actions = '';
		$edit_actions = $preview = '<a href="preview.php" onclick="javascript:void window.open(\'preview.php?server_serial_no=' . $row->server_serial_no . '\',\'1356124444538\',\'' . $__FM_CONFIG['default']['popup']['dimensions'] . ',toolbar=0,menubar=0,location=0,status=0,scrollbars=1,resizable=1,left=0,top=0\');return false;">' . $__FM_CONFIG['icons']['preview'] . '</a>';
		
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
			$edit_actions = __('Client Upgrade Available') . '<br />' . $preview;
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
		<tr id="$row->server_id" name="$row->server_name" $class>
			$checkbox
			<td>$os_image</td>
			<td title="$row->server_serial_no">$edit_name</td>
			<td>$row->server_update_method $port</td>
			<td>$row->server_type</td>
			<td>$row->server_version</td>
			<td>$row->server_config_file</td>
			<td class="column-actions">$edit_status</td>
		</tr>

HTML;
	}

	/**
	 * Displays the form to add new server
	 */
	function printForm($data = '', $action = 'add') {
		global $__FM_CONFIG;
		
		$server_id = 0;
		$server_name = $server_type = $server_update_port = null;
		$server_update_method = $server_config_file = $server_os = null;
		$server_installed = false;
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}

		$server_update_port_style = ($server_update_method == 'cron') ? 'style="display: none;"' : 'style="display: block;"';
		
		if ($server_installed == 'yes') {
			if (strpos($server_update_method, 'http') === false) {
				$server_update_method_choices = array($server_update_method);
			} else {
				$server_update_method_choices = array('http', 'https');
			}
		} else {
			$server_update_method_choices = enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_update_method');
		}
		
		$available_server_types = $this->getAvailableFirewalls(enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_type'), $server_os);
		
		$server_type = buildSelect('server_type', 'server_type', $available_server_types, $server_type, 1);
		$server_update_method = buildSelect('server_update_method', 'server_update_method', $server_update_method_choices, $server_update_method, 1);
		
		$popup_title = $action == 'add' ? __('Add Firewall') : __('Edit Firewall');
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		$alternative_help = ($action == 'add' && getOption('client_auto_register')) ? sprintf('<p><b>%s</b> %s</p>', __('Note:'), __('The client installer can automatically generate this entry.')) : null;
		$server_name_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_name');

		$return_form = sprintf('
		%s
		<form name="manage" id="manage">
			<input type="hidden" name="page" value="servers" />
			<input type="hidden" name="action" value="%s" />
			<input type="hidden" name="server_id" value="%d" />
			%s
			<table class="form-table">
				<tr>
					<th width="33&#37;" scope="row"><label for="server_name">%s</label></th>
					<td width="67&#37;"><input name="server_name" id="server_name" type="text" value="%s" size="40" placeholder="fw1.local" maxlength="%d" class="required" /></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="server_type">%s</label></th>
					<td width="67&#37;">%s</td>
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
				__('Firewall Type'), $server_type,
				__('Update Method'), $server_update_method, $server_update_port_style, $server_update_port,
				__('Config File'), $server_config_file,
				$popup_footer
			);

		return $return_form;
	}
	
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
	
	function getAvailableFirewalls($all_firewalls, $os) {
		switch ($os) {
			case 'FreeBSD':
				array_shift($all_firewalls);
				break;
			case 'OpenBSD':
				return array();
			case 'Darwin':
				return array('ipfw');
			case 'SunOS':
				return array('ipfilter');
			case 'Linux':
				return array('iptables');
		}
		
		return $all_firewalls;
	}
	
}

if (!isset($fm_module_servers))
	$fm_module_servers = new fm_module_servers();
