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
 | fmDNS: Easily manage one or more ISC BIND servers                       |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdns/                             |
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

		$start = $_SESSION['user']['record_count'] * ($page - 1);

		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$bulk_actions_list = array(_('Enable'), _('Disable'), _('Delete'));
		}
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
		} else {
			$title_array[] = array(
				'class' => 'header-tiny header-nosort'
			);
		}
		$title_array[] = array('title' => __('Option'), 'rel' => 'cfg_name');
		$title_array[] = array('title' => __('Value'), 'rel' => 'cfg_data');
		$title_array[] = array('title' => _('Comment'), 'class' => 'header-nosort');
		
		$perms = ($results[0]->domain_id) ? zoneAccessIsAllowed(array($results[0]->domain_id), 'manage_zones') : currentUserCan('manage_servers', $_SESSION['module']);
		if ($perms) $title_array[] = array('title' => __('Actions'), 'class' => 'header-actions header-nosort');

		echo '<div class="overflow-container">';
		echo displayTableHeader($table_info, $title_array);
		
		if ($result) {
			$y = 0;
			for ($x=$start; $x<$num_rows; $x++) {
				if ($y == $_SESSION['user']['record_count']) break;
				$this->displayRow($results[$x], $perms);
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
		global $fmdb, $__FM_CONFIG, $global_form_field_excludes;
		
		/** Validate post */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		if (empty($post['cfg_name'])) return false;
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', sanitize($post['cfg_name']), 'cfg_', 'cfg_name', "AND cfg_type='{$post['cfg_type']}' AND server_serial_no='{$post['server_serial_no']}' AND view_id='{$post['view_id']}' AND domain_id='{$post['domain_id']}' AND server_id='{$post['server_id']}'");
		if ($fmdb->num_rows) {
			$num_same_config = $fmdb->num_rows;
			$query = "SELECT def_max_parameters FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}functions WHERE def_option='" . sanitize($post['cfg_name']) . "' AND def_option_type='{$post['cfg_type']}'";
			$fmdb->get_results($query);
			if ($fmdb->last_result[0]->def_max_parameters >= 0 && $num_same_config >= $fmdb->last_result[0]->def_max_parameters) {
				return __('This record already exists.');
			}
		}
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}config`";
		$sql_fields = '(';
		$sql_values = '';
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		
		$exclude = array_merge($global_form_field_excludes, array('cfg_id'));
		
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				if (!strlen($data) && ($key != 'cfg_comment') && ($post['cfg_name'] != 'forwarders')) return __('Empty values are not allowed.');
				if ($key == 'cfg_name' && !isDNSNameAcceptable($data)) return sprintf(__('%s is not an acceptable option name.'), $data);
				$sql_fields .= $key . ', ';
				$sql_values .= "'$data', ";
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the option because a database error occurred.'), 'sql');
		}

		setBuildUpdateConfigFlag($post['server_serial_no'], 'yes', 'build');

		$tmp_name = $post['cfg_name'];
		$tmp_server_name = getServerName($post['server_serial_no']);
		$tmp_view_name = $post['view_id'] ? getNameFromID($post['view_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name') : 'All Views';
		$tmp_domain_name = (isset($post['domain_id']) && $post['domain_id']) ? "\nZone: " . displayFriendlyDomainName(getNameFromID($post['domain_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name')) : null;

		$cfg_data = str_replace(";\n\t\t", '; ', $this->parseDefType($post['cfg_name'], $post['cfg_data']));
		addLogEntry("Added option:\nType: {$post['cfg_type']}\nName: $tmp_name\nValue: $cfg_data\nServer: $tmp_server_name\nView: {$tmp_view_name}{$tmp_domain_name}\nComment: {$post['cfg_comment']}");
		return true;
	}

	/**
	 * Updates the selected option
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG, $global_form_field_excludes;
		
		/** Validate post */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		if (isset($post['cfg_id']) && !isset($post['cfg_name'])) {
			$post['cfg_name'] = getNameFromID($post['cfg_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'cfg_id', 'cfg_name');
		}
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', $post['cfg_name'], 'cfg_', 'cfg_name', "AND cfg_id!={$post['cfg_id']} AND cfg_type='{$post['cfg_type']}' AND server_serial_no='{$post['server_serial_no']}' AND view_id='{$post['view_id']}' AND domain_id='{$post['domain_id']}' AND server_id='{$post['server_id']}'");
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			if ($result[0]->cfg_id != $post['cfg_id']) {
				$num_same_config = $fmdb->num_rows;
				$query = "SELECT def_max_parameters FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}functions WHERE def_option='" . $post['cfg_name'] . "' AND def_option_type='{$post['cfg_type']}'";
				$fmdb->get_results($query);
				if ($fmdb->last_result[0]->def_max_parameters >= 0 && $num_same_config > $fmdb->last_result[0]->def_max_parameters - 1) {
					return __('This record already exists.');
				}
			}
		}
		
		$exclude = array_merge($global_form_field_excludes, array('cfg_id'));

		$sql_edit = '';
		
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				if (!strlen($data) && ($key != 'cfg_comment') && ($post['cfg_name'] != 'forwarders')) return __('Empty values are not allowed.');
				if ($key == 'cfg_name' && !isDNSNameAcceptable($data)) return sprintf(__('%s is not an acceptable option name.'), $data);
				$sql_edit .= $key . "='" . $data . "', ";
			}
		}
		$sql = rtrim($sql_edit, ', ');
		
		// Update the config
		$old_name = getNameFromID($post['cfg_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'cfg_id', 'cfg_name');
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` SET $sql WHERE `cfg_id`={$post['cfg_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not update the option because a database error occurred.'), 'sql');
		}

		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		setBuildUpdateConfigFlag($post['server_serial_no'], 'yes', 'build');

		$tmp_server_name = getServerName($post['server_serial_no']);
		$tmp_view_name = $post['view_id'] ? getNameFromID($post['view_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name') : 'All Views';
		$tmp_domain_name = (isset($post['domain_id']) && $post['domain_id']) ? "\nZone: " . displayFriendlyDomainName(getNameFromID($post['domain_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name')) : null;

		$cfg_data = str_replace(";\n\t\t", '; ', $this->parseDefType($post['cfg_name'], $post['cfg_data']));
		addLogEntry("Updated option '$old_name' to:\nName: {$post['cfg_name']}\nValue: {$cfg_data}\nServer: $tmp_server_name\nView: {$tmp_view_name}{$tmp_domain_name}\nComment: {$post['cfg_comment']}");
		return true;
	}
	
	
	/**
	 * Deletes the selected option
	 */
	function delete($id, $server_serial_no = 0) {
		global $fmdb, $__FM_CONFIG;
		
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'cfg_id', 'cfg_name');
		$tmp_server_name = $server_serial_no ? getNameFromID($server_serial_no, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name') : 'All Servers';

		if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', $id, 'cfg_', 'deleted', 'cfg_id') === false) {
			return formatError(__('This option could not be deleted because a database error occurred.'), 'sql');
		} else {
			setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
			addLogEntry(sprintf(__("Option '%s' for %s was deleted."), $tmp_name, $tmp_server_name));
			return true;
		}
	}


	function displayRow($row, $perms) {
		global $fmdb, $__FM_CONFIG, $fm_dns_acls;
		
		if (!class_exists('fm_dns_acls')) {
			include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_acls.php');
		}
		
		$disabled_class = ($row->cfg_status == 'disabled') ? ' class="disabled"' : null;
		
		if ($perms) {
			$edit_uri = (strpos($_SERVER['REQUEST_URI'], '?')) ? $_SERVER['REQUEST_URI'] . '&' : $_SERVER['REQUEST_URI'] . '?';
			$edit_status = '<td class="column-actions">';
			$edit_status .= '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a class="status_form_link" href="#" rel="';
			$edit_status .= ($row->cfg_status == 'active') ? 'disabled' : 'active';
			$edit_status .= '">';
			$edit_status .= ($row->cfg_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
			$edit_status .= '</a>';
			$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			$edit_status .= '</td>';
			$checkbox = '<input type="checkbox" name="bulk_list[]" value="' . $row->cfg_id .'" />';
		} else {
			$edit_status = $checkbox = null;
		}
		
		$comments = nl2br($row->cfg_comment);
		
		/** Parse address_match_element configs */
		$cfg_data = $this->parseDefType($row->cfg_name, $row->cfg_data);
		
		$cfg_name = ($row->cfg_in_clause == 'yes') ? $row->cfg_name : '<b>' . $row->cfg_name . '</b>';

		echo <<<HTML
		<tr id="$row->cfg_id" name="$row->cfg_name"$disabled_class>
			<td>$checkbox</td>
			<td>$cfg_name</td>
			<td>$cfg_data</td>
			<td>$comments</td>
			$edit_status
		</tr>
HTML;
	}

	/**
	 * Displays the form to add new option
	 */
	function printForm($data = '', $action = 'add', $cfg_type = 'global', $cfg_type_id = null) {
		global $fmdb, $__FM_CONFIG, $fm_dns_zones;
		
		$cfg_id = $domain_id = 0;
		if (!$cfg_type_id) $cfg_type_id = 0;
		$cfg_name = $cfg_comment = null;
		$server_serial_no_field = $cfg_isparent = $cfg_data = null;
		
		switch(strtolower($cfg_type)) {
			case 'global':
			case 'ratelimit':
			case 'rrset':
				if (isset($_POST['item_sub_type'])) {
					$cfg_id_name = $_POST['item_sub_type'];
				} else {
					$cfg_id_name = (isset($_POST['view_id']) || strtolower($cfg_type) == 'ratelimit') ? 'view_id' : 'domain_id';
				}
				$server_serial_no = (isset($_REQUEST['request_uri']['server_serial_no']) && (intval($_REQUEST['request_uri']['server_serial_no']) > 0 || $_REQUEST['request_uri']['server_serial_no'][0] == 'g')) ? sanitize($_REQUEST['request_uri']['server_serial_no']) : 0;
				$server_serial_no_field = '<input type="hidden" name="server_serial_no" value="' . $server_serial_no . '" />';
				$server_id = (isset($_REQUEST['request_uri']['server_id']) && intval($_REQUEST['request_uri']['server_id']) > 0) ? sanitize($_REQUEST['request_uri']['server_id']) : 0;
				$server_id_field = '<input type="hidden" name="server_id" value="' . $server_id . '" />';
				$disabled = ($action == 'add') ? null : 'disabled';
				break;
		}
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		$cfg_isparent = buildSelect('cfg_isparent', 'cfg_isparent', enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_isparent'), $cfg_isparent, 1);
		$avail_options_array = $this->availableOptions($action, $server_serial_no, $cfg_type, $cfg_name);
		$cfg_avail_options = buildSelect('cfg_name', 'cfg_name', $avail_options_array, $cfg_name, 1, $disabled, false, 'displayOptionPlaceholder()');

		$query = "SELECT def_type FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}functions WHERE def_function='$cfg_type' AND 
				def_option=";
		if ($action != 'add') {
			$query .= "'$cfg_name'";
		} else {
			$query .= "'{$avail_options_array[0]}'";
		}
		$results = $fmdb->get_results($query);
		
		if ($fmdb->num_rows) {
			$data_holder = $results[0]->def_type;
		}
		
		$cfg_data = sanitize($cfg_data);

		$popup_title = $action == 'add' ? __('Add Option') : __('Edit Option');
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		$return_form = sprintf('<script>
			displayOptionPlaceholder("%s");
		</script>
		%s
		<form name="manage" id="manage">
			<input type="hidden" name="page" value="options" />
			<input type="hidden" name="action" value="%s" />
			<input type="hidden" name="cfg_id" value="%d" />
			<input type="hidden" name="cfg_type" value="%s" />
			<input type="hidden" name="%s" value="%s" />
			%s
			%s
			<table class="form-table">
				<tr>
					<th width="33&#37;" scope="row"><label for="cfg_name">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
				<tr class="value_placeholder">
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="cfg_comment">%s</label></th>
					<td width="67&#37;"><textarea id="cfg_comment" name="cfg_comment" rows="4" cols="30">%s</textarea></td>
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
				$cfg_data, $popup_header,
				$action, $cfg_id, $cfg_type, $cfg_id_name, $cfg_type_id,
				$server_serial_no_field, $server_id_field,
				__('Option Name'), $cfg_avail_options,
				_('Comment'), $cfg_comment,
				$popup_footer
			);

		return $return_form;
	}
	
	
	function availableParents($cfg_type, $prefix = null, $server_serial_no = 0, $include = 'none') {
		global $fmdb, $__FM_CONFIG;

		if (!is_array($include)) $include = array($include);
		
		$i=0;
		if (in_array('blank', $include)) {
			$return[$i][] = '';
			$return[$i][] = '';
			$i++;
		}
		if (in_array('none', $include)) {
			$return[$i][] = 'None';
			$return[$i][] = '0';
			$i++;
		}
		if (in_array('tls-default', $include)) {
			foreach (array('none', 'ephemeral') as $param) {
				$return[$i][] = $param;
				$return[$i][] = $param;
				$i++;
			}
		}
		
		$query = "SELECT cfg_id,cfg_name,cfg_data FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}config WHERE 
				account_id='{$_SESSION['user']['account_id']}' AND cfg_status='active' AND cfg_isparent='yes' AND 
				cfg_type='$cfg_type' AND server_serial_no IN ('0', '$server_serial_no') ORDER BY cfg_name,cfg_data ASC";
		$result = $fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$results = $fmdb->last_result;
			for ($j=0; $j<$fmdb->num_rows; $j++) {
				$return[$i][] = $results[$j]->cfg_data;
				$return[$i][] = $prefix . $results[$j]->cfg_id;
				$i++;
			}
		}
		
		return $return;
	}


	function availableOptions($action, $server_serial_no, $option_type = 'global', $cfg_name = null) {
		global $fmdb, $__FM_CONFIG;
		
		$temp_array = array();
		$return = array();
		
		if ($action == 'add') {
			if (isset($_POST['view_id'])) {
				$cfg_id_sql[] = 'view_id = ' . $_POST['view_id'];
			}
			if (isset($_POST['item_id'])) {
				if ($_POST['item_sub_type'] != 'domain_id') {
					$cfg_id_sql[] = 'domain_id=0';
				}
				$cfg_id_sql[] = $_POST['item_sub_type'] . ' = ' . $_POST['item_id'];
			}
			if (isset($cfg_id_sql)) {
				$cfg_id_sql = implode(' AND ', $cfg_id_sql);
			} else {
				$cfg_id_sql = 'view_id=0 AND domain_id=0';
			}
			
			$query = "SELECT cfg_name FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}config WHERE cfg_type='$option_type' AND cfg_status IN (
						'active', 'disabled'
					) AND account_id='{$_SESSION['user']['account_id']}' AND (
						(server_serial_no='$server_serial_no' AND $cfg_id_sql)
					)";
			$def_result = $fmdb->get_results($query);
			$def_result_count = $fmdb->num_rows;
			for ($i=0; $i<$def_result_count; $i++) {
				$temp_array[$i] = $def_result[$i]->cfg_name;
			}
			
			$query = "SELECT * FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}functions WHERE def_function='options'
				AND def_option_type='$option_type'";
			$query .= " AND def_clause_support LIKE '%";
			if (isset($option_type) && in_array($option_type, array_keys($__FM_CONFIG['options']['avail_types'])) &&
					isset($_POST['item_id']) && $_POST['item_id'] != 0) {
				switch ($_POST['item_sub_type']) {
					case 'view_id':
						$query .= 'V';
						break;
					case 'domain_id':
						$query .= 'Z';
						$domain_type = getNameFromID($_POST['item_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_type');
						$domain_type = ($domain_type == 'stub') ? 'B' : strtoupper($domain_type[0]);
						$query .= ($option_type != 'ratelimit') ? "%' AND def_zone_support LIKE '%$domain_type" : null;
						break;
					case 'server_id':
						$query .= 'S';
						break;
				}
			} else {
				$query .= 'O';
			}
			$query .= "%'";
		} else {
			$query = "SELECT * FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}functions WHERE def_function='options'
				AND def_option_type='$option_type' AND def_option='$cfg_name'";
		}
		$query .= " ORDER BY def_option ASC";

		$def_avail_result = $fmdb->get_results($query);
		$def_avail_result_count = $fmdb->num_rows;
		
		if ($def_avail_result_count) {
			$j=0;
			for ($i=0; $i<$def_avail_result_count; $i++) {
				$array_count_values = @array_count_values($temp_array);
				if ((is_array($temp_array) && array_search($def_avail_result[$i]->def_option, $temp_array) === false)
					|| !isset($temp_array)
					|| $array_count_values[$def_avail_result[$i]->def_option] < $def_avail_result[$i]->def_max_parameters
					|| $def_avail_result[$i]->def_max_parameters < 0) {
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
		
		if (is_array($post['cfg_data'])) $post['cfg_data'] = join(' ', $post['cfg_data']);
		
		if (isset($post['cfg_name'])) {
			$def_option = sprintf("'%s'", $post['cfg_name']);
		} elseif (isset($post['cfg_id'])) {
			$def_option = sprintf("'%s'", getNameFromID(intval($post['cfg_id']), "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config", 'cfg_', 'cfg_id', 'cfg_name', null, 'active'));
		} else return false;
		
		switch(trim($def_option, "'")) {
			case 'primaries':
			case 'also-notify':
				$terminate = ',';
				break;
			case 'listen-on':
			case 'listen-on-v6':
				$terminate = '; ';
				break;
		}
		/** Handle masters and also-notify formats */
		if (!empty($post['cfg_data_port']) && $post['cfg_data_port']) {
			$tmp_cfg_data[] = 'port ' . $post['cfg_data_port'];
		}
		if (!empty($post['cfg_data_dscp'])) {
			$tmp_cfg_data[] = 'dscp ' . $post['cfg_data_dscp'];
		}
		if (isset($post['cfg_data_params']) && is_array($post['cfg_data_params'])) {
			foreach ($post['cfg_data_params'] as $k => $v) {
				if ($v) $tmp_cfg_data[] = "$k $v";
			}
		}
		if (isset($tmp_cfg_data) && is_array($tmp_cfg_data)) {
			if (!$post['cfg_data'] || $post['cfg_data'] == ',') $post['cfg_data'] = 'any';
			if ($post['cfg_data']) {
				$tmp_cfg_data[] = '{ ' . trim(str_replace(',', $terminate, $post['cfg_data']), $terminate) . $terminate . '}';
			}
			$post['cfg_data'] = join(' ', $tmp_cfg_data);
			unset($tmp_cfg_data);
		}
		unset($post['cfg_data_port'], $post['cfg_data_dscp'], $post['cfg_data_params']);
		$post['cfg_data'] = str_replace(',,', ',', $post['cfg_data']);
		
		if (!isset($post['view_id'])) $post['view_id'] = 0;
		if (!isset($post['domain_id'])) $post['domain_id'] = 0;
		if (!isset($post['cfg_in_clause'])) $post['cfg_in_clause'] = 'yes';
		
		/** Validate against functions table */
		$post = $this->validateDefType($post, $def_option);

		return $post;
	}
	

	/**
	 * Parses for address_match_element and formats
	 *
	 * @since 1.3
	 * @package fmDNS
	 *
	 * @param string $cfg_name Config name to query
	 * @param string $cfg_data Data to parse/format
	 * @return string Return formated data
	 */
	function parseDefType($cfg_name, $cfg_data) {
		global $fmdb, $__FM_CONFIG, $fm_dns_acls, $fm_dns_zones;
		
		$query = "SELECT def_type FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}functions WHERE def_option = '{$cfg_name}'";
		$result = $fmdb->get_results($query);
		if ($fmdb->num_rows) {
			if (isset($result[0]->def_type)) $def_type = $result[0]->def_type;
		} else $def_type = null;
		
		if (strpos($def_type, 'rrset_order_spec') !== false) {
			$order_spec_elements = explode(' ', $cfg_data);
			// class
			if ($order_spec_elements[0] != 'any') $order_spec[] = 'class ' . $order_spec_elements[0];
			// type
			if ($order_spec_elements[1] != 'any') $order_spec[] = 'type ' . $order_spec_elements[1];
			// name
			if ($order_spec_elements[2] != 0) $order_spec[] = 'name "' . displayFriendlyDomainName(getNameFromID($order_spec_elements[2], "fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", 'domain_', 'domain_id', 'domain_name')) . '"';
			//order
			$order_spec[] = 'order ' . $order_spec_elements[3];
			
			return join(' ', $order_spec);
		}

		if (strpos($def_type, 'domain_select') !== false) {
			if (!$fm_dns_zones) {
				include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_zones.php');
			}

			foreach (explode(',', $cfg_data) as $domain_name) {
				if (preg_match('/^g_\d+/', $domain_name) == true) {
					$domain_name = $fm_dns_zones->getZoneGroupMembers(str_replace('g_', '', $domain_name));
				}
				if (!is_array($domain_name)) {
					$domain_name = array($domain_name);
				}

				foreach ($domain_name as $domain) {
					$hosted_domain_name = getNameFromID($domain, "fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", 'domain_', 'domain_id', 'domain_name');
					$hosted_domain_name = ($hosted_domain_name) ? $hosted_domain_name : $domain;
					$exclude_domain_names[] = '"' . displayFriendlyDomainName($hosted_domain_name) . '"';
				}
			}
			
			return join(";\n\t\t", $exclude_domain_names);
		}

		if (!$fm_dns_acls) {
			include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_acls.php');
		}
		
		return (strpos($cfg_data, 'acl_') !== false
			|| strpos($cfg_data, 'key_') !== false
			|| strpos($cfg_data, 'domain_') !== false
			|| strpos($def_type, 'address_match_element') !== false
			|| strpos($cfg_data, 'master_') !== false
			|| strpos($def_type, 'domain_name') !== false
			|| strpos($cfg_data, 'http_') !== false
			|| strpos($cfg_data, 'tls_') !== false
			|| strpos($cfg_data, 'dnssec_') !== false
			|| strpos($cfg_data, 'file_') !== false)
			? $fm_dns_acls->parseACL($cfg_data) : str_replace(',', '; ', $cfg_data);
	}
	

	/**
	 * Creates a drop down based on the def type options
	 *
	 * @since 1.3.5
	 * @package fmDNS
	 *
	 * @param string $cfg_name Config name to query
	 * @param string $cfg_data Data to parse/format
	 * @param string $select_name Name of the select object
	 * @param string $options Additional options
	 * @return string Return formated data
	 */
	function populateDefTypeDropdown($def_type, $cfg_data, $select_name = 'cfg_data', $options = null) {
		$raw_def_type_array = explode(')', str_replace('(', '', $def_type));
		if ($cfg_data) {
			$saved_data = explode(' ', (string) $cfg_data);
		} else {
			$saved_data = array('', '');
		}
		$i = 0;
		$dropdown = '';
		foreach ($raw_def_type_array as $raw_def_type) {
			$def_type_items = array();
			if (strlen(trim($raw_def_type))) {
				$raw_items = explode('|', $raw_def_type);
				if ($options == 'include-blank') {
					array_unshift($raw_items, '');
				}
				foreach ($raw_items as $item) {
					$def_type_items[] = trim($item);
				}
				$dropdown .= buildSelect($select_name . '[]', $select_name, $def_type_items, $saved_data[$i], 1);
			}
			$i++;
		}
		
		return $dropdown;
	}

	/**
	 * Validates def type
	 *
	 * @since 6.0.0
	 * @package fmDNS
	 *
	 * @param array $post Option type to validate
	 * @param string $def_option Optional definition type
	 * @return string Return formated data
	 */
	function validateDefType($post, $def_option = null) {
		global $fmdb, $__FM_CONFIG;

		$def_option = ($def_option) ? $def_option : "'{$post['cfg_name']}'";
		
		$query = "SELECT def_type,def_dropdown FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}functions WHERE def_option = $def_option";
		$result = $fmdb->get_results($query);
		if ($fmdb->num_rows) {
			if ($result[0]->def_dropdown == 'no') {
				$valid_types = trim(str_replace(array('(', ')'), '', $result[0]->def_type));
				
				switch ($valid_types) {
					case 'integer':
					case 'seconds':
					case 'minutes':
						if (!verifyNumber($post['cfg_data'])) return sprintf(__('%s is an invalid number.'), $post['cfg_data']);
						break;
					case 'port':
						if (!verifyNumber($post['cfg_data'], 0, 65535)) return sprintf(__('%s is an invalid port number.'), $post['cfg_data']);
						break;
					case 'quoted_string':
					case 'quoted_string | none':
					case 'quoted_string | none | hostname':
						$tmp_array = array();
						foreach (explode(';', $post['cfg_data']) as $k => $v) {
							$tmp_array[$k] = trim(str_replace('"', '', $v));
						}
						$post['cfg_data'] = '"' . implode('"; "', $tmp_array) . '"';
						if (in_array($post['cfg_data'], array('"none"', '"hostname"'))) $post['cfg_data'] = str_replace('"', '', $post['cfg_data']);
						break;
					case 'address_match_element':
						/** Need to check for valid ACLs or IP addresses */
						
						break;
					case 'ipv4_address | ipv6_address':
					case 'ipv4_address | *':
					case 'ipv6_address | *':
						if ($post['cfg_data'] != '*') {
							if (!verifyIPAddress($post['cfg_data'])) return sprintf(__('%s is an invalid IP address.'), $post['cfg_data']);
						}
						break;
				}
			}
		}

		return $post;
	}
}

if (!isset($fm_module_options))
	$fm_module_options = new fm_module_options();
