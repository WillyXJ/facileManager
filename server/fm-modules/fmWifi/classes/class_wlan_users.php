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
 | fmWifi: Easily manage one or more access points                         |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmwifi/                            |
 +-------------------------------------------------------------------------+
*/

class fm_wifi_wlan_users {
	
	/**
	 * Displays the item list
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param array $result Record rows of all items
	 * @return null
	 */
	function rows($result, $page, $total_pages, $type = 'wlan_users') {
		global $fmdb;
		
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;
		
		if (currentUserCan('manage_' . $type, $_SESSION['module'])) {
			$bulk_actions_list = array(_('Enable'), _('Disable'), _('Delete'));
		}

		$fmdb->num_rows = $num_rows;

		$start = $_SESSION['user']['record_count'] * ($page - 1);
		echo displayPagination($page, $total_pages, @buildBulkActionMenu($bulk_actions_list));

		$table_info = array(
						'class' => 'display_results',
						'id' => 'table_edits',
						'name' => $type
					);

		if (is_array($bulk_actions_list)) {
			$title_array[] = array(
								'title' => '<input type="checkbox" class="tickall" onClick="toggle(this, \'bulk_list[]\')" />',
								'class' => 'header-tiny header-nosort'
							);
		}
		$title_array = array_merge((array) $title_array, array(_('Login'), __('MAC Address'), __('Associated WLANs'), _('Comment')));
		if (is_array($bulk_actions_list)) $title_array[] = array('title' => _('Actions'), 'class' => 'header-actions');

		echo displayTableHeader($table_info, $title_array);

		if ($result) {
			$y = 0;
			for ($x=$start; $x<$num_rows; $x++) {
				if ($y == $_SESSION['user']['record_count']) break;
				$this->displayRow($results[$x]);
				$y++;
			}
		}

		echo "</tbody>\n</table>\n";
		if (!$result) {
			printf('<p id="table_edits" class="noresult" name="%s">%s</p>', $type, __('There are no items.'));
		}
	}

	/**
	 * Adds the new object
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param array $post $_POST data
	 * @return boolean or string
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
//		echo '<pre>';print_r($post); exit;
		
		$query = "INSERT INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}wlan_users` (`account_id`, `wlan_user_login`, `wlan_user_password`, `wlan_user_comment`, `wlan_user_mac`, `wlan_ids`) 
				VALUES('{$_SESSION['user']['account_id']}', '{$post['wlan_user_login']}', '{$post['wlan_user_password']}', '{$post['wlan_user_comment']}', '{$post['wlan_user_mac']}', '{$post['wlan_ids']}')";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(_('Could not add the user because a database error occurred.'), 'sql');
		}
		
		$log_message = "Added WLAN User:\nName: {$post['wlan_user_login']}\nHardware Address: {$post['wlan_user_mac']}";
		$log_message .= "\nComment: {$post['wlan_user_comment']}";
		addLogEntry($log_message);
		
//		setBuildUpdateConfigFlag(getWLANServers($insert_id), 'yes', 'build');
		
		return true;
	}

	/**
	 * Updates the selected host
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param array $post $_POST data
	 * @return boolean or string
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
//		echo '<pre>';print_r($post);exit;
		
		$exclude = array('submit', 'action', 'user_id', 'type');

		$sql_edit = null;
		
		/** Loop through all posted keys and values to build SQL statement */
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . sanitize($data) . "', ";
			}
		}
		$sql = rtrim($sql_edit, ', ');
		
		/** Update the server */
		$old_name = getNameFromID($post['user_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'wlan_users', 'wlan_user_', 'wlan_user_id', 'wlan_user_login');
		$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}wlan_users` SET $sql WHERE `wlan_user_id`={$post['user_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(_('Could not update the user because a database error occurred.'), 'sql');
		}
		
		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		/** Server changed so configuration needs to be built */
//		setBuildUpdateConfigFlag(getServerSerial($post['server_id'], $_SESSION['module']), 'yes', 'build');
		
		/** Add entry to audit log */
		$log_message = "Updated WLAN user '$old_name' to:\nName: {$post['wlan_user_login']}\nHardware Address: {$post['wlan_user_mac']}";
		$log_message .= "\nComment: {$post['wlan_user_comment']}";
		return true;
	}
	
	/**
	 * Deletes the selected server
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param integer $id ID to delete
	 * @return boolean or string
	 */
	function delete($id, $server_serial_no = 0) {
		global $fmdb, $__FM_CONFIG;
		
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'wlan_users', 'wlan_user_', 'wlan_user_id', 'wlan_user_login');

		/** Delete item */
		if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'wlan_users', $id, 'wlan_user_', 'deleted', 'wlan_user_id') === false) {
			return formatError(__('This user could not be deleted because a database error occurred.'), 'sql');
		} else {
			setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
			addLogEntry(sprintf(__("WLAN User '%s' was deleted."), $tmp_name));
			return true;
		}
	}


	/**
	 * Displays the server entry table row
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param object $row Single data row from $results
	 * @return null
	 */
	function displayRow($row) {
		global $fmdb, $__FM_CONFIG;
		
		$class = ($row->wlan_user_status == 'disabled') ? 'disabled' : null;
		
		$edit_status = $edit_actions = $checkbox = null;
		
		if (currentUserCan('manage_hosts', $_SESSION['module'])) {
			$edit_status = '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a class="status_form_link" href="#" rel="';
			$edit_status .= ($row->wlan_user_status == 'active') ? 'disabled' : 'active';
			$edit_status .= '">';
			$edit_status .= ($row->wlan_user_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
			$edit_status .= '</a>';
			$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			$edit_status = '<td id="row_actions">' . $edit_status . '</td>';
			$checkbox = '<td><input type="checkbox" name="bulk_list[]" value="' . $row->wlan_user_id .'" /></td>';
		}
		
		$edit_status = $edit_actions . $edit_status;
		
		$associated_wlans = __('All WLANs');
		if ($row->wlan_ids) {
			$associated_wlans = null;
			foreach (explode(';', $row->wlan_ids) as $id) {
				$associated_wlans[] = getNameFromID($id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_', 'config_id', 'config_data');
			}
			$associated_wlans = join('; ', $associated_wlans);
		}
		
		if ($class) $class = 'class="' . $class . '"';
				
		echo <<<HTML
		<tr id="$row->wlan_user_id" name="$row->wlan_user_login" $class>
			$checkbox
			<td>$row->wlan_user_login</td>
			<td>$row->wlan_user_mac</td>
			<td>$associated_wlans</td>
			<td>$row->wlan_user_comment</td>
			$edit_status
		</tr>

HTML;
	}

	/**
	 * Displays the add/edit form
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param array $data Either $_POST data or returned SQL results
	 * @param string $action Add or edit
	 * @return string
	 */
	function printForm($data = '', $action = 'add', $type = 'host', $addl_vars = null) {
		global $fmdb, $__FM_CONFIG, $fm_wifi_wlans;
		
		include(ABSPATH . 'fm-modules/facileManager/classes/class_users.php');
		
		$assoc_wlans = $wlan_user_mac = $wlan_user_comment = null;
		$wlan_ids = 0;
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		if (isset($wlan_user_login)) {
			$data[0]->user_login = $wlan_user_login;
		}
		if (isset($wlan_user_id)) {
			$data[0]->user_id = $wlan_user_id;
		}
		
		$assoc_wlans = buildSelect('wlan_ids', 'wlan_ids', $fm_wifi_wlans->getWLANList('wpa2'), $wlan_ids, 1, null, true);
		
		$popup_title = ($action == 'add') ? _('Add User') : _('Edit User');
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		$return_form = sprintf('<form name="manage" id="manage" method="post" action="">
		%s
		%s
						<table class="form-table">
							<tr>
								<th width="33&#37;" scope="row"><label for="wlan_user_mac">%s</label></th>
								<td width="67&#37;" nowrap><input name="wlan_user_mac" id="wlan_user_mac" type="text" value="%s" maxlength="17" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="wlan_ids">%s</label></th>
								<td width="67&#37;" nowrap>%s</td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="wlan_user_comment">%s</label></th>
								<td width="67&#37;"><textarea id="wlan_user_comment" name="wlan_user_comment" rows="4" cols="30">%s</textarea></td>
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
				$popup_header,
				$fm_users->printUsersForm($data, $action, array('user_login', 'user_password' => $GLOBALS['PWD_STRENGTH']), 'wlan_users', null, null, null, false, 'embed'),
				__('MAC Address'), $wlan_user_mac,
				__('Associated WLANs'), $assoc_wlans,
				_('Comment'), $wlan_user_comment,
				$popup_footer
			);

		return $return_form;
	}
	
	/**
	 * Validates the user-submitted data (for add and edit)
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param array $post Posted data to validate
	 * @return array
	 */
	function validatePost($post) {
		global $__FM_CONFIG;

		$post['wlan_user_login'] = sanitize($post['user_login']);
		$post['wlan_user_password'] = sanitize($post['user_password']);
		$post['wlan_user_mac'] = sanitize($post['wlan_user_mac']);
		$post['wlan_user_comment'] = sanitize($post['wlan_user_comment']);
		$post['wlan_ids'] = (in_array('0', $post['wlan_ids'])) ? 0 : join(';', $post['wlan_ids']);

		if (empty($post['wlan_user_login'])) return __('No username is defined.');
		if ($post['action'] == 'add' && empty($post['wlan_user_password'])) return __('No password is defined.');
		if ($post['user_password'] != $post['cpassword']) return _('Passwords do not match.');
		if (empty($post['wlan_user_password'])) unset($post['wlan_user_password']);
		
		unset($post['user_login'], $post['user_password'], $post['cpassword']);
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'wlan_users', 'wlan_user_name');
		if ($field_length !== false && strlen($post['config_name']) > $field_length) return sprintf(dngettext($_SESSION['module'], 'Group name is too long (maximum %d character).', 'Host name is too long (maximum %d characters).', $field_length), $field_length);
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'wlan_users', $post['wlan_user_login'], 'wlan_user_', 'wlan_user_login');
		if ($fmdb->num_rows) return __('This user already exists.');
		
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'wlan_users', $post['wlan_user_mac'], 'wlan_user_', 'wlan_user_mac');
		if ($fmdb->num_rows) return __('This hardware address already exists.');
		
		/** Valid MAC address? */
		if (version_compare(PHP_VERSION, '5.5.0', '>=') && !verifySimpleVariable($post['wlan_user_mac'], FILTER_VALIDATE_MAC)) {
			return __('The hardware address is invalid.');
		}
		
		return $post;
	}
	
	
}

if (!isset($fm_wifi_wlan_users))
	$fm_wifi_wlan_users = new fm_wifi_wlan_users();

?>
