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

class fm_module_servers {
	
	/**
	 * Displays the server list
	 */
	function rows($result) {
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

		if (!$result) {
			printf('<p id="table_edits" class="noresult" name="servers">%s</p>', __('There are no firewall servers.'));
		} else {
			echo @buildBulkActionMenu($bulk_actions_list, 'server_id_list');
			
			$table_info = array(
							'class' => 'display_results',
							'id' => 'table_edits',
							'name' => 'servers'
						);

			$title_array[] = array('class' => 'header-tiny');
			$title_array = array_merge($title_array, array(__('Hostname'), __('Method'), __('Firewall Type'), __('Version'), __('Config File')));
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
		
		$exclude = array('submit', 'action', 'server_id', 'compress', 'AUTHKEY', 'module_name', 'module_type', 'config', 'update_from_client');

		foreach ($post as $key => $data) {
			$clean_data = sanitize($data);
			if (($key == 'server_name') && empty($clean_data)) return __('No server name defined.');
			if (!in_array($key, $exclude)) {
				$sql_fields .= $key . ',';
				$sql_values .= "'$clean_data',";
			}
		}
		$sql_fields = rtrim($sql_fields, ',') . ')';
		$sql_values = rtrim($sql_values, ',');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) return __('Could not add the server because a database error occurred.');
		
		/** Add default fM interaction rules */
		$account_id = (isset($post['AUTHKEY'])) ? getAccountID($post['AUTHKEY']) : $_SESSION['user']['account_id'];
		include_once(ABSPATH . 'fm-modules/' . $module . '/classes/class_policies.php');
		$fm_host_id = getNameFromID($fm_name, 'fm_' . $__FM_CONFIG[$module]['prefix'] . 'objects', 'object_', 'object_name', 'object_id', $account_id);
		$fm_service_id[] = 'g' . getNameFromID('Web Server', 'fm_' . $__FM_CONFIG[$module]['prefix'] . 'groups', 'group_', 'group_name', 'group_id', $account_id);
		if ($post['server_type'] == 'iptables') $fm_service_id[] = 's' . getNameFromID('High TCP Ports', 'fm_' . $__FM_CONFIG[$module]['prefix'] . 'services', 'service_', 'service_name', 'service_id', $account_id);
		$default_rules[] = array(
								'account_id' => $account_id,
								'server_serial_no' => $post['server_serial_no'],
								'source_items' => array('o' . $fm_host_id),
								'destination_items' => '',
								'services_items' => $fm_service_id,
								'policy_comment' => sprintf(__('Required for %s client interaction.'), $fm_name)
							);
		$default_rules[] = array(
								'account_id' => $account_id,
								'server_serial_no' => $post['server_serial_no'],
								'policy_direction' => 'out',
								'source_items' => '',
								'destination_items' => array('o' . $fm_host_id),
								'services_items' => $fm_service_id,
								'policy_comment' => sprintf(__('Required for %s client interaction.'), $fm_name)
							);

		foreach ($default_rules as $rule) {
			$fm_module_policies->add($rule);
		}

		addLogEntry("Added server:\nName: {$post['server_name']} ({$post['server_serial_no']})\nType: {$post['server_type']}\n" .
				"Update Method: {$post['server_update_method']}\nConfig File: {$post['server_config_file']}");
		return true;
	}

	/**
	 * Updates the selected server
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		$exclude = array('submit', 'action', 'server_id', 'compress', 'AUTHKEY', 'module_name', 'module_type', 'config', 'SERIALNO', 'update_from_client');

		$sql_edit = null;
		
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . sanitize($data) . "',";
			}
		}
		$sql = rtrim($sql_edit, ',');
		
		// Update the server
		$old_name = getNameFromID($post['server_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
		$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}servers` SET $sql WHERE `server_id`={$post['server_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) return __('Could not update the server because a database error occurred.');
		
		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		setBuildUpdateConfigFlag(getServerSerial($post['server_id'], $_SESSION['module']), 'yes', 'build');
		
		addLogEntry("Updated server '$old_name' to:\nName: {$post['server_name']}\nType: {$post['server_type']}\n" .
					"Update Method: {$post['server_update_method']}\nConfig File: {$post['server_config_file']}");
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
		
		return __('This server could not be deleted.');
	}


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
			<td>$row->server_type</td>
			<td>$row->server_version</td>
			<td>$row->server_config_file</td>
			<td id="edit_delete_img">$edit_status</td>
		</tr>

HTML;
	}

	/**
	 * Displays the form to add new server
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
		
		$available_server_types = $this->getAvailableFirewalls(enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_type'), $server_os);
		
		$server_type = buildSelect('server_type', 'server_type', $available_server_types, $server_type, 1);
		$server_update_method = buildSelect('server_update_method', 'server_update_method', $server_update_method_choices, $server_update_method, 1);
		
		$popup_title = $action == 'add' ? __('Add Firewall') : __('Edit Firewall');
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
					<td width="67&#37;"><input name="server_name" id="server_name" type="text" value="%s" size="40" placeholder="fw1.local" maxlength="%d" /></td>
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
	
	function buildServerConfig($serial_no, $action = 'buildconf', $friendly_action = 'Configuration Build') {
		global $fmdb, $__FM_CONFIG, $fm_name;
		
		/** Check serial number */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', sanitize($serial_no), 'server_', 'server_serial_no');
		if (!$fmdb->num_rows) return sprintf('<p class="error">%s</p>', __('This server is not found.'));

		$server_details = $fmdb->last_result;
		extract(get_object_vars($server_details[0]), EXTR_SKIP);
		$options[] = $response = null;
		
		switch($server_update_method) {
			case 'cron':
				if ($action == 'buildconf') {
					/* set the server_update_config flag */
					setBuildUpdateConfigFlag($serial_no, 'conf', 'update');
					$response = sprintf('<p>%s</p>'. "\n", __('This server will be updated on the next cron run.'));
				} else {
					$response = sprintf('<p>%s</p>'. "\n", __('This server receives updates via cron - please manage the server manually.'));
				}
				break;
			case 'http':
			case 'https':
				/** Test the port first */
				if (!socketTest($server_name, $server_update_port, 10)) {
					return sprintf('<p class="error">%s</p>'. "\n", sprintf(__('Failed: could not access %s using %s (tcp/%d).'), $server_name, $server_update_method, $server_update_port));
				}
				
				/** Remote URL to use */
				$url = $server_update_method . '://' . $server_name . ':' . $server_update_port . '/' . $_SESSION['module'] . '/reload.php';
				
				/** Data to post to $url */
				$post_data = array('action'=>'buildconf', 'serial_no'=>$server_serial_no);
				
				$post_result = @unserialize(getPostData($url, $post_data));
				
				if (!is_array($post_result)) {
					/** Something went wrong */
					if (empty($post_result)) {
						return sprintf('<p class="error">%s</p>', sprintf(__('It appears %s does not have php configured properly within httpd or httpd is not running.'), $server_name));
					}
					return $response . '<p class="error">' . $post_result . '</p>'. "\n";
				} else {
					if (count($post_result) > 1) {
						$response .= '<textarea rows="7" cols="100">';
						
						/** Loop through and format the output */
						foreach ($post_result as $line) {
							$response .= "[$server_name] $line\n";
						}
						
						$response .= "</textarea>\n";
					} else {
						$response .= "<p>[$server_name] " . $post_result[0] . '</p>';
					}
				}
				break;
			case 'ssh':
				/** Test the port first */
				if (!socketTest($server_name, $server_update_port, 10)) {
					return sprintf('<p class="error">%s</p>'. "\n", sprintf(__('Failed: could not access %s using %s (tcp/%d).'), $server_name, $server_update_method, $server_update_port));
				}
				
				/** Get SSH key */
				$ssh_key = getOption('ssh_key_priv', $_SESSION['user']['account_id']);
				if (!$ssh_key) {
					return sprintf('<p class="error">%s</p>'. "\n", sprintf(__('Failed: SSH key is not <a href="%s">defined</a>.'), getMenuURL(_('General'))));
				}
				
				$temp_ssh_key = getOption('fm_temp_directory') . '/fm_id_rsa';
				if (file_exists($temp_ssh_key)) @unlink($temp_ssh_key);
				if (@file_put_contents($temp_ssh_key, $ssh_key) === false) {
					return sprintf('<p class="error">%s</p>'. "\n", sprintf(__('Failed: could not load SSH key into %s.'), $temp_ssh_key));
				}
				
				@chmod($temp_ssh_key, 0400);
				
				$ssh_user = getOption('ssh_user', $_SESSION['user']['account_id']);
				if (!$ssh_user) {
					return sprintf('<p class="error">%s</p>'. "\n", sprintf(__('Failed: SSH user is not <a href="%s">defined</a>.'), getMenuURL(_('General'))));
				}
		
				/** Test SSH authentication */
				exec(findProgram('ssh') . " -t -i $temp_ssh_key -o 'StrictHostKeyChecking no' -p $server_update_port -l $ssh_user $server_name 'ls /usr/local/$fm_name/{$_SESSION['module']}/fw.php'", $post_result, $retval);
				if ($retval) {
					/** Something went wrong */
					@unlink($temp_ssh_key);
					return sprintf('<p class="error">%s</p>'. "\n", __('Could not login via SSH.'));
				}
				unset($post_result);
				
				/** Run build */
				exec(findProgram('ssh') . " -t -i $temp_ssh_key -o 'StrictHostKeyChecking no' -p $server_update_port -l $ssh_user $server_name 'sudo php /usr/local/$fm_name/{$_SESSION['module']}/fw.php $action " . implode(' ', $options) . "'", $post_result, $retval);
				
				@unlink($temp_ssh_key);
				
				if ($retval) {
					/** Something went wrong */
					return '<p class="error">' . ucfirst($friendly_action) . ' failed.</p>'. "\n";
				} else {
				if (!count($post_result)) $post_result[] = ucfirst($friendly_action) . ' was successful.';
					
					if (count($post_result) > 1) {
						$response .= '<textarea rows="4" cols="100">';
						
						/** Loop through and format the output */
						foreach ($post_result as $line) {
							$response .= "[$server_name] $line\n";
						}
						
						$response .= "</textarea>\n";
					} else {
						$response .= "<p>[$server_name] " . $post_result[0] . '</p>';
					}
				}
				break;
		}
		
		/* reset the server_build_config flag */
		if (!strpos($response, strtolower('failed'))) {
			setBuildUpdateConfigFlag($serial_no, 'no', 'build');
		}

		$tmp_name = getNameFromID($serial_no, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name');
		addLogEntry(ucfirst($friendly_action) . " was performed on server '$tmp_name'.");

		return $response;
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
				return array('pf');
				break;
			case 'Darwin':
				return array('ipfw');
				break;
			case 'SunOS':
				return array('ipfilter');
				break;
			case 'Linux':
				return array('iptables');
				break;
		}
		
		return $all_firewalls;
	}
	
}

if (!isset($fm_module_servers))
	$fm_module_servers = new fm_module_servers();

?>
