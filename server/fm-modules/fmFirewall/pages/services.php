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

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_services.php');

printHeader();
@printMenu();

$search_query = createSearchSQL(array('name', 'type', 'src_ports', 'dest_ports', 'tcp_flags', 'comment'), 'service_');

//$allowed_to_add = ($type == 'custom' && currentUserCan('manage_services', $_SESSION['module'])) ? true : false;
echo printPageHeader((string) $response, null, currentUserCan('manage_services', $_SESSION['module']), 'tcp', null, 'noscroll');

$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', 'service_name', 'service_', $search_query);
$total_pages = ceil($fmdb->num_rows / $_SESSION['user']['record_count']);
if ($page > $total_pages) $page = $total_pages;
$fm_module_services->rows($result, $page, $total_pages);

printFooter();
