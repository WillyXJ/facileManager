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

/**
 * Handles password resets
 *
 * @author		Jon LaBass
 * @version		$Id:$
 * @copyright	2013
 *
 */

define('CLIENT', true);

require_once('fm-init.php');

$message = '<p>Please enter your new password.</p>';

/** Redirect if key and login are not set */
if (!count($_POST) && (!array_key_exists('key', $_GET) || !array_key_exists('login', $_GET))) header('Location: ' . $GLOBALS['RELPATH']);

/** Check key for validity */
if (!checkForgottonPasswordKey(sanitize($_GET['key']), sanitize($_GET['login']))) header('Location: ' . $GLOBALS['RELPATH'] . '?forgot_password&keyInvalid');

if (count($_POST)) {
	extract($_POST);
	extract($_GET);
	
	if ($user_password != $cpassword) {
		$message = '<p class="failed">The passwords do not match.</p>';
	} else {
		if (!resetPassword(sanitize($login), sanitize($key), sanitize($user_password))) $message = '<p class="failed">Your password failed to get updated.</p>';
		else {
			require_once(ABSPATH . 'fm-modules/facileManager/classes/class_logins.php');
			$fm_login->checkPassword($login, $user_password);
			
			exit(printResetConfirmation());
		}
	}
}

printPasswordResetForm($message);

/**
 * Display password reset user form.
 *
 * @since 1.0
 * @package facileManager
 */
function printPasswordResetForm($message=null) {
	global $__FM_CONFIG;

	printHeader('Password Reset', 'install');
	
	if (class_exists('fmdb')) $GLOBALS['PWD_STRENGTH'] = getOption('auth_fm_pw_strength');
	$strength = $GLOBALS['PWD_STRENGTH'];
	echo <<<HTML
	<form id="forgotpwd" method="post" action="{$_SERVER['REQUEST_URI']}">
		<input type="hidden" name="reset_pwd" value="1" />
		<center>
		<div id="message">$message</div>
		<table class="form-table">
			<tr>
				<th><label for="user_password">New Password</label></th>
				<td><input type="password" size="25" name="user_password" id="user_password" placeholder="password" onkeyup="javascript:checkPasswd('user_password', 'resetpwd', '$strength');" /></td>
			</tr>
			<tr>
				<th><label for="cpassword">Confirm Password</label></th>
				<td><input type="password" size="25" name="cpassword" id="cpassword" placeholder="password again" onkeyup="javascript:checkPasswd('cpassword', 'resetpwd', '$strength');" /></td>
			</tr>
			<tr>
				<th>Password Validity</th>
				<td><div id="passwd_check">No Password</div></td>
			</tr>
			<tr class="pwdhint">
				<th width="33%" scope="row">Hint</th>
				<td width="67%">
				{$__FM_CONFIG['password_hint'][$GLOBALS['PWD_STRENGTH']]}
				<p id="forgotton_link"><a href="{$GLOBALS['RELPATH']}">&larr; Login form</a></p>
				</td>
			</tr>
		</table>
		</center>
		<p class="step"><input id="resetpwd" name="submit" type="submit" value="Submit" class="button" disable /></p>
	</form>

HTML;
}


/**
 * Checks validity of key and login for password resets.
 *
 * @since 1.0
 * @package facileManager
 */
function checkForgottonPasswordKey($key, $fm_login) {
	global $fmdb, $__FM_CONFIG;
	
	$time = date("Y-m-d H:i:s", strtotime($__FM_CONFIG['clean']['days'] . ' days ago'));
	$query = "SELECT * FROM `fm_pwd_resets` WHERE `pwd_id`='$key' AND `pwd_login`=(SELECT `user_id` FROM `fm_users` WHERE `user_login`='$fm_login' AND `user_status`!='deleted') AND `pwd_timestamp`>='$time'";
	$fmdb->get_results($query);
	
	if ($fmdb->num_rows) return true;
	
	return false;
}


/**
 * Resets the user password.
 *
 * @since 1.0
 * @package facileManager
 */
function resetPassword($fm_login, $key, $user_password) {
	global $fmdb;
	
	$user_info = getUserInfo($fm_login, 'user_login');
	$fm_login_id = $user_info['user_id'];
	
	/** Update password */
	$query = "UPDATE `fm_users` SET `user_password`=password('$user_password'), `user_force_pwd_change`='no' WHERE `user_id`='$fm_login_id'";
	$fmdb->query($query);
	
	if ($fmdb->rows_affected) {
		/** Remove entry from fm_pwd_resets table */
		$query = "DELETE FROM `fm_pwd_resets` WHERE `pwd_login`='$fm_login_id'";
		$fmdb->query($query);
		
		return true;
	}
	
	return false;
}


/**
 * Prints a password reset confirmation page.
 *
 * @since 1.0
 * @package facileManager
 */
function printResetConfirmation() {
	global $fm_name;

	printHeader('Password Reset', 'install');
	
	echo <<<HTML
	<center>
	<p>Your password has been updated!  Click 'Next' to login and start using $fm_name.</p>
	<p class="step"><a href="{$GLOBALS['RELPATH']}" class="button">Next</a></p>
	</center>

HTML;
}

?>
