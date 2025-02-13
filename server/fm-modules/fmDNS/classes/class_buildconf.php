<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) The facileManager Team                                    |
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

require_once(ABSPATH . 'fm-modules/shared/classes/class_buildconf.php');

class fm_module_buildconf extends fm_shared_module_buildconf {

	public $url_config_file = null;

	public $server_info = null;
	
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
		include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_masters.php');
		
		setTimezone();
		
		$files = array();
		$server_serial_no = sanitize($post_data['SERIALNO']);
		$message = '';
		extract($post_data);
		if (!isset($fm_module_servers)) include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_servers.php');
		$server_group_ids = $fm_module_servers->getServerGroupIDs(getNameFromID($server_serial_no, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_id'));

		$GLOBALS['built_domain_ids'] = array();
		
		list($server_version) = explode('-', getNameFromID($server_serial_no, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_version'));
		if (!$server_version) {
			$server_version = '10.0';
		}

		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', $server_serial_no, 'server_', 'server_serial_no', 'AND server_type!="remote"');
		if ($fmdb->num_rows) {
			$server_result = $fmdb->last_result;
			$data = $server_result[0];
			extract(get_object_vars($data), EXTR_SKIP);
			
			/** Disabled DNS server */
			if ($GLOBALS['basename'] != 'preview.php') {
				if ($server_status != 'active') {
					$error = sprintf(_('Server is %s.'), $server_status) . "\n";
					if (isset($post_data['preview'])) {
						return $error;
					}
					if ($compress) echo gzcompress(serialize($error));
					else echo serialize($error);

					exit;
				}
			}

			/** Missing configuration file */
			if (empty(trim($server_config_file))) {
				$error = _('This server does not have a configuration file defined.') . "\n";
				if (isset($post_data['preview'])) {
					return $error;
				}
				if ($compress) echo gzcompress(serialize($error));
				else echo serialize($error);
				
				exit;
			}

			/** rndc key option */
			if ($server_key_with_rndc == 'default') {
				$data->server_key_with_rndc = getOption('use_named_keys_with_rndc', $_SESSION['user']['account_id'], $_SESSION['module']);
			}
			
			$this->server_info = $data;

			include(ABSPATH . 'fm-includes/version.php');
			$config = $zones = $key_config = '// This file was built using ' . $_SESSION['module'] . ' ' . $__FM_CONFIG[$_SESSION['module']]['version'] . ' on ' . date($date_format . ' ' . $time_format . ' e') . "\n\n";

			if ($server_url_server_type && $server_url_config_file) {
				$data->files[$server_url_config_file] = $this->url_config_file = str_replace('//', '#', $config);
			}
			
			$query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` WHERE `cfg_name`='directory'";
			$config_dir_result = $fmdb->get_results($query);
			$logging = $keys = $servers = '';
			

			/** Build keys config */
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_id', 'key_', 'AND key_type="tsig" AND key_view=0 AND key_status="active"');
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
					basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_id', 'cfg_', 'AND server_id!="' . $server_id . '" AND cfg_name="keys" AND (cfg_data="key_' . $key_result[$i]->key_id . '" OR cfg_data LIKE "key_' . $key_result[$i]->key_id . ',%" OR cfg_data LIKE "%,key_' . $key_result[$i]->key_id . ',%" OR cfg_data LIKE "%,key_' . $key_result[$i]->key_id . '")');
					if ($fmdb->num_rows) {
						$server_result = $fmdb->last_result;
						foreach ($server_result as $server_info) {
							$servers .= $this->formatServerKeys($server_info->server_id, $key_name);
						}
						unset($server_result);
					}
				}
			}

			if ($keys) {
				$data->files[dirname($server_config_file) . '/named.conf.keys'] = array('contents' => $key_config, 'mode' => 0400);
			
				$config .= "include \"" . dirname($server_config_file) . "/named.conf.keys\";\n\n";
			}
			$config .= $servers;
			unset($key_result, $key_config_count, $key_config, $servers, $keys);
			
			
			/** Build extra files */
			$group_ids_sql = ($server_group_ids) ? ', "g_' . implode('", "g_', $server_group_ids) . '"' : null;
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'files', 'file_name', 'file_', 'AND server_serial_no IN ("0", "' . $server_serial_no . '"' . $group_ids_sql . ') AND file_status="active"');
			if ($fmdb->num_rows) {
				foreach ($fmdb->last_result as $item_arr) {
					$data->files[str_replace(array('$ROOT', '$ZONES'), array($server_root_dir, $server_zones_dir), $item_arr->file_location) . '/include.d/' . $item_arr->file_name] = array('contents' => $item_arr->file_contents, 'mode' => 0400);
				}
			}
			
			
			/** Build Servers */
			basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_id', 'cfg_', 'AND cfg_name="keys" AND cfg_data=""');
			if ($fmdb->num_rows) {
				$server_result = $fmdb->last_result;
				foreach ($server_result as $server_info) {
					$config .= (getNameFromID($server_info->server_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_id', 'server_type') == 'remote') ? $this->formatServerKeys($server_info->server_id) : null;
				}
				$config .= "\n";
				unset($server_result);
			}
			
			
			/** Build ACLs */
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_id', 'acl_', 'AND acl_parent_id=0 AND acl_status="active" AND server_serial_no="0"');
			if ($fmdb->num_rows) {
				$acl_result = $fmdb->last_result;
				$count = $fmdb->num_rows;
				for ($i=0; $i < $count; $i++) {
					$global_acl_array[$acl_result[$i]->acl_name] = null;
					basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_id', 'acl_', 'AND acl_parent_id=' . $acl_result[$i]->acl_id . ' AND acl_status="active" AND server_serial_no="0"');
					if ($fmdb->num_rows) $acl_child_result = $fmdb->last_result;
					for ($j=0; $j < $fmdb->num_rows; $j++) {
						foreach(explode(',', $acl_child_result[$j]->acl_addresses) as $address) {
							if(trim($address)) $global_acl_array[$acl_result[$i]->acl_name][] = trim($address);
						}
					}
					$global_acl_array[$acl_result[$i]->acl_name] = array(implode(',', (array) $global_acl_array[$acl_result[$i]->acl_name]), $acl_result[$i]->acl_comment);
					unset($acl_child_result);
				}
			} else $global_acl_array = array();

			$server_acl_array = array();
			/** Override with group-specific configs */
			if (is_array($server_group_ids)) {
				basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_id', 'acl_', 'AND acl_parent_id=0 AND acl_status="active" AND server_serial_no IN ("g_' . implode('","g_', $server_group_ids) . '")');
				if ($fmdb->num_rows) {
					$server_acl_result = $fmdb->last_result;
					$acl_config_count = $fmdb->num_rows;
					for ($i=0; $i < $acl_config_count; $i++) {
						$server_acl_addresses = array();
						basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_id', 'acl_', 'AND acl_parent_id=' . $server_acl_result[$i]->acl_id . ' AND acl_status="active" AND server_serial_no IN ("g_' . implode('","g_', $server_group_ids) . '")');
						if ($fmdb->num_rows) $acl_child_result = $fmdb->last_result;
						for ($j=0; $j < $fmdb->num_rows; $j++) {
							foreach(explode(',', $acl_child_result[$j]->acl_addresses) as $address) {
								if(trim($address)) $server_acl_addresses[] = trim($address);
							}
						}
						$server_acl_array[$server_acl_result[$i]->acl_name] = array(implode(',', (array) $server_acl_addresses), $server_acl_result[$i]->acl_comment);
						unset($acl_child_result, $server_acl_addresses);
					}
				}
			}

			/** Override with server-specific ACLs */
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_id', 'acl_', 'AND acl_parent_id=0 AND acl_status="active" AND server_serial_no="' . $server_serial_no . '"');
			if ($fmdb->num_rows) {
				$server_acl_result = $fmdb->last_result;
				$acl_config_count = $fmdb->num_rows;
				for ($i=0; $i < $acl_config_count; $i++) {
					$server_acl_addresses = array();
					basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_id', 'acl_', 'AND acl_parent_id=' . $server_acl_result[$i]->acl_id . ' AND acl_status="active"');
					if ($fmdb->num_rows) $acl_child_result = $fmdb->last_result;
					for ($j=0; $j < $fmdb->num_rows; $j++) {
						foreach(explode(',', $acl_child_result[$j]->acl_addresses) as $address) {
							if(trim($address)) $server_acl_addresses[] = trim($address);
						}
					}
					$server_acl_array[$server_acl_result[$i]->acl_name] = array(implode(',', (array) $server_acl_addresses), $server_acl_result[$i]->acl_comment);
					unset($acl_child_result, $server_acl_addresses);
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
				if ($acl_item) $config .= "\t" . str_replace('; ', ";\n\t", $fm_dns_acls->parseACL($acl_item)) . ';';
				$config .= "\n};\n\n";
			}
			unset($acl_result, $global_acl_array, $server_acl_array, $acl_array);


			/** Build HTTP endpoints */
			$config .= $this->getStandardBlocks('http', 0, $this->server_info, $server_group_ids);


			/** Build TLS connections */
			$config .= $this->getStandardBlocks('tls', 0, $this->server_info, $server_group_ids);


			/** Build DNSSEC policies */
			$config .= $this->getStandardBlocks('dnssec-policy', 0, $this->server_info, $server_group_ids);


			/** Build Masters */
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'masters', 'master_id', 'master_', 'AND master_parent_id=0 AND master_status="active" AND server_serial_no="0"');
			if ($fmdb->num_rows) {
				$master_result = $fmdb->last_result;
				$count = $fmdb->num_rows;
				for ($i=0; $i < $count; $i++) {
					$global_master_array[$master_result[$i]->master_name] = $global_master_ports = '';
					basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'masters', 'master_id', 'master_', 'AND master_parent_id=' . $master_result[$i]->master_id . ' AND master_status="active" AND server_serial_no="0"');
					$child_count = $fmdb->num_rows;
					if ($child_count) {
						$master_child_result = $fmdb->last_result;
					}
					for ($j=0; $j < $child_count; $j++) {
						foreach(explode(',', $master_child_result[$j]->master_addresses) as $address) {
							if ($master_child_result[$j]->master_comment) $global_master_array[$master_result[$i]->master_name] .= "\t// " . $master_child_result[$j]->master_comment . "\n";
							if (trim($address)) $global_master_array[$master_result[$i]->master_name] .= "\t" . $fm_dns_acls->parseACL($address);
							if ($master_child_result[$j]->master_port) $global_master_array[$master_result[$i]->master_name] .= ' port ' . $master_child_result[$j]->master_port;
							if ($master_child_result[$j]->master_key_id) $global_master_array[$master_result[$i]->master_name] .= ' key "' . $fm_dns_keys->parseKey('key_' . $master_child_result[$j]->master_key_id) . '"';
							if ($master_child_result[$j]->master_tls_id && version_compare($server_version, '9.18.0', '>=')) $global_master_array[$master_result[$i]->master_name] .= ' tls "' . getNameFromID($master_child_result[$j]->master_tls_id, "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config", 'cfg_', 'cfg_id', 'cfg_data', null, 'active') . '"';
							$global_master_array[$master_result[$i]->master_name] .= ";\n";
						}
					}
					if ($master_result[$i]->master_port) {
						$global_master_ports .= ' port ' . $master_result[$i]->master_port;
					}
					if ($master_result[$i]->master_dscp && version_compare($server_version, '9.10', '>=')) {
						$global_master_ports .= ' dscp ' . $master_result[$i]->master_dscp;
					}
					$global_master_array[$master_result[$i]->master_name] = array(rtrim(ltrim((string) $global_master_array[$master_result[$i]->master_name], "\t"), ";\n"), $global_master_ports, $master_result[$i]->master_comment);
					unset($master_child_result);
				}
			} else $global_master_array = array();

			$server_master_array = array();
			/** Override with group-specific configs */
			if (is_array($server_group_ids)) {
				basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'masters', 'master_id', 'master_', 'AND master_parent_id=0 AND master_status="active" AND server_serial_no IN ("g_' . implode('","g_', $server_group_ids) . '")');
				if ($fmdb->num_rows) {
					$server_master_result = $fmdb->last_result;
					$master_config_count = $fmdb->num_rows;
					for ($i=0; $i < $master_config_count; $i++) {
						$server_master_addresses = $server_master_ports = '';
						basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'masters', 'master_id', 'master_', 'AND master_parent_id=' . $server_master_result[$i]->master_id . ' AND master_status="active" AND server_serial_no IN ("g_' . implode('","g_', $server_group_ids) . '")');
						if ($fmdb->num_rows) $master_child_result = $fmdb->last_result;
						for ($j=0; $j < $fmdb->num_rows; $j++) {
							foreach(explode(',', $master_child_result[$j]->master_addresses) as $address) {
								if ($master_child_result[$i]->master_comment) $server_master_addresses .= "\t// " . $master_child_result[$i]->master_comment . "\n";
								if (trim($address)) $server_master_addresses .= "\t" . $fm_dns_acls->parseACL(trim($address));
								if ($master_child_result[$i]->master_port) $server_master_addresses .= ' port ' . $master_child_result[$i]->master_port;
								if ($master_child_result[$i]->master_key_id) $server_master_addresses .= ' key "' . $fm_dns_keys->parseKey('key_' . $master_child_result[$i]->master_key_id) . '"';
								if ($master_child_result[$i]->master_tls_id && version_compare($server_version, '9.18.0', '>=')) $server_master_addresses .= ' tls "' . getNameFromID($master_child_result[$i]->master_tls_id, "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config", 'cfg_', 'cfg_id', 'cfg_data', null, 'active') . '"';
								$server_master_addresses .= ";\n";
							}
						}
						if ($master_result[$i]->master_port) {
							$server_master_ports .= ' port ' . $master_result[$i]->master_port;
						}
						if ($master_result[$i]->master_dscp && version_compare($server_version, '9.10', '>=')) {
							$server_master_ports .= ' dscp ' . $master_result[$i]->master_dscp;
						}
						$server_master_array[$server_master_result[$i]->master_name] = array(rtrim(ltrim($server_master_addresses, "\t"), ";\n"), $server_master_ports, $server_master_result[$i]->master_comment);
						unset($master_child_result, $server_master_addresses);
					}
				}
			}

			/** Override with server-specific Masters */
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'masters', 'master_id', 'master_', 'AND master_parent_id=0 AND master_status="active" AND server_serial_no="' . $server_serial_no . '"');
			if ($fmdb->num_rows) {
				$server_master_result = $fmdb->last_result;
				$master_config_count = $fmdb->num_rows;
				for ($i=0; $i < $master_config_count; $i++) {
					$server_master_addresses = $server_master_ports = '';
					basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'masters', 'master_id', 'master_', 'AND master_parent_id=' . $server_master_result[$i]->master_id . ' AND master_status="active"');
					if ($fmdb->num_rows) $master_child_result = $fmdb->last_result;
					for ($j=0; $j < $fmdb->num_rows; $j++) {
						foreach(explode(',', $master_child_result[$j]->master_addresses) as $address) {
							if ($master_child_result[$i]->master_comment) $server_master_addresses .= "\t// " . $master_child_result[$i]->master_comment . "\n";
							if (trim($address)) $server_master_addresses .= "\t" . $fm_dns_acls->parseACL(trim($address));
							if ($master_child_result[$i]->master_port) $server_master_addresses .= ' port ' . $master_child_result[$i]->master_port;
							if ($master_child_result[$i]->master_key_id) $server_master_addresses .= ' key "' . $fm_dns_keys->parseKey('key_' . $master_child_result[$i]->master_key_id) . '"';
							if ($master_child_result[$i]->master_tls_id && version_compare($server_version, '9.18.0', '>=')) $server_master_addresses .= ' tls "' . getNameFromID($master_child_result[$i]->master_tls_id, "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config", 'cfg_', 'cfg_id', 'cfg_data', null, 'active') . '"';
							$server_master_addresses .= ";\n";
						}
					}
					if ($master_result[$i]->master_port) {
						$server_master_ports .= ' port ' . $master_result[$i]->master_port;
					}
					if ($master_result[$i]->master_dscp && version_compare($server_version, '9.10', '>=')) {
						$server_master_ports .= ' dscp ' . $master_result[$i]->master_dscp;
					}
					$server_master_array[$server_master_result[$i]->master_name] = array(rtrim(ltrim($server_master_addresses, "\t"), ";\n"), $server_master_ports, $server_master_result[$i]->master_comment);
					unset($master_child_result, $server_master_addresses);
				}
			}

			/** Merge arrays */
			$master_array = array_merge($global_master_array, $server_master_array);

			/** Format Primaries config */
			foreach ($master_array as $master_name => $master_data) {
				list($master_item, $master_ports, $master_comment) = $master_data;
				if ($master_comment) {
					$comment = wordwrap($master_comment, 50, "\n");
					$config .= '// ' . str_replace("\n", "\n// ", $comment) . "\n";
					unset($comment);
				}
				$config .= 'primaries "' . $master_name . '"' . $master_ports . " {\n";
				$config .= "\t" . $master_item;
				if ($master_item) $config .= ';';
				$config .= "\n};\n\n";
			}
			unset($master_result, $global_master_array, $server_master_array, $master_array);


			/** Build logging config */
			if (is_array($server_group_ids)) {
				basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_name` DESC,`cfg_data`,`cfg_id', 'cfg_', 'AND cfg_type="logging" AND cfg_isparent="yes" AND cfg_status="active" AND server_serial_no in ("0", "' . $server_serial_no . '", "g_' . implode('","g_', $server_group_ids) . '")');
			} else {
				basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_name` DESC,`cfg_data`,`cfg_id', 'cfg_', 'AND cfg_type="logging" AND cfg_isparent="yes" AND cfg_status="active" AND server_serial_no in ("0", "' . $server_serial_no . '")');
			}
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
								$channels = '';
								foreach (explode(';', $child_result[$j]->cfg_data) as $channel) {
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
				unset($logging_result, $count, $child_result, $count2);
			}
			if ($logging) $logging = "logging {\n$logging};\n\n";
			
			$config .= $logging;
			unset($logging);

			
			/** Get global config params that support multiple instances */
			$def_query = 'SELECT def_option FROM fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'functions WHERE def_max_parameters!=1';
			$def_results = $fmdb->query($def_query);
			foreach ($fmdb->last_result as $k => $obj) {
				$multi_def_params[] = $obj->def_option;
			}

			/** Build global configs */
			$config .= "options {\n";
			$config .= "\tdirectory \"" . str_replace('$ROOT', $server_root_dir, $config_dir_result[0]->cfg_data) . "\";\n";
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_id', 'cfg_', 'AND cfg_type="global" AND cfg_name!="include" AND view_id=0 AND domain_id=0 AND server_id=0 AND server_serial_no="0" AND cfg_status="active"');
			if ($fmdb->num_rows) {
				$config_result = $fmdb->last_result;
				$global_config_count = $fmdb->num_rows;
				for ($i=0; $i < $global_config_count; $i++) {
					if (in_array($config_result[$i]->cfg_name, $multi_def_params)) {
						$global_config[$config_result[$i]->cfg_name][] = array($config_result[$i]->cfg_data, $config_result[$i]->cfg_comment);
					} else {
						$global_config[$config_result[$i]->cfg_name] = array($config_result[$i]->cfg_data, $config_result[$i]->cfg_comment);
					}
				}
				unset($config_result, $global_config_count);
			} else $global_config = array();

			$server_config = array();
			/** Override with group-specific configs */
			if (is_array($server_group_ids)) {
				basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_id', 'cfg_', 'AND cfg_type="global" AND cfg_name!="include" AND view_id=0 AND domain_id=0 AND server_id=0 AND server_serial_no IN ("g_' . implode('","g_', $server_group_ids) . '") AND cfg_status="active"');
				if ($fmdb->num_rows) {
					$server_config_result = $fmdb->last_result;
					$config_count = $fmdb->num_rows;
					for ($j=0; $j < $config_count; $j++) {
						if (in_array($server_config_result[$j]->cfg_name, $multi_def_params)) {
							$server_config[$server_config_result[$j]->cfg_name][] = @array($server_config_result[$j]->cfg_data, $server_config_result[$j]->cfg_comment);
						} else {
							$server_config[$server_config_result[$j]->cfg_name] = @array($server_config_result[$j]->cfg_data, $server_config_result[$j]->cfg_comment);
						}
					}
					unset($server_config_result, $global_config_count);
				}
			}

			/** Override with server-specific configs */
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_id', 'cfg_', 'AND cfg_type="global" AND cfg_name!="include" AND view_id=0 AND domain_id=0 AND server_id=0 AND server_serial_no="' . $server_serial_no . '" AND cfg_status="active"');
			if ($fmdb->num_rows) {
				$server_config_result = $fmdb->last_result;
				$config_count = $fmdb->num_rows;
				for ($j=0; $j < $config_count; $j++) {
					if (in_array($server_config_result[$j]->cfg_name, $multi_def_params)) {
						$server_config[$server_config_result[$j]->cfg_name][] = @array($server_config_result[$j]->cfg_data, $server_config_result[$j]->cfg_comment);
					} else {
						$server_config[$server_config_result[$j]->cfg_name] = @array($server_config_result[$j]->cfg_data, $server_config_result[$j]->cfg_comment);
					}
				}
				unset($server_config_result, $global_config_count);
			}

			/** Merge arrays */
			$config_array = array_merge($global_config, $server_config);
			unset($global_config, $server_config);
			
			$include_hint_zone = false;

			foreach ($config_array as $cfg_name => $cfg_data) {
				if (!is_array($cfg_data[0])) {
					list($cfg_info, $cfg_comment) = $cfg_data;

					/** Include hint zone (root servers) */
					if ($cfg_name == 'recursion' && $cfg_info == 'yes') $include_hint_zone = true;
					
					$config .= $this->formatConfigOption($cfg_name, $cfg_info, $cfg_comment, $this->server_info, "\t");
				} else {
					/** Handle multiple instances if param */
					foreach ($cfg_data as $k => $multi_cfg_data) {
						list($cfg_info, $cfg_comment) = $multi_cfg_data;
						$config .= $this->formatConfigOption($cfg_name, $cfg_info, $cfg_comment, $this->server_info, "\t");
					}
				}
			}
			/** Build global option includes */
			$config .= $this->getIncludeFiles(0, $server_serial_no, $server_group_ids);
			
			/** Build rate limits */
			$config .= $this->getRateLimits(0, $server_serial_no);
			
			/** Build RRSet */
			$config .= $this->getRRSetOrder(0, $server_serial_no);
			
			/** Build RPZ */
			$config .= $this->getRPZ(0, $server_serial_no, $server_group_ids);
			
			$config .= "};\n\n";
			unset($config_array);
			
			
			/** Build controls configs */
			if (is_array($server_group_ids)) {
				basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'controls', 'control_id', 'control_', 'AND control_type="controls" AND server_serial_no IN ("0","' . $server_serial_no . '", "g_' . implode('","g_', $server_group_ids) . '") AND control_status="active"');
			} else {
				basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'controls', 'control_id', 'control_', 'AND control_type="controls" AND server_serial_no IN ("0","' . $server_serial_no . '") AND control_status="active"');
			}
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
			unset($control_result, $control_config_count, $control_config);

			/** Build statistics-channels configs */
			if (is_array($server_group_ids)) {
				basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'controls', 'control_id', 'control_', 'AND control_type="statistics" AND server_serial_no IN ("0","' . $server_serial_no . '", "g_' . implode('","g_', $server_group_ids) . '") AND control_status="active"');
			} else {
				basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'controls', 'control_id', 'control_', 'AND control_type="statistics" AND server_serial_no IN ("0","' . $server_serial_no . '") AND control_status="active"');
			}
			if ($fmdb->num_rows) {
				$control_result = $fmdb->last_result;
				$control_config_count = $fmdb->num_rows;
				$control_config = "statistics-channels {\n";
				for ($i=0; $i < $control_config_count; $i++) {
					if ($control_result[$i]->control_comment) {
						$comment = wordwrap($control_result[$i]->control_comment, 50, "\n");
						$control_config .= "\t// " . str_replace("\n", "\n// ", $comment) . "\n";
						unset($comment);
					}
					$control_config .= "\tinet " . $control_result[$i]->control_ip;
					$control_config .= ' port ' . $control_result[$i]->control_port;
					if (!empty($control_result[$i]->control_addresses)) $control_config .= ' allow { ' . trim($fm_dns_acls->parseACL($control_result[$i]->control_addresses), '; ') . '; };';
					$control_config .= "\n";
				}
				$control_config .= "};\n\n";
			} else $control_config = null;
			
			$config .= $control_config;
			unset($control_result, $control_config_count, $control_config);

			/** Build extra includes */
			$config .= $this->getIncludeFiles(0, $server_serial_no, $server_group_ids, 0, 'outside');
			
			
			/** Debian-based requires named.conf.options */
			if (isDebianSystem($server_os_distro)) {
				$data->files[dirname($server_config_file) . '/named.conf.options'] = array('contents' => $config, 'mode' => 0444, 'chown' => 'root');
				$config = $zones . "include \"" . dirname($server_config_file) . "/named.conf.options\";\n\n";
				$data->files[$server_config_file] = array('contents' => $config, 'mode' => 0444, 'chown' => 'root');
				$config = $zones;
			}
			

			/** Build Views */
			if (is_array($server_group_ids)) {
				basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', array('server_serial_no', 'view_order_id'), 'view_', "AND view_status='active' AND server_serial_no IN ('0', '$server_serial_no', 'g_" . implode("','g_", $server_group_ids) . "')");
			} else {
				basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', array('server_serial_no', 'view_order_id'), 'view_', "AND view_status='active' AND server_serial_no IN ('0', '$server_serial_no')");
			}
			if ($fmdb->num_rows) {
				$view_result = $fmdb->last_result;
				$view_count = $fmdb->num_rows;
				for ($i=0; $i < $view_count; $i++) {
					$include_hint_zone_local = $include_hint_zone;
					
					if ($view_result[$i]->view_comment) {
						$comment = wordwrap($view_result[$i]->view_comment, 50, "\n");
						$config .= '// ' . str_replace("\n", "\n// ", $comment) . "\n";
						unset($comment);
					}
					$config .= 'view "' . $view_result[$i]->view_name . "\" {\n";

					/** Get corresponding config records */
					basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_id', 'cfg_', "AND cfg_status='active' AND cfg_name!='include' AND cfg_type='global' AND server_serial_no='0' AND view_id='" . $view_result[$i]->view_id . "'");
					if ($fmdb->num_rows) {
						$config_result = $fmdb->last_result;
						$view_config_count = $fmdb->num_rows;
						for ($j=0; $j < $view_config_count; $j++) {
							$view_config[$config_result[$j]->cfg_name] = array($config_result[$j]->cfg_data, $config_result[$j]->cfg_comment);
						}
						unset($config_result, $view_config_count);
					} else $view_config = array();

					$server_view_config = array();
					/** Override with group-specific configs */
					if (is_array($server_group_ids)) {
						basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_id', 'cfg_', "AND cfg_status='active' AND cfg_name!='include' AND cfg_type='global' AND server_serial_no IN ('g_" . implode("','g_", $server_group_ids) . "') AND view_id='" . $view_result[$i]->view_id . "'");
						if ($fmdb->num_rows) {
							$server_config_result = $fmdb->last_result;
							$view_config_count = $fmdb->num_rows;
							for ($j=0; $j < $view_config_count; $j++) {
								$server_view_config[$server_config_result[$j]->cfg_name] = array($server_config_result[$j]->cfg_data, $server_config_result[$j]->cfg_comment);
							}
							unset($server_config_result, $view_config_count);
						}
					}

					/** Override with server-specific configs */
					basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_id', 'cfg_', "AND cfg_status='active' AND cfg_name!='include' AND cfg_type='global' AND server_serial_no='$server_serial_no' AND view_id='" . $view_result[$i]->view_id . "'");
					if ($fmdb->num_rows) {
						$server_config_result = $fmdb->last_result;
						$view_config_count = $fmdb->num_rows;
						for ($j=0; $j < $view_config_count; $j++) {
							$server_view_config[$server_config_result[$j]->cfg_name] = array($server_config_result[$j]->cfg_data, $server_config_result[$j]->cfg_comment);
						}
						unset($server_config_result, $view_config_count);
					}

					/** Merge arrays */
					$config_array = array_merge($view_config, $server_view_config);
					unset($view_config, $server_view_config);

					foreach ($config_array as $cfg_name => $cfg_data) {
						list($cfg_info, $cfg_comment) = $cfg_data;

						$config .= $this->formatConfigOption($cfg_name, $cfg_info, $cfg_comment, $this->server_info, "\t");
						
						if ($cfg_name == 'recursion') {
							$include_hint_zone_local = ($cfg_info == 'yes') ? true : false;
						}
					}
					unset($config_array);

					/** Build includes */
					$config .= $this->getIncludeFiles($view_result[$i]->view_id, $server_serial_no, $server_group_ids);

					/** Build rate limits */
					$config .= $this->getRateLimits($view_result[$i]->view_id, $server_serial_no);

					/** Build RRSet */
					$config .= $this->getRRSetOrder($view_result[$i]->view_id, $server_serial_no);

					/** Build RPZ */
					$config .= $this->getRPZ($view_result[$i]->view_id, $server_serial_no, $server_group_ids);
					
					/** Get corresponding keys */
					basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_id', 'key_', "AND key_status='active' AND key_view='" . $view_result[$i]->view_id . "'");
					if ($fmdb->num_rows) {
						$key_result = $fmdb->last_result;
						$key_config = '// This file was built using ' . $_SESSION['module'] . ' ' . $fm_version . ' on ' . date($date_format . ' ' . $time_format . ' e') . "\n\n";
						$key_count = $fmdb->num_rows;
						for ($k=0; $k < $key_count; $k++) {
							$key_name = trimFullStop($key_result[$k]->key_name);
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
							basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_id', 'cfg_', 'AND server_id!="' . $server_id . '" AND cfg_name="keys" AND (cfg_data="key_' . $key_result[$k]->key_id . '" OR cfg_data LIKE "key_' . $key_result[$k]->key_id . ',%" OR cfg_data LIKE "%,key_' . $key_result[$k]->key_id . ',%" OR cfg_data LIKE "%,key_' . $key_result[$k]->key_id . '") AND cfg_status="active"');
							if ($fmdb->num_rows) {
								$server_result = $fmdb->last_result;
								foreach ($server_result as $server_info) {
									$config .= $this->formatServerKeys($server_info->server_id, $key_name, true);
								}
								unset($server_result);
							}
						}
						$data->files[$server_zones_dir . '/views.conf.' . sanitize($view_result[$i]->view_name, '-') . '.keys'] = array('contents' => $key_config, 'mode' => 0400);
						unset($key_result, $key_count);
					}
					
					/** Generate zone file */
					list($tmp_files, $error) = $this->buildZoneDefinitions($server_zones_dir, $server_slave_zones_dir, $server_serial_no, $view_result[$i]->view_id, sanitize($view_result[$i]->view_name, '-'), $include_hint_zone_local);
					if ($error) $message = $error;
					
					/** Include zones for view */
					if (is_array($tmp_files)) {
						/** Include view keys if present */
						if (@array_key_exists($server_zones_dir . '/views.conf.' . sanitize($view_result[$i]->view_name, '-') . '.keys', (array) $data->files)) {
							$config .= "\tinclude \"" . $server_zones_dir . "/views.conf." . sanitize($view_result[$i]->view_name, '-') . ".keys\";\n";
						}
						$config .= "\tinclude \"" . $server_zones_dir . '/zones.conf.' . sanitize($view_result[$i]->view_name, '-') . "\";\n";
						$files = array_merge($files, $tmp_files);
					}
					
					$config .= "};\n\n";
					
					$key_config = $view_config = $server_view_config = null;
				}
			} else {
				/** Generate zones.conf.all */
				list($files, $message) = $this->buildZoneDefinitions($server_zones_dir, $server_slave_zones_dir, $server_serial_no, 0, null, $include_hint_zone);
				
				/** Include all zones in one file */
				if (is_array($files)) {
					$config .= "\ninclude \"" . $server_zones_dir . "/zones.conf.all\";\n";
				}
			}

			/** Handle masters|slaves vs primaries|secondaries */
			if (version_compare($server_version, '9.16.12', '<')) {
				$config = str_replace(array('primaries', 'primary', 'secondary'), array('masters', 'master', 'slave'), $config);
				foreach ($files as $filename => $v) {
					if (strpos($filename, 'zones.conf') !== false && !is_array($v)) {
						$files[$filename] = str_replace(array('primaries', 'primary', 'secondary'), array('masters', 'master', 'slave'), $v);
					}
				}
			}

			/** Debian-based requires named.conf.local */
			if (isDebianSystem($server_os_distro)) {
				$data->files[dirname($server_config_file) . '/named.conf.local'] = array('contents' => $config, 'mode' => 0444, 'chown' => 'root');
				$config = $data->files[$server_config_file]['contents'] . "include \"" . dirname($server_config_file) . "/named.conf.local\";\n\n";
			}

			$data->files[$server_config_file] = array('contents' => $config, 'mode' => 0444, 'chown' => 'root');
			unset($config);
			if (is_array($files)) {
				$data->files = array_merge($data->files, $files);
				unset($files);
			}

			/** Set variable containing all loaded domain_ids */
			if (!$dryrun) {
				$data->built_domain_ids = array_unique($GLOBALS['built_domain_ids']);
				$this->setBuiltDomainIDs($server_serial_no, $data->built_domain_ids);
			}
			
			/** url-only servers should not have any other files configured */
			if ($this->server_info->server_type == 'url-only') {
				unset($data->files);
				unset($GLOBALS['built_domain_ids']);
			}
			if ($server_url_server_type && $server_url_config_file) {
				$data->files[$this->server_info->server_url_config_file] = $this->url_config_file;
			}
			
			return array(get_object_vars($data), $message);
		}
		
		/** Bad DNS server */
		$error = _('Server is not found.') . "\n";
		if (isset($post_data['preview'])) {
			return $error;
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
	function buildZoneConfig($post_data) {
		global $fmdb, $__FM_CONFIG, $fm_module_servers, $fm_login;
		
		$server_serial_no = sanitize($post_data['SERIALNO']);
		extract($post_data);
		
		if (!isset($fm_login)) {
			require_once(ABSPATH . 'fm-modules/facileManager/classes/class_logins.php');
		}
		if ($fm_login->isLoggedIn()) {
			if (!currentUserCan(array('access_specific_zones', 'view_all'), $_SESSION['module'], array(0, $domain_id))) {
				unAuth();
			}
		}
		
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', $server_serial_no, 'server_', 'server_serial_no');
		if ($fmdb->num_rows || $SERIALNO == -1) {
			if ($SERIALNO != -1) {
				$data = $fmdb->last_result[0];
				extract(get_object_vars($data), EXTR_SKIP);
			}
			/** Ensure $data is an object */
			if (!is_object($data)) {
				$data = (object)[];
			}

			if ($server_type == 'url-only') {
				$_POST['action'] = 'buildconf';
				return $this->buildServerConfig($_POST);
			} elseif (!$domain_id) {
				/** Build all zone files */
				list($data->files, $message) = $this->buildZoneDefinitions($server_zones_dir, $server_slave_zones_dir, $server_serial_no);
			} else {
				/** Build zone files for $domain_id */
				$server_version = getNameFromID($server_serial_no, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_version');
				$query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE `domain_status`='active' AND (`domain_id`=" . sanitize($domain_id) . " OR `domain_clone_domain_id`=" . sanitize($domain_id) . ") ";
				if ($SERIALNO != -1) {
					$server_id = getServerID($server_serial_no, $_SESSION['module']);
					$query .= " AND (`domain_name_servers`='0' OR `domain_name_servers`='s_{$server_id}' OR `domain_name_servers` LIKE 's_{$server_id};%' OR `domain_name_servers` LIKE '%;s_{$server_id};%' OR `domain_name_servers` LIKE '%;s_{$server_id}'";

					/** Get the associated server groups */
					if (!isset($fm_module_servers)) {
						include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_servers.php');
					}
					if ($server_group_ids = $fm_module_servers->getServerGroupIDs($server_id)) {
						foreach ($server_group_ids as $group_id) {
							$query .= " OR `domain_name_servers`='g_{$group_id}' OR `domain_name_servers` LIKE 'g_{$group_id};%' OR `domain_name_servers` LIKE '%;g_{$group_id};%' OR `domain_name_servers` LIKE '%;g_{$group_id}'";
						}
					}
					$query .= ')';
				}
				$query .= " ORDER BY `domain_clone_domain_id`,`domain_name`";
				$fmdb->query($query);
				if ($fmdb->num_rows) {
					$count = $fmdb->num_rows;
					$zone_result = $fmdb->last_result;
					
					/** Get zone filename format */
					$file_format = getOption('zone_file_format', $_SESSION['user']['account_id'], $_SESSION['module']);
					if (!$file_format) {
						$file_format = $__FM_CONFIG[$_SESSION['module']]['default']['options']['zone_file_format']['default_value'];
					}
					
					for ($i=0; $i < $count; $i++) {
						/** Is this a clone id? */
						if ($zone_result[$i]->domain_clone_domain_id) $zone_result[$i] = $this->mergeZoneDetails($zone_result[$i], 'clone');
						elseif ($zone_result[$i]->domain_template_id) $zone_result[$i] = $this->mergeZoneDetails($zone_result[$i], 'template');
						
						if (getSOACount($zone_result[$i]->domain_id)) {
							$domain_name = trimFullStop($this->getDomainName($zone_result[$i]->domain_mapping, trimFullStop($zone_result[$i]->domain_name)));
							$file_ext = null;

							/** Are there multiple zones with the same name? */
							if (isset($zone_result[$i]->parent_domain_id)) {
								basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $zone_result[$i]->domain_name, 'domain_', 'domain_name', 'AND domain_id!=' . $zone_result[$i]->parent_domain_id);
								if ($fmdb->num_rows) $file_ext = '.' . $zone_result[$i]->parent_domain_id;
							} else {
								$zone_result[$i]->parent_domain_id = $zone_result[$i]->domain_id;
								basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $zone_result[$i]->domain_name, 'domain_', 'domain_name', 'AND domain_id!=' . $zone_result[$i]->domain_id);
								if ($fmdb->num_rows) $file_ext = '.' . $zone_result[$i]->domain_id;
							}
//							basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $zone_result[$i]->domain_name, 'domain_', 'domain_name', 'AND domain_clone_domain_id=0 AND domain_id!=' . $zone_result[$i]->domain_id);
//							if ($fmdb->num_rows) $file_ext = $zone_result[$i]->domain_id . ".$file_ext";
							
							/** Build zone file */
							$domain_type = ($zone_result[$i]->domain_type == 'primary' && version_compare($server_version, '9.16.12', '<')) ? 'master' : $zone_result[$i]->domain_type;
							$data->files[$server_zones_dir . '/' . $domain_type . '/' . str_replace('{ZONENAME}', $domain_name . $file_ext, $file_format)] = $this->buildZoneFile($zone_result[$i], $server_serial_no);
						}
					}

					unset($zone_result, $count);
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
	function buildZoneDefinitions($server_zones_dir, $server_slave_zones_dir, $server_serial_no, $view_id = 0, $view_name = null, $include_hint_zone = false) {
		global $fmdb, $__FM_CONFIG, $fm_dns_acls, $fm_module_servers;
		
		$error = '';
		
		include(ABSPATH . 'fm-includes/version.php');
		
		/** Get datetime formatting */
		$date_format = getOption('date_format', $_SESSION['user']['account_id']);
		$time_format = getOption('time_format', $_SESSION['user']['account_id']);
		
		$files = array();
		$zones = '// This file was built using ' . $_SESSION['module'] . ' ' . $__FM_CONFIG[$_SESSION['module']]['version'] . ' on ' . date($date_format . ' ' . $time_format . ' e') . "\n\n";
		$server_id = getServerID($server_serial_no, $_SESSION['module']);
		$server_version = getNameFromID($server_serial_no, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_version');
		
		/** Build hint zone (root servers) */
		if ($include_hint_zone) {
			$zones .= 'zone "." {' . "\n";
			$zones .= "\ttype hint;\n";
			$zones .= "\tfile \"$server_zones_dir/hint/named.root\";\n";
			$zones .= "};\n";
			
			list($files[$server_zones_dir . '/hint/named.root'], $error) = $this->getHintZone();
		}

		/** Build zones */
		$group_sql = '';
		if ($server_group_ids = $fm_module_servers->getServerGroupIDs($server_id)) {
			foreach ($server_group_ids as $group_id) {
				$group_sql .= " OR (`domain_name_servers`='0' OR `domain_name_servers`='g_{$group_id}' OR `domain_name_servers` LIKE 'g_{$group_id};%' OR `domain_name_servers` LIKE '%;g_{$group_id};%' OR `domain_name_servers` LIKE '%;g_{$group_id}')";
			}
			if ($group_sql) {
				$group_sql = ' OR ' . ltrim($group_sql, ' OR ');
			}
		}
		$view_sql = "AND (`domain_view`<=0 OR `domain_view`=$view_id OR `domain_view` LIKE '$view_id;%' OR `domain_view` LIKE '%;$view_id' OR `domain_view` LIKE '%;$view_id;%')";
		$query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE `domain_status`='active' AND `domain_template`='no' AND 
			((`domain_name_servers`='0' OR `domain_name_servers`='s_{$server_id}' OR `domain_name_servers` LIKE 's_{$server_id};%' OR `domain_name_servers` LIKE '%;s_{$server_id};%' OR `domain_name_servers` LIKE '%;s_{$server_id}' $group_sql))
			 $view_sql ORDER BY `domain_dnssec_parent_domain_id` DESC,`domain_clone_domain_id`,`domain_name` ASC";
		$fmdb->query($query);
		if ($fmdb->num_rows || $include_hint_zone) {
			$count = $fmdb->num_rows;
			$zone_result = $fmdb->last_result;

			/** Get zone filename format */
			$file_format = getOption('zone_file_format', $_SESSION['user']['account_id'], $_SESSION['module']);
			if (!$file_format) {
				$file_format = $__FM_CONFIG[$_SESSION['module']]['default']['options']['zone_file_format']['default_value'];
			}

			for ($i=0; $i < $count; $i++) {
				if ($zone_result[$i]->domain_type == 'url-redirect') {
					$zone_result[$i]->domain_type = 'primary';
				}
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
							if (!$server_group_ids) $skip = false;
							else {
								foreach ($server_group_ids as $group_id) {
									if (!$domain_server_id || 'g_' . $group_id == $domain_server_id) $skip = false;
								}
							}
						}
					}
					if ($skip) continue;
				}
				
				/** Valid SOA and NS records must exist */
				if ((getSOACount($zone_result[$i]->domain_id) && getNSCount($zone_result[$i]->domain_id)) ||
					$zone_result[$i]->domain_type != 'primary') {

					$domain_name_file = $this->getDomainName($zone_result[$i]->domain_mapping, trimFullStop($zone_result[$i]->domain_name));
					$domain_name = isset($zone_result[$i]->domain_name_file) ? $this->getDomainName($zone_result[$i]->domain_mapping, trimFullStop($zone_result[$i]->domain_name_file)) : $domain_name_file;
					list ($domain_type, $auto_zone_options) = $this->processServerGroups($zone_result[$i], $server_id, $view_id);
					$zones .= 'zone "' . trimFullStop($domain_name) . "\" {\n";
					$zones .= "\ttype $domain_type;\n";
					$default_file_ext = $file_ext = '';
					
					/** Are there multiple zones with the same name? */
					if (isset($zone_result[$i]->parent_domain_id)) {
						basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $zone_result[$i]->domain_name, 'domain_', 'domain_name', 'AND domain_id!=' . $zone_result[$i]->parent_domain_id);
						if ($fmdb->num_rows) $file_ext = '.' . $zone_result[$i]->parent_domain_id;
					} else {
						$zone_result[$i]->parent_domain_id = $zone_result[$i]->domain_id;
						basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $zone_result[$i]->domain_name, 'domain_', 'domain_name', 'AND domain_id!=' . $zone_result[$i]->domain_id);
						if ($fmdb->num_rows) $file_ext = '.' . $zone_result[$i]->domain_id;
					}
					
					if ($domain_type == 'secondary' && $file_ext == $default_file_ext) {
						$file_ext = '.' . $view_id;
					}
					unset($default_file_ext);
					
					switch($domain_type) {
						case 'primary':
						case 'secondary':
							$zone_data_dir = ($server_slave_zones_dir && $domain_type == 'secondary') ? $server_slave_zones_dir : $server_zones_dir;
							$domain_name_file = str_replace('{ZONENAME}', trimFullStop($domain_name_file) . $file_ext, $file_format);
							$zones .= "\tfile \"$zone_data_dir/$domain_type/$domain_name_file\";\n";
							$zones .= $this->getZoneOptions(array($zone_result[$i]->domain_id, $zone_result[$i]->parent_domain_id, $zone_result[$i]->domain_template_id), $server_serial_no, $domain_type, $server_group_ids, $auto_zone_options);
							/** Build zone file */
							$zone_file_contents = ($domain_type == 'primary') ? $this->buildZoneFile($zone_result[$i], $server_serial_no, $server_group_ids) : null;
							if ($zone_file_contents != null) {
								if (version_compare($server_version, '9.16.12', '<')) {
									$domain_type = 'master';
								}
								$files[$zone_data_dir . '/' . $domain_type . '/' . $domain_name_file] = array('contents' => $zone_file_contents, 'syntax_check' => $zone_result[$i]->domain_check_config);
							}
							unset($zone_file_contents);
							break;
						case 'stub':
							$zone_data_dir = ($server_slave_zones_dir) ? $server_slave_zones_dir : $server_zones_dir;
							$zones .= "\tfile \"$zone_data_dir/stub/" . str_replace('{ZONENAME}', trimFullStop($domain_name) . $file_ext, $file_format) . "\";\n";
							$domain_master_servers = str_replace(';', "\n", rtrim(str_replace(' ', '', getNameFromID($zone_result[$i]->domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'domain_id', 'cfg_data', null, "AND cfg_name='primaries'")), ';'));
							$zones .= "\tprimaries { " . trim($fm_dns_acls->parseACL($domain_master_servers), '; ') . "; };\n";
							break;
						case 'forward':
							$zones .= $this->getZoneOptions($zone_result[$i]->domain_id, $server_serial_no, $domain_type, $server_group_ids, $auto_zone_options);
					}
					$zones .= "};\n";

					/** Build DNSSEC keys for domain_id */
					$real_domain_id = (isset($zone_result[$i]->parent_domain_id)) ? $zone_result[$i]->parent_domain_id : $zone_result[$i]->omain_id;
					basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'keys', array('key_id', 'domain_id'), 'key_', "AND key_type='dnssec' AND key_status='active' AND key_signing='yes' AND domain_id=$real_domain_id");
					if ($fmdb->num_rows) {
						$dnssec_keys_result = $fmdb->last_result;

						/** Get key-directory */
						if ($key_directory = str_replace('$ZONES', $server_zones_dir, $this->getKeyDirectory($real_domain_id, $view_id, $server_serial_no, $server_id))) {
							/** Populate the DNSSEC signing key files */
							foreach ($dnssec_keys_result as $dnssec_key_record) {
								$files[$key_directory . DIRECTORY_SEPARATOR . $dnssec_key_record->key_name . '.private'] = $dnssec_key_record->key_secret;
								$files[$key_directory . DIRECTORY_SEPARATOR . $dnssec_key_record->key_name . '.key'] = $dnssec_key_record->key_public;
							}
						}
					}
	
					/** Add domain_id to built_domain_ids for tracking */
					$GLOBALS['built_domain_ids'][] = $zone_result[$i]->domain_id;
					if (isset($zone_result[$i]->parent_domain_id)) {
						$GLOBALS['built_domain_ids'][] = $zone_result[$i]->parent_domain_id;
					}
				}
			}
			unset($zone_result, $count);
			
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
	function buildZoneFile($domain, $server_serial_no, $server_group_ids = null) {
		global $__FM_CONFIG, $fmdb;
		
		/** Get datetime formatting */
		$date_format = getOption('date_format', $_SESSION['user']['account_id']);
		$time_format = getOption('time_format', $_SESSION['user']['account_id']);
		
		$zone_file = '; This file was built using ' . $_SESSION['module'] . ' ' . $__FM_CONFIG[$_SESSION['module']]['version'] . ' on ' . date($date_format . ' ' . $time_format . ' e') . "\n\n";

		/** Get the SOA */
		list($soa, $soa_ttl) = $this->buildSOA($domain);
		$zone_file .= $soa;
		
		/** Get the records */
		$zone_file .= $this->buildRecords($domain, $server_serial_no, $soa_ttl);
		
		/** Get additional DS records */
		basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_id', 'domain_', 'AND domain_dnssec_parent_domain_id="' . $domain->parent_domain_id . '"');
		if ($fmdb->num_rows) {
			for($i=0; $i<$fmdb->num_rows; $i++) {
				$zone_file .= $fmdb->last_result[$i]->domain_dnssec_ds_rr;
			}
		}
		
		/** Build includes */
		$domain_id = (isset($domain->parent_domain_id)) ? $domain->parent_domain_id : $domain->domain_id;
		// $zone_file .= ltrim(str_replace(array('include ', ';'), array('$INCLUDE ', ''), $this->getIncludeFiles(0, $server_serial_no, $server_group_ids, $domain_id)));

		/** Sign the zone? */
		if ($server_serial_no > 0 && $domain->domain_dnssec == 'yes' && $domain->domain_dnssec_sign_inline == 'no') {
			$zone_file = $this->dnssecSignZone($domain, $zone_file);
		}
		
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
					$fmdb->query($query);
					if ($fmdb->num_rows) {
						$zone_result = $fmdb->last_result[0];
						/** Is this a clone id? */
						if ($zone_result->domain_clone_domain_id) $zone_result = $this->mergeZoneDetails($zone_result, 'clone');
						elseif ($zone_result->domain_template_id) $zone_result = $this->mergeZoneDetails($zone_result, 'template');
						
						if (getSOACount($zone_result->domain_id)) {
							/** Get zone filename format */
							$file_format = getOption('zone_file_format', $_SESSION['user']['account_id'], $_SESSION['module']);
							if (!$file_format) {
								$file_format = $__FM_CONFIG[$_SESSION['module']]['default']['options']['zone_file_format']['default_value'];
							}

							$domain_name_file = $this->getDomainName($zone_result->domain_mapping, trimFullStop($zone_result->domain_name));
							$default_file_ext = $file_ext = null;
					
							/** Are there multiple zones with the same name? */
							if (isset($zone_result->parent_domain_id)) {
								basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $zone_result->domain_name, 'domain_', 'domain_name', 'AND domain_id!=' . $zone_result->parent_domain_id);
								if ($fmdb->num_rows) $file_ext = '.' . $zone_result->parent_domain_id;
							} else {
								$zone_result->parent_domain_id = $zone_result->domain_id;
								basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $zone_result->domain_name, 'domain_', 'domain_name', 'AND domain_id!=' . $zone_result->domain_id);
								if ($fmdb->num_rows) $file_ext = '.' . $zone_result->domain_id;
							}
							
							if ($domain_type == 'secondary' && $file_ext == $default_file_ext) {
								$file_ext = $view_id . ".$default_file_ext";
							}
							unset($default_file_ext);

							/** Build zone file */
							$domain_type = ($zone_result->domain_type == 'primary' && version_compare($server_version, '9.16.12', '<')) ? 'master' : $zone_result->domain_type;
							$data->files[$server_zones_dir . '/' . $domain_type . '/' . str_replace('{ZONENAME}', trimFullStop($domain_name_file) . $file_ext, $file_format)] = $this->buildZoneFile($zone_result, $server_serial_no);
							
							/** Track reloads */
							$data->reload_domain_ids[] = isset($zone_result->parent_domain_id) ? $zone_result->parent_domain_id : $zone_result->domain_id;
						}
					}
				}
				if (is_array($data->files)) return array(get_object_vars($data), null);
			} else {
				/** process server config build */
				list($config, $message) = $this->buildServerConfig($post_data);
				$config['server_build_all'] = true;
				$config['purge_config_files'] = $data->purge_config_files;
				
				return array($config, $message);
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
		
		$zone_file = '';
		
		$query = "SELECT * FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}domains d, fm_{$__FM_CONFIG['fmDNS']['prefix']}soa s WHERE 
			domain_status='active' AND d.account_id='{$_SESSION['user']['account_id']}' AND s.account_id='{$_SESSION['user']['account_id']}'
			AND s.soa_id=d.soa_id AND d.domain_id IN ('{$domain->parent_domain_id}','{$domain->domain_id}','{$domain->domain_template_id}')";
		$fmdb->get_results($query);
		if ($fmdb->num_rows) {
			extract(get_object_vars($fmdb->last_result[0]));
			
			$domain_name_trim = trimFullStop($domain->domain_name);
			
			$master_server = ($soa_append == 'yes') ? trimFullStop($soa_master_server) . '.' . $domain_name_trim . '.' : trimFullStop($soa_master_server) . '.';
			$admin_email = ($soa_append == 'yes') ? trimFullStop($soa_email_address) . '.' . $domain_name_trim . '.' : trimFullStop($soa_email_address) . '.';
			
			$domain_name = $this->getDomainName($domain->domain_mapping, $domain_name_trim);
			
			$domain_id = isset($domain->parent_domain_id) ? $domain->parent_domain_id : $domain->domain_id;
			$serial = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'soa_serial_no');
			
			$ttl = (isset($domain->domain_ttl)) ? $domain->domain_ttl : $soa_ttl;

			$zone_file .= '$TTL ' . $ttl . "\n";
			$zone_file .= "$domain_name IN SOA $master_server $admin_email (\n";
			$zone_file .= "\t\t$serial\t; Serial\n";
			$zone_file .= "\t\t$soa_refresh\t\t; Refresh\n";
			$zone_file .= "\t\t$soa_retry\t\t; Retry\n";
			$zone_file .= "\t\t$soa_expire\t\t; Expire\n";
			$zone_file .= "\t\t$soa_ttl )\t\t; Negative caching of TTL\n\n";
		}
		
		return array($zone_file, $this->getSOASeconds($soa_ttl));
	}


	/**
	 * Builds the records for $domain->domain_id
	 *
	 * @since 1.0
	 * @package fmDNS
	 */
	function buildRecords($domain, $server_serial_no, $default_ttl) {
		global $fmdb, $__FM_CONFIG;
		
		$zone_file = $skipped_records = '';
		$domain_name_trim = trimFullStop($domain->domain_name);
		list($server_version) = explode('-', getNameFromID($server_serial_no, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_version'));
		if (!$server_version) {
			$server_version = '10.0';
		}
		
		/** Is this a cloned zone */
		if (isset($domain->parent_domain_id)) {
			if ($domain->domain_template == 'no' && !$domain->domain_template_id) {
				$full_zone_clone = (getOption('clones_use_dnames', $_SESSION['user']['account_id'], $_SESSION['module']) == 'yes') ? true : false;
				if ($domain->domain_clone_dname) {
					$full_zone_clone = ($domain->domain_clone_dname == 'yes') ? true : false;
				}
			} else $full_zone_clone = false;
			
			/** Are there any additional records? */
			if ($full_zone_clone) {
				basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $domain->parent_domain_id, 'record_', 'domain_id', "AND `record_status`='active'");
				if ($fmdb->num_rows) {
					$full_zone_clone = false;
				}
			}
			
			/** Are there any skipped records? */
			global $fm_dns_records;
			if (!class_exists('fm_dns_records')) include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_records.php');
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
				$record_result[$count] = (object) [
					'record_name' => '@',
					'record_value' => trimFullStop(getNameFromID($domain->domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name')) . '.',
					'record_ttl' => null,
					'record_class' => 'IN',
					'record_type' => 'DNAME',
					'record_append' => 'no',
					'record_comment' => null
				];
				
				$count++;
			}
			
			for ($i=0; $i < $count; $i++) {
				$domain_name = $this->getDomainName($domain->domain_mapping, $domain_name_trim);
				$record_comment = $record_result[$i]->record_comment ?  ' ; ' . $record_result[$i]->record_comment : null;
				$record_name = ($record_result[$i]->record_append == 'yes') ? $record_result[$i]->record_name . '.' . $domain_name_trim . '.' : $record_result[$i]->record_name;
				if ($record_result[$i]->record_name[0] == '@') {
					$record_name = $domain_name;
				}
				
				$record_ttl = $record_result[$i]->record_ttl;
				if ($domain->domain_dynamic == 'yes') {
					$record_ttl = ($record_ttl > 0) ? $record_ttl : $default_ttl;
				}
				$record_start = str_pad($record_name, 25) . $separator . $record_ttl . $separator . $record_result[$i]->record_class . $separator . $record_result[$i]->record_type;

				// Swap custom variables for values
				$record_result[$i]->record_value = $this->swapVars4Values($record_result[$i]->record_value, $domain);
				
				switch($record_result[$i]->record_type) {
					case 'A':
					case 'AAAA':
						$record_array[$record_result[$i]->record_type]['Description'] = 'Host addresses';
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_start . $separator . $record_result[$i]->record_value . $record_comment . "\n";
						break;
					case 'CAA':
						$record_array[$record_result[$i]->record_type]['Version'] = '9.9.6';
						$record_array[$record_result[$i]->record_type]['Description'] = 'Certification Authority Authorizations';
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_start . $separator . $record_result[$i]->record_flags . ' ' . $record_result[$i]->record_params . ' "' . $record_result[$i]->record_value . '"' . $record_comment . "\n";
						break;
					case 'CERT':
						$record_array[$record_result[$i]->record_type]['Version'] = '9.7.0';
						$record_array[$record_result[$i]->record_type]['Description'] = 'Certificates';
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_start . $separator . $record_result[$i]->record_cert_type . ' ' . $record_result[$i]->record_key_tag . ' ' . $record_result[$i]->record_algorithm . "\t(\n\t\t\t" . str_replace("\n", "\n\t\t\t", $record_result[$i]->record_value) . ' )' . $record_comment . "\n";
						break;
					case 'CNAME':
					case 'DNAME':
						$record_array[$record_result[$i]->record_type]['Description'] = 'Aliases';
						$record_value = ($record_result[$i]->record_append == 'yes') ? $record_result[$i]->record_value . '.' . $domain_name_trim . '.' : $record_result[$i]->record_value;
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_start . $separator . $record_value . $record_comment . "\n";
						break;
					case 'DHCID':
						$record_array[$record_result[$i]->record_type]['Version'] = '9.5.0';
						$record_array[$record_result[$i]->record_type]['Description'] = 'DHCP ID records';
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_start . $separator . "\t(\n\t\t\t" . str_replace("\n", "\n\t\t\t", $record_result[$i]->record_value) . ' )' . $record_comment . "\n";
						break;
					case 'DLV':
						$record_array[$record_result[$i]->record_type]['Description'] = 'DNSSEC Lookaside Validation';
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_start . $separator . $record_result[$i]->record_key_tag . ' ' . $record_result[$i]->record_algorithm . "\t(\n\t\t\t" . str_replace("\n", "\n\t\t\t", $record_result[$i]->record_value) . ' )' . $record_comment . "\n";
						break;
					case 'DS':
						$record_array[$record_result[$i]->record_type]['Description'] = 'Delegation Signer';
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_start . $separator . $record_result[$i]->record_key_tag . ' ' . $record_result[$i]->record_algorithm . ' ' . $record_result[$i]->record_cert_type . "\t(\n\t\t\t" . str_replace("\n", "\n\t\t\t", $record_result[$i]->record_value) . ' )' . $record_comment . "\n";
						break;
					case 'DNSKEY':
					case 'KEY':
						$record_array[$record_result[$i]->record_type]['Version'] = '9.5.0';
						$record_array[$record_result[$i]->record_type]['Description'] = 'Key records';
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_start . $separator . $record_result[$i]->record_flags . ' 3 ' . $record_result[$i]->record_algorithm . "\t(\n\t\t\t" . str_replace("\n", "\n\t\t\t", $record_result[$i]->record_value) . ' )' . $record_comment . "\n";
						break;
					case 'HINFO':
						$record_array[$record_result[$i]->record_type]['Description'] = 'Hardware information records';
						$hardware = (strpos($record_result[$i]->record_value, ' ') === false) ? $record_result[$i]->record_value : '"' . $record_result[$i]->record_value . '"';
						$os = (strpos($record_result[$i]->record_os, ' ') === false) ? $record_result[$i]->record_os : '"' . $record_result[$i]->record_os . '"';
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_start . $separator . $hardware . ' ' . $os . $record_comment . "\n";
						break;
					case 'KX':
						$record_array[$record_result[$i]->record_type]['Description'] = 'Key Exchange records';
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_start . $separator . $record_result[$i]->record_priority . $separator . $record_result[$i]->record_value . $record_comment . "\n";
						break;
					case 'MX':
						$record_array[2 . $record_result[$i]->record_type]['Description'] = 'Mail Exchange records';
						$record_value = ($record_result[$i]->record_append == 'yes') ? $record_result[$i]->record_value . '.' . $domain_name_trim . '.' : $record_result[$i]->record_value;
						$record_array[2 . $record_result[$i]->record_type]['Data'][] = $record_start . $separator . $record_result[$i]->record_priority . $separator . $record_value . $record_comment . "\n";
						break;
					case 'NAPTR':
						$record_array[$record_result[$i]->record_type]['Description'] = 'Name Authority Pointer records';
						$record_value = ($record_result[$i]->record_append == 'yes') ? $record_result[$i]->record_value . '.' . $domain_name_trim . '.' : $record_result[$i]->record_value;
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_start . $separator . $record_result[$i]->record_weight . $separator . $record_result[$i]->record_priority . $separator . '"' . $record_result[$i]->record_flags . '"' . $separator . '"' . $record_result[$i]->record_params . '"' . $separator . '"' . $record_result[$i]->record_regex . '"' . $separator . $record_value . $record_comment . "\n";
						break;
					case 'NS':
						$record_array[1 . $record_result[$i]->record_type]['Description'] = 'Name servers';
						$record_value = ($record_result[$i]->record_append == 'yes') ? $record_result[$i]->record_value . '.' . $domain_name_trim . '.' : $record_result[$i]->record_value;
						$record_array[1 . $record_result[$i]->record_type]['Data'][] = $record_start . $separator . $record_value . $record_comment . "\n";
						break;
					case 'OPENPGPKEY':
						$record_array[$record_result[$i]->record_type]['Version'] = '9.11.0';
						$record_array[$record_result[$i]->record_type]['Description'] = 'OpenPGP Keys';
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_start . $separator . "\t(\n\t\t\t" . str_replace("\n", "\n\t\t\t", $record_result[$i]->record_value) . ' )' . $record_comment . "\n";
						break;
					case 'PTR':
						$record_array[$record_result[$i]->record_type]['Description'] = 'Addresses point to hosts';
						$record_name = ($record_result[$i]->record_append == 'yes' && $domain->domain_mapping == 'reverse') ? $record_result[$i]->record_name . '.' . $domain_name : $record_result[$i]->record_name;
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_start . $separator . $record_result[$i]->record_value . $record_comment . "\n";
						break;
					case 'RP':
						$record_array[$record_result[$i]->record_type]['Description'] = 'Responsible Persons';
						$record_value = ($record_result[$i]->record_append == 'yes') ? $record_result[$i]->record_value . '.' . $domain_name_trim . '.' : $record_result[$i]->record_value;
						$record_text = ($record_result[$i]->record_append == 'yes') ? $record_result[$i]->record_text . '.' . $domain_name_trim . '.' : $record_result[$i]->record_text;
						if (!strlen($record_result[$i]->record_text)) $record_text = '.';
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_start . $separator . $record_value . $separator . $record_text . $record_comment . "\n";
						break;
					case 'SMIMEA':
						$record_array[$record_result[$i]->record_type]['Version'] = '9.11.0';
						$record_array[$record_result[$i]->record_type]['Description'] = 'S/MIME';
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_start . $separator . $record_result[$i]->record_priority . ' ' . $record_result[$i]->record_weight . ' ' . $record_result[$i]->record_port . $separator . "\t(\n\t\t\t" . str_replace("\n", "\n\t\t\t", $record_result[$i]->record_value) . ' )' . $record_comment . "\n";
						break;
					case 'SSHFP':
						$record_array[$record_result[$i]->record_type]['Version'] = '9.3.0';
						$record_array[$record_result[$i]->record_type]['Description'] = 'SSH Key Fingerprint records';
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_start . $separator . $record_result[$i]->record_algorithm . ' ' . $record_result[$i]->record_cert_type . ' ' . $record_result[$i]->record_value . $record_comment . "\n";
						break;
					case 'SRV':
						$record_array[$record_result[$i]->record_type]['Description'] = 'Service records';
						$record_value = ($record_result[$i]->record_append == 'yes') ? $record_result[$i]->record_value . '.' . $domain_name_trim . '.' : $record_result[$i]->record_value;
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_start . $separator . $record_result[$i]->record_priority . $separator . $record_result[$i]->record_weight . $separator . $record_result[$i]->record_port . $separator . $record_value . $record_comment . "\n";
						break;
					case 'TLSA':
						$record_array[$record_result[$i]->record_type]['Description'] = 'Transport Layer Security';
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_start . $separator . $record_result[$i]->record_priority . ' ' . $record_result[$i]->record_weight . ' ' . $record_result[$i]->record_port . $separator . $record_result[$i]->record_value . $record_comment . "\n";
						break;
					case 'TXT':
						$record_array[$record_result[$i]->record_type]['Description'] = 'TXT records';
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_start . "\t(\"" . join("\"\n\t\t\"", $this->characterSplit($record_result[$i]->record_value)) . "\")" . $record_comment . "\n";
						break;
					case 'URI':
						$record_array[$record_result[$i]->record_type]['Version'] = '9.11.0';
						$record_array[$record_result[$i]->record_type]['Description'] = 'Service records';
						$record_array[$record_result[$i]->record_type]['Data'][] = $record_start . $separator . $record_result[$i]->record_priority . $separator . $record_result[$i]->record_weight . $separator . '"' . $record_result[$i]->record_value . '"' . $record_comment . "\n";
						break;
					case 'URL':
						if ($url_rr_web_servers = getOption('url_rr_web_servers', $_SESSION['user']['account_id'], $_SESSION['module'])) {
							$record_array[$record_result[$i]->record_type]['Description'] = 'URL redirects';
							foreach (explode(';', str_replace(',', ';', $url_rr_web_servers)) as $url_host) {
								$url_host = trim($url_host);
								if (verifyIPAddress($url_host)) {
									$url_rr_type = strpos($url_host, '.') ? 'A' : 'AAAA';
								} else {
									$url_rr_type = 'CNAME';
									$url_host = trimFullStop($url_host) . '.';
								}
								$record_array[$record_result[$i]->record_type]['Data'][] = str_replace('URL', $url_rr_type, $record_start) . $separator . $url_host . $record_comment . "\n";

							}
							$this->url_config_file .= $this->buildURLWebRedirects(trimFullStop($record_name), $record_result[$i]->record_value, $record_result[$i]->record_comment);
						}
						break;
					case 'CUSTOM':
						$domain_custom_rr_data = $record_result[$i]->record_value;
						break;
				}
			}
			
			ksort($record_array);
			
			/** Zone file output */
			foreach ($record_array as $rr => $rr_array) {
				/** Check if rr is supported by server_version */
				if (array_key_exists('Version', $rr_array) && version_compare($server_version, $rr_array['Version'], '<')) {
					$zone_file .= ";\n; BIND " . $rr_array['Version'] . ' or greater is required for ' . $rr . ' types.' . "\n;\n\n";
					continue;
				}
				
				$zone_file .= '; ' . $rr_array['Description'] . "\n";
				$zone_file .= implode('', $rr_array['Data']);
				$zone_file .= "\n";
			}
			unset($record_result);

			if (isset($domain_custom_rr_data)) {
				$zone_file .= sprintf("\n;\n; %s\n;\n%s\n", __('This section is added from the custom field.'), $domain_custom_rr_data);
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
			$parent_zone->domain_dnssec = $zone->domain_dnssec;
			$parent_zone->domain_dnssec_sig_expire = $zone->domain_dnssec_sig_expire;
			$parent_zone->domain_dnssec_sign_inline = $zone->domain_dnssec_sign_inline;
			$parent_zone->domain_check_config = $zone->domain_check_config;
			
			if ($zone->domain_view > -1) $parent_zone->domain_view = $zone->domain_view;

			/** Set domain_ttl correctly */
			if ($zone->domain_ttl) {
				$parent_zone->domain_ttl = $zone->domain_ttl;
			} elseif ($zone->domain_clone_domain_id && $parent_zone->domain_template_id) {
				basicGet("fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", $zone->domain_clone_domain_id, 'domain_', 'domain_id');
				if ($fmdb->last_result[0]->domain_ttl) {
					$parent_zone->domain_ttl = $fmdb->last_result[0]->domain_ttl;
				} else {
					basicGet("fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", $parent_zone->domain_template_id, 'domain_', 'domain_id');
					$parent_zone->domain_ttl = $fmdb->last_result[0]->domain_ttl;	
				}
			}
			
			return $parent_zone;
		}
		
		return false;
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
		
		/** Get only the version number */
		$server_version = preg_split('/\s+/', $server_version);
		
		if (version_compare($server_version[0], $required_version, '<')) {
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

		if (count((array) $built_domain_ids)) {
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
			
			/** Update domain_check_config */
			$query = "UPDATE `fm_" . $__FM_CONFIG['fmDNS']['prefix'] . "domains` SET `domain_check_config`='no' WHERE domain_id IN (" . implode(',', array_unique($built_domain_ids)) . ')';
			$fmdb->query($query);
		}
	}
	
	
	/**
	 * Processes the server config checks
	 *
	 * @since 2.2
	 * @package fmDNS
	 *
	 * @param array $raw_data Array containing named files and contents
	 * @param string|array $checks_to_run String or array of what checks to run
	 * @return string|void
	 */
	function processConfigsChecks($files_array, $checks_to_run = array('checkconf', 'checkzone')) {
		global $__FM_CONFIG;

		if (!is_array($checks_to_run)) $checks_to_run = array($checks_to_run);
		
		if (!array_key_exists('server_serial_no', $files_array)) return;
		if (getOption('enable_config_checks', $_SESSION['user']['account_id'], 'fmDNS') != 'yes') return;
		if ($this->server_info->server_type == 'url-only') return;
		
		$die = false;
		$message = null;
		$named_checkconf = findProgram('named-checkconf');
		$named_checkzone = findProgram('named-checkzone');
		
		$uname = php_uname('n');
		if (!$named_checkconf || !$named_checkzone) {
			return sprintf('<div id="config_check" class="info"><p>%s</p></div>', 
					sprintf(__('The named utilities (specifically named-checkconf and named-checkzone) cannot be found on %s. If they were installed, these configs and zones could be checked for syntax.'), $uname));
		}
		
		$fm_temp_directory = '/' . ltrim(getOption('fm_temp_directory'), '/');
		$tmp_dir = rtrim($fm_temp_directory, '/') . '/' . $_SESSION['module'] . '_' . date("YmdHis") . '/';
		system('rm -rf ' . $tmp_dir);
		
		/** Create temporary directory structure */
		foreach ($files_array['files'] as $file => $file_properties) {
			$contents = is_array($file_properties) ? $file_properties['contents'] : $file_properties;
			if (!is_dir(dirname($tmp_dir . $file))) {
				if (!@mkdir(dirname($tmp_dir . $file), 0777, true)) {
					$class = 'class="info"';
					$message = $this->getSyntaxCheckMessage('writeable', array('fm_temp_directory' => $fm_temp_directory));
					$die = true;
					break;
				}
			}
			file_put_contents($tmp_dir . $file, $contents);

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
							if (isset($files_array['files'][$tmp_zone_def_file[1]]['syntax_check']) && $files_array['files'][$tmp_zone_def_file[1]]['syntax_check'] == 'yes') {
								$zone_files[$view][$tmp_zone_def[1]] = $tmp_zone_def_file[1];
							}
						}
					}
				}
			}
		}
		
		/** Create temporary server root directory */
		if (!is_dir($tmp_dir . $files_array['server_root_dir'])) {
			@mkdir($tmp_dir . $files_array['server_root_dir'], 0777, true);
		}
		
		$retval = false;
		if (!$die) {
			/** Run named-checkconf */
			if (in_array('checkconf', $checks_to_run)) {
				$named_checkconf_cmd = findProgram('sudo') . ' -n ' . $named_checkconf . ' -t ' . $tmp_dir . ' ' . $files_array['server_config_file'] . ' 2>&1';
				exec($named_checkconf_cmd, $named_checkconf_results, $retval);
				/** Remove key-directory statements for config checks */
				foreach ($named_checkconf_results as $key => $val) {
					if (strpos($val, 'key-directory') !== false) {
						unset($named_checkconf_results[$key]);
					}
				}

				if ($retval || $named_checkconf_results) {
					$class = ($retval) ? 'class="error"' : 'class="info"';
					$named_checkconf_results = implode("\n", $named_checkconf_results);
					if (strpos($named_checkconf_results, 'sudo') !== false) {
						$class = 'class="info"';
						$message = $this->getSyntaxCheckMessage('sudo', array('checkconf_cmd' => $named_checkconf_cmd, 'checkconf_results' => $named_checkconf_results));
					} else {
						$message_type = (strpos($class, 'errors') !== false) ? 'errors' : 'warning';
						$message = $this->getSyntaxCheckMessage($message_type, array('checkconf_results' => $named_checkconf_results));
					}
					
				}
			}

			/** Run named-checkzone */
			if (in_array('checkzone', $checks_to_run)) {
				if (!$retval) {
					if (count($files_array['files']) == 1) {
						$tmp_zone_file_array = array_keys($files_array['files']);
						$zone_files['all'] = array(rtrim($tmp_zone_file_array[0], '.conf') => $tmp_zone_file_array[0]);
					}
					$named_checkzone_results = '';
					if (array($zone_files)) {
						foreach ($zone_files as $view => $zones) {
							foreach ($zones as $zone_name => $zone_file) {
								$named_checkzone_cmd = findProgram('sudo') . ' -n ' . $named_checkzone . ' -t ' . $tmp_dir . ' ' . $zone_name . ' ' . $zone_file . ' 2>&1';
								exec($named_checkzone_cmd, $results, $retval);
								if ($retval) {
									$class = 'class="error"';
									$named_checkzone_results .= implode("\n", $results);
									if (strpos($named_checkzone_results, 'sudo') !== false) {
										$class = 'class="info"';
										$message = $this->getSyntaxCheckMessage('sudo', array('checkconf_cmd' => $named_checkzone_cmd, 'checkconf_results' => $named_checkzone_results));
										break 2;
									}
								}
							}
						}
					}
					
					if ($named_checkzone_results) {
						if (empty($message)) $message = $this->getSyntaxCheckMessage('errors', array('checkconf_results' => $named_checkzone_results));
					} else {
						if (empty($message)) {
							$class = null;
							$message = $this->getSyntaxCheckMessage('loadable');
						}
					}
				}
			}
		}
		
		/** Remove temporary directory */
		system('rm -rf ' . $tmp_dir);
		
		return <<<HTML
			<div id="config_check" $class>
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
	 * @param string $server_id The ID of the server
	 * @param string $key_name The key name
	 * @param boolean $view Add extra tabs if this config is part of a view
	 * @return string
	 */
	function formatServerKeys($server_id, $key_name = null, $view = false) {
		global $fmdb, $__FM_CONFIG, $server_group_ids, $server_serial_no;
		
		$server_status = getNameFromID($server_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_id', 'server_status');
		if ($server_status == 'active') {
			$server_name = getNameFromID($server_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
			
			$extra_tab = ($view == true) ? "\t" : null;
			$server_ip = gethostbyname($server_name);
			/** Use server_address if the name does not resolve */
			if ($server_ip == $server_name) {
				$server_ip = getNameFromID($server_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_id', 'server_address');
			}
			$server = ($server_ip) ? $extra_tab . 'server ' . $server_ip . " {\n" : "server [cannot resolve " . $server_name . "] {\n";
			$config = ($key_name) ? "$extra_tab\tkeys { \"$key_name\"; };\n" : null;
			
			/** Get additional server options */
			basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_id', 'cfg_', 'AND cfg_type="global" AND cfg_name!="keys" AND cfg_data!="" AND server_id=' . $server_id . ' AND server_serial_no="0" AND cfg_status="active"');
			if ($fmdb->num_rows) {
				foreach ($fmdb->last_result as $config_result) {
					$global_config[$config_result->cfg_name] = array($config_result->cfg_data, $config_result->cfg_comment);
				}
			} else $global_config = array();

			$server_config = array();
			/** Override with group-specific configs */
			if (is_array($server_group_ids)) {
				basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_id', 'cfg_', 'AND cfg_type="global" AND cfg_name!="keys" AND cfg_data!="" AND server_id=' . $server_id . ' AND server_serial_no IN ("g_' . implode('","g_', $server_group_ids) . '") AND cfg_status="active"');
				if ($fmdb->num_rows) {
					foreach ($fmdb->last_result as $server_config_result) {
						$server_config[$server_config_result->cfg_name] = @array($server_config_result->cfg_data, $server_config_result->cfg_comment);
					}
				}
			}

			/** Override with server-specific configs */
			basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_id', 'cfg_', 'AND cfg_type="global" AND cfg_name!="keys" AND cfg_data!="" AND server_id=' . $server_id . ' AND server_serial_no="' . $server_serial_no . '" AND cfg_status="active"');
			if ($fmdb->num_rows) {
				foreach ($fmdb->last_result as $server_config_result) {
					$server_config[$server_config_result->cfg_name] = @array($server_config_result->cfg_data, $server_config_result->cfg_comment);
				}
			}

			/** Merge arrays */
			$config_array = array_merge($global_config, $server_config);
			unset($global_config, $server_config);

			foreach ($config_array as $cfg_name => $cfg_data) {
				list($cfg_info, $cfg_comment) = $cfg_data;

				$config .= $this->formatConfigOption($cfg_name, $cfg_info, $cfg_comment, $this->server_info, "\t$extra_tab");
			}
			
			if (!$config) {
				return null;
			}
			
			$config .= "$extra_tab};\n";
			
			return $server . $config;
		}

		return null;
	}


	/**
	 * Gets the hint zone
	 *
	 * @since 1.3
	 * @package fmDNS
	 *
	 * @return array
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
		
		/** Set proxy server settings if applicable */
		if (getOption('proxy_enable')) {
			$default_opts = array(
				'http' => array(
					'request_fulluri' => true,
					'method' => 'GET',
					'proxy' => 'tcp://' . getOption('proxy_host') . ':' . getOption('proxy_port')
				)
			);

			$proxyauth = getOption('proxy_user') . ':' . getOption('proxy_pass');
			if ($proxyauth != ':') {
				$default_opts['http']['header'] = 'Proxy-Authorization: Basic ' . base64_encode($proxyauth);
			}
			$default = stream_context_set_default($default_opts);
		}
		
		$remote_headers = get_headers($remote_hint_zone, 1);
		
		if (filemtime($local_hint_zone) < @strtotime($remote_headers['Last-Modified']) && !isset($GLOBALS['root_servers_updated'])) {
			$GLOBALS['root_servers_updated'] = true;
			
			/** Download the latest root servers (must be writeable by web server) */
			if (is_writeable($local_hint_zone)) {
				file_put_contents($local_hint_zone, fopen($remote_hint_zone, 'r'));
			} else {
				return sprintf('<div id="config_check" class="info"><p>%s</p><p>%s</p></div>',
						sprintf(__('The root servers have been recently updated, but the webserver user (%s) cannot write to %s to update the hint zone.'), $__FM_CONFIG['webserver']['user_info']['name'], $local_hint_zone),
						__('A local copy will be used instead.'));
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
	 * @param array $server_group_ids Server IDs of the server group
	 * @param array $auto_zone_options Automatically generated zone options
	 * @return string
	 */
	function getZoneOptions($domain_ids, $server_serial_no, $domain_type, $server_group_ids, $auto_zone_options = array()) {
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
		
		include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_options.php');
		$config = '';
		
		$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_name', 'cfg_', "AND cfg_type='global' AND domain_id IN ('" . join("','", $domain_ids) . "') AND server_serial_no='0' AND cfg_status='active'");
		if ($fmdb->num_rows) {
			$config_result = $fmdb->last_result;
			$global_config_count = $fmdb->num_rows;
			for ($i=0; $i < $global_config_count; $i++) {
				$global_config[$config_result[$i]->cfg_name] = array($config_result[$i]->cfg_data, $config_result[$i]->cfg_comment);
			}
			unset($config_result);
		} else $global_config = array();

		$server_config = array();
		/** Override with group-specific configs */
		if (is_array($server_group_ids)) {
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_id', 'cfg_', "AND cfg_type='global' AND domain_id IN ('" . join("','", $domain_ids) . "') AND server_serial_no IN ('g_" . implode("','g_", $server_group_ids) . "') AND cfg_status='active'");
			if ($fmdb->num_rows) {
				$server_config_result = $fmdb->last_result;
				$config_count = $fmdb->num_rows;
				for ($j=0; $j < $config_count; $j++) {
					$server_config[$server_config_result[$j]->cfg_name] = @array($server_config_result[$j]->cfg_data, $server_config_result[$j]->cfg_comment);
				}
				unset($server_config_result, $global_config_count);
			}
		}

		/** Override with server-specific configs */
		$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_name', 'cfg_', "AND cfg_type='global' AND domain_id IN ('" . join("','", $domain_ids) . "') AND server_serial_no='$server_serial_no' AND cfg_status='active'");
		if ($fmdb->num_rows) {
			$server_config_result = $fmdb->last_result;
			$global_config_count = $fmdb->num_rows;
			for ($j=0; $j < $global_config_count; $j++) {
				$server_config[$server_config_result[$j]->cfg_name] = @array($server_config_result[$j]->cfg_data, $config_result[$j]->cfg_comment);
			}
			unset($server_config_result);
		}

		/** Merge arrays */
		$config_array = array_merge($global_config, $server_config);
		unset($global_config, $server_config);

		if (is_array($auto_zone_options) && count($auto_zone_options)) {
			foreach ($auto_zone_options as $cfg_name => $cfg_data) {
				if (array_key_exists($cfg_name, $config_array)) {
					if (is_array($cfg_data)) {
						$cfg_data = array(str_replace(',}', '', $config_array[$cfg_name][0]) . ',' . $cfg_data[0], $config_array[$cfg_name][1]);
						if (strpos($config_array[$cfg_name][0], '{') !== false) {
							$cfg_data[0] .= ',}';
						}
						if (count($cfg_data) < 2) {
							$cfg_data[] = '';
						}
					}
					$auto_zone_options[$cfg_name] = $cfg_data;
				}
			}
			$config_array = array_merge($config_array, $auto_zone_options);
		}
		
		foreach ($config_array as $cfg_name => $cfg_data) {
			if ($cfg_name == 'include') continue;
			list($cfg_info, $cfg_comment) = $cfg_data;

			$config .= $this->formatConfigOption($cfg_name, $cfg_info, $cfg_comment, $this->server_info, "\t", " AND def_zone_support LIKE '%" . strtoupper(substr($domain_type, 0, 1)) . "%'");
		}

		/** Build includes */
		$config .= $this->getIncludeFiles(0, $server_serial_no, $server_group_ids, $domain_ids);

		return $config;
	}
	
	/**
	 * Formats the server rate-limit statements
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
		
		/** Check if rrl is supported by server_version */
		list($server_version) = explode('-', getNameFromID($server_serial_no, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_version'));
		$unsupported_version = $this->versionCompatCheck('Response Rate Limiting', '9.9.4', $server_version);
		
		$ratelimits = $ratelimits_domains = '';
		$rate_config_array = array();
		
		basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', array('domain_id', 'server_serial_no', 'cfg_name'), 'cfg_', 'AND cfg_type="ratelimit" AND view_id=' . $view_id . ' AND server_serial_no="0" AND cfg_status="active"');
		if ($fmdb->num_rows) {
			if ($unsupported_version) return $unsupported_version;
			$rate_result = $fmdb->last_result;
			$global_rate_count = $fmdb->num_rows;
			for ($i=0; $i < $global_rate_count; $i++) {
				if ($rate_result[$i]->domain_id) {
					$rate_config_array['domain'][displayFriendlyDomainName(getNameFromID($rate_result[$i]->domain_id, "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domains", 'domain_', 'domain_id', 'domain_name', null, 'active'))][$rate_result[$i]->cfg_name][] = array($rate_result[$i]->cfg_data, $rate_result[$i]->cfg_comment);
				} else {
					$rate_config_array[$rate_result[$i]->cfg_name][] = array($rate_result[$i]->cfg_data, $rate_result[$i]->cfg_comment);
				}
			}
			unset($rate_result);
		}
		
		/** Override with server-specific configs */
		basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', array('domain_id', 'server_serial_no', 'cfg_name'), 'cfg_', 'AND cfg_type="ratelimit" AND view_id=' . $view_id . ' AND server_serial_no=' . $server_serial_no . ' AND cfg_status="active"');
		if ($fmdb->num_rows) {
			if ($unsupported_version) return $unsupported_version;
			$server_config_result = $fmdb->last_result;
			$global_config_count = $fmdb->num_rows;
			for ($i=0; $i < $global_config_count; $i++) {
				if ($server_config_result[$i]->domain_id) {
					$server_config['domain'][displayFriendlyDomainName(getNameFromID($server_config_result[$i]->domain_id, "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domains", 'domain_', 'domain_id', 'domain_name', null, 'active'))][$server_config_result[$i]->cfg_name][] = array($server_config_result[$i]->cfg_data, $server_config_result[$i]->cfg_comment);
				} else {
					$server_config[$server_config_result[$i]->cfg_name][] = array($server_config_result[$i]->cfg_data, $server_config_result[$i]->cfg_comment);
				}
			}
			unset($server_config_result);
		} else $server_config = array();

		/** Merge arrays */
		$rate_config_array = array_merge((array)$rate_config_array, $server_config);
		unset($server_config);
		
		foreach ($rate_config_array as $cfg_name => $value_array) {
			foreach ($value_array as $domain_name => $cfg_data) {
				if ($cfg_name != 'domain') {
					list($cfg_info, $cfg_comment) = $cfg_data;
					$ratelimits .= $this->formatConfigOption($cfg_name, $cfg_info, $cfg_comment, $this->server_info);
				} else {
					foreach ($cfg_data as $domain_cfg_name => $domain_cfg_data) {
						$ratelimits_domains .= "\trate-limit {\n\t\tdomain $domain_name;\n";
						foreach ($domain_cfg_data as $domain_cfg_data2) {
							list($cfg_param, $cfg_comment) = $domain_cfg_data2;
							$ratelimits_domains .= $this->formatConfigOption($domain_cfg_name, $cfg_param, $cfg_comment, $this->server_info);
						}
						$ratelimits_domains .= "\t};\n";
					}
				}
			}
		}
		if ($ratelimits) {
			$ratelimits = "\trate-limit {\n{$ratelimits}\t};\n";
		}
		return ($ratelimits || $ratelimits_domains) ? $ratelimits . $ratelimits_domains : null;
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
	 * @param string $server_root_dir Server root directory
	 * @param string $tab How the tab should look
	 * @param string $sql Additional SQL statement
	 * @return string|void
	 */
	function formatConfigOption($cfg_name, $cfg_info, $cfg_comment = null, $server_info = null, $tab = "\t\t", $sql = null) {
		global $fmdb, $__FM_CONFIG, $fm_module_options;
		
		$config = '';
		
		$query = "SELECT def_multiple_values,def_minimum_version FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}functions WHERE def_option = '{$cfg_name}' $sql";
		$fmdb->get_results($query);
		if (!$fmdb->num_rows) {
			return;
		} else {
			$def_multiple_values = $fmdb->last_result[0]->def_multiple_values;
			$def_minimum_version = (isset($fmdb->last_result[0]->def_minimum_version)) ? $fmdb->last_result[0]->def_minimum_version : 0;
		}

		// Ensure minimum version is achieved
		if ($server_info && $server_info->server_version) {
			if (version_compare($server_info->server_version, $def_minimum_version, '<')) {
				return $tab . sprintf(__('// BIND %s or greater is required for %s'), $def_minimum_version, $cfg_name) . "\n";
			}
		}
		
		if ($cfg_comment) {
			$comment = wordwrap($cfg_comment, 50, "\n");
			$config .= "\n$tab// " . str_replace("\n", "\n$tab// ", $comment) . "\n";
			unset($comment);
		}
		$config .= $tab . $cfg_name . ' ';
		if ($def_multiple_values == 'yes' && strpos($cfg_info, '{') === false) $config .= '{ ';

		/** Parse address_match_element configs */
		if (!isset($fm_module_options)) include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_options.php');
		$cfg_info = $fm_module_options->parseDefType($cfg_name, $cfg_info);
		
		if ($server_info) {
			$config .= str_replace(array('$ROOT', '$ZONES'), array($server_info->server_root_dir, $server_info->server_zones_dir), trim(rtrim(trim($cfg_info), ';')));
		} else {
			$config .= trim(rtrim(trim($cfg_info), ';'));
		}
		if ($def_multiple_values == 'yes' && strpos($cfg_info, '}') === false) {
			$config .= $cfg_info ? '; }' : ' }';
		}
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
	 * @param object $zone_array The zone data
	 * @param integer $server_id The server id to check
	 * @param integer $view_id The view id
	 * @return array
	 */
	function processServerGroups($zone_array, $server_id, $view_id) {
		global $fmdb, $__FM_CONFIG;
		
		$zone_options = '';
		$auto_also_notify = array();

		extract(get_object_vars($zone_array), EXTR_OVERWRITE);

		/** Is this a clone? Then get clone name servers */
		if (isset($parent_domain_id) && $parent_domain_id) {
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $parent_domain_id, 'domain_', 'domain_id');
			if ($fmdb->num_rows) {
				extract(get_object_vars($fmdb->last_result[0]), EXTR_OVERWRITE);
			}
		}

		$domain_name_servers = explode(';', $domain_name_servers);
		if (!count($domain_name_servers) || in_array('0', $domain_name_servers) || 
				$domain_type != 'primary' || in_array('s_' . $server_id, $domain_name_servers)) {
			return array($domain_type, '');
		}
		
		foreach ($domain_name_servers as $ids) {
			if ($ids == '0' || strpos($ids, 's_') !== false) continue;
			
			if (strpos($ids, 'g_') !== false) {
				basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'server_groups', preg_replace('/\D/', '', $ids), 'group_', 'group_id');
				if ($fmdb->num_rows) {
					extract(get_object_vars($fmdb->last_result[0]));
					
					$group_masters = explode(';', $group_masters);
					$group_slaves = explode(';', $group_slaves);
					
					if (in_array($server_id, $group_masters)) {
						if ($group_auto_also_notify == 'yes') {
							$auto_also_notify = array_merge($auto_also_notify, $this->resolveServerGroupMembers($group_slaves));
						}
					}
					
					if (in_array($server_id, $group_slaves)) {
						$tmp_key_id = "";
						if ($domain_key_id) {
							$tmp_key_id = $domain_key_id;
						}
						if ($view_id && !$domain_key_id) {
							$tmp_key_id = getNameFromID($view_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'views', 'view_', 'view_id', 'view_key_id');
						}

						return array('secondary', array('primaries' => $this->resolveServerGroupMembers($group_masters, $tmp_key_id)));
					}
				}
			}
		}
		
		if (count($auto_also_notify)) {
			$zone_options = array('also-notify' => $auto_also_notify);
		}
		return array($domain_type, $zone_options);
	}
	
	/**
	 * Attempts to resolve the member servers for the group
	 *
	 * @since 2.0
	 * @package fmDNS
	 *
	 * @param array $servers The array of group members
	 * @param integer $key_id The zone transfer key id
	 * @return array|null
	 */
	function resolveServerGroupMembers($servers, $key_id = null) {
		global $__FM_CONFIG, $fmdb;
		
		if (!count($servers)) return null;
		
		foreach ($servers as $server_id) {
			$server_name = getNameFromID($server_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
			$server_ip = gethostbyname($server_name);
			$key = '';
			if ($key_id) {
				$key = sprintf(' key %s', getNameFromID($key_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'keys', 'key_', 'key_id', 'key_name'));
			}

			/** Use server_address if the name does not resolve */
			if ($server_ip == $server_name) {
				$server_ip = getNameFromID($server_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_id', 'server_address');
			}
			$server_ips[] = ($server_ip && $server_ip != $server_name) ? $server_ip . $key : sprintf(__('Cannot resolve %s'), $server_name) . $key;
		}
		
		return array(implode(',', (array) $server_ips), '');
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
		$temp = explode("\n", wordwrap($text, $limit, "\n", true));
		for ($i=0; $i<count($temp)-1; $i++) {
			if (strlen($temp[$i]) < $limit) {
				$temp[$i] .= ' ';
			}
		}
		
		return $temp;
	}
	
	/**
	 * Attempts to resolve the master servers for the group
	 *
	 * @since 2.1
	 * @package fmDNS
	 *
	 * @param integer $view_id The view_id of the zone
	 * @param integer $server_serial_no The server serial number for overrides
	 * @param array   $server_group_ids The array containing server group IDs for overrides
	 * @param string  $server_root_dir Server root directory
	 * @param integer|array $domain_id The ID of the zone
	 * @param string  $clause Whether includes are inside or outside of clauses
	 * @return string
	 */
	function getIncludeFiles($view_id, $server_serial_no, $server_group_ids = null, $domain_id = 0, $clause = 'inside') {
		global $fmdb, $__FM_CONFIG;
		
		if (is_array($server_group_ids)) {
			$server_group_ids = 'g_' . implode('","g_', $server_group_ids);
		}
		$domain_id_sql = is_array($domain_id) ? "domain_id IN ('" . join("','", $domain_id) . "')" : 'domain_id=' . $domain_id;
		if ($clause == 'inside') {
			$clause_sql = ' AND cfg_in_clause="yes"';
			$tab = "\t";
		} else {
			$clause_sql = ' AND cfg_in_clause="no"';
			$tab = null;
		}
		basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_id', 'cfg_', 'AND cfg_type="global" AND cfg_name="include"' . $clause_sql . ' AND view_id=' . $view_id . ' AND ' . $domain_id_sql . ' AND server_serial_no IN ("0", "' . $server_serial_no . '", "' . $server_group_ids . '") AND cfg_status="active"');
		if ($fmdb->num_rows) {
			$config_result = $fmdb->last_result;
			for ($i=0; $i < $fmdb->num_rows; $i++) {
				$include_config['include'][] = array($config_result[$i]->cfg_data, $config_result[$i]->cfg_comment);
			}
			unset($config_result);
		} else $include_config = null;

		if (is_array($include_config)) {
			$include_files = null;
			foreach ($include_config as $cfg_name => $value_array) {
				foreach ($value_array as $cfg_data) {
					list($cfg_info, $cfg_comment) = $cfg_data;
					$include_files .= $this->formatConfigOption($cfg_name, $cfg_info, $cfg_comment, $this->server_info, $tab);
				}
			}
			return str_replace(array('$ROOT', '$ZONES'), array(getNameFromID($server_serial_no, "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}servers", 'server_', 'server_serial_no', 'server_root_dir', null, 'active'), getNameFromID($server_serial_no, "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}servers", 'server_', 'server_serial_no', 'server_zones_dir', null, 'active')), $include_files);
		} else {
			return '';
		}
	}
	
	/**
	 * Updates tables to reset flags
	 *
	 * @since 2.2
	 * @package fmDNS
	 *
	 * @param integer $server_serial_no Server serial number
	 * @param array $post_data Array containing data sent by the client
	 * @return null
	 */
	function moduleUpdateReloadFlags($server_serial_no, $post_data) {
		global $fmdb, $__FM_CONFIG;
		
		extract($post_data);
		
		if (isset($built_domain_ids)) {
			$this->setBuiltDomainIDs($server_serial_no, $built_domain_ids);
		}
		if (isset($reload_domain_ids)) {
			$query = "DELETE FROM `fm_" . $__FM_CONFIG['fmDNS']['prefix'] . "track_reloads` WHERE `server_serial_no`='" . sanitize($server_serial_no) . "' AND domain_id IN (" . implode(',', array_unique($reload_domain_ids)) . ')';
			$fmdb->query($query);
			
			/** Update domain_check_config */
			$query = "UPDATE `fm_" . $__FM_CONFIG['fmDNS']['prefix'] . "domains` SET `domain_check_config`='no' WHERE domain_id IN (" . implode(',', array_unique($reload_domain_ids)) . ')';
			$fmdb->query($query);
		}
	}
	
	
	/**
	 * Converts a SOA value to seconds
	 *
	 * @since 3.0
	 * @package fmDNS
	 *
	 * @param string $soa SOA value
	 * @return integer|string
	 */
	function getSOASeconds($soa) {
		if (!preg_match('/\d[a-zA-Z]/', $soa)) {
			return $soa;
		}
		
		$search = array('S', 'M', 'H', 'D', 'W');
		$replace = array(' seconds ', ' minutes ', ' hours ', ' days ', ' weeks ');
		
		return strtotime('+' . str_replace($search, $replace, strtoupper($soa))) - time();
	}
	
	
	/**
	 * Formats the server rrset-order statements
	 *
	 * @since 2.0
	 * @package fmDNS
	 *
	 * @param integer $view_id The view_id of the zone
	 * @param integer $server_serial_no The server serial number for overrides
	 * @return string
	 */
	function getRRSetOrder($view_id, $server_serial_no) {
		global $fmdb, $__FM_CONFIG;
		
		$rrsets = '';
		$config = array();
		
		/** Use server-specific configs if present */
		foreach (array($server_serial_no, 0) as $serial_no) {
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', array('domain_id', 'server_serial_no', 'cfg_name'), 'cfg_', 'AND cfg_type="rrset" AND view_id=' . $view_id . ' AND server_serial_no=' . $serial_no . ' AND cfg_status="active"');
			if ($fmdb->num_rows) {
				$result = $fmdb->last_result;
				$count = $fmdb->num_rows;
				for ($i=0; $i < $count; $i++) {
					$config[$result[$i]->cfg_name][] = array($result[$i]->cfg_data, $result[$i]->cfg_comment);
				}
				unset($result);
				break;
			}
		}
		
		foreach ((array) $config as $cfg_name => $value_array) {
			foreach ($value_array as $cfg_data) {
				list($cfg_info, $cfg_comment) = $cfg_data;
				$rrsets .= $this->formatConfigOption($cfg_name, $cfg_info, $cfg_comment, $this->server_info);
			}
			$rrsets = str_replace($cfg_name, '', $rrsets);
		}
		return ($rrsets) ? "\trrset-order {\n{$rrsets}\t};\n" : null;
	}
	
	
	/**
	 * Signs the DNSSEC zone file
	 *
	 * @since 3.0
	 * @package fmDNS
	 *
	 * @param object $domain The domain result
	 * @param string $zone_file_contents Contents of zone file to sign
	 * @return string
	 */
	function dnssecSignZone($domain, $zone_file_contents) {
		global $fmdb, $__FM_CONFIG;
		
		/** Locate dnssec binaries */
		if (!$dnssec_signzone = findProgram('dnssec-signzone')) {
			exit(displayResponseClose(sprintf(__('The dnssec-signzone binary could not be found on %s so DNSSEC zone signing cannot be done.'), php_uname('n'))));
		}
		
		/** Create temp directory */
		list($tmp_dir, $created) = createTempDir($_SESSION['module'], 'datetime');
		if ($created === false) exit(displayResponseClose(sprintf(__('%s is not writeable by %s so DNSSEC zone signing cannot be done.'), $tmp_dir, $__FM_CONFIG['webserver']['user_info']['name'])));

		/** Create temp files */
		$temp_zone_file = $tmp_dir . 'db.' . $domain->domain_name . '.hosts';
		file_put_contents($temp_zone_file, $zone_file_contents);
		
		/** Get associated DNSSEC keys */
		basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', array('key_signing', 'key_created'), 'key_', 'AND key_type="dnssec" AND domain_id=' . $domain->parent_domain_id . ' AND key_status IN ("active","revoked")', null, false, 'DESC');
		if (!$fmdb->sql_errors && $fmdb->num_rows) {
			$dnssec_keys = $fmdb->last_result;
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				if ($dnssec_keys[$i]->key_signing == 'yes') {
					$dnssec_key_signing_array[$dnssec_keys[$i]->key_subtype][] = array($dnssec_keys[$i]->key_name, $dnssec_keys[$i]->key_secret);
				}
				file_put_contents($tmp_dir . $dnssec_keys[$i]->key_name . '.private', $dnssec_keys[$i]->key_secret);
				file_put_contents($tmp_dir . $dnssec_keys[$i]->key_name . '.key', $dnssec_keys[$i]->key_public);
				file_put_contents($temp_zone_file, $dnssec_keys[$i]->key_public, FILE_APPEND);
			}
		} else {
			return $zone_file_contents;
		}
		
		$dnssec_endtime = getDNSSECExpiration($domain, 'endtime');
		
		foreach ($dnssec_key_signing_array['KSK'] as $ksk_array) {
			$dnssec_ksk[] = '-k ' . $ksk_array[0];
		}
		$dnssec_ksk = join(' ', $dnssec_ksk);
		
		/** Sign zone with all keys */
		$dnssec_output = shell_exec('cd ' . escapeshellarg($tmp_dir) . ' && ' . $dnssec_signzone . ' -g -K ' . escapeshellarg($tmp_dir) . ' -o ' . escapeshellarg($domain->domain_name) . ' ' . $dnssec_ksk . ' -f ' . escapeshellarg($temp_zone_file) . '.signed -e ' . $dnssec_endtime . ' ' . escapeshellarg($temp_zone_file) . ' ' . escapeshellarg($dnssec_key_signing_array['ZSK'][0][0]) . ' 2>&1');
		if (file_exists($temp_zone_file . '.signed')) {
			$signed_zone = file_get_contents($temp_zone_file . '.signed');
			$GLOBALS[$_SESSION['module']]['DNSSEC'][] = array('domain_id' => $domain->parent_domain_id, 'domain_dnssec_signed' => strtotime('now'));
		}
		
		/** Generated DS RR */
		if ($domain->domain_dnssec_generate_ds) {
			if (file_exists($tmp_dir . 'dsset-' . $domain->domain_name . '.')) {
				$generated_ds_rr = file_get_contents($tmp_dir . 'dsset-' . $domain->domain_name . '.');
				basicUpdate('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $domain->parent_domain_id, 'domain_dnssec_ds_rr', $generated_ds_rr, 'domain_id');
			}
		}
		
		system('rm -rf ' . $tmp_dir);
		
		return $signed_zone ? $signed_zone : $dnssec_output;
	}
	
	
	/**
	 * Builds the web server redirects
	 *
	 * @since 4.0
	 * @package fmDNS
	 *
	 * @param string $hostname The hostname to redirect
	 * @param string $url The URL to redirect the hostnme to
	 * @param string $comment Redirect record comment
	 * @return string
	 */
	function buildURLWebRedirects($hostname, $url, $comment = null) {
		global $fmdb, $__FM_CONFIG;

		$config = null;

		if ($comment) {
			$comment = "# $comment\n";
		}

		switch ($this->server_info->server_url_server_type) {
			case 'httpd':
				$config = '
%sRewriteCond "%%{HTTP_HOST}" "^%s" [NC]
RewriteRule "^/?(.*)"      "%s" [L,R,LE]
';
				break;
			case 'lighttpd':
				$config = '
%s$HTTP["host"] =~ "%s" {
  url.redirect  = (
    "^/(.*)" => "%s",
  )
}
';
				break;
			case 'nginx':
				$config = '
';
				break;
			default:
				return null;
		}

		$config = sprintf($config, $comment, str_replace('*', '(^.*|\.)', str_replace('.', '\.', $hostname)), $url);

		// return $config;
		return (strpos($this->url_config_file, $config) === false) ? $config : null;
	}
		
	/**
	 * Formats the RPZ statements
	 *
	 * @since 4.0
	 * @package fmDNS
	 *
	 * @param integer $view_id The view_id of the zone
	 * @param integer $server_serial_no The server serial number for overrides
	 * @param array $server_group_ids Array containing group server IDs
	 * @return string
	 */
	function getRPZ($view_id, $server_serial_no, $server_group_ids) {
		global $fmdb, $__FM_CONFIG, $fm_dns_zones;
		
		/** Check if rpz is supported by server_version */
		list($server_version) = explode('-', getNameFromID($server_serial_no, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_version'));
		$unsupported_version = $this->versionCompatCheck('Response Policy Zones', '9.10.0', $server_version);
		
		$global_rpz_config = $domain_rpz_config = '';
		$config_array = array();
		
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', array('cfg_order_id'), 'cfg_', 'AND cfg_type="rpz" AND cfg_isparent="yes" AND view_id=' . $view_id . ' AND server_serial_no="0" AND cfg_status="active"');
		if ($fmdb->num_rows) {
			if ($unsupported_version) return $unsupported_version;
			$result = $fmdb->last_result;
			$global_config_count = $fmdb->num_rows;

			if (!$fm_dns_zones) {
				include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_zones.php');
			}

			for ($i=0; $i < $global_config_count; $i++) {
				$domain_ids = $result[$i]->domain_id;
				if (preg_match('/^g_\d+/', $domain_ids) == true) {
					$domain_ids = $fm_dns_zones->getZoneGroupMembers(str_replace('g_', '', $domain_ids));
				}
				if (!is_array($domain_ids)) {
					$domain_ids = array($domain_ids);
				}

				foreach ($domain_ids as $domain_id) {
					$domain = displayFriendlyDomainName(getNameFromID($domain_id, "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domains", 'domain_', 'domain_id', 'domain_name', null, 'active'));

					basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', array('cfg_name'), 'cfg_', 'AND cfg_type="rpz" AND cfg_parent="' . $result[$i]->cfg_id . '" AND cfg_isparent="no" AND server_serial_no="0"');
					foreach ($fmdb->last_result as $record) {
						if ($record->cfg_data) {
							$config_array['domain'][$domain][$record->cfg_name] = $record->cfg_data;
						}
					}
				}
			}
			unset($result);
		}
		
		$server_config = array();
		/** Override with group-specific configs */
		if (is_array($server_group_ids)) {
			basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_order_id', 'cfg_', 'AND cfg_type="rpz" AND cfg_isparent="yes" AND view_id=' . $view_id . ' AND server_serial_no IN ("g_' . implode('","g_', $server_group_ids) . '") AND cfg_status="active"');
			if ($fmdb->num_rows) {
				if ($unsupported_version) return $unsupported_version;
				$server_config_result = $fmdb->last_result;
				$global_config_count = $fmdb->num_rows;
				for ($i=0; $i < $global_config_count; $i++) {
					$domain_ids = $server_config_result[$i]->domain_id;
					if (preg_match('/^g_\d+/', $domain_ids) == true) {
						$domain_ids = $fm_dns_zones->getZoneGroupMembers(str_replace('g_', '', $domain_ids));
					}
					if (!is_array($domain_ids)) {
						$domain_ids = array($domain_ids);
					}
	
					foreach ($domain_ids as $domain_id) {
						$domain = displayFriendlyDomainName(getNameFromID($domain_id, "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domains", 'domain_', 'domain_id', 'domain_name', null, 'active'));
	
						basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', array('cfg_name'), 'cfg_', 'AND cfg_type="rpz" AND cfg_parent="' . $server_config_result[$i]->cfg_id . '" AND cfg_isparent="no" AND server_serial_no="' . $server_config_result[$i]->server_serial_no . '"');
						foreach ($fmdb->last_result as $record) {
							$server_config['domain'][$domain][$record->cfg_name] = $record->cfg_data;
						}
					}
				}
				unset($server_config_result);
			}
		}

		/** Override with server-specific configs */
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', array('cfg_order_id'), 'cfg_', 'AND cfg_type="rpz" AND cfg_isparent="yes" AND view_id=' . $view_id . ' AND server_serial_no=' . $server_serial_no . ' AND cfg_status="active"');
		if ($fmdb->num_rows) {
			if ($unsupported_version) return $unsupported_version;
			$server_config_result = $fmdb->last_result;
			$global_config_count = $fmdb->num_rows;
			for ($i=0; $i < $global_config_count; $i++) {
				$domain_ids = $server_config_result[$i]->domain_id;
				if (preg_match('/^g_\d+/', $domain_ids) == true) {
					$domain_ids = $fm_dns_zones->getZoneGroupMembers(str_replace('g_', '', $domain_ids));
				}
				if (!is_array($domain_ids)) {
					$domain_ids = array($domain_ids);
				}

				foreach ($domain_ids as $domain_id) {
					$domain = displayFriendlyDomainName(getNameFromID($domain_id, "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domains", 'domain_', 'domain_id', 'domain_name', null, 'active'));

					basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', array('cfg_name'), 'cfg_', 'AND cfg_type="rpz" AND cfg_parent="' . $server_config_result[$i]->cfg_id . '" AND cfg_isparent="no" AND server_serial_no="' . $server_config_result[$i]->server_serial_no . '"');
					foreach ($fmdb->last_result as $record) {
						$server_config['domain'][$domain][$record->cfg_name] = $record->cfg_data;
					}
				}
			}
			unset($server_config_result);
		}

		/** Merge arrays */
		$config_array = array_replace_recursive((array)$config_array, $server_config);
		unset($server_config);
		
		foreach ($config_array as $cfg_name => $value_array) {
			foreach ($value_array as $domain_name => $cfg_data) {
				if (!$domain_name) {
					foreach ($cfg_data as $global_cfg_name => $global_cfg_data) {
						if ($global_cfg_data) {
							$global_rpz_config .= "$global_cfg_name $global_cfg_data ";
						}
					}
				} else {
					$domain_rpz_config .= "\n\t\tzone \"$domain_name\" ";
					foreach ($cfg_data as $domain_cfg_name => $domain_cfg_data) {
						if ($domain_cfg_data) {
							$domain_rpz_config .= "$domain_cfg_name $domain_cfg_data ";
						}
					}
					$domain_rpz_config .= "; ";
				}
			}
		}
		if ($domain_rpz_config) {
			$domain_rpz_config = "$domain_rpz_config\n\t";
		}
		return ($global_rpz_config || $domain_rpz_config) ? "\tresponse-policy {" . $domain_rpz_config . trim('} ' . $global_rpz_config) . ";\n" : null;
	}
	
	
	/**
	 * Gets the key-directory
	 *
	 * @since 4.0
	 * @package fmDNS
	 *
	 * @param integer $domain_id The domain_id to get key directory for
	 * @return string
	 */
	function getKeyDirectory($domain_id, $view_id, $server_serial_no, $server_id) {
		global $fmdb, $__FM_CONFIG, $fm_module_servers;

		/** Get associated server group IDs */
		$server_group_ids = $fm_module_servers->getServerGroupIDs($server_id);
		$server_group_ids_sql = (is_array($server_group_ids)) ? ", 'g_" . implode("', 'g_", $server_group_ids) . "'" : null;
		
		$query = "SELECT * FROM `fm_dns_config` WHERE `cfg_name` = 'key-directory' AND cfg_type='global' AND account_id=1 AND cfg_status='active'
			AND domain_id IN (0, $domain_id) AND view_id IN (0, $view_id) AND server_serial_no IN ('0', '$server_serial_no' $server_group_ids_sql)
			ORDER BY domain_id DESC, view_id DESC, (server_serial_no > 0) DESC, server_serial_no DESC LIMIT 1";
		$fmdb->query($query);

		if ($fmdb->num_rows) {
			return str_replace(array('"', "'"), '', $fmdb->last_result[0]->cfg_data);
		}

		return null;
	}


	/**
	 * Checks if the server version is compatible with the feature
	 *
	 * @since 4.0.1
	 * @package fmDNS
	 *
	 * @param string $feature The feature to check
	 * @param string $required_version The minimum required version
	 * @param string $server_version Installed server version
	 * @param string $tab Tab spacing to use
	 * @return string
	 */
	private function versionCompatCheck($feature, $required_version, $server_version, $tab = "\t") {
		if (version_compare($server_version, $required_version, '<')) {
			return sprintf("%1\$s//\n%1\$s// BIND %2\$s or greater is required for %3\$s.\n%1\$s//\n\n", $tab, $required_version, $feature);
		}

		return null;
	}


	/**
	 * Formats the server http endpoint statements
	 *
	 * @since 6.0
	 * @package fmDNS
	 *
	 * @param string $type http, tls, or dnssec-policy
	 * @param integer $view_id The view_id of the zone
	 * @param object $server_info The server info object
	 * @param array $server_group_ids Array containing group server IDs
	 * @return string
	 */
	function getStandardBlocks($type, $view_id, $server_info, $server_group_ids) {
		global $fmdb, $__FM_CONFIG;

		$display_type = $type;

		/** Minimum version */
		$minimum_version = getMinimumFeatureVersion($type, '', 'version');

		switch ($type) {
			case 'http':
				$friendly_name = 'HTTP Endpoints';
				break;
			case 'tls':
				$friendly_name = 'TLS Connections';
				break;
			case 'dnssec-policy':
				$friendly_name = 'DNSSEC Policies';
				break;
		}
		
		/** Check if type is supported by server_version */
		list($server_version) = explode('-', $server_info->server_version);
		$unsupported_version = $this->versionCompatCheck($friendly_name, $minimum_version, $server_version, '');
		
		$global_config = '';
		$config_array = array();
		
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', array('cfg_order_id'), 'cfg_', 'AND cfg_type="' . $type . '" AND cfg_name="!config_name!" AND cfg_isparent="yes" AND view_id=' . $view_id . ' AND server_serial_no="0" AND cfg_status="active"');
		if ($fmdb->num_rows) {
			if ($unsupported_version) return $unsupported_version;
			$result = $fmdb->last_result;
			$global_config_count = $fmdb->num_rows;
			for ($i=0; $i < $global_config_count; $i++) {
				basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', array('cfg_name'), 'cfg_', 'AND cfg_type="' . $type . '" AND cfg_parent="' . $result[$i]->cfg_id . '" AND cfg_isparent="no" AND server_serial_no="0"');
				foreach ($fmdb->last_result as $record) {
					if ($record->cfg_data) {
						$config_array[$result[$i]->cfg_data][$record->cfg_name] = $record->cfg_data;
					}
				}
		
			}
			unset($result);
		}
		
		$server_config = array();
		/** Override with group-specific configs */
		if (is_array($server_group_ids)) {
			basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_order_id', 'cfg_', 'AND cfg_type="' . $type . '" AND cfg_name="!config_name!" AND cfg_isparent="yes" AND view_id=' . $view_id . ' AND server_serial_no IN ("g_' . implode('","g_', $server_group_ids) . '") AND cfg_status="active"');
			if ($fmdb->num_rows) {
				if ($unsupported_version) return $unsupported_version;
				$server_config_result = $fmdb->last_result;
				$global_config_count = $fmdb->num_rows;
				for ($i=0; $i < $global_config_count; $i++) {
					basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', array('cfg_name'), 'cfg_', 'AND cfg_type="' . $type . '" AND cfg_parent="' . $server_config_result[$i]->cfg_id . '" AND cfg_isparent="no" AND server_serial_no="' . $server_config_result[$i]->server_serial_no . '"');
					foreach ($fmdb->last_result as $record) {
						$server_config[$server_config_result[$i]->cfg_data][$record->cfg_name] = $record->cfg_data;
					}
				}
				unset($server_config_result);
			}
		}

		/** Override with server-specific configs */
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', array('cfg_order_id'), 'cfg_', 'AND cfg_type="' . $type . '" AND cfg_name="!config_name!" AND cfg_isparent="yes" AND view_id=' . $view_id . ' AND server_serial_no=' . $server_info->server_serial_no . ' AND cfg_status="active"');
		if ($fmdb->num_rows) {
			if ($unsupported_version) return $unsupported_version;
			$server_config_result = $fmdb->last_result;
			$global_config_count = $fmdb->num_rows;
			for ($i=0; $i < $global_config_count; $i++) {
				basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', array('cfg_name'), 'cfg_', 'AND cfg_type="' . $type . '" AND cfg_parent="' . $server_config_result[$i]->cfg_id . '" AND cfg_isparent="no"');
				foreach ($fmdb->last_result as $record) {
					$server_config[$server_config_result[$i]->cfg_data][$record->cfg_name] = $record->cfg_data;
				}
			}
			unset($server_config_result);
		}

		/** Merge arrays */
		$config_array = array_replace_recursive((array)$config_array, $server_config);
		unset($server_config);
		
		foreach ($config_array as $element_name => $value_array) {
			$global_config .= sprintf("%s %s {\n", $display_type, $element_name);
			foreach ($value_array as $param => $value) {
				if ($value) $global_config .= $this->formatConfigOption($param, $value, null, $server_info, "\t", "AND def_function='{$type}'");
			}
			$global_config .= "};\n";
		}
		return ($global_config) ? $global_config . "\n" : null;
	}
	
	/**
	 * Formats the server http endpoint statements
	 *
	 * @since 6.2
	 * @package fmDNS
	 *
	 * @param string $record_value String to parse
	 * @param object $domain Current processing domain
	 * @return string
	 */
	private function swapVars4Values($record_value, $domain) {
		global $__FM_CONFIG;

		$record_value = str_replace(array('{domain}'), array($domain->domain_name), $record_value);

		preg_match_all('/{(\w+):(\d+)}/', $record_value, $matches, PREG_SET_ORDER);
		if (count($matches)) {
			foreach ($matches as $match_array) {
				list($search, $type, $id) = $match_array;
				if (!in_array($type, array('domain'))) continue;
				$value = getNameFromID($id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . $type . 's', $type . '_', $type . '_id', $type . '_name');

				$record_value = str_replace(array($search), array($value), $record_value);
			}
		}

		return $record_value;
	}
	
}

if (!isset($fm_module_buildconf))
	$fm_module_buildconf = new fm_module_buildconf();
