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
 | Processes zone records page                                             |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

$map = (isset($_GET['map'])) ? strtolower($_GET['map']) : 'forward';

/** Include module variables */
if (isset($_SESSION['module'])) include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/variables.inc.php');

$default_record_type = array_shift(array_values($__FM_CONFIG['records']['common_types']));
if (isset($_GET['record_type'])) {
	$record_type = strtoupper($_GET['record_type']);
} else {
	$record_type = $default_record_type;
}

$domain_id = (isset($_GET['domain_id'])) ? $_GET['domain_id'] : header('Location: ' . $__FM_CONFIG['menu']['Zones']['URL']);
if (!isValidDomain($domain_id)) header('Location: ' . $__FM_CONFIG['menu']['Zones']['URL']);

/** Does the user have access? */
if (!currentUserCan(array('access_specific_zones', 'view_all'), $_SESSION['module'], array(0, $domain_id))) unAuth();

if (in_array($record_type, $__FM_CONFIG['records']['require_zone_rights']) && !currentUserCan('manage_zones', $_SESSION['module'])) unAuth();
if (getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_clone_domain_id') && $record_type == 'SOA') $record_type = $default_record_type;

printHeader();
@printMenu();

include(ABSPATH . 'fm-modules/fmDNS/classes/class_records.php');

$zone_access_allowed = true;
$supported_record_types = enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', 'record_type');
sort($supported_record_types);

$zone_access_allowed = currentUserCan('access_specific_zones', $_SESSION['module'], array(0, $domain_id));
		
if (!in_array($record_type, $supported_record_types) && !in_array($record_type, $__FM_CONFIG['records']['common_types'])) $record_type = $__FM_CONFIG['records']['common_types'][0];
$avail_types = buildRecordTypes($record_type, $domain_id, $map, $supported_record_types);

$response = $form_data = $action = null;
if (reloadZone($domain_id)) {
	if (reloadAllowed($domain_id) && currentUserCan('reload_zones', $_SESSION['module']) && $zone_access_allowed) $response = '** You need to <a href="" class="zone_reload" id="' . $domain_id . '">reload</a> this zone **';
}
if (!getNSCount($domain_id)) {
	$response = '** One more more NS records still needs to be created for this zone **';
}
if (!getSOACount($domain_id)) {
	$response = '** The SOA record still needs to be created for this zone **';
}

echo '<div id="body_container">' . "\n";
if (!empty($response)) echo '<div id="response"><p>' . $response . '</p></div>';
echo "	<h2>Records</h2>
	$avail_types\n";
	
	if (currentUserCan('manage_records', $_SESSION['module']) && $zone_access_allowed) {
		echo '<form method="POST" action="zone-records-validate.php">
	<input type="hidden" name="domain_id" value="' . $domain_id . '">
	<input type="hidden" name="record_type" value="' . $record_type . '">
	<input type="hidden" name="map" value="' . $map . '">' . "\n";
	}

	if ($record_type == 'SOA') {
		$soa_query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}soa` WHERE `domain_id`='$domain_id'";
		$fmdb->get_results($soa_query);
		if ($fmdb->num_rows) $result = $fmdb->last_result;
		else $result = null;
		$fm_dns_records->buildSOA($result, $domain_id);
		if (currentUserCan('manage_records', $_SESSION['module']) && $zone_access_allowed) {
			echo '
		<p><input type="submit" name="submit" value="Validate" class="button" /></p>
	</form>' . "\n";
		}
	} else {
		switch ($record_type) {
			case 'NS':
				$sort_field = 'record_value';
				$ip_sort = false;
				break;
			case 'PTR':
				$sort_field = 'record_name';
				$ip_sort = true;
				break;
			case 'MX':
				$sort_field = 'record_priority';
				$ip_sort = true;
				break;
			default:
				$sort_field = 'record_name';
				$ip_sort = false;
				break;
		}
		$parent_domain_id = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_clone_domain_id');
		$valid_domain_ids = ($parent_domain_id) ? "IN ('$domain_id', '$parent_domain_id')" : "='$domain_id'";
		$record_sql = "AND domain_id $valid_domain_ids AND record_type='$record_type'";
		$sort_direction = null;
		
		if (isset($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']])) {
			extract($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']], EXTR_OVERWRITE);
		}
		
		if (in_array($record_type, array('A', 'AAAA')) && $sort_field == 'record_value') $ip_sort = true;
		
		$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', array($sort_field, 'record_name'), 'record_', $record_sql, null, $ip_sort, $sort_direction);
		$fm_dns_records->rows($result, $record_type, $domain_id);
		
		if (currentUserCan('manage_records', $_SESSION['module']) && $zone_access_allowed) {
			echo '
		<br /><br />
		<a name="#manage"></a>
		<h2>Add Record</h2>' . "\n";
		
			$fm_dns_records->printRecordsForm($form_data, $action, $record_type, $domain_id);
			echo '
		<p><input type="submit" name="submit" value="Validate" class="button" /></p>
	</form>' . "\n";
		}
	}

echo '</div>' . "\n";

printFooter();


function buildRecordTypes($record_type = null, $domain_id = null, $map = 'forward', $supported_record_types) {
	global $__FM_CONFIG;
	
	$menu_selects = $menu_sub_selects = null;
	
	if (isset($record_type) && $domain_id != null) {
		foreach ($__FM_CONFIG['records']['common_types'] as $type) {
			if (in_array($type, $__FM_CONFIG['records']['require_zone_rights']) && !currentUserCan('manage_zones', $_SESSION['module'])) continue;
			if (getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_clone_domain_id') && $type == 'SOA') continue;

			$select = ($record_type == $type) ? ' class="selected"' : '';
			$menu_selects .= "<span$select><a$select href=\"zone-records.php?map={$map}&domain_id={$domain_id}&record_type=$type\">$type</a></span>\n";
		}
		
		/** More record types menu */
		if (count($__FM_CONFIG['records']['common_types']) < count($supported_record_types)) {
			foreach ($supported_record_types as $type) {
				if (!in_array($type, $__FM_CONFIG['records']['common_types'])) {
					if ($record_type == $type) {
						$menu_selects .= "<span class=\"selected\"><a class=\"selected\" href=\"zone-records.php?map={$map}&domain_id={$domain_id}&record_type=$type\">$type</a></span>\n";
					} else {
						$menu_sub_selects .= "<li><a href=\"zone-records.php?map={$map}&domain_id={$domain_id}&record_type=$type\"><span>$type</span></a></li>\n";
					}
				}
			}
			$menu_selects = <<<MENU
			<div id="recordmenu">
			<ul>
				<li class="has-sub"><a href="#"><span>...</span></a>
					<ul>
					$menu_sub_selects
					</ul>
				</li>
			</ul>
			</div>
			$menu_selects

MENU;
		}
	}
	
	return '<div id="configtypesmenu">' . $menu_selects . '</div>';
}

function isValidDomain($domain_id) {
	global $fmdb, $__FM_CONFIG;
	
	basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $domain_id, 'domain_', 'domain_id');
	if ($fmdb->num_rows) {
		$result = $fmdb->last_result;
		if ($result[0]->domain_type == 'master') return true;
	}
	
	return false;
}

?>
