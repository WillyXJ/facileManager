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
 | fmModule: Brief module description                                      |
 +-------------------------------------------------------------------------+
 | http://URL                                                              |
 +-------------------------------------------------------------------------+
*/

function installfmModuleSchema($database, $module, $noisy = 'noisy') {
	global $fmdb, $fm_name;
	
	/** Include module variables */
	@include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'variables.inc.php');
	
	/** Create fmModule tables **/
	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}servers` (
  `server_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `server_serial_no` int(10) NOT NULL,
  `server_name` varchar(255) NOT NULL,
  `server_os` varchar(50) DEFAULT NULL,
  `server_os_distro` varchar(150) DEFAULT NULL,
  `server_config_file` varchar(255) NOT NULL DEFAULT '/etc/dhcp/dhcpd.conf',
  `server_update_method` enum('http','https','cron','ssh') NOT NULL DEFAULT 'http',
  `server_update_port` int(5) NOT NULL DEFAULT '0',
  `server_build_config` enum('yes','no') NOT NULL DEFAULT 'no',
  `server_update_config` enum('yes','no') NOT NULL DEFAULT 'no',
  `server_installed` enum('yes','no') NOT NULL DEFAULT 'no',
  `server_client_version` varchar(150) DEFAULT NULL,
  `server_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'disabled',
  PRIMARY KEY (`server_id`),
  UNIQUE KEY `idx_server_serial_no` (`server_serial_no`)
) ENGINE = MYISAM  DEFAULT CHARSET=utf8;
TABLE;


	/** Required inserts for module versioning **/
	$inserts[] = <<<INSERT
INSERT INTO `$database`.`fm_options` (option_name, option_value, module_name) 
	SELECT 'version', '{$__FM_CONFIG[$module]['version']}', '$module' FROM DUAL
WHERE NOT EXISTS
	(SELECT option_name FROM `$database`.`fm_options` WHERE option_name = 'version'
		AND module_name='$module');
INSERT;
	$inserts[] = <<<INSERT
INSERT INTO `$database`.`fm_options` (option_name, option_value, module_name) 
	SELECT 'client_version', '{$__FM_CONFIG[$module]['client_version']}', '$module' FROM DUAL
WHERE NOT EXISTS
	(SELECT option_name FROM `$database`.`fm_options` WHERE option_name = 'client_version'
		AND module_name='$module');
INSERT;



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