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
 | fmDNS: Easily manage one or more ISC BIND servers                       |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdns/                             |
 +-------------------------------------------------------------------------+
 | Processes config builds                                                 |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

/** Handle client interactions */
define('CLIENT', true);

require_once('fm-init.php');
include(ABSPATH . 'fm-modules/facileManager/classes/class_accounts.php');
include(ABSPATH . 'fm-modules/fmDNS/classes/class_buildconf.php');

/** Validate daemon version */
if (array_key_exists('action', $_POST) && $_POST['action'] == 'version_check') {
	$data = $fm_module_buildconf->validateDaemonVersion($_POST);
	if ($_POST['compress']) echo gzcompress(serialize($data));
	else echo serialize($data);
	exit;
}

/** Ensure we have a valid account */
$account_verify = $fm_accounts->verify($_POST);
if ($account_verify != 'Success') {
	if ($_POST['compress']) echo gzcompress(serialize($account_verify));
	else echo serialize($account_verify);
	exit;
}

/** Process action */
if (array_key_exists('action', $_POST)) {
	/** Process building of the server config */
	if ($_POST['action'] == 'buildconf') {
		$data = $fm_module_buildconf->buildServerConfig($_POST);
	}
	
	/** Process building of zone files */
	if ($_POST['action'] == 'zones') {
		$data = $fm_module_buildconf->buildZoneConfig($_POST);
	}
	
	/** Process building of whatever is required */
	if ($_POST['action'] == 'cron') {
		$data = $fm_module_buildconf->buildCronConfigs($_POST);
	}
	
	/** Process updating the tables */
	if ($_POST['action'] == 'update') {
		$data = $fm_module_buildconf->updateReloadFlags($_POST);
	}
	
	/** Output $data */
	if (!empty($data)) {
		if ($_POST['compress']) echo gzcompress(serialize($data));
		else echo serialize($data);
	}
	
	$fm_module_buildconf->updateServerVersion();
}

?>
