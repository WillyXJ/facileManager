<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2013-2020 The facileManager Team                          |
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
*/

if (!currentUserCan(array('manage_servers', 'view_all'), $_SESSION['module'])) unAuth();

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_rpz.php');

$display_option_type = __('Global');
$display_option_type_sql = 'rpz';
$server_serial_no = (isset($_REQUEST['server_serial_no'])) ? sanitize($_REQUEST['server_serial_no']) : 0;

$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'add';

/* Configure options for a view */
if (array_key_exists('view_id', $_GET) && !array_key_exists('server_id', $_GET)) {
	$view_id = (isset($_GET['view_id'])) ? sanitize($_GET['view_id']) : null;
	if (!$view_id) {
		header('Location: ' . $GLOBALS['basename']);
		exit;
	}
	
	basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'views', $view_id, 'view_', 'view_id');
	if (!$fmdb->num_rows) {
		header('Location: config-views.php');
		exit;
	}
	$view_info = $fmdb->last_result;
	
	$display_option_type = $view_info[0]->view_name;
	$display_option_type_sql .= "' AND view_id='$view_id";
	
	$name = 'view_id';
	$rel = $view_id;
/* Configure options for a server */
} elseif (array_key_exists('server_id', $_GET)) {
	$server_id = (isset($_GET['server_id'])) ? sanitize($_GET['server_id']) : null;
	if (!$server_id) {
		header('Location: ' . $GLOBALS['basename']);
		exit;
	}
	
	basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', $server_id, 'server_', 'server_id');
	if (!$fmdb->num_rows) {
		header('Location: config-servers.php');
		exit;
	}
	$server_info = $fmdb->last_result;
	
	$display_option_type = $server_info[0]->server_name;
	$display_option_type_sql .= "' AND server_id='$server_id";
	
	$name = 'server_id';
	$rel = $server_id;
} else {
	$view_id = $domain_id = $name = $rel = null;
	$display_option_type_sql .= "' AND view_id='0";
	if ($option_type == 'Global') $display_option_type_sql .= "' AND domain_id='0' AND server_id='0";
}

printHeader();
@printMenu();

$addl_title_blocks[] = buildServerSubMenu($server_serial_no);
$addl_title_blocks[] = buildViewSubMenu($view_id);

if (isset($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']])) {
	extract($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']], EXTR_OVERWRITE);
}

basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', array('cfg_order_id'), 'cfg_', "AND cfg_type='$display_option_type_sql' AND server_serial_no='$server_serial_no' AND cfg_name='!config_name!' AND domain_id=0 AND cfg_isparent='yes'", null, false, $sort_direction);
$global_result = $fmdb->last_result;
$global_num_rows = $fmdb->num_rows;
$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', array('cfg_order_id'), 'cfg_', "AND cfg_type='$display_option_type_sql' AND server_serial_no='$server_serial_no' AND cfg_name='!config_name!' AND domain_id>0 AND cfg_isparent='yes'", null, false, $sort_direction);
$tmp_last_result = array_merge((array) $global_result, (array) $fmdb->last_result);
$tmp_num_rows = $fmdb->num_rows + $global_num_rows;
$total_pages = ceil($tmp_num_rows / $_SESSION['user']['record_count']);
if ($page > $total_pages) $page = $total_pages;

/** RPZ is limited to 32 defined zones */
$perms = ($tmp_num_rows - $global_num_rows >= 32) ? false : currentUserCan('manage_zones', $_SESSION['module']);

echo printPageHeader(array((string) $response, getMinimumFeatureVersion('options', 'policy', 'message', "AND def_option_type='rpz'")), $display_option_type . ' ' . getPageTitle(), $perms, $name, $rel, null, $addl_title_blocks);

$fmdb->last_result = $tmp_last_result;
$fmdb->num_rows = $tmp_num_rows;
$fm_module_rpz->rows($fmdb->num_rows, $page, $total_pages);

printFooter();
