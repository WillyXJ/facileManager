<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2013-2019 The facileManager Team                          |
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
 | fmWifi: Easily manage hostapd on one or more systems                    |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmWifi/                            |
 +-------------------------------------------------------------------------+
*/

/**
 * fmWifi Client Utility
 *
 * @package fmWifi
 * @subpackage Client
 *
 */

/** Client version */
$data['server_client_version'] = '0.3';

error_reporting(0);

$module_name = basename(dirname(__FILE__));

/** Check for options */
$get_status = in_array('status', $argv) ? true : false;
$get_status_all = in_array('status-all', $argv) ? true : false;
$show_clients = in_array('show-clients', $argv) ? true : false;
$ebtables = (in_array('-e', $argv) || in_array('ebtables', $argv)) ? true : false;

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
	if ($argv[$i] == '-o') {
		$output_type = $argv[$i+1];
		$i++;
	}
	if (strncmp(strtolower($argv[$i+1]), 'block=', 6) == 0) {
		$block = true;
		$mac = substr($argv[$i+1], 6);
		if ($mac) {
			$bad_macs[] = $mac;
		}
		$i++;
	}
}

/** Ensure options meet requirements */
if (($block && !count((array) $bad_macs)) || ($ebtables && !$block)) {
	if ($output_type == 'human') echo fM("You must specify a MAC address (block=00:11:22:aa:bb:cc) to block.\n");
	exit(1);
}

if ($ebtables && $block) {
	/** Ensure ebtables is installed */
	$program = 'ebtables';
	if (!findProgram($program)) {
		if ($output_type == 'human') {
			echo fM(sprintf('Would you like me to try installing %s? [Y/n] ', $program));
			$auto_install = strtolower(trim(fgets(STDIN)));
			if (!$auto_install) {
				$auto_install = 'y';
			}

			if ($auto_install == 'y') {
				installPackage($program);
			} else {
				$ebtables = false;
				echo fM("Will not use ebtables to additionally block the MAC addresses.\n");
			}
		} else {
			$ebtables = false;
		}
	}
}

/** Check if running supported version */
$data['server_version'] = detectDaemonVersion();

/** Get system status */
if ($get_status) {
	apStatus();
}

/** Get full system status */
if ($get_status_all) {
	apStatus('all');
}

/** Show client connections and stats */
if ($show_clients) {
	apClientStats();
}

/** Block clients */
if ($block) {
	apBlockClient($bad_macs, $ebtables);
}

/** Build the configs provided by $url */
$retval = buildConf($url, $data);

if (!$retval) exit(1);

?>
