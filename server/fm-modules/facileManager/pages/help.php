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
 | Shows the help files                                                    |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

define('CLIENT', true);

require_once('fm-init.php');

require_once(ABSPATH . 'fm-modules/facileManager/classes/class_logins.php');

/** Enforce authentication */
if (!$fm_login->isLoggedIn()) {
	echo '<script>close();</script>';
	echo '<pre>You must be logged in to view these files.</pre>';
	exit;
}

printHeader('fmHelp', 'facileManager', true);

echo '<div id="help_file_container" style="padding-top: 5em;">' . "\n";
echo buildHelpFile();
echo '</div>' . "\n";

printFooter();

?>
