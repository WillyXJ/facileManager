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

class fm_dns_controls {
	
	/**
	 * Displays the control list
	 */
	function rows($result, $type, $page, $total_pages) {
		global $fmdb;
		
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;

		$start = $_SESSION['user']['record_count'] * ($page - 1);

		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$bulk_actions_list = array(_('Enable'), _('Disable'), _('Delete'));
		}
		echo displayPagination($page, $total_pages, buildBulkActionMenu($bulk_actions_list));

		$table_info = array(
						'class' => 'display_results sortable',
						'id' => 'table_edits',
						'name' => 'controls'
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
		$title_array = array_merge((array) $title_array, array(
			array('title' => __('IP Address'), 'rel' => 'control_ip'),
			array('title' => __('Port'), 'rel' => 'control_port'),
			array('title' => __('Address List'), 'class' => 'header-nosort')
		));
		if ($type == 'controls') $title_array[] = array('title' => __('Keys'), 'class' => 'header-nosort');
		$title_array[] = array('title' => _('Comment'), 'class' => 'header-nosort');
		if (currentUserCan('manage_servers', $_SESSION['module'])) $title_array[] = array('title' => __('Actions'), 'class' => 'header-actions header-nosort');

		echo '<div class="overflow-container">';
		echo displayTableHeader($table_info, $title_array);
		
		if ($result) {
			$y = 0;
			for ($x=$start; $x<$num_rows; $x++) {
				if ($y == $_SESSION['user']['record_count']) break;
				$this->displayRow($results[$x], $type);
				$y++;
			}
		}
			
		echo "</tbody>\n</table>\n";
		if (!$result) {
			$message = $type == 'controls' ? __('There are no controls.') : __('There are no statistics channels.');
			printf('<p id="table_edits" class="noresult" name="controls">%s</p>', $message);
		}
	}

	/**
	 * Adds the new control
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG, $global_form_field_excludes, $fm_dns_acls, $fm_dns_keys;
		
		if (!class_exists('fm_dns_acls')) {
			include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_acls.php');
		}
		if (!class_exists('fm_dns_keys')) {
			include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_keys.php');
		}

		/** Validate post */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}controls`";
		$sql_fields = '(';
		$sql_values = '';
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		
		$exclude = array_merge($global_form_field_excludes, array('server_id'));
		$logging_exclude = array_diff(array_keys($post), $exclude, array('control_id', 'action', 'account_id', 'tab-group-1', 'sub_type'));
		$log_message = sprintf(__('Added %s with the following details'), $post['control_type']) . ":\n";

		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_fields .= $key . ', ';
				$sql_values .= "'$data', ";
				if (in_array($key, $logging_exclude)) {
					if ($key == 'server_serial_no') {
						$log_message .= formatLogKeyData('', 'server', getServerName($data));
					} elseif ($key == 'control_addresses') {
						$log_message .= formatLogKeyData('control_', $key, (strpos($data, 'acl_') !== false) ? $fm_dns_acls->parseACL($data) : $data);
					} elseif ($key == 'control_keys') {
						$log_message .= formatLogKeyData('control_', $key, $fm_dns_keys->parseKey($data, '; '));
					} elseif (strlen($data)) {
						$log_message .= formatLogKeyData('control_', $key, $data);
					}
				}
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the control because a database error occurred.'), 'sql');
		}

		setBuildUpdateConfigFlag($post['server_serial_no'], 'yes', 'build');

		addLogEntry($log_message);
		return true;
	}

	/**
	 * Updates the selected control
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG, $global_form_field_excludes, $fm_dns_acls, $fm_dns_keys;
		
		if (!class_exists('fm_dns_acls')) {
			include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_acls.php');
		}
		if (!class_exists('fm_dns_keys')) {
			include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_keys.php');
		}
		
		/** Validate post */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		/** Cleans up control_addresses for future parsing **/
//		$post['control_addresses'] = verifyAndCleanAddresses($post['control_addresses']);
//		if ($post['control_addresses'] === false) return 'Invalid address(es) specified.';
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		
		$exclude = array_merge($global_form_field_excludes, array('server_id'));
		$logging_exclude = array_diff(array_keys($post), $exclude, array('control_id', 'action', 'account_id', 'tab-group-1', 'sub_type'));
		$log_message = sprintf(__('Updated %s (%s) to the following details'), $post['control_type'], $post['control_ip']) . ":\n";

		$sql_edit = '';
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . $data . "', ";
				if (in_array($key, $logging_exclude)) {
					if ($key == 'server_serial_no') {
						$log_message .= formatLogKeyData('', 'server', getServerName($data));
					} elseif ($key == 'control_addresses') {
						$log_message .= formatLogKeyData('control_', $key, (strpos($data, 'acl_') !== false) ? $fm_dns_acls->parseACL($data) : $data);
					} elseif ($key == 'control_keys') {
						$log_message .= formatLogKeyData('control_', $key, $fm_dns_keys->parseKey($data, '; '));
					} elseif (strlen($data)) {
						$log_message .= formatLogKeyData('control_', $key, $data);
					}
				}
			}
		}
		$sql = rtrim($sql_edit, ', ');
		
		// Update the control
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}controls` SET $sql WHERE `control_id`={$post['control_id']}";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not update the control because a database error occurred.'), 'sql');
		}

		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		setBuildUpdateConfigFlag($post['server_serial_no'], 'yes', 'build');

		addLogEntry($log_message);
		return true;
	}
	
	
	/**
	 * Deletes the selected control
	 */
	function delete($id, $server_serial_no, $type) {
		global $fmdb, $__FM_CONFIG;
		
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'controls', 'control_', 'control_id', 'control_ip');
		if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'controls', $id, 'control_', 'deleted', 'control_id') === false) {
			return formatError(__('This control could not be deleted because a database error occurred.'), 'sql');
		} else {
			setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
			$log_message = sprintf(__('Deleted a %s'), $type) . ":\n";
			$log_message .= formatLogKeyData('', 'Address', $tmp_name);
			$log_message .= formatLogKeyData('_serial_no', 'server_serial_no', getServerName($server_serial_no));
			addLogEntry($log_message);
			return true;
		}
	}


	function displayRow($row, $type) {
		global $__FM_CONFIG, $fm_dns_acls, $fm_dns_keys;
		
		if (!class_exists('fm_dns_acls')) {
			include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_acls.php');
		}
		
		if (!class_exists('fm_dns_keys') && $type == 'controls') {
			include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_keys.php');
		}
		
		$disabled_class = ($row->control_status == 'disabled') ? ' class="disabled"' : null;
		$checkbox = null;
		
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$edit_status = '<td class="column-actions">';
			$edit_status .= '<a class="edit_form_link" name="' . $type . '" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a class="status_form_link" href="#" rel="';
			$edit_status .= ($row->control_status == 'active') ? 'disabled' : 'active';
			$edit_status .= '">';
			$edit_status .= ($row->control_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
			$edit_status .= '</a>';
			$edit_status .= '<a href="#" name="' . $type . '" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			$edit_status .= '</td>';
			$checkbox = '<input type="checkbox" name="bulk_list[]" value="' . $row->control_id .'" />';
		} else {
			$edit_status = null;
		}
		
		$control_port = !empty($row->control_port) ? $row->control_port : 953;
		$control_addresses = strpos($row->control_addresses, 'acl_') !== false ? $fm_dns_acls->parseACL($row->control_addresses) : $row->control_addresses;
		$control_keys = ($type == 'controls') ? '<td>' . $fm_dns_keys->parseKey($row->control_keys, '; ') . '</td>' : null;
		
		$comments = nl2br($row->control_comment);

		echo <<<HTML
		<tr id="$row->control_id"$disabled_class>
			<td>$checkbox</td>
			<td>$row->control_ip</td>
			<td>$control_port</td>
			<td>$control_addresses</td>
			$control_keys
			<td>$comments</td>
			$edit_status
		</tr>
HTML;
	}

	/**
	 * Displays the form to add new control
	 */
	function printForm($data = '', $action = 'add', $type = 'controls') {
		global $__FM_CONFIG, $fm_dns_acls;
		
		$control_id = 0;
		$control_ip = $control_addresses = $control_comment = null;
		$control_port = null;
		$control_keys = '';
		$server_serial_no = (isset($_REQUEST['request_uri']['server_serial_no']) && (intval($_REQUEST['request_uri']['server_serial_no']) > 0 || $_REQUEST['request_uri']['server_serial_no'][0] == 'g')) ? sanitize($_REQUEST['request_uri']['server_serial_no']) : 0;
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		$control_addresses = str_replace(';', "\n", rtrim(str_replace(' ', '', (string) $control_addresses), ';'));
		if ($type == 'controls') {
			$control_keys = buildSelect('control_keys', 'control_keys', availableItems('key', 'nonempty', 'AND key_type="tsig"'), explode(';', $control_keys), 1, null, true, null, null, __('Select one or more keys'));
			$control_key_form = sprintf('<tr>
					<th width="33&#37;" scope="row"><label for="control_keys">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>', __('Keys'), $control_keys);
		} else {
			$control_key_form = null;
		}

		$available_acls = $fm_dns_acls->buildACLJSON($control_addresses, $server_serial_no);
		
		$popup_title = $action == 'add' ? __('Add Control') : __('Edit Control');
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		$return_form = sprintf('
		%s
		<form name="manage" id="manage">
			<input type="hidden" name="page" value="controls" />
			<input type="hidden" name="action" value="%s" />
			<input type="hidden" name="control_id" value="%d" />
			<input type="hidden" name="server_serial_no" value="%s" />
			<input type="hidden" name="control_keys" value="" />
			<input type="hidden" name="control_type" value="%s" />
			<table class="form-table">
				<tr>
					<th width="33&#37;" scope="row"><label for="control_ip">%s</label></th>
					<td width="67&#37;"><input name="control_ip" id="control_ip" type="text" value="%s" size="40" placeholder="127.0.0.1" /></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="control_port">%s</label></th>
					<td width="67&#37;"><input name="control_port" id="control_port" type="text" value="%s" size="40" placeholder="953" /></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="control_predefined">%s</label></th>
					<td width="67&#37;">
						<input type="hidden" name="control_addresses" id="address_match_element" data-placeholder="%s" value="%s" class="required" /><br />
						( address_match_element )
					</td>
				</tr>
				%s
				<tr>
					<th width="33&#37;" scope="row"><label for="control_comment">%s</label></th>
					<td width="67&#37;"><textarea id="control_comment" name="control_comment" rows="4" cols="30">%s</textarea></td>
				</tr>
			</table>
		%s
		</form>
		<script>
			$(document).ready(function() {
				$("#manage select").select2({
					width: "200px",
					minimumResultsForSearch: 10,
					allowClear: true
				});
				$("#address_match_element").select2({
					createSearchChoice:function(term, data) { 
						if ($(data).filter(function() { 
							return this.text.localeCompare(term)===0; 
						}).length===0) 
						{return {id:term, text:term};} 
					},
					multiple: true,
					width: "200px",
					tokenSeparators: [",", " ", ";"],
					data: %s
				});
			});
		</script>',
				$popup_header,
				$action, $control_id, $server_serial_no, $type,
				__('IP Address'), $control_ip,
				__('Port'), $control_port,
				__('Allowed Address List'), __('Define allowed hosts'), $control_addresses,
				$control_key_form,
				_('Comment'), $control_comment,
				$popup_footer, $available_acls
			);

		return $return_form;
	}
	
	
	function validatePost($post) {
		global $fmdb, $__FM_CONFIG;

		if (!$post['control_id']) unset($post['control_id']);
		
		if (is_array($post['control_keys'])) $post['control_keys'] = join(',', $post['control_keys']);
		
		if (!empty($post['control_ip']) && $post['control_ip'] != '*') {
			if (!verifyIPAddress($post['control_ip'])) return sprintf(__('%s is not a valid IP address.'), $post['control_ip']);
		} else $post['control_ip'] = '*';
		
		if (empty($post['control_addresses'])) {
			return __('Allowed addresses not defined.');
		}
		
		if (!empty($post['control_port'])) {
			if (!verifyNumber($post['control_port'], 0, 65535)) return sprintf(__('%d is not a valid port number.'), $post['control_port']);
		} else $post['control_port'] = 953;
		
		return $post;
	}
	
	
}

if (!isset($fm_dns_controls))
	$fm_dns_controls = new fm_dns_controls();
