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

$map = (isset($_GET['map'])) ? strtolower($_GET['map']) : 'forward';

/** Include module variables */
if (isset($_SESSION['module'])) include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/variables.inc.php');

$default_record_type = $map == 'reverse' ? 'PTR' : 'A';
if (isset($_GET['record_type'])) {
	$record_type = strtoupper($_GET['record_type']);
} else {
	$record_type = $default_record_type;
}

if (isset($_GET['domain_id'])){
	$domain_id = $_GET['domain_id'];
} else {
	header('Location: ' . getMenuURL(__('Zones')));
	exit;
}
if (!isValidDomain($domain_id)) {
	header('Location: ' . getMenuURL(__('Zones')));
	exit;
}

/** Does the user have access? */
if (!currentUserCan(array('access_specific_zones', 'view_all'), $_SESSION['module'], array(0, $domain_id))) unAuth();

if (in_array($record_type, $__FM_CONFIG['records']['require_zone_rights']) && !currentUserCan('manage_zones', $_SESSION['module'])) unAuth();
if ($record_type == 'SOA') {
	if (getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_clone_domain_id')) $record_type = $default_record_type;
	elseif (getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_template_id')) $record_type = $default_record_type;
}

define('FM_INCLUDE_SEARCH', true);

printHeader();
@printMenu();

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_records.php');

$search_query = createSearchSQL(array('name', 'value', 'ttl', 'class', 'text', 'comment'), 'record_');

$supported_record_types = enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', 'record_type');
sort($supported_record_types);
$supported_record_types[] = 'SOA';

$parent_domain_ids = getZoneParentID($domain_id);
$zone_access_allowed = zoneAccessIsAllowed($parent_domain_ids);
		
if (!in_array($record_type, $supported_record_types)) $record_type = $default_record_type;
$avail_types = buildRecordTypes($record_type, $parent_domain_ids, $map, $supported_record_types, $search_query);

$response = $form_data = $action = null;
if (reloadZone($domain_id)) {
	if (reloadAllowed($domain_id) && currentUserCan('reload_zones', $_SESSION['module']) && $zone_access_allowed) $response = '** You need to <a href="" class="zone_reload" id="' . $domain_id . '">reload</a> this zone **';
}
if (!getNSCount($domain_id)) {
	$response = sprintf('** %s **', __('One more more NS records still needs to be created for this zone'));
}
if (!getSOACount($domain_id)) {
	$response = sprintf('** %s **', __('The SOA record still needs to be created for this zone'));
}

$body = '<div id="body_container" class="fm-noscroll">' . "\n";
if (!empty($response)) $body .= '<div id="response"><p>' . $response . '</p></div>';
$body .= sprintf('<h2>%s</h2>
	<div id="pagination_container" class="submenus record-types">
	<div>
	<div class="stretch"></div>
	%s
	</div>
</div>', __('Records'), $avail_types);
	
if (currentUserCan('manage_records', $_SESSION['module']) && $zone_access_allowed) {
	$form = '<form method="POST" action="zone-records-validate.php">
<input type="hidden" name="domain_id" value="' . $domain_id . '">
<input type="hidden" name="record_type" value="' . $record_type . '">
<input type="hidden" name="map" value="' . $map . '">' . "\n";
} else $form = null;

if ($record_type == 'SOA') {
	$soa_query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}soa` WHERE `account_id`='{$_SESSION['user']['account_id']}' AND
		`soa_id`=(SELECT `soa_id` FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE `domain_id`='$domain_id') AND 
		`soa_status`='active'";
	$result = $fmdb->get_results($soa_query);
	$body .= $form . $fm_dns_records->buildSOA($result);
	if (currentUserCan('manage_records', $_SESSION['module']) && $zone_access_allowed) {
		$body .= sprintf('<p><input type="submit" name="submit" value="%s" class="button" /></p></form>' . "\n", __('Validate'));
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
	$record_sql = "AND domain_id IN (" . join(',', $parent_domain_ids) . ") AND record_type='$record_type'";
	$sort_direction = null;

	if (isset($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']])) {
		extract($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']], EXTR_OVERWRITE);
	}

	if (in_array($record_type, array('A', 'AAAA')) && $sort_field == 'record_value') $ip_sort = true;
	
	if (isset($search_query)) $record_sql .= $search_query;

	$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', array($sort_field, 'record_name'), 'record_', $record_sql, null, $ip_sort, $sort_direction);
	$total_pages = ceil($fmdb->num_rows / $_SESSION['user']['record_count']);
	if ($page > $total_pages) $page = $total_pages;
	$pagination = displayPagination($page, $total_pages);
	$body .= $pagination . '<div class="overflow-container">' . $form;
	
	$record_rows = $fm_dns_records->rows($result, $record_type, $domain_id, $page);

	if (currentUserCan('manage_records', $_SESSION['module']) && $zone_access_allowed) {
		$body .= '<div class="existing-container">' . $record_rows;
		$body .= sprintf('</div><div class="new-container">
	<a name="#manage"></a>
	<h2>%s</h2>
	%s
	<p><input type="submit" name="submit" value="%s" class="button" /></p>
</form></div>' . "\n", __('Add Record'), $fm_dns_records->printRecordsForm($form_data, $action, $record_type, $domain_id), __('Validate'));
	} else {
		$body .= '<div class="existing-container no-bottom-margin">' . $record_rows;
	}
}

echo $body . '</div>' . "\n";

printFooter();


function buildRecordTypes($record_type = null, $all_domain_ids = null, $map = 'forward', $supported_record_types, $search_query = null) {
	global $fmdb, $__FM_CONFIG;
	
	$menu_selects = $menu_sub_selects = null;
	
	$q = isset($_GET['q']) ? '&q=' . sanitize($_GET['q']) : null;
	
	if (isset($record_type) && $all_domain_ids != null) {
		$domain_id = $all_domain_ids[0];
		$query = "SELECT DISTINCT `record_type` FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}records WHERE `record_status`!='deleted' AND
			`account_id`={$_SESSION['user']['account_id']} AND `domain_id` IN (" . implode(',', $all_domain_ids) . ") $search_query";
		$type_result = $fmdb->get_results($query);
		$used_record_types = array();
		if ($fmdb->num_rows) {
			for ($i=0; $i < $fmdb->num_rows; $i++) {
				$used_record_types[] = $type_result[$i]->record_type;
			}
		}
		@sort($used_record_types);
		
		$used_record_types[] = 'SOA';
		
		foreach ($used_record_types as $type) {
			if (empty($type)) continue;
			if (in_array($type, $__FM_CONFIG['records']['require_zone_rights']) && !currentUserCan('manage_zones', $_SESSION['module'])) continue;
			if ($type == 'SOA') {
				/** Skip clones */
				if (getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_clone_domain_id')) continue;
				
				/** Skip templates */
				if (getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_template_id')) continue;
			}

			$select = ($record_type == $type) ? ' class="selected"' : '';
			$menu_selects .= "<span$select><a$select href=\"zone-records.php?map={$map}&domain_id={$domain_id}&record_type={$type}{$q}\">$type</a></span>\n";
		}
		
		/** More record types menu */
		if (count($used_record_types) < count($supported_record_types)) {
			foreach ($supported_record_types as $type) {
				if (!in_array($type, $used_record_types)) {
					if ($record_type == $type) {
						$menu_selects .= "<span class=\"selected\"><a class=\"selected\" href=\"zone-records.php?map={$map}&domain_id={$domain_id}&record_type=$type\">$type</a></span>\n";
					} else {
						$menu_sub_selects .= "<li><a href=\"zone-records.php?map={$map}&domain_id={$domain_id}&record_type={$type}{$q}\"><span>$type</span></a></li>\n";
					}
				}
			}
			$menu_selects = <<<MENU
			$menu_selects
			</div>
			<div id="configtypesmenu" class="nopadding dropdown">
				<div id="recordmenu">
				<ul>
					<li class="has-sub"><a href="#"><span>...</span></a>
						<ul>
						$menu_sub_selects
						</ul>
					</li>
				</ul>
				</div>

MENU;
		}
	}
	
	return '<div id="configtypesmenu" class="submenus">' . $menu_selects . '</div>';
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
