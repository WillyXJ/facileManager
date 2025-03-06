<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) The facileManager Team                                    |
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

function upgradefmDNSSchema($running_version) {
	global $fmdb;
	
	/** Include module variables */
	@include(dirname(__FILE__) . '/variables.inc.php');
	
	/** Get current version */
	if (!$running_version) {
		$running_version = getOption('version', 0, 'fmDNS');
	}
	
	/** Checks to support older versions (ie n-3 upgrade scenarios */
	$success = version_compare($running_version, '7.1.1', '<') ? upgradefmDNS_711($__FM_CONFIG, $running_version) : true;
	if (!$success) return $fmdb->last_error;
	
	setOption('client_version', $__FM_CONFIG['fmDNS']['client_version'], 'auto', false, 0, 'fmDNS');
		
	return true;
}

function deleteUnusedFiles($files_to_delete) {
	$this_dir = dirname(__FILE__);
	foreach ($files_to_delete as $file) {
		$filename = $this_dir . '/' . $file;
		if (is_writable($filename)) {
			unlink($filename);
		}
	}
}

/** 1.0-b5 */
function upgradefmDNS_100($__FM_CONFIG) {
	global $fmdb;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` CHANGE  `record_ttl`  `record_ttl` VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT  ''";
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '1.0-beta5', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 1.0-b7 */
function upgradefmDNS_101($__FM_CONFIG, $running_version) {
	global $fmdb;
	
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

	setOption('version', '1.0-beta7', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 1.0-b10 */
function upgradefmDNS_102($__FM_CONFIG, $running_version) {
	global $fmdb;
	
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

	setOption('version', '1.0-beta10', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 1.0-b11 */
function upgradefmDNS_103($__FM_CONFIG, $running_version) {
	global $fmdb;
	
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

	setOption('version', '1.0-beta11', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 1.0-b13 */
function upgradefmDNS_104($__FM_CONFIG, $running_version) {
	global $fmdb;
	
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

	setOption('version', '1.0-beta13', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 1.0-b14 */
function upgradefmDNS_105($__FM_CONFIG, $running_version) {
	global $fmdb;
	
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

	setOption('version', '1.0-beta14', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 1.0-rc2 */
function upgradefmDNS_106($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '1.0-b14', '<') ? upgradefmDNS_105($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = <<<TABLESQL
CREATE TABLE IF NOT EXISTS `fm_{$__FM_CONFIG['fmDNS']['prefix']}options` (
  `option_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `option_name` varchar(255) NOT NULL,
  `option_value` varchar(255) NOT NULL,
  PRIMARY KEY (`option_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;
TABLESQL;

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

	setOption('version', '1.0-rc2', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 1.0-rc3 */
function upgradefmDNS_107($__FM_CONFIG, $running_version) {
	global $fmdb;
	
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

	setOption('version', '1.0-rc3', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 1.0-rc6 */
function upgradefmDNS_108($__FM_CONFIG, $running_version) {
	global $fmdb;
	
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

	setOption('version', '1.0-rc6', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 1.0 */
function upgradefmDNS_109($__FM_CONFIG, $running_version) {
	global $fmdb;
	
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

	setOption('version', '1.0', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 1.0.1 */
function upgradefmDNS_110($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '1.0', '<') ? upgradefmDNS_109($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$fmdb->query("SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions`");
	$table[] = ($fmdb->num_rows) ? null : "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` ADD  `def_dropdown` ENUM(  'yes',  'no' ) NOT NULL DEFAULT  'no'";

	$inserts[] = <<<INSERTSQL
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
INSERTSQL;

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

	setOption('version', '1.0.1', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 1.1 */
function upgradefmDNS_111($__FM_CONFIG, $running_version) {
	global $fmdb;
	
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

	if (!setOption('fmDNS_client_version', $__FM_CONFIG['fmDNS']['client_version'], 'auto', false)) return false;
		
	setOption('version', '1.1', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 1.2-beta1 */
function upgradefmDNS_1201($__FM_CONFIG, $running_version) {
	global $fmdb;
	
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
	$table[] = <<<TABLESQL
CREATE TABLE IF NOT EXISTS `fm_{$__FM_CONFIG['fmDNS']['prefix']}records_skipped` (
  `account_id` int(11) NOT NULL,
  `domain_id` int(11) NOT NULL,
  `record_id` int(11) NOT NULL,
  `record_status` enum('active','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
TABLESQL;

	$inserts = $updates = null;
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	/** Force rebuild of server configs for Issue #75 */
	$current_module = $_SESSION['module'];
	@session_start();
	$_SESSION['module'] = 'fmDNS';
	setBuildUpdateConfigFlag(null, 'yes', 'build', $__FM_CONFIG);
	$_SESSION['module'] = $current_module;
	unset($current_module);
	session_write_close();
	
	/** Move module options */
	$result = $fmdb->get_results("SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}options`");
	if ($fmdb->num_rows) {
		$count = $fmdb->num_rows;
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
	
	$result = $fmdb->get_results("SELECT * FROM `fm_users`");
	if ($fmdb->num_rows) {
		$count = $fmdb->num_rows;
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
		
	setOption('version', '1.2-beta1', 'auto', false, 0, 'fmDNS');
	
	return true;
}


/** 1.2-rc1 */
function upgradefmDNS_1202($__FM_CONFIG, $running_version) {
	global $fmdb;
	
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
	
	$result = $fmdb->get_results("SELECT * FROM `fm_users`");
	if ($fmdb->num_rows) {
		$count = $fmdb->num_rows;
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
		
	setOption('version', '1.2-rc1', 'auto', false, 0, 'fmDNS');
	
	return true;
}


/** 1.2.3 */
function upgradefmDNS_123($__FM_CONFIG, $running_version) {
	global $fmdb;
	
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

	setOption('version', '1.2.3', 'auto', false, 0, 'fmDNS');
	
	return true;
}


/** 1.2.4 */
function upgradefmDNS_124($__FM_CONFIG, $running_version) {
	global $fmdb;
	
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

	setOption('version', '1.2.4', 'auto', false, 0, 'fmDNS');
	
	return true;
}


/** 1.3-beta1 */
function upgradefmDNS_1301($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '1.2.4', '<') ? upgradefmDNS_124($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = <<<TABLESQL
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
TABLESQL;

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
	
	$inserts[] = <<<INSERTSQL
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
INSERTSQL;

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
	
	setOption('version', '1.3-beta1', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 1.3-beta2 */
function upgradefmDNS_1302($__FM_CONFIG, $running_version) {
	global $fmdb;
	
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
	
	setOption('version', '1.3-beta2', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 1.3 */
function upgradefmDNS_130($__FM_CONFIG, $running_version) {
	global $fmdb;
	
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

	setOption('version', '1.3', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 1.3.1 */
function upgradefmDNS_131($__FM_CONFIG, $running_version) {
	global $fmdb;
	
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

	setOption('version', '1.3.1', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 2.0-alpha1 */
function upgradefmDNS_2001($__FM_CONFIG, $running_version) {
	global $fmdb;
	
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

	setOption('version', '2.0-alpha1', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 2.0-alpha2 */
function upgradefmDNS_2002($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '2.0-alpha1', '<') ? upgradefmDNS_2001($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = <<<TABLESQL
CREATE TABLE IF NOT EXISTS `fm_{$__FM_CONFIG['fmDNS']['prefix']}server_groups` (
  `group_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `group_name` varchar(128) NOT NULL,
  `group_masters` text NOT NULL,
  `group_slaves` text NOT NULL,
  `group_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`group_id`)
) ENGINE = MYISAM DEFAULT CHARSET=utf8;
TABLESQL;

	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` ADD `def_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` ADD `def_option_type` ENUM('global','ratelimit') NOT NULL DEFAULT 'global' AFTER `def_function`";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` ADD `def_max_parameters` INT(3) NOT NULL DEFAULT '1' ";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` ADD `def_zone_support` VARCHAR(10) NULL DEFAULT NULL AFTER `def_clause_support` ";
	
	$inserts[] = <<<INSERTSQL
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
INSERTSQL;

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

	setOption('version', '2.0-alpha2', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 2.0-beta1 */
function upgradefmDNS_2003($__FM_CONFIG, $running_version) {
	global $fmdb;
	
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

	setOption('clones_use_dnames', $__FM_CONFIG['fmDNS']['default']['options']['clones_use_dnames']['default_value'], 'auto', false, 0, 'fmDNS');

	setOption('version', '2.0-beta1', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 2.0-rc2 */
function upgradefmDNS_2004($__FM_CONFIG, $running_version) {
	global $fmdb;
	
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

	setOption('version', '2.0-rc2', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 2.0 */
function upgradefmDNS_200($__FM_CONFIG, $running_version) {
	global $fmdb;
	
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

	setOption('version', '2.0', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 2.1-beta1 */
function upgradefmDNS_2101($__FM_CONFIG, $running_version) {
	global $fmdb;
	
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
	
	$inserts[] = <<<INSERTSQL
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
('options', 'global', 'include', '( quoted_string )', 'no', 'OVZ', 'no', '-1'),
('options', 'response-policy', '( string )', 'no', 'O', NULL, 'no', '1')
;
INSERTSQL;
	
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

	setOption('version', '2.1-beta1', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 2.1-rc1 */
function upgradefmDNS_2102($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '2.1-beta1', '<') ? upgradefmDNS_2101($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` CHANGE  `record_type`  `record_type` ENUM( 'A','AAAA','CERT','CNAME','DNAME','DNSKEY','KEY','KX','MX','NS','PTR','RP','SRV','TXT','HINFO','SSHFP','NAPTR' ) NOT NULL DEFAULT  'A'";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` ADD `record_params` VARCHAR(255) NULL AFTER `record_port`, ADD `record_regex` VARCHAR(255) NULL AFTER `record_params`";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` CHANGE `record_flags` `record_flags` ENUM('0','256','257','', 'U', 'S', 'A', 'P') NULL DEFAULT NULL";
	
	/** Run queries */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '2.1-rc1', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 2.1.2 */
function upgradefmDNS_212($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '2.1-rc1', '<') ? upgradefmDNS_2102($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` DROP INDEX idx_record_account_id";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` ADD KEY `idx_record_type` (`record_type`)";
	
	/** Run queries */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '2.1.2', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 2.1.8 */
function upgradefmDNS_218($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '2.1.2', '<') ? upgradefmDNS_212($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` AS d1, `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` AS d2
		SET d1.domain_name_servers=d2.domain_name_servers
		WHERE d1.domain_template_id=d2.domain_id";
	
	/** Run queries */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '2.1.8', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 2.2 */
function upgradefmDNS_220($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '2.1.8', '<') ? upgradefmDNS_218($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}acls` ADD `acl_parent_id` INT NOT NULL DEFAULT '0' AFTER `server_serial_no`";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` ADD `cfg_in_clause` ENUM('yes','no') NOT NULL DEFAULT 'yes' AFTER `cfg_data`";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}acls` CHANGE `acl_name` `acl_name` VARCHAR(255) NULL DEFAULT NULL";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}acls` CHANGE `acl_addresses` `acl_addresses` TEXT NULL DEFAULT NULL";
	
	/** Run queries */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}
	
	/** Remove stale entries from server/group deletes */
	$servers[] = 0;
	$query = "SELECT server_serial_no FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` WHERE server_status!='deleted'";
	$fmdb->query($query);
	if ($fmdb->num_rows) {
		for($i=0; $i<$fmdb->num_rows; $i++) {
			$servers[] = $fmdb->last_result[$i]->server_serial_no;
		}
	}
	$query = "SELECT group_id FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}server_groups` WHERE group_status!='deleted'";
	$fmdb->query($query);
	if ($fmdb->num_rows) {
		for($i=0; $i<$fmdb->num_rows; $i++) {
			$servers[] = 'g_' . $fmdb->last_result[$i]->group_id;
		}
	}
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}acls` SET acl_status='deleted' WHERE server_serial_no NOT IN ('" . join($servers, "','") . "')";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` SET cfg_status='deleted' WHERE server_serial_no NOT IN ('" . join($servers, "','") . "')";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}controls` SET control_status='deleted' WHERE server_serial_no NOT IN ('" . join($servers, "','") . "')";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}views` SET view_status='deleted' WHERE server_serial_no NOT IN ('" . join($servers, "','") . "')";

	/** Rework ACL table */
	$query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}acls`";
	$fmdb->query($query);
	if ($fmdb->num_rows) {
		for($i=0; $i<$fmdb->num_rows; $i++) {
			if ($fmdb->last_result[$i]->acl_predefined != 'as defined:') {
				$fmdb->last_result[$i]->acl_addresses = $fmdb->last_result[$i]->acl_predefined;
			}
			foreach (explode(',', $fmdb->last_result[$i]->acl_addresses) as $acl_address) {
				if ($acl_address) {
					$inserts[] = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}acls` (account_id, server_serial_no, acl_parent_id, acl_addresses, acl_status)
						VALUES ({$fmdb->last_result[$i]->account_id}, {$fmdb->last_result[$i]->server_serial_no}, {$fmdb->last_result[$i]->acl_id}, '$acl_address', '{$fmdb->last_result[$i]->acl_status}')";
				}
			}
		}
	}
	
	/** Drop fields */
	$inserts[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}acls` DROP `acl_predefined`";
	
	/** Run queries */
	if (count($inserts) && $inserts[0]) {
		foreach ($inserts as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '2.2', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 2.2.1 */
function upgradefmDNS_221($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '2.2', '<') ? upgradefmDNS_220($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}acls` CHANGE `acl_name` `acl_name` VARCHAR(255) NULL DEFAULT NULL";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}acls` CHANGE `acl_addresses` `acl_addresses` TEXT NULL DEFAULT NULL";
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}acls` SET acl_addresses = NULL WHERE acl_parent_id=0";
	
	/** Run queries */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}
	
	setOption('version', '2.2.1', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 2.2.6 */
function upgradefmDNS_226($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '2.2.1', '<') ? upgradefmDNS_221($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_type` = '( yes | no | auto )' WHERE `def_option` = 'dnssec-validation'";
	
	/** Run queries */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}
	
	setOption('version', '2.2.6', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 3.0-alpha1 */
function upgradefmDNS_3001($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '2.2.6', '<') ? upgradefmDNS_221($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", 'domain_dynamic')) {
		$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` ADD `domain_dynamic` ENUM('yes','no') NOT NULL DEFAULT 'no' AFTER `domain_clone_dname`";
	}
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", 'soa_serial_no_previous')) {
		$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` ADD `soa_serial_no_previous` INT(2) NOT NULL DEFAULT '0' AFTER `soa_serial_no`";
	}
	
	/** Run queries */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}
	
	setOption('version', '3.0-alpha1', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 3.0-alpha2 */
function upgradefmDNS_3002($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '3.0-alpha1', '<') ? upgradefmDNS_3001($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}records", 'record_ptr_id')) {
		$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` ADD `record_ptr_id` INT(11) NOT NULL DEFAULT '0' AFTER `domain_id`";
	}
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}controls", 'control_type')) {
		$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}controls` ADD `control_type` ENUM('controls','statistics') NOT NULL DEFAULT 'controls' AFTER `server_serial_no`";
	}
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` CHANGE `record_type` `record_type` ENUM('A','AAAA','CERT','CNAME','DHCID','DLV','DNAME','DNSKEY','DS','KEY','KX','MX','NS','PTR','RP','SRV','TXT','HINFO','SSHFP','NAPTR') NOT NULL DEFAULT 'A'";
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}functions", 'def_minimum_version')) {
		$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` ADD `def_minimum_version` VARCHAR(20) NULL";
	}
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` CHANGE `def_option_type` `def_option_type` ENUM('global','ratelimit','rrset') NOT NULL DEFAULT 'global'";
	
	/** Run queries */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}
	
	/** Link existing RRs */
	if (basicGetList("fm_{$__FM_CONFIG['fmDNS']['prefix']}records", 'record_id', 'record_', "AND record_type='PTR'")) {
		$ptr_count = $fmdb->num_rows;
		$ptr_results = $fmdb->last_result;
		
		for ($i=0; $i<$ptr_count; $i++) {
			$record_value = trim($ptr_results[$i]->record_value, '.');
			$domain_parts = explode('.', $record_value);
			
			$temp_domain_parts = $domain_parts;
			$reversed_domain_parts = array_reverse($temp_domain_parts);
			for ($j=1; $j<count($domain_parts); $j++) {
				for ($k=0; $k<$j; $k++) {
					$popped = array_pop($reversed_domain_parts);
				}

				$domain = join('.', array_reverse($reversed_domain_parts));
				if ($domain_id = getNameFromID($domain, "fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", 'domain_', 'domain_name', 'domain_id')) {
					$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` SET `record_ptr_id`='{$ptr_results[$i]->record_id}' "
							. "WHERE `account_id`='{$_SESSION['user']['account_id']}' AND `domain_id`='$domain_id' "
							. "AND `record_type`='A' AND `record_name`='{$domain_parts[0]}' LIMIT 1";
					if ($fmdb->query($query)) break;
				}
			}
		}
	}
	
	$inserts[] = <<<INSERTSQL
INSERT IGNORE INTO  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` (
`def_function` ,
`def_option_type`,
`def_option` ,
`def_type` ,
`def_multiple_values` ,
`def_clause_support`,
`def_zone_support`,
`def_dropdown`,
`def_max_parameters`,
`def_minimum_version`
)
VALUES 
('options', 'global', 'serial-update-method', '( increment | unixtime | date )', 'no', 'OVZ', 'M', 'yes', 1, '9.9.0'),
('options', 'global', 'inline-signing', '( yes | no )', 'no', 'Z', 'MS', 'yes', 1, '9.9.0'),
('options', 'global', 'dnstap', '( auth | auth response | auth query | client | client response | client query | forwarder | forward response | forwarder query | resolver | resolver response | resolver query )', 'yes', 'OV', NULL, 'yes', 1, '9.11.0'),
('options', 'global', 'dnstap-output', '( file | unix ) ( quoted_string )', 'no', 'O', NULL, 'no', 1, '9.11.0'),
('options', 'global', 'dnstap-identity', '( quoted_string | hostname | none )', 'no', 'O', NULL, 'no', 1, '9.11.0'),
('options', 'global', 'dnstap-version', '( quoted_string | none )', 'no', 'O', NULL, 'no', 1, '9.11.0'),
('options', 'global', 'geoip-directory', '( quoted_string )', 'no', 'O', NULL, 'no', 1, '9.10.0'),
('options', 'global', 'lock-file', '( quoted_string | none )', 'no', 'O', NULL, 'no', 1, '9.11.0'),
('options', 'global', 'dscp', '( integer )', 'no', 'O', NULL, 'no', 1, '9.10.0'),
('options', 'global', 'root-delegation-only exclude', '( quoted_string )', 'yes', 'O', NULL, 'no', 1, NULL),
('options', 'global', 'disable-ds-digests', 'domain { digest_type; [ digest_type; ] }', 'no', 'O', NULL, 'no', '-1', '9.10.0'),
('options', 'global', 'dnssec-loadkeys-interval', '( minutes )', 'no', 'OZ', 'MS', 'no', 1, NULL),
('options', 'global', 'dnssec-update-mode', '( maintain | no-resign )', 'no', 'OZ', 'MS', 'yes', 1, '9.9.0'),
('options', 'global', 'nta-lifetime', '( duration )', 'no', 'O', NULL, 'no', 1, '9.11.0'),
('options', 'global', 'nta-recheck', '( duration )', 'no', 'O', NULL, 'no', 1, '9.11.0'),
('options', 'global', 'max-zone-ttl', '( unlimited | integer )', 'no', 'O', NULL, 'no', 1, '9.11.0'),
('options', 'global', 'automatic-interface-scan', '( yes | no )', 'no', 'O', NULL, 'yes', 1, '9.10.0'),
('options', 'global', 'allow-new-zones', '( yes | no )', 'no', 'O', NULL, 'yes', 1, NULL),
('options', 'global', 'geoip-use-ecs', '( yes | no )', 'no', 'O', NULL, 'yes', 1, '9.11.0'),
('options', 'global', 'message-compression', '( yes | no )', 'no', 'O', NULL, 'yes', 1, '9.11.0'),
('options', 'global', 'minimal-any', '( yes | no )', 'no', 'O', NULL, 'yes', 1, '9.11.0'),
('options', 'global', 'require-server-cookie', '( yes | no )', 'no', 'O', NULL, 'yes', 1, '9.11.0'),
('options', 'global', 'send-cookie', '( yes | no )', 'no', 'OS', NULL, 'yes', 1, '9.11.0'),
('options', 'global', 'nocookie-udp-size', '( integer )', 'no', 'O', NULL, 'no', 1, '9.11.0'),
('options', 'global', 'cookie-algorithm', '( aes | sha1 | sha256 )', 'no', 'O', NULL, 'yes', 1, '9.11.0'),
('options', 'global', 'cookie-secret', '( quoted_string )', 'no', 'O', NULL, 'no', 1, '9.11.0'),
('options', 'global', 'request-expire', '( yes | no )', 'no', 'OS', NULL, 'yes', 1, '9.11.0'),
('options', 'global', 'filter-aaaa-on-v4', '( yes | no | break-dnssec )', 'no', 'O', NULL, 'yes', 1, NULL),
('options', 'global', 'filter-aaaa-on-v6', '( yes | no | break-dnssec )', 'no', 'O', NULL, 'yes', 1, NULL),
('options', 'global', 'check-names', '( master | slave | response ) ( warn | fail | ignore )', 'no', 'O', NULL, 'yes', 1, NULL),
('options', 'global', 'check-spf', '( warn | ignore )', 'no', 'OVZ', 'M', 'yes', 1, NULL),
('options', 'global', 'allow-v6-synthesis', '( address_match_element )', 'yes', 'O', NULL, 'no', 1, NULL),
('options', 'global', 'filter-aaaa', '( address_match_element )', 'yes', 'O', NULL, 'no', 1, NULL),
('options', 'global', 'keep-response-order', '( address_match_element )', 'yes', 'O', NULL, 'no', 1, '9.11.0'),
('options', 'global', 'no-case-compress', '( address_match_element )', 'yes', 'O', NULL, 'no', 1, NULL),
('options', 'global', 'resolver-query-timeout', '( integer )', 'no', 'O', NULL, 'no', 1, NULL),
('options', 'global', 'notify-rate', '( integer )', 'no', 'O', NULL, 'no', 1, '9.11.0'),
('options', 'global', 'startup-notify-rate', '( integer )', 'no', 'O', NULL, 'no', 1, '9.11.0'),
('options', 'global', 'transfer-message-size', '( integer )', 'no', 'O', NULL, 'no', 1, '9.11.0'),
('options', 'global', 'fetches-per-zone', '( integer [ ( drop | fail ) ] )', 'no', 'O', NULL, 'no', 1, NULL),
('options', 'global', 'fetches-per-server', '( integer [ ( drop | fail ) ] )', 'no', 'O', NULL, 'no', 1, NULL),
('options', 'global', 'fetch-quota-params', '( integer fixedpoint fixedpoint fixedpoint )', 'no', 'O', NULL, 'no', 1, NULL),
('options', 'global', 'topology', '( address_match_element )', 'yes', 'O', NULL, 'no', 1, NULL),
('options', 'global', 'servfail-ttl', '( seconds )', 'no', 'O', NULL, 'no', 1, '9.11.0'),
('options', 'global', 'masterfile-style', '( relative | full )', 'no', 'O', NULL, 'no', 1, '9.11.0'),
('options', 'global', 'max-recursion-depth', '( integer )', 'no', 'O', NULL, 'no', 1, NULL),
('options', 'global', 'max-recursion-queries', '( integer )', 'no', 'O', NULL, 'no', 1, NULL),
('options', 'global', 'max-rsa-exponent-size', '( integer )', 'no', 'O', NULL, 'no', 1, NULL),
('options', 'global', 'prefetch', '( integer [integer] )', 'no', 'O', NULL, 'no', 1, '9.10.0'),
('options', 'global', 'v6-bias', '( integer )', 'no', 'O', NULL, 'no', 1, '9.10.0'),
('options', 'global', 'edns-version', '( integer )', 'no', 'S', NULL, 'no', 1, '9.11.0'),
('options', 'global', 'tcp-only', '( yes | no )', 'no', 'S', NULL, 'yes', 1, '9.11.0'),
('options', 'global', 'keys', '( key_id )', 'no', 'S', NULL, 'no', 1, NULL),
('options', 'global', 'use-queryport-pool', '( yes | no )', 'no', 'S', NULL, 'yes', 1, NULL),
('options', 'global', 'queryport-pool-ports', '( integer )', 'no', 'S', NULL, 'no', 1, NULL),
('options', 'global', 'queryport-pool-updateinterval', '( integer )', 'no', 'S', NULL, 'no', 1, NULL),
('options', 'global', 'lwres-tasks', '( integer )', 'no', 'R', NULL, 'no', 1, '9.11.0'),
('options', 'global', 'lwres-clients', '( integer )', 'no', 'R', NULL, 'no', 1, '9.11.0'),
('options', 'rrset', 'rrset-order', '( rrset_order_spec )', 'no', 'OV', NULL, 'no', '-1', NULL)
;
INSERTSQL;
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_type` = '( yes | no | auto )' WHERE `def_option` = 'dnssec-validation'";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_minimum_version` = '9.9.4', `def_clause_support` = 'OVZ' WHERE `def_option_type` = 'ratelimit'";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_type` = '( hmac-sha1 | hmac-sha224 | hmac-sha256 | hmac-sha384 | hmac-sha512 | hmac-md5 )', `def_dropdown`='yes' WHERE `def_option` = 'session-keyalg'";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_type` = '( auto | no | domain trust-anchor domain )' WHERE `def_option` = 'dnssec-lookaside'";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_type` = '( full | terse | none | yes | no )' WHERE `def_option` = 'zone-statistics'";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_type` = '( yes | no | explicit | master-only )' WHERE `def_option` = 'notify'";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_clause_support` = 'OSV' WHERE `def_option` = 'provide-ixfr'";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_clause_support` = 'OSVZ' WHERE `def_option` IN ('request-ixfr', 'query-source', 'query-source-v6')";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_clause_support` = 'OVZ' WHERE `def_option` = 'ixfr-from-differences'";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_clause_support` = 'OSV' WHERE `def_option` IN ('request-nsid')";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_type` = '( allow | maintain | off )' WHERE `def_option` = 'auto-dnssec'";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_zone_support` = 'MS' WHERE `def_option` = 'update-check-ksk'";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_zone_support` = NULL WHERE `def_option` = 'forward'";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_max_parameters` = '-1' WHERE `def_option` IN ('listen-on', 'listen-on-v6', 'disable-empty-zone')";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_type` = '( range ip_port ip_port )' WHERE `def_option` IN ('avoid-v4-udp-ports', 'avoid-v6-udp-ports')";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_type` = '( size_spec )' WHERE `def_type` = '( size_in_bytes )'";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_type` = '( size_spec )' WHERE `def_option` IN ('files')";
	$inserts[] = "DELETE FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` WHERE `def_option` IN ('cleaning-interval')";
	$inserts[] = "DELETE FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` WHERE `cfg_name` IN ('cleaning-interval')";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_type` = '( text | raw | map )' WHERE `def_option` = 'masterfile-format'";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_type` = '( quoted_string ) [ except-from { ( quoted_string ) } ]' WHERE `def_option` IN ('deny-answer-aliases')";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_option` = 'deny-answer-addresses' WHERE `def_option` = 'deny-answer-address'";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` SET `cfg_name` = 'deny-answer-addresses' WHERE `fm_dns_config`.`cfg_name` = 'deny-answer-address'";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_type` = '( integer )' WHERE `def_option` = 'responses-per-second'";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_zone_support` = 'S' WHERE `def_option` IN ('try-tcp-refresh', 'request-ixfr')";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_type` = '( domain { algorithm; [ algorithm; ] } )', `def_zone_support` = 'O', `def_max_parameters` = '-1', `def_multiple_values` = 'no' WHERE `def_option` = 'disable-algorithms'";

	/** Run queries */
	if (count($inserts) && $inserts[0]) {
		foreach ($inserts as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '3.0-alpha2', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 3.0-alpha3 */
function upgradefmDNS_3003($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '3.0-alpha2', '<') ? upgradefmDNS_3002($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` SET `record_cert_type`=1 WHERE `record_type`='SSHFP'";

	/** Run queries */
	if (count($inserts) && $inserts[0]) {
		foreach ($inserts as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '3.0-alpha3', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 3.0-beta1 */
function upgradefmDNS_3004($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '3.0-alpha3', '<') ? upgradefmDNS_3003($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", 'domain_dnssec')) {
		$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` ADD `domain_dnssec` ENUM('yes','no') NOT NULL DEFAULT 'no' AFTER `domain_dynamic`";
	}
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", 'domain_dnssec_sig_expire')) {
		$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` ADD `domain_dnssec_sig_expire` INT(2) NOT NULL DEFAULT '0' AFTER `domain_dnssec`";
	}
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", 'domain_dnssec_signed')) {
		$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` ADD `domain_dnssec_signed` INT(2) NOT NULL DEFAULT '0' AFTER `domain_dnssec_sig_expire`";
	}
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}keys", 'domain_id')) {
		$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}keys` ADD `domain_id` INT(11) NOT NULL AFTER `account_id`";
	}
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}keys", 'key_type')) {
		$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}keys` ADD `key_type` ENUM('tsig','dnssec') NOT NULL DEFAULT 'tsig' AFTER `domain_id`";
	}
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}keys", 'key_subtype')) {
		$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}keys` ADD `key_subtype` ENUM('ZSK','KSK') NULL AFTER `key_type`";
	}
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}keys` CHANGE `key_algorithm` `key_algorithm` ENUM('hmac-md5','hmac-sha1','hmac-sha224','hmac-sha256','hmac-sha384','hmac-sha512','rsamd5','rsasha1','dsa','nsec3rsasha1','nsec3dsa','rsasha256','rsasha512','eccgost','ecdsap256sha256','ecdsap384sha384') NOT NULL DEFAULT 'hmac-md5'";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}keys` CHANGE `key_secret` `key_secret` TEXT NOT NULL";
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}keys", 'key_size')) {
		$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}keys` ADD `key_size` INT(2) NULL AFTER `key_secret`";
	}
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}keys", 'key_created')) {
		$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}keys` ADD `key_created` INT(2) NULL AFTER `key_size`";
	}
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}keys", 'key_signing')) {
		$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}keys` ADD `key_signing` ENUM('yes','no') NOT NULL DEFAULT 'no' AFTER `key_comment`";
	}
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}keys", 'key_public')) {
		$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}keys` ADD `key_public` TEXT NULL DEFAULT NULL AFTER `key_secret`";
	}
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}keys` CHANGE `key_status` `key_status` ENUM('active','disabled','revoked','deleted') NOT NULL DEFAULT 'active'";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` CHANGE  `record_type`  `record_type` ENUM('A','AAAA','CAA','CERT','CNAME','DHCID','DLV','DNAME','DNSKEY','DS','KEY','KX','MX','NS','PTR','RP','SRV','TXT','HINFO','SSHFP','NAPTR') NOT NULL DEFAULT  'A'";

	/** Run queries */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}
	
	setOption('version', '3.0-beta1', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 3.0-beta2 */
function upgradefmDNS_3005($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '3.0-beta1', '<') ? upgradefmDNS_3004($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", 'domain_dnssec_generate_ds')) {
		$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` ADD `domain_dnssec_generate_ds` ENUM('yes','no') NOT NULL DEFAULT 'no' AFTER `domain_dnssec`";
	}
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", 'domain_dnssec_ds_rr')) {
		$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` ADD `domain_dnssec_ds_rr` TEXT NULL AFTER `domain_dnssec_generate_ds`";
	}
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", 'domain_dnssec_parent_domain_id')) {
		$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` ADD `domain_dnssec_parent_domain_id` INT(11) NOT NULL DEFAULT '0' AFTER `domain_dnssec_ds_rr`";
	}
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}keys` CHANGE `domain_id` `domain_id` INT(11) NULL DEFAULT NULL";

	/** Run queries */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '3.0-beta2', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 3.1.0 */
function upgradefmDNS_310($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '3.0-beta2', '<') ? upgradefmDNS_3005($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}views", 'view_order_id')) {
		$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}views` ADD `view_order_id` INT(11) NOT NULL AFTER `server_serial_no`";
	}
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", 'domain_groups')) {
		$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` ADD `domain_groups` VARCHAR(255) NOT NULL DEFAULT '0' AFTER `domain_name`";
	}
	$table[] = <<<TABLESQL
CREATE TABLE IF NOT EXISTS `fm_{$__FM_CONFIG['fmDNS']['prefix']}domain_groups` (
  `group_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `group_name` varchar(128) NOT NULL,
  `group_comment` text NOT NULL,
  `group_status` enum('active','disabled','deleted') NOT NULL,
  PRIMARY KEY (`group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
TABLESQL;

	$inserts[] = "UPDATE `fm_options` SET `option_name` = 'enable_config_checks' WHERE `option_name` = 'enable_named_checks'";

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

	setOption('version', '3.1', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 3.1.2 */
function upgradefmDNS_312($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '3.1', '<') ? upgradefmDNS_310($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domain_groups` CHANGE `group_id` `group_id` INT(11) NOT NULL AUTO_INCREMENT";

	/** Run queries */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	setOption('version', '3.1.2', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 3.2.0 */
function upgradefmDNS_320($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '3.1', '<') ? upgradefmDNS_310($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = <<<TABLESQL
CREATE TABLE IF NOT EXISTS `fm_{$__FM_CONFIG['fmDNS']['prefix']}masters` (
  `master_id` INT(11) NOT NULL AUTO_INCREMENT ,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `server_serial_no` varchar(255) NOT NULL DEFAULT '0',
  `master_parent_id` INT NOT NULL DEFAULT '0',
  `master_name` VARCHAR(255) NULL DEFAULT NULL,
  `master_addresses` TEXT NULL DEFAULT NULL,
  `master_port` INT(5) NULL DEFAULT NULL,
  `master_dscp` INT(5) NULL DEFAULT NULL,
  `master_key_id` INT(11) NOT NULL DEFAULT '0',
  `master_comment` text,
  `master_status` ENUM( 'active',  'disabled',  'deleted') NOT NULL DEFAULT  'active',
  PRIMARY KEY (`master_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
TABLESQL;

	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` SET cfg_data=REPLACE(cfg_data, '; ', ',') WHERE (cfg_name='also-notify' OR cfg_name='masters') AND cfg_data LIKE '%; %'";
	$inserts[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` SET cfg_data=REPLACE(cfg_data, ';', ',') WHERE (cfg_name='also-notify' OR cfg_name='masters') AND cfg_data LIKE '%;%'";

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

	setOption('version', '3.2', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 3.3.0 */
function upgradefmDNS_330($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '3.2', '<') ? upgradefmDNS_320($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` CHANGE `server_type` `server_type` ENUM('bind9','remote') NOT NULL DEFAULT 'bind9'";
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}config", 'server_id')) {
		$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` ADD `server_id` INT(11) NOT NULL DEFAULT '0' AFTER `cfg_type`";
	}

	/** Move the server_key to the config table */
	$query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers`";
	$fmdb->query($query);
	if ($fmdb->num_rows) {
		$config_opts = array('bogus', 'edns', 'provide-ixfr', 'request-ixfr',
			'transfers', 'transfer-format');
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}config`";
		$sql_fields = '(`cfg_type`, `server_id`, `cfg_name`, `cfg_data`, `cfg_status`)';
		$sql_values = null;
		foreach ($fmdb->last_result as $server_info) {
			$server_key = ($server_info->server_key) ? $server_info->server_key : '';
			$sql_values .= "('global', '{$server_info->server_id}', 'keys', '$server_key', '{$server_info->server_status}'), ";
			foreach ($config_opts as $option) {
				$sql_values .= "('global', '{$server_info->server_id}', '$option', '', '{$server_info->server_status}'), ";
			}
		}

		$sql_values = rtrim($sql_values, ', ');

		$inserts[] = "$sql_insert $sql_fields VALUES $sql_values";
	}
	$inserts[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` DROP `server_key`";
	
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

	setOption('version', '3.3', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 3.3.3 */
function upgradefmDNS_333($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '3.3', '<') ? upgradefmDNS_330($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` DROP `server_key`";
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}config", 'server_id')) {
		$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` ADD `server_id` INT(11) NOT NULL DEFAULT '0' AFTER `cfg_type`";
	}
	
	/** Run queries */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
		}
	}

	setOption('version', '3.3.3', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 3.3.4 */
function upgradefmDNS_334($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '3.3.3', '<') ? upgradefmDNS_333($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_zone_support` = 'M' WHERE `def_option` IN ('allow-update', 'check-dup-records', 'check-integrity', 'check-mx', 'check-mx-cname', 'check-sibling', 'check-srv-cname', 'check-wildcard', 'dnssec-secure-to-insecure', 'max-zone-ttl')";
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_zone_support` = 'S' WHERE `def_option` IN ('allow-update-forwarding', 'request-expire')";
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_zone_support` = 'MS' WHERE `def_option` IN ('also-notify', 'alt-transfer-source', 'alt-transfer-source-v6', 'masterfile-style', 'max-transfer-idle-out', 'max-transfer-time-out', 'notify-source', 'notify-source-v6')";
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_zone_support` = 'MSF' WHERE `def_option` IN ('forwarders', 'forward')";
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_zone_support` = 'F' WHERE `def_option` = 'delegation-only'";
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_multiple_values` = 'yes' WHERE `def_option` = 'cookie-secret'";
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_type` = '( master | slave | response | secondary ) ( warn | fail | ignore )' WHERE `def_option` = 'check-names'";
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_clause_support` = 'Z' WHERE `def_option` = 'inline-signing'";
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_type` = '( yes | no | primary | master | secondary | slave )' WHERE `def_option` = 'ixfr-from-differences'";
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_zone_support` = NULL WHERE `def_option` IN ('transfers-in', 'transfers-out', 'transfers-per-ns')";

	$table[] = <<<INSERTSQL
INSERT IGNORE INTO  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` (
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
('options', 'answer-cookie', '( yes | no )', 'no', 'O', NULL, 'yes', '9.11.4'),
('options', 'cleaning-interval', '( minutes )', 'no', 'OV', NULL, 'no', NULL),
('options', 'dlz', '( quoted_string )', 'no', 'Z', 'MS', 'no', NULL),
('options', 'fstrm-set-buffer-hint', '( integer )', 'no', 'O', NULL, 'no', '9.11.0'),
('options', 'fstrm-set-flush-timeout', '( integer )', 'no', 'O', NULL, 'no', '9.11.0'),
('options', 'fstrm-set-input-queue-size', '( integer )', 'no', 'O', NULL, 'no', '9.11.0'),
('options', 'fstrm-set-output-notify-threshold', '( integer )', 'no', 'O', NULL, 'no', '9.11.0'),
('options', 'fstrm-set-output-queue-model', '( mpsc | spsc )', 'no', 'O', NULL, 'yes', '9.11.0'),
('options', 'fstrm-set-output-queue-size', '( integer )', 'no', 'O', NULL, 'no', '9.11.0'),
('options', 'fstrm-set-reopen-interval', '( integer )', 'no', 'O', NULL, 'no', '9.11.0'),
('options', 'glue-cache', '( yes | no )', 'no', 'O', NULL, 'yes', '9.13.0'),
('options', 'lmdb-mapsize', '( size_spec )', 'no', 'O', NULL, 'no', '9.11.2'),
('options', 'max-records', '( integer )', 'no', 'Z', 'MS', 'no', NULL),
('options', 'min-cache-ttl', '( seconds )', 'no', 'OV', NULL, 'no', '9.14.0'),
('options', 'min-ncache-ttl', '( seconds )', 'no', 'OV', NULL, 'no', '9.14.0'),
('options', 'max-stale-ttl', '( integer )', 'no', 'O', NULL, 'no', '9.12.1'),
('options', 'new-zones-directory', '( quoted_string )', 'no', 'O', NULL, 'no', '9.12.1'),
('options', 'nxdomain-redirect', '( quoted_string )', 'no', 'O', NULL, 'no', '9.11.2'),
('options', 'qname-minimization', '( strict | relaxed | disabled | off )', 'no', 'O', NULL, 'yes', '9.14.0'),
('options', 'request-expire', '( yes | no )', 'no', 'O', NULL, 'yes', '9.11.2'),
('options', 'resolver-nonbackoff-tries', '( integer )', 'no', 'O', NULL, 'no', '9.12.1'),
('options', 'resolver-retry-interval', '( integer )', 'no', 'O', NULL, 'no', '9.12.1'),
('options', 'response-padding', '( address_match_element ) [ block-size integer ]', 'no', 'O', NULL, 'no', '9.12.0'),
('options', 'root-key-sentinel', '( yes | no )', 'no', 'O', 'M', 'yes', '9.11.4'),
('options', 'stale-answer-enable', '( yes | no )', 'no', 'O', NULL, 'yes', '9.12.1'),
('options', 'stale-answer-ttl', '( integer )', 'no', 'O', NULL, 'no', '9.12.1'),
('options', 'synth-from-dnssec', '( yes | no )', 'no', 'O', NULL, 'yes', '9.12.0'),
('options', 'tcp-advertised-timeout', '( integer )', 'no', 'O', NULL, 'no', '9.12.0'),
('options', 'tcp-idle-timeout', '( integer )', 'no', 'O', NULL, 'no', '9.12.0'),
('options', 'tcp-initial-timeout', '( integer )', 'no', 'O', NULL, 'no', '9.12.0'),
('options', 'tcp-keepalive-timeout', '( integer )', 'no', 'O', NULL, 'no', '9.12.0'),
('options', 'tkey-gssapi-keytab', '( quoted_string )', 'no', 'O', NULL, 'no', NULL)
INSERTSQL;
	
	/** Run queries */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
		}
	}

	setOption('version', '3.3.4', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 3.4.0 */
function upgradefmDNS_340($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '3.3.4', '<') ? upgradefmDNS_334($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` CHANGE `server_type` `server_type` ENUM('bind9','remote') NOT NULL DEFAULT 'bind9'";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` CHANGE `record_type` `record_type` ENUM('A','AAAA','CAA','CERT','CNAME','DHCID','DLV','DNAME','DNSKEY','DS','HINFO','KEY','KX','MX','NAPTR','NS','OPENPGPKEY','PTR','RP','SRV','TLSA','TXT','SSHFP') NOT NULL DEFAULT 'A'";
	
	/** Run queries */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
		}
	}

	setOption('version', '3.4', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 4.0.0-beta1 */
function upgradefmDNS_4001($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '3.4.0', '<') ? upgradefmDNS_340($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", 'domain_check_config')) {
		$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` ADD `domain_check_config` ENUM('yes','no') NOT NULL DEFAULT 'yes' AFTER `domain_dnssec_signed`";
	}
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` CHANGE `record_type` `record_type` ENUM('A','AAAA','CAA','CERT','CNAME','DHCID','DLV','DNAME','DNSKEY','DS','HINFO','KEY','KX','MX','NAPTR','NS','OPENPGPKEY','PTR','RP','SSHFP','SRV','TLSA','TXT','URL') NOT NULL DEFAULT 'A'";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` CHANGE `server_type` `server_type` ENUM('bind9','remote','url-only') NOT NULL DEFAULT 'bind9'";
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}servers", 'server_url_server_type')) {
		$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` ADD `server_url_server_type` ENUM('httpd','lighttpd','nginx') NULL DEFAULT NULL AFTER `server_config_file`, ADD `server_url_config_file` VARCHAR(255) NULL DEFAULT NULL AFTER `server_url_server_type`";
	}
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` CHANGE `server_update_method` `server_update_method` ENUM('http','https','cron','ssh') NULL DEFAULT NULL";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` CHANGE `server_update_port` `server_update_port` INT(5) NULL DEFAULT NULL";
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}servers", 'server_menu_display')) {
		$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` ADD `server_menu_display` ENUM('include','exclude') NOT NULL DEFAULT 'include' AFTER `server_client_version`";
	}
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` SET `server_menu_display`='exclude' WHERE `server_type`='remote'";
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` CHANGE `def_option_type` `def_option_type` ENUM('global','ratelimit','rrset','rpz') NOT NULL DEFAULT 'global'";
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}config", 'cfg_order_id')) {
		$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` ADD `cfg_order_id` INT(11) NOT NULL DEFAULT '0' AFTER `cfg_parent`";
	}
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", 'domain_dnssec_sign_inline')) {
		$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` ADD `domain_dnssec_sign_inline` ENUM('yes','no') NOT NULL DEFAULT 'no' AFTER `domain_dnssec_signed`";
	}

	$table[] = <<<INSERTSQL
INSERT IGNORE INTO  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` (
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
('options', 'rpz', 'recursive-only', '( yes | no )', 'no', 'OV', 'yes', '9.10.0'),
('options', 'rpz', 'max-policy-ttl', '( integer )', 'no', 'OV', 'no', '9.10.0'),
('options', 'rpz', 'break-dnssec', '( yes | no )', 'no', 'OV', 'yes', '9.10.0'),
('options', 'rpz', 'min-ns-dots', '( integer )', 'no', 'OV', 'no', '9.10.0'),
('options', 'rpz', 'qname-wait-recurse', '( yes | no )', 'no', 'OV', 'yes', '9.10.0'),
('options', 'rpz', 'nsip-wait-recurse', '( yes | no )', 'no', 'OV', 'yes', '9.10.0')
;
INSERTSQL;
	
	/** Run queries */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
		}
	}

	setOption('version', '4.0.0-beta1', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 4.0.0 */
function upgradefmDNS_400($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '4.0.0-beta1', '<') ? upgradefmDNS_4001($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$queries[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` CHANGE `record_type` `record_type` ENUM('A','AAAA','CAA','CERT','CNAME','DHCID','DLV','DNAME','DNSKEY','DS','HINFO','KEY','KX','MX','NAPTR','NS','OPENPGPKEY','PTR','RP','SMIMEA','SSHFP','SRV','TLSA','TXT','URI','URL') NOT NULL DEFAULT 'A'";

	/** Run queries */
	if (count($queries) && $queries[0]) {
		foreach ($queries as $schema) {
			$fmdb->query($schema);
		}
	}

	setOption('version', '4.0.0', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 5.0.0 */
function upgradefmDNS_500($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '4.0.0', '<') ? upgradefmDNS_400($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$queries[] = <<<INSERTSQL
INSERT IGNORE INTO  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` (
`def_function` ,
`def_option` ,
`def_type` ,
`def_multiple_values` ,
`def_clause_support`,
`def_dropdown`,
`def_minimum_version`
)
VALUES 
('options', 'validate-except', '( domain_select )', 'yes', 'O', 'no', '9.13.3')
;
INSERTSQL;
	$queries[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` CHANGE `record_type` `record_type` ENUM('A','AAAA','CAA','CERT','CNAME','DHCID','DLV','DNAME','DNSKEY','DS','HINFO','KEY','KX','MX','NAPTR','NS','OPENPGPKEY','PTR','RP','SMIMEA','SSHFP','SRV','TLSA','TXT','URI','URL','CUSTOM') NOT NULL DEFAULT 'A'";

	/** Run queries */
	if (count($queries) && $queries[0]) {
		foreach ($queries as $schema) {
			$fmdb->query($schema);
		}
	}

	setOption('version', '5.0.0', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 5.1.0 */
function upgradefmDNS_510($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '5.0.0', '<') ? upgradefmDNS_500($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", 'domain_ttl')) {
		$queries[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` ADD `domain_ttl` VARCHAR(50) DEFAULT NULL AFTER `domain_template_id`";
	}

	/** Run queries */
	if (count($queries) && $queries[0]) {
		foreach ($queries as $schema) {
			$fmdb->query($schema);
		}
	}

	setOption('version', '5.1.0', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 5.2.0 */
function upgradefmDNS_520($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '5.1.0', '<') ? upgradefmDNS_510($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}servers", 'server_slave_zones_dir')) {
		$queries[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` ADD `server_slave_zones_dir` VARCHAR(255) NULL DEFAULT NULL AFTER `server_zones_dir`";
	}

	/** Run queries */
	if (count($queries) && $queries[0]) {
		foreach ($queries as $schema) {
			$fmdb->query($schema);
		}
	}

	setOption('version', '5.2.0', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 5.3.0 */
function upgradefmDNS_530($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '5.2.0', '<') ? upgradefmDNS_520($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_type` = '( warn | fail | ignore )' WHERE `def_option` = 'check-names' AND `def_clause_support`='Z'";

	/** Fix any incorrect check-names configs */
	$query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` WHERE `cfg_name`='check-names' AND `domain_id`!=0";
	$fmdb->query($query);
	if ($fmdb->num_rows) {
		foreach ($fmdb->last_result as $result) {
			$check_names = explode(' ', $result->cfg_data);
			$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` SET `cfg_data`='" . $check_names[count($check_names)-1] . "' WHERE `cfg_id`=" . $result->cfg_id;
		}
	}

	/** Run queries */
	if (count($queries) && $queries[0]) {
		foreach ($queries as $schema) {
			$fmdb->query($schema);
		}
	}

	setOption('version', '5.3.0', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 6.0.1 */
function upgradefmDNS_601($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '5.3.0', '<') ? upgradefmDNS_530($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	/** Fix bad 6.0.0 upgrades */
	if (version_compare($running_version, '6.0.0', '=')) {
		$queries[] = "DELETE FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` WHERE `def_function` IN('http','tls','dnssec-policy','')";
		$queries[] = "DELETE FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` WHERE `def_option` IN ('checkds','padding','tcp-keepalive')";
		$queries[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` CHANGE  `def_function` `def_function` ENUM('options', 'logging', 'key', 'view', 'http', 'tls', 'dnssec-policy')  NOT NULL";
		$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` SET `cfg_status`='deleted' WHERE `cfg_type`='ratelimit' AND `domain_id`!=0";
		$queries[] = <<<TABLESQL
	CREATE TABLE IF NOT EXISTS `fm_{$__FM_CONFIG['fmDNS']['prefix']}files` (
	  `file_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	  `account_id` int(11) NOT NULL DEFAULT '1',
	  `server_serial_no` varchar(255) NOT NULL DEFAULT '0',
	  `file_location` ENUM('\$ROOT') NOT NULL DEFAULT '\$ROOT',
	  `file_name` VARCHAR(255) NOT NULL,
	  `file_contents` text,
	  `file_comment` text,
	  `file_status` ENUM( 'active', 'disabled', 'deleted') NOT NULL DEFAULT 'active'
	) ENGINE = MYISAM DEFAULT CHARSET=utf8;
TABLESQL;
$queries[] = <<<INSERTSQL
INSERT IGNORE INTO  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` (
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
('options', 'checkds', '( explicit | integer )', 'no', 'Z', 'PS', 'no', '9.19.12'),
('options', 'padding', '( integer )', 'no', 'S', NULL, 'no', '9.16.0'),
('options', 'tcp-keepalive', '( integer )', 'no', 'OS', NULL, 'no', '9.16.0'),
('tls', 'cert-file', '( quoted_string )', 'no', 'T', NULL, 'no', '9.18.0'),
('tls', 'key-file', '( quoted_string )', 'no', 'T', NULL, 'no', '9.18.0'),
('tls', 'ca-file', '( quoted_string )', 'no', 'T', NULL, 'no', '9.18.0'),
('tls', 'dhparam-file', '( quoted_string )', 'no', 'T', NULL, 'no', '9.18.0'),
('tls', 'ciphers', '( quoted_string )', 'no', 'T', NULL, 'no', '9.18.0'),
('tls', 'prefer-server-ciphers', '( yes | no )', 'no', 'T', NULL, 'yes', '9.18.0'),
('tls', 'protocols', '( quoted_string )', 'yes', 'T', NULL, 'no', '9.18.0'),
('tls', 'remote-hostname', '( quoted_string )', 'no', 'T', NULL, 'no', '9.18.0'),
('tls', 'session-tickets', '( yes | no )', 'no', 'T', NULL, 'yes', '9.18.0'),
('http', 'endpoints', '( quoted_string )', 'yes', 'H', NULL, 'no', '9.18.0'),
('http', 'listener-clients', '( integer )', 'no', 'H', NULL, 'no', '9.18.0'),
('http', 'streams-per-connection', '( integer )', 'no', 'H', NULL, 'no', '9.18.0'),
('dnssec-policy', 'cdnskey', '( yes | no )', 'no', 'D', NULL, 'yes', '9.16.0'),
('dnssec-policy', 'cds-digest-types', '( string )', 'yes', 'D', NULL, 'no', '9.16.0'),
('dnssec-policy', 'dnskey-ttl', '( duration )', 'no', 'D', NULL, 'no', '9.16.0'),
('dnssec-policy', 'inline-signing', '( yes | no )', 'no', 'D', NULL, 'yes', '9.16.0'),
('dnssec-policy', 'max-zone-ttl', '( duration )', 'no', 'D', NULL, 'no', '9.16.0'),
('dnssec-policy', 'nsec3param', '( [ iterations <integer> ] [ optout <boolean> ] [ salt-length <integer> ] )', 'no', 'D', NULL, 'no', '9.16.0'),
('dnssec-policy', 'parent-ds-ttl', '( duration )', 'no', 'D', NULL, 'no', '9.16.0'),
('dnssec-policy', 'parent-propagation-delay', '( duration )', 'no', 'D', NULL, 'no', '9.16.0'),
('dnssec-policy', 'publish-safety', '( duration )', 'no', 'D', NULL, 'no', '9.16.0'),
('dnssec-policy', 'purge-keys', '( duration )', 'no', 'D', NULL, 'no', '9.16.0'),
('dnssec-policy', 'retire-safety', '( duration )', 'no', 'D', NULL, 'no', '9.16.0'),
('dnssec-policy', 'signatures-refresh', '( duration )', 'no', 'D', NULL, 'no', '9.16.0'),
('dnssec-policy', 'signatures-validity', '( duration )', 'no', 'D', NULL, 'no', '9.16.0'),
('dnssec-policy', 'signatures-validity-dnskey', '( duration )', 'no', 'D', NULL, 'no', '9.16.0'),
('dnssec-policy', 'zone-propagation-delay', '( duration )', 'no', 'D', NULL, 'no', '9.16.0')
;
INSERTSQL;
		$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_zone_support` = REPLACE(def_zone_support, 'M', 'P')";
		$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_clause_support` = 'OS' WHERE `def_option_type` = 'tcp-keepalive'";

		/** Run queries */
		if (count($queries) && $queries[0]) {
			foreach ($queries as $schema) {
				$fmdb->query($schema);
			}
		}
	} else {
		/** Clean 6.0.0 upgrades */
		$queries[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` CHANGE  `def_function` `def_function` ENUM('options', 'logging', 'key', 'view', 'http', 'tls', 'dnssec-policy')  NOT NULL";
		$queries[] = <<<INSERTSQL
INSERT IGNORE INTO  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` (
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
('options', 'check-svcb', '( yes | no )', 'no', 'OVZ', 'P', 'yes', '9.19.6'),
('options', 'checkds', '( explicit | integer )', 'no', 'Z', 'PS', 'no', '9.19.12'),
('options', 'dnskey-sig-validity', '( integer )', 'no', 'OVZ', 'P', 'no', '9.16.0'),
('options', 'dnsrps-enable', '( yes | no )', 'no', 'OV', NULL, 'yes', '9.16.0'),
('options', 'dnsrps-options', '( quoted_string )', 'yes', 'OV', NULL, 'no', '9.16.0'),
('options', 'http-listener-clients', '( integer )', 'no', 'O', NULL, 'no', '9.18.0'),
('options', 'http-port', '( integer )', 'no', 'O', NULL, 'no', '9.18.0'),
('options', 'http-streams-per-connection', '( integer )', 'no', 'O', NULL, 'no', '9.18.0'),
('options', 'https-port', '( integer )', 'no', 'O', NULL, 'no', '9.18.0'),
('options', 'ipv4only-contact', '( quoted_string )', 'no', 'OV', NULL, 'no', '9.18.0'),
('options', 'ipv4only-enable', '( yes | no )', 'no', 'OV', NULL, 'yes', '9.18.0'),
('options', 'ipv4only-server', '( quoted_string )', 'no', 'OV', NULL, 'no', '9.18.0'),
('options', 'max-ixfr-ratio', '( unlimited | percentage )', 'no', 'OVZ', 'P', 'no', '9.16.0'),
('options', 'padding', '( integer )', 'no', 'S', NULL, 'no', '9.16.0'),
('options', 'reuseport', '( yes | no )', 'no', 'O', NULL, 'no', '9.18.0'),
('options', 'stale-answer-client-timeout', '( diabled | off | integer )', 'no', 'OV', NULL, 'no', '9.16.0'),
('options', 'stale-cache-enable', '( yes | no )', 'no', 'OV', NULL, 'yes', '9.16.0'),
('options', 'stale-refresh-time', '( integer )', 'no', 'OV', NULL, 'no', '9.16.0'),
('options', 'tcp-keepalive', '( integer )', 'no', 'OS', NULL, 'no', '9.16.0'),
('options', 'tcp-receive-buffer', '( integer )', 'no', 'O', NULL, 'no', '9.18.0'),
('options', 'tcp-send-buffer', '( integer )', 'no', 'O', NULL, 'no', '9.18.0'),
('options', 'tls-port', '( integer )', 'no', 'O', NULL, 'no', '9.19.0'),
('options', 'udp-receive-buffer', '( integer )', 'no', 'O', NULL, 'no', '9.18.0'),
('options', 'udp-send-buffer', '( integer )', 'no', 'O', NULL, 'no', '9.18.0'),
('options', 'update-quota', '( integer )', 'no', 'O', NULL, 'no', '9.19.9'),
('tls', 'cert-file', '( quoted_string )', 'no', 'T', NULL, 'no', '9.18.0'),
('tls', 'key-file', '( quoted_string )', 'no', 'T', NULL, 'no', '9.18.0'),
('tls', 'ca-file', '( quoted_string )', 'no', 'T', NULL, 'no', '9.18.0'),
('tls', 'dhparam-file', '( quoted_string )', 'no', 'T', NULL, 'no', '9.18.0'),
('tls', 'ciphers', '( quoted_string )', 'no', 'T', NULL, 'no', '9.18.0'),
('tls', 'prefer-server-ciphers', '( yes | no )', 'no', 'T', NULL, 'yes', '9.18.0'),
('tls', 'protocols', '( quoted_string )', 'yes', 'T', NULL, 'no', '9.18.0'),
('tls', 'remote-hostname', '( quoted_string )', 'no', 'T', NULL, 'no', '9.18.0'),
('tls', 'session-tickets', '( yes | no )', 'no', 'T', NULL, 'yes', '9.18.0'),
('http', 'endpoints', '( quoted_string )', 'yes', 'H', NULL, 'no', '9.18.0'),
('http', 'listener-clients', '( integer )', 'no', 'H', NULL, 'no', '9.18.0'),
('http', 'streams-per-connection', '( integer )', 'no', 'H', NULL, 'no', '9.18.0'),
('options', 'dnssec-policy', '( default | insecure | none )', 'no', 'OVZ', 'PS', 'yes', '9.16.0'),
('dnssec-policy', 'cdnskey', '( yes | no )', 'no', 'D', NULL, 'yes', '9.16.0'),
('dnssec-policy', 'cds-digest-types', '( string )', 'yes', 'D', NULL, 'no', '9.16.0'),
('dnssec-policy', 'dnskey-ttl', '( duration )', 'no', 'D', NULL, 'no', '9.16.0'),
('dnssec-policy', 'inline-signing', '( yes | no )', 'no', 'D', NULL, 'yes', '9.16.0'),
('dnssec-policy', 'max-zone-ttl', '( duration )', 'no', 'D', NULL, 'no', '9.16.0'),
('dnssec-policy', 'nsec3param', '( [ iterations <integer> ] [ optout <boolean> ] [ salt-length <integer> ] )', 'no', 'D', NULL, 'no', '9.16.0'),
('dnssec-policy', 'parent-ds-ttl', '( duration )', 'no', 'D', NULL, 'no', '9.16.0'),
('dnssec-policy', 'parent-propagation-delay', '( duration )', 'no', 'D', NULL, 'no', '9.16.0'),
('dnssec-policy', 'publish-safety', '( duration )', 'no', 'D', NULL, 'no', '9.16.0'),
('dnssec-policy', 'purge-keys', '( duration )', 'no', 'D', NULL, 'no', '9.16.0'),
('dnssec-policy', 'retire-safety', '( duration )', 'no', 'D', NULL, 'no', '9.16.0'),
('dnssec-policy', 'signatures-refresh', '( duration )', 'no', 'D', NULL, 'no', '9.16.0'),
('dnssec-policy', 'signatures-validity', '( duration )', 'no', 'D', NULL, 'no', '9.16.0'),
('dnssec-policy', 'signatures-validity-dnskey', '( duration )', 'no', 'D', NULL, 'no', '9.16.0'),
('dnssec-policy', 'zone-propagation-delay', '( duration )', 'no', 'D', NULL, 'no', '9.16.0')
;
INSERTSQL;
	
		$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_multiple_values` = 'yes' WHERE `def_option` = 'dnssec-must-be-secure'";
		$queries[] = "DELETE FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` WHERE `def_option` = 'request-expire' AND `def_clause_support`='O";
		$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_multiple_values` = 'yes' WHERE `def_option` = 'dlz'";
		$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_clause_support` = 'OV' WHERE `def_option` IN ('allow-new-zones', 'fetch-quota-params', 
			'fetches-per-server', 'fetches-per-zone', 'lmdb-mapsize', 'max-recursion-depth', 'max-recursion-queries', 'max-stale-ttl', 'message-compression', 'minimal-any', 
			'new-zones-directory', 'no-case-compress', 'nocookie-udp-size', 'nta-lifetime', 'nta-recheck', 'nxdomain-redirect', 'prefetch', 'qname-minimization', 
			'require-server-cookie', 'servfail-ttl', 'stale-answer-enable', 'stale-answer-ttl', 'synth-from-dnssec', 'v6-bias', 'validate-except')";
		$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_clause_support` = 'ZV' WHERE `def_option` = 'max-records'";
		$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_clause_support` = 'OV' WHERE `def_option_type` = 'ratelimit'";

		$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` SET `cfg_status`='deleted' WHERE `cfg_type`='ratelimit' AND `domain_id`!=0";
		if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}masters", 'master_tls_id')) {
			$queries[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}masters` ADD `master_tls_id` INT NOT NULL DEFAULT '0' AFTER `master_key_id`";
		}
		$queries[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` CHANGE `domain_type` `domain_type` ENUM('primary','secondary','master','slave','forward','stub') NOT NULL DEFAULT 'primary'";
		$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` SET `domain_type`='primary' WHERE `domain_type`='master'";
		$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` SET `domain_type`='secondary' WHERE `domain_type`='slave'";
		$queries[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` CHANGE `domain_type` `domain_type` ENUM('primary','secondary','forward','stub') NOT NULL DEFAULT 'primary'";
		$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_option` = 'primaries' WHERE `def_option` = 'masters'";
		$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_zone_support` = 'P' WHERE `def_option` = 'include'";
		$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` SET `cfg_name` = 'primaries' WHERE `cfg_name` = 'masters'";
		$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_zone_support` = REPLACE(def_zone_support, 'M', 'P')";
		$queries[] = <<<TABLESQL
	CREATE TABLE IF NOT EXISTS `fm_{$__FM_CONFIG['fmDNS']['prefix']}files` (
	  `file_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	  `account_id` int(11) NOT NULL DEFAULT '1',
	  `server_serial_no` varchar(255) NOT NULL DEFAULT '0',
	  `file_location` ENUM('\$ROOT') NOT NULL DEFAULT '\$ROOT',
	  `file_name` VARCHAR(255) NOT NULL,
	  `file_contents` text,
	  `file_comment` text,
	  `file_status` ENUM( 'active', 'disabled', 'deleted') NOT NULL DEFAULT 'active'
	) ENGINE = MYISAM DEFAULT CHARSET=utf8;
TABLESQL;
	
		/** Run queries */
		if (count($queries) && $queries[0]) {
			foreach ($queries as $schema) {
				$fmdb->query($schema);
			}
		}
	}

	setOption('version', '6.0.1', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 6.0.3 */
function upgradefmDNS_603($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '6.0.1', '<') ? upgradefmDNS_601($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_type` = '( primary | secondary | response ) ( warn | fail | ignore )',`def_clause_support`='OV',`def_max_parameters`='-1' WHERE `def_option` = 'check-names' AND `def_zone_support` IS NULL";
	$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_type` = '( yes | no | primary | secondary )',`def_clause_support`='OV' WHERE `def_option` = 'ixfr-from-differences'";
	$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_dropdown` = 'yes' WHERE `def_option` = 'masterfile-style'";
	$queries[] = <<<INSERTSQL
	INSERT IGNORE INTO  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` (
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
	('options', 'ixfr-from-differences', '( yes | no )', 'no', 'Z', 'PS', 'yes', NULL)
	;
	INSERTSQL;

	$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` SET `cfg_data` = REPLACE(REPLACE(cfg_data, 'master', 'primary'), 'slave', 'secondary') WHERE `cfg_name`='check-names'";
	$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` SET `cfg_data` = REPLACE(REPLACE(cfg_data, 'master', 'primary'), 'slave', 'secondary') WHERE `cfg_name`='ixfr-from-differences'";
	$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` SET `server_build_config`='yes' WHERE `server_installed`='yes' AND `server_status`='active'";

	/** Run queries */
	if (count($queries) && $queries[0]) {
		foreach ($queries as $schema) {
			$fmdb->query($schema);
		}
	}

	setOption('version', '6.0.3', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 6.1.0 */
function upgradefmDNS_610($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '6.0.3', '<') ? upgradefmDNS_603($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` SET `cfg_name` = '!config_name!' WHERE `cfg_name` IN ('tls-connection', 'http-endpoint')";
	$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_minimum_version` = '9.19.11' WHERE `def_function`='dnssec-policy' AND `def_option` = 'dnskey-ttl'";
	$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_minimum_version` = '9.19.16' WHERE `def_function`='dnssec-policy' AND `def_option` IN ('cdnskey', 'inline-signing')";
	$queries[] = <<<INSERTSQL
INSERT IGNORE INTO  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` (
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
('dnssec-policy', 'signatures-jitter', '( duration )', 'no', 'D', NULL, 'no', '9.18.27')
;
INSERTSQL;

	/** Run queries */
	if (count($queries) && $queries[0]) {
		foreach ($queries as $schema) {
			$fmdb->query($schema);
		}
	}

	setOption('version', '6.1.0', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 6.2.0 */
function upgradefmDNS_620($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '6.1.0', '<') ? upgradefmDNS_610($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_type` = '( yes | no | explicit | primary-only )' WHERE `def_option` = 'notify'";
	$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` SET `cfg_data` = REPLACE(cfg_data, 'master', 'primary') WHERE `cfg_name`='notify'";
	$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` SET `cfg_data` = SUBSTRING_INDEX(cfg_data, ',', 1) WHERE `cfg_name`='keys' AND `server_id`>0";
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}views", 'view_key_id')) {
		$queries[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}views` ADD `view_key_id` INT(11) NOT NULL DEFAULT '0' AFTER `view_name`";
	}
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", 'domain_key_id')) {
		$queries[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` ADD `domain_key_id` INT(11) NULL DEFAULT NULL AFTER `domain_clone_dname`";
	}

	/** Run queries */
	if (count($queries) && $queries[0]) {
		foreach ($queries as $schema) {
			$fmdb->query($schema);
		}
	}

	setOption('version', '6.2.0', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 7.0.0-beta1 */
function upgradefmDNS_700b1($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '6.2.0', '<') ? upgradefmDNS_620($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$queries[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` CHANGE `domain_id` `domain_id` VARCHAR(100) NOT NULL DEFAULT '0'";
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}server_groups", 'group_auto_also_notify')) {
		$queries[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}server_groups` ADD `group_auto_also_notify` ENUM('yes','no') NOT NULL DEFAULT 'no' AFTER `group_name`";
	}
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}servers", 'server_key_with_rndc')) {
		$queries[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` ADD `server_key_with_rndc` ENUM('default','yes','no') NOT NULL DEFAULT 'default' AFTER `server_menu_display`";
	}
	$queries[] = "DELETE FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` WHERE `def_option_type` = 'rpz'";
	$queries[] = <<<INSERTSQL
INSERT IGNORE INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` (
`def_function` ,
`def_option_type`,
`def_option` ,
`def_type` ,
`def_multiple_values` ,
`def_clause_support`,
`def_zone_support`,
`def_dropdown`,
`def_minimum_version`
)
VALUES 
('options', 'rpz', 'add-soa', '( yes | no )', 'no', 'OV', 'all', 'yes', '9.18.0'),
('options', 'rpz', 'max-policy-ttl', '( duration )', 'no', 'OV', 'all', 'no', '9.10.0'),
('options', 'rpz', 'min-update-interval', '( duration )', 'no', 'OV', 'all', 'no', '9.18.0'),
('options', 'rpz', 'recursive-only', '( yes | no )', 'no', 'OV', 'all', 'yes', '9.10.0'),
('options', 'rpz', 'nsip-enable', '( yes | no )', 'no', 'OV', 'all', 'yes', '9.18.0'),
('options', 'rpz', 'nsdname-enable', '( yes | no )', 'no', 'OV', 'all', 'yes', '9.18.0'),
('options', 'rpz', 'break-dnssec', '( yes | no )', 'no', 'OV', 'global', 'yes', '9.10.0'),
('options', 'rpz', 'min-ns-dots', '( integer )', 'no', 'OV', 'global', 'no', '9.10.0'),
('options', 'rpz', 'nsip-wait-recurse', '( yes | no )', 'no', 'OV', 'global', 'yes', '9.10.0'),
('options', 'rpz', 'nsdname-wait-recurse', '( yes | no )', 'no', 'OV', 'global', 'yes', '9.18.0'),
('options', 'rpz', 'qname-wait-recurse', '( yes | no )', 'no', 'OV', 'global', 'yes', '9.10.0'),
('options', 'rpz', 'dnsrps-enable', '( yes | no )', 'no', 'OV', 'global', 'yes', '9.18.0'),
('options', 'rpz', 'dnsrps-options', '( string )', 'no', 'OV', 'global', 'no', '9.18.0'),
('options', 'rpz', 'log', '( yes | no )', 'no', 'OV', 'domain', 'yes', '9.10.0'),
('options', 'rpz', 'ede', '( none | forged | blocked | censored | filtered | prohibited )', 'no', 'OV', 'domain', 'yes', '9.19.0'),
('options', 'rpz', 'policy', '( cname | disabled | drop | given | no-op | nodata | nxdomain | passthru | tcp-only )', 'no', 'OV', 'domain', 'yes', '9.10.0')
INSERTSQL;
	$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` SET `cfg_name`='!config_name!' WHERE `cfg_type`='rpz' AND `cfg_isparent`='yes' AND `cfg_name`='zone'";

	/** Add new RPZ configs */
	$query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` WHERE `cfg_type`='rpz' AND `cfg_isparent`='yes' AND `cfg_status`!='deleted'";
	$fmdb->query($query);
	if ($fmdb->num_rows) {
		foreach ($fmdb->last_result as $result) {
			$queries[] = <<<INSERTSQL
INSERT IGNORE INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` (
`server_serial_no`,
`cfg_type`,
`server_id`,
`view_id`,
`cfg_parent`,
`cfg_name`,
`cfg_data`
)
VALUES
('$result->server_serial_no', 'rpz', '$result->server_id', '$result->view_id', '$result->cfg_id', 'add-soa', ''),
('$result->server_serial_no', 'rpz', '$result->server_id', '$result->view_id', '$result->cfg_id', 'min-update-interval', ''),
('$result->server_serial_no', 'rpz', '$result->server_id', '$result->view_id', '$result->cfg_id', 'nsip-enable', ''),
('$result->server_serial_no', 'rpz', '$result->server_id', '$result->view_id', '$result->cfg_id', 'nsdname-enable', ''),
('$result->server_serial_no', 'rpz', '$result->server_id', '$result->view_id', '$result->cfg_id', 'nsdname-wait-recurse', ''),
('$result->server_serial_no', 'rpz', '$result->server_id', '$result->view_id', '$result->cfg_id', 'dnsrps-enable', ''),
('$result->server_serial_no', 'rpz', '$result->server_id', '$result->view_id', '$result->cfg_id', 'dnsrps-options', ''),
('$result->server_serial_no', 'rpz', '$result->server_id', '$result->view_id', '$result->cfg_id', 'ede', '')
INSERTSQL;
		}
	}

	/** Run queries */
	if (count($queries) && $queries[0]) {
		foreach ($queries as $schema) {
			$fmdb->query($schema);
		}
	}

	setOption('version', '7.0.0-beta1', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 7.0.0-beta2 */
function upgradefmDNS_700b2($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '7.0.0-beta1', '<') ? upgradefmDNS_700b1($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}servers", 'server_address')) {
		$queries[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` ADD `server_address` varchar(255) DEFAULT NULL AFTER `server_name`";
	}
	if (!columnExists("fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", 'domain_comment')) {
		$queries[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` ADD `domain_comment` TEXT NULL DEFAULT NULL AFTER `domain_dnssec_sign_inline`";
	}

	/** Run queries */
	if (isset($queries) && count($queries) && $queries[0]) {
		foreach ($queries as $schema) {
			$fmdb->query($schema);
		}
	}

	/** Delete unused files */
	deleteUnusedFiles(array('pages/config-rpz.php'));

	setOption('version', '7.0.0-beta2', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 7.0.0-beta3 */
function upgradefmDNS_700b3($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '7.0.0-beta2', '<') ? upgradefmDNS_700b2($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$queries[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` CHANGE `record_flags` `record_flags` ENUM('0','128','256','257','','U','S','A','P') NULL DEFAULT NULL";

	/** Run queries */
	if (isset($queries) && count($queries) && $queries[0]) {
		foreach ($queries as $schema) {
			$fmdb->query($schema);
		}
	}

	setOption('version', '7.0.0-beta3', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 7.0.2 */
function upgradefmDNS_702($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '7.0.0-beta3', '<') ? upgradefmDNS_700b3($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$queries[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` CHANGE `record_append` `record_append` enum('yes','no') NOT NULL DEFAULT 'no'";

	/** Run queries */
	if (isset($queries) && count($queries) && $queries[0]) {
		foreach ($queries as $schema) {
			$fmdb->query($schema);
		}
	}

	setOption('version', '7.0.2', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 7.0.5 */
function upgradefmDNS_705($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '7.0.2', '<') ? upgradefmDNS_702($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$queries[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` MODIFY COLUMN record_value MEDIUMTEXT";
	$queries[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDNS']['prefix']}files` MODIFY COLUMN file_contents MEDIUMTEXT";

	/** Run queries */
	if (isset($queries) && count($queries) && $queries[0]) {
		foreach ($queries as $schema) {
			$fmdb->query($schema);
		}
	}

	setOption('version', '7.0.5', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 7.0.6 */
function upgradefmDNS_706($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '7.0.5', '<') ? upgradefmDNS_705($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` SET record_flags='0' WHERE record_type='CAA' AND (record_flags='' OR record_flags IS NULL)";

	/** Run queries */
	if (isset($queries) && count($queries) && $queries[0]) {
		foreach ($queries as $schema) {
			$fmdb->query($schema);
		}
	}

	setOption('version', '7.0.6', 'auto', false, 0, 'fmDNS');
	
	return true;
}

/** 7.1.1 */
function upgradefmDNS_711($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '7.0.6', '<') ? upgradefmDNS_706($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;

	$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET `def_zone_support` = 'P' WHERE `def_option` = 'update-policy'";
	
	/** Run queries */
	if (isset($queries) && count($queries) && $queries[0]) {
		foreach ($queries as $schema) {
			$fmdb->query($schema);
		}
	}

	/** Delete unused files */
	deleteUnusedFiles(array('pages/zone-records-validate.php'));

	setOption('version', '7.1.1', 'auto', false, 0, 'fmDNS');
	
	return true;
}

