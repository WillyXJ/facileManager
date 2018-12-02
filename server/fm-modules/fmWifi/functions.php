<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2013-2018 The facileManager Team                               |
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
 | fmWifi: Brief module description                                      |
 +-------------------------------------------------------------------------+
 | http://URL                                                              |
 +-------------------------------------------------------------------------+
*/

/**
 * Checks the app functionality
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmWifi
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
 * @subpackage fmWifi
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
 * @subpackage fmWifi
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
 * @subpackage fmWifi
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
 * @subpackage fmWifi
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
 * @subpackage fmWifi
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
 * @subpackage fmWifi
 *
 * @return none
 */
function buildModuleMenu() {
	$badge_counts = getModuleBadgeCounts('type1');
	
	addObjectPage(__('Config'), _('Access Points'), array('manage_servers', 'build_server_configs', 'view_all'), $_SESSION['module'], 'config-servers.php');
		addSubmenuPage('config-servers.php', _('Access Points'), _('Access Points'), array('manage_servers', 'build_server_configs', 'view_all'), $_SESSION['module'], 'config-servers.php', null, null, getModuleBadgeCounts('servers'));

	addObjectPage(__('WLAN'), __('Manage WLANs'), array('manage_wlans', 'view_all'), $_SESSION['module'], 'config-wlans.php');
		addSubmenuPage('config-wlans.php', _('Manage'), _('Manage WLANs'), array('manage_wlans', 'build_server_configs', 'view_all'), $_SESSION['module'], 'config-wlans.php');
//		addSubmenuPage('config-wlans.php', __('Options'), __('Options'), array('manage_wlans', 'view_all'), $_SESSION['module'], 'config-options.php');
		addSubmenuPage('config-wlans.php', __('Users'), __('Users'), array('manage_wlan_users', 'view_all'), $_SESSION['module'], 'config-users.php');
		addSubmenuPage('config-wlans.php', __('ACLs'), __('ACLs'), array('manage_wlans', 'view_all'), $_SESSION['module'], 'config-acls.php');
	
	addSettingsPage($_SESSION['module'], sprintf(__('%s Settings'), $_SESSION['module']), array('manage_settings', 'view_all'), $_SESSION['module'], 'module-settings.php');
}


/**
 * Gets the APs hosting a WLAN
 *
 * @since 0.1
 * @package facileManager
 * @subpackage fmWifi
 *
 * @param id $id WLAN ID to check
 * @return string
 */
function getWLANServers($id) {
	global $__FM_CONFIG, $fmdb;
	
	$serial_no = null;
	
	if ($id) {
		/** Force buildconf for all associated servers */
		$configured_servers = getNameFromID($id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_', 'config_id', 'config_aps');
		if ($configured_servers) {
			$servers = getHostedServers($configured_servers);
			
			/** Loop through name servers */
			if ($servers) {
				$server_count = $fmdb->num_rows;
				for ($i=0; $i<$server_count; $i++) {
					$serial_no[] = $servers[$i]->server_serial_no;
				}
				$serial_no = implode(',', $serial_no);
			}
		}
	}
	
	return $serial_no;
}


/**
 * Gets the servers from groups
 *
 * @since 0.1
 * @package facileManager
 * @subpackage fmWifi
 *
 * @param array $configured_servers Configured servers for WLAN
 * @return string
 */
function getHostedServers($configured_servers) {
	global $fmdb, $__FM_CONFIG;

	/** Check domain_name_servers */
	if ($configured_servers) {
		$configured_servers = explode(';', rtrim($configured_servers, ';'));
		$servers_sql = 'AND `server_id` IN (';
		foreach($configured_servers as $server) {
			if ($server[0] == 's') $server = str_replace('s_', '', $server);

			/** Process server groups */
			if ($server[0] == 'g') {
				$group_servers = getNameFromID(preg_replace('/\D/', null, $server), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'server_groups', 'group_', 'group_id', 'group_members');

				foreach (explode(';', $group_servers) as $server_id) {
					if (!empty($server_id)) $servers_sql .= sprintf("'%s',", str_replace('s_', '', $server_id));
				}
			} else {
				if (!empty($server)) $servers_sql .= "'$server',";
			}
		}
		$servers_sql = rtrim($servers_sql, ',') . ')';
	} else $servers_sql = null;

	$query = "SELECT * FROM `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}servers` WHERE `server_status`='active' AND account_id='{$_SESSION['user']['account_id']}' $servers_sql ORDER BY `server_update_method`";
	$result = $fmdb->query($query);

	/** No name servers so return */
	if (!$fmdb->num_rows) return false;

	return $fmdb->last_result;
}


?>