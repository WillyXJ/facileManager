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
 | fmSQLPass: Change database user passwords across multiple servers.      |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmsqlpass/                         |
 +-------------------------------------------------------------------------+
*/

class fm_sqlpass_groups {
	
	/**
	 * Displays the group list
	 */
	function rows($result) {
		global $fmdb;
		
		if (!$result) {
			echo '<p id="table_edits" class="noresult" name="groups">There are no server groups.</p>';
		} else {
			?>
			<table class="display_results" id="table_edits" name="groups">
				<thead>
					<tr>
						<th>Group Name</th>
						<th>Associated Servers</th>
						<th width="110" style="text-align: center;">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php
					$num_rows = $fmdb->num_rows;
					$results = $fmdb->last_result;
					for ($x=0; $x<$num_rows; $x++) {
						$this->displayRow($results[$x]);
					}
					?>
				</tbody>
			</table>
			<?php
		}
	}

	/**
	 * Adds the new group
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		$query = "INSERT INTO `fm_{$__FM_CONFIG['fmSQLPass']['prefix']}groups` (`account_id`, `group_name`) VALUES('{$_SESSION['user']['account_id']}', '{$post['group_name']}')";
		$result = $fmdb->query($query);
		
		if (!$fmdb->result) return 'Could not add the group because a database error occurred.';

		addLogEntry("Added server group '$group_name'.");
		return true;
	}

	/**
	 * Updates the selected group
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		$exclude = array('submit', 'action', 'group_id', 'page');

		$sql_edit = null;
		
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . sanitize($data) . "',";
			}
		}
		$sql = rtrim($sql_edit, ',');
		
		// Update the group
		$old_name = getNameFromID($post['group_id'], 'fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'groups', 'group_', 'group_id', 'group_name');
		$query = "UPDATE `fm_{$__FM_CONFIG['fmSQLPass']['prefix']}groups` SET $sql WHERE `group_id`={$post['group_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$fmdb->query($query);
		
		if (!$fmdb->result) return 'Could not add the group because a database error occurred.';
		
		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;
		
		addLogEntry("Updated server group '$old_name' to name: '{$post['group_name']}'.");
		return true;
	}
	
	
	/**
	 * Deletes the selected group
	 */
	function delete($id) {
		global $fmdb, $__FM_CONFIG;
		
		// Delete group
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'groups', 'group_', 'group_id', 'group_name');
		if (!updateStatus('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'groups', $id, 'group_', 'deleted', 'group_id')) {
			return 'This server group could not be deleted.'. "\n";
		} else {
			addLogEntry("Deleted server group '$tmp_name'.");
			return true;
		}
	}


	function displayRow($row) {
		global $fmdb, $__FM_CONFIG, $allowed_to_manage_servers;
		
		$disabled_class = ($row->group_status == 'disabled') ? ' class="disabled"' : null;
		
		$assoc_servers = 'None';
		
		$query = "SELECT server_name from fm_{$__FM_CONFIG['fmSQLPass']['prefix']}servers WHERE server_status!='deleted' AND account_id={$_SESSION['user']['account_id']}
					AND (server_groups={$row->group_id} OR server_groups LIKE '{$row->group_id};%' OR server_groups LIKE '%;{$row->group_id};%' 
					OR server_groups LIKE '%;{$row->group_id}')";
		if ($result = $fmdb->query($query)) {
			$assoc_servers = null;
			$result = $fmdb->last_result;
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$assoc_servers .= $result[$i]->server_name . ', ';
			}
			$assoc_servers = rtrim($assoc_servers, ', ');
		}
		
		if ($allowed_to_manage_servers) {
			$edit_status = '<td id="edit_delete_img">';
			$edit_status .= '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a href="' . $GLOBALS['basename'] . '?action=edit&id=' . $row->group_id . '&status=';
			$edit_status .= ($row->group_status == 'active') ? 'disabled' : 'active';
			$edit_status .= isset($row->server_serial_no) ? '&server_serial_no=' . $row->server_serial_no : null;
			$edit_status .= '">';
			$edit_status .= ($row->group_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
			$edit_status .= '</a>';
			$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			$edit_status .= '</td>';
		} else {
			$edit_status = '<td style="text-align: center;">N/A</td>';
		}
		
		echo <<<HTML
		<tr id="$row->group_id"$disabled_class>
			<td>$row->group_name</td>
			<td>$assoc_servers</td>
			$edit_status
		</tr>
HTML;
	}

	/**
	 * Displays the form to add new group
	 */
	function printForm($data = '', $action = 'add') {
		global $__FM_CONFIG;
		
		$group_id = 0;
		$group_name = null;
		$ucaction = ucfirst($action);
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($data))
				extract($data);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		/** Check name field length */
		$group_name_length = getColumnLength('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'groups', 'group_name');

		$return_form = <<<FORM
		<form name="manage" id="manage" method="post" action="">
			<input type="hidden" name="action" id="action" value="$action" />
			<input type="hidden" name="group_id" id="group_id" value="$group_id" />
			<table class="form-table">
				<tr>
					<th width="33%" scope="row"><label for="group_name">Group Name</label></th>
					<td width="67%"><input name="group_name" id="group_name" type="text" value="$group_name" size="40" placeholder="internal" maxlength="$group_name_length" /></td>
				</tr>
			</table>
			<input type="submit" name="submit" id="submit" value="$ucaction Group" class="button" />
			<input value="Cancel" class="button cancel" id="cancel_button" />
		</form>
FORM;

		return $return_form;
	}

	function validatePost($post) {
		global $fmdb, $__FM_CONFIG;
		
		$post['group_name'] = sanitize($post['group_name']);
		
		if (empty($post['group_name'])) return 'No group name defined.';

		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'groups', 'group_name');
		if ($field_length !== false && strlen($post['group_name']) > $field_length) return 'Group name is too long (maximum ' . $field_length . ' characters).';
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'groups', sanitize($post['group_name']), 'group_', 'group_name');
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			if ($result[0]->group_id != $post['group_id']) return 'This group name already exists.';
		}
		
		return $post;
	}
	
}

if (!isset($fm_sqlpass_groups))
	$fm_sqlpass_groups = new fm_sqlpass_groups();

?>
