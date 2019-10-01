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
 | fmSQLPass: Change database user passwords across multiple servers.      |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmsqlpass/                         |
 +-------------------------------------------------------------------------+
*/

class fm_sqlpass_groups {
	
	/**
	 * Displays the group list
	 */
	function rows($result, $page, $total_pages) {
		global $fmdb;
		
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;

		$start = $_SESSION['user']['record_count'] * ($page - 1);
		echo displayPagination($page, $total_pages);

		if (!$result) {
			printf('<p id="table_edits" class="noresult" name="groups">%s</p>', __('There are no server groups.'));
		} else {
			$table_info = array(
							'class' => 'display_results',
							'id' => 'table_edits',
							'name' => 'groups'
						);

			$title_array = array(__('Group Name'), __('Associated Servers'));
			if (currentUserCan('manage_servers', $_SESSION['module'])) $title_array[] = array('title' => __('Actions'), 'class' => 'header-actions');

			echo displayTableHeader($table_info, $title_array);
			
			$y = 0;
			for ($x=$start; $x<$num_rows; $x++) {
				if ($y == $_SESSION['user']['record_count']) break;
				$this->displayRow($results[$x]);
				$y++;
			}
			
			echo "</tbody>\n</table>\n";
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
		
		if ($fmdb->sql_errors) {
			return formatError(_('Could not add the group because a database error occurred.'), 'sql');
		}

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
				$sql_edit .= $key . "='" . sanitize($data) . "', ";
			}
		}
		$sql = rtrim($sql_edit, ', ');
		
		// Update the group
		$old_name = getNameFromID($post['group_id'], 'fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'groups', 'group_', 'group_id', 'group_name');
		$query = "UPDATE `fm_{$__FM_CONFIG['fmSQLPass']['prefix']}groups` SET $sql WHERE `group_id`={$post['group_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(_('Could not update the group because a database error occurred.'), 'sql');
		}
		
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
			return formatError(__('This server group could not be deleted.') . "\n");
		} else {
			addLogEntry("Deleted server group '$tmp_name'.");
			return true;
		}
	}


	function displayRow($row) {
		global $fmdb, $__FM_CONFIG;
		
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
		
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$edit_status = '<td id="row_actions">';
			$edit_status .= '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a class="status_form_link" href="#" rel="';
			$edit_status .= ($row->group_status == 'active') ? 'disabled' : 'active';
			$edit_status .= '">';
			$edit_status .= ($row->group_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
			$edit_status .= '</a>';
			$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			$edit_status .= '</td>';
		} else {
			$edit_status = null;
		}
		
		echo <<<HTML
		<tr id="$row->group_id" name="$row->group_name"$disabled_class>
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

		$popup_title = $action == 'add' ? __('Add Group') : __('Edit Group');
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		$return_form = sprintf('<form name="manage" id="manage" method="post" action="">
		%s
			<input type="hidden" name="action" id="action" value="%s" />
			<input type="hidden" name="group_id" id="group_id" value="%d" />
			<table class="form-table">
				<tr>
					<th width="33&#37;" scope="row"><label for="group_name">%s</label></th>
					<td width="67&#37;"><input name="group_name" id="group_name" type="text" value="%s" size="40" placeholder="%s" maxlength="%d" /></td>
				</tr>
			</table>
		%s
		</form>',
				$popup_header,
				$action, $group_id,
				__('Group Name'), $group_name, __('internal'), $group_name_length,
				$popup_footer
			);

		return $return_form;
	}

	function validatePost($post) {
		global $fmdb, $__FM_CONFIG;
		
		$post['group_name'] = sanitize($post['group_name']);
		
		if (empty($post['group_name'])) return __('No group name defined.');

		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'groups', 'group_name');
		if ($field_length !== false && strlen($post['group_name']) > $field_length) return sprintf(__('Group name is too long (maximum %d characters).'), $field_length);
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'groups', sanitize($post['group_name']), 'group_', 'group_name');
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			if ($result[0]->group_id != $post['group_id']) return __('This group name already exists.');
		}
		
		return $post;
	}
	
}

if (!isset($fm_sqlpass_groups))
	$fm_sqlpass_groups = new fm_sqlpass_groups();

?>
