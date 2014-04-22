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
	$success = version_compare($running_version, '1.0-b5', '<') ? upgradefmFirewall_0105($__FM_CONFIG, $running_version) : true;
	if (!$success) return $fmdb->last_error;
	
	return true;
}

/** 1.0-b3 */
function upgradefmFirewall_0103($__FM_CONFIG, $running_version) {
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

/** 1.0-b5 */
function upgradefmFirewall_0105($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '1.0-b3', '<') ? upgradefmFirewall_0103($__FM_CONFIG, $running_version) : true;
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

	if (!setOption('fmFirewall_client_version', $__FM_CONFIG['fmFirewall']['client_version'], 'auto', false)) return false;
	
	return true;
}


?>