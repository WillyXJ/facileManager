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
	
	/** Checks to support older versions (ie n-3 upgrade scenarios */
	$retval = upgradefmFirewall_100();
	
	if ($retval) {
		return 'Success';
	} else {
		return 'Failed';
	}
}

function upgradefmFirewall_100() {
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