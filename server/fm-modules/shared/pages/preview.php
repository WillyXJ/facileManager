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
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/                                   |
 +-------------------------------------------------------------------------+
 | Shows a preview of the server configuration files                       |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

define('CLIENT', true);

require('fm-init.php');

require_once(ABSPATH . 'fm-modules/facileManager/classes/class_logins.php');
include(ABSPATH . 'fm-modules/facileManager/classes/class_accounts.php');
include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_buildconf.php');

/** Enforce authentication */
if (!$fm_login->isLoggedIn()) {
	exit('<pre>Invalid account.</pre>');
}

$preview = $check_status = null;

if (array_key_exists('server_serial_no', $_GET) && is_numeric($_GET['server_serial_no'])) {
	extract($_GET);

	$data['SERIALNO'] = $server_serial_no;
	$data['compress'] = 0;
	$data['dryrun'] = true;

	basicGet('fm_accounts', $_SESSION['user']['account_id'], 'account_', 'account_id');
	$account_result = $fmdb->last_result;
	$data['AUTHKEY'] = $account_result[0]->account_key;

	$raw_data = $fm_module_buildconf->buildServerConfig($data);

	if (!is_array($raw_data)) {
		$preview = unserialize($raw_data);
	} else {
		list($preview, $check_status) = $fm_module_buildconf->processConfigs($raw_data);
	}
} else {
	$preview = 'Invalid Server ID.';
}

printHeader('Server Config Preview', 'facileManager', false, false);
echo $check_status . "<pre>\n" . $preview . "\n</pre>\n";
printFooter();

?>
