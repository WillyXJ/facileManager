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
*/

if (!array_key_exists('domain_id', $_GET)) {
	$perms = currentUserCan('manage_servers', $_SESSION['module']);
	if (!currentUserCan(array('manage_servers', 'view_all'), $_SESSION['module'])) unAuth();
}

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_options.php');

$option_type = (isset($_GET['type'])) ? sanitize($_GET['type']) : 'global';
if (!array_key_exists($option_type, $__FM_CONFIG['options']['avail_types'])) {
	header('Location: ' . $GLOBALS['basename']);
	exit;
}
$display_option_type = $__FM_CONFIG['options']['avail_types'][$option_type];
$display_option_type_sql = $option_type;
$display_option_sub = null;
$server_serial_no = (isset($_REQUEST['server_serial_no'])) ? sanitize($_REQUEST['server_serial_no']) : 0;

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
	
	$display_option_sub = $view_info[0]->view_name;
	$display_option_type_sql .= "' AND view_id='$view_id";
	
	$name = 'view_id';
	$rel = $view_id;
/* Configure options for a zone */
} elseif (array_key_exists('domain_id', $_GET)) {
	$domain_id = (isset($_GET['domain_id'])) ? sanitize($_GET['domain_id']) : null;
	if (!$domain_id) {
		header('Location: ' . $GLOBALS['basename']);
		exit;
	}
	
	/** Does the user have access? */
	$perms = zoneAccessIsAllowed(array($domain_id), 'manage_zones');
	if (!currentUserCan(array('access_specific_zones', 'view_all'), $_SESSION['module'], array(0, $domain_id))) unAuth();

	basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $domain_id, 'domain_', 'domain_id');
	if (!$fmdb->num_rows) {
		header('Location: zones.php');
		exit;
	}
	$domain_info = $fmdb->last_result;
	
	$display_option_sub = displayFriendlyDomainName($domain_info[0]->domain_name);
	$display_option_type_sql .= "' AND domain_id='$domain_id";
	
	$name = 'domain_id';
	$rel = $domain_id;
/* Configure options for a server (not server overrides) */
} elseif (array_key_exists('server_id', $_GET) && $display_option_type_sql != 'rpz') {
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
	
	$display_option_sub = $server_info[0]->server_name;
	$display_option_type_sql .= "' AND server_id='$server_id";
	
	$name = 'server_id';
	$rel = $server_id;
} else {
	$view_id = $domain_id = $name = $rel = null;
	$display_option_type_sql .= "' AND view_id='0";
	if ($option_type == 'global') $display_option_type_sql .= "' AND domain_id='0' AND server_id='0";
}

if ($display_option_sub) {
	$display_option_sub = sprintf(' (%s)', $display_option_sub);
}

printHeader();
@printMenu();

$addl_title_blocks[] = buildSubMenu($option_type, $__FM_CONFIG['options']['avail_types'], array('domain_id'));
if (!array_key_exists('server_id', $_GET) && !array_key_exists('domain_id', $_GET)) {
	$addl_title_blocks[] = buildViewSubMenu($view_id);
}
if (array_key_exists('server_id', $_GET) || array_key_exists('domain_id', $_GET)) {
	array_pop($__FM_CONFIG['options']['avail_types']);
	array_pop($__FM_CONFIG['options']['avail_types']);
}
$addl_title_blocks[] = buildServerSubMenu($server_serial_no);

$sort_direction = $comment = null;
$sort_field = 'cfg_name';

if ($option_type == 'rpz') {
	include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_rpz.php');
	$working_class = $fm_module_rpz;
	$comment = getMinimumFeatureVersion('options', 'policy', 'message', "AND def_option_type='rpz'");

	/** Get rpz for all zones */
	basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', array('cfg_order_id'), 'cfg_', "AND cfg_type='$display_option_type_sql' AND server_serial_no='$server_serial_no' AND cfg_name='!config_name!' AND domain_id='0' AND cfg_isparent='yes'", null, false, $sort_direction);
	$global_result = $fmdb->last_result;
	$global_num_rows = $fmdb->num_rows;

	/** Get rpz for defined zones */
	$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', array('cfg_order_id'), 'cfg_', "AND cfg_type='$display_option_type_sql' AND server_serial_no='$server_serial_no' AND cfg_name='!config_name!' AND domain_id!='0' AND cfg_isparent='yes'", null, false, $sort_direction);

	/** Merge arrays for future parsing */
	$tmp_last_result = array_merge((array) $global_result, (array) $fmdb->last_result);
	$tmp_num_rows = $fmdb->num_rows;

	/** RPZ is limited to 32 defined zones */
	if ($tmp_num_rows >= 32) $perms = false;

	$tmp_num_rows += $global_num_rows;
} else {
	$working_class = $fm_module_options;

	if (isset($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']])) {
		extract($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']], EXTR_OVERWRITE);
	}

	$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', array('domain_id', $sort_field, 'cfg_name'), 'cfg_', "AND cfg_type='$display_option_type_sql' AND server_serial_no='$server_serial_no'", null, false, $sort_direction);
	$tmp_last_result = $fmdb->last_result;
	$tmp_num_rows = $fmdb->num_rows;
}

echo printPageHeader(array('message' => (string) $response, 'comment' => $comment), $display_option_type . ' ' . getPageTitle() . $display_option_sub, $perms, $name, $rel, 'noscroll', $addl_title_blocks);

$fmdb->last_result = $tmp_last_result;
$fmdb->num_rows = $tmp_num_rows;
$total_pages = ceil($fmdb->num_rows / $_SESSION['user']['record_count']);
if ($page > $total_pages) $page = $total_pages;

$working_class->rows($fmdb->num_rows, $page, $total_pages);

printFooter();
