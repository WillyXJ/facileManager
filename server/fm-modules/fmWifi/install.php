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
 | fmWifi: Brief module description                                      |
 +-------------------------------------------------------------------------+
 | http://URL                                                              |
 +-------------------------------------------------------------------------+
*/

function installfmWifiSchema($database, $module, $noisy = 'noisy') {
	global $fmdb, $fm_name;
	
	/** Include module variables */
	@include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'variables.inc.php');
	
	/** Create fmWifi tables **/
	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}acls` (
  `acl_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `server_serial_no` int(10) NOT NULL,
  `wlan_ids` varchar(255) NOT NULL DEFAULT '0',
  `acl_mac` varchar(20) NOT NULL,
  `acl_action` enum('accept','deny') NOT NULL DEFAULT 'accept',
  `acl_comment` text,
  `acl_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`acl_id`),
  UNIQUE KEY `idx_server_serial_no` (`server_serial_no`)
) ENGINE = MYISAM  DEFAULT CHARSET=utf8;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}config` (
  `config_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `server_serial_no` varchar(255) NOT NULL DEFAULT '0',
  `config_type` enum('global','wlan') NOT NULL DEFAULT 'global',
  `config_is_parent` enum('yes','no') NOT NULL DEFAULT 'no',
  `config_parent_id` int(11) NOT NULL DEFAULT '0',
  `config_name` varchar(50) NOT NULL,
  `config_data` text NOT NULL,
  `config_aps` varchar(255) NOT NULL DEFAULT '0',
  `config_comment` text,
  `config_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`config_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}functions` (
  `def_id` int(11) NOT NULL AUTO_INCREMENT,
  `def_function` enum('options') NOT NULL DEFAULT 'options',
  `def_option_type` enum('global','wlan') NOT NULL DEFAULT 'global',
  `def_option` varchar(255) NOT NULL,
  `def_type` text NOT NULL,
  `def_multiple_values` enum('yes','no') NOT NULL DEFAULT 'no',
  `def_dropdown` enum('yes','no') NOT NULL DEFAULT 'no',
  `def_minimum_version` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`def_id`),
  KEY `idx_def_option` (`def_option`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}servers` (
  `server_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `server_serial_no` int(10) NOT NULL,
  `server_name` varchar(255) NOT NULL,
  `server_os` varchar(50) DEFAULT NULL,
  `server_os_distro` varchar(150) DEFAULT NULL,
  `server_type` enum('hostapd') NOT NULL DEFAULT 'hostapd',
  `server_version` varchar(150) DEFAULT NULL,
  `server_mode` enum('router','bridge') NOT NULL DEFAULT 'router',
  `server_config_file` varchar(255) NOT NULL DEFAULT '/etc/hostapd/hostapd.conf',
  `server_interfaces` text,
  `server_wlan_interface` varchar(50) DEFAULT NULL,
  `server_bridge_interface` varchar(50) DEFAULT NULL,
  `server_wlan_driver` enum('hostap','wired','none','nl80211','bsd') NOT NULL DEFAULT 'hostap',
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
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}server_groups` (
  `group_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `group_name` varchar(128) NOT NULL,
  `group_members` text NOT NULL,
  `group_comment` text,
  `group_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}wlan_users` (
  `wlan_user_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `wlan_user_login` varchar(128) NOT NULL,
  `wlan_user_mac` varchar(20) NOT NULL,
  `wlan_ids` varchar(255) NOT NULL DEFAULT '0',
  `wlan_user_password` varchar(255) NOT NULL,
  `wlan_user_comment` varchar(255) DEFAULT NULL,
  `wlan_user_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`wlan_user_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
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
`def_option`,
`def_type`,
`def_multiple_values`,
`def_dropdown`,
`def_minimum_version`
)
VALUES 
('ssid', '( quoted_string )', 'no', 'no', NULL),
('wpa', '( integer )', 'no', 'no', NULL),
('channel', '( integer )', 'no', 'no', NULL),
('driver', '( hostap | wired | none | nl80211 | bsd )', 'no', 'yes', NULL),
('hw_mode', '( a | b | g | ad )', 'no', 'yes', NULL),
('ieee80211n', '( 0 | 1 )', 'no', 'yes', NULL),
('ieee80211ac', '( 0 | 1 )', 'no', 'yes', NULL),
('ieee80211d', '( 0 | 1 )', 'no', 'yes', NULL),
('wmm_enabled', '( 0 | 1 )', 'no', 'yes', NULL),
('auth_algs', '( 0 | 1 )', 'no', 'yes', NULL),
('ignore_broadcast_ssid', '( 0 | 1 )', 'no', 'yes', NULL),
('wpa_key_mgmt', '( WPA-PSK | WPA-PSK-SHA256 | WPA-EAP | WPA-EAP-SHA256 )', 'no', 'yes', NULL),
('wpa_pairwise', '( CCMP | TKIP | CCMP-256 | GCMP | GCMP-256 )', 'no', 'yes', NULL),
('rsn_pairwise', '( CCMP | TKIP | CCMP-256 | GCMP | GCMP-256 )', 'no', 'yes', NULL),
('country_code', '( AD | AE | AF | AG | AI | AL | AM | AO | AQ | AR | AS | AT | AU | AW | AX | AZ | BA | BB | BD | BE | BF | BG | BH | BI | BJ | BL | BM | BN | BO | BQ | BR | BS | BT | BV | BW | BY | BZ | CA | CC | CD | CF | CG | CH | CI | CK | CL | CM | CN | CO | CR | CU | CV | CW | CX | CY | CZ | DE | DJ | DK | DM | DO | DZ | EC | EE | EG | EH | ER | ES | ET | FI | FJ | FK | FM | FO | FR | GA | GB | GD | GE | GF | GG | GH | GI | GL | GM | GN | GP | GQ | GR | GS | GT | GU | GW | GY | HK | HM | HN | HR | HT | HU | ID | IE | IL | IM | IN | IO | IQ | IR | IS | IT | JE | JM | JO | JP | KE | KG | KH | KI | KM | KN | KP | KR | KW | KY | KZ | LA | LB | LC | LI | LK | LR | LS | LT | LU | LV | LY | MA | MC | MD | ME | MF | MG | MH | MK | ML | MM | MN | MO | MP | MQ | MR | MS | MT | MU | MV | MW | MX | MY | MZ | NA | NC | NE | NF | NG | NI | NL | NO | NP | NR | NU | NZ | OM | PA | PE | PF | PG | PH | PK | PL | PM | PN | PR | PS | PT | PW | PY | QA | RE | RO | RS | RU | RW | SA | SB | SC | SD | SE | SG | SH | SI | SJ | SK | SL | SM | SN | SO | SR | SS | ST | SV | SX | SY | SZ | TC | TD | TF | TG | TH | TJ | TK | TL | TM | TN | TO | TR | TT | TV | TW | TZ | UA | UG | UM | US | UY | UZ | VA | VC | VE | VG | VI | VN | VU | WF | WS | YE | YT | ZA | ZM | ZW )', 'no', 'yes', NULL)
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