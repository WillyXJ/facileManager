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
 | fmFirewall: Easily manage one or more software firewalls                |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmfirewall/                        |
 +-------------------------------------------------------------------------+
*/

function installfmFirewallSchema($link = null, $database, $module, $noisy = 'noisy') {
	global $fm_name;
	
	/** Include module variables */
	@include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'variables.inc.php');
	
	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG[$module]['prefix']}groups` (
  `group_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `group_type` enum('object','service') NOT NULL,
  `group_name` varchar(255) NOT NULL,
  `group_items` text NOT NULL,
  `group_comment` text,
  `group_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`group_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG[$module]['prefix']}objects` (
  `object_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `object_type` enum('host','network') NOT NULL,
  `object_name` varchar(255) NOT NULL,
  `object_address` varchar(255) NOT NULL,
  `object_mask` varchar(15) NOT NULL,
  `object_comment` text,
  `object_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`object_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG[$module]['prefix']}policies` (
  `policy_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `server_serial_no` int(10) NOT NULL,
  `policy_type` enum('rules','nat') NOT NULL DEFAULT 'rules',
  `policy_order_id` int(11) NOT NULL,
  `policy_interface` varchar(150) NOT NULL DEFAULT 'any',
  `policy_direction` enum('in','out') NOT NULL DEFAULT 'in',
  `policy_action` enum('pass','block','reject') NOT NULL DEFAULT 'pass',
  `policy_source_not` enum('0','1') NOT NULL DEFAULT '0',
  `policy_source` text,
  `policy_destination_not` enum('0','1') NOT NULL DEFAULT '0',
  `policy_destination` text,
  `policy_services_not` enum('0','1') NOT NULL DEFAULT '0',
  `policy_services` text,
  `policy_time` text,
  `policy_options` int(3) NOT NULL DEFAULT '0',
  `policy_comment` text,
  `policy_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`policy_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG[$module]['prefix']}servers` (
  `server_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `server_serial_no` int(10) NOT NULL,
  `server_name` varchar(255) NOT NULL,
  `server_os` varchar(50) DEFAULT NULL,
  `server_os_distro` varchar(150) DEFAULT NULL,
  `server_type` enum('iptables','ipfw','ipfilter','pf') NOT NULL DEFAULT 'iptables',
  `server_version` varchar(150) DEFAULT NULL,
  `server_config_file` varchar(255) NOT NULL DEFAULT '/usr/local/$fm_name/$module/rules.fw',
  `server_interfaces` text,
  `server_update_method` enum('http','https','cron','ssh') NOT NULL DEFAULT 'http',
  `server_update_port` int(5) NOT NULL DEFAULT '0',
  `server_build_config` enum('yes','no') NOT NULL DEFAULT 'no',
  `server_update_config` enum('yes','no') NOT NULL DEFAULT 'no',
  `server_installed` enum('yes','no') NOT NULL DEFAULT 'no',
  `server_client_version` varchar(150) DEFAULT NULL,
  `server_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'disabled',
  PRIMARY KEY (`server_id`),
  UNIQUE KEY `server_serial_no` (`server_serial_no`)
) ENGINE = MYISAM  DEFAULT CHARSET=utf8;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG[$module]['prefix']}services` (
  `service_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `service_type` enum('icmp','tcp','udp') NOT NULL,
  `service_name` varchar(255) NOT NULL,
  `service_icmp_type` int(3) DEFAULT NULL,
  `service_icmp_code` int(3) DEFAULT NULL,
  `service_src_ports` varchar(11) DEFAULT NULL,
  `service_dest_ports` varchar(11) DEFAULT NULL,
  `service_tcp_flags` varchar(5) DEFAULT NULL,
  `service_established` enum('0','1') NOT NULL DEFAULT '0',
  `service_comment` text,
  `service_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`service_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG[$module]['prefix']}time` (
  `time_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `time_name` varchar(255) NOT NULL,
  `time_start_date` date DEFAULT NULL,
  `time_end_date` date DEFAULT NULL,
  `time_start_time` time NOT NULL,
  `time_end_time` time NOT NULL,
  `time_weekdays` int(3) NOT NULL DEFAULT '0',
  `time_comment` text,
  `time_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`time_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;
TABLE;

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

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}objects` (account_id, object_type, object_name, object_address, object_mask, object_comment) 
	SELECT '1', 'host', '$fm_name', '{$_SERVER['SERVER_ADDR']}', '255.255.255.255', '$fm_name Server' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG[$module]['prefix']}objects` WHERE 
	object_type = 'host' AND object_name = '$fm_name' AND account_id = '1'
	);
INSERT;


	/** Default networks */
	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}objects` (account_id, object_type, object_name, object_address, object_mask, object_comment) 
	SELECT '1', 'network', 'net-10.0.0.0', '10.0.0.0', '255.0.0.0', '10.0.0.0/8 - This block is reserved for use in private networks and should not appear on the public Internet. Its intended use is documented in RFC1918.' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG[$module]['prefix']}objects` WHERE 
	object_type = 'network' AND object_name = 'net-10.0.0.0' AND account_id = '1'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}objects` (account_id, object_type, object_name, object_address, object_mask, object_comment) 
	SELECT '1', 'network', 'net-172.16.0.0', '172.16.0.0', '255.240.0.0', '172.16.0.0/12 - This block is reserved for use in private networks and should not appear on the public Internet. Its intended use is documented in RFC1918.' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG[$module]['prefix']}objects` WHERE 
	object_type = 'network' AND object_name = 'net-172.16.0.0' AND account_id = '1'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}objects` (account_id, object_type, object_name, object_address, object_mask, object_comment) 
	SELECT '1', 'network', 'net-192.168.0.0', '192.168.0.0', '255.255.0.0', '192.168.0.0/16 - This block is reserved for use in private networks and should not appear on the public Internet. Its intended use is documented in RFC1918.' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG[$module]['prefix']}objects` WHERE 
	object_type = 'network' AND object_name = 'net-192.168.0.0' AND account_id = '1'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}objects` (account_id, object_type, object_name, object_address, object_mask, object_comment) 
	SELECT '1', 'network', 'All Multicasts', '224.0.0.0', '240.0.0.0', '224.0.0.0/4 - This block, formerly known as the Class D address space, is allocated for use in IPv4 multicast address assignments. The IANA guidelines for assignments from this space are described in RFC3171.' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG[$module]['prefix']}objects` WHERE 
	object_type = 'network' AND object_name = 'All Multicasts' AND account_id = '1'
	);
INSERT;

	$groups[] = array('object',
					array(
						'network|net-10.0.0.0',
						'network|net-172.16.0.0',
						'network|net-192.168.0.0'
					), 'rfc1918', 'RFC1918 networks.'
				);


	/** Default ICMP Services */
	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}services` (account_id, service_type, service_name, service_icmp_type, service_icmp_code) 
	SELECT '1', 'icmp', 'Any ICMP', '-1', '-1' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG[$module]['prefix']}services` WHERE 
	service_type = 'icmp' AND service_name = 'Any ICMP' AND account_id = '1'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}services` (account_id, service_type, service_name, service_icmp_type, service_icmp_code) 
	SELECT '1', 'icmp', 'Ping Reply', '0', '0' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG[$module]['prefix']}services` WHERE 
	service_type = 'icmp' AND service_name = 'Ping Reply' AND account_id = '1'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}services` (account_id, service_type, service_name, service_icmp_type, service_icmp_code) 
	SELECT '1', 'icmp', 'Ping Request', '8', '0' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG[$module]['prefix']}services` WHERE 
	service_type = 'icmp' AND service_name = 'Ping Request' AND account_id = '1'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}services` (account_id, service_type, service_name, service_icmp_type, service_icmp_code) 
	SELECT '1', 'icmp', 'Ping Unreachable', '3', '3' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG[$module]['prefix']}services` WHERE 
	service_type = 'icmp' AND service_name = 'Ping Unreachable' AND account_id = '1'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}services` (account_id, service_type, service_name, service_icmp_type, service_icmp_code) 
	SELECT '1', 'icmp', 'Host Unreachable', '3', '1' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG[$module]['prefix']}services` WHERE 
	service_type = 'icmp' AND service_name = 'Host Unreachable' AND account_id = '1'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}services` (account_id, service_type, service_name, service_icmp_type, service_icmp_code, service_comment) 
	SELECT '1', 'icmp', 'Time Exceeded', '11', '0', 'Traceroute requires this type of ICMP messages.' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG[$module]['prefix']}services` WHERE 
	service_type = 'icmp' AND service_name = 'Time Exceeded' AND account_id = '1'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}services` (account_id, service_type, service_name, service_icmp_type, service_icmp_code) 
	SELECT '1', 'icmp', 'Time Exceeded in Transit', '11', '1' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG[$module]['prefix']}services` WHERE 
	service_type = 'icmp' AND service_name = 'Time Exceeded in Transit' AND account_id = '1'
	);
INSERT;


	/** Default TCP/UDP Services */
	$services[] = array('tcp', 'Any TCP', '', '', NULL, '');
	$services[] = array('udp', 'Any UDP', '', '', NULL, '');
	$services[] = array('tcp', 'High TCP Ports', '', '1024:65535', NULL, '');
	$services[] = array('udp', 'High UDP Ports', '', '1024:65535', NULL, '');
	$services[] = array('tcp', 'ssh', '', '22:22', NULL, '');
	$services[] = array('tcp', 'rdp', '', '3389:3389', NULL, '');
	$services[] = array('tcp', 'http', '', '80:80', NULL, '');
	$services[] = array('tcp', 'https', '', '443:443', NULL, '');
	$services[] = array('tcp', 'mysql', '', '3306:3306', NULL, '');
	$services[] = array('tcp', 'mssql', '', '1433:1433', NULL, '');
	$services[] = array('tcp', 'postgre', '', '5432:5432', NULL, '');
	$services[] = array('tcp', 'domain', '', '53:53', NULL, '');
	$services[] = array('udp', 'domain', '', '53:53', NULL, '');
	$services[] = array('tcp', 'ftp', '', '21:21', NULL, '');
	$services[] = array('tcp', 'ftp-data', '20:20', '1024:65535', NULL, '');
	$services[] = array('tcp', 'ftp-data passive', '', '20:20', NULL, '');
	$services[] = array('tcp', 'smtp', '', '25:25', NULL, '');
	$services[] = array('tcp', 'smtps', '', '465:465', NULL, '');
	$services[] = array('tcp', 'pop3', '', '110:110', NULL, '');
	$services[] = array('tcp', 'pop3s', '', '995:995', NULL, '');
	$services[] = array('tcp', 'imap', '', '143:143', NULL, '');
	$services[] = array('tcp', 'imaps', '', '993:993', NULL, '');
	$services[] = array('tcp', 'squid', '', '3128:3128', NULL, 'Standard proxy server');
	$services[] = array('tcp', 'telnet', '', '23:23', NULL, '');
	$services[] = array('tcp', 'afp', '', '548:548', NULL, 'Apple File Sharing over TCP');
	$services[] = array('tcp', 'nfs', '', '2049:2049', NULL, '');
	$services[] = array('udp', 'nfs', '', '2049:2049', NULL, '');
	$services[] = array('tcp', 'kerberos', '', '88:88', NULL, '');
	$services[] = array('udp', 'kerberos', '', '88:88', NULL, '');
	$services[] = array('udp', 'kerberos-adm', '', '749:750', NULL, '');
	$services[] = array('tcp', 'ldap', '', '389:389', NULL, '');
	$services[] = array('tcp', 'ldaps', '', '636:636', NULL, '');
	$services[] = array('tcp', 'eklogin', '', '2105:2105', NULL, '');
	$services[] = array('tcp', 'klogin', '', '543:543', NULL, '');
	$services[] = array('tcp', 'kpasswd', '', '464:464', NULL, '');
	$services[] = array('tcp', 'krb524', '', '4444:4444', NULL, '');
	$services[] = array('tcp', 'ksh', '', '544:544', NULL, '');
	$services[] = array('udp', 'netbios-ns', '', '137:137', NULL, '');
	$services[] = array('udp', 'netbios-dgm', '', '138:138', NULL, '');
	$services[] = array('tcp', 'netbios-ssn', '', '139:139', NULL, '');
	$services[] = array('udp', 'bootps', '', '67:67', NULL, '');
	$services[] = array('udp', 'bootpc', '', '68:68', NULL, '');
	$services[] = array('tcp', 'smb', '', '445:445', NULL, 'SMB over TCP');
	$services[] = array('udp', 'ntp', '', '123:123', NULL, '');
	$services[] = array('udp', 'snmp', '', '161:161', NULL, '');
	$services[] = array('udp', 'snmp-trap', '', '162:162', NULL, '');
	$services[] = array('udp', 'syslog', '', '514:514', NULL, '');
	$services[] = array('udp', 'tftp', '', '69:69', NULL, '');
	$services[] = array('udp', 'traceroute', '', '33434:33524', NULL, '');
	$services[] = array('udp', 'kerberos', '', '88:88', NULL, '');
	$services[] = array('tcp', 'radius', '', '1812:1812', NULL, 'Radius Protocol');
	$services[] = array('tcp', 'radius acct', '', '1813:1813', NULL, 'Radius Accounting');
	$services[] = array('udp', 'radius', '', '1645:1645', NULL, '');
	$services[] = array('tcp', 'WINS replication', '', '42:42', NULL, '');
	$services[] = array('tcp', 'microsoft-rpc', '', '135:135', NULL, '');
	$services[] = array('udp', 'microsoft-rpc', '', '135:135', NULL, '');
	$services[] = array('tcp', 'sunrpc', '', '111:111', NULL, '');
	$services[] = array('udp', 'sunrpc', '', '111:111', NULL, '');
	$services[] = array('tcp', 'cvsup', '', '5999:5999', NULL, 'CVSup file transfers (FreeBSD uses this)');
	$services[] = array('tcp', 'irc', '', '6667:6667', NULL, '');
	$services[] = array('tcp', 'Christmas Tree', '', '', '63:37', 'Packets that are lit up like a Christmas Tree');

	$groups[] = array('service',
					array(
						'tcp|ssh',
						'tcp|rdp'
					), 'Remote Server Administration', ''
				);
	$groups[] = array('service',
					array(
						'tcp|http',
						'tcp|https'
					), 'Web Server', ''
				);
	$groups[] = array('service',
					array(
						'tcp|domain',
						'udp|domain'
					), 'DNS', ''
				);
	$groups[] = array('service',
					array(
						'tcp|ftp',
						'tcp|ftp-data',
						'tcp|ftp-data passive'
					), 'FTP', ''
				);
	$groups[] = array('service',
					array(
						'tcp|kerberos',
						'udp|kerberos',
						'udp|kerberos-adm',
						'tcp|eklogin',
						'tcp|klogin',
						'tcp|kpasswd',
						'tcp|krb524',
						'tcp|ksh'
					), 'Kerberos', ''
				);
	$groups[] = array('service',
					array(
						'udp|bootps',
						'udp|bootpc'
					), 'DHCP', ''
				);
	$groups[] = array('service',
					array(
						'tcp|nfs',
						'udp|nfs'
					), 'NFS', ''
				);
	$groups[] = array('service',
					array(
						'udp|netbios-ns',
						'udp|netbios-dgm',
						'tcp|netbios-ssn'
					), 'NETBIOS', ''
				);


	foreach ($services as $array) {
		list($protocol, $name, $src_port, $dest_port, $tcp_flags, $comment) = $array;
		
		$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}services` (account_id, service_type, service_name, service_src_ports, service_dest_ports, service_tcp_flags, service_comment) 
	SELECT '1', '$protocol', '$name', '$src_port', '$dest_port', '$tcp_flags', '$comment' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG[$module]['prefix']}services` WHERE 
	service_type = '$protocol' AND service_name = '$name' AND account_id = '1'
	);
INSERT;
	}

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
			'build_server_configs'	=> 'Build Server Configs',
			'manage_policies'		=> 'Policy Management',
			'manage_objects'		=> 'Object Management',
			'manage_services'		=> 'Service Management',
			'manage_time'			=> 'Time Management'
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
	
	/** Process groups */
	foreach ($groups as $array) {
		list($group_type, $item_array, $group_name, $comment) = $array;
		$group_ids = null;
		foreach ($item_array as $item) {
			list($protocol, $name) = explode('|', $item);
			if ($protocol == 'group') {
				if ($link) {
					$query = "SELECT * FROM $database.fm_{$__FM_CONFIG[$module]['prefix']}groups WHERE group_status!='deleted'
								AND account_id=1 AND group_name='$name' LIMIT 1";
					$result = mysql_query($query, $link);
					$temp_result = mysql_fetch_object($result);
				} else {
					basicGet($database . "`.`fm_{$__FM_CONFIG[$module]['prefix']}groups", $name, 'group_', 'group_name', null, 1);
					$temp_result = $fmdb->last_result[0];
				}
				$type_id = 'group_id';
				$prefix = 'g';
			} else {
				if ($link) {
					$query = "SELECT * FROM $database.fm_{$__FM_CONFIG[$module]['prefix']}{$group_type}s WHERE {$group_type}_status!='deleted'
								AND account_id=1 AND {$group_type}_name='$name' AND {$group_type}_type = '$protocol' LIMIT 1";
					$result = mysql_query($query, $link);
					$temp_result = mysql_fetch_object($result);
				} else {
					basicGet($database . "`.`fm_{$__FM_CONFIG[$module]['prefix']}{$group_type}s", $name, $group_type . '_', $group_type . '_name', "AND {$group_type}_type = '$protocol'", 1);
					$temp_result = $fmdb->last_result[0];
				}
				$type_id = $group_type . '_id';
				$prefix = substr($group_type, 0, 1);
			}
			$group_ids[] = $prefix . $temp_result->$type_id;
		}

		$group_items = implode(';', $group_ids);
		$group_inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG[$module]['prefix']}groups` (account_id, group_type, group_name, group_items, group_comment) 
	SELECT '1', '$group_type', '$group_name', '$group_items', '$comment' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG[$module]['prefix']}groups` WHERE 
	group_type = '$group_type' AND group_name = '$group_name' AND account_id = '1'
	);
INSERT;
		
	}

	/** Insert site values if not already present */
	foreach ($group_inserts as $query) {
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