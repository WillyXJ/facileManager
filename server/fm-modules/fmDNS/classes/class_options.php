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
 | fmDNS: Easily manage one or more ISC BIND servers                       |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdns/                             |
 +-------------------------------------------------------------------------+
*/

class fm_module_options {
	
	/**
	 * Displays the option list
	 */
	function rows($result) {
		global $fmdb;
		
		if (!$result) {
			echo '<p id="table_edits" class="noresult" name="options">There are no options.</p>';
		} else {
			$num_rows = $fmdb->num_rows;
			$results = $fmdb->last_result;

			$table_info = array(
							'class' => 'display_results',
							'id' => 'table_edits',
							'name' => 'options'
						);

			$title_array = array('Option', 'Value', 'Comment');
			if (currentUserCan('manage_servers', $_SESSION['module'])) $title_array[] = array('title' => 'Actions', 'class' => 'header-actions');

			echo displayTableHeader($table_info, $title_array);
			
			for ($x=0; $x<$num_rows; $x++) {
				$this->displayRow($results[$x]);
			}
			
			echo "</tbody>\n</table>\n";
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
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', sanitize($post['cfg_name']), 'cfg_', 'cfg_name', "AND cfg_type='{$post['cfg_type']}' AND server_serial_no='{$post['server_serial_no']}' AND view_id='{$post['view_id']}'");
		if ($fmdb->num_rows) return false;
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}config`";
		$sql_fields = '(';
		$sql_values = null;
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		
		$exclude = array('submit', 'action', 'cfg_id');
		
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$clean_data = sanitize($data);
				if (!strlen($clean_data) && $key != 'cfg_comment') return false;
				if ($key == 'cfg_name' && !isDNSNameAcceptable($clean_data)) return false;
				$sql_fields .= $key . ',';
				$sql_values .= "'$clean_data',";
			}
		}
		$sql_fields = rtrim($sql_fields, ',') . ')';
		$sql_values = rtrim($sql_values, ',');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if (!$fmdb->result) return false;

		$tmp_name = $post['cfg_name'];
		$tmp_server_name = $post['server_serial_no'] ? getNameFromID($post['server_serial_no'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name') : 'All Servers';
		$tmp_view_name = $post['view_id'] ? getNameFromID($post['view_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name') : 'All Views';
		$tmp_domain_name = isset($post['domain_id']) ? "\nZone: " . getNameFromID($post['domain_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name') : null;

		include_once(ABSPATH . 'fm-modules/fmDNS/classes/class_acls.php');
		$cfg_data = strpos($post['cfg_data'], 'acl_') !== false ? $fm_dns_acls->parseACL($post['cfg_data']) : $post['cfg_data'];
		addLogEntry("Added option:\nName: $tmp_name\nValue: $cfg_data\nServer: $tmp_server_name\nView: {$tmp_view_name}{$tmp_domain_name}\nComment: {$post['cfg_comment']}");
		return true;
	}

	/**
	 * Updates the selected option
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
//		print_r($post);exit;
		
		/** Validate post */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		if (isset($post['cfg_id']) && !isset($post['cfg_name'])) {
			$post['cfg_name'] = getNameFromID($post['cfg_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'cfg_id', 'cfg_name');
		}
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', sanitize($post['cfg_name']), 'cfg_', 'cfg_name', "AND cfg_type='{$post['cfg_type']}' AND server_serial_no='{$post['server_serial_no']}' AND view_id='{$post['view_id']}'");
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			if ($result[0]->cfg_id != $post['cfg_id']) return false;
		}
		
		$exclude = array('submit', 'action', 'cfg_id');

		$sql_edit = null;
		
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$clean_data = sanitize($data);
				if (!strlen($clean_data) && $key != 'cfg_comment') return false;
				if ($key == 'cfg_name' && !isDNSNameAcceptable($clean_data)) return false;
				$sql_edit .= $key . "='" . $clean_data . "',";
			}
		}
		$sql = rtrim($sql_edit, ',');
		
		// Update the config
		$old_name = getNameFromID($post['cfg_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'cfg_id', 'cfg_name');
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` SET $sql WHERE `cfg_id`={$post['cfg_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		if (!$fmdb->result) return false;

		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		$tmp_server_name = $post['server_serial_no'] ? getNameFromID($post['server_serial_no'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name') : 'All Servers';
		$tmp_view_name = $post['view_id'] ? getNameFromID($post['view_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name') : 'All Views';
		$tmp_domain_name = isset($post['domain_id']) ? "\nZone: " . getNameFromID($post['domain_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name') : null;

		include_once(ABSPATH . 'fm-modules/fmDNS/classes/class_acls.php');
		$cfg_data = strpos($post['cfg_data'], 'acl_') !== false ? $fm_dns_acls->parseACL($post['cfg_data']) : $post['cfg_data'];
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
			return 'This option could not be deleted.'. "\n";
		} else {
			setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
			addLogEntry("Deleted option '$tmp_name' for $tmp_server_name.");
			return true;
		}
	}


	function displayRow($row) {
		global $fmdb, $__FM_CONFIG, $fm_dns_acls;
		
		if (!class_exists('fm_dns_acls')) {
			include(ABSPATH . 'fm-modules/fmDNS/classes/class_acls.php');
		}
		
		$disabled_class = ($row->cfg_status == 'disabled') ? ' class="disabled"' : null;
		
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$edit_uri = (strpos($_SERVER['REQUEST_URI'], '?')) ? $_SERVER['REQUEST_URI'] . '&' : $_SERVER['REQUEST_URI'] . '?';
			$edit_status = '<td id="edit_delete_img">';
			$edit_status .= '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a href="' . $edit_uri . 'action=edit&id=' . $row->cfg_id . '&status=';
			$edit_status .= ($row->cfg_status == 'active') ? 'disabled' : 'active';
			$edit_status .= '">';
			$edit_status .= ($row->cfg_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
			$edit_status .= '</a>';
			$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			$edit_status .= '</td>';
		} else {
			$edit_status = null;
		}
		
		$comments = nl2br($row->cfg_comment);
		
		/** Parse address_match_element configs */
		$cfg_data = $this->parseDefType($row->cfg_name, $row->cfg_data);

		echo <<<HTML
		<tr id="$row->cfg_id"$disabled_class>
			<td>$row->cfg_name</td>
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
		global $fmdb, $__FM_CONFIG;
		
		$cfg_id = 0;
		$cfg_name = $cfg_root_dir = $cfg_zones_dir = $cfg_comment = null;
		$ucaction = ucfirst($action);
		$server_serial_no_field = $cfg_isparent = $cfg_parent = $cfg_data = null;
		
		switch(strtolower($cfg_type)) {
			case 'global':
				if (isset($_POST['item_sub_type'])) {
					$cfg_id_name = sanitize($_POST['item_sub_type']);
				} else {
					$cfg_id_name = isset($_POST['view_id']) ? 'view_id' : 'domain_id';
				}
				$data_holder = null;
				$server_serial_no = (isset($_REQUEST['server_serial_no']) && $_REQUEST['server_serial_no'] > 0) ? sanitize($_REQUEST['server_serial_no']) : 0;
				$server_serial_no_field = '<input type="hidden" name="server_serial_no" value="' . $server_serial_no . '" />';
				$request_uri = $cfg_type_id ? $cfg_id_name . '=' . $cfg_type_id : null;
				$request_uri .= $server_serial_no && $cfg_type_id ? '&' : null;
				$request_uri .= $server_serial_no ? 'server_serial_no=' . $server_serial_no : null;
				$request_uri = $request_uri ? '?' . $request_uri : null;
				$disabled = $action == 'add' ? null : 'disabled';
				break;
			case 'logging':
				$name_holder = 'severity';
				$name_note = null;
				$data_holder = 'dynamic';
				$data_note = null;
				break;
			case 'keys':
				$name_holder = 'key';
				$name_note = null;
				$data_holder = 'rndc-key';
				$data_note = null;
				break;
		}
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		$cfg_isparent = buildSelect('cfg_isparent', 'cfg_isparent', enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_isparent'), $cfg_isparent, 1);
		$cfg_parent = buildSelect('cfg_parent', 'cfg_parent', $this->availableParents($cfg_id, $cfg_type), $cfg_parent);
		$avail_options_array = $this->availableOptions($action, $server_serial_no);
		$cfg_avail_options = buildSelect('cfg_name', 'cfg_name', $avail_options_array, $cfg_name, 1, $disabled, false, 'displayOptionPlaceholder()');

		$query = "SELECT def_type FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}functions WHERE def_function='options' AND 
				def_option=";
		if ($action != 'add') {
			$query .= "'$cfg_name'";
		} else {
			$query .= "'{$avail_options_array[0]}'";
		}
		$fmdb->get_results($query);
		
		if ($fmdb->num_rows) {
			$results = $fmdb->last_result;
			
			$data_holder = $results[0]->def_type;
		}
		
		$cfg_data = sanitize($cfg_data);

		$popup_header = buildPopup('header', $ucaction . ' Option');
		$popup_footer = buildPopup('footer');
		
		$return_form = <<<FORM
		<script>
			displayOptionPlaceholder("$cfg_data");
		</script>
		<form name="manage" id="manage" method="post" action="$request_uri">
		$popup_header
			<input type="hidden" name="action" value="$action" />
			<input type="hidden" name="cfg_id" value="$cfg_id" />
			<input type="hidden" name="cfg_type" value="$cfg_type" />
			<input type="hidden" name="$cfg_id_name" value="$cfg_type_id" />
			$server_serial_no_field
			<table class="form-table">
				<tr>
					<th width="33%" scope="row"><label for="cfg_name">Option Name</label></th>
					<td width="67%">$cfg_avail_options</td>
				</tr>
				<tr class="value_placeholder">
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="cfg_comment">Comment</label></th>
					<td width="67%"><textarea id="cfg_comment" name="cfg_comment" rows="4" cols="30">$cfg_comment</textarea></td>
				</tr>
			</table>
		$popup_footer
		</form>
		<script>
			$(document).ready(function() {
				$("#manage select").select2({
					width: '200px',
					minimumResultsForSearch: 10
				});
			});
		</script>
FORM;

		return $return_form;
	}
	
	
	function availableParents($cfg_id, $cfg_type) {
		global $fmdb, $__FM_CONFIG;
		
		$return[0][] = 'None';
		$return[0][] = '0';
		
		$query = "SELECT cfg_id,cfg_name,cfg_data FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}config WHERE 
				account_id='{$_SESSION['user']['account_id']}' AND cfg_status='active' AND cfg_isparent='yes' AND 
				cfg_id!=$cfg_id AND cfg_type='$cfg_type' ORDER BY cfg_name,cfg_data ASC";
		$result = $fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$results = $fmdb->last_result;
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$return[$i+1][] = $results[$i]->cfg_name . ' ' . $results[$i]->cfg_data;
				$return[$i+1][] = $results[$i]->cfg_id;
			}
		}
		
		return $return;
	}


	function availableViews() {
		global $fmdb, $__FM_CONFIG;
		
		$return[0][] = 'None';
		$return[0][] = '0';
		
		$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_id', 'view_');
		if ($fmdb->num_rows) {
			$results = $fmdb->last_result;
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$return[$i+1][] = $results[$i]->view_name;
				$return[$i+1][] = $results[$i]->view_id;
			}
		}
		
		return $return;
	}


	function availableOptions($action, $server_serial_no) {
		global $fmdb, $__FM_CONFIG;
		
		if ($action == 'add') {
			if (isset($_POST['item_id']) && $_POST['item_id'] != 0) {
				$cfg_id_sql = $_POST['item_sub_type'] . ' = ' . $_POST['item_id'];
			} else {
				$cfg_id_sql = 'view_id=0 AND domain_id=0';
			}
			
			$query = "SELECT * FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}functions WHERE def_function='options'
				AND def_option NOT IN (
					SELECT cfg_name FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}config WHERE cfg_type='global' AND cfg_status IN (
						'active', 'disabled'
				) AND account_id='{$_SESSION['user']['account_id']}' AND (
					(server_serial_no=$server_serial_no AND $cfg_id_sql)
				)
			)";
			$query .= " AND def_clause_support LIKE '%";
			if (isset($_POST['item_id']) && $_POST['item_id'] != 0) {
				switch ($_POST['item_sub_type']) {
					case 'view_id':
						$query .= 'V';
						break;
					case 'domain_id':
						$query .= 'Z';
						break;
				}
			} else {
				$query .= 'O';
			}
			$query .= "%'";
		} else {
			$query = "SELECT * FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}functions WHERE def_function='options'";
		}
		$query .= " ORDER BY def_option ASC";
		
		$fmdb->get_results($query);
		$def_result = $fmdb->last_result;
		$def_result_count = $fmdb->num_rows;
		
		if ($fmdb->num_rows) {
			$results = $fmdb->last_result;
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$return[$i] = $results[$i]->def_option;
			}
		}
		
		return $return;
	}
	
	
	function validatePost($post) {
		global $fmdb, $__FM_CONFIG;
		
		$post['cfg_comment'] = trim($post['cfg_comment']);
		
		if (is_array($post['cfg_data'])) $post['cfg_data'] = join(' ', $post['cfg_data']);
		
		if (isset($post['cfg_name'])) {
			$def_option = "'{$post['cfg_name']}'";
		} elseif (isset($post['cfg_id'])) {
			$def_option = "(SELECT cfg_name FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config WHERE cfg_id = {$post['cfg_id']})";
		} else return false;
		
		$query = "SELECT def_type,def_dropdown FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}functions WHERE def_option = $def_option";
		$fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			if ($result[0]->def_dropdown == 'no') {
				$valid_types = trim(str_replace(array('(', ')'), '', $result[0]->def_type));
				
				switch ($valid_types) {
					case 'integer':
					case 'seconds':
					case 'minutes':
					case 'size_in_bytes':
						if (!verifyNumber($post['cfg_data'])) return $post['cfg_data'] . ' is an invalid number.';
						break;
					case 'port':
						if (!verifyNumber($post['cfg_data'], 0, 65535)) return $post['cfg_data'] . ' is an invalid port number.';
						break;
					case 'quoted_string':
						$post['cfg_data'] = '"' . trim($post['cfg_data'], '"') . '"';
						break;
					case 'quoted_string | none':
						$post['cfg_data'] = '"' . trim($post['cfg_data'], '"') . '"';
						if ($post['cfg_data'] == '"none"') $post['cfg_data'] = 'none';
						break;
					case 'address_match_element':
						/** Need to check for valid ACLs or IP addresses */
						
						break;
					case 'ipv4_address | ipv6_address':
						if (!verifyIPAddress($post['cfg_data'])) return $post['cfg_data'] . ' is an invalid IP address.';
						break;
					case 'ipv4_address | *':
					case 'ipv6_address | *':
						if ($post['cfg_data'] != '*') {
							if (!verifyIPAddress($post['cfg_data'])) $post['cfg_data'] . ' is an invalid IP address.';
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
	 * @since 1.3
	 * @package facileManager
	 *
	 * @param string $cfg_name Config name to query
	 * @param string $cfg_data Data to parse/format
	 * @return string Return formated data
	 */
	function parseDefType($cfg_name, $cfg_data) {
		global $fmdb, $__FM_CONFIG, $fm_dns_acls;
		
		$query = "SELECT def_type FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}functions WHERE def_option = '{$cfg_name}'";
		$fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			if (isset($result[0]->def_type)) $def_type = $result[0]->def_type;
		}
		
		return (strpos($cfg_data, 'acl_') !== false || strpos($def_type, 'address_match_element') !== false) ? $fm_dns_acls->parseACL($cfg_data) : $cfg_data;
	}
}

if (!isset($fm_module_options))
	$fm_module_options = new fm_module_options();

?>
