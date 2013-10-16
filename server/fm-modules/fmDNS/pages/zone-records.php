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

$page_name = 'Zones';
$page_name_sub = ($map == 'forward') ? 'Forward' : 'Reverse';

if (isset($_GET['record_type'])) {
	$record_type = strtoupper($_GET['record_type']);
} else {
	$record_type = $map == 'forward' ? 'A' : 'PTR';
}

$domain_id = (isset($_GET['domain_id'])) ? $_GET['domain_id'] : header('Location: ' . $__FM_CONFIG['menu']['Zones']['URL']);
if (!isValidDomain($domain_id)) header('Location: ' . $__FM_CONFIG['menu']['Zones']['URL']);

printHeader();
@printMenu($page_name, $page_name_sub);

include(ABSPATH . 'fm-modules/fmDNS/classes/class_records.php');

$zone_access_allowed = true;

if (isset($_SESSION['user']['module_perms']['perm_extra'])) {
	$module_extra_perms = isSerialized($_SESSION['user']['module_perms']['perm_extra']) ? unserialize($_SESSION['user']['module_perms']['perm_extra']) : $_SESSION['user']['module_perms']['perm_extra'];
	$zone_access_allowed = (is_array($module_extra_perms) && 
		!in_array(0, $module_extra_perms['zone_access']) && !$super_admin) ? in_array($domain_id, $module_extra_perms['zone_access']) : true;
}

if (in_array($record_type, $__FM_CONFIG['records']['require_zone_rights']) && !$allowed_to_manage_zones) header('Location: /');
$avail_types = buildRecordTypes($record_type, $domain_id, $map);

$response = $form_data = $action = null;
if (reloadZone($domain_id)) {
	$response = '** You need to reload this zone **';
}
if (!getNSCount($domain_id)) {
	$response = '** You still need to create NS records for this zone **';
}
if (!getSOACount($domain_id)) {
	$response = '** You still need to create the SOA for this zone **';
}

if (!empty($response)) echo '<div id="response"><p>' . $response . '</p></div>';
echo '<div id="body_container"';
if (!empty($response)) echo ' style="margin-top: 4em;"';
echo ">
	<h2>Records</h2>
	$avail_types\n";
	
	if ($allowed_to_manage_records && $zone_access_allowed) {
		echo '<form method="POST" action="zone-recordsupd">
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
		if ($allowed_to_manage_records && $zone_access_allowed) {
			echo '
		<p><input type="submit" name="submit" value="Validate" class="button" /></p>
	</form>' . "\n";
		}
	} else {
		if (in_array($record_type, array('A', 'AAAA'))) {
			$sort_field = 'record_value';
			$record_sql = "AND domain_id='$domain_id' AND record_type IN ('A', 'AAAA')";
		} else {
			$sort_field = 'record_name';
			$record_sql = "AND domain_id='$domain_id' AND record_type='$record_type'";
		}
		$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $sort_field, 'record_', $record_sql, null, true);
		$fm_dns_records->rows($result, $record_type, $domain_id);
		
		if ($allowed_to_manage_records && $zone_access_allowed) {
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


function buildRecordTypes($record_type = null, $domain_id = null, $map = 'forward') {
	global $__FM_CONFIG, $allowed_to_manage_zones;
	
	$menu_selects = null;
	
	if (isset($record_type) && $domain_id != null) {
		foreach ($__FM_CONFIG['records']['avail_types'] as $type) {
			if (in_array($type, $__FM_CONFIG['records']['require_zone_rights']) && !$allowed_to_manage_zones) continue;
			$select = ($record_type == $type) ? ' class="selected"' : '';
			$menu_selects .= "<span$select><a$select href=\"zone-records.php?map={$map}&domain_id={$domain_id}&record_type=$type\">$type</a></span>\n";
		}
	}
	
	return '<div id="configtypesmenu">' . $menu_selects . '</div>';
}

function isValidDomain($domain_id) {
	global $fmdb, $__FM_CONFIG;
	
	basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $domain_id, 'domain_', 'domain_id');
	if ($fmdb->num_rows) {
		$result = $fmdb->last_result;
		if ($result[0]->domain_type == 'master' && !$result[0]->domain_clone_domain_id) return true;
	}
	
	return false;
}

?>
