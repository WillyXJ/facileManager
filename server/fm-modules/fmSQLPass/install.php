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

function installfmSQLPassSchema($link, $database, $module, $noisy = true) {
	/** Include module variables */
	@include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'variables.inc.php');
	
	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG['fmSQLPass']['prefix']}groups` (
  `group_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `group_name` varchar(255) NOT NULL,
  `group_pwd_change` int(10) DEFAULT NULL,
  `group_status` enum('active','disabled','deleted') NOT NULL,
  PRIMARY KEY (`group_id`)
) ENGINE = MYISAM DEFAULT CHARSET=utf8;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG['fmSQLPass']['prefix']}options` (
  `option_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `option_name` varchar(255) NOT NULL,
  `option_value` varchar(255) NOT NULL,
  PRIMARY KEY (`option_id`)
) ENGINE = MYISAM DEFAULT CHARSET=utf8;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG['fmSQLPass']['prefix']}servers` (
  `server_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `server_serial_no` int(10) NOT NULL,
  `server_type` enum('MySQL') NOT NULL,
  `server_port` int(5) DEFAULT NULL,
  `server_name` varchar(255) NOT NULL,
  `server_groups` text,
  `server_credentials` text,
  `server_status` enum('active','disabled','deleted') NOT NULL,
  PRIMARY KEY (`server_id`)
) ENGINE = MYISAM DEFAULT CHARSET=utf8;
TABLE;


	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_options` (option_name, option_value) 
	SELECT '{$module}_version', '{$__FM_CONFIG[$module]['version']}' FROM DUAL
WHERE NOT EXISTS
	(SELECT option_name FROM $database.`fm_options` WHERE option_name = '{$module}_version');
INSERT;



	/** Create table schema */
	foreach ($table as $schema) {
		if ($link) {
			$result = mysql_query($schema, $link);
		} else {
			global $fmdb;
			$result = $fmdb->query($schema);
		}
	}

	/** Insert site values if not already present */
//	$query = "SELECT * FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}config";
//	$temp_result = mysql_query($query, $link);
//	if (!@mysql_num_rows($temp_result)) {
		foreach ($inserts as $query) {
			if ($link) {
				$result = mysql_query($query, $link);
			} else {
				$result = $fmdb->query($query);
			}
		}
//	}

	if (function_exists('displayProgress')) {
		return displayProgress($module, $result, $noisy);
	} else {
		if ($result) {
			return 'Success';
		} else {
			return 'Failed';
		}
	}
}

?>