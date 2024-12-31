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
 | fmDHCP: Easily manage one or more ISC DHCP servers                      |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdhcp/                            |
 +-------------------------------------------------------------------------+
*/

/** Ensure user can use this page */
if (!currentUserCan(array('manage_leases', 'view_all'), $_SESSION['module'])) unAuth();

$server_serial_no = (isset($_REQUEST['server_serial_no'])) ? sanitize($_REQUEST['server_serial_no']) : 0;

printHeader();
@printMenu();

$addl_title_blocks[] = buildServerSubMenu($server_serial_no, null, null, __('Select a server'));
echo printPageHeader((string) $response, null, false, null, null, 'noscroll', $addl_title_blocks);

$placeholder = sprintf('<div><p>%s:</p>%s</div>', __('Please choose a server to view leases from.'), $addl_title_blocks[0]);

if ($server_serial_no) {
	include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_leases.php');
	$placeholder = $fm_dhcp_leases->getServerLeases($server_serial_no);
}

echo $placeholder;

printFooter();
