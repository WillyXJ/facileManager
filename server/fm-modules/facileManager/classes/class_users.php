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
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/                                           |
 +-------------------------------------------------------------------------+
*/

class fm_users {
	
	/**
	 * Displays the user list
	 */
	function rows($result) {
		global $fmdb;
		
		if (!$result) {
			echo '<p id="noresult">There are no users.</p>';
		} else {
			?>
			<table class="display_results" id="table_edits" name="users">
				<thead>
					<tr>
						<th width="10" style="text-align: center;"></th>
						<th>Login</th>
						<th>Last Session Date</th>
						<th>Last Session Host</th>
						<th>Authenticate With</th>
						<th>Super Admin</th>
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
	 * Adds the new user
	 */
	function add($data) {
		global $fmdb, $fm_name, $fm_login;
		
		extract($data, EXTR_SKIP);
		
		$user_login = sanitize($user_login);
		$user_password = sanitize($user_password);
		$user_email = sanitize($user_email);
		
		/** Template user? */
		if (isset($user_template_only) && $user_template_only == 'yes') {
			$user_template_only = 'yes';
			$user_status = 'disabled';
			$user_auth_type = 0;
		} else {
			$user_template_only = 'no';
			$user_status = 'active';
			$user_auth_type = 1;
		}

		if (empty($user_login)) return 'No username defined.';
		if (empty($user_password) && $user_template_only == 'no') return 'No password defined.';
		if ($user_password != $cpassword && $user_template_only == 'no') return 'Passwords do not match.';
		if (empty($user_email) && $user_template_only == 'no') return 'No e-mail address defined.';
		
		/** Check name field length */
		$field_length = getColumnLength('fm_users', 'user_login');
		if ($field_length !== false && strlen($user_login) > $field_length) return 'Username is too long (maximum ' . $field_length . ' characters).';
		
		/** Force password change? */
		$user_force_pwd_change = (isset($user_force_pwd_change) && $user_force_pwd_change == 'yes') ? 'yes' : 'no';

		/** Does the record already exist for this account? */
		$query = "SELECT * FROM `fm_users` WHERE `user_status`!='deleted' AND `user_login`='$user_login'";
		$fmdb->get_results($query);
		if ($fmdb->num_rows) return 'This user already exists.';
		
		/** Process user permissions */
		$user_perms = 0;
		if (isset($fm_perm[$fm_name])) {
			foreach ($fm_perm[$fm_name] as $dec => $checked) {
				if ($dec == 1) {
					$user_perms = 1;
					break;
				}
				$user_perms += $dec;
			}
		}
		
		$query = "INSERT INTO `fm_users` (`account_id`, `user_login`, `user_password`, `user_email`, `user_force_pwd_change`, `user_perms`, `user_template_only`, `user_status`, `user_auth_type`) 
				VALUES('{$_SESSION['user']['account_id']}', '$user_login', password('$user_password'), '$user_email', '$user_force_pwd_change', $user_perms, '$user_template_only', '$user_status', $user_auth_type)";
		$result = $fmdb->query($query);
		
		if (!$result) return 'Could not add the user to the database.';

		/** Process user module permissions */
		if (isset($fm_perm)) {
			$new_user_id = $fmdb->insert_id;
			foreach ($fm_perm as $module => $perm_array) {
				$perm_dec = 0;
				$perm_extra = null;
				foreach ($perm_array as $dec => $checked) {
					if ($dec == 1) {
						$perm_dec = 1;
						break;
					}
					if (substr($dec, 0, 5) == 'extra') {
						$perm_extra[substr($dec, 6)] = in_array(0, $checked) ? array(0) : $checked;
						continue;
					}
					$perm_dec += $dec;
				}
				/** If super-admin then no module perms should be defined */
				if ($user_perms == 1 || $perm_dec == 1) {
					if ($user_perms == 1) $perm_dec = 0;
					if (is_array($perm_extra)) {
						foreach ($perm_extra as $key => $value) {
							$perm_extra[$key] = array(0);
						}
					}
				}
				
				$query = "INSERT INTO `fm_perms` (`user_id`, `perm_module`, `perm_value`, `perm_extra`) VALUES ($new_user_id, '$module', $perm_dec, '" . serialize($perm_extra) . "')";
				$result = $fmdb->query($query);
			}
		}
		
		/** Process forced password change */
		if ($user_force_pwd_change == 'yes') $fm_login->processUserPwdResetForm($user_login);
		
		addLogEntry("Added user '$user_login'.");
		return true;
	}

	/**
	 * Updates the selected user
	 */
	function update($post) {
		global $fmdb, $fm_name, $fm_login;
		
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

		if (!isset($post['user_id'])) {
			$post['user_id'] = $_SESSION['user']['id'];
			$post['user_login'] = $_SESSION['user']['name'];
		}
		if (empty($post['user_login'])) return 'No username defined.';
		if (!empty($post['user_password'])) {
			if (empty($post['cpassword']) || $post['user_password'] != $post['cpassword']) return 'Passwords do not match.';
			$post['user_password'] = sanitize($post['user_password'], false);
			$sql_pwd = "`user_password`=password('" . $post['user_password'] . "'),";
		} else $sql_pwd = null;
		
		/** Check name field length */
		$field_length = getColumnLength('fm_users', 'user_login');
		if ($field_length !== false && strlen($post['user_login']) > $field_length) return 'Username is too long (maximum ' . $field_length . ' characters).';
		
		/** Does the record already exist for this account? */
		basicGet('fm_users', sanitize($post['user_login']), 'user_', 'user_login');
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			if ($result[0]->user_id != $post['user_id']) return 'This user already exists.';
		}
		
		$sql_edit = null;
		
		$exclude = array('submit', 'action', 'user_id', 'cpassword', 'user_password', 'fm_perm', 'is_ajax');

		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . sanitize($data) . "',";
			}
		}
		$sql = rtrim($sql_edit . $sql_pwd, ',');
		
		/** Process user permissions */
		$user_perms = 0;
		if (isset($post['fm_perm'][$fm_name])) {
			foreach ($post['fm_perm'][$fm_name] as $dec => $checked) {
				if ($dec == 1) {
					$user_perms = 1;
					break;
				}
				$user_perms += $dec;
			}
		}
		if (isset($post['fm_perm'])) $sql .= ',user_perms=' . $user_perms;
		
		// Update the user
		$query = "UPDATE `fm_users` SET $sql WHERE `user_id`={$post['user_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		if (!$fmdb->last_result) return 'Could not update the user in the database.';
		
		/** Process user module permissions */
		if (isset($post['fm_perm'])) {
			foreach ($post['fm_perm'] as $module => $perm_array) {
				if ($module == $fm_name) continue;
				
				$perm_dec = 0;
				$perm_extra = null;
				foreach ($perm_array as $dec => $checked) {
					if ($dec == 1) {
						$perm_dec = 1;
						break;
					}
					if (substr($dec, 0, 5) == 'extra') {
						$perm_extra[substr($dec, 6)] = in_array(0, $checked) ? array(0) : $checked;
						continue;
					}
					$perm_dec += $dec;
				}
				/** If super-admin then no module perms should be defined */
				if ($user_perms == 1 || $perm_dec == 1) {
					if ($user_perms == 1) $perm_dec = 0;
					if (is_array($perm_extra)) {
						foreach ($perm_extra as $key => $value) {
							$perm_extra[$key] = array(0);
						}
					}
				}
				
				/** Are we inserting or updating? */
				$query = "SELECT * FROM fm_perms WHERE user_id={$post['user_id']} AND perm_module='$module'";
				$fmdb->get_results($query);
				
				if ($fmdb->num_rows) {
					$result = $fmdb->last_result;
					if ($result[0]->perm_value == $perm_dec && $result[0]->perm_extra == serialize($perm_dec)) {
						$query = null;
						break;
					}
					
					$query = "UPDATE `fm_perms` SET `perm_module`='$module', `perm_value`=$perm_dec, `perm_extra`='" . serialize($perm_extra) . "' WHERE `user_id`={$post['user_id']}";
				} else {
					$query = "INSERT INTO `fm_perms` (`user_id`, `perm_module`, `perm_value`, `perm_extra`) VALUES ({$post['user_id']}, '$module', $perm_dec, '" . serialize($perm_extra) . "')";
				}
				$result = $fmdb->query($query);

				if (!$fmdb->last_result) return 'Could not update the user in the database.';
			}
		}

		/** Process forced password change */
		if (isset($post['user_force_pwd_change']) && $post['user_force_pwd_change'] == 'yes') $fm_login->processUserPwdResetForm($post['user_login']);
		
		return true;
	}
	
	
	/**
	 * Deletes the selected user
	 */
	function delete($id) {
		global $fm_name;
		
		/** Ensure user is not current LDAP template user */
		if (getOption('auth_method') == 2) {
			$template_user_id = getOption('ldap_user_template');
			if ($id == $template_user_id) return 'This user is the LDAP user template and cannot be deleted at this time.';
		}
		
		$tmp_name = getNameFromID($id, 'fm_users', 'user_', 'user_id', 'user_login');
		if (!updateStatus('fm_users', $id, 'user_', 'deleted', 'user_id')) {
			return 'This user could not be deleted.'. "\n";
		} else {
			addLogEntry("Deleted user '$tmp_name'.", $fm_name);
			return true;
		}
	}


	function displayRow($row) {
		global $__FM_CONFIG, $allowed_to_manage_users, $fm_name;
		
		$disabled_class = ($row->user_status == 'disabled') ? ' class="disabled"' : null;

		if ($allowed_to_manage_users && $_SESSION['user']['id'] != $row->user_id) {
			$edit_status = null;
			$edit_status .= '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			if ($row->user_template_only == 'no') {
				if ($row->user_id != $_SESSION['user']['id']) {
					$edit_status .= '<a href="' . $GLOBALS['basename'] . '?action=edit&id=' . $row->user_id . '&status=';
					$edit_status .= ($row->user_status == 'active') ? 'disabled">' . $__FM_CONFIG['icons']['disable'] : 'active">' . $__FM_CONFIG['icons']['enable'];
					$edit_status .= '</a>';
	
					/** Cannot change password without mail_enable defined */
					if (getOption('mail_enable') && $row->user_auth_type != 2 && $row->user_template_only == 'no') {
						$edit_status .= '<a class="reset_password" id="' . $row->user_login . '" href="#">' . $__FM_CONFIG['icons']['pwd_reset'] . '</a>';
					}
				} else {
					$edit_status .= '<center>Enabled</center>';
				}
			}
			if ($row->user_id != 1) {
				$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			}
		} else {
			$user_actions = ($row->user_id == $_SESSION['user']['id'] && getOption('auth_method') != 2) ? '<a style="width: 110px; margin: auto;" class="account_settings" id="' . $_SESSION['user']['id'] . '" href="#">' . $__FM_CONFIG['icons']['pwd_change'] . '</a>' : 'N/A';
			$edit_status = $user_actions;
		}
		
		$star = ($row->user_perms & PERM_FM_SUPER_ADMIN) ? $__FM_CONFIG['icons']['star'] : null;
		$template_user = ($row->user_template_only == 'yes') ? $__FM_CONFIG['icons']['template_user'] : null;
		
		$last_login = ($row->user_last_login == 0) ? 'Never' : date("F d, Y \a\\t H:i T", $row->user_last_login);
		if ($row->user_ipaddr) {
			$user_ipaddr = (verifyIPAddress($row->user_ipaddr) !== false) ? @gethostbyaddr($row->user_ipaddr) : $row->user_ipaddr;
		} else $user_ipaddr = 'None';
		$super_admin_status = ($row->user_perms & PERM_FM_SUPER_ADMIN) ? 'yes' : 'no';
		
		if ($row->user_auth_type == 2) {
			$user_auth_type = 'LDAP';
		} elseif ($row->user_auth_type == 1) {
			$user_auth_type = $fm_name;
		} else {
			$user_auth_type = 'None';
		}
		
		echo <<<HTML
		<tr id="$row->user_id"$disabled_class>
			<td>$star $template_user</td>
			<td>$row->user_login</td>
			<td>$last_login</td>
			<td>$user_ipaddr</td>
			<td>$user_auth_type</td>
			<td>$super_admin_status</td>
			<td id="edit_delete_img">$edit_status</td>
		</tr>

HTML;
	}

	/**
	 * Displays the form to add new user
	 */
	function printUsersForm($data = '', $action = 'add', $form_bits = array(), $button_text = 'Save', $button_id = 'submit', $action_page = 'admin-users', $print_form_head = true) {
		global $__FM_CONFIG, $fm_name, $fm_login, $super_admin;

		$user_id = 0;
		$user_login = $user_password = $cpassword = null;
		$ucaction = ucfirst($action);
		$disabled = (isset($_GET['id']) && $_SESSION['user']['id'] == $_GET['id']) ? 'disabled' : null;
		$button_disabled = null;
		$user_email = null;
		$hidden = $user_form = $user_perm_form = $email_form = $user_options_form = $verbose_form = null;
		$user_perms = $user_force_pwd_change = $password_form = $user_template_only = null;
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
			$user_password = null;
		}
		if (in_array('user_login', $form_bits)) {
			/** Get field length */
			$field_length = getColumnLength('fm_users', 'user_login');
			
			$username_form = $action == 'add' ? '<input name="user_login" id="user_login" type="text" value="' . $user_login . '" size="40" maxlength="' . $field_length . '" />' : '<span id="form_username">' . $user_login . '</span>';
			$hidden = '<input type="hidden" name="user_id" value="' . $user_id . '" />';
			$hidden .= $action != 'add' ? '<input type="hidden" name="user_login" value="' . $user_login . '" />' : null;
			$user_form = <<<FORM_ROW
				<tr>
					<th width="33%" scope="row"><label for="user_login">User Login</label></th>
					<td width="67%">$username_form</td>
				</tr>

FORM_ROW;
		}
		if (in_array('user_email', $form_bits)) {
			/** Get field length */
			$field_length = getColumnLength('fm_users', 'user_login');
			
			$email_form = <<<FORM_ROW
				<tr>
					<th width="33%" scope="row"><label for="user_email">User Email</label></th>
					<td width="67%"><input name="user_email" id="user_email" type="email" value="$user_email" size="32" maxlength="$field_length" $disabled /></td>
				</tr>
				
FORM_ROW;
		}

		if (in_array('user_password', $form_bits) || array_key_exists('user_password', $form_bits)) {
			$button_disabled = 'disabled';
			$strength = PWD_STRENGTH;
			if (array_key_exists('user_password', $form_bits)) $strength = $form_bits['user_password'];
			$password_form = <<<FORM_ROW
				<tr>
					<th width="33%" scope="row"><label for="user_password">User Password</label></th>
					<td width="67%"><input name="user_password" id="user_password" type="password" value="" size="40" onkeyup="javascript:checkPasswd('user_password', '$button_id', '$strength');" /></td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="cpassword">Confirm Password</label></th>
					<td width="67%"><input name="cpassword" id="cpassword" type="password" value="" size="40" onkeyup="javascript:checkPasswd('cpassword', '$button_id', '$strength');" /></td>
				</tr>
				<tr>
					<th width="33%" scope="row">Password Validity</th>
					<td width="67%"><div id="passwd_check">No Password</div></td>
				</tr>
				<tr class="pwdhint">
					<th width="33%" scope="row">Hint</th>
					<td width="67%">{$__FM_CONFIG['password_hint'][$strength]}</td>
				</tr>
			
FORM_ROW;
		}
		
		if (in_array('user_options', $form_bits)) {
			$force_pwd_check = ($user_force_pwd_change == 'yes') ? 'checked disabled' : null;
			$user_template_only_check = ($user_template_only == 'yes') ? 'checked' : null;
			$user_options_form = <<<FORM_ROW
				<tr>
					<th width="33%" scope="row">Options</th>
					<td width="67%">
						<input style="height: 10px;" name="user_force_pwd_change" id="user_force_pwd_change" value="yes" type="checkbox" $force_pwd_check /><label for="user_force_pwd_change">Force Password Change at Next Login</label><br />
						<input style="height: 10px;" name="user_template_only" id="user_template_only" value="yes" type="checkbox" $user_template_only_check /><label for="user_template_only">Template User</label>
					</td>
				</tr>
			
FORM_ROW;
		}
		
		if (in_array('verbose', $form_bits)) {
			$hidden .= '<input type="hidden" name="verbose" value="0" />' . "\n";
			$verbose_form = <<<FORM_ROW
				<tr>
					<th width="33%" scope="row">Options</th>
					<td width="67%"><input style="height: 10px;" name="verbose" id="verbose" type="checkbox" value="1" checked /><label for="verbose">Verbose Output</label></td>
				</tr>
			
FORM_ROW;
		}
		
		do if (in_array('user_perms', $form_bits)) {
			/** Cannot edit perms of super-admin if logged in user is not a super-admin */
			if (($user_perms & PERM_FM_SUPER_ADMIN) && !$super_admin) break;
			
			/** Disable all perms if logged in user is super-admin editing self */
			$disabled = ($user_id == $_SESSION['user']['id'] && $super_admin) ? 'disabled' : null;
			
			$all_constants = get_defined_constants(true);
			$fm_perm_boxes = $perm_boxes = null;
			$i = 1;
			/** Process facileManager permissions */
			foreach ($all_constants['user'] as $key => $value) {
				if (preg_match('/^PERM_FM_/', $key)) {
					$fm_perm_name = ucwords(strtolower(str_replace('_', ' ', str_replace('PERM_FM_', '', $key))));
					$checked = ($user_perms & $value) ? 'checked' : null;
					$fm_perm_boxes .= ' <input name="fm_perm[' . $fm_name . '][' . $value . ']" id="fm_perm_' . $value . '" type="checkbox" ' . $checked . ' ' . $disabled . '/> <label for="fm_perm_' . $value . '">' . $fm_perm_name . '</label>' . "\n";
					if ($i == 3) $fm_perm_boxes .= "<br />\n";
					$i++;
				}
			}
			if (!empty($fm_perm_boxes)) {
				$perm_boxes .= <<<PERM
				<tr id="userperms">
					<th width="33%" scope="row">$fm_name</th>
					<td width="67%">
					$fm_perm_boxes
					</td>
				</tr>

PERM;
			}
			
			/** Process module permissions */
			$active_modules = getActiveModules();
			foreach ($active_modules as $module_name) {
				$module_perm_boxes = null;
				/** Get available module variables */
				$module_var_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $module_name . DIRECTORY_SEPARATOR . 'variables.inc.php';
				if (file_exists($module_var_file)) {
					include($module_var_file);
					$all_constants = get_defined_constants(true);
					$user_module_perms = $user_id ? $fm_login->getModulePerms($user_id, $module_name, true) : 0;
					
					$j = 1;
					foreach ($all_constants['user'] as $key => $value) {
						if (preg_match('/^PERM_' . strtoupper($__FM_CONFIG[$module_name]['prefix']) . '/', $key) || preg_match('/^PERM_MODULE_/', $key)) {
							$module_perm_name = ucwords(strtolower(str_replace('_', ' ', str_replace('PERM_' . strtoupper($__FM_CONFIG[$module_name]['prefix']), '', str_replace('PERM_MODULE_', '', $key)))));
							$checked = ($user_module_perms['perm_value'] & $value) ? 'checked' : null;
							if ($module_perm_name == 'Access Denied') {
								$module_perm_name = '<b>' . $module_perm_name . '</b>';
							}
							$module_perm_boxes .= ' <input name="fm_perm[' . $module_name . '][' . $value . ']" id="fm_' . $__FM_CONFIG[$module_name]['prefix'] . 'perm_' . $value . '" type="checkbox" ' . $checked . '/> <label for="fm_' . $__FM_CONFIG[$module_name]['prefix'] . 'perm_' . $value . '">' . $module_perm_name . '</label>' . "\n";
							if ($j == 3) $module_perm_boxes .= "<br />\n";
							$j++;
						}
					}
					$module_extra_functions = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $module_name . DIRECTORY_SEPARATOR . 'functions.extra.php';
					if (file_exists($module_extra_functions)) {
						include($module_extra_functions);

						$function = 'print' . $module_name . 'UsersForm';
						if (function_exists($function)) {
							$module_perm_boxes .= $function($user_module_perms['perm_extra'], $module_name);
						}
					}
				}
				if (!empty($module_perm_boxes)) {
					$perm_boxes .= <<<PERM
					<tr id="userperms">
						<th width="33%" scope="row">$module_name</th>
						<td width="67%">
						$module_perm_boxes
						</td>
					</tr>
	
PERM;
				}
			}
			
			if (!empty($perm_boxes)) {
				$user_perm_form = <<<PERM
					<tr><td colspan="2"><br /><br /><i>User Permissions</i></td></tr>
			$perm_boxes

PERM;
			}
		} while (false);
		
		$return_form = ($print_form_head) ? '<form name="manage" id="manage" method="post" action="' . $action_page . '">' . "\n" : null;
		$return_form .= <<<FORM
			<div class="leftbox">
			<input type="hidden" name="action" value="$action" />
			$hidden
			<table class="form-table" width="495px">
				<tr><td colspan="2"><i>User Details</i></td></tr>
			$user_form
			$email_form
			$password_form
			$user_options_form
			$verbose_form
			$user_perm_form
			</table>
			<p>
				<input type="submit" id="$button_id" name="submit" value="$button_text" class="button" $button_disabled />
				<input value="Cancel" class="button cancel" id="cancel_button" />
			</p>
			</div>
		</form>
FORM;

		return $return_form;
	}

}

if (!isset($fm_users))
	$fm_users = new fm_users();

?>
