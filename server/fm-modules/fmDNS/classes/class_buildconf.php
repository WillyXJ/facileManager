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

class fm_module_buildconf {

	/**
	 * Processes the server configs
	 *
	 * @since 1.0
	 * @package fmDNS
	 *
	 * @param array $files_array Array containing named files and contents
	 * @return string
	 */
	function processConfigs($raw_data) {
		$preview = null;
		
		$check_status = @$this->namedSyntaxChecks($raw_data);
		foreach ($raw_data['files'] as $filename => $contents) {
			$preview .= str_repeat('=', 75) . "\n";
			$preview .= $filename . ":\n";
			$preview .= str_repeat('=', 75) . "\n";
			if (strpos($check_status, 'error') !== false) {
				$i = 1;
				$contents_array = explode("\n", $contents);
				foreach ($contents_array as $line) {
					$preview .= '<font color="#ccc">' . str_pad($i, strlen(count($contents_array)), ' ', STR_PAD_LEFT) . '</font> ';
					if (strpos($check_status, "$filename:$i:") !== false) {
						$preview .= sprintf('<font color="red">%s</font>', $line);
					} else {
						$preview .= $line;
					}
					$preview .= "\n";
					$i++;
				}
				$preview .= "\n";
			} else {
				$preview .= "$contents\n\n";
			}
		}
		
		return array($preview, $check_status);
	}
	
	
	/**
	 * Generates the server config and updates the DNS server
	 *
	 * @since 1.0
	 * @package fmDNS
	 */
	function buildServerConfig($post_data) {
		global $fmdb, $__FM_CONFIG, $fm_dns_acls, $fm_dns_keys, $fm_module_servers;
		
		/** Get datetime formatting */
		$date_format = getOption('date_format', $_SESSION['user']['account_id']);
		$time_format = getOption('time_format', $_SESSION['user']['account_id']);
		
		include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_acls.php');
		include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_keys.php');
		
		setTimezone();
		
		$files = array();
		$server_serial_no = sanitize($post_data['SERIALNO']);
		$message = null;
		extract($post_data);
		if (!isset($fm_module_servers)) include(ABSPATH . 'fm-modules/fmDNS/classes/class_servers.php');
		$server_group_ids = $fm_module_servers->getServerGroupIDs(getNameFromID($SERIALNO, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_id'));

		$GLOBALS['built_domain_ids'] = null;
		
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', $server_serial_no, 'server_', 'server_serial_no');
		if ($fmdb->num_rows) {
			$server_result = $fmdb->last_result;
			$data = $server_result[0];
			extract(get_object_vars($data), EXTR_SKIP);
			
			/** Disabled DNS server */
			if ($server_status != 'active') {
				$error = "DNS server is $server_status.\n";
				if ($compress) echo gzcompress(serialize($error));
				else echo serialize($error);
				
				exit;
			}
			
			include(ABSPATH . 'fm-includes/version.php');
			
			$config = $zones = $key_config = '// This file was built using ' . $_SESSION['module'] . ' ' . $__FM_CONFIG[$_SESSION['module']]['version'] . ' on ' . date($date_format . ' ' . $time_format . ' e') . "\n\n";
			$query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` WHERE `cfg_name`='directory'";
			$config_dir_result = $fmdb->query($query);
			$config_dir_result = $fmdb->last_result;
			$logging = $keys = $servers = null;
			

			/** Build keys config */
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_id', 'key_', 'AND key_view=0 AND key_status="active"');
			if ($fmdb->num_rows) {
				$key_result = $fmdb->last_result;
				$key_config_count = $fmdb->num_rows;
				for ($i=0; $i < $key_config_count; $i++) {
					$key_name = trimFullStop($key_result[$i]->key_name);
					$keys .= $key_name . "\n";
					if ($key_result[$i]->key_comment) {
						$comment = wordwrap($key_result[$i]->key_comment, 50, "\n");
						$key_config .= '// ' . str_replace("\n", "\n// ", $comment) . "\n";
						unset($comment);
					}
					$key_config .= "key \"$key_name\" {\n";
					$key_config .= "\talgorithm " . $key_result[$i]->key_algorithm . ";\n";
					$key_config .= "\tsecret \"" . $key_result[$i]->key_secret . "\";\n";
					$key_config .= "};\n\n";
					
					/** Get associated servers */
					basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_id', 'server_', 'AND server_serial_no!="' . $server_serial_no . '" AND server_key=' . $key_result[$i]->key_id . ' AND server_status="active"');
					if ($fmdb->num_rows) {
						$server_result = $fmdb->last_result;
						$server_count = $fmdb->num_rows;
						for ($j=0; $j < $server_count; $j++) {
							$servers .= $this->formatServerKeys($server_result[$j]->server_name, $key_name);
						}
					}
				}
			}
			
			$config .= $servers;

			if ($keys) {
				$data->files[dirname($server_config_file) . '/named.conf.keys'] = $key_config;
			
				$config .= "include \"" . dirname($server_config_file) . "/named.conf.keys\";\n\n";
			}
			
			
			/** Build ACLs */
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_id', 'acl_', 'AND acl_status="active" AND server_serial_no="0"');
			if ($fmdb->num_rows) {
				$acl_result = $fmdb->last_result;
				for ($i=0; $i < $fmdb->num_rows; $i++) {
					if ($acl_result[$i]->acl_predefined != 'as defined:') {
						$global_acl_array[$acl_result[$i]->acl_name] = array($acl_result[$i]->acl_predefined, $acl_result[$i]->acl_comment);
					} else {
						$addresses = explode(',', $acl_result[$i]->acl_addresses);
						$global_acl_array[$acl_result[$i]->acl_name] = null;
						foreach($addresses as $address) {
							if(trim($address)) $global_acl_array[$acl_result[$i]->acl_name] .= "\t" . $address . ";\n";
						}
						$global_acl_array[$acl_result[$i]->acl_name] = array(rtrim(ltrim($global_acl_array[$acl_result[$i]->acl_name], "\t"), ";\n"), $acl_result[$i]->acl_comment);
					}
				}
			} else $global_acl_array = array();

			$server_acl_array = array();
			/** Override with group-specific configs */
			if (is_array($server_group_ids)) {
				basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_id', 'acl_', 'AND acl_status="active" AND server_serial_no IN ("g_' . implode('","g_', $server_group_ids) . '")');
				if ($fmdb->num_rows) {
					$server_acl_result = $fmdb->last_result;
					$acl_config_count = $fmdb->num_rows;
					for ($j=0; $j < $acl_config_count; $j++) {
						if ($server_acl_result[$j]->acl_predefined != 'as defined:') {
							$server_acl_array[$server_acl_result[$j]->acl_name] = array($server_acl_result[$j]->acl_predefined, $server_acl_result[$j]->acl_comment);
						} else {
							$addresses = explode(',', $server_acl_result[$j]->acl_addresses);
							$server_acl_addresses = null;
							foreach($addresses as $address) {
								if(trim($address)) $server_acl_addresses .= "\t" . trim($address) . ";\n";
							}
							$server_acl_array[$server_acl_result[$j]->acl_name] = array(rtrim(ltrim($server_acl_addresses, "\t"), ";\n"), $server_acl_result[$j]->acl_comment);
						}
					}
				}
			}

			/** Override with server-specific ACLs */
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_id', 'acl_', 'AND acl_status="active" AND server_serial_no="' . $server_serial_no . '"');
			if ($fmdb->num_rows) {
				$server_acl_result = $fmdb->last_result;
				$acl_config_count = $fmdb->num_rows;
				for ($j=0; $j < $acl_config_count; $j++) {
					if ($server_acl_result[$j]->acl_predefined != 'as defined:') {
						$server_acl_array[$server_acl_result[$j]->acl_name] = array($server_acl_result[$j]->acl_predefined, $server_acl_result[$j]->acl_comment);
					} else {
						$addresses = explode(',', $server_acl_result[$j]->acl_addresses);
						$server_acl_addresses = null;
						foreach($addresses as $address) {
							if(trim($address)) $server_acl_addresses .= "\t" . trim($address) . ";\n";
						}
						$server_acl_array[$server_acl_result[$j]->acl_name] = array(rtrim(ltrim($server_acl_addresses, "\t"), ";\n"), $server_acl_result[$j]->acl_comment);
					}
				}
			}

			/** Merge arrays */
			$acl_array = array_merge($global_acl_array, $server_acl_array);

			/** Format ACL config */
			foreach ($acl_array as $acl_name => $acl_data) {
				list($acl_item, $acl_comment) = $acl_data;
				if ($acl_comment) {
					$comment = wordwrap($acl_comment, 50, "\n");
					$config .= '// ' . str_replace("\n", "\n// ", $comment) . "\n";
					unset($comment);
				}
				$config .= 'acl "' . $acl_name . "\" {\n";
				$config .= "\t" . $acl_item . ";\n";
				$config .= "};\n\n";
			}


			/** Build logging config */
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_name` DESC,`cfg_data`,`cfg_id', 'cfg_', 'AND cfg_type="logging" AND cfg_isparent="yes" AND cfg_status="active" AND server_serial_no in ("0", "' . $server_serial_no . '", "g_' . implode('","g_', $server_group_ids) . '")');
			if ($fmdb->num_rows) {
				$logging_result = $fmdb->last_result;
				$count = $fmdb->num_rows;
				for ($i=0; $i < $count; $i++) {
					if ($logging_result[$i]->cfg_comment) {
						$comment = wordwrap($logging_result[$i]->cfg_comment, 50, "\n");
						$logging .= "\t// " . str_replace("\n", "\n\t// ", $comment) . "\n";
						unset($comment);
					}
					$logging .= "\t" . $logging_result[$i]->cfg_name . ' ' . $logging_result[$i]->cfg_data . " {\n";
					
					/** Get logging config details */
					basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_id` ASC,`cfg_name`,`cfg_data', 'cfg_', 'AND cfg_type="logging" AND cfg_parent="' . $logging_result[$i]->cfg_id . '" AND cfg_status="active"');
					if ($fmdb->num_rows) {
						$child_result = $fmdb->last_result;
						$count2 = $fmdb->num_rows;
						for ($j=0; $j < $count2; $j++) {
							if ($logging_result[$i]->cfg_name == 'channel') {
								$logging .= "\t\t" . $child_result[$j]->cfg_name;
								if ($child_result[$j]->cfg_data && $child_result[$j]->cfg_data != $child_result[$j]->cfg_name) $logging .= ' ' . $child_result[$j]->cfg_data;
								$logging .= ";\n";
							} else {
								$channels = null;
								$assoc_channels = explode(';', $child_result[$j]->cfg_data);
								foreach ($assoc_channels as $channel) {
									if (is_numeric($channel)) {
										$channel = getNameFromID($channel, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'cfg_id', 'cfg_data');
									}
									$channels .= "\t\t$channel;\n";
								}
								$logging .= $channels;
							}
						}
					}					
					
					/** Close */
					$logging .= "\t};\n";
				}
			}
			if ($logging) $logging = "logging {\n$logging};\n\n";
			
			$config .= $logging;

			
			/** Build global configs */
			$config .= "options {\n";
			$config .= "\tdirectory \"" . str_replace('$ROOT', $server_root_dir, $config_dir_result[0]->cfg_data) . "\";\n";
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_id', 'cfg_', 'AND cfg_type="global" AND view_id=0 AND domain_id=0 AND server_serial_no="0" AND cfg_status="active"');
			if ($fmdb->num_rows) {
				$config_result = $fmdb->last_result;
				$global_config_count = $fmdb->num_rows;
				for ($i=0; $i < $global_config_count; $i++) {
					$global_config[$config_result[$i]->cfg_name] = array($config_result[$i]->cfg_data, $config_result[$i]->cfg_comment);
				}
			} else $global_config = array();

			$server_config = array();
			/** Override with group-specific configs */
			if (is_array($server_group_ids)) {
				basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_id', 'cfg_', 'AND cfg_type="global" AND view_id=0 AND domain_id=0  AND server_serial_no IN ("g_' . implode('","g_', $server_group_ids) . '") AND cfg_status="active"');
				if ($fmdb->num_rows) {
					$server_config_result = $fmdb->last_result;
					$global_config_count = $fmdb->num_rows;
					for ($j=0; $j < $global_config_count; $j++) {
						$server_config[$server_config_result[$j]->cfg_name] = @array($server_config_result[$j]->cfg_data, $server_config_result[$j]->cfg_comment);
					}
				}
			}

			/** Override with server-specific configs */
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_id', 'cfg_', 'AND cfg_type="global" AND view_id=0 AND domain_id=0  AND server_serial_no="' . $server_serial_no . '" AND cfg_status="active"');
			if ($fmdb->num_rows) {
				$server_config_result = $fmdb->last_result;
				$global_config_count = $fmdb->num_rows;
				for ($j=0; $j < $global_config_count; $j++) {
					$server_config[$server_config_result[$j]->cfg_name] = @array($server_config_result[$j]->cfg_data, $server_config_result[$j]->cfg_comment);
				}
			}

			/** Merge arrays */
			$config_array = array_merge($global_config, $server_config);
			
			$include_hint_zone = false;

			foreach ($config_array as $cfg_name => $cfg_data) {
				list($cfg_info, $cfg_comment) = $cfg_data;

				/** Include hint zone (root servers) */
				if ($cfg_name == 'recursion' && $cfg_info == 'yes') $include_hint_zone = true;

				$query = "SELECT def_multiple_values FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}functions WHERE def_option = '{$cfg_name}'";
				$fmdb->get_results($query);
				if (!$fmdb->num_rows) $def_multiple_values = 'no';
				else {
					$result = $fmdb->last_result[0];
					$def_multiple_values = $result->def_multiple_values;
				}
				if ($cfg_comment) {
					$comment = wordwrap($cfg_comment, 50, "\n");
					$config .= "\n\t// " . str_replace("\n", "\n\t// ", $comment) . "\n";
					unset($comment);
				}
				$config .= "\t" . $cfg_name . ' ';
				if ($def_multiple_values == 'yes' && strpos($cfg_info, '{') === false) $config .= '{ ';
				$cfg_info = strpos($cfg_info, 'acl_') !== false ? $fm_dns_acls->parseACL($cfg_info) : $cfg_info;
				$config .= str_replace('$ROOT', $server_root_dir, trim(rtrim(trim($cfg_info), ';')));
				if ($def_multiple_values == 'yes' && strpos($cfg_info, '}') === false) $config .= '; }';
				$config .= ";\n";
				
				unset($cfg_info);
				if ($cfg_comment) $config .= "\n";
			}
			/** Build rate limits */
			$config .= $this->getRateLimits(0, $server_serial_no);
			
			$config .= "};\n\n";
			
			
			/** Build controls configs */
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'controls', 'control_id', 'control_', 'AND server_serial_no IN ("0","' . $server_serial_no . '", "g_' . implode('","g_', $server_group_ids) . '") AND control_status="active"');
			if ($fmdb->num_rows) {
				$control_result = $fmdb->last_result;
				$control_config_count = $fmdb->num_rows;
				$control_config = "controls {\n";
				for ($i=0; $i < $control_config_count; $i++) {
					if ($control_result[$i]->control_comment) {
						$comment = wordwrap($control_result[$i]->control_comment, 50, "\n");
						$control_config .= "\t// " . str_replace("\n", "\n// ", $comment) . "\n";
						unset($comment);
					}
					$control_config .= "\tinet " . $control_result[$i]->control_ip;
					if ($control_result[$i]->control_port != 953) $control_config .= ' port ' . $control_result[$i]->control_port;
					if (!empty($control_result[$i]->control_addresses)) $control_config .= ' allow { ' . trim($fm_dns_acls->parseACL($control_result[$i]->control_addresses), '; ') . '; }';
					$control_config .= (!empty($control_result[$i]->control_keys)) ? ' keys { "' . $fm_dns_keys->parseKey($control_result[$i]->control_keys) . '"; };' : ";";
					$control_config .= "\n";
				}
				$control_config .= "};\n\n";
			} else $control_config = null;
			
			$config .= $control_config;
			unset($control_config);

			
			/** Debian-based requires named.conf.options */
			if (isDebianSystem($server_os_distro)) {
				$data->files[dirname($server_config_file) . '/named.conf.options'] = $config;
				$config = $zones . "include \"" . dirname($server_config_file) . "/named.conf.options\";\n\n";
				$data->files[$server_config_file] = $config;
				$config = $zones;
			}
			

			/** Build Views */
			if (is_array($server_group_ids)) {
				basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_id', 'view_', "AND view_status='active' AND server_serial_no IN ('0', '$server_serial_no', 'g_" . implode("','g_", $server_group_ids) . "')");
			} else {
				basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_id', 'view_', "AND view_status='active' AND server_serial_no IN ('0', '$server_serial_no')");
			}
			if ($fmdb->num_rows) {
				$view_result = $fmdb->last_result;
				$view_count = $fmdb->num_rows;
				for ($i=0; $i < $view_count; $i++) {
					if ($view_result[$i]->view_comment) {
						$comment = wordwrap($view_result[$i]->view_comment, 50, "\n");
						$config .= '// ' . str_replace("\n", "\n// ", $comment) . "\n";
						unset($comment);
					}
					$config .= 'view "' . $view_result[$i]->view_name . "\" {\n";

					/** Get cooresponding config records */
					basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_id', 'cfg_', "AND cfg_status='active' AND cfg_type='global' AND server_serial_no='0' AND view_id='" . $view_result[$i]->view_id . "'");
					if ($fmdb->num_rows) {
						$config_result = $fmdb->last_result;
						$view_config_count = $fmdb->num_rows;
						for ($j=0; $j < $view_config_count; $j++) {
							$view_config[$config_result[$j]->cfg_name] = array($config_result[$j]->cfg_data, $config_result[$j]->cfg_comment);
						}
					} else $view_config = array();

					$server_view_config = array();
					/** Override with group-specific configs */
					if (is_array($server_group_ids)) {
						basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_id', 'cfg_', "AND cfg_status='active' AND cfg_type='global' AND server_serial_no IN ('g_" . implode("','g_", $server_group_ids) . "') AND view_id='" . $view_result[$i]->view_id . "'");
						if ($fmdb->num_rows) {
							$server_config_result = $fmdb->last_result;
							$view_config_count = $fmdb->num_rows;
							for ($j=0; $j < $view_config_count; $j++) {
								$server_view_config[$server_config_result[$j]->cfg_name] = array($server_config_result[$j]->cfg_data, $server_config_result[$j]->cfg_comment);
							}
						}
					}

					/** Override with server-specific configs */
					basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_id', 'cfg_', "AND cfg_status='active' AND cfg_type='global' AND server_serial_no='$server_serial_no' AND view_id='" . $view_result[$i]->view_id . "'");
					if ($fmdb->num_rows) {
						$server_config_result = $fmdb->last_result;
						$view_config_count = $fmdb->num_rows;
						for ($j=0; $j < $view_config_count; $j++) {
							$server_view_config[$server_config_result[$j]->cfg_name] = array($server_config_result[$j]->cfg_data, $server_config_result[$j]->cfg_comment);
						}
					}

					/** Merge arrays */
					$config_array = array_merge($view_config, $server_view_config);

					foreach ($config_array as $cfg_name => $cfg_data) {
						list($cfg_info, $cfg_comment) = $cfg_data;

						/** Include hint zone (root servers) */
						if ($cfg_name == 'recursion' && $cfg_info == 'yes') $include_hint_zone = true;

						$query = "SELECT def_multiple_values FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}functions WHERE def_option = '{$cfg_name}'";
						$fmdb->get_results($query);
						if (!$fmdb->num_rows) $def_multiple_values = 'no';
						else {
							$result = $fmdb->last_result[0];
							$def_multiple_values = $result->def_multiple_values;
						}
						if ($cfg_comment) {
							$comment = wordwrap($cfg_comment, 50, "\n");
							$config .= "\n\t// " . str_replace("\n", "\n\t// ", $comment) . "\n";
							unset($comment);
						}
						$config .= "\t" . $cfg_name . ' ';
						if ($def_multiple_values == 'yes') $config .= '{ ';
						$cfg_info = $fm_dns_acls->parseACL($cfg_info);
						$config .= str_replace('$ROOT', $server_root_dir, trim(rtrim(trim($cfg_info), ';')));
						if ($def_multiple_values == 'yes') $config .= '; }';
						$config .= ";\n";
						
						if ($cfg_comment) $config .= "\n";
						unset($cfg_info);
					}

					/** Build rate limits */
					$config .= $this->getRateLimits($view_result[$i]->view_id, $server_serial_no);

					/** Get cooresponding keys */
					basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_id', 'key_', "AND key_status='active' AND key_view='" . $view_result[$i]->view_id . "'");
					if ($fmdb->num_rows) {
						$key_result = $fmdb->last_result;
						$key_config = '// This file was built using ' . $_SESSION['module'] . ' ' . $fm_version . ' on ' . date($date_format . ' ' . $time_format . ' e') . "\n\n";
						$key_count = $fmdb->num_rows;
						for ($k=0; $k < $key_count; $k++) {
							$key_name = trimFullStop($key_result[$k]->key_name) . '.';
							if ($key_result[$k]->key_comment) {
								$comment = wordwrap($key_result[$k]->key_comment, 50, "\n");
								$key_config .= '// ' . str_replace("\n", "\n// ", $comment) . "\n";
								unset($comment);
							}
							$key_config .= "key \"" . $key_name . "\" {\n";
							$key_config .= "\talgorithm " . $key_result[$k]->key_algorithm . ";\n";
							$key_config .= "\tsecret \"" . $key_result[$k]->key_secret . "\";\n";
							$key_config .= "};\n\n";
					
							/** Get associated servers */
							basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_id', 'server_', 'AND server_serial_no!="' . $server_serial_no . '" AND server_key=' . $key_result[$k]->key_id . ' AND server_status="active"');
							if ($fmdb->num_rows) {
								$server_result = $fmdb->last_result;
								$server_count = $fmdb->num_rows;
								$servers = null;
								for ($j=0; $j < $server_count; $j++) {
									$config .= $this->formatServerKeys($server_result[$j]->server_name, $key_name, true);
								}
							}
						}
						$data->files[$server_zones_dir . '/views.conf.' . sanitize($view_result[$i]->view_name, '-') . '.keys'] = $key_config;
					}
					
					/** Generate zone file */
					list($tmp_files, $error) = $this->buildZoneDefinitions($server_zones_dir, $server_serial_no, $view_result[$i]->view_id, sanitize($view_result[$i]->view_name, '-'), $include_hint_zone);
					if ($error) $message = $error;
					
					/** Include zones for view */
					if (is_array($tmp_files)) {
						/** Include view keys if present */
						if (@array_key_exists($server_zones_dir . '/views.conf.' . sanitize($view_result[$i]->view_name, '-') . '.keys', $data->files)) {
							$config .= "\tinclude \"" . $server_zones_dir . "/views.conf." . sanitize($view_result[$i]->view_name, '-') . ".keys\";\n";
						}
						$config .= "\tinclude \"" . $server_zones_dir . '/zones.conf.' . sanitize($view_result[$i]->view_name, '-') . "\";\n";
						$files = array_merge($files, $tmp_files);
					}
					
					$config .= "};\n\n";
					
					$key_config = $view_config = $server_view_config = null;
				}
			} else {
				/** Generate zones.all.conf */
				list($files, $message) = $this->buildZoneDefinitions($server_zones_dir, $server_serial_no, 0, null, $include_hint_zone);
				
				/** Include all zones in one file */
				if (is_array($files)) {
					$config .= "\ninclude \"" . $server_zones_dir . "/zones.conf.all\";\n";
				}
			}

			/** Debian-based requires named.conf.local */
			if (isDebianSystem($server_os_distro)) {
				$data->files[dirname($server_config_file) . '/named.conf.local'] = $config;
				$config = $data->files[$server_config_file] . "include \"" . dirname($server_config_file) . "/named.conf.local\";\n\n";
			}

			$data->files[$server_config_file] = $config;
			if (is_array($files)) {
				$data->files = array_merge($data->files, $files);
			}

			/** Set variable containing all loaded domain_ids */
			if (!$dryrun) {
				$this->setBuiltDomainIDs($server_serial_no, array_unique($GLOBALS['built_domain_ids']));
				$data->built_domain_ids = array_unique($GLOBALS['built_domain_ids']);
			}
			
			return array(get_object_vars($data), $message);
		}
		
		/** Bad DNS server */
		$error = "DNS server is not found.\n";
		if ($compress) echo gzcompress(serialize($error));
		else echo serialize($error);
	}
	
	/**
	 * Generates the zone configs (not files)
	 *
	 * @since 1.0
	 * @package fmDNS
	 */
	function buildZoneConfig($post_data) {
		global $fmdb, $__FM_CONFIG;
		
		$server_serial_no = sanitize($post_data['SERIALNO']);
		extract($post_data);
		
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', $server_serial_no, 'server_', 'server_serial_no');
		if ($fmdb->num_rows || $SERIALNO == -1) {
			if ($SERIALNO != -1) {
				$result = $fmdb->last_result;
				$data = $result[0];
				extract(get_object_vars($data), EXTR_SKIP);
			}
			
			if (!$domain_id) {
				/** Build all zone files */
				list($data->files, $message) = $this->buildZoneDefinitions($server_zones_dir, $server_serial_no);
			} else {
				/** Build zone files for $domain_id */
				$query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE `domain_status`='active' AND (`domain_id`=" . sanitize($domain_id) . " OR `domain_clone_domain_id`=" . sanitize($domain_id) . ") ";
				if ($SERIALNO != -1) {
					$server_id = getServerID($server_serial_no, $_SESSION['module']);
					$query .= " AND (`domain_name_servers`='0' OR `domain_name_servers`='s_{$server_id}' OR `domain_name_servers` LIKE 's_{$server_id};%' OR `domain_name_servers` LIKE '%;s_{$server_id};%')";
				}
				$query .= " ORDER BY `domain_clone_domain_id`,`domain_name`";
				$result = $fmdb->query($query);
				if ($fmdb->num_rows) {
					$count = $fmdb->num_rows;
					$zone_result = $fmdb->last_result;
					for ($i=0; $i < $count; $i++) {
						/** Is this a clone id? */
						if ($zone_result[$i]->domain_clone_domain_id) $zone_result[$i] = $this->mergeZoneDetails($zone_result[$i], 'clone');
						elseif ($zone_result[$i]->domain_template_id) $zone_result[$i] = $this->mergeZoneDetails($zone_result[$i], 'template');
						
						if (getSOACount($zone_result[$i]->domain_id)) {
							$domain_name = $this->getDomainName($zone_result[$i]->domain_mapping, trimFullStop($zone_result[$i]->domain_name));
							$file_ext = ($zone_result[$i]->domain_mapping == 'forward') ? 'hosts' : 'rev';

							/** Are there multiple zones with the same name? */
							if (isset($zone_result[$i]->parent_domain_id)) {
								basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $zone_result[$i]->domain_name, 'domain_', 'domain_name', 'AND domain_id!=' . $zone_result[$i]->parent_domain_id);
								if ($fmdb->num_rows) $file_ext = $zone_result[$i]->parent_domain_id . ".$file_ext";
							} else {
								$zone_result[$i]->parent_domain_id = $zone_result[$i]->domain_id;
								basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $zone_result[$i]->domain_name, 'domain_', 'domain_name', 'AND domain_id!=' . $zone_result[$i]->domain_id);
								if ($fmdb->num_rows) $file_ext = $zone_result[$i]->domain_id . ".$file_ext";
							}
//							basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $zone_result[$i]->domain_name, 'domain_', 'domain_name', 'AND domain_clone_domain_id=0 AND domain_id!=' . $zone_result[$i]->domain_id);
//							if ($fmdb->num_rows) $file_ext = $zone_result[$i]->domain_id . ".$file_ext";
							
							/** Build zone file */
							$data->files[$server_zones_dir . '/' . $zone_result[$i]->domain_type . '/db.' . $domain_name . "$file_ext"] = $this->buildZoneFile($zone_result[$i]);
						}
					}
					if (isset($data->files)) {
						/** set the server_update_config flag */
						if (!$dryrun) setBuildUpdateConfigFlag($server_serial_no, 'yes', 'update');
						
						return array(get_object_vars($data), null);
					}
				}
				
				/** Bad domain id */
				$error = "Domain ID $domain_id is not found or is not hosted on this server.\n";
			}
		} else {
			/** Bad DNS server */
			$error = "DNS server is not found.\n";
		}
		
		if ($compress) echo gzcompress(serialize($error));
		else echo serialize($error);
	}
	
	
	/**
	 * Generates the zone configs (not files)
	 *
	 * @since 1.0
	 * @package fmDNS
	 */
	function buildZoneDefinitions($server_zones_dir, $server_serial_no, $view_id = 0, $view_name = null, $include_hint_zone = false) {
		global $fmdb, $__FM_CONFIG, $fm_dns_acls, $fm_module_servers;
		
		$error = null;
		
		include(ABSPATH . 'fm-includes/version.php');
		
		/** Get datetime formatting */
		$date_format = getOption('date_format', $_SESSION['user']['account_id']);
		$time_format = getOption('time_format', $_SESSION['user']['account_id']);
		
		$files = null;
		$zones = '// This file was built using ' . $_SESSION['module'] . ' ' . $__FM_CONFIG[$_SESSION['module']]['version'] . ' on ' . date($date_format . ' ' . $time_format . ' e') . "\n\n";
		$server_id = getServerID($server_serial_no, $_SESSION['module']);
		
		/** Build hint zone (root servers) */
		if ($include_hint_zone) {
			$zones .= 'zone "." {' . "\n";
			$zones .= "\ttype hint;\n";
			$zones .= "\tfile \"$server_zones_dir/hint/named.root\";\n";
			$zones .= "};\n";
			
			list($files[$server_zones_dir . '/hint/named.root'], $error) = $this->getHintZone();
		}

		/** Build zones */
		$server_group_ids = $fm_module_servers->getServerGroupIDs($server_id);
		$group_sql = null;
		foreach ($server_group_ids as $group_id) {
			$group_sql .= " OR (`domain_name_servers`='0' OR `domain_name_servers`='g_{$group_id}' OR `domain_name_servers` LIKE 'g_{$group_id};%' OR `domain_name_servers` LIKE '%;g_{$group_id};%' OR `domain_name_servers` LIKE '%;g_{$group_id}')";
		}
		if ($group_sql) {
			$group_sql = ' OR ' . ltrim($group_sql, ' OR ');
		}
		$view_sql = "AND (`domain_view`<=0 OR `domain_view`=$view_id OR `domain_view` LIKE '$view_id;%' OR `domain_view` LIKE '%;$view_id' OR `domain_view` LIKE '%;$view_id;%')";
		$query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE `domain_status`='active' AND `domain_template`='no' AND 
			((`domain_name_servers`='0' OR `domain_name_servers`='s_{$server_id}' OR `domain_name_servers` LIKE 's_{$server_id};%' OR `domain_name_servers` LIKE '%;s_{$server_id};%' OR `domain_name_servers` LIKE '%;s_{$server_id}' $group_sql))
			 $view_sql ORDER BY `domain_clone_domain_id`,`domain_name`";
		$result = $fmdb->query($query);
		if ($fmdb->num_rows) {
			$count = $fmdb->num_rows;
			$zone_result = $fmdb->last_result;
			for ($i=0; $i < $count; $i++) {
				/** Is this a clone id? */
				if ($zone_result[$i]->domain_clone_domain_id) $zone_result[$i] = $this->mergeZoneDetails($zone_result[$i], 'clone');
				elseif ($zone_result[$i]->domain_template_id) $zone_result[$i] = $this->mergeZoneDetails($zone_result[$i], 'template');
				if ($zone_result[$i] == false) continue;
				
				if ($zone_result[$i]->domain_template == 'yes') {
					$skip = true;
					foreach (explode(';', $zone_result[$i]->domain_name_servers) as $domain_server_id) {
						if ($domain_server_id[0] == 's') {
							if (!$domain_server_id || 's_' . $server_id == $domain_server_id) $skip = false;
						} else {
							foreach ($server_group_ids as $group_id) {
								if (!$domain_server_id || 'g_' . $group_id == $domain_server_id) $skip = false;
							}
						}
					}
					if ($skip) continue;
				}
				
				/** Valid SOA and NS records must exist */
				if ((getSOACount($zone_result[$i]->domain_id) && getNSCount($zone_result[$i]->domain_id)) ||
					$zone_result[$i]->domain_type != 'master') {

					$domain_name_file = $this->getDomainName($zone_result[$i]->domain_mapping, trimFullStop($zone_result[$i]->domain_name));
					$domain_name = isset($zone_result[$i]->domain_name_file) ? $this->getDomainName($zone_result[$i]->domain_mapping, trimFullStop($zone_result[$i]->domain_name_file)) : $domain_name_file;
					list ($domain_type, $auto_zone_options) = $this->processServerGroups($zone_result[$i], $server_id);
					$zones .= 'zone "' . rtrim($domain_name, '.') . "\" {\n";
					$zones .= "\ttype $domain_type;\n";
					$file_ext = ($zone_result[$i]->domain_mapping == 'forward') ? 'hosts' : 'rev';
					
					/** Are there multiple zones with the same name? */
					if (isset($zone_result[$i]->parent_domain_id)) {
						basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $zone_result[$i]->domain_name, 'domain_', 'domain_name', 'AND domain_id!=' . $zone_result[$i]->parent_domain_id);
						if ($fmdb->num_rows) $file_ext = $zone_result[$i]->parent_domain_id . ".$file_ext";
					} else {
						$zone_result[$i]->parent_domain_id = $zone_result[$i]->domain_id;
						basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $zone_result[$i]->domain_name, 'domain_', 'domain_name', 'AND domain_id!=' . $zone_result[$i]->domain_id);
						if ($fmdb->num_rows) $file_ext = $zone_result[$i]->domain_id . ".$file_ext";
					}
					
					switch($domain_type) {
						case 'master':
						case 'slave':
							$zones .= "\tfile \"$server_zones_dir/$domain_type/db." . $domain_name_file . "$file_ext\";\n";
							$zones .= $this->getZoneOptions(array($zone_result[$i]->domain_id, $zone_result[$i]->parent_domain_id, $zone_result[$i]->domain_template_id), $server_serial_no, $domain_type). (string) $auto_zone_options;
							/** Build zone file */
							if ($domain_type == 'master') {
								$files[$server_zones_dir . '/master/db.' . $domain_name_file . $file_ext] = $this->buildZoneFile($zone_result[$i], $server_serial_no);
							}
							break;
						case 'stub':
							$zones .= "\tfile \"$server_zones_dir/stub/db." . $domain_name . "$file_ext\";\n";
							$domain_master_servers = str_replace(';', "\n", rtrim(str_replace(' ', '', getNameFromID($zone_result[$i]->domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'domain_id', 'cfg_data', null, "AND cfg_name='masters'")), ';'));
							$zones .= "\tmasters { " . trim($fm_dns_acls->parseACL($domain_master_servers), '; ') . "; };\n";
							break;
						case 'forward':
							$zones .= $this->getZoneOptions($zone_result[$i]->domain_id, $server_serial_no, $domain_type). (string) $auto_zone_options;
					}
					$zones .= "};\n";
	
					/** Add domain_id to built_domain_ids for tracking */
					$GLOBALS['built_domain_ids'][] = $zone_result[$i]->domain_id;
					if (isset($zone_result[$i]->parent_domain_id)) {
						$GLOBALS['built_domain_ids'][] = $zone_result[$i]->parent_domain_id;
					}
				}
			}
			
			if ($view_name) {
				$files[$server_zones_dir . '/zones.conf.' . $view_name] = $zones;
			} else {
				$files[$server_zones_dir . '/zones.conf.all'] = $zones;
			}
		}
		return array($files, $error);
	}
	
	
	/**
	 * Builds the zone file for $domain_id
	 *
	 * @since 1.0
	 * @package fmDNS
	 */
	function buildZoneFile($domain, $server_serial_no) {
		global $__FM_CONFIG;
		
		include(ABSPATH . 'fm-includes/version.php');
		
		/** Get datetime formatting */
		$date_format = getOption('date_format', $_SESSION['user']['account_id']);
		$time_format = getOption('time_format', $_SESSION['user']['account_id']);
		
		$zone_file = '; This file was built using ' . $_SESSION['module'] . ' ' . $__FM_CONFIG[$_SESSION['module']]['version'] . ' on ' . date($date_format . ' ' . $time_format . ' e') . "\n\n";
		
		/** get the SOA */
		$zone_file .= $this->buildSOA($domain);
		
		/** get the records */
		$zone_file .= $this->buildRecords($domain, $server_serial_no);
		
		return $zone_file;
	}
	
	
	/**
	 * Figures out what files to update on the DNS server
	 *
	 * @since 1.0
	 * @package fmDNS
	 */
	function buildCronConfigs($post_data) {
		global $fmdb, $__FM_CONFIG;
		
		$server_serial_no = sanitize($post_data['SERIALNO']);
		extract($post_data);
		
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', $server_serial_no, 'server_', 'server_serial_no');
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			$data = $result[0];
			extract(get_object_vars($data), EXTR_SKIP);
			
			/** check if this server is configured for cron updates */
			if ($server_update_method != 'cron') {
				$error = "This server is not configured to receive updates via cron.\n";
				if ($compress) echo gzcompress(serialize($error));
				else echo serialize($error);
				return;
			}
			
			/** check if there are updates */
			if ($server_update_config == 'no') {
				$error = "No updates found.\n";
				if ($compress) echo gzcompress(serialize($error));
				else echo serialize($error);
				return;
			}
			
			/** purge configs first? */
			$data->purge_config_files = getOption('purge_config_files', getAccountID($post_data['AUTHKEY']), 'fmDNS');
			
			/** process zone reloads if present */
			$track_reloads = $this->getReloadRequests($server_serial_no);
			if ($track_reloads && $server_update_config == 'yes') {
				/** process zone config build */
				for ($i=0; $i < count($track_reloads); $i++) {
					$query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE `domain_status`='active' AND (`domain_id`=" . $track_reloads[$i]->domain_id . " OR `domain_clone_domain_id`=" . $track_reloads[$i]->domain_id . 
							") ORDER BY `domain_clone_domain_id`,`domain_name`";
					$result = $fmdb->query($query);
					if ($fmdb->num_rows) {
						$zone_result = $fmdb->last_result[0];
						/** Is this a clone id? */
						if ($zone_result->domain_clone_domain_id) $zone_result = $this->mergeZoneDetails($zone_result, 'clone');
						elseif ($zone_result->domain_template_id) $zone_result = $this->mergeZoneDetails($zone_result, 'template');
						
						if (getSOACount($zone_result->domain_id)) {
							$domain_name = $this->getDomainName($zone_result->domain_mapping, trimFullStop($zone_result->domain_name));
							$file_ext = ($zone_result->domain_mapping == 'forward') ? 'hosts' : 'rev';

							/** Are there multiple zones with the same name? */
					if (isset($zone_result->parent_domain_id)) {
						basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $zone_result->domain_name, 'domain_', 'domain_name', 'AND domain_id!=' . $zone_result->parent_domain_id);
						if ($fmdb->num_rows) $file_ext = $zone_result->parent_domain_id . ".$file_ext";
					} else {
						$zone_result->parent_domain_id = $zone_result->domain_id;
						basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $zone_result->domain_name, 'domain_', 'domain_name', 'AND domain_id!=' . $zone_result->domain_id);
						if ($fmdb->num_rows) $file_ext = $zone_result->domain_id . ".$file_ext";
					}
//							basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $zone_result->domain_name, 'domain_', 'domain_name', 'AND domain_clone_domain_id=0 AND domain_id!=' . $zone_result->domain_id);
//							if ($fmdb->num_rows) $file_ext = $zone_result->domain_id . ".$file_ext";
							
							/** Build zone file */
							$data->files[$server_zones_dir . '/' . $zone_result->domain_type . '/db.' . $domain_name . $file_ext] = $this->buildZoneFile($zone_result);
							
							/** Track reloads */
							$data->reload_domain_ids[] = isset($zone_result->parent_domain_id) ? $zone_result->parent_domain_id : $zone_result->domain_id;
						}
					}
				}
				if (is_array($data->files)) return get_object_vars($data);
			} else {
				/** process server config build */
				list($config, $message) = $this->buildServerConfig($post_data);
				$config['server_build_all'] = true;
				$config['purge_config_files'] = $data->purge_config_files;
				
				return $config;
			}
			
		}
		
		/** Bad DNS server */
		$error = "DNS server is not found.\n";
		if ($compress) echo gzcompress(serialize($error));
		else echo serialize($error);
	}
	
	/**
	 * Gets count of zones to reload based on server_serial_no
	 *
	 * @since 1.0
	 * @package fmDNS
	 */
	function getReloadRequests($server_serial_no) {
		global $fmdb, $__FM_CONFIG;
		
		$query = "SELECT * FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}track_reloads WHERE server_serial_no='" . $server_serial_no . "'";
		$track_reloads_result = $fmdb->query($query);

		if ($fmdb->num_rows) {
			$track_reloads = $fmdb->last_result;
			return $track_reloads;
		}
		
		return false;
	}
	

	/**
	 * Builds the SOA for $domain->domain_id
	 *
	 * @since 1.0
	 * @package fmDNS
	 */
	function buildSOA($domain) {
		global $fmdb, $__FM_CONFIG;
		
		$zone_file = null;
		
		$query = "SELECT * FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}domains d, fm_{$__FM_CONFIG['fmDNS']['prefix']}soa s WHERE 
			domain_status='active' AND d.account_id='{$_SESSION['user']['account_id']}' AND s.account_id='{$_SESSION['user']['account_id']}'
			AND s.soa_id=d.soa_id AND d.domain_id IN ('{$domain->parent_domain_id}','{$domain->domain_id}','{$domain->domain_template_id}')";
		$fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$soa_result = $fmdb->last_result;
			extract(get_object_vars($soa_result[0]));
			
			$domain_name_trim = trimFullStop($domain->domain_name);
			
			$master_server = ($soa_append == 'yes') ? trimFullStop($soa_master_server) . '.' . $domain_name_trim . '.' : trimFullStop($soa_master_server) . '.';
			$admin_email = ($soa_append == 'yes') ? trimFullStop($soa_email_address) . '.' . $domain_name_trim . '.' : trimFullStop($soa_email_address) . '.';
			
			$domain_name = $this->getDomainName($domain->domain_mapping, $domain_name_trim);
			
			$domain_id = isset($domain->parent_domain_id) ? $domain->parent_domain_id : $domain->domain_id;
			$serial = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'soa_serial_no');
			
			$zone_file .= '$TTL ' . $soa_ttl . "\n";
			$zone_file .= "$domain_name IN SOA $master_server $admin_email (\n";
			$zone_file .= "\t\t$serial\t; Serial\n";
			$zone_file .= "\t\t$soa_refresh\t\t; Refresh\n";
			$zone_file .= "\t\t$soa_retry\t\t; Retry\n";
			$zone_file .= "\t\t$soa_expire\t\t; Expire\n";
			$zone_file .= "\t\t$soa_ttl )\t\t; Negative caching of TTL\n\n";
		}
		
		return $zone_file;
	}


	/**
	 * Builds the records for $domain->domain_id
	 *
	 * @since 1.0
	 * @package fmDNS
	 */
	function buildRecords($domain, $server_serial_no) {
		global $fmdb, $__FM_CONFIG;
		
		$zone_file = $skipped_records = null;
		$domain_name_trim = trimFullStop($domain->domain_name);
		list($server_version) = explode('-', getNameFromID($server_serial_no, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_version'));
		
		/** Is this a cloned zone */
		if (isset($domain->parent_domain_id)) {
			$full_zone_clone = (getOption('clones_use_dnames', $_SESSION['user']['account_id'], $_SESSION['module']) == 'yes') ? true : false;
			if ($domain->domain_clone_dname) {
				$full_zone_clone = ($domain->domain_clone_dname == 'yes') ? true : false;
			}
			
			/** Are there any additional records? */
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $domain->parent_domain_id, 'record_', 'domain_id', "AND `record_status`='active'");
			if ($fmdb->num_rows) {
				$full_zone_clone = false;
			}
			
			/** Are there any skipped records? */
			global $fm_dns_records;
			if (!class_exists('fm_dns_records')) include(ABSPATH . 'fm-modules/fmDNS/classes/class_records.php');
			if ($skipped_records = $fm_dns_records->getSkippedRecordIDs($domain->parent_domain_id)) $full_zone_clone = false;
			
			$valid_domain_ids = $full_zone_clone == false ? "IN (" . join(',', getZoneParentID($domain->parent_domain_id)) . ')' : "='{$domain->domain_id}' AND record_type='NS'";
		} else {
			$valid_domain_ids = "='{$domain->domain_id}'";
		}
		$order_sql = ($domain->domain_mapping == 'reverse') ? array('record_type', 'INET_ATON(record_name)', 'record_value') : array('record_type', 'record_name', 'INET_ATON(record_value)');
		$record_sql = "AND domain_id $valid_domain_ids AND record_status='active'";
		$record_sql .= $skipped_records ? ' AND record_id NOT IN (' . implode(',', $skipped_records) . ')' : null;
		$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $order_sql, 'record_', $record_sql);
		
		if ($fmdb->num_rows) {
			$count = $fmdb->num_rows;
			$record_result = $fmdb->last_result;
			$separator = '   ';
			
			/** Add full zone clone dname record */
			if (isset($domain->parent_domain_id) && $full_zone_clone == true) {
				$record_result[$count]->record_name = '@';
				$record_result[$count]->record_value = trimFullStop(getNameFromID($domain->domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name')) . '.';
				$record_result[$count]->record_ttl = null;
				$record_result[$count]->record_class = 'IN';
				$record_result[$count]->record_type = 'DNAME';
				$record_result[$count]->record_append = 'no';
				$record_result[$count]->record_comment = null;
				
				$count++;
			}
			
			for ($i=0; $i < $count; $i++) {
				$domain_name = $this->getDomainName($domain->domain_mapping, $domain_name_trim);
				$record_comment = $record_result[$i]->record_comment ?  ' ; ' . $record_result[$i]->record_comment : null;
				$record_name = ($record_result[$i]->record_append == 'yes') ? $record_result[$i]->record_name . '.' . $domain_name_trim . '.' : $record_result[$i]->record_name;
				if ($record_result[$i]->record_name[0] == '@') {
					$record_name = $domain_name;
				}
				switch($record_result[$i]->record_type) {
					case 'A':
					case 'AAAA':
						$record_array[$record_result[$i]->record_type]['Description'] = 'Host addresses';
						$record_array[$record_result[$i]->record_type]['Data'][] = str_pad($record_name, 25) . $separator . $record_result[$i]->record_ttl . $separator . $record_result[$i]->record_class . $separator . $record_result[$i]->record_type . $separator . $record_result[$i]->record_value . $record_comment . "\n";
						break;
					case 'CERT':
						$record_array[$record_result[$i]->record_type]['Version'] = '9.7.0';
						$record_array[$record_result[$i]->record_type]['Description'] = 'Certificates';
						$record_array[$record_result[$i]->record_type]['Data'][] = str_pad($record_name, 25) . $separator . $record_result[$i]->record_ttl . $separator . $record_result[$i]->record_class . $separator . $record_result[$i]->record_type . $separator . $record_result[$i]->record_cert_type . ' ' . $record_result[$i]->record_key_tag . ' ' . $record_result[$i]->record_algorithm . "\t(\n\t\t\t" . str_replace("\n", "\n\t\t\t", $record_result[$i]->record_value) . ' )' . $record_comment . "\n";
						break;
					case 'CNAME':
					case 'DNAME':
						$record_array[$record_result[$i]->record_type]['Description'] = 'Aliases';
						$record_value = ($record_result[$i]->record_append == 'yes') ? $record_result[$i]->record_value . '.' . $domain_name_trim . '.' : $record_result[$i]->record_value;
						$record_array[$record_result[$i]->record_type]['Data'][] = str_pad($record_name, 25) . $separator . $record_result[$i]->record_ttl . $separator . $record_result[$i]->record_class . $separator . $record_result[$i]->record_type . $separator . $record_value . $record_comment . "\n";
						break;
					case 'DHCID':
						$record_array[$record_result[$i]->record_type]['Version'] = '9.5.0';
						$record_array[$record_result[$i]->record_type]['Description'] = 'DHCP ID records';
//						$record_array[$record_result[$i]->record_type]['Data'][] = str_pad($record_name, 25) . $separator . $record_result[$i]->record_ttl . $separator . $record_result[$i]->record_class . $separator . $record_result[$i]->record_type . $separator . $record_result[$i]->record_flags . ' 3 ' . $record_result[$i]->record_algorithm . "\t(\n\t\t\t" . str_replace("\n", "\n\t\t\t", $record_result[$i]->record_value) . ' )' . $record_comment . "\n";
						break;
					case 'DLV':
					case 'DS':
						$record_array[$record_result[$i]->record_type]['Description'] = 'DNSSEC Lookaside Validation';
//						$record_array[$record_result[$i]->record_type]['Data'][] = str_pad($record_name, 25) . $separator . $record_result[$i]->record_ttl . $separator . $record_result[$i]->record_class . $separator . $record_result[$i]->record_type . $separator . $record_result[$i]->record_cert_type . ' ' . $record_result[$i]->record_key_tag . ' ' . $record_result[$i]->record_algorithm . "\t(\n\t\t\t" . str_replace("\n", "\n\t\t\t", $record_result[$i]->record_value) . ' )' . $record_comment . "\n";
						break;
					case 'DNSKEY':
					case 'KEY':
						$record_array[$record_result[$i]->record_type]['Version'] = '9.5.0';
						$record_array[$record_result[$i]->record_type]['Description'] = 'Key records';
						$record_array[$record_result[$i]->record_type]['Data'][] = str_pad($record_name, 25) . $separator . $record_result[$i]->record_ttl . $separator . $record_result[$i]->record_class . $separator . $record_result[$i]->record_type . $separator . $record_result[$i]->record_flags . ' 3 ' . $record_result[$i]->record_algorithm . "\t(\n\t\t\t" . str_replace("\n", "\n\t\t\t", $record_result[$i]->record_value) . ' )' . $record_comment . "\n";
						break;
					case 'HINFO':
						$record_array[$record_result[$i]->record_type]['Description'] = 'Hardware information records';
						$hardware = (strpos($record_result[$i]->record_value, ' ') === false) ? $record_result[$i]->record_value : '"' . $record_result[$i]->record_value . '"';
						$os = (strpos($record_result[$i]->record_os, ' ') === false) ? $record_result[$i]->record_os : '"' . $record_result[$i]->record_os . '"';
						$record_array[$record_result[$i]->record_type]['Data'][] = str_pad($record_name, 25) . $separator . $record_result[$i]->record_ttl . $separator . $record_result[$i]->record_class . $separator . $record_result[$i]->record_type . $separator . $hardware . ' ' . $os . $record_comment . "\n";
						break;
					case 'KX':
						$record_array[$record_result[$i]->record_type]['Description'] = 'Key Exchange records';
						$record_array[$record_result[$i]->record_type]['Data'][] = str_pad($record_name, 25) . $separator . $record_result[$i]->record_ttl . $separator . $record_result[$i]->record_class . $separator . $record_result[$i]->record_type . $separator . $record_result[$i]->record_priority . $separator . $record_result[$i]->record_value . $record_comment . "\n";
						break;
					case 'MX':
						$record_array[2 . $record_result[$i]->record_type]['Description'] = 'Mail Exchange records';
						$record_array[2 . $record_result[$i]->record_type]['Data'][] = str_pad($record_name, 25) . $separator . $record_result[$i]->record_ttl . $separator . $record_result[$i]->record_class . $separator . $record_result[$i]->record_type . $separator . $record_result[$i]->record_priority . $separator . $record_result[$i]->record_value . $record_comment . "\n";
						break;
					case 'TXT':
						$record_array[$record_result[$i]->record_type]['Description'] = 'TXT records';
						$record_array[$record_result[$i]->record_type]['Data'][] = str_pad($record_name, 25) . $separator . $record_result[$i]->record_ttl . $separator . $record_result[$i]->record_class . $separator . $record_result[$i]->record_type . "\t(\"" . join("\";\n\t\t\"", $this->characterSplit($record_result[$i]->record_value)) . "\")" . $record_comment . "\n";
						break;
					case 'SSHFP':
						$record_array[$record_result[$i]->record_type]['Version'] = '9.3.0';
						$record_array[$record_result[$i]->record_type]['Description'] = 'SSH Key Fingerprint records';
						$record_array[$record_result[$i]->record_type]['Data'][] = str_pad($record_name, 25) . $separator . $record_result[$i]->record_ttl . $separator . $record_result[$i]->record_class . $separator . $record_result[$i]->record_type . $separator . $record_result[$i]->record_algorithm . ' 1 ' . $record_result[$i]->record_value . $record_comment . "\n";
						break;
					case 'SRV':
						$record_array[$record_result[$i]->record_type]['Description'] = 'Service records';
						$record_value = ($record_result[$i]->record_append == 'yes') ? $record_result[$i]->record_value . '.' . $domain_name_trim . '.' : $record_result[$i]->record_value;
						$record_array[$record_result[$i]->record_type]['Data'][] = str_pad($record_name, 25) . $separator . $record_result[$i]->record_ttl . $separator . $record_result[$i]->record_class . $separator . $record_result[$i]->record_type . $separator . $record_result[$i]->record_priority . $separator . $record_result[$i]->record_weight . $separator . $record_result[$i]->record_port . $separator . $record_value . $record_comment . "\n";
						break;
					case 'PTR':
						$record_array[$record_result[$i]->record_type]['Description'] = 'Addresses point to hosts';
						$record_name = ($record_result[$i]->record_append == 'yes' && $domain->domain_mapping == 'reverse') ? $record_result[$i]->record_name . '.' . $domain_name : $record_result[$i]->record_name;
						$record_array[$record_result[$i]->record_type]['Data'][] = str_pad($record_name, 25) . $separator . $record_result[$i]->record_ttl . $separator . $record_result[$i]->record_class . $separator . $record_result[$i]->record_type . $separator . $record_result[$i]->record_value . $record_comment . "\n";
						break;
					case 'RP':
						$record_array[$record_result[$i]->record_type]['Description'] = 'Responsible Persons';
						$record_value = ($record_result[$i]->record_append == 'yes') ? $record_result[$i]->record_value . '.' . $domain_name_trim . '.' : $record_result[$i]->record_value;
						$record_text = ($record_result[$i]->record_append == 'yes') ? $record_result[$i]->record_text . '.' . $domain_name_trim . '.' : $record_result[$i]->record_text;
						if (!strlen($record_result[$i]->record_text)) $record_text = '.';
						$record_array[$record_result[$i]->record_type]['Data'][] = str_pad($record_name, 25) . $separator . $record_result[$i]->record_ttl . $separator . $record_result[$i]->record_class . $separator . $record_result[$i]->record_type . $separator . $record_value . $separator . $record_text . $record_comment . "\n";
						break;
					case 'NS':
						$record_array[1 . $record_result[$i]->record_type]['Description'] = 'Name servers';
						$record_value = ($record_result[$i]->record_append == 'yes') ? $record_result[$i]->record_value . '.' . $domain_name_trim . '.' : $record_result[$i]->record_value;
						$record_array[1 . $record_result[$i]->record_type]['Data'][] = str_pad($record_name, 25) . $separator . $record_result[$i]->record_ttl . $separator . $record_result[$i]->record_class . $separator . $record_result[$i]->record_type . $separator . $record_value . $record_comment . "\n";
						break;
				}
			}
			
			ksort($record_array);
			
			/** Zone file output */
			foreach ($record_array as $rr=>$rr_array) {
				/** Check if rr is supported by server_version */
				if (array_key_exists('Version', $rr_array) && version_compare($server_version, $rr_array['Version'], '<')) {
					$zone_file .= ";\n; BIND " . $rr_array['Version'] . ' or greater is required for ' . $rr . ' types.' . "\n;\n\n";
					continue;
				}
				
				$zone_file .= '; ' . $rr_array['Description'] . "\n";
				$zone_file .= implode('', $rr_array['Data']);
				$zone_file .= "\n";
			}
		}
		
		return $zone_file;
	}
	
	
	/**
	 * Returns the $domain_name based on $map
	 *
	 * @since 1.0
	 * @package fmDNS
	 */
	function getDomainName($map, $domain_name_trim) {
		if ($map == 'reverse' && substr($domain_name_trim, -5) != '.arpa') {
			@list($octet1, $octet2, $octet3) = explode('.', $domain_name_trim);
			if ($octet3 != null) {
				$domain_name = "$octet3.$octet2.$octet1.in-addr.arpa.";
			} elseif ($octet2 != null) {
				$domain_name = "$octet2.$octet1.in-addr.arpa.";
			} else {
				$domain_name = "$octet1.in-addr.arpa.";
			}
		} else $domain_name = $domain_name_trim . '.';
		
		return $domain_name;
	}
	
	
	/**
	 * Updates tables to reset flags
	 *
	 * @since 1.0
	 * @package fmDNS
	 */
	function updateReloadFlags($post_data) {
		global $fmdb, $__FM_CONFIG;
		
		$server_serial_no = sanitize($post_data['SERIALNO']);
		extract($post_data);
		
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', $server_serial_no, 'server_', 'server_serial_no');
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			$data = $result[0];
			extract(get_object_vars($data), EXTR_SKIP);

			$msg = (!setBuildUpdateConfigFlag($server_serial_no, 'no', 'build') || !setBuildUpdateConfigFlag($server_serial_no, 'no', 'update')) ? "Could not update the backend database.\n" : "Success.\n";
			$msg = "Success.\n";
			if (isset($built_domain_ids)) {
				$this->setBuiltDomainIDs($server_serial_no, $built_domain_ids);
			}
			if (isset($reload_domain_ids)) {
				$query = "DELETE FROM `fm_" . $__FM_CONFIG['fmDNS']['prefix'] . "track_reloads` WHERE `server_serial_no`='" . sanitize($server_serial_no) . "' AND domain_id IN (" . implode(',', array_unique($reload_domain_ids)) . ')';
				$fmdb->query($query);
			}
		} else $msg = "DNS server is not found.\n";
		
		if ($compress) echo gzcompress(serialize($msg));
		else echo serialize($msg);
	}
	
	
	/**
	 * Sets cloned details to that of the parent domain
	 *
	 * @since 1.0
	 * @package fmDNS
	 */
	function mergeZoneDetails($zone, $type) {
		global $fmdb, $__FM_CONFIG;
		
		if ($type == 'clone') {
			basicGet("fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", $zone->domain_clone_domain_id, 'domain_', 'domain_id');
		} elseif ($type == 'template') {
			basicGet("fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", $zone->domain_template_id, 'domain_', 'domain_id');
		}
		if ($fmdb->num_rows) {
			$parent_zone = $fmdb->last_result[0];
			$parent_zone->parent_domain_id = $zone->domain_id;
			$parent_zone->domain_id = ($zone->domain_clone_domain_id) ? $zone->domain_clone_domain_id : $zone->domain_template_id;
			$parent_zone->domain_name = $zone->domain_name;
			$parent_zone->domain_name_file = $zone->domain_name;
			$parent_zone->domain_clone_dname = $zone->domain_clone_dname;
			
			if ($zone->domain_view > -1) $parent_zone->domain_view = $zone->domain_view;
			
			return $parent_zone;
		}
		
		return false;
	}
	
	
	/**
	 * Updates the daemon version number in the database
	 *
	 * @since 1.0
	 * @package fmDNS
	 */
	function updateServerVersion() {
		global $fmdb, $__FM_CONFIG;
		
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` SET `server_version`='" . $_POST['server_version'] . "', `server_os`='" . $_POST['server_os'] . "' WHERE `server_serial_no`='" . $_POST['SERIALNO'] . "' AND `account_id`=
			(SELECT account_id FROM `fm_accounts` WHERE `account_key`='" . $_POST['AUTHKEY'] . "')";
		$fmdb->query($query);
	}
	
	
	/**
	 * Validate the daemon version number of the client
	 *
	 * @since 1.0
	 * @package fmDNS
	 */
	function validateDaemonVersion($data) {
		global $__FM_CONFIG;
		extract($data);
		
		if ($server_type == 'bind9') {
			$required_version = $__FM_CONFIG['fmDNS']['required_dns_version'];
		}
		
		if (version_compare($server_version, $required_version, '<')) {
			return false;
		}
		
		return true;
	}


	/**
	 * Update fm_{$__FM_CONFIG['fmDNS']['prefix']}track_builds
	 *
	 * @since 1.0
	 * @package fmDNS
	 */
	function setBuiltDomainIDs($server_serial_no, $built_domain_ids) {
		global $fmdb, $__FM_CONFIG;

		if (!empty($built_domain_ids)) {
			/** Delete old records first */
			basicDelete('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'track_builds', $server_serial_no, 'server_serial_no', false);
			basicDelete('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'track_reloads', $server_serial_no, 'server_serial_no', false);

			/** Add new records */
			$sql = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}track_builds` VALUES ";
			foreach ($built_domain_ids as $id) {
				$sql .= '(' . $id . ',' . $server_serial_no . '),';
			}
			$sql = rtrim($sql, ',');
			$fmdb->query($sql);
		}
	}
	
	
	/**
	 * Performs syntax checks with named-check* utilities
	 *
	 * @since 1.0
	 * @package fmDNS
	 *
	 * @param array $files_array Array containing named files and contents
	 * @return string
	 */
	function namedSyntaxChecks($files_array) {
		global $__FM_CONFIG;
		
		if (!array_key_exists('server_serial_no', $files_array)) return;
		if (getOption('enable_named_checks', $_SESSION['user']['account_id'], 'fmDNS') != 'yes') return;
		
		$die = false;
		$named_checkconf = findProgram('named-checkconf');
		
		$uname = php_uname('n');
		if (!$named_checkconf) {
			return <<<INFO
			<div id="named_check" class="info">
				<p>The named utilities (specifically named-checkconf and named-checkzone) cannot be found on $uname. If they were installed,
				these configs and zones could be checked for syntax.</p>
			</div>

INFO;
		}
		
		$fm_temp_directory = '/' . ltrim(getOption('fm_temp_directory'), '/');
		$tmp_dir = rtrim($fm_temp_directory, '/') . '/' . $_SESSION['module'] . '_' . date("YmdHis") . '/';
		system('rm -rf ' . $tmp_dir);
		$debian_system = isDebianSystem($files_array['server_os_distro']);
		$named_conf_contents = null;
		/** Create temporary directory structure */
		foreach ($files_array['files'] as $file => $contents) {
			if (!is_dir(dirname($tmp_dir . $file))) {
				if (!@mkdir(dirname($tmp_dir . $file), 0777, true)) {
					$class = 'class="info"';
					$message = $fm_temp_directory . ' is not writeable by ' . $__FM_CONFIG['webserver']['user_info']['name'] . ' so the named checks cannot be performed.';
					$die = true;
					break;
				}
			}
			file_put_contents($tmp_dir . $file, $contents);
			if ($debian_system && (strpos($file, 'named.conf.options') || strpos($file, 'named.conf.local'))) $named_conf_contents .= $contents;

			/** Create temporary directory from named.conf's 'directory' line */
			if (strpos($contents, 'directory')) {
				preg_match('/directory(.+?)+/', $contents, $directory_line);
				if (count($directory_line)) {
					$line_array = explode('"', $directory_line[0]);
					@mkdir($tmp_dir . $line_array[1], 0777, true);
					$named_conf = $file;
				}
			}
			
			/** Build array of zone files to check */
			if (preg_match('/\/zones\.conf\.(.+?)/', $file)) {
				$view = preg_replace('/(.+?)zones\.conf\.+/', '', $file);

				$tmp_contents = preg_replace('/^\/\/(.+?)+/', '', $contents);
				$tmp_contents = explode("};\n", trim($tmp_contents));
				foreach($tmp_contents as $zone_def) {
					if (strpos($zone_def, 'type master;') !== false) {
						preg_match('/^zone "(.+?)+/', $zone_def, $tmp_zone_def);
						$tmp_zone_def = explode('"', $tmp_zone_def[0]);
						preg_match('/file "(.+?)+/', trim($zone_def), $tmp_zone_def_file);
						$tmp_zone_def_file = explode('"', $tmp_zone_def_file[0]);
						if (!empty($tmp_zone_def_file[1])) {
							$zone_files[$view][$tmp_zone_def[1]] = $tmp_zone_def_file[1];
						}
					}
				}
			}
		}
		
		if ($debian_system) file_put_contents($tmp_dir . $named_conf, $named_conf_contents);
		
		if (!$die) {
			/** Run named-checkconf */
			$named_checkconf_cmd = findProgram('sudo') . ' ' . findProgram('named-checkconf') . ' -t ' . $tmp_dir . ' ' . $named_conf . ' 2>&1';
			exec($named_checkconf_cmd, $named_checkconf_results, $retval);
			if ($retval) {
				$class = 'class="error"';
				$named_checkconf_results = implode("\n", $named_checkconf_results);
				if (strpos($named_checkconf_results, 'sudo') !== false) {
					$class = 'class="info"';
					$message = 'The webserver user (' . $__FM_CONFIG['webserver']['user_info']['name'] . ') on ' . $uname . ' does not have permission to run 
								the following command:<br /><pre>' . $named_checkconf_cmd . '</pre><p>The following error ocurred:<pre>' .
								$named_checkconf_results . '</pre>';
				} else {
					$message = 'Your named configuration contains one or more errors:<br /><pre>' . $named_checkconf_results . '</pre>';
				}
				
			/** Run named-checkconf */
			} else {
				$named_checkzone_results = null;
				if (array($zone_files)) {
					foreach ($zone_files as $view => $zones) {
						foreach ($zones as $zone_name => $zone_file) {
							$named_checkzone_cmd = findProgram('sudo') . ' ' . findProgram('named-checkzone') . ' -t ' . $tmp_dir . ' ' . $zone_name . ' ' . $zone_file . ' 2>&1';
							exec($named_checkzone_cmd, $results, $retval);
							if ($retval) {
								$class = 'class="error"';
								$named_checkzone_results .= implode("\n", $results);
								if (strpos($named_checkzone_results, 'sudo') !== false) {
									$class = 'class="info"';
									$message = 'The webserver user (' . $__FM_CONFIG['webserver']['user_info']['name'] . ') on ' . $uname . ' does not have permission to run 
												the following command:<br /><pre>' . $named_checkzone_cmd . '</pre><p>The following error ocurred:<pre>' .
												$named_checkzone_results . '</pre>';
									break 2;
								}
							}
						}
					}
				}
				
				if ($named_checkzone_results) {
					if (empty($message)) $message = 'Your zone configuration files contain one or more errors:<br /><pre>' . $named_checkzone_results . '</pre>';
				} else {
					$class = null;
					$message = 'Your named configuration and zone files are loadable.';
				}
			}
		}
		
		/** Remove temporary directory */
		system('rm -rf ' . $tmp_dir);
		
		return <<<HTML
			<div id="named_check" $class>
				<p>$message</p>
			</div>

HTML;
	}
	
	
	/**
	 * Formats the server key statements
	 *
	 * @since 1.2
	 * @package fmDNS
	 *
	 * @param string $server_name The server name
	 * @param string $key_name The key name
	 * @param boolean $view Add extra tabs if this config is part of a view
	 * @return string
	 */
	function formatServerKeys($server_name, $key_name, $view = false) {
		$extra_tab = ($view == true) ? "\t" : null;
		$server_ip = gethostbyname($server_name);
		$servers = ($server_ip) ? $extra_tab . 'server ' . $server_ip . " {\n" : "server [cannot resolve " . $server_name . "] {\n";
		$servers .= "$extra_tab\tkeys { \"$key_name\"; };\n";
		$servers .= "$extra_tab};\n";
		
		return $servers;
	}


	/**
	 * Gets the hint zone
	 *
	 * @since 1.3
	 * @package fmDNS
	 *
	 * @return string
	 */
	function getHintZone() {
		$local_hint_zone = ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/extra/named.root';
		
		$error = $this->getLatestRootServers();
		
		$hint_zone = file_get_contents($local_hint_zone);
		
		return array($hint_zone, $error);
	}


	/**
	 * Gets the latest root servers
	 *
	 * @since 1.3
	 * @package fmDNS
	 */
	function getLatestRootServers() {
		global $__FM_CONFIG;
		
		$local_hint_zone = ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/extra/named.root';
		$remote_hint_zone = 'http://www.internic.net/domain/named.root';
		
		$remote_headers = get_headers($remote_hint_zone, 1);
		
		if (filemtime($local_hint_zone) < strtotime($remote_headers['Last-Modified']) && !isset($GLOBALS['root_servers_updated'])) {
			$GLOBALS['root_servers_updated'] = true;
			
			/** Download the latest root servers (must be writeable by web server) */
			if (is_writeable($local_hint_zone)) {
				file_put_contents($local_hint_zone, fopen($remote_hint_zone, 'r'));
			} else {
				return <<<HTML
			<div id="named_check" class="info">
				<p>The root servers have been recently updated, but the webserver user ({$__FM_CONFIG['webserver']['user_info']['name']}) cannot write to $local_hint_zone to update the hint zone.</p>
				<p>A local copy will be used instead.</p>
			</div>
HTML;
			}
		}
		
		return null;
	}


	/**
	 * Formats the server key statements
	 *
	 * @since 1.3
	 * @package fmDNS
	 *
	 * @param array $domain_ids The domain_ids of the zone
	 * @param integer $server_serial_no The server serial number
	 * @param string $domain_type Type of zone (master, slave, etc.)
	 * @return string
	 */
	function getZoneOptions($domain_ids, $server_serial_no, $domain_type) {
		global $fmdb, $__FM_CONFIG, $fm_module_options;
		
		/** Ensure $domain_ids is an array) */
		if (!is_array($domain_ids)) {
			$temp_domain_ids[] = $domain_ids;
			$domain_ids = $temp_domain_ids;
			unset($temp_domain_ids);
		}
		
		/** Remove zeros */
		foreach ($domain_ids as $key => $id) {
			if (!$id) unset($domain_ids[$key]);
		}
		
		include_once(ABSPATH . 'fm-modules/fmDNS/classes/class_options.php');
		$config = null;
		
		$server_root_dir = getNameFromID($server_serial_no, "fm_{$__FM_CONFIG['fmDNS']['prefix']}servers", 'server_', 'server_serial_no', 'server_root_dir');
		
		$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_name', 'cfg_', "AND cfg_type='global' AND domain_id IN ('" . join("','", $domain_ids) . "') AND server_serial_no='0' AND cfg_status='active'");
		if ($fmdb->num_rows) {
			$config_result = $fmdb->last_result;
			$global_config_count = $fmdb->num_rows;
			for ($i=0; $i < $global_config_count; $i++) {
				$global_config[$config_result[$i]->cfg_name] = array($config_result[$i]->cfg_data, $config_result[$i]->cfg_comment);
			}
		} else $global_config = array();

		/** Override with server-specific configs */
		$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_name', 'cfg_', "AND cfg_type='global' AND domain_id IN ('" . join("','", $domain_ids) . "') AND server_serial_no='$server_serial_no' AND cfg_status='active'");
		if ($fmdb->num_rows) {
			$server_config_result = $fmdb->last_result;
			$global_config_count = $fmdb->num_rows;
			for ($j=0; $j < $global_config_count; $j++) {
				$server_config[$server_config_result[$j]->cfg_name] = @array($server_config_result[$j]->cfg_data, $config_result[$j]->cfg_comment);
			}
		} else $server_config = array();

		/** Merge arrays */
		$config_array = array_merge($global_config, $server_config);
		
		foreach ($config_array as $cfg_name => $cfg_data) {
			$query = "SELECT def_multiple_values FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}functions WHERE def_option = '{$cfg_name}' AND def_zone_support LIKE '%" . strtoupper(substr($domain_type, 0, 1)) . "%'";
			$fmdb->get_results($query);
			if (!$fmdb->num_rows) {
				continue;
			} else {
				$def_multiple_values = $fmdb->last_result[0]->def_multiple_values;
			}
			list($cfg_info, $cfg_comment) = $cfg_data;
			
			if ($cfg_comment) {
				$comment = wordwrap($cfg_comment, 50, "\n");
				$config .= "\n\t// " . str_replace("\n", "\n\t// ", $comment) . "\n";
				unset($comment);
			}
			$config .= "\t" . $cfg_name . ' ';
			if ($def_multiple_values == 'yes' && strpos($cfg_info, '{') === false) $config .= '{ ';
			
			/** Parse address_match_element configs */
			$cfg_info = $fm_module_options->parseDefType($cfg_name, $cfg_info);

			$config .= str_replace('$ROOT', $server_root_dir, trim(rtrim(trim($cfg_info), ';')));
			if ($def_multiple_values == 'yes' && strpos($cfg_info, '}') === false) $config .= '; }';
			$config .= ";\n";

			unset($cfg_info);
			if ($cfg_comment) $config .= "\n";
		}

		return $config;
	}
	
	/**
	 * Formats the server key statements
	 *
	 * @since 2.0
	 * @package fmDNS
	 *
	 * @param integer $view_id The view_id of the zone
	 * @param integer $server_serial_no The server serial number for overrides
	 * @return string
	 */
	function getRateLimits($view_id, $server_serial_no) {
		global $fmdb, $__FM_CONFIG, $fm_dns_acls;
		
		$ratelimits = $ratelimits_domains = $rate_config_array = null;
		
		basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', array('domain_id', 'server_serial_no', 'cfg_name'), 'cfg_', 'AND cfg_type="ratelimit" AND view_id=' . $view_id . ' AND server_serial_no="0" AND cfg_status="active"');
		if ($fmdb->num_rows) {
			$rate_result = $fmdb->last_result;
			$global_rate_count = $fmdb->num_rows;
			for ($i=0; $i < $global_rate_count; $i++) {
				if ($rate_result[$i]->domain_id) {
					$rate_config_array['domain'][displayFriendlyDomainName(getNameFromID($rate_result[$i]->domain_id, "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domains", 'domain_', 'domain_id', 'domain_name', null, 'active'))][$rate_result[$i]->cfg_name][] = array($rate_result[$i]->cfg_data, $rate_result[$i]->cfg_comment);
				} else {
					$rate_config_array[$rate_result[$i]->cfg_name][] = array($rate_result[$i]->cfg_data, $rate_result[$i]->cfg_comment);
				}
			}
		}
		
		/** Override with server-specific configs */
		basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', array('domain_id', 'server_serial_no', 'cfg_name'), 'cfg_', 'AND cfg_type="ratelimit" AND view_id=' . $view_id . ' AND server_serial_no=' . $server_serial_no . ' AND cfg_status="active"');
		if ($fmdb->num_rows) {
			$server_config_result = $fmdb->last_result;
			$global_config_count = $fmdb->num_rows;
			for ($i=0; $i < $global_config_count; $i++) {
				if ($server_config_result[$i]->domain_id) {
					$server_config['domain'][displayFriendlyDomainName(getNameFromID($server_config_result[$i]->domain_id, "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domains", 'domain_', 'domain_id', 'domain_name', null, 'active'))][$server_config_result[$i]->cfg_name][] = array($server_config_result[$i]->cfg_data, $server_config_result[$i]->cfg_comment);
				} else {
					$server_config[$server_config_result[$i]->cfg_name][] = array($server_config_result[$i]->cfg_data, $server_config_result[$i]->cfg_comment);
				}
			}
		} else $server_config = array();

		/** Merge arrays */
		$rate_config_array = array_merge((array)$rate_config_array, $server_config);
		
		
		/** Check if rrl is supported by server_version */
		if (count($rate_config_array)) {
			list($server_version) = explode('-', getNameFromID($server_serial_no, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_version'));
			if (version_compare($server_version, '9.9.4', '<')) {
				return "\t//\n\t// BIND 9.9.4 or greater is required for Response Rate Limiting.\n\t//\n\n";
			}
		}
		
		foreach ($rate_config_array as $cfg_name => $value_array) {
			foreach ($value_array as $domain_name => $cfg_data) {
				if ($cfg_name != 'domain') {
					list($cfg_info, $cfg_comment) = $cfg_data;
					$ratelimits .= $this->formatConfigOption ($cfg_name, $cfg_info, $cfg_comment);
				} else {
					foreach ($cfg_data as $domain_cfg_name => $domain_cfg_data) {
						$ratelimits_domains .= "\t};\n\trate-limit {\n\t\tdomain $domain_name;\n";
						foreach ($domain_cfg_data as $domain_cfg_data2) {
							list($cfg_param, $cfg_comment) = $domain_cfg_data2;
							$ratelimits_domains .= $this->formatConfigOption ($domain_cfg_name, $cfg_param, $cfg_comment);
						}
					}
				}
			}
		}
		return ($ratelimits || $ratelimits_domains) ? "\trate-limit {\n{$ratelimits}{$ratelimits_domains}\t};\n\n" : null;
	}
	
	/**
	 * Formats the config option statements
	 *
	 * @since 2.0
	 * @package fmDNS
	 *
	 * @param string $cfg_name Config option name
	 * @param string $cfg_info Config option values
	 * @param string $cfg_comment Config option comment
	 * @return string
	 */
	function formatConfigOption($cfg_name, $cfg_info, $cfg_comment) {
		global $fmdb, $__FM_CONFIG, $fm_dns_acls;
		
		$config = null;
		
		$query = "SELECT def_multiple_values FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}functions WHERE def_option = '{$cfg_name}'";
		$fmdb->get_results($query);
		if (!$fmdb->num_rows) $def_multiple_values = 'no';
		else {
			$result = $fmdb->last_result[0];
			$def_multiple_values = $result->def_multiple_values;
		}
		if ($cfg_comment) {
			$comment = wordwrap($cfg_comment, 50, "\n");
			$config .= "\n\t\t// " . str_replace("\n", "\n\t\t// ", $comment) . "\n";
			unset($comment);
		}
		$config .= "\t\t" . $cfg_name . ' ';
		if ($def_multiple_values == 'yes' && strpos($cfg_info, '{') === false) $config .= '{ ';
		$cfg_info = strpos($cfg_info, 'acl_') !== false ? $fm_dns_acls->parseACL($cfg_info) : $cfg_info;
		$config .= trim(rtrim(trim($cfg_info), ';'));
		if ($def_multiple_values == 'yes' && strpos($cfg_info, '}') === false) $config .= '; }';
		$config .= ";\n";

		unset($cfg_info);
		if ($cfg_comment) $config .= "\n";
		
		return $config;
	}
	
	/**
	 * Processes the server groups to determine master/slave arrangement
	 *
	 * @since 2.0
	 * @package fmDNS
	 *
	 * @param array $zone_array The zone data
	 * @param integer $server_id The server id to check
	 * @return array
	 */
	function processServerGroups($zone_array, $server_id) {
		global $fmdb, $__FM_CONFIG;
		
		extract(get_object_vars($zone_array), EXTR_OVERWRITE);
		
		$domain_name_servers = explode(';', $domain_name_servers);
		if (!count($domain_name_servers) || in_array('0', $domain_name_servers) || 
				$domain_type != 'master' || in_array('s_' . $server_id, $domain_name_servers)) {
			return array($domain_type, null);
		}
		
		foreach ($domain_name_servers as $ids) {
			if ($ids == '0' || strpos($ids, 's_') !== false) continue;
			
			if (strpos($ids, 'g_') !== false) {
				basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'server_groups', preg_replace('/\D/', null, $ids), 'group_', 'group_id');
				if ($fmdb->num_rows) {
					extract(get_object_vars($fmdb->last_result[0]));
					
					$group_masters = explode(';', $group_masters);
					$group_slaves = explode(';', $group_slaves);
					
					if (in_array($server_id, $group_masters)) {
						return array($domain_type, null);
					}
					
					if (in_array($server_id, $group_slaves)) {
						return array('slave', sprintf("\tmasters { %s };\n", $this->resolveServerGroupMasters($group_masters)));
					}
				}
			}
		}
		
		return array($domain_type, null);
	}
	
	/**
	 * Attempts to resolve the master servers for the group
	 *
	 * @since 2.0
	 * @package fmDNS
	 *
	 * @param array $zone_array The zone data
	 * @param integer $server_id The server id to check
	 * @return array
	 */
	function resolveServerGroupMasters($masters) {
		global $__FM_CONFIG;
		
		if (!count($masters)) return null;
		
		foreach ($masters as $server_id) {
			$server_name = getNameFromID($server_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
			$server_ip = gethostbyname($server_name);
			$master_ips[] = ($server_ip != $server_name) ? $server_ip : sprintf(__('Cannot resolve %s'), $server_name);
		}
		
		return implode('; ', (array) $master_ips) . ';';
	}
	
	/**
	 * Attempts to resolve the master servers for the group
	 *
	 * @since 2.0
	 * @package fmDNS
	 *
	 * @param string $text The text to split
	 * @param integer $limit The number of characters to split at
	 * @return array
	 */
	function characterSplit($text, $limit = 255) {
		return explode("\n", wordwrap($text, $limit, "\n", true));
	}
}

if (!isset($fm_module_buildconf))
	$fm_module_buildconf = new fm_module_buildconf();

?>
