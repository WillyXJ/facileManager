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

$option_type = (isset($_GET['type'])) ? sanitize(ucfirst($_GET['type'])) : 'Global';
$display_option_type = $__FM_CONFIG['options']['avail_types'][strtolower($option_type)];
$display_option_type_sql = strtolower($option_type);
$server_serial_no = (isset($_REQUEST['server_serial_no'])) ? sanitize($_REQUEST['server_serial_no']) : 0;

/* Configure options for a view */
if (array_key_exists('view_id', $_GET) && !array_key_exists('server_id', $_GET)) {
	$view_id = (isset($_GET['view_id'])) ? sanitize($_GET['view_id']) : null;
	if (!$view_id) {
		header('Location: ' . $GLOBALS['basename']);
		exit;
	}
	
	basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', $view_id, 'view_', 'view_id');
	if (!$fmdb->num_rows) {
		header('Location: config-views.php');
		exit;
	}
	$view_info = $fmdb->last_result;
	
	$display_option_type = $view_info[0]->view_name;
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
	
	$display_option_type = displayFriendlyDomainName($domain_info[0]->domain_name);
	$display_option_type_sql .= "' AND domain_id='$domain_id";
	
	$name = 'domain_id';
	$rel = $domain_id;
/* Configure options for a server */
} elseif (array_key_exists('server_id', $_GET)) {
	$server_id = (isset($_GET['server_id'])) ? sanitize($_GET['server_id']) : null;
	if (!$server_id) {
		header('Location: ' . $GLOBALS['basename']);
		exit;
	}
	
	basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', $server_id, 'server_', 'server_id');
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

if ($perms) {
	$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'add';
	$uri_params = generateURIParams(array('type', 'view_id', 'domain_id', 'server_id', 'server_serial_no'), 'include');
	
	switch ($action) {
	case 'add':
		if (!empty($_POST)) {
			$result = $fm_module_options->add($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else {
				setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
				header('Location: ' . $GLOBALS['basename'] . $uri_params);
				exit;
			}
		}
		break;
	case 'edit':
		if (!empty($_POST)) {
			$result = $fm_module_options->update($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else {
				setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
				header('Location: ' . $GLOBALS['basename'] . $uri_params);
				exit;
			}
		}
	}
}

printHeader();
@printMenu();

if (array_key_exists('server_id', $_GET) || array_key_exists('domain_id', $_GET)) {
	array_pop($__FM_CONFIG['options']['avail_types']);
	array_pop($__FM_CONFIG['options']['avail_types']);
	$avail_views = null;
} else {
	$avail_views = buildViewSubMenu($view_id);
}
$avail_types = buildSubMenu(strtolower($option_type), $__FM_CONFIG['options']['avail_types'], array('domain_id'));
$avail_servers = buildServerSubMenu($server_serial_no);

$sort_direction = null;
$sort_field = 'cfg_name';
if (isset($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']])) {
	extract($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']], EXTR_OVERWRITE);
}

echo printPageHeader((string) $response, $display_option_type . ' ' . getPageTitle(), $perms, $name, $rel);
echo <<<HTML
<div id="pagination_container" class="submenus">
	<div>
	<div class="stretch"></div>
	$avail_types
	$avail_views
	$avail_servers
	</div>
</div>

HTML;
	
$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', array('domain_id', $sort_field, 'cfg_name'), 'cfg_', "AND cfg_type='$display_option_type_sql' AND server_serial_no='$server_serial_no'", null, false, $sort_direction);
$total_pages = ceil($fmdb->num_rows / $_SESSION['user']['record_count']);
if ($page > $total_pages) $page = $total_pages;
$fm_module_options->rows($result, $page, $total_pages);

printFooter();

?>
