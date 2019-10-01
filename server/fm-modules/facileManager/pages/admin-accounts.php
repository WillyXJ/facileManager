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

/** Handle client installations */
if (array_key_exists('verify', $_GET)) {
	if (!defined('CLIENT')) define('CLIENT', true);
	
	require_once('fm-init.php');
	include(ABSPATH . 'fm-modules/facileManager/classes/class_accounts.php');
	
	if (array_key_exists('compress', $_POST) && $_POST['compress']) echo gzcompress(serialize($fm_accounts->verify($_POST)));
	else echo serialize($fm_accounts->verify($_POST));
	exit;
}

header('Location: ' . $GLOBALS['RELPATH']);
exit;

?>