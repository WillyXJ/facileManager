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
 | http://www.facilemanager.com/                                           |
 +-------------------------------------------------------------------------+
 | Processes client installations                                          |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

/** Handle client installations */
if (arrayKeysExist(array('genserial', 'addserial', 'install'), $_GET)) {
	if (!defined('CLIENT')) define('CLIENT', true);
	
	require_once('fm-init.php');
	include(ABSPATH . 'fm-modules/' . $_POST['module_name'] . '/variables.inc.php');

	if (array_key_exists('genserial', $_GET)) {
		$module = ($_POST['module_name']) ? $_POST['module_name'] : $_SESSION['module'];
		$data['server_serial_no'] = generateSerialNo($module);
	}
	
	if (array_key_exists('addserial', $_GET)) {
		/** Client expects an array for a good return */
		$data = $_POST;
		
		include(ABSPATH . 'fm-modules/facileManager/classes/class_accounts.php');
		/** Check account key */
		$account_status = $fm_accounts->verifyAccount($_POST['AUTHKEY']);
		if ($account_status !== true) {
			$data = $account_status;
		} else {
			/** Does the record already exist for this account? */
			basicGet('fm_' . $__FM_CONFIG[$_POST['module_name']]['prefix'] . 'servers', $_POST['server_name'], 'server_', 'server_name');
			if ($fmdb->num_rows) {
				$server_array = $fmdb->last_result;
				$_POST['server_id'] = $server_array[0]->server_id;
				$update_server = moduleAddServer('update');
			} else {
				/** Add new server */
				$add_server = moduleAddServer('add');
				if ($add_server === false) {
					$data = "Could not add server to account.\n";
				}
			}
		}
	}
	
	if (array_key_exists('install', $_GET)) {
		include(ABSPATH . 'fm-modules/facileManager/classes/class_accounts.php');
		/** Check account key */
		$account_status = $fm_accounts->verifyAccount($_POST['AUTHKEY']);
		if ($account_status !== true) {
			$data = $account_status;
		} else {
			/** Set flags */
			$data = basicUpdate('fm_' . $__FM_CONFIG[$_POST['module_name']]['prefix'] . 'servers', $_POST['SERIALNO'], 'server_installed', 'yes', 'server_serial_no');
			if (function_exists('moduleCompleteInstallation')) {
				moduleCompleteClientInstallation();
			}
		}
	}
	
	if ($_POST['compress']) echo gzcompress(serialize($data));
	else echo serialize($data);
	exit;
}

?>