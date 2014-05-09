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
 | fmSQLPass: Change database user passwords across multiple servers.      |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmsqlpass/                         |
 +-------------------------------------------------------------------------+
 | Processes password management page                                      |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

if (!currentUserCan(array('manage_passwords', 'view_all'), $_SESSION['module'])) unAuth();

$response = isset($response) ? $response : null;

printHeader();
@printMenu();

include(ABSPATH . 'fm-modules/fmSQLPass/classes/class_passwords.php');
include(ABSPATH . 'fm-modules/facileManager/classes/class_users.php');

echo printPageHeader($response);

$result = basicGetList('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'groups', 'group_name', 'group_', 'active');
$fm_sqlpass_passwords->rows($result);

printFooter();

?>
