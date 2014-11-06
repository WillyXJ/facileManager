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
 | Processes zone record updates                                           |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

include(ABSPATH . 'fm-modules/fmDNS/classes/class_records.php');

if (empty($_POST)) header('Location: ' . $GLOBALS['RELPATH']);
extract($_POST);

/** Should the user be here? */
if (!currentUserCan('manage_records', $_SESSION['module'])) unAuth();
if (!currentUserCan('access_specific_zones', $_SESSION['module'], array(0, $domain_id))) unAuth();
if (in_array($record_type, $__FM_CONFIG['records']['require_zone_rights']) && !currentUserCan('manage_zones', $_SESSION['module'])) unAuth();

/** Make sure we can handle all of the variables */
checkMaxInputVars();

$domain_info['id']       = $domain_id;
$domain_info['name']     = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name');
$domain_info['map']      = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_mapping');
$domain_info['clone_of'] = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_clone_domain_id');

if (isset($_POST['update'])) {
	if ($_POST['update']['soa_template_chosen']) {
		global $fm_dns_records;
		/** Save the soa_template_chosen in domains table and end */
		include_once(ABSPATH . 'fm-modules/fmDNS/classes/class_records.php');
		$fm_dns_records->assignSOA($_POST['update']['soa_template_chosen'], $domain_id);
		header('Location: zone-records.php?map=' . $_POST['map'] . '&domain_id=' . $domain_id . '&record_type=SOA');
	}
	
	$_POST['update'] = buildUpdateArray($domain_id, $record_type, $_POST['update']);
}

$table_info = array('class' => 'display_results');
$header_array = $fm_dns_records->getHeader(strtoupper($record_type));
$header = displayTableHeader($table_info, $header_array);

$body = null;
foreach($_POST as $name => $array) {
	if (in_array($name, array('create', 'update'))) $body .= createOutput($domain_info, $record_type, $array, $name, $header_array);
}

printHeader();
@printMenu();

echo <<<HTML
<div id="body_container">
	<h2>Record Validation</h2>
	<form method="POST" action="zone-records-write.php">
	<input type="hidden" name="domain_id" value="$domain_id">
	<input type="hidden" name="record_type" value="$record_type">
	<input type="hidden" name="map" value="$map">
	<table class="display_results">
		<thead>
			<tr>
				$header
			</tr>
		</thead>
		<tbody>
			$body
		</tbody>
	</table>
	<p>
		<input type="reset" value="Back" onClick="history.go(-1)" class="button" />
		<input type="submit" name="submit" value="Submit" class="button primary" />
	</p>
</form>
</div>
HTML;

printFooter();


function createOutput($domain_info, $record_type, $data_array, $type, $header_array) {
	global $__FM_CONFIG;
	
	$html = null;
	
	extract($domain_info, EXTR_PREFIX_ALL, 'domain');
	
	/** Skips only allowed with clone zones and imports */
	$skips_allowed = ($type == 'update' && $domain_clone_of) ? true : false;
	
	foreach ($data_array as $id => $data) {
		if (!is_array($data)) continue;
		if (isset($data['Delete'])) {
			$action = 'Delete';
			$html .= buildInputReturn('update', $id ,'record_status', 'deleted');
			$value[$id] = $data;
		} elseif (array_key_exists('record_skipped', $data) && $skips_allowed) {
			if ($data['record_skipped'] == 'on') {
				$action = 'Skip Import';
				$html.= buildInputReturn('skip', $id ,'record_status', 'active');
			} else {
				$action = 'Include';
				$html.= buildInputReturn('skip', $id ,'record_status', 'deleted');
			}
			$value[$id] = $data;
		} else {
			$action = ucfirst($type);
			list($valid_data, $valid_html, $input_error[$id]) = validateEntry($type, $id, $data, $record_type);
			if (!isset($input_error[$id])) unset($input_error[$id]);
			$html .= $valid_html;
			if (is_array($valid_data)) {
				$value[$id] = $valid_data;
			}
		}
		if (is_array($value[$id])) $value[$id]['action'] = $action;
	}
	
	if (array_key_exists('soa_template_chosen', $value)) {
		unset($value['soa_template_chosen']);
	}
	foreach ($value as $id => $array) {
		if (count($input_error[$id]['errors'])) {
			$img = $__FM_CONFIG['icons']['fail'];
			$action = 'None';
		} else {
			$img = $__FM_CONFIG['icons']['ok'];
		}
		$html .= "<tr><td>{$array['action']}</td>";
		foreach ($header_array as $head_id => $head_array) {
			if (!is_array($head_array) || !array_key_exists('rel', $head_array)) {
				continue;
			}
			
			$html .= '<td';
			if (in_array($head_array['rel'], array('record_append', 'soa_append'))) {
				$html .= ' class="center"';
			}
			if (empty($array[$head_array['rel']])) {
				$array[$head_array['rel']] = "<i>empty</i>";
			}
			$html .= '>' . $array[$head_array['rel']];
			if (($head_array['rel'] == 'record_value' && $array['record_append'] == 'yes') ||
					(in_array($head_array['rel'], array('soa_master_server', 'soa_email_address')) && $array['soa_append'] == 'yes')) {
				$html .= '<span class="grey">.' . $domain_info['name'] . '</span>';
			}
			if (isset($input_error[$id]['errors'][$head_array['rel']])) {
				$html .= ' <span class="valid_error">' . $input_error[$id]['errors'][$head_array['rel']] . '</span>';
			}
			if (isset($input_error[$id]['info'][$head_array['rel']])) {
				$html .= ' <span class="valid_message">' . $input_error[$id]['info'][$head_array['rel']] . '</span>';
			}
			$html .= '</td>';
		}
		$html .= '<td class="center">' . $img . '</td>';
		$html .= "</tr>\n";
	}
	
	return $html;
}

function validateEntry($action, $id, $data, $record_type) {
	$messages = null;
	$html = null;
	$append = array('CNAME', 'NS', 'MX', 'SRV', 'DNAME', 'CERT', 'RP');
	
	if ($action == 'create' && !isset($data['record_append']) && in_array($record_type, $append)) {
		$data['record_append'] = 'yes';
	}
	if (!empty($data['record_value'])) {
		$data['record_value'] = str_replace(array('"', "'"), '', $data['record_value']);
		foreach ($data as $key => $val) {
			if ($key == 'record_name' && $record_type != 'PTR') {
				if (!$val) {
					$val = '@';
					$data[$key] = $val;
				}
				if (!verifyName($val, true, $record_type)) {
					$messages['errors'][$key] = 'Invalid';
				}
			}
			
			if (in_array($key, array('record_ttl', 'record_priority', 'record_weight', 'record_port'))) {
				if (!empty($val) && verifyNumber($val) === false) {
					$messages['errors'][$key] = 'Invalid';
				}
			}
			
			if ($record_type == 'A') {
				if ($key == 'record_value') {
					if (verifyIPAddress($val) === false) {
						$messages['errors'][$key] = 'Invalid IP';
					}
				}
				if ($key == 'PTR') {
					global $domain_id;
					$retval = checkPTRZone($data['record_value'], $domain_id);
					list($val, $error_msg) = $retval;
					if ($val == null) {
						$messages['errors']['record_value'] = $error_msg;
					} else {
						$messages['info']['record_value'] = $error_msg;
					}
				}
			}
			
			if ($record_type == 'PTR') {
				if ($key == 'record_name') {
					if ($domain_map == 'reverse') {
						if (verifyIPAddress(buildFullIPAddress($data['record_name'], $domain)) === false) {
							$messages['errors'][$key] = 'Invalid record';
						}
					} else {
						if (!verifyCNAME('yes', $data['record_name'], false, true)) {
							$messages['errors'][$key] = 'Invalid record';
						}
					}
				}
			}
			
			if ((in_array($record_type, array('CNAME', 'DNAME', 'MX', 'NS', 'SRV'))) || 
					$record_type == 'PTR' && $key == 'record_value') {
				if ($key == 'record_value') {
					$val = $data['record_append'] == 'yes' || $val == '@' ? trim($val, '.') : trim($val, '.') . '.';
					$data[$key] = $val;
					if (!verifyCNAME($data['record_append'], $val)) {
						$messages['errors'][$key] = 'Invalid value';
					}
				}
			}
			
			if (!count($messages['errors'])) {
				$html .= buildInputReturn($action, $id, $key, $val);
			} else $html = null;
		}
	} elseif ($record_type == 'SOA') {
		if ($_POST['create']['soa_template_chosen']) {
			global $fm_dns_records;
			// Save the soa_template_chosen in domains table and end
			include_once(ABSPATH . 'fm-modules/fmDNS/classes/class_records.php');
			$fm_dns_records->assignSOA($_POST['create']['soa_template_chosen'], $_POST['domain_id']);
			header('Location: zone-records.php?map=' . $_POST['map'] . '&domain_id=' . $_POST['domain_id'] . '&record_type=SOA');
		}
		foreach ($data as $key => $val) {
			if (in_array($key, array('domain_id', 'soa_status'))) continue;
			if ($key == 'soa_email_address') {
				$val = strpos($val, '@') ? str_replace('@', '.', rtrim($val, '.') . '.') : $val ;
				$data[$key] = $val;
			}
			if (in_array($key, array('soa_master_server', 'soa_email_address'))) {
				$val = rtrim($val, '.');
				if (strpos($_POST['update'][$id]['soa_master_server'], $domain) && strpos($_POST['update'][$id]['soa_email_address'], $domain)) {
					$new_val = rtrim(str_replace($domain, '', $val), '.');
					if ($new_val != rtrim($val, '.')) {
						$data['soa_append'] = 'yes';
					}
					$val = $new_val;
				}
				if ($data['soa_append'] == 'no') {
					$val .= '.';
				}
			}
			if ($key != 'soa_append') {
				if (in_array($key, array('soa_master_server', 'soa_email_address'))) {
					$val = $data['soa_append'] == 'yes' ? trim($val, '.') : trim($val, '.') . '.';
					$data[$key] = $val;
					if (!verifyCNAME($data['soa_append'], $val, false)) {
						$messages['errors'][$key] = 'Invalid';
					}
				} else {
					if (array_key_exists('soa_template', $data) && $data['soa_template'] == 'yes') {
						if (!verifyNAME($val, false)) {
							$messages['errors'][$key] = 'Invalid';
						}
					}
				}
			}
			
			if (!count($messages['errors'])) {
				$html .= buildInputReturn($action, $id, $key, $val);
			} else $html = null;
		}
	} else {
		unset($data);
	}
	
	return array($data, $html, $messages);
}

function buildInputReturn($action, $id, $key, $val) {
	return '<input type="hidden" name="' . $action . "[$id][$key]" . '" value="' . $val . "\">\n";
}

function buildUpdateArray($domain_id, $record_type, $data_array) {
	$exclude_keys = array('record_skipped', 'record_append');
	$sql_records = buildSQLRecords($record_type, $domain_id);
	if (!count($sql_records) && $record_type == 'SOA') {
		return $data_array;
	}
	$raw_changes = compareValues($data_array, $sql_records);
	if (count($raw_changes)) {
		foreach ($raw_changes as $i => $data_array) {
			foreach ($exclude_keys as $key) {
				if (!array_key_exists($key, $data_array)) {
					unset($sql_records[$i][$key]);
				}
			}
			$changes[$i] = array_merge($sql_records[$i], $raw_changes[$i]);
		}
	} else {
		return false;
	}
	unset($sql_records, $raw_changes);
	
	return $changes;
}

function buildSQLRecords($record_type, $domain_id) {
	global $fmdb, $__FM_CONFIG;
	
	if ($record_type == 'SOA') {
		$soa_query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}soa` WHERE `account_id`='{$_SESSION['user']['account_id']}' AND
			`soa_id`=(SELECT `soa_id` FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE `domain_id`='$domain_id') AND 
			`soa_template`='no' AND `soa_status`='active'";
		$fmdb->get_results($soa_query);
		if ($fmdb->num_rows) $result = $fmdb->last_result;
		else return null;
		
		foreach (get_object_vars($result[0]) as $key => $val) {
			$sql_results[$result[0]->soa_id][$key] = $val;
		}
		array_shift($sql_results[$result[0]->soa_id]);
		array_shift($sql_results[$result[0]->soa_id]);
		return $sql_results;
	} else {
		$parent_domain_id = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_clone_domain_id');
		$valid_domain_ids = ($parent_domain_id) ? "IN ('$domain_id', '$parent_domain_id')" : "='$domain_id'";
		
		if (in_array($record_type, array('A', 'AAAA'))) {
			$record_sql = "AND domain_id $valid_domain_ids AND record_type IN ('A', 'AAAA')";
		} else {
			$record_sql = "AND domain_id $valid_domain_ids AND record_type='$record_type'";
		}
		$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', 'record_name', 'record_', $record_sql);
		if ($result) {
			$results = $fmdb->last_result;

			for ($i=0; $i<$result; $i++) {
				$static_array = array('record_name', 'record_ttl', 'record_class',
					'record_value', 'record_comment', 'record_status');
				$optional_array = array('record_priority', 'record_weight', 'record_port',
					'record_os', 'record_cert_type', 'record_key_tag', 'record_algorithm',
					'record_flags', 'record_text', 'record_append');
				
				foreach ($static_array as $field) {
					$sql_results[$results[$i]->record_id][$field] = $results[$i]->$field;
				}
				foreach ($optional_array as $field) {
					if ($results[$i]->$field != null) {
						$sql_results[$results[$i]->record_id][$field] = $results[$i]->$field;
					}
				}
				
				/** Skipped record? */
				basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records_skipped', $results[$i]->record_id, 'record_', 'record_id', "AND domain_id=$domain_id");
				$sql_results[$results[$i]->record_id]['record_skipped'] = ($fmdb->num_rows) ? 'on' : 'off';
			}
		}
		return $sql_results;
	}

}

function compareValues($data_array, $sql_records) {
	$changes = array();
	foreach ($data_array as $key => $val) {
		$diff = array_diff_assoc($data_array[$key], $sql_records[$key]);
		if ($diff) {
			$changes[$key] = $diff;
		}
	}

	return $changes;
}

function verifyName($record_name, $allow_null = true, $record_type = null) {
	if (!$allow_null && !strlen($record_name)) return false;
	
	if (substr($record_name, 0, 1) == '*' && substr_count($record_name, '*') < 2) {
		return true;
	} elseif (preg_match('/^[a-z0-9_\-.]+$/i', $record_name) == true
			&& preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $record_name) == true) {
		return true;
	} elseif ($record_name == '@') {
		return true;
	}
	
	return false;
}

function verifyCNAME($append, $record, $allow_null = true, $allow_underscore = false) {
	if (!$allow_null && !strlen($record)) return false;
	
	if (preg_match('/^[a-z0-9_\-.]+$/i', $record) == true) {
		if ($append == 'yes') {
			if (strstr($record, '.') == false) {
				return true;
			} else {
				if (preg_match('/\d{1,3}\.\d{1,3}\-\d{1,3}/', $record)) return true;
			}
		} else {
			return substr($record, -1, 1) == '.';
		}
		return true;
	} else if ($record == '@') {
		return true;
	}
	return false;
}

function checkPTRZone($ip, $domain_id) {
	global $fmdb, $__FM_CONFIG;

	list($ip1, $ip2, $ip3, $ip4) = explode('.' , $ip);
	$zone = "'$ip3.$ip2.$ip1.in-addr.arpa', '$ip2.$ip1.in-addr.arpa', '$ip1.in-addr.arpa'";

	basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $zone, 'domain_', 'domain_name', "OR domain_name IN ($zone) AND domain_status!='deleted'");
	if ($fmdb->num_rows) {
		$result = $fmdb->last_result;
		return array($result[0]->domain_id, null);
	} else {
		if (getOption('auto_create_ptr_zones', $_SESSION['user']['account_id'], $_SESSION['module']) == 'yes') {
			return autoCreatePTRZone($zone, $domain_id);
		}
		return array(null, 'Reverse zone does not exist.');
	}
}

function autoCreatePTRZone($new_zones, $fwd_domain_id) {
	global $__FM_CONFIG, $fmdb;

	basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $fwd_domain_id, 'domain_', 'domain_id');
	if ($fmdb->num_rows) {
		$result = $fmdb->last_result;

		$new_zone = explode(",", $new_zones);

		$ptr_array['domain_id'] = 0;
		$ptr_array['domain_name'] = trim($new_zone[0], "'");
		$ptr_array['domain_mapping'] = 'reverse';
		$ptr_array['domain_name_servers'] = explode(';', $result[0]->domain_name_servers);

		$copy_fields = array('soa_id', 'domain_view', 'domain_type');
		foreach ($copy_fields as $field) {
			$ptr_array[$field] = $result[0]->$field;
		}

		global $fm_dns_zones;

		if (!class_exists('fm_dns_zones')) {
			include_once(ABSPATH . 'fm-modules/fmDNS/classes/class_zones.php');
		}
		$retval = $fm_dns_zones->add($ptr_array);

		return !is_int($retval) ? array(null, $retval) : array($retval, 'Created reverse zone.');
	}

	return array(null, 'Forward domain not found.');
}
?>
