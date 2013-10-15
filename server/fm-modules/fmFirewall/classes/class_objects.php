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

class fm_module_objects {
	
	/**
	 * Displays the object list
	 */
	function rows($result, $type) {
		global $fmdb, $allowed_to_manage_objects;
		
		echo '			<table class="display_results" id="table_edits" name="objects">' . "\n";
		if (!$result) {
			echo '<p id="noresult">There are no ' . $type . ' objects defined.</p>';
		} else {
			$title_array = ($type != 'address') ? array('Object Name', 'Address', 'Netmask', 'Comment') : array('Object Name', 'Address', 'Comment');
			echo "<thead>\n<tr>\n";
			
			foreach ($title_array as $title) {
				$style = ($title == 'Comment') ? ' style="width: 40%;"' : null;
				echo '<th' . $style . '>' . $title . '</th>' . "\n";
			}
			
			if ($allowed_to_manage_objects) echo '<th width="110" style="text-align: center;">Actions</th>' . "\n";
			
			echo <<<HTML
					</tr>
				</thead>
				<tbody>

HTML;
					$num_rows = $fmdb->num_rows;
					$results = $fmdb->last_result;
					for ($x=0; $x<$num_rows; $x++) {
						$this->displayRow($results[$x]);
					}
					echo '</tbody>';
		}
		echo '</table>';
	}

	/**
	 * Adds the new object
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}objects`";
		$sql_fields = '(';
		$sql_values = null;
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		
		$exclude = array('submit', 'action', 'object_id', 'compress', 'AUTHKEY', 'module_name', 'module_type', 'config');

		foreach ($post as $key => $data) {
			$clean_data = sanitize($data);
			if (($key == 'object_name') && empty($clean_data)) return 'No object name defined.';
			if (!in_array($key, $exclude)) {
				$sql_fields .= $key . ',';
				$sql_values .= "'$clean_data',";
			}
		}
		$sql_fields = rtrim($sql_fields, ',') . ')';
		$sql_values = rtrim($sql_values, ',');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if (!$result) return 'Could not add the object because a database error occurred.';

		addLogEntry("Added object:\nName: {$post['object_name']}\nType: {$post['object_type']}\n" .
				"Address: {$post['object_address']} / {$post['object_mask']}\nComment: {$post['object_comment']}");
		return true;
	}

	/**
	 * Updates the selected object
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		$exclude = array('submit', 'action', 'object_id', 'compress', 'AUTHKEY', 'module_name', 'module_type', 'config', 'SERIALNO');

		$sql_edit = null;
		
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . sanitize($data) . "',";
			}
		}
		$sql = rtrim($sql_edit, ',');
		
		// Update the object
		$old_name = getNameFromID($post['object_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', 'object_', 'object_id', 'object_name');
		$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}objects` SET $sql WHERE `object_id`={$post['object_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		if (!$result) return 'Could not update the object because a database error occurred.';

//		setBuildUpdateConfigFlag(getServerSerial($post['object_id'], $_SESSION['module']), 'yes', 'build');
		
		addLogEntry("Updated object '$old_name' to:\nName: {$post['object_name']}\nType: {$post['object_type']}\n" .
					"Address: {$post['object_address']} / {$post['object_mask']}\nComment: {$post['object_comment']}");
		return true;
	}
	
	/**
	 * Deletes the selected object
	 */
	function delete($object_id) {
		global $fmdb, $__FM_CONFIG;
		
		/** Does the object_id exist for this account? */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', $object_id, 'object_', 'object_id');
		if ($fmdb->num_rows) {
			/** Delete object */
			$tmp_name = getNameFromID($object_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', 'object_', 'object_id', 'object_name');
			if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', $object_id, 'object_', 'deleted', 'object_id')) {
				addLogEntry("Deleted object '$tmp_name'.");
				return true;
			}
		}
		
		return 'This object could not be deleted.';
	}


	function displayRow($row) {
		global $__FM_CONFIG, $allowed_to_manage_objects, $allowed_to_build_configs;
		
		$edit_status = null;
		
		if ($allowed_to_manage_objects) {
			$edit_status = '<a class="edit_form_link" name="' . $row->object_type . '" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			$edit_status = '<td id="edit_delete_img">' . $edit_status . '</td>';
		}
		
		$edit_name = $row->object_name;
		$netmask = ($row->object_type != 'address') ? "<td>$row->object_mask</td>" : null;
		
		echo <<<HTML
			<tr id="$row->object_id">
				<td>$row->object_name</td>
				<td>$row->object_address</td>
				$netmask
				<td>$row->object_comment</td>
				$edit_status
			</tr>

HTML;
	}

	/**
	 * Displays the form to add new object
	 */
	function printForm($data = '', $action = 'add', $type = 'host') {
		global $__FM_CONFIG;
		
		$object_id = 0;
		$object_name = $object_address = $object_comment = null;
		$object_mask = null;
		$ucaction = ucfirst($action);
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}

		/** Show/hide divs */
		$netmask_option = ($type == 'address') ? 'none' : 'block';

		$object_name_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', 'object_name');
		$object_address_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', 'object_address');
		$object_mask_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', 'object_mask');
		$object_type = buildSelect('object_type', 'object_type', enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', 'object_type'), $type, 1);
		
		$return_form = <<<FORM
		<form name="manage" id="manage" method="post" action="objects?type=$type">
			<input type="hidden" name="action" value="$action" />
			<input type="hidden" name="object_id" value="$object_id" />
			<table class="form-table">
				<tr>
					<th width="33%" scope="row"><label for="object_name">Object Name</label></th>
					<td width="67%"><input name="object_name" id="object_name" type="text" value="$object_name" size="40" placeholder="http" maxlength="$object_name_length" /></td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="object_type">Object Type</label></th>
					<td width="67%">
						$object_type
					</td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="object_address">Address</label></th>
					<td width="67%"><input name="object_address" id="object_address" type="text" value="$object_address" size="40" placeholder="127.0.0.1" maxlength="$object_address_length" /></td>
				</tr>
				<tr id="netmask_option" style="display: $netmask_option;">
					<th width="33%" scope="row"><label for="object_mask">Netmask</label></th>
					<td width="67%"><input name="object_mask" id="object_mask" type="text" value="$object_mask" size="40" placeholder="255.255.255.0" maxlength="$object_mask_length" /></td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="object_comment">Comment</label></th>
					<td width="67%"><textarea id="object_comment" name="object_comment" rows="4" cols="30">$object_comment</textarea></td>
				</tr>
			</table>
			<input type="submit" name="submit" value="$ucaction Object" class="button" />
			<input value="Cancel" class="button cancel" id="cancel_button" />
		</form>
FORM;

		return $return_form;
	}
	
	
	function validatePost($post) {
		global $fmdb, $__FM_CONFIG;
		
		if (empty($post['object_name'])) return 'No object name defined.';
		if (empty($post['object_address'])) return 'No object address defined.';
		if ($post['object_type'] != 'address') {
			if (empty($post['object_mask'])) return 'No object netmask defined.';
		}
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', 'object_name');
		if ($field_length !== false && strlen($post['object_name']) > $field_length) return 'Object name is too long (maximum ' . $field_length . ' characters).';
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', $post['object_name'], 'object_', 'object_name', "AND object_type='{$post['object_type']}' AND object_id!={$post['object_id']}");
		if ($fmdb->num_rows) return 'This object name already exists.';
		
		/** Check address and mask */
		if (!verifyIPAddress($post['object_address'])) return 'Address is invalid.';
		if ($post['object_type'] != 'address') {
			if (!verifyIPAddress($post['object_mask'])) return 'Netmask is invalid.';
		}
		
		return $post;
	}
	
}

if (!isset($fm_module_objects))
	$fm_module_objects = new fm_module_objects();

?>
