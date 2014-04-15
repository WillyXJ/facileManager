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

class fm_dns_views {
	
	/**
	 * Displays the view list
	 */
	function rows($result) {
		global $fmdb;
		
		if (!$result) {
			echo '<p id="table_edits" class="noresult" name="views">There are no views.</p>';
		} else {
			?>
			<table class="display_results" id="table_edits" name="views">
				<thead>
					<tr>
						<th>View Name</th>
						<th>Comment</th>
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
	 * Adds the new view
	 */
	function add($data) {
		global $fmdb, $__FM_CONFIG;
		
		extract($data, EXTR_SKIP);
		
		$view_name = sanitize($view_name);
		
		if (empty($view_name)) return 'No view name defined.';
		$view_comment = trim($view_comment);
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_name');
		if ($field_length !== false && strlen($view_name) > $field_length) return 'View name is too long (maximum ' . $field_length . ' characters).';
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', $view_name, 'view_', 'view_name');
		if ($fmdb->num_rows) return 'This view already exists.';
		
		$query = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}views` (`account_id`, `server_serial_no`, `view_name`, `view_comment`) VALUES('{$_SESSION['user']['account_id']}', $server_serial_no, '$view_name', '$view_comment')";
		$result = $fmdb->query($query);
		
		if (!$fmdb->result) return 'Could not add the view because a database error occurred.';

		addLogEntry("Added view:\nName: $view_name\nComment: $view_comment");
		return true;
	}

	/**
	 * Updates the selected view
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		if (empty($post['view_name'])) return 'No view name defined.';
		$post['view_comment'] = trim($post['view_comment']);

		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_name');
		if ($field_length !== false && strlen($post['view_name']) > $field_length) return 'View name is too long (maximum ' . $field_length . ' characters).';
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', sanitize($post['view_name']), 'view_', 'view_name');
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			if ($result[0]->view_id != $post['view_id']) return 'This view already exists.';
		}
		
		$exclude = array('submit', 'action', 'view_id', 'page');

		$sql_edit = null;
		
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . sanitize($data) . "',";
			}
		}
		$sql = rtrim($sql_edit, ',');
		
		/** Update the view */
		$old_name = getNameFromID($post['view_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name');
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}views` SET $sql WHERE `view_id`={$post['view_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		if (!$fmdb->result) return 'Could not update the view because a database error occurred.';

		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		addLogEntry("Updated view '$old_name' to the following:\nName: {$post['view_name']}\nComment: {$post['view_comment']}");
		return true;
	}
	
	
	/**
	 * Deletes the selected view
	 */
	function delete($id) {
		global $fmdb, $__FM_CONFIG;
		
		/** Are there any associated zones? */
		basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_id', 'domain_', "AND (`domain_view`=$id or `domain_view` LIKE '$id;%' or `domain_view` LIKE '%;$id' or `domain_view` LIKE '%;$id;%')");
		if ($fmdb->num_rows) {
			return 'There are zones associated with this view.';
		}
		
		/** Are there any corresponding configs to delete? */
		basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_id', 'cfg_', 'AND cfg_view=' . $id);
		if ($fmdb->num_rows) {
			/** Delete corresponding configs */
			if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', $id, 'cfg_', 'deleted', 'cfg_view') === false) {
				return 'The corresponding configs could not be deleted.';
			}
		}
		
		/** Delete view */
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name');
		if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', $id, 'view_', 'deleted', 'view_id') === false) {
			return 'This view could not be deleted because a database error occurred.';
		} else {
//			setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
			addLogEntry("Deleted view '$tmp_name'.");
			return true;
		}
	}


	function displayRow($row) {
		global $__FM_CONFIG, $allowed_to_manage_servers;
		
		$disabled_class = ($row->view_status == 'disabled') ? ' class="disabled"' : null;
		
		$edit_name = '<a href="config-options.php?view_id=' . $row->view_id;
		$edit_name .= $row->server_serial_no ? '&server_serial_no=' . $row->server_serial_no : null;
		$edit_name .= '">' . $row->view_name . '</a>';
		if ($allowed_to_manage_servers) {
			$edit_status = '<td id="edit_delete_img">';
			$edit_status .= '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a href="' . $GLOBALS['basename'] . '?action=edit&id=' . $row->view_id . '&status=';
			$edit_status .= ($row->view_status == 'active') ? 'disabled' : 'active';
			$edit_status .= $row->server_serial_no ? '&server_serial_no=' . $row->server_serial_no : null;
			$edit_status .= '">';
			$edit_status .= ($row->view_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
			$edit_status .= '</a>';
			$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			$edit_status .= '</td>';
		} else {
			$edit_status = '<td style="text-align: center;">N/A</td>';
		}
		
		echo <<<HTML
		<tr id="$row->view_id"$disabled_class>
			<td>$edit_name</td>
			<td>$row->view_comment</td>
			$edit_status
		</tr>
HTML;
	}

	/**
	 * Displays the form to add new view
	 */
	function printForm($data = '', $action = 'add') {
		global $__FM_CONFIG;
		
		$view_id = 0;
		$view_name = $view_root_dir = $view_zones_dir = $view_comment = null;
		$ucaction = ucfirst($action);
		$server_serial_no = (isset($_REQUEST['server_serial_no']) && $_REQUEST['server_serial_no'] > 0) ? sanitize($_REQUEST['server_serial_no']) : 0;
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($data))
				extract($data);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		/** Get field length */
		$view_name_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_name');
		
		$return_form = <<<FORM
		<form name="manage" id="manage" method="post" action="">
			<input type="hidden" name="page" id="page" value="views" />
			<input type="hidden" name="action" id="action" value="$action" />
			<input type="hidden" name="view_id" id="view_id" value="$view_id" />
			<input type="hidden" name="server_serial_no" value="$server_serial_no" />
			<table class="form-table">
				<tr>
					<th width="33%" scope="row"><label for="view_name">View Name</label></th>
					<td width="67%"><input name="view_name" id="view_name" type="text" value="$view_name" size="40" placeholder="internal" maxlength="$view_name_length" /></td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="view_comment">Comment</label></th>
					<td width="67%"><textarea id="view_comment" name="view_comment" rows="4" cols="30">$view_comment</textarea></td>
				</tr>
			</table>
			<input type="submit" name="submit" id="submit" value="$ucaction View" class="button" />
			<input value="Cancel" class="button cancel" id="cancel_button" />
		</form>
FORM;

		return $return_form;
	}

}

if (!isset($fm_dns_views))
	$fm_dns_views = new fm_dns_views();

?>
