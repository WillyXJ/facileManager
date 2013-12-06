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
		printHeader('Login', 'install');
		
		/** Cannot change password without mail_enable defined */
		$mail_enable = (getOption('fm_db_version') >= 18) ? getOption('mail_enable') : false;
		$auth_method = (getOption('fm_db_version') >= 18) ? getOption('auth_method') : false;
		$forgot_link = ($mail_enable && $auth_method == 1) ? '<p id="forgotton_link"><a href="?forgot_password">Forgot your password?</a></p>' : null;

		echo <<<HTML
		<form id="loginform" action="{$_SERVER['REQUEST_URI']}" method="post">
		<center>
		<div id="message"></div>
		<div id="login_form">
		<table class="form-table">
			<tr>
				<th><label for="username">Username:</label></th>
				<td><input type="text" size="25" name="username" id="username" placeholder="username" /></td>
			</tr>
			<tr>
				<th><label for="password">Password:</label></th>
				<td>
					<input type="password" size="25" name="password" id="password" placeholder="password" />
					$forgot_link
				</td>
			</tr>
		</table>
		</center>
		<p class="step"><input name="submit" id="loginbtn" type="submit" value="Login" class="button" /></p>
		</form>
		</div>
	
HTML;
		
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
		if (!getOption('mail_enable') || getOption('auth_method') != 1) header('Location: ' . $GLOBALS['RELPATH']);

		printHeader('Password Reset', 'install');
		
		echo <<<HTML
		<form id="forgotpwd" method="post" action="{$_SERVER['PHP_SELF']}?forgot_password">
			<input type="hidden" name="reset_pwd" value="1" />
			<center>
			<div id="message">$message</div>
			<p>Please enter your username and a password reset link will be emailed to the address on file:</p>
			<table class="form-table">
				<tr>
					<th>Username</th>
					<td>
						<input type="text" size="25" name="user_login" id="user_login" value="" placeholder="username" />
						<p id="forgotton_link"><a href="{$GLOBALS['RELPATH']}">&larr; Login form</a></p>
					</td>
				</tr>
			</table>
			</center>
			<p class="step"><input id="forgotbtn" name="submit" type="submit" value="Submit" class="button" /></p>
		</form>
	
HTML;
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
		
		$query = "INSERT INTO fm_pwd_resets VALUES ('$uniqhash', '$fm_login', " . time() . ");";
		$fmdb->query($query);
		
		if (!$fmdb->rows_affected) return false;
		
		/** Mail the reset link */
		$mail_enable = getOption('mail_enable');
		if ($mail_enable) {
			$result = $this->mailPwdResetLink($fm_login, $uniqhash);
			if ($result !== true) {
				$query = "DELETE FROM fm_pwd_resets WHERE pwd_id='$uniqhash' AND pwd_login='$fm_login';";
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
				if (!isset($_COOKIE['myid'])) {
					session_set_cookie_params(time() + 60 * 60 * 24 * 7);
					@session_start();
	
					$_SESSION['user']['logged_in'] = true;
					$_SESSION['user']['id'] = 1;
					$_SESSION['user']['fm_perms'] = 1;
					$_SESSION['user']['account_id'] = 1;
					$_SESSION['user']['module_perms']['perm_value'] = 0;
		
					$modules = getActiveModules(true);
					if (!isset($_SESSION['module'])) {
						$_SESSION['module'] = (is_array($modules) && count($modules)) ? $modules[0] : $fm_name;
					}
	
					setcookie('myid', session_id(), time() + 60 * 60 * 24 * 7);
				}
				
				session_set_cookie_params(time() + 60 * 60 * 24 * 7);
				@session_id($_COOKIE['myid']);
				@session_start();
	
				return true;
			}
		}

		/** Auth method defined so let's validate */
		if (isset($_COOKIE['myid'])) {
			$myid = $_COOKIE['myid'];
				
			/** Init the session. */
			session_set_cookie_params(time() + 60 * 60 * 24 * 7);
			session_id($myid);
			@session_start();
				
			/** Check if they're logged in. */
			if (isset($_SESSION['user']['logged_in']) && $_SESSION['user']['logged_in']) {
				/** Set the last login info */
				if (strtotime("-1 hour") > $_SESSION['user']['last_login']) {
					$_SESSION['user']['last_login'] = strtotime("-15 minutes");
					$_SESSION['user']['ipaddr'] = isset($_SERVER['REMOTE_HOST']) ? $_SERVER['REMOTE_HOST'] : $_SERVER['REMOTE_ADDR'];
//					$this->updateSessionDB($_SESSION['user']);
				}
				return true;
			}
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
		$auth_method = (getOption('fm_db_version') >= 18) ? getOption('auth_method') : true;
		if ($auth_method) {
			/** Builtin Authentication */
			if ($auth_method == 1) {
				$pwd_query = ($encrypted) ? "'$pass'" : $pwd_query = "password('$pass')";
		
				if (getOption('fm_db_version') >= 18) {
					$result = $fmdb->get_results("SELECT * FROM `fm_users` WHERE `user_status`='active' AND `user_auth_type`=1 AND `user_template_only`='no' AND `user_login`='$user_login' AND `user_password`=" . $pwd_query);
				} else {
					$result = $fmdb->get_results("SELECT * FROM `fm_users` WHERE `user_status`='active' AND `user_login`='$user_login' AND `user_password`=" . $pwd_query);
				}
				if (!$fmdb->num_rows) {
					@mysql_free_result($result);
					return false;
				} else {
					$user = $fmdb->last_result[0];
					
					/** Enforce password change? */
					if (getOption('fm_db_version') >= 15) {
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
			
					@mysql_free_result($result);
					
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
	 * Gets the module permissions for the user
	 *
	 * @since 1.0
	 * @package facileManager
	 *
	 * @param integer $user_id User ID to get permissions for
	 * @param string $module_name Module name to get permissions for
	 * @param boolean $include_extra Whether or not to include extra permissions
	 * @return boolean
	 */
	function getModulePerms($user_id, $module_name = null, $include_extra = false) {
		global $fmdb, $__FM_CONFIG;
		
		if (empty($module_name)) {
			$module_name = $_SESSION['module'];
		}
		
		$result = $fmdb->get_results("SELECT * FROM `fm_perms` WHERE user_id=$user_id AND perm_module='$module_name'");
		if (!$fmdb->num_rows) {
			@mysql_free_result($result);
			return false;
		} else {
			$perm_row = $fmdb->last_result[0];
			@mysql_free_result($result);
			if ($include_extra) return array('perm_value' => $perm_row->perm_value, 'perm_extra' => $perm_row->perm_extra);
			else return $perm_row->perm_value;
		}
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
		
		$query = "UPDATE fm_users set user_ipaddr='{$_SESSION['user']['ipaddr']}', user_last_login=" . time() . " WHERE `user_login`='". $fm_login ."' AND `user_status`!='deleted';";
		$fmdb->get_results($query);
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
		if (isset($_COOKIE['myid'])) {
			$myid = $_COOKIE['myid'];
			
			// Init the session.
			session_set_cookie_params(time() + 60 * 60 * 24 * 7);
			session_id($myid);
			@session_start();
			$this->updateSessionDB($_SESSION['user']['name']);
			@session_unset($_SESSION['user']);
//			session_destroy();
			setcookie('myid', '');
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
		if (isEmailAddressValid($user_info['user_email']) === false) return 'There is no valid e-mail address associated with this user.';
		
		$phpmailer_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $fm_name . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class.phpmailer.php';
		if (!file_exists($phpmailer_file)) {
			return 'Unable to send email - PHPMailer class is missing.';
		} else {
			require $phpmailer_file;
		}
		
		$mail = new PHPMailer;
		
		/** Set PHPMailer options from database */
		$mail->Host = getOption('mail_smtp_host');
		$mail->SMTPAuth = getOption('mail_smtp_auth');
		if ($mail->SMTPAuth) {
			$mail->Username = getOption('mail_smtp_user');
			$mail->Password = getOption('mail_smtp_pass');
		}
		if (getOption('mail_smtp_tls')) $mail->SMTPSecure = 'tls';
		
		$mail->FromName = $fm_name;
		$mail->From = getOption('mail_from');
		$mail->AddAddress($user_info['user_email']);
		
		$mail->Subject = $fm_name . ' Password Reset';
		$mail->Body = $this->buildPwdResetEmail($user_info, $uniq_hash, true, $mail->Subject, $mail->From);
		$mail->AltBody = $this->buildPwdResetEmail($user_info, $uniq_hash, false);
		$mail->IsHTML(true);
		
		$mail->IsSMTP();
		
		if(!$mail->Send()) {
			return 'Mailer Error: ' . $mail->ErrorInfo;
		}
		
		return true;
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
{$__FM_CONFIG['icons']['fm_logo']}
<span style="font-size: 16pt; font-weight: bold; position: relative; top: -10px; margin-left: 10px;">$fm_name</span>
</div>
<div id="shadow" style="-moz-border-radius: 0% 0% 100% 100% / 0% 0% 8px 8px; -webkit-border-radius: 0% 0% 100% 100% / 0% 0% 8px 8px; border-radius: 0% 0% 100% 100% / 0% 0% 8px 8px; -moz-box-shadow: rgba(0,0,0,.30) 0 2px 3px !important; -webkit-box-shadow: rgba(0,0,0,.30) 0 2px 3px !important; box-shadow: rgba(0,0,0,.30) 0 2px 3px !important;">
<div id="container" style="background-color: #fff; min-height: 200px; margin-top: 1em; padding: 0 1.5em .5em; border: 1px solid #fff; -webkit-border-radius: 5px; -moz-border-radius: 5px; border-radius: 5px; -webkit-box-shadow: inset 0 2px 1px rgba(255,255,255,.97) !important; -moz-box-shadow: inset 0 2px 1px rgba(255,255,255,.97) !important; box-shadow: inset 0 2px 1px rgba(255,255,255,.97) !important;">
<p>Hi {$user_info['user_login']},</p>
<p>You (or somebody else) has requested a link to reset your $fm_name password.</p>
<p>If you don't want to reset your password, then you can ignore this message.</p>
<p>To rest your password, click the following link:<br />
<<a href="{$GLOBALS['FM_URL']}password_reset?key=$uniq_hash&login={$user_info['user_login']}">{$GLOBALS['FM_URL']}password_reset?key=$uniq_hash&login={$user_info['user_login']}</a>></p>
</div>
</div>
<p style="font-size: 10px; color: #888; text-align: center;">$fm_name | $from_address</p>
</body>
</html>
BODY;
		} else {
			$body = <<<BODY
Hi {$user_info['user_login']},

You (or somebody else) has requested a link to reset your $fm_name password.

If you don't want to reset your password, then you can ignore this message.

To rest your password, click the following link:

{$GLOBALS['FM_URL']}password_reset?key=$uniq_hash&login={$user_info['user_login']}
BODY;
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
		
		session_set_cookie_params(time() + 60 * 60 * 24 * 7);
		@session_start();
		$_SESSION['user']['logged_in'] = true;
		$_SESSION['user']['id'] = $user->user_id;
		$_SESSION['user']['name'] = $user->user_login;
		$_SESSION['user']['fm_perms'] = $user->user_perms;
		$_SESSION['user']['last_login'] = $user->user_last_login;
		$_SESSION['user']['account_id'] = $user->account_id;
		$_SESSION['user']['ipaddr'] = isset($_SERVER['REMOTE_HOST']) ? $_SERVER['REMOTE_HOST'] : $_SERVER['REMOTE_ADDR'];

		$modules = getActiveModules(true);
		if (@in_array($user->user_default_module, $modules)) {
			$_SESSION['module'] = $user->user_default_module;
		} else {
			$_SESSION['module'] = (is_array($modules) && count($modules)) ? $modules[0] : $fm_name;
		}
		if ($_SESSION['module'] != $fm_name) {
			$_SESSION['user']['module_perms'] = $this->getModulePerms($user->user_id, null, true);
		}
		setcookie('myid', session_id(), time() + 60 * 60 * 24 * 7);
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
		if (empty($ldap_server))		$ldap_server		= getOption('ldap_server');
		if (empty($ldap_port))			$ldap_port			= getOption('ldap_port');
		if (empty($ldap_port_ssl))		$ldap_port_ssl		= getOption('ldap_port_ssl');
		if (empty($ldap_version))		$ldap_version		= getOption('ldap_version');
		if (empty($ldap_encryption))	$ldap_encryption	= getOption('ldap_encryption');
		if (empty($ldap_referrals))		$ldap_referrals		= getOption('ldap_referrals');
		if (empty($ldap_dn))			$ldap_dn			= getOption('ldap_dn');
		if (empty($ldap_group_require))	$ldap_group_require	= getOption('ldap_group_require');
		if (empty($ldap_group_dn))		$ldap_group_dn		= getOption('ldap_group_dn');
		
		$ldap_dn = str_replace('<username>', $username, $ldap_dn);

		if ($ldap_encryption == 'SSL') {
			$ldap_connect = @ldap_connect('ldaps://' . $ldap_server . ':' . $ldap_port_ssl);
		} else {
			$ldap_connect = @ldap_connect($ldap_server, $ldap_port);
		}
		
		if ($ldap_connect) {
			/** Set protocol version */
			if (!@ldap_set_option($ldap_connect, LDAP_OPT_PROTOCOL_VERSION, $ldap_version)) {
				@ldap_close($ldap_connect);
				return false;
			}
			
			/** Set referrals */
			if (!$ldap_referrals) {
				if(!@ldap_set_option($ldap_connect, LDAP_OPT_REFERRALS, 0)) {
					@ldap_close($ldap_connect);
					return false;
				}
			}
			
			/** Start TLS if requested */
			if ($ldap_encryption == 'TLS') {
				if (!@ldap_start_tls($ldap_connect)) {
					@ldap_close($ldap_connect);
					return false;
				}
			}
			
			$ldap_bind = @ldap_bind($ldap_connect, $ldap_dn, $password);
			
			if ($ldap_bind) {
				if ($ldap_group_require) {
					/** Process group membership if required */
					$ldap_group_response = @ldap_compare($ldap_connect, $ldap_group_dn, $ldap_group_attribute, $username);
					
					if ($ldap_group_response !== true) {
						@ldap_close($ldap_connect);
						return false;
					}
				}
				
				/** Get user permissions from database */
				$fmdb->get_results("SELECT * FROM `fm_users` WHERE `user_status`='active' AND `user_auth_type`=2 AND `user_template_only`='no' AND `user_login`='$username'");
				if (!$fmdb->num_rows) {
					if (!$this->createUserFromTemplate($username)) {
						@ldap_close($ldap_connect);
						return false;
					}
				}
				
				$this->setSession($fmdb->last_result[0]);
				
				return true;
			}
			
			/** Close LDAP connection */
			@ldap_close($ldap_connect);
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
		
		/** User does not exist in database - get the template user */
		$result = $fmdb->get_results("SELECT * FROM `fm_users` WHERE `user_id` = " . getOption('ldap_user_template'));
		if (!$fmdb->num_rows) return false;
		
		/** Attempt to add the new LDAP user to the database based on the template */
		$fmdb->query("INSERT INTO `fm_users` (`account_id`,`user_login`, `user_password`, `user_email`, `user_auth_type`, `user_perms`) 
					SELECT `account_id`, '$username', '', '', 2, `user_perms` from `fm_users` WHERE `user_id`=" . getOption('ldap_user_template'));
		if (!$fmdb->rows_affected) return false;
		
		/** Attempt to add the new LDAP user permissions to the database based on the template */
		$fmdb->query("INSERT INTO `fm_perms` (`user_id`,`perm_module`, `perm_value`, `perm_extra`) 
					SELECT {$fmdb->insert_id}, `perm_module`, `perm_value`, `perm_extra` from `fm_perms` WHERE `user_id`=" . getOption('ldap_user_template'));
		if (!$fmdb->last_result) return false;
		
		/** Get the user results now */
		$fmdb->get_results("SELECT * FROM `fm_users` WHERE `user_status`='active' AND `user_auth_type`=2 AND `user_template_only`='no' AND `user_login`='$username'");
		if (!$fmdb->num_rows) return false;
		
		return true;
	}

}

if (!isset($fm_login))
	$fm_login = new fm_login();

?>
