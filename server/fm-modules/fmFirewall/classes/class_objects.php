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
 | fmFirewall: Easily manage one or more software firewalls                |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmfirewall/                        |
 +-------------------------------------------------------------------------+
*/

class fm_module_objects {
	
	/**
	 * Displays the object list
	 */
	function rows($result, $type, $page, $total_pages) {
		global $fmdb;
		
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;

		if (currentUserCan('manage_' . $type . 's', $_SESSION['module'])) {
			$bulk_actions_list = array(_('Delete'));
		}

		$start = $_SESSION['user']['record_count'] * ($page - 1);
		echo displayPagination($page, $total_pages, @buildBulkActionMenu($bulk_actions_list));
		echo '<div class="overflow-container">';

		$table_info = array(
						'class' => 'display_results',
						'id' => 'table_edits',
						'name' => 'objects'
					);

		if (is_array($bulk_actions_list)) {
			$title_array[] = array(
								'title' => '<input type="checkbox" class="tickall" onClick="toggle(this, \'bulk_list[]\')" />',
								'class' => 'header-tiny header-nosort'
							);
		}
		$title_array = array_merge((array) $title_array, array(__('Object Name'), __('Address')));
		if ($type != 'host') $title_array[] = __('Netmask');
		$title_array[] = array('title' => _('Comment'), 'style' => 'width: 40%;');
		if (is_array($bulk_actions_list)) $title_array[] = array('title' => _('Actions'), 'class' => 'header-actions');

		echo '<div class="existing-container" style="bottom: 10em;">';
		echo displayTableHeader($table_info, $title_array);

		if ($result) {
			$y = 0;
			for ($x=$start; $x<$num_rows; $x++) {
				if ($y == $_SESSION['user']['record_count']) break;
				$this->displayRow($results[$x]);
				$y++;
			}
		}
			
		echo "</tbody>\n</table></div></div>\n";
		if (!$result) {
			printf('<p id="table_edits" class="noresult" name="objects">%s</p>', sprintf(__('There are no %s objects defined.'), $type));
		}
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
			if (($key == 'object_name') && empty($clean_data)) return __('No object name defined.');
			if (!in_array($key, $exclude)) {
				$sql_fields .= $key . ', ';
				$sql_values .= "'$clean_data', ";
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the object because a database error occurred.'), 'sql');
		}

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
				$sql_edit .= $key . "='" . sanitize($data) . "', ";
			}
		}
		$sql = rtrim($sql_edit, ', ');
		
		// Update the object
		$old_name = getNameFromID($post['object_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', 'object_', 'object_id', 'object_name');
		$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}objects` SET $sql WHERE `object_id`={$post['object_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not update the object because a database error occurred.'), 'sql');
		}
		
		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

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
			/** Is the object_id present in a policy? */
			if (isItemInPolicy($object_id, 'object')) return __('This object could not be deleted because it is associated with one or more policies.');
			
			/** Delete object */
			$tmp_name = getNameFromID($object_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', 'object_', 'object_id', 'object_name');
			if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', $object_id, 'object_', 'deleted', 'object_id')) {
				addLogEntry(sprintf(__('Object (%s) was deleted.'), $tmp_name));
				return true;
			}
		}
		
		return formatError(__('This object could not be deleted.'), 'sql');
	}


	function displayRow($row) {
		global $__FM_CONFIG;
		
		$disabled_class = ($row->object_status == 'disabled') ? ' class="disabled"' : null;
		
		$edit_status = $checkbox = null;
		
		if (currentUserCan('manage_objects', $_SESSION['module'])) {
			$edit_status = '<a class="edit_form_link" name="' . $row->object_type . '" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			if (!isItemInPolicy($row->object_id, 'object')) {
				$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
				$checkbox = '<td><input type="checkbox" name="bulk_list[]" value="' . $row->object_id .'" /></td>';
			} else {
				$checkbox = '<td></td>';
			}
			$edit_status = '<td id="row_actions">' . $edit_status . '</td>';
		}
		
		$edit_name = $row->object_name;
		$netmask = ($row->object_type != 'host') ? "<td>$row->object_mask</td>" : null;
		$comments = nl2br($row->object_comment);
		
		echo <<<HTML
			<tr id="$row->object_id" name="$row->object_name"$disabled_class>
				$checkbox
				<td>$row->object_name</td>
				<td>$row->object_address</td>
				$netmask
				<td>$comments</td>
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
		$netmask_option = ($type == 'host') ? 'style="display: none;"' : null;

		$object_name_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', 'object_name');
		$object_address_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', 'object_address');
		$object_mask_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', 'object_mask');
		$object_type = buildSelect('object_type', 'object_type', enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', 'object_type'), $type, 1);
		
		$popup_title = $action == 'add' ? __('Add Object') : __('Edit Object');
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		$return_form = sprintf('<form name="manage" id="manage" method="post" action="?type=%s">
		%s
			<input type="hidden" name="action" value="%s" />
			<input type="hidden" name="object_id" value="%s" />
			<table class="form-table">
				<tr>
					<th width="33&#37;" scope="row"><label for="object_name">%s</label></th>
					<td width="67&#37;"><input name="object_name" id="object_name" type="text" value="%s" size="40" placeholder="http" maxlength="%s" /></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="object_type">%s</label></th>
					<td width="67&#37;">
						%s
					</td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="object_address">%s</label></th>
					<td width="67&#37;"><input name="object_address" id="object_address" type="text" value="%s" size="40" placeholder="127.0.0.1" maxlength="%s" /></td>
				</tr>
				<tr id="netmask_option" %s>
					<th width="33&#37;" scope="row"><label for="object_mask">%s</label></th>
					<td width="67&#37;"><input name="object_mask" id="object_mask" type="text" value="%s" size="40" placeholder="255.255.255.0" maxlength="%s" /></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="object_comment">%s</label></th>
					<td width="67&#37;"><textarea id="object_comment" name="object_comment" rows="4" cols="30">%s</textarea></td>
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
				$type, $popup_header, $action, $object_id,
				__('Object Name'), $object_name, $object_name_length,
				__('Object Type'), $object_type,
				__('Address'), $object_address, $object_address_length,
				$netmask_option, __('Netmask'), $object_mask, $object_mask_length,
				_('Comment'), $object_comment,
				$popup_footer
			);

		return $return_form;
	}
	
	
	function validatePost($post) {
		global $fmdb, $__FM_CONFIG;
		
		if (empty($post['object_name'])) return __('No object name defined.');
		if (empty($post['object_address'])) return __('No object address defined.');
		if ($post['object_type'] == 'network') {
			if (empty($post['object_mask'])) return __('No object netmask defined.');
		}
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', 'object_name');
		if ($field_length !== false && strlen($post['object_name']) > $field_length) return sprintf(dngettext($_SESSION['module'], 'Object name is too long (maximum %d character).', 'Object name is too long (maximum %d characters).', $field_length), $field_length);
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', $post['object_name'], 'object_', 'object_name', "AND object_type='{$post['object_type']}' AND object_id!={$post['object_id']}");
		if ($fmdb->num_rows) return __('This object name already exists.');
		
		/** Check address and mask */
		if (!verifyIPAddress($post['object_address'])) return __('Address is invalid.');
		if ($post['object_type'] == 'network') {
			if (!verifyIPAddress($post['object_mask'])) return __('Netmask is invalid.');
		}
		
		return $post;
	}
	
}

if (!isset($fm_module_objects))
	$fm_module_objects = new fm_module_objects();

?>
