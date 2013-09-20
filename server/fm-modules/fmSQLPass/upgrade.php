<?php

function upgradefmSQLPassSchema($module) {
	global $fmdb;
	
	/** Checks to support older versions (ie n-3 upgrade scenarios */
	$retval = upgradefmSQLPass_100();
	
	if ($retval) {
		return 'Success';
	} else {
		return 'Failed';
	}
}

function upgradefmSQLPass_100() {
	global $fmdb;
	
	$table[] = null;
	
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