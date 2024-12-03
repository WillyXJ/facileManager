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

if (isset($_GET['manage-records'])) {
	include(dirname(__FILE__) . '/zone-records.php');
	exit;
}

if (!currentUserCan(array('manage_zones', 'manage_records', 'reload_zones', 'view_all'), $_SESSION['module'])) unAuth();

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_zones.php');

if (!isset($map)) {
	header('Location: zones-forward.php');
	exit;
}
if (isset($_GET['map'])) {
	header('Location: zones-' . sanitize(strtolower($_GET['map'])) . '.php');
	exit;
}
$map = (isset($_POST['createZone'][0]['domain_mapping'])) ? strtolower($_POST['createZone'][0]['domain_mapping']) : $map;

define('FM_INCLUDE_SEARCH', true);

printHeader();
@printMenu();

$search_query = createSearchSQL(array('name', 'mapping', 'type'), 'domain_');

/** Check if any servers need their configs built first */
$reload_allowed = reloadAllowed();
if (!$reload_allowed && !$response) $response = '<p>' . sprintf(__('You currently have no name servers hosting zones. <a href="%s">Click here</a> to manage one or more servers.'), getMenuURL(_('Servers'))) . '</p>';

echo printPageHeader((string) $response, null, currentUserCan('manage_zones', $_SESSION['module']), $map, null, 'noscroll');

$sort_direction = null;
if ($map == 'groups') {
	$sort_field = 'group_name';
	if (isset($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']])) {
		extract($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']], EXTR_OVERWRITE);
	}
	$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domain_groups', 'group_name', 'group_', null, null, false, $sort_direction);
} else {
	$sort_field = 'domain_name';
	if (isset($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']])) {
		extract($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']], EXTR_OVERWRITE);
	}

	/** Get zones based on access */
	$user_capabilities = getUserCapabilities($_SESSION['user']['id'], 'all');

	$limited_domain_ids = ')';
	if (isset($user_capabilities[$_SESSION['module']]) && (array_key_exists('access_specific_zones', $user_capabilities[$_SESSION['module']]) && !array_key_exists('view_all', $user_capabilities[$_SESSION['module']]) && $user_capabilities[$_SESSION['module']]['access_specific_zones'][0])) {
		$limited_domain_ids = "OR domain_clone_domain_id>0) AND domain_id IN (";
		$limited_domain_ids .= join(',', $fm_dns_zones->getZoneAccessIDs($user_capabilities[$_SESSION['module']]['access_specific_zones'])) . ')';
	}

	/** Process domain_view filtering */
	if (isset($_GET['domain_view']) && !in_array(0, $_GET['domain_view'])) {
		foreach (array_merge(array(0), (array) $_GET['domain_view']) as $view_id) {
			$view_id = sanitize($view_id);
			(string) $domain_view_sql .= " (domain_view='$view_id' OR domain_view LIKE '$view_id;%' OR domain_view LIKE '%;$view_id;%' OR domain_view LIKE '%;$view_id') OR";
		}
		if ($domain_view_sql) {
			$domain_view_sql = ' AND (' . rtrim($domain_view_sql, ' OR') . ')';
		}
	}

	/** Process domain_group filtering */
	if (isset($_GET['domain_group']) && !in_array(0, $_GET['domain_group'])) {
		foreach (array_merge((array) $_GET['domain_group']) as $group_id) {
			$group_id = sanitize($group_id);
			(string) $domain_group_sql .= " (domain_groups='$group_id' OR domain_groups LIKE '$group_id;%' OR domain_groups LIKE '%;$group_id;%' OR domain_groups LIKE '%;$group_id') OR";
		}
		if ($domain_group_sql) {
			$domain_group_sql = ' AND (' . rtrim($domain_group_sql, ' OR') . ')';
		}
		$limited_domain_ids = "OR domain_clone_domain_id>0)";
	}

	if (getOption('zone_sort_hierarchical', $_SESSION['user']['account_id'], $_SESSION['module']) == 'yes') {
		if ($map == 'forward') {
			$query = "SELECT *,
				SUBSTRING_INDEX(`domain_name`, '.', -2) AS a, 
				SUBSTRING_INDEX(`domain_name`, '.', -3) AS b, 
				LPAD(SUBSTRING_INDEX(`domain_name`, '.', -4),255,'.') AS c 
				FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE `domain_status`!='deleted' AND account_id='1' AND domain_template='no' AND domain_mapping='{$map}' AND (domain_clone_domain_id='0' " . $limited_domain_ids . (string) $domain_view_sql . (string) $domain_group_sql . (string) $search_query . " 
				ORDER BY a $sort_direction, b $sort_direction, c $sort_direction, `domain_name` $sort_direction";
		} else {
			$query = "SELECT *,
				LPAD(REGEXP_SUBSTR(SUBSTRING_INDEX(domain_name, '.',1), '[0-9]+'),3,0) AS a,
				LPAD(REGEXP_SUBSTR(SUBSTRING_INDEX(SUBSTRING_INDEX(domain_name, '.',2),'.',-1), '[0-9]+'),3,0) AS b,
				LPAD(REGEXP_SUBSTR(SUBSTRING_INDEX(SUBSTRING_INDEX(domain_name, '.',3),'.',-1), '[0-9]+'),3,0) AS c
				FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE `domain_status`!='deleted' AND account_id='1' AND domain_template='no' AND domain_mapping='{$map}' AND (domain_clone_domain_id='0' " . $limited_domain_ids . (string) $domain_view_sql . (string) $domain_group_sql . (string) $search_query . " 
				ORDER BY c $sort_direction, b $sort_direction, a $sort_direction, `domain_name` $sort_direction";
		}
		$result = $fmdb->query($query);
	} else {
		$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', array($sort_field, 'domain_name'), 'domain_', "AND domain_template='no' AND domain_mapping='$map' AND (domain_clone_domain_id='0' $limited_domain_ids " . (string) $domain_view_sql . (string) $domain_group_sql . (string) $search_query, null, false, $sort_direction, false, "SUBSTRING_INDEX(`domain_name`, '.', -2),SUBSTRING_INDEX(`domain_name`, '.', 2),`domain_name`");
	}
}

$total_pages = ceil($fmdb->num_rows / $_SESSION['user']['record_count']);
if ($page > $total_pages) $page = $total_pages;

$fm_dns_zones->rows($result, $map, $reload_allowed, $page, $total_pages);

printFooter();
