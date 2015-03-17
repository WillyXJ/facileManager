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
 | Includes common module tools                                            |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

if ($_SESSION['module'] != $fm_name) {
	$module_tools_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class_tools.php';
	if (file_exists($module_tools_file) && !class_exists('fm_module_tools')) {
		include($module_tools_file);
	}

	if (method_exists($fm_module_tools, 'connectTests')) {
		$tools_option[] = sprintf('
			<h2>%s</h2>
			<p>%s</p>
			<p class="step"><input id="connect-test" name="submit" type="submit" value="%s" class="button" %s/></p>
			<br />', _('Connection Tests'), sprintf(_('Test the connectivity of your %s servers with the %s server.'), $_SESSION['module'], $fm_name),
				_('Run Tests'), $disabled);
	}
}

?>
