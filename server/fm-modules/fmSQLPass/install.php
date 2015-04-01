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

function installfmSQLPassSchema($link, $database, $module, $noisy = 'noisy') {
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
INSERT INTO $database.`fm_options` (option_name, option_value, module_name) 
	SELECT 'version', '{$__FM_CONFIG[$module]['version']}', '$module' FROM DUAL
WHERE NOT EXISTS
	(SELECT option_name FROM $database.`fm_options` WHERE option_name = 'version'
		AND module_name='$module');
INSERT;


	/** Update user capabilities */
	$fm_user_caps = null;
	if ($link) {
		$fm_user_caps_query = "SELECT option_value FROM $database.`fm_options` WHERE option_name='fm_user_caps'";
		$result = mysql_query($fm_user_caps_query, $link);
		if ($result) {
			$row = mysql_fetch_array($result, MYSQL_NUM);
			$fm_user_caps = isSerialized($row[0]) ? unserialize($row[0]) : $row[0];
		}
	} else {
		$fm_user_caps = getOption('fm_user_caps');
	}
	$insert = ($fm_user_caps === null) ? true : false;
	
	$fm_user_caps[$module] = array(
			'view_all'				=> 'View All',
			'manage_servers'		=> 'Server Management',
			'manage_passwords'		=> 'Password Management',
			'manage_settings'		=> 'Manage Settings'
		);
	$fm_user_caps = serialize($fm_user_caps);
	
	if ($insert) {
		$inserts[] = "INSERT INTO $database.`fm_options` (option_name, option_value) VALUES ('fm_user_caps', '$fm_user_caps')";
	} else {
		$inserts[] = "UPDATE $database.`fm_options` SET option_value='$fm_user_caps' WHERE option_name='fm_user_caps'";
	}


	/** Create table schema */
	foreach ($table as $schema) {
		if ($link) {
			$result = mysql_query($schema, $link);
			if (mysql_error($link)) {
				return (function_exists('displayProgress')) ? displayProgress($module, $result, $noisy, mysql_error($link)) : $result;
			}
		} else {
			global $fmdb;
			$result = $fmdb->query($schema);
			if ($fmdb->last_error) {
				return (function_exists('displayProgress')) ? displayProgress($module, $result, $noisy, $fmdb->last_error) : $result;
			}
		}
	}

	/** Insert site values if not already present */
	foreach ($inserts as $query) {
		if ($link) {
			$result = mysql_query($query, $link);
			if (mysql_error($link)) {
				return (function_exists('displayProgress')) ? displayProgress($module, $result, $noisy, mysql_error($link)) : $result;
			}
		} else {
			$result = $fmdb->query($query);
			if ($fmdb->last_error) {
				return (function_exists('displayProgress')) ? displayProgress($module, $result, $noisy, $fmdb->last_error) : $result;
			}
		}
	}

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