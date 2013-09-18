<?php

/**
 * Processes main page
 *
 * @author		Jon LaBass
 * @version		$Id:$
 * @copyright	2013
 *
 */

if (isset($_POST['module_type']) && $_POST['module_type'] == 'CLIENT') {
	define('CLIENT', true);
}
if (isset($_POST['module_name'])) {
	$_SESSION['module'] = $_POST['module_name'];
}

require('fm-init.php');

if (array_key_exists('logout', $GLOBALS['URI'])) exit;

$_SERVER['REQUEST_URI'] = !strpos($_SERVER['REQUEST_URI'], '.php') ? str_replace('?', '.php?', $_SERVER['REQUEST_URI']) : $_SERVER['REQUEST_URI'];
$path_parts = pathinfo($_SERVER['REQUEST_URI']);
$GLOBALS['basename'] = (($path_parts['filename'] && $path_parts['filename'] != str_replace('/', '', $GLOBALS['RELPATH'])) && substr($_SERVER['REQUEST_URI'], -1) != '/') ? $path_parts['filename'] : 'index';

if ($GLOBALS['basename'] == 'index') {
	require_once(ABSPATH . 'fm-includes/init.php');
	checkAppVersions();
}

if (file_exists(includeModuleFile($_SESSION['module'], $GLOBALS['basename'] . '.php'))) {
	include(includeModuleFile($_SESSION['module'], $GLOBALS['basename'] . '.php'));
} else throwHTTPError('404');

?>