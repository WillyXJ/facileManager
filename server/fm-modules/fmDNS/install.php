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
 | fmDNS: Easily manage one or more ISC BIND servers                       |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdns/                             |
 +-------------------------------------------------------------------------+
*/

function installfmDNSSchema($link = null, $database, $module, $noisy = true) {
	/** Include module variables */
	@include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'variables.inc.php');
	
	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG[$module]['prefix']}acls` (
  `acl_id` INT(11) NOT NULL AUTO_INCREMENT ,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `server_serial_no` int(11) NOT NULL DEFAULT '0',
  `acl_name` VARCHAR(255) NOT NULL ,
  `acl_predefined` ENUM( 'none',  'any',  'localhost',  'localnets',  'as defined:') NOT NULL ,
  `acl_addresses` TEXT NOT NULL ,
  `acl_comment` text,
  `acl_status` ENUM( 'active',  'disabled',  'deleted') NOT NULL DEFAULT  'active',
  PRIMARY KEY (`acl_id`)
) ENGINE = MYISAM DEFAULT CHARSET=utf8;
TABLE;
	
	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG[$module]['prefix']}config` (
  `cfg_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `server_serial_no` int(11) NOT NULL DEFAULT '0',
  `cfg_type` varchar(255) NOT NULL DEFAULT 'global',
  `view_id` int(11) NOT NULL DEFAULT '0',
  `domain_id` int(11) NOT NULL DEFAULT '0',
  `cfg_isparent` enum('yes','no') NOT NULL DEFAULT 'no',
  `cfg_parent` int(11) NOT NULL DEFAULT '0',
  `cfg_name` varchar(50) NOT NULL,
  `cfg_data` text NOT NULL,
  `cfg_comment` text,
  `cfg_status` enum('hidden','active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`cfg_id`),
  KEY `domain_id` (`domain_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG[$module]['prefix']}controls` (
  `control_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `server_serial_no` int(11) NOT NULL DEFAULT '0',
  `control_ip` varchar(15) NOT NULL DEFAULT '*',
  `control_port` int(5) NOT NULL DEFAULT '953',
  `control_addresses` text NOT NULL,
  `control_keys` varchar(255) DEFAULT NULL,
  `control_comment` text,
  `control_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`control_id`)
) ENGINE = MYISAM DEFAULT CHARSET=utf8;
TABLE;
	
	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG[$module]['prefix']}domains` (
  `domain_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `domain_template` ENUM('yes','no') NOT NULL DEFAULT 'no',
  `domain_default` ENUM('yes','no') NOT NULL DEFAULT 'no',
  `soa_id` int(11) NOT NULL DEFAULT '0',
  `soa_serial_no` INT(2) UNSIGNED ZEROFILL NOT NULL DEFAULT  '0',
  `domain_name` varchar(255) NOT NULL DEFAULT '',
  `domain_name_servers` varchar(255) NOT NULL DEFAULT '0',
  `domain_view` varchar(255) NOT NULL DEFAULT '0',
  `domain_mapping` enum('forward','reverse') NOT NULL DEFAULT 'forward',
  `domain_type` enum('master','slave','forward','stub') NOT NULL DEFAULT 'master',
  `domain_clone_domain_id` int(11) NOT NULL DEFAULT '0',
  `domain_reload` enum('yes','no') NOT NULL DEFAULT 'no',
  `domain_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`domain_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG[$module]['prefix']}functions` (
  `def_id` int(11) NOT NULL AUTO_INCREMENT,
  `def_function` enum('options','logging','key','view') NOT NULL,
  `def_option_type` enum('global','ratelimit') NOT NULL DEFAULT 'global',
  `def_option` varchar(255) NOT NULL,
  `def_type` varchar(200) NOT NULL,
  `def_multiple_values` enum('yes','no') NOT NULL DEFAULT 'no',
  `def_clause_support` varchar(10) NOT NULL DEFAULT 'O',
  `def_zone_support` VARCHAR(10) NULL DEFAULT NULL,
  `def_dropdown` enum('yes','no') NOT NULL DEFAULT 'no',
  `def_max_parameters` int(3) NOT NULL DEFAULT '1',
  PRIMARY KEY (`def_id`),
  KEY `def_option` (`def_option`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG[$module]['prefix']}keys` (
  `key_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `key_name` varchar(255) NOT NULL,
  `key_algorithm` enum('hmac-md5',  'hmac-sha1',  'hmac-sha224',  'hmac-sha256', 'hmac-sha384',  'hmac-sha512') NOT NULL DEFAULT 'hmac-md5',
  `key_secret` varchar(255) NOT NULL,
  `key_view` int(11) NOT NULL DEFAULT '0',
  `key_comment` text,
  `key_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`key_id`)
) ENGINE = MYISAM DEFAULT CHARSET=utf8;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG[$module]['prefix']}records` (
  `record_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `domain_id` int(11) NOT NULL DEFAULT '0',
  `record_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `record_name` varchar(255) DEFAULT '@',
  `record_value` text,
  `record_ttl` varchar(50) NOT NULL DEFAULT '',
  `record_class` enum('IN','CH','HS') NOT NULL DEFAULT 'IN',
  `record_type` ENUM( 'A',  'AAAA',  'CERT',  'CNAME',  'DNAME',  'DNSKEY', 'KEY',
	'KX',  'MX',  'NS',  'PTR',  'RP',  'SRV',  'TXT', 'HINFO', 'SSHFP' ) NOT NULL DEFAULT  'A',
  `record_priority` int(4) DEFAULT NULL,
  `record_weight` int(4) DEFAULT NULL,
  `record_port` int(4) DEFAULT NULL,
  `record_os` varchar(255) DEFAULT NULL,
  `record_cert_type` tinyint(4) DEFAULT NULL,
  `record_key_tag` int(11) DEFAULT NULL,
  `record_algorithm` tinyint(4) DEFAULT NULL,
  `record_flags` enum('0','256','257') DEFAULT NULL,
  `record_text` varchar(255) DEFAULT NULL,
  `record_comment` varchar(200),
  `record_append` enum('yes','no') NOT NULL DEFAULT 'yes',
  `record_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`record_id`),
  KEY `domain_id` (`domain_id`)
) ENGINE=INNODB DEFAULT CHARSET=utf8 ;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG[$module]['prefix']}records_skipped` (
  `skip_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `domain_id` int(11) NOT NULL,
  `record_id` int(11) NOT NULL,
  `record_status` enum('active','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`skip_id`),
  KEY `record_id` (`record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG[$module]['prefix']}servers` (
  `server_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `server_serial_no` int(10) NOT NULL,
  `server_name` varchar(255) NOT NULL,
  `server_os` varchar(50) DEFAULT NULL,
  `server_os_distro` varchar(50) DEFAULT NULL,
  `server_key` int(11) NOT NULL,
  `server_type` enum('bind9') NOT NULL DEFAULT 'bind9',
  `server_version` varchar(150) DEFAULT NULL,
  `server_run_as_predefined` enum('named','bind','daemon','as defined:') NOT NULL DEFAULT 'named',
  `server_run_as` varchar(50) DEFAULT NULL,
  `server_root_dir` varchar(255) NOT NULL,
  `server_chroot_dir` VARCHAR(255) NULL DEFAULT NULL,
  `server_zones_dir` varchar(255) NOT NULL,
  `server_config_file` varchar(255) NOT NULL DEFAULT '/etc/named.conf',
  `server_update_method` enum('http','https','cron','ssh') NOT NULL DEFAULT 'http',
  `server_update_port` int(5) NOT NULL DEFAULT '0',
  `server_build_config` enum('yes','no') NOT NULL DEFAULT 'no',
  `server_update_config` enum('yes','no','conf') NOT NULL DEFAULT 'no',
  `server_installed` enum('yes','no') NOT NULL DEFAULT 'no',
  `server_client_version` varchar(150) DEFAULT NULL,
  `server_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'disabled',
  PRIMARY KEY (`server_id`),
  UNIQUE KEY `server_serial_no` (`server_serial_no`)
) ENGINE = MYISAM  DEFAULT CHARSET=utf8;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG[$module]['prefix']}server_groups` (
  `group_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `group_name` varchar(128) NOT NULL,
  `group_masters` text NOT NULL,
  `group_slaves` text NOT NULL,
  `group_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG[$module]['prefix']}soa` (
  `soa_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `soa_template` ENUM(  'yes',  'no' ) NOT NULL DEFAULT  'no',
  `soa_default` ENUM(  'yes',  'no' ) NOT NULL DEFAULT  'no',
  `soa_name` varchar(255) DEFAULT NULL,
  `soa_master_server` varchar(50) NOT NULL DEFAULT '',
  `soa_append` enum('yes','no') NOT NULL DEFAULT 'yes',
  `soa_email_address` varchar(50) NOT NULL DEFAULT '',
  `soa_refresh` varchar(50) DEFAULT '21600',
  `soa_retry` varchar(50) DEFAULT '7200',
  `soa_expire` varchar(50) DEFAULT '604800',
  `soa_ttl` varchar(50) DEFAULT '1200',
  `soa_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`soa_id`)
) ENGINE=INNODB DEFAULT CHARSET=utf8 ;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG[$module]['prefix']}track_builds` (
  `domain_id` int(11) NOT NULL,
  `server_serial_no` int(11) NOT NULL
) ENGINE=INNODB DEFAULT CHARSET=utf8;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG[$module]['prefix']}track_reloads` (
  `domain_id` int(11) NOT NULL,
  `server_serial_no` int(11) NOT NULL,
  `soa_id` int(11) NOT NULL
) ENGINE = INNODB DEFAULT CHARSET=utf8;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG[$module]['prefix']}views` (
  `view_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `server_serial_no` int(11) NOT NULL DEFAULT '0',
  `view_name` VARCHAR(255) NOT NULL ,
  `view_comment` text,
  `view_status` ENUM( 'active',  'disabled',  'deleted') NOT NULL DEFAULT  'active'
) ENGINE = MYISAM DEFAULT CHARSET=utf8;
TABLE;


	/** fm_prefix_config inserts */
	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '0', '0', 'directory', '\$ROOT', 'hidden' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG[$module]['prefix']}config` WHERE account_id = '0');
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'version', 'none', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG[$module]['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'version' AND server_serial_no = '0'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'hostname', 'none', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG[$module]['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'hostname' AND server_serial_no = '0'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'recursion', 'no', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG[$module]['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'recursion' AND server_serial_no = '0'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'statistics-file', '"\$ROOT/named.stats"', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG[$module]['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'statistics-file' AND server_serial_no = '0'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'zone-statistics', 'yes', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG[$module]['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'zone-statistics' AND server_serial_no = '0'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'pid-file', '"\$ROOT/named.pid"', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG[$module]['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'pid-file' AND server_serial_no = '0'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'dump-file', '"\$ROOT/named.dump"', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG[$module]['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'dump-file' AND server_serial_no = '0'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'auth-nxdomain', 'no', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG[$module]['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'auth-nxdomain' AND server_serial_no = '0'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'cleaning-interval', '120', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG[$module]['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'cleaning-interval' AND server_serial_no = '0'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'interface-interval', '0', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG[$module]['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'interface-interval' AND server_serial_no = '0'
	);
INSERT;
	
	
	
	/** fm_prefix_functions inserts*/
	$inserts[] = <<<INSERT
INSERT IGNORE INTO  $database.`fm_{$__FM_CONFIG[$module]['prefix']}functions` (
`def_function` ,
`def_option` ,
`def_type` ,
`def_multiple_values` ,
`def_clause_support`,
`def_zone_support`,
`def_dropdown`
)
VALUES 
('key', 'algorithm', 'string', 'no', 'K', NULL, 'no'),
('key', 'secret', 'quoted_string', 'no', 'K', NULL, 'no'),
('options', 'acache-cleaning-interval', '( minutes )', 'no', 'OV', NULL, 'no'),
('options', 'acache-enable', '( yes | no )', 'no', 'OV', NULL, 'yes'),
('options', 'additional-from-auth', '( yes | no )', 'no', 'OV', NULL, 'yes'),
('options', 'additional-from-cache', '( yes | no )', 'no', 'OV', NULL, 'yes'),
('options', 'allow-notify', '( address_match_element )', 'yes', 'OVZ', 'S', 'no'),
('options', 'allow-query', '( address_match_element )', 'yes', 'OVZ', 'MS', 'no'),
('options', 'allow-query-cache', '( address_match_element )', 'yes', 'OV', NULL, 'no'),
('options', 'allow-query-cache-on', '( address_match_element )', 'yes', 'OV', NULL, 'no'),
('options', 'allow-query-on', '( address_match_element )', 'yes', 'OVZ', 'MS', 'no'),
('options', 'allow-recursion', '( address_match_element )', 'yes', 'OV', NULL, 'no'),
('options', 'allow-recursion-on', '( address_match_element )', 'yes', 'OV', NULL, 'no'),
('options', 'allow-transfer', '( address_match_element )', 'yes', 'OVZ', 'MS', 'no'),
('options', 'allow-update', '( address_match_element )', 'yes', 'OVZ', 'MS', 'no'),
('options', 'allow-update-forwarding', '( address_match_element )', 'yes', 'OVZ', 'MS', 'no'),
('options', 'also-notify', '( ipv4_address | ipv6_address ) [ port ( ip_port | * ) ]', 'yes', 'OVZ', 'M', 'no'),
('options', 'alt-transfer-source', '( ipv4_address | * ) [ port ( ip_port | * ) ]', 'no', 'OVZ', 'S', 'no'),
('options', 'alt-transfer-source-v6', '( ipv6_address | * ) [ port ( ip_port | * ) ]', 'no', 'OVZ', 'S', 'no'),
('options', 'attach-cache', '( quoted_string )', 'no', 'OV', NULL, 'no'),
('options', 'auth-nxdomain', '( yes | no )', 'no', 'OV', NULL, 'yes'),
('options', 'auto-dnssec', '( allow | maintain | create | off )', 'no', 'OVZ', 'MS', 'yes'),
('options', 'avoid-v4-udp-ports', '( ip_port )', 'yes', 'O', NULL, 'no'),
('options', 'avoid-v6-udp-ports', '( ip_port )', 'yes', 'O', NULL, 'no'),
('options', 'bindkeys-file', '( quoted_string )', 'no', 'O', NULL, 'no'),
('options', 'blackhole', '( address_match_element )', 'yes', 'O', NULL, 'no'),
('options', 'bogus', '( yes | no )', 'no', 'S', NULL, 'yes'),
('options', 'check-dup-records', '( fail | warn | ignore )', 'no', 'OVZ', 'MS', 'yes'),
('options', 'check-integrity', '( yes | no )', 'no', 'OVZ', 'MS', 'yes'),
('options', 'check-mx', '( fail | warn | ignore )', 'no', 'OVZ', 'MS', 'yes'),
('options', 'check-mx-cname', '( fail | warn | ignore )', 'no', 'OVZ', 'MS', 'yes'),
('options', 'check-names', '( warn | fail | ignore )', 'no', 'Z', 'MS', 'yes'),
('options', 'check-sibling', '( yes | no )', 'no', 'OVZ', 'MS', 'yes'),
('options', 'check-srv-cname', '( fail | warn | ignore )', 'no', 'OVZ', 'MS', 'yes'),
('options', 'check-wildcard', '( yes | no )', 'no', 'OVZ', 'MS', 'yes'),
('options', 'cleaning-interval', '( minutes )', 'no', 'OV', NULL, 'no'),
('options', 'clients-per-query', '( integer )', 'no', 'OV', NULL, 'no'),
('options', 'coresize', '( size_in_bytes )', 'no', 'O', NULL, 'no'),
('options', 'database', '( quoted_string )', 'no', 'Z', 'MS', 'no'),
('options', 'datasize', '( size_in_bytes )', 'no', 'O', NULL, 'no'),
('options', 'delegation-only', '( yes | no )', 'no', 'Z', 'MS', 'yes'),
('options', 'deny-answer-address', '( address_match_element ) [ except-from { ( address_match_element ) } ]', 'yes', 'OV', NULL, 'no'),
('options', 'deny-answer-aliases', '( quoted_string ) [ except-from { ( address_match_element ) } ]', 'yes', 'OV', NULL, 'no'),
('options', 'dialup', '( yes | no | notify | refresh | passive | notify-passive )', 'no', 'OVZ', 'MS', 'yes'),
('options', 'disable-algorithms', '( string )', 'yes', 'OV', NULL, 'no'),
('options', 'disable-empty-zone', '( quoted_string )', 'no', 'OV', NULL, 'no'),
('options', 'dnssec-accept-expired', '( yes | no )', 'no', 'OV', NULL, 'yes'),
('options', 'dnssec-dnskey-kskonly', '( yes | no )', 'no', 'OVZ', 'MS', 'yes'),
('options', 'dnssec-enable', '( yes | no )', 'no', 'OV', NULL, 'yes'),
('options', 'dnssec-lookaside', 'domain trust-anchor domain', 'no', 'OV', NULL, 'no'),
('options', 'dnssec-must-be-secure', 'domain ( yes | no )', 'no', 'OV', NULL, 'no'),
('options', 'dnssec-secure-to-insecure', '( yes | no )', 'no', 'OVZ', 'MS', 'yes'),
('options', 'dnssec-validation', '( yes | no )', 'no', 'OV', NULL, 'yes'),
('options', 'dual-stack-servers', '( quoted_string )', 'yes', 'OV', NULL, 'no'),
('options', 'dump-file', '( quoted_string )', 'no', 'O', NULL, 'no'),
('options', 'edns', '( yes | no )', 'no', 'S', NULL, 'yes'),
('options', 'edns-udp-size', '( size_in_bytes )', 'no', 'OSV', NULL, 'no'),
('options', 'empty-contact', '( string )', 'no', 'OV', NULL, 'no'),
('options', 'empty-server', '( string )', 'no', 'OV', NULL, 'no'),
('options', 'empty-zones-enable', '( yes | no )', 'no', 'OV', NULL, 'yes'),
('options', 'files', '( integer )', 'no', 'O', NULL, 'no'),
('options', 'flush-zones-on-shutdown', '( yes | no )', 'no', 'O', NULL, 'yes'),
('options', 'forward', '( first | only )', 'no', 'OVZ', 'F', 'yes'),
('options', 'forwarders', '[ port ( ip_port | * ) ] { ( ipv4_address | ipv6_address ) } [ port ( ip_port | * ) ]', 'yes', 'OVZ', 'F', 'no'),
('options', 'heartbeat-interval', '( minutes )', 'no', 'O', NULL, 'no'),
('options', 'hostname', '( quoted_string | none )', 'no', 'O', NULL, 'no'),
('options', 'interface-interval', '( minutes )', 'no', 'O', NULL, 'no'),
('options', 'ixfr-from-differences', '( yes | no )', 'no', 'Z', 'MS', 'yes'),
('options', 'journal', '( quoted_string )', 'no', 'Z', 'MS', 'no'),
('options', 'key-directory', '( quoted_string )', 'no', 'OVZ', 'MS', 'no'),
('options', 'lame-ttl', '( seconds )', 'no', 'OV', NULL, 'no'),
('options', 'listen-on', '[ port ( ip_port | * ) ] { ( ipv4_address ) }', 'yes', 'OR', NULL, 'no'),
('options', 'listen-on-v6', '[ port ( ip_port | * ) ] { ( ipv6_address ) }', 'yes', 'O', NULL, 'no'),
('options', 'managed-keys-directory', '( quoted_string )', 'no', 'O', NULL, 'no'),
('options', 'masterfile-format', '( text | raw )', 'no', 'OVZ', 'MS', 'yes'),
('options', 'masters', '( { ipv4_address | ipv6_address } )', 'yes', 'OVZ', 'S', 'no'),
('options', 'match-clients', '( address_match_element )', 'yes', 'V', NULL, 'no'),
('options', 'match-destinations', '( address_match_element )', 'yes', 'V', NULL, 'no'),
('options', 'match-mapped-addresses', '( yes | no )', 'no', 'O', NULL, 'yes'),
('options', 'match-recursive-only', '( yes | no )', 'no', 'V', NULL, 'yes'),
('options', 'max-acache-size', '( size_in_bytes )', 'no', 'OV', NULL, 'no'),
('options', 'max-cache-size', '( size_in_bytes )', 'no', 'OV', NULL, 'no'),
('options', 'max-cache-ttl', '( seconds )', 'no', 'OV', NULL, 'no'),
('options', 'max-clients-per-query', '( integer )', 'no', 'OV', NULL, 'no'),
('options', 'max-journal-size', '( size_in_bytes )', 'no', 'OVZ', 'MS', 'no'),
('options', 'max-ncache-ttl', '( seconds )', 'no', 'OV', NULL, 'no'),
('options', 'max-refresh-time', '( seconds )', 'no', 'OVZ', 'S', 'no'),
('options', 'max-retry-time', '( seconds )', 'no', 'OVZ', 'S', 'no'),
('options', 'max-transfer-idle-in', '( minutes )', 'no', 'OVZ', 'S', 'no'),
('options', 'max-transfer-idle-out', '( minutes )', 'no', 'OVZ', 'M', 'no'),
('options', 'max-transfer-time-in', '( minutes )', 'no', 'OVZ', 'S', 'no'),
('options', 'max-transfer-time-out', '( minutes )', 'no', 'OVZ', 'M', 'no'),
('options', 'max-udp-size', '( size_in_bytes )', 'no', 'OSV', NULL, 'no'),
('options', 'memstatistics', '( yes | no )', 'no', 'O', NULL, 'yes'),
('options', 'memstatistics-file', '( quoted_string )', 'no', 'O', NULL, 'no'),
('options', 'min-refresh-time', '( seconds )', 'no', 'OVZ', 'S', 'no'),
('options', 'min-retry-time', '( seconds )', 'no', 'OVZ', 'S', 'no'),
('options', 'minimal-responses', '( yes | no )', 'no', 'OV', NULL, 'yes'),
('options', 'multi-master', '( yes | no )', 'no', 'OVZ', 'S', 'yes'),
('options', 'ndots', '( integer )', 'no', 'R', NULL, 'no'),
('options', 'notify', '( yes | no | explicit )', 'no', 'OVZ', 'MS', 'yes'),
('options', 'notify-delay', '( seconds )', 'no', 'OVZ', 'MS', 'no'),
('options', 'notify-source', '( ipv4_address | * ) [ port ( ip_port | * ) ]', 'no', 'OSVZ', 'M', 'no'),
('options', 'notify-source-v6', '( ipv6_address | * ) [ port ( ip_port | * ) ]', 'no', 'OSVZ', 'M', 'no'),
('options', 'notify-to-soa', '( yes | no )', 'no', 'OVZ', 'MS', 'yes'),
('options', 'pid-file', '( quoted_string | none )', 'no', 'O', NULL, 'no'),
('options', 'port', '( ip_port )', 'no', 'O', NULL, 'no'),
('options', 'preferred-glue', '( A | AAAA )', 'no', 'OV', NULL, 'yes'),
('options', 'provide-ixfr', '( yes | no )', 'no', 'S', 'M', 'yes'),
('options', 'query-source', 'address ( ipv4_address | * ) [ port ( ip_port | * ) ]', 'no', 'OVZ', 'MS', 'no'),
('options', 'query-source-v6', 'address ( ipv6_address | * ) [ port ( ip_port | * ) ]', 'no', 'OVZ', 'MS', 'no'),
('options', 'querylog', '( yes | no )', 'no', 'O', NULL, 'yes'),
('options', 'random-device', '( quoted_string )', 'no', 'O', NULL, 'no'),
('options', 'recursing-file', '( quoted_string )', 'no', 'O', NULL, 'no'),
('options', 'recursion', '( yes | no )', 'no', 'OV', NULL, 'yes'),
('options', 'recursive-clients', '( integer )', 'no', 'O', NULL, 'no'),
('options', 'request-ixfr', '( yes | no )', 'no', 'OVZ', 'S', 'yes'),
('options', 'request-nsid', '( yes | no )', 'no', 'OV', NULL, 'yes'),
('options', 'reserved-sockets', '( integer )', 'no', 'O', NULL, 'no'),
('options', 'search', '( quoted_string )', 'yes', 'R', NULL, 'no'),
('options', 'secroots-file', '( quoted_string )', 'no', 'O', NULL, 'no'),
('options', 'serial-query-rate', '( integer )', 'no', 'O', NULL, 'no'),
('options', 'server-id', '( quoted_string | none | hostname )', 'no', 'O', NULL, 'no'),
('options', 'session-keyfile', '( quoted_string | none )', 'no', 'O', NULL, 'no'),
('options', 'session-keyname', '( quoted_string )', 'no', 'O', NULL, 'no'),
('options', 'session-keyalg', '( string )', 'no', 'O', NULL, 'no'),
('options', 'sig-signing-nodes', '( integer )', 'no', 'OVZ', 'MS', 'no'),
('options', 'sig-signing-signatures', '( integer )', 'no', 'OVZ', 'MS', 'no'),
('options', 'sig-signing-type', '( integer )', 'no', 'OVZ', 'MS', 'no'),
('options', 'sig-validity-interval', '( days )', 'no', 'OVZ', 'MS', 'no'),
('options', 'sortlist', '( address_match_element )', 'yes', 'OV', NULL, 'no'),
('options', 'stacksize', '( size_in_bytes )', 'no', 'O', NULL, 'no'),
('options', 'statistics-file', '( quoted_string )', 'no', 'O', NULL, 'no'),
('options', 'tcp-clients', '( integer )', 'no', 'O', NULL, 'no'),
('options', 'tcp-listen-queue', '( integer )', 'no', 'O', NULL, 'no'),
('options', 'tkey-dhkey', '( quoted_string integer )', 'no', 'O', NULL, 'no'),
('options', 'tkey-domain', '( quoted_string )', 'no', 'O', NULL, 'no'),
('options', 'tkey-gssapi-credential', '( quoted_string )', 'no', 'O', NULL, 'no'),
('options', 'transfer-format', '( many-answers | one-answer )', 'no', 'OSVZ', 'M', 'yes'),
('options', 'transfer-source', '( ipv4_address | * ) [ port ( ip_port | * ) ]', 'no', 'OSVZ', 'S', 'no'),
('options', 'transfer-source-v6', '( ipv6_address | * ) [ port ( ip_port | * ) ]', 'no', 'OSVZ', 'S', 'no'),
('options', 'transfers', '( integer )', 'no', 'S', NULL, 'no'),
('options', 'transfers-in', '( integer )', 'no', 'O', 'S', 'no'),
('options', 'transfers-out', '( integer )', 'no', 'O', 'M', 'no'),
('options', 'transfers-per-ns', '( integer )', 'no', 'O', 'S', 'no'),
('options', 'try-tcp-refresh', '( yes | no )', 'no', 'OVZ', NULL, 'yes'),
('options', 'update-check-ksk', '( yes | no )', 'no', 'OVZ', NULL, 'yes'),
('options', 'update-policy', '( local | { update-policy-rule } )', 'no', 'Z', 'MS', 'no'),
('options', 'use-alt-transfer-source', '( yes | no )', 'no', 'OVZ', 'MS', 'yes'),
('options', 'use-v4-udp-ports', '( range ip_port ip_port )', 'no', 'O', NULL, 'no'),
('options', 'use-v6-udp-ports', '( range ip_port ip_port )', 'no', 'O', NULL, 'no'),
('options', 'view', '( quoted_string )', 'no', 'R', NULL, 'no'),
('options', 'version', '( quoted_string | none )', 'no', 'O', NULL, 'no'),
('options', 'zero-no-soa-ttl', '( yes | no )', 'no', 'OVZ', 'MS', 'yes'),
('options', 'zero-no-soa-ttl-cache', '( yes | no )', 'no', 'OV', NULL, 'yes'),
('options', 'zone-statistics', '( yes | no )', 'no', 'OVZ', 'MS', 'yes')
;
INSERT;
	
	$inserts[] = <<<INSERT
INSERT IGNORE INTO  $database.`fm_{$__FM_CONFIG[$module]['prefix']}functions` (
`def_function` ,
`def_option_type`,
`def_option` ,
`def_type` ,
`def_multiple_values` ,
`def_clause_support`,
`def_dropdown`
)
VALUES 
('options', 'ratelimit', 'referrals-per-second', '( integer )', 'no', 'OV', 'no'),
('options', 'ratelimit', 'nodata-per-second', '( integer )', 'no', 'OV', 'no'),
('options', 'ratelimit', 'nxdomains-per-second', '( integer )', 'no', 'OV', 'no'),
('options', 'ratelimit', 'errors-per-second', '( integer )', 'no', 'OV', 'no'),
('options', 'ratelimit', 'all-per-second', '( integer )', 'no', 'OV', 'no'),
('options', 'ratelimit', 'window', '( integer )', 'no', 'OV', 'no'),
('options', 'ratelimit', 'log-only', '( yes | no )', 'no', 'OV', 'yes'),
('options', 'ratelimit', 'qps-scale', '( integer )', 'no', 'OV', 'no'),
('options', 'ratelimit', 'ipv4-prefix-length', '( integer )', 'no', 'OV', 'no'),
('options', 'ratelimit', 'ipv6-prefix-length', '( integer )', 'no', 'OV', 'no'),
('options', 'ratelimit', 'slip', '( integer )', 'no', 'OV', 'no'),
('options', 'ratelimit', 'exempt-clients', '( address_match_element )', 'yes', 'OV', 'no'),
('options', 'ratelimit', 'max-table-size', '( integer )', 'no', 'OV', 'no'),
('options', 'ratelimit', 'min-table-size', '( integer )', 'no', 'OV', 'no')
;
INSERT;

	$inserts[] = <<<INSERT
INSERT IGNORE INTO  $database.`fm_{$__FM_CONFIG[$module]['prefix']}functions` (
`def_function` ,
`def_option_type`,
`def_option` ,
`def_type` ,
`def_multiple_values` ,
`def_clause_support`,
`def_dropdown`,
`def_max_parameters`
)
VALUES 
('options', 'ratelimit', 'responses-per-second', '( [size integer] [ratio fixedpoint] integer )', 'no', 'OV', 'no', '5')
;
INSERT;

	
	/** fm_options inserts */
	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_options` (option_name, option_value, module_name) 
	SELECT 'version', '{$__FM_CONFIG[$module]['version']}', '$module' FROM DUAL
WHERE NOT EXISTS
	(SELECT option_name FROM $database.`fm_options` WHERE option_name = 'version'
		AND module_name='$module');
INSERT;
	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_options` (option_name, option_value, module_name) 
	SELECT 'client_version', '{$__FM_CONFIG[$module]['client_version']}', '$module' FROM DUAL
WHERE NOT EXISTS
	(SELECT option_name FROM $database.`fm_options` WHERE option_name = 'client_version'
		AND module_name='$module');
INSERT;

	/** localhost domain and records */
	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}domains` (`domain_name`, `domain_mapping`) VALUES 
	('localhost', 'forward'), 
	('0.0.127.in-addr.arpa', 'reverse'),
	('0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.ip6.arpa', 'reverse');
INSERT;
	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}records` (`domain_id`, `record_name`, `record_value`, `record_ttl`, `record_type`, `record_append`) VALUES 
	(1, '@', '127.0.0.1', '', 'A', 'yes'),
	(1, '@', '::1', '', 'AAAA', 'yes'),
	(1, '@', '@', '', 'NS', 'no'),
	(2, '1', 'localhost.', '', 'PTR', 'yes'),
	(2, '@', 'localhost.', '', 'NS', 'no'),
	(3, '1', 'localhost.', '', 'PTR', 'yes'),
	(3, '@', 'localhost.', '', 'NS', 'no');
INSERT;

	/** Update user capabilities */
	$fm_user_caps = null;
	if ($link) {
		$fm_user_caps_query = "SELECT option_value FROM $database.`fm_options` WHERE option_name='fm_user_caps';";
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
			'build_server_configs'	=> 'Build Server Configs',
			'manage_zones'			=> 'Zone Management',
			'manage_records'		=> 'Record Management',
			'reload_zones'			=> 'Reload Zones',
			'manage_settings'		=> 'Manage Settings'
		);
	$fm_user_caps = serialize($fm_user_caps);
	
	if ($insert) {
		$inserts[] = "INSERT INTO $database.`fm_options` (option_name, option_value) VALUES ('fm_user_caps', '$fm_user_caps');";
	} else {
		$inserts[] = "UPDATE $database.`fm_options` SET option_value='$fm_user_caps' WHERE option_name='fm_user_caps';";
	}


	/** Create table schema */
	foreach ($table as $schema) {
		if ($link) {
			$result = mysql_query($schema, $link);
			if (mysql_error($link)) {
				return mysql_error($link);
			}
		} else {
			global $fmdb;
			$result = $fmdb->query($schema);
			if ($fmdb->last_error) {
				return $fmdb->last_error;
			}
		}
	}

	/** Insert site values if not already present */
	foreach ($inserts as $query) {
		if ($link) {
			$result = mysql_query($query, $link);
			if (mysql_error($link)) {
				return mysql_error($link);
			}
		} else {
			$result = $fmdb->query($query);
			if ($fmdb->last_error) {
				return $fmdb->last_error;
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