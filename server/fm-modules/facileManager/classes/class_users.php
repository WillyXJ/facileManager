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
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/                                           |
 +-------------------------------------------------------------------------+
*/

class fm_users {
	
	/**
	 * Displays the user list
	 *
	 * @since 1.0
	 * @package facileManager
	 */
	function rows($result, $type, $page, $total_pages) {
		global $fmdb;
		
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;

		$start = $_SESSION['user']['record_count'] * ($page - 1);
		echo displayPagination($page, $total_pages);

		$table_info = array(
						'class' => 'display_results sortable',
						'id' => 'table_edits',
						'name' => 'users'
					);
		$title_array[] = array('class' => 'header-tiny header-nosort');

		if ($type == 'users') {
			$title_array[] = array('title' => _('Login'), 'rel' => 'user_login');
			array_push($title_array,
					array('title' => _('Last Session Date'), 'rel' => 'user_last_login'),
					array('title' => _('Last Session Host'), 'rel' => 'user_ipaddr'),
					array('title' => _('Authenticate With'), 'rel' => 'user_auth_type'),
					array('title' => _('Comment'), 'class' => 'header-nosort'));
		} elseif ($type == 'groups') {
			array_push($title_array,
				array('title' => _('Group Name'), 'rel' => 'group_name'),
				array('title' => _('Group Members'), 'class' => 'header-nosort'),
				array('title' => _('Comment'), 'class' => 'header-nosort'));
		} elseif ($type == 'keys') {
			array_push($title_array,
				array('title' => _('Key'), 'rel' => 'key_token'));
			if (currentUserCan('manage_users')) {
				array_push($title_array,
					array('title' => _('User'), 'rel' => 'user_id'));	
			}
		}
		$title_array[] = array('title' => _('Actions'), 'class' => 'header-actions header-nosort');

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
			if ($type == 'users') {
				$message = _('There are no users.');
			} elseif ($type == 'groups') {
				$message = _('There are no groups.');
			} elseif ($type == 'keys') {
				$message = _('There are no keys.');
			}
			printf('<p id="table_edits" class="noresult" name="users">%s</p>', $message);
		}
	}

	/**
	 * Adds the new user
	 *
	 * @since 1.0
	 * @package facileManager
	 */
	function addUser($data) {
		global $__FM_CONFIG, $fmdb, $global_form_field_excludes, $fm_name, $fm_login;
		
		if (isset($data['group_id'])) {
			return $this->addGroup($data);
		}
		
		if (!isset($data['user_template_only'])) $data['user_template_only'] = 'no';
		extract($data, EXTR_SKIP);
		
		$log_message = "Added user:\n";
		$exclude = array_merge($global_form_field_excludes, array('user_id', 'cpassword', 'user_password', 'user_caps', 'process_user_caps', 'type'));

		foreach ($data as $key => $val) {
			if (!in_array($key, $exclude)) {
				if ($key == 'user_auth_type') $val = $__FM_CONFIG['options']['auth_method'][$val][0];
				if ($key == 'user_group') $val = $this->getName('group', $val);
				$log_message .= formatLogKeyData('user_', $key, $val);
			}
		}

		/** Template user? */
		if (isset($user_template_only) && $user_template_only == 'yes') {
			$user_template_only = 'yes';
			$user_status = 'disabled';
			$user_auth_type = 0;
		} else {
			$user_template_only = 'no';
			$user_status = 'active';
			$user_auth_type = isset($user_auth_type) ? $user_auth_type : 1;
		}

		$ret = $this->validateFormField('user_login', $user_login);
		if ($ret !== true) return $ret;
		if ($user_auth_type == 2) {
			$user_password = null;
		} elseif (isset($user_template_only) && $user_template_only == 'no') {
			$ret = $this->validateFormField('user_password', $user_password, $data);
			if ($ret !== true) return $ret;
		}
		$ret = $this->validateFormField('user_email', $user_email, $data);
		if ($ret !== true) return $ret;
		
		/** Check name field length */
		$field_length = getColumnLength('fm_users', 'user_login');
		if ($field_length !== false && strlen($user_login) > $field_length) return sprintf(_('Username is too long (maximum %d characters).'), $field_length);
		
		/** Force password change? */
		$user_force_pwd_change = (isset($user_force_pwd_change) && $user_force_pwd_change == 'yes') ? 'yes' : 'no';

		/** Does the record already exist for this account? */
		$query = "SELECT * FROM `fm_users` WHERE `user_status`!='deleted' AND `user_login`='$user_login'";
		$fmdb->get_results($query);
		if ($fmdb->num_rows) return _('This user already exists.');
		
		if ($user_group) {
			$user_caps = '';
		} else {
			$user_group = 0;
		}
		
		/** Process user permissions */
		if (isset($user_caps[$fm_name])) {
			if (array_key_exists('do_everything', $user_caps[$fm_name])) {
				$user_caps = array($fm_name => array('do_everything' => 1));
			}
		}
		if (isset($user_caps)) {
			$log_message .= formatLogKeyData('', 'permissions', $this->getFriendlyCaps($user_caps));
		}

		if (!isset($user_default_module)) $user_default_module = '';
		
		$query = "INSERT INTO `fm_users` (`account_id`, `user_login`, `user_password`, `user_comment`, `user_email`, `user_group`, `user_force_pwd_change`, `user_default_module`, `user_caps`, `user_template_only`, `user_status`, `user_auth_type`) 
				VALUES('{$_SESSION['user']['account_id']}', '$user_login', '" . password_hash($user_password, PASSWORD_DEFAULT) . "', '$user_comment', '$user_email', '$user_group', '$user_force_pwd_change', '$user_default_module', '" . serialize($user_caps) . "', '$user_template_only', '$user_status', $user_auth_type)";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(_('Could not add the user because a database error occurred.'), 'sql');
		}

		/** Process forced password change */
		if ($user_force_pwd_change == 'yes') $fm_login->processUserPwdResetForm($user_login, 'no mail');
		
		$log_message = sprintf("Added user:\n", $user_login);
		$exclude = array('submit', 'action', 'user_id', 'cpassword', 'user_password', 'user_caps', 'is_ajax', 'process_user_caps', 'type');

		foreach ($data as $key => $data) {
			if (!in_array($key, $exclude)) {
				$log_message .= formatLogKeyData('user_', $key, $data);
			}
		}

		addLogEntry($log_message, $fm_name);
		return true;
	}

	/**
	 * Adds the new group
	 *
	 * @since 2.1
	 * @package facileManager
	 */
	function addGroup($data) {
		global $fmdb, $global_form_field_excludes, $fm_name, $fm_login;
		
		extract($data, EXTR_SKIP);
		
		$log_message = "Added user group:\n";
		$exclude = array_merge($global_form_field_excludes, array('group_id', 'user_caps', 'process_user_caps', 'type', 'group_users'));

		foreach ($data as $key => $val) {
			if (!in_array($key, $exclude)) {
				$log_message .= formatLogKeyData('group_', $key, $val);
			}
		}

		if (empty($group_name)) return _('No group name defined.');
		
		/** Check name field length */
		$field_length = getColumnLength('fm_groups', 'group_name');
		if ($field_length !== false && strlen($group_name) > $field_length) return sprintf(_('Group name is too long (maximum %d characters).'), $field_length);
		
		/** Does the record already exist for this account? */
		$query = "SELECT * FROM `fm_groups` WHERE `group_status`!='deleted' AND `group_name`='$group_name'";
		$fmdb->get_results($query);
		if ($fmdb->num_rows) return _('This group already exists.');
		
		/** Process user permissions */
		if (isset($user_caps[$fm_name])) {
			if (array_key_exists('do_everything', $user_caps[$fm_name])) {
				$user_caps = array($fm_name => array('do_everything' => 1));
			}
		}
		if (isset($user_caps)) {
			$log_message .= formatLogKeyData('', 'permissions', $this->getFriendlyCaps($user_caps));
		}
		
		$query = "INSERT INTO `fm_groups` (`account_id`, `group_name`, `group_caps`, `group_comment`, `group_status`) 
				VALUES('{$_SESSION['user']['account_id']}', '$group_name', '" . serialize($user_caps) . "', '$group_comment', 'active')";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(_('Could not add the group because a database error occurred.'), 'sql');
		}

		if (isset($group_users) && is_array($group_users)) {
			$query = "UPDATE `fm_users` SET `user_group`='{$fmdb->insert_id}', `user_caps`=NULL WHERE `user_id` IN ('" . join("','", $group_users) . "')";
			if (!$fmdb->query($query)) {
				$addl_text = ($fmdb->last_error) ? '<br />' . $fmdb->last_error : null;
				return formatError(_('Could not associate the users with the group.'), 'sql');
			}
			foreach ((array) $group_users as $id) {
				$group_members[] = $this->getName('user', $id);
			}
		} else {
			$group_members[] = _('None');
		}
		$log_message .= formatLogKeyData('group_', 'group_users', join(', ', (array) $group_members));

		addLogEntry($log_message, $fm_name);
		return true;
	}

	/**
	 * Updates the selected user
	 *
	 * @since 1.0
	 * @package facileManager
	 */
	function editUser($post) {
		global $__FM_CONFIG, $fmdb, $global_form_field_excludes, $fm_name, $fm_login;

		if (isset($post['group_id'])) {
			return $this->editGroup($post);
		}
		
		/** Template user? */
		if (isset($post['user_template_only']) && $post['user_template_only'] == 'yes') {
			$post['user_template_only'] = 'yes';
			$post['user_auth_type'] = 0;
			$post['user_status'] = 'disabled';
		} else {
			$post['user_template_only'] = 'no';
			$post['user_auth_type'] = getNameFromID($post['user_id'], 'fm_users', 'user_', 'user_id', 'user_auth_type');
			if (!$post['user_auth_type']) $post['user_auth_type'] = 1;
		}

		$post['user_id'] = (!isset($post['user_id']) || !$post['user_id']) ? $_SESSION['user']['id'] : intval($post['user_id']);

		/** Authorized to update users? */
		if ((!currentUserCan('manage_users') && ($_SESSION['user']['id'] != $post['user_id'] || isset($post['user_caps']))) 
			|| ($_SESSION['user']['id'] != $post['user_id'] && $post['user_id'] == getDefaultAdminID()) 
			|| isset($post['user_login'])
			|| (isset($post['user_caps']) && $_SESSION['user']['id'] == $post['user_id'] && $_SESSION['user']['id'] == getDefaultAdminID())) {
				return _('You do not have permission to make these changes.');
		}

		$user_login = getNameFromID($post['user_id'], 'fm_users', 'user_', 'user_id', 'user_login');
		$log_message = sprintf("Updated user '%s':\n", $user_login);

		$ret = $this->validateFormField('user_email', $post['user_email'], $post);
		if ($ret !== true) return $ret;
		
		if (!empty($post['user_password'])) {
			if (empty($post['cpassword']) || $post['user_password'] != $post['cpassword']) return _('Passwords do not match.');
			if (password_verify($post['user_password'], getNameFromID($post['user_id'], 'fm_users', 'user_', 'user_id', 'user_password'))) return _('Password is not changed.');
			$ret = $this->validateFormField('user_password', $post['user_password'], $post);
			if ($ret !== true) return $ret;

			$sql_pwd = "`user_password`='" . password_hash($post['user_password'], PASSWORD_DEFAULT) . "',";
			$log_message .= formatLogKeyData('user_', 'Password', 'Changed');
		} else $sql_pwd = null;
		
		$sql_edit = '';
		
		$exclude = array_merge($global_form_field_excludes, array('user_id', 'cpassword', 'user_password', 'user_caps', 'process_user_caps', 'type'));

		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . $data . "', ";
				if ($key == 'user_auth_type') $data = $__FM_CONFIG['options']['auth_method'][$data][0];
				if ($key == 'user_group') $data = $this->getName('group', $data);
				$log_message .= formatLogKeyData('user_', $key, $data);
			}
		}
		$sql = rtrim($sql_edit . $sql_pwd, ', ');
		
		/** Process user permissions */
		if (isset($post['process_user_caps']) && (!isset($post['user_caps']) || $post['user_group'])) $post['user_caps'] = array();
		
		if (isset($post['user_caps'][$fm_name])) {
			if (array_key_exists('do_everything', $post['user_caps'][$fm_name])) {
				$post['user_caps'] = array($fm_name => array('do_everything' => 1));
			}
		}
		if (isset($post['user_caps'])) {
			$sql .= ",user_caps='" . serialize($post['user_caps']) . "'";
			$log_message .= formatLogKeyData('', 'permissions', $this->getFriendlyCaps($post['user_caps']));
		}
		
		/** Update the user */
		$query = "UPDATE `fm_users` SET $sql WHERE `user_id`={$post['user_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(_('Could not update the user because a database error occurred.'), 'sql');
		}

		/** Set theme */
		if (isset($post['user_theme'])) {
			@session_start();
			$_SESSION['user']['theme'] = $post['user_theme'];
			$_SESSION['user']['theme_mode'] = $post['user_theme_mode'];
			session_write_close();
		}

		/** Process forced password change */
		if (isset($post['user_force_pwd_change']) && $post['user_force_pwd_change'] == 'yes') $fm_login->processUserPwdResetForm($user_login, 'no mail');
		
		addLogEntry($log_message, $fm_name);
		
		return true;
	}
	
	
	/**
	 * Updates the selected group
	 *
	 * @since 2.1
	 * @package facileManager
	 */
	function editGroup($post) {
		global $fmdb, $global_form_field_excludes, $fm_name, $fm_login;
		
		$post['group_id'] = intval($post['group_id']);
		if (!isset($post['group_id'])) return _('This is a malformed request.');
		if (empty($post['group_name'])) return _('No group name defined.');

		$group_name = $post['group_name'];
		
		/** Check name field length */
		$field_length = getColumnLength('fm_groups', 'group_name');
		if ($field_length !== false && strlen($group_name) > $field_length) return sprintf(_('Group name is too long (maximum %d characters).'), $field_length);
		
		/** Does the record already exist for this account? */
		$query = "SELECT * FROM `fm_groups` WHERE `group_status`!='deleted' AND `group_name`='$group_name' AND `group_id`!='{$post['group_id']}'";
		$fmdb->get_results($query);
		if ($fmdb->num_rows) return _('This group already exists.');
		
		$log_message = sprintf("Updated user group '%s':\n", $this->getName('group', $post['group_id']));

		$sql_edit = '';
		
		$exclude = array_merge($global_form_field_excludes, array('group_id', 'user_caps', 'process_user_caps', 'type', 'group_users'));

		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . $data . "', ";
				$log_message .= formatLogKeyData('group_', $key, $data);
			}
		}
		$sql = rtrim($sql_edit, ', ');

		if (isset($post['group_users']) && is_array($post['group_users'])) {
			foreach ((array) $post['group_users'] as $id) {
				$group_members[] = $this->getName('user', $id);
			}
		} else {
			$group_members[] = _('None');
		}
		$log_message .= formatLogKeyData('group_', 'group_users', join(', ', (array) $group_members));

		/** Process group permissions */
		if (isset($post['process_user_caps']) && !isset($post['user_caps'])) $post['user_caps'] = array();
		
		if (isset($post['user_caps'][$fm_name])) {
			if (array_key_exists('do_everything', $post['user_caps'][$fm_name])) {
				$post['user_caps'] = array($fm_name => array('do_everything' => 1));
			}
		}
		if (isset($post['user_caps'])) {
			$sql .= ",group_caps='" . serialize($post['user_caps']) . "'";
			$log_message .= formatLogKeyData('', 'permissions', $this->getFriendlyCaps($post['user_caps']));
		}
		
		/** Update the group */
		$query = "UPDATE `fm_groups` SET $sql WHERE `group_id`={$post['group_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(_('Could not update the group because a database error occurred.'), 'sql');
		}
		
		/* Associated users with group */
		$queries[] = "UPDATE `fm_users` SET `user_group`='0', `user_caps`=NULL WHERE `user_group`='{$post['group_id']}'";
		$queries[] = "UPDATE `fm_users` SET `user_group`='{$post['group_id']}', `user_caps`=NULL WHERE `user_id` IN ('" . join("','", (array) $post['group_users']) . "')";
		foreach ($queries as $query) {
			$fmdb->query($query);
			if ($fmdb->sql_errors) {
				return formatError(_('Could not associate the users with the group.'), 'sql');
			}
		}

		addLogEntry($log_message, $fm_name);
		
		return true;
	}
	
	
	/**
	 * Deletes the selected user
	 *
	 * @since 1.0
	 * @package facileManager
	 */
	function delete($id, $type = 'user') {
		global $fm_name;
		
		$functionCan = $type . 'Can';
		
		if ($type != 'key' && (!currentUserCan('do_everything') && $functionCan($id, 'do_everything')) || ($type == 'user' && $id == getDefaultAdminID())) {
			return sprintf(_('You do not have permission to delete this %s.'), $type);
		}
		if ($type == 'user') {
			/** Ensure user is not current LDAP template user */
			if (getOption('auth_method') == 2) {
				$template_user_id = getOption('ldap_user_template');
				if ($id == $template_user_id) return _('This user is the LDAP user template and cannot be deleted at this time.');
			}
			$field = 'user_login';
		} elseif ($type == 'group') {
			$field = 'group_name';
			if (basicUpdate('fm_users', $id, 'user_group', 0, 'user_group') === false) {
				return formatError(_('This group could not be removed from the associated users.'), 'sql');
			}
		} elseif ($type == 'key') {
			$field = 'key_token';
		}
		
		$tmp_name = getNameFromID($id, 'fm_' . $type . 's', $type . '_', $type . '_id', $field);
		if (!updateStatus('fm_' . $type . 's', $id, $type . '_', 'deleted', $type . '_id')) {
			return formatError(sprintf(_('This %s could not be deleted.'), $type), 'sql');
		} else {
			addLogEntry(sprintf(_("Deleted %s '%s'."), $type, $tmp_name), $fm_name);
			return true;
		}
	}


	/**
	 * Displays the user rows
	 *
	 * @since 1.0
	 * @package facileManager
	 */
	function displayRow($row, $type) {
		global $__FM_CONFIG, $fm_name;
		
		$property = rtrim($type, 's') . '_status';
		$disabled_class = ($row->$property == 'disabled') ? ' class="disabled"' : null;

		if ($type == 'users') {
			$id = $row->user_id;
			$default_id = getDefaultAdminID();
			if (currentUserCan('manage_users') && $_SESSION['user']['id'] != $row->user_id) {
				$edit_status = '';
				if ($row->user_template_only == 'yes' && (currentUserCan('do_everything') || (!userCan($row->user_id, 'do_everything')))) {
					$edit_status .= '<a class="copy_form_link" name="' . $type . '" href="#">' . $__FM_CONFIG['icons']['copy'] . '</a>';
					$edit_status .= '<a class="edit_form_link" name="' . $type . '" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
				}
				if ($row->user_template_only == 'no') {
					if ($row->user_id != $_SESSION['user']['id']) {
						if ((currentUserCan('do_everything') || !userCan($row->user_id, 'do_everything')) && $row->user_id != $default_id) {
							$edit_status .= '<a class="edit_form_link" name="' . $type . '" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
							$edit_status .= '<a class="status_form_link" href="#" rel="';
							$edit_status .= ($row->user_status == 'active') ? 'disabled">' . $__FM_CONFIG['icons']['disable'] : 'active">' . $__FM_CONFIG['icons']['enable'];
							$edit_status .= '</a>';
						}

						/** Cannot change password without mail_enable defined */
						if (getOption('mail_enable') && $row->user_auth_type != 2 && $row->user_template_only == 'no') {
							$edit_status .= '<a class="reset_password" id="' . $row->user_login . '" href="#">' . $__FM_CONFIG['icons']['pwd_reset'] . '</a>';
						}
					} else {
						$edit_status .= sprintf('<center>%s</center>', _('Enabled'));
					}
				}
				if ((currentUserCan('do_everything') || !userCan($row->user_id, 'do_everything')) && $row->user_id != $default_id) {
					$edit_status .= '<a href="#" name="' . $type . '" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
				}
			} else {
				$edit_status = '<a style="width: 110px; margin: auto;" class="account_settings" id="' . $_SESSION['user']['id'] . '" href="#">' . $__FM_CONFIG['icons']['pwd_change'] . '</a>';
			}

			$star = (userCan($row->user_id, 'do_everything')) ? $__FM_CONFIG['icons']['star'] : null;
			$template_user = ($row->user_template_only == 'yes') ? $__FM_CONFIG['icons']['template_user'] : null;

			$last_login = ($row->user_last_login == 0) ? _('Never') : date("F d, Y \a\\t H:i T", $row->user_last_login);
			if ($row->user_ipaddr) {
				$user_ipaddr = $row->user_ipaddr;
			} else $user_ipaddr = _('None');

			if ($row->user_auth_type == 2) {
				$user_auth_type = 'LDAP';
			} elseif ($row->user_auth_type == 1) {
				$user_auth_type = $fm_name;
			} else {
				$user_auth_type = _('None');
			}
			$column = "<td>$star $template_user</td>
			<td>{$row->user_login}</td>
			<td>$last_login</td>
			<td>$user_ipaddr</td>
			<td>$user_auth_type</td>
			<td>{$row->user_comment}</td>";
			$name = $row->user_login;
		} elseif ($type == 'groups') {
			$id = $row->group_id;
			if (currentUserCan('do_everything') || (!groupCan($row->group_id, 'do_everything') && currentUserCan('manage_users'))) {
				$edit_status = '<a class="edit_form_link" name="' . $type . '" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
				$edit_status .= '<a href="#" name="' . $type . '" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			} else {
				$edit_status = $id = '';
			}
			$star = (groupCan($row->group_id, 'do_everything')) ? $__FM_CONFIG['icons']['star'] : null;
			
			$group_members_arr = $this->getGroupUsers($row->group_id, 'all');
			foreach ($group_members_arr as $group => $member) {
				$group_members[] = $member[0];
			}
			$group_members = join(', ', (array) $group_members);
			
			$column = "<td>$star</td>
			<td>{$row->group_name}</td>
			<td>$group_members</td>
			<td>" . nl2br($row->group_comment) . "</td>";
			$name = $row->group_name;
		} elseif ($type == 'keys') {
			$edit_status = $id = $user_column = '';
			$can_manage_users = currentUserCan('manage_users');
			if ($can_manage_users || $row->user_id == $_SESSION['user']['id']) {
				$id = $row->key_id;
				$edit_status .= '<a class="status_form_link" href="#" rel="';
				$edit_status .= ($row->key_status == 'active') ? 'disabled' : 'active';
				$edit_status .= '">';
				$edit_status .= ($row->key_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
				$edit_status .= '</a>';
				$edit_status .= '<a href="#" name="' . $type . '" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			}

			if ($can_manage_users) {
				$user_column = sprintf('<td>%s</td>', getNameFromID($row->user_id, 'fm_users', 'user_', 'user_id', 'user_login'));
			}

			$column = "<td></td>
			<td>{$row->key_token}</td>
			$user_column";
			$name = $row->key_token;
		}
		
		echo <<<HTML
		<tr id="$id" name="$name"$disabled_class>
			$column
			<td class="column-actions">$edit_status</td>
		</tr>

HTML;
	}

	/**
	 * Displays the form to add new user
	 *
	 * @since 1.0
	 * @package facileManager
	 */
	function printUsersForm($data = '', $action = 'add', $form_bits = array(), $type = 'users', $button_text = 'Save', $button_id = 'submit', $action_page = 'admin-users.php', $print_form_head = true, $display_type = 'popup') {
		global $__FM_CONFIG, $fm_name, $fm_login;

		$user_id = $group_id = 0;
		$user_login = $user_password = $cpassword = $user_comment = null;
		$ucaction = ucfirst($action);
		$disabled = (isset($_GET['id']) && $_SESSION['user']['id'] == $_GET['id']) ? 'disabled' : null;
		$button_disabled = null;
		$user_email = $user_default_module = $user_theme = $user_theme_mode = null;
		$hidden = $user_perm_form = $return_form_rows = null;
		$user_force_pwd_change = $user_template_only = null;
		$group_name = $group_comment = $user_group = null;
		
		$default_id = getDefaultAdminID();
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
			$user_password = null;
		}
		if ($action == 'add') {
			$popup_title = $type == 'users' ? __('Add User') : __('Add Group');
		} else {
			$popup_title = $type == 'users' ? __('Edit User') : __('Edit Group');
		}
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		$hidden = '<input type="hidden" name="type" value="' . $type . '" />';
		
		if (in_array('user_login', $form_bits)) {
			/** Get field length */
			$field_length = getColumnLength('fm_users', 'user_login');
			
			$username_form = ($action == 'add' || (string) array_search('user_login', $form_bits) == 'editable') ? '<input name="user_login" id="user_login" type="text" value="' . $user_login . '" size="40" maxlength="' . $field_length . '" class="required" />' : '<span id="form_username">' . $user_login . '</span>';
			$hidden .= '<input type="hidden" name="user_id" value="' . $user_id . '" />';
			$return_form_rows .= '<tr>
					<th width="33%" scope="row"><label for="user_login">' . _('User Login') . '</label></th>
					<td width="67%">' . $username_form . '</td>
				</tr>';
		}
		if (in_array('user_comment', $form_bits)) {
			/** Get field length */
			$field_length = getColumnLength('fm_users', 'user_comment');
			
			$return_form_rows .= '<tr>
					<th width="33%" scope="row"><label for="user_comment">' . _('User Comment') . '</label></th>
					<td width="67%"><input name="user_comment" id="user_comment" type="text" value="' . $user_comment . '" size="32" maxlength="' . $field_length . '" ' . $disabled . ' /></td>
				</tr>';
		}
		if (in_array('user_email', $form_bits)) {
			/** Get field length */
			$field_length = getColumnLength('fm_users', 'user_login');
			
			$return_form_rows .= '<tr>
					<th width="33%" scope="row"><label for="user_email">' . _('User Email') . '</label></th>
					<td width="67%"><input name="user_email" id="user_email" type="email" value="' . $user_email . '" size="32" maxlength="' . $field_length . '" ' . $disabled . ' class="required" /></td>
				</tr>';
		}

		if (in_array('user_auth_method', $form_bits) && getOption('auth_method')) {
			if (!isset($user_auth_type)) {
				$user_auth_type = 1;
			}
			
			$auth_method_types = $__FM_CONFIG['options']['auth_method'];
			if (array_shift($auth_method_types) && count($auth_method_types) > 1) {
				$return_form_rows .= '<tr>
					<th width="33%" scope="row"><label for="user_auth_type">' . _('Authentication Method') . '</label></th>
					<td width="67%">' . buildSelect('user_auth_type', 'user_auth_type', $auth_method_types, $user_auth_type) . '</td>
				</tr>';
			}
		}
		
		if ((in_array('user_password', $form_bits) || array_key_exists('user_password', $form_bits)) || ($user_id == $_SESSION['user']['id'] && $user_auth_type != 2)) {
			if ($action == 'add') {
				$button_disabled = 'disabled';
				$field_required = 'class="required" ';
			} else {
				$field_required = '';
			}

			$strength = $GLOBALS['PWD_STRENGTH'];
			if (array_key_exists('user_password', $form_bits)) $strength = $form_bits['user_password'];
			$return_form_rows .= '<tr class="user_password">
					<th width="33%" scope="row"><label for="user_password">' . _('User Password') . '</label></th>
					<td width="67%"><input name="user_password" id="user_password" type="password" value="" size="40" onkeyup="javascript:checkPasswd(\'user_password\', \'' . $button_id . '\', \'' . $strength . '\');" ' . $field_required . '/></td>
				</tr>
				<tr class="user_password">
					<th width="33%" scope="row"><label for="cpassword">' . _('Confirm Password') . '</label></th>
					<td width="67%"><input name="cpassword" id="cpassword" type="password" value="" size="40" onkeyup="javascript:checkPasswd(\'cpassword\', \'' . $button_id . '\', \'' . $strength . '\');" ' . $field_required . '/></td>
				</tr>
				<tr class="user_password">
					<th width="33%" scope="row">' . _('Password Validity') . '</th>
					<td width="67%"><div id="passwd_check">' . _('No Password') . '</div></td>
				</tr>
				<tr class="pwdhint user_password">
					<th width="33%" scope="row">' . _('Hint') . '</th>
					<td width="67%">' . $__FM_CONFIG['password_hint'][$strength][1] . '</td>
				</tr>';
		}
		
		if (in_array('user_groups', $form_bits) && $user_id != $default_id) {
			$user_group_options = buildSelect('user_group', 'user_group', $this->getGroups(), $user_group);
			$return_form_rows .= '<tr>
					<th width="33%" scope="row">' . _('Associated Group') . '</th>
					<td width="67%">' . $user_group_options . '</td>
				</tr>';
		}
		
		if (in_array('user_module', $form_bits)) {
			$active_modules = ($user_id == $_SESSION['user']['id']) ? getActiveModules(true) : getActiveModules();
			if (count($active_modules) > 1) {
				$user_module_options = buildSelect('user_default_module', 'user_default_module', $active_modules, $user_default_module);
				$return_form_rows .= '<tr>
					<th width="33%" scope="row">' . _('Default Module') . '</th>
					<td width="67%">' . $user_module_options . '</td>
				</tr>';
			}
			unset($active_modules);
		}
		
		if (in_array('user_theme', $form_bits)) {
			if (!$user_theme) $user_theme = $__FM_CONFIG['default']['theme'];
			if (!$user_theme_mode) $user_theme_mode = 'System';
			$user_theme_options = buildSelect('user_theme', 'user_theme', getThemes(), $user_theme);
			$user_theme_mode_options = buildSelect('user_theme_mode', 'user_theme_mode', enumMYSQLSelect('fm_users', 'user_theme_mode'), $user_theme_mode);
			$return_form_rows .= '<tr>
					<th width="33%" scope="row">' . _('Theme') . '</th>
					<td width="67%">' . $user_theme_options . '</td>
				</tr>
				<tr class="theme-mode-selector">
					<th width="33%" scope="row">' . _('Theme Mode') . '</th>
					<td width="67%">' . $user_theme_mode_options . '</td>
				</tr>';
		}
		
		if (in_array('user_options', $form_bits) && $user_id != $default_id) {
			$force_pwd_check = ($user_force_pwd_change == 'yes') ? 'checked disabled' : null;
			$user_template_only_check = ($user_template_only == 'yes') ? 'checked' : null;
			$return_form_rows .= '<tr>
					<th width="33%" scope="row">' . _('Options') . '</th>
					<td width="67%">
						<input name="user_force_pwd_change" id="user_force_pwd_change" value="yes" type="checkbox" ' . $force_pwd_check . '/><label for="user_force_pwd_change">' . _('Force Password Change at Next Login') . '</label><br />
						<input name="user_template_only" id="user_template_only" value="yes" type="checkbox" ' . $user_template_only_check . '/><label for="user_template_only">' . _('Template User') . '</label>
					</td>
				</tr>';
		}
		
		if (in_array('user_token', $form_bits) && getOption('api_token_support') && $user_id == $_SESSION['user']['id']) {
			$return_form_rows .= '<tr>
					<th width="33%" scope="row"></th>
					<td width="67%">
						<span><a href="admin-users.php?type=keys">' . __('Configure API Keys') . ' &raquo;</a></span>
					</td>
				</tr>';
		}
		
		if (in_array('group_name', $form_bits)) {
			/** Get field length */
			$field_length = getColumnLength('fm_groups', 'group_name');
			
			$hidden .= '<input type="hidden" name="group_id" value="' . $group_id . '" />';
			$hidden .= $action != 'add' ? '<input type="hidden" name="group_name" value="' . $group_name . '" />' : null;
			$return_form_rows .= '<tr>
					<th width="33%" scope="row"><label for="group_name">' . _('Group Name') . '</label></th>
					<td width="67%"><input name="group_name" id="group_name" type="text" value="' . $group_name . '" size="32" maxlength="' . $field_length . '" ' . $disabled . ' class="required" /></td>
				</tr>';
		}
		
		if (in_array('comment', $form_bits)) {
			$return_form_rows .= '<tr>
					<th width="33%" scope="row"><label for="group_comment">' . _('Comment') . '</label></th>
					<td width="67%"><textarea id="group_comment" name="group_comment" rows="4" cols="30">' . $group_comment . '</textarea></td>
				</tr>';
		}
		
		if (in_array('group_users', $form_bits)) {
			$group_users = buildSelect('group_users', 'group_users', $this->getGroupUsers(), $this->getGroupUsers($group_id), 5, null, true, null, 'wide_select', _('Select one or more users'));
			$return_form_rows .= '<tr>
					<th width="33%" scope="row">' . _('Associated Users') . '</th>
					<td width="67%">' . $group_users . '</td>
				</tr>';
		}
		
		if (in_array('verbose', $form_bits)) {
			$hidden .= '<input type="hidden" name="verbose" value="0" />' . "\n";
			$return_form_rows .= '<tr>
					<th width="33%" scope="row">' . _('Options') . '</th>
					<td width="67%"><input name="verbose" id="verbose" type="checkbox" value="1" checked /><label for="verbose">' . _('Verbose Output') . '</label></td>
				</tr>';
		}
		
		do if (in_array('user_perms', $form_bits)) {
			/** Cannot edit perms if a member of a group or super-admin if logged in user is not a super-admin */
			if ($user_id == $default_id || (userCan($user_id, 'do_everything') && !currentUserCan('do_everything'))) break;
			
			if ($type == 'users') {
				$id = $user_id;
				$perm_function = 'userCan';
			} else {
				$id = $group_id;
				$perm_function = 'groupCan';
			}
			
			$user_is_super_admin = $perm_function($id, 'do_everything');
			
			$fm_perm_boxes = $perm_boxes = '';
			$i = 1;
			$fm_user_caps = getAvailableUserCapabilities();
			foreach ($fm_user_caps[$fm_name] as $key => $title) {
				if ($key == 'do_everything') {
					if (!currentUserCan('do_everything')) continue;
					$title = "<b>$title</b>";
				}
				if ($key != 'do_everything' && $user_is_super_admin) {
					$checked = null;
				} else {
					$checked = ($perm_function($id, $key)) ? 'checked' : null;
				}
				$fm_perm_boxes .= ' <input name="user_caps[' . $fm_name . '][' . $key . ']" id="fm_perm_' . $key . '" type="checkbox" value="1" ' . $checked . '/> <label for="fm_perm_' . $key . '">' . $title . '</label>' . "\n";
				/** Display checkboxes three per row */
				if ($i == 3) {
					$fm_perm_boxes .= "<br />\n";
					$i = 0;
				}
				$i++;
			}
			if (!empty($fm_perm_boxes)) {
				$perm_boxes .= <<<PERM
				<tr id="userperms" class="user_permissions">
					<th width="33%" scope="row">$fm_name</th>
					<td width="67%">
						<input type="hidden" name="process_user_caps" value="1" />
						$fm_perm_boxes
					</td>
				</tr>

PERM;
			}
			
			/** Process module permissions */
			$active_modules = getActiveModules();
			foreach ($active_modules as $module_name) {
				$module_perm_boxes = '';
				$i = 1;
				if (array_key_exists($module_name, $fm_user_caps)) {
					foreach ($fm_user_caps[$module_name] as $key => $title) {
						$checked = ($perm_function($id, $key, $module_name) && !$user_is_super_admin) ? 'checked' : null;
						$module_perm_boxes .= ' <input name="user_caps[' . $module_name . '][' . $key . ']" id="fm_perm_' . $module_name . '_' . $key . '" type="checkbox" value="1" ' . $checked . '/> <label for="fm_perm_' . $module_name . '_' . $key . '">' . $title . '</label>' . "\n";
						/** Display checkboxes three per row */
						if ($i == 3) {
							$module_perm_boxes .= "<br />\n";
							$i = 0;
						}
						$i++;
					}
					$module_extra_functions = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $module_name . DIRECTORY_SEPARATOR . 'functions.extra.php';
					if (file_exists($module_extra_functions)) {
						include($module_extra_functions);

						$function = 'print' . $module_name . 'UsersForm';
						if (function_exists($function)) {
							$module_perm_boxes .= $function(getUserCapabilities($id, substr($type, 0, -1)), $module_name);
						}
					}
				}
				
				
				if (!empty($module_perm_boxes)) {
					$perm_boxes .= <<<PERM
					<tr id="userperms" class="user_permissions">
						<th width="33%" scope="row">$module_name</th>
						<td width="67%">
						$module_perm_boxes
						</td>
					</tr>
	
PERM;
				}
			}
			
			if (!empty($perm_boxes)) {
				$user_perm_form = sprintf('<tr class="user_permissions"><td colspan="2" class="form-break"><i>%s</i></td></tr>', _('Permissions')) . $perm_boxes;
			}
		} while (false);
		
		$return_form = ($print_form_head) ? '<form name="manage" id="manage" method="post" action="' . $action_page . '">' . "\n" : null;
		$return_form = '';
		if ($display_type == 'popup') $return_form .= $popup_header;
		$return_form .= '
			<div>
			<form id="fm_user_profile">
			<input type="hidden" name="action" value="' . $action . '" />' . $hidden . '
			<table class="form-table" width="495px">
				<tr><td colspan="2"><i>' . _('Details') . '</i></td></tr>' . $return_form_rows . $user_perm_form;
		
		$return_form .= '</table></div>';

		if ($display_type == 'popup') $return_form .= $popup_footer . '
		</form>
		<script>
			$(document).ready(function() {
				$(".form-table select").select2({
					containerCss: { "min-width": "165px" },
					minimumResultsForSearch: -1
				});
				$("select.wide_select").select2({
					width: "300px",
					minimumResultsForSearch: -1
				});
				$("#user_group").trigger("change");
			});
		</script>';

		return $return_form;
	}

	
	/**
	 * Gets all associated users for the group
	 *
	 * @since 2.1
	 * @package facileManager
	 *
	 * @param integer $group_id Group ID to return user list from
	 * @return array
	 */
	function getGroupUsers($group_id = null, $include = 'id-only') {
		global $fmdb, $__FM_CONFIG;
		
		$user_list = array();
		
		if ($group_id == null) {
			basicGetList('fm_users', 'user_login', 'user_', "AND user_template_only='no' AND user_id!={$_SESSION['user']['id']} AND (user_caps IS NULL OR user_caps NOT LIKE '%do_everything%')");
		} else {
			$query = "SELECT user_id, user_login FROM fm_users WHERE account_id={$_SESSION['user']['account_id']} AND user_status!='deleted' AND user_template_only='no' AND user_group=" . sanitize($group_id);
			$fmdb->get_results($query);
		}
		
		for ($i=0; $i<$fmdb->num_rows; $i++) {
			if ($include == 'all' || ($include == 'id-only' && $group_id == null)) {
				$user_list[$i][] = $fmdb->last_result[$i]->user_login;
				$user_list[$i][] = $fmdb->last_result[$i]->user_id;
			} else {
				$user_list[] = $fmdb->last_result[$i]->user_id;
			}
		}
		
		return $user_list;
	}

	
	/**
	 * Gets all available user groups
	 *
	 * @since 2.1
	 * @package facileManager
	 *
	 * @return array
	 */
	function getGroups() {
		global $fmdb;
		
		$group_list[0][] = null;
		$group_list[0][] = 0;
		
		basicGetList('fm_groups', 'group_name', 'group_');
		
		for ($i=0; $i<$fmdb->num_rows; $i++) {
			$group_list[$i+1][] = $fmdb->last_result[$i]->group_name;
			$group_list[$i+1][] = $fmdb->last_result[$i]->group_id;
		}
		
		return (array) $group_list;
	}

	
	/**
	 * Generates unique API keypair for the user
	 *
	 * @since 4.0
	 * @package facileManager
	 *
	 * @return string
	 */
	function generateAPIKey() {
		global $fmdb, $__FM_CONFIG, $fm_name;
		
		$unique_key = false;

		$popup_header = buildPopup('header', _('API Key Creation'));
		$popup_footer = buildPopup('footer', _('OK'), array('cancel_button' => 'cancel'));
		
		/** Ensure the key is unique */
		while ($unique_key == false) {
			$key_token = strtoupper(genRandomString(mt_rand(20, 30)));
			$key_secret = genRandomString(mt_rand(30, 50));

			$query = "SELECT * FROM `fm_keys` WHERE `key_status`!='deleted' AND `key_token`='$key_token'";
			$fmdb->get_results($query);
			if (!$fmdb->num_rows) $unique_key = true;
		}

		$query = sprintf("INSERT INTO fm_keys (`account_id`, `user_id`, `key_token`, `key_secret`) VALUES (%d, %d, '%s', '%s')",
			$_SESSION['user']['account_id'], $_SESSION['user']['id'], $key_token, password_hash($key_secret, PASSWORD_DEFAULT));
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return $popup_header . formatError(_('Could not create the keypair because a database error occurred.'), 'sql') . $popup_footer;
		}

		addLogEntry(sprintf(_("Added API key '%s'."), $key_token), $fm_name);

		/** Give user ability to copy keypair */
		$message = sprintf('<p>%s %s</p>
		<p>%s <u>%s</u></p>
		<p><b>%s :</b> %s<br />
		<b>%s :</b> %s</p>',
			$__FM_CONFIG['icons']['ok'],
			_('Your API keypair has been successfully created.'),
			_('Please copy the keypair and store in safe place.'),
			_('This your only opportunity of retrieving the secret key.'),
			_('Key'), $key_token,
			_('Secret'), $key_secret
		);
		
		$popup_footer = buildPopup('footer', _('OK'), array('cancel_button' => 'cancel'), 'admin-users.php?type=keys');

		return $popup_header . $message . $popup_footer;
	}


	/**
	 * Gets the user or group name
	 *
	 * @since 4.8.0
	 * @package facileManager
	 *
	 * @param string $type User or group
	 * @param integer $id ID of object to get
	 * @return string
	 */
	private function getName($type, $id) {
		$field = ($type == 'user') ? "{$type}_login" : "{$type}_name";
		return ($id) ? getNameFromID($id, "fm_{$type}s", "{$type}_", "{$type}_id", $field) : _('None');
	}


	/**
	 * Gets a friendly version of the user/group capabilities
	 *
	 * @since 4.8.0
	 * @package facileManager
	 *
	 * @param array $caps Array of capabilities
	 * @return string
	 */
	private function getFriendlyCaps($caps) {
		$fm_user_caps = getAvailableUserCapabilities();

		if (!is_array($caps)) return _('None');
		$perms = array();

		foreach ($caps as $module => $module_caps) {
			$module_perms = array();
			foreach ($module_caps as $key => $val) {
				if (array_key_exists($key, $fm_user_caps[$module])) {
					$module_perms[] = $fm_user_caps[$module][$key];
				} else {
					$module_extra_functions = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'functions.extra.php';
					if (file_exists($module_extra_functions)) {
						include_once($module_extra_functions);

						$function = 'get' . $module . 'UsersPermLogging';
						if (function_exists($function)) {
							$module_perms[] = $function($fm_user_caps, $key, $val);
						}
					}
				}
			}
			$perms[] = sprintf('%s - %s', $module, join(', ', $module_perms));
		}

		return join("\n", $perms);
	}


	/**
	 * Validates a form field
	 *
	 * @since 4.8.0
	 * @package facileManager
	 *
	 * @param string $field Form field name
	 * @param string $value Form field value
	 * @param array $data Data array of secondary fields and values
	 * @return string
	 */
	function validateFormField($field, $value, $data = '') {
		switch ($field) {
			case 'user_login':
				if (empty($value)) return _('No username defined.');
				break;
			case 'user_password':
			case 'cpassword':
				if (is_array($data)) {
					if (empty($value) && isset($data['user_template_only']) && $data['user_template_only'] == 'no') return _('No password defined.');
					if ($value != $data['cpassword'] && isset($data['user_template_only']) && $data['user_template_only'] == 'no') return _('Passwords do not match.');
					$regex = ($GLOBALS['PWD_STRENGTH'] == 'strong') ? '/^(?=.{8,})(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*\W).*$/' : '/^(?=.{7,})(((?=.*[A-Z])(?=.*[a-z]))|((?=.*[A-Z])(?=.*[0-9]))|((?=.*[a-z])(?=.*[0-9]))).*$/';
					if (!preg_match($regex, $value)) return _('Password does not meet the complexity requirements.');
				}
				break;
			case 'user_email':
				if (empty($value) && isset($data['user_template_only']) && $data['user_template_only'] == 'no') return _('No e-mail address defined.');
				if (!filter_var($value, FILTER_VALIDATE_EMAIL)) return _('Invalid e-mail address format.');
				break;
		}

		return true;
	}
}

if (!isset($fm_users))
	$fm_users = new fm_users();
