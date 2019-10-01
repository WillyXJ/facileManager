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
	return null;
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
	
	/** Prevent a double-load */
	if (!count($_GET)) return null;
	
	$apstats_result = null;
	
	/** Get AP Stats results */
	$query = "SELECT * FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}stats";
	if ($fmdb->query($query)) {
		$apstats_result = $fmdb->num_rows ? $fmdb->last_result : null;
	}
	
	/** Show WLAN Stats */
	basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_data', 'config_', 'AND config_name="ssid"');
	$result = ($fmdb->num_rows) ? $fmdb->last_result : null;
	$table_info = array(
					'class' => 'display_results',
					'name' => 'wlans'
				);

	$title_array = array(__('SSID'), __('Clients'), __('APs'), __('Usage'), __('Band'), __('Channel'));

	$return = displayTableHeader($table_info, $title_array);

	$ap_clients = array();
	if ($apstats_result) {
		foreach ($apstats_result as $server) {
			$ap_info = unserialize($server->stat_info);
			foreach((array) $ap_info['interfaces'] as $iface_info) {
				if (array_key_exists('ssid', $iface_info)) {
					if (!array_key_exists('clients', $iface_info)) {
						$iface_info['clients'] = array();
					}
					if (!isset($ap_clients[$iface_info['ssid']]['aps'])) {
						$ap_clients[$iface_info['ssid']]['aps'] = 0;
					}
					$ap_clients[$iface_info['ssid']]['aps']++;
					if (!isset($ap_clients[$iface_info['ssid']]['clients'])) {
						$ap_clients[$iface_info['ssid']]['clients'] = 0;
					}
					$ap_clients[$iface_info['ssid']]['clients'] += count($iface_info['clients']);
					$ap_clients[$iface_info['ssid']]['channel'] = $iface_info['channel'];
					$ap_clients[$iface_info['ssid']]['band'] = $iface_info['band'];

					if (!isset($ap_clients[$iface_info['ssid']]['usage'])) {
						$ap_clients[$iface_info['ssid']]['usage'] = 0;
					}
					foreach ($iface_info['clients'] as $mac => $client_info) {
						$ap_clients[$iface_info['ssid']]['usage'] += $client_info['rx bytes'];
						$ap_clients[$iface_info['ssid']]['usage'] += $client_info['tx bytes'];
					}
				}
			}
		}
	}
	
	if ($result) {
		foreach ($result as $ap_info) {
			$ap_count = (array_key_exists($ap_info->config_data, $ap_clients) && $ap_clients[$ap_info->config_data]['aps']) ? $ap_clients[$ap_info->config_data]['aps'] : 0;
			$client_count = (array_key_exists($ap_info->config_data, $ap_clients) && $ap_clients[$ap_info->config_data]['clients']) ? $ap_clients[$ap_info->config_data]['clients'] : 0;
			$usage = (array_key_exists($ap_info->config_data, $ap_clients)) ? formatSize($ap_clients[$ap_info->config_data]['usage']) : formatSize(0);
			$channel = (array_key_exists($ap_info->config_data, $ap_clients)) ? $ap_clients[$ap_info->config_data]['channel'] : __('Offline');
			$band = (array_key_exists($ap_info->config_data, $ap_clients)) ? $ap_clients[$ap_info->config_data]['band'] : __('Offline');
			$return .= <<<HTML
				<tr>
					<td>{$ap_info->config_data}</td>
					<td>$client_count</td>
					<td>$ap_count</td>
					<td>$usage</td>
					<td>$band</td>
					<td>$channel</td>
				</tr>
HTML;
		}
	}

	$return .= "</tbody>\n</table>\n</div>\n<div>\n";
	
	
	/** Show AP Stats */
	$return .= sprintf("<br /><br /><br /><h2>%s</h2>", __('Access Points'));
	basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_name', 'server_');
	$result = ($fmdb->num_rows) ? $fmdb->last_result : null;
	$table_info = array(
					'class' => 'display_results',
					'name' => 'servers'
				);

	$title_array = array(__('AP Name'), __('Status'), __('Uptime'), __('Clients'), __('IP Address'), __('Groups'));

	$return .= displayTableHeader($table_info, $title_array);

	if ($result) {
		global $fm_module_servers;
		if (!class_exists('fm_module_servers')) {
			include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_servers.php');
		}
		foreach ($result as $server_info) {
			$ap_clients = 0;
			$ap_status = str_replace(_('Failed'), __('AP is down'), $__FM_CONFIG['module']['icons']['fail']);
			$ap_groups = $fm_module_servers->getServerGroups($server_info->server_id, 'group_name');
			if (is_array($ap_groups)) {
				$ap_groups = join('; ', $ap_groups);
			} else {
				$ap_groups = sprintf('<i>%s</i>', _('None'));
			}
			$ap_uptime = secondsToTime(0);
			if ($apstats_result) {
				foreach ($apstats_result as $apstats) {
					if ($apstats->server_serial_no != $server_info->server_serial_no) continue;
					
					$ap_uptime = secondsToTime(0);
					$ap_stat = unserialize($apstats->stat_info);
					$active_time = strtotime('3 minutes ago');

					$reported_ap = false;
					if ($active_time <= $apstats->stat_last_report) {
						$reported_ap = $ap_stat['status'];
					}

					if (!$reported_ap && $ap_stat['status']) {
						unset($ap_stat['interfaces']);
						$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}stats` SET stat_info = NULL WHERE `account_id`='" . $_SESSION['user']['account_id'] . "' AND `server_serial_no`='$server_info->server_serial_no' LIMIT 1";
						$fmdb->query($query);
					}
					$uptime = 0;
					if ($reported_ap && $apstats->server_serial_no == $server_info->server_serial_no) {
						$uptime = $ap_stat['hostapd-uptime'];
					}
					$ap_uptime = secondsToTime($uptime);
					$ap_status = ($apstats->server_serial_no == $server_info->server_serial_no && $reported_ap) ? str_replace(_('OK'), __('AP is up'), $__FM_CONFIG['module']['icons']['ok']) : str_replace(_('Failed'), __('AP is down'), $__FM_CONFIG['module']['icons']['fail']);
					foreach((array) $ap_stat['interfaces'] as $iface_info) {
						if (array_key_exists('clients', $iface_info)) {
							foreach ((array) $iface_info['clients'] as $mac => $client_info) {
								$ap_client_stats[$mac] = $client_info;
								$ap_client_stats[$mac]['ssid'] = $iface_info['ssid'];
								$ap_client_stats[$mac]['ap'] = $apstats->server_serial_no;
								$ap_clients ++;
							}
						}
//					$ap_clients =+ count((array) $iface_info['clients']);
					}
					$ap_addresses = isset($ap_stat['interface-addresses']) ? join('<br />', $ap_stat['interface-addresses']) : null;
				}
			}
			$return .= <<<HTML
				<tr>
					<td>{$server_info->server_name}</td>
					<td>$ap_status</td>
					<td>$ap_uptime</td>
					<td>$ap_clients</td>
					<td>$ap_addresses</td>
					<td>$ap_groups</td>
				</tr>
HTML;
		}
	}

	$return .= "</tbody>\n</table>\n</div>\n<div>\n";
	
	
	
	
	/** Show Client Stats */
	$return .= sprintf("<br /><br /><br /><h2>%s</h2>\n", __('Client Connections'));
	$table_info = array(
					'class' => 'display_results',
					'id' => 'table_edits',
					'name' => 'wlan_users'
				);

	$title_array = array(__('User'), __('IP Address'), __('WLAN'), __('AP'), __('Connected'), __('Down'), __('Up'), __('Bitrate'), __('SNR (dB)'), __('Actions'));

	$return .= displayTableHeader($table_info, $title_array);

	if (isset($ap_client_stats)) {
		foreach ($ap_client_stats as $mac => $client_info) {
			$actions = sprintf('<a href="#" id="block-wifi-client" class="delete valid_error">%s %s</a>', $__FM_CONFIG['module']['icons']['block'], __('Block'));
			
			$user = getNameFromID($mac, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'wlan_users', 'wlan_user_', 'wlan_user_mac', 'wlan_user_login');
			if (!$user) {
				$user = $mac;
			} else {
				$user = parseMenuLinks(sprintf('<a href="__menu{Users}">%s</a>', $user));
				$actions = null;
			}
			$ap = getNameFromID($client_info['ap'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name');
			$signal = explode(' ', $client_info['signal']);
			$signal = $signal[0];
			$bitrate = sprintf('%s down<br />%s up', $client_info['tx bitrate'], $client_info['rx bitrate']);
			$rx_bytes = formatSize($client_info['tx bytes']);
			$tx_bytes = formatSize($client_info['rx bytes']);
			$connected_time = explode(' ', $client_info['connected time']);
			$connected_time = secondsToTime($connected_time[0]);
			
		$return .= <<<HTML
		<tr id="$mac" rel="{$client_info['ssid']}">
			<td>$user</td>
			<td>{$client_info['ip-address']}</td>
			<td>{$client_info['ssid']}</td>
			<td>$ap</td>
			<td>$connected_time</td>
			<td>$rx_bytes</td>
			<td>$tx_bytes</td>
			<td>$bitrate</td>
			<td>$signal</td>
			<td>$actions</td>
		</tr>
HTML;
		}
	}
	
	
	$return .= "</tbody>\n</table>\n</div>\n</div>\n";
	
	return $return;
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
		<a class="list_title">Configure Access Points</a>
		<div id="fmwifi_config_servers">
			<p>All aspects of Access points (AP) configuration takes place in the Config menu 
			item. From there you can add, edit {$__FM_CONFIG['icons']['edit']}, 
			delete {$__FM_CONFIG['icons']['delete']} servers and options depending on your user permissions.</p>
			
			Access Points can be defined at <a href="__menu{Access Points}">Access Points</a>. In the add/edit 
			window, select and define the server hostname, update method, configuration file, and the mode of the AP. 
			All of these options are automatically defined during the client installation.</p>
			<p>The server can be updated via the following methods:</p>
			<ul>
				<li><i>http(s) -</i> $fm_name will initiate a http(s) connection to the AP which updates the configs.</li>
				<li><i>cron -</i> The DNS servers will initiate a http connection to $fm_name to update the configs.</li>
				<li><i>ssh -</i> $fm_name will SSH to the AP which updates the configs.</li>
			</ul>
			<p>In order for the AP to be enabled, the client app needs to be installed on the AP.</p>
			<p><i>The 'Server Management' or 'Super Admin' permission is required to add, edit, and delete APs.</i></p>
			<p>Once an AP is added or modified, the configuration files for the AP will need to be built for the changes to take effect. 
			Before building the configuration {$__FM_CONFIG['icons']['build']} you can preview {$__FM_CONFIG['icons']['preview']} the configs to 
			ensure they are how you desire them.</p>
			<p>You can define AP Groups if two or more APs need to hold the same WLAN configuration.</p>
			<p><i>The 'Build Server Configs' or 'Super Admin' permission is required to build the AP configurations.</i></p>
		</div>
	</li>
	<li>
		<a class="list_title">WLAN Management</a>
		<div id="fmwifi_config_wlans">
			<p>All aspects of WLAN configuration takes place in the WLAN menu 
			item. From there you can add, edit {$__FM_CONFIG['icons']['edit']}, 
			delete {$__FM_CONFIG['icons']['delete']} WLANs and options depending on your user permissions.</p>
			
			<p><b>WLANs</b><br />
			WLANs can be defined at WLAN &rarr; <a href="__menu{Manage}">Manage</a>. In the add/edit WLAN 
			window, select and define the SSID, APs to serve the WLAN, mode, security, channel, and country options. If 
			WPA is enabled, fmWifi will only configure WPA2.</p>
			<p><i>The 'Manage WLANs' or 'Super Admin' permission is required to add, edit, and delete WLANs.</i></p>
			<br />
			
			<p><b>Options</b><br />
			Options can be defined globally or server-based which is controlled by the servers drop-down menu in the upper right. Currently, the options 
			configuration is rudimentary and can be defined at Config &rarr; <a href="__menu{Options}">Options</a>.</p>
			<p>Server-level options always supercede global options.</p>
			<p><i>The 'Server Management' or 'Super Admin' permission is required to manage WLAN options.</i></p>
			<br />
			
			<p><b>Users</b><br />
			If you want to use individual user accounts, they need to be defined at WLAN &rarr; <a href="__menu{Users}">Users</a>. Users allow 
			unique passphrases for each connecting MAC address (client) to the WLAN. These will override the WLAN WPA2 passphrase which may or may 
			not be included in the WLAN configuration which can be defined in the <a href="__menu{{$_SESSION['module']} Settings}">Settings</a>.</p>
			<p><i>The 'Manage WLAN Users' or 'Super Admin' permission is required to manage views.</i></p>
			<br />
			
			<p><b>ACLs</b><br />
			Access Control Lists are defined at WLAN &rarr; <a href="__menu{ACLs}">ACLs</a> to define which MAC addresses (clients) 
			are allowed or denied access to the WLAN.</p>
			<p>When defining an ACL, specify the WLANs it applies to, the client MAC address, and the action.</p>
			<p><i>The 'Manage WLAN Users' or 'Super Admin' permission is required to manage ACLs.</i></p>
			<br />
		</div>
	</li>
	<li>
		<a class="list_title">Module Settings</a>
		<div>
			<p>Settings for {$_SESSION['module']} can be updated from the <a href="__menu{{$_SESSION['module']} Settings}">Settings</a> menu item.</p>
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
		addSubmenuPage('config-wlans.php', __('Options'), __('Options'), array('manage_wlans', 'view_all'), $_SESSION['module'], 'config-options.php');
		addSubmenuPage('config-wlans.php', __('Users'), __('Users'), array('manage_wlan_users', 'view_all'), $_SESSION['module'], 'config-users.php');
		addSubmenuPage('config-wlans.php', __('ACLs'), __('ACLs'), array('manage_wlans', 'view_all'), $_SESSION['module'], 'config-acls.php');

		addSubmenuPage('index.php', __('WLANs'), __('WLANs'), array('manage_wlans', 'view_all'), $_SESSION['module'], 'index.php', null, 5);
//		addSubmenuPage('index.php', __('Access Points'), __('Access Points'), array('manage_wlans', 'view_all'), $_SESSION['module'], 'config-acls.php');

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


/**
 * Formats seconds to time
 *
 * @since 0.1
 * @package facileManager
 * @subpackage fmWifi
 *
 * @param integer $seconds Seconds to parse
 * @return string
 */
function secondsToTime($seconds) {
	if (!$seconds) {
		return '0s';
	}
	
	$dtF = new \DateTime('@0');
	$dtT = new \DateTime("@$seconds");
	return $dtF->diff($dtT)->format('%ad %hh %im %ss');
}


/**
 * Builds the wlan listing in a dropdown menu
 *
 * @since 0.2
 * @package facileManager
 * @subpackage fmWifi
 *
 * @param integer $item_id WLAN ID to select
 * $param string $class Class name to apply to the div
 * @return string
 */
function buildWLANSubMenu($item_id = 0, $server_serial_no = 0, $class = null) {
	$list = buildSelect('item_id', 'item_id', availableWLANs(), $item_id, 1, null, false, 'this.form.submit()');
	
	$hidden_inputs = null;
	foreach ($GLOBALS['URI'] as $param => $value) {
		if ($param == 'item_id') continue;
		$hidden_inputs .= '<input type="hidden" name="' . $param . '" value="' . $value . '" />' . "\n";
	}
	
	$class = $class ? 'class="' . $class . '"' : null;

	$return = <<<HTML
	<div id="configtypesmenu" $class>
		<form action="{$GLOBALS['basename']}" method="GET">
		$hidden_inputs
		$list
		</form>
	</div>
HTML;

	return $return;
}


/**
 * Returns an array of wlans
 *
 * @since 0.2
 * @package facileManager
 * @subpackage fmWifi
 *
 * @return array
 */
function availableWLANs() {
	global $fmdb, $__FM_CONFIG;
	
	$array[0][] = __('All WLANs');
	$array[0][] = '0';
	
	$j = 0;
	/** WLANs */
	$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_name', 'config_', 'AND config_name="ssid"');
	if ($fmdb->num_rows) {
		$results = $fmdb->last_result;
		for ($i=0; $i<$fmdb->num_rows; $i++) {
			$array[$j+1][] = $results[$i]->config_data;
			$array[$j+1][] = $results[$i]->config_id;
			$j++;
		}
	}
	
	return $array;
}


?>