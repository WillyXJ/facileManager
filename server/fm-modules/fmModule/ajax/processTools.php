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

/** 
 *  If your module has any extra admin tools, you can process them here.
 *  If not, you can remove this file.
 */

if (!defined('AJAX')) define('AJAX', true);
require_once('../../../fm-init.php');

if (is_array($_POST) && count($_POST) && currentUserCan('run_tools')) {
	if (isset($_POST['task']) && !empty($_POST['task'])) {
		switch($_POST['task']) {
			case 'button-1':
				$response = buildPopup('header', __('Button 1'));
				$response .= sprintf('<p>%s</p>', __('Button 1 was clicked.'));
				break;
			case 'button-2':
				$response = buildPopup('header', _('Error'));
				$response .= sprintf('<p>%s</p>', __('Button 2 generates an error.'));
				break;
		}
	}
}

?>