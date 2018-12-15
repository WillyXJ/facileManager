<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2013-2018 The facileManager Team                               |
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
 | fmWifi: Easily manage one or more access points                         |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmwifi/                            |
 +-------------------------------------------------------------------------+
*/

function upgradefmWifiSchema($module_name) {
	global $fmdb;
	
	/** Include module variables */
	@include(dirname(__FILE__) . '/variables.inc.php');
	
	/** Get current version */
	$running_version = getOption('version', 0, $module_name);
	
	/** Checks to support older versions (ie n-3 upgrade scenarios */
	$success = version_compare($running_version, '1.1.1', '<') ? upgradefmWifi_111($__FM_CONFIG, $running_version) : true;
	if (!$success) return $fmdb->last_error;
	
	setOption('client_version', $__FM_CONFIG['fmWifi']['client_version'], 'auto', false, 0, 'fmWifi');
	
	return true;
}

/** 1.0.1 */
function upgradefmWifi_101($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	/** Insert upgrade steps here **/
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmWifi']['prefix']}table` ...";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmWifi']['prefix']}table` ...";
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}
	
	/** Handle updating table with client version and module version **/
	if (!setOption('fmWifi_client_version', $__FM_CONFIG['fmWifi']['client_version'], 'auto', false)) return false;
	
	setOption('version', '1.0.1', 'auto', false, 0, $module_name);
	
	return true;
}

/** 1.1.1 */
function upgradefmWifi_111($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	/** Check if previous upgrades have run (to support n+1) **/
	$success = version_compare($running_version, '1.0.1', '<') ? upgradefmWifi_101($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	/** Insert upgrade steps here **/
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmWifi']['prefix']}table` ...";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmWifi']['prefix']}table` ...";
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	/** Handle updating table with client version and module version **/
	if (!setOption('fmWifi_client_version', $__FM_CONFIG['fmWifi']['client_version'], 'auto', false)) return false;
	
	setOption('version', '1.1.1', 'auto', false, 0, $module_name);
	
	return true;
}

?>