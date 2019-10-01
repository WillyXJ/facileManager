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
 | fmFirewall: Easily manage one or more software firewalls                |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmfirewall/                        |
 +-------------------------------------------------------------------------+
*/

if (!currentUserCan(array('manage_policies', 'view_all'), $_SESSION['module'])) unAuth();

/** Include module variables */
if (isset($_SESSION['module'])) include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/variables.inc.php');

$server_config_page = $GLOBALS['RELPATH'] . $menu[getParentMenuKey()][4];
$type = (isset($_GET['type']) && array_key_exists(sanitize(strtolower($_GET['type'])), $__FM_CONFIG['policy']['avail_types'])) ? sanitize(strtolower($_GET['type'])) : 'filter';
$server_serial_no = (isset($_GET['server_serial_no'])) ? sanitize($_GET['server_serial_no']) : null;
if ($server_serial_no === 0) {
	header('Location: ' . $GLOBALS['basename']);
	exit;
}
$original_server_serial_no = $server_serial_no;

/** Validate serial_no */
$valid = false;
if (getNameFromID($server_serial_no, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_id')) {
	$valid = true;
} elseif ($server_serial_no[0] == 't' && getNameFromID(preg_replace('/\D/', null, $server_serial_no), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_', 'policy_id', 'policy_status')) {
	$valid = true;
}

if ($valid === false) {
	$server_serial_no = $original_server_serial_no = null;
}

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_policies.php');

if (currentUserCan('manage_policies', $_SESSION['module'])) {
	$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'add';
	switch ($action) {
	case 'add':
		if (!empty($_POST)) {
			$result = $fm_module_policies->add($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else {
				header('Location: ' . $GLOBALS['basename'] . "?type=$type&server_serial_no=$server_serial_no");
				exit;
			}
		}
		break;
	case 'edit':
		if (!empty($_POST)) {
			$result = $fm_module_policies->update($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else {
				header('Location: ' . $GLOBALS['basename'] . "?type=$type&server_serial_no=$server_serial_no");
				exit;
			}
		}
		break;
	}
}

printHeader();
@printMenu();

$avail_types = buildSubMenu($type, $__FM_CONFIG['policy']['avail_types']);
$avail_servers = availableServers('serial', array('all'));
$j = 0;
/** Templates */
$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_name', 'policy_', "AND policy_type='template'");
if ($fmdb->num_rows && !$fmdb->sql_errors) {
	$avail_servers[__('Templates')][] = null;
	foreach ($fmdb->last_result as $results) {
		$avail_servers[__('Templates')][$j][] = $results->policy_name;
		$avail_servers[__('Templates')][$j][] = 't_' . $results->policy_id;
		$j++;
	}
}
$avail_servers = buildServerSubMenu($server_serial_no, $avail_servers);

$allowed_to_add = ($server_serial_no) ? currentUserCan('manage_policies', $_SESSION['module']) : false;
echo printPageHeader((string) $response, null, $allowed_to_add, $type, null, 'noscroll');
echo <<<HTML
<div id="pagination_container" class="submenus">
	<div>
	<div class="stretch"></div>
	$avail_servers
	</div>
</div>

HTML;

/** Get template ID if appropriate */
$template_id = 0;
$template_id_sql = null;
if ($server_serial_no[0] == 't') {
	$template_id = preg_replace('/\D/', null, $server_serial_no);
	$template_id_sql = "AND policy_template_id=$template_id";
	$server_serial_no = 0;
}

$fmdb->num_rows = 0;

/** Get policies for server including templates */
$server_id = getServerID($server_serial_no, $_SESSION['module']);

$tmp_id = ($server_serial_no) ? $server_id : $template_id;
$template_ids = getTemplateIDs($tmp_id, $server_serial_no);

if (count($template_ids)) {
	list($template_results, $template_id_count) = getTemplatePolicies($template_ids, $server_id, $template_id, $type);
}

$result = null;
if ($original_server_serial_no) {
	$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_order_id', 'policy_', "AND server_serial_no='$server_serial_no' AND policy_type='$type' $template_id_sql");
}
$fmdb->num_rows += $template_id_count;
$template_results = array_merge((array) $template_results, (array) $fmdb->last_result);
$fmdb->last_result = $template_results;
$total_pages = ceil($fmdb->num_rows / $_SESSION['user']['record_count']);
if ($page > $total_pages) $page = $total_pages;
$fm_module_policies->rows($result, $type, $page, $total_pages);

printFooter();

?>
