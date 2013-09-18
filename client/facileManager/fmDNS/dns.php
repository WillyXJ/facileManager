<?php

/**
 * fmDNS Client Utility
 *
 * @package facileManager
 * @subpackage Client
 *
 */

$module_name = basename(dirname(__FILE__));

$whoami = 'root';
$url = null;

/** Check for options */
$dryrun = (in_array('-n', $argv) || in_array('dryrun', $argv)) ? true : false;
$buildconf = (in_array('-b', $argv) || in_array('buildconf', $argv)) ? true : false;
$zones = (in_array('-z', $argv) || in_array('zones', $argv)) ? true : false;
$cron = (in_array('-c', $argv) || in_array('cron', $argv)) ? true : false;

$fm_client_functions = dirname(dirname(__FILE__)) . '/functions.php';
if (file_exists($fm_client_functions)) {
	include_once($fm_client_functions);
} else {
	echo "The facileManager client scripts are not installed.\n";
	exit(1);
}

/** Get domain_id */
for ($i=0; $i < count($argv); $i++) {
	if (strncmp(strtolower($argv[$i]), 'id=', 3) == 0) {
		$data['domain_id'] = substr($argv[$i], 3);
	}
}

/** Check running user */
if (exec(findProgram('whoami')) != $whoami && !$dryrun) {
	echo "This script must run as $whoami.\n";
	exit(1);
}

/** Check if running supported version */
$data['server_version'] = detectDaemonVersion();

/** Build everything required via cron */
if ($cron) {
	$data['action'] = 'cron';
}

/** Build the server config */
if ($buildconf) {
	$data['action'] = 'buildconf';
}

/** Build the zone files */
if ($zones) {
	$data['action'] = 'zones';
}

/** Set variables to pass */
$url = $proto . '://' . FMHOST . 'buildconf';
$data['AUTHKEY'] = AUTHKEY;
$data['SERIALNO'] = SERIALNO;
$data['compress'] = $compress;
$data['dryrun'] = $dryrun;

/** Build the configs provided by $url */
$retval = buildConf($url, $data);

if (!$retval) exit(1);

?>
