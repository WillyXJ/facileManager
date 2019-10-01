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
 | fmDHCP: Easily manage one or more ISC DHCP servers                      |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdhcp/                            |
 +-------------------------------------------------------------------------+
*/

$type = (isset($_GET['type']) && array_key_exists(sanitize(strtolower($_GET['type'])), $__FM_CONFIG['networks']['avail_types'])) ? sanitize(strtolower($_GET['type'])) : 'subnets';
$display_type = $__FM_CONFIG['networks']['avail_types'][$type];

$avail_types = buildSubMenu($type, $__FM_CONFIG['networks']['avail_types']);

if (!isset($fm_dhcp_item)) {
	if (!class_exists('fm_dhcp_networks')) {
		include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_networks.php');
	}

	$fm_dhcp_item = new fm_dhcp_networks();
}

/** Ensure user can use this page */
$required_permission[] = 'manage_networks';

include(dirname(__FILE__) . '/objects.php');

?>
