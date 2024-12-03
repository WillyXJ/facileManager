<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) The facileManager Team                                    |
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
 +-------------------------------------------------------------------------+
*/

if (!defined('AJAX')) define('AJAX', true);
require_once('../../../fm-init.php');

$zone_access_allowed = true;

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_records.php');

if (is_array($_POST) && count($_POST)) {
	if (isset($_POST['action']) && $_POST['action'] == 'get-record-value-form' && isset($_POST['record_type'])) {
		extract($_POST);
		$index = 1;
		if (isset($_POST['id_index'])) {
			preg_match('/\[\d+\]/', $_POST['id_index'], $matches);
			if ($matches) {
				$index = intval(str_replace(array('[', ']'), '', $matches[0]));
			}
		}
		$form = $fm_dns_records->getInputForm($record_type, true, $domain_id, null, 'record-value-group-only', $index);
		echo $form;
		exit;
	}
	if (currentUserCan('manage_records', $_SESSION['module'])) {
		if (array_key_exists('domain_id', $_POST) && array_key_exists('record_type', $_POST)) {
			extract($_POST);
			$additional_lines = $fm_dns_records->getInputForm($record_type, true, $domain_id, null, 'no-actions', ($clicks) + 1);
			echo $additional_lines;
		}
	}
}
