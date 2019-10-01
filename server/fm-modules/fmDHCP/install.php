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
	$table[] = <<<TABLESQL
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
TABLESQL;

	$table[] = <<<TABLESQL
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
TABLESQL;

	$table[] = <<<TABLESQL
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}functions` (
  `def_id` int(11) NOT NULL AUTO_INCREMENT,
  `def_function` enum('options') NOT NULL DEFAULT 'options',
  `def_option_type` enum('global','shared','subnet','group','host','pool','peer') NOT NULL DEFAULT 'global',
  `def_prefix` varchar(20) DEFAULT NULL,
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
TABLESQL;


	/** Required inserts for module versioning **/
	$inserts[] = <<<INSERTSQL
INSERT INTO `$database`.`fm_options` (option_name, option_value, module_name) 
	SELECT 'version', '{$__FM_CONFIG[$module]['version']}', '$module' FROM DUAL
WHERE NOT EXISTS
	(SELECT option_name FROM `$database`.`fm_options` WHERE option_name = 'version'
		AND module_name='$module');
INSERTSQL;
	$inserts[] = <<<INSERTSQL
INSERT INTO `$database`.`fm_options` (option_name, option_value, module_name) 
	SELECT 'client_version', '{$__FM_CONFIG[$module]['client_version']}', '$module' FROM DUAL
WHERE NOT EXISTS
	(SELECT option_name FROM `$database`.`fm_options` WHERE option_name = 'client_version'
		AND module_name='$module');
INSERTSQL;

	
	$inserts[] = <<<INSERTSQL
INSERT IGNORE INTO  `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}functions` (
`def_function`,
`def_option_type`,
`def_prefix`,
`def_option`,
`def_type`,
`def_multiple_values`,
`def_dropdown`,
`def_max_parameters`,
`def_direction`,
`def_minimum_version`
)
VALUES 
('options', 'host', NULL, 'hardware', '( ethernet | token ring | fddi )', 'no', 'yes', 1, 'forward', NULL),
('options', 'host', NULL, 'fixed-address', '( address_match_element )', 'no', 'no', 1, 'forward', NULL),
('options', 'subnet', NULL, 'authoritative', '( on | off )', 'no', 'yes', 1, 'empty', NULL),
('options', 'shared', NULL, 'authoritative', '( on | off )', 'no', 'yes', 1, 'empty', NULL),
('options', 'global', NULL, 'bootp', '( allow | deny | ignore )', 'no', 'yes', 1, 'reverse', NULL),
('options', 'global', NULL, 'booting', '( allow | deny | ignore )', 'no', 'yes', 1, 'reverse', NULL),
('options', 'global', NULL, 'duplicates', '( allow | deny | ignore )', 'no', 'yes', 1, 'reverse', NULL),
('options', 'global', NULL, 'declines', '( allow | deny | ignore )', 'no', 'yes', 1, 'reverse', NULL),
('options', 'global', NULL, 'client-updates', '( allow | deny )', 'no', 'yes', 1, 'reverse', NULL),
('options', 'global', NULL, 'default-lease-time', '( seconds )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', NULL, 'max-lease-time', '( seconds )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', NULL, 'min-lease-time', '( seconds )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', NULL, 'min-secs', '( seconds )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', NULL, 'dynamic-bootp-lease-cutoff', '( date )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', NULL, 'dynamic-bootp-lease-length', '( seconds )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', NULL, 'get-lease-hostnames', '( on | off )', 'no', 'yes', 1, 'forward', NULL),
('options', 'global', NULL, 'always-reply-rfc1048', '( on | off )', 'no', 'yes', 1, 'forward', NULL),
('options', 'global', NULL, 'use-lease-addr-for-default-route', '( on | off )', 'no', 'yes', 1, 'forward', NULL),
('options', 'global', NULL, 'server-identifier', '( address_match_element )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', NULL, 'site-option-space', '( option_space )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', NULL, 'vendor-option-space', '( option_space )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', NULL, 'always-broadcast', '( on | off )', 'no', 'yes', 1, 'forward', NULL),
('options', 'global', NULL, 'ddns-domainname', '( quoted_string )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', NULL, 'ddns-hostname', '( quoted_string )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', NULL, 'ddns-rev-domainname', '( quoted_string )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', NULL, 'lease-file-name', '( quoted_string )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', NULL, 'pid-file-name', '( quoted_string )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', NULL, 'ddns-updates', '( on | off )', 'no', 'yes', 1, 'forward', NULL),
('options', 'global', NULL, 'omapi-port', '( port )', 'no', 'no', 1, 'forward', '3.0'),
('options', 'global', NULL, 'omapi-key', '( key )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', NULL, 'stash-agent-options', '( on | off )', 'no', 'yes', 1, 'forward', NULL),
('options', 'global', NULL, 'update-optimization', '( on | off )', 'no', 'yes', 1, 'forward', NULL),
('options', 'global', NULL, 'ping-check', '( on | off )', 'no', 'yes', 1, 'forward', NULL),
('options', 'global', NULL, 'update-static-leases', '( on | off )', 'no', 'yes', 1, 'forward', NULL),
('options', 'global', NULL, 'log-facility', '( kern | user | mail | daemon | auth | syslog | lpr | news | uucp | cron | authpriv | ftp | local0 | local1 | local2 | local3 | local4 | local5 | local6 | local7', 'no', 'yes', 1, 'forward', NULL),
('options', 'global', NULL, 'unknown-clients', '( allow | deny | ignore )', 'no', 'yes', 1, 'reverse', NULL),
('options', 'global', NULL, 'filename', '( quoted_string )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', NULL, 'server-name', '( quoted_string )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', NULL, 'next-server', '( address_match_element )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', NULL, 'include', '( quoted_string )', 'no', 'no', -1, 'forward', NULL),
('options', 'pool', NULL, 'known clients', '( allow | deny )', 'no', 'yes', 1, 'reverse', NULL),
('options', 'pool', NULL, 'unknown clients', '( allow | deny )', 'no', 'yes', 1, 'reverse', NULL),
('options', 'pool', NULL, 'dynamic bootp clients', '( allow | deny )', 'no', 'yes', 1, 'reverse', NULL),
('options', 'pool', NULL, 'all clients', '( allow | deny )', 'no', 'yes', 1, 'reverse', NULL),
('options', 'peer', NULL, 'primary', '( on | off )', 'no', 'yes', 1, 'empty', NULL),
('options', 'peer', NULL, 'secondary', '( on | off )', 'no', 'yes', 1, 'empty', NULL),
('options', 'peer', NULL, 'address', '( address_match_element )', 'no', 'no', 1, 'forward', NULL),
('options', 'peer', NULL, 'peer-address', '( address_match_element )', 'no', 'no', 1, 'forward', NULL),
('options', 'peer', NULL, 'port', '( tcp-port )', 'no', 'no', 1, 'forward', NULL),
('options', 'peer', NULL, 'peer-port', '( tcp-port )', 'no', 'no', 1, 'forward', NULL),
('options', 'peer', NULL, 'max-response-delay', '( seconds )', 'no', 'no', 1, 'forward', NULL),
('options', 'peer', NULL, 'max-unacked-updates', '( integer )', 'no', 'no', 1, 'forward', NULL),
('options', 'peer', NULL, 'mclt', '( seconds )', 'no', 'no', 1, 'forward', NULL),
('options', 'peer', NULL, 'split', '( integer )', 'no', 'no', 1, 'forward', NULL),
('options', 'peer', NULL, 'hba', '( colon-separated-hex-list )', 'no', 'no', 1, 'forward', NULL),
('options', 'peer', NULL, 'load balance max seconds', '( seconds )', 'no', 'no', 1, 'forward', NULL),
('options', 'global', 'option', 'host-name', '( quoted_string )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'routers', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'domain-name-servers', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'subnet-mask', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'broadcast-address', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'domain-name', '( quoted_string )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'domain-search', '( quoted_string )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'time-servers', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'log-servers', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'swap-server', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'root-path', '( quoted_string )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'nis-domain', '( quoted_string )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'nis-servers', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'font-servers', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'x-display-manager', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'ntp-servers', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'netbios-name-servers', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'netbios-scope', '( quoted_string )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'netbios-node-type', '( integer )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'time-offset', '( integer )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'dhcp-server-identifier', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'slp-directory-agent', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'slp-service-scope', '( quoted_string )', 'no', 'no', '1', 'forward', NULL)
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