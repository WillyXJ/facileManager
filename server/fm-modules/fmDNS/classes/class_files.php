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

class fm_dns_files {
	
	/**
	 * Displays the file list
	 *
	 * @since 6.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param array $result Database query array
	 * @param integer $page Page number
	 * @param integer $total_pages Total number of pages
	 * @return none
	 */
	function rows($result, $page, $total_pages) {
		global $fmdb;
		
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;

		$start = $_SESSION['user']['record_count'] * ($page - 1);

		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$bulk_actions_list = array(_('Enable'), _('Disable'), _('Delete'));
		}
		echo displayPagination($page, $total_pages, buildBulkActionMenu($bulk_actions_list));

		$table_info = array(
						'class' => 'display_results',
						'id' => 'table_edits',
						'name' => 'files'
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
		$title_array = array_merge((array) $title_array, array(array('title' => __('File Name')), array('title' => __('File Location')), array('title' => _('Comment'), 'class' => 'header-nosort')));
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$title_array[] = array('title' => __('Actions'), 'class' => 'header-actions header-nosort');
			if ($num_rows > 1) $table_info['class'] .= ' grab1';
		}

		echo displayTableHeader($table_info, $title_array);
		
		if ($result) {
			$y = 0;
			for ($x=$start; $x<$num_rows; $x++) {
				if ($y == $_SESSION['user']['record_count']) break;
				$this->displayRow($results[$x], $num_rows);
				$y++;
			}
		}
			
		echo "</tbody>\n</table>\n";
		if (!$result) {
			printf('<p id="table_edits" class="noresult" name="files">%s</p>', __('There are no files.'));
		}
	}

	/**
	 * Adds the new item
	 *
	 * @since 6.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param array $post Posted array
	 * @return string|array|boolean
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate post */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		extract($post, EXTR_SKIP);
		
		$log_message = __("Added a file with the following") . ":\n";
		$logging_excluded_fields = array('page', 'action', 'file_id', 'item_type');
		foreach ($post as $key => $data) {
			if (in_array($key, $logging_excluded_fields)) continue;
			if ($key == 'server_serial_no') {
				$log_message .= formatLogKeyData('', 'server', getServerName($data));
			} else {
				$log_message .= formatLogKeyData('file_', $key, $data);
			}
		}

		$query = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}files` (`account_id`, `server_serial_no`, `file_location`, `file_name`, `file_contents`, `file_comment`) VALUES('{$_SESSION['user']['account_id']}', '$server_serial_no', '$file_location', '$file_name', '$file_contents', '$file_comment')";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the item because a database error occurred.'), 'sql');
		}

		setBuildUpdateConfigFlag($post['server_serial_no'], 'yes', 'build');

		addLogEntry($log_message);
		return true;
	}

	/**
	 * Updates the selected item
	 *
	 * @since 6.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param array $post Posted array
	 * @return string|array|boolean
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate post */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		$include = array('server_serial_no', 'file_location', 'file_name', 'file_contents', 'file_comment');

		$sql_edit = '';
		$old_name = getNameFromID($post['file_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'files', 'file_', 'file_id', 'file_name');
		$log_message = sprintf(__("Updated file '%s' to the following"), $old_name) . ":\n";
		
		foreach ($post as $key => $data) {
			if (in_array($key, $include)) {
				$sql_edit .= $key . "='" . $data . "', ";
				if ($key == 'server_serial_no') {
					$log_message .= formatLogKeyData('', 'server', getServerName($data));
				} else {
					$log_message .= formatLogKeyData('file_', $key, $data);
				}
			}
		}
		$sql = rtrim($sql_edit, ', ');
		
		/** Update the file */
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}files` SET $sql WHERE `file_id`={$post['file_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not update the item because a database error occurred.'), 'sql');
		}

		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		setBuildUpdateConfigFlag($post['server_serial_no'], 'yes', 'build');

		addLogEntry($log_message);
		return true;
	}
	
	
	/**
	 * Deletes the selected item
	 *
	 * @since 6.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param integer $id Item ID
	 * @param integer $server_serial_no Server Serial Number
	 * @return string|array|boolean
	 */
	function delete($id, $server_serial_no) {
		global $fmdb, $__FM_CONFIG;
		
		/** Are there any corresponding configs? */
		if (getConfigAssoc($id, 'file')) {
			return formatError(__('This item is still being referenced and could not be deleted.'), 'sql');
		}

		/** Delete file */
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'files', 'file_', 'file_id', 'file_name');
		if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'files', $id, 'file_', 'deleted', 'file_id') === false) {
			return formatError(__('This item could not be deleted because a database error occurred.'), 'sql');
		} else {
			$log_message = __('Deleted a file') . ":\n";
			$log_message .= formatLogKeyData('', 'Name', $tmp_name);
			$log_message .= formatLogKeyData('_serial_no', 'server_serial_no', getServerName($server_serial_no));
			addLogEntry($log_message);
			return true;
		}
	}


	/**
	 * Displays the result row
	 *
	 * @since 6.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param object $row Row array
	 * @param integer $num_rows Number of rows
	 */
	function displayRow($row, $num_rows) {
		global $__FM_CONFIG;
		
		$disabled_class = ($row->file_status == 'disabled') ? ' class="disabled"' : null;
		$checkbox = null;
		
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$edit_status = '<td id="row_actions">';
			$edit_status .= '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			if (!getConfigAssoc($row->file_id, 'file')) {
				$edit_status .= '<a class="status_form_link" href="#" rel="';
				$edit_status .= ($row->file_status == 'active') ? 'disabled' : 'active';
				$edit_status .= '">';
				$edit_status .= ($row->file_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
				$edit_status .= '</a>';
				$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
				$checkbox = '<input type="checkbox" name="bulk_list[]" value="' . $row->file_id .'" />';
			}
			$edit_status .= '</td>';
		} else {
			$edit_status = null;
		}
		
		$comments = nl2br($row->file_comment);

		echo <<<HTML
		<tr id="$row->file_id" name="$row->file_name"$disabled_class>
			<td>$checkbox</td>
			<td>$row->file_name</td>
			<td>$row->file_location</td>
			<td>$comments</td>
			$edit_status
		</tr>
HTML;
	}

	/**
	 * Displays the form to add/edit items
	 *
	 * @since 6.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param array $data Posted array
	 * @param string $action Add or Edit
	 * @return string
	 */
	function printForm($data = '', $action = 'add') {
		global $__FM_CONFIG;
		
		$file_id = 0;
		$file_location = $file_name = $file_contents = $file_comment = null;
		$server_serial_no = (isset($_REQUEST['request_uri']['server_serial_no']) && (intval($_REQUEST['request_uri']['server_serial_no']) > 0 || $_REQUEST['request_uri']['server_serial_no'][0] == 'g')) ? sanitize($_REQUEST['request_uri']['server_serial_no']) : 0;
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($data))
				extract($data);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		/** Strip file extension */
		$file_name = @pathinfo($file_name)['filename'];

		/** Get field length */
		$file_name_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'files', 'file_name');

		$file_location = buildSelect('file_location', 'file_location', enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'files', 'file_location'), $file_location);
		
		$popup_title = $action == 'add' ? __('Add File') : __('Edit File');
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		$return_form = sprintf('
		%s
		<form name="manage" id="manage">
			<input type="hidden" name="page" id="page" value="files" />
			<input type="hidden" name="action" id="action" value="%s" />
			<input type="hidden" name="file_id" id="file_id" value="%d" />
			<input type="hidden" name="server_serial_no" value="%s" />
			<table class="form-table">
				<tr>
					<th width="33&#37;" scope="row"><label for="file_location">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="file_name">%s</label> <a href="#" class="tooltip-top" data-tooltip="%s"><i class="fa fa-question-circle"></i></a></th>
					<td width="67&#37;"><input name="file_name" id="file_name" type="text" value="%s" size="40" placeholder="custom-file" maxlength="%d" class="required" /></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="file_contents">%s</label></th>
					<td width="67&#37;"><textarea id="file_contents" name="file_contents" rows="10" cols="40">%s</textarea></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="file_comment">%s</label></th>
					<td width="67&#37;"><textarea id="file_comment" name="file_comment" rows="4" cols="40">%s</textarea></td>
				</tr>
			</table>
		%s
		</form>
		<script>
			$(document).ready(function() {
				$("#manage select").select2({
					minimumResultsForSearch: 10,
					allowClear: true,
					width: "200px"
				});
			});
		</script>',
				$popup_header,
				$action, $file_id, $server_serial_no,
				__('Location'), $file_location,
				__('Name'), __('The extension will be automatically appended based on file location.'), $file_name, $file_name_length,
				__('Contents'), $file_contents,
				_('Comment'), $file_comment, $popup_footer
			);

		return $return_form;
	}

	/**
	 * Validates the submitted form
	 *
	 * @since 6.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param array $post Posted array
	 * @return array|string
	 */
	function validatePost($post) {
		global $fmdb, $__FM_CONFIG;
		
		if (empty($post['files_id']) || !$post['files_id']) unset($post['files_id']);
		else $post['files_id'] = intval($post['files_id']);

		$pathinfo = pathinfo($post['file_name']);
		if (isset($pathinfo['extension'])) $post['file_name'] = $pathinfo['filename'];
		$post['file_name'] = trim(basename(sanitize($post['file_name'], '-')), '.');
		if (empty($post['file_name'])) return __('No file name defined.');

		/** Set the extension */
		$post['file_name'] .= ($post['file_location'] == '$ZONES') ? '.hosts' : '.conf';

		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'files', 'file_name');
		if ($field_length !== false && strlen($post['file_name']) > $field_length) return sprintf(__('File name is too long (maximum %s characters).'), $field_length);
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'files', $post['file_name'], 'file_', 'file_name', "AND file_location='{$post['file_location']}' AND server_serial_no='{$post['server_serial_no']}'");
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			if ($result[0]->file_id != $post['file_id']) return __('This file already exists.');
		}
		
		$post['file_comment'] = sanitize(trim($post['file_comment']));
		
		return $post;
	}


	/**
	 * Builds a JSON array
	 *
	 * @since 6.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param string $saved_item
	 * @param integer $server_serial_no
	 * @param integer $domain_id
	 * @return array|string
	 */
	function buildJSON($saved_item, $server_serial_no = 0, $domain_id = 0) {
		global $fmdb, $__FM_CONFIG, $fm_module_servers;

		$list = array();

		if (!isset($fm_module_servers)) include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_servers.php');
		$server_group_ids = $fm_module_servers->getServerGroupIDs(getNameFromID($server_serial_no, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_id'));

		$group_ids_sql = ($server_group_ids) ? ', "g_' . implode('", "g_', $server_group_ids) . '"' : null;
		$file_location = ($domain_id) ? '$ZONES' : '$ROOT';

		basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'files', 'file_name', 'file_', 'AND server_serial_no IN ("0", "' . $server_serial_no . '"' . $group_ids_sql . ') AND file_location="' . $file_location . '" AND file_status="active"');
		for ($j=0; $j<$fmdb->num_rows; $j++) {
			$list[$j]['id'] = 'file_' . $fmdb->last_result[$j]->file_id;
			$list[$j]['text'] = $fmdb->last_result[$j]->file_name;
		}
		$temp = array();
		foreach ($list as $temp_array) {
			$temp[] = $temp_array['id'];
		}

		$i = count($list);
		if ($saved_item) {
			if (array_search($saved_item, $temp) === false) {
				$list[$i]['id'] = $saved_item;
				$list[$i]['text'] = $saved_item;
				$i++;
			}
		}
		unset($temp);

		return json_encode($list);
	}

}

if (!isset($fm_dns_files))
	$fm_dns_files = new fm_dns_files();
