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
 * Checks the app functionality
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmModule
 *
 * @return string
 */
function moduleFunctionalCheck() {
	global $fmdb, $__FM_CONFIG;
	$html_checks = null;
	$checks = array();
	
	/** Perform checks to display in yellow bar */
	$checks[] = ($something == true) ? null : sprintf('<p>' . __('moduleFunctionalCheck() failed. User message goes here. <a href="%s">Click here</a> to define a linked page.') . '</p>', getMenuURL(__('Menu Title 2')));

	foreach ($checks as $val) {
		$html_checks .= $val;
	}
	
	return $html_checks;
}

/**
 * Builds the dashboard for display
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmModule
 *
 * @return string
 */
function buildModuleDashboard() {
	global $fmdb, $__FM_CONFIG;
	
	return sprintf('<p>%s</p>', sprintf(__('%s has no dashboard content yet.'), $_SESSION['module']));

}


/**
 * Builds the additional module menu for display
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmModule
 *
 * @return array
 */
function buildModuleToolbar() {
	global $__FM_CONFIG;
	
	if (isset($_GET['server_serial_no'])) {
		$server_name = getNameFromID($_GET['server_serial_no'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name');
		$domain_menu = sprintf('<div id="topheadpart">
			<span class="single_line">%s:&nbsp;&nbsp; %s</span>
		</div>', __('Firewall'), $server_name);
	} else $domain_menu = null;
	
	return array($domain_menu, null);
}


/**
 * Builds the help for display
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmModule
 *
 * @return none
 */
function buildModuleHelpFile() {
	global $__FM_CONFIG;
	
	$body = <<<HTML
<h3>{$_SESSION['module']}</h3>
<ul>
	<li>
		<a class="list_title">Help Menu Title 1</a>
		<div id="menu_id_1">
			<p>Text for the menu title.</p>
			<br />
		</div>
	</li>
	
HTML;
	
	return $body;
}


/**
 * Adds a server
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmModule
 *
 * @param string $action Add or edit
 * @return boolean
 */
function moduleAddServer($action) {
	include(ABSPATH . 'fm-modules/' . $_POST['module_name'] . '/classes/class_servers.php');
	
	return $fm_module_servers->$action($_POST);
}


/**
 * Gets the menu badge counts
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmModule
 *
 * @param string $type Which badge counts should be collected
 * @return boolean
 */
function getModuleBadgeCounts($type) {
	global $fmdb, $__FM_CONFIG;
	
	$badge_counts = null;
	if ($type == 'type1') {
		$badge_counts = array('submenu1' => 0, 'submenu2' => 0);
		
		/** Logic to set badge counts per submenu */
		
	} elseif ($type == 'servers' && currentUserCan('manage_servers', $_SESSION['module'])) {
		$server_builds = array();
		
		/** Servers */
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_', "AND `server_installed`!='yes' OR (`server_status`='active' AND `server_build_config`='yes')");
		$server_count = $fmdb->num_rows;
		if ($server_count) $server_results = $fmdb->last_result;
		for ($i=0; $i<$server_count; $i++) {
			$server_builds[] = $server_results[$i]->server_name;
		}
		
		/** Client software version check */
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_', "AND `server_client_version`!='" . getOption('client_version', 0, $_SESSION['module']) . "'");
		$server_count = $fmdb->num_rows;
		if ($server_count) $server_results = $fmdb->last_result;
		for ($i=0; $i<$server_count; $i++) {
			$server_builds[] = $server_results[$i]->server_name;
		}
		
		$servers = array_unique($server_builds);
		$badge_counts = count($servers);
		
		unset($server_builds, $servers, $server_count, $server_results);
	}
	
	return $badge_counts;
}


/**
 * Adds the module menu items
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmModule
 *
 * @return none
 */
function buildModuleMenu() {
	$badge_counts = getModuleBadgeCounts('type1');
	
	addObjectPage(__('Config'), _('Servers'), array('manage_servers', 'build_server_configs', 'view_all'), $_SESSION['module'], 'config-servers.php');
		addSubmenuPage('config-servers.php', _('Servers'), _('Servers'), array('manage_servers', 'build_server_configs', 'view_all'), $_SESSION['module'], 'config-servers.php', null, null, getModuleBadgeCounts('servers'));

	addObjectPage(__('Menu Title 1'), __('Page Title 1'), array('required_permissions', 'view_all'), $_SESSION['module'], 'page1.php');
		addSubmenuPage('page1.php', __('Submenu Title 1'), __('Submenu Page Title 1'), null, $_SESSION['module'], 'page1-submenu1.php', null, null, $badge_counts['submenu1']);
		addSubmenuPage('page1.php', __('Submenu Title 2'), __('Submenu Page Title 1'), null, $_SESSION['module'], 'page1-submenu2.php', null, null, $badge_counts['submenu2']);
	
	addObjectPage(__('Menu Title 2'), __('Page Title 2'), array('required_permissions', 'view_all'), $_SESSION['module'], 'page2.php');
	
	addSettingsPage($_SESSION['module'], sprintf(__('%s Settings'), $_SESSION['module']), array('manage_settings', 'view_all'), $_SESSION['module'], 'module-settings.php');
}


?>