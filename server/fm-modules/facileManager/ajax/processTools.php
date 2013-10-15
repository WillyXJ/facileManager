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
 | Processes admin tools                                                   |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

define('AJAX', true);
require_once('../../../fm-init.php');

include(ABSPATH . 'fm-modules/facileManager/classes/class_tools.php');
$shared_tools_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'ajax' . DIRECTORY_SEPARATOR . 'processTools.php';
if (file_exists($shared_tools_file) && $_SESSION['module'] != $fm_name) {
	include($shared_tools_file);
}

$module_tools_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'ajax' . DIRECTORY_SEPARATOR . 'processTools.php';
if (file_exists($module_tools_file) && $_SESSION['module'] != $fm_name) {
	include($module_tools_file);
}

if (is_array($_POST) && count($_POST) && $allowed_to_run_tools) {
	if (isset($_POST['task']) && !empty($_POST['task'])) {
		switch($_POST['task']) {
			case 'module_install':
				$module_name = isset($_POST['item']) ? sanitize($_POST['item']) : null;
				$response = '<h2>Installing Module</h2>' . "\n";
				$response .= $fm_tools->installModule($module_name);
				
				echo <<<MSG
				$response<br />
				<a href="{$GLOBALS['RELPATH']}admin-modules" class="button" id="cancel_button">OK</a>
MSG;
				exit;
				
				break;
			case 'module_upgrade':
				$module_name = isset($_POST['item']) ? sanitize($_POST['item']) : null;
				$response = '<h2>Upgrading Module</h2>' . "\n";
				$response .= $fm_tools->upgradeModule($module_name);
				
				echo <<<MSG
				$response<br />
				<a href="{$GLOBALS['RELPATH']}admin-modules" class="button" id="cancel_button">OK</a>
MSG;
				exit;
				
				break;
			case 'db-cleanup':
				$response = '<h2>Database Clean Up Results</h2>' . "\n";
				$response .= '<p>' . $fm_tools->cleanupDatabase() . '</p>';
				break;
		}
	}
} else {
	echo '<h2>Error</h2>' . "\n";
	echo '<p>You are not authorized to run this tool.</p>' . "\n";
}

echo $response . '<input type="submit" value="OK" class="button" id="cancel_button" />' . "\n";

?>
