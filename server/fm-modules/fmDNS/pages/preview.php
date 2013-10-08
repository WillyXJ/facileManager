<?php

/**
 * Shows a preview of the configuration files
 *
 * @author		Jon LaBass
 * @version		$Id:$
 * @copyright	2013
 *
 */

define('CLIENT', true);

require('fm-init.php');

require_once(ABSPATH . 'fm-modules/facileManager/classes/class_logins.php');
include(ABSPATH . 'fm-modules/facileManager/classes/class_accounts.php');
include(ABSPATH . 'fm-modules/fmDNS/classes/class_buildconf.php');

/** Enforce authentication */
if (!$fm_login->isLoggedIn()) {
	echo '<pre>Invalid account.</pre>';
	exit;
}

$preview = $named_check_status = null;

if (array_key_exists('server_serial_no', $_GET) && is_numeric($_GET['server_serial_no'])) {
	extract($_GET);

	$data['SERIALNO'] = $server_serial_no;
	$data['compress'] = 0;
	$data['dryrun'] = true;

	basicGet('fm_accounts', $_SESSION['user']['account_id'], 'account_', 'account_id');
	$account_result = $fmdb->last_result;
	$data['AUTHKEY'] = $account_result[0]->account_key;

	$raw_data = $fm_module_buildconf->buildServerConfig($data);

	if (!is_array($raw_data)) {
		$preview = unserialize($raw_data);
	} else {
		$named_check_status = $fm_module_buildconf->namedSyntaxChecks($raw_data);
		foreach ($raw_data['files'] as $filename => $contents) {
			$preview .= str_repeat('=', 75) . "\n";
			$preview .= $filename . ":\n";
			$preview .= str_repeat('=', 75) . "\n";
			$preview .= $contents . "\n\n";
		}
	}
} else {
	$preview = 'Invalid Server ID.';
}

printHeader('Server Config Preview', 'facileManager', false, false);
echo $named_check_status . '<pre>' . $preview . '</pre>';
printFooter();

?>
