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

function installfmSQLPassSchema($database, $module, $noisy = 'noisy') {
	global $fmdb;
	
	/** Include module variables */
	@include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'variables.inc.php');
	
	$table[] = <<<TABLESQL
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG['fmSQLPass']['prefix']}groups` (
  `group_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `group_name` varchar(255) NOT NULL,
  `group_pwd_change` int(10) DEFAULT NULL,
  `group_status` enum('active','disabled','deleted') NOT NULL,
  PRIMARY KEY (`group_id`)
) ENGINE = MYISAM DEFAULT CHARSET=utf8;
TABLESQL;

	$table[] = <<<TABLESQL
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG['fmSQLPass']['prefix']}servers` (
  `server_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `server_serial_no` int(10) NOT NULL,
  `server_type` enum('MySQL','PostgreSQL') NOT NULL,
  `server_port` int(5) DEFAULT NULL,
  `server_name` varchar(255) NOT NULL,
  `server_groups` text,
  `server_credentials` text,
  `server_status` enum('active','disabled','deleted') NOT NULL,
  PRIMARY KEY (`server_id`)
) ENGINE = MYISAM DEFAULT CHARSET=utf8;
TABLESQL;


	$inserts[] = <<<INSERTSQL
INSERT INTO `$database`.`fm_options` (option_name, option_value, module_name) 
	SELECT 'version', '{$__FM_CONFIG[$module]['version']}', '$module' FROM DUAL
WHERE NOT EXISTS
	(SELECT option_name FROM `$database`.`fm_options` WHERE option_name = 'version'
		AND module_name='$module');
INSERTSQL;



	/** Create table schema */
	foreach ($table as $schema) {
		$result = $fmdb->query($schema);
		if ($fmdb->last_error) {
			return (function_exists('displayProgress')) ? displayProgress($module, $fmdb->result, $noisy, $fmdb->last_error) : $fmdb->result;
		}
	}

	/** Insert site values if not already present */
	foreach ($inserts as $query) {
		$result = $fmdb->query($query);
		if ($fmdb->last_error) {
			return (function_exists('displayProgress')) ? displayProgress($module, $fmdb->result, $noisy, $fmdb->last_error) : $fmdb->result;
		}
	}

	if (function_exists('displayProgress')) {
		return displayProgress($module, $fmdb->result, $noisy);
	} else {
		return ($fmdb->result) ? 'Success' : 'Failed';
	}
}

?>