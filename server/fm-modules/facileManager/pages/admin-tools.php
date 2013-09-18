<?php

/**
 * Processes admin-settings page
 *
 * @author		Jon LaBass
 * @version		$Id:$
 * @copyright	2013
 *
 */

$page_name = 'Admin';
$page_name_sub = 'Tools';

include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . 'facileManager' . DIRECTORY_SEPARATOR . 'permissions.inc.php');
include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . 'facileManager' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class_tools.php');

$admin_tools = $output = $block_style = null;
$response = isset($response) ? $response : null;
$tools_option = array();
$import_output = '<p>Processing...</p>';

$disabled = ($allowed_to_run_tools) ? null : 'disabled';

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
@printMenu($page_name, $page_name_sub);

if (!empty($response)) echo '<div id="response">' . $response . '</div>';
echo '<div id="body_container"';
if (!empty($response)) echo ' style="margin-top: 4em;"';

$backup_button = findProgram('mysqldump') ? '<p class="step"><input id="db-backup" name="submit" type="submit" value="Backup Database" class="button" ' . $disabled . ' /></p>' : '<p>The required mysqldump utility is not found on ' . php_uname('n') . '.</p>';

$tools_option[] = <<<HTML
			<h2>Backup Database</h2>
			<p>Run an ad hoc backup of your database.</p>
			$backup_button
			<br />
HTML;

$tools_option[] = <<<HTML
			<h2>Clean Up Database</h2>
			<p>You should periodically clean up your database to permanently remove deleted items. Make sure you backup your database first!</p>
			<p class="step"><input id="db-cleanup" name="submit" type="submit" value="Clean Up Database" class="button" $disabled /></p>
			<br />
HTML;

/** Get available module tools */
$module_var_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'variables.inc.php';
if (file_exists($module_var_file)) {
	include($module_var_file);
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
