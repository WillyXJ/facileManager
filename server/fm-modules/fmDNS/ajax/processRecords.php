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
 | Processes form posts                                                    |
 +-------------------------------------------------------------------------+
*/

if (!defined('AJAX')) define('AJAX', true);
require_once('../../../fm-init.php');

include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $fm_name . DIRECTORY_SEPARATOR . 'ajax' . DIRECTORY_SEPARATOR . 'functions.php');

include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_records.php');

/** Make sure it's a valid request */
if (!is_array($_POST) || !array_key_exists('action', $_POST)) {
	exit;
}
if (!in_array($_POST['action'], array('process-record-updates', 'validate-record-updates'))) {
	exit;
}

if (!isset($_POST['uri_params']['record_type'])) $_POST['uri_params']['record_type'] = 'ALL';

/** Should the user be here? */
if (!isset($_POST['uri_params'])) returnUnAuth();
if (!currentUserCan('manage_records', $_SESSION['module'])) returnUnAuth();
if (!isset($_POST['uri_params']['domain_id']) || !zoneAccessIsAllowed(array($_POST['uri_params']['domain_id']))) returnUnAuth();
if (!isset($_POST['uri_params']['record_type']) || (in_array($_POST['uri_params']['record_type'], $__FM_CONFIG['records']['require_zone_rights']) && !currentUserCan('manage_zones', $_SESSION['module']))) returnUnAuth();

/* RR types that allow record append */
$append = array('CNAME', 'NS', 'MX', 'SRV', 'DNAME', 'RP', 'NAPTR');

if (!isset($global_form_field_excludes)) $global_form_field_excludes = array();

if ($_POST['action'] == 'validate-record-updates') {
	echo validateRecordUpdates();
}

if ($_POST['action'] == 'process-record-updates') {
	include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/pages/zone-records-write.php');
	exit;
	// echo processRecordUpdates();
}



/**
 * Validates the record for saving
 *
 * @since 7.0.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param int $domain_id domain_id to get
 */
function validateRecordUpdates() {
	global $__FM_CONFIG, $append;

	extract($_POST);
	extract($_POST['uri_params'], EXTR_OVERWRITE);

	/* RR types that allow record append */
	$append = array('CNAME', 'NS', 'MX', 'SRV', 'DNAME', 'RP', 'NAPTR');

	$create_update = 'update';
	$GLOBALS['new_cname_rrs'] = array();

	if (isset($_POST['create'])) {
		$create_update = 'create';
	}

	/** Get real record_type */
	if ($record_type == 'ALL') {
		$record_type = ($create_update == 'update') ? getNameFromID(array_keys($_POST[$create_update])[0], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', 'record_', 'record_id', 'record_type') : $_POST[$create_update][array_keys($_POST[$create_update])[0]]['record_type'];
	}

	if (isset($_POST['update'])) {
		$_POST[$create_update] = buildUpdateArray($domain_id, $record_type, $_POST[$create_update], $append);
	}

	/** Fix this! */
	$domain_info['id']          = $domain_id;
	$domain_info['name']        = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name');
	// $domain_info['map']         = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_mapping');
	// $domain_info['clone_of']    = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_clone_domain_id');
	// $domain_info['template_id'] = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_template_id');

	foreach ($_POST[$create_update] as $id => $data) {
		list($valid_data, $input_error) = validateEntry($create_update, $id, $data, $record_type, $append, $_POST[$create_update], $domain_info);
		if (!isset($input_error)) unset($input_error);
	}

	header("Content-type: application/json");
	return json_encode(array($valid_data, $input_error));
}


function buildUpdateArray($domain_id, $record_type, $data_array, $append) {
	$exclude_keys = array('record_skipped');
	if (!in_array($record_type, $append)) {
		$exclude_keys[] = 'record_append';
	}
	
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
			$changes[$i] = array_merge((array) $sql_records[$i], (array) $raw_changes[$i]);
		}
	} else {
		return $data_array;
	}
	unset($sql_records, $raw_changes);
	
	return $changes;
}


function validateEntry($action, $id, $data, $record_type, $append, $data_array, $domain_info) {
	global $map;

	$messages = array();
	
	if (!isset($data['record_append']) && in_array($record_type, $append)) {
		$data['record_append'] = 'no';
	}
	if (isset($data['Delete'])) {
		$data['record_status'] = 'deleted';
		unset($data['Delete']);
	}
	if ($record_type != 'SOA') {
		if (!empty($data['record_value'])) {
			$data['record_value'] = str_replace(array('"', "'"), '', $data['record_value']);
			if (strpos($data['record_value'], "\n")) {
				foreach (explode("\n", $data['record_value']) as $line) {
					$tmp_value[] = trim($line);
				}
				$data['record_value'] = join("\n", $tmp_value);
			}
		} elseif ($record_type != 'HINFO' && (!isset($data['record_value']) || empty($data['record_value']))) {
			$messages['errors']['record_value'] = __('This field cannot be empty.');
		}
		if ($record_type != 'PTR' && (!isset($data['record_name']) || empty($data['record_name']))) {
			$data['record_name'] = '@';
		}
		foreach ($data as $key => $val) {
			$data[$key] = trim($val, '"\'');
			
			if ($key == 'record_name' && $record_type != 'PTR') {
				if ($data['record_status'] == 'active' && !verifyName($domain_info['id'], $val, $id, true, $record_type, $data_array)) {
					$messages['errors'][$key] = __('Names can only contain letters, numbers, hyphens (-), underscores (_), or @. However, @ is not valid for CNAME records. Additionally, the same name cannot exist for A and CNAME records.');
				}
			}
			
			if (in_array($key, array('record_priority', 'record_weight', 'record_port'))) {
				if (!empty($val) && verifyNumber($val) === false) {
					$messages['errors'][$key] = __('This field must be a number.');
				}
			}
			
			if ($key == 'record_ttl') {
				if (!empty($val) && verifyTTL($val) === false) {
					$messages['errors'][$key] = __('This field must be in TTL format which consists of numbers and time-based letters (s, m, h, d, w, y). Examples include 300, 5d, or 1y4w9d.');
				}
			}
			
			if ($record_type == 'A') {
				if ($key == 'record_value') {
					if (verifyIPAddress($val) === false) {
						$messages['errors'][$key] = __('IP address must be in valid IPv4 or IPv6 format.');
					}
				}
				if ($key == 'PTR' && !isset($messages['errors']['record_value'])) {
					$retval = checkPTRZone($data['record_value'], $domain_info['id']);
					list($val, $error_msg) = $retval;
					if ($val == null) {
						$messages['errors']['record_value'] = $error_msg;
					} else {
						$data[$key] = $val;
					}
				}
			}
			
			if ($record_type == 'PTR') {
				if ($key == 'record_name') {
					if ($map == 'reverse') {
						if (verifyIPAddress(buildFullIPAddress($data['record_name'], $domain_info['name'])) === false) {
							$messages['errors'][$key] = __('Invalid record');
						}
					} else {
						if (!verifyCNAME('yes', $data['record_name'], false, true)) {
							$messages['errors'][$key] = __('Invalid record');
						}
					}
				}
			}
			
			if ((in_array($record_type, array('CNAME', 'DNAME', 'MX', 'NS', 'SRV', 'NAPTR'))) || 
					$record_type == 'PTR' && $key == 'record_value') {
				if ($key == 'record_value') {
					$val = ((isset($data['record_append']) && $data['record_append'] == 'yes') || $val == '@') ? trim($val, '.') : trim($val, '.') . '.';
					$data[$key] = $val;
					if ((isset($data['record_append']) && !verifyCNAME($data['record_append'], $val)) || ($record_type == 'NS' && !validateHostname($val))) {
						$messages['errors'][$key] = __('Invalid value');
					}
				}
			}

			if ($key == 'record_key_tag') {
				if ((!isset($data[$key])) || empty($data[$key])) {
					$messages['errors'][$key] = __('The Key Tag may not be empty.');
				}
			}
		}
	} elseif ($record_type == 'SOA') {
		if ($_POST['create']['soa_template_chosen']) {
			global $fm_dns_records;
			// Save the soa_template_chosen in domains table and end
			include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_records.php');
			$fm_dns_records->assignSOA($_POST['create']['soa_template_chosen'], $_POST['domain_id']);
			header('Location: zone-records.php?map=' . $_POST['map'] . '&domain_id=' . $_POST['domain_id'] . '&record_type=SOA');
			exit;
		}
		if (!isset($data['soa_append'])) {
			$data['soa_append'] = 'no';
		}
		foreach ($data as $key => $val) {
			if (in_array($key, array('domain_id', 'soa_status'))) continue;
			if ($key == 'soa_email_address') {
				$val = strpos($val, '@') ? str_replace('@', '.', rtrim($val, '.') . '.') : $val ;
				$data[$key] = $val;
			}
			if (in_array($key, array('soa_master_server', 'soa_email_address'))) {
				$val = rtrim($val, '.');
				if (strpos($_POST['update'][$id]['soa_master_server'], $domain_info['name']) && strpos($_POST['update'][$id]['soa_email_address'], $domain_info['name'])) {
					$new_val = rtrim(str_replace($domain_info['name'], '', $val), '.');
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
					if (!verifyCNAME($data['soa_append'], $val, false) || ($key == 'soa_master_server' && !validateHostname($val))) {
						$messages['errors'][$key] = __('Invalid');
					}
				} else {
					if (in_array($key, array('soa_refresh', 'soa_retry', 'soa_expire', 'soa_ttl'))) {
						if (!empty($val) && verifyTTL($val) === false) {
							$messages['errors'][$key] = __('Invalid');
						}
					} elseif (array_key_exists('soa_template', $data) && $data['soa_template'] == 'yes') {
						if (!verifyNAME($val, $id, false)) {
							$messages['errors'][$key] = __('Invalid');
						}
					}
				}
			}
			
			if (!isset($messages['errors']) || !count($messages['errors'])) {
				$html .= buildInputReturn($action, $id, $key, $val);
			} else $html = null;
		}
	} else {
		unset($data);
	}
	
	return array($data, $messages);
}


function verifyName($domain_id, $record_name, $id, $allow_null = true, $record_type = null, $data_array = null) {
	global $fmdb, $__FM_CONFIG;
	
	if (!$allow_null && !strlen($record_name)) return false;
	
	/** Ensure singleton RR type from existing records */
	$sql = ($record_type != 'CNAME') ? " AND record_type='CNAME'" : " AND record_id!=$id";
	basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', 'record_id', 'record_', "AND record_name='$record_name' AND domain_id=$domain_id $sql AND record_status='active'", null, false, 'ASC');
	if ($fmdb->num_rows) {
		foreach ($fmdb->last_result as $res_array) {
			if (isset($_POST['update']) && is_array($_POST['update']) 
				&& array_key_exists($res_array->record_id, $_POST['update']) 
				&& (array_key_exists('Delete', $_POST['update'][$res_array->record_id]) 
					|| $_POST['update'][$res_array->record_id]['record_status'] != 'active')) {
				continue;
			}
			if (array_key_exists($res_array->record_id, $data_array)) {
				if ($data_array[$res_array->record_id]['record_status'] == 'active') {
					return false;
				}
			} else {
				return false;
			}
		}
	}
	/** Ensure no updates create duplicate CNAME RR */
	// if (is_array($_POST['update']) && is_array($_POST['create']) && $record_type == 'CNAME') {
	// 	$tmp_array = array();
	// 	foreach ($_POST['update'] as $k => $v) {
	// 		if ($v['record_name'] == $record_name && $v['record_status'] == 'active' && !array_key_exists('Delete', $v)) $tmp_array[] = $_POST['update'][$k];
	// 	}
	// 	foreach ($_POST['create'] as $k => $v) {
	// 		if ($v['record_name'] == $record_name && $v['record_status'] == 'active') $tmp_array[] = $_POST['create'][$k];
	// 	}
	// 	if (count(array_column($tmp_array, 'record_status')) > 1) return false;
	// 	unset($tmp_array);
	// }

	/** Ensure singleton RR type from new records */
	if (in_array($record_name, $GLOBALS['new_cname_rrs'])) {
		return false;
	} elseif ($record_type == 'CNAME') {
		$GLOBALS['new_cname_rrs'][] = $record_name;
	}
	
	if (substr($record_name, 0, 1) == '*' && substr_count($record_name, '*') < 2) {
		return true;
	} elseif (preg_match('/^[a-z0-9_\-.]+$/i', $record_name) == true
			&& preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $record_name) == true) {
		if (in_array($record_type, array('A', 'MX'))) {
			return validateHostname($record_name);
		}
		return true;
	} elseif ($record_name == '@' && $record_type != 'CNAME') {
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
	} else if (in_array($record, array('@', '*.'))) {
		return true;
	}
	return false;
}

/**
 * Returns whether record hostname is valid or not
 *
 * @since 2.1
 * @package fmDNS
 *
 * @param string $hostname Hostname to check
 * @return boolean
 */
function validateHostname($hostname) {
	if ($hostname[0] == '-' || strpos($hostname, '_') !== false) {
		return false;
	}
	
	return true;
}

/**
 * Returns whether record TTL is valid or not
 *
 * @since 3.0
 * @package fmDNS
 *
 * @param string $ttl TTL to check
 * @return boolean
 */
function verifyTTL($ttl) {
	/** Return true if $ttl is a number */
	if (verifyNumber($ttl)) return true;
	
	/** Check if last character is a-z */
	if (!preg_match('/[a-z]/i', substr($ttl, -1))) return false;
	
	/** Check for s, m, h, d, w */
	preg_match_all('/\d+[a-z]/i', $ttl, $matches);
	
	/** Something is wrong */
	if (count($matches) > 1) return false;
	
	foreach ($matches[0] as $match) {
		$split = preg_split('/[smhdwy]/i', $match);
		if (!verifyNumber($split[0])) return false;
	}
	
	return true;
}
