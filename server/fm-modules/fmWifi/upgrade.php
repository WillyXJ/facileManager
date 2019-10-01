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

function upgradefmWifiSchema($module_name) {
	global $fmdb;
	
	/** Include module variables */
	@include(dirname(__FILE__) . '/variables.inc.php');
	
	/** Get current version */
	$running_version = getOption('version', 0, 'fmWifi');
	
	/** Checks to support older versions (ie n-3 upgrade scenarios */
	$success = version_compare($running_version, '0.2.1', '<') ? upgradefmWifi_021($__FM_CONFIG, $running_version) : true;
	if (!$success) return $fmdb->last_error;
	
	setOption('client_version', $__FM_CONFIG['fmWifi']['client_version'], 'auto', false, 0, 'fmWifi');
	
	return true;
}

/** 0.2 */
function upgradefmWifi_02($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	/** Insert upgrade steps here **/
	$table[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmWifi']['prefix']}functions` ADD `def_int_range` VARCHAR(5) NULL DEFAULT NULL AFTER `def_dropdown`";
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmWifi']['prefix']}functions` SET `def_option_type`='wlan'";
	$table[] = "UPDATE `fm_{$__FM_CONFIG['fmWifi']['prefix']}config` SET `config_type`='wlan'";
	$table[] = <<<INSERTSQL
INSERT IGNORE INTO `fm_{$__FM_CONFIG['fmWifi']['prefix']}functions` (
`def_option`,
`def_type`,
`def_multiple_values`,
`def_dropdown`,
`def_int_range`,
`def_minimum_version`,
`def_option_type`
)
VALUES 
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
('radius_server_ipv6', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global')

INSERTSQL;
	
	/** Add new options to all WLANs */
	$fmdb->query("SELECT * FROM fm_{$__FM_CONFIG['fmWifi']['prefix']}config WHERE config_is_parent='yes'");
	if ($fmdb->num_rows) {
		foreach ($fmdb->last_result as $wlan) {
			foreach (array('max_num_sta', 'no_probe_resp_if_max_sta', 'preamble') as $option) {
				$table[] = <<<INSERTSQL
INSERT IGNORE INTO `fm_{$__FM_CONFIG['fmWifi']['prefix']}config` VALUES (
NULL, {$wlan->account_id}, {$wlan->server_serial_no}, 'wlan', 'no', {$wlan->config_id}, '$option', '', '{$wlan->config_aps}', NULL, '{$wlan->config_status}'
)
INSERTSQL;
			}
		}
	}

	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}
	
	setOption('version', '0.2', 'auto', false, 0, 'fmWifi');
	
	return true;
}

/** 0.2 */
function upgradefmWifi_021($__FM_CONFIG, $running_version) {
	global $fmdb, $module_name;
	
	/** Check if previous upgrades have run (to support n+1) **/
	$success = version_compare($running_version, '0.2', '<') ? upgradefmWifi_02($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	/** Insert upgrade steps here **/
	$table[] = <<<INSERTSQL
INSERT IGNORE INTO `fm_{$__FM_CONFIG['fmWifi']['prefix']}functions` (
`def_option`,
`def_type`,
`def_multiple_values`,
`def_dropdown`,
`def_int_range`,
`def_minimum_version`,
`def_option_type`
)
VALUES 
('ieee80211h', '( 0 | 1 )', 'no', 'yes', NULL, NULL, 'global')

INSERTSQL;
	
	/** Create table schema */
	if (count($table) && $table[0]) {
		foreach ($table as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}
	
	setOption('version', '0.2.1', 'auto', false, 0, 'fmWifi');
	
	return true;
}

?>