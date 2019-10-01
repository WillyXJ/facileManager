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
 * facileManager Installer Functions
 *
 * @package facileManager
 * @subpackage Installer
 *
 */

/**
 * Attempts to create config.inc.php
 *
 * @since 1.0
 * @package facileManager
 * @subpackage Installer
 */
function createConfig() {
	global $fm_name, $branding_logo;
	
	$temp_config = generateConfig();
	$temp_file = ABSPATH . 'config.inc.php';
	
	if (!file_exists($temp_file) || !file_get_contents($temp_file)) {
		if (@file_put_contents($temp_file, '') === false) {

			printf('
	<div id="fm-branding">
		<img src="%s" /><span>%s</span>
	</div>
	<div id="window">
	<p>' . _('I cannot create %s so please manually create it with the following contents:') . '</p>
	<textarea rows="18">%s</textarea>
	<p>' . _('Once done, click "Install."') . '</p>
	<p class="step"><a href="?step=3" class="button click_once">' . _('Install') . '</a></p></div>', 
			$branding_logo, _('Install'), "<code>$temp_file</code>", $temp_config);
		} else {
			printf('<form method="post" action="?step=3">
	<div id="fm-branding">
		<img src="%s" /><span>%s</span>
	</div>
	<div id="window">
		<table class="form-table">' . "\n", $branding_logo, _('Install'));
			
			$retval = @file_put_contents($temp_file, $temp_config) ? true : false;
			displayProgress(_('Creating Configuration File'), $retval);
			
			echo "</table>\n";
			
			if ($retval) {
				echo '<p>' .
					_("Config file has been created! Now let's create the database schema.") .
					'</p><p class="step"><a href="?step=3" class="button click_once">' . _('Continue') . '</a></p>';
			} else {
				echo '<p>' . _('Config file creation failed. Please try again.') .
					'</p><p class="step"><a href="?step=2" class="button click_once">' . _('Try Again') . '</a></p>';
			}
			
			echo "</div></form>\n";
		}
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

require_once(ABSPATH . 'fm-modules/facileManager/functions.php');

?>
CFG;

	return $config;
}

/**
 * Processes installation.
 *
 * @since 1.0
 * @package facileManager
 * @subpackage Installer
 */
function fmInstall($database) {
	global $fm_name, $branding_logo;
	
	printf('<form method="post" action="?step=3">
	<div id="fm-branding">
		<img src="%s" /><span>%s</span>
	</div>
	<div id="window">
<table class="form-table">' . "\n", $branding_logo, _('Install'));

	$retval = installDatabase($database);
	
	echo "</table>\n";

	if ($retval) {
		echo '<p>' . _("Database setup is complete! Now let's create your administrative account.") .
			'</p><p class="step"><a href="?step=4" class="button">' . _('Continue') . '</a></p>';
	} else {
		echo '<p>' . _("Database setup failed. Please try again.") .
			'</p><p class="step"><a href="?step=3" class="button click_once">' . _('Try Again') . '</a></p>';
	}
	
	echo "</div></form>\n";
}


function installDatabase($database) {
	global $fmdb, $fm_version, $fm_name;
	
	$db_selected = $fmdb->select($database, 'silent');
	if (!$db_selected) {
		$query = sanitize("CREATE DATABASE IF NOT EXISTS `$database` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci");
		$result = $fmdb->query($query);
		$output = displayProgress(_('Creating Database'), $fmdb->result);
	} else {
		$output = true;
	}
	
	if ($output == true) $output = installSchema($database);
	if ($output == true) {
		$modules = getAvailableModules();
		if (count($modules)) {
			printf('<tr><td colspan="2" id="install_module_list"><p><b>%s</b><br />%s</p></td></tr>',
					_('The following modules were installed as well:'),
					_('(They can always be uninstalled later.)')
				);

			foreach ($modules as $module_name) {
				if (file_exists(dirname(__FILE__) . '/../' . $module_name . '/install.php')) {
					include(dirname(__FILE__) . '/../' . $module_name . '/install.php');
					
					$function = 'install' . $module_name . 'Schema';
					if (function_exists($function)) {
						$output = $function($database, $module_name);
					}
					if ($output == true) {
						addLogEntry(sprintf(_('%s %s was born.'), $module_name, $fm_version), $module_name);
					}
				}
			}
		}
	}
	
	return ($output == true) ? true : false;
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
) ENGINE = MYISAM DEFAULT CHARSET=utf8;
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
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
TABLESQL;

	$table[] = <<<TABLESQL
CREATE TABLE IF NOT EXISTS `$database`.`fm_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_login` varchar(255) NOT NULL DEFAULT '0',
  `account_id` int(11) NOT NULL DEFAULT '1',
  `log_module` varchar(255) NOT NULL,
  `log_timestamp` int(10) NOT NULL DEFAULT '0',
  `log_data` text NOT NULL,
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
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;
TABLESQL;

	$table[] = <<<TABLESQL
CREATE TABLE IF NOT EXISTS `$database`.`fm_pwd_resets` (
  `pwd_id` varchar(255) NOT NULL,
  `pwd_login` int(11) NOT NULL,
  `pwd_timestamp` int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`pwd_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
TABLESQL;

	$table[] = <<<TABLESQL
CREATE TABLE IF NOT EXISTS `$database`.`fm_users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `user_login` varchar(128) NOT NULL,
  `user_password` varchar(255) NOT NULL,
  `user_email` varchar(255) NOT NULL,
  `user_comment` varchar(255) DEFAULT NULL,
  `user_group` INT(11) DEFAULT NULL,
  `user_default_module` varchar(255) DEFAULT NULL,
  `user_auth_type` int(1) NOT NULL DEFAULT '1',
  `user_caps` text,
  `user_last_login` int(10) NOT NULL DEFAULT '0',
  `user_ipaddr` varchar(255) DEFAULT NULL,
  `user_force_pwd_change` enum('yes','no') NOT NULL DEFAULT 'no',
  `user_template_only` enum('yes','no') NOT NULL DEFAULT 'no',
  `user_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`user_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
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
	SELECT 'mail_enable', '1' FROM DUAL
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
			return displayProgress(sprintf(_('Creating %s Schema'), $fm_name), $fmdb->result, 'noisy', $fmdb->last_error);
		}
	}

	/** Insert site values if not already present */
	$query = "SELECT * FROM fm_options";
	$temp_result = $fmdb->query($query);
	if (!$fmdb->num_rows) {
		foreach ($inserts as $query) {
			$result = $fmdb->query($query);
			if ($fmdb->last_error) {
				return displayProgress(sprintf(_('Creating %s Schema'), $fm_name), $fmdb->result, 'noisy', $fmdb->last_error);
			}
		}
	}
	
	addLogEntry(sprintf(_('%s %s was born.'), $fm_name, $fm_version), $fm_name);

	return displayProgress(sprintf(_('Creating %s Schema'), $fm_name), $fmdb->result);
}


?>
