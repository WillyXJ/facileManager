<?php

/**
 * Processes admin tools
 *
 * @author		Jon LaBass
 * @version		$Id:$
 * @copyright	2013
 *
 */

if (!defined('AJAX')) define('AJAX', true);
require_once('../../../fm-init.php');

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_tools.php');

$response = null;
if (is_array($_POST) && count($_POST) && $allowed_to_run_tools) {
	if (isset($_POST['task']) && !empty($_POST['task'])) {
		switch($_POST['task']) {
			case 'connect-test':
				$response = '<h2>Connectivity Test Results</h2>' . "\n";
				$response .= '<textarea rows="15" cols="80">' . "\n";
				$response .= $fm_dns_tools->connectTests();
				$response .= '</textarea>' . "\n";
				break;
		}
	}
	
	$response .= "<br />\n";
} else {
	echo '<h2>Error</h2>' . "\n";
	echo '<p>You are not authorized to run these tools.</p>' . "\n";
}

?>
