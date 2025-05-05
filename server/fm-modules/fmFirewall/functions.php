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
	return null;
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
		$server_name = ($_GET['server_serial_no'][0] == 't') ? getNameFromID(preg_replace('/\D/', '', $_GET['server_serial_no']), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_', 'policy_id', 'policy_name') : getNameFromID($_GET['server_serial_no'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name');
		$domain_menu = sprintf('<div id="topheadpart">
			<span>%s:&nbsp;&nbsp; %s</span>
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
			(Hosts and Networks)
			are managed from the <a href="__menu{Addresses}">Addresses</a> menu item. Give the object a name and
			specify the host or network address.</p>
			<p><a href="__menu{Address Groups}">Address Groups</a> allow you to group object types together for easy policy 
			creation. For example, you might want all of your web servers to be grouped together for a web server policy rule.</a>
			<p><i>The 'Object Management' or 'Super Admin' permission is required to add, edit, and delete services and service groups.</i></p>
			<br />
		</div>
	</li>
	<li>
		<a class="list_title">Services</a>
		<div id="fmfw_services">
			<p>Much like an appliance firewall, services need to be defined before they can be used in policies. All services 
			(ICMP, TCP, UDP) are managed from the 
			<a href="__menu{Services}">Services</a> menu item. Give the service a name, specify the ports (or 
			types/codes for ICMP) and any TCP flags.</p>
			<p><a href="__menu{Service Groups}">Service Groups</a> allow you to group services together for easy policy 
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
	
	$array = array();
	$name = $group_type . '_name';
	$id = $group_type . '_id';
	
	$service_ids = $group_ids = array();
	$edit_group_id_sql = null;
	
	if (is_array($select_ids) && count($select_ids)) {
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
	$list_groups_count = $list_services_count = 0;
	for ($i=0; $i<$fmdb->num_rows; $i++) {
		if ($fmdb->last_result[$i]->group_type == 'object') {
			$list_group_name = __('Groups');
			$list_count = $list_groups_count;
			$list_groups_count++;
		} else {
			$list_group_name = __('Services');
			$list_count = $list_services_count;
			$list_services_count++;
		}
		$array[$list_group_name][$list_count][] = $fmdb->last_result[$i]->group_name;
		$array[$list_group_name][$list_count][] = 'g' . $fmdb->last_result[$i]->group_id;
	}
	
	/** Objects/Services */
	if ($list_type == 'available') {
		$select_ids_sql = (is_array($service_ids) && count($service_ids)) ? "AND {$group_type}_id NOT IN (" . implode(',', $service_ids) . ")" : null;
	} else {
		$select_ids_sql = (is_array($service_ids) && count($service_ids)) ? "AND {$group_type}_id IN (" . implode(',', $service_ids) . ")" : null;
	}
		
	basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . $group_type . 's', $group_type . '_name', $group_type . '_', $select_ids_sql);
	$list_network_count = $list_host_count = 0;
	if ($group_type == 'service') {
		$j = count($array[__('Services')][$list_count]);
		$list_group_name = __('Services');
	} else {
		$j = 0;
	}
	for ($i=0; $i<$fmdb->num_rows; $i++) {
		if ($group_type == 'object') {
			if ($fmdb->last_result[$i]->object_type == 'network') {
				$list_group_name = __('Networks');
				$j = $list_network_count;
				$list_network_count++;
			} else {
				$list_group_name = __('Hosts');
				$j = $list_host_count;
				$list_host_count++;
			}
		}
		$array[$list_group_name][$j][] = ($group_type == 'service') ? $fmdb->last_result[$i]->$name . ' (' . $fmdb->last_result[$i]->service_type . ')' : $fmdb->last_result[$i]->$name;
		$array[$list_group_name][$j][] = substr($group_type, 0, 1) . $fmdb->last_result[$i]->$id;
		$j++;
	}
	
	return $array;
}


function getGroupItems($group_items) {
	$group_items_assigned = null;
	
	if ($group_items) {
		$delimiter = getDelimiter($group_items);
		$group_items_assigned = explode($delimiter, trim($group_items, $delimiter));
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
	addObjectPage(array(__('Firewalls'), 'fire'), __('Firewall Servers'), array('manage_servers', 'build_server_configs', 'manage_policies', 'view_all'), $_SESSION['module'], 'config-servers.php', null, true, getModuleBadgeCounts('servers'));

	addObjectPage(array(__('Policies'), 'file-text'), __('Firewall Policy'), array('manage_policies', 'view_all'), $_SESSION['module'], 'config-policy.php');
		addSubmenuPage('config-policy.php', __('Policies'), __('Firewall Policy'), array('manage_policies', 'view_all'), $_SESSION['module'], 'config-policy.php');
		addSubmenuPage('config-policy.php', __('Templates'), __('Policy Templates'), array('manage_policies', 'view_all'), $_SESSION['module'], 'templates-policy.php');

	addObjectPage(array(__('Objects'), 'cubes'), __('Address Groups'), array('manage_objects', 'view_all'), $_SESSION['module'], 'object-groups.php');
		addSubmenuPage('object-groups.php', __('Address Groups'), __('Address Groups'), array('manage_objects', 'view_all'), $_SESSION['module'], 'object-groups.php');
		addSubmenuPage('object-groups.php', __('Addresses'), __('Addresses'), array('manage_objects', 'view_all'), $_SESSION['module'], 'objects.php');
		addSubmenuPage('object-groups.php', __('Service Groups'), __('Service Groups'), array('manage_services', 'view_all'), $_SESSION['module'], 'service-groups.php');
		addSubmenuPage('object-groups.php', __('Services'), __('Services'), array('manage_services', 'view_all'), $_SESSION['module'], 'services.php');

	addObjectPage(array(__('Time'), 'clock-o'), __('Time Restrictions'), array('manage_time', 'view_all'), $_SESSION['module'], 'config-time.php');
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


/**
 * Marks global search matches
 *
 * @since 3.0
 * @package facileManager
 * @subpackage fmFirewall
 * 
 * @param array $matches Search matches to mark
 * 
 * @return string
 */
function markGlobalSearchMatch($matches) {
	return sprintf('<mark>%s</mark>', $matches[0]);
}


/**
 * Gets global search results
 *
 * @since 3.0
 * @package facileManager
 * @subpackage fmFirewall
 * 
 * @param string $item_id ID of item to search for
 * 
 * @return string
 */
function getGlobalSearchResults($item_id) {
	global $__FM_CONFIG, $fmdb;
	$results = array(
		__('Policies') => array(),
		__('Objects') => array()
	);

	/** Get item name */
	if ($item_id[0] == 's') {
		$tmp_name = getNameFromID(substr($item_id, 1), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', 'service_', 'service_id', 'service_name');
	} elseif ($item_id[0] == 'o') {
		$tmp_name = getNameFromID(substr($item_id, 1), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', 'object_', 'object_id', 'object_name');
	} elseif ($item_id[0] == 'g') {
		$tmp_name = getNameFromID(substr($item_id, 1), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'groups', 'group_', 'group_id', 'group_name');
	} elseif ($item_id[0] == 't') {
		$tmp_name = getNameFromID(substr($item_id, 1), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'time', 'time_', 'time_id', 'time_name');
	} else {
		$tmp_name = $item_id;
	}

	/** Get nested parents */
	$nested_parents = getNestedSearchResults($item_id, $results);
	if (count($nested_parents[__('Objects')])) {
		$results[__('Objects')] = array_merge($results[__('Objects')], $nested_parents[__('Objects')]);
	}
	if (count($nested_parents[__('Policies')])) {
		$results[__('Policies')] = array_merge($results[__('Policies')], $nested_parents[__('Policies')]);
	}

	/** Unset any empty categories */
	foreach ($results as $category => $records) {
		if (!count($records)) unset($results[$category]);
	}

	/** Build the search result tabs */
	$x = 1;
	foreach ($results as $category => $records) {
		$checked = ($x == 1) ? 'checked' : null;
		$tab[] = sprintf('
		<div id="tab">
			<input type="radio" name="tab-group-1" id="tab-%1$d" %2$s />
			<label for="tab-%1$d">%3$s <span class="menu-badge"><p>%4$s</p></span></label>
			<div id="tab-content">
				%5$s
			</div>
		</div>
		', $x, $checked, $category, count($records), displayGlobalSearchResults($category, $records, $tmp_name));
		$x++;
	}

	$popup_header = buildPopup('header', __('Global Search') . ': ' . $tmp_name);
	$popup_footer = buildPopup('footer', _('OK'), array('cancel_button' => 'cancel'));
	
	$return_form = sprintf('
	%s
	%s
%s',
			$popup_header,
			(isset($tab)) ? sprintf('<div id="tabs" class="global-search-results">%s</div>', implode("\n", $tab)) : sprintf('<p>%s</p>', __('This item is not used anywhere.')),
			$popup_footer
		);

	return $return_form;

}


/**
 * Displays global search results
 *
 * @since 3.0
 * @package facileManager
 * @subpackage fmFirewall
 * 
 * @param string $category Search result category
 * @param array $records Search results for the category
 * @param string $search_term Search term queried
 * 
 * @return string
 */
function displayGlobalSearchResults($category, $records, $search_term = null) {
	global $__FM_CONFIG;

	$return = '';

	$table_info = array(
		'class' => 'display_results global-search-results',
		'id' => 'table_edits'
	);
	if ($category == 'Policies') {
		$title_array = array(__('Policy'));
	} else {
		$title_array = array(__('Name'));
	}
	if ($category != 'Time') {
		$title_array[] = __('Type');
	}
	if ($category == 'Policies') {
		$title_array[] = __('Rule Name');
		$title_array[] = __('Rule Number');
	}
	$title_array = array_merge((array) $title_array, array(array('title' => _('Comment'), 'style' => 'width: 40%;')));
	if ($category != 'Policies') $title_array[] = array('class' => 'header-tiny');

	$return .= displayTableHeader($table_info, $title_array);

	foreach ($records as $row) {
		$rule_name = $rule_number = $name = $type = $comment = $global_search = null;
		if (property_exists($row, 'group_name')) {
			$name = parseMenuLinks(sprintf('<a href="__menu{%1$s}?q=\'%2$s\'">%2$s</a>', ($row->group_type == 'service') ? __('Service Groups') : __('Address Groups'), $row->group_name));
			$type = ($row->group_type == 'service') ? __('Service Group') : __('Address Group');
			$comment = $row->group_comment;
			$global_search = sprintf('<td><span rel="g%s">%s</span></td>', $row->group_id, $__FM_CONFIG['module']['icons']['search']);
		} elseif (property_exists($row, 'policy_name')) {
			$name = getGlobalSearchPolicyName($row, $search_term);
			$type = ($row->policy_type == 'filter') ? __('Filter') : __('NAT');
			$comment = $row->policy_comment;
			$rule_name = sprintf('<td>%s</td>', $row->policy_name);
			$rule_number = sprintf('<td>%s</td>', $row->policy_order_id);
		}
		$return .= sprintf('<tr>
			<td>%s</td>
			<td>%s</td>
			%s
			%s
			<td>%s</td>
			%s
		</tr>
		',
		$name, $type, $rule_name, $rule_number, $comment, $global_search);
	}

	$return .= "</tbody>\n</table>";

	return $return;
}


/**
 * Gets the policy name for a global search result
 *
 * @since 3.0
 * @package facileManager
 * @subpackage fmFirewall
 * 
 * @param object $rule Rule data from search result
 * @param string $search_term Search term queried
 * 
 * @return string
 */
function getGlobalSearchPolicyName($rule, $search_term = null) {
	global $__FM_CONFIG;

	$policy_name = ($rule->server_serial_no)
		? getNameFromID($rule->server_serial_no, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name')
		: getNameFromID($rule->policy_template_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_', 'policy_id', 'policy_name');

	return parseMenuLinks(sprintf('<a href="__menu{%s}?server_serial_no=%s&type=%s%s">%s</a>',
		__('Policies'),
		($rule->policy_template_id) ? "t_{$rule->policy_template_id}" : $rule->server_serial_no,
		$rule->policy_type,
		($search_term) ? "&q='$search_term'" : null,
		$policy_name
	));
}
	

/**
 * Gets the nested results from an ID
 *
 * @since 3.0
 * @package facileManager
 * @subpackage fmFirewall
 * 
 * @param string $item_id Item ID to query
 * @param string $results Empty multidimensional array to append to
 * 
 * @return array
 */
function getNestedSearchResults($item_id, $results) {
	global $__FM_CONFIG, $fmdb;

	$tmp_results = $results;

	$search_query = "(__FIELD__='$item_id' OR __FIELD__ LIKE '$item_id;%' OR __FIELD__ LIKE '%;$item_id;%' OR __FIELD__ LIKE '%;$item_id')";

	/** Get group results */
	foreach (array('service', 'object') as $group_type) {
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'groups', 'group_name', 'group_', "AND group_type='$group_type' AND " . str_replace('__FIELD__', 'group_items', $search_query));
		if ($fmdb->num_rows) {
			foreach ($fmdb->last_result as $row) {
				$results[__('Objects')][] = $row;
				$nested_results = getNestedSearchResults('g' . $row->group_id, $tmp_results);
				if (count($nested_results[__('Objects')])) {
					$results[__('Objects')] = array_merge($results[__('Objects')], $nested_results[__('Objects')]);
				}
				if (count($nested_results[__('Policies')])) {
					$results[__('Policies')] = array_merge($results[__('Policies')], $nested_results[__('Policies')]);
				}
			}
		}
	}

	/** Get policy results */
	$policy_search_fields = array('policy_source', 'policy_source_translated', 'policy_destination', 'policy_destination_translated', 'policy_services', 'policy_services_translated', 'policy_time');
	$tmp_search_sql = '';
	foreach ($policy_search_fields as $field) {
		$tmp_search_sql .= ' OR ' . str_replace('__FIELD__', $field, $search_query);
	}
	$tmp_search_sql = substr($tmp_search_sql, 4);
	basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_order_id', 'policy_', "AND ($tmp_search_sql)");
	if ($fmdb->num_rows) {
		foreach ($fmdb->last_result as $row) {
			$found = false;
			if (isset($nested_results[__('Policies')])) {
				foreach ($nested_results[__('Policies')] as $array) {
					if ($row->policy_id == $array->policy_id) $found = true;
				}
			}
			if (!$found) {
				$results[__('Policies')][] = $row;
			}
		}
	}
	
	return $results;
}


/**
 * Converts the netmask to cidr value
 *
 * @since 3.0
 * @package facileManager
 * @subpackage fmFirewall
 * 
 * @param string $mask Mask to convert
 * 
 * @return string
 */
function mask2cidr($mask) {
	$long = ip2long($mask);
	$base = ip2long('255.255.255.255');
	return 32 - log(($long ^ $base) +1, 2);
}


/**
 * Gets a delimiter based on value
 *
 * @since 3.2
 * @package facileManager
 * @subpackage fmFirewall
 * 
 * @param string|array $values String or array to search
 * 
 * @return string
 */
function getDelimiter($values) {
	$delimiter_opts = array(';', ',');

	foreach ($delimiter_opts as $delimiter) {
		if (is_array($values)) {
			if (in_array($delimiter, $values)) break;
		} else {
			if (strpos($values, $delimiter) !== false) break;
		}
	}

	return $delimiter;
}


/**
 * Verifies if an address/CIDR is valid
 *
 * @since 3.2
 * @package facileManager
 * @subpackage fmFirewall
 * 
 * @param string $ipCIDR Address with CIDR
 * 
 * @return bool
 */
function verifyCIDR($ipCIDR) {
	@list($address, $cidr) = explode('/', $ipCIDR);
	if (!$cidr) $cidr = 32;

	if (!verifyNumber($cidr, 0, 32)) return false;

	if (!verifyIPAddress($address)) return false;

	return true;
}
