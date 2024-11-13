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
 | fmFirewall: Easily manage one or more software firewalls                |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmfirewall/                        |
 +-------------------------------------------------------------------------+
*/

if (!currentUserCan(array('manage_services', 'view_all'), $_SESSION['module'])) unAuth();

define('FM_INCLUDE_SEARCH', true);

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_groups.php');

printHeader();
@printMenu();

$search_query = createSearchSQL(array('name', 'items', 'comment'), 'group_');

$group_type = 'service';

echo printPageHeader((string) $response, null, currentUserCan('manage_services', $_SESSION['module']), $group_type);

$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'groups', 'group_name', 'group_', "AND group_type='$group_type'" . $search_query);
$total_pages = ceil($fmdb->num_rows / $_SESSION['user']['record_count']);
if ($page > $total_pages) $page = $total_pages;
$fm_module_groups->rows($result, $group_type, $page, $total_pages);

printFooter();
