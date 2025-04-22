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
 * facileManager Upgrader Functions
 *
 * @package facileManager
 * @subpackage Upgrader
 *
 */

/**
 * Processes upgrade.
 *
 * @since 1.0
 * @package facileManager
 * @subpackage Upgrader
 */
function fmUpgrade($database) {
	global $fmdb, $branding_logo;
	include(ABSPATH . 'fm-includes/version.php');
	include(ABSPATH . 'fm-modules/facileManager/variables.inc.php');
	
	$errors = false;
	
	$GLOBALS['running_db_version'] = getOption('fm_db_version');
	
	printf('<div id="fm-branding">
		<img src="%s" /><span>%s</span>
	</div>
	<div id="window"><table class="form-table">' . "\n", $branding_logo, _('Upgrade'));

	/**	Get latest upgrade function */
	$tmp_all_functions = get_defined_functions();
	$upgrade_function = preg_grep('/^fmupgrade_.*/', $tmp_all_functions['user']);
	$upgrade_function = end($upgrade_function);
	unset($tmp_all_functions);
	
	/** Checks to support older versions (ie n-3 upgrade scenarios */
	$success = ($GLOBALS['running_db_version'] < $fm_db_version) ? $upgrade_function($database) : true;

	if ($success) {
		$success = upgradeConfig('fm_db_version', $fm_db_version);
		setOption('version_check', array('timestamp' => 0, 'data' => null), 'update', true, 0, $fm_name);
	} else {
		$errors = true;
	}
	
	displayProgress(sprintf(_('Upgrading Core v%s Schema'), $fm_version), $success);
	
	/** Upgrade any necessary modules */
	include(ABSPATH . 'fm-modules/'. $fm_name . '/classes/class_tools.php');
	$module_list = $fmdb->get_results("SELECT module_name,option_value FROM fm_options WHERE option_name='version'");
	$num_rows = $fmdb->num_rows;
	for ($x=0; $x<$num_rows; $x++) {
		$module_name = $module_list[$x]->module_name;
		$success = $fm_tools->upgradeModule($module_name, 'quiet', $module_list[$x]->option_value);
		if ($success !== 'already current') {
			if (!$success || $fmdb->last_error) {
				$errors = true;
				$success = false;
			} else {
				$success = true;
			}
			displayProgress(sprintf(_('Upgrading %s Schema'), $module_name), $success);
		}
	}

	echo "</table>";
	
	if (!$errors) {
		displaySetupMessage(1, $GLOBALS['RELPATH']);
	} else {
		displaySetupMessage(2);
	}
	
	echo "</div>";
}


function fmUpgrade_100($database) {
	global $fmdb;
	
	$queries[] = "ALTER TABLE  `$database`.`fm_users` CHANGE  `user_ipaddr`  `user_ipaddr` VARCHAR( 255 ) NULL DEFAULT NULL ";
	
	/** Create table schema */
	if (count($queries) && $queries[0]) {
		foreach ($queries as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}
	
	upgradeConfig('fm_db_version', 11, false);

	return true;
}


function fmUpgrade_101($database) {
	global $fmdb;
	
	$success = true;
	
	/** Prereq */
	$success = ($GLOBALS['running_db_version'] < 11) ? fmUpgrade_100($database) : true;
	
	if ($success) {
		/** Schema change */
		$queries[] = "ALTER TABLE  `$database`.`fm_users` ADD  `user_force_pwd_change` ENUM(  'yes',  'no' ) NOT NULL DEFAULT  'no' AFTER  `user_ipaddr` ";
		
		/** Create table schema */
		if (count($queries) && $queries[0]) {
			foreach ($queries as $schema) {
				$fmdb->query($schema);
				if (!$fmdb->result || $fmdb->sql_errors) return false;
			}
		}
	}

	upgradeConfig('fm_db_version', 14, false);
	
	return $success;
}


function fmUpgrade_104($database) {
	global $fmdb;
	
	$success = true;
	
	/** Prereq */
	$success = ($GLOBALS['running_db_version'] < 14) ? fmUpgrade_101($database) : true;
	
	if ($success) {
		$queries = $inserts = $updates = null;
		
		/** Schema change */
		if ($GLOBALS['running_db_version'] < 14) {
			$queries[] = "ALTER TABLE `$database`. `fm_logs` ENGINE = INNODB";
			$queries[] = "ALTER TABLE `$database`. `fm_perms` ADD  `perm_extra` TEXT NULL ";
		}
		if ($GLOBALS['running_db_version'] < 15) {
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
		}
		if ($GLOBALS['running_db_version'] < 17) {
			$queries[] = "ALTER TABLE  `$database`.`fm_users` CHANGE  `user_status`  `user_status` ENUM(  'active',  'disabled',  'deleted' ) NOT NULL DEFAULT  'active'";
		}
		if ($GLOBALS['running_db_version'] < 18) {
			if ($GLOBALS['running_db_version'] >= 15) {
				$query = "SELECT * FROM `$database`.`fm_preferences`";
				$all_prefs = $fmdb->get_results($query);
				$count = $fmdb->num_rows;
				for ($i=0; $i<$count; $i++) {
					$inserts[] = <<<INSERTSQL
						INSERT INTO `fm_options` (`option_name`, `option_value`) 
							SELECT '{$all_prefs[$i]->pref_name}', '{$all_prefs[$i]->pref_value}' FROM DUAL
						WHERE NOT EXISTS
							(SELECT option_name FROM `$database`.`fm_options` WHERE option_name = '{$all_prefs[$i]->pref_name}');
INSERTSQL;
				}
			}
			
			$default_timezone = date_default_timezone_get() ? date_default_timezone_get() : 'Europe/London';
			
			$query = "SELECT account_id FROM `$database`.`fm_accounts`";
			$all_accounts = $fmdb->get_results($query);
			$count = $fmdb->num_rows;
			for ($j=0; $j<$count; $j++) {
				$inserts[] = <<<INSERTSQL
			INSERT INTO `$database`.`fm_options` (`option_name`, `option_value`) 
				SELECT 'mail_smtp_tls', '0' FROM DUAL
			WHERE NOT EXISTS
				(SELECT option_name FROM `$database`.`fm_options` WHERE option_name = 'mail_smtp_tls');
INSERTSQL;

				$inserts[] = <<<INSERTSQL
			INSERT INTO `$database`.`fm_options` (`account_id` ,`option_name`, `option_value`) 
				SELECT {$all_accounts[$j]->account_id}, 'timezone', '$default_timezone' FROM DUAL
			WHERE NOT EXISTS
				(SELECT option_name FROM `$database`.`fm_options` WHERE option_name = 'timezone');
INSERTSQL;

				$inserts[] = <<<INSERTSQL
			INSERT INTO `$database`.`fm_options` (`account_id` ,`option_name`, `option_value`) 
				SELECT {$all_accounts[$j]->account_id}, 'date_format', 'D, d M Y' FROM DUAL
			WHERE NOT EXISTS
				(SELECT option_name FROM `$database`.`fm_options` WHERE option_name = 'date_format');
INSERTSQL;

				$inserts[] = <<<INSERTSQL
			INSERT INTO `$database`.`fm_options` (`account_id` ,`option_name`, `option_value`) 
				SELECT {$all_accounts[$j]->account_id}, 'time_format', 'H:i:s O' FROM DUAL
			WHERE NOT EXISTS
				(SELECT option_name FROM `$database`.`fm_options` WHERE option_name = 'time_format');
INSERTSQL;

			}
			
			if ($GLOBALS['running_db_version'] >= 15) {
				$inserts[] = "DROP TABLE `$database`.`fm_preferences`";
			}

			/** Update timestamp fields with unix epoch seconds */
			$queries[] = "ALTER TABLE  `$database`.`fm_logs` CHANGE  `log_timestamp`  `log_timestamp` VARCHAR( 20 ) NOT NULL DEFAULT  '0'";
			$query = "SELECT * FROM `$database`.`fm_logs`";
			$all_results = $fmdb->get_results($query);
			$count = $fmdb->num_rows;
			for ($k=0; $k<$count; $k++) {
				$timestamp = strtotime($all_results[$k]->log_timestamp . ' ' . 'America/Denver');
				$updates[] = "UPDATE `$database`.`fm_logs` SET log_timestamp='$timestamp' WHERE log_id={$all_results[$k]->log_id}";
			}
			$updates[] = "ALTER TABLE  `$database`.`fm_logs` CHANGE  `log_timestamp`  `log_timestamp` INT( 10 ) NOT NULL DEFAULT  '0'";
			
			$queries[] = "ALTER TABLE  `$database`.`fm_users` CHANGE  `user_last_login`  `user_last_login` VARCHAR( 20 ) NOT NULL DEFAULT  '0'";
			$query = "SELECT * FROM `$database`.`fm_users`";
			$all_results = $fmdb->get_results($query);
			$count = $fmdb->num_rows;
			for ($k=0; $k<$count; $k++) {
				$timestamp = strtotime($all_results[$k]->user_last_login . ' ' . 'America/Denver');
				$updates[] = "UPDATE `$database`.`fm_users` SET user_last_login='$timestamp' WHERE user_id={$all_results[$k]->user_id}";
			}
			$updates[] = "ALTER TABLE  `$database`.`fm_users` CHANGE  `user_last_login`  `user_last_login` INT( 10 ) NOT NULL DEFAULT  '0'";
			
			$queries[] = "ALTER TABLE  `$database`.`fm_pwd_resets` CHANGE  `pwd_timestamp`  `pwd_timestamp` VARCHAR( 20 ) NOT NULL DEFAULT  '0'";
			$query = "SELECT * FROM `$database`.`fm_pwd_resets`";
			$all_results = $fmdb->get_results($query);
			$count = $fmdb->num_rows;
			for ($k=0; $k<$count; $k++) {
				$timestamp = strtotime($all_results[$k]->pwd_timestamp . ' ' . 'America/Denver');
				$updates[] = "UPDATE `$database`.`fm_pwd_resets` SET pwd_timestamp='$timestamp' WHERE pwd_id='{$all_results[$k]->pwd_id}'";
			}
			$updates[] = "ALTER TABLE  `$database`.`fm_pwd_resets` CHANGE  `pwd_timestamp`  `pwd_timestamp` INT( 10 ) NOT NULL DEFAULT  '0'";

			$queries[] = "ALTER TABLE  `$database`.`fm_users` ADD  `user_auth_type` INT( 1 ) NOT NULL DEFAULT  '1' AFTER  `user_email` ";
			$queries[] = "ALTER TABLE  `$database`.`fm_users` ADD  `user_template_only` ENUM(  'yes',  'no' ) NOT NULL DEFAULT  'no' AFTER  `user_force_pwd_change` ";
		}
		
		/** Create table schema */
		if (count($queries) && $queries[0]) {
			foreach ($queries as $schema) {
				$fmdb->query($schema);
				if (!$fmdb->result || $fmdb->sql_errors) return false;
			}
		}
	
		if (count($inserts) && $inserts[0] && $success) {
			foreach ($inserts as $query) {
				$fmdb->query($query);
				if (!$fmdb->result || $fmdb->sql_errors) return false;
			}
		}
	
		if (count($updates) && $updates[0] && $success) {
			foreach ($updates as $query) {
				$fmdb->query($query);
				if (!$fmdb->result || $fmdb->sql_errors) return false;
			}
		}
	}

	upgradeConfig('fm_db_version', 18, false);
	
	return $success;
}


function fmUpgrade_105($database) {
	global $fmdb;
	
	$success = true;
	
	/** Prereq */
	$success = ($GLOBALS['running_db_version'] < 18) ? fmUpgrade_104($database) : true;
	
	if ($success) {
		$queries = $inserts = $updates = null;

		$tmp = sys_get_temp_dir();
		/** Schema change */
		$inserts[] = <<<INSERTSQL
INSERT INTO `$database`.`fm_options` (`option_name`, `option_value`) 
	SELECT 'fm_temp_directory', '$tmp' FROM DUAL
WHERE NOT EXISTS
	(SELECT option_name FROM `$database`.`fm_options` WHERE option_name = 'fm_temp_directory');
INSERTSQL;

		if (count($inserts) && $inserts[0] && $success) {
			foreach ($inserts as $query) {
				$fmdb->query($query);
				if (!$fmdb->result || $fmdb->sql_errors) return false;
			}
		}
	}

	upgradeConfig('fm_db_version', 22, false);
	
	return $success;
}


/** fM v1.0 **/
function fmUpgrade_106($database) {
	global $fmdb;
	
	$success = true;
	
	/** Prereq */
	$success = ($GLOBALS['running_db_version'] < 22) ? fmUpgrade_105($database) : true;
	
	if ($success) {
		/** Schema change */
		$queries[] = "ALTER TABLE  `$database`.`fm_users` ADD  `user_default_module` VARCHAR( 255 ) NULL DEFAULT NULL AFTER  `user_email` ";

		/** Create table schema */
		if (count($queries) && $queries[0]) {
			foreach ($queries as $schema) {
				$fmdb->query($schema);
				if (!$fmdb->result || $fmdb->sql_errors) return false;
			}
		}
	}

	upgradeConfig('fm_db_version', 27, false);
	
	return $success;
}


/** fM v1.0.1 **/
function fmUpgrade_107($database) {
	global $fmdb;
	
	$success = true;
	
	/** Prereq */
	$success = ($GLOBALS['running_db_version'] < 27) ? fmUpgrade_106($database) : true;
	
	if ($success) {
		/** Schema change */
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

		if (count($inserts) && $inserts[0] && $success) {
			foreach ($inserts as $query) {
				$fmdb->query($query);
				if ($fmdb->last_error) {
					echo $fmdb->last_error;
					return false;
				}
			}
		}
	}

	upgradeConfig('fm_db_version', 28, false);
	
	return $success;
}


/** fM v1.2-beta1 **/
function fmUpgrade_1201($database) {
	global $fmdb, $fm_name;
	
	$success = true;
	
	/** Prereq */
	$success = ($GLOBALS['running_db_version'] < 28) ? fmUpgrade_107($database) : true;
	
	if ($success) {
		/** Schema change */
		$queries[] = "ALTER TABLE  `$database`.`fm_options` ADD  `module_name` VARCHAR( 255 ) NULL AFTER  `account_id` ";
		$queries[] = "ALTER TABLE  `fm_users` ADD  `user_caps` TEXT NULL AFTER  `user_auth_type` ";

		/** Create table schema */
		if (count($queries) && $queries[0]) {
			foreach ($queries as $schema) {
				$fmdb->query($schema);
				if (!$fmdb->result || $fmdb->sql_errors) return false;
			}
		}
	
		$inserts = null;
		if (count($inserts) && $inserts[0] && $success) {
			foreach ($inserts as $query) {
				$fmdb->query($query);
				if ($fmdb->last_error) {
					echo $fmdb->last_error;
					return false;
				}
			}
		}
		
		/** Update fm_options */
		$version_check = getOption($fm_name . '_version_check');
		if ($version_check !== false) {
			if (!setOption('version_check', $version_check, 'auto', true, 0, $fm_name)) return false;
			$query = "DELETE FROM `$database`.`fm_options` WHERE option_name='{$fm_name}_version_check'";
			$fmdb->query($query);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
		$modules = getAvailableModules();
		if (count($modules)) {
			foreach ($modules as $module_name) {
				$module_version = getOption($module_name . '_version');
				if ($module_version !== false) {
					if (!setOption('version', $module_version, 'auto', false, 0, $module_name)) return false;
				}
				$module_version_check = getOption($module_name . '_version_check');
				if ($module_version_check !== false) {
					if (!setOption('version_check', $module_version_check, 'auto', true, 0, $module_name)) return false;
				}
				$module_client_version = getOption($module_name . '_client_version');
				if ($module_client_version !== false) {
					if (!setOption('client_version', $module_client_version, 'auto', false, 0, $module_name)) return false;
				}
				$query = "DELETE FROM `$database`.`fm_options` WHERE option_name LIKE '{$module_name}%_version%'";
				$fmdb->query($query);
				if (!$fmdb->result || $fmdb->sql_errors) return false;
			}
		}
		
		/** Update user capabilities */
		$fm_user_caps[$fm_name] = array(
				'do_everything'		=> '<b>Super Admin</b>',
				'manage_modules'	=> 'Module Management',
				'manage_users'		=> 'User Management',
				'run_tools'			=> 'Run Tools',
				'manage_settings'	=> 'Manage Settings'
			);
		if (!setOption('fm_user_caps', $fm_user_caps)) return false;
		
		$result = $fmdb->get_results("SELECT * FROM `fm_users`");
		if ($fmdb->num_rows) {
			$count = $fmdb->num_rows;
			for ($i=0; $i<$count; $i++) {
				$user_caps = null;
				/** Update user capabilities */
				$j = 1;
				foreach ($fm_user_caps[$fm_name] as $slug => $trash) {
					if ($j & $result[$i]->user_perms) $user_caps[$fm_name][$slug] = 1;
					$j = $j*2 ;
				}
				$fmdb->query("UPDATE fm_users SET user_caps = '" . serialize($user_caps) . "' WHERE user_id=" . $result[$i]->user_id);
				if (!$fmdb->result || $fmdb->sql_errors) return false;
			}
		}
		$fmdb->query("ALTER TABLE `fm_users` DROP `user_perms`;");
		if (!$fmdb->result || $fmdb->sql_errors) return false;
		
		/** Temporarily move the module user capabilities to fm_users */
		$result = $fmdb->get_results("SELECT * FROM `fm_perms`");
		if ($fmdb->num_rows) {
			$count = $fmdb->num_rows;
			for ($i=0; $i<$count; $i++) {
				if (!$user_info = getUserInfo($result[$i]->user_id)) continue;
				/** Update user capabilities */
				$user_caps = isSerialized($user_info['user_caps']) ? unserialize($user_info['user_caps']) : $user_info['user_caps'];
				$user_caps[$result[$i]->perm_module] = isSerialized($result[$i]->perm_extra) ? unserialize($result[$i]->perm_extra) : $result[$i]->perm_extra;
				$user_caps[$result[$i]->perm_module]['imported_perms'] = $result[$i]->perm_value;

				$fmdb->query("UPDATE fm_users SET user_caps = '" . serialize($user_caps) . "' WHERE user_id=" . $result[$i]->user_id);
				if (!$fmdb->result || $fmdb->sql_errors) return false;
			}
		}
		$fmdb->query("DROP TABLE `fm_perms`");
		if (!$fmdb->result || $fmdb->sql_errors) return false;
		
	}

	upgradeConfig('fm_db_version', 32, false);
	
	return $success;
}


/** fM v1.2-rc1 **/
function fmUpgrade_1202($database) {
	global $fm_name;
	
	$success = true;
	
	/** Prereq */
	$success = ($GLOBALS['running_db_version'] < 32) ? fmUpgrade_1201($database) : true;
	
	if ($success) {
		$fm_user_caps = getOption('fm_user_caps');
	
		/** Update user capabilities */
		$fm_user_caps[$fm_name] = array(
				'do_everything'		=> '<b>Super Admin</b>',
				'manage_modules'	=> 'Module Management',
				'manage_users'		=> 'User Management',
				'run_tools'			=> 'Run Tools',
				'manage_settings'	=> 'Manage Settings',
				'view_logs'			=> 'View Logs'
			);
		if (!setOption('fm_user_caps', $fm_user_caps)) return false;
	}

	upgradeConfig('fm_db_version', 34, false);
	
	return $success;
}


/** fM v2.0-beta1 **/
function fmUpgrade_2002($database) {
	$success = true;
	
	/** Prereq */
	$success = ($GLOBALS['running_db_version'] < 34) ? fmUpgrade_1202($database) : true;
	
	if ($success) {
		if (!setOption('client_auto_register', 1)) return false;
	}

	upgradeConfig('fm_db_version', 37, false);
	
	return $success;
}


/** fM v2.0 **/
function fmUpgrade_200($database) {
	global $fmdb;
	
	$success = true;
	
	/** Prereq */
	$success = ($GLOBALS['running_db_version'] < 37) ? fmUpgrade_2002($database) : true;
	
	if ($success) {
		if (!setOption('ssh_user', 'fm_user', 'auto', true, $_SESSION['user']['account_id'])) return false;
		if ($pw_strength = getOption('auth_fm_pw_strength')) {
			if (!setOption('auth_fm_pw_strength', ucfirst($pw_strength))) return false;
		}
		
		$queries[] = "DELETE FROM `$database`.`fm_options` WHERE option_name='fm_user_caps'";

		/** Create table schema */
		if (count($queries) && $queries[0]) {
			foreach ($queries as $schema) {
				$fmdb->query($schema);
				if (!$fmdb->result || $fmdb->sql_errors) return false;
			}
		}
	}

	upgradeConfig('fm_db_version', 42, false);
	
	return $success;
}


/** fM v2.1-beta1 **/
function fmUpgrade_2101($database) {
	global $fmdb;
	
	$success = true;
	
	/** Prereq */
	$success = ($GLOBALS['running_db_version'] < 42) ? fmUpgrade_200($database) : true;
	
	if ($success) {
		$queries[] = "CREATE TABLE IF NOT EXISTS `$database`.`fm_groups` (
  `group_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `group_name` varchar(128) NOT NULL,
  `group_caps` text,
  `group_comment` text,
  `group_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`group_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8";
		$queries[] = "ALTER TABLE `$database`.`fm_users` ADD `user_group` INT(11) DEFAULT NULL AFTER `user_email`";

		/** Create table schema */
		if (count($queries) && $queries[0]) {
			foreach ($queries as $schema) {
				$fmdb->query($schema);
				if (!$fmdb->result || $fmdb->sql_errors) return false;
			}
		}
	}

	upgradeConfig('fm_db_version', 43, false);
	
	return $success;
}


/** fM v3.0-rc1 **/
function fmUpgrade_3001($database) {
	global $fmdb;
	
	$success = true;
	
	/** Prereq */
	$success = ($GLOBALS['running_db_version'] < 43) ? fmUpgrade_2101($database) : true;
	
	if ($success) {
		$queries[] = "ALTER TABLE `$database`.`fm_users` ADD `user_comment` VARCHAR(255) NULL DEFAULT NULL AFTER `user_email`";

		/** Create table schema */
		if (count($queries) && $queries[0]) {
			foreach ($queries as $schema) {
				$fmdb->query($schema);
				if (!$fmdb->result || $fmdb->sql_errors) return false;
			}
		}
	}

	upgradeConfig('fm_db_version', 45, false);
	
	return $success;
}


/** fM v3.1 **/
function fmUpgrade_310($database) {
	global $fmdb, $fm_name;
	
	$success = true;
	
	/** Prereq */
	$success = ($GLOBALS['running_db_version'] < 45) ? fmUpgrade_3001($database) : true;
	
	if ($success) {
		$queries[] = "ALTER TABLE `$database`.`fm_logs` CHANGE `user_id` `user_login` VARCHAR(255) NOT NULL DEFAULT '0'";

		/** Create table schema */
		if (count($queries) && $queries[0]) {
			foreach ($queries as $schema) {
				$fmdb->query($schema);
				if (!$fmdb->result || $fmdb->sql_errors) return false;
			}
		}
		
		/** Update fm_logs with user_login from user_id */
		$result = $fmdb->get_results("SELECT * FROM `fm_users`");
		if ($fmdb->num_rows) {
			$count = $fmdb->num_rows;
			for ($i=0; $i<$count; $i++) {
				$fmdb->query("UPDATE fm_logs SET user_login = '" . $result[$i]->user_login . "' WHERE user_login='" . $result[$i]->user_id . "'");
				if ($fmdb->sql_errors) return false;
			}
		}
		
		$fmdb->query("UPDATE fm_logs SET user_login = '$fm_name' WHERE user_login='0'");
		if ($fmdb->sql_errors) return false;
		
	}

	upgradeConfig('fm_db_version', 46, false);
	
	return $success;
}


/** fM v3.1.1 **/
function fmUpgrade_311($database) {
	global $fmdb;
	
	$success = true;
	
	/** Prereq */
	$success = ($GLOBALS['running_db_version'] < 46) ? fmUpgrade_310($database) : true;
	
	if ($success) {
		/** Update fm_logs with user_login from user_id */
		$result = $fmdb->get_results("SELECT * FROM `fm_users`");
		if ($fmdb->num_rows) {
			$count = $fmdb->num_rows;
			for ($i=0; $i<$count; $i++) {
				$fmdb->query("UPDATE fm_logs SET user_login = '" . $result[$i]->user_login . "' WHERE user_login='" . $result[$i]->user_id . "'");
				if ($fmdb->sql_errors) return false;
			}
		}
	}

	upgradeConfig('fm_db_version', 47, false);
	
	return $success;
}


/** fM v4.0.0-beta1 **/
function fmUpgrade_4001($database) {
	global $fmdb;
	
	$success = true;
	
	/** Prereq */
	$success = ($GLOBALS['running_db_version'] < 47) ? fmUpgrade_311($database) : true;
	
	if ($success) {
		$queries[] = "CREATE TABLE IF NOT EXISTS `$database`.`fm_keys` (
			`key_id` int(11) NOT NULL,
			`account_id` int(11) NOT NULL DEFAULT '1',
			`user_id` int(11) NOT NULL,
			`key_token` varchar(255) NOT NULL,
			`key_secret` varchar(255) NOT NULL,
			`key_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
			PRIMARY KEY (`key_id`),
			UNIQUE KEY `idx_key_token` (`key_token`)
		  ) ENGINE=MyISAM DEFAULT CHARSET=utf8";

		/** Create table schema */
		if (count($queries) && $queries[0]) {
			foreach ($queries as $schema) {
				$fmdb->query($schema);
				if (!$fmdb->result || $fmdb->sql_errors) return false;
			}
		}
	}

	upgradeConfig('fm_db_version', 48, false);
	
	return $success;
}


/** fM v4.0.2 **/
function fmUpgrade_402($database) {
	global $fmdb;
	
	$success = true;
	
	/** Prereq */
	$success = ($GLOBALS['running_db_version'] < 48) ? fmUpgrade_4001($database) : true;
	
	if ($success) {
		$queries[] = "ALTER TABLE `$database`.`fm_keys` MODIFY `key_id` int(11) NOT NULL AUTO_INCREMENT";

		/** Create table schema */
		if (count($queries) && $queries[0]) {
			foreach ($queries as $schema) {
				$fmdb->query($schema);
				if (!$fmdb->result || $fmdb->sql_errors) return false;
			}
		}
	}

	upgradeConfig('fm_db_version', 49, false);
	
	return $success;
}


/** fM v4.5.0 **/
function fmUpgrade_450($database) {
	global $fmdb;
	
	$success = true;
	
	/** Prereq */
	$success = ($GLOBALS['running_db_version'] < 49) ? fmUpgrade_402($database) : true;
	
	if ($success) {
		$queries[] = "DELETE FROM `$database`.`fm_options` WHERE `option_name`='version_check'";

		/** Create table schema */
		if (count($queries) && $queries[0]) {
			foreach ($queries as $schema) {
				$fmdb->query($schema);
				if (!$fmdb->result || $fmdb->sql_errors) return false;
			}
		}
	}

	upgradeConfig('fm_db_version', 50, false);
	
	return $success;
}


/** fM v4.6.0 **/
function fmUpgrade_460($database) {
	global $fmdb;
	
	$success = true;
	
	/** Prereq */
	$success = ($GLOBALS['running_db_version'] < 50) ? fmUpgrade_450($database) : true;
	
	if ($success) {
		$queries[] = "UPDATE `fm_options` SET `option_value`='TLS' WHERE `option_name`='mail_smtp_tls' and `option_value`=1";

		/** Create table schema */
		if (count($queries) && $queries[0]) {
			foreach ($queries as $schema) {
				$fmdb->query($schema);
				if (!$fmdb->result || $fmdb->sql_errors) return false;
			}
		}
	}

	upgradeConfig('fm_db_version', 51, false);
	
	return $success;
}


/** fM v4.7.0 **/
function fmUpgrade_470($database) {
	global $fmdb;
	
	$success = true;
	
	/** Prereq */
	$success = ($GLOBALS['running_db_version'] < 51) ? fmUpgrade_460($database) : true;
	
	if ($success) {
		$result = $fmdb->get_results("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '{$database}' AND ENGINE = 'MyISAM'");
		if ($fmdb->num_rows) {
			foreach ($result as $table) {
				$queries[] = "ALTER TABLE {$table->TABLE_NAME} ENGINE=INNODB";
			}
		}
		
		/** Create table schema */
		if (count($queries) && $queries[0]) {
			foreach ($queries as $schema) {
				$fmdb->query($schema);
				if (!$fmdb->result || $fmdb->sql_errors) return false;
			}
		}
	}

	upgradeConfig('fm_db_version', 52, false);
	
	return $success;
}


/** fM v5.0.0-beta1 **/
function fmUpgrade_500b1($database) {
	global $fmdb;
	
	$success = true;
	
	/** Prereq */
	$success = ($GLOBALS['running_db_version'] < 52) ? fmUpgrade_470($database) : true;
	
	$queries = array();
	if ($success) {
		if (!columnExists("fm_users", 'user_theme')) {
			$queries[] = "ALTER TABLE `fm_users` ADD `user_theme` VARCHAR(255) NULL DEFAULT NULL AFTER `user_default_module`";
		}
		
		/** Create table schema */
		if (count($queries) && $queries[0]) {
			foreach ($queries as $schema) {
				$fmdb->query($schema);
				if (!$fmdb->result || $fmdb->sql_errors) return false;
			}
		}
	}

	upgradeConfig('fm_db_version', 55, false);
	
	return $success;
}


/** fM v5.1.0 **/
function fmUpgrade_510($database) {
	global $fmdb;
	
	$success = true;
	
	/** Prereq */
	$success = ($GLOBALS['running_db_version'] < 55) ? fmUpgrade_500b1($database) : true;
	
	$queries = array();
	if ($success) {
		$queries[] = "UPDATE `fm_options` SET `option_value` = REPLACE(option_value, '<username>', '{username}') WHERE `option_name`='ldap_dn'";
		if (!columnExists("fm_users", 'user_theme_mode')) {
			$queries[] = "ALTER TABLE `fm_users` ADD `user_theme_mode` enum('Light','Dark','System') NULL DEFAULT 'System' AFTER `user_theme`";
		}
		
		/** Create table schema */
		if (count($queries) && $queries[0]) {
			foreach ($queries as $schema) {
				$fmdb->query($schema);
				if (!$fmdb->result || $fmdb->sql_errors) return false;
			}
		}
	}

	@session_id($_COOKIE['myid']);
	@session_start();
	$_SESSION['user']['theme_mode'] = 'System';
	session_write_close();

	upgradeConfig('fm_db_version', 56, false);
	
	return $success;
}


/** fM v5.1.2 **/
function fmUpgrade_512($database) {
	global $fmdb;
	
	$success = true;
	
	/** Prereq */
	$success = ($GLOBALS['running_db_version'] < 56) ? fmUpgrade_510($database) : true;
	
	$queries = array();
	if ($success) {
		$queries[] = "ALTER TABLE `fm_logs` MODIFY COLUMN `log_data` MEDIUMTEXT";
		
		/** Create table schema */
		if (count($queries) && $queries[0]) {
			foreach ($queries as $schema) {
				$fmdb->query($schema);
				if (!$fmdb->result || $fmdb->sql_errors) return false;
			}
		}
	}

	upgradeConfig('fm_db_version', 57, false);
	
	return $success;
}


/** fM v5.2.0 **/
function fmUpgrade_520($database) {
	global $fmdb;
	
	$success = true;
	
	/** Prereq */
	$success = ($GLOBALS['running_db_version'] < 57) ? fmUpgrade_512($database) : true;
	
	$queries = array();
	if ($success) {
		$queries[] = "ALTER TABLE `fm_users` MODIFY `user_theme_mode` enum('Light','Dark','System') NULL DEFAULT 'System'";
		
		/** Create table schema */
		if (count($queries) && $queries[0]) {
			foreach ($queries as $schema) {
				$fmdb->query($schema);
				if (!$fmdb->result || $fmdb->sql_errors) return false;
			}
		}
	}

	upgradeConfig('fm_db_version', 58, false);
	
	return $success;
}


/** fM v5.3.1 **/
function fmUpgrade_531($database) {
	global $fmdb;
	
	$success = true;
	
	/** Prereq */
	$success = ($GLOBALS['running_db_version'] < 58) ? fmUpgrade_520($database) : true;
	
	$queries = array();
	if ($success) {
		if (getOption('api_token_support') == 1) {
			setOption('enforce_ssl', 1, 'auto', false);
		}
		
		/** Create table schema */
		if (count($queries) && $queries[0]) {
			foreach ($queries as $schema) {
				$fmdb->query($schema);
				if (!$fmdb->result || $fmdb->sql_errors) return false;
			}
		}
	}

	upgradeConfig('fm_db_version', 59, false);
	
	return $success;
}


/**
 * Updates the database with the db version number.
 *
 * @since 1.0
 * @package facileManager
 * @subpackage Upgrader
 */
function upgradeConfig($field, $value, $logit = true) {
	global $fmdb;
	
	$query = "UPDATE `fm_options` SET option_value='$value' WHERE option_name='$field'";
	$fmdb->query($query);
	if ($fmdb->last_error) {
		echo $fmdb->last_error;
		return false;
	}

	@session_id($_COOKIE['myid']);
	@session_start();
	unset($_SESSION['user']['fm_perms']);
	session_write_close();
	
	if ($logit) {
		include(ABSPATH . 'fm-includes/version.php');
		include(ABSPATH . 'fm-modules/facileManager/variables.inc.php');
		
		addLogEntry(sprintf(_('%s was upgraded to %s.'), $fm_name, $fm_version), $fm_name);
	}
	
	return true;
}


/**
 * Displays a message during setup.
 *
 * @since 1.1.1
 * @package facileManager
 * @subpackage Upgrader
 */
function displaySetupMessage($message = 1, $url = null) {
	global $fm_name;
	
	switch ($message) {
		case 1:
			printf('
	<p>' . _('Database upgrade for %1$s is complete! Click \'Next\' to start using %1$s.') . '</p>
	<p class="step"><a href="%2$s" class="button">' . _('Next') . '</a></p>', $fm_name, $url);
			break;
		case 2:
			echo '
	<p>' . _('Database upgrade failed. Please try again.') . '</p>
	<p class="step"><a href="?step=2" class="button">' . _('Try Again') . '</a></p>';
			break;
	}
}


/**
 * Checks if a table column exists.
 *
 * @since 4.0.1
 * @package facileManager
 * @subpackage Upgrader
 * 
 * @param string $table The table containing the column
 * @param string $column The column to check
 */
function columnExists($table, $column) {
	global $fmdb, $__FM_CONFIG;

	$fmdb->query("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='{$__FM_CONFIG['db']['name']}' AND TABLE_NAME='$table' AND COLUMN_NAME='$column'");

	return $fmdb->num_rows;
}
