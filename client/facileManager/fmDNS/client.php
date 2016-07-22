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
 | fmDNS: Easily manage one or more ISC BIND servers                       |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdns/                             |
 +-------------------------------------------------------------------------+
*/

/**
 * fmDNS Client Utility
 *
 * @package fmDNS
 * @subpackage Client
 *
 */

/** Client version */
$data['server_client_version'] = '2.2.3';

error_reporting(0);

$module_name = basename(dirname(__FILE__));

/** Check for options */
$zones = (in_array('-z', $argv) || in_array('zones', $argv)) ? true : false;
$dump_cache = in_array('dump-cache', $argv) ? true : false;
$clear_cache = in_array('clear-cache', $argv) ? true : false;

/** Include shared client functions */
$fm_client_functions = dirname(dirname(__FILE__)) . '/functions.php';
if (file_exists($fm_client_functions)) {
	include_once($fm_client_functions);
} else {
	echo "The facileManager core client scripts are required, but not found.\n";
	exit(1);
}

/** Get domain_id */
for ($i=0; $i < count($argv); $i++) {
	if (strncmp(strtolower($argv[$i]), 'id=', 3) == 0) {
		$data['domain_id'] = substr($argv[$i], 3);
	}
}

/** Check if running supported version */
$data['server_version'] = detectDaemonVersion();

/** Build the zone files */
if ($zones) {
	$data['action'] = 'zones';
}

/** Dump the cache */
if ($dump_cache) {
	manageCache('dumpdb -cache', 'Dumping cache');
}

/** Clear the cache */
if ($clear_cache) {
	manageCache('flush', 'Clearing cache');
}

/** Build the configs provided by $url */
$retval = buildConf($url, $data);

if (!$retval) exit(1);

?>
