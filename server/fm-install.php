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
	exit;
}

/** Ensure we meet the requirements */
require_once(ABSPATH . 'fm-includes/init.php');
require_once(ABSPATH . 'fm-includes/version.php');
if ($app_compat = checkAppVersions(false)) {
	bailOut($app_compat);
}

$step = isset($_GET['step']) ? $_GET['step'] : 0;

$branding_logo = $GLOBALS['RELPATH'] . 'fm-modules/' . $fm_name . '/images/fm.png';

switch ($step) {
	case 0:
	case 1:
		if ((!file_exists(ABSPATH . 'config.inc.php') || !file_get_contents(ABSPATH . 'config.inc.php')) || 
				(@include(ABSPATH . 'config.inc.php') && !@is_array($__FM_CONFIG['db']))) {
			printHeader(_('Installation'), 'install');
			displaySetup();
		} else {
			header('Location: ' . $GLOBALS['RELPATH'] . 'fm-install.php?step=3');
			exit;
		}
		break;
	case 2:
		if (!$_POST || !array($_POST)) {
			header('Location: ' . $GLOBALS['RELPATH'] . 'fm-install.php');
			exit;
		}
		printHeader(_('Installation'), 'install');
		processSetup();
		break;
	case 3:
		if (!file_exists(ABSPATH . 'config.inc.php') || !file_get_contents(ABSPATH . 'config.inc.php')) {
			header('Location: ' . $GLOBALS['RELPATH'] . 'fm-install.php');
			exit;
		}
		require_once(ABSPATH . 'fm-modules/facileManager/install.php');
		
		@include(ABSPATH . 'config.inc.php');
		include_once(ABSPATH . 'fm-includes/fm-db.php');
		$fmdb = new fmdb($__FM_CONFIG['db']['user'], $__FM_CONFIG['db']['pass'], $__FM_CONFIG['db']['name'], $__FM_CONFIG['db']['host'], 'connect only');
		
		$mysql_server_version = ($fmdb->use_mysqli) ? $fmdb->dbh->server_info : mysql_get_server_info();
		if (version_compare($mysql_server_version, $required_mysql_version, '<')) {
			bailOut(sprintf('<p style="text-align: center;">' . _('Your MySQL server (%1$s) is running MySQL version %2$s but %3$s %4$s requires at least %5$s.') . '</p>', $__FM_CONFIG['db']['host'], $mysql_server_version, $fm_name, $fm_version, $required_mysql_version));
			break;
		}
		
		printHeader(_('Installation'), 'install');

		/** Check if already installed */
		if (isset($__FM_CONFIG['db']['name'])) {
			$query = "SELECT option_id FROM `{$__FM_CONFIG['db']['name']}`.`fm_options` WHERE `option_name`='fm_db_version'";
			$result = $fmdb->query($query);
		} else {
			header('Location: ' . $GLOBALS['RELPATH']);
			exit;
		}
		
		if ($result && $fmdb->num_rows) {
			/** Check if the default admin account exists */
			if (!checkAccountCreation($__FM_CONFIG['db']['name'])) {
				header('Location: ' . $GLOBALS['RELPATH'] . 'fm-install.php?step=4');
				exit;
			} else {
				header('Location: ' . $GLOBALS['RELPATH']);
				exit;
			}
		} else {
			fmInstall($__FM_CONFIG['db']['name']);
		}
		break;
	case 4:
		if (!file_exists(ABSPATH . 'config.inc.php') || !file_get_contents(ABSPATH . 'config.inc.php')) {
			header('Location: ' . $GLOBALS['RELPATH'] . 'fm-install.php');
			exit;
		}
		
		include(ABSPATH . 'config.inc.php');
		include_once(ABSPATH . 'fm-includes/fm-db.php');
		$fmdb = new fmdb($__FM_CONFIG['db']['user'], $__FM_CONFIG['db']['pass'], $__FM_CONFIG['db']['name'], $__FM_CONFIG['db']['host'], 'connect only');
		
		/** Make sure the super-admin account doesn't already exist */
		if (!checkAccountCreation($__FM_CONFIG['db']['name'])) {
			printHeader(_('Installation'), 'install');
			displayAccountSetup();
		} else {
			header('Location: ' . $GLOBALS['RELPATH']);
			exit;
		}
		
		break;
	case 5:
		if (!file_exists(ABSPATH . 'config.inc.php') || !file_get_contents(ABSPATH . 'config.inc.php')) {
			header('Location: ' . $GLOBALS['RELPATH'] . 'fm-install.php');
			exit;
		}
		if (!$_POST || !array($_POST)) {
			header('Location: ' . $GLOBALS['RELPATH'] . 'fm-install.php');
			exit;
		}
		
		include(ABSPATH . 'config.inc.php');
		include_once(ABSPATH . 'fm-includes/fm-db.php');
		$fmdb = new fmdb($__FM_CONFIG['db']['user'], $__FM_CONFIG['db']['pass'], $__FM_CONFIG['db']['name'], $__FM_CONFIG['db']['host'], 'connect only');
		
		/** Make sure the super-admin account doesn't already exist */
		if (!checkAccountCreation($__FM_CONFIG['db']['name'])) {
			processAccountSetup($__FM_CONFIG['db']['name']);
		}
		
		header('Location: ' . $GLOBALS['RELPATH'] . 'fm-install.php?step=6');
		break;
	case 6:
		printHeader(_('Installation'), 'install');
		include(ABSPATH . 'fm-modules/facileManager/variables.inc.php');
		
		printf('<div id="fm-branding">
		<img src="%s" /><span>%s</span>
	</div>
	<div id="window"><p>', $branding_logo, _('Install'));
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
	global $fm_name, $branding_logo;
	
	if ($error) {
		$error = sprintf('<strong>' . _('ERROR: %s') . "</strong>\n", $error);
	}
	
	$dbhost = (isset($_POST['dbhost'])) ? $_POST['dbhost'] : 'localhost';
	$dbname = (isset($_POST['dbname'])) ? $_POST['dbname'] : $fm_name;
	$dbuser = (isset($_POST['dbuser'])) ? $_POST['dbuser'] : null;
	$dbpass = (isset($_POST['dbpass'])) ? $_POST['dbpass'] : null;
	
	printf('
<form method="post" action="?step=2">
	<div id="fm-branding">
		<img src="%s" /><span>%s</span>
	</div>
	<div id="window">
	%s
	<p>' . _('Before we can install the backend database, your database credentials are needed. (They will also be used to generate the <code>config.inc.php</code> file.)') . '</p>
	<table>
		<tr>
			<th><label for="dbhost">' . _('Database Host') . '</label></th>
			<td><input type="text" size="25" name="dbhost" id="dbhost" value="%s" placeholder="localhost" /></td>
		</tr>
		<tr>
			<th><label for="dbname">' . _('Database Name') . '</label></th>
			<td><input type="text" size="25" name="dbname" id="dbname" value="%s" placeholder="%3$s" /></td>
		</tr>
		<tr>
			<th><label for="dbuser">' . _('Username') . '</label></th>
			<td><input type="text" size="25" name="dbuser" id="dbuser" value="%s" placeholder="' . _('username') . '" /></td>
		</tr>
		<tr>
			<th><label for="dbpass">' . _('Password') . '</label></th>
			<td><input type="password" size="25" name="dbpass" id="dbpass" value="%s" placeholder="' . _('password') . '" /></td>
		</tr>
	</table>
	<p class="step"><input name="submit" type="submit" value="' . _('Submit') . '" class="button" /></p>
	</div>
</form>', $branding_logo, _('Install'), $error, $dbhost, $dbname, $dbuser, $dbpass);
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
	
	include_once(ABSPATH . 'fm-includes/fm-db.php');
	$fmdb = new fmdb($dbuser, $dbpass, $dbname, $dbhost, 'silent connect');
	if (!$fmdb->dbh) {
		exit(displaySetup(_('Could not connect to MySQL. Please check your credentials.')));
	} else {
		$db_selected = $fmdb->select($dbname, 'silent');
		if ($fmdb->last_error && strpos($fmdb->last_error, 'Unknown database') === false) {
			exit(displaySetup($fmdb->last_error));
		}
		if ($db_selected) {
			$tables = $fmdb->query('SHOW TABLES FROM `' . $dbname . '`;');
			if ($fmdb->num_rows) {
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
	global $__FM_CONFIG, $branding_logo;

	if ($error) {
		$error = sprintf('<strong>' . _('ERROR: %s') . "</strong>\n", $error);
	}
	
	printf('
<form method="post" action="?step=5" class="disable-auto-complete">
	<div id="fm-branding">
		<img src="%s" /><span>%s</span>
	</div>
	<div id="window">
	%3$s
	<p>' . _('Ok, now create your super-admin account') . '</p>
	<table class="form-table">
		<tr>
			<th><label for="user_login">' . _('Username') . '</label></th>
			<td><input type="text" size="25" name="user_login" id="user_login" placeholder="username" onkeyup="javascript:checkPasswd(\'user_password\', \'createaccount\', \'%4$s\');" /></td>
		</tr>
		<tr>
			<th><label for="user_email">' . _('Email') . '</label></th>
			<td><input type="email" size="25" name="user_email" id="user_email" placeholder="email address" onkeyup="javascript:checkPasswd(\'user_password\', \'createaccount\', \'%4$s\');" /></td>
		</tr>
		<tr>
			<th><label for="user_password">' . _('Password') . '</label></th>
			<td><input type="password" size="25" name="user_password" id="user_password" placeholder="password" onkeyup="javascript:checkPasswd(\'user_password\', \'createaccount\', \'%4$s\');" autocomplete="off" /></td>
		</tr>
		<tr>
			<th><label for="cpassword">' . _('Confirm Password') . '</label></th>
			<td><input type="password" size="25" name="cpassword" id="cpassword" placeholder="password again" onkeyup="javascript:checkPasswd(\'cpassword\', \'createaccount\', \'%4$s\');" /></td>
		</tr>
		<tr>
			<th>' . _('Password Validity') . '</th>
			<td><div id="passwd_check">' . _('No Password') . '</div></td>
		</tr>
		<tr class="pwdhint">
			<th width="33&#37;" scope="row">' . _('Hint') . '</th>
			<td width="67&#37;">%5$s</td>
		</tr>
	</table>
	<p class="step"><input id="createaccount" name="submit" type="submit" value="' . _('Submit') . '" class="button" disabled /></p>
	</div>
</form>', $branding_logo, _('Install'), $error, $GLOBALS['PWD_STRENGTH'], $__FM_CONFIG['password_hint'][$GLOBALS['PWD_STRENGTH']][1]);
}

/**
 * Processes account creation.
 *
 * @since 1.0
 * @package facileManager
 * @subpackage Installer
 */
function processAccountSetup($database) {
	global $fmdb, $fm_name;
	
	if (!function_exists('sanitize')) {
		require_once(ABSPATH . '/fm-modules/facileManager/functions.php');
	}
	
	extract($_POST);
	$user = sanitize($user_login);
	$pass = sanitize($user_password);
	$email = sanitize($user_email);
	
	/** Ensure username and password are defined */
	if (empty($user) || empty($pass)) {
		printHeader(_('Installation'), 'install');
		exit(displayAccountSetup(_('Username and password cannot be empty.')));
	}
	
	$query = "INSERT INTO `$database`.fm_users (user_login, user_password, user_email, user_caps, user_ipaddr, user_status) VALUES('$user', password('$pass'), '$email', '" . serialize(array($fm_name => array('do_everything' => 1))). "', '{$_SERVER['REMOTE_ADDR']}', 'active')";
	$result = $fmdb->query($query) or die($fmdb->last_error);
	
	addLogEntry(sprintf(_("Installer created user '%s'"), $user), $fm_name);
}

/**
 * Ensures the account is unique.
 *
 * @since 1.0
 * @package facileManager
 * @subpackage Installer
 */
function checkAccountCreation($database) {
	global $fmdb;
	
	$query = "SELECT user_id FROM `$database`.fm_users WHERE user_id='1'";
	$result = $fmdb->query($query);
	
	return ($result === false || ($result && $fmdb->num_rows)) ? true : false;
}

?>
