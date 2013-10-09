<?php

function upgradefmSQLPassSchema($module) {
	global $fmdb;
	
	/** Include module variables */
	@include(dirname(__FILE__) . '/variables.inc.php');
	
	/** Get current version */
	$running_version = getOption($module . '_version', 0);

	/** Checks to support older versions (ie n-3 upgrade scenarios */
	$success = version_compare($running_version, '1.0-b2', '<') ? upgradefmSQLPass_100($__FM_CONFIG, $running_version) : true;
	if (!$success) return 'Failed';
	
	return 'Success';
}

function upgradefmSQLPass_100($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmSQLPass']['prefix']}servers` ADD  `server_port` INT( 5 ) NULL DEFAULT NULL AFTER  `server_type` ;";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmSQLPass']['prefix']}servers` CHANGE  `server_groups`  `server_groups` TEXT NULL DEFAULT NULL ;";
	
	$inserts[] = null;
	
	$updates[] = null;
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$result = $fmdb->query($schema);
			if (!$fmdb->result) return false;
		}
	}

	if (count($inserts) && $inserts[0]) {
		foreach ($inserts as $query) {
			$result = $fmdb->query($query);
			if (!$fmdb->result) return false;
		}
	}

	if (count($updates) && $updates[0]) {
		foreach ($updates as $query) {
			$result = $fmdb->query($query);
			if (!$fmdb->result) return false;
		}
	}

	return true;
}


?>