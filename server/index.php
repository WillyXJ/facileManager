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
*/

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