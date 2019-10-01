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
		
		if (@is_object($server_data)) {
			extract(get_object_vars($server_data));
		}
		$server_data->files[$server_data->server_config_file] = $header;
		
		/** Set interface name */
		if ($server_wlan_interface) {
			$config[] = sprintf("interface=%s", $server_wlan_interface);
		}
		/** Set bridge */
		if ($server_mode == 'bridge') {
			$config[] = sprintf("bridge=%s", $server_bridge_interface);
		}
		/** Set interface driver */
		if ($server_wlan_driver && $server_mode == 'router') {
			$config[] = sprintf("driver=%s", $server_wlan_driver);
		}

		$parent = ($type == 'global') ? 'no' : 'yes';
		if (!isset($fm_module_servers)) {
			if (!class_exists('fm_module_servers')) {
				include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_servers.php');
			}
		}

		$assoc_group_ids = $fm_module_servers->getServerGroups($server_data->server_id);
		$config_aps_group_sql = null;
		
		foreach(preg_filter('/^/', 'g_', $assoc_group_ids) as $group_id) {
			$config_aps_group_sql .= " OR config_aps='$group_id' OR config_aps LIKE '$group_id;%' OR config_aps LIKE '%;$group_id;%' OR config_aps LIKE '%;$group_id'";
		}
		$config_aps_sql = " AND (config_aps='0' OR config_aps='s_{$server_data->server_id}' OR config_aps LIKE 's_{$server_data->server_id};%' OR config_aps LIKE '%;s_{$server_data->server_id};%' OR config_aps LIKE '%;s_{$server_data->server_id}' $config_aps_group_sql)";
		
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_id', 'config_', 'AND config_type="wlan" AND config_is_parent="yes" AND config_parent_id=0 AND config_status="active" AND server_serial_no="0"' . $config_aps_sql);
		if ($fmdb->num_rows && !$fmdb->sql_errors) {
			$config_result = $fmdb->last_result;
			$count = $fmdb->num_rows;
			for ($i=0; $i < $count; $i++) {
				$config[] = sprintf("\n%s=%s", $config_result[$i]->config_name, $config_result[$i]->config_data);

				if ($config_result[$i]->config_is_parent == 'yes') {
					/** Get details */
					$ssid = $config_result[$i]->config_data;
					basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_id` ASC,`config_name`,`config_data', 'config_', 'AND config_type="' . $config_result[$i]->config_type . '" AND config_parent_id="' . $config_result[$i]->config_id . '" AND config_status="active"');
					if ($fmdb->num_rows) {
						$child_result = $fmdb->last_result;
						$count2 = $fmdb->num_rows;
						for ($j=0; $j < $count2; $j++) {
							if ($child_result[$j]->config_data != '') {
//								$fmdb->get_results("SELECT * FROM `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}functions` WHERE `def_option`='{$child_result[$j]->config_name}'");
								if ($child_result[$j]->config_name == 'wpa_key_mgmt' && strpos($child_result[$j]->config_data, 'WPA-PSK') !== false) {
									$psk_filename = $this->getPSKFilename($server_data, $ssid);
									$config[] = sprintf('wpa_psk_file=%s', $psk_filename);
									$server_data->files[$psk_filename] = array('contents' => $this->buildPSKFile($header, $server_data, $config_result[$i]->config_id), 'mode' => 0400);
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
									/** WPA2 */
									if ($child_result[$j]->config_name == 'auth_algs' && $child_result[$j]->config_data == '1') {
										$child_result[$j]->config_data = "1\nwpa=2";
									}
								
									/** MAC filtering */
									if ($child_result[$j]->config_name == 'macaddr_acl') {
										$dirname = dirname($server_data->server_config_file);
										$mac_files = array('accept' => $dirname . '/hostapd-accept-' . $_SESSION['module'] . '-' . sanitize($ssid, '_'),
											'deny' => $dirname . '/hostapd-deny-' . $_SESSION['module'] . '-' . sanitize($ssid, '_'));

										$child_result[$j]->config_data = sprintf("%s\naccept_mac_file=%s\ndeny_mac_file=%s",
												$child_result[$j]->config_data,
												$mac_files['accept'], $mac_files['deny']);
										
										$server_data->files = array_merge($server_data->files, $this->buildACLFiles($header, $server_data, $config_result[$i]->config_id, $mac_files));
									}
									$config[] = sprintf('%s=%s', $child_result[$j]->config_name, $child_result[$j]->config_data);
								}
							}
						}
					}
				}
			}
			$first_ssid = $config_result[0]->config_id;
			unset($config_result, $count, $child_result, $count2);
		}
		
		/** Build global configs */
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_id', 'config_', 'AND config_type="' . $type . '" AND config_is_parent="no" AND config_parent_id=0 AND config_status="active" AND server_serial_no="0"' . $config_aps_sql);
		if ($fmdb->num_rows) {
			foreach ($fmdb->last_result as $config_result) {
				$global_config[$config_result->config_name] = array($config_result->config_data, $config_result->config_comment);
			}
		} else $global_config = array();

		$server_config = array();
		/** Override with group-specific configs */
		if (is_array($assoc_group_ids)) {
			basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_id', 'config_', 'AND config_type="' . $type . '" AND config_is_parent="no" AND config_parent_id=0 AND config_status="active" AND server_serial_no IN ("g_' . implode('","g_', $assoc_group_ids) . '")' . $config_aps_sql);
			if ($fmdb->num_rows) {
				foreach ($fmdb->last_result as $server_config_result) {
					$server_config[$server_config_result->config_name] = @array($server_config_result->config_data, $server_config_result->config_comment);
				}
			}
		}

		/** Override with server-specific configs */
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_id', 'config_', 'AND config_type="' . $type . '" AND config_is_parent="no" AND config_parent_id=0 AND config_status="active" AND server_serial_no="' . $server_serial_no . '"' . $config_aps_sql);
		if ($fmdb->num_rows) {
			foreach ($fmdb->last_result as $server_config_result) {
				$server_config[$server_config_result->config_name] = @array($server_config_result->config_data, $server_config_result->config_comment);
			}
		}

		/** Merge arrays */
		$config_array = array_merge($global_config, $server_config);
		unset($global_config, $server_config);

		/** Override with WLAN configs */
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_id', 'config_', 'AND config_type="' . $type . '" AND config_is_parent="no" AND config_parent_id=' . $first_ssid . ' AND config_status="active" AND server_serial_no="0"' . $config_aps_sql);
		if ($fmdb->num_rows) {
			foreach ($fmdb->last_result as $config_result) {
				$global_config[$config_result->config_name] = array($config_result->config_data, $config_result->config_comment);
			}
		} else $global_config = array();

		$server_config = array();
		/** Override with group-specific configs */
		if (is_array($assoc_group_ids)) {
			basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_id', 'config_', 'AND config_type="' . $type . '" AND config_is_parent="no" AND config_parent_id=' . $first_ssid . ' AND config_status="active" AND server_serial_no IN ("g_' . implode('","g_', $assoc_group_ids) . '")' . $config_aps_sql);
			if ($fmdb->num_rows) {
				foreach ($fmdb->last_result as $server_config_result) {
					$server_config[$server_config_result->config_name] = @array($server_config_result->config_data, $server_config_result->config_comment);
				}
			}
		}

		/** Override with server-specific configs */
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_id', 'config_', 'AND config_type="' . $type . '" AND config_is_parent="no" AND config_parent_id=' . $first_ssid . ' AND config_status="active" AND server_serial_no="' . $server_serial_no . '"' . $config_aps_sql);
		if ($fmdb->num_rows) {
			foreach ($fmdb->last_result as $server_config_result) {
				$server_config[$server_config_result->config_name] = @array($server_config_result->config_data, $server_config_result->config_comment);
			}
		}

		/** Merge arrays */
		$config_array = array_merge($global_config, $server_config);
		unset($global_config, $server_config);

		/** Format global config */
		$config[] = null;
		foreach ($config_array as $cfg_name => $cfg_data) {
			list($cfg_info, $cfg_comment) = $cfg_data;
			if ($cfg_comment) {
				$comment = wordwrap($cfg_comment, 50, "\n");
				$config[] = '# ' . str_replace("\n", "\n# ", $comment);
				unset($comment);
			}
			$config[] = "$cfg_name=$cfg_info";
		}
		unset($config_array);

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
	 * @param string $header Header message for the file
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
		foreach ($fmdb->last_result as $wlan_user) {
			$comment = ($wlan_user->wlan_user_comment) ? ' (' . $wlan_user->wlan_user_comment . ')' : null;
			$config[] = '# ' . $wlan_user->wlan_user_login . $comment;
			$config[] = $wlan_user->wlan_user_mac . ' ' . $wlan_user->wlan_user_password;
		}
		if (!$fmdb->num_rows || getOption('include_wlan_psk', $_SESSION['user']['account_id'], $_SESSION['module']) == 'yes') {
			$query = "SELECT config_data FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config WHERE config_status='active' AND account_id='{$_SESSION['user']['account_id']}' AND config_parent_id=$wlan_id AND config_name='wpa_passphrase' LIMIT 1";
			$fmdb->query($query);
			if ($fmdb->num_rows) {
				$config[] = "00:00:00:00:00:00 " . $fmdb->last_result[0]->config_data;
			}
		}
		
		return join("\n", $config);
	}


	/**
	 * Populates the ACL accept/deny files for the server
	 *
	 * @since 0.1
	 * @package fmWifi
	 *
	 * @param string $header Header message for the file
	 * @param object $server_info Server information
	 * @param string $wlan_id SSID
	 * @param array $mac_files MAC accept/deny filenames
	 * @return boolean
	 */
	function buildACLFiles($header, $server_info, $wlan_id, $mac_files) {
		global $fmdb, $__FM_CONFIG;
		
		$config['accept'][] = $config['deny'][] = $header;
		$config['accept'][] = sprintf("# %s\n", wordwrap(__('This file contains a list of MAC addresses that are allowed to authenticate with the AP.'), 60, "\n# "));
		$config['deny'][] = sprintf("# %s\n", wordwrap(__('This file contains a list of MAC addresses that are not allowed to authenticate with the AP.'), 60, "\n# "));
		
		/** Get user list for SSID */
		$wlan_sql = " AND (wlan_ids='0' OR wlan_ids='$wlan_id' OR wlan_ids LIKE '$wlan_id;%' OR wlan_ids LIKE '%;$wlan_id;%' OR wlan_ids LIKE '%;$wlan_id')";
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'wlan_users', 'wlan_user_id', 'wlan_user_', 'AND wlan_user_status="active"' . $wlan_sql);
		foreach ($fmdb->last_result as $wlan_user) {
			$comment = ($wlan_user->wlan_user_comment) ? ' (' . $wlan_user->wlan_user_comment . ')' : null;
			$config['accept'][] = '# ' . $wlan_user->wlan_user_login . $comment;
			$config['accept'][] = $wlan_user->wlan_user_mac;
		}
		
		/** Get MAC ACLs for SSID */
		$wlan_sql = " AND (wlan_ids='0' OR wlan_ids='$wlan_id' OR wlan_ids LIKE '$wlan_id;%' OR wlan_ids LIKE '%;$wlan_id;%' OR wlan_ids LIKE '%;$wlan_id')";
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'acls', 'acl_id', 'acl_', 'AND acl_status="active"' . $wlan_sql);
		foreach ($fmdb->last_result as $wlan_acl) {
			if ($wlan_acl->acl_comment) $config[$wlan_acl->acl_action][] = '# ' . $wlan_acl->acl_comment;
			$config[$wlan_acl->acl_action][] = $wlan_acl->acl_mac;
		}
		
		return array($mac_files['accept'] => join("\n", $config['accept']), $mac_files['deny'] => join("\n", $config['deny']));
	}


}

if (!isset($fm_module_buildconf))
	$fm_module_buildconf = new fm_module_buildconf();

?>
