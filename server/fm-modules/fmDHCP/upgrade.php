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
 | fmDHCP: Easily manage one or more ISC DHCP servers                      |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdhcp/                            |
 +-------------------------------------------------------------------------+
*/

function upgradefmDHCPSchema($module_name) {
	global $fmdb;
	
	/** Include module variables */
	@include(dirname(__FILE__) . '/variables.inc.php');
	
	/** Get current version */
	$running_version = getOption('version', 0, 'fmDHCP');
	
	/** Checks to support older versions (ie n-3 upgrade scenarios */
	$success = version_compare($running_version, '0.3.2', '<') ? upgradefmDHCP_032($__FM_CONFIG, $running_version) : true;
	if (!$success) return $fmdb->last_error;
	
	setOption('client_version', $__FM_CONFIG['fmDHCP']['client_version'], 'auto', false, 0, 'fmDHCP');
	
	return true;
}

/** 0.2 */
function upgradefmDHCP_02($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDHCP']['prefix']}functions` ADD `def_prefix` VARCHAR(20) NULL DEFAULT NULL AFTER `def_option_type`";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDHCP']['prefix']}functions` CHANGE `def_option_type` `def_option_type` ENUM('global','shared','subnet','group','host','pool','peer') NOT NULL DEFAULT 'global'";
	
	/** Insert upgrade steps here **/
	$inserts[] = "INSERT IGNORE INTO  `fm_{$__FM_CONFIG['fmDHCP']['prefix']}functions` (
		`def_function`, `def_option_type`, `def_prefix`, `def_option`, `def_type`, `def_multiple_values`, `def_dropdown`, `def_max_parameters`, `def_direction`, `def_minimum_version`
)
VALUES
('options', 'global', 'option', 'host-name', '( quoted_string )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'routers', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'domain-name-servers', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'subnet-mask', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'broadcast-address', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'domain-name', '( quoted_string )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'domain-search', '( quoted_string )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'time-servers', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'log-servers', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'swap-server', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'root-path', '( quoted_string )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'nis-domain', '( quoted_string )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'nis-servers', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'font-servers', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'x-display-manager', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'ntp-servers', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'netbios-name-servers', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'netbios-scope', '( quoted_string )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'netbios-node-type', '( integer )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'time-offset', '( integer )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'dhcp-server-identifier', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'slp-directory-agent', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'slp-service-scope', '( quoted_string )', 'no', 'no', '1', 'forward', NULL),
('options', 'shared', NULL, 'authoritative', '( on | off )', 'no', 'yes', 1, 'empty', NULL)
";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDHCP']['prefix']}functions` SET `def_option_type`='subnet' WHERE `def_option`='authoritative' AND `def_option_type`='global' LIMIT 1";
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}
	
	if (count($inserts) && $inserts[0]) {
		foreach ($inserts as $query) {
			$fmdb->query($query);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}
	
	/** Add empty options so updates work */
	$new_options = array(
		'host-name', 'routers', 'domain-name-servers', 'subnet-mask', 'broadcast-address',
		'domain-name', 'domain-search', 'time-servers', 'log-servers', 'swap-server',
		'root-path', 'nis-domain', 'nis-servers', 'font-servers', 'x-display-manager',
		'ntp-servers', 'netbios-name-servers', 'netbios-scope', 'netbios-node-type',
		'time-offset', 'dhcp-server-identifier', 'slp-directory-agent', 'slp-service-scope'
	);
	$fmdb->query("SELECT * FROM `fm_{$__FM_CONFIG['fmDHCP']['prefix']}config` WHERE `config_is_parent`='yes' AND `config_status`!='deleted'");
	$num_rows = $fmdb->num_rows;
	$result = $fmdb->last_result;
	$sql_start = "INSERT IGNORE INTO `fm_{$__FM_CONFIG['fmDHCP']['prefix']}config` 
		(`config_type`,`config_parent_id`,`config_name`,`config_data`,`config_assigned_to`) VALUES ";

	for ($i=0; $i<$num_rows; $i++) {
		foreach ($new_options as $option) {
			$values[] = "('{$result[$i]->config_type}','{$result[$i]->config_id}','$option','','{$result[$i]->config_assigned_to}')";
		}
		$fmdb->query($sql_start . join(',', $values));
		unset($values);
	}

	/** Handle updating table with module version **/
	setOption('version', '0.2', 'auto', false, 0, 'fmDHCP');
	
	return true;
}

/** 0.3.1 */
function upgradefmDHCP_031($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	/** Insert upgrade steps here **/
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDHCP']['prefix']}config` SET `config_name`='load balance max seconds' WHERE `config_name`='load balance max secs'";
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}
	
	if (count($inserts) && $inserts[0]) {
		foreach ($inserts as $query) {
			$fmdb->query($query);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}
	
	/** Handle updating table with module version **/
	setOption('version', '0.3.1', 'auto', false, 0, 'fmDHCP');
	
	return true;
}

/** 0.3.2 */
function upgradefmDHCP_032($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	/** Insert upgrade steps here **/
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDHCP']['prefix']}config` SET `config_name`='load balance max seconds' WHERE `config_name`='load_balance_max_seconds'";
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}
	
	if (count($inserts) && $inserts[0]) {
		foreach ($inserts as $query) {
			$fmdb->query($query);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}
	
	/** Handle updating table with module version **/
	setOption('version', '0.3.2', 'auto', false, 0, 'fmDHCP');
	
	return true;
}

?>