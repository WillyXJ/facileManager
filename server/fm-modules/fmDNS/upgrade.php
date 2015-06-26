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

function upgradefmDNSSchema($module_name) {
	global $fmdb;
	
	/** Include module variables */
	@include(dirname(__FILE__) . '/variables.inc.php');
	
	/** Get current version */
	$running_version = getOption('version', 0, $module_name);
	
	/** Checks to support older versions (ie n-3 upgrade scenarios */
	$success = version_compare($running_version, '2.1', '<') ? upgradefmDNS_210($__FM_CONFIG, $running_version) : true;
	if (!$success) return $fmdb->last_error;
	
	setOption('client_version', $__FM_CONFIG['fmDNS']['client_version'], 'auto', false, 0, 'fmDNS');
		
	return true;
}

/** 1.0-b5 */
function upgradefmDNS_100($__FM_CONFIG) {
	global $fmdb, $module_name;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` CHANGE  `record_ttl`  `record_ttl` VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT  ''";
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '1.0-beta5', 'auto', false, 0, $module_name);
	
	return true;
}

/** 1.0-b7 */
function upgradefmDNS_101($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	$success = version_compare($running_version, '1.0-b5', '<') ? upgradefmDNS_100($__FM_CONFIG) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` CHANGE  `server_type`  `server_type` ENUM(  'bind9' ) NOT NULL DEFAULT  'bind9',
CHANGE  `server_run_as`  `server_run_as` VARCHAR( 50 ) NULL DEFAULT NULL,
CHANGE  `server_run_as_predefined`  `server_run_as_predefined` ENUM(  'named',  'bind',  'daemon',  'as defined:' ) NOT NULL DEFAULT  'named' ";
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '1.0-beta7', 'auto', false, 0, $module_name);
	
	return true;
}

/** 1.0-b10 */
function upgradefmDNS_102($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	$success = version_compare($running_version, '1.0-b7', '<') ? upgradefmDNS_101($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` CHANGE  `server_run_as_predefined`  `server_run_as_predefined` ENUM(  'named',  'bind',  'daemon',  'root',  'wheel', 'as defined:' ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT  'named'";
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '1.0-beta10', 'auto', false, 0, $module_name);
	
	return true;
}

/** 1.0-b11 */
function upgradefmDNS_103($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	$success = version_compare($running_version, '1.0-b10', '<') ? upgradefmDNS_102($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` ADD  `def_multiple_values` ENUM(  'yes',  'no' ) NOT NULL DEFAULT  'no',
ADD  `def_view_support` ENUM(  'yes',  'no' ) NOT NULL DEFAULT  'no'";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` CHANGE  `def_type`  `def_type` VARCHAR( 200 ) NOT NULL ";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` DROP  `def_id` ";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` ADD UNIQUE (`def_option`)";
	
	$inserts[] = "INSERT IGNORE INTO  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` (
`def_function` ,
`def_option` ,
`def_type` ,
`def_multiple_values` ,
`def_view_support`
)
VALUES 
('options',  'avoid-v4-udp-ports',  '( port )',  'yes',  'no'), 
('options',  'avoid-v6-udp-ports',  '( port )',  'yes',  'no'),
('options',  'blackhole',  '( address_match_element )',  'yes',  'no'),
('options',  'coresize',  '( size_in_bytes )',  'no',  'no'),
('options',  'datasize',  '( size_in_bytes )',  'no',  'no'),
('options',  'dump-file',  '( quoted_string )',  'no',  'no'),
('options',  'files',  '( size_in_bytes )',  'no',  'no'),
('options',  'heartbeat-interval',  '( integer )',  'no',  'no'),
('options',  'hostname',  '( quoted_string | none )',  'no',  'no'),
('options',  'interface-interval',  '( integer )',  'no',  'no'),
('options',  'listen-on',  '( address_match_element )',  'yes',  'no'),
('options',  'listen-on-v6',  '( address_match_element )',  'yes',  'no'),
('options',  'match-mapped-addresses',  '( yes | no )',  'no',  'no'),
('options',  'memstatistics-file',  '( quoted_string )',  'no',  'no'),
('options',  'pid-file',  '( quoted_string | none )',  'no',  'no'),
('options',  'port',  '( integer )',  'no',  'no'),
('options',  'querylog',  '( yes | no )',  'no',  'no'),
('options',  'recursing-file',  '( quoted_string )',  'no',  'no'),
('options',  'random-device',  '( quoted_string )',  'no',  'no'),
('options',  'recursive-clients',  '( integer )',  'no',  'no'),
('options',  'serial-query-rate',  '( integer )',  'no',  'no'),
('options',  'server-id',  '( quoted_string | none )',  'no',  'no'),
('options',  'stacksize',  '( size_in_bytes )',  'no',  'no'),
('options',  'statistics-file',  '( quoted_string )',  'no',  'no'),
('options',  'tcp-clients',  '( integer )',  'no',  'no'),
('options',  'tcp-listen-queue',  '( integer )',  'no',  'no'),
('options',  'transfers-per-ns',  '( integer )',  'no',  'no'),
('options',  'transfers-in',  '( integer )',  'no',  'no'),
('options',  'transfers-out',  '( integer )',  'no',  'no'),
('options',  'use-ixfr',  '( yes | no )',  'no',  'no'),
('options',  'version',  '( quoted_string | none )',  'no',  'no'),

('options',  'allow-recursion',  '( address_match_element )',  'yes',  'yes'),
('options',  'sortlist',  '( address_match_element )',  'yes',  'yes'),
('options',  'auth-nxdomain',  '( yes | no )',  'no',  'yes'),
('options',  'minimal-responses',  '( yes | no )',  'no',  'yes'),
('options',  'recursion',  '( yes | no )',  'no',  'yes'),
('options',  'provide-ixfr',  '( yes | no )',  'no',  'yes'),
('options',  'request-ixfr',  '( yes | no )',  'no',  'yes'),
('options',  'additional-from-auth',  '( yes | no )',  'no',  'yes'),
('options',  'additional-from-cache',  '( yes | no )',  'no',  'yes'),
('options',  'query-source',  'address ( ipv4_address | * ) [ port ( ip_port | * ) ]',  'no',  'yes'),
('options',  'query-source-v6',  'address ( ipv6_address | * ) [ port ( ip_port | * ) ]',  'no',  'yes'),
('options',  'cleaning-interval',  '( integer )',  'no',  'yes'),
('options',  'lame-ttl',  '( seconds )',  'no',  'yes'),
('options',  'max-ncache-ttl',  '( seconds )',  'no',  'yes'),
('options',  'max-cache-ttl',  '( seconds )',  'no',  'yes'),
('options',  'transfer-format',  '( many-answers | one-answer )',  'no',  'yes'),
('options',  'max-cache-size',  '( size_in_bytes )',  'no',  'yes'),
('options',  'check-names',  '( master | slave | response) ( warn | fail | ignore )',  'no',  'yes'),
('options',  'cache-file',  '( quoted_string )',  'no',  'yes'),
('options',  'preferred-glue',  '( A | AAAA )',  'no',  'yes'),
('options',  'edns-udp-size',  '( size_in_bytes )',  'no',  'yes'),
('options',  'dnssec-enable',  '( yes | no )',  'no',  'yes'),
('options',  'dnssec-lookaside',  'domain trust-anchor domain',  'no',  'yes'),
('options',  'dnssec-must-be-secure',  'domain ( yes | no )',  'no',  'yes'),
('options',  'dialup',  '( yes | no | notify | refresh | passive | notify-passive )',  'no',  'yes'),
('options',  'ixfr-from-differences',  '( yes | no )',  'no',  'yes'),
('options',  'allow-query',  '( address_match_element )',  'yes',  'yes'),
('options',  'allow-transfer',  '( address_match_element )',  'yes',  'yes'),
('options',  'allow-update-forwarding',  '( address_match_element )',  'yes',  'yes'),
('options',  'notify',  '( yes | no | explicit )',  'no',  'yes'),
('options',  'notify-source',  '( ipv4_address | * )',  'no',  'yes'),
('options',  'notify-source-v6',  '( ipv6_address | * )',  'no',  'yes'),
('options',  'also-notify',  '( ipv4_address | ipv6_address )',  'yes',  'yes'),
('options',  'allow-notify',  '( address_match_element )',  'yes',  'yes'),
('options',  'forward',  '( first | only )',  'no',  'yes'),
('options',  'forwarders',  '( ipv4_address | ipv6_address )',  'yes',  'yes'),
('options',  'max-journal-size',  '( size_in_bytes )',  'no',  'yes'),
('options',  'max-transfer-time-in',  '( minutes )',  'no',  'yes'),
('options',  'max-transfer-time-out',  '( minutes )',  'no',  'yes'),
('options',  'max-transfer-idle-in',  '( minutes )',  'no',  'yes'),
('options',  'max-transfer-idle-out',  '( minutes )',  'no',  'yes'),
('options',  'max-retry-time',  '( seconds )',  'no',  'yes'),
('options',  'min-retry-time',  '( seconds )',  'no',  'yes'),
('options',  'max-refresh-time',  '( seconds )',  'no',  'yes'),
('options',  'min-refresh-time',  '( seconds )',  'no',  'yes'),
('options',  'multi-master',  '( yes | no )',  'no',  'yes'),
('options',  'sig-validity-interval',  '( integer )',  'no',  'yes'),
('options',  'transfer-source',  '( ipv4_address | * )',  'no',  'yes'),
('options',  'transfer-source-v6',  '( ipv6_address | * )',  'no',  'yes'),
('options',  'alt-transfer-source',  '( ipv4_address | * )',  'no',  'yes'),
('options',  'alt-transfer-source-v6',  '( ipv6_address | * )',  'no',  'yes'),
('options',  'use-alt-transfer-source',  '( yes | no )',  'no',  'yes'),
('options',  'zone-statistics',  '( yes | no )',  'no',  'yes'),
('options',  'key-directory',  '( quoted_string )',  'no',  'yes')
";
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	if (count($inserts) && $inserts[0]) {
		foreach ($inserts as $query) {
			$fmdb->query($query);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '1.0-beta11', 'auto', false, 0, $module_name);
	
	return true;
}

/** 1.0-b13 */
function upgradefmDNS_104($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	$success = version_compare($running_version, '1.0-b11', '<') ? upgradefmDNS_103($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` CHANGE  `record_type`  `record_type` ENUM(  'A',  'AAAA',  'CNAME',  'TXT',  'MX',  'PTR',  'SRV',  'NS' ) NOT NULL DEFAULT  'A' ";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` ENGINE = INNODB";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}soa` ENGINE = INNODB";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}track_builds` ENGINE = INNODB";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}track_reloads` ENGINE = INNODB";
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '1.0-beta13', 'auto', false, 0, $module_name);
	
	return true;
}

/** 1.0-b14 */
function upgradefmDNS_105($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	$success = version_compare($running_version, '1.0-b13', '<') ? upgradefmDNS_104($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` CHANGE  `domain_name`  `domain_name` VARCHAR( 255 ) NOT NULL DEFAULT  ''";
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '1.0-beta14', 'auto', false, 0, $module_name);
	
	return true;
}

/** 1.0-rc2 */
function upgradefmDNS_106($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	$success = version_compare($running_version, '1.0-b14', '<') ? upgradefmDNS_105($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS `fm_{$__FM_CONFIG['fmDNS']['prefix']}options` (
  `option_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `option_name` varchar(255) NOT NULL,
  `option_value` varchar(255) NOT NULL,
  PRIMARY KEY (`option_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;
TABLE;

	$inserts[] = "INSERT IGNORE INTO  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` (
`def_function` ,
`def_option` ,
`def_type` ,
`def_multiple_values` ,
`def_view_support`
)
VALUES 
('options',  'match-clients',  '( address_match_element )',  'yes',  'yes'),
('options',  'match-destinations',  '( address_match_element )',  'yes',  'yes'),
('options',  'match-recursive-only',  '( yes | no )',  'no',  'yes')
";	


	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	if (count($inserts) && $inserts[0]) {
		foreach ($inserts as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '1.0-rc2', 'auto', false, 0, $module_name);
	
	return true;
}

/** 1.0-rc3 */
function upgradefmDNS_107($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	$success = version_compare($running_version, '1.0-rc2', '<') ? upgradefmDNS_106($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` ADD  `server_update_port` INT( 5 ) NOT NULL DEFAULT  '0' AFTER  `server_update_method` ";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` ADD  `server_os` VARCHAR( 50 ) DEFAULT NULL AFTER  `server_name` ";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` CHANGE  `server_run_as`  `server_run_as` VARCHAR( 50 ) NULL ";

	$updates[] = "UPDATE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` SET  `server_update_port` =  '80' WHERE  `server_update_method` = 'http'";
	$updates[] = "UPDATE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` SET  `server_update_port` =  '443' WHERE  `server_update_method` = 'https'";


	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	if (count($updates) && $updates[0]) {
		foreach ($updates as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '1.0-rc3', 'auto', false, 0, $module_name);
	
	return true;
}

/** 1.0-rc6 */
function upgradefmDNS_108($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	$success = version_compare($running_version, '1.0-rc3', '<') ? upgradefmDNS_107($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` CHANGE  `server_os`  `server_os_distro` VARCHAR( 50 ) NULL DEFAULT NULL ";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` ADD  `server_os` VARCHAR( 50 ) NULL DEFAULT NULL AFTER  `server_name` ";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` CHANGE  `server_run_as_predefined`  `server_run_as_predefined` ENUM(  'named',  'bind',  'daemon',  'as defined:' ) NOT NULL DEFAULT  'named'";


	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '1.0-rc6', 'auto', false, 0, $module_name);
	
	return true;
}

/** 1.0 */
function upgradefmDNS_109($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	$success = version_compare($running_version, '1.0-rc6', '<') ? upgradefmDNS_108($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` ADD  `def_dropdown` ENUM(  'yes',  'no' ) NOT NULL DEFAULT  'no'";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` CHANGE  `server_update_method`  `server_update_method` ENUM(  'http',  'https',  'cron',  'ssh' ) NOT NULL DEFAULT  'http'";

	$updates[] = "UPDATE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET  `def_dropdown` =  'yes' WHERE  `def_option` IN ('match-mapped-addresses','transfer-format','check-names','preferred-glue','dialup','notify','forward')";
	$updates[] = "UPDATE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET  `def_dropdown` =  'yes' WHERE  `def_type` =  '( yes | no )'";
	$updates[] = "UPDATE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET  `def_type` =  '( master | slave | response ) ( warn | fail | ignore )' WHERE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions`.`def_option` =  'check-names'";
	$updates[] = "UPDATE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET  `def_type` =  '( port )' WHERE  `def_option` =  'port'";

	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	if (count($updates) && $updates[0]) {
		foreach ($updates as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '1.0', 'auto', false, 0, $module_name);
	
	return true;
}

/** 1.0.1 */
function upgradefmDNS_110($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	$success = version_compare($running_version, '1.0', '<') ? upgradefmDNS_109($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$fmdb->query("SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions`");
	$table[] = ($fmdb->num_rows) ? null : "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` ADD  `def_dropdown` ENUM(  'yes',  'no' ) NOT NULL DEFAULT  'no'";

	$inserts[] = <<<INSERT
INSERT IGNORE INTO  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` (
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

	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	if (count($inserts) && $inserts[0]) {
		foreach ($inserts as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '1.0.1', 'auto', false, 0, $module_name);
	
	return true;
}

/** 1.1 */
function upgradefmDNS_111($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	$success = version_compare($running_version, '1.0.1', '<') ? upgradefmDNS_110($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}acls` ADD  `acl_comment` TEXT NULL AFTER  `acl_addresses` ";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` ADD  `cfg_comment` TEXT NULL AFTER  `cfg_data` ";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}keys` ADD  `key_comment` TEXT NULL AFTER  `key_view` ";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}views` ADD  `view_comment` TEXT NULL AFTER  `view_name` ";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` CHANGE  `domain_type`  `domain_type` ENUM(  'master',  'slave',  'forward',  'stub' ) NOT NULL DEFAULT  'master'";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` CHANGE  `server_update_config`  `server_update_config` ENUM(  'yes',  'no',  'conf' ) NOT NULL DEFAULT  'no'";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` ADD  `server_client_version` VARCHAR( 150 ) NULL AFTER  `server_installed` ";

	$inserts = $updates = null;
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	if (count($inserts) && $inserts[0]) {
		foreach ($inserts as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	if (count($updates) && $updates[0]) {
		foreach ($updates as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	if (!setOption('fmDNS_client_version', $__FM_CONFIG['fmDNS']['client_version'], 'auto', false)) return false;
		
	setOption('version', '1.1', 'auto', false, 0, $module_name);
	
	return true;
}

/** 1.2-beta1 */
function upgradefmDNS_1201($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	$success = version_compare($running_version, '1.1', '<') ? upgradefmDNS_111($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` CHANGE  `record_type`  `record_type` ENUM( 'A',  'AAAA',  'CERT',  'CNAME',  'DNAME',  'DNSKEY', 'KEY',  'KX',  'MX',  'NS',  'PTR',  'RP',  'SRV',  'TXT', 'HINFO' ) NOT NULL DEFAULT  'A'";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` CHANGE  `record_class`  `record_class` ENUM(  'IN',  'CH',  'HS' ) NOT NULL DEFAULT  'IN'";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` ADD  `record_os` VARCHAR( 255 ) NULL AFTER  `record_port`,
ADD  `record_cert_type` TINYINT NULL AFTER  `record_os` ,
ADD  `record_key_tag` INT NULL AFTER  `record_cert_type` ,
ADD  `record_algorithm` TINYINT NULL AFTER  `record_key_tag`,
ADD  `record_flags` ENUM(  '0',  '256',  '257' ) NULL AFTER  `record_algorithm`,
ADD  `record_text` VARCHAR( 255 ) NULL AFTER  `record_flags` ";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` CHANGE  `record_value`  `record_value` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL ";
	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS `fm_{$__FM_CONFIG['fmDNS']['prefix']}records_skipped` (
  `account_id` int(11) NOT NULL,
  `domain_id` int(11) NOT NULL,
  `record_id` int(11) NOT NULL,
  `record_status` enum('active','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
TABLE;

	$inserts = $updates = null;
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	if (count($inserts) && $inserts[0]) {
		foreach ($inserts as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	if (count($updates) && $updates[0]) {
		foreach ($updates as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}
	
	/** Force rebuild of server configs for Issue #75 */
	$current_module = $_SESSION['module'];
	$_SESSION['module'] = 'fmDNS';
	setBuildUpdateConfigFlag(null, 'yes', 'build', $__FM_CONFIG);
	$_SESSION['module'] = $current_module;
	unset($current_module);
	
	/** Move module options */
	$fmdb->get_results("SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}options`");
	if ($fmdb->num_rows) {
		$count = $fmdb->num_rows;
		$result = $fmdb->last_result;
		for ($i=0; $i<$count; $i++) {
			if (!setOption($result[$i]->option_name, $result[$i]->option_value, 'auto', true, $result[$i]->account_id, 'fmDNS')) return false;
		}
	}
	$fmdb->query("DROP TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}options`");
	if (!$fmdb->result || $fmdb->sql_errors) return false;
	
	$fm_user_caps = getOption('fm_user_caps');
	
	/** Update user capabilities */
	$fm_user_caps['fmDNS'] = array(
			'read_only'				=> '<b>Read Only</b>',
			'manage_servers'		=> 'Server Management',
			'build_server_configs'	=> 'Build Server Configs',
			'manage_zones'			=> 'Zone Management',
			'manage_records'		=> 'Record Management',
			'reload_zones'			=> 'Reload Zones',
			'manage_settings'		=> 'Manage Settings'
		);
	if (!setOption('fm_user_caps', $fm_user_caps)) return false;
	
	$fmdb->get_results("SELECT * FROM `fm_users`");
	if ($fmdb->num_rows) {
		$count = $fmdb->num_rows;
		$result = $fmdb->last_result;
		for ($i=0; $i<$count; $i++) {
			$user_caps = null;
			/** Update user capabilities */
			$j = 1;
			$temp_caps = null;
			foreach ($fm_user_caps['fmDNS'] as $slug => $trash) {
				$user_caps = isSerialized($result[$i]->user_caps) ? unserialize($result[$i]->user_caps) : $result[$i]->user_caps;
				if (@array_key_exists('fmDNS', $user_caps)) {
					if ($user_caps['fmDNS']['imported_perms'] == 0) {
						$temp_caps['fmDNS']['read_only'] = 1;
					} else {
						if ($j & $user_caps['fmDNS']['imported_perms'] && $j > 1) $temp_caps['fmDNS'][$slug] = 1;
						$j = $j*2 ;
					}
				} else {
					$temp_caps['fmDNS']['read_only'] = $user_caps['fmDNS']['read_only'] = 1;
				}
			}
			if (@array_key_exists('fmDNS', $temp_caps)) $user_caps['fmDNS'] = array_merge($temp_caps['fmDNS'], $user_caps['fmDNS']);
			if (@array_key_exists('zone_access', $user_caps['fmDNS'])) $user_caps['fmDNS']['access_specific_zones'] = $user_caps['fmDNS']['zone_access'];
			unset($user_caps['fmDNS']['imported_perms'], $user_caps['fmDNS']['zone_access']);
			
			$fmdb->query("UPDATE fm_users SET user_caps = '" . serialize($user_caps) . "' WHERE user_id=" . $result[$i]->user_id);
			if (!$fmdb->result) return false;
		}
	}

	setOption('client_version', $__FM_CONFIG['fmDNS']['client_version'], 'auto', false, 0, 'fmDNS');
		
	setOption('version', '1.2-beta1', 'auto', false, 0, $module_name);
	
	return true;
}


/** 1.2-rc1 */
function upgradefmDNS_1202($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	$success = version_compare($running_version, '1.2-beta1', '<') ? upgradefmDNS_1201($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$fm_user_caps = getOption('fm_user_caps');
	
	/** Update user capabilities */
	$fm_user_caps['fmDNS'] = array(
			'view_all'				=> 'View All',
			'manage_servers'		=> 'Server Management',
			'build_server_configs'	=> 'Build Server Configs',
			'manage_zones'			=> 'Zone Management',
			'manage_records'		=> 'Record Management',
			'reload_zones'			=> 'Reload Zones',
			'manage_settings'		=> 'Manage Settings'
		);
	if (!setOption('fm_user_caps', $fm_user_caps)) return false;
	
	$fmdb->get_results("SELECT * FROM `fm_users`");
	if ($fmdb->num_rows) {
		$count = $fmdb->num_rows;
		$result = $fmdb->last_result;
		for ($i=0; $i<$count; $i++) {
			$user_caps = null;
			/** Update user capabilities */
			$temp_caps = null;
			foreach ($fm_user_caps['fmDNS'] as $slug => $trash) {
				$user_caps = isSerialized($result[$i]->user_caps) ? unserialize($result[$i]->user_caps) : $result[$i]->user_caps;
				if (@array_key_exists('fmDNS', $user_caps)) {
					if (array_key_exists('read_only', $user_caps['fmDNS'])) {
						$temp_caps['fmDNS']['view_all'] = 1;
						unset($user_caps['fmDNS']['read_only']);
					}
				}
			}
			if (@array_key_exists('fmDNS', $temp_caps)) $user_caps['fmDNS'] = array_merge($temp_caps['fmDNS'], $user_caps['fmDNS']);
			$fmdb->query("UPDATE fm_users SET user_caps = '" . serialize($user_caps) . "' WHERE user_id=" . $result[$i]->user_id);
			if (!$fmdb->result) return false;
		}
	}
	
	setOption('client_version', $__FM_CONFIG['fmDNS']['client_version'], 'auto', false, 0, 'fmDNS');
		
	setOption('version', '1.2-rc1', 'auto', false, 0, $module_name);
	
	return true;
}


/** 1.2.3 */
function upgradefmDNS_123($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	$success = version_compare($running_version, '1.2-rc1', '<') ? upgradefmDNS_1202($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}records_skipped` DROP PRIMARY KEY";

	$inserts = $updates = null;
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	if (count($inserts) && $inserts[0]) {
		foreach ($inserts as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	if (count($updates) && $updates[0]) {
		foreach ($updates as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}
		
	setOption('version', '1.2.3', 'auto', false, 0, $module_name);
	
	return true;
}


/** 1.2.4 */
function upgradefmDNS_124($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	$success = version_compare($running_version, '1.2.3', '<') ? upgradefmDNS_123($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` ADD INDEX (  `domain_id` ) ";

	$inserts = $updates = null;
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '1.2.4', 'auto', false, 0, $module_name);
	
	return true;
}


/** 1.3-beta1 */
function upgradefmDNS_1301($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	$success = version_compare($running_version, '1.2.4', '<') ? upgradefmDNS_124($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS `fm_{$__FM_CONFIG['fmDNS']['prefix']}controls` (
  `control_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `server_serial_no` int(11) NOT NULL DEFAULT '0',
  `control_ip` varchar(15) NOT NULL DEFAULT '*',
  `control_port` int(5) NOT NULL DEFAULT '953',
  `control_addresses` text NOT NULL,
  `control_keys` varchar(255) DEFAULT NULL,
  `control_comment` text NOT NULL,
  `control_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`control_id`)
) ENGINE = MYISAM DEFAULT CHARSET=utf8;
TABLE;

	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` CHANGE  `record_type`  `record_type` ENUM( 'A',  'AAAA',  'CERT',  'CNAME',  'DNAME',  'DNSKEY', 'KEY',  'KX',  'MX',  'NS',  'PTR',  'RP',  'SRV',  'TXT', 'HINFO', 'SSHFP' ) NOT NULL DEFAULT  'A'";

	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}soa` ADD `soa_name` VARCHAR(255) NULL AFTER `domain_id`";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` ADD  `server_chroot_dir` VARCHAR( 255 ) NULL DEFAULT NULL AFTER  `server_root_dir`";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` ADD  `soa_id` INT( 11 ) NOT NULL DEFAULT '0' AFTER  `account_id` ,
		ADD  `soa_serial_no` INT(2) UNSIGNED ZEROFILL NOT NULL DEFAULT '0' AFTER  `soa_id` ";

	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}soa` ADD  `soa_template` ENUM(  'yes',  'no' ) NOT NULL DEFAULT  'no' AFTER  `domain_id`";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` CHANGE `cfg_view` `view_id` INT(11) NOT NULL DEFAULT '0'";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` ADD `domain_id` INT(11) NOT NULL DEFAULT '0' AFTER `view_id`";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` ADD INDEX(`domain_id`)";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` CHANGE `def_view_support` `def_clause_support` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'O'";
	$table[] = "TRUNCATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions`";
	
	$updates[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` d JOIN `fm_{$__FM_CONFIG['fmDNS']['prefix']}soa` s ON d.`domain_id` = s.`domain_id` SET d.`soa_id`=s.`soa_id`";
	$updates[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` d JOIN `fm_{$__FM_CONFIG['fmDNS']['prefix']}soa` s ON d.`domain_id` = s.`domain_id` SET d.`soa_serial_no`=s.`soa_serial_no`";
	$updates[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}soa` DROP `domain_id`, DROP `soa_serial_no`";
	
	$inserts[] = <<<INSERT
INSERT IGNORE INTO  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` (
`def_function` ,
`def_option` ,
`def_type` ,
`def_multiple_values` ,
`def_clause_support`,
`def_dropdown`
)
VALUES 
('key', 'algorithm', 'string', 'no', 'K', 'no'),
('key', 'secret', 'quoted_string', 'no', 'K', 'no'),
('options', 'acache-cleaning-interval', '( minutes )', 'no', 'OV', 'no'),
('options', 'acache-enable', '( yes | no )', 'no', 'OV', 'yes'),
('options', 'additional-from-auth', '( yes | no )', 'no', 'OV', 'yes'),
('options', 'additional-from-cache', '( yes | no )', 'no', 'OV', 'yes'),
('options', 'allow-notify', '( address_match_element )', 'yes', 'OVZ', 'no'),
('options', 'allow-query', '( address_match_element )', 'yes', 'OVZ', 'no'),
('options', 'allow-query-cache', '( address_match_element )', 'yes', 'OV', 'no'),
('options', 'allow-query-cache-on', '( address_match_element )', 'yes', 'OV', 'no'),
('options', 'allow-query-on', '( address_match_element )', 'yes', 'OVZ', 'no'),
('options', 'allow-recursion', '( address_match_element )', 'yes', 'OV', 'no'),
('options', 'allow-recursion-on', '( address_match_element )', 'yes', 'OV', 'no'),
('options', 'allow-transfer', '( address_match_element )', 'yes', 'OVZ', 'no'),
('options', 'allow-update', '( address_match_element )', 'yes', 'OVZ', 'no'),
('options', 'allow-update-forwarding', '( address_match_element )', 'yes', 'OVZ', 'no'),
('options', 'also-notify', '( ipv4_address | ipv6_address ) [ port ( ip_port | * ) ]', 'yes', 'OVZ', 'no'),
('options', 'alt-transfer-source', '( ipv4_address | * ) [ port ( ip_port | * ) ]', 'no', 'OVZ', 'no'),
('options', 'alt-transfer-source-v6', '( ipv6_address | * ) [ port ( ip_port | * ) ]', 'no', 'OVZ', 'no'),
('options', 'attach-cache', '( quoted_string )', 'no', 'OV', 'no'),
('options', 'auth-nxdomain', '( yes | no )', 'no', 'OV', 'yes'),
('options', 'auto-dnssec', '( allow | maintain | create | off )', 'no', 'OVZ', 'yes'),
('options', 'avoid-v4-udp-ports', '( ip_port )', 'yes', 'O', 'no'), 
('options', 'avoid-v6-udp-ports', '( ip_port )', 'yes', 'O', 'no'),
('options', 'bindkeys-file', '( quoted_string )', 'no', 'O', 'no'),
('options', 'blackhole', '( address_match_element )', 'yes', 'O', 'no'),
('options', 'bogus', '( yes | no )', 'no', 'S', 'yes'),
('options', 'check-dup-records', '( fail | warn | ignore )', 'no', 'OVZ', 'yes'),
('options', 'check-integrity', '( yes | no )', 'no', 'OVZ', 'yes'),
('options', 'check-mx', '( fail | warn | ignore )', 'no', 'OVZ', 'yes'),
('options', 'check-mx-cname', '( fail | warn | ignore )', 'no', 'OVZ', 'yes'),
('options', 'check-names', '( master | slave | response ) ( warn | fail | ignore )', 'no', 'OVZ', 'yes'),
('options', 'check-sibling', '( yes | no )', 'no', 'OVZ', 'yes'),
('options', 'check-srv-cname', '( fail | warn | ignore )', 'no', 'OVZ', 'yes'),
('options', 'check-wildcard', '( yes | no )', 'no', 'OVZ', 'yes'),
('options', 'cleaning-interval', '( minutes )', 'no', 'OV', 'no'),
('options', 'clients-per-query', '( integer )', 'no', 'OV', 'no'),
('options', 'coresize', '( size_in_bytes )', 'no', 'O', 'no'),
('options', 'database', '( quoted_string )', 'no', 'Z', 'no'),
('options', 'datasize', '( size_in_bytes )', 'no', 'O', 'no'),
('options', 'delegation-only', '( yes | no )', 'no', 'Z', 'yes'),
('options', 'deny-answer-address', '( address_match_element ) [ except-from { ( address_match_element ) } ]', 'yes', 'OV', 'no'),
('options', 'deny-answer-aliases', '( quoted_string ) [ except-from { ( address_match_element ) } ]', 'yes', 'OV', 'no'),
('options', 'dialup', '( yes | no | notify | refresh | passive | notify-passive )', 'no', 'OVZ', 'yes'),
('options', 'disable-algorithms', '( string )', 'yes', 'OV', 'no'),
('options', 'disable-empty-zone', '( quoted_string )', 'no', 'OV', 'no'),
('options', 'dnssec-accept-expired', '( yes | no )', 'no', 'OV', 'yes'),
('options', 'dnssec-dnskey-kskonly', '( yes | no )', 'no', 'OVZ', 'yes'),
('options', 'dnssec-enable', '( yes | no )', 'no', 'OV', 'yes'),
('options', 'dnssec-lookaside', 'domain trust-anchor domain', 'no', 'OV', 'no'),
('options', 'dnssec-must-be-secure', 'domain ( yes | no )', 'no', 'OV', 'no'),
('options', 'dnssec-secure-to-insecure', '( yes | no )', 'no', 'OVZ', 'yes'),
('options', 'dnssec-validation', '( yes | no )', 'no', 'OV', 'yes'),
('options', 'dual-stack-servers', '( quoted_string )', 'yes', 'OV', 'no'),
('options', 'dump-file', '( quoted_string )', 'no', 'O', 'no'),
('options', 'edns', '( yes | no )', 'no', 'S', 'yes'),
('options', 'edns-udp-size', '( size_in_bytes )', 'no', 'OSV', 'no'),
('options', 'empty-contact', '( string )', 'no', 'OV', 'no'),
('options', 'empty-server', '( string )', 'no', 'OV', 'no'),
('options', 'empty-zones-enable', '( yes | no )', 'no', 'OV', 'yes'),
('options', 'files', '( integer )', 'no', 'O', 'no'),
('options', 'flush-zones-on-shutdown', '( yes | no )', 'no', 'O', 'yes'),
('options', 'forward', '( first | only )', 'no', 'OVZ', 'yes'),
('options', 'forwarders', '[ port ( ip_port | * ) ] { ( ipv4_address | ipv6_address ) } [ port ( ip_port | * ) ]', 'yes', 'OVZ', 'no'),
('options', 'heartbeat-interval', '( minutes )', 'no', 'O', 'no'),
('options', 'hostname', '( quoted_string | none )', 'no', 'O', 'no'),
('options', 'interface-interval', '( minutes )', 'no', 'O', 'no'),
('options', 'ixfr-from-differences', '( yes | no )', 'no', 'Z', 'yes'),
('options', 'journal', '( quoted_string )', 'no', 'Z', 'no'),
('options', 'key-directory', '( quoted_string )', 'no', 'OVZ', 'no'),
('options', 'lame-ttl', '( seconds )', 'no', 'OV', 'no'),
('options', 'listen-on', '[ port ( ip_port | * ) ] { ( ipv4_address ) }', 'yes', 'OR', 'no'),
('options', 'listen-on-v6', '[ port ( ip_port | * ) ] { ( ipv6_address ) }', 'yes', 'O', 'no'),
('options', 'managed-keys-directory', '( quoted_string )', 'no', 'O', 'no'),
('options', 'masterfile-format', '( text | raw )', 'no', 'OVZ', 'yes'),
('options', 'masters', '( { ipv4_address | ipv6_address } )', 'yes', 'OVZ', 'no'),
('options', 'match-clients', '( address_match_element )', 'yes', 'V', 'no'),
('options', 'match-destinations', '( address_match_element )', 'yes', 'V', 'no'),
('options', 'match-mapped-addresses', '( yes | no )', 'no', 'O', 'yes'),
('options', 'match-recursive-only', '( yes | no )', 'no', 'V', 'yes'),
('options', 'max-acache-size', '( size_in_bytes )', 'no', 'OV', 'no'),
('options', 'max-cache-size', '( size_in_bytes )', 'no', 'OV', 'no'),
('options', 'max-cache-ttl', '( seconds )', 'no', 'OV', 'no'),
('options', 'max-clients-per-query', '( integer )', 'no', 'OV', 'no'),
('options', 'max-journal-size', '( size_in_bytes )', 'no', 'OVZ', 'no'),
('options', 'max-ncache-ttl', '( seconds )', 'no', 'OV', 'no'),
('options', 'max-refresh-time', '( seconds )', 'no', 'OVZ', 'no'),
('options', 'max-retry-time', '( seconds )', 'no', 'OVZ', 'no'),
('options', 'max-transfer-idle-in', '( minutes )', 'no', 'OVZ', 'no'),
('options', 'max-transfer-idle-out', '( minutes )', 'no', 'OVZ', 'no'),
('options', 'max-transfer-time-in', '( minutes )', 'no', 'OVZ', 'no'),
('options', 'max-transfer-time-out', '( minutes )', 'no', 'OVZ', 'no'),
('options', 'max-udp-size', '( size_in_bytes )', 'no', 'OSV', 'no'),
('options', 'memstatistics', '( yes | no )', 'no', 'O', 'yes'),
('options', 'memstatistics-file', '( quoted_string )', 'no', 'O', 'no'),
('options', 'min-refresh-time', '( seconds )', 'no', 'OVZ', 'no'),
('options', 'min-retry-time', '( seconds )', 'no', 'OVZ', 'no'),
('options', 'minimal-responses', '( yes | no )', 'no', 'OV', 'yes'),
('options', 'multi-master', '( yes | no )', 'no', 'OVZ', 'yes'),
('options', 'ndots', '( integer )', 'no', 'R', 'no'),
('options', 'notify', '( yes | no | explicit )', 'no', 'OVZ', 'yes'),
('options', 'notify-delay', '( seconds )', 'no', 'OVZ', 'no'),
('options', 'notify-source', '( ipv4_address | * ) [ port ( ip_port | * ) ]', 'no', 'OSVZ', 'no'),
('options', 'notify-source-v6', '( ipv6_address | * ) [ port ( ip_port | * ) ]', 'no', 'OSVZ', 'no'),
('options', 'notify-to-soa', '( yes | no )', 'no', 'OVZ', 'yes'),
('options', 'pid-file', '( quoted_string | none )', 'no', 'O', 'no'),
('options', 'port', '( ip_port )', 'no', 'O', 'no'),
('options', 'preferred-glue', '( A | AAAA )', 'no', 'OV', 'yes'),
('options', 'provide-ixfr', '( yes | no )', 'no', 'S', 'yes'),
('options', 'query-source', 'address ( ipv4_address | * ) [ port ( ip_port | * ) ]', 'no', 'OVZ', 'no'),
('options', 'query-source-v6', 'address ( ipv6_address | * ) [ port ( ip_port | * ) ]', 'no', 'OVZ', 'no'),
('options', 'querylog', '( yes | no )', 'no', 'O', 'yes'),
('options', 'random-device', '( quoted_string )', 'no', 'O', 'no'),
('options', 'recursing-file', '( quoted_string )', 'no', 'O', 'no'),
('options', 'recursion', '( yes | no )', 'no', 'OV', 'yes'),
('options', 'recursive-clients', '( integer )', 'no', 'O', 'no'),
('options', 'request-ixfr', '( yes | no )', 'no', 'OVZ', 'yes'),
('options', 'request-nsid', '( yes | no )', 'no', 'OV', 'yes'),
('options', 'reserved-sockets', '( integer )', 'no', 'O', 'no'),
('options', 'search', '( quoted_string )', 'yes', 'R', 'no'),
('options', 'secroots-file', '( quoted_string )', 'no', 'O', 'no'),
('options', 'serial-query-rate', '( integer )', 'no', 'O', 'no'),
('options', 'server-id', '( quoted_string | none | hostname )', 'no', 'O', 'no'),
('options', 'session-keyfile', '( quoted_string | none )', 'no', 'O', 'no'),
('options', 'session-keyname', '( quoted_string )', 'no', 'O', 'no'),
('options', 'session-keyalg', '( string )', 'no', 'O', 'no'),
('options', 'sig-signing-nodes', '( integer )', 'no', 'OVZ', 'no'),
('options', 'sig-signing-signatures', '( integer )', 'no', 'OVZ', 'no'),
('options', 'sig-signing-type', '( integer )', 'no', 'OVZ', 'no'),
('options', 'sig-validity-interval', '( days )', 'no', 'OVZ', 'no'),
('options', 'sortlist', '( address_match_element )', 'yes', 'OV', 'no'),
('options', 'stacksize', '( size_in_bytes )', 'no', 'O', 'no'),
('options', 'statistics-file', '( quoted_string )', 'no', 'O', 'no'),
('options', 'tcp-clients', '( integer )', 'no', 'O', 'no'),
('options', 'tcp-listen-queue', '( integer )', 'no', 'O', 'no'),
('options', 'tkey-dhkey', '( quoted_string integer )', 'no', 'O', 'no'),
('options', 'tkey-domain', '( quoted_string )', 'no', 'O', 'no'),
('options', 'tkey-gssapi-credential', '( quoted_string )', 'no', 'O', 'no'),
('options', 'transfer-format', '( many-answers | one-answer )', 'no', 'OSVZ', 'yes'),
('options', 'transfer-source', '( ipv4_address | * ) [ port ( ip_port | * ) ]', 'no', 'OSVZ', 'no'),
('options', 'transfer-source-v6', '( ipv6_address | * ) [ port ( ip_port | * ) ]', 'no', 'OSVZ', 'no'),
('options', 'transfers', '( integer )', 'no', 'S', 'no'),
('options', 'transfers-in', '( integer )', 'no', 'O', 'no'),
('options', 'transfers-out', '( integer )', 'no', 'O', 'no'),
('options', 'transfers-per-ns', '( integer )', 'no', 'O', 'no'),
('options', 'try-tcp-refresh', '( yes | no )', 'no', 'OVZ', 'yes'),
('options', 'update-check-ksk', '( yes | no )', 'no', 'OVZ', 'yes'),
('options', 'update-policy', '( local | { update-policy-rule } )', 'no', 'Z', 'no'),
('options', 'use-alt-transfer-source', '( yes | no )', 'no', 'OVZ', 'yes'),
('options', 'use-v4-udp-ports', '( range ip_port ip_port )', 'no', 'O', 'no'),
('options', 'use-v6-udp-ports', '( range ip_port ip_port )', 'no', 'O', 'no'),
('options', 'view', '( quoted_string )', 'no', 'R', 'no'),
('options', 'version', '( quoted_string | none )', 'no', 'O', 'no'),
('options', 'zero-no-soa-ttl', '( yes | no )', 'no', 'OVZ', 'yes'),
('options', 'zero-no-soa-ttl-cache', '( yes | no )', 'no', 'OV', 'yes'),
('options', 'zone-statistics', '( yes | no )', 'no', 'OVZ', 'yes')
;
INSERT;

	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	if (count($inserts) && $inserts[0]) {
		foreach ($inserts as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	if (count($updates) && $updates[0]) {
		foreach ($updates as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}
	
	$query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains`";
	$fmdb->query($query);
	$num_rows = $fmdb->num_rows;
	$results = $fmdb->last_result;
	$translate_array = array('domain_check_names' => 'check-names',
								'domain_notify_slaves' => 'notify',
								'domain_multi_masters' => 'multi-master',
								'domain_transfers_from' => 'transfers-from',
								'domain_updates_from' => 'updates-from',
								'domain_master_servers' => 'masters',
								'domain_forward_servers' => 'forwarders');
	for ($x=0; $x<$num_rows; $x++) {
		foreach ($translate_array as $old_key => $new_key) {
			if ($results[$x]->$old_key) {
				$query = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` 
					(account_id,domain_id,cfg_name,cfg_data) VALUES ({$_SESSION['user']['account_id']},
					{$results[$x]->domain_id}, '$new_key', '{$results[$x]->$old_key}')";
				$fmdb->query($query);
				if (!$fmdb->result || $fmdb->sql_errors) return false;
			}
		}
	}
	
	$query = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains`";
	foreach ($translate_array as $old_key => $new_key) {
		$query .= " DROP `$old_key`,";
	}
	$query = rtrim($query, ',');
	$fmdb->query($query);
	if (!$fmdb->result || $fmdb->sql_errors) return false;
	
	/** Update manually entered ACLs with acl_ids */
	$query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` WHERE cfg_type='global'";
	$fmdb->query($query);
	$num_rows = $fmdb->num_rows;
	$results = $fmdb->last_result;
	for ($x=0; $x<$num_rows; $x++) {
		$cfg_data_array = explode(';', $results[$x]->cfg_data);
		$new_cfg_data = null;
		foreach ($cfg_data_array as $acl_name) {
			$acl_id = getNameFromID(trim($acl_name), "fm_{$__FM_CONFIG['fmDNS']['prefix']}acls", 'acl_', 'acl_name', 'acl_id');
			$new_cfg_data .= $acl_id ? "acl_{$acl_id}," : $acl_name . ',';
		}
		$new_cfg_data = rtrim($new_cfg_data, ',');
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` SET cfg_data='$new_cfg_data' WHERE cfg_id=" . $results[$x]->cfg_id;
		$fmdb->query($query);
		if (!$fmdb->result || $fmdb->sql_errors) return false;
	}
	
	/**
	$query = "SELECT domain_id,domain_name_servers FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains`";
	$fmdb->query($query);
	$num_rows = $fmdb->num_rows;
	$results = $fmdb->last_result;
	for ($x=0; $x<$num_rows; $x++) {
		if ($results[$x]->domain_name_servers == 0) continue;
		
		$name_server_ids = explode(';', $results[$x]->domain_name_servers);
		$server_serial_nos = null;
		foreach ($name_server_ids as $server_id) {
			$server_serial_nos[] = getNameFromID($server_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_id', 'server_serial_no');
		}
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` SET domain_name_servers='" . implode(';', $server_serial_nos) . "' "
				. "WHERE domain_id=" . $results[$x]->domain_id;
		$fmdb->query($query);
		if (!$fmdb->result || $fmdb->sql_errors) return false;
	}
	*/

	setOption('version', '1.3-beta1', 'auto', false, 0, $module_name);
	
	return true;
}

/** 1.3-beta2 */
function upgradefmDNS_1302($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	$success = version_compare($running_version, '1.3-beta1', '<') ? upgradefmDNS_1301($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$updates[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}acls` SET acl_addresses = replace(acl_addresses, ';', ',') WHERE instr(acl_addresses, ';') > 0";
	$updates[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}acls` SET acl_addresses = replace(acl_addresses, ' ', '') WHERE instr(acl_addresses, ' ') > 0";
	$updates[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_type` = '[ port ( ip_port | * ) ] { ( ipv4_address ) }' WHERE `fm_dns_functions`.`def_option` = 'listen-on'";
	$updates[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_type` = '[ port ( ip_port | * ) ] { ( ipv6_address ) }' WHERE `fm_dns_functions`.`def_option` = 'listen-on-v6'";
	$updates[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_type` = '( warn | fail | ignore )', `def_clause_support` = 'Z' WHERE `fm_dns_functions`.`def_option` = 'check-names'";

	if (count($updates) && $updates[0]) {
		foreach ($updates as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}
	
	setOption('version', '1.3-beta2', 'auto', false, 0, $module_name);
	
	return true;
}

/** 1.3 */
function upgradefmDNS_130($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	$success = version_compare($running_version, '1.3-beta2', '<') ? upgradefmDNS_1302($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}records_skipped` ADD `skip_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}records_skipped` ADD INDEX(`record_id`)";

	$inserts = $updates = null;
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '1.3', 'auto', false, 0, $module_name);
	
	return true;
}

/** 1.3.1 */
function upgradefmDNS_131($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	$success = version_compare($running_version, '1.3', '<') ? upgradefmDNS_130($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}soa` ADD `soa_default` ENUM('yes','no') NOT NULL DEFAULT 'no' AFTER `soa_template`";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}keys` CHANGE  `key_algorithm`  `key_algorithm` ENUM(  'hmac-md5',  'hmac-sha1',  'hmac-sha224',  'hmac-sha256', 'hmac-sha384',  'hmac-sha512' ) NOT NULL DEFAULT  'hmac-md5'";

	$inserts = $updates = null;
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '1.3.1', 'auto', false, 0, $module_name);
	
	return true;
}

/** 2.0-alpha1 */
function upgradefmDNS_2001($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	$success = version_compare($running_version, '1.3.1', '<') ? upgradefmDNS_131($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` ADD `domain_template` ENUM('yes','no') NOT NULL DEFAULT 'no' AFTER `account_id`, ADD `domain_default` ENUM('yes','no') NOT NULL DEFAULT 'no' AFTER `domain_template`";

	$inserts = $updates = null;
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '2.0-alpha1', 'auto', false, 0, $module_name);
	
	return true;
}

/** 2.0-alpha2 */
function upgradefmDNS_2002($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	$success = version_compare($running_version, '2.0-alpha1', '<') ? upgradefmDNS_2001($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS `fm_{$__FM_CONFIG['fmDNS']['prefix']}server_groups` (
  `group_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `group_name` varchar(128) NOT NULL,
  `group_masters` text NOT NULL,
  `group_slaves` text NOT NULL,
  `group_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`group_id`)
) ENGINE = MYISAM DEFAULT CHARSET=utf8;
TABLE;

	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` ADD `def_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` ADD `def_option_type` ENUM('global','ratelimit') NOT NULL DEFAULT 'global' AFTER `def_function`";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` ADD `def_max_parameters` INT(3) NOT NULL DEFAULT '1' ";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` ADD `def_zone_support` VARCHAR(10) NULL DEFAULT NULL AFTER `def_clause_support` ";
	
	$inserts[] = <<<INSERT
INSERT IGNORE INTO  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` (
`def_function`,
`def_option_type`,
`def_option`,
`def_type`,
`def_multiple_values`,
`def_clause_support`,
`def_dropdown`,
`def_max_parameters`
)
VALUES 
('options', 'ratelimit', 'responses-per-second', '( [size integer] [ratio fixedpoint] integer )', 'no', 'OV', 'no', '5'),
('options', 'ratelimit', 'referrals-per-second', '( integer )', 'no', 'OV', 'no', '1'),
('options', 'ratelimit', 'nodata-per-second', '( integer )', 'no', 'OV', 'no', '1'),
('options', 'ratelimit', 'nxdomains-per-second', '( integer )', 'no', 'OV', 'no', '1'),
('options', 'ratelimit', 'errors-per-second', '( integer )', 'no', 'OV', 'no', '1'),
('options', 'ratelimit', 'all-per-second', '( integer )', 'no', 'OV', 'no', '1'),
('options', 'ratelimit', 'window', '( integer )', 'no', 'OV', 'no', '1'),
('options', 'ratelimit', 'log-only', '( yes | no )', 'no', 'OV', 'yes', '1'),
('options', 'ratelimit', 'qps-scale', '( integer )', 'no', 'OV', 'no', '1'),
('options', 'ratelimit', 'ipv4-prefix-length', '( integer )', 'no', 'OV', 'no', '1'),
('options', 'ratelimit', 'ipv6-prefix-length', '( integer )', 'no', 'OV', 'no', '1'),
('options', 'ratelimit', 'slip', '( integer )', 'no', 'OV', 'no', '1'),
('options', 'ratelimit', 'exempt-clients', '( address_match_element )', 'yes', 'OV', 'no', '1'),
('options', 'ratelimit', 'max-table-size', '( integer )', 'no', 'OV', 'no', '1'),
('options', 'ratelimit', 'min-table-size', '( integer )', 'no', 'OV', 'no', '1')
;
INSERT;

	$updates[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_zone_support` = 'MS' WHERE `fm_dns_functions`.`def_clause_support` LIKE '%Z%'";
	$updates[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_zone_support` = 'F' WHERE `fm_dns_functions`.`def_option` IN ('forward', 'forwarders')";
	$updates[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_zone_support` = 'M' WHERE `fm_dns_functions`.`def_option` IN 
		('also-notify', 'max-transfer-idle-out', 'max-transfer-out', 'notify-source', 'notify-source-v6', 'provide-ixfr', 'transfer-format',
		'transfers-out', 'update-policy')";
	$updates[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_zone_support` = 'S' WHERE `fm_dns_functions`.`def_option` IN 
		('allow-notify', 'alt-transfer-source', 'alt-transfer-source-v6', 'masters', 'max-refresh-time', 'max-retry-time', 'max-transfer-idle-in',
		'max-transfer-time-in', 'min-refresh-time', 'min-retry-time', 'multi-master', 'request-ixfr', 'transfer-source', 'transfer-source-v6',
		'transfers-in', 'transfers-per-ns')";
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	if (count($inserts) && $inserts[0]) {
		foreach ($inserts as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	if (count($updates) && $updates[0]) {
		foreach ($updates as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	/** Prepend domain_name_servers with s_ */
	$query = "SELECT domain_id,domain_name_servers FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE domain_name_servers!=0";
	$fmdb->query($query);
	$num_rows = $fmdb->num_rows;
	$results = $fmdb->last_result;
	for ($x=0; $x<$num_rows; $x++) {
		$name_server_ids = explode(';', $results[$x]->domain_name_servers);
		$new_server_ids = null;
		foreach ($name_server_ids as $server_id) {
			$new_server_ids[] = 's_' . $server_id;
		}
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` SET domain_name_servers='" . implode(';', $new_server_ids) . "' "
				. "WHERE domain_id=" . $results[$x]->domain_id;
		$fmdb->query($query);
		if (!$fmdb->result || $fmdb->sql_errors) return false;
	}

	setOption('version', '2.0-alpha2', 'auto', false, 0, $module_name);
	
	return true;
}

/** 2.0-beta1 */
function upgradefmDNS_2003($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	$success = version_compare($running_version, '2.0-alpha2', '<') ? upgradefmDNS_2002($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` ADD `domain_clone_dname` ENUM('yes','no') NULL DEFAULT NULL AFTER `domain_clone_domain_id`";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` ADD `domain_template_id` INT(11) NOT NULL DEFAULT '0' AFTER `domain_default`";
	
	$inserts = $updates = null;
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('clones_use_dnames', $__FM_CONFIG['fmDNS']['default']['options']['clones_use_dnames']['default_value'], 'auto', false, 0, $module_name);

	setOption('version', '2.0-beta1', 'auto', false, 0, $module_name);
	
	return true;
}

/** 2.0-rc2 */
function upgradefmDNS_2004($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	$success = version_compare($running_version, '2.0-beta1', '<') ? upgradefmDNS_2003($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}acls` CHANGE `server_serial_no` `server_serial_no` VARCHAR(255) NOT NULL DEFAULT '0'";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` CHANGE `server_serial_no` `server_serial_no` VARCHAR(255) NOT NULL DEFAULT '0'";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}controls` CHANGE `server_serial_no` `server_serial_no` VARCHAR(255) NOT NULL DEFAULT '0'";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}views` CHANGE `server_serial_no` `server_serial_no` VARCHAR(255) NOT NULL DEFAULT '0'";
	
	$inserts = $updates = null;
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '2.0-rc2', 'auto', false, 0, $module_name);
	
	return true;
}

/** 2.0 */
function upgradefmDNS_200($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	$success = version_compare($running_version, '2.0-rc2', '<') ? upgradefmDNS_2004($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` CHANGE `soa_serial_no` `soa_serial_no` INT(2) NOT NULL DEFAULT  '0'";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}track_reloads` DROP `soa_id`";
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}acls` SET `server_serial_no` = REPLACE(`server_serial_no`, 'g', 'g_') WHERE `server_serial_no` LIKE 'g%'";
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` SET `server_serial_no` = REPLACE(`server_serial_no`, 'g', 'g_') WHERE `server_serial_no` LIKE 'g%'";
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}controls` SET `server_serial_no` = REPLACE(`server_serial_no`, 'g', 'g_') WHERE `server_serial_no` LIKE 'g%'";
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}views` SET `server_serial_no` = REPLACE(`server_serial_no`, 'g', 'g_') WHERE `server_serial_no` LIKE 'g%'";
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` SET `soa_serial_no` = " . date('Ymd') . '00';
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}soa` s, `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` d SET soa_append='no' WHERE d.domain_mapping='reverse' AND s.soa_template='no' AND d.soa_id=s.soa_id";
	
	$inserts = $updates = null;
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '2.0', 'auto', false, 0, $module_name);
	
	return true;
}

/** 2.1 */
function upgradefmDNS_210($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	$success = version_compare($running_version, '2.0', '<') ? upgradefmDNS_200($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` DROP INDEX domain_id, ADD INDEX `idx_domain_id` (`domain_id`)";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` ADD INDEX `idx_domain_status` (`domain_status`)";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` DROP INDEX def_option, ADD INDEX `idx_def_option` (`def_option`)";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` DROP INDEX domain_id, ADD INDEX `idx_domain_id` (`domain_id`)";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` ADD INDEX `idx_record_status` (`record_status`)";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` ADD INDEX `idx_record_account_id` (`account_id`)";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}records_skipped` DROP INDEX record_id, ADD INDEX `idx_record_id` (`record_id`)";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` DROP INDEX server_serial_no, ADD UNIQUE `idx_server_serial_no` (`server_serial_no`)";
	
	$inserts[] = <<<INSERT
INSERT IGNORE INTO  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` (
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
('options', 'global', 'include', '( quoted_string )', 'no', 'OVZ', 'no', '-1')
;
INSERT;
	
	/** Run queries */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	/** Run queries */
	if (count($inserts) && $inserts[0]) {
		foreach ($inserts as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '2.1', 'auto', false, 0, $module_name);
	
	return true;
}

?>