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
 | fmDNS: Easily manage one or more ISC BIND servers                       |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdns/                             |
 +-------------------------------------------------------------------------+
 | Add more form elements                                                  |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

if (!defined('AJAX')) define('AJAX', true);
require_once('../../../fm-init.php');

$zone_access_allowed = true;

include(ABSPATH . 'fm-modules/fmDNS/classes/class_records.php');

if (is_array($_POST) && count($_POST)) {
	echo 'hi';
	if ($allowed_to_manage_records) {
		if (array_key_exists('domain_id', $_POST) && array_key_exists('record_type', $_POST)) {
			extract($_POST);
			$additional_lines = $fm_dns_records->getInputForm($record_type, true, $domain_id, null, ($clicks * 4) + 5);
			echo $additional_lines;
		}
	}
}

?>
