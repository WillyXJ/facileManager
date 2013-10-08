<?php

/**
 * fmDNS Client Utility HTTPD Handler
 *
 * @package fmDNS
 * @subpackage Client
 *
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/functions.php');

initWebRequest();

/** Process $_POST for buildconf or zone reload */
if (isset($_POST['action'])) {
	switch ($_POST['action']) {
		case 'buildconf':
			exec(findProgram('sudo') . ' ' . findProgram('php') . ' ' . dirname(dirname(__FILE__)) . '/dns.php buildconf', $output, $retval);
			if ($retval) {
				/** Something went wrong */
				$output[] = 'Config build failed.';
			} else {
				$output[] = 'Config build was successful.';
			}
			break;
		case 'reload':
			if (!isset($_POST['domain_id']) || !is_numeric($_POST['domain_id'])) {
				echo serialize('Zone ID is not found.');
				exit;
			}
			
			exec(findProgram('sudo') . ' ' . findProgram('php') . ' ' . dirname(dirname(__FILE__)) . '/dns.php zones id=' . $domain_id, $output, $retval);
			if ($retval) {
				/** Something went wrong */
				$output[] = 'Zone reload failed.';
			} else {
				$output[] = 'Zone reload was successful.';
			}
			break;
	}
}

echo serialize($output);

?>