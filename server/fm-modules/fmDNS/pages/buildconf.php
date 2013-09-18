<?php

/**
 * Processes config builds
 *
 * @author		Jon LaBass
 * @version		$Id:$
 * @copyright	2013
 *
 */

/** Handle client interactions */
define('CLIENT', true);

require_once('fm-init.php');
include(ABSPATH . 'fm-modules/facileManager/classes/class_accounts.php');
include(ABSPATH . 'fm-modules/fmDNS/classes/class_buildconf.php');

/** Validate daemon version */
if (array_key_exists('action', $_POST) && $_POST['action'] == 'version_check') {
	$data = $fm_dns_buildconf->validateDaemonVersion($_POST);
	if ($_POST['compress']) echo gzcompress(serialize($data));
	else echo serialize($data);
	exit;
}

/** Ensure we have a valid account */
$account_verify = $fm_accounts->verify($_POST);
if ($account_verify != 'Success') {
	if ($_POST['compress']) echo gzcompress(serialize($account_verify));
	else echo serialize($account_verify);
	exit;
}

/** Process action */
if (array_key_exists('action', $_POST)) {
	/** Process building of the server config */
	if ($_POST['action'] == 'buildconf') {
		$data = $fm_dns_buildconf->buildServerConfig($_POST);
	}
	
	/** Process building of zone files */
	if ($_POST['action'] == 'zones') {
		$data = $fm_dns_buildconf->buildZoneConfig($_POST);
	}
	
	/** Process building of whatever is required */
	if ($_POST['action'] == 'cron') {
		$data = $fm_dns_buildconf->buildCronConfigs($_POST);
	}
	
	/** Process updating the tables */
	if ($_POST['action'] == 'update') {
		$data = $fm_dns_buildconf->updateReloadFlags($_POST);
	}
	
	/** Output $data */
	if (!empty($data)) {
		if ($_POST['compress']) echo gzcompress(serialize($data));
		else echo serialize($data);
	}
	
	$fm_dns_buildconf->updateServerVersion();
}

?>
