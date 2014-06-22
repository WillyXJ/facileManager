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

if (is_array($_POST) && count($_POST) && currentUserCan('run_tools')) {
	if (isset($_POST['task']) && !empty($_POST['task'])) {
		switch($_POST['task']) {
			case 'module_install':
				$module_name = isset($_POST['item']) ? sanitize($_POST['item']) : null;
				$response = buildPopup('header', 'Installing Module');
				$response .= $fm_tools->installModule($module_name);
				$response .= buildPopup('footer', 'OK', array('cancel_button' => 'cancel'), "{$GLOBALS['RELPATH']}admin-modules.php");
				
				echo $response;
				exit;
				
				break;
			case 'module_upgrade':
				$module_name = isset($_POST['item']) ? sanitize($_POST['item']) : null;
				$response = buildPopup('header', 'Upgrading Module');
				$response .= $fm_tools->upgradeModule($module_name);
				$response .= buildPopup('footer', 'OK', array('cancel_button' => 'cancel'), "{$GLOBALS['RELPATH']}admin-modules.php");
				
				echo $response;
				exit;
				
				break;
			case 'db-cleanup':
				$response = buildPopup('header', 'Database Clean Up Results');
				$response .= '<p>' . $fm_tools->cleanupDatabase() . '</p>';
				break;
		}
	}
} else {
	echo buildPopup('header', 'Error');
	echo '<p>You are not authorized to run this tool.</p>' . "\n";
}

echo $response . buildPopup('footer', 'OK', array('cancel_button' => 'cancel'));

?>
