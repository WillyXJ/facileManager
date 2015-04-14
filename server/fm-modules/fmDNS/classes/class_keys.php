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

class fm_dns_keys {
	
	/**
	 * Displays the key list
	 */
	function rows($result) {
		global $fmdb;
		
		if (!$result) {
			printf('<p id="table_edits" class="noresult" name="keys">%s</p>', __('There are no keys.'));
		} else {
			$num_rows = $fmdb->num_rows;
			$results = $fmdb->last_result;
			
			$table_info = array(
							'class' => 'display_results sortable',
							'id' => 'table_edits',
							'name' => 'keys'
						);

			$title_array = array(array('title' => __('Key'), 'rel' => 'key_name'), 
				array('title' => __('Algorithm'), 'class' => 'header-nosort'), 
				array('title' => __('Secret'), 'rel' => 'key_secret'), 
				array('title' => __('View'), 'class' => 'header-nosort'), 
				array('title' => __('Comment'), 'class' => 'header-nosort'));
			if (currentUserCan('manage_servers', $_SESSION['module'])) $title_array[] = array('title' => __('Actions'), 'class' => 'header-actions header-nosort');

			echo displayTableHeader($table_info, $title_array);
			
			for ($x=0; $x<$num_rows; $x++) {
				$this->displayRow($results[$x]);
			}
			
			echo "</tbody>\n</table>\n";
		}
	}

	/**
	 * Adds the new key
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG;
		
		$post['key_comment'] = trim($post['key_comment']);

		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_name');
		if ($field_length !== false && strlen($post['key_name']) > $field_length) return sprintf(ngettext('Key name is too long (maximum %d character).', 'Key name is too long (maximum %d characters).', 1), $field_length);
		
		/** Does the key already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', sanitize($post['key_name']), 'key_', 'key_name');
		if ($fmdb->num_rows) return 'This key already exists.';
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}keys`";
		$sql_fields = '(';
		$sql_values = null;
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		
		$exclude = array('submit', 'action', 'key_id');

		foreach ($post as $key => $data) {
			$clean_data = sanitize($data);
			if (($key == 'key_name' || $key == 'key_secret') && empty($clean_data)) return __('No key defined.');
			if (!in_array($key, $exclude)) {
				$sql_fields .= $key . ',';
				$sql_values .= "'$clean_data',";
			}
		}
		$sql_fields = rtrim($sql_fields, ',') . ')';
		$sql_values = rtrim($sql_values, ',');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if (!$fmdb->result) return __('Could not add the key because a database error occurred.');

		$view_name = $post['key_view'] ? getNameFromID($post['key_view'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name') : 'All Views';
		addLogEntry("Added key:\nName: {$post['key_name']}\nAlgorithm: {$post['key_algorithm']}\nSecret: {$post['key_secret']}\nView: $view_name\nComment: {$post['key_comment']}");
		return true;
	}

	/**
	 * Updates the selected key
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		if (empty($post['key_name']) || empty($post['key_secret'])) return __('No key defined.');
		$post['key_comment'] = trim($post['key_comment']);

		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_name');
		if ($field_length !== false && strlen($post['key_name']) > $field_length) return sprintf(ngettext('Key name is too long (maximum %d character).', 'Key name is too long (maximum %d characters).', 1), $field_length);
		
		/** Does the key already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', sanitize($post['key_name']), 'key_', 'key_name');
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			if ($result[0]->key_id != $post['key_id']) return __('This key already exists.');
		}
		
		$exclude = array('submit', 'action', 'key_id');

		$sql_edit = null;
		
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . sanitize($data) . "',";
			}
		}
		$sql = rtrim($sql_edit, ',');
		
		// Update the key
		$old_name = getNameFromID($post['key_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_', 'key_id', 'key_name');
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}keys` SET $sql WHERE `key_id`={$post['key_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		if (!$fmdb->result) return __('Could not update the key because a database error occurred.');

		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		$view_name = $post['key_view'] ? getNameFromID($post['key_view'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name') : 'All Views';
		addLogEntry("Updated key '$old_name' to the following:\nName: {$post['key_name']}\nAlgorithm: {$post['key_algorithm']}\nSecret: {$post['key_secret']}\nView: $view_name\nComment: {$post['key_comment']}");
		return true;
	}
	
	
	/**
	 * Deletes the selected key
	 */
	function delete($id, $server_serial_no = 0) {
		global $fmdb, $__FM_CONFIG;
		
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_', 'key_id', 'key_name');
		if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', $id, 'key_', 'deleted', 'key_id') === false) {
			return __('This key could not be deleted because a database error occurred.');
		} else {
			setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
			addLogEntry(sprintf(__("Key '%s' was deleted."), $tmp_name));
			return true;
		}
	}


	function displayRow($row) {
		global $__FM_CONFIG;
		
		$disabled_class = ($row->key_status == 'disabled') ? ' class="disabled"' : null;
		
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$edit_status = '<td id="edit_delete_img">';
			$edit_status .= '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			if (!getConfigAssoc($row->key_id, 'key')) {
				$edit_status .= '<a class="status_form_link" href="#" rel="';
				$edit_status .= ($row->key_status == 'active') ? 'disabled' : 'active';
				$edit_status .= '">';
				$edit_status .= ($row->key_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
				$edit_status .= '</a>';
				$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			}
			$edit_status .= '</td>';
		} else {
			$edit_status = null;
		}
		
		$edit_name = $row->key_name;
		$key_view = ($row->key_view) ? getNameFromID($row->key_view, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name') : 'All Views';
		
		$comments = nl2br($row->key_comment);

		echo <<<HTML
		<tr id="$row->key_id"$disabled_class>
			<td>$edit_name</td>
			<td>$row->key_algorithm</td>
			<td>$row->key_secret</td>
			<td>$key_view</td>
			<td>$comments</td>
			$edit_status
		</tr>
HTML;
	}

	/**
	 * Displays the form to add new key
	 */
	function printForm($data = '', $action = 'add') {
		global $__FM_CONFIG, $fm_dns_zones;
		
		include_once(ABSPATH . 'fm-modules/fmDNS/classes/class_zones.php');
		
		$key_id = 0;
		$key_name = $key_root_dir = $key_zones_dir = $key_comment = null;
		$ucaction = ucfirst($action);
		$key_algorithm = $key_view = $key_secret = null;
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}

		/** Check name field length */
		$key_name_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_name');
		$key_secret_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_secret');

		$key_algorithm = buildSelect('key_algorithm', 'key_algorithm', enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_algorithm'), $key_algorithm, 1);
		$key_view = buildSelect('key_view', 'key_view', $fm_dns_zones->availableViews(), $key_view);
		
		$popup_header = buildPopup('header', $ucaction . ' Key');
		$popup_footer = buildPopup('footer');
		
		$return_form = sprintf('<form name="manage" id="manage" method="post" action="">
		%s
			<input type="hidden" name="action" value="%s" />
			<input type="hidden" name="key_id" value="%d" />
			<table class="form-table">
				<tr>
					<th width="33&#37;" scope="row"><label for="key_name">%s</label></th>
					<td width="67&#37;"><input name="key_name" id="key_name" type="text" value="%s" size="40" maxlength="%d" /></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="key_view">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="key_algorithm">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="key_secret">%s</label></th>
					<td width="67&#37;"><input name="key_secret" id="key_secret" type="text" value="%s" size="40" maxlength="%d" /></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="key_comment">%s</label></th>
					<td width="67&#37;"><textarea id="key_comment" name="key_comment" rows="4" cols="30">%s</textarea></td>
				</tr>
			</table>
		%s
		</form>
		<script>
			$(document).ready(function() { $("#manage select").select2({minimumResultsForSearch: 10}); });
		</script>',
				$popup_header,
				$action, $key_id,
				__('Key Name'), $key_name, $key_name_length,
				__('View'), $key_view,
				__('Algorithm'), $key_algorithm,
				__('Secret'), $key_secret, $key_secret_length,
				__('Comment'), $key_comment,
				$popup_footer
			);

		return $return_form;
	}
	
	
	function parseKey($keys, $glue = '"; "') {
		global $__FM_CONFIG;
		
		$formatted_keys = null;
		foreach (explode(',', $keys) as $key_id) {
			$key_id = str_replace('key_', '', $key_id);
			$formatted_keys[] = getNameFromID($key_id, "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}keys", 'key_', 'key_id', 'key_name', null, 'active');
		}
		
		return implode($glue, $formatted_keys);
	}


}

if (!isset($fm_dns_keys))
	$fm_dns_keys = new fm_dns_keys();

?>
