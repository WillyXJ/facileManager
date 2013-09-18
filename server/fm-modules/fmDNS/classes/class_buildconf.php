<?php

class fm_dns_buildconf {
	
	/**
	 * Generates the server config and updates the DNS server
	 *
	 * @since 1.0
	 * @package facileManager
	 */
	function buildServerConfig($post_data) {
		global $fmdb, $__FM_CONFIG;
		
		/** Get datetime formatting */
		$date_format = getOption('date_format', $_SESSION['user']['account_id']);
		$time_format = getOption('time_format', $_SESSION['user']['account_id']);
		
		$files = array();
		$server_serial_no = sanitize($post_data['SERIALNO']);
		extract($post_data);

		$GLOBALS['built_domain_ids'] = null;
		$data->server_build_all = true;
		
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
					$global_config[$config_result[$i]->cfg_name] = $config_result[$i]->cfg_data;
				}
			} else $global_config = array();

			/** Override with server-specific configs */
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_id', 'cfg_', 'AND cfg_type="global" AND cfg_view=0 AND server_serial_no=' . $server_serial_no . ' AND cfg_status="active"');
			if ($fmdb->num_rows) {
				$server_config_result = $fmdb->last_result;
				$global_config_count = $fmdb->num_rows;
				for ($j=0; $j < $global_config_count; $j++) {
					$server_config[$server_config_result[0]->cfg_name] = $server_config_result[0]->cfg_data;
				}
			} else $server_config = array();

			/** Merge arrays */
			$config_array = array_merge($global_config, $server_config);
			foreach ($config_array as $cfg_name => $cfg_data) {
				$config .= "\t" . $cfg_name . ' ' . str_replace('$ROOT', $server_root_dir, $cfg_data) . ";\n";
			}
			$config .= "};\n\n";
			

			/** Build logging config */
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_name` DESC,`cfg_data`,`cfg_id', 'cfg_', 'AND cfg_type="logging" AND cfg_isparent="yes" AND cfg_status="active" AND server_serial_no in (0, ' . $server_serial_no . ')');
			if ($fmdb->num_rows) {
				$logging_result = $fmdb->last_result;
				$count = $fmdb->num_rows;
				for ($i=0; $i < $count; $i++) {
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
					$key_config .= "key $key_name {\n";
					$key_config .= "\talgorithm " . $key_result[$i]->key_algorithm . ";\n";
					$key_config .= "\tsecret \"" . $key_result[$i]->key_secret . "\";\n";
					$key_config .= "};\n\n";
					
					/** Get associated servers */
					basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_id', 'server_', 'AND server_key=' . $key_result[$i]->key_id . ' AND server_status="active"');
					if ($fmdb->num_rows) {
						$server_result = $fmdb->last_result;
						$server_count = $fmdb->num_rows;
						for ($j=0; $j < $server_count; $j++) {
							$servers .= 'server ' . $server_result[$j]->server_name . " {\n";
							$servers .= "\tkey \"$key_name\";\n";
							$servers .= "};\n";
						}
					}
				}
			}

			if ($keys) {
				$keys = "keys {\n$keys};\n";
			}
			$data->files[dirname($server_config_file) . '/named.keys.conf'] = $key_config;
			
			$config .= $logging . $servers . "\ninclude \"" . dirname($server_config_file) . "/named.keys.conf\";\n\n";;
			

			/** Build ACLs */
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_id', 'acl_', 'AND acl_status="active" AND server_serial_no=0');
			if ($fmdb->num_rows) {
				$acl_result = $fmdb->last_result;
				for ($i=0; $i < $fmdb->num_rows; $i++) {
					if ($acl_result[$i]->acl_predefined != 'as defined:') {
						$global_acl_array[$acl_result[$i]->acl_name] = $acl_result[$i]->acl_predefined;
					} else {
						$addresses = explode(' ', $acl_result[$i]->acl_addresses);
						foreach($addresses as $address) {
							if(trim($address)) $global_acl_array[$acl_result[$i]->acl_name] .= "\t" . $address . "\n";
						}
					}
				}

				/** Override with server-specific ACLs */
				basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_id', 'acl_', 'AND acl_status="active" AND server_serial_no=' . $server_serial_no);
				if ($fmdb->num_rows) {
					$server_acl_result = $fmdb->last_result;
					$acl_config_count = $fmdb->num_rows;
					for ($j=0; $j < $acl_config_count; $j++) {
						if ($server_acl_result[$j]->acl_predefined != 'as defined:') {
							$server_acl_array[$server_acl_result[$j]->acl_name] = $server_acl_result[$j]->acl_predefined;
						} else {
							$addresses = explode(' ', $server_acl_result[$j]->acl_addresses);
							$server_acl_addresses = null;
							foreach($addresses as $address) {
								if(trim($address)) $server_acl_addresses .= "\t" . trim($address) . "\n";
							}
							$server_acl_array[$server_acl_result[$j]->acl_name] = rtrim(ltrim($server_acl_addresses, "\t"), ";\n");
						}
					}
				} else $server_acl_array = array();

				/** Merge arrays */
				$acl_array = array_merge($global_acl_array, $server_acl_array);

				/** Format ACL config */
				foreach ($acl_array as $acl_name => $acl_data) {
					$config .= 'acl "' . $acl_name . "\" {\n";
					$config .= "\t" . $acl_data . ";\n";
					$config .= "};\n\n";
				}
			}
			

			/** Build Views */
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_id', 'view_', "AND view_status='active' AND server_serial_no IN (0, $server_serial_no)");
			if ($fmdb->num_rows) {
				$view_result = $fmdb->last_result;
				$view_count = $fmdb->num_rows;
				for ($i=0; $i < $view_count; $i++) {
					$config .= 'view "' . $view_result[$i]->view_name . "\" {\n";

					/** Get cooresponding config records */
					basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_id', 'cfg_', "AND cfg_status='active' AND cfg_type='global' AND server_serial_no=0 AND cfg_view='" . $view_result[$i]->view_id . "'");
					if ($fmdb->num_rows) {
						$config_result = $fmdb->last_result;
						$view_config_count = $fmdb->num_rows;
						for ($j=0; $j < $view_config_count; $j++) {
							$view_config[$config_result[$j]->cfg_name] = $config_result[$j]->cfg_data;
						}
					} else $view_config = array();

					/** Override with server-specific configs */
					basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_id', 'cfg_', "AND cfg_status='active' AND cfg_type='global' AND server_serial_no=$server_serial_no AND cfg_view='" . $view_result[$i]->view_id . "'");
					if ($fmdb->num_rows) {
						$server_config_result = $fmdb->last_result;
						$view_config_count = $fmdb->num_rows;
						for ($j=0; $j < $view_config_count; $j++) {
							$server_view_config[$server_config_result[$j]->cfg_name] = $server_config_result[$j]->cfg_data;
						}
					} else $server_view_config = array();

					/** Merge arrays */
					$config_array = array_merge($view_config, $server_view_config);

					foreach ($config_array as $cfg_name => $cfg_data) {
						$config .= "\t" . $cfg_name . ' ' . str_replace('$ROOT', $server_root_dir, $cfg_data) . ";\n";
					}

					/** Get cooresponding keys */
					basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_id', 'key_', "AND key_status='active' AND key_view='" . $view_result[$i]->view_id . "'");
					if ($fmdb->num_rows) {
						$key_result = $fmdb->last_result;
						$key_config = '// This file was built using ' . $_SESSION['module'] . ' ' . $fm_version . ' on ' . date($date_format . ' ' . $time_format . ' e') . "\n\n";
						for($k=0; $k < $fmdb->num_rows; $k++) {
							$key_name = trimFullStop($key_result[$k]->key_name) . '.';
							$key_config .= "key \"" . $key_name . "\" {\n";
							$key_config .= "\talgorithm " . $key_result[$k]->key_algorithm . ";\n";
							$key_config .= "\tsecret \"" . $key_result[$k]->key_secret . "\";\n";
							$key_config .= "};\n\n";
					
							/** Get associated servers */
							basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_id', 'server_', 'AND server_key=' . $key_result[$k]->key_id . ' AND server_status="active"');
							if ($fmdb->num_rows) {
								$server_result = $fmdb->last_result;
								$server_count = $fmdb->num_rows;
								$servers = null;
								for ($j=0; $j < $server_count; $j++) {
									$config .= "\tserver " . $server_result[$j]->server_name . " {\n";
									$config .= "\t\tkey \"$key_name\";\n";
									$config .= "\t};\n";
								}
							}
						}
					}
					$data->files[$server_zones_dir . '/views.keys.conf'] = $key_config;
					
					/** Generate zone file */
					$tmp_files = $this->buildZoneDefinitions($server_zones_dir, $server_serial_no, $view_result[$i]->view_id, $view_result[$i]->view_name);
					
					/** Include zones for view */
					if (is_array($tmp_files)) {
						$config .= "\n\tinclude \"" . $server_zones_dir . "/views.keys.conf\";\n";
						$config .= "\tinclude \"" . $server_zones_dir . '/zones.' . $view_result[$i]->view_name . ".conf\";\n";
						$files = array_merge($files, $tmp_files);
					}
					
					$config .= "};\n\n";
					
					$view_config = $server_view_config = null;
				}
			} else {
				/** Generate zones.all.conf */
				$files = $this->buildZoneDefinitions($server_zones_dir, $server_serial_no);
				
				/** Include all zones in one file */
				if (is_array($files)) {
					$config .= "\ninclude \"" . $server_zones_dir . "/zones.all.conf\";\n";
				}
			}

			$data->files[$server_config_file] = $config;
			if (is_array($files)) {
				$data->files = array_merge($data->files, $files);
			}

			/** Set variable containing all loaded domain_ids */
			if (!$dryrun) {
				$data->built_domain_ids = rtrim($GLOBALS['built_domain_ids'], ',');
				$this->setBuiltDomainIDs($server_serial_no, $data->built_domain_ids);
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
	 * @package facileManager
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

							/** Build zone file */
							$data->files[$server_zones_dir . '/db.' . $domain_name . "$file_ext"] = $this->buildZoneFile($zone_result[$i]);
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
	 * @package facileManager
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
				/** Add domain_id to built_domain_ids for tracking */
				$GLOBALS['built_domain_ids'] .= $zone_result[$i]->domain_id . ',';

				/** Is this a clone id? */
				if ($zone_result[$i]->domain_clone_domain_id) $zone_result[$i] = $this->mergeZoneDetails($zone_result[$i], $zone_result, $count);
				if ($zone_result[$i] == false) break;
				
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
							$zones .= "\tfile \"$server_zones_dir/db." . $domain_name_file . "$file_ext\";\n";
							$zones .= $zone_result[$i]->domain_check_names ? "\tcheck-names " . $zone_result[$i]->domain_check_names . ";\n" : null;
							$zones .= $zone_result[$i]->domain_notify_slaves ? "\tnotify " . $zone_result[$i]->domain_notify_slaves . ";\n" : null;
							/** Build zone file */
							$files[$server_zones_dir . '/db.' . $domain_name_file . "$file_ext"] = $this->buildZoneFile($zone_result[$i]);
							break;
						case 'slave':
							$zones .= "\tmasters { " . $zone_result[$i]->domain_master_servers . "};\n";
							$zones .= "\tfile \"$server_zones_dir/slaves/db." . $domain_name . "$file_ext\";\n";
							$zones .= $zone_result[$i]->domain_notify_slaves ? "\tnotify " . $zone_result[$i]->domain_notify_slaves . ";\n" : null;
							$zones .= $zone_result[$i]->domain_multi_masters ? "\tmulti-master " . $zone_result[$i]->domain_multi_masters . ";\n" : null;
							break;
						case 'forward':
							$zones .= "\tforwarders { " . $zone_result[$i]->domain_forward_servers . "};\n";
					}
					$zones .= "};\n";
				}
			}
			
			if ($view_name) {
				$files[$server_zones_dir . '/zones.' . $view_name . '.conf'] = $zones;
			} else {
				$files[$server_zones_dir . '/zones.all.conf'] = $zones;
			}
		}
		return $files;
	}
	
	
	/**
	 * Builds the zone file for $domain_id
	 *
	 * @since 1.0
	 * @package facileManager
	 */
	function buildZoneFile($domain) {
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
		$zone_file .= $this->buildRecords($domain);
		
		return $zone_file;
	}
	
	
	/**
	 * Figures out what files to update on the DNS server
	 *
	 * @since 1.0
	 * @package facileManager
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
			
			/** process zone reloads if present */
			$track_reloads = $this->getReloadRequests($server_serial_no);
			if ($track_reloads) {
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
							$data->files[$server_zones_dir . '/db.' . $domain_name . "$file_ext"] = $this->buildZoneFile($zone_result[0]);
						}
					}
				}
				if (is_array($data->files)) return get_object_vars($data);
			} else {
				/** process server config build */
				$config = $this->buildServerConfig($post_data);
				$config['server_build_all'] = true;
				
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
	 * @package facileManager
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
	 * @package facileManager
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
	 * @package facileManager
	 */
	function buildRecords($domain) {
		global $fmdb, $__FM_CONFIG;
		
		$zone_file = null;
		$domain_name_trim = trimFullStop($domain->domain_name);
		
		if ($domain->domain_mapping == 'reverse') {
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $domain->domain_id, 'record_', 'domain_id', "AND `record_status`='active' ORDER BY record_type,INET_ATON(record_name),record_value");
		} else {
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $domain->domain_id, 'record_', 'domain_id', "AND `record_status`='active' ORDER BY record_type,INET_ATON(record_value),record_name");
		}
		if ($fmdb->num_rows) {
			$ns_records = $mx_records = $txt_records = $a_records = $cname_records = $srv_records = $ptr_records = null;

			$count = $fmdb->num_rows;
			$record_result = $fmdb->last_result;
			for ($i=0; $i < $count; $i++) {
				$domain_name = $this->getDomainName($domain->domain_mapping, $domain_name_trim);
				$record_comment = $record_result[$i]->record_comment ?  ' ; ' . $record_result[$i]->record_comment : null;
				switch($record_result[$i]->record_type) {
					case 'A':
					case 'AAAA':
						$record_name = ($record_result[$i]->record_append == 'yes') ? $record_result[$i]->record_name . '.' . $domain_name_trim . '.' : $record_result[$i]->record_name;
						if ($record_result[$i]->record_name[0] == '@') {
							$record_name = $domain_name;
						}
						$a_records .= $record_name . "\t" . $record_result[$i]->record_ttl . "\t" . $record_result[$i]->record_class . "\t" . $record_result[$i]->record_type . "\t" . $record_result[$i]->record_value . $record_comment . "\n";
						break;
					case 'CNAME':
						$record_name = ($record_result[$i]->record_append == 'yes') ? $record_result[$i]->record_name . '.' . $domain_name_trim . '.' : $record_result[$i]->record_name;
						$cname_records .= $record_name . "\t" . $record_result[$i]->record_ttl . "\t" . $record_result[$i]->record_class . "\t" . $record_result[$i]->record_type . "\t" . $record_result[$i]->record_value . $record_comment . "\n";
						break;
					case 'MX':
						$record_name = ($record_result[$i]->record_append == 'yes') ? $record_result[$i]->record_name . '.' . $domain_name_trim . '.' : $record_result[$i]->record_name;
						if ($record_result[$i]->record_name[0] == '@') {
							$record_name = $domain_name;
						}
						$mx_records .= $record_name . "\t" . $record_result[$i]->record_ttl . "\t" . $record_result[$i]->record_class . "\t" . $record_result[$i]->record_type . "\t" . $record_result[$i]->record_priority . "\t" . $record_result[$i]->record_value . $record_comment . "\n";
						break;
					case 'TXT':
						$record_name = ($record_result[$i]->record_append == 'yes') ? $record_result[$i]->record_name . '.' . $domain_name_trim . '.' : $record_result[$i]->record_name;
						if ($record_result[$i]->record_name[0] == '@') {
							$record_name = $domain_name;
						}
						$txt_records .= $record_name . "\t" . $record_result[$i]->record_ttl . "\t" . $record_result[$i]->record_class . "\t" . $record_result[$i]->record_type . "\t\"" . $record_result[$i]->record_value . $record_comment . "\"\n";
						break;
					case 'SRV':
						$record_name = ($record_result[$i]->record_append == 'yes') ? $record_result[$i]->record_name . '.' . $domain_name_trim . '.' : $record_result[$i]->record_name;
						$record_value = ($record_result[$i]->record_append == 'yes') ? $record_result[$i]->record_value . '.' . $domain_name_trim . '.' : $record_result[$i]->record_value;
						if ($record_result[$i]->record_name[0] == '@') {
							$record_name = $domain_name;
						}
						$srv_records .= $record_name . "\t" . $record_result[$i]->record_ttl . "\t" . $record_result[$i]->record_class . "\t" . $record_result[$i]->record_type . "\t" . $record_result[$i]->record_priority . "\t" . $record_result[$i]->record_weight . "\t" . $record_result[$i]->record_port . "\t" . $record_value . $record_comment . "\n";
						break;
					case 'PTR':
						$record_name = ($record_result[$i]->record_append == 'yes') ? $record_result[$i]->record_name . '.' . $domain_name : $record_result[$i]->record_name;
						$ptr_records .= $record_name . "\t" . $record_result[$i]->record_ttl . "\t" . $record_result[$i]->record_class . "\t" . $record_result[$i]->record_type . "\t" . $record_result[$i]->record_value . $record_comment . "\n";
						break;
					case 'NS':
						if ($record_result[$i]->record_name[0] == '@') {
							$record_name = $domain_name;
						}
						$record_value = ($record_result[$i]->record_append == 'yes') ? $record_result[$i]->record_value . '.' . $domain_name_trim . '.' : $record_result[$i]->record_value;
						$ns_records .= $record_name . "\t" . $record_result[$i]->record_ttl . "\t" . $record_result[$i]->record_class . "\t" . $record_result[$i]->record_type . "\t" . $record_value . $record_comment . "\n";
						break;
				}
			}
			
			/** Record labeling */
			if ($ns_records) $ns_records = "; Name servers\n$ns_records";
			if ($mx_records) $mx_records = "\n; Mail Exchange records\n$mx_records";
			if ($txt_records) $txt_records = "\n; TXT records\n$txt_records";
			if ($a_records) $a_records = "\n; Host addresses\n$a_records";
			if ($cname_records) $cname_records = "\n; Aliases\n$cname_records";
			if ($srv_records) $srv_records = "\n; Service records\n$srv_records";
			if ($ptr_records) $ptr_records = "\n; Addresses point to hosts\n$ptr_records";
			
			/** Zone file output */
			$zone_file .= $ns_records;
			$zone_file .= ($domain->domain_mapping == 'reverse') ? $ptr_records . "\n" : $mx_records . $txt_records . $a_records . $cname_records . $srv_records . "\n";
		}
		
		return $zone_file;
	}
	
	
	/**
	 * Returns the $domain_name based on $map
	 *
	 * @since 1.0
	 * @package facileManager
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
	 * @package facileManager
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
				$msg = (!setBuildUpdateConfigFlag($server_serial_no, 'no', 'build') && !setBuildUpdateConfigFlag($server_serial_no, 'no', 'update')) ? "Could not update the backend database.\n" : "Success.\n";
				$msg = "Success.\n";
//			}
			$this->setBuiltDomainIDs($server_serial_no, $built_domain_ids);
		} else $msg = "DNS server is not found.\n";
		
		if ($compress) echo gzcompress(serialize($msg));
		else echo serialize($msg);
	}
	
	
	/**
	 * Sets cloned details to that of the parent domain
	 *
	 * @since 1.0
	 * @package facileManager
	 */
	function mergeZoneDetails($zone, $all_zones, $count) {
		for ($i = 0; $i < $count; $i++) {
			if ($all_zones[$i]->domain_id == $zone->domain_clone_domain_id) {
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
	 * @package facileManager
	 */
	function updateServerVersion() {
		global $fmdb, $__FM_CONFIG;
		
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` SET `server_version`='" . $_POST['server_version'] . "' WHERE `server_serial_no`='" . $_POST['SERIALNO'] . "' AND `account_id`=
			(SELECT account_id FROM `fm_accounts` WHERE `account_key`='" . $_POST['AUTHKEY'] . "')";
		$fmdb->query($query);
	}
	
	
	/**
	 * Validate the daemon version number of the client
	 *
	 * @since 1.0
	 * @package facileManager
	 */
	function validateDaemonVersion($data) {
		global $__FM_CONFIG;
		extract($data);
		
		if ($server_type == 'bind9') {
			$required_version = $__FM_CONFIG['fmDNS']['required_dns_version'];
		}
		
		if (substr($server_version, 0, strlen($required_version)) < $required_version) {
			return false;
		}
		
		return true;
	}


	/**
	 * Update fm_{$__FM_CONFIG['fmDNS']['prefix']}track_builds
	 *
	 * @since 1.0
	 * @package facileManager
	 */
	function setBuiltDomainIDs($server_serial_no, $built_domain_ids) {
		global $fmdb, $__FM_CONFIG;

		if (!empty($built_domain_ids)) {
			/** Delete old records first */
			basicDelete('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'track_builds', $server_serial_no, 'server_serial_no', false);

			/** Add new records */
			$sql = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}track_builds` VALUES ";
			$domain_ids = explode(',', $built_domain_ids);
			foreach ($domain_ids as $id) {
				$sql .= '(' . $id . ',' . $server_serial_no . '),';
			}
			$sql = rtrim($sql, ',');
			$fmdb->query($sql);
		}
	}

}

if (!isset($fm_dns_buildconf))
	$fm_dns_buildconf = new fm_dns_buildconf();

?>
