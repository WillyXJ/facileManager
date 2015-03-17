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
			exec(findProgram('sudo') . ' ' . findProgram('php') . ' ' . dirname(dirname(__FILE__)) . '/dns.php buildconf' . $_POST['options'] . ' 2>&1', $output, $retval);
			if ($retval) {
				/** Something went wrong */
				$output[] = 'Config build failed.';
			} else {
				$output[] = 'Config build was successful.';
			}
			break;
		case 'reload':
			if (!isset($_POST['domain_id']) || !is_numeric($_POST['domain_id'])) {
				exit(serialize('Zone ID is not found.'));
			}
			
			exec(findProgram('sudo') . ' ' . findProgram('php') . ' ' . dirname(dirname(__FILE__)) . '/dns.php zones id=' . $_POST['domain_id'] . ' 2>&1', $rawoutput, $retval);
			if ($retval) {
				/** Something went wrong */
				$output[] = 'Zone reload failed.';
				$output[] = $rawoutput;
			} else {
				$output[] = 'Zone reload was successful.';
			}
			break;
		case 'upgrade':
			exec(findProgram('sudo') . ' ' . findProgram('php') . ' ' . dirname(dirname(__FILE__)) . '/dns.php upgrade 2>&1', $output);
			break;
	}
}

echo serialize($output);

?>