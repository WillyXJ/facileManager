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
 | fmWifi: Easily manage one or more access points                         |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmwifi/                            |
 +-------------------------------------------------------------------------+
*/

/** Process action */
if (array_key_exists('action', $_POST)) {
	/** Include any additional module-specific build config actions */
	if ($_POST['action'] == 'status') {
		if (!class_exists('fm_module_servers')) {
			include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_servers.php');
		}
		$data = $fm_module_servers->getServerInfo($_POST['SERIALNO']);
	}	
	if ($_POST['action'] == 'status-upload') {
		if (!class_exists('fm_wifi_wlans')) {
			include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_wlans.php');
		}
		$fm_wifi_wlans->updateWLANInfo($_POST['SERIALNO']);
		exit;
	}	
}

?>
