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
 | fmSQLPass: Change database user passwords across multiple servers.      |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmsqlpass/                         |
 +-------------------------------------------------------------------------+
 | Processes password management page                                      |
 +-------------------------------------------------------------------------+
*/

if (!currentUserCan(array('manage_passwords', 'view_all'), $_SESSION['module'])) unAuth();


printHeader();
@printMenu();

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_passwords.php');
include(ABSPATH . 'fm-modules/facileManager/classes/class_users.php');

echo printPageHeader((string) $response);

$result = basicGetList('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'groups', 'group_name', 'group_', 'active');
$total_pages = ceil($fmdb->num_rows / $_SESSION['user']['record_count']);
if ($page > $total_pages) $page = $total_pages;
$fm_sqlpass_passwords->rows($result, $page, $total_pages);

printFooter();

?>
