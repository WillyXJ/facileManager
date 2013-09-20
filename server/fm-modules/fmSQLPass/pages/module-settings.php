<?php

/**
 * Processes main page
 *
 * @author		Jon LaBass
 * @version		$Id:$
 * @copyright	2013
 *
 */

$page_name = 'Settings';

printHeader();
@printMenu($page_name, $page_name_sub);

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_settings.php');

if (isset($_POST['save'])) {
	if ($allowed_to_manage_backup_options) {
		if (!empty($_POST)) {
			if (!$fm_sqlpass_settings->save($_POST)) {
				$response = 'These backup options could not be saved.'. "\n";
			} else {
				$response = 'These backup options have been saved.'. "\n";
			}
		}
	}
}

echo <<<HTML
<div id="response" style="display: none;"></div>
<div id="body_container">
	<h2>{$_SESSION['module']} Settings</h2>

HTML;
	
echo $fm_module_settings->printForm();

echo '</div>' . "\n";

printFooter();

?>
