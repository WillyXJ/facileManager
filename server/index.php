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

/**
 * Processes main page
 *
 * @version		$Id:$
 * @copyright	2013
 *
 */

if (isset($_REQUEST['module_type']) && $_REQUEST['module_type'] == 'CLIENT') {
	define('CLIENT', true);
}
if (isset($_REQUEST['module_name'])) {
	@session_start();
	$_SESSION['module'] = $_REQUEST['module_name'];
}

require('fm-init.php');

if (is_array($GLOBALS)) {
	if (@array_key_exists('logout', $GLOBALS['URI'])) exit;
}

if (isset($GLOBALS['basename']) && $GLOBALS['basename'] == 'index.php') {
	require_once(ABSPATH . 'fm-includes/init.php');
	checkAppVersions();
}

if (function_exists('includeModuleFile')) {
	if (@file_exists(includeModuleFile($_SESSION['module'], $GLOBALS['basename']))) {
		@include(includeModuleFile($_SESSION['module'], $GLOBALS['basename']));
	} else throwHTTPError('404');
}

?>