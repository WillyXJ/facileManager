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

include(ABSPATH . 'fm-modules/fmDNS/classes/class_zones.php');

if (is_array($_POST) && count($_POST) && $allowed_to_reload_zones) {
	echo '<h2>Zone Reload Results</h2>' . "\n";
	
	if (isset($_POST['domain_id']) && !empty($_POST['domain_id'])) {
		$response = $fm_dns_zones->buildZoneConfig($_POST['domain_id']);
	}
	
	echo $response . "<br />\n";
} else {
	echo '<h2>Error</h2>' . "\n";
	echo '<p>You are not authorized to reload zones.</p>' . "\n";
}

echo '<br /><input type="submit" value="OK" class="button cancel" id="cancel_button" />' . "\n";

?>