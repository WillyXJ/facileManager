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
 | fmWifi: Easily manage one or more access points                         |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmwifi/                            |
 +-------------------------------------------------------------------------+
*/

class fm_wifi_wlans {
	
	/**
	 * Displays the item list
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param array $result Record rows of all items
	 * @return null
	 */
	function rows($result, $page, $total_pages, $type = 'wlans') {
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
		$title_array = array_merge((array) $title_array, array(
			array('title' => __('SSID'), 'rel' => 'config_data'),
			array('title' => __('Frequency'), 'class' => 'header-nosort'),
			array('title' => __('Security'), 'class' => 'header-nosort'),
			array('title' => __('Associated APs'), 'class' => 'header-nosort'),
			array('title' => _('Comment'), 'class' => 'header-nosort')
		));
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
	 * @subpackage fmWifi
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
		$sql_values = '';
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		$post['config_is_parent'] = 'yes';
		$name = $post['config_name'];
		
		if (empty($name)) return __('No name defined.');
		
		$include = array_merge(array('account_id', 'server_serial_no', 'config_is_parent', 'config_data', 'config_name', 'config_comment', 'config_parent_id', 'config_aps', 'config_type'));
		$logging_excluded_fields = array('account_id', 'config_is_parent', 'config_type', 'config_name');
		$log_message = __("Added WLAN with the following") . ":\n";
		
		/** Insert the category parent */
		foreach ($post as $key => $data) {
			if (in_array($key, $include)) {
				if ($data) {
					$sql_fields .= $key . ', ';
					$sql_values .= "'$data', ";
					if ($key == 'config_data') $key = 'SSID';
					$log_message .= ($data && !in_array($key, $logging_excluded_fields)) ? formatLogKeyData('config_', $key, $data) : null;
				}
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_start $sql_fields VALUES ($sql_values)";
		$fmdb->query($query);
		
		$insert_id = $fmdb->insert_id;

		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the item because a database error occurred.'), 'sql');
		}

		
		/** Insert config children */
		$child['config_is_parent'] = 'no';
		$child['config_parent_id'] = $fmdb->insert_id;
		$child['config_data'] = $child['config_name'] = null;
		$child['account_id'] = $post['account_id'];
		$child['config_type'] = $post['config_type'];
		
		if (isset($post['hardware-type'])) {
			$post['hardware'] = $post['hardware-type'] . ' ' . $post['hardware'];
			unset($post['hardware-type']);
		}
		
		$include = array_diff(array_keys($post), $include, array('config_id', 'action', 'tab-group-1', 'submit', 'page', 'item_type', 'config_type', 'uri_params'));
		
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
				if ($i) $sql_fields .= $key . ', ';
				
				$sql_values .= "'$data', ";
			}
			if ($handler == 'wpa_passphrase') $post[$handler] = str_repeat('*', 8);
			$log_message .= ($post[$handler]) ? formatLogKeyData('config_', $handler, $post[$handler]) : null;
			$i = 0;
			$sql_values = rtrim($sql_values, ', ') . '), (';
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', (');
		
		$query = "$sql_start $sql_fields VALUES $sql_values";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the item because a database error occurred.'), 'sql');
		}
		
		setBuildUpdateConfigFlag(getWLANServers($insert_id), 'yes', 'build');
		
		addLogEntry($log_message);
		return true;
	}

	/**
	 * Updates the selected host
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
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
		$sql_values = '';
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		$post['config_is_parent'] = 'yes';
		$name = $post['config_name'];
		
		if (empty($name)) return __('No name defined.');
		
		$include = array_merge(array('account_id', 'server_serial_no', 'config_is_parent', 'config_data', 'config_name', 'config_comment', 'config_parent_id', 'config_aps'));
		$logging_excluded_fields = array('account_id', 'config_is_parent', 'config_type', 'config_name');
		
		$old_name = getNameFromID($post['config_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_', 'config_id', 'config_data');
		$log_message = sprintf(__("Updated WLAN '%s' to the following"), $old_name) . ":\n";

		/** Insert the category parent */
		foreach ($post as $key => $data) {
			if (in_array($key, $include)) {
				if ($data) {
					$sql_values .= "$key='$data', ";
					if ($key == 'config_data') $key = 'SSID';
					$log_message .= ($data && !in_array($key, $logging_excluded_fields)) ? formatLogKeyData('config_', $key, $data) : null;
				}
			}
		}
		$sql_values = rtrim($sql_values, ', ');
		
		$item_id = $post['config_id'];
		
		$query = "$sql_start $sql_values WHERE config_id={$post['config_id']} LIMIT 1";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the item because a database error occurred.'), 'sql');
		}
		
		$rows_affected = $fmdb->rows_affected;

		/** Update config children */
		$child['config_is_parent'] = 'no';
		$child['config_data'] = $child['config_name'] = null;
		
		if (isset($post['hardware-type'])) {
			$post['hardware'] = $post['hardware-type'] . ' ' . $post['hardware'];
			unset($post['hardware-type']);
		}
		
		$include = array_diff(array_keys($post), $include, array('config_id', 'action', 'tab-group-1', 'submit', 'account_id', 'page', 'item_type', 'config_type', 'uri_params'));
		$sql_start = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config` SET ";
		
		foreach ($include as $handler) {
			$sql_values = '';
			$child['config_name'] = $handler;
			$child['config_data'] = $post[$handler];
			
			foreach ($child as $key => $data) {
				$sql_values .= "$key='$data', ";
			}
			if ($handler == 'wpa_passphrase') $post[$handler] = str_repeat('*', 8);
			$log_message .= ($post[$handler]) ? formatLogKeyData('config_', $handler, $post[$handler]) : null;
			$sql_values = rtrim($sql_values, ', ');
			
			$query = "$sql_start $sql_values WHERE config_parent_id={$post['config_id']} AND config_name='$handler' LIMIT 1";
			$fmdb->query($query);

			if ($fmdb->sql_errors) {
				return formatError(__('Could not update the item because a database error occurred.'), 'sql');
			}

			$rows_affected += $fmdb->rows_affected;
		}
		
		/** Reassigned children */
		$query = "$sql_start config_parent_id=0 WHERE config_parent_id={$post['config_id']} AND config_is_parent='yes'";
		$fmdb->query($query);

		/** Return if there are no changes */
		if (!$rows_affected) return true;

		/** Server changed so configuration needs to be built */
		setBuildUpdateConfigFlag(getWLANServers($item_id), 'yes', 'build');
		
		addLogEntry($log_message);
		return true;
	}
	
	/**
	 * Deletes the selected server
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param integer $id ID to delete
	 * @return boolean|string
	 */
	function delete($id, $server_serial_no = 0) {
		global $fmdb, $__FM_CONFIG;
		
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_', 'config_id', 'config_data');

		/** Delete associated children */
		updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $id, 'config_', 'deleted', 'config_parent_id');
		
		/** Delete item */
		if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $id, 'config_', 'deleted', 'config_id') === false) {
			return formatError(__('This item could not be deleted because a database error occurred.'), 'sql');
		} else {
			setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
			addLogEntry(sprintf(__("WLAN '%s' was deleted."), $tmp_name));
			return true;
		}
	}


	/**
	 * Displays the server entry table row
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param object $row Single data row from $results
	 * @return null
	 */
	function displayRow($row) {
		global $fmdb, $__FM_CONFIG;
		
		$class = ($row->config_status == 'disabled') ? 'disabled' : null;
		
		$edit_status = $checkbox = null;
		$icons = array();
		
		if (currentUserCan('manage_wlans', $_SESSION['module'])) {
			$edit_status = '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a class="status_form_link" href="#" rel="';
			$edit_status .= ($row->config_status == 'active') ? 'disabled' : 'active';
			$edit_status .= '">';
			$edit_status .= ($row->config_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
			$edit_status .= '</a>';
			$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			$edit_status = '<td class="column-actions">' . $edit_status . '</td>';
			$checkbox = '<input type="checkbox" name="bulk_list[]" value="' . $row->config_id .'" />';
		}
		$icons[] = sprintf('<a href="config-options.php?item_id=%d" class="tooltip-bottom mini-icon" data-tooltip="%s"><i class="mini-icon fa fa-sliders" aria-hidden="true"></i></a>', $row->config_id, __('Configure Additional Options'));
		
		if ($class) $class = 'class="' . $class . '"';
		if (is_array($icons)) {
			$icons = implode(' ', $icons);
		}
		
		$query = 'SELECT * FROM `fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config` WHERE `config_status`!="deleted" AND `account_id`="' . $_SESSION['user']['account_id'] . '" AND 
			`config_name`="ssid" AND `config_data`="' . $row->config_data . '"';
		$fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$last_result = $fmdb->last_result[0];
			/** Display security type */
			$query = 'SELECT * FROM `fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config` WHERE `config_status`!="deleted" AND `account_id`="' . $_SESSION['user']['account_id'] . '" AND 
				`config_name`="wpa_key_mgmt" AND `config_parent_id`="' . $last_result->config_id . '"';
			$fmdb->get_results($query);
			$security_type = $fmdb->last_result[0]->config_data;
			
			/** Display frequency */
			$query = 'SELECT * FROM `fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config` WHERE `config_status`!="deleted" AND `account_id`="' . $_SESSION['user']['account_id'] . '" AND 
				`config_name`="hw_mode" AND `config_parent_id`="' . $last_result->config_id . '"';
			$fmdb->get_results($query);
			switch ($fmdb->last_result[0]->config_data) {
				case 'a':
					$frequency = '5 GHz';
					break;
				case 'b':
				case 'g':
					$frequency = '2.4 GHz';
					break;
				case 'ad':
					$frequency = '60 GHz';
					break;
				default:
					$frequency = _('None');
			}
		}
		
		if (!$security_type) {
			$security_type = _('None');
		}
		
		$associated_aps = _('All Servers');
		if ($row->config_aps) {
			$associated_aps = array();
			foreach (explode(';', $row->config_aps) as $server_id) {
				if ($server_id[0] == 'g') {
					$table = 'server_groups';
					$prefix = 'group_';
				} else {
					$table = 'servers';
					$prefix = 'server_';
				}
				$associated_aps[] = getNameFromID(str_replace(array('s_', 'g_'), '', $server_id), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . $table, $prefix, $prefix . 'id', $prefix . 'name');
			}
			$associated_aps = join('; ', $associated_aps);
		}
		
		echo <<<HTML
		<tr id="$row->config_id" name="$row->config_data" $class>
			<td>$checkbox</td>
			<td>$row->config_data $icons</td>
			<td>$frequency</td>
			<td>$security_type</td>
			<td>$associated_aps</td>
			<td>$row->config_comment</td>
			$edit_status
		</tr>

HTML;
	}

	/**
	 * Displays the add/edit form
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param array $data Either $_POST data or returned SQL results
	 * @param string $action Add or edit
	 * @return string
	 */
	function printForm($data = '', $action = 'add', $type = 'host', $addl_vars = null) {
		global $fmdb, $__FM_CONFIG, $fm_module_options;
		
		$hw_mode_options = array(
			array('802.11a (5 GHz)', 'a'),
			array('802.11b (2.4 GHz)', 'b'),
			array('802.11g (2.4 GHz)', 'g'),
			array('802.11ad (60 GHz)', 'ad')
		);
		$macaddr_acl_options = array(
			array(__('Accept unless in deny list'), '0'),
			array(__('Deny unless in accept list'), '1'),
			array(__('Use external RADIUS server'), '2')
		);
		
		$ignore_broadcast_ssid_checked = $ieee80211n_checked = $ieee80211ac_checked = $ieee80211d_checked = null;
		$wmm_enabled_checked = $auth_algs_checked = $macaddr_acl_checked = null;
		
		$config_id = $config_aps = 0;
		$config_name = $config_comment = $config_data = $channel = null;
		$server_serial_no = (isset($_REQUEST['request_uri']['server_serial_no']) && (intval($_REQUEST['request_uri']['server_serial_no']) > 0 || $_REQUEST['request_uri']['server_serial_no'][0] == 'g')) ? sanitize($_REQUEST['request_uri']['server_serial_no']) : 0;
		$country_code = 'US';
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		$config_name = $config_data;
		
		/** Get child elements */
		$ignore_broadcast_ssid_checked = (str_replace(array('"', "'"), '', $this->getConfig($config_id, 'ignore_broadcast_ssid'))) ? 'checked' : null;
		$ieee80211n_checked = (str_replace(array('"', "'"), '', $this->getConfig($config_id, 'ieee80211n'))) ? 'checked' : null;
		$ieee80211ac_checked = (str_replace(array('"', "'"), '', $this->getConfig($config_id, 'ieee80211ac'))) ? 'checked' : null;
		$ieee80211d_checked = (str_replace(array('"', "'"), '', $this->getConfig($config_id, 'ieee80211d'))) ? 'checked' : null;
		$wmm_enabled_checked = (str_replace(array('"', "'"), '', $this->getConfig($config_id, 'wmm_enabled'))) ? 'checked' : null;
		$preamble_checked = (str_replace(array('"', "'"), '', $this->getConfig($config_id, 'preamble'))) ? 'checked' : null;
		$config_aps = buildSelect('config_aps', 'config_aps', availableServers('id'), explode(';', $config_aps), 1, null, true);
		$hw_mode = $this->getConfig($config_id, 'hw_mode');
		$hw_mode_options = buildSelect('hw_mode', 'hw_mode', $hw_mode_options, $hw_mode);
		$auth_algs_checked = (str_replace(array('"', "'"), '', $this->getConfig($config_id, 'auth_algs'))) ? 'checked' : null;
		$macaddr_acl_options = buildSelect('macaddr_acl', 'macaddr_acl', $macaddr_acl_options, $this->getConfig($config_id, 'macaddr_acl'));
		$macaddr_note = sprintf(' <a href="#" class="tooltip-top" data-tooltip="%s"><i class="fa fa-question-circle"></i></a>', __('The ACL functionality of hostapd (macaddr_acl) does not seem to work with Raspbian. Therefore, the use of ebtables is recommended to deny clients.'));

		$wpa_passphrase = $this->getConfig($config_id, 'wpa_passphrase');
		$wpa_key_mgmt = $fm_module_options->populateDefTypeDropdown($fm_module_options->parseDefType('wpa_key_mgmt'), $this->getConfig($config_id, 'wpa_key_mgmt'), 'wpa_key_mgmt');
		$wpa_pairwise = $fm_module_options->populateDefTypeDropdown($fm_module_options->parseDefType('wpa_pairwise'), $this->getConfig($config_id, 'wpa_pairwise'), 'wpa_pairwise');

		$channel = str_replace(array('"', "'"), '', $this->getConfig($config_id, 'channel'));
		$country_code = ($config_id) ? $this->getConfig($config_id, 'country_code') : $country_code;
		$country_code = $this->buildConfigOptions('country_code', $country_code);
		$max_num_sta = $this->getConfig($config_id, 'max_num_sta');
		$max_num_sta_note = sprintf(' <a href="#" class="tooltip-right" data-tooltip="%s"><i class="fa fa-question-circle"></i></a>', __('Limit the number of clients that can connect or leave empty for the maximum (2007). In addition, you can choose to ignore broadcast Probe Request frames from unassociated clients if the maximum client count has been reached which would discourage clients from trying to associate with this AP if the association would be rejected due to the maximum client limit.'));
		$no_probe_resp_if_max_sta_checked = (str_replace(array('"', "'"), '', $this->getConfig($config_id, 'no_probe_resp_if_max_sta'))) ? 'checked' : null;

		$ieee80211n_entry = 'none';
		if (in_array($hw_mode, array('', 'a', 'b', 'g'))) {
			$hw_mode_option_style = 'block';
			$ieee80211ac_style = (in_array($hw_mode, array('', 'a'))) ? 'inline' : 'none';
			if ($hw_mode == 'b') {
				$preamble_style = 'inline';
				$ieee80211n_entry = 'none';
			} else {
				$preamble_style = 'none';
				$ieee80211n_entry = 'inline';
			}
		} else {
			$hw_mode_option_style = $ieee80211ac_style = $preamble_style = 'none';
		}
		
		$security_options_style = ($auth_algs_checked) ? 'block' : 'none';

		$popup_title = ($action == 'add') ? __('Add Item') : __('Edit Item');
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		$return_form = sprintf('
		%s
		<form name="manage" id="manage">
			<input type="hidden" name="page" value="wlans" />
			<input type="hidden" name="action" value="%s" />
			<input type="hidden" name="config_id" value="%d" />
			<input type="hidden" name="server_serial_no" value="%s" />
			<input type="hidden" name="ctrl_interface" value="/var/run/hostapd" />
			<input type="hidden" name="ctrl_interface_group" value="0" />
			<div id="tabs">
				<div id="tab">
					<input type="radio" name="tab-group-1" id="tab-1" checked />
					<label for="tab-1">%s</label>
					<div id="tab-content">
						<table class="form-table">
							<tr>
								<th width="33&#37;" scope="row"><label for="config_name">%s</label></th>
								<td width="67&#37;" nowrap><input name="config_name" id="config_name" type="text" value="%s" class="required" /><br /><input name="ignore_broadcast_ssid" id="ignore_broadcast_ssid" type="checkbox" value="on" %s /> <label for="ignore_broadcast_ssid">%s</label></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="config_aps">%s</label></th>
								<td width="67&#37;">%s</td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="hw_mode">%s</label></th>
								<td width="67&#37;">%s
									<div id="hw_mode_option" style="display: %s;">
										<div>
											<span id="preamble_entry" style="display: %s;"><input name="preamble" id="preamble" type="checkbox" value="on" %s /> <label for="preamble">%s</label></span>
										</div>
										<div>
											<span id="ieee80211n_entry" style="display: %s;"><input name="ieee80211n" id="ieee80211n" type="checkbox" value="on" %s /> <label for="ieee80211n">%s</label></span>
											<span id="ieee80211ac_entry" style="display: %s;"><input name="ieee80211ac" id="ieee80211ac" type="checkbox" value="on" %s /> <label for="ieee80211ac">%s</label></span>
										</div>
										<div>
											<span id="wmm_entry" style="display: %s;"><input name="wmm_enabled" id="wmm_enabled" type="checkbox" value="on" %s /> <label for="wmm_enabled">%s</label></span>
										</div>
									</div>
								</td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row" style="padding-top:0;">%s</th>
								<td width="67&#37;">
									<input name="auth_algs" id="auth_algs" type="checkbox" value="on" %s /> <label for="auth_algs">%s</label><br />
									<h4>%s: %s</h4>
									%s
								</td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="config_comment">%s</label></th>
								<td width="67&#37;"><textarea id="config_comment" name="config_comment" rows="4" cols="30">%s</textarea></td>
							</tr>
						</table>
					</div>
				</div>
				<div id="tab" class="security_options" style="display: %s;">
					<input type="radio" name="tab-group-1" id="tab-2" />
					<label for="tab-2">%s</label>
					<div id="tab-content">
						<table class="form-table">
							<tr>
								<th width="33&#37;" scope="row"><label for="wpa_passphrase">%s</label></th>
								<td width="67&#37;"><input name="wpa_passphrase" id="wpa_passphrase" class="text_icon" type="password" value="%s" /> <i id="show_password" class="fa fa-eye eye-attention grey text_icon" title="%s"></i></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="wpa_key_mgmt">%s</label></th>
								<td width="67&#37;">%s</td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="wpa_pairwise">%s</label></th>
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
								<th width="33&#37;" scope="row"><label for="channel">%s</label></th>
								<td width="67&#37;"><input name="channel" id="channel" type="text" value="%s" style="width: 5em;" onkeydown="return validateNumber(event)" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="country_code">%s</label></th>
								<td width="67&#37;">%s<br /><input name="ieee80211d" id="ieee80211d" type="checkbox" value="on" %s /> <label for="ieee80211d">%s</label></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="max_num_sta">%s</label> %s</th>
								<td width="67&#37;">
									<input name="max_num_sta" id="max_num_sta" type="text" value="%s" style="width: 5em;" onkeydown="return validateNumber(event)" />
									<br /><input name="no_probe_resp_if_max_sta" id="no_probe_resp_if_max_sta" type="checkbox" value="on" %s /> <label for="no_probe_resp_if_max_sta">%s</label>
								</td>
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
				$popup_header, $action, $config_id, $server_serial_no,
				__('Basic'),
				__('SSID'), $config_name, $ignore_broadcast_ssid_checked, __('Do not broadcast SSID'),
				__('Associated APs'), $config_aps,
				__('Hardware Mode'), $hw_mode_options,
				$hw_mode_option_style, $preamble_style, $preamble_checked, __('Enable short preamble'), $ieee80211n_entry, $ieee80211n_checked, __('Enable 802.11n'), $ieee80211ac_style, $ieee80211ac_checked, __('Enable 802.11ac'), $ieee80211n_entry, $wmm_enabled_checked, __('Enable QoS Support'),
				__('Security'), $auth_algs_checked, __('Enable WPA2'), __('MAC address filtering'), $macaddr_note, $macaddr_acl_options,
				_('Comment'), $config_comment,
				$security_options_style, __('Security'),
				__('WPA Passphrase'), $wpa_passphrase, __('Show'),
				__('Encryption Key'), $wpa_key_mgmt,
				__('Pairwise Cipher Suite'), $wpa_pairwise,
				__('Advanced'),
				__('Channel'), $channel,
				__('Country'), $country_code, $ieee80211d_checked, __('Limit the frequencies to regulatory limits'),
				__('Client Limit'), $max_num_sta_note, $max_num_sta, $no_probe_resp_if_max_sta_checked, __('Ignore broadcast Probe Request frames'),
				$popup_footer
			);

		return $return_form;
	}
	
	/**
	 * Gets config item data from key
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param integer $config_id Config parent ID to retrieve children for
	 * @param string $config_opt Config option to retrieve
	 * @return string
	 */
	function getConfig($config_id, $config_opt = null) {
		global $fmdb, $__FM_CONFIG;
		
		$return = '';
		
		/** Get the data from $config_opt */
		$query = "SELECT config_id,config_data FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config WHERE account_id='{$_SESSION['user']['account_id']}' AND config_status!='deleted' AND config_parent_id='{$config_id}' AND config_name='$config_opt' ORDER BY config_id ASC";
		$result = $fmdb->get_results($query);
		if (!$fmdb->sql_errors && $fmdb->num_rows) {
			return $fmdb->last_result[0]->config_data;
		}
		
		return $return;
	}
	
	/**
	 * Gets config item data from key
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param string $config_name Config name to get options for
	 * @param string $config_data Current config data for selection
	 * @return string
	 */
	private function buildConfigOptions($config_name, $config_data) {
		global $__FM_CONFIG, $fmdb, $fm_module_options;
		
		$query = "SELECT def_type,def_dropdown,def_minimum_version FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}functions WHERE def_option = '$config_name'";
		$fmdb->get_results($query);
		if ($fmdb->num_rows) {
			/** Build array of possible values */
			if (!class_exists('fm_module_options')) {
				include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_options.php');
			}
			return $fm_module_options->populateDefTypeDropdown($fmdb->last_result[0]->def_type, $config_data, $config_name);
		}
		
		return null;
	}
	
	/**
	 * Validates the user-submitted data (for add and edit)
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param array $post Posted data to validate
	 * @return array|string
	 */
	function validatePost($post) {
		global $__FM_CONFIG, $fmdb, $fm_module_options;
		
		include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_options.php');
		if (array_key_exists('config_name', $post)) {
			$post_tmp['config_data'] = $post['config_name'];
			$post_tmp['config_name'] = 'ssid';
		}
		if (array_key_exists('config_data', $post)) {
			$post_tmp['config_data'] = $post['config_data'];
		}
		$post['ignore_broadcast_ssid'] = array_key_exists('ignore_broadcast_ssid', $post) ? 1 : 0;
		$post['ieee80211n'] = array_key_exists('ieee80211n', $post) ? 1 : null;
		$post['ieee80211ac'] = array_key_exists('ieee80211ac', $post) ? 1 : null;
		$post['ieee80211d'] = array_key_exists('ieee80211d', $post) ? 1 : null;
		$post['wmm_enabled'] = array_key_exists('wmm_enabled', $post) ? 1 : null;
		$post['preamble'] = array_key_exists('preamble', $post) ? 1 : null;
		$post['auth_algs'] = array_key_exists('auth_algs', $post) ? 1 : null;
		$post['no_probe_resp_if_max_sta'] = array_key_exists('no_probe_resp_if_max_sta', $post) ? 1 : null;
		
		$security_fields_to_null = array('wpa_key_mgmt', 'wpa_pairwise');
		if (!$post['auth_algs']) {
			foreach ($security_fields_to_null as $field) {
				$post[$field] = null;
			}
		}
	
		foreach ($post as $key => $val) {
			if (!$val) continue;
			if (in_array($key, array('slp-directory-agent-only', 'slp-service-scope-only'))) {
				unset($post[$key]);
				continue;
			}
			if ($key == 'config_aps') {
				if (in_array('0', $val)) $val = 0;
			}
			if (is_array($val)) {
				$val = join(';', $val);
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
		$field_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_data');
		if ($field_length !== false && strlen($post['config_name']) > $field_length) return sprintf(dngettext($_SESSION['module'], 'Group name is too long (maximum %d character).', 'Host name is too long (maximum %d characters).', $field_length), $field_length);
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $post['config_data'], 'config_', 'config_data', "AND config_name='ssid' AND config_is_parent='yes' AND config_id!='{$post['config_id']}'");
		if ($fmdb->num_rows) return __('This WLAN already exists.');
		
		$post['config_type'] = 'wlan';
		
		return $post;
	}
	
	/**
	 * Gets WLAN listing
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param string $security What security to include
	 * @return array
	 */
	function getWLANList($security = 'open') {
		global $__FM_CONFIG, $fmdb;
		
		$include = true;
		
		$list[0][] = __('All WLANs');
		$list[0][] = '0';
		
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_id', 'config_', " AND config_is_parent='yes' AND config_parent_id=0 AND config_name='ssid' AND config_status='active'");
		if ($fmdb->num_rows) {
			$last_result = $fmdb->last_result;
			$count = $fmdb->num_rows;
			$i = 1;
			for ($j=0; $j<$count; $j++) {
				if ($security != 'open') {
					$query = "SELECT config_data FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config WHERE config_status='active' AND account_id='{$_SESSION['user']['account_id']}' AND config_parent_id={$last_result[$j]->config_id} AND config_name='auth_algs' AND config_data='1' LIMIT 1";
					$fmdb->query($query);
					$include = ($fmdb->num_rows) ? true : false;
				}
				if ($include) {
					$list[$i][] = $last_result[$j]->config_data;
					$list[$i][] = $last_result[$j]->config_id;
					$i++;
				}
			}
		}
		
		return $list;
	}
	
	/**
	 * Gets WLAN listing
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param array $wlan_ids WLAN IDs to translate
	 * @return string
	 */
	function getWLANLoggingNames($wlan_ids) {
		global $__FM_CONFIG, $fmdb;
		
		$wlan_names = '';
		foreach ((array) $wlan_ids as $id) {
			if (!$id) {
				return __('All WLANs');
			}
			$name = getNameFromID($id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_', 'config_id', 'config_data');
			$wlan_names .= "$name; ";
		}
		
		return rtrim($wlan_names, '; ');
	}
	
	/**
	 * Updates WLAN stats
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param integer $server_serial_no Server serial number to update
	 * @return void
	 */
	function updateWLANInfo($server_serial_no) {
		global $__FM_CONFIG, $fmdb;
		
		$ap_info = serialize($_POST['ap-info']);
		
		$query = "REPLACE INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}stats` (`account_id`, `server_serial_no`, `stat_last_report`, `stat_info`) VALUES ('" . getAccountID($_POST['AUTHKEY']) . "', '$server_serial_no', '" . strtotime('now') . "', '$ap_info')";
		$fmdb->query($query);
	}
	
}

if (!isset($fm_wifi_wlans))
	$fm_wifi_wlans = new fm_wifi_wlans();
