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
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/                                           |
 +-------------------------------------------------------------------------+
 | Processes module admin tools                                            |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

if (!defined('AJAX')) define('AJAX', true);
require_once('../../../fm-init.php');

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_tools.php');

$response = null;
if (is_array($_POST) && count($_POST) && $allowed_to_run_tools) {
	if (isset($_POST['task']) && !empty($_POST['task'])) {
		switch($_POST['task']) {
			case 'connect-test':
				$response = '<h2>Connectivity Test Results</h2>' . "\n";
				$response .= '<textarea rows="15" cols="80">' . "\n";
				$response .= $fm_module_tools->connectTests();
				$response .= '</textarea>' . "\n";
				break;
		}
	}
	
	$response .= "<br />\n";
} else {
	echo '<h2>Error</h2>' . "\n";
	echo '<p>You are not authorized to run these tools.</p>' . "\n";
}

?>
