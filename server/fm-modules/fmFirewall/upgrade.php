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
 | fmFirewall: Easily manage one or more software firewalls                |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmfirewall/                        |
 +-------------------------------------------------------------------------+
*/

function upgradefmFirewallSchema($module) {
	global $fmdb;
	
	/** Include module variables */
	@include(dirname(__FILE__) . '/variables.inc.php');
	
	/** Get current version */
	$running_version = getOption('version', 0, $module);
	
	/** Checks to support older versions (ie n-3 upgrade scenarios */
	$success = version_compare($running_version, '1.0-rc1', '<') ? upgradefmFirewall_01006($__FM_CONFIG, $running_version) : true;
	if (!$success) return $fmdb->last_error;
	
	return true;
}

/** 1.0-b3 */
function upgradefmFirewall_01003($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmFirewall']['prefix']}servers` ADD  `server_client_version` VARCHAR( 150 ) NULL AFTER  `server_installed` ;";
	
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

	if (!setOption('fmFirewall_client_version', $__FM_CONFIG['fmFirewall']['client_version'], 'auto', false)) return false;
	
	return true;
}

/** 1.0-beta5 */
function upgradefmFirewall_01005($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '1.0-b3', '<') ? upgradefmFirewall_01003($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	/** Move module options */
	$fmdb->get_results("SELECT * FROM `fm_{$__FM_CONFIG['fmFirewall']['prefix']}options`");
	if ($fmdb->num_rows) {
		$count = $fmdb->num_rows;
		$result = $fmdb->last_result;
		for ($i=0; $i<$count; $i++) {
			if (!setOption($result[$i]->option_name, $result[$i]->option_value, 'auto', true, $result[$i]->account_id, 'fmFirewall')) return false;
		}
	}
	$fmdb->query("DROP TABLE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}options`");
	if (!$fmdb->result || $fmdb->sql_errors) return false;

	$fm_user_caps = getOption('fm_user_caps');
	
	/** Update user capabilities */
	$fm_user_caps['fmFirewall'] = array(
			'read_only'				=> '<b>Read Only</b>',
			'manage_servers'		=> 'Server Management',
			'build_server_configs'	=> 'Build Server Configs',
			'manage_objects'		=> 'Object Management',
			'manage_services'		=> 'Service Management',
			'manage_time'			=> 'Time Management'
		);
	if (!setOption('fm_user_caps', $fm_user_caps)) return false;
	
	$fmdb->get_results("SELECT * FROM `fm_users`");
	if ($fmdb->num_rows) {
		$count = $fmdb->num_rows;
		$result = $fmdb->last_result;
		for ($i=0; $i<$count; $i++) {
			$user_caps = null;
			/** Update user capabilities */
			$j = 1;
			$temp_caps = null;
			foreach ($fm_user_caps['fmFirewall'] as $slug => $trash) {
				$user_caps = isSerialized($result[$i]->user_caps) ? unserialize($result[$i]->user_caps) : $result[$i]->user_caps;
				if (@array_key_exists('fmFirewall', $user_caps)) {
					if ($user_caps['fmFirewall']['imported_perms'] == 0) {
						$temp_caps['fmFirewall']['read_only'] = 1;
					} else {
						if ($j & $user_caps['fmFirewall']['imported_perms'] && $j > 1) $temp_caps['fmFirewall'][$slug] = 1;
						$j = $j*2 ;
					}
				} else {
					$temp_caps['fmFirewall']['read_only'] = $user_caps['fmFirewall']['read_only'] = 1;
				}
			}
			if (@array_key_exists('fmFirewall', $temp_caps)) $user_caps['fmFirewall'] = array_merge($temp_caps['fmFirewall'], $user_caps['fmFirewall']);
			unset($user_caps['fmFirewall']['imported_perms']);
			$fmdb->query("UPDATE fm_users SET user_caps = '" . serialize($user_caps) . "' WHERE user_id=" . $result[$i]->user_id);
			if (!$fmdb->result) return false;
		}
	}
	
	setOption('client_version', $__FM_CONFIG['fmFirewall']['client_version'], 'auto', false, 0, 'fmFirewall');
	
	return true;
}


/** 1.0-rc1 */
function upgradefmFirewall_01006($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '1.0-beta5', '<') ? upgradefmFirewall_01005($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$fm_user_caps = getOption('fm_user_caps');
	
	/** Update user capabilities */
	$fm_user_caps['fmFirewall'] = array(
			'view_all'				=> 'View All',
			'manage_servers'		=> 'Server Management',
			'build_server_configs'	=> 'Build Server Configs',
			'manage_policies'		=> 'Policy Management',
			'manage_objects'		=> 'Object Management',
			'manage_services'		=> 'Service Management',
			'manage_time'			=> 'Time Management'
		);
	if (!setOption('fm_user_caps', $fm_user_caps)) return false;
	
	$fmdb->get_results("SELECT * FROM `fm_users`");
	if ($fmdb->num_rows) {
		$count = $fmdb->num_rows;
		$result = $fmdb->last_result;
		for ($i=0; $i<$count; $i++) {
			$user_caps = null;
			/** Update user capabilities */
			$temp_caps = null;
			foreach ($fm_user_caps['fmFirewall'] as $slug => $trash) {
				$user_caps = isSerialized($result[$i]->user_caps) ? unserialize($result[$i]->user_caps) : $result[$i]->user_caps;
				if (@array_key_exists('fmFirewall', $user_caps)) {
					if (array_key_exists('read_only', $user_caps['fmFirewall'])) {
						$temp_caps['fmFirewall']['view_all'] = 1;
						unset($user_caps['fmFirewall']['read_only']);
					}
				}
			}
			if (@array_key_exists('fmFirewall', $temp_caps)) $user_caps['fmFirewall'] = array_merge($temp_caps['fmFirewall'], $user_caps['fmFirewall']);
			$fmdb->query("UPDATE fm_users SET user_caps = '" . serialize($user_caps) . "' WHERE user_id=" . $result[$i]->user_id);
			if (!$fmdb->result) return false;
		}
	}
	
	setOption('client_version', $__FM_CONFIG['fmFirewall']['client_version'], 'auto', false, 0, 'fmFirewall');
	
	return true;
}


?>