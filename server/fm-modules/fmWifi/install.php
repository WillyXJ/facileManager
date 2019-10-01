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
 | fmWifi: Easily manage one or more access points                         |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmwifi/                            |
 +-------------------------------------------------------------------------+
*/

function installfmWifiSchema($database, $module, $noisy = 'noisy') {
	global $fmdb, $fm_name;
	
	/** Include module variables */
	@include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'variables.inc.php');
	
	/** Create fmWifi tables **/
	$table[] = <<<TABLESQL
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
TABLESQL;

	$table[] = <<<TABLESQL
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
TABLESQL;

	$table[] = <<<TABLESQL
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}functions` (
  `def_id` int(11) NOT NULL AUTO_INCREMENT,
  `def_function` enum('options') NOT NULL DEFAULT 'options',
  `def_option_type` enum('global','wlan') NOT NULL DEFAULT 'global',
  `def_option` varchar(255) NOT NULL,
  `def_type` text NOT NULL,
  `def_multiple_values` enum('yes','no') NOT NULL DEFAULT 'no',
  `def_dropdown` enum('yes','no') NOT NULL DEFAULT 'no',
  `def_int_range` varchar(5) NULL DEFAULT NULL,
  `def_minimum_version` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`def_id`),
  KEY `idx_def_option` (`def_option`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;
TABLESQL;

	$table[] = <<<TABLESQL
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
TABLESQL;

	$table[] = <<<TABLESQL
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}server_groups` (
  `group_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `group_name` varchar(128) NOT NULL,
  `group_members` text NOT NULL,
  `group_comment` text,
  `group_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
TABLESQL;

	$table[] = <<<TABLESQL
CREATE TABLE IF NOT EXISTS `$database`.`fm_{$__FM_CONFIG[$module]['prefix']}stats` (
  `account_id` int(11) NOT NULL DEFAULT '1',
  `server_serial_no` int(10) NOT NULL,
  `stat_last_report` INT(10) NOT NULL DEFAULT '0',
  `stat_info` TEXT,
  PRIMARY KEY (`server_serial_no`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
TABLESQL;
	
	$table[] = <<<TABLESQL
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
`def_option`,
`def_type`,
`def_multiple_values`,
`def_dropdown`,
`def_int_range`,
`def_minimum_version`,
`def_option_type`
)
VALUES 
('ssid', '( quoted_string )', 'no', 'no', NULL, NULL, 'wlan'),
('wpa', '( integer )', 'no', 'no', NULL, NULL, 'wlan'),
('channel', '( integer )', 'no', 'no', NULL, NULL, 'wlan'),
('driver', '( hostap | wired | none | nl80211 | bsd )', 'no', 'yes', NULL, NULL, 'wlan'),
('hw_mode', '( a | b | g | ad )', 'no', 'yes', NULL, NULL, 'wlan'),
('ieee80211n', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'wlan'),
('ieee80211ac', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'wlan'),
('ieee80211d', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'wlan'),
('wmm_enabled', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'wlan'),
('auth_algs', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'wlan'),
('ignore_broadcast_ssid', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'wlan'),
('wpa_key_mgmt', '( WPA-PSK | WPA-PSK-SHA256 | WPA-EAP | WPA-EAP-SHA256 )', 'no', 'yes', NULL, NULL, 'wlan'),
('wpa_pairwise', '( CCMP | TKIP | CCMP-256 | GCMP | GCMP-256 )', 'no', 'yes', NULL, NULL, 'wlan'),
('rsn_pairwise', '( CCMP | TKIP | CCMP-256 | GCMP | GCMP-256 )', 'no', 'yes', NULL, NULL, 'wlan'),
('country_code', '( AD | AE | AF | AG | AI | AL | AM | AO | AQ | AR | AS | AT | AU | AW | AX | AZ | BA | BB | BD | BE | BF | BG | BH | BI | BJ | BL | BM | BN | BO | BQ | BR | BS | BT | BV | BW | BY | BZ | CA | CC | CD | CF | CG | CH | CI | CK | CL | CM | CN | CO | CR | CU | CV | CW | CX | CY | CZ | DE | DJ | DK | DM | DO | DZ | EC | EE | EG | EH | ER | ES | ET | FI | FJ | FK | FM | FO | FR | GA | GB | GD | GE | GF | GG | GH | GI | GL | GM | GN | GP | GQ | GR | GS | GT | GU | GW | GY | HK | HM | HN | HR | HT | HU | ID | IE | IL | IM | IN | IO | IQ | IR | IS | IT | JE | JM | JO | JP | KE | KG | KH | KI | KM | KN | KP | KR | KW | KY | KZ | LA | LB | LC | LI | LK | LR | LS | LT | LU | LV | LY | MA | MC | MD | ME | MF | MG | MH | MK | ML | MM | MN | MO | MP | MQ | MR | MS | MT | MU | MV | MW | MX | MY | MZ | NA | NC | NE | NF | NG | NI | NL | NO | NP | NR | NU | NZ | OM | PA | PE | PF | PG | PH | PK | PL | PM | PN | PR | PS | PT | PW | PY | QA | RE | RO | RS | RU | RW | SA | SB | SC | SD | SE | SG | SH | SI | SJ | SK | SL | SM | SN | SO | SR | SS | ST | SV | SX | SY | SZ | TC | TD | TF | TG | TH | TJ | TK | TL | TM | TN | TO | TR | TT | TV | TW | TZ | UA | UG | UM | US | UY | UZ | VA | VC | VE | VG | VI | VN | VU | WF | WS | YE | YT | ZA | ZM | ZW )', 'no', 'yes', NULL, NULL, 'wlan'),
('logger_syslog', '( -1 | 1 | 2 | 4 | 8 | 16 | 32 | 64 )', 'no', 'yes', NULL, NULL, 'global'),
('logger_syslog_level', '( 0 | 1 | 2 | 3 | 4 )', 'no', 'yes', NULL, NULL, 'global'),
('logger_stdout', '( -1 | 1 | 2 | 4 | 8 | 16 | 32 | 64 )', 'no', 'yes', NULL, NULL, 'global'),
('logger_stdout_level', '( 0 | 1 | 2 | 3 | 4 )', 'no', 'yes', NULL, NULL, 'global'),
('local_pwr_constraint', '( integer )', 'no', 'no', '0:255', NULL, 'global'),
('spectrum_mgmt_required', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global'),
('acs_num_scans', '( integer )', 'no', 'no', '1:100', NULL, 'global'),
('acs_chan_bias', '( string )', 'no', 'no', NULL, NULL, 'global'),
('chanlist', '( string )', 'no', 'no', NULL, NULL, 'global'),
('acs_exclude_dfs', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global'),
('beacon_int', '( integer )', 'no', 'no', '15:65535', NULL, 'global'),
('dtim_period', '( integer )', 'no', 'no', '1:255', NULL, 'global'),
('max_num_sta', '( integer )', 'no', 'no', '0:2007', NULL, 'wlan'),
('no_probe_resp_if_max_sta', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'wlan'),
('rts_threshold', '( integer )', 'no', 'no', '-1:65535', NULL, 'global'),
('fragm_threshold', '( integer )', 'no', 'no', '256:2346', NULL, 'global'),
('supported_rates', '( string )', 'no', 'no', NULL, NULL, 'global'),
('basic_rates', '( string )', 'no', 'no', NULL, NULL, 'global'),
('beacon_rate', '( string )', 'no', 'no', NULL, NULL, 'global'),
('preamble', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'wlan'),
('vendor_elements', '( string )', 'no', 'no', NULL, NULL, 'global'),
('assocresp_elements', '( string )', 'no', 'no', NULL, NULL, 'global'),
('multi_ap', '( 0 | 1 | 2 | 3 )', 'no', 'yes', NULL, NULL, 'global'),
('ap_max_inactivity', '( integer )', 'no', 'no', NULL, NULL, 'global'),
('skip_inactivity_poll', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global'),
('disassoc_low_ack', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global'),
('max_listen_interval', '( integer )', 'no', 'no', NULL, NULL, 'global'),
('wds_sta', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global'),
('wds_bridge', '( string )', 'no', 'no', NULL, NULL, 'global'),
('start_disabled', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global'),
('ap_isolate', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global'),
('bss_load_update_period', '( integer )', 'no', 'no', NULL, NULL, 'global'),
('chan_util_avg_period', '( integer )', 'no', 'no', NULL, NULL, 'global'),
('bss_load_test', '( string )', 'no', 'no', NULL, NULL, 'global'),
('multicast_to_unicast', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global'),
('broadcast_deauth', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global'),
('ht_capab', '( string )', 'no', 'no', NULL, NULL, 'global'),
('require_ht', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global'),
('obss_interval', '( integer )', 'no', 'no', NULL, NULL, 'global'),
('vht_capab', '( string )', 'no', 'no', NULL, NULL, 'global'),
('require_vht', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global'),
('vht_oper_chwidth', '( 0 | 1 | 2 | 3 )', 'no', 'yes', NULL, NULL, 'global'),
('vht_oper_centr_freq_seg0_idx', '( integer )', 'no', 'no', NULL, NULL, 'global'),
('vht_oper_centr_freq_seg1_idx', '( integer )', 'no', 'no', NULL, NULL, 'global'),
('use_sta_nsts', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global'),
('ieee8021x', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global'),
('eapol_version', '( integer )', 'no', 'no', NULL, NULL, 'global'),
('eap_message', '( string )', 'no', 'no', NULL, NULL, 'global'),
('eap_reauth_period', '( seconds )', 'no', 'no', NULL, NULL, 'global'),
('use_pae_group_addr', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global'),
('erp_send_reauth_start', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global'),
('erp_domain', '( string )', 'no', 'no', NULL, NULL, 'global'),
('eap_server', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global'),
('eap_user_file', '( string )', 'no', 'no', NULL, NULL, 'global'),
('ca_cert', '( string )', 'no', 'no', NULL, NULL, 'global'),
('server_cert', '( string )', 'no', 'no', NULL, NULL, 'global'),
('private_key', '( string )', 'no', 'no', NULL, NULL, 'global'),
('private_key_passwd', '( string )', 'no', 'no', NULL, NULL, 'global'),
('server_id', '( string )', 'no', 'no', NULL, NULL, 'global'),
('check_crl', '( 0 | 1 | 2 )', 'no', 'yes', NULL, NULL, 'global'),
('check_crl_strict', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global'),
('tls_session_lifetime', '( seconds )', 'no', 'no', NULL, NULL, 'global'),
('tls_flags', '( string )', 'no', 'no', NULL, NULL, 'global'),
('ocsp_stapling_response', '( string )', 'no', 'no', NULL, NULL, 'global'),
('ocsp_stapling_response_multi', '( string )', 'no', 'no', NULL, NULL, 'global'),
('dh_file', '( string )', 'no', 'no', NULL, NULL, 'global'),
('openssl_ciphers', '( string )', 'no', 'no', NULL, NULL, 'global'),
('fragment_size', '( integer )', 'no', 'no', NULL, NULL, 'global'),
('pwd_group', '( integer )', 'no', 'no', NULL, NULL, 'global'),
('eap_sim_db', '( string )', 'no', 'no', NULL, NULL, 'global'),
('eap_sim_db_timeout', '( seconds )', 'no', 'no', NULL, NULL, 'global'),
('pac_opaque_encr_key', '( string )', 'no', 'no', NULL, NULL, 'global'),
('eap_fast_a_id', '( string )', 'no', 'no', NULL, NULL, 'global'),
('eap_fast_a_id_info', '( string )', 'no', 'no', NULL, NULL, 'global'),
('eap_fast_prov', '( 0 | 1 | 2 | 3 )', 'no', 'yes', NULL, NULL, 'global'),
('pac_key_lifetime', '( seconds )', 'no', 'no', NULL, NULL, 'global'),
('pac_key_refresh_time', '( seconds )', 'no', 'no', NULL, NULL, 'global'),
('eap_sim_aka_result_ind', '( integer )', 'no', 'no', NULL, NULL, 'global'),
('tnc', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global'),
('eap_server_erp', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global'),
('iapp_interface', '( string )', 'no', 'no', NULL, NULL, 'global'),
('own_ip_addr', '( ipv4_address | ipv6_address )', 'no', 'no', NULL, NULL, 'global'),
('nas_identifier', '( string )', 'no', 'no', NULL, NULL, 'global'),
('radius_client_addr', '( ipv4_address | ipv6_address )', 'no', 'no', NULL, NULL, 'global'),
('auth_server_addr', '( ipv4_address | ipv6_address )', 'no', 'no', NULL, NULL, 'global'),
('auth_server_port', '( port )', 'no', 'no', NULL, NULL, 'global'),
('auth_server_shared_secret', '( string )', 'no', 'no', NULL, NULL, 'global'),
('acct_server_addr', '( ipv4_address | ipv6_address )', 'no', 'no', NULL, NULL, 'global'),
('acct_server_port', '( port )', 'no', 'no', NULL, NULL, 'global'),
('acct_server_shared_secret', '( string )', 'no', 'no', NULL, NULL, 'global'),
('radius_retry_primary_interval', '( seconds )', 'no', 'no', NULL, NULL, 'global'),
('radius_acct_interim_interval', '( seconds )', 'no', 'no', NULL, NULL, 'global'),
('radius_request_cui', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global'),
('dynamic_vlan', '( 0 | 1 | 2 )', 'no', 'yes', NULL, NULL, 'global'),
('per_sta_vif', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global'),
('vlan_file', '( string )', 'no', 'no', NULL, NULL, 'global'),
('vlan_tagged_interface', '( string )', 'no', 'no', NULL, NULL, 'global'),
('vlan_bridge', '( string )', 'no', 'no', NULL, NULL, 'global'),
('vlan_naming', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global'),
('radius_auth_req_attr', '( string )', 'no', 'no', NULL, NULL, 'global'),
('radius_das_port', '( port )', 'no', 'no', NULL, NULL, 'global'),
('radius_das_client', '( string )', 'no', 'no', NULL, NULL, 'global'),
('radius_das_time_window', '( seconds )', 'no', 'no', NULL, NULL, 'global'),
('radius_das_require_event_timestamp', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global'),
('radius_das_require_message_authenticator', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global'),
('radius_server_clients', '( string )', 'no', 'no', NULL, NULL, 'global'),
('radius_server_auth_port', '( port )', 'no', 'no', NULL, NULL, 'global'),
('radius_server_acct_port', '( port )', 'no', 'no', NULL, NULL, 'global'),
('radius_server_ipv6', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global'),
('ieee80211h', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global')
INSERTSQL;

	$option_name = 'use_ebtables';
	$inserts[] = <<<INSERTSQL
INSERT INTO `$database`.`fm_options` (option_name, option_value, module_name) 
	SELECT '$option_name', '{$__FM_CONFIG[$module]['default']['options'][$option_name]['default_value']}', '$module' FROM DUAL
WHERE NOT EXISTS
	(SELECT option_name FROM `$database`.`fm_options` WHERE option_name = '$option_name'
		AND module_name='$module');
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