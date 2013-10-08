<?php

/**
 * Processes admin-settings page
 *
 * @author		Jon LaBass
 * @version		$Id:$
 * @copyright	2013
 *
 */

$module_tools_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class_tools.php';
if (file_exists($module_tools_file) && !class_exists('fm_module_tools')) {
	include($module_tools_file);
}

if (method_exists($fm_module_tools, 'connectTests')) {
	$tools_option[] = <<<HTML
			<h2>Connection Tests</h2>
			<p>Test the connectivity of your {$_SESSION['module']} servers with the $fm_name server.</p>
			<p class="step"><input id="connect-test" name="submit" type="submit" value="Run Tests" class="button" $disabled /></p>
			<br />
HTML;
}

?>
