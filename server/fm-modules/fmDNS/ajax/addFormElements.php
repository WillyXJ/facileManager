<?php

/**
 * Add more form elements
 *
 * @author		Jon LaBass
 * @version		$Id:$
 * @copyright	2013
 *
 */

if (!defined('AJAX')) define('AJAX', true);
require_once('../../../fm-init.php');

include(ABSPATH . 'fm-modules/fmDNS/classes/class_records.php');

if (is_array($_POST) && count($_POST)) {
	if ($allowed_to_manage_zones) {
		if (array_key_exists('domain_id', $_POST) && array_key_exists('record_type', $_POST)) {
			extract($_POST);
			$additional_lines = $fm_dns_records->getInputForm($record_type, true, $domain_id, null, 5);
			echo $additional_lines;
		}
	}
}

?>
