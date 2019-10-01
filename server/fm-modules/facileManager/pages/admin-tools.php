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
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/                                           |
 +-------------------------------------------------------------------------+
*/

if (!currentUserCan('run_tools')) unAuth();

if (!class_exists('fm_tools')) {
	include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . 'facileManager' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class_tools.php');
}

$admin_tools = $output = $block_style = $classes = null;
$response = isset($response) ? $response : null;
$tools_option = array();
$import_output = sprintf('<p>%s <i class="fa fa-spinner fa-spin"></i></p>', _('Processing...'));

if (array_key_exists('submit', $_POST)) {
	switch($_POST['submit']) {
		case _('Clean Up Database'):
			$response = $fm_tools->cleanupDatabase();
			break;
		case _('Backup Database'):
			$response = $fm_tools->backupDatabase();
			if (!$response) header('Location: ' . $GLOBALS['basename']);
			exit;
			break;
	}
}

printHeader();
@printMenu();

$backup_button = findProgram('mysqldump') ? sprintf('<p class="step"><input id="db-backup" name="submit" type="submit" value="%s" class="button" /></p>', _('Backup Database')) : sprintf(_('<p>The required mysqldump utility is not found on %s.</p>'), php_uname('n'));

$tools_option[] = '<h2>' . _('Backup Database') . '</h2>
			<p>' . _('Run an ad hoc backup of your database.') . "</p>
			$backup_button
			<br />";

$purge_logs = currentUserCan('do_everything') ? ' <input id="purge-logs" name="submit" type="submit" value="' . _('Purge Logs') . '" class="button double-click" />' : null;
$tools_option[] = '<h2>' . _('Clean Up Database') . '</h2>
			<p>' . _('You should periodically clean up your database to permanently remove deleted items. Make sure you backup your database first!') . '</p>
			<p class="step"><input id="db-cleanup" name="submit" type="submit" value="' . _('Clean Up Database') . '" class="button" />' . $purge_logs . '</p>
			<br />';

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

echo '<div id="body_container">' . "\n";
if (!empty($response)) echo '<div id="response">' . displayResponseClose($response) . "</div>\n";
else echo '<div id="response" style="display: none;"></div>' . "\n";

echo <<<HTML
	<div id="admin-tools">
		<form enctype="multipart/form-data" method="post" action="" id="admin-tools-form">
		$admin_tools
		</form>
	</div>
HTML;

printFooter($classes, $output, $block_style);

?>
