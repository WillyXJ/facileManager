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

/** Ensure user can use this page */
$required_permission[] = 'manage_wlan_users';
if (!currentUserCan(array_merge($required_permission, array('view_all')), $_SESSION['module'])) unAuth();

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_wlan_users.php');

printHeader();
@printMenu();

echo printPageHeader((string) $response, null, currentUserCan($required_permission, $_SESSION['module']), null, null, 'noscroll');

$sort_direction = null;
$sort_field = 'wlan_user_login';
if (isset($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']])) {
	extract($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']], EXTR_OVERWRITE);
}

$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'wlan_users', array($sort_field, 'wlan_user_login'), 'wlan_user_', null, null, false, $sort_direction);
$total_pages = ceil($fmdb->num_rows / $_SESSION['user']['record_count']);
if ($page > $total_pages) $page = $total_pages;
$fm_wifi_wlan_users->rows($result, $page, $total_pages);

printFooter();
