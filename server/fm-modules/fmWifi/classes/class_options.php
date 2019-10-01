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

class fm_module_options {
	
	/**
	 * Displays the option list
	 */
	function rows($result, $page, $total_pages) {
		global $fmdb;
		
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;

		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$bulk_actions_list = array(_('Enable'), _('Disable'), _('Delete'));
		}

		$start = $_SESSION['user']['record_count'] * ($page - 1);
		echo displayPagination($page, $total_pages, @buildBulkActionMenu($bulk_actions_list));

		$table_info = array(
						'class' => 'display_results sortable',
						'id' => 'table_edits',
						'name' => 'options'
					);

		if (is_array($bulk_actions_list)) {
			$title_array[] = array(
								'title' => '<input type="checkbox" class="tickall" onClick="toggle(this, \'bulk_list[]\')" />',
								'class' => 'header-tiny header-nosort'
							);
		}
		$title_array[] = array('title' => __('Option'), 'rel' => 'config_name');
		$title_array[] = array('title' => __('Value'), 'rel' => 'config_data');
		$title_array[] = array('title' => _('Comment'), 'class' => 'header-nosort');
		if (currentUserCan('manage_servers', $_SESSION['module'])) $title_array[] = array('title' => __('Actions'), 'class' => 'header-actions header-nosort');

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
			printf('<p id="table_edits" class="noresult" name="options">%s</p>', __('There are no options.'));
		}
	}

	/**
	 * Adds the new option
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate post */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		if (empty($post['config_name'])) return false;
//		print_r($post);exit;
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', sanitize($post['config_name']), 'config_', 'config_name', "AND config_type='{$post['config_type']}' AND config_data!='' AND server_serial_no='{$post['server_serial_no']}'");
		if ($fmdb->num_rows) {
			$num_same_config = $fmdb->num_rows;
			$query = "SELECT def_max_parameters FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}functions WHERE def_option='" . sanitize($post['config_name']) . "' AND def_option_type='{$post['config_type']}'";
			$fmdb->get_results($query);
			if ($fmdb->last_result[0]->def_max_parameters >= 0 && $num_same_config >= $fmdb->last_result[0]->def_max_parameters) {
				return __('This record already exists.');
			}
		}
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config`";
		$sql_fields = '(';
		$sql_values = null;
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		
		$exclude = array('submit', 'action', 'config_id');
		
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$clean_data = sanitize($data);
				if (!strlen($clean_data) && $key != 'config_comment') return __('Empty values are not allowed.');
//				if ($key == 'config_name' && !isDNSNameAcceptable($clean_data)) return sprintf(__('%s is not an acceptable option name.'), $clean_data);
				$sql_fields .= $key . ', ';
				$sql_values .= "'$clean_data', ";
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the option because a database error occurred.'), 'sql');
		}
		
		$tmp_name = $post['config_name'];
		$tmp_server_name = $post['server_serial_no'] ? getNameFromID($post['server_serial_no'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name') : 'All Servers';
//		$tmp_view_name = $post['view_id'] ? getNameFromID($post['view_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'views', 'view_', 'view_id', 'view_name') : 'All Views';
//		$tmp_domain_name = isset($post['domain_id']) ? "\nZone: " . displayFriendlyDomainName(getNameFromID($post['domain_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name')) : null;

//		include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_acls.php');
//		$config_data = strpos($post['config_data'], 'acl_') !== false ? $fm_dns_acls->parseACL($post['config_data']) : $post['config_data'];
		addLogEntry("Added option:\nName: $tmp_name\nValue: {$post['config_data']}\nServer: $tmp_server_name\nComment: {$post['config_comment']}");
		return true;
	}

	/**
	 * Updates the selected option
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate post */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		if (isset($post['config_id']) && !isset($post['config_name'])) {
			$post['config_name'] = getNameFromID($post['config_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_', 'config_id', 'config_name');
		}
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', sanitize($post['config_name']), 'config_', 'config_name', "AND config_id!={$post['config_id']} AND config_data!='{$post['config_data']}' AND config_type='{$post['config_type']}' AND server_serial_no='{$post['server_serial_no']}'");
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			if ($result[0]->config_id != $post['config_id']) {
				$num_same_config = $fmdb->num_rows;
				$query = "SELECT def_max_parameters FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}functions WHERE def_option='" . sanitize($post['config_name']) . "' AND def_option_type='{$post['config_type']}'";
				$fmdb->get_results($query);
				if ($fmdb->last_result[0]->def_max_parameters >= 0 && $num_same_config > $fmdb->last_result[0]->def_max_parameters - 1) {
					return __('This record already exists.');
				}
			}
		}
		
		$exclude = array('submit', 'action', 'config_id');

		$sql_edit = null;
		
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$clean_data = sanitize($data);
				if (!strlen($clean_data) && $key != 'config_comment') return false;
//				if ($key == 'config_name' && !isDNSNameAcceptable($clean_data)) return false;
				$sql_edit .= $key . "='" . $clean_data . "', ";
			}
		}
		$sql = rtrim($sql_edit, ', ');
		
		// Update the config
		$old_name = getNameFromID($post['config_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_', 'config_id', 'config_name');
		$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config` SET $sql WHERE `config_id`={$post['config_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not update the option because a database error occurred.'), 'sql');
		}

		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		$tmp_server_name = $post['server_serial_no'] ? getNameFromID($post['server_serial_no'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name') : 'All Servers';
//		$tmp_view_name = $post['view_id'] ? getNameFromID($post['view_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'views', 'view_', 'view_id', 'view_name') : 'All Views';
//		$tmp_domain_name = isset($post['domain_id']) ? "\nZone: " . displayFriendlyDomainName(getNameFromID($post['domain_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name')) : null;

//		include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_acls.php');
//		$config_data = strpos($post['config_data'], 'acl_') !== false ? $fm_dns_acls->parseACL($post['config_data']) : $post['config_data'];
		addLogEntry("Updated option '$old_name' to:\nName: {$post['config_name']}\nValue: {$post['config_data']}\nServer: $tmp_server_name\nComment: {$post['config_comment']}");
		return true;
	}
	
	
	/**
	 * Deletes the selected option
	 */
	function delete($id, $server_serial_no = 0) {
		global $fmdb, $__FM_CONFIG;
		
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_', 'config_id', 'config_name');
		$tmp_server_name = $server_serial_no ? getNameFromID($server_serial_no, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name') : 'All Servers';

		if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $id, 'config_', 'deleted', 'config_id') === false) {
			return formatError(__('This option could not be deleted because a database error occurred.'), 'sql');
		} else {
			setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
			addLogEntry(sprintf(__("Option '%s' for %s was deleted."), $tmp_name, $tmp_server_name));
			return true;
		}
	}


	function displayRow($row) {
		global $fmdb, $__FM_CONFIG, $fm_dns_acls;
		
		if (!class_exists('fm_dns_acls')) {
			include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_acls.php');
		}
		
		$disabled_class = ($row->config_status == 'disabled') ? ' class="disabled"' : null;
		
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$edit_uri = (strpos($_SERVER['REQUEST_URI'], '?')) ? $_SERVER['REQUEST_URI'] . '&' : $_SERVER['REQUEST_URI'] . '?';
			$edit_status = '<td id="row_actions">';
			$edit_status .= '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a class="status_form_link" href="#" rel="';
			$edit_status .= ($row->config_status == 'active') ? 'disabled' : 'active';
			$edit_status .= '">';
			$edit_status .= ($row->config_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
			$edit_status .= '</a>';
			$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			$edit_status .= '</td>';
			$checkbox = '<td><input type="checkbox" name="bulk_list[]" value="' . $row->config_id .'" /></td>';
		} else {
			$edit_status = $checkbox = null;
		}
		
		$comments = nl2br($row->config_comment);
		
		/** Parse address_match_element configs */
		$config_data = $this->parseDefType($row->config_name, $row->config_data);
		
		$config_name = ($row->config_in_clause == 'yes') ? $row->config_name : '<b>' . $row->config_name . '</b>';

		echo <<<HTML
		<tr id="$row->config_id" name="$row->config_name"$disabled_class>
			$checkbox
			<td>$config_name</td>
			<td>$config_data</td>
			<td>$comments</td>
			$edit_status
		</tr>
HTML;
	}

	/**
	 * Displays the form to add new option
	 */
	function printForm($data = '', $action = 'add', $config_type = 'global', $config_type_id = null) {
		global $fmdb, $__FM_CONFIG, $fm_dns_zones;
		
		$config_id = $domain_id = 0;
		if (!$config_type_id) $config_type_id = 0;
		$config_name = $config_root_dir = $config_zones_dir = $config_comment = null;
		$ucaction = ucfirst($action);
		$server_serial_no_field = $config_is_parent = $config_parent_id = $config_data = $config_id_name = null;
		
		if (isset($_POST['item_sub_type'])) {
			$config_id_name = sanitize($_POST['item_sub_type']);
		}
		$data_holder = null;
		$server_serial_no = (isset($_REQUEST['request_uri']['server_serial_no']) && (intval($_REQUEST['request_uri']['server_serial_no']) > 0 || $_REQUEST['request_uri']['server_serial_no'][0] == 'g')) ? sanitize($_REQUEST['request_uri']['server_serial_no']) : 0;
		$server_serial_no_field = '<input type="hidden" name="server_serial_no" value="' . $server_serial_no . '" />';
		$request_uri = 'config-options.php';
		if (isset($_REQUEST['request_uri'])) {
			$request_uri .= '?';
			foreach ($_REQUEST['request_uri'] as $key => $val) {
				$request_uri .= $key . '=' . sanitize($val) . '&';
			}
			$request_uri = rtrim($request_uri, '&');
		}
		$disabled = $action == 'add' ? null : 'disabled';
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		$config_is_parent = buildSelect('config_is_parent', 'config_is_parent', enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_is_parent'), $config_is_parent, 1);
		$config_parent_id = buildSelect('config_parent_id', 'config_parent_id', $this->availableParents($config_id, $config_type), $config_parent_id);
		$avail_options_array = $this->availableOptions($action, $server_serial_no, $config_type, $config_name);
		$config_avail_options = buildSelect('config_name', 'config_name', $avail_options_array, $config_name, 1, $disabled, false, 'displayOptionPlaceholder()');

		$query = "SELECT def_type FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}functions WHERE def_function='$config_type' AND 
				def_option=";
		if ($action != 'add') {
			$query .= "'$config_name'";
		} else {
			$query .= "'{$avail_options_array[0]}'";
		}
		$results = $fmdb->get_results($query);
		
		if ($fmdb->num_rows) {
			$data_holder = $results[0]->def_type;
		}
		
		$config_data = sanitize($config_data);

		$popup_title = $action == 'add' ? __('Add Option') : __('Edit Option');
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		$addl_options = null;

		$return_form = sprintf('<script>
			displayOptionPlaceholder("%s");
		</script>
		<form name="manage" id="manage" method="post" action="%s">
		%s
			<input type="hidden" name="action" value="%s" />
			<input type="hidden" name="config_id" value="%d" />
			<input type="hidden" name="config_type" value="%s" />
			<input type="hidden" name="%s" value="%s" />
			%s
			<table class="form-table">
				%s
				<tr>
					<th width="33&#37;" scope="row"><label for="config_name">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
				<tr class="value_placeholder">
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="config_comment">%s</label></th>
					<td width="67&#37;"><textarea id="config_comment" name="config_comment" rows="4" cols="30">%s</textarea></td>
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
				$config_data, $request_uri, $popup_header,
				$action, $config_id, $config_type, $config_id_name, $config_type_id, $server_serial_no_field,
				$addl_options,
				__('Option Name'), $config_avail_options,
				_('Comment'), $config_comment,
				$popup_footer
			);

		return $return_form;
	}
	
	
	function availableParents($config_id, $config_type) {
		global $fmdb, $__FM_CONFIG;
		
		$return[0][] = 'None';
		$return[0][] = '0';
		
		$query = "SELECT config_id,config_name,config_data FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config WHERE 
				account_id='{$_SESSION['user']['account_id']}' AND config_status='active' AND config_is_parent='yes' AND 
				config_id!=$config_id AND config_type='$config_type' ORDER BY config_name,config_data ASC";
		$result = $fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$results = $fmdb->last_result;
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$return[$i+1][] = $results[$i]->config_name . ' ' . $results[$i]->config_data;
				$return[$i+1][] = $results[$i]->config_id;
			}
		}
		
		return $return;
	}


	function availableOptions($action, $server_serial_no, $option_type = 'global', $config_name = null) {
		global $fmdb, $__FM_CONFIG;
		
		$temp_array = $return = null;
		
		if ($action == 'add') {
//			if (isset($_POST['view_id'])) {
//				$config_id_sql[] = 'view_id = ' . sanitize($_POST['view_id']);
//			}
			if (isset($_POST['item_id'])) {
				$config_id_sql[] = 'config_parent_id = ' . sanitize($_POST['item_id']);
			}
			if (isset($config_id_sql)) {
				$config_id_sql = 'AND ' . implode(' AND ', $config_id_sql);
			} else {
				$config_id_sql = null;
			}
			
			$query = "SELECT config_name FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config WHERE config_data!='' AND config_status IN (
						'active', 'disabled'
					) AND account_id='{$_SESSION['user']['account_id']}' AND (
						(server_serial_no='$server_serial_no' $config_id_sql)
					)";
			$def_result = $fmdb->get_results($query);
			if ($fmdb->num_rows) {
				$def_result_count = $fmdb->num_rows;
				for ($i=0; $i<$def_result_count; $i++) {
					$temp_array[$i] = $def_result[$i]->config_name;
				}
			}
			
			$query = "SELECT * FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}functions WHERE def_function='options'";
			$query .= " AND def_option_type IN ('";
			if (isset($_POST['item_id']) && $_POST['item_id'] != 0) {
				$query .= sanitize($_POST['item_sub_type']);
				if ($_POST['item_sub_type'] != 'failover') {
					$query .= "','global";
				}
			} else {
				$query .= 'global';
			}
			$query .= "')";
		} else {
			$query = "SELECT * FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}functions WHERE def_function='options'
				AND def_option='$config_name'";
		}
		$query .= " ORDER BY def_option ASC";
		
		$def_avail_result = $fmdb->get_results($query);
		$def_avail_result_count = $fmdb->num_rows;
		
		if ($def_avail_result_count) {
			$j=0;
			for ($i=0; $i<$def_avail_result_count; $i++) {
				if ((is_array($temp_array) && array_search($def_avail_result[$i]->def_option, $temp_array) === false) ||
						!isset($temp_array)) {
					$return[$j] = $def_avail_result[$i]->def_option;
					$j++;
				}
			}
			return $return;
		}
		
		return;
	}
	
	
	function validatePost($post) {
		global $fmdb, $__FM_CONFIG;
		
		$post['config_comment'] = trim($post['config_comment']);
		
		if (is_array($post['config_data'])) $post['config_data'] = join(' ', $post['config_data']);
		
		if (isset($post['config_name'])) {
			$def_option = "'{$post['config_name']}'";
		} elseif (isset($post['config_id'])) {
			$def_option = "(SELECT config_name FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config WHERE config_id = {$post['config_id']})";
		} else return false;
		
		if (isset($post['config_type']) && $post['config_type'] == 'options') {
			$post['config_type'] = getNameFromID($post['config_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_', 'config_parent_id', 'config_type');
			if ($post['action'] == 'add') {
				$post['config_parent_id'] = $post['config_id'];
				$post['config_id'] = 0;
			}
		}
		
		$post = $this->validateDefType($post, $def_option);
		
		return $post;
	}
	
	
	/**
	 * Validates def type
	 *
	 * @since 0.1
	 * @package fmWifi
	 *
	 * @param string $def_option Option type to validate
	 * @return string Return formated data
	 */
	function validateDefType($post) {
		global $fmdb, $__FM_CONFIG;
		
		$query = "SELECT def_type,def_dropdown,def_int_range FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}functions WHERE def_option = '{$post['config_name']}'";
		$result = $fmdb->get_results($query);
		if ($fmdb->num_rows) {
			if ($result[0]->def_dropdown == 'no') {
				$valid_types = trim(str_replace(array('(', ')'), '', $result[0]->def_type));
				
				switch ($valid_types) {
					case 'integer':
					case 'seconds':
					case 'minutes':
						if (!verifyNumber($post['config_data'])) return sprintf(__('%s is an invalid number.'), $post['config_data']);
						if ($result[0]->def_int_range) {
							list($start, $end) = explode(':', $result[0]->def_int_range);
							if (!verifyNumber($post['config_data'], $start, $end)) return sprintf(__('%s is not in the valid range (%s).'), $post['config_data'], $start . '-' . $end);
						}
						break;
					case 'port':
						if (!verifyNumber($post['config_data'], 0, 65535)) return sprintf(__('%s is an invalid port number.'), $post['config_data']);
						break;
					case 'quoted_string':
						$post['config_data'] = '"' . str_replace(array('"', "'"), '', $post['config_data']) . '"';
						break;
					case 'address_match_element':
						/** Need to check for valid ACLs or IP addresses */
						
						break;
					case 'ipv4_address | ipv6_address':
						if (!verifyIPAddress($post['config_data'])) return sprintf(__('%s is an invalid IP address.'), $post['config_data']);
						break;
					case 'ipv4_address | *':
					case 'ipv6_address | *':
						if ($post['config_data'] != '*') {
							if (!verifyIPAddress($post['config_data'])) return sprintf(__('%s is an invalid IP address.'), $post['config_data']);
						}
						break;
				}
			}
		}
		
		return $post;
	}
	

	/**
	 * Parses for address_match_element and formats
	 *
	 * @since 0.1
	 * @package fmWifi
	 *
	 * @param string $config_name Config name to query
	 * @param string $config_data Data to parse/format
	 * @return string Return formated data
	 */
	function parseDefType($config_name, $config_data = null) {
		global $fmdb, $__FM_CONFIG;
		
		if ($config_data == null) {
			$query = "SELECT def_option,def_type,def_dropdown,def_minimum_version FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}functions WHERE def_option = '{$config_name}'";
			$result = $fmdb->get_results($query);
			if ($fmdb->num_rows) {
				if (isset($result[0]->def_type)) $config_data = $result[0]->def_type;
			} else $config_data = null;
		}
		
		return str_replace(',', '; ', $config_data);
	}
	

	/**
	 * Creates a drop down based on the def type options
	 *
	 * @since 0.1
	 * @package fmWifi
	 *
	 * @param string $config_name Config name to query
	 * @param string $config_data Data to parse/format
	 * @return string Return formated data
	 */
	function populateDefTypeDropdown($def_type, $config_data, $select_name = 'config_data') {
		global $fmdb, $__FM_CONFIG;
		
		$raw_def_type_array = explode(')', str_replace('(', '', $def_type));
		$saved_data = explode(' ', $config_data);
		$i = 0;
		$dropdown = null;
		foreach ($raw_def_type_array as $raw_def_type) {
			$def_type_items = null;
			if (strlen(trim($raw_def_type))) {
				$raw_items = explode('|', $raw_def_type);
				foreach ($raw_items as $item) {
					$def_type_items[] = trim($item);
				}
				$dropdown .= buildSelect($select_name . '[]', $select_name, $def_type_items, $saved_data[$i], 1);
			}
			$i++;
		}
		
		return $dropdown;
	}
}

if (!isset($fm_module_options))
	$fm_module_options = new fm_module_options();

?>
