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

function upgradefmDNSSchema($module) {
	global $fmdb;
	
	/** Include module variables */
	@include(dirname(__FILE__) . '/variables.inc.php');
	
	/** Get current version */
	$running_version = getOption($module . '_version', 0);
	
	/** Checks to support older versions (ie n-3 upgrade scenarios */
	$success = version_compare($running_version, '1.0', '<') ? upgradefmDNS_109($__FM_CONFIG, $running_version) : true;
	if (!$success) return 'Failed';
	
	return 'Success';
}

/** 1.0-b5 */
function upgradefmDNS_100($__FM_CONFIG) {
	global $fmdb;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` CHANGE  `record_ttl`  `record_ttl` VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT  '';";
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result) return false;
		}
	}

	return true;
}

/** 1.0-b7 */
function upgradefmDNS_101($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '1.0-b5', '<') ? upgradefmDNS_100($__FM_CONFIG) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` CHANGE  `server_type`  `server_type` ENUM(  'bind9' ) NOT NULL DEFAULT  'bind9',
CHANGE  `server_run_as`  `server_run_as` VARCHAR( 50 ) NULL DEFAULT NULL,
CHANGE  `server_run_as_predefined`  `server_run_as_predefined` ENUM(  'named',  'bind',  'daemon',  'as defined:' ) NOT NULL DEFAULT  'named' ;";
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result) return false;
		}
	}

	return true;
}

/** 1.0-b10 */
function upgradefmDNS_102($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '1.0-b7', '<') ? upgradefmDNS_101($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` CHANGE  `server_run_as_predefined`  `server_run_as_predefined` ENUM(  'named',  'bind',  'daemon',  'root',  'wheel', 'as defined:' ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT  'named';";
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result) return false;
		}
	}

	return true;
}

/** 1.0-b11 */
function upgradefmDNS_103($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '1.0-b10', '<') ? upgradefmDNS_102($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` ADD  `def_multiple_values` ENUM(  'yes',  'no' ) NOT NULL DEFAULT  'no',
ADD  `def_view_support` ENUM(  'yes',  'no' ) NOT NULL DEFAULT  'no';";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` CHANGE  `def_type`  `def_type` VARCHAR( 200 ) NOT NULL ;";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` DROP  `def_id` ;";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` ADD UNIQUE (`def_option`);";
	
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
;";
	
	$updates[] = null;
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result) return false;
		}
	}

	if (count($inserts) && $inserts[0]) {
		foreach ($inserts as $query) {
			$fmdb->query($query);
			if (!$fmdb->result) return false;
		}
	}

	if (count($updates) && $updates[0]) {
		foreach ($updates as $query) {
			$fmdb->query($query);
			if (!$fmdb->result) return false;
		}
	}

	return true;
}

/** 1.0-b13 */
function upgradefmDNS_104($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '1.0-b11', '<') ? upgradefmDNS_103($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` CHANGE  `record_type`  `record_type` ENUM(  'A',  'AAAA',  'CNAME',  'TXT',  'MX',  'PTR',  'SRV',  'NS' ) NOT NULL DEFAULT  'A' ;";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` ENGINE = INNODB;";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}soa` ENGINE = INNODB;";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}track_builds` ENGINE = INNODB;";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}track_reloads` ENGINE = INNODB;";
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result) return false;
		}
	}

	return true;
}

/** 1.0-b14 */
function upgradefmDNS_105($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '1.0-b13', '<') ? upgradefmDNS_104($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` CHANGE  `domain_name`  `domain_name` VARCHAR( 255 ) NOT NULL DEFAULT  '';";
	
	$inserts[] = null;
	
	$updates[] = null;
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result) return false;
		}
	}

	if (count($inserts) && $inserts[0]) {
		foreach ($inserts as $query) {
			$fmdb->query($query);
			if (!$fmdb->result) return false;
		}
	}

	if (count($updates) && $updates[0]) {
		foreach ($updates as $query) {
			$fmdb->query($query);
			if (!$fmdb->result) return false;
		}
	}

	return true;
}

/** 1.0-rc2 */
function upgradefmDNS_106($__FM_CONFIG, $running_version) {
	global $fmdb;
	
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
			if (!$fmdb->result) return false;
		}
	}

	if (count($inserts) && $inserts[0]) {
		foreach ($inserts as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result) return false;
		}
	}

	return true;
}

/** 1.0-rc3 */
function upgradefmDNS_107($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '1.0-rc2', '<') ? upgradefmDNS_106($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` ADD  `server_update_port` INT( 5 ) NOT NULL DEFAULT  '0' AFTER  `server_update_method` ;";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` ADD  `server_os` VARCHAR( 50 ) DEFAULT NULL AFTER  `server_name` ;";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` CHANGE  `server_run_as`  `server_run_as` VARCHAR( 50 ) NULL ;";

	$updates[] = "UPDATE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` SET  `server_update_port` =  '80' WHERE  `server_update_method` = 'http';";
	$updates[] = "UPDATE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` SET  `server_update_port` =  '443' WHERE  `server_update_method` = 'https';";


	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result) return false;
		}
	}

	if (count($updates) && $updates[0]) {
		foreach ($updates as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result) return false;
		}
	}

	return true;
}

/** 1.0-rc6 */
function upgradefmDNS_108($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '1.0-rc3', '<') ? upgradefmDNS_107($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` CHANGE  `server_os`  `server_os_distro` VARCHAR( 50 ) NULL DEFAULT NULL ;";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` ADD  `server_os` VARCHAR( 50 ) NULL DEFAULT NULL AFTER  `server_name` ;";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` CHANGE  `server_run_as_predefined`  `server_run_as_predefined` ENUM(  'named',  'bind',  'daemon',  'as defined:' ) NOT NULL DEFAULT  'named';";

	$inserts = $updates = null;


	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result) return false;
		}
	}

	if (count($inserts) && $inserts[0]) {
		foreach ($inserts as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result) return false;
		}
	}

	if (count($updates) && $updates[0]) {
		foreach ($updates as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result) return false;
		}
	}

	return true;
}

/** 1.0 */
function upgradefmDNS_109($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '1.0-rc6', '<') ? upgradefmDNS_108($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` ADD  `def_dropdown` ENUM(  'yes',  'no' ) NOT NULL DEFAULT  'no';";
	$table[] = "ALTER TABLE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` CHANGE  `server_update_method`  `server_update_method` ENUM(  'http',  'https',  'cron',  'ssh' ) NOT NULL DEFAULT  'http';";

	$inserts = null;
	
	$updates[] = "UPDATE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET  `def_dropdown` =  'yes' WHERE  `def_option` IN ('match-mapped-addresses','transfer-format','check-names','preferred-glue','dialup','notify','forward');";
	$updates[] = "UPDATE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET  `def_dropdown` =  'yes' WHERE  `def_type` =  '( yes | no )';";
	$updates[] = "UPDATE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET  `def_type` =  '( master | slave | response ) ( warn | fail | ignore )' WHERE `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions`.`def_option` =  'check-names';";
	$updates[] = "UPDATE  `fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` SET  `def_type` =  '( port )' WHERE  `def_option` =  'port';";

	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result) return false;
		}
	}

	if (count($inserts) && $inserts[0]) {
		foreach ($inserts as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result) return false;
		}
	}

	if (count($updates) && $updates[0]) {
		foreach ($updates as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result) return false;
		}
	}

	return true;
}


?>