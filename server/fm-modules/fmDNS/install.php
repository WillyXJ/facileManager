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

function installfmDNSSchema($database, $module, $noisy = 'noisy') {
	global $fmdb;
	
	/** Include module variables */
	@include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'variables.inc.php');
	
	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}acls` (
  `acl_id` INT(11) NOT NULL AUTO_INCREMENT ,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `server_serial_no` varchar(255) NOT NULL DEFAULT '0',
  `acl_parent_id` INT NOT NULL DEFAULT '0',
  `acl_name` VARCHAR(255) NULL DEFAULT NULL,
  `acl_addresses` TEXT NULL DEFAULT NULL,
  `acl_comment` text,
  `acl_status` ENUM( 'active',  'disabled',  'deleted') NOT NULL DEFAULT  'active',
  PRIMARY KEY (`acl_id`)
) ENGINE = MYISAM DEFAULT CHARSET=utf8;
TABLE;
	
	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}config` (
  `cfg_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `server_serial_no` varchar(255) NOT NULL DEFAULT '0',
  `cfg_type` varchar(255) NOT NULL DEFAULT 'global',
  `view_id` int(11) NOT NULL DEFAULT '0',
  `domain_id` int(11) NOT NULL DEFAULT '0',
  `cfg_isparent` enum('yes','no') NOT NULL DEFAULT 'no',
  `cfg_parent` int(11) NOT NULL DEFAULT '0',
  `cfg_name` varchar(50) NOT NULL,
  `cfg_data` text NOT NULL,
  `cfg_in_clause` enum('yes','no') NOT NULL DEFAULT 'yes',
  `cfg_comment` text,
  `cfg_status` enum('hidden','active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`cfg_id`),
  KEY `idx_domain_id` (`domain_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}controls` (
  `control_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `server_serial_no` varchar(255) NOT NULL DEFAULT '0',
  `control_type` enum('controls','statistics') NOT NULL DEFAULT 'controls',
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
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}domains` (
  `domain_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `domain_template` ENUM('yes','no') NOT NULL DEFAULT 'no',
  `domain_default` ENUM('yes','no') NOT NULL DEFAULT 'no',
  `domain_template_id` INT(11) NOT NULL DEFAULT '0',
  `soa_id` int(11) NOT NULL DEFAULT '0',
  `soa_serial_no` INT(2) NOT NULL DEFAULT  '0',
  `soa_serial_no_previous` INT(2) NOT NULL DEFAULT '0',
  `domain_name` varchar(255) NOT NULL DEFAULT '',
  `domain_name_servers` varchar(255) NOT NULL DEFAULT '0',
  `domain_view` varchar(255) NOT NULL DEFAULT '0',
  `domain_mapping` enum('forward','reverse') NOT NULL DEFAULT 'forward',
  `domain_type` enum('master','slave','forward','stub') NOT NULL DEFAULT 'master',
  `domain_clone_domain_id` int(11) NOT NULL DEFAULT '0',
  `domain_clone_dname` ENUM('yes','no') NULL DEFAULT NULL,
  `domain_dynamic` ENUM('yes','no') NOT NULL DEFAULT 'no',
  `domain_dnssec` enum('yes','no') NOT NULL DEFAULT 'no',
  `domain_dnssec_generate_ds` enum('yes','no') NOT NULL DEFAULT 'no',
  `domain_dnssec_ds_rr` text,
  `domain_dnssec_parent_domain_id` int(11) NOT NULL DEFAULT '0',
  `domain_dnssec_sig_expire` int(2) NOT NULL DEFAULT '0',
  `domain_dnssec_signed` INT(2) NOT NULL DEFAULT '0',
  `domain_reload` enum('yes','no') NOT NULL DEFAULT 'no',
  `domain_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`domain_id`),
  KEY `idx_domain_status` (`domain_status`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}functions` (
  `def_id` int(11) NOT NULL AUTO_INCREMENT,
  `def_function` enum('options','logging','key','view') NOT NULL,
  `def_option_type` enum('global','ratelimit','rrset') NOT NULL DEFAULT 'global',
  `def_option` varchar(255) NOT NULL,
  `def_type` varchar(200) NOT NULL,
  `def_multiple_values` enum('yes','no') NOT NULL DEFAULT 'no',
  `def_clause_support` varchar(10) NOT NULL DEFAULT 'O',
  `def_zone_support` VARCHAR(10) NULL DEFAULT NULL,
  `def_dropdown` enum('yes','no') NOT NULL DEFAULT 'no',
  `def_max_parameters` int(3) NOT NULL DEFAULT '1',
  `def_minimum_version` VARCHAR(20) NULL,
  PRIMARY KEY (`def_id`),
  KEY `idx_def_option` (`def_option`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}keys` (
  `key_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `domain_id` INT(11) NULL DEFAULT NULL,
  `key_type` enum('tsig','dnssec') NOT NULL DEFAULT 'tsig',
  `key_subtype` ENUM('ZSK','KSK') NULL,
  `key_name` varchar(255) NOT NULL,
  `key_algorithm` enum('hmac-md5','hmac-sha1','hmac-sha224','hmac-sha256','hmac-sha384','hmac-sha512','rsamd5','rsasha1','dsa','nsec3rsasha1','nsec3dsa','rsasha256','rsasha512','eccgost','ecdsap256sha256','ecdsap384sha384') NOT NULL DEFAULT 'hmac-md5',
  `key_secret` TEXT NOT NULL,
  `key_public` text,
  `key_size` INT(2) NULL,
  `key_created` INT(2) NULL,
  `key_view` int(11) NOT NULL DEFAULT '0',
  `key_comment` text,
  `key_signing` enum('yes','no') NOT NULL DEFAULT 'no',
  `key_status` enum('active','disabled','revoked','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`key_id`)
) ENGINE = MYISAM DEFAULT CHARSET=utf8;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}records` (
  `record_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `domain_id` int(11) NOT NULL DEFAULT '0',
  `record_ptr_id` int(11) NOT NULL DEFAULT '0',
  `record_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `record_name` varchar(255) DEFAULT '@',
  `record_value` text,
  `record_ttl` varchar(50) NOT NULL DEFAULT '',
  `record_class` enum('IN','CH','HS') NOT NULL DEFAULT 'IN',
  `record_type` enum('A','AAAA','CAA','CERT','CNAME','DHCID','DLV','DNAME','DNSKEY','DS','KEY','KX','MX','NS','PTR','RP','SRV','TXT','HINFO','SSHFP','NAPTR') NOT NULL DEFAULT 'A',
  `record_priority` int(4) DEFAULT NULL,
  `record_weight` int(4) DEFAULT NULL,
  `record_port` int(4) DEFAULT NULL,
  `record_params` varchar(255) DEFAULT NULL,
  `record_regex` varchar(255) DEFAULT NULL,
  `record_os` varchar(255) DEFAULT NULL,
  `record_cert_type` tinyint(4) DEFAULT NULL,
  `record_key_tag` int(11) DEFAULT NULL,
  `record_algorithm` tinyint(4) DEFAULT NULL,
  `record_flags` enum('0','256','257','','U','S','A','P') DEFAULT NULL,
  `record_text` varchar(255) DEFAULT NULL,
  `record_comment` varchar(200) DEFAULT NULL,
  `record_append` enum('yes','no') NOT NULL DEFAULT 'yes',
  `record_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`record_id`),
  KEY `idx_domain_id` (`domain_id`),
  KEY `idx_record_status` (`record_status`),
  KEY `idx_record_type` (`record_type`)
) ENGINE=INNODB DEFAULT CHARSET=utf8 ;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}records_skipped` (
  `skip_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `domain_id` int(11) NOT NULL,
  `record_id` int(11) NOT NULL,
  `record_status` enum('active','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`skip_id`),
  KEY `idx_record_id` (`record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}servers` (
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
  UNIQUE KEY `idx_server_serial_no` (`server_serial_no`)
) ENGINE = MYISAM  DEFAULT CHARSET=utf8;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}server_groups` (
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
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}soa` (
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
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}track_builds` (
  `domain_id` int(11) NOT NULL,
  `server_serial_no` int(11) NOT NULL
) ENGINE=INNODB DEFAULT CHARSET=utf8;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}track_reloads` (
  `domain_id` int(11) NOT NULL,
  `server_serial_no` int(11) NOT NULL
) ENGINE = INNODB DEFAULT CHARSET=utf8;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}views` (
  `view_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `server_serial_no` varchar(255) NOT NULL DEFAULT '0',
  `view_order_id` int(11) NOT NULL,
  `view_name` VARCHAR(255) NOT NULL ,
  `view_comment` text,
  `view_status` ENUM( 'active',  'disabled',  'deleted') NOT NULL DEFAULT  'active'
) ENGINE = MYISAM DEFAULT CHARSET=utf8;
TABLE;


	/** fm_prefix_config inserts */
	$inserts[] = <<<INSERT
INSERT INTO `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '0', '0', 'directory', '\$ROOT', 'hidden' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}config` WHERE account_id = '0');
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'version', 'none', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'version' AND server_serial_no = '0'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'hostname', 'none', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'hostname' AND server_serial_no = '0'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'recursion', 'no', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'recursion' AND server_serial_no = '0'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'statistics-file', '"\$ROOT/named.stats"', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'statistics-file' AND server_serial_no = '0'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'zone-statistics', 'yes', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'zone-statistics' AND server_serial_no = '0'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'pid-file', '"\$ROOT/named.pid"', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'pid-file' AND server_serial_no = '0'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'dump-file', '"\$ROOT/named.dump"', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'dump-file' AND server_serial_no = '0'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'auth-nxdomain', 'no', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'auth-nxdomain' AND server_serial_no = '0'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'cleaning-interval', '120', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'cleaning-interval' AND server_serial_no = '0'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'interface-interval', '0', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'interface-interval' AND server_serial_no = '0'
	);
INSERT;
	
	
	
	/** fm_prefix_functions inserts*/
	$inserts[] = <<<INSERT
INSERT IGNORE INTO  `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}functions` (
`def_function` ,
`def_option` ,
`def_type` ,
`def_multiple_values` ,
`def_clause_support`,
`def_zone_support`,
`def_dropdown`,
`def_minimum_version`
)
VALUES 
('key', 'algorithm', 'string', 'no', 'K', NULL, 'no', NULL),
('key', 'secret', 'quoted_string', 'no', 'K', NULL, 'no', NULL),
('options', 'acache-cleaning-interval', '( minutes )', 'no', 'OV', NULL, 'no', NULL),
('options', 'acache-enable', '( yes | no )', 'no', 'OV', NULL, 'yes', NULL),
('options', 'additional-from-auth', '( yes | no )', 'no', 'OV', NULL, 'yes', NULL),
('options', 'additional-from-cache', '( yes | no )', 'no', 'OV', NULL, 'yes', NULL),
('options', 'allow-new-zones', '( yes | no )', 'no', 'O', NULL, 'yes', NULL),
('options', 'allow-notify', '( address_match_element )', 'yes', 'OVZ', 'S', 'no', NULL),
('options', 'allow-query', '( address_match_element )', 'yes', 'OVZ', 'MS', 'no', NULL),
('options', 'allow-query-cache', '( address_match_element )', 'yes', 'OV', NULL, 'no', NULL),
('options', 'allow-query-cache-on', '( address_match_element )', 'yes', 'OV', NULL, 'no', NULL),
('options', 'allow-query-on', '( address_match_element )', 'yes', 'OVZ', 'MS', 'no', NULL),
('options', 'allow-recursion', '( address_match_element )', 'yes', 'OV', NULL, 'no', NULL),
('options', 'allow-recursion-on', '( address_match_element )', 'yes', 'OV', NULL, 'no', NULL),
('options', 'allow-transfer', '( address_match_element )', 'yes', 'OVZ', 'MS', 'no', NULL),
('options', 'allow-update', '( address_match_element )', 'yes', 'OVZ', 'MS', 'no', NULL),
('options', 'allow-update-forwarding', '( address_match_element )', 'yes', 'OVZ', 'MS', 'no', NULL),
('options', 'allow-v6-synthesis', '( address_match_element )', 'yes', 'O', NULL, 'no', NULL),
('options', 'also-notify', '( ipv4_address | ipv6_address ) [ port ( ip_port | * ) ]', 'yes', 'OVZ', 'M', 'no', NULL),
('options', 'alt-transfer-source', '( ipv4_address | * ) [ port ( ip_port | * ) ]', 'no', 'OVZ', 'S', 'no', NULL),
('options', 'alt-transfer-source-v6', '( ipv6_address | * ) [ port ( ip_port | * ) ]', 'no', 'OVZ', 'S', 'no', NULL),
('options', 'attach-cache', '( quoted_string )', 'no', 'OV', NULL, 'no', NULL),
('options', 'auth-nxdomain', '( yes | no )', 'no', 'OV', NULL, 'yes', NULL),
('options', 'auto-dnssec', '( allow | maintain | off )', 'no', 'OVZ', 'MS', 'yes', '9.7.0'),
('options', 'automatic-interface-scan', '( yes | no )', 'no', 'O', NULL, 'yes', '9.10.0'),
('options', 'avoid-v4-udp-ports', '( range ip_port ip_port )', 'yes', 'O', NULL, 'no', NULL),
('options', 'avoid-v6-udp-ports', '( range ip_port ip_port )', 'yes', 'O', NULL, 'no', NULL),
('options', 'bindkeys-file', '( quoted_string )', 'no', 'O', NULL, 'no', NULL),
('options', 'blackhole', '( address_match_element )', 'yes', 'O', NULL, 'no', NULL),
('options', 'bogus', '( yes | no )', 'no', 'S', NULL, 'yes', NULL),
('options', 'check-dup-records', '( fail | warn | ignore )', 'no', 'OVZ', 'MS', 'yes', NULL),
('options', 'check-integrity', '( yes | no )', 'no', 'OVZ', 'MS', 'yes', NULL),
('options', 'check-mx', '( fail | warn | ignore )', 'no', 'OVZ', 'MS', 'yes', NULL),
('options', 'check-mx-cname', '( fail | warn | ignore )', 'no', 'OVZ', 'MS', 'yes', NULL),
('options', 'check-names', '( warn | fail | ignore )', 'no', 'Z', 'MS', 'yes', NULL),
('options', 'check-names', '( master | slave | response ) ( warn | fail | ignore )', 'no', 'O', NULL, 'yes', NULL),
('options', 'check-sibling', '( yes | no )', 'no', 'OVZ', 'MS', 'yes', NULL),
('options', 'check-spf', '( warn | ignore )', 'no', 'OVZ', 'M', 'yes', NULL),
('options', 'check-srv-cname', '( fail | warn | ignore )', 'no', 'OVZ', 'MS', 'yes', NULL),
('options', 'check-wildcard', '( yes | no )', 'no', 'OVZ', 'MS', 'yes', NULL),
('options', 'clients-per-query', '( integer )', 'no', 'OV', NULL, 'no', NULL),
('options', 'cookie-algorithm', '( aes | sha1 | sha256 )', 'no', 'O', NULL, 'yes', '9.11.0'),
('options', 'cookie-secret', '( quoted_string )', 'no', 'O', NULL, 'no', '9.11.0'),
('options', 'coresize', '( size_spec )', 'no', 'O', NULL, 'no', NULL),
('options', 'database', '( quoted_string )', 'no', 'Z', 'MS', 'no', NULL),
('options', 'datasize', '( size_spec )', 'no', 'O', NULL, 'no', NULL),
('options', 'delegation-only', '( yes | no )', 'no', 'Z', 'MS', 'yes', NULL),
('options', 'deny-answer-addresses', '( address_match_element ) [ except-from { ( address_match_element ) } ]', 'yes', 'OV', NULL, 'no', NULL),
('options', 'deny-answer-aliases', '( quoted_string ) [ except-from { ( quoted_string ) } ]', 'yes', 'OV', NULL, 'no', NULL),
('options', 'dialup', '( yes | no | notify | refresh | passive | notify-passive )', 'no', 'OVZ', 'MS', 'yes', NULL),
('options', 'dnssec-accept-expired', '( yes | no )', 'no', 'OV', NULL, 'yes', NULL),
('options', 'dnssec-dnskey-kskonly', '( yes | no )', 'no', 'OVZ', 'MS', 'yes', NULL),
('options', 'dnssec-enable', '( yes | no )', 'no', 'OV', NULL, 'yes', NULL),
('options', 'dnssec-loadkeys-interval', '( minutes )', 'no', 'OZ', 'MS', 'no', NULL),
('options', 'dnssec-lookaside', '( auto | no | domain trust-anchor domain )', 'no', 'OV', NULL, 'no', NULL),
('options', 'dnssec-must-be-secure', 'domain ( yes | no )', 'no', 'OV', NULL, 'no', NULL),
('options', 'dnssec-secure-to-insecure', '( yes | no )', 'no', 'OVZ', 'MS', 'yes', NULL),
('options', 'dnssec-update-mode', '( maintain | no-resign )', 'no', 'OZ', 'MS', 'yes', '9.9.0'),
('options', 'dnssec-validation', '( yes | no | auto )', 'no', 'OV', NULL, 'yes', NULL),
('options', 'dnstap', '( auth | auth response | auth query | client | client response | client query | forwarder | forward response | forwarder query | resolver | resolver response | resolver query )', 'yes', 'OV', NULL, 'yes', '9.11.0'),
('options', 'dnstap-identity', '( quoted_string | hostname | none )', 'no', 'O', NULL, 'no', '9.11.0'),
('options', 'dnstap-output', '( file | unix ) ( quoted_string )', 'no', 'O', NULL, 'no', '9.11.0'),
('options', 'dnstap-version', '( quoted_string | none )', 'no', 'O', NULL, 'no', '9.11.0'),
('options', 'dscp', '( integer )', 'no', 'O', NULL, 'no', '9.10.0'),
('options', 'dual-stack-servers', '( quoted_string )', 'yes', 'OV', NULL, 'no', NULL),
('options', 'dump-file', '( quoted_string )', 'no', 'O', NULL, 'no', NULL),
('options', 'edns', '( yes | no )', 'no', 'S', NULL, 'yes', NULL),
('options', 'edns-version', '( integer )', 'no', 'S', NULL, 'no', '9.11.0'),
('options', 'edns-udp-size', '( size_spec )', 'no', 'OSV', NULL, 'no', NULL),
('options', 'empty-contact', '( string )', 'no', 'OV', NULL, 'no', NULL),
('options', 'empty-server', '( string )', 'no', 'OV', NULL, 'no', NULL),
('options', 'empty-zones-enable', '( yes | no )', 'no', 'OV', NULL, 'yes', NULL),
('options', 'fetch-quota-params', '( integer fixedpoint fixedpoint fixedpoint )', 'no', 'O', NULL, 'no', NULL),
('options', 'fetches-per-server', '( integer [ ( drop | fail ) ] )', 'no', 'O', NULL, 'no', NULL),
('options', 'fetches-per-zone', '( integer [ ( drop | fail ) ] )', 'no', 'O', NULL, 'no', NULL),
('options', 'files', '( size_spec )', 'no', 'O', NULL, 'no', NULL),
('options', 'filter-aaaa', '( address_match_element )', 'yes', 'O', NULL, 'no', NULL),
('options', 'filter-aaaa-on-v4', '( yes | no | break-dnssec )', 'no', 'O', NULL, 'yes', NULL),
('options', 'filter-aaaa-on-v6', '( yes | no | break-dnssec )', 'no', 'O', NULL, 'yes', NULL),
('options', 'flush-zones-on-shutdown', '( yes | no )', 'no', 'O', NULL, 'yes', NULL),
('options', 'forward', '( first | only )', 'no', 'OVZ', NULL, 'yes', NULL),
('options', 'forwarders', '[ port ( ip_port | * ) ] { ( ipv4_address | ipv6_address ) } [ port ( ip_port | * ) ]', 'yes', 'OVZ', 'F', 'no', NULL),
('options', 'geoip-directory', '( quoted_string )', 'no', 'O', NULL, 'no', '9.10.0'),
('options', 'geoip-use-ecs', '( yes | no )', 'no', 'O', NULL, 'yes', '9.11.0'),
('options', 'heartbeat-interval', '( minutes )', 'no', 'O', NULL, 'no', NULL),
('options', 'hostname', '( quoted_string | none )', 'no', 'O', NULL, 'no', NULL),
('options', 'inline-signing', '( yes | no )', 'no', 'Z', 'MS', 'yes', '9.9.0'),
('options', 'interface-interval', '( minutes )', 'no', 'O', NULL, 'no', NULL),
('options', 'ixfr-from-differences', '( yes | no )', 'no', 'OVZ', 'MS', 'yes', NULL),
('options', 'journal', '( quoted_string )', 'no', 'Z', 'MS', 'no', NULL),
('options', 'keep-response-order', '( address_match_element )', 'yes', 'O', NULL, 'no', '9.11.0'),
('options', 'key-directory', '( quoted_string )', 'no', 'OVZ', 'MS', 'no', NULL),
('options', 'keys', '( key_id )', 'no', 'S', NULL, 'no', NULL),
('options', 'lame-ttl', '( seconds )', 'no', 'OV', NULL, 'no', NULL),
('options', 'lock-file', '( quoted_string | none )', 'no', 'O', NULL, 'no', '9.11.0'),
('options', 'lwres-clients', '( integer )', 'no', 'R', NULL, 'no', '9.11.0'),
('options', 'lwres-tasks', '( integer )', 'no', 'R', NULL, 'no', '9.11.0'),
('options', 'managed-keys-directory', '( quoted_string )', 'no', 'O', NULL, 'no', NULL),
('options', 'masterfile-format', '( text | raw | map )', 'no', 'OVZ', 'MS', 'yes', NULL),
('options', 'masterfile-style', '( relative | full )', 'no', 'O', NULL, 'no', '9.11.0'),
('options', 'masters', '( { ipv4_address | ipv6_address } )', 'yes', 'OVZ', 'S', 'no', NULL),
('options', 'match-clients', '( address_match_element )', 'yes', 'V', NULL, 'no', NULL),
('options', 'match-destinations', '( address_match_element )', 'yes', 'V', NULL, 'no', NULL),
('options', 'match-mapped-addresses', '( yes | no )', 'no', 'O', NULL, 'yes', NULL),
('options', 'match-recursive-only', '( yes | no )', 'no', 'V', NULL, 'yes', NULL),
('options', 'max-acache-size', '( size_spec )', 'no', 'OV', NULL, 'no', NULL),
('options', 'max-cache-size', '( size_spec )', 'no', 'OV', NULL, 'no', NULL),
('options', 'max-cache-ttl', '( seconds )', 'no', 'OV', NULL, 'no', NULL),
('options', 'max-clients-per-query', '( integer )', 'no', 'OV', NULL, 'no', NULL),
('options', 'max-journal-size', '( size_spec )', 'no', 'OVZ', 'MS', 'no', NULL),
('options', 'max-ncache-ttl', '( seconds )', 'no', 'OV', NULL, 'no', NULL),
('options', 'max-recursion-depth', '( integer )', 'no', 'O', NULL, 'no', NULL),
('options', 'max-recursion-queries', '( integer )', 'no', 'O', NULL, 'no', NULL),
('options', 'max-refresh-time', '( seconds )', 'no', 'OVZ', 'S', 'no', NULL),
('options', 'max-retry-time', '( seconds )', 'no', 'OVZ', 'S', 'no', NULL),
('options', 'max-rsa-exponent-size', '( integer )', 'no', 'O', NULL, 'no', NULL),
('options', 'max-transfer-idle-in', '( minutes )', 'no', 'OVZ', 'S', 'no', NULL),
('options', 'max-transfer-idle-out', '( minutes )', 'no', 'OVZ', 'M', 'no', NULL),
('options', 'max-transfer-time-in', '( minutes )', 'no', 'OVZ', 'S', 'no', NULL),
('options', 'max-transfer-time-out', '( minutes )', 'no', 'OVZ', 'M', 'no', NULL),
('options', 'max-udp-size', '( size_spec )', 'no', 'OSV', NULL, 'no', NULL),
('options', 'max-zone-ttl', '( unlimited | integer )', 'no', 'O', NULL, 'no', '9.11.0'),
('options', 'memstatistics', '( yes | no )', 'no', 'O', NULL, 'yes', NULL),
('options', 'memstatistics-file', '( quoted_string )', 'no', 'O', NULL, 'no', NULL),
('options', 'message-compression', '( yes | no )', 'no', 'O', NULL, 'yes', '9.11.0'),
('options', 'min-refresh-time', '( seconds )', 'no', 'OVZ', 'S', 'no', NULL),
('options', 'min-retry-time', '( seconds )', 'no', 'OVZ', 'S', 'no', NULL),
('options', 'minimal-any', '( yes | no )', 'no', 'O', NULL, 'yes', '9.11.0'),
('options', 'minimal-responses', '( yes | no )', 'no', 'OV', NULL, 'yes', NULL),
('options', 'multi-master', '( yes | no )', 'no', 'OVZ', 'S', 'yes', NULL),
('options', 'no-case-compress', '( address_match_element )', 'yes', 'O', NULL, 'no', NULL),
('options', 'nocookie-udp-size', '( integer )', 'no', 'O', NULL, 'no', '9.11.0'),
('options', 'ndots', '( integer )', 'no', 'R', NULL, 'no', NULL),
('options', 'notify', '( yes | no | explicit | master-only )', 'no', 'OVZ', 'MS', 'yes', NULL),
('options', 'notify-delay', '( seconds )', 'no', 'OVZ', 'MS', 'no', NULL),
('options', 'notify-rate', '( integer )', 'no', 'O', NULL, 'no', '9.11.0'),
('options', 'notify-source', '( ipv4_address | * ) [ port ( ip_port | * ) ]', 'no', 'OSVZ', 'M', 'no', NULL),
('options', 'notify-source-v6', '( ipv6_address | * ) [ port ( ip_port | * ) ]', 'no', 'OSVZ', 'M', 'no', NULL),
('options', 'notify-to-soa', '( yes | no )', 'no', 'OVZ', 'MS', 'yes', NULL),
('options', 'nta-lifetime', '( duration )', 'no', 'O', NULL, 'no', '9.11.0'),
('options', 'nta-recheck', '( duration )', 'no', 'O', NULL, 'no', '9.11.0'),
('options', 'pid-file', '( quoted_string | none )', 'no', 'O', NULL, 'no', NULL),
('options', 'port', '( ip_port )', 'no', 'O', NULL, 'no', NULL),
('options', 'preferred-glue', '( A | AAAA )', 'no', 'OV', NULL, 'yes', NULL),
('options', 'prefetch', '( integer [integer] )', 'no', 'O', NULL, 'no', '9.10.0'),
('options', 'provide-ixfr', '( yes | no )', 'no', 'OSV', 'M', 'yes', NULL),
('options', 'query-source', 'address ( ipv4_address | * ) [ port ( ip_port | * ) ]', 'no', 'OSVZ', 'MS', 'no', NULL),
('options', 'query-source-v6', 'address ( ipv6_address | * ) [ port ( ip_port | * ) ]', 'no', 'OSVZ', 'MS', 'no', NULL),
('options', 'querylog', '( yes | no )', 'no', 'O', NULL, 'yes', NULL),
('options', 'queryport-pool-ports', '( integer )', 'no', 'S', NULL, 'no', NULL),
('options', 'queryport-pool-updateinterval', '( integer )', 'no', 'S', NULL, 'no', NULL),
('options', 'random-device', '( quoted_string )', 'no', 'O', NULL, 'no', NULL),
('options', 'recursing-file', '( quoted_string )', 'no', 'O', NULL, 'no', NULL),
('options', 'recursion', '( yes | no )', 'no', 'OV', NULL, 'yes', NULL),
('options', 'recursive-clients', '( integer )', 'no', 'O', NULL, 'no', NULL),
('options', 'request-ixfr', '( yes | no )', 'no', 'OSVZ', 'S', 'yes', NULL),
('options', 'request-nsid', '( yes | no )', 'no', 'OSV', NULL, 'yes', NULL),
('options', 'request-expire', '( yes | no )', 'no', 'OS', NULL, 'yes', '9.11.0'),
('options', 'require-server-cookie', '( yes | no )', 'no', 'O', NULL, 'yes', '9.11.0'),
('options', 'reserved-sockets', '( integer )', 'no', 'O', NULL, 'no', NULL),
('options', 'resolver-query-timeout', '( integer )', 'no', 'O', NULL, 'no', NULL),
('options', 'response-policy', '( string )', 'no', 'O', NULL, 'no', NULL),
('options', 'root-delegation-only exclude', '( quoted_string )', 'yes', 'O', NULL, 'no', NULL),
('options', 'search', '( quoted_string )', 'yes', 'R', NULL, 'no', NULL),
('options', 'secroots-file', '( quoted_string )', 'no', 'O', NULL, 'no', NULL),
('options', 'send-cookie', '( yes | no )', 'no', 'OS', NULL, 'yes', '9.11.0'),
('options', 'serial-query-rate', '( integer )', 'no', 'O', NULL, 'no', NULL),
('options', 'serial-update-method', '( increment | unixtime | date )', 'no', 'OVZ', 'M', 'yes', '9.9.0'),
('options', 'server-id', '( quoted_string | none | hostname )', 'no', 'O', NULL, 'no', NULL),
('options', 'servfail-ttl', '( seconds )', 'no', 'O', NULL, 'no', '9.11.0'),
('options', 'session-keyfile', '( quoted_string | none )', 'no', 'O', NULL, 'no', NULL),
('options', 'session-keyname', '( quoted_string )', 'no', 'O', NULL, 'no', NULL),
('options', 'session-keyalg', '( hmac-sha1 | hmac-sha224 | hmac-sha256 | hmac-sha384 | hmac-sha512 | hmac-md5 )', 'no', 'O', NULL, 'yes', NULL),
('options', 'sig-signing-nodes', '( integer )', 'no', 'OVZ', 'MS', 'no', NULL),
('options', 'sig-signing-signatures', '( integer )', 'no', 'OVZ', 'MS', 'no', NULL),
('options', 'sig-signing-type', '( integer )', 'no', 'OVZ', 'MS', 'no', NULL),
('options', 'sig-validity-interval', '( days )', 'no', 'OVZ', 'MS', 'no', NULL),
('options', 'sortlist', '( address_match_element )', 'yes', 'OV', NULL, 'no', NULL),
('options', 'stacksize', '( size_spec )', 'no', 'O', NULL, 'no', NULL),
('options', 'startup-notify-rate', '( integer )', 'no', 'O', NULL, 'no', '9.11.0'),
('options', 'statistics-file', '( quoted_string )', 'no', 'O', NULL, 'no', NULL),
('options', 'tcp-clients', '( integer )', 'no', 'O', NULL, 'no', NULL),
('options', 'tcp-listen-queue', '( integer )', 'no', 'O', NULL, 'no', NULL),
('options', 'tcp-only', '( yes | no )', 'no', 'S', NULL, 'yes', '9.11.0'),
('options', 'tkey-dhkey', '( quoted_string integer )', 'no', 'O', NULL, 'no', NULL),
('options', 'tkey-domain', '( quoted_string )', 'no', 'O', NULL, 'no', NULL),
('options', 'tkey-gssapi-credential', '( quoted_string )', 'no', 'O', NULL, 'no', NULL),
('options', 'topology', '( address_match_element )', 'yes', 'O', NULL, 'no', NULL),
('options', 'transfer-format', '( many-answers | one-answer )', 'no', 'OSVZ', 'M', 'yes', NULL),
('options', 'transfer-message-size', '( integer )', 'no', 'O', NULL, 'no', '9.11.0'),
('options', 'transfer-source', '( ipv4_address | * ) [ port ( ip_port | * ) ]', 'no', 'OSVZ', 'S', 'no', NULL),
('options', 'transfer-source-v6', '( ipv6_address | * ) [ port ( ip_port | * ) ]', 'no', 'OSVZ', 'S', 'no', NULL),
('options', 'transfers', '( integer )', 'no', 'S', NULL, 'no', NULL),
('options', 'transfers-in', '( integer )', 'no', 'O', 'S', 'no', NULL),
('options', 'transfers-out', '( integer )', 'no', 'O', 'M', 'no', NULL),
('options', 'transfers-per-ns', '( integer )', 'no', 'O', 'S', 'no', NULL),
('options', 'try-tcp-refresh', '( yes | no )', 'no', 'OVZ', 'S', 'yes', NULL),
('options', 'update-check-ksk', '( yes | no )', 'no', 'OVZ', 'MS', 'yes', NULL),
('options', 'update-policy', '( local | { update-policy-rule } )', 'no', 'Z', 'MS', 'no', NULL),
('options', 'use-alt-transfer-source', '( yes | no )', 'no', 'OVZ', 'MS', 'yes', NULL),
('options', 'use-queryport-pool', '( yes | no )', 'no', 'S', NULL, 'yes', NULL),
('options', 'use-v4-udp-ports', '( range ip_port ip_port )', 'no', 'O', NULL, 'no', NULL),
('options', 'use-v6-udp-ports', '( range ip_port ip_port )', 'no', 'O', NULL, 'no', NULL),
('options', 'v6-bias', '( integer )', 'no', 'O', NULL, 'no', '9.10.0'),
('options', 'view', '( quoted_string )', 'no', 'R', NULL, 'no', NULL),
('options', 'version', '( quoted_string | none )', 'no', 'O', NULL, 'no', NULL),
('options', 'zero-no-soa-ttl', '( yes | no )', 'no', 'OVZ', 'MS', 'yes', NULL),
('options', 'zero-no-soa-ttl-cache', '( yes | no )', 'no', 'OV', NULL, 'yes', NULL),
('options', 'zone-statistics', '( full | terse | none | yes | no )', 'no', 'OVZ', 'MS', 'yes', NULL)
;
INSERT;
	
	$inserts[] = <<<INSERT
INSERT IGNORE INTO  `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}functions` (
`def_function` ,
`def_option_type`,
`def_option` ,
`def_type` ,
`def_multiple_values` ,
`def_clause_support`,
`def_dropdown`,
`def_minimum_version`
)
VALUES 
('options', 'ratelimit', 'referrals-per-second', '( integer )', 'no', 'OVZ', 'no', '9.9.4'),
('options', 'ratelimit', 'nodata-per-second', '( integer )', 'no', 'OVZ', 'no', '9.9.4'),
('options', 'ratelimit', 'nxdomains-per-second', '( integer )', 'no', 'OVZ', 'no', '9.9.4'),
('options', 'ratelimit', 'errors-per-second', '( integer )', 'no', 'OVZ', 'no', '9.9.4'),
('options', 'ratelimit', 'all-per-second', '( integer )', 'no', 'OVZ', 'no', '9.9.4'),
('options', 'ratelimit', 'window', '( integer )', 'no', 'OVZ', 'no', '9.9.4'),
('options', 'ratelimit', 'log-only', '( yes | no )', 'no', 'OVZ', 'yes', '9.9.4'),
('options', 'ratelimit', 'qps-scale', '( integer )', 'no', 'OVZ', 'no', '9.9.4'),
('options', 'ratelimit', 'ipv4-prefix-length', '( integer )', 'no', 'OVZ', 'no', '9.9.4'),
('options', 'ratelimit', 'ipv6-prefix-length', '( integer )', 'no', 'OVZ', 'no', '9.9.4'),
('options', 'ratelimit', 'slip', '( integer )', 'no', 'OVZ', 'no', '9.9.4'),
('options', 'ratelimit', 'exempt-clients', '( address_match_element )', 'yes', 'OVZ', 'no', '9.9.4'),
('options', 'ratelimit', 'max-table-size', '( integer )', 'no', 'OVZ', 'no', '9.9.4'),
('options', 'ratelimit', 'min-table-size', '( integer )', 'no', 'OVZ', 'no', '9.9.4')
;
INSERT;

	$inserts[] = <<<INSERT
INSERT IGNORE INTO  `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}functions` (
`def_function` ,
`def_option_type`,
`def_option` ,
`def_type` ,
`def_multiple_values` ,
`def_clause_support`,
`def_dropdown`,
`def_max_parameters`,
`def_minimum_version`
)
VALUES 
('options', 'ratelimit', 'responses-per-second', '( integer )', 'no', 'OV', 'no', '5', NULL),
('options', 'global', 'include', '( quoted_string )', 'no', 'OVZ', 'no', '-1', NULL),
('options', 'global', 'disable-algorithms', 'domain { algorithm; [ algorithm; ] }', 'no', 'O', 'no', '-1', NULL),
('options', 'global', 'disable-ds-digests', 'domain { digest_type; [ digest_type; ] }', 'no', 'O', 'no', '-1', '9.10.0'),
('options', 'global', 'disable-empty-zone', '( quoted_string )', 'no', 'OV', 'no', '-1', NULL),
('options', 'global', 'listen-on', '[ port ( ip_port | * ) ] { ( ipv4_address ) }', 'yes', 'OR', 'no', '-1', NULL),
('options', 'global', 'listen-on-v6', '[ port ( ip_port | * ) ] { ( ipv6_address ) }', 'yes', 'O', 'no', '-1', NULL),
('options', 'rrset', 'rrset-order', '( rrset_order_spec )', 'no', 'OV', 'no', '-1', NULL)
;
INSERT;

	
	/** fm_options inserts */
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
INSERT INTO `$database`.`fm_options` (option_name, option_value, module_name) 
	SELECT 'clones_use_dnames', '{$__FM_CONFIG[$module]['default']['options']['clones_use_dnames']['default_value']}', '$module' FROM DUAL
WHERE NOT EXISTS
	(SELECT option_name FROM `$database`.`fm_options` WHERE option_name = 'clones_use_dnames'
		AND module_name='$module');
INSERT;

	/** localhost domain and records */
	$inserts[] = <<<INSERT
INSERT INTO `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}domains` (`domain_name`, `domain_mapping`) VALUES 
	('localhost', 'forward'), 
	('0.0.127.in-addr.arpa', 'reverse'),
	('0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.ip6.arpa', 'reverse');
INSERT;
	$inserts[] = <<<INSERT
INSERT INTO `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}records` (`domain_id`, `record_name`, `record_value`, `record_ttl`, `record_type`, `record_append`) VALUES 
	(1, '@', '127.0.0.1', '', 'A', 'yes'),
	(1, '@', '::1', '', 'AAAA', 'yes'),
	(1, '@', '@', '', 'NS', 'no'),
	(2, '1', 'localhost.', '', 'PTR', 'yes'),
	(2, '@', 'localhost.', '', 'NS', 'no'),
	(3, '1', 'localhost.', '', 'PTR', 'yes'),
	(3, '@', 'localhost.', '', 'NS', 'no');
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