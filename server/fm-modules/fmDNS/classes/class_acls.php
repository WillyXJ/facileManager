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

class fm_dns_acls {
	
	/**
	 * Displays the acl list
	 */
	function rows($result) {
		global $fmdb;
		
		if (!$result) {
			echo '<p id="table_edits" class="noresult" name="acls">There are no ACLs.</p>';
		} else {
			$num_rows = $fmdb->num_rows;
			$results = $fmdb->last_result;
			
			$table_info = array(
							'class' => 'display_results',
							'id' => 'table_edits',
							'name' => 'acls'
						);

			$title_array = array('Name', 'Address List', 'Comment');
			if (currentUserCan('manage_servers', $_SESSION['module'])) $title_array[] = array('title' => 'Actions', 'class' => 'header-actions');

			echo displayTableHeader($table_info, $title_array);
			
			for ($x=0; $x<$num_rows; $x++) {
				$this->displayRow($results[$x]);
			}
			
			echo "</tbody>\n</table>\n";
		}
	}

	/**
	 * Adds the new acl
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_name');
		if ($field_length !== false && strlen($post['acl_name']) > $field_length) return 'ACL name is too long (maximum ' . $field_length . ' characters).';
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', sanitize($post['acl_name']), 'acl_', 'acl_name');
		if ($fmdb->num_rows) return 'This ACL already exists.';
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}acls`";
		$sql_fields = '(';
		$sql_values = null;
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		
		/** Cleans up acl_addresses for future parsing **/
		$post['acl_addresses'] = verifyAndCleanAddresses($post['acl_addresses']);
		if ($post['acl_addresses'] === false) return 'Invalid address(es) specified.';
		
		$post['acl_comment'] = trim($post['acl_comment']);
		
		$exclude = array('submit', 'action', 'server_id');

		foreach ($post as $key => $data) {
			$clean_data = sanitize($data);
			if ($key == 'acl_name' && empty($clean_data)) return 'No ACL name defined.';
			if (!in_array($key, $exclude)) {
				$sql_fields .= $key . ',';
				$sql_values .= "'$clean_data',";
			}
		}
		$sql_fields = rtrim($sql_fields, ',') . ')';
		$sql_values = rtrim($sql_values, ',');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if (!$fmdb->result) return 'Could not add the ACL because a database error occurred.';

		$acl_addresses = $post['acl_predefined'] == 'as defined:' ? $post['acl_addresses'] : $post['acl_predefined'];
		addLogEntry("Added ACL:\nName: {$post['acl_name']}\nAddresses: $acl_addresses\nComment: {$post['acl_comment']}");
		return true;
	}

	/**
	 * Updates the selected acl
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_name');
		if ($field_length !== false && strlen($post['acl_name']) > $field_length) return 'ACL name is too long (maximum ' . $field_length . ' characters).';
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', sanitize($post['acl_name']), 'acl_', 'acl_name');
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			if ($result[0]->acl_id != $post['acl_id']) return 'This ACL already exists.';
		}
		
		if (empty($post['acl_name'])) return 'No ACL name defined.';
		/** Cleans up acl_addresses for future parsing **/
		$post['acl_addresses'] = verifyAndCleanAddresses($post['acl_addresses']);
		if ($post['acl_addresses'] === false) return 'Invalid address(es) specified.';
		
		if ($post['acl_predefined'] != 'as defined:') $post['acl_addresses'] = null;

		$post['acl_comment'] = trim($post['acl_comment']);
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		
		$exclude = array('submit', 'action', 'server_id');

		$sql_edit = null;
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . sanitize($data) . "',";
			}
		}
		$sql = rtrim($sql_edit, ',');
		
		// Update the acl
		$old_name = getNameFromID($post['acl_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_', 'acl_id', 'acl_name');
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}acls` SET $sql WHERE `acl_id`={$post['acl_id']}";
		$result = $fmdb->query($query);
		
		if (!$fmdb->result) return 'Could not update the ACL because a database error occurred.';

		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		$acl_addresses = $post['acl_predefined'] == 'as defined:' ? $post['acl_addresses'] : $post['acl_predefined'];
		addLogEntry("Updated ACL '$old_name' to the following:\nName: {$post['acl_name']}\nAddresses: $acl_addresses\nComment: {$post['acl_comment']}");
		return true;
	}
	
	
	/**
	 * Deletes the selected ACL
	 */
	function delete($id, $server_serial_no = 0) {
		global $fmdb, $__FM_CONFIG;
		
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_', 'acl_id', 'acl_name');
		if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', $id, 'acl_', 'deleted', 'acl_id') === false) {
			return 'This ACL could not be deleted because a database error occurred.';
		} else {
			setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
			addLogEntry("Deleted ACL '$tmp_name'.");
			return true;
		}
	}


	function displayRow($row) {
		global $__FM_CONFIG;
		
		$disabled_class = ($row->acl_status == 'disabled') ? ' class="disabled"' : null;
		
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$edit_status = '<td id="edit_delete_img">';
			$edit_status .= '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a href="' . $GLOBALS['basename'] . '?action=edit&id=' . $row->acl_id . '&status=';
			$edit_status .= ($row->acl_status == 'active') ? 'disabled' : 'active';
			$edit_status .= $row->server_serial_no ? '&server_serial_no=' . $row->server_serial_no : null;
			$edit_status .= '">';
			$edit_status .= ($row->acl_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
			$edit_status .= '</a>';
			$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			$edit_status .= '</td>';
		} else {
			$edit_status = null;
		}
		
		$edit_name = $row->acl_name;
		$edit_addresses = ($row->acl_predefined == 'as defined:') ? nl2br(str_replace(';', "\n", $row->acl_addresses)) : $row->acl_predefined;
		
		$comments = nl2br($row->acl_comment);

		echo <<<HTML
		<tr id="$row->acl_id"$disabled_class>
			<td>$edit_name</td>
			<td>$edit_addresses</td>
			<td>$comments</td>
			$edit_status
		</tr>
HTML;
	}

	/**
	 * Displays the form to add new acl
	 */
	function printForm($data = '', $action = 'add') {
		global $__FM_CONFIG;
		
		$acl_id = 0;
		$acl_name = $acl_addresses = $acl_comment = null;
		$acl_predefined = 'as defined:';
		$ucaction = ucfirst($action);
		$server_serial_no = (isset($_REQUEST['server_serial_no']) && $_REQUEST['server_serial_no'] > 0) ? sanitize($_REQUEST['server_serial_no']) : 0;
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		$acl_predefined = buildSelect('acl_predefined', 'acl_predefined', enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_predefined'), $acl_predefined);
		$acl_addresses = str_replace(';', "\n", rtrim(str_replace(' ', '', $acl_addresses), ';'));

		/** Get field length */
		$acl_name_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_name');

		$popup_header = buildPopup('header', $ucaction . ' ACL');
		$popup_footer = buildPopup('footer');
		
		$return_form = <<<FORM
		<form name="manage" id="manage" method="post" action="">
		$popup_header
			<input type="hidden" name="action" value="$action" />
			<input type="hidden" name="acl_id" value="$acl_id" />
			<input type="hidden" name="server_serial_no" value="$server_serial_no" />
			<table class="form-table">
				<tr>
					<th width="33%" scope="row"><label for="acl_name">ACL Name</label></th>
					<td width="67%"><input name="acl_name" id="acl_name" type="text" value="$acl_name" size="40" placeholder="internal" maxlength="$acl_name_length" /></td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="acl_predefined">Matched Address List</label></th>
					<td width="67%">$acl_predefined
					<textarea name="acl_addresses" rows="7" cols="28" placeholder="Addresses and subnets delimited by space, semi-colon, or newline">$acl_addresses</textarea></td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="acl_comment">Comment</label></th>
					<td width="67%"><textarea id="acl_comment" name="acl_comment" rows="4" cols="30">$acl_comment</textarea></td>
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

	/**
	 * Gets the ACL listing
	 */
	function getACLList($server_serial_no = 0, $include = 'acl') {
		global $__FM_CONFIG, $fmdb;
		
		$acl_list = null;
		$serial_sql = $server_serial_no ? "AND server_serial_no IN (0,$server_serial_no)" : "AND server_serial_no=0";
		$i = 0;
		
		basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_id', 'acl_', $serial_sql);
		if ($fmdb->num_rows) {
			$last_result = $fmdb->last_result;
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$acl_list[$i]['id'] = 'acl_' . $last_result[$i]->acl_id;
				$acl_list[$i]['text'] = $last_result[$i]->acl_name;
			}
		}
		
		if ($include == 'all') {
			foreach (array('none', 'any', 'localhost', 'localnets') as $predefined) {
				$acl_list[$i]['id'] = $predefined;
				$acl_list[$i]['text'] = $predefined;
				$i++;
			}
		}
		
		return $acl_list;
	}

	/**
	 * Builds the ACL listing JSON
	 */
	function buildACLJSON($saved_acls, $server_serial_no = 0) {
		$available_acls = $this->getACLList($server_serial_no, 'all');
		$temp_acls = null;
		foreach ($available_acls as $temp_acl_array) {
			$temp_acls[] = $temp_acl_array['id'];
		}
		$i = count($available_acls);
		foreach (explode(',', $saved_acls) as $saved_acl) {
			if (!$saved_acl) continue;
			if (array_search($saved_acl, $temp_acls) === false) {
				$available_acls[$i]['id'] = $saved_acl;
				$available_acls[$i]['text'] = $saved_acl;
				$i++;
			}
		}
		$available_acls = json_encode($available_acls);
		unset($temp_acl_array, $temp_acl);
		
		return $available_acls;
	}
	
	
	function parseACL($address_match_list) {
		global $__FM_CONFIG;
		
		$acls = explode(',', $address_match_list);
		$formatted_acls = null;
		foreach ($acls as $address) {
			if (strpos($address, 'acl_') === false) $formatted_acls[] = $address;
			
			$acl_id = str_replace('acl_', '', $address);
			$formatted_acls[] = getNameFromID($acl_id, "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}acls", 'acl_', 'acl_id', 'acl_name', null, 'active');
		}
		
		return implode('; ', $formatted_acls);
	}

}

if (!isset($fm_dns_acls))
	$fm_dns_acls = new fm_dns_acls();

?>
