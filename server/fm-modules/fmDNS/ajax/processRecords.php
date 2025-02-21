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
 | Processes form posts                                                    |
 +-------------------------------------------------------------------------+
*/

if (!defined('AJAX')) define('AJAX', true);
require_once('../../../fm-init.php');

include_once(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $fm_name . DIRECTORY_SEPARATOR . 'ajax' . DIRECTORY_SEPARATOR . 'functions.php');

include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_records.php');

/** Make sure it's a valid request */
if (!is_array($_POST) || !array_key_exists('action', $_POST)) {
	exit;
}
if (!in_array($_POST['action'], array('process-record-updates', 'validate-record-updates'))) {
	exit;
}

if (!isset($_POST['uri_params']['record_type'])) $_POST['uri_params']['record_type'] = 'ALL';

/** Should the user be here? */
if (!isset($_POST['uri_params'])) returnUnAuth();
if (!currentUserCan('manage_records', $_SESSION['module'])) returnUnAuth();
if (!isset($_POST['uri_params']['domain_id']) || !zoneAccessIsAllowed(array($_POST['uri_params']['domain_id']))) returnUnAuth();
if (!isset($_POST['uri_params']['record_type']) || (in_array($_POST['uri_params']['record_type'], $__FM_CONFIG['records']['require_zone_rights']) && !currentUserCan('manage_zones', $_SESSION['module']))) returnUnAuth();

/* RR types that allow record append */
$append = array('CNAME', 'NS', 'MX', 'SRV', 'DNAME', 'RP', 'NAPTR');

if (!isset($global_form_field_excludes)) $global_form_field_excludes = array();

if ($_POST['action'] == 'validate-record-updates') {
	if (isset($_POST['record_type']) && $_POST['record_type'] == 'SOA') {
		$validate_response = $fm_dns_records->validateRecordUpdates('array');

		/* Success! */
		if (!is_array($validate_response)) exit($validate_response);

		/* Validation errors */
		if (count($validate_response[1])) {
			header("Content-type: application/json");
			exit(json_encode($validate_response));
		}

		/* Validation is clean, let's save */
		$_POST['action'] = 'process-record-updates';
	} else {
		echo $fm_dns_records->validateRecordUpdates();
	}
}

if ($_POST['action'] == 'process-record-updates') {
	include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/pages/zone-records-write.php');
	exit;
}
