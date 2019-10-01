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
 | Shows the help files                                                    |
 +-------------------------------------------------------------------------+
*/

define('CLIENT', true);

require_once('fm-init.php');

require_once(ABSPATH . 'fm-modules/facileManager/classes/class_logins.php');

/** Enforce authentication */
if (!$fm_login->isLoggedIn()) {
	echo '<script>close();</script>';
	printf('<pre></pre>', _('You must be logged in to view these files.'));
	exit;
}

printHeader('fmHelp', 'facileManager', 'help-file');

echo '<div id="help_file_container" style="padding-top: 5em;">' . "\n";
echo buildHelpFile();
echo '</div>' . "\n";

printFooter();

?>
