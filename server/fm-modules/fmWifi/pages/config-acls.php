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
 | fmWifi: Easily manage one or more access points                         |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmwifi/                            |
 +-------------------------------------------------------------------------+
*/

if (!isset($fm_wifi_acls)) {
	if (!class_exists('fm_wifi_acls')) {
		include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_acls.php');
	}
}

/** Ensure user can use this page */
$required_permission[] = 'manage_wlan_users';

$include_submenus = false;

/** Ensure user can use this page */
if (!currentUserCan(array_merge($required_permission, array('view_all')), $_SESSION['module'])) unAuth();

$server_serial_no = (isset($_REQUEST['server_serial_no'])) ? sanitize($_REQUEST['server_serial_no']) : 0;
if (!isset($display_type)) $display_type = null;
if (!isset($avail_types)) $avail_types = null;
if (!isset($include_submenus)) $include_submenus = true;

printHeader();
@printMenu();

echo printPageHeader((string) $response, $display_type, currentUserCan($required_permission, $_SESSION['module']), null, null, 'noscroll');

if ($include_submenus === true) {
//	$avail_servers = buildServerSubMenu($server_serial_no);
	echo <<<HTML
<div id="pagination_container" class="submenus">
	<div>
	<div class="stretch"></div>
	$avail_types
	$avail_servers
	</div>
</div>

HTML;
}
	
/** Process domain_view filtering */
if (isset($_GET['wlan_ids']) && !in_array(0, $_GET['wlan_ids'])) {
	foreach (array_merge(array(0), (array) $_GET['wlan_ids']) as $view_id) {
		(string) $domain_view_sql .= " (wlan_ids='$view_id' OR wlan_ids LIKE '$view_id;%' OR wlan_ids LIKE '%;$view_id;%' OR wlan_ids LIKE '%;$view_id') OR";
	}
	if ($domain_view_sql) {
		$domain_view_sql = ' AND (' . rtrim($domain_view_sql, ' OR') . ')';
	}
}

/** Get server listing */
$sort_direction = null;
$sort_field = 'acl_mac';
if (isset($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']])) {
	extract($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']], EXTR_OVERWRITE);
}

$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'acls', array('acl_id', $sort_field, 'acl_mac'), 'acl_', 'AND server_serial_no="' . $server_serial_no . '"' . (string) $domain_view_sql, null, false, $sort_direction);
$total_pages = ceil($fmdb->num_rows / $_SESSION['user']['record_count']);
if ($page > $total_pages) $page = $total_pages;
$fm_wifi_acls->rows($result, $page, $total_pages);

printFooter();
