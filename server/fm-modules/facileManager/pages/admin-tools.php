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
 | Processes tools page                                                    |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

if (!currentUserCan('run_tools')) unAuth();

if (!class_exists('fm_tools')) {
	include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . 'facileManager' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class_tools.php');
}

$admin_tools = $output = $block_style = null;
$response = isset($response) ? $response : null;
$tools_option = array();
$import_output = '<p>Processing...</p>';

if (array_key_exists('submit', $_POST)) {
	switch($_POST['submit']) {
		case 'Clean Up Database':
			$response = $fm_tools->cleanupDatabase();
			break;
		case 'Backup Database':
			$response = $fm_tools->backupDatabase();
			if (!$response) header('Location: ' . $GLOBALS['basename']);
			break;
	}
}

printHeader();
@printMenu();

if (!empty($response)) echo '<div id="response">' . $response . '</div>';
echo '<div id="body_container"';
if (!empty($response)) echo ' style="margin-top: 4em;"';

$backup_button = findProgram('mysqldump') ? '<p class="step"><input id="db-backup" name="submit" type="submit" value="Backup Database" class="button" /></p>' : '<p>The required mysqldump utility is not found on ' . php_uname('n') . '.</p>';

$tools_option[] = <<<HTML
			<h2>Backup Database</h2>
			<p>Run an ad hoc backup of your database.</p>
			$backup_button
			<br />
HTML;

$tools_option[] = <<<HTML
			<h2>Clean Up Database</h2>
			<p>You should periodically clean up your database to permanently remove deleted items. Make sure you backup your database first!</p>
			<p class="step"><input id="db-cleanup" name="submit" type="submit" value="Clean Up Database" class="button" /></p>
			<br />
HTML;

/** Get available module tools */
$module_var_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'variables.inc.php';
if (file_exists($module_var_file)) {
	include($module_var_file);
}
$shared_tools_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'admin-tools.inc.php';
if (file_exists($shared_tools_file)) {
	include($shared_tools_file);
}
$module_tools_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'admin-tools.inc.php';
if (file_exists($module_tools_file)) {
	include($module_tools_file);
}

foreach ($tools_option as $tool) {
	$admin_tools .= $tool;
}

echo <<<HTML
>
	<div id="admin-tools">
		<form enctype="multipart/form-data" method="post" action="">
		$admin_tools
		</form>
	</div>
HTML;

printFooter($output, $block_style);

?>
