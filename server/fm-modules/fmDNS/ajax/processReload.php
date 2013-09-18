<?php

/**
 * Processes zone reloads
 *
 * @author		Jon LaBass
 * @version		$Id:$
 * @copyright	2013
 *
 */

if (!defined('AJAX')) define('AJAX', true);
require_once('../../../fm-init.php');

include(ABSPATH . 'fm-modules/fmDNS/classes/class_servers.php');
include(ABSPATH . 'fm-modules/fmDNS/classes/class_zones.php');

if (is_array($_POST) && count($_POST)) {
	if (isset($_POST['action']) && $_POST['action'] == 'build') {
		if (!$allowed_to_build_configs) {
			echo '<p class="error">You are not authorized to build server configs.</p>';
			exit;
		}
		$server_serial_no = getNameFromID($_POST['server_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_id', 'server_serial_no');
		echo $fm_dns_servers->buildServerConfig($server_serial_no);
		exit;
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