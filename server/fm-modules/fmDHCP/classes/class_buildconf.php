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
 | fmDHCP: Easily manage one or more ISC DHCP servers                      |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdhcp/                            |
 +-------------------------------------------------------------------------+
*/

require_once(ABSPATH . 'fm-modules/shared/classes/class_buildconf.php');

class fm_module_buildconf extends fm_shared_module_buildconf {
	
	/**
	 * Generates the server config and updates the client
	 *
	 * @since 0.1
	 * @package fmDHCP
	 *
	 * @param array $raw_data Array containing files and contents
	 * @return string|array|void
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
			
			include(ABSPATH . 'fm-includes/version.php');
			
			$config = '# This file was built using ' . $_SESSION['module'] . ' ' . $__FM_CONFIG[$_SESSION['module']]['version'] . ' on ' . date($date_format . ' ' . $time_format . ' e') . "\n";

			$function = $server_type . 'BuildConfig';
			$config .= $this->$function($server_serial_no);

			$data->files[$server_config_file] = $config;
			if (is_array($files)) {
				$data->files = array_merge($data->files, $files);
			}
			
			return array(get_object_vars($data), null);
		}
		
		/** Bad server */
		$error = _('Server is not found.') . "\n";
		if (isset($post_data['preview'])) {
			return $error;
		}
		if ($compress) echo gzcompress(serialize($error));
		else echo serialize($error);
	}
	
	
	/**
	 * Builds config for ISC DHCPD
	 *
	 * @since 0.1
	 * @package fmDHCP
	 *
	 * @param $server_serial_no Server serial number
	 * @return string
	 */
	private function dhcpdBuildConfig($server_serial_no) {
		global $fmdb, $__FM_CONFIG;
		
		$config = '';

		/** Build items */
		foreach (array('global', 'peer', 'shared', 'subnet', 'group', 'host') as $item) {
			$peers = null;
			$config .= "\n## $item items ##";
			if ($item == 'peer') {
				$peers = $this->dhcpdGetPeers($server_serial_no);
			}
			$config .= $this->dhcpdBuildConfigItems($item, 0, null, $peers);
		}

		return $config;
	}
	
	
	/**
	 * Builds config items for ISC DHCPD
	 *
	 * @since 0.1
	 * @package fmDHCP
	 *
	 * @param string $type Type of config item to build
	 * @param integer $parent_id Parent ID to get children for
	 * @param string $tab Tab character
	 * @return string
	 */
	private function dhcpdBuildConfigItems($type, $parent_id = 0, $tab = null, $peers = null) {
		global $fmdb, $__FM_CONFIG;
		
		$config = $peer_type = '';
		
		$parent = ($type == 'global') ? 'no' : 'yes';
		
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_id', 'config_', 'AND config_type="' . $type . '" AND config_is_parent="' . $parent . '" AND config_parent_id=' . $parent_id . ' AND config_status="active" AND server_serial_no="0"');
		if ($fmdb->num_rows) {
			$config_result = $fmdb->last_result;
			$count = $fmdb->num_rows;
			for ($i=0; $i < $count; $i++) {
				/** Peer? */
				if ($type == 'peer') {
					if (!$peers) continue;
					if (is_array($peers)) {
						if (!array_key_exists('primary', $peers)) {
							$peers['primary'] = array();
						}
						if (!array_key_exists('secondary', $peers)) {
							$peers['secondary'] = array();
						}
					}
					if (!in_array($config_result[$i]->config_id, $peers['primary']) && !in_array($config_result[$i]->config_id, $peers['secondary'])) {
						continue;
					}
				}
				
				if ($config_result[$i]->config_comment) {
					$comment = wordwrap($config_result[$i]->config_comment, 50, "\n");
					$config .= "\n$tab# " . str_replace("\n", "\n# ", $comment) . "\n";
					unset($comment);
				} else {
					$config .= "\n";
				}
				$config_name = $config_result[$i]->config_name;
				if ($type == 'peer') {
					$config_name = 'failover peer';
				} else {
					// Get option prefix
					$fmdb->get_results('SELECT def_prefix,def_direction FROM fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'functions WHERE def_option_type="' . $type . '" AND def_option="' . $config_name . '"');
					if ($fmdb->num_rows && $fmdb->last_result[0]->def_prefix) {
						$config_name = $fmdb->last_result[0]->def_prefix . ' ' . $config_name;
					}
					$direction = $fmdb->num_rows ? $fmdb->last_result[0]->def_direction : null;
				}

				if ($direction == 'empty') {
					$config .= $tab . $config_name . ';';
				} elseif ($direction == 'reverse') {
					$config .= $tab . $config_result[$i]->config_data . ' ' . $config_name . ';';
				} else {
					$config .= $tab . $config_name;
					unset($config_name);
					if (!in_array($type, array('group', 'pool'))) {
						if ($type == 'shared') {
							$config .= '-network';
						}
						$config_data = $config_result[$i]->config_data;
						if ($type == 'peer') {
							$config_data = "\"$config_data\"";
						}
						$config .= ' ' . $config_data;
						unset($config_data);
					}
					
					if ($parent == 'yes') {
						$config .= " {\n";

						/** Get details */
						basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_id` ASC,`config_name`,`config_data', 'config_', 'AND config_type="' . $type . '" AND config_parent_id="' . $config_result[$i]->config_id . '" AND config_status="active"');
						if ($fmdb->num_rows) {
							$child_result = $fmdb->last_result;
							$count2 = $fmdb->num_rows;
							if ($type == 'peer' && $peers && (in_array($config_result[$i]->config_id, $peers['primary']) || in_array($config_result[$i]->config_id, $peers['secondary']))) {
								$config .= "$tab\t";
								$peer_type = in_array($config_result[$i]->config_id, $peers['primary']) ? 'primary' : 'secondary';
								$config .= "$peer_type;\n";
							}
							for ($j=0; $j < $count2; $j++) {
								if ($child_result[$j]->config_data) {
									$fmdb->get_results("SELECT * FROM `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}functions` WHERE `def_option`='{$child_result[$j]->config_name}'");
									if (in_array($child_result[$j]->config_name, array('peer-address', 'peer-port'))) {
										$child_result[$j]->config_name = str_replace('-', ' ', $child_result[$j]->config_name);
									}
									$direction = ($fmdb->num_rows) ? $fmdb->last_result[0]->def_direction : null;
									$option_prefix = ($fmdb->num_rows && $fmdb->last_result[0]->def_prefix) ? $fmdb->last_result[0]->def_prefix . ' ' : null;
									if (strpos($child_result[$j]->config_data, ';') !== false) {
										$lines = explode(';', $child_result[$j]->config_data);
										foreach ($lines as $line) {
											$config .= "$tab\t$option_prefix" . $child_result[$j]->config_name . ' ' . trim($line) . ";\n";
										}
									} elseif ($direction == 'reverse') {
										$config .= "$tab\t$option_prefix" . $child_result[$j]->config_data . ' ' . $child_result[$j]->config_name . ";\n";
									} elseif ($direction == 'empty') {
										if ($child_result[$j]->config_data == 'on') {
											$config .= "$tab\t$option_prefix" . $child_result[$j]->config_name . ";\n";
										}
									} elseif ($type == 'peer' && $child_result[$j]->config_name == 'load-balancing') {
										if ($peer_type == 'primary') {
											$config .= "$tab\t$option_prefix" . $child_result[$j]->config_data . ";\n";
										}
									} else {
										if ($peer_type == 'secondary') {
											if (in_array($child_result[$j]->config_name, array('mclt'))) {
												continue;
											}
											if (strpos($child_result[$j]->config_name, 'peer') !== false) {
												$child_result[$j]->config_name = substr($child_result[$j]->config_name, 5);
											} elseif (in_array($child_result[$j]->config_name, array('address', 'port'))) {
												$child_result[$j]->config_name = 'peer ' . $child_result[$j]->config_name;
											}
										}
										if ($type == 'peer' && strpos($child_result[$j]->config_name, 'address') !== false) {
											$server_name = getNameFromID($child_result[$j]->config_data, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name');
											$dns_rr_lookup = dns_get_record($server_name, DNS_A);
											if (!is_array($dns_rr_lookup) || !isset($dns_rr_lookup[0]['ip'])) {
												$server_address = getNameFromID($child_result[$j]->config_data, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_address');
												if ($server_address) {
													$server_name = $server_address;
												}
												unset($server_address);
											}
											$child_result[$j]->config_data = $server_name;
											unset($server_name);
										}
										if ($child_result[$j]->config_name == 'failover peer') {
											$child_result[$j]->config_data = '"' . getNameFromID($child_result[$j]->config_data, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_', 'config_id', 'config_data') . '"';
										}
										$config .= "$tab\t$option_prefix" . $child_result[$j]->config_name . ' ' . $child_result[$j]->config_data . ";\n";
									}
								}
							}
						}

						/** Nested items? */
						$newtab = $tab . "\t";
						if (!in_array($type, array('host', 'peer'))) {
							$nested_items[] = 'host';
							if (!in_array($type, array('host', 'group'))) $nested_items[] = 'group';
							if (!in_array($type, array('host', 'pool'))) $nested_items[] = 'pool';
							if (!in_array($type, array('host', 'subnet'))) $nested_items[] = 'subnet';
							foreach (array_reverse(array_unique($nested_items)) as $subitem) {
								$sub_config = $this->dhcpdBuildConfigItems($subitem, $config_result[$i]->config_id, $newtab);
								if (trim($sub_config)) {
									$config .= $sub_config;
								}
							}
						}

						/** Close */
						$config .= "$tab}";
					} else {
						$config .= ';';
					}
				}
			}
			unset($config_result, $count, $child_result, $count2);
		}
		
		return $config . "\n";
	}
	
	
	/**
	 * Builds config for ISC DHCPD
	 *
	 * @since 0.1
	 * @package fmDHCP
	 *
	 * @param integer $server_serial_no Server serial number
	 * @return string
	 */
	private function dhcpdGetPeers($server_serial_no) {
		global $fmdb, $__FM_CONFIG;
		$ids = false;
		
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_id', 'config_', 'AND config_type="peer" AND config_is_parent="no" AND config_status="active" AND config_data="' . $server_serial_no . '" AND config_name LIKE "%address"');
		if ($fmdb->num_rows) {
			for ($x=0; $x<$fmdb->num_rows; $x++) {
				if ($fmdb->last_result[$x]->config_name == 'address') {
					$ids['primary'][] = $fmdb->last_result[$x]->config_parent_id;
				}
				if ($fmdb->last_result[$x]->config_name == 'peer-address') {
					$ids['secondary'][] = $fmdb->last_result[$x]->config_parent_id;
				}
			}
		}
		
		return $ids;
	}
	
	
	/**
	 * Figures out what files to update on the client
	 *
	 * @since 0.1
	 * @package fmDHCP
	 *
	 * @param array $raw_data Array containing files and contents
	 * @return string|void
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
	 * @package fmDHCP
	 *
	 * @param array $data Array containing files and contents
	 * @return boolean
	 */
	function validateDaemonVersion($data) {
		global $__FM_CONFIG;
		extract($data);
		
		if ($server_type == 'dhcpd') {
			$required_version = $__FM_CONFIG['fmDHCP']['required_daemon_version'];
		}
		
		/** Get only the version number */
		$server_version = preg_split('/[\s-]+/', $server_version);
		
		if (version_compare($server_version[0], $required_version, '<')) {
			return false;
		}
		
		return true;
	}


	/**
	 * Processes the server config checks
	 *
	 * @since 0.1
	 * @package fmDHCP
	 *
	 * @param array $files_array Array containing files and contents
	 * @return string|void
	 */
	function processConfigsChecks($files_array) {
		global $__FM_CONFIG;
		
		if (!array_key_exists('server_serial_no', $files_array)) return;
		if (getOption('enable_config_checks', $_SESSION['user']['account_id'], $_SESSION['module']) != 'yes') return;
		
		$die = false;
		$checkconf = findProgram('dhcpd');
		
		if (!$checkconf) {
			return $this->getSyntaxCheckMessage('binary', array('binary' => 'dhcpd'));
		}
		
		$fm_temp_directory = '/' . ltrim(getOption('fm_temp_directory'), '/');
		$tmp_dir = rtrim($fm_temp_directory, '/') . '/' . $_SESSION['module'] . '_' . date("YmdHis") . '/';
		system('rm -rf ' . $tmp_dir);
		$debian_system = isDebianSystem($files_array['server_os_distro']);
		
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
		}
		
		/** Create temporary server root directory */
		if (!is_dir($tmp_dir . $files_array['server_root_dir'])) {
			@mkdir($tmp_dir . $files_array['server_root_dir'], 0777, true);
		}
		
		if (!$die) {
			/** Check config */
			$checkconf_cmd = findProgram('sudo') . ' -n ' . findProgram('dhcpd') . ' -t -cf ' . $tmp_dir . $files_array['server_config_file'] . ' 2>&1';
			exec($checkconf_cmd, $checkconf_results, $retval);
			if ($retval) {
				$class = 'class="error"';
				$checkconf_results = join("\n", $checkconf_results);
				if (strpos($checkconf_results, 'sudo') !== false) {
					$class = 'class="info"';
					$message = $this->getSyntaxCheckMessage('sudo', array('checkconf_cmd' => $checkconf_cmd, 'checkconf_results' => $checkconf_results));
				} else {
					foreach(explode("\n", $checkconf_results) as $line) {
						if (preg_match('/(Consortium|All rights reserved|For info)/i', $line)) continue;
						$tmp_results[] = $line;
					}
					if (is_array($tmp_results)) {
						$checkconf_results = join("\n", $tmp_results);
						unset($tmp_results);
					}
					$message = $this->getSyntaxCheckMessage('errors', array('checkconf_results' => $checkconf_results));
				}
			} else {
				$class = null;
				$message = $this->getSyntaxCheckMessage('loadable');
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
	
	
}

if (!isset($fm_module_buildconf))
	$fm_module_buildconf = new fm_module_buildconf();
