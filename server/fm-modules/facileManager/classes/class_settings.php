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

class fm_settings {
	
	/**
	 * Saves the options
	 */
	function save() {
		global $fmdb, $__FM_CONFIG, $fm_name;

		if (!currentUserCan('manage_settings')) return _('You do not have permission to make these changes.');
		
		$force_logout = false;
		$exclude = array('save', 'item_type', 'gen_ssh');
		$ports = array('ldap_port', 'ldap_port_ssl', 'fm_port_ssl');
		
		$log_message = _('Set system settings to the following:') . "\n";
		
		foreach ($_POST as $key => $data) {
			if (!in_array($key, $exclude)) {
				unset($data_array);
				if (is_array($data)) {
					$data_array = $data;
					$account_id = $_SESSION['user']['account_id'];
					$data = $data[$account_id];
				} else $account_id = 0;
				
				/** Check if the option has changed */
				$current_value = getOption($key, $account_id);
				unset($account_id);
				if ($current_value == $data) continue;
				
				if ($key == 'mail_from' && isEmailAddressValid($data) === false) return sprintf(_('%s is not a valid e-mail address.'), $data);
				if (in_array($key, $ports)) {
					if ($data && !verifyNumber($data, 1, 65535, false)) return _('Invalid port number specified.');
				}
				
				if (isset($data_array)) $data = $data_array;
				
				$new_array[$key] = ($current_value === false) ? array($data, 'insert') : array($data, 'update');
			}
		}
		
		if (isset($new_array) && is_array($new_array)) {
			foreach ($new_array as $option => $value) {
				list($option_value, $command) = $value;
				if (is_array($option_value)) {
					$data_array = $option_value;
					$account_id = $_SESSION['user']['account_id'];
					$option_value = $option_value[$account_id];
				} else $account_id = 0;
				
				/** Update with the new value */
				setOption($option, $option_value, $command, false, $account_id);
				unset($account_id);
	
				if ($fmdb->sql_errors) {
					return formatError(_('Could not save settings because a database error occurred.'), 'sql');
				}
		
				$log_value = trim($option_value);
				$log_message .= ucwords(str_replace('_', ' ', $option)) . ': ';
				if (@is_array($__FM_CONFIG['options'][$option][0])) {
					foreach ($__FM_CONFIG['options'][$option] as $array) {
						if ($log_value == $array[1]) {
							$log_message .= $array[0];
							break;
						}
					}
				} elseif ($option == 'mail_smtp_pass') $log_message .= str_repeat('*', 8);
				elseif ($option == 'date_format' || $option == 'time_format') $log_message .= date($log_value);
				elseif ($option == 'ldap_user_template') $log_message .= getNameFromID($log_value, 'fm_users', 'user_', 'user_id', 'user_login');
				elseif ($option_value == '1') $log_message .= _('Yes');
				elseif ($option_value == '0') $log_message .= _('No');
				else $log_message .= $log_value;
				
				$log_message .= "\n";
				
				if ($option == 'auth_method') $force_logout = true;

				if (isset($data_array)) {
					$data = $data_array;
					unset($data_array);
				}
			}
			
			addLogEntry($log_message, $fm_name);

			if ($force_logout) {
				exit('force_logout');
			}

			return true;
		}
		
		return true;
	}
	
	
	/**
	 * Generates a SSH key pair
	 *
	 * @since 1.0
	 * @package facileManager
	 */
	function generateSSHKeyPair() {
		global $fmdb, $__FM_CONFIG, $fm_name;
		
		if (! $fm_temp_directory = getOption('fm_temp_directory')) {
			$fm_temp_directory = sys_get_temp_dir();
		}
		
		/** Create the ssh key pair */
		exec(findProgram('ssh-keygen') . " -t rsa -b 2048 -f $fm_temp_directory/fm_id_rsa -N ''", $exec_array, $retval);
		$array['ssh_key_priv'] = @file_get_contents($fm_temp_directory . '/fm_id_rsa');
		$array['ssh_key_pub'] = @file_get_contents($fm_temp_directory . '/fm_id_rsa.pub');
		
		@unlink($fm_temp_directory . '/fm_id_rsa');
		@unlink($fm_temp_directory . '/fm_id_rsa.pub');
		
		if ($retval) {
			return _('SSH key generation failed.');
		}
		
		foreach ($array as $key => $data) {
			/** Check if the option has changed */
			$current_value = getOption($key);
			if ($current_value == $data) continue;
			
			$new_array[$key] = ($current_value === false) ? array($data, 'insert') : array($data, 'update');
		}
		
		if (isset($new_array) && is_array($new_array)) {
			foreach ($new_array as $option => $value) {
				list($option_value, $command) = $value;
				
				/** Update with the new value */
				setOption($option, $option_value, $command, false, $_SESSION['user']['account_id']);
		
				if ($fmdb->sql_errors) {
					return formatError(_('Could not save settings because a database error occurred.'), 'sql');
				}
			}
			
			addLogEntry(_('Generated system SSH key pair.'), $fm_name);
		}
		
		return true;
	}
	
	
	/**
	 * Displays the form to modify options
	 *
	 * @since 1.0
	 * @package facileManager
	 */
	function printForm() {
		global $fmdb, $__FM_CONFIG, $fm_name;
		
		$tmp = sys_get_temp_dir();
		
		$save_button = currentUserCan('manage_settings') ? sprintf('<p><input type="button" name="save" id="save_fm_settings" value="%s" class="button primary" /></p>', _('Save')) : null;
		$sshkey_button = currentUserCan('manage_settings') ? sprintf('<input type="button" name="gen_ssh" id="generate_ssh_key_pair" value="%s" class="button" />', _('Generate')) : null;
		if ($sshkey_button !== null) {
			$ssh_priv = getOption('ssh_key_priv', $_SESSION['user']['account_id']);
			if ($ssh_priv) {
				$sshkey_button = sprintf('<p>%s</p>', _('SSH key pair is generated.'));
				unset($ssh_priv);
			}
		}
		
		/** Authentication Method */
		$auth_method = getOption('auth_method');
		$auth_method_list = buildSelect('auth_method', 'auth_method', $__FM_CONFIG['options']['auth_method'], $auth_method);
		
		/** fM Auth Section */
		$i=0;
		$password_strength_descriptions = sprintf("<p>%s</p>\n", _('Required password strength for user accounts.'));
		foreach ($__FM_CONFIG['password_hint'] as $strength => $hint) {
			list($ucstrength, $description) = $hint;
			$auth_fm_pw_strength_opt[$i][] = $ucstrength;
			$auth_fm_pw_strength_opt[$i][] = $strength;
			$password_strength_descriptions .= sprintf("<p><i>%s</i> - %s</p>\n", $ucstrength, $description);
			$i++;
		}
		$auth_fm_pw_strength_list = buildSelect('auth_fm_pw_strength', 'auth_fm_pw_strength', $auth_fm_pw_strength_opt, $GLOBALS['PWD_STRENGTH']);
		if ($auth_method == 1) {
			 $auth_fm_options_style = 'style="display: block;"';
		} else $auth_fm_options_style = null;
		
		$auth_message_option_style = ($auth_method) ? 'style="display: block;"' : null;
		
		/** LDAP Section */
		if ($auth_method == 2) {
			 $auth_ldap_options_style = 'style="display: block;"';
		} else $auth_ldap_options_style = null;
		$ldap_server = getOption('ldap_server');
		$ldap_port = getOption('ldap_port');
		$ldap_port_ssl = getOption('ldap_port_ssl');
		$ldap_dn = getOption('ldap_dn');
		
		$ldap_version = getOption('ldap_version');
		$ldap_version_list = buildSelect('ldap_version', 'ldap_version', $__FM_CONFIG['options']['ldap_version'], $ldap_version);
		$ldap_encryption = getOption('ldap_encryption');
		$ldap_encryption_list = buildSelect('ldap_encryption', 'ldap_encryption', $__FM_CONFIG['options']['ldap_encryption'], $ldap_encryption);
		$ldap_cert_file = getOption('ldap_cert_file');
		$ldap_ca_cert_file = getOption('ldap_ca_cert_file');
		$ldap_referrals = getOption('ldap_referrals');
		$ldap_referrals_list = buildSelect('ldap_referrals', 'ldap_referrals', $__FM_CONFIG['options']['ldap_referrals'], $ldap_referrals);
		
		$ldap_group_require = getOption('ldap_group_require');
		if ($ldap_group_require) {
			 $ldap_group_require_checked = 'checked';
			 $ldap_group_require_options_style = 'style="display: block;"';
		} else $ldap_group_require_options_style = $ldap_group_require_checked = null;
		$ldap_group_dn = getOption('ldap_group_dn');
		$ldap_group_attribute = getOption('ldap_group_attribute');
		$ldap_group_search_dn = getOption('ldap_group_search_dn');

		$ldap_user_template = getOption('ldap_user_template');
		$ldap_user_template_list = buildSelect('ldap_user_template', 'ldap_user_template', $this->buildUserList(), $ldap_user_template);

		/** Login Message Section */
		$login_message = getOption('login_message');
		$login_message_accept_checked = (getOption('login_message_accept')) ? 'checked' : null;

		/** Client Autoregistration Section */
		$client_auto_register_checked = (getOption('client_auto_register')) ? 'checked' : null;

		/** SSL Section */
		$enforce_ssl_checked = (getOption('enforce_ssl')) ? 'checked' : null;
		$fm_port_ssl = getOption('fm_port_ssl');
		
		/** API Section */
		$api_token_support_checked = (getOption('api_token_support')) ? 'checked' : null;
		$enforce_ssl_checked .= ($api_token_support_checked) ? ' disabled class="disabled"' : null;

		/** Mailing Section */
		$mail_enable = getOption('mail_enable');
		if ($mail_enable) {
			 $mail_enable_checked = 'checked';
			 $fm_mailing_options_style = 'style="display: block;"';
		} else $mail_enable_checked = $fm_mailing_options_style = null;
		
		if (getOption('mail_smtp_auth')) {
			 $mail_smtp_auth_checked = 'checked';
			 $mail_smtp_auth_options_style = 'style="display: block;"';
		} else $mail_smtp_auth_checked = $mail_smtp_auth_options_style = null;

		$mail_smtp_host = getOption('mail_smtp_host');
		$mail_smtp_port = getOption('mail_smtp_port');
		$mail_smtp_user = getOption('mail_smtp_user');
		$mail_smtp_pass = getOption('mail_smtp_pass');
		$mail_smtp_tls = (getOption('mail_smtp_tls'));
		$mail_smtp_tls_list = buildSelect('mail_smtp_tls', 'mail_smtp_tls', array('', 'SSL', 'TLS'), $mail_smtp_tls);
		
		$mail_from = getOption('mail_from');
		
		/** Proxy Server Section */
		$proxy_enable = getOption('proxy_enable');
		if ($proxy_enable) {
			 $proxy_enable_checked = 'checked';
			 $fm_proxy_options_style = 'style="display: block;"';
		} else $proxy_enable_checked = $fm_proxy_options_style = null;
		
		$proxy_host = getOption('proxy_host');
		$proxy_port = getOption('proxy_port');
		$proxy_user = getOption('proxy_user');
		$proxy_pass = getOption('proxy_pass');
		
		/** Timestamp formatting */
		$timezone = getOption('timezone', $_SESSION['user']['account_id']);
		$timezone_list = $this->buildTimezoneList($timezone);
		
		$date_format = getOption('date_format', $_SESSION['user']['account_id']);
		$date_format_list = buildSelect('date_format[' . $_SESSION['user']['account_id'] . ']', 'date_format', $__FM_CONFIG['options']['date_format'], $date_format);
		
		$time_format = getOption('time_format', $_SESSION['user']['account_id']);
		$time_format_list = buildSelect('time_format[' . $_SESSION['user']['account_id'] . ']', 'time_format', $__FM_CONFIG['options']['time_format'], $time_format);
		
		/** Logging Method */
		$log_method = getOption('log_method');
		$log_method_list = buildSelect('log_method', 'log_method', $__FM_CONFIG['options']['log_method'], $log_method);
		$log_syslog_options_style = (!$log_method) ? 'style="display: none;"' : null;
		$syslog_facility = getOption('syslog_facility');
		$syslog_facilities = buildSelect('syslog_facility', 'syslog_facility', $__FM_CONFIG['options']['syslog_facilities'], $syslog_facility);
		
		/** Other Section */
		$show_errors = getOption('show_errors');
		$show_errors_checked = $show_errors ? 'checked' : null;
		
		$fm_temp_directory = getOption('fm_temp_directory');
		
		/** Software Update Section */
		if (!$software_update_interval = getOption('software_update_interval')) $software_update_interval = 'week';
		$software_update_list = buildSelect('software_update_interval', 'software_update_interval', $__FM_CONFIG['options']['software_update_interval'], $software_update_interval);
		if (!$software_update_tree = getOption('software_update_tree')) $software_update_tree = 'Stable';
		$software_update_tree_list = buildSelect('software_update_tree', 'software_update_tree', $__FM_CONFIG['options']['software_update_tree'], $software_update_tree);
		$software_update = getOption('software_update');
		if ($software_update) {
			$software_update_checked = 'checked';
			$software_update_options_style = 'style="display: block;"';
			if (currentUserCan('manage_modules')) {
				/** Get datetime formatting */
				$date_format = getOption('date_format', $_SESSION['user']['account_id']);
				$time_format = getOption('time_format', $_SESSION['user']['account_id']);

				$updates_last_checked = getOption('version_check', 0, $fm_name);
				$updates_last_checked = (intval($updates_last_checked['timestamp'])) ? sprintf('<b>%s:</b> %s', _('Last checked'), date($date_format . ' ' . $time_format . ' e', $updates_last_checked['timestamp'])) : null;
				$software_update_list .= sprintf('<p><a name="force_software_check" id="force_software_check" class="button click_once" >%s</a></p><p><i class="ok">%s</i></p>', _('Check Now'), $updates_last_checked);
			}
		} else $software_update_checked = $software_update_options_style = null;

		$ssh_user = getOption('ssh_user', $_SESSION['user']['account_id']);
		$sm_brand_img = getBrandLogo();

		$maintenance_mode = getOption('maintenance_mode');
		$maintenance_mode_checked = $maintenance_mode ? 'checked' : null;
		
		$return_form = '
		<form name="manage" id="manage" method="post" action="' . $GLOBALS['basename'] . '">
			<input type="hidden" name="item_type" value="fm_settings" />
			<input type="hidden" name="ldap_group_require" value="0" />
			<input type="hidden" name="login_message_accept" value="0" />
			<input type="hidden" name="client_auto_register" value="0" />
			<input type="hidden" name="api_token_support" value="0" />
			<input type="hidden" name="enforce_ssl" value="0" />
			<input type="hidden" name="mail_enable" value="0" />
			<input type="hidden" name="mail_smtp_auth" value="0" />
			<input type="hidden" name="mail_smtp_tls" value="0" />
			<input type="hidden" name="show_errors" value="0" />
			<input type="hidden" name="software_update" value="0" />
			<input type="hidden" name="maintenance_mode" value="0" />
			<div id="settings">
				<div id="settings-section">
					<div id="setting-row">
						<div class="description">
							<label for="auth_method">' . _('Authentication Method') . '</label>
							<p><i>' . _('None') . '</i> - ' . _('Authentication will not be used and all users will have full access.') . '</p>
							<p><i>' . _('Built-in Authentication') . '</i> - ' . sprintf(_('Users are authenticated against %s thus allowing them to have specific privileges within the application.'), $fm_name) . '</p>
							<p><i>' . _('LDAP Authentication') . '</i> - ' . _('Allows users to authenticate against a LDAP server. This option only appears if the PHP LDAP module is loaded.') . '</p>
						</div>
						<div class="choices">
							' . $auth_method_list . '
						</div>
					</div>
					<div id="auth_fm_options" ' . $auth_fm_options_style . '>
						<div id="setting-row">
							<div class="description">
								<label for="auth_fm_pw_strength">' . _('Password Strength') . '</label>
								' . $password_strength_descriptions . '
							</div>
							<div class="choices">
								' . $auth_fm_pw_strength_list . '
							</div>
						</div>
					</div>
					<div id="auth_ldap_options" ' . $auth_ldap_options_style . '>
						<div id="setting-row">
							<div class="description">
								<label for="ldap_server">' . _('LDAP Server') . '</label>
								<p>' . _('The DNS hostname or IP address of the server.') . '</p>
							</div>
							<div class="choices">
								<input name="ldap_server" id="ldap_server" type="text" value="' . $ldap_server . '" size="40" placeholder="ldap.example.com" />
							</div>
						</div>
						<div id="setting-row">
							<div class="description">
								<label for="ldap_port">' . _('Standard Port') . '</label>
								<p>' . _('TCP/UDP port for non-SSL communication.') . '</p>
							</div>
							<div class="choices">
								<input name="ldap_port" id="ldap_port" type="text" value="' . $ldap_port . '" size="10" placeholder="389" onkeydown="return validateNumber(event)" maxlength="5" max="65535" />
							</div>
						</div>
						<div id="setting-row">
							<div class="description">
								<label for="ldap_port_ssl">' . _('SSL Port') . '</label>
								<p>' . _('TCP/UDP port for SSL communication.') . '</p>
							</div>
							<div class="choices">
								<input name="ldap_port_ssl" id="ldap_port_ssl" type="text" value="' . $ldap_port_ssl . '" size="10" placeholder="636" onkeydown="return validateNumber(event)" maxlength="5" max="65535" />
							</div>
						</div>
						<div id="setting-row">
							<div class="description">
								<label for="ldap_version">' . _('Protocol Version') . '</label>
								<p>' . _('Protocol version the server supports.') . '</p>
							</div>
							<div class="choices">
								' . $ldap_version_list . '
							</div>
						</div>
						<div id="setting-row">
							<div class="description">
								<label for="ldap_encryption">' . _('Encryption') . '</label>
								<p>' . _('Encryption type the server supports. TLS is only supported by version 3.') . '</p>
							</div>
							<div class="choices">
								' . $ldap_encryption_list . '
							</div>
						</div>
						<div id="setting-row">
							<div class="description">
								<label for="ldap_cert_file">' . _('Certificate File') . '</label>
								<p>' . _('Local location of certificate.') . '</p>
							</div>
							<div class="choices">
								<input name="ldap_cert_file" id="ldap_cert_file" type="text" value="' . $ldap_cert_file . '" size="40" />
							</div>
						</div>
						<div id="setting-row">
							<div class="description">
								<label for="ldap_ca_cert_file">' . _('Root CA Certificate') . '</label>
								<p>' . _('Local location of root CA certificate.') . '</p>
							</div>
							<div class="choices">
								<input name="ldap_ca_cert_file" id="ldap_ca_cert_file" type="text" value="' . $ldap_ca_cert_file . '" size="40" />
							</div>
						</div>
						<div id="setting-row">
							<div class="description">
								<label for="ldap_referrals">' . _('Referrals') . '</label>
								<p>' . _('Enable or disable LDAP referrals.') . '</p>
							</div>
							<div class="choices">
								' . $ldap_referrals_list . '
							</div>
						</div>
						<div id="setting-row">
							<div class="description">
								<label for="ldap_dn">' . _('Distinguished Name (DN)') . '</label>
								<p>' . _('Distinguished Name syntax.') . '</p>
								<p><i>OpenLDAP</i> - uid={username},ou=people,dc=domain,dc=local<br />
								<i>Windows</i> - {username}@domain.local</p>
								<p><i>{username}</i> will be automatically replaced with the username entered at the login page.</p>
							</div>
							<div class="choices">
								<input name="ldap_dn" id="ldap_dn" type="text" value="' . $ldap_dn . '" size="40" placeholder="uid={username},ou=people,dc=domain,dc=local" />
							</div>
						</div>
						<div id="setting-row">
							<div class="description">
								<label for="ldap_group_require">' . _('Require Group Membership') . '</label>
								<p>' . _('Require user to be a member of a group to authenticate.') . '</p>
							</div>
							<div class="choices">
								<input name="ldap_group_require" id="ldap_group_require" type="checkbox" value="1" ' . $ldap_group_require_checked . ' /><label for="ldap_group_require">' . _('Require Group Membership') . '</label>
							</div>
						</div>
						<div id="ldap_group_require_options" ' . $ldap_group_require_options_style . '>
							<div id="setting-row">
								<div class="description">
									<label for="ldap_group_dn">' . _('Group Distinguished Name (DN)') . '</label>
									<p>' . _('Distinguished Name of the group the user must have membership of.') . '</p>
								</div>
								<div class="choices">
									<input name="ldap_group_dn" id="ldap_group_dn" type="text" value="' . $ldap_group_dn . '" size="40" />
								</div>
							</div>
							<div id="setting-row">
								<div class="description">
									<label for="ldap_group_attribute">' . _('Group Member Attribute') . '</label>
									<p>' . _('Name of the attribute that contains the usernames of the group members.') . '</p>
								</div>
								<div class="choices">
									<input name="ldap_group_attribute" id="ldap_group_attribute" type="text" value="' . $ldap_group_attribute . '" size="40" />
								</div>
							</div>
							<div id="setting-row">
								<div class="description">
									<label for="ldap_group_search_dn">' . _('Search Start DN') . '</label>
									<p>' . _('The DN to begin the user/group search from in the event searching the entire Active Directory is not allowed (optional).') . '</p>
								</div>
								<div class="choices">
									<input name="ldap_group_search_dn" id="ldap_group_search_dn" type="text" value="' . $ldap_group_search_dn . '" size="40" />
								</div>
							</div>
						</div>
						<div id="setting-row">
							<div class="description">
								<label for="ldap_user_template">' . _('User Template') . '</label>
								<p>' . sprintf(_('The name of the user account that %s will use as a template for new LDAP users.'), $fm_name) . '</p>
							</div>
							<div class="choices">
								' . $ldap_user_template_list . '
							</div>
						</div>
					</div>
					<div id="auth_message_option" ' . $auth_message_option_style . '>
						<div id="setting-row">
							<div class="description">
								<label for="login_message">' . _('Login Message') . '</label>
								<p>' . _('An optional message to display on the login page.') . '</p>
							</div>
							<div class="choices">
								<textarea name="login_message" id="login_message" type="text" cols="40">' . $login_message . '</textarea><br />
								<input name="login_message_accept" id="login_message_accept" type="checkbox" value="1" ' . $login_message_accept_checked . ' /><label for="login_message_accept">' . _('Require Users to Acknowledge Login Message') . '</label>
							</div>
						</div>
					</div>
					<div id="setting-row">
						<div class="description">
							<label for="client_auto_register">' . _('Automatic Client Registration') . '</label>
							<p>' . sprintf(_('Allow clients to automatically register with %s.'), $fm_name) . '</p>
						</div>
						<div class="choices">
							<input name="client_auto_register" id="client_auto_register" type="checkbox" value="1" ' . $client_auto_register_checked . ' /><label for="client_auto_register">' . _('Allow Automatic Client Registration') . '</label>
						</div>
					</div>
					<div id="setting-row">
						<div class="description">
							<label for="api_token_support">' . _('Enable API Token Support') . '</label>
							<p>' . _('Allow users to authenticate via API tokens. (https must be enforced)') . '</p>
						</div>
						<div class="choices">
							<input name="api_token_support" id="api_token_support" type="checkbox" value="1" ' . $api_token_support_checked . ' /><label for="api_token_support">' . _('Enable API') . '</label>
						</div>
					</div>
				</div>
				<div id="settings-section">
					<div id="setting-row">
						<div class="description">
							<label for="enforce_ssl">' . _('Enforce SSL') . '</label>
							<p>' . _('Attempt to auto-detect and redirect the user to https.') . '</p>
						</div>
						<div class="choices">
							<input name="enforce_ssl" id="enforce_ssl" type="checkbox" value="1" ' . $enforce_ssl_checked . ' /><label id="enforce_ssl_label" for="enforce_ssl" ' . $enforce_ssl_checked . '>' . _('Enforce SSL') . '</label>
						</div>
					</div>
					<div id="setting-row">
						<div class="description">
							<label for="fm_port_ssl">' . _('HTTPS Port') . '</label>
							<p>' . sprintf(_('The HTTPS TCP port %s runs on.'), $fm_name) . '</p>
						</div>
						<div class="choices">
							<input name="fm_port_ssl" id="fm_port_ssl" type="text" value="' . $fm_port_ssl . '" size="40" placeholder="443" onkeydown="return validateNumber(event)" maxlength="5" max="65535" />
						</div>
					</div>
				</div>
				<div id="settings-section">
					<div id="setting-row">
						<div class="description">
							<label for="mail_enable">' . _('Enable Mailing') . '</label>
							<p>' . sprintf(_('If this is unchecked, %s will never send an e-mail (including password reset links).'), $fm_name) . '</p>
						</div>
						<div class="choices">
							<input name="mail_enable" id="mail_enable" type="checkbox" value="1" ' . $mail_enable_checked . ' /><label for="mail_enable">' . _('Enable Mailing') . '</label>
						</div>
					</div>
					<div id="fm_mailing_options" ' . $fm_mailing_options_style . '>
						<div id="setting-row">
							<div class="description">
								<label for="mail_smtp_host">' . _('SMTP Hostnames') . '</label>
								<p>' . _('Main and backup SMTP servers to use (semi-colon delimited).') . '</p>
							</div>
							<div class="choices">
								<input name="mail_smtp_host" id="mail_smtp_host" type="text" value="' . $mail_smtp_host . '" size="40" placeholder="smtp1.domain.com;smtp2.domain.com" />
							</div>
						</div>
						<div id="setting-row">
							<div class="description">
								<label for="mail_smtp_port">' . _('Server Port') . '</label>
								<p>' . _('SMTP server port to connect to.') . '</p>
							</div>
							<div class="choices">
								<input name="mail_smtp_port" id="mail_smtp_port" type="text" value="' . $mail_smtp_port . '" size="40" placeholder="25" />
							</div>
						</div>
						
						<div id="setting-row">
							<div class="description">
								<label for="mail_smtp_auth">' . _('Enable SMTP Authentication') . '</label>
								<p>' . _('Use authentication with your SMTP server.') . '</p>
							</div>
							<div class="choices">
								<input name="mail_smtp_auth" id="mail_smtp_auth" type="checkbox" value="1" ' . $mail_smtp_auth_checked . ' /><label for="mail_smtp_auth">' . _('Enable SMTP Authentication') . '</label>
							</div>
						</div>
						<div id="mail_smtp_auth_options" ' . $mail_smtp_auth_options_style . '>
							<div id="setting-row">
								<div class="description">
									<label for="mail_smtp_user">' . _('SMTP Username') . '</label>
									<p>' . _('Username your SMTP server requires for authentication.') . '</p>
								</div>
								<div class="choices">
									<input name="mail_smtp_user" id="mail_smtp_user" type="text" value="' . $mail_smtp_user . '" size="40" placeholder="username" />
								</div>
							</div>
							<div id="setting-row">
								<div class="description">
									<label for="mail_smtp_pass">' . _('SMTP Password') . '</label>
									<p>' . _('Password your SMTP server requires for authentication.') . '</p>
								</div>
								<div class="choices">
									<input name="mail_smtp_pass" id="mail_smtp_pass" type="password" value="' . $mail_smtp_pass . '" size="40" placeholder="password" />
								</div>
							</div>
						</div>
						
						<div id="setting-row">
							<div class="description">
								<label for="mail_smtp_tls">' . _('Secure Connection') . '</label>
								<p>' . _('Use SSL or TLS with your SMTP server connection.') . '</p>
							</div>
							<div class="choices">
							' . $mail_smtp_tls_list . '
							</div>
						</div>
	
						<div id="setting-row">
							<div class="description">
								<label for="mail_from">' . _('Mail From') . '</label>
								<p>' . sprintf(_('E-mails sent by %s should be from this address. This only applies if the mailing method is enabled.'), $fm_name) . '</p>
							</div>
							<div class="choices">
								<input name="mail_from" id="mail_from" type="email" value="' . $mail_from . '" size="40" placeholder="noreply@mydomain.com" />
							</div>
						</div>

						<div id="setting-row">
							<div class="description">
								<label>' . _('Test Mail Settings') . '</label>
								<p>' . _('Test the entered mail settings to ensure functionality.') . '</p>
							</div>
							<div class="choices">
								' . sprintf('<p><a name="test_mail_settings" id="test_mail_settings" class="button">%s</a></p>', _('Test Now')) . '
							</div>
						</div>
					</div>
				</div>
				<div id="settings-section">
					<div id="setting-row">
						<div class="description">
							<label for="proxy_enable">' . _('Enable Proxy Server') . '</label>
							<p>' . _('Check this if a proxy server is required to access the Internet.') . '</p>
						</div>
						<div class="choices">
							<input name="proxy_enable" id="proxy_enable" type="checkbox" value="1" ' . $proxy_enable_checked . ' /><label for="proxy_enable">' . _('Enable Proxy Server') . '</label>
						</div>
					</div>
					<div id="fm_proxy_options" ' . $fm_proxy_options_style . '>
						<div id="setting-row">
							<div class="description">
								<label for="proxy_host">' . _('Proxy Server') . '</label>
								<p>' . _('Proxy server name to use.') . '</p>
							</div>
							<div class="choices">
								<input name="proxy_host" id="proxy_host" type="text" value="' . $proxy_host . '" size="40" placeholder="proxy1.domain.com" />
							</div>
						</div>
						<div id="setting-row">
							<div class="description">
								<label for="proxy_port">' . _('Proxy Port') . '</label>
								<p>' . _('The TCP port your proxy server uses.') . '</p>
							</div>
							<div class="choices">
								<input name="proxy_port" id="proxy_port" type="text" value="' . $proxy_port . '" size="40" placeholder="8080" onkeydown="return validateNumber(event)" maxlength="5" max="65535" />
							</div>
						</div>
						<div id="setting-row">
							<div class="description">
								<label for="proxy_user">' . _('Proxy Username') . '</label>
								<p>' . _('Username your proxy server requires for authentication.') . '</p>
							</div>
							<div class="choices">
								<input name="proxy_user" id="proxy_user" type="text" value="' . $proxy_user . '" size="40" placeholder="username" />
							</div>
						</div>
						<div id="setting-row">
							<div class="description">
								<label for="proxy_pass">' . _('Proxy Password') . '</label>
								<p>' . _('Password your proxy server requires for authentication.') . '</p>
							</div>
							<div class="choices">
								<input name="proxy_pass" id="proxy_pass" type="password" value="' . $proxy_pass . '" size="40" placeholder="password" />
							</div>
						</div>
					</div>
				</div>
				<div id="settings-section">
					<div id="setting-row">
						<div class="description">
							<label for="timezone">' . _('Timezone') . '</label>
							<p>' . _('Choose a city in the same timezone as you.') . '</p>
						</div>
						<div class="choices">
							' . $timezone_list . '
						</div>
					</div>
					<div id="setting-row">
						<div class="description">
							<label for="date_format">' . _('Date Format') . '</label>
							<p>' . _('The date format all timestamps will be displayed in. This includes all throughout the UI, log files, and configuration files.') . '</p>
						</div>
						<div class="choices">
							' . $date_format_list . '
						</div>
					</div>
					<div id="setting-row">
						<div class="description">
							<label for="time_format">' . _('Time Format') . '</label>
							<p>' . _('The time format all timestamps will be displayed in. This includes all throughout the UI, log files, and configuration files.') . '</p>
						</div>
						<div class="choices">
							' . $time_format_list . '
						</div>
					</div>
				</div>
				<div id="settings-section">
					<div id="setting-row">
						<div class="description">
							<label for="log_method">' . _('Logging Method') . '</label>
							<p>' . sprintf(_('Where to send %s log messages.'), $fm_name) . '</p>
						</div>
						<div class="choices">
							' . $log_method_list . '
						</div>
					</div>
					<div id="log_syslog_options" ' . $log_syslog_options_style . '>
						<div id="setting-row">
							<div class="description">
								<label for="software_update_tree">' . _('Syslog Facility') . '</label>
								<p>' . sprintf(_('The syslog facility %s should send log messages to.'), $fm_name) . '</p>
							</div>
							<div class="choices">
								' . $syslog_facilities . '
							</div>
						</div>
					</div>
				</div>
				<div id="settings-section">
					<div id="setting-row">
						<div class="description">
							<label for="show_errors">' . _('Show Errors') . '</label>
							<p>' . sprintf(_('If this is checked, %s will display application errors when they occur.'), $fm_name) . '</p>
						</div>
						<div class="choices">
							<input name="show_errors" id="show_errors" type="checkbox" value="1" ' . $show_errors_checked . ' /><label for="show_errors">' . _('Show Errors') . '</label>
						</div>
					</div>
					<div id="setting-row">
						<div class="description">
							<label for="fm_temp_directory">' . _('Temporary Directory') . '</label>
							<p>' . sprintf(_('Temporary directory on %s to use for scratch files (must be writeable by %s).'), php_uname('n'), $__FM_CONFIG['webserver']['user_info']['name']) . '</p>
						</div>
						<div class="choices">
							<input name="fm_temp_directory" id="fm_temp_directory" type="text" value="' . $fm_temp_directory . '" size="40" placeholder="$tmp" />
						</div>
					</div>
				</div>
				<div id="settings-section">
					<div id="setting-row">
						<div class="description">
							<label for="software_update">' . _('Software Update') . '</label>
							<p>' . sprintf(_('If this is checked, %s will automatically check for updates.'), $fm_name) . '</p>
						</div>
						<div class="choices">
							<input name="software_update" id="software_update" type="checkbox" value="1" ' . $software_update_checked . ' /><label for="software_update">' . _('Check for Updates') . '</label>
						</div>
					</div>
					<div id="software_update_options" ' . $software_update_options_style . '>
						<div id="setting-row">
							<div class="description">
								<label for="software_update_tree">' . _('Software Tree') . '</label>
								<p>' . sprintf(_('The minimum software tree %s should check updates for.'), $fm_name) . '</p>
							</div>
							<div class="choices">
								' . $software_update_tree_list . '
							</div>
						</div>
						<div id="setting-row">
							<div class="description">
								<label for="software_update_interval">' . _('Update Check Interval') . '</label>
								<p>' . sprintf(_('The frequency %s should check for updates.'), $fm_name) . '</p>
							</div>
							<div class="choices">
								' . $software_update_list . '
							</div>
						</div>
					</div>
				</div>
				<div id="settings-section">
					<div id="setting-row">
						<div class="description">
							<label>' . _('SSH Username') . '</label>
							<p>' . _('The local system user to use for client interaction via SSH.') . '</p>
						</div>
						<div class="choices">
							<input name="ssh_user[' . $_SESSION['user']['account_id'] . ']" id="ssh_user" type="text" value="' . $ssh_user . '" size="40" placeholder="fm_user" />
						</div>
					</div>
					<div id="setting-row">
						<div class="description">
							<label>' . _('SSH Key Pair') . '</label>
							<p>' . _('If a ssh key pair is generated, clients can be updated over ssh.') . '</p>
						</div>
						<div id="gen_ssh_action" class="choices">
							' . $sshkey_button . '
						</div>
					</div>
				</div>
				<div id="settings-section">
					<div id="setting-row">
						<div class="description">
							<span id="brand_img"><img src="' . $sm_brand_img . '" /></span>
							<label>' . _('Image Branding') . '</label>
							<p>' . _('Rebrand this installation with your image.<br />(Recommended size: 48px x 48px)') . '</p>
						</div>
						<div class="choices">
							<input name="sm_brand_img" id="sm_brand_img" type="text" value="' . $sm_brand_img . '" size="40" placeholder="path/to/image" />
						</div>
					</div>
				</div>
				<div id="settings-section">
					<div id="setting-row">
						<div class="description">
							<label for="maintenance_mode">' . _('Enable Maintenance Mode') . '</label>
							<p>' . sprintf(_('This allows only users with Super Admin or Module Management privileges to authenticate.'), $fm_name) . '</p>
						</div>
						<div class="choices">
							<input name="maintenance_mode" id="maintenance_mode" type="checkbox" value="1" ' . $maintenance_mode_checked . ' /><label for="maintenance_mode">' . _('Enable Maintenance Mode') . '</label>
						</div>
					</div>
				</div>
			' . $save_button . '
			</div>
		</form>';

		return $return_form;
	}
	
	
	/**
	 * Builds a timezone list
	 *
	 * @since 1.0
	 * @package facileManager
	 */
	function buildTimezoneList($selected_zone = null) {
		$structure[] = '<select name="timezone[' . $_SESSION['user']['account_id'] . ']" id="timezone">';

		$i = 0;
		foreach (timezone_identifiers_list() as $zone) {
			$zone = explode('/', $zone);
			
			$zonen[$i]['continent'] = isset($zone[0]) ? $zone[0] : '';
			$zonen[$i]['city'] = isset($zone[1]) ? $zone[1] : '';
			$zonen[$i]['subcity'] = isset($zone[2]) ? $zone[2] : '';
			$i++;
		}
		asort($zonen);
		
		foreach ($zonen as $key => $zone) {
			if (!isset($current_continent)) {
				$structure[] = '<optgroup label="' . $zone['continent'] . '">';
			} elseif ($current_continent != $zone['continent']) {
				$structure[] = '</optgroup>';
				$structure[] = '<optgroup label="' . $zone['continent'] . '">';
			}
			$current_continent = $zone['continent'];
			$value = array($zone['continent']);
			
			if (empty($zone['city'])) {
				$display = str_replace('_', ' ', $zone['continent']);
			} else {
				$value[] = $zone['city'];
				$display = str_replace('_', ' ', $zone['city']);
				
				if (!empty($zone['subcity'])) {
					$value[] = $zone['subcity'];
					$display .= ' - ' . str_replace('_', ' ', $zone['subcity']);
				}
			}
			
			$value = implode('/', $value);
			$selected = '';
			if ($value === $selected_zone) {
				$selected = 'selected="selected" ';
			}
			$structure[] = '<option ' . $selected . 'value="' . $value . '">' . $display . '</option>';
		}
		
		$structure[] = '</select>';
		
		return implode("\n", $structure);
	}
	
	
	/**
	 * Builds a user list
	 *
	 * @since 1.0
	 * @package facileManager
	 */
	function buildUserList() {
		global $fmdb;
		
		basicGetList('fm_users', 'user_login', 'user_', $sql = 'AND user_auth_type<2');
		
		$user_list[0][] = _('None');
		$user_list[0][] = 0;
		
		$user_result = $fmdb->last_result;
		for ($i=1; $i<=$fmdb->num_rows; $i++) {
			$user_list[$i][] = $user_result[$i-1]->user_login;
			$user_list[$i][] = $user_result[$i-1]->user_id;
		}
		
		return $user_list;
	}
	
}

if (!isset($fm_settings))
	$fm_settings = new fm_settings();
