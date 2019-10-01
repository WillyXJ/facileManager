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
 | fmDNS: Easily manage one or more ISC BIND servers                       |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdns/                             |
 +-------------------------------------------------------------------------+
*/

require_once(ABSPATH . 'fm-modules/shared/classes/class_tools.php');

class fm_module_tools extends fm_shared_module_tools {
	
	/**
	 * Imports records from a zone file and presents a confirmation
	 *
	 * @since 1.0
	 * @package fmDNS
	 *
	 * @param integer $domain_id Domain ID to import records into
	 * @return string
	 */
	function zoneImportWizard($domain_id) {
		global $__FM_CONFIG, $fm_name;
		
		if (!currentUserCan('manage_records', $_SESSION['module'])) return $this->unAuth('zone');
		if (!zoneAccessIsAllowed(array($domain_id))) return $this->unAuth('zone');
		
		$get_dynamic_zone_data = array_key_exists('get_dynamic_zone_data', $_POST);
		
		$raw_contents = file_get_contents($_FILES['import-file']['tmp_name']);
		/** Strip commented lines */
		$clean_contents = preg_replace('/^;.*\n?/m', '', $raw_contents);
		/** Strip blank lines */
		$clean_contents = preg_replace('/^\n?/m', '', $clean_contents);
		/** Strip $GENERATE lines */
		$clean_contents = preg_replace('/^\$GENERATE.*\n?/m', '', $clean_contents, -1, $generate_count);
		/** Strip $ORIGIN lines */
		$clean_contents = preg_replace('/^\$ORIGIN.*\n?/m', '', $clean_contents, -1, $origin_count);
		/** Change tabs into spaces */
		$clean_contents = str_replace("\t", ' ', $clean_contents);
		
		/** Handle unsupported message */
		if ($generate_count || $origin_count) {
			$unsupported[] = sprintf('<h4>%s:</h4>', __('Unsupported Entries'));
			$unsupported[] = '<p class="soa_import">' . sprintf(__('%s currently does not support importing $GENERATE and $ORIGIN entries which were found in your zone file.'), $fm_name) . '</p>';
			$unsupported = implode("\n", $unsupported);
		} else $unsupported = null;
		
		$domain_name = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name');
		$domain_map = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_mapping');

		$count = 1;

		/** Detect SOA */
		if (((!getSOACount($domain_id) || $get_dynamic_zone_data) && strpos($clean_contents, ' SOA ') !== false) &&
			(in_array('SOA', $__FM_CONFIG['records']['require_zone_rights']) && currentUserCan('manage_zones', $_SESSION['module']))) {
			
			$raw_soa = preg_replace_callback("/SOA(.+?)\)/sim", 
					function ($matches) {
						return str_replace(PHP_EOL, ' ', $matches);
					}, $clean_contents);
			preg_match("/SOA(.+?)(\)|\n)/esim", $clean_contents, $raw_soa);
			preg_match("/TTL(.+?)$/esim", $clean_contents, $raw_ttl);
			if (is_array($raw_soa)) {
				$raw_soa = preg_replace('/;(.+?)+/', '', $raw_soa[1]);
				$soa = str_replace(array("\n", "\t", '(', ')', '  '), ' ', preg_replace('/\s\s+/', ' ', $raw_soa));
				$soa = str_replace(' ', '|', trim($soa));
				$soa_fields = explode('|', str_replace('||', '|', $soa));
				
				list($soa_array['soa_master_server'], $soa_array['soa_email_address'], $tmp_serial, $soa_array['soa_refresh'],
					$soa_array['soa_retry'], $soa_array['soa_expire'], $tmp_neg_cache) = $soa_fields;
				
				if (strpos($soa_array['soa_master_server'], $domain_name) !== false) {
					$soa_array['soa_master_server'] = str_replace('.' . trimFullStop($domain_name) . '.', '', $soa_array['soa_master_server']);
					$soa_array['soa_email_address'] = str_replace('.' . trimFullStop($domain_name) . '.', '', $soa_array['soa_email_address']);
					$soa_array['soa_append'] = 'yes';
				} else $soa_array['soa_append'] = 'no';
			}
			$soa_array['soa_ttl'] = (count($raw_ttl)) ? trim(preg_replace('/;(.+?)+/', '', $raw_ttl[1])) : $tmp_neg_cache;
			
			$soa_row = '<h4>SOA:</h4><p class="soa_import">';
			
			if ($get_dynamic_zone_data) {
				$soa_serial_no = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'soa_serial_no');
				if ($tmp_serial <= $soa_serial_no) {
					$soa_row = null;
				} else {
					$soa_row .= "<input type=\"hidden\" name=\"update[$count][soa_serial_no]\" value=\"$tmp_serial\" />" . sprintf('%s: %d', __('Updated serial number'), $tmp_serial);
				}
			} else {
				$soa_row .= trimFullStop($domain_name) . '. IN SOA ' . $soa_array['soa_master_server'];
				if ($soa_array['soa_append'] == 'yes') $soa_row .= '.' . trimFullStop($domain_name) . '.';
				$soa_row .= ' ' . $soa_array['soa_email_address'];
				if ($soa_array['soa_append'] == 'yes') $soa_row .= '.' . trimFullStop($domain_name) . '.';
				$soa_row .= ' ( &lt;autogen_serial&gt; ' . $soa_array['soa_refresh'] . ' ' . $soa_array['soa_retry'] . ' ' . 
						$soa_array['soa_expire'] . ' ' . $soa_array['soa_ttl'] . ' )';

				$soa_row = <<<HTML
						<input type="hidden" name="create[$count][soa_master_server]" value="{$soa_array['soa_master_server']}" />
						<input type="hidden" name="create[$count][soa_email_address]" value="{$soa_array['soa_email_address']}" />
						<input type="hidden" name="create[$count][soa_refresh]" value="{$soa_array['soa_refresh']}" />
						<input type="hidden" name="create[$count][soa_retry]" value="{$soa_array['soa_retry']}" />
						<input type="hidden" name="create[$count][soa_expire]" value="{$soa_array['soa_expire']}" />
						<input type="hidden" name="create[$count][soa_ttl]" value="{$soa_array['soa_ttl']}" />
						<input type="hidden" name="create[$count][record_type]" value="SOA" />
						<input type="hidden" name="create[$count][soa_append]" value="{$soa_array['soa_append']}" />
						$soa_row
						<span><label><input type="checkbox" name="create[$count][record_skip]" />Skip Import</label></span>
HTML;
			}
			
			if ($soa_row) {
				$soa_row .= <<<HTML
						</p>
						
						<h4>Records:</h4>

HTML;

				$count++;
			}
		} else $soa_row = null;

		$clean_contents = str_replace('.' . trimFullStop($domain_name) . '.', '', $clean_contents);
		$clean_contents = str_replace("\n" . trimFullStop($domain_name) . '.', "\n@", $clean_contents);
		$clean_contents = str_replace(trimFullStop($domain_name) . '.', '', $clean_contents);
		
		$available_record_types = array_filter(enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', 'record_type'), 'removeRestrictedRR');
		sort($available_record_types);

		/** Loop through the lines */
		$lines = explode(PHP_EOL, $clean_contents);
		$failed = 0;
		$rows = array();
		$valid_hashes = array(';', '//', '#');
		for ($x = 0; $x < count($lines); $x++) {
			/** Handle multi-line records */
			$line = $lines[$x];
			if (strpos($line, '(') !== false) {
				while (strpos($line, ')') === false) {
					$x++;
					$line .= ' ' . $lines[$x];
				}
				$line = str_replace(array('(', ')'), '', $line);
			}
			
			unset($array);
			$record_action = __('Add');
			
			if (!strlen(trim($line))) continue;
			
			foreach ($valid_hashes as $tmp_hash) {
				if (strpos($line, $tmp_hash) !== false) {
					$hash = $tmp_hash;
					break;
				} else $hash = null;
			}
			if ($hash == '//') $hash = '\/\/';
			
			/** Break up the line for comments */
			if ($hash) {
				$comment_parts = preg_split("/{$hash}+/", $line);
				
				/** Handle semi-colons in record value */
				if ($hash == ';' && strpos($line, '"') !== false) {
					if (strrpos($line, $hash) < strrpos($line, '"')) {
						$comment_parts = array($line, '');
					} else {
						$comment_parts = array(substr($line, 0, strrpos($line, $hash)), substr($line, strrpos($line, $hash) + 1));
					}
				}
				
				$array['record_comment'] = trim($comment_parts[1]) ? trim($comment_parts[1]) : __('none');
			} else {
				$comment_parts[0] = $line;
				$array['record_comment'] = __('none');
			}
			
			/** Break up the line for parts */
			$parts = preg_split('/\s+/', trim($comment_parts[0]));
			
			if (in_array('A', $parts) || in_array('AAAA', $parts) || in_array('CNAME', $parts) || in_array('DNAME', $parts) 
					|| in_array('DHCID', $parts)) {
				foreach (array('A', 'AAAA', 'CNAME', 'DNAME', 'DHCID') as $rr_type) {
					if (in_array($rr_type, $parts)) {
						$key = array_search($rr_type, $parts);
						break;
					}
				}
				switch($key) {
					case 3:
						list($array['record_name'], $array['record_ttl'], $array['record_class'], $array['record_type'], $array['record_value']) = $parts;
						break;
					case 2:
						if (is_numeric($parts[0])) {
							$array['record_name'] = isset($current_name) ? $current_name : '@';
							list($array['record_ttl'], $array['record_class'], $array['record_type'], $array['record_value']) = $parts;
						} else {
							list($array['record_name'], $array['record_class'], $array['record_type'], $array['record_value']) = $parts;
						}
						break;
					case 1:
						$array['record_name'] = isset($current_name) ? $current_name : '@';
						list($array['record_class'], $array['record_type'], $array['record_value']) = $parts;
				}
			} elseif (in_array('CAA', $parts)) {
				switch(array_search('CAA', $parts)) {
					case 3:
						list($array['record_name'], $array['record_ttl'], $array['record_class'], $array['record_type'], $null, $array['record_params'], $array['record_value']) = $parts;
						break;
					case 2:
						if (is_numeric($parts[0])) {
							$array['record_name'] = isset($current_name) ? $current_name : '@';
							list($array['record_ttl'], $array['record_class'], $array['record_type'], $null, $array['record_params'], $array['record_value']) = $parts;
						} else {
							list($array['record_name'], $array['record_class'], $array['record_type'], $null, $array['record_params'], $array['record_value']) = $parts;
						}
						break;
					case 1:
						$array['record_name'] = isset($current_name) ? $current_name : '@';
						list($array['record_class'], $array['record_type'], $null, $array['record_params'], $array['record_value']) = $parts;
				}
				$array['record_value'] = str_replace('"', '', $array['record_value']);
				unset($null);
			} elseif (in_array('CERT', $parts)) {
				switch(array_search('CERT', $parts)) {
					case 3:
						list($array['record_name'], $array['record_ttl'], $array['record_class'], $array['record_type'], $array['record_cert_type'], $array['record_key_tag'], $array['record_algorithm'], $array['record_value']) = $parts;
						break;
					case 2:
						if (is_numeric($parts[0])) {
							$array['record_name'] = isset($current_name) ? $current_name : '@';
							list($array['record_ttl'], $array['record_class'], $array['record_type'], $array['record_cert_type'], $array['record_key_tag'], $array['record_algorithm'], $array['record_value']) = $parts;
						} else {
							list($array['record_name'], $array['record_class'], $array['record_type'], $array['record_cert_type'], $array['record_key_tag'], $array['record_algorithm'], $array['record_value']) = $parts;
						}
						break;
					case 1:
						$array['record_name'] = isset($current_name) ? $current_name : '@';
						list($array['record_class'], $array['record_type'], $array['record_cert_type'], $array['record_key_tag'], $array['record_algorithm'], $array['record_value']) = $parts;
				}
			} elseif (in_array('HINFO', $parts)) {
				switch(array_search('HINFO', $parts)) {
					case 3:
						list($array['record_name'], $array['record_ttl'], $array['record_class'], $array['record_type'], $array['record_value'], $array['record_os']) = $parts;
						break;
					case 2:
						if (is_numeric($parts[0])) {
							$array['record_name'] = isset($current_name) ? $current_name : '@';
							list($array['record_ttl'], $array['record_class'], $array['record_type'], $array['record_value'], $array['record_os']) = $parts;
						} else {
							list($array['record_name'], $array['record_class'], $array['record_type'], $array['record_value'], $array['record_os']) = $parts;
						}
						break;
					case 1:
						$array['record_name'] = isset($current_name) ? $current_name : '@';
						list($array['record_class'], $array['record_type'], $array['record_value'], $array['record_os']) = $parts;
				}
			} elseif (in_array('KX', $parts) || in_array('MX', $parts)) {
				$key = (array_search('MX', $parts)) ? array_search('MX', $parts) : array_search('KX', $parts);
				switch($key) {
					case 3:
						list($array['record_name'], $array['record_ttl'], $array['record_class'], $array['record_type'], $array['record_priority'], $array['record_value']) = $parts;
						break;
					case 2:
						if (is_numeric($parts[0])) {
							$array['record_name'] = isset($current_name) ? $current_name : '@';
							list($array['record_ttl'], $array['record_class'], $array['record_type'], $array['record_priority'], $array['record_value']) = $parts;
						} else {
							list($array['record_name'], $array['record_class'], $array['record_type'], $array['record_priority'], $array['record_value']) = $parts;
						}
						break;
					case 1:
						$array['record_name'] = isset($current_name) ? $current_name : '@';
						list($array['record_class'], $array['record_type'], $array['record_priority'], $array['record_value']) = $parts;
				}
			} elseif (in_array('NAPTR', $parts)) {
				switch(array_search('NAPTR', $parts)) {
					case 3:
						list($array['record_name'], $array['record_ttl'], $array['record_class'], $array['record_type'], $array['record_weight'], $array['record_priority'], $array['record_flags'], $array['record_params'], $array['record_regex'], $array['record_value']) = $parts;
						break;
					case 2:
						if (is_numeric($parts[0])) {
							$array['record_name'] = isset($current_name) ? $current_name : '@';
							list($array['record_ttl'], $array['record_class'], $array['record_type'], $array['record_weight'], $array['record_priority'], $array['record_flags'], $array['record_params'], $array['record_regex'], $array['record_value']) = $parts;
						} else {
							list($array['record_name'], $array['record_class'], $array['record_type'], $array['record_weight'], $array['record_priority'], $array['record_flags'], $array['record_params'], $array['record_regex'], $array['record_value']) = $parts;
						}
						break;
					case 1:
						$array['record_name'] = isset($current_name) ? $current_name : '@';
						list($array['record_class'], $array['record_type'], $array['record_weight'], $array['record_priority'], $array['record_flags'], $array['record_params'], $array['record_regex'], $array['record_value']) = $parts;
				}
				$array['record_regex'] = str_replace('"', '', $array['record_regex']);
				$array['record_params'] = str_replace('"', '', $array['record_params']);
				$array['record_flags'] = str_replace('"', '', $array['record_flags']);
			} elseif (in_array('RP', $parts)) {
				switch(array_search('RP', $parts)) {
					case 3:
						list($array['record_name'], $array['record_ttl'], $array['record_class'], $array['record_type'], $array['record_value'], $array['record_text']) = $parts;
						break;
					case 2:
						if (is_numeric($parts[0])) {
							$array['record_name'] = isset($current_name) ? $current_name : '@';
							list($array['record_ttl'], $array['record_class'], $array['record_type'], $array['record_value'], $array['record_text']) = $parts;
						} else {
							list($array['record_name'], $array['record_class'], $array['record_type'], $array['record_value'], $array['record_text']) = $parts;
						}
						break;
					case 1:
						$array['record_name'] = isset($current_name) ? $current_name : '@';
						list($array['record_class'], $array['record_type'], $array['record_value'], $array['record_text']) = $parts;
				}
			} elseif (in_array('SSHFP', $parts)) {
				switch(array_search('SSHFP', $parts)) {
					case 3:
						list($array['record_name'], $array['record_ttl'], $array['record_class'], $array['record_type'], $array['record_algorithm'], $array['record_cert_type'], $array['record_value']) = $parts;
						break;
					case 2:
						if (is_numeric($parts[0])) {
							$array['record_name'] = isset($current_name) ? $current_name : '@';
							list($array['record_ttl'], $array['record_class'], $array['record_type'], $array['record_algorithm'], $array['record_cert_type'], $array['record_value']) = $parts;
						} else {
							list($array['record_name'], $array['record_class'], $array['record_type'], $array['record_algorithm'], $array['record_cert_type'], $array['record_value']) = $parts;
						}
						break;
					case 1:
						$array['record_name'] = isset($current_name) ? $current_name : '@';
						list($array['record_class'], $array['record_type'], $array['record_algorithm'], $array['record_cert_type'], $array['record_value']) = $parts;
				}
			} elseif (in_array('SRV', $parts)) {
				switch(array_search('SRV', $parts)) {
					case 3:
						list($array['record_name'], $array['record_ttl'], $array['record_class'], $array['record_type'], $array['record_priority'], $array['record_weight'], $array['record_port'], $array['record_value']) = $parts;
						break;
					case 2:
						if (is_numeric($parts[0])) {
							$array['record_name'] = isset($current_name) ? $current_name : '@';
							list($array['record_ttl'], $array['record_class'], $array['record_type'], $array['record_priority'], $array['record_weight'], $array['record_port'], $array['record_value']) = $parts;
						} else {
							list($array['record_name'], $array['record_class'], $array['record_type'], $array['record_priority'], $array['record_weight'], $array['record_port'], $array['record_value']) = $parts;
						}
						break;
					case 1:
						$array['record_name'] = isset($current_name) ? $current_name : '@';
						list($array['record_class'], $array['record_type'], $array['record_priority'], $array['record_weight'], $array['record_port'], $array['record_value']) = $parts;
				}
			} elseif (in_array('TXT', $parts)) {
				$key = array_search('TXT', $parts);
				$txt_record = null;
				for ($i=$key + 1; $i<count($parts); $i++) {
					$txt_record .= $parts[$i] . ' ';
				}
				$parts[$key + 1] = rtrim($txt_record);
				switch($key) {
					case 3:
						list($array['record_name'], $array['record_ttl'], $array['record_class'], $array['record_type'], $array['record_value']) = $parts;
						break;
					case 2:
						if (is_numeric($parts[0])) {
							$array['record_name'] = isset($current_name) ? $current_name : '@';
							list($array['record_ttl'], $array['record_class'], $array['record_type'], $array['record_value']) = $parts;
						} else {
							list($array['record_name'], $array['record_class'], $array['record_type'], $array['record_value']) = $parts;
						}
						break;
					case 1:
						$array['record_name'] = isset($current_name) ? $current_name : '@';
						list($array['record_class'], $array['record_type'], $array['record_value']) = $parts;
				}
				$array['record_value'] = str_replace('"', '', $array['record_value']);
			}
			if (in_array('PTR', $parts)) {
				switch(array_search('PTR', $parts)) {
					case 3:
						list($array['record_name'], $array['record_ttl'], $array['record_class'], $array['record_type'], $array['record_value']) = $parts;
						break;
					case 2:
						if ($parts[0] > 255) {
							$array['record_name'] = isset($current_name) ? $current_name : '@';
							list($array['record_ttl'], $array['record_class'], $array['record_type'], $array['record_value']) = $parts;
						} else {
							list($array['record_name'], $array['record_class'], $array['record_type'], $array['record_value']) = $parts;
						}
						break;
					case 1:
						$array['record_name'] = isset($current_name) ? $current_name : '@';
						list($array['record_class'], $array['record_type'], $array['record_value']) = $parts;
				}
			}
			if (in_array('NS', $parts) && in_array('NS', $__FM_CONFIG['records']['require_zone_rights']) && currentUserCan('manage_zones', $_SESSION['module'])) {
				switch(array_search('NS', $parts)) {
					case 3:
						list($array['record_name'], $array['record_ttl'], $array['record_class'], $array['record_type'], $array['record_value']) = $parts;
						break;
					case 2:
						if (is_numeric($parts[0]) && $parts[0] > 255) {
							$array['record_name'] = isset($current_name) ? $current_name : '@';
							list($array['record_ttl'], $array['record_class'], $array['record_type'], $array['record_value']) = $parts;
						} else {
							list($array['record_name'], $array['record_class'], $array['record_type'], $array['record_value']) = $parts;
						}
						break;
					case 1:
						$array['record_name'] = isset($current_name) ? $current_name : '@';
						list($array['record_class'], $array['record_type'], $array['record_value']) = $parts;
				}
			}
			
			if (empty($array['record_name']) && !empty($array['record_comment'])) continue;
			
			$array['record_append'] = (substr($array['record_value'], -1) == '.') ? 'no' : 'yes';
			
			/** Set current_name to check for blanks on next run */
			$current_name = $array['record_name'];
			
			/** Set ttl to default if empty */
			if (!$array['record_ttl']) $array['record_ttl'] = __('default');
			
			$all_records[] = $array;
			
			/** Automatically skip duplicates */
			$checked = $this->checkDuplicates($array, $domain_id, $all_records);
			
			if (!$get_dynamic_zone_data || ($get_dynamic_zone_data && !$checked)) {
				if (!array_key_exists($array['record_type'], $rows)) {
					$rows[$array['record_type']] = null;
				}
				$rows[$array['record_type']] .= $this->displayImportRow($array, $record_action, $count, $checked);
				$count++;
			}
		}
		
		/** Are there any dynamically deleted records? */
		if ($get_dynamic_zone_data) {
			$sql_records = buildSQLRecords('all', $domain_id);
			
			$delete_array = array();
			foreach ($sql_records as $id => $db_record) {
				$found = false;
				foreach ($all_records as $server_record) {
					if ($db_record['record_name'] == $server_record['record_name'] &&
							$db_record['record_value'] == $server_record['record_value'] &&
							$db_record['record_type'] == $server_record['record_type']) {
						$found = true;
						break;
					}
				}
				if (!$found) {
					$delete_array[$id] = $db_record;
				}
			}
			
			foreach ($delete_array as $key => $delete_record) {
				if (!array_key_exists($delete_record['record_type'], $rows)) {
					$rows[$delete_record['record_type']] = null;
				}
				$rows[$delete_record['record_type']] .= $this->displayImportRow($delete_record, _('Delete'), $key, null);
			}
		}
		
		$table_info = array(
						'class' => 'display_results',
						'id' => 'table_edits',
						'name' => 'views'
					);

		$title_array_front = array(__('Action'), __('Record'), __('TTL'), __('Class'), __('Type'));
		$title_array_end = array(
			_('Comment'),
			array('title' => __('Append Domain'), 'style' => 'text-align: center;', 'nowrap' => null),
			array('title' => __('Actions'), 'class' => 'header-actions')
		);
		
		$popup_header = buildPopup('header', __('Import Verification'));
		$popup_footer = buildPopup('footer', __('Import'), array('import' => 'submit', 'cancel_button' => 'cancel'));
		
		if (count($rows)) {
			ksort($rows);
			$table = null;
			foreach ($rows as $rr_type => $row) {
				switch ($rr_type) {
					case 'CERT':
						$title_array = array(__('Type'), __('Key Tag'), __('Algorithm'), __('Value'));
						break;
					case 'CAA':
						$title_array = array(__('Flags'), __('Value'));
						break;
					case 'HINFO':
						$title_array = array(__('Hardware'), __('OS'));
						break;
					case 'KX':
					case 'MX':
						$title_array = array(__('Priority'), __('Value'));
						break;
					case 'NAPTR':
						$title_array = array(__('Order'), __('Pref'), __('Flags'), __('Params'), __('Regex'), __('Replace'));
						break;
					case 'RP':
						$title_array = array(__('Value'), __('Text'));
						break;
					case 'SSHFP':
						$title_array = array(__('Algorithm'), __('Type'), __('Value'));
						break;
					case 'SRV':
						$title_array = array(__('Priority'), __('Value'), __('Weight'), __('Port'));
						break;
					default:
						$title_array = array(__('Value'));
						break;
				}
				$table .= sprintf('%s
						%s
						</tbody>
						</table>
						<br />',
							displayTableHeader($table_info, array_merge($title_array_front, $title_array, $title_array_end)),
							$row
						);
			}
			$body = <<<BODY
		<form method="post" action="zone-records-write.php">
		$popup_header
			<p>Domain: $domain_name</p>
			<input type="hidden" name="domain_id" value="{$domain_id}">
			<input type="hidden" name="map" value="$domain_map">
			<input type="hidden" name="import_records" value="true">
			<input type="hidden" name="import_file" value="{$_FILES['import-file']['name']}">
			$unsupported
			$soa_row
			$table
		$popup_footer
		</form>
BODY;
		} else {
			$body = sprintf('%s<p>%s</p>%s', $popup_header, __('There are no records found.'), buildPopup('footer', _('OK'), array('cancel_button' => 'cancel')));
		}

		return $body;
		
	}
	
	/**
	 * Checks for duplicate entries during import process
	 */
	function checkDuplicates($array, $domain_id, $all_records) {
		global $fmdb, $__FM_CONFIG;
		
		$domain_ids = join(',', getZoneParentID($domain_id));
		$sql_select = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` WHERE `record_status`!='deleted' AND `domain_id` IN ($domain_ids) AND ";
		
		foreach ($array as $key => $data) {
			if (!in_array($key, array('record_comment', 'record_ttl'))) {
				$data = sanitize($data);
				$sql_select .= ($data) ? "$key='$data' AND " : "($key='' OR $key IS NULL) AND ";
			}
		}
		$sql_select = rtrim($sql_select, ' AND ');
		
		$result = $fmdb->query($sql_select);
		
		if ($fmdb->num_rows) return 'checked';
		
		/** Check for duplicate RR in database */
		$query = "SELECT DISTINCT `record_type` FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}records WHERE `record_status`='active' AND account_id='{$_SESSION['user']['account_id']}' AND `domain_id` IN ($domain_ids) AND `record_name`='{$array['record_name']}'";
		$fmdb->query($query);
		
		if ($fmdb->num_rows) {
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$types[] = $fmdb->last_result[$i]->record_type;
			}
			/** Duplicate RR in the database already */
			if (in_array('CNAME', $types)) return 'checked disabled';
		}
		
		unset($types);
		
		/** Duplicate RR in the imported zone file */
		foreach ($all_records as $tmp_array) {
			foreach ($tmp_array as $key => $val) {
				if ($key == 'record_type' && $tmp_array['record_name'] == $array['record_name']) {
					$types[] = $val;
				}
			}
		}
		if (count($types)) {
			array_unique($types);
			if (count($types) > 1 && in_array('CNAME', $types)) return 'checked disabled';
		}
		
		return null;
	}

	/**
	 * Tests server connectivity
	 */
	function moduleConnectTests($server_data) {
		global $fmdb, $__FM_CONFIG;
		
		/** dns tests */
		$connect_test = "\t" . str_pad(__('DNS:'), 15);
		$port = 53;
		if (socketTest($server_data->server_name, $port, 10)) $connect_test .=  _('success') . ' (tcp/' . $port . ')';
		else $connect_test .=  _('failed') . ' (tcp/' . $port . ')';
		$connect_test .=  "\n";
		
		return $connect_test;
	}
	
	
	function unAuth($message) {
		$response = buildPopup('header', _('Error'));
		$response .= sprintf('<p>%s</p>', sprintf(__('You do not have permission to access this %s.'), $message));
		return $response . buildPopup('footer', _('OK'), array('cancel_button' => 'cancel'));
	}
	
	
	/**
	 * Displays the import record rows
	 * 
	 * @since 3.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param array $array Record details
	 * @param string $action Action to take on the record
	 * @param integer $id Unique ID
	 * @param string $checked Check the tickbox
	 * @return boolean
	 */
	function displayImportRow($array, $action, $id, $checked = null) {
		$editable = ($action == _('Delete')) ? false : true;
		$db_action = ($action == __('Add')) ? 'create' : 'update';
		
		$row = '<tr class="import_swap';
		if (!$editable) $row .= ' attention';
		if ($checked) $row .= ' disabled';
		$row .='">' . "\n";
		$row .= "<td>$action</td>\n<td>";
		if (!$editable) {
			$row .= '<input type="hidden" name="record_type" value="' . $array['record_type'] . '" />';
		}
		$row .= $editable ? '<span id="name' . $id . '" onclick="exchange(this);">' . $array['record_name'] . '</span><input onblur="exchange(this);" type="text" id="name' . $id . 'b" name="' . $db_action . '[' . $id . '][record_name]" value="' . $array['record_name'] . '" />' : $array['record_name'];
		$row .= "</td>\n<td>";
		$row .= $editable ? '<span id="ttl' . $id . '" onclick="exchange(this);">' . $array['record_ttl'] . '</span><input onblur="exchange(this);" type="text" id="ttl' . $id . 'b" name="' . $db_action . '[' . $id . '][record_ttl]" value="' . $array['record_ttl'] . '" />' : $array['record_ttl'];
		$row .= "</td>\n";
		$row .= '<td><input type="hidden" name="' . $db_action . '[' . $id . '][record_class]" value="' . $array['record_class'] . '" />' . $array['record_class'] . '</td>' . "\n";
		$row .= '<td><input type="hidden" name="' . $db_action . '[' . $id . '][record_type]" value="' . $array['record_type'] . '" />' . $array['record_type'] . "</td>\n";
		if ($array['record_type'] == 'NAPTR') {
			$row .= '<td>';
			$row .= $editable ? '<span id="weight' . $id . '" onclick="exchange(this);">' . $array['record_weight'] . '</span><input onblur="exchange(this);" type="text" id="weight' . $id . 'b" name="' . $db_action . '[' . $id . '][record_weight]" value="' . $array['record_weight'] . '" />' : $array['record_weight'];
			$row .= "</td>\n";
			$row .= '<td>';
			$row .= $editable ? '<span id="priority' . $id . '" onclick="exchange(this);">' . $array['record_priority'] . '</span><input onblur="exchange(this);" type="text" id="priority' . $id . 'b" name="' . $db_action . '[' . $id . '][record_priority]" value="' . $array['record_priority'] . '" />' : $array['record_priority'];
			$row .= "</td>\n";
			$row .= '<td>';
			$row .= $editable ? '<span id="flags' . $id . '" onclick="exchange(this);">' . $array['record_flags'] . '</span><input onblur="exchange(this);" type="text" id="flags' . $id . 'b" name="' . $db_action . '[' . $id . '][record_flags]" value="' . $array['record_flags'] . '" />' : $array['record_flags'];
			$row .= "</td>\n";
			$row .= '<td>';
			$row .= $editable ? '<span id="params' . $id . '" onclick="exchange(this);">' . $array['record_params'] . '</span><input onblur="exchange(this);" type="text" id="params' . $id . 'b" name="' . $db_action . '[' . $id . '][record_params]" value="' . $array['record_params'] . '" />' : $array['record_params'];
			$row .= "</td>\n";
			$row .= '<td>';
			$row .= $editable ? '<span id="regex' . $id . '" onclick="exchange(this);">' . $array['record_regex'] . '</span><input onblur="exchange(this);" type="text" id="regex' . $id . 'b" name="' . $db_action . '[' . $id . '][record_regex]" value="' . $array['record_regex'] . '" />' : $array['record_regex'];
			$row .= "</td>\n";
		}
		if (array_key_exists('record_priority', $array) && $array['record_type'] != 'NAPTR') {
			$row .= '<td>';
			$row .= $editable ? '<span id="priority' . $id . '" onclick="exchange(this);">' . $array['record_priority'] . '</span><input onblur="exchange(this);" type="text" id="priority' . $id . 'b" name="' . $db_action . '[' . $id . '][record_priority]" value="' . $array['record_priority'] . '" />' : $array['record_priority'];
			$row .= "</td>\n";
		}
		if (array_key_exists('record_cert_type', $array)) {
			$row .= '<td><input type="hidden" name="' . $db_action . '[' . $id . '][record_cert_type]" value="' . $array['record_cert_type'] . '" />' . $array['record_cert_type'] . "</td>\n";
		}
		if (array_key_exists('record_key_tag', $array)) {
			$row .= '<td><input type="hidden" name="' . $db_action . '[' . $id . '][record_key_tag]" value="' . $array['record_key_tag'] . '" />' . $array['record_key_tag'] . "</td>\n";
		}
		if (array_key_exists('record_algorithm', $array)) {
			$row .= '<td><input type="hidden" name="' . $db_action . '[' . $id . '][record_algorithm]" value="' . $array['record_algorithm'] . '" />' . $array['record_algorithm'] . "</td>\n";
		}
		if (array_key_exists('record_params', $array) && $array['record_type'] != 'NAPTR') {
			$row .= '<td>';
			$row .= $editable ? '<span id="flags' . $id . '" onclick="exchange(this);">' . $array['record_params'] . '</span><input onblur="exchange(this);" type="text" id="flags' . $id . 'b" name="' . $db_action . '[' . $id . '][record_params]" value="' . $array['record_params'] . '" />' : $array['record_params'];
			$row .= "</td>\n";
		}
		$row .= '<td>';
		$row .= $editable ? '<span id="value' . $id . '" onclick="exchange(this);">' . $array['record_value'] . '</span><input onblur="exchange(this);" type="text" id="value' . $id . 'b" name="' . $db_action . '[' . $id . '][record_value]" value="' . $array['record_value'] . '" />' : $array['record_value'];
		$row .= "</td>\n";
		if (array_key_exists('record_weight', $array) && $array['record_type'] != 'NAPTR') {
			$row .= '<td>';
			$row .= $editable ? '<span id="weight' . $id . '" onclick="exchange(this);">' . $array['record_weight'] . '</span><input onblur="exchange(this);" type="text" id="weight' . $id . 'b" name="' . $db_action . '[' . $id . '][record_weight]" value="' . $array['record_weight'] . '" />' : $array['record_weight'];
			$row .= "</td>\n";
		}
		if (array_key_exists('record_port', $array)) {
			$row .= '<td>';
			$row .= $editable ? '<span id="port' . $id . '" onclick="exchange(this);">' . $array['record_port'] . '</span><input onblur="exchange(this);" type="text" id="port' . $id . 'b" name="' . $db_action . '[' . $id . '][record_port]" value="' . $array['record_port'] . '" />' : $array['record_port'];
			$row .= "</td>\n";
		}
		if (array_key_exists('record_text', $array)) {
			$row .= '<td>';
			$row .= $editable ? '<span id="text' . $id . '" onclick="exchange(this);">' . $array['record_text'] . '</span><input onblur="exchange(this);" type="text" id="text' . $id . 'b" name="' . $db_action . '[' . $id . '][record_text]" value="' . $array['record_text'] . '" />' : $array['record_text'];
			$row .= "</td>\n";
		}
		if (array_key_exists('record_os', $array)) {
			$row .= '<td>';
			$row .= $editable ? '<span id="os' . $id . '" onclick="exchange(this);">' . $array['record_os'] . '</span><input onblur="exchange(this);" type="text" id="os' . $id . 'b" name="' . $db_action . '[' . $id . '][record_os]" value="' . $array['record_os'] . '" />' : $array['record_os'];
			$row .= "</td>\n";
		}
		$row .= "</td>\n<td>";
		$row .= $editable ? '<span id="comment' . $id . '" onclick="exchange(this);">' . $array['record_comment'] . '</span><input onblur="exchange(this);" type="text" id="comment' . $id . 'b" name="' . $db_action . '[' . $id . '][record_comment]" value="' . $array['record_comment'] . '" />' : $array['record_comment'];
		$row .= (!$editable) ? '<input type="hidden" name="update[' . $id . '][record_status]" value="deleted" />' : null;
		$row .= "</td>\n";
		$row .= '<td style="text-align: center;"><input type="hidden" name="' . $db_action . '[' . $id . '][record_append]" value="' . $array['record_append'] . '" />' . $array['record_append'] . "</td>\n";
		$row .= '<td><label><input type="checkbox" class="import_skip" name="' . $db_action . '[' . $id . '][record_skip]" ' . $checked . ' />' . __('Skip Import') . '</label>';
		$row .= ($editable && $array['record_type'] == 'A') ? '<br /><label><input type="checkbox" name="' . $db_action . '[' . $id . '][PTR]" />' . __('Create PTR') . '</label> <a href="#" class="tooltip-left" data-tooltip="' . __('This will only work if the reverse zone is already created or the Create Reverse Zones Automatically setting is enabled') . '"><i class="fa fa-question-circle"></i></a>' : null;
		$row .= "</td>\n";
		$row .= "</tr>\n";

		return $row;
	}
	
	/**
	 * Imports zones and records from a BIND-compatible dump file
	 *
	 * @since 3.0
	 * @package fmDNS
	 *
	 * @return boolean
	 */
	function bulkZoneImportWizard() {
		global $__FM_CONFIG, $fmdb, $fm_name, $fm_dns_views, $fm_dns_zones, $fm_dns_records;
		
		if (!currentUserCan(array('manage_zones', 'manage_records'), $_SESSION['module'])) return $this->unAuth('zone');
		
		$popup_header = buildPopup('header', __('Bulk Zone Import'));
		$popup_footer = buildPopup('footer', _('OK'), array('cancel_button' => 'cancel'));
		
		$message = sprintf('<p>%s</p>', __('Bulk zone import complete.'));
		$view_name = $ttl = null;
		$view_id = 0;
		
		$file = fopen($_FILES['import-file']['tmp_name'], 'r');
		while (!feof($file)){
			$line = fgets($file);
			
			if (strpos($line, '.bind/CH') !== false || strpos($line, '.server/CH') !== false) {
				continue;
			}
			
			/** Handle view creations */
			if (strpos($line, 'Start view') !== false) {
				$view_name = trim(str_replace('; Start view ', '', $line));
				
				if (!$view_id = getNameFromID($view_name, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_name', 'view_id')) {
					/** Create the view */
					if (!class_exists('fm_dns_views')) {
						include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_views.php');
					}
					$view_create_result = $fm_dns_views->add(array('view_name'=>$view_name, 'view_comment'=>'', 'server_serial_no'=>0));
					if ($view_create_result !== true) {
						$message = sprintf(__('Import failed to create the %s view. %s'), $view_name, $view_create_result);
						break;
					} else {
						$view_id = getNameFromID($view_name, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_name', 'view_id');
					}
				}
			}
			
			/** Handle zone creations */
			if (strpos($line, 'Zone dump') !== false) {
				$domain_name = strtolower(trim(str_replace(array("; Zone dump of '", "/IN'", "/IN/$view_name'"), '', $line)));
				
				basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $domain_name, 'domain_', 'domain_name', "AND domain_view='$view_id'");
				
				if (!$fmdb->num_rows) {
					$domain_mapping = (strpos($domain_name, 'in-addr.arpa') !== false || strpos($domain_name, 'ip6.arpa') !== false) ? 'reverse' : 'forward';
					
					/** Create the zone */
					if (!class_exists('fm_dns_zones')) {
						include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_zones.php');
					}
					$domain_id = $fm_dns_zones->add(array('domain_name'=>$domain_name, 'domain_mapping'=>$domain_mapping, 'domain_view'=>$view_id, 'domain_name_servers'=>0));
					if (!is_int($domain_id)) {
						$message = sprintf(__('Import failed to create the %s zone. %s'), $domain_name, $domain_id);
						break;
					}
				} else {
					$domain_id = $fmdb->last_result[0]->domain_id;
				}
				
				$ttl = null;
			}
			
			
			/** Handle record creations */
			if (!class_exists('fm_dns_records')) {
				include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_records.php');
			}
			
			if (strpos($line, 'IN SOA') !== false) {
				if (!getSOACount($domain_id)) {
					$soa = explode('IN SOA', $line);
					$soa_fields = preg_split('/\s+/', trim($soa[1]));

					list($soa_array['soa_master_server'], $soa_array['soa_email_address'], $soa_serial_no, $soa_array['soa_refresh'],
						$soa_array['soa_retry'], $soa_array['soa_expire'], $soa_array['soa_ttl']) = $soa_fields;
					$ttl = $soa_array['soa_ttl'];
					$soa_array['soa_append'] = 'no';

					$fm_dns_records->add($domain_id, 'SOA', $soa_array);

					/** Set SOA serial number from import */
					if (!class_exists('fm_dns_zones')) {
						include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_zones.php');
					}
					$fm_dns_zones->updateSOASerialNo($domain_id, $soa_serial_no, 'static');

					unset($soa);
					unset($soa_fields);
					unset($soa_array);
				} else {
					$query = "SELECT * FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}domains d, fm_{$__FM_CONFIG['fmDNS']['prefix']}soa s WHERE 
						domain_status='active' AND d.account_id='{$_SESSION['user']['account_id']}' AND s.account_id='{$_SESSION['user']['account_id']}'
						AND s.soa_id=d.soa_id AND d.domain_id='$domain_id'";
					$fmdb->get_results($query);
					if ($fmdb->num_rows) {
						$ttl = $fmdb->last_result[0]->soa_ttl;
					}
				}
			} elseif (strpos($line, ' IN ') !== false) {
				$line = str_replace('.' . trimFullStop($domain_name) . '.', '', $line);
				$rr_fields = preg_split('/\s+/', trim($line));
				
				$rr['record_name'] = $rr_fields[0];
				$rr['record_name'] = str_replace(trimFullStop($domain_name) . '.', '', $rr['record_name']);
				if (!strlen($rr['record_name'])) {
					unset($rr['record_name']);
				}
				if ($rr_fields[1] != $ttl) {
					$rr['record_ttl'] = $rr_fields[1];
				}
				$rr['record_class'] = $rr_fields[2];
				$rr['record_type'] = $rr_fields[3];
				$rr['record_value'] = $rr_fields[4];
				
				/** Process specific RRs */
				switch ($rr['record_type']) {
					case 'CAA':
						$rr['record_params'] = $rr_fields[5];
						$rr['record_value'] = $rr_fields[6];
						break;
					case 'CERT':
						$rr['record_cert_type'] = $rr_fields[4];
						$rr['record_key_tag'] = $rr_fields[5];
						$rr['record_algorithm'] = $rr_fields[6];
						$txt_record = null;
						for ($i=7; $i<count($rr_fields); $i++) {
							$txt_record[] = $rr_fields[$i];
						}
						$rr['record_value'] = join("\n", $txt_record);
						break;
					case 'DHCID':
						$txt_record = null;
						for ($i=4; $i<count($rr_fields); $i++) {
							$txt_record[] = $rr_fields[$i];
						}
						$rr['record_value'] = join("\n", $txt_record);
						break;
					case 'DLV':
						$rr['record_key_tag'] = $rr_fields[4];
						$rr['record_algorithm'] = $rr_fields[5];
						$txt_record = null;
						for ($i=6; $i<count($rr_fields); $i++) {
							$txt_record[] = $rr_fields[$i];
						}
						$rr['record_value'] = join("\n", $txt_record);
						break;
					case 'DS':
						$rr['record_key_tag'] = $rr_fields[4];
						$rr['record_algorithm'] = $rr_fields[5];
						$rr['record_cert_type'] = $rr_fields[6];
						$txt_record = null;
						for ($i=7; $i<count($rr_fields); $i++) {
							$txt_record[] = $rr_fields[$i];
						}
						$rr['record_value'] = join("\n", $txt_record);
						break;
					case 'DNSKEY':
					case 'KEY':
						$rr['record_flags'] = $rr_fields[4];
						$rr['record_algorithm'] = $rr_fields[6];
						$txt_record = null;
						for ($i=7; $i<count($rr_fields); $i++) {
							$txt_record[] = $rr_fields[$i];
						}
						$rr['record_value'] = join("\n", $txt_record);
						break;
					case 'HINFO':
						$rr['record_os'] = $rr_fields[5];
						break;
					case 'KX':
					case 'MX':
						$rr['record_priority'] = $rr_fields[4];
						$rr['record_value'] = $rr_fields[5];
						break;
					case 'NAPTR':
						$rr['record_weight'] = $rr_fields[4];
						$rr['record_priority'] = $rr_fields[5];
						$rr['record_flags'] = $rr_fields[6];
						$rr['record_params'] = str_replace('"', '', $rr_fields[7]);
						$rr['record_regex'] = str_replace('"', '', $rr_fields[8]);
						$rr['record_value'] = $rr_fields[9];
						break;
					case 'RP':
						$rr['record_text'] = $rr_fields[5];
						break;
					case 'SSHFP':
						$rr['record_algorithm'] = $rr_fields[4];
						$rr['record_value'] = $rr_fields[6];
						break;
					case 'SRV':
						$rr['record_priority'] = $rr_fields[4];
						$rr['record_weight'] = $rr_fields[5];
						$rr['record_port'] = $rr_fields[6];
						$rr['record_value'] = $rr_fields[7];
						break;
					case 'TXT':
						$txt_record = null;
						for ($i=4; $i<count($rr_fields); $i++) {
							$txt_record .= $rr_fields[$i] . ' ';
						}
						$rr['record_value'] = rtrim($txt_record);
						break;
				}
				
				if (substr($rr['record_value'], -1) == '.') {
					$rr['record_append'] = 'no';
				}
				$rr['record_value'] = str_replace('"', '', $rr['record_value']);
				
				$fm_dns_records->add($domain_id, $rr['record_type'], $rr);

				unset($rr);
				unset($rr_fields);
			}
		}
		fclose($file);

		return $popup_header . $message . $popup_footer;
	}

}

if (!isset($fm_module_tools))
	$fm_module_tools = new fm_module_tools();

?>
