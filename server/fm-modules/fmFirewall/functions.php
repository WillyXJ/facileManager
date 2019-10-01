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
	
	return sprintf('<p>%s</p>', sprintf(__('%s has no dashboard content yet.'), $_SESSION['module']));

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
	
	if (isset($_GET['server_serial_no']) && $_GET['server_serial_no']) {
		$server_name = ($_GET['server_serial_no'][0] == 't') ? getNameFromID(preg_replace('/\D/', null, $_GET['server_serial_no']), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_', 'policy_id', 'policy_name') : getNameFromID($_GET['server_serial_no'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name');
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
			there you can add, edit {$__FM_CONFIG['icons']['edit']}, and delete {$__FM_CONFIG['icons']['delete']} 
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
			menu item. From there, you can add, edit {$__FM_CONFIG['icons']['edit']}, delete 
			{$__FM_CONFIG['icons']['delete']}, and reorder rules (drag and drop the row). When adding or editing a rule, you can select the 
			firewall interface the rule applies to, the direction, source, destination, services, time restriction (iptables only), action, and
			any options you want for the rule.</p>
			<p>Policy Templates contain policy rules that can be applied to multiple firewalls (targets). When viewing policies, any rules from a template
			will be highlighted and not editable.</p>
			<p><i>The 'Server Management' or 'Super Admin' permission is required to add, edit, and delete firewall templates and policies.</i></p>
			<p>When the rules are defined and ready for deployment to the firewall server, you can preview {$__FM_CONFIG['icons']['preview']} the config
			before building {$__FM_CONFIG['icons']['build']} it from the <a href="__menu{Firewalls}">Firewalls</a> 
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
	
	$badge_counts = null;
	
	if ($type == 'servers') {
		$server_builds = array();
		
		/** Servers */
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_', "AND `server_installed`!='yes' OR (`server_status`='active' AND `server_build_config`='yes')");
		if ($fmdb->num_rows) {
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$server_builds[] = $fmdb->last_result[$i]->server_name;
			}
		}
		if (version_compare(getOption('version', 0, $_SESSION['module']), '1.0-b3', '>=')) {
			basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_', "AND `server_client_version`!='" . getOption('client_version', 0, $_SESSION['module']) . "'");
			if ($fmdb->num_rows) {
				for ($i=0; $i<$fmdb->num_rows; $i++) {
					$server_builds[] = $fmdb->last_result[$i]->server_name;
				}
			}
		}
		
		$servers = array_unique($server_builds);
		$badge_counts = count($servers);
		
		unset($server_builds, $servers);
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
	addObjectPage(__('Firewalls'), __('Firewall Servers'), array('manage_servers', 'build_server_configs', 'manage_policies', 'view_all'), $_SESSION['module'], 'config-servers.php', null, true);
		addSubmenuPage('config-servers.php', null, __('Firewall Policy'), null, $_SESSION['module'], 'config-policy.php', null, null, getModuleBadgeCounts('servers'));

	addObjectPage(__('Policies'), __('Firewall Policy'), array('manage_policies', 'view_all'), $_SESSION['module'], 'config-policy.php');
		addSubmenuPage('config-policy.php', __('Policies'), __('Firewall Policy'), array('manage_policies', 'view_all'), $_SESSION['module'], 'config-policy.php');
		addSubmenuPage('config-policy.php', __('Templates'), __('Policy Templates'), array('manage_policies', 'view_all'), $_SESSION['module'], 'templates-policy.php');

	addObjectPage(__('Objects'), __('Object Groups'), array('manage_objects', 'view_all'), $_SESSION['module'], 'object-groups.php');
		addSubmenuPage('object-groups.php', __('Groups'), __('Object Groups'), array('manage_objects', 'view_all'), $_SESSION['module'], 'object-groups.php');
		addSubmenuPage('object-groups.php', __('Hosts'), __('Host Objects'), array('manage_objects', 'view_all'), $_SESSION['module'], 'objects-host.php');
		addSubmenuPage('object-groups.php', __('Networks'), __('Network Objects'), array('manage_objects', 'view_all'), $_SESSION['module'], 'objects-network.php');

	addObjectPage(__('Services'), __('Service Groups'), array('manage_services', 'view_all'), $_SESSION['module'], 'service-groups.php');
		addSubmenuPage('service-groups.php', __('Groups'), __('Service Groups'), array('manage_services', 'view_all'), $_SESSION['module'], 'service-groups.php');
		addSubmenuPage('service-groups.php', __('ICMP'), __('ICMP Services'), array('manage_services', 'view_all'), $_SESSION['module'], 'services-icmp.php');
		addSubmenuPage('service-groups.php', __('TCP'), __('TCP Services'), array('manage_services', 'view_all'), $_SESSION['module'], 'services-tcp.php');
		addSubmenuPage('service-groups.php', __('UDP'), __('UDP Services'), array('manage_services', 'view_all'), $_SESSION['module'], 'services-udp.php');

	addObjectPage(__('Time'), __('Time Restrictions'), array('manage_time', 'view_all'), $_SESSION['module'], 'config-time.php');
}


/**
 * Gets policy template IDs
 *
 * @since 2.0
 * @package facileManager
 * @subpackage fmFirewall
 * 
 * @param integer $id Server or template ID
 * @param integer $server_serial_no
 * 
 * @return array
 */
function getTemplateIDs($id, $server_serial_no = 0) {
	global $__FM_CONFIG, $fmdb;
	
	$template_ids = array();
	
	if ($server_serial_no) {
		$template_id_count = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_order_id', 'policy_', "AND (policy_targets='0' OR policy_targets='s_$id' OR policy_targets LIKE 's_$id;%' OR policy_targets LIKE '%;s_$id' OR policy_targets LIKE '%;s_$id;%') AND policy_type='template' AND policy_status='active'");
	} else {
		/** Template stack? */
		$template_id_count = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_order_id', 'policy_', "AND policy_id='$id' AND (policy_template_stack!='' OR policy_template_stack!=NULL) AND policy_type='template'");
	}
	
	if ($template_id_count) {
		$template_id_results = $fmdb->last_result;
		foreach ($template_id_results as $row) {
			if ($row->policy_template_stack) {
				foreach (explode(';', $row->policy_template_stack) as $stack_tpl_id) {
					$template_ids[] = $stack_tpl_id;
				}
			}
			$template_ids[] = $row->policy_id;
		}
	}
	
	return $template_ids;
}


/**
 * Gets template policies
 *
 * @since 2.0
 * @package facileManager
 * @subpackage fmFirewall
 * 
 * @param array $template_ids Template IDs
 * @param integer $server_id
 * @param integer $template_id
 * @param string $type Type of policy item to retrieve
 * @param string $status Status of policy
 * 
 * @return array
 */
function getTemplatePolicies($template_ids, $server_id = 0, $template_id = 0, $type = 'filter') {
	global $__FM_CONFIG, $fmdb;
	
	$template_id_count = 0;
	$template_results = array();
	

	foreach ($template_ids as $tpl_id) {
		$target_sql = ($server_id) ? "AND (policy_targets='' OR policy_targets='0' OR policy_targets='s_$server_id' OR policy_targets LIKE 's_$server_id;%' OR policy_targets LIKE '%;s_$server_id' OR policy_targets LIKE '%;s_$server_id;%')" : null;
		$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_order_id', 'policy_', "AND policy_template_id=$tpl_id AND policy_template_id!=$template_id AND policy_type='$type' $target_sql");
		if ($result) {
			$template_id_count += $fmdb->num_rows;
			foreach ($fmdb->last_result as $key => $object) {
				$fmdb->last_result[$key]->policy_from_template = true;
			}
			$template_results = array_merge((array) $template_results, $fmdb->last_result);
		}
	}
	
	return array($template_results, $template_id_count);
}


?>