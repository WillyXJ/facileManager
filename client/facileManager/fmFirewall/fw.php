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
 | fmFirewall: Easily manage one or more software firewalls                |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmfirewall/                        |
 +-------------------------------------------------------------------------+
*/

/**
 * fmFirewall Client Utility
 *
 * @package fmFirewall
 * @subpackage Client
 *
 */

$module_name = basename(dirname(__FILE__));

$whoami = 'root';
$url = null;

/** Check for options */
$dryrun = (in_array('-n', $argv) || in_array('dryrun', $argv)) ? true : false;
$buildconf = (in_array('-b', $argv) || in_array('buildconf', $argv)) ? true : false;
$cron = (in_array('-c', $argv) || in_array('cron', $argv)) ? true : false;

/** Include shared client functions */
$fm_client_functions = dirname(dirname(__FILE__)) . '/functions.php';
if (file_exists($fm_client_functions)) {
	include_once($fm_client_functions);
} else {
	echo "The facileManager client scripts are not installed.\n";
	exit(1);
}

/** Check running user */
if (exec(findProgram('whoami')) != $whoami && !$dryrun) {
	echo "This script must run as $whoami.\n";
	exit(1);
}

/** Check if running supported version */
$data['server_version'] = detectFWVersion();

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
