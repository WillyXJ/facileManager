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
 | fmWifi: Easily manage one or more access points                         |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmwifi/                            |
 +-------------------------------------------------------------------------+
*/

if (!currentUserCan(array('manage_servers', 'view_all'), $_SESSION['module'])) unAuth();

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_options.php');

$option_type = (isset($_GET['type'])) ? sanitize(ucfirst($_GET['type'])) : 'Global';
$display_option_type = $option_type;
$display_option_type_sql = strtolower($option_type);
$server_serial_no = (isset($_REQUEST['server_serial_no'])) ? sanitize($_REQUEST['server_serial_no']) : 0;

/* Configure options for an item */
if (array_key_exists('item_id', $_GET)) {
	$item_id = (isset($_GET['item_id'])) ? sanitize($_GET['item_id']) : 0;
	if (!$item_id) {
		header('Location: ' . $GLOBALS['basename']);
		exit;
	}
	
	basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $item_id, 'config_', 'config_id');
	$item_info = $fmdb->last_result[0];
	if ($item_info->config_is_parent != 'yes') {
		header('Location: ' . $GLOBALS['basename']);
		exit;
	}
	
	$display_option_type = ucfirst($item_info->config_data);
	$display_option_type_sql = "{$item_info->config_type}', 'global') AND config_parent_id='$item_id' AND config_data!='";
	
	$name = 'config_parent_id';
	$rel = $item_id;
} else {
	$display_option_type_sql .= "') AND config_parent_id='0' AND config_is_parent='no";
}

if (currentUserCan('manage_servers', $_SESSION['module'])) {
	$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'add';
	$uri_params = generateURIParams(array('type', 'item_id', 'server_serial_no'), 'include');
	
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

$avail_servers = buildServerSubMenu($server_serial_no);
$avail_wlans = buildWLANSubMenu($item_id);

$sort_direction = null;
$sort_field = 'config_name';
if (isset($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']])) {
	extract($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']], EXTR_OVERWRITE);
}

echo printPageHeader((string) $response, $display_option_type . ' ' . getPageTitle(), currentUserCan('manage_servers', $_SESSION['module']), $name, $rel);
echo <<<HTML
<div id="pagination_container" class="submenus">
	<div>
	<div class="stretch"></div>
	<div id="configtypesmenu"></div>
	$avail_wlans
	$avail_servers
	</div>
</div>

HTML;
	
$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', array('config_id', $sort_field, 'config_name'), 'config_', "AND config_type IN ('$display_option_type_sql' AND server_serial_no='$server_serial_no'", null, false, $sort_direction);
$total_pages = ceil($fmdb->num_rows / $_SESSION['user']['record_count']);
if ($page > $total_pages) $page = $total_pages;
$fm_module_options->rows($result, $page, $total_pages);

printFooter();

/**
 * Builds the server listing in a dropdown menu
 *
 * @since 0.1
 * @package facileManager
 * @subpackage fmWifi
 *
 * @param integer $server_serial_no Selected server serial number
 * @param array $available_servers Available servers for the list
 * @param array $class Additional classes to pass to the div
 * @return string
 */
function buildObjectsSubMenu($item_id = 0) {
	$object_list = buildSelect('item_id', 'item_id', availableObjects(), $item_id, 1, null, false, 'this.form.submit()');
	
	$hidden_inputs = null;
	foreach ($GLOBALS['URI'] as $param => $value) {
		if ($param == 'item_id') continue;
		$hidden_inputs .= '<input type="hidden" name="' . $param . '" value="' . $value . '" />' . "\n";
	}
	
	$class = $class ? 'class="' . join(' ', (array) $class) . '"' : null;

	$return = <<<HTML
	<div id="configtypesmenu" $class>
		<form action="{$GLOBALS['basename']}" method="GET">
		$hidden_inputs
		$object_list
		</form>
	</div>
HTML;

	return $return;
}


?>
