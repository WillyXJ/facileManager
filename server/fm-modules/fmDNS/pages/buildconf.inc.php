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
 | http://www.facilemanager.com/modules/fmdns/                             |
 +-------------------------------------------------------------------------+
 | Processes config builds                                                 |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

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
