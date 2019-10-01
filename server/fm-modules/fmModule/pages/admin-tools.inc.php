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
 | fmModule: Brief module description                                      |
 +-------------------------------------------------------------------------+
 | http://URL                                                              |
 +-------------------------------------------------------------------------+
*/

$module_tools_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class_tools.php';
if (file_exists($module_tools_file) && !class_exists('fm_module_tools')) {
	include($module_tools_file);
}

$button = null;
if (currentUserCan('run_tools') && currentUserCan('manage_servers', $_SESSION['module'])) {
	$button = sprintf('<p class="step"><input id="button-1" name="submit" type="submit" value="%s" class="button" /> '
			. '<input id="button-2" name="submit" type="submit" value="%s" class="button" /></p>', __('Button 1'), __('Button 2'));
}

$tools_option[] = sprintf('<div id="admin-tools-select">
			<h2>%s</h2>
			<p>%s</p>
			%s
			</div>
			<br />', __('Module Tool Title'), __('Tool description.'), $button);

?>
