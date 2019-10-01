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
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/                                           |
 +-------------------------------------------------------------------------+
*/

class fm_login {
	
	/**
	 * Displays the login form
	 *
	 * @since 1.0
	 * @package facileManager
	 *
	 * @return string
	 */
	function printLoginForm() {
		global $fm_name;
		
		printHeader(_('Login'), 'login');
		
		/** Cannot change password without mail_enable defined */
		$mail_enable = (getOption('fm_db_version') >= 18) ? getOption('mail_enable') : false;
		$auth_method = (getOption('fm_db_version') >= 18) ? getOption('auth_method') : false;
		$forgot_link = ($mail_enable && $auth_method == 1) ? sprintf('<p id="forgotton_link"><a href="?forgot_password">%s</a></p>', _('Forgot your password?')) : null;
		
		$branding_logo = getBrandLogo();
		
		$login_message = getOption('login_message');
		if ($login_message) {
			$login_message = '<p class="success">' . $login_message . '</p>';
		}

		printf('<form id="loginform" action="%1$s" method="post">
		<div id="fm-branding">
			<img src="%2$s" /><span>%3$s</span>
		</div>
		<div id="login_form">
		<table>
			<tr>
				<td>
					<div class="input-wrapper">
						<input type="text" size="25" name="username" id="username" placeholder="%4$s" />
					</div>
				</td>
				<td>
					<div class="input-wrapper">
						<input type="password" size="25" name="password" id="password" placeholder="%5$s" />
					</div>
				</td>
				<td><input name="submit" id="loginbtn" type="submit" value="%3$s" class="button" /></td>
			</tr>
		</table>
		%6$s
		<div id="message">%7$s</div>
		</form>
		</div>', $_SERVER['REQUEST_URI'], $branding_logo, _('Login'), _('Username'),
				_('Password'), $forgot_link, $login_message);
		
		exit(printFooter());
	}
	
	
	/**
	 * Display password reset user form.
	 *
	 * @since 1.0
	 * @package facileManager
	 *
	 * @param string $message Message to display to the user
	 * @return string
	 */
	function printUserForm($message = null) {
		/** Should not be here if there is no mail_enable defined or if not using builtin auth */
		if (!getOption('mail_enable') || getOption('auth_method') != 1) {
			header('Location: ' . $GLOBALS['RELPATH']);
			exit;
		}

		global $fm_name;
		printHeader(_('Password Reset'), 'login');
		
		$branding_logo = getBrandLogo();
		
		printf('<form id="loginform" action="%s?forgot_password" method="post">
		<input type="hidden" name="reset_pwd" value="1" />
		<div id="fm-branding">
			<img src="%s" /><span>%s</span>
		</div>
		<div id="login_form">
		<table>
			<tr>
				<td>
					<div class="input-wrapper">
						<input type="text" name="user_login" id="user_login" placeholder="%s" style="width: 400px;" />
					</div>
				</td>
				<td><input name="submit" id="forgotbtn" type="submit" value="%s" class="button" /></td>
			</tr>
		</table>
		<p id="forgotton_link"><a href="%s">&larr; %s</a></p>
		<div id="message">%s</div>
		</form>
		</div>', $_SERVER['PHP_SELF'], $branding_logo, _('Reset Password'), _('Username'),
				_('Submit'), $GLOBALS['RELPATH'], _('Login form'), $message);
	}
	
		
	/**
	 * Process password reset user form.
	 *
	 * @since 1.0
	 * @package facileManager
	 *
	 * @param string $user_login Username to authenticate
	 * @return boolean
	 */
	function processUserPwdResetForm($user_login = null) {
		global $fmdb;
		
		if (empty($user_login)) return;
		
		$user_info = getUserInfo(sanitize($user_login), 'user_login');
		
		/** If the user is not found, just return lest we give away valid user accounts */
		if ($user_info == false) return true;
		
		$fm_login = $user_info['user_id'];
		$uniqhash = genRandomString(mt_rand(30, 50));
		
		$query = "INSERT INTO fm_pwd_resets VALUES ('$uniqhash', '$fm_login', " . time() . ")";
		$fmdb->query($query);
		
		if (!$fmdb->rows_affected) return false;
		
		/** Mail the reset link */
		$mail_enable = getOption('mail_enable');
		if ($mail_enable) {
			$result = $this->mailPwdResetLink($fm_login, $uniqhash);
			if ($result !== true) {
				$query = "DELETE FROM fm_pwd_resets WHERE pwd_id='$uniqhash' AND pwd_login='$fm_login'";
				$fmdb->query($query);
		
				return $result;
			}
		}
		
		return true;
	}
	
	
	/**
	 * Checks if the user is authenticated
	 *
	 * @since 1.0
	 * @package facileManager
	 *
	 * @return boolean
	 */
	function isLoggedIn() {
		global $fm_name;
		
		if (defined('INSTALL')) return false;
		
		/** No auth_method defined */
		if (getOption('fm_db_version') >= 18) {
			if (!getOption('auth_method')) {
				if (!isset($_COOKIE['fmid'])) {
					@session_start();
	
					$_SESSION['user']['logged_in'] = true;
					$_SESSION['user']['id'] = 1;
					$_SESSION['user']['account_id'] = 1;
		
					$modules = getActiveModules(true);
					if (!isset($_SESSION['module'])) {
						$_SESSION['module'] = (is_array($modules) && count($modules)) ? $modules[0] : $fm_name;
					}
	
					setcookie('fmid', session_id(), strtotime('+1 year'));
				}
				
				session_set_cookie_params(strtotime('+1 year'));
				if (!empty($_COOKIE['fmid'])) {
					@session_id($_COOKIE['fmid']);
					@session_start();
				}
				session_write_close();
	
				return true;
			}
		}

		/** Auth method defined so let's validate */
		if (isset($_COOKIE['fmid'])) {
			$fmid = $_COOKIE['fmid'];
				
			/** Init the session. */
			session_set_cookie_params(strtotime('+1 week'));
			session_id($fmid);
			@session_start();
				
			/** Check if they're logged in. */
			if (isset($_SESSION['user']['logged_in']) && $_SESSION['user']['logged_in']) {
				/** Set the last login info */
				if (strtotime("-1 hour") > $_SESSION['user']['last_login']) {
					$_SESSION['user']['last_login'] = strtotime("-15 minutes");
					$_SESSION['user']['ipaddr'] = isset($_SERVER['REMOTE_HOST']) ? $_SERVER['REMOTE_HOST'] : $_SERVER['REMOTE_ADDR'];
				}
				
				/** Should the user be logged in? */
				if (getNameFromID($_SESSION['user']['id'], 'fm_users', 'user_', 'user_id', 'user_status') != 'active') {
					session_write_close();
					header('Location: ' . $GLOBALS['RELPATH'] . '?logout');
					return false;
				}
				session_write_close();
				
				return true;
			}
			session_write_close();
		}
		return false;
	}
	
	/**
	 * Do the authentication
	 *
	 * @since 1.0
	 * @package facileManager
	 *
	 * @param string $user_login Username to authenticate
	 * @param string $pass Password to authenticate with
	 * @param boolean $encrypted Whether or not the password is already encrypted
	 * @return boolean
	 */
	function checkPassword($user_login, $pass, $encrypted = false) {
		global $fmdb, $__FM_CONFIG, $fm_name;
		
		if (empty($user_login) || empty($pass)) return false;
		
		/** Built-in authentication */
		$fm_db_version = getOption('fm_db_version');
		$auth_method = ($fm_db_version >= 18) ? getOption('auth_method') : true;
		if ($auth_method) {
			/** Default Super Admin? */
			$result = $fmdb->query("SELECT * FROM `fm_users` WHERE `user_auth_type`=1 ORDER BY user_id ASC LIMIT 1");
			if ($fmdb->last_result[0]->user_login == $user_login) {
				$auth_method = 1;
			}

			/** Builtin Authentication */
			if ($auth_method == 1) {
				$pwd_query = ($encrypted) ? "'$pass'" : "password('$pass')";
		
				if ($fm_db_version >= 18) {
					$result = $fmdb->get_results("SELECT * FROM `fm_users` WHERE `user_status`='active' AND `user_auth_type`=1 AND `user_template_only`='no' AND `user_login`='$user_login'");
				} else {
					/** Old auth */
					$result = $fmdb->get_results("SELECT * FROM `fm_users` WHERE `user_status`='active' AND `user_login`='$user_login' AND `user_password`=" . $pwd_query);
				}
				if (!$fmdb->num_rows) {
					if (useMySQLi()) {
						@mysqli_free_result($result);
					} else {
						@mysql_free_result($result);
					}
					return false;
				} else {
					$user = $fmdb->last_result[0];
					
					/** Check password */
					if ($user->user_password[0] == '*') {
						/** Old MySQL hashing that needs to change */
						if ($user->user_password != '*' . strtoupper(sha1(sha1($pass, true)))) {
							return false;
						}
						resetPassword($user_login, $pass);
					} else {
						/** PHP hashing */
						if (!password_verify($pass, $user->user_password)) {
							return false;
						}
					}
					
					/** Enforce password change? */
					if ($fm_db_version >= 15) {
						if ($user->user_force_pwd_change == 'yes') {
							$pwd_reset_query = "SELECT * FROM `fm_pwd_resets` WHERE `pwd_login`={$user->user_id} ORDER BY `pwd_timestamp` LIMIT 1";
							$fmdb->get_results($pwd_reset_query);
							if ($fmdb->num_rows) {
								$reset = $fmdb->last_result[0];
								return array($reset->pwd_id, $user_login);
							}
						}
					}
			
					$this->setSession($user);
			
					if (useMySQLi()) {
						@mysqli_free_result($result);
					} else {
						@mysql_free_result($result);
					}
					
					return true;
				}
			/** LDAP Authentication */
			} else {
				return $this->doLDAPAuth($user_login, $pass);
			}
		}
		
		return false;
	}
	
	
	/**
	 * Update the session in the db
	 *
	 * @since 1.0
	 * @package facileManager
	 *
	 * @param string $fm_login Username to update the database with
	 * @return null
	 */
	function updateSessionDB($fm_login) {
		global $fmdb;
		
		$query = "UPDATE fm_users set user_ipaddr='{$_SESSION['user']['ipaddr']}', user_last_login=" . time() . " WHERE `user_login`='". $fm_login ."' AND `user_status`!='deleted'";
		$fmdb->query($query);
	}


	/**
	 * Logout the user
	 *
	 * @since 1.0
	 * @package facileManager
	 *
	 * @return null
	 */
	function logout() {
		if (isset($_COOKIE['fmid'])) {
			$fmid = $_COOKIE['fmid'];
			
			// Init the session.
			session_id($fmid);
			@session_start();
			$this->updateSessionDB($_SESSION['user']['name']);
			@session_unset();
			setcookie('fmid', '');
			@session_destroy();
			unset($_COOKIE['fmid']);
		}
	}
	
	/**
	 * Mail the user password reset link
	 *
	 * @since 1.0
	 * @package facileManager
	 *
	 * @param string $fm_login Username to send the mail to
	 * @param string $uniq_hash Unique password reset hash
	 * @return boolean
	 */
	function mailPwdResetLink($fm_login, $uniq_hash) {
		global $fm_name;
		
		$user_info = getUserInfo($fm_login);
		if (isEmailAddressValid($user_info['user_email']) === false) return _('There is no valid e-mail address associated with this user.');
		
		$subject = sprintf(_('%s Password Reset'), $fm_name);
		$from = getOption('mail_from');
		
		return sendEmail($user_info['user_email'], $subject, $this->buildPwdResetEmail($user_info, $uniq_hash, true, $subject, $from), $this->buildPwdResetEmail($user_info, $uniq_hash, false));
	}
	
	/**
	 * Builds the user password reset link email
	 *
	 * @since 1.0
	 * @package facileManager
	 *
	 * @param array $user_info User information to build the email from
	 * @param string $uniq_hash Unique password reset hash
	 * @param boolean $build_html Whether or not to build a html version
	 * @param string $title HTML Email title
	 * @param string $from_address Displayed sent from address
	 * @return string
	 */
	function buildPwdResetEmail($user_info, $uniq_hash, $build_html = true, $title = null, $from_address = null) {
		global $fm_name, $__FM_CONFIG;
		
		if ($build_html) {
			$branding_logo = $GLOBALS['FM_URL'] . str_replace('//', '/', str_replace($GLOBALS['RELPATH'], '', getBrandLogo()));
			
			$body = <<<BODY
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" style="background-color: #eeeeee;">
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<title>$title</title>
</head>
<body style="background-color: #eeeeee; font: 13px 'Lucida Grande', 'Lucida Sans Unicode', Tahoma, Verdana, sans-serif; margin: 1em auto; min-width: 600px; max-width: 600px; padding: 20px; padding-bottom: 50px; -webkit-text-size-adjust: none;">
<div style="margin-bottom: -8px;">
<img src="$branding_logo" style="padding-left: 17px;" />
<span style="font-size: 16pt; font-weight: bold; position: relative; top: -16px; margin-left: 10px;">$fm_name</span>
</div>
<div id="shadow" style="-moz-border-radius: 0% 0% 100% 100% / 0% 0% 8px 8px; -webkit-border-radius: 0% 0% 100% 100% / 0% 0% 8px 8px; border-radius: 0% 0% 100% 100% / 0% 0% 8px 8px; -moz-box-shadow: rgba(0,0,0,.30) 0 2px 3px !important; -webkit-box-shadow: rgba(0,0,0,.30) 0 2px 3px !important; box-shadow: rgba(0,0,0,.30) 0 2px 3px !important;">
<div id="container" style="background-color: #fff; min-height: 200px; margin-top: 1em; padding: 0 1.5em .5em; border: 1px solid #fff; -webkit-border-radius: 5px; -moz-border-radius: 5px; border-radius: 5px; -webkit-box-shadow: inset 0 2px 1px rgba(255,255,255,.97) !important; -moz-box-shadow: inset 0 2px 1px rgba(255,255,255,.97) !important; box-shadow: inset 0 2px 1px rgba(255,255,255,.97) !important;">
<p>Hi {$user_info['user_login']},</p>
<p>You (or somebody else) has requested a link to reset your $fm_name password.</p>
<p>If you don't want to reset your password, then you can ignore this message.</p>
<p>To reset your password, click the following link:<br />
<<a href="{$GLOBALS['FM_URL']}password_reset.php?key=$uniq_hash&login={$user_info['user_login']}">{$GLOBALS['FM_URL']}password_reset.php?key=$uniq_hash&login={$user_info['user_login']}</a>></p>
<p>This link expires in {$__FM_CONFIG['clean']['time']}.</p>
</div>
</div>
<p style="font-size: 10px; color: #888; text-align: center;">$fm_name | $from_address</p>
</body>
</html>
BODY;
		} else {
			$body = sprintf('Hi %s,

You (or somebody else) has requested a link to reset your %s password.

If you don\'t want to reset your password, then you can ignore this message.

To reset your password, click the following link:

%s',
	$user_info['user_login'], $fm_name, "{$GLOBALS['FM_URL']}password_reset.php?key=$uniq_hash&login={$user_info['user_login']}");
		}
		
		return $body;
	}
	
	/**
	 * Sets the session variables for the authenticated user
	 *
	 * @since 1.0
	 * @package facileManager
	 *
	 * @param object $user User information to create session variables from
	 * @return null
	 */
	function setSession($user) {
		global $fm_name;
		
		@session_start();
		session_regenerate_id(true);
		$_SESSION['user']['logged_in'] = true;
		$_SESSION['user']['id'] = $user->user_id;
		$_SESSION['user']['name'] = $user->user_login;
		$_SESSION['user']['last_login'] = $user->user_last_login;
		$_SESSION['user']['account_id'] = $user->account_id;
		$_SESSION['user']['ipaddr'] = isset($_SERVER['REMOTE_HOST']) ? $_SERVER['REMOTE_HOST'] : $_SERVER['REMOTE_ADDR'];
		
		/** Upgrade compatibility */
		if (getOption('fm_db_version') < 32) $_SESSION['user']['fm_perms'] = $user->user_perms;

		setUserModule($user->user_default_module);
		setcookie('fmid', session_id(), strtotime('+1 week'));
		$this->updateSessionDB($_SESSION['user']['name']);
		session_write_close();
	}
	
	
	/**
	 * Performs the LDAP authentication procedure
	 *
	 * @since 1.0
	 * @package facileManager
	 *
	 * @param string $username Username to authenticate
	 * @param string $password Username to authenticate with
	 * @return boolean
	 */
	function doLDAPAuth($username, $password) {
		global $fmdb;

		/** Get LDAP variables */
		if (empty($ldap_server))          $ldap_server          = getOption('ldap_server');
		if (empty($ldap_port))            $ldap_port            = getOption('ldap_port');
		if (empty($ldap_port_ssl))        $ldap_port_ssl        = getOption('ldap_port_ssl');
		if (empty($ldap_version))         $ldap_version         = getOption('ldap_version');
		if (empty($ldap_encryption))      $ldap_encryption      = getOption('ldap_encryption');
		if (empty($ldap_referrals))       $ldap_referrals       = getOption('ldap_referrals');
		if (empty($ldap_dn))              $ldap_dn              = getOption('ldap_dn');
		if (empty($ldap_group_require))   $ldap_group_require   = getOption('ldap_group_require');
		if (empty($ldap_group_dn))        $ldap_group_dn        = getOption('ldap_group_dn');
		if (empty($ldap_group_attribute)) $ldap_group_attribute = getOption('ldap_group_attribute');

		$ldap_dn = str_replace('<username>', $username, $ldap_dn);

		if ($ldap_encryption == 'SSL') {
			$ldap_connect = @ldap_connect('ldaps://' . $ldap_server, $ldap_port_ssl);
		} else {
			$ldap_connect = @ldap_connect($ldap_server, $ldap_port);
		}
		
		if ($ldap_connect) {
			/** Set protocol version */
			if (!@ldap_set_option($ldap_connect, LDAP_OPT_PROTOCOL_VERSION, $ldap_version)) {
				$this->closeLDAPConnect($ldap_connect);
				return false;
			}
			
			/** Set referrals */
			if(!@ldap_set_option($ldap_connect, LDAP_OPT_REFERRALS, $ldap_referrals)) {
				$this->closeLDAPConnect($ldap_connect);
				return false;
			}
			
			/** Start TLS if requested */
			if ($ldap_encryption == 'TLS') {
				if (!@ldap_start_tls($ldap_connect)) {
					$this->closeLDAPConnect($ldap_connect);
					return false;
				}
			}
			
			$ldap_bind = @ldap_bind($ldap_connect, $ldap_dn, $password);
			
			if ($ldap_bind) {
				if ($ldap_group_require) {
					if (strpos($ldap_dn, '@') !== false) {
						/** Convert AD ldap_dn to real ldap_dn */
						$ldap_dn_parts = explode('@', $ldap_dn);
						$ldap_dn = 'dc=' . join(',dc=', explode('.', $ldap_dn_parts[1]));
						
						/** Process AD group membership */
						$ldap_group_response = $this->checkGroupMembership($ldap_connect, $this->getDN($ldap_connect, $username, $ldap_dn), $ldap_group_dn, $ldap_group_attribute);
					} else {
						/** Process LDAP group membership */
						$ldap_group_response = @ldap_compare($ldap_connect, $ldap_group_dn, $ldap_group_attribute, $username);
					}
					
					if ($ldap_group_response !== true) {
						$this->closeLDAPConnect($ldap_connect);
						return false;
					}
				}
				
				/** Get user permissions from database */
				$fmdb->get_results("SELECT * FROM `fm_users` WHERE `user_status`='active' AND `user_auth_type`=2 AND `user_template_only`='no' AND `user_login`='$username'");
				if (!$fmdb->num_rows) {
					if (!$this->createUserFromTemplate($username)) {
						$this->closeLDAPConnect($ldap_connect);
						return false;
					}
				}
				
				$this->setSession($fmdb->last_result[0]);

				$this->closeLDAPConnect($ldap_connect);
				
				return true;
			}
			
			/** Close LDAP connection */
			$this->closeLDAPConnect($ldap_connect);
		}
		
		return false;
	}
	
	
	/**
	 * Creates a LDAP user from the defined template user
	 *
	 * @since 1.0
	 * @package facileManager
	 *
	 * @param string $username Username to create
	 * @return boolean
	 */
	function createUserFromTemplate($username) {
		global $fmdb;
		
		$template_user_id = getOption('ldap_user_template');
		
		/** User does not exist in database - get the template user */
		$result = $fmdb->get_results("SELECT * FROM `fm_users` WHERE `user_id` = " . $template_user_id);
		if (!$fmdb->num_rows) return false;
		
		/** Attempt to add the new LDAP user to the database based on the template */
		$fmdb->query("INSERT INTO `fm_users` (`account_id`,`user_login`, `user_password`, `user_email`, `user_default_module`, `user_auth_type`, `user_caps`) 
					SELECT `account_id`, '$username', '', '', `user_default_module`, 2, `user_caps` from `fm_users` WHERE `user_id`=" . $template_user_id);
		if (!$fmdb->rows_affected) return false;
		
		/** Get the user results now */
		$fmdb->get_results("SELECT * FROM `fm_users` WHERE `user_status`='active' AND `user_auth_type`=2 AND `user_template_only`='no' AND `user_login`='$username'");
		if (!$fmdb->num_rows) return false;
		
		return true;
	}


	/**
	 * Closes a LDAP resource
	 *
	 * @since 3.0
	 * @package facileManager
	 *
	 * @param resource $ldap_connect Resource to close
	 * @return boolean
	 */
	private function closeLDAPConnect($ldap_connect) {
		if (is_resource($ldap_connect)) {
			@ldap_close($ldap_connect);
		}
	}
	
	
	/**
	 * Gets the DN of an account name
	 * (based on user comment from http://php.net/manual/en/ref.ldap.php#99347)
	 *
	 * @since 3.0
	 * @package facileManager
	 *
	 * @param resource $ldap_connect Resource to use
	 * @param string $samaccountname SAM Account name to search for
	 * @param string $basedn Base DN to use
	 * @return boolean
	 */
	private function getDN($ldap_connect, $samaccountname, $basedn) {
		$attributes = array('dn');
		$result = ldap_search($ldap_connect, $basedn,
			"(samaccountname={$samaccountname})", $attributes);
		if ($result === false) return '';
		$entries = ldap_get_entries($ldap_connect, $result);
		return ($entries['count']>0) ? $entries[0]['dn'] : '';
	}
	
	
	/**
	 * Checks recursive group membership of the user
	 * (based on user comment from http://php.net/manual/en/ref.ldap.php#99347)
	 *
	 * @since 3.0
	 * @package facileManager
	 *
	 * @param resource $ldap_connect Resource to use
	 * @param string $userdn
	 * @param string $groupdn
	 * @param string $ldap_group_attribute
	 * @return boolean
	 */
	private function checkGroupMembership($ldap_connect, $userdn, $groupdn, $ldap_group_attribute) {
		$result = ldap_read($ldap_connect, $userdn, '(objectclass=*)', array($ldap_group_attribute));
		if ($result === false) return false;
		
		$entries = ldap_get_entries($ldap_connect, $result);
		if ($entries['count'] <= 0) return false;
		
		if (empty($entries[0][$ldap_group_attribute])) {
			return false;
		} else {
			for ($i = 0; $i < $entries[0][$ldap_group_attribute]['count']; $i++) {
				if ($entries[0][$ldap_group_attribute][$i] == $groupdn) return true;
				elseif ($this->checkGroupMembership($ldap_connect, $entries[0][$ldap_group_attribute][$i], $groupdn)) return true;
			};
		};
		return false;
	}
	
}

if (!isset($fm_login))
	$fm_login = new fm_login();

?>
