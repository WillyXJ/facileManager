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

define('CLIENT', true);

require_once('fm-init.php');

$message = null;

/** Redirect if key and login are not set */
if (!count($_POST) && (!array_key_exists('key', $_GET) || !array_key_exists('login', $_GET))) {
	header('Location: ' . $GLOBALS['RELPATH']);
	exit;
}

/** Check key for validity */
if (!checkForgottonPasswordKey(sanitize($_GET['key']), sanitize($_GET['login']))) {
	header('Location: ' . $GLOBALS['RELPATH'] . '?forgot_password&keyInvalid');
	exit;
}

if (count($_POST)) {
	extract($_POST);
	extract($_GET);
	
	if ($user_password != $cpassword) {
		$message = sprintf('<p class="failed">%s</p>', _('The passwords do not match.'));
	} else {
		$login = sanitize($login);
		$user_password = sanitize($user_password);

		$result = resetPassword($login, $user_password);
		if ($result !== true) {
			$message_text = ($result === false) ? _('Your password failed to get updated.') : $result;
			$message = sprintf('<p class="failed">%s</p>', $message_text);
		} else {
			require_once(ABSPATH . 'fm-modules/facileManager/classes/class_logins.php');
			$fm_login->checkPassword($login, $user_password);
			
			addLogEntry(_('Changed password'), $fm_name);

			printResetConfirmation();
			exit();
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
function printPasswordResetForm($message = null) {
	global $__FM_CONFIG, $fm_name;

	printHeader(_('Password Reset'), 'install');
	
	if (class_exists('fmdb')) $strength = getOption('auth_fm_pw_strength');
	if ($strength) $GLOBALS['PWD_STRENGTH'] = $strength;
	echo '<form id="forgotpwd" method="post" action="' . $_SERVER['REQUEST_URI'] . '">
		<input type="hidden" name="reset_pwd" value="1" />
		<div id="fm-branding">
			<img src="' . getBrandLogo() . '" /><span>' . _('Password Reset') . '</span>
		</div>
		<div id="window">
		<div id="message">' . $message . '</div>
		<table class="form-table">
			<tr>
				<th><label for="user_password">' . _('New Password') . '</label></th>
				<td><input type="password" size="25" name="user_password" id="user_password" placeholder="' . _('password') . '" onkeyup="javascript:checkPasswd(\'user_password\', \'resetpwd\', \'' . $GLOBALS['PWD_STRENGTH'] . '\');" /></td>
			</tr>
			<tr>
				<th><label for="cpassword">' . _('Confirm Password') . '</label></th>
				<td><input type="password" size="25" name="cpassword" id="cpassword" placeholder="' . _('password again') . '" onkeyup="javascript:checkPasswd(\'cpassword\', \'resetpwd\', \'' . $GLOBALS['PWD_STRENGTH'] . '\');" /></td>
			</tr>
			<tr>
				<th>' . _('Password Validity') . '</th>
				<td><div id="passwd_check">' . _('No Password') . '</div></td>
			</tr>
			<tr class="pwdhint">
				<th width="33%" scope="row">' . _('Hint') . '</th>
				<td width="67%">' . $__FM_CONFIG['password_hint'][$GLOBALS['PWD_STRENGTH']][1]. '
				</td>
			</tr>
		</table>
		<p class="step"><input id="resetpwd" name="submit" type="submit" value="' . _('Submit') . '" class="button" disabled /></p>
		</div>
	</form>';
}


/**
 * Checks validity of key and login for password resets.
 *
 * @since 1.0
 * @package facileManager
 */
function checkForgottonPasswordKey($key, $fm_login) {
	global $fmdb, $__FM_CONFIG;
	
	$time = date("U", strtotime($__FM_CONFIG['clean']['time'] . ' ago'));
	$query = "SELECT * FROM `fm_pwd_resets` WHERE `pwd_id`='$key' AND `pwd_login`=(SELECT `user_id` FROM `fm_users` WHERE `user_login`='$fm_login' AND `user_status`!='deleted') AND `pwd_timestamp`>='$time'";
	$fmdb->get_results($query);
	
	if ($fmdb->num_rows) return true;
	
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

	printHeader(_('Password Reset'), 'install');
	
	printf('<div id="fm-branding">
		<img src="' . getBrandLogo() . '" /><span>' . _('Password Reset') . '</span>
	</div>
	<div id="window"><p>' . _("Your password has been updated! Click 'Next' to login and start using %s.") . '</p>
		<p class="step"><a href="%s" class="button">' . _('Next') . '</a></p>
		</div>', $fm_name, $GLOBALS['RELPATH']);
}
