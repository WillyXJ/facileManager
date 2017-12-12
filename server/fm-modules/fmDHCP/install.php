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
 | fmDHCP: Easily manage one or more ISC DHCP servers                      |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdhcp/                            |
 +-------------------------------------------------------------------------+
*/

function installfmDHCPSchema($database, $module, $noisy = 'noisy') {
	global $fmdb, $fm_name;
	
	/** Include module variables */
	@include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'variables.inc.php');
	
	/** Create fmDHCP tables **/
	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}servers` (
  `server_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `server_serial_no` int(10) NOT NULL,
  `server_name` varchar(255) NOT NULL,
  `server_address` varchar(255) DEFAULT NULL,
  `server_os` varchar(50) DEFAULT NULL,
  `server_os_distro` varchar(150) DEFAULT NULL,
  `server_type` enum('dhcpd') NOT NULL DEFAULT 'dhcpd',
  `server_version` varchar(150) DEFAULT NULL,
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

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}config` (
  `config_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `server_serial_no` varchar(255) NOT NULL DEFAULT '0',
  `config_type` enum('global','subnet','shared','host','group','pool','peer') DEFAULT NULL,
  `config_is_parent` enum('yes','no') NOT NULL DEFAULT 'no',
  `config_parent_id` int(11) NOT NULL DEFAULT '0',
  `config_name` varchar(50) NOT NULL,
  `config_data` text NOT NULL,
  `config_assigned_to` int(11) NOT NULL DEFAULT '0',
  `config_comment` text,
  `config_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`config_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}functions` (
  `def_id` int(11) NOT NULL AUTO_INCREMENT,
  `def_function` enum('options') NOT NULL DEFAULT 'options',
  `def_option_type` enum('global','host','pool','peer') NOT NULL DEFAULT 'global',
  `def_option` varchar(255) NOT NULL,
  `def_type` varchar(200) NOT NULL,
  `def_multiple_values` enum('yes','no') NOT NULL DEFAULT 'no',
  `def_dropdown` enum('yes','no') NOT NULL DEFAULT 'no',
  `def_max_parameters` int(3) NOT NULL DEFAULT '1',
  `def_direction` enum('forward','reverse','empty') NOT NULL DEFAULT 'forward',
  `def_minimum_version` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`def_id`),
  KEY `idx_def_option` (`def_option`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;
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

	
	$inserts[] = <<<INSERT
INSERT IGNORE INTO  `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}functions` (
`def_function`,
`def_option_type`,
`def_option`,
`def_type`,
`def_multiple_values`,
`def_dropdown`,
`def_max_parameters`,
`def_direction`,
`def_minimum_version`
)
VALUES 
('options', 'host', 'hardware', '( ethernet | token ring | fddi )', 'no', 'yes', 1, 'forward', NULL),
('options', 'host', 'fixed-address', '( address_match_element )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', 'bootp', '( allow | deny | ignore )', 'no', 'yes', 1, 'reverse', NULL),
('options', 'global', 'booting', '( allow | deny | ignore )', 'no', 'yes', 1, 'reverse', NULL),
('options', 'global', 'duplicates', '( allow | deny | ignore )', 'no', 'yes', 1, 'reverse', NULL),
('options', 'global', 'declines', '( allow | deny | ignore )', 'no', 'yes', 1, 'reverse', NULL),
('options', 'global', 'client-updates', '( allow | deny )', 'no', 'yes', 1, 'reverse', NULL),
('options', 'global', 'default-lease-time', '( seconds )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', 'max-lease-time', '( seconds )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', 'min-lease-time', '( seconds )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', 'min-secs', '( seconds )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', 'dynamic-bootp-lease-cutoff', '( date )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', 'dynamic-bootp-lease-length', '( seconds )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', 'get-lease-hostnames', '( on | off )', 'no', 'yes', 1, 'forward', NULL),
('options', 'global', 'authoritative', '( on | off )', 'no', 'yes', 1, 'empty', NULL),
('options', 'global', 'always-reply-rfc1048', '( on | off )', 'no', 'yes', 1, 'forward', NULL),
('options', 'global', 'use-lease-addr-for-default-route', '( on | off )', 'no', 'yes', 1, 'forward', NULL),
('options', 'global', 'server-identifier', '( address_match_element )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', 'site-option-space', '( option_space )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', 'vendor-option-space', '( option_space )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', 'always-broadcast', '( on | off )', 'no', 'yes', 1, 'forward', NULL),
('options', 'global', 'ddns-domainname', '( quoted_string )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', 'ddns-hostname', '( quoted_string )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', 'ddns-rev-domainname', '( quoted_string )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', 'lease-file-name', '( quoted_string )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', 'pid-file-name', '( quoted_string )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', 'ddns-updates', '( on | off )', 'no', 'yes', 1, 'forward', NULL),
('options', 'global', 'omapi-port', '( port )', 'no', 'no', 1, 'forward', '3.0'),
('options', 'global', 'omapi-key', '( key )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', 'stash-agent-options', '( on | off )', 'no', 'yes', 1, 'forward', NULL),
('options', 'global', 'update-optimization', '( on | off )', 'no', 'yes', 1, 'forward', NULL),
('options', 'global', 'ping-check', '( on | off )', 'no', 'yes', 1, 'forward', NULL),
('options', 'global', 'update-static-leases', '( on | off )', 'no', 'yes', 1, 'forward', NULL),
('options', 'global', 'log-facility', '( kern | user | mail | daemon | auth | syslog | lpr | news | uucp | cron | authpriv | ftp | local0 | local1 | local2 | local3 | local4 | local5 | local6 | local7', 'no', 'yes', 1, 'forward', NULL),
('options', 'global', 'unknown-clients', '( allow | deny | ignore )', 'no', 'yes', 1, 'reverse', NULL),
('options', 'global', 'filename', '( quoted_string )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', 'server-name', '( quoted_string )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', 'next-server', '( address_match_element )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', 'include', '( quoted_string )', 'no', 'no', -1, 'forward', NULL),
('options', 'pool', 'known clients', '( allow | deny )', 'no', 'yes', 1, 'reverse', NULL),
('options', 'pool', 'unknown clients', '( allow | deny )', 'no', 'yes', 1, 'reverse', NULL),
('options', 'pool', 'dynamic bootp clients', '( allow | deny )', 'no', 'yes', 1, 'reverse', NULL),
('options', 'pool', 'all clients', '( allow | deny )', 'no', 'yes', 1, 'reverse', NULL),
('options', 'peer', 'primary', '( on | off )', 'no', 'yes', 1, 'empty', NULL),
('options', 'peer', 'secondary', '( on | off )', 'no', 'yes', 1, 'empty', NULL),
('options', 'peer', 'address', '( address_match_element )', 'no', 'no', 1, 'forward', NULL),
('options', 'peer', 'peer-address', '( address_match_element )', 'no', 'no', 1, 'forward', NULL),
('options', 'peer', 'port', '( tcp-port )', 'no', 'no', 1, 'forward', NULL),
('options', 'peer', 'peer-port', '( tcp-port )', 'no', 'no', 1, 'forward', NULL),
('options', 'peer', 'max-response-delay', '( seconds )', 'no', 'no', 1, 'forward', NULL),
('options', 'peer', 'max-unacked-updates', '( integer )', 'no', 'no', 1, 'forward', NULL),
('options', 'peer', 'mclt', '( seconds )', 'no', 'no', 1, 'forward', NULL),
('options', 'peer', 'split', '( integer )', 'no', 'no', 1, 'forward', NULL),
('options', 'peer', 'hba', '( colon-separated-hex-list )', 'no', 'no', 1, 'forward', NULL),
('options', 'peer', 'load balance max secs', '( seconds )', 'no', 'no', 1, 'forward', NULL)
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