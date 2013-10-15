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
 * Bootstrap file for setting the ABSPATH constant
 * and loading the config.php file.
 *
 * If the fm-config.php file is not found then an error
 * will be displayed asking the visitor to set up the
 * config.php file.
 *
 * @internal This file must be parsable by PHP4.
 *
 * @package facileManager
 */

/** Define ABSPATH as this files directory */
if (!defined('ABSPATH')) define('ABSPATH', dirname(__FILE__) . '/');
if (!defined('AJAX')) {
	$GLOBALS['RELPATH'] = rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/';
} else {
	$GLOBALS['RELPATH'] = rtrim(substr($_SERVER['PHP_SELF'], 0, strpos($_SERVER['PHP_SELF'], 'fm-modules')), '/') . '/';
}

if (file_exists(ABSPATH . 'config.inc.php')) {
	
	/** The config file resides in ABSPATH */
	require_once(ABSPATH . 'config.inc.php');
	if (!function_exists('functionalCheck') && is_array($__FM_CONFIG['db'])) {
		require_once(ABSPATH . 'fm-modules/facileManager/functions.php');
	} elseif (!function_exists('functionalCheck') && !is_array($__FM_CONFIG['db'])) {
		// A config file is empty
		header('Location: ' . $GLOBALS['RELPATH'] . 'fm-install.php');
	}

	$GLOBALS['URI'] = convertURIToArray();

	if (!defined('INSTALL') && !defined('CLIENT')) {
		require_once(ABSPATH . 'fm-includes/fm-db.php');
		
		/** Enforce SSL if applicable */
		if (getOption('fm_db_version') >= 23 && getOption('enforce_ssl')) {
			if (!isSiteSecure()) {
				$fm_port_ssl = getOption('fm_port_ssl') ? getOption('fm_port_ssl') : 443;
				header('Location: https://' . $_SERVER['HTTP_HOST'] . ':' . $fm_port_ssl . $_SERVER['REQUEST_URI']);
			}
		}
		
		require_once(ABSPATH . 'fm-modules/facileManager/classes/class_logins.php');
		
		if (!$fm_login->isLoggedIn()) {
			require_once(ABSPATH . 'fm-includes/init.php');
			checkAppVersions();
		}
			
		/** Process password resets */
		if (!$fm_login->isLoggedIn() && array_key_exists('forgot_password', $_GET)) {
			$message = array_key_exists('keyInvalid', $_GET) ? '<p class="failed">That key is invalid.</p>' : null;
			if (count($_POST)) {
				$result = $fm_login->processUserPwdResetForm($_POST['user_login']);
				if ($result === true) {
					$message = '<p class="success">Your password reset email has been sent.</p>';
				} else {
					$message = '<p class="failed">' . $result . '</p>';
				}
				
				if ($_POST['is_ajax']) {
					exit($message);
				}
			}
			
			$fm_login->printUserForm($message);
			
			exit;
		}
		
		/** Process authentication */
		if (!$fm_login->isLoggedIn() && is_array($_POST) && count($_POST)) {
			$user_login = sanitize($_POST['username']);
			$user_pass  = sanitize($_POST['password']);
			
			if ($_POST['is_ajax']) {
				$logged_in = $fm_login->checkPassword($user_login, $user_pass, false);
				if (is_array($logged_in)) {
					list($reset_key, $user_login) = $logged_in;
					echo "password_reset?key=$reset_key&login=$user_login";
				} elseif (!$logged_in) {
					echo 'failed';
				} else echo $_SERVER['REQUEST_URI'];
			} else {
				if (!$fm_login->checkPassword($user_login, $user_pass, false)) $fm_login->printLoginForm();
				else header('Location: ' . $GLOBALS['RELPATH']);
			}
			
			exit;
		}
	
		/** Enforce authentication */
		if (!$fm_login->isLoggedIn()) $fm_login->printLoginForm();
		
		/** Do the logout */
		if (isset($_GET) && array_key_exists('logout', $_GET)) {
			$fm_login->logout();
			header('Location: ' . $GLOBALS['RELPATH']);
		}
		
		/** Show/Hide errors */
		if (getOption('show_errors')) {
			error_reporting(E_ALL);
			ini_set('display_errors', true);
		} else {
			ini_set('display_errors', false);
			error_reporting(0);
		}
		
		/** Include module variables */
		include(ABSPATH . 'fm-modules/' . $fm_name . '/variables.inc.php');
		if (isset($_SESSION['module'])) include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/variables.inc.php');

		/** Handle module change request */
		if (isset($_REQUEST['module']) && !isset($_REQUEST['action'])) {
			$_SESSION['module'] = in_array($_REQUEST['module'], getActiveModules(true)) ? $_REQUEST['module'] : $fm_name;
			if ($_SESSION['module'] != $fm_name) {
				$_SESSION['user']['module_perms'] = $fm_login->getModulePerms($_SESSION['user']['id'], null, true);
			}
			header('Location: ' . $GLOBALS['RELPATH']);
		}
		
		if (!defined('UPGRADE')) {
			/** Once logged in process the menuing */
			if ($fm_login->isLoggedIn()) {
				if (isUpgradeAvailable()) {
					if ($super_admin) {
						header('Location: ' . $GLOBALS['RELPATH'] . 'fm-upgrade.php');
					} else {
						$response = '<p class="error">** The database for ' . $fm_name . ' still needs to be upgraded.  Please contact a super-admin. **</p>';
					}
				}
			}
		}
		
		/** Debug mode */
		if (array_key_exists('debug', $_GET)) {
			echo '<pre>';
			print_r($_SESSION);
			echo '</pre>';
		}
		
		$page = isset($_GET['p']) ? intval($_GET['p']) : 1;
	} elseif (defined('CLIENT')) {
		require_once(ABSPATH . 'fm-includes/fm-db.php');
	}

	/** Include module functions file */
	if (isset($_SESSION['module']) && $_SESSION['module'] != $fm_name) {
		/** Get available module variables */
		$module_var_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'variables.inc.php';
		if (file_exists($module_var_file)) {
			include($module_var_file);
		}
		
		/** Get available module functions */
		$module_functions_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'functions.php';
		if (is_file($module_functions_file) && !function_exists('moduleFunctionalCheck')) {
			include_once($module_functions_file);
		}
	}

} else {

	// A config file doesn't exist

	require_once(ABSPATH . 'fm-includes/init.php');
	require_once(ABSPATH . 'fm-includes/version.php');
	
	header('Location: ' . $GLOBALS['RELPATH'] . 'fm-install.php');

}

?>
