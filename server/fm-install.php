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
 * facileManager Installer
 *
 * @package facileManager
 * @subpackage Administration
 *
 */

/** Define ABSPATH as this files directory */
define('ABSPATH', dirname(__FILE__) . '/');

/** Set installation variable */
define('INSTALL', true);
$GLOBALS['RELPATH'] = rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/';

/** Check if authenticated */
require_once(ABSPATH . 'fm-modules/facileManager/classes/class_logins.php');

if ($fm_login->isLoggedIn() || (isset($_SESSION) && array_key_exists('user', $_SESSION))) {
	$fm_login->logout();
	header('Location: ' . $GLOBALS['RELPATH']);
}

/** Ensure we meet the requirements */
require_once(ABSPATH . 'fm-includes/init.php');
require_once(ABSPATH . 'fm-includes/version.php');
$app_compat = checkAppVersions(false);

if ($app_compat) {
	bailOut($app_compat);
}

$step = isset($_GET['step']) ? $_GET['step'] : 0;

switch ($step) {
	case 0:
	case 1:
		if (!file_exists(ABSPATH . 'config.inc.php') || !file_get_contents(ABSPATH . 'config.inc.php')) {
			printHeader(_('Installation'), 'install');
			displaySetup();
		} else {
			header('Location: ' . $GLOBALS['RELPATH'] . 'fm-install.php?step=3');
		}
		break;
	case 2:
		if (!$_POST || !array($_POST)) header('Location: ' . $GLOBALS['RELPATH'] . 'fm-install.php');
		printHeader(_('Installation'), 'install');
		processSetup();
		break;
	case 3:
		if (!file_exists(ABSPATH . 'config.inc.php') || !file_get_contents(ABSPATH . 'config.inc.php')) header('Location: ' . $GLOBALS['RELPATH'] . 'fm-install.php');
		require_once(ABSPATH . 'fm-modules/facileManager/install.php');
		
		@include(ABSPATH . 'config.inc.php');
		$link = @mysql_connect($__FM_CONFIG['db']['host'], $__FM_CONFIG['db']['user'], $__FM_CONFIG['db']['pass']);
		
		if (version_compare(mysql_get_server_info(), $required_mysql_version, '<')) {
			bailOut(sprintf('<p style="text-align: center;">' . _('Your MySQL server (%1$s) is running MySQL version %2$s but %3$s %4$s requires at least %5$s.') . '</p>', $__FM_CONFIG['db']['host'], mysql_get_server_info(), $fm_name, $fm_version, $required_mysql_version));
			break;
		}
		
		printHeader(_('Installation'), 'install');

		/** Check if already installed */
		$query = "SELECT option_id FROM `{$__FM_CONFIG['db']['name']}`.`fm_options` WHERE `option_name`='fm_db_version'";
		$result = @mysql_query($query, $link);
		
		fmInstall($link, $__FM_CONFIG['db']['name']);
		break;
	case 4:
		if (!file_exists(ABSPATH . 'config.inc.php') || !file_get_contents(ABSPATH . 'config.inc.php')) header('Location: ' . $GLOBALS['RELPATH'] . 'fm-install.php');
		
		include(ABSPATH . 'config.inc.php');
		$link = @mysql_connect($__FM_CONFIG['db']['host'], $__FM_CONFIG['db']['user'], $__FM_CONFIG['db']['pass']);
		
		/** Make sure the super-admin account doesn't already exist */
		if (!checkAccountCreation($link, $__FM_CONFIG['db']['name'])) {
			printHeader(_('Installation'), 'install');
			displayAccountSetup();
		} else {
			header('Location: ' . $GLOBALS['RELPATH']);
		}
		
		break;
	case 5:
		if (!file_exists(ABSPATH . 'config.inc.php') || !file_get_contents(ABSPATH . 'config.inc.php')) header('Location: ' . $GLOBALS['RELPATH'] . 'fm-install.php');
		if (!$_POST || !array($_POST)) header('Location: ' . $GLOBALS['RELPATH'] . 'fm-install.php');
		
		include(ABSPATH . 'config.inc.php');
		$link = @mysql_connect($__FM_CONFIG['db']['host'], $__FM_CONFIG['db']['user'], $__FM_CONFIG['db']['pass']);
		
		/** Make sure the super-admin account doesn't already exist */
		if (!checkAccountCreation($link, $__FM_CONFIG['db']['name'])) {
			processAccountSetup($link, $__FM_CONFIG['db']['name']);
		}
		
		header('Location: ' . $GLOBALS['RELPATH'] . 'fm-install.php?step=6');
		break;
	case 6:
		printHeader(_('Installation'), 'install');
		include(ABSPATH . 'fm-modules/facileManager/variables.inc.php');
		
		echo '<center><p>';
		printf(_("Installation is complete! Click 'Next' to login and start using %s."), $fm_name);
		printf('</p><p class="step"><a href="%s" class="button">%s</a></p></center>', $GLOBALS['RELPATH'], _('Next'));
		break;
}

printFooter();


/**
 * Display install body.
 *
 * @since 1.0
 * @package facileManager
 * @subpackage Installer
 */
function displaySetup($error = null) {
	global $fm_name;
	
	if ($error) {
		$error = sprintf('<strong>' . _('ERROR: %s') . "</strong>\n", $error);
	}
	
	$dbhost = (isset($_POST['dbhost'])) ? $_POST['dbhost'] : 'localhost';
	$dbname = (isset($_POST['dbname'])) ? $_POST['dbname'] : strtolower($fm_name);
	$dbuser = (isset($_POST['dbuser'])) ? $_POST['dbuser'] : null;
	$dbpass = (isset($_POST['dbpass'])) ? $_POST['dbpass'] : null;
	
	printf('
<form method="post" action="?step=2">
	<center>
	%1$s
	<p>' . _('Before we can install the backend database, I need your database credentials. (I will also use them to generate the <code>config.inc.php</code> file.)') . '</p>
	<table class="form-table">
		<tr>
			<th><label for="dbhost">' . _('Database Host') . '</label></th>
			<td><input type="text" size="25" name="dbhost" id="dbhost" value="%2$s" placeholder="localhost" /></td>
		</tr>
		<tr>
			<th><label for="dbname">' . _('Database Name') . '</label></th>
			<td><input type="text" size="25" name="dbname" id="dbname" value="%3$s" placeholder="%3$s" /></td>
		</tr>
		<tr>
			<th><label for="dbuser">' . _('Username') . '</label></th>
			<td><input type="text" size="25" name="dbuser" id="dbuser" value="%4$s" placeholder="' . _('username') . '" /></td>
		</tr>
		<tr>
			<th><label for="dbpass">' . _('Password') . '</label></th>
			<td><input type="password" size="25" name="dbpass" id="dbpass" value="%5$s" placeholder="' . _('password') . '" /></td>
		</tr>
	</table>
	</center>
	<p class="step"><input name="submit" type="submit" value="' . _('Submit') . '" class="button" /></p>
</form>', $error, $dbhost, $dbname, $dbuser, $dbpass);
}

/**
 * Processes installation.
 *
 * @since 1.0
 * @package facileManager
 * @subpackage Installer
 */
function processSetup() {
	extract($_POST);
	
	$link = @mysql_connect($dbhost, $dbuser, $dbpass);
	if (!$link) {
		exit(displaySetup(_('Could not connect to MySQL.<br />Please check your credentials.')));
	} else {
		$db_selected = @mysql_select_db($dbname, $link);
		if (mysql_error() && strpos(mysql_error(), 'Unknown database') === false) {
			exit(displaySetup(mysql_error()));
		}
		if ($db_selected) {
			$tables = @mysql_query(sanitize('SHOW TABLES FROM ' . $dbname . ';'), $link);
			@mysql_close($link);
			if (@mysql_num_rows($tables)) {
				exit(displaySetup(_('Database already exists and contains one or more tables.<br />Please choose a different name.')));
			}
		}
	}
	
	require_once(ABSPATH . 'fm-modules/facileManager/install.php');
	createConfig();
}

/**
 * Display account setup.
 *
 * @since 1.0
 * @package facileManager
 * @subpackage Installer
 */
function displayAccountSetup($error = null) {
	global $__FM_CONFIG;

	if ($error) {
		$error = sprintf('<strong>' . _('ERROR: %s') . "</strong>\n", $error);
	}
	
	$strength = $GLOBALS['PWD_STRENGTH'];
	printf('
<form method="post" action="?step=5">
	<center>
	%1$s
	<p>' . _('Ok, now create your super-admin account') . '</p>
	<table class="form-table">
		<tr>
			<th><label for="user_login">' . _('Username') . '</label></th>
			<td><input type="text" size="25" name="user_login" id="user_login" placeholder="username" onkeyup="javascript:checkPasswd(\'user_password\', \'createaccount\', \'%2$s\');" /></td>
		</tr>
		<tr>
			<th><label for="user_email">' . _('Email') . '</label></th>
			<td><input type="email" size="25" name="user_email" id="user_email" placeholder="email address" onkeyup="javascript:checkPasswd(\'user_password\', \'createaccount\', \'%2$s\');" /></td>
		</tr>
		<tr>
			<th><label for="user_password">' . _('Password') . '</label></th>
			<td><input type="password" size="25" name="user_password" id="user_password" placeholder="password" onkeyup="javascript:checkPasswd(\'user_password\', \'createaccount\', \'%2$s\');" /></td>
		</tr>
		<tr>
			<th><label for="cpassword">' . _('Confirm Password') . '</label></th>
			<td><input type="password" size="25" name="cpassword" id="cpassword" placeholder="password again" onkeyup="javascript:checkPasswd(\'cpassword\', \'createaccount\', \'%2$s\');" /></td>
		</tr>
		<tr>
			<th>' . _('Password Validity') . '</th>
			<td><div id="passwd_check">' . _('No Password') . '</div></td>
		</tr>
		<tr class="pwdhint">
			<th width="33%" scope="row">' . _('Hint') . '</th>
			<td width="67%">%3$s</td>
		</tr>
	</table>
	</center>
	<p class="step"><input id="createaccount" name="submit" type="submit" value="' . _('Submit') . '" class="button" disabled /></p>
</form>', $error, $strength, $__FM_CONFIG['password_hint'][$GLOBALS['PWD_STRENGTH']]);
}

/**
 * Processes account creation.
 *
 * @since 1.0
 * @package facileManager
 * @subpackage Installer
 */
function processAccountSetup($link, $database) {
	global $fm_name;
	
	if (!function_exists('sanitize')) {
		require_once(ABSPATH . '/fm-modules/facileManager/functions.php');
	}
	
	extract($_POST);
	$user = sanitize($_POST['user_login']);
	$pass = sanitize($_POST['user_password']);
	$email = sanitize($_POST['user_email']);
	
	$query = "INSERT INTO $database.fm_users (user_login, user_password, user_email, user_caps, user_ipaddr, user_status) VALUES('$user', password('$pass'), '$email', '" . serialize(array($fm_name => array('do_everything' => 1))). "', '{$_SERVER['REMOTE_ADDR']}', 'active')";
	$result = mysql_query($query, $link) or die(mysql_error());
	
	addLogEntry(sprintf(_("Installer created user '%s'"), $user), $fm_name, $link);
}

/**
 * Ensures the account is unique.
 *
 * @since 1.0
 * @package facileManager
 * @subpackage Installer
 */
function checkAccountCreation($link, $database) {
	$query = "SELECT user_id FROM $database.fm_users WHERE user_id='1'";
	$result = mysql_query($query, $link);
	
	return ($result && mysql_num_rows($result)) ? true : false;
}

?>
