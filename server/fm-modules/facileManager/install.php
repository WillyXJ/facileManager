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
 * facileManager Installer Functions
 *
 * @package facileManager
 * @subpackage Installer
 *
 */

/**
 * Processes installation.
 *
 * @since 1.0
 * @package facileManager
 * @subpackage Installer
 */
function processSetup() {
	global $__FM_CONFIG;
	extract($_POST);

	foreach ($ssl as $key=>$val) {
		if (isset($install_enable_ssl)) {
			$__FM_CONFIG['db'][$key] = $val;
		} else {
			unset($_POST['ssl']);
			break;
		}
	}
	
	include_once(ABSPATH . 'fm-includes/fm-db.php');
	$fmdb = new fmdb($dbuser, $dbpass, $dbname, $dbhost, 'silent connect');
	if (!$fmdb->dbh) {
		exit(sprintf('ERROR: %s', _('Could not connect to MySQL')));
	} else {
		$db_selected = $fmdb->select($dbname, 'silent');
		if ($fmdb->last_error && strpos($fmdb->last_error, 'Unknown database') === false) {
			exit(sprintf('ERROR: %s', $fmdb->last_error));
		}
		if ($db_selected) {
			$tables = $fmdb->query('SHOW TABLES FROM `' . $dbname . '`;');
			if ($fmdb->num_rows) {
				exit(sprintf('ERROR: %s<br />%s', _('Database already exists and contains one or more tables.'), _('Please choose a different name.')));
			}
		}
	}
	
	createConfig();
}

/**
 * Attempts to create config.inc.php
 *
 * @since 1.0
 * @package facileManager
 * @subpackage Installer
 */
function createConfig() {
	global $step;
	
	$temp_config = generateConfig();
	$temp_file = ABSPATH . 'config.inc.php';
	
	if (!file_exists($temp_file) || !file_get_contents($temp_file)) {
		if (@file_put_contents($temp_file, '') === false) {

			$left_content = sprintf('<div class="flex flex-column">
	<p>' . _('%s cannot be created. Please manually create it with the following contents:') . '</p>
	<textarea rows="18">%s</textarea>
	<p>' . _('Once done, click "Install."') . '</p>
	</div><div class="button-wrapper"><a href="?step=2" class="button click_once">' . _('Install') . '</a></div>', 
			"<code>$temp_file</code>", $temp_config);
		} else {
			$left_content = '<div class="flex flex-column"><table class="form-table">';
			
			$retval = @file_put_contents($temp_file, $temp_config) ? true : false;
			list($rv, $tmp_content) = displayProgress(_('Creating Configuration File'), $retval, 'display');
			
			$left_content .= $tmp_content . "</table>\n";
			
			if ($retval) {
				$left_content .= '<p>' .
					_("Config file has been created! Now let's create the database schema.") .
					'</p></div><div class="button-wrapper"><a href="?step=2" class="button click_once">' . _('Continue') . '</a></div>';
			} else {
				$left_content .= '<p>' . _('Config file creation failed. Please try again.') .
					'</p></div><div class="button-wrapper"><a href="?step=2" class="button click_once">' . _('Try Again') . '</a></div>';
			}
		}
		
		echo displayPreAppForm(_('Installation'), 'window', $left_content, displayProgressBar($step), 'flex', null, '?step=3');
	}
}

/**
 * Generates config.inc.php content
 *
 * @since 1.0
 * @package facileManager
 * @subpackage Installer
 */
function generateConfig() {
	global $fm_name;
	
	extract($_POST);
	$dbname = sanitize($dbname, '_');
	
	$dbpass = str_replace("'", "\'", $dbpass);

	if (isset($install_enable_ssl)) {
		$ssl_config = <<<CFG
/** Database SSL connection settings (optional) */
\$__FM_CONFIG['db']['key'] = '{$ssl['key']}';
\$__FM_CONFIG['db']['cert'] = '{$ssl['cert']}';
\$__FM_CONFIG['db']['ca'] = '{$ssl['ca']}';
\$__FM_CONFIG['db']['capath'] = '{$ssl['capth']}';
\$__FM_CONFIG['db']['cipher'] = '{$ssl['cipher']}';

CFG;
	} else {
		$ssl_config = null;
	}

	$config = <<<CFG
<?php

/**
 * Contains configuration details for $fm_name
 *
 * @package $fm_name
 *
 */

/** Database credentials */
\$__FM_CONFIG['db']['host'] = '$dbhost';
\$__FM_CONFIG['db']['user'] = '$dbuser';
\$__FM_CONFIG['db']['pass'] = '$dbpass';
\$__FM_CONFIG['db']['name'] = '$dbname';

$ssl_config
require_once(ABSPATH . 'fm-modules/facileManager/functions.php');

CFG;

	return $config;
}

/**
 * Processes account creation.
 *
 * @since 1.0
 * @package facileManager
 * @subpackage Installer
 */
function processAccountSetup($database) {
	global $fmdb, $fm_name, $__FM_CONFIG;
	
	if (!function_exists('sanitize')) {
		require_once(ABSPATH . '/fm-modules/facileManager/functions.php');
	}
	
	extract($_POST);
	$user = sanitize($user_login);
	$pass = sanitize($user_password);
	$cpass = $cpassword;
	$email = sanitize($user_email);

	/** Ensure username and password are defined */
	if (empty($user) || empty($pass)) {
		exit(sprintf('ERROR: %s', _('Username and password cannot be empty.')));
	}
	if ($cpass != $pass) {
		exit(sprintf('ERROR: %s', _('Passwords do not match.')));
	}
	if ($passwd_check != $__FM_CONFIG['password_hint'][$GLOBALS['PWD_STRENGTH']][0]) {
		exit(sprintf('ERROR: %s', _('Password does not meet the complexity requirements.')));
	}
	
	$query = "INSERT INTO `$database`.fm_users (user_login, user_password, user_email, user_caps, user_ipaddr, user_status) VALUES('$user', '" . password_hash($pass, PASSWORD_DEFAULT) . "', '$email', '" . serialize(array($fm_name => array('do_everything' => 1))). "', '{$_SERVER['REMOTE_ADDR']}', 'active')";
	$result = $fmdb->query($query) or die($fmdb->last_error);
	
	addLogEntry(sprintf(_("Installer created user '%s'"), $user), $fm_name);

	$left_content = sprintf('<p>%s</p>', sprintf(_("Installation is complete! Click 'Next' to login and start using %s."), $fm_name));
	$left_content .= sprintf('<div class="button-wrapper"><a href="%s" class="button"><i class="fa fa-sign-in" aria-hidden="true"></i> %s</a></div>', $GLOBALS['RELPATH'], _('Next'));
	echo displayPreAppForm(_('Installation Complete'), 'window', $left_content, displayProgressBar(4), 'flex');
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
	
	$query = "SELECT user_id FROM `$database`.fm_users WHERE user_status='active' AND user_auth_type='1' AND user_caps='" . serialize(array('facileManager' => array('do_everything' => 1))) . "' ORDER BY user_id ASC LIMIT 1";
	$result = $fmdb->query($query);

	return ($result === false || ($result && $fmdb->num_rows)) ? true : false;
}

/**
 * Display progress bar
 *
 * @since 5.4.0
 * @package facileManager
 * @subpackage Installer
 *
 * @param integer $step Current step
 * @return string
 */
function displayProgressBar($step = 0) {
	$form_steps = [
		1 => _('Configuration File'),
		2 => _('Load Database'),
		3 => _('Create Account'),
		4 => _('Complete!')
	];

	if ($step <= 1) {
		$step = 1;
	}

	$step_progress = '';
	foreach ($form_steps as $step_number => $title) {
		$class = '';
		if ($step_number < $step) {
			$class = 'complete';
		} elseif ($step_number == $step) {
			$class = 'active';
		}
		$step_progress .= sprintf('<div class="flex %s"><div class="step-number flex">%s</div><div class="step">%s</div></div>', $class, $step_number, $title);
	}
	$return = sprintf('<div class="progress-bar flex flex-column">%s</div>', $step_progress);

	return $return;
}

/**
 * Processes installation.
 *
 * @since 1.0
 * @package facileManager
 * @subpackage Installer
 */
function fmInstall($database) {
	global $fm_name, $branding_logo, $step;
	
	
	list($retval, $left_content) = installDatabase($database);
	
	$left_content = '<div class="flex flex-column"><table class="form-table">' . $left_content . "</table>\n";

	if ($retval) {
		$left_content .= '<p>' . _("Database setup is complete! Now let's create your administrative account.") .
			'</p></div><div class="button-wrapper"><a href="?step=3" class="button">' . _('Continue') . '</a></div>';
	} else {
		$left_content .= '<p>' . _("Database setup failed. Please try again.") .
			'</p></div><div class="button-wrapper"><a href="?step=2" class="button click_once">' . _('Try Again') . '</a></div>';
	}
	
	echo displayPreAppForm(_('Installation'), 'window', $left_content, displayProgressBar($step), 'flex', null, '?step=3');
}


function installDatabase($database) {
	global $fmdb, $fm_version, $fm_name;
	
	$content = '';
	$output = false;

	$db_selected = $fmdb->select($database, 'silent');
	if (!$db_selected) {
		$query = sanitize("CREATE DATABASE IF NOT EXISTS `$database` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci");
		$fmdb->query($query);
		list($output, $content) = displayProgress(_('Creating Database'), $fmdb->result, 'display');
	} else {
		$output = true;
	}
	
	if ($output === true) {
		list($output, $tmp_content) = installSchema($database);
		$content .= $tmp_content;
	}

	if ($output === true) {
		$modules = getAvailableModules();
		if (count($modules)) {
			$content .= sprintf('<tr><td colspan="2" id="install_module_list"><p><b>%s</b><br />%s</p></td></tr>',
					_('The following modules were installed as well:'),
					_('(They can always be uninstalled later.)')
				);

			foreach ($modules as $module_name) {
				if (file_exists(dirname(__FILE__) . '/../' . $module_name . '/install.php')) {
					include(dirname(__FILE__) . '/../' . $module_name . '/install.php');
					
					$function = 'install' . $module_name . 'Schema';
					if (function_exists($function)) {
						list($output, $tmp_content) = $function($database, $module_name, 'display');
						$content .= $tmp_content;
					}
					if ($output == true) {
						addLogEntry(sprintf(_('%s %s was installed.'), $module_name, $fm_version), $module_name);
					}
				}
			}
		}
	}
	
	return [$output, $content];
}


function installSchema($database) {
	global $fmdb;
	
	include(ABSPATH . 'fm-includes/version.php');
	include(ABSPATH . 'fm-modules/facileManager/variables.inc.php');
	
	$default_timezone = date_default_timezone_get() ? date_default_timezone_get() : 'America/Denver';

	$table[] = <<<TABLESQL
CREATE TABLE IF NOT EXISTS `$database`.`fm_accounts` (
  `account_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
  `account_key` varchar(255) NOT NULL,
  `account_name` VARCHAR(255) NOT NULL ,
  `account_status` ENUM( 'active',  'disabled',  'deleted') NOT NULL DEFAULT  'active'
) ENGINE = INNODB DEFAULT CHARSET=utf8;
TABLESQL;

	$table[] = <<<TABLESQL
CREATE TABLE IF NOT EXISTS `$database`.`fm_groups` (
  `group_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `group_name` varchar(128) NOT NULL,
  `group_caps` text,
  `group_comment` text,
  `group_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`group_id`)
) ENGINE=INNODB  DEFAULT CHARSET=utf8;
TABLESQL;

	$table[] = <<<TABLESQL
CREATE TABLE IF NOT EXISTS `$database`.`fm_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_login` varchar(255) NOT NULL DEFAULT '0',
  `account_id` int(11) NOT NULL DEFAULT '1',
  `log_module` varchar(255) NOT NULL,
  `log_timestamp` int(10) NOT NULL DEFAULT '0',
  `log_data` mediumtext NOT NULL,
  PRIMARY KEY (`log_id`)
) ENGINE=INNODB DEFAULT CHARSET=utf8 ;
TABLESQL;

	$table[] = <<<TABLESQL
CREATE TABLE IF NOT EXISTS `$database`.`fm_options` (
  `option_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '0',
  `module_name` varchar(255) DEFAULT NULL,
  `option_name` varchar(50) NOT NULL,
  `option_value` text NOT NULL,
  PRIMARY KEY (`option_id`)
) ENGINE=INNODB  DEFAULT CHARSET=utf8 ;
TABLESQL;

	$table[] = <<<TABLESQL
CREATE TABLE IF NOT EXISTS `$database`.`fm_temp_auth_keys` (
  `pwd_id` varchar(255) NOT NULL,
  `pwd_login` int(11) NOT NULL,
  `pwd_timestamp` int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`pwd_id`)
) ENGINE=INNODB DEFAULT CHARSET=utf8;
TABLESQL;

	$table[] = <<<TABLESQL
CREATE TABLE IF NOT EXISTS `$database`.`fm_users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `user_login` varchar(128) NOT NULL,
  `user_password` varchar(255) NOT NULL,
  `user_display_name` varchar(255) NULL,
  `user_email` varchar(255) NOT NULL,
  `user_comment` varchar(255) DEFAULT NULL,
  `user_group` int(11) DEFAULT NULL,
  `user_2fa_method` enum('0','app','email') NOT NULL DEFAULT '0',
  `user_2fa_secret` varchar(255) DEFAULT NULL,
  `user_2fa_recovery_code` varchar(255) DEFAULT NULL,
  `user_default_module` varchar(255) DEFAULT NULL,
  `user_theme` varchar(255) NULL DEFAULT NULL,
  `user_theme_mode` enum('Light','Dark','System') NULL DEFAULT 'System',
  `user_auth_type` int(1) NOT NULL DEFAULT '1',
  `user_caps` text NULL DEFAULT NULL,
  `user_module_prefs` TEXT NULL DEFAULT NULL,
  `user_last_login` int(10) NOT NULL DEFAULT '0',
  `user_ipaddr` varchar(255) DEFAULT NULL,
  `user_force_pwd_change` enum('yes','no') NOT NULL DEFAULT 'no',
  `user_template_only` enum('yes','no') NOT NULL DEFAULT 'no',
  `user_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`user_id`)
) ENGINE=INNODB  DEFAULT CHARSET=utf8;
TABLESQL;

$table[] = <<<TABLESQL
CREATE TABLE IF NOT EXISTS `$database`.`fm_keys` (
  `key_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `user_id` int(11) NOT NULL,
  `key_name` varchar(255) NULL,
  `key_token` varchar(255) NOT NULL,
  `key_secret` varchar(255) NOT NULL,
  `key_comment` text NULL,
  `key_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`key_id`),
  UNIQUE KEY `idx_key_token` (`key_token`)
) ENGINE=INNODB DEFAULT CHARSET=utf8;
TABLESQL;


	$inserts[] = <<<INSERTSQL
INSERT IGNORE INTO  `$database`.`fm_accounts` (`account_id` ,`account_key`, `account_name` ,`account_status`) VALUES ('1' , 'default', 'Default Account',  'active');
INSERTSQL;

	$inserts[] = <<<INSERTSQL
INSERT INTO `$database`.`fm_options` (option_name, option_value) 
	SELECT 'fm_db_version', '$fm_db_version' FROM DUAL
WHERE NOT EXISTS
	(SELECT option_name FROM `$database`.`fm_options` WHERE option_name = 'fm_db_version');
INSERTSQL;

	$inserts[] = <<<INSERTSQL
INSERT INTO `$database`.`fm_options` (`option_name`, `option_value`) 
	SELECT 'auth_method', '1' FROM DUAL
WHERE NOT EXISTS
	(SELECT option_name FROM `$database`.`fm_options` WHERE option_name = 'auth_method');
INSERTSQL;

	$inserts[] = <<<INSERTSQL
INSERT INTO `$database`.`fm_options` (`option_name`, `option_value`) 
	SELECT 'mail_enable', '0' FROM DUAL
WHERE NOT EXISTS
	(SELECT option_name FROM `$database`.`fm_options` WHERE option_name = 'mail_enable');
INSERTSQL;

	$inserts[] = <<<INSERTSQL
INSERT INTO `$database`.`fm_options` (`option_name`, `option_value`) 
	SELECT 'mail_smtp_host', 'localhost' FROM DUAL
WHERE NOT EXISTS
	(SELECT option_name FROM `$database`.`fm_options` WHERE option_name = 'mail_smtp_host');
INSERTSQL;

	$inserts[] = "
INSERT INTO `$database`.`fm_options` (`option_name`, `option_value`) 
	SELECT 'mail_from', 'noreply@" . php_uname('n') . "' FROM DUAL
WHERE NOT EXISTS
	(SELECT option_name FROM `$database`.`fm_options` WHERE option_name = 'mail_from');
";

	$inserts[] = <<<INSERTSQL
INSERT INTO `$database`.`fm_options` (`option_name`, `option_value`) 
	SELECT 'mail_smtp_tls', '0' FROM DUAL
WHERE NOT EXISTS
	(SELECT option_name FROM `$database`.`fm_options` WHERE option_name = 'mail_smtp_tls');
INSERTSQL;

	$inserts[] = <<<INSERTSQL
INSERT INTO `$database`.`fm_options` (`account_id` ,`option_name`, `option_value`) 
	SELECT 1, 'timezone', '$default_timezone' FROM DUAL
WHERE NOT EXISTS
	(SELECT option_name FROM `$database`.`fm_options` WHERE option_name = 'timezone');
INSERTSQL;

	$inserts[] = <<<INSERTSQL
INSERT INTO `$database`.`fm_options` (`account_id` ,`option_name`, `option_value`) 
	SELECT 1, 'date_format', 'D, d M Y' FROM DUAL
WHERE NOT EXISTS
	(SELECT option_name FROM `$database`.`fm_options` WHERE option_name = 'date_format');
INSERTSQL;

	$inserts[] = <<<INSERTSQL
INSERT INTO `$database`.`fm_options` (`account_id` ,`option_name`, `option_value`) 
	SELECT 1, 'time_format', 'H:i:s O' FROM DUAL
WHERE NOT EXISTS
	(SELECT option_name FROM `$database`.`fm_options` WHERE option_name = 'time_format');
INSERTSQL;

	$tmp = sys_get_temp_dir();
	$inserts[] = <<<INSERTSQL
INSERT INTO `$database`.`fm_options` (`account_id` ,`option_name`, `option_value`) 
	SELECT 0, 'fm_temp_directory', '$tmp' FROM DUAL
WHERE NOT EXISTS
	(SELECT option_name FROM `$database`.`fm_options` WHERE option_name = 'fm_temp_directory');
INSERTSQL;

	$inserts[] = <<<INSERTSQL
INSERT INTO `$database`.`fm_options` (`account_id` ,`option_name`, `option_value`) 
	SELECT 0, 'software_update', 1 FROM DUAL
WHERE NOT EXISTS
	(SELECT option_name FROM `$database`.`fm_options` WHERE option_name = 'software_update');
INSERTSQL;

	$inserts[] = <<<INSERTSQL
INSERT INTO `$database`.`fm_options` (`account_id` ,`option_name`, `option_value`) 
	SELECT 0, 'software_update_interval', 'week' FROM DUAL
WHERE NOT EXISTS
	(SELECT option_name FROM `$database`.`fm_options` WHERE option_name = 'software_update_interval');
INSERTSQL;

	$inserts[] = <<<INSERTSQL
INSERT INTO `$database`.`fm_options` (`account_id` ,`option_name`, `option_value`) 
	SELECT 0, 'client_auto_register', 1 FROM DUAL
WHERE NOT EXISTS
	(SELECT option_name FROM `$database`.`fm_options` WHERE option_name = 'client_auto_register');
INSERTSQL;

	$inserts[] = <<<INSERTSQL
INSERT INTO `$database`.`fm_options` (`account_id` ,`option_name`, `option_value`) 
	SELECT 0, 'ssh_user', 'fm_user' FROM DUAL
WHERE NOT EXISTS
	(SELECT option_name FROM `$database`.`fm_options` WHERE option_name = 'ssh_user');
INSERTSQL;


	/** Create table schema */
	foreach ($table as $schema) {
		$result = $fmdb->query($schema);
		if ($fmdb->last_error) {
			return displayProgress(sprintf(_('Creating %s Schema'), $fm_name), $fmdb->result, 'display', $fmdb->last_error);
		}
	}

	/** Insert site values if not already present */
	$query = "SELECT * FROM fm_options";
	$temp_result = $fmdb->query($query);
	if (!$fmdb->num_rows) {
		foreach ($inserts as $query) {
			$fmdb->query($query);
			if ($fmdb->last_error) {
				return displayProgress(sprintf(_('Creating %s Schema'), $fm_name), $fmdb->result, 'display', $fmdb->last_error);
			}
		}
	}
	
	addLogEntry(sprintf(_('%s %s was installed.'), $fm_name, $fm_version), $fm_name);

	return displayProgress(sprintf(_('Creating %s Schema'), $fm_name), $fmdb->result, 'display');
}
