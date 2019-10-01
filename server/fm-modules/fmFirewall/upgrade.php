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
 | fmFirewall: Easily manage one or more software firewalls                |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmfirewall/                        |
 +-------------------------------------------------------------------------+
*/

function upgradefmFirewallSchema($running_version) {
	global $fmdb;
	
	/** Include module variables */
	@include(dirname(__FILE__) . '/variables.inc.php');
	
	/** Get current version */
	if (!$running_version) {
		$running_version = getOption('version', 0, 'fmFirewall');
	}
	
	/** Checks to support older versions (ie n-3 upgrade scenarios */
	$success = version_compare($running_version, '2.0', '<') ? upgradefmFirewall_200($__FM_CONFIG, $running_version) : true;
	if (!$success) return $fmdb->last_error;
	
	setOption('client_version', $__FM_CONFIG['fmFirewall']['client_version'], 'auto', false, 0, 'fmFirewall');
	
	return true;
}

/** 1.0-b3 */
function upgradefmFirewall_01003($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmFirewall']['prefix']}servers` ADD  `server_client_version` VARCHAR( 150 ) NULL AFTER  `server_installed` ";
	
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

	setOption('version', '1.0-beta3', 'auto', false, 0, 'fmFirewall');
	
	return true;
}

/** 1.0-beta5 */
function upgradefmFirewall_01005($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '1.0-b3', '<') ? upgradefmFirewall_01003($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	/** Move module options */
	$result = $fmdb->get_results("SELECT * FROM `fm_{$__FM_CONFIG['fmFirewall']['prefix']}options`");
	if ($fmdb->num_rows) {
		$count = $fmdb->num_rows;
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
	
	$result = $fmdb->get_results("SELECT * FROM `fm_users`");
	if ($fmdb->num_rows) {
		$count = $fmdb->num_rows;
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
	
	setOption('version', '1.0-beta5', 'auto', false, 0, 'fmFirewall');
	
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
	
	$result = $fmdb->get_results("SELECT * FROM `fm_users`");
	if ($fmdb->num_rows) {
		$count = $fmdb->num_rows;
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
	
	setOption('version', '1.0-rc1', 'auto', false, 0, 'fmFirewall');
	
	return true;
}

/** 1.1.1 */
function upgradefmFirewall_111($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '1.0-rc1', '<') ? upgradefmFirewall_01006($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}servers` DROP INDEX server_serial_no, ADD UNIQUE `idx_server_serial_no` (`server_serial_no`)";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` ADD INDEX `idx_policy_account_id` (`account_id`)";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` ADD INDEX `idx_policy_status` (`policy_status`)";
	
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

	setOption('version', '1.1.1', 'auto', false, 0, 'fmFirewall');
	
	return true;
}

/** 1.4-beta2 */
function upgradefmFirewall_1401($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '1.1.1', '<') ? upgradefmFirewall_111($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}time` ADD `time_zone` ENUM('utc','kerneltz','localtz') NOT NULL DEFAULT 'utc' AFTER `time_weekdays`";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}time` ADD `time_weekdays_not` ENUM('','!') NOT NULL DEFAULT '' AFTER `time_end_time`";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}time` ADD `time_contiguous` ENUM('yes','no') NOT NULL DEFAULT 'no' AFTER `time_weekdays`";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}time` ADD `time_monthdays_not` ENUM('','!') NOT NULL DEFAULT '' AFTER `time_weekdays`";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}time` ADD `time_monthdays` TEXT NULL DEFAULT NULL AFTER `time_monthdays_not`";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` CHANGE `policy_type` `policy_type` ENUM('rules','filter','nat') NOT NULL DEFAULT 'filter'";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` ADD `policy_packet_state` TEXT NULL DEFAULT NULL AFTER `policy_options`";
	
	$inserts = $updates = null;
	
	$updates[] = "UPDATE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` SET `policy_type`='filter'";
	$updates[] = "UPDATE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` SET `policy_packet_state`='NEW' WHERE `policy_action`='pass'";
	$updates[] = "UPDATE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` SET `policy_packet_state`='NEW,ESTABLISHED,RELATED' WHERE `policy_action`='pass' AND `policy_options` IN (2,3,7)";
	$updates[] = "UPDATE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` SET `policy_packet_state`='ESTABLISHED,RELATED' WHERE `policy_action`!='pass' AND `policy_options` IN (2,3,7)";
	$updates[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` CHANGE `policy_type` `policy_type` ENUM('filter','nat') NOT NULL DEFAULT 'filter'";

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

	setOption('version', '1.4-beta2', 'auto', false, 0, 'fmFirewall');
	
	return true;
}

/** 2.0 */
function upgradefmFirewall_200($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '1.4-beta2', '<') ? upgradefmFirewall_1401($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` ADD `policy_name` VARCHAR(255) NULL DEFAULT NULL AFTER `policy_order_id`";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` ADD `policy_template_id` INT(11) NOT NULL DEFAULT '0' AFTER `policy_type`";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` CHANGE `policy_type` `policy_type` ENUM('filter','nat','template') NOT NULL DEFAULT 'filter'";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` ADD `policy_targets` VARCHAR(255) NOT NULL DEFAULT '0' AFTER `policy_order_id`";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` ADD `policy_template_stack` VARCHAR(255) NULL DEFAULT NULL AFTER `policy_type`";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` CHANGE `policy_order_id` `policy_order_id` INT(11) NULL DEFAULT NULL";
	
	/** Change policy negates */
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` CHANGE `policy_source_not` `policy_source_not` ENUM('0','1','','!') NOT NULL DEFAULT '0'";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` CHANGE `policy_destination_not` `policy_destination_not` ENUM('0','1','','!') NOT NULL DEFAULT '0'";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` CHANGE `policy_services_not` `policy_services_not` ENUM('0','1','','!') NOT NULL DEFAULT '0'";
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` SET `policy_source_not`='!' WHERE `policy_source_not`='1'";
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` SET `policy_source_not`='' WHERE `policy_source_not`='0'";
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` SET `policy_destination_not`='!' WHERE `policy_destination_not`='1'";
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` SET `policy_destination_not`='' WHERE `policy_destination_not`='0'";
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` SET `policy_services_not`='!' WHERE `policy_services_not`='1'";
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` SET `policy_services_not`='' WHERE `policy_services_not`='0'";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` CHANGE `policy_source_not` `policy_source_not` ENUM('','!') NOT NULL DEFAULT ''";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` CHANGE `policy_destination_not` `policy_destination_not` ENUM('','!') NOT NULL DEFAULT ''";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` CHANGE `policy_services_not` `policy_services_not` ENUM('','!') NOT NULL DEFAULT ''";
	
	/** Enable quick option on all pf rules */
	$result = $fmdb->get_results("SELECT `server_serial_no` FROM `fm_{$__FM_CONFIG['fmFirewall']['prefix']}servers` WHERE `server_type`='pf'");
	if ($fmdb->num_rows) {
		foreach ($fmdb->last_result as $server) {
			$serial_nos[] = $server->server_serial_no;
		}
		$table[] = "UPDATE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` SET `policy_options`=`policy_options` + 8 WHERE `server_serial_no` IN (" . join(',', $serial_nos) . ")";
		$table[] = "UPDATE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` SET `policy_packet_state`='keep state' WHERE `server_serial_no` IN (" . join(',', $serial_nos) . ") AND `policy_action='pass'";
	}
	
	/** Set packet state on all "pass" rules */
	$serial_nos = null;
	$result = $fmdb->get_results("SELECT `server_serial_no` FROM `fm_{$__FM_CONFIG['fmFirewall']['prefix']}servers` WHERE `server_type`='ipfilter'");
	if ($fmdb->num_rows) {
		foreach ($fmdb->last_result as $server) {
			$serial_nos[] = $server->server_serial_no;
		}
		$table[] = "UPDATE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` SET `policy_packet_state`='keep state' WHERE `server_serial_no` IN (" . join(',', $serial_nos) . ") `policy_action='pass'";
	}
	$serial_nos = null;
	$result = $fmdb->get_results("SELECT `server_serial_no` FROM `fm_{$__FM_CONFIG['fmFirewall']['prefix']}servers` WHERE `server_type`='ipfw'");
	if ($fmdb->num_rows) {
		foreach ($fmdb->last_result as $server) {
			$serial_nos[] = $server->server_serial_no;
		}
		$table[] = "UPDATE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}policies` SET `policy_packet_state`='keep-state' WHERE `server_serial_no` IN (" . join(',', $serial_nos) . ") `policy_action='pass'";
	}
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '2.0', 'auto', false, 0, 'fmFirewall');
	
	return true;
}

?>