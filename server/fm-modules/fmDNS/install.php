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
  `cfg_view` int(11) NOT NULL DEFAULT '0',
  `cfg_isparent` enum('yes','no') NOT NULL DEFAULT 'no',
  `cfg_parent` int(11) NOT NULL DEFAULT '0',
  `cfg_name` varchar(50) NOT NULL,
  `cfg_data` text NOT NULL,
  `cfg_comment` text,
  `cfg_status` enum('hidden','active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`cfg_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG[$module]['prefix']}domains` (
  `domain_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `domain_name` varchar(255) NOT NULL DEFAULT '',
  `domain_name_servers` varchar(255) NOT NULL DEFAULT '0',
  `domain_view` varchar(255) NOT NULL DEFAULT '0',
  `domain_mapping` enum('forward','reverse') NOT NULL DEFAULT 'forward',
  `domain_type` enum('master','slave','forward','stub') NOT NULL DEFAULT 'master',
  `domain_check_names` enum('warn','fail','ignore') DEFAULT NULL,
  `domain_notify_slaves` enum('yes','no') DEFAULT NULL,
  `domain_multi_masters` enum('yes','no') DEFAULT NULL,
  `domain_transfers_from` varchar(255) DEFAULT NULL,
  `domain_updates_from` varchar(255) DEFAULT NULL,
  `domain_clone_domain_id` int(11) NOT NULL DEFAULT '0',
  `domain_master_servers` varchar(255) DEFAULT NULL,
  `domain_forward_servers` varchar(255) DEFAULT NULL,
  `domain_reload` enum('yes','no') NOT NULL DEFAULT 'no',
  `domain_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`domain_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG[$module]['prefix']}functions` (
  `def_function` enum('options','logging','key','view') NOT NULL,
  `def_option` varchar(255) NOT NULL,
  `def_type` varchar(200) NOT NULL,
  `def_multiple_values` enum('yes','no') NOT NULL DEFAULT 'no',
  `def_view_support` enum('yes','no') NOT NULL DEFAULT 'no',
  `def_dropdown` enum('yes','no') NOT NULL DEFAULT 'no',
  UNIQUE KEY `def_option` (`def_option`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG[$module]['prefix']}keys` (
  `key_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `key_name` varchar(255) NOT NULL,
  `key_algorithm` enum('hmac-md5') NOT NULL DEFAULT 'hmac-md5',
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
  `record_type` ENUM( 'A',  'AAAA',  'CERT',  'CNAME',  'DNAME',  'DNSKEY', 'KEY',  'KX',  'MX',  'NS',  'PTR',  'RP',  'SRV',  'TXT', 'HINFO' ) NOT NULL DEFAULT  'A',
  `record_priority` int(4) DEFAULT NULL,
  `record_weight` int(4) DEFAULT NULL,
  `record_port` int(4) DEFAULT NULL,
  `record_os` varchar(255) DEFAULT NULL,
  `record_cert_type` tinyint(4) DEFAULT NULL,
  `record_key_tag` int(11) DEFAULT NULL,
  `record_algorithm` tinyint(4) DEFAULT NULL,
  `record_flags` enum('0','256','257') DEFAULT NULL,
  `record_text` varchar(255) DEFAULT NULL,
  `record_comment` varchar(200) NOT NULL,
  `record_append` enum('yes','no') NOT NULL DEFAULT 'yes',
  `record_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`record_id`),
  KEY `domain_id` (`domain_id`)
) ENGINE=INNODB DEFAULT CHARSET=utf8 ;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG[$module]['prefix']}records_skipped` (
  `account_id` int(11) NOT NULL,
  `domain_id` int(11) NOT NULL,
  `record_id` int(11) NOT NULL,
  `record_status` enum('active','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`record_id`)
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
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG[$module]['prefix']}soa` (
  `soa_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `domain_id` int(11) NOT NULL DEFAULT '0',
  `soa_master_server` varchar(50) NOT NULL DEFAULT '',
  `soa_append` enum('yes','no') NOT NULL DEFAULT 'yes',
  `soa_email_address` varchar(50) NOT NULL DEFAULT '',
  `soa_serial_no` tinyint(2) NOT NULL DEFAULT '10',
  `soa_refresh` varchar(50) DEFAULT '21600',
  `soa_retry` varchar(50) DEFAULT '7200',
  `soa_expire` varchar(50) DEFAULT '604800',
  `soa_ttl` varchar(50) DEFAULT '1200',
  `soa_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`soa_id`),
  KEY `domain_id` (`domain_id`,`soa_master_server`,`soa_email_address`,`soa_refresh`,`soa_retry`,`soa_expire`,`soa_ttl`)
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
`def_view_support`,
`def_dropdown`
)
VALUES 
('key', 'algorithm', 'string', 'no', 'no', 'no'),
('key', 'secret', 'quoted_string', 'no', 'no', 'no'),
('options', 'avoid-v4-udp-ports', '( port )', 'yes', 'no', 'no'), 
('options', 'avoid-v6-udp-ports', '( port )', 'yes', 'no', 'no'),
('options', 'blackhole', '( address_match_element )', 'yes', 'no', 'no'),
('options', 'coresize', '( size_in_bytes )', 'no', 'no', 'no'),
('options', 'datasize', '( size_in_bytes )', 'no', 'no', 'no'),
('options', 'dump-file', '( quoted_string )', 'no', 'no', 'no'),
('options', 'files', '( size_in_bytes )', 'no', 'no', 'no'),
('options', 'heartbeat-interval', '( integer )', 'no', 'no', 'no'),
('options', 'hostname', '( quoted_string | none )', 'no', 'no', 'no'),
('options', 'interface-interval', '( integer )', 'no', 'no', 'no'),
('options', 'listen-on', '( address_match_element )', 'yes', 'no', 'no'),
('options', 'listen-on-v6', '( address_match_element )', 'yes', 'no', 'no'),
('options', 'match-mapped-addresses', '( yes | no )', 'no', 'no', 'yes'),
('options', 'memstatistics-file', '( quoted_string )', 'no', 'no', 'no'),
('options', 'pid-file', '( quoted_string | none )', 'no', 'no', 'no'),
('options', 'port', '( port )', 'no', 'no', 'no'),
('options', 'querylog', '( yes | no )', 'no', 'no', 'yes'),
('options', 'recursing-file', '( quoted_string )', 'no', 'no', 'no'),
('options', 'random-device', '( quoted_string )', 'no', 'no', 'no'),
('options', 'recursive-clients', '( integer )', 'no', 'no', 'no'),
('options', 'serial-query-rate', '( integer )', 'no', 'no', 'no'),
('options', 'server-id', '( quoted_string | none )', 'no', 'no', 'no'),
('options', 'stacksize', '( size_in_bytes )', 'no', 'no', 'no'),
('options', 'statistics-file', '( quoted_string )', 'no', 'no', 'no'),
('options', 'tcp-clients', '( integer )', 'no', 'no', 'no'),
('options', 'tcp-listen-queue', '( integer )', 'no', 'no', 'no'),
('options', 'transfers-per-ns', '( integer )', 'no', 'no', 'no'),
('options', 'transfers-in', '( integer )', 'no', 'no', 'no'),
('options', 'transfers-out', '( integer )', 'no', 'no', 'no'),
('options', 'use-ixfr', '( yes | no )', 'no', 'no', 'yes'),
('options', 'version', '( quoted_string | none )', 'no', 'no', 'no'),

('options', 'allow-recursion', '( address_match_element )', 'yes', 'yes', 'no'),
('options', 'sortlist', '( address_match_element )', 'yes', 'yes', 'no'),
('options', 'auth-nxdomain', '( yes | no )', 'no', 'yes', 'yes'),
('options', 'minimal-responses', '( yes | no )', 'no', 'yes', 'yes'),
('options', 'recursion', '( yes | no )', 'no', 'yes', 'yes'),
('options', 'provide-ixfr', '( yes | no )', 'no', 'yes', 'yes'),
('options', 'request-ixfr', '( yes | no )', 'no', 'yes', 'yes'),
('options', 'additional-from-auth', '( yes | no )', 'no', 'yes', 'yes'),
('options', 'additional-from-cache', '( yes | no )', 'no', 'yes', 'yes'),
('options', 'query-source', 'address ( ipv4_address | * ) [ port ( ip_port | * ) ]', 'no', 'yes', 'no'),
('options', 'query-source-v6', 'address ( ipv6_address | * ) [ port ( ip_port | * ) ]', 'no', 'yes', 'no'),
('options', 'cleaning-interval', '( integer )', 'no', 'yes', 'no'),
('options', 'lame-ttl', '( seconds )', 'no', 'yes', 'no'),
('options', 'max-ncache-ttl', '( seconds )', 'no', 'yes', 'no'),
('options', 'max-cache-ttl', '( seconds )', 'no', 'yes', 'no'),
('options', 'transfer-format', '( many-answers | one-answer )', 'no', 'yes', 'yes'),
('options', 'max-cache-size', '( size_in_bytes )', 'no', 'yes', 'no'),
('options', 'check-names', '( master | slave | response ) ( warn | fail | ignore )', 'no', 'yes', 'yes'),
('options', 'cache-file', '( quoted_string )', 'no', 'yes', 'no'),
('options', 'preferred-glue', '( A | AAAA )', 'no', 'yes', 'yes'),
('options', 'edns-udp-size', '( size_in_bytes )', 'no', 'yes', 'no'),
('options', 'dnssec-enable', '( yes | no )', 'no', 'yes', 'yes'),
('options', 'dnssec-lookaside', 'domain trust-anchor domain', 'no', 'yes', 'no'),
('options', 'dnssec-must-be-secure', 'domain ( yes | no )', 'no', 'yes', 'no'),
('options', 'dialup', '( yes | no | notify | refresh | passive | notify-passive )', 'no', 'yes', 'yes'),
('options', 'ixfr-from-differences', '( yes | no )', 'no', 'yes', 'yes'),
('options', 'allow-query', '( address_match_element )', 'yes', 'yes', 'no'),
('options', 'allow-transfer', '( address_match_element )', 'yes', 'yes', 'no'),
('options', 'allow-update-forwarding', '( address_match_element )', 'yes', 'yes', 'no'),
('options', 'notify', '( yes | no | explicit )', 'no', 'yes', 'yes'),
('options', 'notify-source', '( ipv4_address | * )', 'no', 'yes', 'no'),
('options', 'notify-source-v6', '( ipv6_address | * )', 'no', 'yes', 'no'),
('options', 'also-notify', '( ipv4_address | ipv6_address )', 'yes', 'yes', 'no'),
('options', 'allow-notify', '( address_match_element )', 'yes', 'yes', 'no'),
('options', 'forward', '( first | only )', 'no', 'yes', 'yes'),
('options', 'forwarders', '( ipv4_address | ipv6_address )', 'yes', 'yes', 'no'),
('options', 'max-journal-size', '( size_in_bytes )', 'no', 'yes', 'no'),
('options', 'max-transfer-time-in', '( minutes )', 'no', 'yes', 'no'),
('options', 'max-transfer-time-out', '( minutes )', 'no', 'yes', 'no'),
('options', 'max-transfer-idle-in', '( minutes )', 'no', 'yes', 'no'),
('options', 'max-transfer-idle-out', '( minutes )', 'no', 'yes', 'no'),
('options', 'max-retry-time', '( seconds )', 'no', 'yes', 'no'),
('options', 'min-retry-time', '( seconds )', 'no', 'yes', 'no'),
('options', 'max-refresh-time', '( seconds )', 'no', 'yes', 'no'),
('options', 'min-refresh-time', '( seconds )', 'no', 'yes', 'no'),
('options', 'multi-master', '( yes | no )', 'no', 'yes', 'yes'),
('options', 'sig-validity-interval', '( integer )', 'no', 'yes', 'no'),
('options', 'transfer-source', '( ipv4_address | * )', 'no', 'yes', 'no'),
('options', 'transfer-source-v6', '( ipv6_address | * )', 'no', 'yes', 'no'),
('options', 'alt-transfer-source', '( ipv4_address | * )', 'no', 'yes', 'no'),
('options', 'alt-transfer-source-v6', '( ipv6_address | * )', 'no', 'yes', 'no'),
('options', 'use-alt-transfer-source', '( yes | no )', 'no', 'yes', 'yes'),
('options', 'zone-statistics', '( yes | no )', 'no', 'yes', 'yes'),
('options', 'key-directory', '( quoted_string )', 'no', 'yes', 'no'),
('options', 'match-clients', '( address_match_element )', 'yes', 'yes', 'no'),
('options', 'match-destinations', '( address_match_element )', 'yes', 'yes', 'no'),
('options', 'match-recursive-only', '( yes | no )', 'no', 'yes', 'yes'),
('options', 'dnssec-validation', '( yes | no | auto )', 'no', 'yes', 'yes'),
('options', 'bindkeys-file', '( quoted_string )', 'no', 'yes', 'no')
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
	('0.0.127.in-addr-arpa', 'reverse');
INSERT;
	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}records` (`domain_id`, `record_name`, `record_value`, `record_ttl`, `record_type`, `record_append`) VALUES 
	(1, '@', '127.0.0.1', '', 'A', 'yes'),
	(1, '@', '@', '', 'NS', 'no'),
	(2, '1', 'localhost.', '', 'PTR', 'yes'),
	(2, '@', 'localhost.', '', 'NS', 'no');
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