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
 | fmSQLPass: Change database user passwords across multiple servers.      |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmsqlpass/                         |
 +-------------------------------------------------------------------------+
*/

function upgradefmSQLPassSchema($running_version) {
	global $fmdb;
	
	/** Include module variables */
	@include(dirname(__FILE__) . '/variables.inc.php');
	
	/** Get current version */
	if (!$running_version) {
		$running_version = getOption('version', 0, 'fmSQLPass');
	}

	/** Checks to support older versions (ie n-3 upgrade scenarios */
	$success = version_compare($running_version, '1.3-alpha3', '<') ? upgradefmSQLPass_01303($__FM_CONFIG, $running_version) : true;
	if (!$success) return $fmdb->last_error;
	
	return true;
}

/** 1.0-b2 */
function upgradefmSQLPass_01002($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmSQLPass']['prefix']}servers` ADD  `server_port` INT( 5 ) NULL DEFAULT NULL AFTER  `server_type` ";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmSQLPass']['prefix']}servers` CHANGE  `server_groups`  `server_groups` TEXT NULL DEFAULT NULL ";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmSQLPass']['prefix']}servers` CHANGE  `server_credentials`  `server_credentials` TEXT NULL DEFAULT NULL ";
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '1.0-beta2', 'auto', false, 0, 'fmSQLPass');
	
	return true;
}

/** 1.0-b4 */
function upgradefmSQLPass_01004($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '1.0-b2', '<') ? upgradefmSQLPass_01002($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmSQLPass']['prefix']}servers` CHANGE  `server_type`  `server_type` ENUM(  'MySQL',  'PostgreSQL',  'MSSQL' ) NOT NULL ";

	$inserts = $updates = null;


	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	if (count($inserts) && $inserts[0]) {
		foreach ($inserts as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	if (count($updates) && $updates[0]) {
		foreach ($updates as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '1.0-beta4', 'auto', false, 0, 'fmSQLPass');
	
	return true;
}

/** 1.0-beta8 */
function upgradefmSQLPass_01008($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '1.0-b2', '<') ? upgradefmSQLPass_01004($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	/** Move module options */
	$result = $fmdb->get_results("SELECT * FROM `fm_{$__FM_CONFIG['fmSQLPass']['prefix']}options`");
	if ($fmdb->num_rows) {
		$count = $fmdb->num_rows;
		for ($i=0; $i<$count; $i++) {
			if (!setOption($result[$i]->option_name, $result[$i]->option_value, 'auto', true, $result[$i]->account_id, 'fmSQLPass')) return false;
		}
	}
	$fmdb->query("DROP TABLE `fm_{$__FM_CONFIG['fmSQLPass']['prefix']}options`");
	if (!$fmdb->result || $fmdb->sql_errors) return false;

	$fm_user_caps = getOption('fm_user_caps');
	
	/** Update user capabilities */
	$fm_user_caps['fmSQLPass'] = array(
			'read_only'				=> '<b>Read Only</b>',
			'manage_servers'		=> 'Server Management',
			'manage_passwords'		=> 'Password Management',
			'manage_settings'		=> 'Manage Settings'
		);
	if (!setOption('fm_user_caps', $fm_user_caps)) return false;
	
	$result = $fmdb->get_results("SELECT * FROM `fm_users`");
	if ($fmdb->num_rows) {
		$count = $fmdb->num_rows;
		for ($i=0; $i<$count; $i++) {
			$user_caps = null;
			/** Update user capabilities */
			$j = 1;
			$temp_caps = null;
			foreach ($fm_user_caps['fmSQLPass'] as $slug => $trash) {
				$user_caps = isSerialized($result[$i]->user_caps) ? unserialize($result[$i]->user_caps) : $result[$i]->user_caps;
				if (@array_key_exists('fmSQLPass', $user_caps)) {
					if ($user_caps['fmSQLPass']['imported_perms'] == 0) {
						$temp_caps['fmSQLPass']['read_only'] = 1;
					} else {
						if ($j & $user_caps['fmSQLPass']['imported_perms'] && $j > 1) $temp_caps['fmSQLPass'][$slug] = 1;
						$j = $j*2 ;
					}
				} else {
					$temp_caps['fmSQLPass']['read_only'] = $user_caps['fmSQLPass']['read_only'] = 1;
				}
			}
			if (@array_key_exists('fmSQLPass', $temp_caps)) $user_caps['fmSQLPass'] = array_merge($temp_caps['fmSQLPass'], $user_caps['fmSQLPass']);
			unset($user_caps['fmSQLPass']['imported_perms']);
			$fmdb->query("UPDATE fm_users SET user_caps = '" . serialize($user_caps) . "' WHERE user_id=" . $result[$i]->user_id);
			if (!$fmdb->result) return false;
		}
	}
	
	setOption('version', '1.0-beta8', 'auto', false, 0, 'fmSQLPass');
	
	return true;
}

/** 1.0-rc1 */
function upgradefmSQLPass_01009($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '1.0-beta8', '<') ? upgradefmSQLPass_01008($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$fm_user_caps = getOption('fm_user_caps');
	
	/** Update user capabilities */
	$fm_user_caps['fmSQLPass'] = array(
			'view_all'				=> 'View All',
			'manage_servers'		=> 'Server Management',
			'manage_passwords'		=> 'Password Management',
			'manage_settings'		=> 'Manage Settings'
		);
	if (!setOption('fm_user_caps', $fm_user_caps)) return false;
	
	$result = $fmdb->get_results("SELECT * FROM `fm_users`");
	if ($fmdb->num_rows) {
		$count = $fmdb->num_rows;
		for ($i=0; $i<$count; $i++) {
			$user_caps = null;
			/** Update user capabilities */
			$temp_caps = null;
			foreach ($fm_user_caps['fmSQLPass'] as $slug => $trash) {
				$user_caps = isSerialized($result[$i]->user_caps) ? unserialize($result[$i]->user_caps) : $result[$i]->user_caps;
				if (@array_key_exists('fmSQLPass', $user_caps)) {
					if (array_key_exists('read_only', $user_caps['fmSQLPass'])) {
						$temp_caps['fmSQLPass']['view_all'] = 1;
						unset($user_caps['fmSQLPass']['read_only']);
					}
				}
			}
			if (@array_key_exists('fmSQLPass', $temp_caps)) $user_caps['fmSQLPass'] = array_merge($temp_caps['fmSQLPass'], $user_caps['fmSQLPass']);
			$fmdb->query("UPDATE fm_users SET user_caps = '" . serialize($user_caps) . "' WHERE user_id=" . $result[$i]->user_id);
			if (!$fmdb->result) return false;
		}
	}
	
	setOption('version', '1.0-rc1', 'auto', false, 0, 'fmSQLPass');
	
	return true;
}

/** 1.3-alpha3 */
function upgradefmSQLPass_01303($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '1.0-rc1', '<') ? upgradefmSQLPass_01009($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmSQLPass']['prefix']}servers` CHANGE `server_type` `server_type` ENUM('MySQL','PostgreSQL') NOT NULL";
	
	/** Run queries */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}
	
	setOption('version', '1.3-alpha3', 'auto', false, 0, 'fmSQLPass');
	
	return true;
}

?>