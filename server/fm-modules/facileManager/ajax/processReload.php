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
 | Processes zone reloads                                                  |
 +-------------------------------------------------------------------------+
*/

if (!defined('AJAX')) define('AJAX', true);
require_once('../../../fm-init.php');

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_servers.php');

if (is_array($_POST) && count($_POST)) {
	if (isset($_POST['action']) && $_POST['action'] == 'build') {
		if (!currentUserCan('build_server_configs', $_SESSION['module'])) {
			exit(displayResponseClose(_('You are not authorized to build server configs.')));
		}
		$server_serial_no = getNameFromID($_POST['server_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_id', 'server_serial_no');
		exit($fm_module_servers->buildServerConfig($server_serial_no));
	}
}

$shared_ajax_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'ajax' . DIRECTORY_SEPARATOR . 'processReload.php';
if (file_exists($shared_ajax_file) && $_SESSION['module'] != $fm_name) {
	include($shared_ajax_file);
}

$module_ajax_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'ajax' . DIRECTORY_SEPARATOR . 'processReload.php';
if (file_exists($module_ajax_file) && $_SESSION['module'] != $fm_name) {
	include($module_ajax_file);
}

echo buildPopup('footer', _('OK'), array('cancel_button' => 'cancel'));

?>
