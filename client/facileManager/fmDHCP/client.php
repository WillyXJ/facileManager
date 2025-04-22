<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) The facileManager Team                                    |
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
 | fmDHCP: Easily manage one or more ISC DHCP servers                      |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmDHCP/                            |
 +-------------------------------------------------------------------------+
*/

/**
 * fmDHCP Client Utility
 *
 * @package fmDHCP
 * @subpackage Client
 *
 */

/** Client version */
$data['server_client_version'] = '0.10.1';

error_reporting(0);

$module_name = basename(dirname(__FILE__));

/** Check for options */
$dump_leases = $delete_lease = false;
$lease = null;
$output_type = 'human';

/** Include shared client functions */
$fm_client_functions = dirname(dirname(__FILE__)) . '/functions.php';
if (file_exists($fm_client_functions)) {
	include_once($fm_client_functions);
} else {
	echo fM("The facileManager client scripts are not installed.\n");
	exit(1);
}

/** Get long options */
for ($i=0; $i < count($argv); $i++) {
	if ($argv[$i] == '-l') {
		if ($argv[$i+1] == 'dump') {
			$dump_leases = true;
		}
		if ($argv[$i+1] == 'delete') {
			$delete_lease = true;
		}
		if (strncmp(strtolower($argv[$i+1]), 'delete=', 7) == 0) {
			$delete_lease = true;
			$lease = substr($argv[$i+1], 7);
		}
		$i++;
	}
	if ($argv[$i] == '-o') {
		$output_type = $argv[$i+1];
		$i++;
	}
}

/** Ensure options meet requirements */
if ($dump_leases && $delete_lease) {
	echo fM("You cannot specify to dump and delete leases together.\n");
	exit(1);
}
if ($delete_lease && !$lease) {
	echo fM("You must specify a lease (IP address) to delete.\n");
	exit(1);
}

/** Check if running supported version */
$data['server_version'] = detectAppVersion();

/** Dump the leases */
if ($dump_leases || $delete_lease) {
	$lease_file_locations = array(
		'/var/db/dhcpd.leases',
		'/var/lib/dhcp/dhcpd.leases',
		'/var/lib/dhcpd/dhcpd.leases',
		'/var/lib/dhcp3/dhcpd.leases',
		'/var/lib/dhcp/dhcpd/state/dhcpd.leases'
	);
	foreach ($lease_file_locations as $leasefile) {
		if (file_exists($leasefile)) {
			if ($dump_leases) {
				dumpLeases($leasefile);
			} else {
				deleteLease($leasefile, $lease);
			}
		}
	}
	echo fM("Could not locate the dhcpd.leases file!\n");
	exit(1);
}

/** Build the configs provided by $url */
$retval = buildConf($url, $data);

if (!$retval) exit(1);
