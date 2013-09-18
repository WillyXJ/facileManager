<?php

if (empty($_POST)) {
	echo "Incorrect parameters defined.";
	exit;
}

/** Get the config file */
if (file_exists('/usr/local/facileManager/config.inc.php')) {
	require('/usr/local/facileManager/config.inc.php');
}

if (!defined('SERIALNO')) {
	echo serialize('Cannot find the serial number for ' . php_uname('n') . '.');
	exit;
}

extract($_POST, EXTR_SKIP);

/** Ensure the serial numbers match so we don't work on the wrong server */
if ($serial_no != SERIALNO) {
	echo serialize('The serial numbers do not match for ' . php_uname('n') . '.');
	exit;
}

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



/**
 * Finds the path for $program
 *
 * @since 1.0
 * @package facileManager
 */
function findProgram($program) {
	$path = array('/bin', '/sbin', '/usr/bin', '/usr/sbin', '/usr/local/bin', '/usr/local/sbin');

	if (function_exists('is_executable')) {
		while ($this_path = current($path)) {
			if (is_executable("$this_path/$program")) {
				return "$this_path/$program";
			}
			next($path);
		}
	}

	return;
}

?>