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
 | fmFirewall: Easily manage one or more software firewalls                |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmfirewall/                             |
 +-------------------------------------------------------------------------+
*/

require_once(ABSPATH . 'fm-modules/shared/classes/class_buildconf.php');

class fm_module_buildconf extends fm_shared_module_buildconf {
	
	/**
	 * Generates the server config and updates the firewall server
	 *
	 * @since 1.0
	 * @package fmFirewall
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
			
			$config_head = '# This file was built using ' . $_SESSION['module'] . ' ' . $__FM_CONFIG[$_SESSION['module']]['version'] . ' on ' . date($date_format . ' ' . $time_format . ' e') . "\n\n";
			$config = $config_head;
			
			foreach (array('nat', 'filter') as $policy_type) {
				/** Get associated templates */
				$template_ids = getTemplateIDs($server_id, $server_serial_no);
				$template_id_count = 0;
				if (count($template_ids)) {
					list($template_results, $template_id_count) = getTemplatePolicies($template_ids, $server_id, 0, $policy_type);
				}
				
				basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', array('policy_type', 'policy_order_id'), 'policy_', "AND server_serial_no=$server_serial_no AND policy_type='$policy_type'");
				$fmdb->num_rows += $template_id_count;
				if ($fmdb->num_rows) {
					$policy_count = $fmdb->num_rows;
					$policy_result = array_merge((array) $template_results, (array) $fmdb->last_result);
					
					$function = $server_type . 'BuildConfig';
					$config .= $this->$function($policy_result, $policy_count, $server_result[0]) . "\n\n";
					unset($policy_result);
				}
			}

			$data->files[$server_config_file] = $config;
			unset($config);
			
			/** Debian-based systems */
			if (isDebianSystem($server_os_distro)) {
				$data->files['/etc/network/if-pre-up.d/fmFirewall'] = "#!/bin/sh\n{$config_head}iptables-restore < $server_config_file\nexit 0";
			}
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
	 * Figures out what files to update on the firewall
	 *
	 * @since 1.0
	 * @package fmFirewall
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
	 * Updates tables to reset flags
	 *
	 * @since 1.0
	 * @package fmFirewall
	 */
	function updateReloadFlags($post_data) {
		global $fmdb, $__FM_CONFIG;
		
		$server_serial_no = sanitize($post_data['SERIALNO']);
		extract($post_data);
		
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', $server_serial_no, 'server_', 'server_serial_no');
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			$data = $result[0];
			extract(get_object_vars($data), EXTR_SKIP);
			
			$retval = setBuildUpdateConfigFlag($server_serial_no, 'no', 'build');
			$retval = setBuildUpdateConfigFlag($server_serial_no, 'no', 'update');
			$msg = "Success.\n";
		} else $msg = "Server is not found.\n";
		
		if ($compress) echo gzcompress(serialize($msg));
		else echo serialize($msg);
	}
	
	
	function iptablesBuildConfig($policy_result, $count, $server_result) {
		global $fmdb, $__FM_CONFIG, $fm_module_time, $fm_module_services;
		
		if (!class_exists(('fm_module_time'))) {
			include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_time.php');
		}
		if (!class_exists(('fm_module_services'))) {
			include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_services.php');
		}
		
		$fw_actions = array('pass' => 'ACCEPT',
							'block' => 'DROP',
							'reject' => 'REJECT',
							'hide' => 'MASQUERADE',
							'snat' => 'SNAT',
							'dnat' => 'DNAT');
		
		$config['nat'][] = '*nat';
		$config['nat'][] = ':PREROUTING ACCEPT [0:0]';
		$config['nat'][] = ':OUTPUT ACCEPT [0:0]';
		$config['nat'][] = ':POSTROUTING ACCEPT [0:0]';
		$config['nat'][] = null;
		
		$config['filter'][] = '*filter';
		$config['filter'][] = ':INPUT ACCEPT [0:0]';
		$config['filter'][] = ':FORWARD ACCEPT [0:0]';
		$config['filter'][] = ':OUTPUT ACCEPT [0:0]';
		$config['filter'][] = null;
		
		for ($i=0; $i<$count; $i++) {
			if ($policy_result[$i]->policy_status != 'active') continue;
			
			$line = array();
			$keep_state = $uid = null;
			$log_rule = false;
			
			$rule_number = $i + 1;
			$rule_title = sprintf('fmFirewall %s rule %s', $policy_result[$i]->policy_type, $rule_number);
			if ($policy_result[$i]->policy_name) $rule_title .= " ({$policy_result[$i]->policy_name})";
			$config[$policy_result[$i]->policy_type][] = '# ' . $rule_title;
			$rule_comment = wordwrap($policy_result[$i]->policy_comment, 50, "\n");
			$config[$policy_result[$i]->policy_type][] = '# ' . str_replace("\n", "\n# ", $rule_comment);
			unset($rule_comment);
			
			if ($policy_result[$i]->policy_options & $__FM_CONFIG['fw']['policy_options']['log']['bit']) {
				$log_rule = true;
				$log_chain = 'RULE_' . $rule_number;
				$config[$policy_result[$i]->policy_type][] = '-N ' . $log_chain;
				$config[$policy_result[$i]->policy_type][] = '-A ' . strtoupper($policy_result[$i]->policy_direction) . 'PUT -j ' . $log_chain;
			}
			
			$line[] = '-A';

			/** Define chain */
			if ($policy_result[$i]->policy_type == 'filter') {
				$line[] = strtoupper($policy_result[$i]->policy_direction) . 'PUT';
			} elseif ($policy_result[$i]->policy_type == 'nat') {
				if ($policy_result[$i]->policy_snat_type == 'hide') {
					$line[] = 'POSTROUTING';
					$policy_result[$i]->policy_action = 'hide';
				} elseif ($policy_result[$i]->policy_source_translated) {
					$line[] = 'POSTROUTING';
					$policy_result[$i]->policy_action = 'snat';
				} elseif ($policy_result[$i]->policy_destination_translated || $policy_result[$i]->policy_services_translated) {
					$line[] = 'PREROUTING';
					$policy_result[$i]->policy_action = 'dnat';
				}
			}
			if ($policy_result[$i]->policy_interface != 'any') {
				if ($policy_result[$i]->policy_direction == 'in') {
					$line[] = '-i ' . $policy_result[$i]->policy_interface;
				} elseif ($policy_result[$i]->policy_direction == 'out') {
					$line[] = '-o ' . $policy_result[$i]->policy_interface;
				}
			}
			
			$rule_chain = $log_rule ? $log_chain : $fw_actions[$policy_result[$i]->policy_action];
			
			/** Handle keep-states */
			if ($policy_result[$i]->policy_packet_state) {
				foreach (explode(',', $policy_result[$i]->policy_packet_state) as $state) {
					if (in_array($state, $__FM_CONFIG['fw']['policy_states'][$server_result->server_type])) $keep_state[] = $state;
				}
				if ($keep_state) {
					$keep_state = ' -m state --state ' . join(',', $keep_state);
				}
			}
			
			/** Handle frags */
			$frag = ($policy_result[$i]->policy_options & $__FM_CONFIG['fw']['policy_options']['frag']['bit']) ? ' -f' : null;

			/** Handle sources */
			unset($policy_source);
			if ($temp_source = trim($policy_result[$i]->policy_source, ';')) {
				$policy_source = $this->buildAddressList($temp_source);
			} else $policy_source[] = null;

			unset($policy_source_translated);
			if ($temp_source = trim($policy_result[$i]->policy_source_translated, ';')) {
				$policy_source_translated = $this->buildAddressList($temp_source)[0];
			} else $policy_source_translated = null;
			
			/** Handle destinations */
			unset($policy_destination);
			if ($temp_destination = trim($policy_result[$i]->policy_destination, ';')) {
				$policy_destination = $this->buildAddressList($temp_destination);
			} else $policy_destination[] = null;

			unset($policy_destination_translated);
			if ($temp_destination = trim($policy_result[$i]->policy_destination_translated, ';')) {
				$policy_destination_translated = $this->buildAddressList($temp_destination)[0];
			} else $policy_destination_translated = null;

			/** Handle policy tcp flags */
			$policy_tcp_flags = $fm_module_services->getTCPFlags($policy_result[$i]->policy_tcp_flags, 'iptables');

			/** Handle match inverses */
			$source_not = ($policy_result[$i]->policy_source_not) ? '! ' : null;
			$destination_not = ($policy_result[$i]->policy_destination_not) ? '! ' : null;
			$services_not = ($policy_result[$i]->policy_services_not) ? '! ' : null;

			/** Handle services */
			if ($assigned_services = trim($policy_result[$i]->policy_services, ';')) {
				foreach (explode(';', $assigned_services) as $temp_id) {
					$temp_services = array();
					if ($temp_id[0] == 'g') {
						$temp_services[] = $this->extractItemsFromGroup($temp_id);
					} else {
						$temp_services[] = substr($temp_id, 1);
					}
					
					if (is_array($temp_services[0])) $temp_services = $temp_services[0];
					
					foreach ($temp_services as $service_id) {
						basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', $service_id, 'service_', 'service_id', 'active');
						$result = $fmdb->last_result[0];
						
						if ($result->service_type == 'icmp') {
							$icmp_type = $result->service_icmp_type;
							if ($result->service_icmp_code > -1) $icmp_type .= '/' . $result->service_icmp_code;
							
							$policy_services['processed'][$result->service_type][] = ' -p icmp -m icmp --icmp-type ' . $icmp_type;
						} else {
							/** Source ports */
							@list($start, $end) = explode(':', $result->service_src_ports);
							if ($start && $end) {
								$service_source = ($start == $end) ? $start : $result->service_src_ports;
							} else $service_source = null;
							
							/** Destination ports */
							@list($start, $end) = explode(':', $result->service_dest_ports);
							if ($start && $end) {
								$service_destination = ($start == $end) ? $start : $result->service_dest_ports;
							} else $service_destination = null;
							
							/** TCP Flags */
							$tcp_flags = ($result->service_tcp_flags) ? '|' . $result->service_tcp_flags : null;
							
							/** Determine which array to put the service in */
							if ($service_source && $service_destination) {
								$policy_services[$result->service_type]['s-d']['s'][] = $service_source . $tcp_flags;
								$policy_services[$result->service_type]['s-d']['d'][] = $service_destination . $tcp_flags;
							} elseif ($service_source && !$service_destination) {
								$policy_services[$result->service_type]['s-any']['s'][] = $service_source . $tcp_flags;
							} elseif (!$service_source && $service_destination) {
								$policy_services[$result->service_type]['any-d']['d'][] = $service_destination . $tcp_flags;
							} else {
								$policy_services[$result->service_type]['flag_only']['f'][] = $tcp_flags;
							}
						}
					}
				}
			}
			
			if (@is_array($policy_services)) {
				foreach ($policy_services as $protocol => $proto_array) {
					if ($protocol == 'processed') continue;
					
					foreach ($proto_array as $direction_group => $group_array) {
						foreach ($group_array as $direction => $port_array) {
							$l = $k = $j = 0;
							foreach ($port_array as $port) {
								if ($l) break;
								
								if ($j > 14) {
									$k++;
									$j = 0;
								}
								
								if ($direction_group == 's-d') {
									if (@array_key_exists($l, (array) $group_array['s'])) {
										$multiports[$k][] = $group_array['s'][$l] . ' --dport ' . $group_array['d'][$l];
										unset($group_array);
									}
									$l++;
								} else {
									if (strpos($port, '|') !== false) {
										$k++;
										$multiports[$k][] = $port;
										$k++;
										$j = 0;
									} else {
										$multiports[$k][] = $port;
									}
								}
								if (strpos($port, ':')) $j++;
								
								$j++;
							}
							if (@is_array($multiports)) {
								foreach ($multiports as $ports) {
									$ports = array_unique($ports);
									$multi = (count($ports) > 1) ? ' -m multiport --' . $direction . 'ports ' : ' --' . $direction . 'port ';
									if ($direction == 'f') $multi = null;
									$tcp_flags = null;
									if ($protocol == 'tcp' && strpos($ports[0], '|') !== false) {
										list($port, $flags) = explode('|', $ports[0]);
										$tcp_flags = $fm_module_services->getTCPFlags($flags, 'iptables');
										$service_ports = $port;
									} else {
										$service_ports = implode(',', $ports);
									}
									$policy_services['processed'][$protocol][] = ' -p ' . $protocol . $tcp_flags . $multi . $services_not . $service_ports;
								}
							}
							unset($multiports);
						}
					}
					unset($policy_services[$protocol]);
				}
			}
			
			/** Handle time restrictions */
			$time_restrictions = null;
			if ($policy_result[$i]->policy_time) {
				basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'time', substr($policy_result[$i]->policy_time, 1), 'time_', 'time_id', 'active');
				if ($fmdb->num_rows) {
					$time[] = '-m time';
					$time_result = $fmdb->last_result[0];
					
					if ($time_result->time_start_date) $time[] = '--datestart ' . date('Y:m:d', strtotime($time_result->time_start_date));
					if ($time_result->time_end_date) $time[] = '--datestop ' . date('Y:m:d', strtotime($time_result->time_end_date));
					
					if ($time_result->time_start_time) $time[] = '--timestart ' . $time_result->time_start_time;
					if ($time_result->time_end_time) $time[] = '--timestop ' . $time_result->time_end_time;
					
					if ($time_result->time_weekdays && $time_result->time_weekdays != array_sum($__FM_CONFIG['weekdays'])) {
						if (version_compare($server_result->server_version, '1.4', '<')) {
							$weekday_prefix = '--days';
						} else {
							$weekday_prefix .= $time_result->time_weekdays_not . ' --weekdays';
						}
						$time[] = trim($weekday_prefix . ' ' . str_replace(' ', '', $fm_module_time->formatDays($time_result->time_weekdays)));
					}
					
					if (version_compare($server_result->server_version, '1.4', '>')) {
						if ($time_result->time_monthdays) {
							$time[] = trim($time_result->time_monthdays_not . ' --monthdays ' . trim($time_result->time_monthdays, ','));
						}
						
						if ($time_result->time_contiguous == 'yes' && version_compare($server_result->server_version, '1.4.21', '>')) {
							$time[] = '--contiguous';
						}
					}
					
					$time[] = '--' . $time_result->time_zone;
					
					$time_restrictions = implode(' ', $time);
				}
				
				$line[] = $time_restrictions;
			}
			
			/** Handle UID */
			if ($policy_result[$i]->policy_uid) {
				$uid = ' -m owner --uid-owner ' . $policy_result[$i]->policy_uid;
			}
			
			/** Build NAT rule */
			$nat_rule = '';
			if ($policy_result[$i]->policy_type == 'nat') {
				/** SNAT */
				if ($policy_source_translated) {
					$nat_rule .= " --to-source $policy_source_translated";
				}
				/** DNAT */
				if ($policy_destination_translated) {
					$nat_rule .= " --to-destination $policy_destination_translated";
				}
				/** Services */
				if ($policy_result[$i]->policy_services_translated) {
					$temp_service_port = $policy_result[$i]->policy_services_translated;
					if ($temp_service_port[0] == 's') {
						$temp_service_id = substr($temp_service_port, 1);
						$temp_service_port = null;
						basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', $temp_service_id, 'service_', 'service_id', 'active');
						if ($fmdb->num_rows) {
							$result = $fmdb->last_result[0];
							/** Destination ports */
							@list($start, $end) = explode(':', $result->service_dest_ports);
							if ($start && $end) {
								$temp_service_port = ($start == $end) ? $start : str_replace(':', '-', $result->service_dest_ports);
							}
						}
					}
					$nat_rule .= ($temp_service_port) ? ' --to-ports ' . $temp_service_port : null;
				}
			}

			/** Build the rules */
			foreach ($policy_source as $source_address) {
				$source = ($source_address) ? ' -s ' . $source_not . $source_address : null;
				foreach ($policy_destination as $destination_address) {
					$destination = ($destination_address) ? ' -d ' . $destination_not . $destination_address : null;
					if (is_array($policy_services['processed'])) {
						foreach ($policy_services['processed'] as $protocol => $line_array) {
							foreach ($line_array as $rule) {
								$tcp_flags = ($protocol == 'tcp' && strpos($rule, 'tcp-flags') === false) ? $policy_tcp_flags : null;
								$config[$policy_result[$i]->policy_type][] = implode(' ', $line) . $source . $destination . $rule . $tcp_flags . $uid . $keep_state . $frag . ' -j ' . $fw_actions[$policy_result[$i]->policy_action] . $nat_rule;
							}
						}
					} else {
						$rule = implode(' ', $line);
						$tcp_flags = (strpos($rule, 'tcp') !== false && strpos($rule, 'tcp-flags') === false) ? $policy_tcp_flags : null;
						$config[$policy_result[$i]->policy_type][] = $rule . $source . $destination . $tcp_flags . $uid . $keep_state . $frag . ' -j ' . $rule_chain . $nat_rule;
					}
				}
			}
			unset($policy_services['processed']);
			
			/** Handle logging */
			if ($log_rule) {
				$config[$policy_result[$i]->policy_type][] = '-A ' . $log_chain . ' -j LOG --log-level info --log-prefix "' . $rule_title . ' - ' . strtoupper($policy_result[$i]->policy_action) . ': "';
				$config[$policy_result[$i]->policy_type][] = '-A ' . $log_chain . ' -j ' . $fw_actions[$policy_result[$i]->policy_action];
			}
			
			$config[$policy_result[$i]->policy_type][] = null;
		}
		
		$config['filter'][] = $config['nat'][] = 'COMMIT';

		return trim(implode("\n", $config[$policy_result[0]->policy_type]));
	}
	
	
	function pfBuildConfig($policy_result, $count, $server_result) {
		global $fmdb, $__FM_CONFIG, $fm_module_services;
		
		if (!class_exists(('fm_module_services'))) {
			include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_services.php');
		}

		$fw_actions = array('pass' => 'pass',
							'block' => 'block',
							'reject' => 'block return-icmp');
		
		for ($i=0; $i<$count; $i++) {
			if ($policy_result[$i]->policy_status != 'active') continue;

			#~ Only filter rules until NAT is supported
			if ($policy_result[$i]->policy_type != 'filter') continue;
			
			$line = array();
			$label = $keep_state = $uid = null;
			
			$rule_number = $i + 1;
			$rule_title = sprintf('fmFirewall %s rule %s', $policy_result[$i]->policy_type, $rule_number);
			if ($policy_result[$i]->policy_name) $rule_title .= " ({$policy_result[$i]->policy_name})";
			$config[] = '# ' . $rule_title;
			$rule_comment = wordwrap($policy_result[$i]->policy_comment, 50, "\n");
			$config[] = '# ' . str_replace("\n", "\n# ", $rule_comment);
			unset($rule_comment);

			$line[] = $fw_actions[$policy_result[$i]->policy_action];
			$line[] = $policy_result[$i]->policy_direction;
			
			/** Handle logging */
			if ($policy_result[$i]->policy_options & $__FM_CONFIG['fw']['policy_options']['log']['bit']) {
				$line[] = 'log';
				$label = ' label "' . $rule_title . ' - ' . strtoupper($policy_result[$i]->policy_action) . ': "';
			}
			
			/** Handle quick processing */
			if ($policy_result[$i]->policy_options & $__FM_CONFIG['fw']['policy_options']['quick']['bit']) {
				$line[] = 'quick';
			}
			
			/** Handle interface */
			$interface = ($policy_result[$i]->policy_interface != 'any') ? 'on ' . $policy_result[$i]->policy_interface : null;
			
			if ($interface) $line[] = $interface;
			$line[] = 'inet';
			
			/** Handle keep-states */
			if ($policy_result[$i]->policy_packet_state) {
				foreach (explode(',', $policy_result[$i]->policy_packet_state) as $state) {
					if (in_array($state, $__FM_CONFIG['fw']['policy_states'][$server_result->server_type])) $keep_state[] = $state;
				}
				if ($keep_state) {
					$keep_state = ' ' . join(',', $keep_state);
				}
			}

			/** Handle match inverses */
			$services_not = ($policy_result[$i]->policy_services_not) ? '!' : null;

			/** Handle sources */
			unset($policy_source);
			if ($temp_source = trim($policy_result[$i]->policy_source, ';')) {
				$policy_source = $this->buildAddressList($temp_source);
			} else $policy_source = null;
			$source_address = ($policy_result[$i]->policy_source_not) ? '! ' : null;
			if (is_array($policy_source)) {
				if (count($policy_source) > 1) {
					$table[] = 'table <fM_r' . $rule_number . '_src> { ' . implode(', ', $policy_source) . ' }';
					$source_address .= '<fM_r' . $rule_number . '_src>';
				} else {
					$source_address .= implode(', ', $policy_source);
				}
			} else {
				$source_address .= 'any';
			}
			
			/** Handle destinations */
			unset($policy_destination);
			if ($temp_destination = trim($policy_result[$i]->policy_destination, ';')) {
				$policy_destination = $this->buildAddressList($temp_destination);
			} else $policy_destination = null;
			$destination_address = ($policy_result[$i]->policy_destination_not) ? '! ' : null;
			if (is_array($policy_destination)) {
				if (count($policy_destination) > 1) {
					$table[] = 'table <fM_r' . $rule_number . '_dst> { ' . implode(', ', $policy_destination) . ' }';
					$destination_address .= '<fM_r' . $rule_number . '_dst>';
				} else {
					$destination_address .= implode(', ', $policy_destination);
				}
			} else {
				$destination_address .= 'any';
			}
			
			/** Handle policy tcp flags */
			$policy_tcp_flags = $fm_module_services->getTCPFlags($policy_result[$i]->policy_tcp_flags, 'ipfilter');

			/** Handle services */
			$tcp = $udp = $icmp = null;
			if ($assigned_services = trim($policy_result[$i]->policy_services, ';')) {
				foreach (explode(';', $assigned_services) as $temp_id) {
					$temp_services = array();
					if ($temp_id[0] == 'g') {
						$temp_services[] = $this->extractItemsFromGroup($temp_id);
					} else {
						$temp_services[] = substr($temp_id, 1);
					}
					
					if (is_array($temp_services[0])) $temp_services = $temp_services[0];
					
					foreach ($temp_services as $service_id) {
						basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', $service_id, 'service_', 'service_id', 'active');
						$result = $fmdb->last_result[0];
						
						if ($result->service_type == 'icmp') {
							$policy_services['processed'][$result->service_type][] = $result->service_icmp_type . '|' . $result->service_icmp_code;
						} else {
							/** Source ports */
							@list($start, $end) = explode(':', $result->service_src_ports);
							if ($start && $end) {
								$service_source = ($start == $end) ? $start : $result->service_src_ports;
							} else $service_source = null;
							
							/** Destination ports */
							@list($start, $end) = explode(':', $result->service_dest_ports);
							if ($start && $end) {
								$service_destination = ($start == $end) ? $start : $result->service_dest_ports;
							} else $service_destination = null;
							
							/** TCP Flags */
							$tcp_flags = ($result->service_tcp_flags) ? '|' . $result->service_tcp_flags : null;
							
							/** Determine which array to put the service in */
							if ($service_source && $service_destination) {
								$policy_services[$result->service_type]['s-d']['s'][] = $service_source . $tcp_flags;
								$policy_services[$result->service_type]['s-d']['d'][] = $service_destination . $tcp_flags;
							} elseif ($service_source && !$service_destination) {
								$policy_services[$result->service_type]['s-any']['s'][] = $service_source . $tcp_flags;
							} elseif (!$service_source && $service_destination) {
								$policy_services[$result->service_type]['any-d']['d'][] = $service_destination . $tcp_flags;
							} else {
								$policy_services[$result->service_type]['flag_only']['f'][] = $tcp_flags;
							}
						}
					}
				}
			}
			
			if (@is_array($policy_services)) {
				foreach ($policy_services as $protocol => $proto_array) {
					if ($protocol == 'processed') continue;
					
					foreach ($proto_array as $direction_group => $group_array) {
						foreach ($group_array as $direction => $port_array) {
							$l = $k = 0;
							foreach ($port_array as $port) {
								if ($l) break;
								
								if ($direction_group == 's-d') {
									if (is_array($group_array['s']) && array_key_exists($l, $group_array['s'])) {
										$s_equals = (strpos($group_array['s'][$l], ':') === false) ? '= ' : null;
										$d_equals = (strpos($group_array['d'][$l], ':') === false) ? '= ' : null;
										
										$multiports[$k][] = ' port ' . $services_not . $s_equals . $group_array['s'][$l] . '; ' . $services_not . $d_equals . $group_array['d'][$l];
										unset($group_array);
									}
									$l++;
								} else {
									if (strpos($port, '|') !== false) {
										if ($direction == 'f') {
											$multiports[$k][] = '; ' . $port;
										} else {
											$k++;
											$multiports[$k][] = ($direction_group == 's-any') ? ' port ' . $services_not . '= ' . $port . ';' : '; port ' . $services_not . '= ' . $port;
											$k++;
										}
									} else {
										$multiports[$k][] = ($direction_group == 's-any') ? ' port ' . $services_not . '= ' . $port . ';' : '; port ' . $services_not . '= ' . $port;
									}
								}
							}
							if (@is_array($multiports)) {
								foreach ($multiports as $ports) {
									$ports = array_unique($ports);
									$tcp_flags = null;
									if ($protocol == 'tcp' && strpos($ports[0], '|') !== false) {
										list($port, $flags) = explode('|', $ports[0]);
										$tcp_flags = $fm_module_services->getTCPFlags($flags, 'ipfilter');
										$service_ports = $port;
									} else {
										$service_ports = implode(',', $ports);
										$service_ports = str_replace(array(',; ', ',! '), ',', $service_ports);
										if (strpos($service_ports, ',') !== false) $service_ports = str_replace(array('port =', 'port !='), array('{', '{!'), $service_ports) . ' }';
										$service_ports = str_replace(',{', ',', $service_ports);
										$service_ports = str_replace('{', 'port {', $service_ports);
									}
									$policy_services['processed'][$protocol][] = $service_ports . $tcp_flags;
								}
							}
							unset($multiports);
						}
					}
					unset($policy_services[$protocol]);
				}
			}
			
			/** Handle UID */
			if ($policy_result[$i]->policy_uid) {
				$uid = ' user { ' . $policy_result[$i]->policy_uid . ' }';
			}
			
			/** Build the rules */
			if (@is_array($policy_services['processed'])) {
				foreach ($policy_services['processed'] as $protocol => $proto_array) {
					$protocol = 'proto ' . $protocol;
	
					foreach ($proto_array as $rule_ports) {
						@list($source_ports, $destination_ports) = explode(';', $rule_ports);
						if (strpos($protocol, 'icmp') !== false) {
							$icmptypes = ' icmp-type ';
							if (count($proto_array) > 1) {
								$icmptypes .= trim($services_not . ' { ' . implode(', ', str_replace('|', ' code ', $proto_array)) . ' }');
							} else {
								$icmptypes .= trim($services_not . ' ' . implode(', ', str_replace('|', ' code ', $proto_array)));
							}
							$source_ports = $destination_ports = null;
						} else {
							$icmptypes = null;
						}
						
						$tcp_flags = (strpos($protocol, 'tcp') !== false && strpos($rule_ports, 'flags') === false) ? $policy_tcp_flags : null;
						$config[] = implode(' ', $line) . " $protocol from " . $source_address . $source_ports . ' to ' . $destination_address . str_replace('  ', ' ', $destination_ports) . $tcp_flags . $uid . $icmptypes . $keep_state . $label;
						
						if (strpos($protocol, 'icmp') !== false) break;
					}
				}
				unset($policy_services);
			} else {
				$rule = implode(' ', $line);
				$tcp_flags = (strpos($rule, 'tcp') !== false && strpos($rule, 'flags') === false) ? $policy_tcp_flags : null;
				$config[] = $rule . " from $source_address to $destination_address" . $tcp_flags . $uid . $keep_state . $label;
			}
			
			$config[] = null;
		}
		
		$table[] = null;
		
		$config = array_merge($table, (array) $config);
		
		return str_replace('from any to any', 'all', implode("\n", $config)) . "\n\n";
	}
	
	
	function ipfilterBuildConfig($policy_result, $count) {
		global $fmdb, $__FM_CONFIG, $fm_module_services;
		
		if (!class_exists(('fm_module_services'))) {
			include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_services.php');
		}

		$fw_actions = array('pass' => 'pass',
							'block' => 'block',
							'reject' => 'block');
		
		for ($i=0; $i<$count; $i++) {
			if ($policy_result[$i]->policy_status != 'active') continue;

			#~ Only filter rules until NAT is supported
			if ($policy_result[$i]->policy_type != 'filter') continue;
			
			$line = array();
			$keep_state = null;
			
			$rule_number = $i + 1;
			$rule_title = sprintf('fmFirewall %s rule %s', $policy_result[$i]->policy_type, $rule_number);
			if ($policy_result[$i]->policy_name) $rule_title .= " ({$policy_result[$i]->policy_name})";
			$config[] = '# ' . $rule_title;
			$rule_comment = wordwrap($policy_result[$i]->policy_comment, 50, "\n");
			$config[] = '# ' . str_replace("\n", "\n# ", $rule_comment);
			unset($rule_comment);
			
			$line[] = $fw_actions[$policy_result[$i]->policy_action];
			$line[] = $policy_result[$i]->policy_direction;
			
			/** Handle logging */
			if ($policy_result[$i]->policy_options & $__FM_CONFIG['fw']['policy_options']['log']['bit']) {
				$line[] = 'log';
			}
			
			$line[] = 'quick';

			/** Handle interface */
			$interface = ($policy_result[$i]->policy_interface != 'any') ? 'on ' . $policy_result[$i]->policy_interface : null;
			if ($interface) $line[] = $interface;
			
			/** Handle keep-states */
			$keep_state = ($policy_result[$i]->policy_packet_state && in_array('keep state', explode(',', $policy_result[$i]->policy_packet_state))) ? ' keep state' : null;
			
			/** Handle frags */
			$frag = ($policy_result[$i]->policy_options & $__FM_CONFIG['fw']['policy_options']['frag']['bit']) ? ' with frag' : null;

			/** Handle sources */
			unset($policy_source);
			if ($temp_source = trim($policy_result[$i]->policy_source, ';')) {
				$policy_source = $this->buildAddressList($temp_source);
			} else $policy_source[] = 'any';
			
			/** Handle destinations */
			unset($policy_destination);
			if ($temp_destination = trim($policy_result[$i]->policy_destination, ';')) {
				$policy_destination = $this->buildAddressList($temp_destination);
			} else $policy_destination[] = 'any';
			
			/** Handle policy tcp flags */
			$policy_tcp_flags = $fm_module_services->getTCPFlags($policy_result[$i]->policy_tcp_flags, 'ipfilter');

			/** Handle services */
			$services_not = ($policy_result[$i]->policy_services_not) ? '!' : null;
			$tcp = $udp = $icmp = null;
			if ($assigned_services = trim($policy_result[$i]->policy_services, ';')) {
				foreach (explode(';', $assigned_services) as $temp_id) {
					$temp_services = array();
					if ($temp_id[0] == 'g') {
						$temp_services[] = $this->extractItemsFromGroup($temp_id);
					} else {
						$temp_services[] = substr($temp_id, 1);
					}
					
					if (is_array($temp_services[0])) $temp_services = $temp_services[0];
					
					foreach ($temp_services as $service_id) {
						basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', $service_id, 'service_', 'service_id', 'active');
						$result = $fmdb->last_result[0];
						
						if ($result->service_type == 'icmp') {
							$policy_services[$result->service_type][] = $result->service_icmp_type . '|' . $result->service_icmp_code;
						} else {
							/** Source ports */
							@list($start, $end) = explode(':', $result->service_src_ports);
							if ($start && $end) {
								$service_source = ($start == $end) ? $start : str_replace(':', ' <> ', $result->service_src_ports);
							} else $service_source = null;
							
							/** Destination ports */
							@list($start, $end) = explode(':', $result->service_dest_ports);
							if ($start && $end) {
								$service_destination = ($start == $end) ? $start : str_replace(':', ' <> ', $result->service_dest_ports);
							} else $service_destination = null;
							
							/** TCP Flags */
							$tcp_flags = ($result->service_tcp_flags) ? '|' . $result->service_tcp_flags : null;
							
							/** Determine which array to put the service in */
							if ($service_source && $service_destination) {
								$policy_services[$result->service_type]['s-d']['s'][] = $service_source . $tcp_flags;
								$policy_services[$result->service_type]['s-d']['d'][] = $service_destination . $tcp_flags;
							} elseif ($service_source && !$service_destination) {
								$policy_services[$result->service_type]['s-any']['s'][] = $service_source . $tcp_flags;
							} elseif (!$service_source && $service_destination) {
								$policy_services[$result->service_type]['any-d']['d'][] = $service_destination . $tcp_flags;
							} else {
								$policy_services[$result->service_type]['flag_only']['f'][] = $tcp_flags;
							}
						}
					}
				}
			}
			
			/** Build the rules */
			foreach ($policy_source as $source_address) {
				$source = ($source_address) ? ' from ' . $source_address : null;
				foreach ($policy_destination as $destination_address) {
					$destination = ($destination_address) ? ' to ' . $destination_address : null;
					if (@is_array($policy_services)) {
						foreach ($policy_services as $protocol => $proto_array) {
							if ($protocol == 'icmp') {
								foreach (@array_unique($proto_array) as $type) {
									list($icmp_type, $icmp_code) = explode('|', $type);
									$icmp_code = ($icmp_code < 0) ? null : ' code ' . $icmp_code;
									$config[] = implode(' ', $line) . " proto $protocol" . $source . $destination . ' icmp-type ' . $icmp_type . $icmp_code . $frag . $keep_state;
								}
							} else {
								foreach ($proto_array as $direction_group => $direction_array) {
									$source_port = $destination_port = null;
									if ($direction_group == 's-any') {
										$source_port = ' port ' . $services_not . '= ';
										foreach (@array_unique($direction_array['s']) as $port) {
											if ($protocol == 'tcp' && strpos($port, '|') !== false) {
												list($service_port, $flags) = explode('|', $port);
												$tcp_flags = $fm_module_services->getTCPFlags($flags, 'ipfilter');
												$service_ports = $service_port;
											} else {
												$service_ports = $port;
												$tcp_flags = null;
											}
											if ($protocol == 'tcp' && !$tcp_flags) {
												$tcp_flags = $policy_tcp_flags;
											}
											$config[] = implode(' ', $line) . " proto $protocol" . $source . $source_port . $service_ports . $destination . $destination_port . $tcp_flags . $frag . $keep_state;
										}
									} elseif ($direction_group == 'any-d') {
										$destination_port = ' port ' . $services_not . '= ';
										foreach (@array_unique($direction_array['d']) as $port) {
											if ($protocol == 'tcp' && strpos($port, '|') !== false) {
												list($service_port, $flags) = explode('|', $port);
												$tcp_flags = $fm_module_services->getTCPFlags($flags, 'ipfilter');
												$service_ports = $service_port;
											} else {
												$service_ports = $port;
												$tcp_flags = null;
											}
											if ($protocol == 'tcp' && !$tcp_flags) {
												$tcp_flags = $policy_tcp_flags;
											}
											$config[] = implode(' ', $line) . " proto $protocol" . $source . $source_port . $destination . $destination_port . $service_ports . $tcp_flags . $frag . $keep_state;
										}
									} elseif ($direction_group == 's-d') {
										$source_port = ' port ' . $services_not . '= ';
										$destination_port = ' port ' . $services_not . '= ';
										foreach (@array_unique($direction_array['s']) as $index => $port) {
											if ($protocol == 'tcp' && strpos($port, '|') !== false) {
												list($service_port, $flags) = explode('|', $port);
												$tcp_flags = $fm_module_services->getTCPFlags($flags, 'ipfilter');
												$service_ports = $service_port;
											} else {
												$service_ports = $port;
												$tcp_flags = null;
											}
											if ($protocol == 'tcp' && !$tcp_flags) {
												$tcp_flags = $policy_tcp_flags;
											}
											$config[] = implode(' ', $line) . " proto $protocol" . $source . $source_port . $service_ports . $destination . $destination_port . $direction_array['d'][$index] . $tcp_flags . $frag . $keep_state;
										}
									} elseif ($direction_group == 'flag_only') {
										foreach (@array_unique($direction_array['f']) as $port) {
											if ($protocol == 'tcp' && strpos($port, '|') !== false) {
												list($service_port, $flags) = explode('|', $port);
												$tcp_flags = $fm_module_services->getTCPFlags($flags, 'ipfilter');
												$service_ports = $service_port;
											} else {
												$service_ports = $port;
												$tcp_flags = null;
											}
											if ($protocol == 'tcp' && !$tcp_flags) {
												$tcp_flags = $policy_tcp_flags;
											}
											$config[] = implode(' ', $line) . " proto $protocol" . $source . $source_port . $destination . $destination_port . $service_ports . $tcp_flags . $frag . $keep_state;
										}
									}
								}
							}
						}
					} else {
						$rule = implode(' ', $line);
						$tcp_flags = (strpos($rule, 'tcp') !== false) ? $policy_tcp_flags : null;
						$config[] = $rule . $source . $destination . $tcp_flags . $frag . $keep_state;
					}
				}
			}
			unset($policy_services);
			

			$config[] = null;
		}

		return str_replace('from any to any', 'all', implode("\n", (array) $config));
	}
	
	
	function ipfwBuildConfig($policy_result, $count) {
		global $fmdb, $__FM_CONFIG, $fm_module_services;
		
		if (!class_exists(('fm_module_services'))) {
			include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_services.php');
		}

		$fw_actions = array('pass' => 'allow',
							'block' => 'deny',
							'reject' => 'unreach host');
		
		$cmd = 'ipfw -q add';
		
		#~ Only filter rules until NAT is supported
		if ($policy_result[$i]->policy_type == 'filter') {
			$config[] = 'ipfw -q -f flush';
			$config[] = $cmd . ' check-state';
			$config[] = null;
		}
		
		for ($i=0; $i<$count; $i++) {
			if ($policy_result[$i]->policy_status != 'active') continue;

			#~ Only filter rules until NAT is supported
			if ($policy_result[$i]->policy_type != 'filter') continue;
			
			$line = array();
			$keep_state = $uid = null;
			
			$rule_number = $i + 1;
			$rule_title = sprintf('fmFirewall %s rule %s', $policy_result[$i]->policy_type, $rule_number);
			if ($policy_result[$i]->policy_name) $rule_title .= " ({$policy_result[$i]->policy_name})";
			$config[] = '# ' . $rule_title;
			$rule_comment = wordwrap($policy_result[$i]->policy_comment, 50, "\n");
			$config[] = '# ' . str_replace("\n", "\n# ", $rule_comment);
			unset($rule_comment);
			
			$line[] = $cmd;
			$line[] = $fw_actions[$policy_result[$i]->policy_action];
			
			/** Handle logging */
			if ($policy_result[$i]->policy_options & $__FM_CONFIG['fw']['policy_options']['log']['bit']) {
				$line[] = 'log';
			}
			
			/** Handle interface */
			$interface = ($policy_result[$i]->policy_interface != 'any') ? ' via ' . $policy_result[$i]->policy_interface : null;
			
			/** Handle keep-states */
			$keep_state = ($policy_result[$i]->policy_packet_state && in_array('keep-state', explode(',', $policy_result[$i]->policy_packet_state))) ? ' keep-state' : null;
			
			/** Handle established option */
			$established = ($policy_result[$i]->policy_action == 'pass' && ($policy_result[$i]->policy_options & $__FM_CONFIG['fw']['policy_options']['established']['bit'])) ? 'established ' : null;

			/** Handle fragment option */
			$frag = ($policy_result[$i]->policy_options & $__FM_CONFIG['fw']['policy_options']['frag']['bit']) ? 'frag ' : null;

			/** Handle match inverses */
			$services_not = ($policy_result[$i]->policy_services_not) ? 'not ' : null;

			/** Handle sources */
			unset($policy_source);
			if ($temp_source = trim($policy_result[$i]->policy_source, ';')) {
				$policy_source = $this->buildAddressList($temp_source);
			} else $policy_source = null;
			$source_address = ($policy_result[$i]->policy_source_not) ? 'not ' : null;
			$source_address .= (is_array($policy_source)) ? implode(',', $policy_source) : 'any';
			
			/** Handle destinations */
			unset($policy_destination);
			if ($temp_destination = trim($policy_result[$i]->policy_destination, ';')) {
				$policy_destination = $this->buildAddressList($temp_destination);
			} else $policy_destination = null;
			$destination_address = ($policy_result[$i]->policy_destination_not) ? 'not ' : null;
			$destination_address .= (is_array($policy_destination)) ? implode(',', $policy_destination) : 'any';
			
			/** Handle policy tcp flags */
			$policy_tcp_flags = $fm_module_services->getTCPFlags($policy_result[$i]->policy_tcp_flags, 'ipfw');

			/** Handle services */
			if ($assigned_services = trim($policy_result[$i]->policy_services, ';')) {
				foreach (explode(';', $assigned_services) as $temp_id) {
					$temp_services = array();
					if ($temp_id[0] == 'g') {
						$temp_services[] = $this->extractItemsFromGroup($temp_id);
					} else {
						$temp_services[] = substr($temp_id, 1);
					}
					
					if (is_array($temp_services[0])) $temp_services = $temp_services[0];
					
					foreach ($temp_services as $service_id) {
						basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', $service_id, 'service_', 'service_id', 'active');
						$result = $fmdb->last_result[0];
						
						if ($result->service_type == 'icmp') {
							$policy_services['processed'][$result->service_type][] = $result->service_icmp_type;
						} else {
							/** Source ports */
							@list($start, $end) = explode(':', $result->service_src_ports);
							if ($start && $end) {
								$service_source = ($start == $end) ? $start : $result->service_src_ports;
							} else $service_source = null;
							
							/** Destination ports */
							@list($start, $end) = explode(':', $result->service_dest_ports);
							if ($start && $end) {
								$service_destination = ($start == $end) ? $start : $result->service_dest_ports;
							} else $service_destination = null;
							
							/** TCP Flags */
							$tcp_flags = ($result->service_tcp_flags) ? '|' . $result->service_tcp_flags : null;
							
							/** Determine which array to put the service in */
							if ($service_source && $service_destination) {
								$policy_services[$result->service_type]['s-d']['s'][] = $service_source . $tcp_flags;
								$policy_services[$result->service_type]['s-d']['d'][] = $service_destination . $tcp_flags;
							} elseif ($service_source && !$service_destination) {
								$policy_services[$result->service_type]['s-any']['s'][] = $service_source . $tcp_flags;
							} elseif (!$service_source && $service_destination) {
								$policy_services[$result->service_type]['any-d']['d'][] = $service_destination . $tcp_flags;
							} else {
								$policy_services[$result->service_type]['flag_only']['f'][] = $tcp_flags;
							}
						}
					}
				}
			}
			
			if (@is_array($policy_services)) {
				foreach ($policy_services as $protocol => $proto_array) {
					if ($protocol == 'processed') continue;
					
					foreach ($proto_array as $direction_group => $group_array) {
						foreach ($group_array as $direction => $port_array) {
							$l = $k = 0;
							foreach ($port_array as $port) {
								if ($l) break;
								
								if ($direction_group == 's-d') {
									if (@array_key_exists($l, (array) $group_array['s'])) {
										$multiports[$k][] = ' ' . $services_not . $group_array['s'][$l] . '; ' . $services_not . $group_array['d'][$l];
										unset($group_array);
									}
									$l++;
								} else {
									if (strpos($port, '|') !== false) {
										$k++;
										$multiports[$k][] = ($direction_group == 's-any') ? ' ' . $services_not . $port . ';' : '; ' . $services_not . $port;
										$k++;
									} else {
										$multiports[$k][] = ($direction_group == 's-any') ? ' ' . $services_not . $port . ';' : '; ' . $services_not . $port;
									}
								}
							}
							if (@is_array($multiports)) {
								foreach ($multiports as $ports) {
									$ports = array_unique($ports);
									$tcp_flags = null;
									if ($protocol == 'tcp' && strpos($ports[0], '|') !== false) {
										list($port, $flags) = explode('|', $ports[0]);
										$tcp_flags = $fm_module_services->getTCPFlags($flags, 'ipfw');
										$service_ports = $port;
									} else {
										$service_ports = implode(',', $ports);
										$service_ports = str_replace(array(',; ', ',not '), ',', $service_ports);
									}
									$policy_services['processed'][$protocol][] = $service_ports . $tcp_flags;
								}
							}
							unset($multiports);
						}
					}
					unset($policy_services[$protocol]);
				}
			}
			
			/** Handle UID */
			if ($policy_result[$i]->policy_uid) {
				$uid = ' uid ' . $policy_result[$i]->policy_uid;
			}
			
			/** Build the rules */
			if (@is_array($policy_services['processed'])) {
				foreach ($policy_services['processed'] as $protocol => $proto_array) {
					foreach ($proto_array as $rule_ports) {
						@list($source_ports, $destination_ports) = explode(';', $rule_ports);
						$icmptypes = ($protocol == 'icmp') ? ' icmptypes ' . trim($services_not . ' ' . implode(',', $proto_array)) : null;
						if ($protocol == 'icmp') {
							$source_ports = $destination_ports = null;
						}
		
						$tcp_flags = ($protocol == 'tcp' && strpos($rule_ports, 'flags') === false) ? $policy_tcp_flags : null;
						$config[] = implode(' ', $line) . " $protocol from " . $source_address . $source_ports . ' to ' . trim($destination_address . str_replace('  ', ' ', $destination_ports)) . $tcp_flags . $icmptypes . ' ' . $established . $frag . $policy_result[$i]->policy_direction . $interface . $keep_state . $uid;
					}
				}
				unset($policy_services);
			} else {
				$rule = implode(' ', $line);
				$tcp_flags = (strpos($rule, 'tcp') !== false) ? $policy_tcp_flags : null;
				$config[] = $rule . " all from $source_address to $destination_address" . $tcp_flags . $icmptypes . ' ' . $established . $frag . $policy_result[$i]->policy_direction . $interface . $keep_state . $uid;
			}
			
			$config[] = null;
		}
		
		return implode("\n", (array) $config);
	}
	
	
	function extractItemsFromGroup($group_id) {
		global $fmdb, $__FM_CONFIG;
		
		$new_group_items = null;
		
		if ($group_id[0] == 'g') {
			basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'groups', substr($group_id, 1), 'group_', 'group_id', 'active');
			$group_result = $fmdb->last_result[0];
			$group_items = $group_result->group_items;
			
			foreach (explode(';', trim($group_result->group_items, ';')) as $id) {
				if ($id[0] == 'g') {
					$new_group_items = $this->extractItemsFromGroup($id);
				} else {
					$temp_items[] = substr($id, 1);
				}
			}
		} else {
			$temp_items[] = substr($group_id, 1);
		}
		
		if (is_array($new_group_items)) $temp_items = array_merge($temp_items, $new_group_items);
		
		return $temp_items;
	}
	
	
	function buildAddressList($addresses) {
		global $fmdb, $__FM_CONFIG;
		
		$address_list = array();
		
		$address_ids = explode(getDelimiter($addresses), $addresses);
		foreach ($address_ids as $temp_id) {
			$temp = array();
			if (verifyCIDR($temp_id)) {
				$address_list[] = $temp_id;
				continue;
			}
			if (strpos($temp_id, '-') !== false) {
				$ip_range = false;
				foreach (explode('-', $temp_id) as $ip_address) {
					$ip_range = (verifyCIDR($ip_address)) ? true : false;
				}
				if ($ip_range) $address_list[] = $temp_id;
			}
			if ($temp_id[0] == 'g') {
				$temp[] = $this->extractItemsFromGroup($temp_id);
			} else {
				$temp[] = substr($temp_id, 1);
			}
			
			if (is_array($temp[0])) $temp = $temp[0];
			
			foreach ($temp as $object_id) {
				basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', $object_id, 'object_', 'object_id', 'active');
				$result = $fmdb->last_result[0];
				
				if ($result->object_type == 'network') {
					$address_list[] = $result->object_address . '/' . mask2cidr($result->object_mask);
				} else {
					$address_list[] = $result->object_address;
				}
			}
		}
		
		return $address_list;
	}
	
	
	/**
	 * Updates the daemon version number in the database
	 *
	 * @since 1.0
	 * @package fmFirewall
	 */
	function updateServerVersion() {
		global $fmdb, $__FM_CONFIG;
		
		$query = "UPDATE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}servers` SET `server_version`='" . $_POST['server_version'] . "', `server_os`='" . $_POST['server_os'] . "' WHERE `server_serial_no`='" . $_POST['SERIALNO'] . "' AND `account_id`=
			(SELECT account_id FROM `fm_accounts` WHERE `account_key`='" . $_POST['AUTHKEY'] . "')";
		$fmdb->query($query);
	}
	
	
	/**
	 * Validate the daemon version number of the client
	 *
	 * @since 1.0
	 * @package fmFirewall
	 */
	function validateDaemonVersion($data) {
		global $__FM_CONFIG;
		/*
		 * return true until this function is actually required
		 * currently there are no features that are version-dependent
		 */
		return true;
	}


}

if (!isset($fm_module_buildconf))
	$fm_module_buildconf = new fm_module_buildconf();
