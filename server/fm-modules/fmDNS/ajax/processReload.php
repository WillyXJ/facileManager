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
 | Processes zone reloads                                                  |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

if (!defined('AJAX')) define('AJAX', true);
require_once('../../../fm-init.php');

include(ABSPATH . 'fm-modules/fmDNS/classes/class_servers.php');
include(ABSPATH . 'fm-modules/fmDNS/classes/class_zones.php');

if (is_array($_POST) && count($_POST)) {
	if (isset($_POST['action']) && $_POST['action'] == 'build') {
		if (!$allowed_to_build_configs) {
			exit('<p class="error">You are not authorized to build server configs.</p>');
		}
		$server_serial_no = getNameFromID($_POST['server_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_id', 'server_serial_no');
		exit($fm_module_servers->buildServerConfig($server_serial_no));
	}
	if (isset($_POST['domain_id']) && $allowed_to_reload_zones) {
		echo '<h2>Zone Reload Results</h2>' . "\n";
		
		if (isset($_POST['domain_id']) && !empty($_POST['domain_id'])) {
			$response = $fm_dns_zones->buildZoneConfig($_POST['domain_id']);
		}
		
		echo $response . "<br />\n";
	} else {
		echo '<h2>Error</h2>' . "\n";
		echo '<p>You are not authorized to reload zones.</p>' . "\n";
	}
}

echo '<br /><input type="submit" value="OK" class="button cancel" id="cancel_button" />' . "\n";

?>