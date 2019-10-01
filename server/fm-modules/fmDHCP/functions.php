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
 | fmDHCP: Easily manage one or more ISC DHCP servers                      |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdhcp/                            |
 +-------------------------------------------------------------------------+
*/

/**
 * Checks the app functionality
 *
 * @since 0.1
 * @package facileManager
 * @subpackage fmDHCP
 *
 * @return string
 */
function moduleFunctionalCheck() {
	global $fmdb, $__FM_CONFIG;
	$html_checks = null;
	$checks = array();
	
	/** Perform checks to display in yellow bar */
//	$checks[] = ($something == true) ? null : sprintf('<p>' . __('moduleFunctionalCheck() failed. User message goes here. <a href="%s">Click here</a> to define a linked page.') . '</p>', getMenuURL(__('Menu Title 2')));

	foreach ($checks as $val) {
		$html_checks .= $val;
	}
	
	return $html_checks;
}

/**
 * Builds the dashboard for display
 *
 * @since 0.1
 * @package facileManager
 * @subpackage fmDHCP
 *
 * @return string
 */
function buildModuleDashboard() {
	global $fmdb, $__FM_CONFIG;
	
	return <<<HTML
	<p>Features still to implement in {$_SESSION['module']} for v1.0 include:</p>
	<ul>
		<li>Server override configurations</li>
		<li>Configuration import tool</li>
		<li>IPv6</li>
		<li>Improved logging</li>
		<li>Help file</li>
		<li>Dashboard content</li>
	</ul>
	
HTML;
	
	return sprintf('<p>%s</p>', sprintf(__('%s has no dashboard content yet.'), $_SESSION['module']));

}


/**
 * Builds the help for display
 *
 * @since 0.1
 * @package facileManager
 * @subpackage fmDHCP
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
			<p>To be written.</p>
			<br />
		</div>
	</li>
	
HTML;
	
	return $body;
}


/**
 * Adds a server
 *
 * @since 0.1
 * @package facileManager
 * @subpackage fmDHCP
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
 * @since 0.1
 * @package facileManager
 * @subpackage fmDHCP
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
 * @since 0.1
 * @package facileManager
 * @subpackage fmDHCP
 *
 * @return none
 */
function buildModuleMenu() {
	$badge_counts = getModuleBadgeCounts('type1');
	
	addObjectPage(__('Objects'), __('Objects'), array('manage_hosts', 'manage_groups', 'manage_pools', 'manage_networks', 'manage_peers', 'view_all'), $_SESSION['module'], 'object-hosts.php');
		addSubmenuPage('object-hosts.php', __('Hosts'), __('Hosts'), array('manage_hosts', 'manage_servers', 'view_all'), $_SESSION['module'], 'object-hosts.php');
		addSubmenuPage('object-hosts.php', __('Groups'), __('Groups'), array('manage_groups', 'manage_servers', 'view_all'), $_SESSION['module'], 'object-groups.php');
		addSubmenuPage('object-hosts.php', __('Pools'), __('Pools'), array('manage_pools', 'manage_servers', 'view_all'), $_SESSION['module'], 'object-pools.php');
		addSubmenuPage('object-hosts.php', __('Networks'), __('Networks'), array('manage_networks', 'manage_servers', 'view_all'), $_SESSION['module'], 'object-networks.php');
		addSubmenuPage('object-hosts.php', __('Peers'), __('Failover Peers'), array('manage_peers', 'manage_servers', 'view_all'), $_SESSION['module'], 'object-peers.php');

	addObjectPage(__('Config'), _('Servers'), array('manage_servers', 'build_server_configs', 'view_all'), $_SESSION['module'], 'config-servers.php');
		addSubmenuPage('config-servers.php', _('Servers'), _('Servers'), array('manage_servers', 'build_server_configs', 'view_all'), $_SESSION['module'], 'config-servers.php', null, null, getModuleBadgeCounts('servers'));
		addSubmenuPage('config-servers.php', __('Options'), __('Options'), array('manage_servers', 'view_all'), $_SESSION['module'], 'config-options.php');

	addObjectPage(__('Leases'), __('Leases'), array('manage_leases', 'view_all'), $_SESSION['module'], 'leases.php');

	addSettingsPage($_SESSION['module'], sprintf(__('%s Settings'), $_SESSION['module']), array('manage_settings', 'view_all'), $_SESSION['module'], 'module-settings.php');
}


/**
 * Returns an array of objects
 *
 * @since 0.1
 * @package facileManager
 * @subpackage fmDHCP
 *
 * @param array $include What groups should be included
 * @return array
 */
function availableObjects($include = array('hosts', 'groups', 'pools', 'subnets', 'shared')) {
	global $fmdb, $__FM_CONFIG;
	
	if (!is_array($include)) {
		$include = (array) $include;
	}
	
	$server_array[0][] = null;
	$server_array[0][0][] = __('Global Options');
	$server_array[0][0][] = '0';
	
	foreach ($include as $type) {
		$j = 0;
		/** Server Groups */
		$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_data', 'config_', 'AND config_type="' . rtrim($type, 's') . '" AND config_name="' . rtrim($type, 's') . '"');
		if ($fmdb->num_rows && !$fmdb->sql_errors) {
			$server_array[__(ucfirst($type))][] = null;
			$results = $fmdb->last_result;
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$server_array[__(ucfirst($type))][$j][] = $results[$i]->config_data;
				$server_array[__(ucfirst($type))][$j][] = $results[$i]->config_id;
				$j++;
			}
		}
	}
	
	return $server_array;
}


?>