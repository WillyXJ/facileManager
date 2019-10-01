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

if (is_array($_POST) && count($_POST)) {
	include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_zones.php');
	
	/** Clean variable */
	$domain_id = intval($_POST['domain_id']);
	
	/** Ensure user is allowed to reload zone */
	$zone_access_allowed = zoneAccessIsAllowed(array($domain_id), 'reload_zones');

	if ($domain_id && $zone_access_allowed) {
		echo buildPopup('header', __('Zone Reload Results'));
		
		if (isset($_POST['domain_id']) && !empty($_POST['domain_id'])) {
			$response = sprintf('<pre>%s</pre>', makePlainText($fm_dns_zones->buildZoneConfig($_POST['domain_id'])));
		}
		
		echo $response . "<br />\n";
	} else {
		echo buildPopup('header', _('Error'));
		printf('<p>%s</p>' . "\n", __('You are not authorized to reload this zone.'));
	}
}

?>