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
			$preview .= $contents . "\n\n";
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
		global $fmdb, $__FM_CONFIG;
		
		/** Get datetime formatting */
		$date_format = getOption('date_format', $_SESSION['user']['account_id']);
		$time_format = getOption('time_format', $_SESSION['user']['account_id']);
		
		setTimezone();
		
		$files = array();
		$server_serial_no = sanitize($post_data['SERIALNO']);
		extract($post_data);

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
			

			/** Build global configs */
			$config .= "options {\n";
			$config .= "\tdirectory \"" . str_replace('$ROOT', $server_root_dir, $config_dir_result[0]->cfg_data) . "\";\n";
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_id', 'cfg_', 'AND cfg_type="global" AND cfg_view=0 AND server_serial_no=0 AND cfg_status="active"');
			if ($fmdb->num_rows) {
				$config_result = $fmdb->last_result;
				$global_config_count = $fmdb->num_rows;
				for ($i=0; $i < $global_config_count; $i++) {
					$global_config[$config_result[$i]->cfg_name] = array($config_result[$i]->cfg_data, $config_result[$i]->cfg_comment);
				}
			} else $global_config = array();

			/** Override with server-specific configs */
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_id', 'cfg_', 'AND cfg_type="global" AND cfg_view=0 AND server_serial_no=' . $server_serial_no . ' AND cfg_status="active"');
			if ($fmdb->num_rows) {
				$server_config_result = $fmdb->last_result;
				$global_config_count = $fmdb->num_rows;
				for ($j=0; $j < $global_config_count; $j++) {
					$server_config[$server_config_result[0]->cfg_name] = array($server_config_result[0]->cfg_data, $config_result[$i]->cfg_comment);
				}
			} else $server_config = array();

			/** Merge arrays */
			$config_array = array_merge($global_config, $server_config);
			
			foreach ($config_array as $cfg_name => $cfg_data) {
				list($cfg_info, $cfg_comment) = $cfg_data;
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
				$config .= str_replace('$ROOT', $server_root_dir, trim(rtrim(trim($cfg_info), ';')));
				if ($def_multiple_values == 'yes' && strpos($cfg_info, '}') === false) $config .= '; }';
				$config .= ";\n";
				
				unset($cfg_info);
				if ($cfg_comment) $config .= "\n";
			}
			$config .= "};\n\n";
			
			/** Debian-based requires named.conf.options */
			if (isDebianSystem($server_os_distro)) {
				$data->files[dirname($server_config_file) . '/named.conf.options'] = $config;
				$config = $zones . "include \"" . dirname($server_config_file) . "/named.conf.options\";\n\n";
				$data->files[$server_config_file] = $config;
				$config = $zones;
			}
			

			/** Build logging config */
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_name` DESC,`cfg_data`,`cfg_id', 'cfg_', 'AND cfg_type="logging" AND cfg_isparent="yes" AND cfg_status="active" AND server_serial_no in (0, ' . $server_serial_no . ')');
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
			

			/** Build keys config */
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_id', 'key_', 'AND key_view=0 AND key_status="active"');
			if ($fmdb->num_rows) {
				$key_result = $fmdb->last_result;
				$key_config_count = $fmdb->num_rows;
				for ($i=0; $i < $key_config_count; $i++) {
					$key_name = trimFullStop($key_result[$i]->key_name) . '.';
					$keys .= $key_name . "\n";
					if ($key_result[$i]->key_comment) {
						$comment = wordwrap($key_result[$i]->key_comment, 50, "\n");
						$key_config .= '// ' . str_replace("\n", "\n// ", $comment) . "\n";
						unset($comment);
					}
					$key_config .= "key $key_name {\n";
					$key_config .= "\talgorithm " . $key_result[$i]->key_algorithm . ";\n";
					$key_config .= "\tsecret \"" . $key_result[$i]->key_secret . "\";\n";
					$key_config .= "};\n\n";
					
					/** Get associated servers */
					basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_id', 'server_', 'AND server_serial_no!=' . $server_serial_no . ' AND server_key=' . $key_result[$i]->key_id . ' AND server_status="active"');
					if ($fmdb->num_rows) {
						$server_result = $fmdb->last_result;
						$server_count = $fmdb->num_rows;
						for ($j=0; $j < $server_count; $j++) {
							$servers .= $this->formatServerKeys($server_result[$j]->server_name, $key_name);
						}
					}
				}
			}
			
			$config .= $logging . $servers;

			if ($keys) {
				$data->files[dirname($server_config_file) . '/named.conf.keys'] = $key_config;
			
				$config .= "include \"" . dirname($server_config_file) . "/named.conf.keys\";\n\n";
			}
			
			/** Build ACLs */
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_id', 'acl_', 'AND acl_status="active" AND server_serial_no=0');
			if ($fmdb->num_rows) {
				$acl_result = $fmdb->last_result;
				for ($i=0; $i < $fmdb->num_rows; $i++) {
					if ($acl_result[$i]->acl_predefined != 'as defined:') {
						$global_acl_array[$acl_result[$i]->acl_name] = array($acl_result[$i]->acl_predefined, $acl_result[$i]->acl_comment);
					} else {
						$addresses = explode(' ', $acl_result[$i]->acl_addresses);
						$global_acl_array[$acl_result[$i]->acl_name] = null;
						foreach($addresses as $address) {
							if(trim($address)) $global_acl_array[$acl_result[$i]->acl_name] .= "\t" . $address . "\n";
						}
						$global_acl_array[$acl_result[$i]->acl_name] = array(rtrim(ltrim($global_acl_array[$acl_result[$i]->acl_name], "\t"), ";\n"), $acl_result[$i]->acl_comment);
					}
				}

				/** Override with server-specific ACLs */
				basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_id', 'acl_', 'AND acl_status="active" AND server_serial_no=' . $server_serial_no);
				if ($fmdb->num_rows) {
					$server_acl_result = $fmdb->last_result;
					$acl_config_count = $fmdb->num_rows;
					for ($j=0; $j < $acl_config_count; $j++) {
						if ($server_acl_result[$j]->acl_predefined != 'as defined:') {
							$server_acl_array[$server_acl_result[$j]->acl_name] = array($server_acl_result[$j]->acl_predefined, $acl_result[$i]->acl_comment);
						} else {
							$addresses = explode(' ', $server_acl_result[$j]->acl_addresses);
							$server_acl_addresses = null;
							foreach($addresses as $address) {
								if(trim($address)) $server_acl_addresses .= "\t" . trim($address) . "\n";
							}
							$server_acl_array[$server_acl_result[$j]->acl_name] = array(rtrim(ltrim($server_acl_addresses, "\t"), ";\n"), $server_acl_result[$j]->acl_comment);
						}
					}
				} else $server_acl_array = array();

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
			}
			

			/** Build Views */
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_id', 'view_', "AND view_status='active' AND server_serial_no IN (0, $server_serial_no)");
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
					basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_id', 'cfg_', "AND cfg_status='active' AND cfg_type='global' AND server_serial_no=0 AND cfg_view='" . $view_result[$i]->view_id . "'");
					if ($fmdb->num_rows) {
						$config_result = $fmdb->last_result;
						$view_config_count = $fmdb->num_rows;
						for ($j=0; $j < $view_config_count; $j++) {
							$view_config[$config_result[$j]->cfg_name] = array($config_result[$j]->cfg_data, $config_result[$j]->cfg_comment);
						}
					} else $view_config = array();

					/** Override with server-specific configs */
					basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_id', 'cfg_', "AND cfg_status='active' AND cfg_type='global' AND server_serial_no=$server_serial_no AND cfg_view='" . $view_result[$i]->view_id . "'");
					if ($fmdb->num_rows) {
						$server_config_result = $fmdb->last_result;
						$view_config_count = $fmdb->num_rows;
						for ($j=0; $j < $view_config_count; $j++) {
							$server_view_config[$server_config_result[$j]->cfg_name] = array($server_config_result[$j]->cfg_data, $config_result[$j]->cfg_comment);
						}
					} else $server_view_config = array();

					/** Merge arrays */
					$config_array = array_merge($view_config, $server_view_config);

					foreach ($config_array as $cfg_name => $cfg_data) {
						list($cfg_info, $cfg_comment) = $cfg_data;
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
						$config .= str_replace('$ROOT', $server_root_dir, trim(rtrim(trim($cfg_info), ';')));
						if ($def_multiple_values == 'yes') $config .= '; }';
						$config .= ";\n";
						
						if ($cfg_comment) $config .= "\n";
						unset($cfg_info);
					}

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
							basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_id', 'server_', 'AND server_serial_no!=' . $server_serial_no . ' AND server_key=' . $key_result[$k]->key_id . ' AND server_status="active"');
							if ($fmdb->num_rows) {
								$server_result = $fmdb->last_result;
								$server_count = $fmdb->num_rows;
								$servers = null;
								for ($j=0; $j < $server_count; $j++) {
									$config .= $this->formatServerKeys($server_result[$j]->server_name, $key_name, true);
								}
							}
						}
						$data->files[$server_zones_dir . '/views.conf.' . $view_result[$i]->view_name . '.keys'] = $key_config;
					}
					
					/** Generate zone file */
					$tmp_files = $this->buildZoneDefinitions($server_zones_dir, $server_serial_no, $view_result[$i]->view_id, $view_result[$i]->view_name);
					
					/** Include zones for view */
					if (is_array($tmp_files)) {
						/** Include view keys if present */
						if (@array_key_exists($server_zones_dir . '/views.conf.' . $view_result[$i]->view_name . '.keys', $data->files)) {
							$config .= "\tinclude \"" . $server_zones_dir . "/views.conf." . $view_result[$i]->view_name . ".keys\";\n";
						}
						$config .= "\tinclude \"" . $server_zones_dir . '/zones.conf.' . $view_result[$i]->view_name . "\";\n";
						$files = array_merge($files, $tmp_files);
					}
					
					$config .= "};\n\n";
					
					$key_config = $view_config = $server_view_config = null;
				}
			} else {
				/** Generate zones.all.conf */
				$files = $this->buildZoneDefinitions($server_zones_dir, $server_serial_no);
				
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
			
			return get_object_vars($data);
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
		
		$zones = null;
		
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
				$data->files = $this->buildZoneDefinitions($server_zones_dir, $server_serial_no);
			} else {
				if ($SERIALNO != -1) {
					$server_id = getServerID($server_serial_no, $_SESSION['module']);
					/** Build zone files for $domain_id */
					$query = "select * from `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` where `domain_status`='active' and (`domain_id`=" . sanitize($domain_id) . " or `domain_clone_domain_id`=" . sanitize($domain_id) . ") and (`domain_name_servers`=0 or `domain_name_servers`='$server_id' or `domain_name_servers` like '$server_id;%' or `domain_name_servers` like '%;$server_id;%') order by `domain_name`";
				} else {
					/** Build zone files for $domain_id */
					$query = "select * from `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` where `domain_status`='active' and (`domain_id`=" . sanitize($domain_id) . " or `domain_clone_domain_id`=" . sanitize($domain_id) . ") order by `domain_name`";
				}
				$result = $fmdb->query($query);
				if ($fmdb->num_rows) {
					$count = $fmdb->num_rows;
					$zone_result = $fmdb->last_result;
					for ($i=0; $i < $count; $i++) {
						/** Is this a clone id? */
						if ($zone_result[$i]->domain_clone_domain_id) $zone_result[$i] = $this->mergeZoneDetails($zone_result[$i], $zone_result, $count);
						
						if (getSOACount($zone_result[$i]->domain_id)) {
							$domain_name = $this->getDomainName($zone_result[$i]->domain_mapping, trimFullStop($zone_result[$i]->domain_name));
							$file_ext = ($zone_result[$i]->domain_mapping == 'forward') ? 'hosts' : 'rev';

							/** Are there multiple zones with the same name? */
							basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $zone_result[$i]->domain_name, 'domain_', 'domain_name', 'AND domain_clone_domain_id=0 AND domain_id!=' . $zone_result[$i]->domain_id);
							if ($fmdb->num_rows) $file_ext = $zone_result[$i]->domain_id . ".$file_ext";
							
							/** Build zone file */
							$data->files[$server_zones_dir . '/' . $zone_result[$i]->domain_type . '/db.' . $domain_name . "$file_ext"] = $this->buildZoneFile($zone_result[$i]);
						}
					}
					if (isset($data->files)) {
						/** set the server_update_config flag */
						if (!$dryrun) setBuildUpdateConfigFlag($server_serial_no, 'yes', 'update');
						
						return get_object_vars($data);
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
	function buildZoneDefinitions($server_zones_dir, $server_serial_no, $view_id = 0, $view_name = null) {
		global $fmdb, $__FM_CONFIG;
		
		include(ABSPATH . 'fm-includes/version.php');
		
		/** Get datetime formatting */
		$date_format = getOption('date_format', $_SESSION['user']['account_id']);
		$time_format = getOption('time_format', $_SESSION['user']['account_id']);
		
		$files = null;
		$zones = '// This file was built using ' . $_SESSION['module'] . ' ' . $__FM_CONFIG[$_SESSION['module']]['version'] . ' on ' . date($date_format . ' ' . $time_format . ' e') . "\n\n";
		$server_id = getServerID($server_serial_no, $_SESSION['module']);
		
		/** Build zones */
		$view_sql = "and (`domain_view`=0 or `domain_view`=$view_id or `domain_view` LIKE '$view_id;%' or `domain_view` LIKE '%;$view_id' or `domain_view` LIKE '%;$view_id;%')";
		$query = "select * from `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` where `domain_status`='active' and (`domain_name_servers`=0 or `domain_name_servers`='$server_id' or `domain_name_servers` like '$server_id;%' or `domain_name_servers` like '%;$server_id%' or `domain_name_servers` like '%;$server_id;%') $view_sql order by `domain_clone_domain_id`,`domain_name`";
		$result = $fmdb->query($query);
		if ($fmdb->num_rows) {
			$count = $fmdb->num_rows;
			$zone_result = $fmdb->last_result;
			for ($i=0; $i < $count; $i++) {
				/** Is this a clone id? */
				if ($zone_result[$i]->domain_clone_domain_id) $zone_result[$i] = $this->mergeZoneDetails($zone_result[$i], $zone_result, $count);
				if ($zone_result[$i] == false) continue;
				
				/** Valid SOA and NS records must exist */
				if ((getSOACount($zone_result[$i]->domain_id) && getNSCount($zone_result[$i]->domain_id)) ||
					$zone_result[$i]->domain_type != 'master') {

					$domain_name_file = $this->getDomainName($zone_result[$i]->domain_mapping, trimFullStop($zone_result[$i]->domain_name));
					$domain_name = isset($zone_result[$i]->domain_name_file) ? $this->getDomainName($zone_result[$i]->domain_mapping, trimFullStop($zone_result[$i]->domain_name_file)) : $domain_name_file;
					$zones .= 'zone "' . rtrim($domain_name, '.') . "\" {\n";
					$zones .= "\ttype " . $zone_result[$i]->domain_type . ";\n";
					$file_ext = ($zone_result[$i]->domain_mapping == 'forward') ? 'hosts' : 'rev';
					
					/** Are there multiple zones with the same name? */
					basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $zone_result[$i]->domain_name, 'domain_', 'domain_name', 'AND domain_clone_domain_id=0 AND domain_id!=' . $zone_result[$i]->domain_id);
					if ($fmdb->num_rows) $file_ext = $zone_result[$i]->domain_id . ".$file_ext";
					
					switch($zone_result[$i]->domain_type) {
						case 'master':
							$zones .= "\tfile \"$server_zones_dir/master/db." . $domain_name_file . "$file_ext\";\n";
							$zones .= $zone_result[$i]->domain_check_names ? "\tcheck-names " . $zone_result[$i]->domain_check_names . ";\n" : null;
							$zones .= $zone_result[$i]->domain_notify_slaves ? "\tnotify " . $zone_result[$i]->domain_notify_slaves . ";\n" : null;
							/** Build zone file */
							$files[$server_zones_dir . '/master/db.' . $domain_name_file . "$file_ext"] = $this->buildZoneFile($zone_result[$i], $server_serial_no);
							break;
						case 'slave':
							$zones .= "\tmasters { " . $zone_result[$i]->domain_master_servers . "};\n";
							$zones .= "\tfile \"$server_zones_dir/slave/db." . $domain_name . "$file_ext\";\n";
							$zones .= $zone_result[$i]->domain_notify_slaves ? "\tnotify " . $zone_result[$i]->domain_notify_slaves . ";\n" : null;
							$zones .= $zone_result[$i]->domain_multi_masters ? "\tmulti-master " . $zone_result[$i]->domain_multi_masters . ";\n" : null;
							break;
						case 'stub':
							$zones .= "\tmasters { " . $zone_result[$i]->domain_master_servers . "};\n";
							$zones .= "\tfile \"$server_zones_dir/stub/db." . $domain_name . "$file_ext\";\n";
							break;
						case 'forward':
							$zones .= "\tforwarders { " . $zone_result[$i]->domain_forward_servers . "};\n";
					}
					$zones .= "};\n";
	
					/** Add domain_id to built_domain_ids for tracking */
					$GLOBALS['built_domain_ids'][] = $zone_result[$i]->domain_id;
				}
			}
			
			if ($view_name) {
				$files[$server_zones_dir . '/zones.conf.' . $view_name] = $zones;
			} else {
				$files[$server_zones_dir . '/zones.conf.all'] = $zones;
			}
		}
		return $files;
	}
	
	
	/**
	 * Builds the zone file for $domain_id
	 *
	 * @since 1.0
	 * @package fmDNS
	 */
	function buildZoneFile($domain, $server_serial_no) {
		global $fmdb, $__FM_CONFIG;
		
		include(ABSPATH . 'fm-includes/version.php');
		
		/** Get datetime formatting */
		$date_format = getOption('date_format', $_SESSION['user']['account_id']);
		$time_format = getOption('time_format', $_SESSION['user']['account_id']);
		
		$zone_file = '; This file was built using ' . $_SESSION['module'] . ' ' . $__FM_CONFIG[$_SESSION['module']]['version'] . ' on ' . date($date_format . ' ' . $time_format . ' e') . "\n\n";
		$a_records = null;
		
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
					$query = "select * from `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` where `domain_status`='active' and `domain_id`=" . $track_reloads[$i]->domain_id;
					$result = $fmdb->query($query);
					if ($fmdb->num_rows) {
						$zone_result = $fmdb->last_result;
						if (getSOACount($zone_result[0]->domain_id)) {
							$domain_name = $this->getDomainName($zone_result[0]->domain_mapping, trimFullStop($zone_result[0]->domain_name));
							$file_ext = ($zone_result[0]->domain_mapping == 'forward') ? 'hosts' : 'rev';

							/** Build zone file */
							$data->files[$server_zones_dir . '/' . $zone_result[$i]->domain_type . '/db.' . $domain_name . "$file_ext"] = $this->buildZoneFile($zone_result[0]);
							
							/** Track reloads */
							$data->reload_domain_ids[] = $zone_result[0]->domain_id;
						}
					}
				}
				if (is_array($data->files)) return get_object_vars($data);
			} else {
				/** process server config build */
				$config = $this->buildServerConfig($post_data);
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
		
		$query = "select * from fm_{$__FM_CONFIG['fmDNS']['prefix']}track_reloads where server_serial_no='" . $server_serial_no . "'";
		$track_reloads_result = $fmdb->query($query);

		if ($fmdb->num_rows) {
			$track_reloads = $fmdb->last_result;
			return $track_reloads;
		} else return false;
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
		
		$query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}soa` WHERE `account_id`='{$_SESSION['user']['account_id']}' AND `domain_id`='" . $domain->domain_id . "'";
		$fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$soa_result = $fmdb->last_result;
			extract(get_object_vars($soa_result[0]));
			
			$domain_name_trim = trimFullStop($domain->domain_name);
			
			$master_server = ($soa_append == 'yes') ? trimFullStop($soa_master_server) . '.' . $domain_name_trim . '.' : trimFullStop($soa_master_server) . '.';
			$admin_email = ($soa_append == 'yes') ? trimFullStop($soa_email_address) . '.' . $domain_name_trim . '.' : trimFullStop($soa_email_address) . '.';
			
			$domain_name = $this->getDomainName($domain->domain_mapping, $domain_name_trim);
			
			$serial = date("Ymd") . $soa_serial_no;
			
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
		global $fmdb, $__FM_CONFIG, $fm_dns_records;
		
		$zone_file = null;
		$domain_name_trim = trimFullStop($domain->domain_name);
		list($server_version) = explode('-', getNameFromID($server_serial_no, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_version'));
		
		/** Is this a cloned zone */
		if (isset($domain->parent_domain_id)) {
			$full_zone_clone = true;
			
			/** Are there any additional records? */
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $domain->parent_domain_id, 'record_', 'domain_id', "AND `record_status`='active'");
			if ($fmdb->num_rows) {
				$full_zone_clone = false;
			}
			
			/** Are there any skipped records? */
			if (!isset($fm_dns_records)) include(ABSPATH . 'fm-modules/fmDNS/classes/class_records.php');
			if ($skipped_records = $fm_dns_records->getSkippedRecordIDs($domain->parent_domain_id)) $full_zone_clone = false;
		}
		
		if (isset($domain->parent_domain_id)) {
			$valid_domain_ids = $full_zone_clone == false ? "IN ('{$domain->domain_id}', '{$domain->parent_domain_id}')" : "='{$domain->domain_id}' AND record_type='NS'";
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
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_name . "\t" . $record_result[$i]->record_ttl . "\t" . $record_result[$i]->record_class . "\t" . $record_result[$i]->record_type . "\t" . $record_result[$i]->record_value . $record_comment . "\n";
						break;
					case 'CERT':
						$record_array[$record_result[$i]->record_type]['Version'] = '9.7.0';
						$record_array[$record_result[$i]->record_type]['Description'] = 'Certificates';
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_name . "\t" . $record_result[$i]->record_ttl . "\t" . $record_result[$i]->record_class . "\t" . $record_result[$i]->record_type . "\t" . $record_result[$i]->record_cert_type . ' ' . $record_result[$i]->record_key_tag . ' ' . $record_result[$i]->record_algorithm . "\t(\n\t\t\t" . str_replace("\n", "\n\t\t\t", $record_result[$i]->record_value) . ' )' . $record_comment . "\n";
						break;
					case 'CNAME':
					case 'DNAME':
						$record_array[$record_result[$i]->record_type]['Description'] = 'Aliases';
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_name . "\t" . $record_result[$i]->record_ttl . "\t" . $record_result[$i]->record_class . "\t" . $record_result[$i]->record_type . "\t" . $record_result[$i]->record_value . $record_comment . "\n";
						break;
					case 'DHCID':
						$record_array[$record_result[$i]->record_type]['Version'] = '9.5.0';
						$record_array[$record_result[$i]->record_type]['Description'] = 'DHCP ID records';
//						$record_array[$record_result[$i]->record_type]['Data'][] = $record_name . "\t" . $record_result[$i]->record_ttl . "\t" . $record_result[$i]->record_class . "\t" . $record_result[$i]->record_type . "\t" . $record_result[$i]->record_flags . ' 3 ' . $record_result[$i]->record_algorithm . "\t(\n\t\t\t" . str_replace("\n", "\n\t\t\t", $record_result[$i]->record_value) . ' )' . $record_comment . "\n";
						break;
					case 'DLV':
					case 'DS':
						$record_array[$record_result[$i]->record_type]['Description'] = 'DNSSEC Lookaside Validation';
//						$record_array[$record_result[$i]->record_type]['Data'][] = $record_name . "\t" . $record_result[$i]->record_ttl . "\t" . $record_result[$i]->record_class . "\t" . $record_result[$i]->record_type . "\t" . $record_result[$i]->record_cert_type . ' ' . $record_result[$i]->record_key_tag . ' ' . $record_result[$i]->record_algorithm . "\t(\n\t\t\t" . str_replace("\n", "\n\t\t\t", $record_result[$i]->record_value) . ' )' . $record_comment . "\n";
						break;
					case 'DNSKEY':
					case 'KEY':
						$record_array[$record_result[$i]->record_type]['Version'] = '9.5.0';
						$record_array[$record_result[$i]->record_type]['Description'] = 'Key records';
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_name . "\t" . $record_result[$i]->record_ttl . "\t" . $record_result[$i]->record_class . "\t" . $record_result[$i]->record_type . "\t" . $record_result[$i]->record_flags . ' 3 ' . $record_result[$i]->record_algorithm . "\t(\n\t\t\t" . str_replace("\n", "\n\t\t\t", $record_result[$i]->record_value) . ' )' . $record_comment . "\n";
						break;
					case 'HINFO':
						$record_array[$record_result[$i]->record_type]['Description'] = 'Hardware information records';
						$hardware = (strpos($record_result[$i]->record_value, ' ') === false) ? $record_result[$i]->record_value : '"' . $record_result[$i]->record_value . '"';
						$os = (strpos($record_result[$i]->record_os, ' ') === false) ? $record_result[$i]->record_os : '"' . $record_result[$i]->record_os . '"';
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_name . "\t" . $record_result[$i]->record_ttl . "\t" . $record_result[$i]->record_class . "\t" . $record_result[$i]->record_type . "\t" . $hardware . ' ' . $os . $record_comment . "\n";
						break;
					case 'KX':
						$record_array[$record_result[$i]->record_type]['Description'] = 'Key Exchange records';
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_name . "\t" . $record_result[$i]->record_ttl . "\t" . $record_result[$i]->record_class . "\t" . $record_result[$i]->record_type . "\t" . $record_result[$i]->record_priority . "\t" . $record_result[$i]->record_value . $record_comment . "\n";
						break;
					case 'MX':
						$record_array[2 . $record_result[$i]->record_type]['Description'] = 'Mail Exchange records';
						$record_array[2 . $record_result[$i]->record_type]['Data'][] = $record_name . "\t" . $record_result[$i]->record_ttl . "\t" . $record_result[$i]->record_class . "\t" . $record_result[$i]->record_type . "\t" . $record_result[$i]->record_priority . "\t" . $record_result[$i]->record_value . $record_comment . "\n";
						break;
					case 'TXT':
						$record_array[$record_result[$i]->record_type]['Description'] = 'TXT records';
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_name . "\t" . $record_result[$i]->record_ttl . "\t" . $record_result[$i]->record_class . "\t" . $record_result[$i]->record_type . "\t\"" . $record_result[$i]->record_value . "\"" . $record_comment . "\n";
						break;
					case 'SRV':
						$record_array[$record_result[$i]->record_type]['Description'] = 'Service records';
						$record_value = ($record_result[$i]->record_append == 'yes') ? $record_result[$i]->record_value . '.' . $domain_name_trim . '.' : $record_result[$i]->record_value;
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_name . "\t" . $record_result[$i]->record_ttl . "\t" . $record_result[$i]->record_class . "\t" . $record_result[$i]->record_type . "\t" . $record_result[$i]->record_priority . "\t" . $record_result[$i]->record_weight . "\t" . $record_result[$i]->record_port . "\t" . $record_value . $record_comment . "\n";
						break;
					case 'PTR':
						$record_array[$record_result[$i]->record_type]['Description'] = 'Addresses point to hosts';
						$record_name = ($record_result[$i]->record_append == 'yes' && $domain->domain_mapping == 'reverse') ? $record_result[$i]->record_name . '.' . $domain_name : $record_result[$i]->record_name;
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_name . "\t" . $record_result[$i]->record_ttl . "\t" . $record_result[$i]->record_class . "\t" . $record_result[$i]->record_type . "\t" . $record_result[$i]->record_value . $record_comment . "\n";
						break;
					case 'RP':
						$record_array[$record_result[$i]->record_type]['Description'] = 'Responsible Persons';
						$record_value = ($record_result[$i]->record_append == 'yes') ? $record_result[$i]->record_value . '.' . $domain_name_trim . '.' : $record_result[$i]->record_value;
						$record_text = ($record_result[$i]->record_append == 'yes') ? $record_result[$i]->record_text . '.' . $domain_name_trim . '.' : $record_result[$i]->record_text;
						if (!strlen($record_result[$i]->record_text)) $record_text = '.';
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_name . "\t" . $record_result[$i]->record_ttl . "\t" . $record_result[$i]->record_class . "\t" . $record_result[$i]->record_type . "\t" . $record_value . "\t" . $record_text . $record_comment . "\n";
						break;
					case 'NS':
						$record_array[1 . $record_result[$i]->record_type]['Description'] = 'Name servers';
						$record_value = ($record_result[$i]->record_append == 'yes') ? $record_result[$i]->record_value . '.' . $domain_name_trim . '.' : $record_result[$i]->record_value;
						$record_array[1 . $record_result[$i]->record_type]['Data'][] = $record_name . "\t" . $record_result[$i]->record_ttl . "\t" . $record_result[$i]->record_class . "\t" . $record_result[$i]->record_type . "\t" . $record_value . $record_comment . "\n";
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
			
//			if (isset($zone)) {
//				$msg = (!setBuildUpdateConfigFlag($server_serial_no, 'no', 'update')) ? "Could not update the backend database1.\n" : "Success.\n";
//				$msg = "Success.\n";
//			} else {
				$msg = (!setBuildUpdateConfigFlag($server_serial_no, 'no', 'build') || !setBuildUpdateConfigFlag($server_serial_no, 'no', 'update')) ? "Could not update the backend database.\n" : "Success.\n";
				$msg = "Success.\n";
//			}
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
	function mergeZoneDetails($zone, $all_zones, $count) {
		for ($i = 0; $i < $count; $i++) {
			if ($all_zones[$i]->domain_id == $zone->domain_clone_domain_id) {
				$all_zones[$i]->parent_domain_id = $zone->domain_id;
				$all_zones[$i]->domain_id = $zone->domain_clone_domain_id;
				$all_zones[$i]->domain_name = $zone->domain_name;
				$all_zones[$i]->domain_name_file = $zone->domain_name;
				
				return $all_zones[$i];
			}
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

}

if (!isset($fm_module_buildconf))
	$fm_module_buildconf = new fm_module_buildconf();

?>
