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
 | fmSQLPass: Change database user passwords across multiple servers.      |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmsqlpass/                         |
 +-------------------------------------------------------------------------+
*/

function upgradefmSQLPassSchema($module) {
	global $fmdb;
	
	/** Include module variables */
	@include(dirname(__FILE__) . '/variables.inc.php');
	
	/** Get current version */
	$running_version = getOption('version', 0, $module);

	/** Checks to support older versions (ie n-3 upgrade scenarios */
	$success = version_compare($running_version, '1.0-b8', '<') ? upgradefmSQLPass_0108($__FM_CONFIG, $running_version) : true;
	if (!$success) return $fmdb->last_error;
	
	return true;
}

/** 1.0-b2 */
function upgradefmSQLPass_0102($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmSQLPass']['prefix']}servers` ADD  `server_port` INT( 5 ) NULL DEFAULT NULL AFTER  `server_type` ;";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmSQLPass']['prefix']}servers` CHANGE  `server_groups`  `server_groups` TEXT NULL DEFAULT NULL ;";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmSQLPass']['prefix']}servers` CHANGE  `server_credentials`  `server_credentials` TEXT NULL DEFAULT NULL ;";
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	return true;
}

/** 1.0-b4 */
function upgradefmSQLPass_0104($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '1.0-b2', '<') ? upgradefmSQLPass_0102($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmSQLPass']['prefix']}servers` CHANGE  `server_type`  `server_type` ENUM(  'MySQL',  'PostgreSQL',  'MSSQL' ) NOT NULL ;";

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

	return true;
}

/** 1.0-b8 */
function upgradefmSQLPass_0108($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '1.0-b2', '<') ? upgradefmSQLPass_0104($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	/** Move module options */
	$fmdb->get_results("SELECT * FROM `fm_{$__FM_CONFIG['fmSQLPass']['prefix']}options`");
	if ($fmdb->num_rows) {
		$count = $fmdb->num_rows;
		$result = $fmdb->last_result;
		for ($i=0; $i<$count; $i++) {
			if (!setOption($result[$i]->option_name, $result[$i]->option_value, 'auto', true, $result[$i]->account_id, 'fmSQLPass')) return false;
		}
	}
	$fmdb->query("DROP TABLE `fm_{$__FM_CONFIG['fmSQLPass']['prefix']}options`");
	if (!$fmdb->result || $fmdb->sql_errors) return false;

	return true;
}

?>