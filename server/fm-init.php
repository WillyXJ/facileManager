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

/**
 * Bootstrap file for setting the ABSPATH constant
 * and loading the config.php file.
 *
 * If the fm-config.php file is not found then an error
 * will be displayed asking the visitor to set up the
 * config.php file.
 *
 * @internal This file must be parsable by PHP5.
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

$_SERVER['REQUEST_URI'] = !strpos($_SERVER['REQUEST_URI'], '.php') ? str_replace('?', '.php?', $_SERVER['REQUEST_URI']) : $_SERVER['REQUEST_URI'];
$path_parts = parse_url($_SERVER['REQUEST_URI']);
$path_parts = array_merge($path_parts, pathinfo($path_parts['path']));

if (file_exists(ABSPATH . 'config.inc.php')) {
	/** Ensure session variables are not manually set */
	if (isset($_POST['_SESSION']) || isset($_GET['_SESSION']) || isset($_REQUEST['_SESSION'])) {
		unset($_POST['_SESSION']);
		unset($_GET['_SESSION']);
		unset($_REQUEST['_SESSION']);
		header('Location: ' . $GLOBALS['RELPATH']);
		exit;
	}
	
	/** The config file resides in ABSPATH */
	require_once(ABSPATH . 'config.inc.php');
	if (!function_exists('functionalCheck') && is_array($__FM_CONFIG['db'])) {
		require_once(ABSPATH . 'fm-modules/facileManager/functions.php');
	} elseif (!function_exists('functionalCheck') || !is_array($__FM_CONFIG['db'])) {
		/** A config file is empty */
		header('Location: ' . $GLOBALS['RELPATH'] . 'fm-install.php');
		exit;
	}
	
	/** Load language */
	include_once(ABSPATH . 'fm-includes/i18n.php');
	
	/** Load fmdb class */
	require_once(ABSPATH . 'fm-includes/fm-db.php');

	$GLOBALS['URI'] = convertURIToArray();

	$GLOBALS['basename'] = (($path_parts['filename'] && $path_parts['filename'] != str_replace('/', '', $GLOBALS['RELPATH'])) && substr($_SERVER['REQUEST_URI'], -1) != '/') ? $path_parts['filename'] . '.php' : 'index.php';
	
	if (!defined('INSTALL') && !defined('CLIENT') && !defined('FM_NO_CHECKS')) {
		$fmdb = new fmdb($__FM_CONFIG['db']['user'], $__FM_CONFIG['db']['pass'], $__FM_CONFIG['db']['name'], $__FM_CONFIG['db']['host']);

		/** Trim and sanitize inputs */
		$_POST = cleanAndTrimInputs($_POST);

		/** Handle special cases with config.inc.php */
		handleHiddenFlags();
	
		/** Enforce SSL if applicable */
		if (getOption('fm_db_version') >= 23 && getOption('enforce_ssl')) {
			if (!isSiteSecure()) {
				$fm_port_ssl = getOption('fm_port_ssl') ? getOption('fm_port_ssl') : 443;
				header('Location: https://' . $_SERVER['HTTP_HOST'] . ':' . $fm_port_ssl . $_SERVER['REQUEST_URI']);
				exit;
			}
		}
		
		require_once(ABSPATH . 'fm-modules/facileManager/classes/class_logins.php');

		if (!$is_logged_in = $fm_login->isLoggedIn()) {
			require_once(ABSPATH . 'fm-includes/init.php');
			checkAppVersions();
		}
			
		/** Do the logout */
		if (isset($_GET) && array_key_exists('logout', $_GET)) {
			$fm_login->logout();
			header('Location: ' . $GLOBALS['RELPATH']);
			exit;
		}
		
		/** Process password resets */
		if (!$is_logged_in && array_key_exists('forgot_password', $_GET)) {
			$message = array_key_exists('keyInvalid', $_GET) ? sprintf('<p class="failed">%s</p>', _('The specified key is invalid.')) : null;
			if (count($_POST)) {
				$result = $fm_login->processUserPwdResetForm($_POST['user_login']);
				if ($result === true) {
					$message = sprintf('<p class="success">%s</p>', _('Your password reset email has been sent to the address on file.'));
				} else {
					$message = sprintf('<div class="failed"><p>%s</p></div>', $result);
				}
				
				if ($_POST['is_ajax']) {
					exit($message);
				}
			}
			
			$fm_login->printUserForm($message);
			
			exit;
		}
		
		/** Process authentication */
		if (!$is_logged_in && is_array($_POST) && count($_POST)) {
			$user_login = $_POST['username'];
			$user_pass  = $_POST['password'];
			
			$logged_in = $fm_login->checkPassword($user_login, $user_pass);
			if (array_key_exists('is_ajax', $_POST) && $_POST['is_ajax']) {
				if ($logged_in === false) {
					echo (array_key_exists('username', $_POST) && $_POST['username']) ? 'failed' : 'force_logout';
				} elseif (is_array($logged_in)) {
					list($reset_key, $user_login) = $logged_in;
					echo "password_reset.php?key=$reset_key&login=$user_login";
				} elseif ($logged_in !== true) {
					printf('<p class="failed">%s</p>', $logged_in);
				} elseif (isMaintenanceMode()) {
					if (currentUserCan('manage_modules')) {
						echo $_SERVER['REQUEST_URI'];
					} else {
						$fm_login->logout();
						printf('<p class="failed">%s</p>', sprintf(_('%s is currently undergoing maintenance. Please try again later.'), $fm_name));
					}
				} elseif (isUpgradeAvailable()) {
					if (currentUserCan(array('do_everything', 'manage_modules')) || (getOption('fm_db_version') < 32 && $_SESSION['user']['fm_perms'] & 1)) {
						echo $GLOBALS['RELPATH'] . 'fm-upgrade.php';
					} else {
						$fm_login->logout();
						printf('<p class="failed">%s</p>', sprintf(_('The database for %s and its modules still needs to be upgraded.<br />Please contact a privileged user.'), $fm_name));
					}
				} else echo $_SERVER['REQUEST_URI'];
			} else {
				if (!$logged_in) {
					$fm_login->printLoginForm();
				} else {
					header('Location: ' . $_SERVER['REQUEST_URI']);
					exit;
				}
			}
			
			exit;
		}
	
		/** Enforce authentication */
		if (!$is_logged_in) {
			if (defined('AJAX')) {
				exit('force_logout');
			}
			$fm_login->printLoginForm();
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
			session_start();
			setUserModule($_REQUEST['module']);
			session_write_close();
			header('Location: ' . $GLOBALS['RELPATH']);
			exit;
		}
		
		/** Ensure selected module is indeed active */
		if (isset($_SESSION['module']) && $_SESSION['module'] != $fm_name && !in_array($_SESSION['module'], getActiveModules())) {
			session_start();
			$_SESSION['module'] = $fm_name;
			session_write_close();
			header('Location: ' . $GLOBALS['RELPATH'] . 'admin-modules.php');
			exit;
		}
		
		if (!defined('UPGRADE')) {
			/** Once logged in process the menuing */
			if ($is_logged_in) {
				if (isUpgradeAvailable() || (isMaintenanceMode() && !currentUserCan('manage_modules'))) {
					if (defined('AJAX')) {
						exit('<div class="hidden">force_logout</div>');
					}
					$fm_login->logout();
					header('Location: ' . $GLOBALS['RELPATH']);
					exit;
				}
			}
		}
		
		/** Handle sort orders */
		if (array_key_exists('sort_by', $_GET)) {
			handleSortOrder();
		}
		
		/** Handle pagination record counts */
		if (array_key_exists('rc', $_GET)) {
			session_start();
			$_SESSION['user']['record_count'] = in_array($_GET['rc'], $__FM_CONFIG['limit']['records']) ? $_GET['rc'] : $__FM_CONFIG['limit']['records'][0];
		} else {
			if (!isset($_SESSION['user']['record_count'])) {
				session_start();
				$_SESSION['user']['record_count'] = $__FM_CONFIG['limit']['records'][0];
			}
		}
		session_write_close();
		
		/** Debug mode */
		if (array_key_exists('debug', $_GET)) {
			echo '<pre>';
			print_r($_SESSION);
			echo '</pre>';
		}
		
		$page = isset($_GET['p']) && intval($_GET['p']) > 0 ? intval($_GET['p']) : 1;
		
		/** Build the user menu */
		include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . 'facileManager' . DIRECTORY_SEPARATOR . 'menu.php');
	} elseif (defined('CLIENT')) {
		$fmdb = new fmdb($__FM_CONFIG['db']['user'], $__FM_CONFIG['db']['pass'], $__FM_CONFIG['db']['name'], $__FM_CONFIG['db']['host']);

		/** Trim and sanitize inputs */
		$_POST = cleanAndTrimInputs($_POST);
	}
	
	if (isset($_POST['module_name'])) {
		session_start();
		$_SESSION['module'] = $_POST['module_name'];
		session_write_close();
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

		if (!defined('CLIENT') && !defined('INSTALL') && !defined('UPGRADE') && !defined('FM_NO_CHECKS')) {
			if (function_exists('buildModuleMenu')) {
				buildModuleMenu();
			}
		}
	}

} elseif (!defined('FM_NO_CHECKS')) {

	/** A config file doesn't exist */
	header('Location: ' . $GLOBALS['RELPATH'] . 'fm-install.php');
	exit;

}
