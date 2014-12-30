<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2013 The facileManager Team                               |
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

/**
 * Checks the app functionality
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmFirewall
 */
function moduleFunctionalCheck() {
	global $fmdb, $__FM_CONFIG;
	$html_checks = null;
	$checks = array();
	
	/** Count active database servers */
//	$checks[] = (basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_', 'active')) ? null : '<p>You currently have no active database servers defined.  <a href="' . getMenuURL('Servers') . '">Click here</a> to define one or more to manage.</p>';
	
	/** Count groups */
//	$checks[] = (basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'groups', 'group_id', 'group_')) ? null : '<p>You currently have no database server groups defined.  <a href="' . getMenuURL('Server Groups'). '">Click here</a> to define one or more.</p>';
	
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
 * @subpackage fmFirewall
 */
function buildModuleDashboard() {
	global $fmdb, $__FM_CONFIG;
	
	return sprintf('<p>%s has no dashboard content yet.</p>', $_SESSION['module']);

}


/**
 * Builds the additional module menu for display
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmFirewall
 */
function buildModuleToolbar() {
	global $__FM_CONFIG;
	
	if (isset($_GET['server_serial_no'])) {
		$server_name = getNameFromID($_GET['server_serial_no'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name');
		$domain_menu = sprintf('<div id="topheadpart">
			<span class="single_line">%s:&nbsp;&nbsp; %s</span>
		</div>', _('Firewall'), $server_name);
	} else $domain_menu = null;
	
	return array($domain_menu, null);
}


/**
 * Builds the help for display
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmFirewall
 */
function buildModuleHelpFile() {
	global $__FM_CONFIG;
	
	$body = <<<HTML
<h3>{$_SESSION['module']}</h3>
<ul>
	<li>
		<a class="list_title">Configure Firewalls</a>
		<div id="fmfw_config_servers">
			<p>Firewall servers can be managed from the <a href="__menu{Firewalls}">Firewalls</a> menu item. From 
			there you can add ({$__FM_CONFIG['icons']['add']}), edit ({$__FM_CONFIG['icons']['edit']}), and delete ({$__FM_CONFIG['icons']['delete']}) 
			firewalls depending on your user permissions.</p>
			<p><i>The 'Server Management' or 'Super Admin' permission is required to add, edit, and delete servers.</i></p>
			<p>Select the firewall type from the list, select the method the firewall will be updated, and define the firewall configuration file. All of 
			these options are automatically defined during the client installation.</p>
			<br />
		</div>
	</li>
	<li>
		<a class="list_title">Firewall Policies</a>
		<div id="fmfw_config_policies">
			<p>Policy Rules are managed by clicking on the firewall server name from the <a href="__menu{Firewalls}">Firewalls</a> 
			menu item. From there, you can add ({$__FM_CONFIG['icons']['add']}), edit ({$__FM_CONFIG['icons']['edit']}), delete 
			({$__FM_CONFIG['icons']['delete']}), and reorder rules (drag and drop the row). When adding or editing a rule, you can select the 
			firewall interface the rule applies to, the direction, source, destination, services, time restriction (iptables only), action, and
			any options you want for the rule.</p>
			<p><i>The 'Server Management' or 'Super Admin' permission is required to add, edit, and delete firewall policies.</i></p>
			<p>When the rules are defined and ready for deployment to the firewall server, you can preview ({$__FM_CONFIG['icons']['preview']}) the config
			before building ({$__FM_CONFIG['icons']['build']}) it from the <a href="__menu{Firewalls}">Firewalls</a> 
			menu item.</p>
			<p><i>The 'Build Server Configs' or 'Super Admin' permission is required to build and deploy firewall policies.</i></p>
			<br />
		</div>
	</li>
	<li>
		<a class="list_title">Objects</a>
		<div id="fmfw_objects">
			<p>Much like an appliance firewall, objects need to be defined before they can be used in policies. All objects 
			(<a href="__menu{Hosts}">Hosts</a> and <a href="__menu{Networks}">Networks</a>)
			are managed from the <a href="__menu{Objects}">Objects</a> menu item. Give the object a name and
			specify the host or network address.</p>
			<p><a href="__menu{Objects}">Object Groups</a> allow you to group object types together for easy policy 
			creation. For example, you might want all of your web servers to be grouped together for a web server policy rule.</a>
			<p><i>The 'Object Management' or 'Super Admin' permission is required to add, edit, and delete services and service groups.</i></p>
			<br />
		</div>
	</li>
	<li>
		<a class="list_title">Services</a>
		<div id="fmfw_services">
			<p>Much like an appliance firewall, services need to be defined before they can be used in policies. All services 
			(<a href="__menu{ICMP}">ICMP</a>, <a href="__menu{TCP}">TCP</a>,
			<a href="__menu{UDP}">UDP</a>) are managed from the 
			<a href="__menu{Services}">Services</a> menu item. Give the service a name, specify the ports (or 
			types/codes for ICMP) and any TCP flags.</p>
			<p><a href="__menu{Services}">Service Groups</a> allow you to group services together for easy policy 
			creation. For example, you might want http and https to be grouped together for a web server.</a>
			<p><i>The 'Service Management' or 'Super Admin' permission is required to add, edit, and delete services and service groups.</i></p>
			<br />
		</div>
	</li>
	<li>
		<a class="list_title">Time Restrictions</a>
		<div id="fmfw_time">
			<p>Time restrictions can be defined from the <a href="__menu{Time}">Time</a> menu item. From there you can 
			specify the start date and time, end date and time, and the weekdays of the restriction. Only iptables firewall type supports the use of time
			restrictions in its policies.</p>
			<p><i>The 'Time Management' or 'Super Admin' permission is required to add, edit, and delete time restrictions.</i></p>
			<br />
		</div>
	</li>
	
HTML;
	
	return $body;
}


function moduleAddServer($action) {
	include(ABSPATH . 'fm-modules/' . $_POST['module_name'] . '/classes/class_servers.php');
	
	return $fm_module_servers->$action($_POST);
}


function availableGroupItems($group_type, $list_type, $select_ids = null, $edit_group_id = null) {
	global $fmdb, $__FM_CONFIG;
	
	$array = null;
	$name = $group_type . '_name';
	$id = $group_type . '_id';
	
	$service_ids = $group_ids = $edit_group_id_sql = null;
	
	if (count($select_ids)) {
		foreach ($select_ids as $temp_id) {
			if (substr($temp_id, 0, 1) == 'g') {
				$group_ids[] = substr($temp_id, 1);
			} else {
				$service_ids[] = substr($temp_id, 1);
			}
		}
	}
	
	/** Groups */
	if ($list_type == 'available') {
		$edit_group_id_sql = (isset($edit_group_id)) ? "AND group_id!=$edit_group_id" : null;
		$select_ids_sql = (is_array($group_ids) && count($group_ids)) ? "AND group_id NOT IN (" . implode(',', $group_ids) . ")" : null;
	} else {
		$select_ids_sql = (is_array($group_ids) && count($group_ids)) ? "AND group_id IN (" . implode(',', $group_ids) . ")" : "AND group_id=0";
	}
		
	basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'groups', 'group_name', 'group_', "AND group_type='$group_type'" . $select_ids_sql . ' ' . $edit_group_id_sql);
	$results = $fmdb->last_result;
	$count = $fmdb->num_rows;
	for ($i=0; $i<$fmdb->num_rows; $i++) {
		$array[$i][] = $results[$i]->group_name;
		$array[$i][] = 'g' . $results[$i]->group_id;
	}
	
	/** Services */
	if ($list_type == 'available') {
		$select_ids_sql = (is_array($service_ids) && count($service_ids)) ? "AND {$group_type}_id NOT IN (" . implode(',', $service_ids) . ")" : null;
	} else {
		$select_ids_sql = (is_array($service_ids) && count($service_ids)) ? "AND {$group_type}_id IN (" . implode(',', $service_ids) . ")" : "AND {$group_type}_id=0";
	}
		
	basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . $group_type . 's', $group_type . '_name', $group_type . '_', $select_ids_sql);
	$results = $fmdb->last_result;
	$j = $count;
	for ($i=0; $i<$fmdb->num_rows; $i++) {
		$array[$j][] = ($group_type == 'service') ? $results[$i]->$name . ' (' . $results[$i]->service_type . ')' : $results[$i]->$name;
		$array[$j][] = substr($group_type, 0, 1) . $results[$i]->$id;
		$j++;
	}
	
	return $array;
}


function getGroupItems($group_items) {
	$group_items_assigned = null;
	
	if ($group_items) {
		$group_items_assigned = explode(';', trim($group_items, ';'));
//			foreach ($item_array as $item) {
//				$group_items_assigned[] = substr($item, 1);
//				$group_items_assigned[] = $item;
//			}
	}
	
	return $group_items_assigned;
}


function isItemInPolicy($id, $type) {
	global $fmdb, $__FM_CONFIG;
	
	if ($type == 'time') {
		$query = "SELECT policy_id FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}policies WHERE account_id='{$_SESSION['user']['account_id']}' 
				AND policy_status!='deleted' AND policy_time={$id}";
	} else {
		$item_id = substr($type, 0, 1) . $id;
		
		$query = "SELECT policy_id FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}policies WHERE account_id='{$_SESSION['user']['account_id']}' AND policy_status!='deleted' AND 
				(policy_source='{$item_id}' OR policy_source LIKE '{$item_id};%' OR policy_source LIKE '%;{$item_id};%' OR policy_source LIKE '%;{$item_id}') OR
				(policy_destination='{$item_id}' OR policy_destination LIKE '{$item_id};%' OR policy_destination LIKE '%;{$item_id};%' OR policy_destination LIKE '%;{$item_id}') OR
				(policy_services='{$item_id}' OR policy_services LIKE '{$item_id};%' OR policy_services LIKE '%;{$item_id};%' OR policy_services LIKE '%;{$item_id}')
				ORDER BY policy_id ASC";
	}
	
	$fmdb->get_results($query);
	if ($fmdb->num_rows) {
		return true;
	}
	
	return false;
}


/**
 * Gets the menu badge counts
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmFirewall
 *
 * @return boolean
 */
function getModuleBadgeCounts($type) {
	global $fmdb, $__FM_CONFIG;
	
	if ($type == 'servers') {
		$badge_counts = null;
		$server_builds = array();
		
		/** Servers */
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_', "AND `server_installed`!='yes' OR (`server_status`='active' AND `server_build_config`='yes')");
		$server_count = $fmdb->num_rows;
		$server_results = $fmdb->last_result;
		for ($i=0; $i<$server_count; $i++) {
			$server_builds[] = $server_results[$i]->server_name;
		}
		if (version_compare(getOption('version', 0, $_SESSION['module']), '1.0-b3', '>=')) {
			basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_', "AND `server_client_version`!='" . getOption($_SESSION['module'] . '_client_version') . "'");
			$server_count = $fmdb->num_rows;
			$server_results = $fmdb->last_result;
			for ($i=0; $i<$server_count; $i++) {
				$server_builds[] = $server_results[$i]->server_name;
			}
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
 * @subpackage fmFirewall
 */
function buildModuleMenu() {
	addObjectPage(_('Firewalls'), _('Firewall Servers'), array('manage_servers', 'build_server_configs', 'manage_policies', 'view_all'), $_SESSION['module'], 'config-servers.php', null, true);
		addSubmenuPage('config-servers.php', null, _('Firewall Policy'), null, $_SESSION['module'], 'config-policy.php', null, null, getModuleBadgeCounts('servers'));

	addObjectPage(_('Objects'), _('Object Groups'), array('manage_objects', 'view_all'), $_SESSION['module'], 'object-groups.php');
		addSubmenuPage('object-groups.php', _('Groups'), _('Object Groups'), array('manage_objects', 'view_all'), $_SESSION['module'], 'object-groups.php');
		addSubmenuPage('object-groups.php', _('Hosts'), _('Host Objects'), array('manage_objects', 'view_all'), $_SESSION['module'], 'objects-host.php');
		addSubmenuPage('object-groups.php', _('Networks'), _('Network Objects'), array('manage_objects', 'view_all'), $_SESSION['module'], 'objects-network.php');

	addObjectPage(_('Services'), _('Service Groups'), array('manage_services', 'view_all'), $_SESSION['module'], 'service-groups.php');
		addSubmenuPage('service-groups.php', _('Groups'), _('Service Groups'), array('manage_services', 'view_all'), $_SESSION['module'], 'service-groups.php');
		addSubmenuPage('service-groups.php', _('ICMP'), _('ICMP Services'), array('manage_services', 'view_all'), $_SESSION['module'], 'services-icmp.php');
		addSubmenuPage('service-groups.php', _('TCP'), _('TCP Services'), array('manage_services', 'view_all'), $_SESSION['module'], 'services-tcp.php');
		addSubmenuPage('service-groups.php', _('UDP'), _('UDP Services'), array('manage_services', 'view_all'), $_SESSION['module'], 'services-udp.php');

	addObjectPage(_('Time'), _('Time Restrictions'), array('manage_time', 'view_all'), $_SESSION['module'], 'config-time.php');
}


?>