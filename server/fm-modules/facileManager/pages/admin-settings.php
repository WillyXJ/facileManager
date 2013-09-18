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
$page_name_sub = 'Settings';

include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $fm_name . DIRECTORY_SEPARATOR . 'permissions.inc.php');
include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $fm_name . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class_settings.php');

printHeader();
@printMenu($page_name, $page_name_sub);

echo <<<HTML
<div id="response" style="display: none;"></div>
<div id="body_container">
	<h2>$fm_name Settings</h2>

HTML;
	
echo $fm_settings->printForm();

echo '</div>' . "\n";

printFooter();

?>
