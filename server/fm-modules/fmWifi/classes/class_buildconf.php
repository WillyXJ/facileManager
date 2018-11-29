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

require_once(ABSPATH . 'fm-modules/shared/classes/class_buildconf.php');

class fm_module_buildconf extends fm_shared_module_buildconf {
	
	/**
	 * Generates the server config and updates the client
	 *
	 * @since 1.0
	 * @package fmWifi
	 *
	 * @param array $raw_data Array containing files and contents
	 * @return string
	 */
	function buildServerConfig($post_data) {
		global $fmdb, $__FM_CONFIG;
		
		/** Get datetime formatting */
		$date_format = getOption('date_format', $_SESSION['user']['account_id']);
		$time_format = getOption('time_format', $_SESSION['user']['account_id']);
		
		setTimezone();
		
		$files = array();
		$server_serial_no = sanitize($post_data['SERIALNO']);
		extract($post_data);

		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', $server_serial_no, 'server_', 'server_serial_no');
		if ($fmdb->num_rows) {
			$server_result = $fmdb->last_result;
			$data = $server_result[0];
			extract(get_object_vars($data), EXTR_SKIP);
			
			/** Disabled server */
			if ($server_status != 'active') {
				$error = "Server is $server_status.\n";
				if ($compress) echo gzcompress(serialize($error));
				else echo serialize($error);
				
				exit;
			}
			
			include(ABSPATH . 'fm-includes/version.php');
			
			$header = '# This file was built using ' . $_SESSION['module'] . ' ' . $__FM_CONFIG[$_SESSION['module']]['version'] . ' on ' . date($date_format . ' ' . $time_format . ' e') . "\n";

			$function = $server_type . 'BuildConfig';
			$data = $this->$function($header, $server_result[0]);

//			$data->files[$server_config_file] = $config;
//			if (is_array($files)) {
//				$data->files = array_merge($data->files, $files);
//			}
			
			return array(get_object_vars($data), null);
		}
		
		/** Bad server */
		$error = "Server is not found.\n";
		if ($compress) echo gzcompress(serialize($error));
		else echo serialize($error);
	}
	
	
	/**
	 * Builds config for ISC DHCPD
	 *
	 * @since 0.1
	 * @package fmDHCP
	 *
	 * @param string $header Header for each file
	 * @param object $server_data Server data from the database
	 * @param string $type Type of configuration
	 * @return string
	 */
	private function hostapdBuildConfig($header, $server_data, $type = 'global') {
		global $fmdb, $__FM_CONFIG, $fm_module_servers;
		
		$server_data->files[$server_data->server_config_file] = $header;
		
		/** Set interface name */
		if ($server_wlan_interface) {
			$config[] = sprintf("interface=%s\n", $server_wlan_interface);
		}
		/** Set bridge */
		if ($server_mode == 'bridge') {
			$config[] = sprintf("bridge=%s\n", $server_bridge_interface);
		}
		/** Set interface driver */
		if ($server_wlan_driver && $server_mode == 'router') {
			$config[] = sprintf("driver=%s\n", $server_wlan_driver);
		}

		$parent = ($type == 'global') ? 'no' : 'yes';
		if (!isset($fm_module_servers)) {
			if (!class_exists('fm_module_servers')) {
				include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_servers.php');
			}
		}

		if ($assoc_group_ids = ($fm_module_servers->getServerGroupIDs($server_data->server_id))) {
			$config_aps_group_sql = ' OR ';
		}
		foreach(preg_filter('/^/', 'g_', $assoc_group_ids) as $group_id) {
			$config_aps_group_sql .= "config_aps='$group_id' OR config_aps LIKE '$group_id;%' OR config_aps LIKE '%;$group_id;%' OR config_aps LIKE '%;$group_id'";
		}
		$config_aps_sql = " AND (config_aps='0' OR config_aps='s_{$server_data->server_id}' OR config_aps LIKE 's_{$server_data->server_id};%' OR config_aps LIKE '%;s_{$server_data->server_id};%' OR config_aps LIKE '%;s_{$server_data->server_id}' $config_aps_group_sql)";
		
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_id', 'config_', 'AND config_type="' . $type . '" AND config_is_parent="yes" AND config_parent_id=0 AND config_status="active" AND server_serial_no="0"' . $config_aps_sql);
		if ($fmdb->num_rows) {
			$config_result = $fmdb->last_result;
			$count = $fmdb->num_rows;
			for ($i=0; $i < $count; $i++) {
				$config[] = sprintf("\n%s=%s", $config_result[$i]->config_name, $config_result[$i]->config_data);

				if ($config_result[$i]->config_is_parent == 'yes') {
					/** Get details */
					$ssid = $config_result[$i]->config_data;
					basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_id` ASC,`config_name`,`config_data', 'config_', 'AND config_type="' . $type . '" AND config_parent_id="' . $config_result[$i]->config_id . '" AND config_status="active"');
					if ($fmdb->num_rows) {
						$child_result = $fmdb->last_result;
						$count2 = $fmdb->num_rows;
						for ($j=0; $j < $count2; $j++) {
							if ($child_result[$j]->config_data != '') {
								$fmdb->get_results("SELECT * FROM `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}functions` WHERE `def_option`='{$child_result[$j]->config_name}'");
								if ($child_result[$j]->config_name == 'wpa_key_mgmt' && strpos($child_result[$j]->config_data, 'WPA-PSK') !== false) {
									$psk_filename = $this->getPSKFilename($server_data, $ssid);
									$config[] = sprintf('wpa_psk_file=%s', $psk_filename);
									$server_data->files[$psk_filename] = $this->buildPSKFile($header, $server_data, $config_result[$i]->config_id);
								}
								if ($child_result[$j]->config_name == 'wpa_passphrase') continue;
								
								/** Remove params for multiple SSIDs */
								if ($i) {
									if (in_array($child_result[$j]->config_name, array('channel', 'country_code'))) continue;
								}
								
								if (strpos($child_result[$j]->config_data, ';') !== false) {
									$lines = explode(';', $child_result[$j]->config_data);
									foreach ($lines as $line) {
										$config[] = sprintf('%s=%s', $child_result[$j]->config_name, trim($line));
									}
								} else {
									if ($child_result[$j]->config_name == 'auth_algs' && $child_result[$j]->config_data == '1') {
										$child_result[$j]->config_data = "1\nwpa=2";
									}
									$config[] = sprintf('%s=%s', $child_result[$j]->config_name, $child_result[$j]->config_data);
								}
							}
						}
					}
				}
			}
			unset($config_result, $count, $child_result, $count2);
		}
		
		$server_data->files[$server_data->server_config_file] .= join("\n", $config);
		
		return $server_data;
	}


	/**
	 * Figures out what files to update on the client
	 *
	 * @since 1.0
	 * @package fmWifi
	 *
	 * @param array $raw_data Array containing files and contents
	 * @return string
	 */
	function buildCronConfigs($post_data) {
		global $fmdb, $__FM_CONFIG;
		
		$server_serial_no = sanitize($post_data['SERIALNO']);
		extract($post_data);
		
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', $server_serial_no, 'server_', 'server_serial_no');
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			$data = $result[0];
			extract(get_object_vars($data), EXTR_SKIP);
			
			/** Check if this server is configured for cron updates */
			if ($server_update_method != 'cron') {
				$error = "This server is not configured to receive updates via cron.\n";
				if ($compress) echo gzcompress(serialize($error));
				else echo serialize($error);
				return;
			}
			
			/** Check if there are updates */
			if ($server_update_config == 'no') {
				$error = "No updates found.\n";
				if ($compress) echo gzcompress(serialize($error));
				else echo serialize($error);
				return;
			}
			
			/** process server config build */
			return $this->buildServerConfig($post_data);
			
		}
		
		/** Bad server */
		$error = "Server is not found.\n";
		if ($compress) echo gzcompress(serialize($error));
		else echo serialize($error);
	}
	
	
	/**
	 * Validate the daemon version number of the client
	 *
	 * @since 0.1
	 * @package fmWifi
	 *
	 * @param array $data Array containing files and contents
	 * @return boolean
	 */
	function validateDaemonVersion($data) {
		global $__FM_CONFIG;
		extract($data);
		
		if ($server_type == 'hostapd') {
			$required_version = $__FM_CONFIG[$_SESSION['module']]['required_daemon_version'];
		}
		
		/** Get only the version number */
		$server_version = preg_split('/[\s-]+/', $server_version);
		
		if (version_compare($server_version[0], $required_version, '<')) {
			return false;
		}
		
		return true;
	}


	/**
	 * Gets the PSK filename for the server
	 *
	 * @since 0.1
	 * @package fmWifi
	 *
	 * @param object $server_info Server information
	 * @param string $ssid SSID
	 * @return boolean
	 */
	function getPSKFilename($server_info, $ssid) {
		return dirname($server_info->server_config_file) . '/' . $server_info->server_type . '-psk-' . $_SESSION['module'] . '-' . sanitize($ssid, '_');
	}


	/**
	 * Populates the PSK filename for the server
	 *
	 * @since 0.1
	 * @package fmWifi
	 *
	 * @param object $server_info Server information
	 * @param string $wlan_id SSID
	 * @return boolean
	 */
	function buildPSKFile($header, $server_info, $wlan_id) {
		global $fmdb, $__FM_CONFIG;
		
		$config[] = $header;
		
		/** Get user list for SSID */
		$wlan_sql = " AND (wlan_ids='0' OR wlan_ids='$wlan_id' OR wlan_ids LIKE '$wlan_id;%' OR wlan_ids LIKE '%;$wlan_id;%' OR wlan_ids LIKE '%;$wlan_id')";
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'wlan_users', 'wlan_user_id', 'wlan_user_', 'AND wlan_user_status="active"' . $wlan_sql);
		if ($fmdb->num_rows) {
			for ($k=0; $k<$fmdb->num_rows; $k++) {
				$comment = ($fmdb->last_result[$k]->wlan_user_comment) ? ' (' . $fmdb->last_result[$k]->wlan_user_comment . ')' : null;
				$config[] = '# ' . $fmdb->last_result[$k]->wlan_user_login . $comment;
				$config[] = $fmdb->last_result[$k]->wlan_user_mac . ' ' . $fmdb->last_result[$k]->wlan_user_password;
			}
		} else {
			$query = "SELECT config_data FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config WHERE config_status='active' AND account_id='{$_SESSION['user']['account_id']}' AND config_parent_id=$wlan_id AND config_name='wpa_passphrase' LIMIT 1";
			$fmdb->query($query);
			if ($fmdb->num_rows) {
				$config[] = "00:00:00:00:00:00 " . $fmdb->last_result[0]->config_data;
			}
		}
		
		return join("\n", $config);
	}


}

if (!isset($fm_module_buildconf))
	$fm_module_buildconf = new fm_module_buildconf();

?>
