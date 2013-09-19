<?php

class fm_dns_tools {
	
	/**
	 * Imports records from a zone file and presents a confirmation
	 */
	function zoneImportWizard() {
		global $__FM_CONFIG;
		
		$raw_contents = file_get_contents($_FILES['import-file']['tmp_name']);
		/** Strip commented lines */
		$clean_contents = preg_replace('/^;.*\n?/m', '', $raw_contents);
		/** Strip blank lines */
		$clean_contents = preg_replace('/^\n?/m', '', $clean_contents);
		/** Strip $GENERATE lines */
		$clean_contents = preg_replace('/^\$GENERATE.*\n?/m', '', $clean_contents);
		
		$domain_name = getNameFromID($_POST['domain_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name');
		$domain_map = getNameFromID($_POST['domain_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_mapping');

		$count = 1;

		/** Detect SOA */
		if (!getSOACount($_POST['domain_id']) && strpos($clean_contents, ' SOA ') !== false) {
			$raw_soa = preg_replace("/SOA(.+?)\)/esim", "str_replace(PHP_EOL, ' ', '\\1')", $clean_contents);
			preg_match("/SOA(.+?)\)/esim", $clean_contents, $raw_soa);
			preg_match("/TTL(.+?)$/esim", $clean_contents, $raw_ttl);
			if (is_array($raw_ttl)) {
				$soa_array['soa_ttl'] = trim(preg_replace('/;(.+?)+/', '', $raw_ttl[1]));
			}
				var_dump($raw_soa);
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
			
			$soa_row = '<h4>SOA:</h4><p class="soa_import">' . trimFullStop($domain_name) . '. IN SOA ' . $soa_array['soa_master_server'];
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
						<span><label><input style="height: 10px;" type="checkbox" name="create[$count][record_skip]" />Skip Import</label></span>
						</p>
						
						<h4>Records:</h4>

HTML;

			$count++;
		} else $soa_row = null;

		$clean_contents = str_replace('.' . trimFullStop($domain_name) . '.', '', $clean_contents);
		$clean_contents = str_replace(trimFullStop($domain_name) . '.', '', $clean_contents);

		/** Loop through the lines */
		$lines = explode(PHP_EOL, $clean_contents);
		$failed = 0;
		$rows = null;
		$valid_hashes = array(';', '//', '#');
		foreach ($lines as $line) {
			$null_keys = array('record_ttl', 'record_priority', 'record_weight', 'record_port');
			foreach ($null_keys as $key) {
				$array[$key] = null;
			}
			if (!strlen(trim($line))) continue;
			
			foreach ($valid_hashes as $tmp_hash) {
				if (strpos($line, $tmp_hash)) {
					$hash = $tmp_hash;
					break;
				} else $hash = null;
			}
			if ($hash == '//') $hash = '\/\/';
			
			/** Break up the line for comments */
			if ($hash) {
				$comment_parts = preg_split("/{$hash}+/", $line);
				$array['record_comment'] = trim($comment_parts[1]) ? trim($comment_parts[1]) : 'none';
			} else {
				$comment_parts[0] = $line;
				$array['record_comment'] = 'none';
			}
			
			/** Break up the line for parts */
			$parts = preg_split('/\s+/', trim($comment_parts[0]));
			
			if ($domain_map == 'forward') {
				if (in_array('MX', $parts)) {
					switch(array_search('MX', $parts)) {
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
				} elseif (in_array('A', $parts) || in_array('CNAME', $parts)) {
					$key = (in_array('A', $parts)) ? array_search('A', $parts) : array_search('CNAME', $parts);
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
				}
			} else {
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
			}
			if (in_array('NS', $parts)) {
				switch(array_search('NS', $parts)) {
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
			}
			
			if (empty($array['record_name']) && !empty($array['record_comment'])) continue;
			
			$array['record_append'] = (substr($array['record_value'], -1) == '.') ? 'no' : 'yes';
			
			/** Set current_name to check for blanks on next run */
			$current_name = $array['record_name'];
			
			/** Automatically skip duplicates */
			$checked = $this->checkDuplicates($array, $_POST['domain_id']);
			
			$class = buildSelect('create[' . $count . '][record_class]', 'class' . $count . 'b', enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', 'record_class'), $array['record_class'], 1, null, false, 'exchange(this);');
			$type = buildSelect('create[' . $count . '][record_type]', 'type' . $count . 'b', enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', 'record_type'), $array['record_type'], 1, null, false, 'exchange(this);');

			$rows .= <<<ROW
					<tr class="import_swap">
						<td><span id="name{$count}" onclick="exchange(this);">{$array['record_name']}</span><input onblur="exchange(this);" type="text" id="name{$count}b" name="create[$count][record_name]" value="{$array['record_name']}" /></td>
						<td><span id="ttl{$count}" onclick="exchange(this);">{$array['record_ttl']}</span><input onblur="exchange(this);" type="number" id="ttl{$count}b" name="create[$count][record_ttl]" value="{$array['record_ttl']}" /></td>
						<td><span id="class{$count}" onclick="exchange(this);">{$array['record_class']}</span>$class</td>
						<td><span id="type{$count}" onclick="exchange(this);">{$array['record_type']}</span>$type</td>
						<td><span id="priority{$count}" onclick="exchange(this);">{$array['record_priority']}</span><input onblur="exchange(this);" type="number" id="priority{$count}b" name="create[$count][record_priority]" value="{$array['record_priority']}" /></td>
						<td><span id="value{$count}" onclick="exchange(this);">{$array['record_value']}</span><input onblur="exchange(this);" type="text" id="value{$count}b" name="create[$count][record_value]" value="{$array['record_value']}" /></td>
						<td><span id="weight{$count}" onclick="exchange(this);">{$array['record_weight']}</span><input onblur="exchange(this);" type="number" id="weight{$count}b" name="create[$count][record_weight]" value="{$array['record_weight']}" /></td>
						<td><span id="port{$count}" onclick="exchange(this);">{$array['record_port']}</span><input onblur="exchange(this);" type="number" id="port{$count}b" name="create[$count][record_port]" value="{$array['record_port']}" /></td>
						<td><span id="comment{$count}" onclick="exchange(this);">{$array['record_comment']}</span><input onblur="exchange(this);" type="text" id="comment{$count}b" name="create[$count][record_comment]" value="{$array['record_comment']}" /></td>
						<td style="text-align: center;" nowrap><input type="hidden" name="create[$count][record_append]" value="{$array['record_append']}" />{$array['record_append']}</td>
						<td style="text-align: center;"><label><input style="height: 10px;" type="checkbox" name="create[$count][record_skip]" $checked />Skip Import</label></td>
					</tr>

ROW;
			$count++;
		}
		
		$body = <<<BODY
<h2>Import Verification</h2>
		<form method="post" action="zone-recordswrite">
			<input type="hidden" name="domain_id" value="{$_POST['domain_id']}">
			<input type="hidden" name="map" value="$domain_map">
			<input type="hidden" name="import_records" value="true">
			<input type="hidden" name="import_file" value="{$_FILES['import-file']['name']}">
			$soa_row
			<table class="display_results">
				<thead>
					<tr>
						<th>Record</th>
						<th>TTL</th>
						<th>Class</th>
						<th>Type</th>
						<th>Priority</th>
						<th>Value</th>
						<th>Weight</th>
						<th>Port</th>
						<th>Comment</th>
						<th style="text-align: center;">Append Domain</th>
						<th style="text-align: center;">Actions</th>
					</tr>
				</thead>
				<tbody>
				$rows
				</tbody>
			</table>
			<br /><input id="import" type="submit" value="Import" class="button" /> <input id="cancel" name="cancel" type="submit" value="Cancel" class="button" id="cancel_button" />
		</form>
BODY;

		return $body;
		
	}
	
	/**
	 * Checks for duplicate entries during import process
	 */
	function checkDuplicates($array, $domain_id) {
		global $fmdb, $__FM_CONFIG;
		
		$sql_select = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` WHERE record_status!='deleted' AND domain_id='$domain_id' AND ";
		
		foreach ($array as $key => $data) {
			if ($key == 'record_comment' && $data == 'none') {
				$data = null;
			}
			$sql_select .= "$key='" . mysql_real_escape_string($data) . "' AND ";
		}
		$sql_select = rtrim($sql_select, ' AND ');
		
		$result = $fmdb->query($sql_select);
		
		if ($fmdb->num_rows) return 'checked';
		
		return null;
	}

	/**
	 * Tests server connectivity
	 */
	function connectTests() {
		global $fmdb, $__FM_CONFIG;
		
		$return = null;
		
		/** Get server list */
		$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_name', 'server_');
		
		/** Process server list */
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;
		for ($x=0; $x<$num_rows; $x++) {
			$return .= 'Running tests for ' . $results[$x]->server_name . "\n";
			
			/** ping tests */
			$return .= "\tPing:\t\t\t";
			if (pingTest($results[$x]->server_name)) $return .=  'success';
			else $return .=  'failed';
			$return .=  "\n";

			/** http(s) tests */
			$return .= "\thttp(s):\t\t";
			if ($results[$x]->server_update_method != 'cron') {
				if (socketTest($results[$x]->server_name, $results[$x]->server_update_port, 10)) {
					$return .= 'success (tcp/' . $results[$x]->server_update_port . ")\n";
					
					/** php tests */
					$return .= "\thttp page:\t\t";
					$php_result = getPostData($results[$x]->server_update_method . '://' . $results[$x]->server_name . '/' .
								$_SESSION['module'] . '/reload.php', null);
					if ($php_result == 'Incorrect parameters defined.') $return .= 'success';
					else $return .= 'failed';
					
				} else $return .=  'failed (tcp/' . $results[$x]->server_update_port . ')';
			} else $return .= 'skipping (host updates via cron)';
			$return .=  "\n";
			
			/** dns tests */
			$return .= "\tDNS:\t\t\t";
			$port = 53;
			if (socketTest($results[$x]->server_name, $port, 10)) $return .=  'success (tcp/' . $port . ')';
			else $return .=  'failed (tcp/' . $port . ')';
			$return .=  "\n";

			$return .=  "\n";
		}
		
		return $return;
	}
	
}

if (!isset($fm_dns_tools))
	$fm_dns_tools = new fm_dns_tools();

?>