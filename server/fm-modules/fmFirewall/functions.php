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
//	$checks[] = (basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_', 'active')) ? null : '<p>You currently have no active database servers defined.  <a href="' . $__FM_CONFIG['menu']['Config']['Servers'] . '">Click here</a> to define one or more to manage.</p>';
	
	/** Count groups */
//	$checks[] = (basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'groups', 'group_id', 'group_')) ? null : '<p>You currently have no database server groups defined.  <a href="' . $__FM_CONFIG['menu']['Config']['Server Groups'] . '">Click here</a> to define one or more.</p>';
	
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
	
	return '<p>' . $_SESSION['module'] . ' still needs to be written.</p>';

	/** Server stats */
	basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_');
	$summary = '<li>You have <b>' . $fmdb->num_rows . '</b> database server';
	if ($fmdb->num_rows != 1) $summary .= 's';
	$summary .= ' configured.</li>' . "\n";
	
	/** Group stats */
	basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'groups', 'group_id', 'group_');
	$summary .= '<li>You have <b>' . $fmdb->num_rows . '</b> group';
	if ($fmdb->num_rows != 1) $summary .= 's';
	$summary .= ' defined.</li>' . "\n";
	
	$dashboard = <<<DASH
	<div id="shadow_box" class="leftbox">
		<div id="shadow_container">
		<h3>Summary</h3>
		$summary
		</div>
	</div>
DASH;

	return $dashboard;
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
		$domain_menu = <<<HTML
		<div id="topheadpart">
			<span class="single_line">Firewall:&nbsp;&nbsp; $server_name</span>
		</div>
HTML;
	} else $domain_menu = null;
	
	return $domain_menu;
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
	
	return 'This still needs to be implemented for ' . $_SESSION['module'];
	
	$body = <<<HTML
<h3>{$_SESSION['module']}</h3>
<ul>
	<li>
		<a class="list_title" onclick="javascript:toggleLayer('fmsqlpass_config_servers', 'block');">Configure Servers</a>
		<div id="fmsqlpass_config_servers">
			<p>Database servers can be managed from the Config &rarr; <a href="{$__FM_CONFIG['module']['menu']['Config']['URL']}">Servers</a> menu item. From 
			there you can add ({$__FM_CONFIG['icons']['add']}), edit ({$__FM_CONFIG['icons']['edit']}), and delete ({$__FM_CONFIG['icons']['delete']}) 
			servers depending on your user permissions.</p>
			<p><i>The 'Server Management' or 'Super Admin' permission is required to add, edit, and delete servers.</i></p>
			<p>Select the database server type from the list and associate the server with a group. You can also override the user credentials 
			{$_SESSION['module']} should use for this server. If the credentials are left blank, {$_SESSION['module']} will use the credentials 
			defined in the module settings.</p>
			<br />
		</div>
	</li>
	<li>
		<a class="list_title" onclick="javascript:toggleLayer('fmsqlpass_config_groups', 'block');">Configure Server Groups</a>
		<div id="fmsqlpass_config_groups">
			<p>Server groups are used so you can change the user passwords on a subset of servers rather than all (or selecting individual servers on
			each run). An example would be to create a group for each data center hosting your servers so you can change the password for all database
			servers within that data center.</p>
			<p>Database server groups can be managed from the Config &rarr; <a href="{$__FM_CONFIG['module']['menu']['Config']['Server Groups']}">Server Groups</a> 
			menu item. From there you can add ({$__FM_CONFIG['icons']['add']}), edit ({$__FM_CONFIG['icons']['edit']}), and delete 
			({$__FM_CONFIG['icons']['delete']}) servers depending on your user permissions.</p>
			<p><i>The 'Server Management' or 'Super Admin' permission is required to add, edit, and delete server groups.</i></p>
			<br />
		</div>
	</li>
	<li>
		<a class="list_title" onclick="javascript:toggleLayer('fmsqlpass_passwords', 'block');">Set Passwords</a>
		<div id="fmsqlpass_passwords">
			<p>Database user passwords can be updated from the Config &rarr; <a href="{$__FM_CONFIG['module']['menu']['Config']['Passwords']}">Passwords</a> 
			menu item. From there you can select the server groups, enter the username to change the password for, and enter the new password.</p>
			<p><i>The 'Password Management' or 'Super Admin' permission is required to update database user passwords.</i></p>
			<p>You can enter the username as "<code>username</code>" which will change the password for all users matching that string. If you want to 
			change the password for "<code>username@localhost</code>" only and leave the password in tact for "<code>username@%</code>" then you can 
			enter the username as "<code>username@localhost</code>"</p>
			<br />
		</div>
	</li>
	<li>
		<a class="list_title" onclick="javascript:toggleLayer('fm_module_settings', 'block');">Module Settings</a>
		<div id="fm_module_settings">
			<p>Settings for {$_SESSION['module']} can be updated from the <a href="{$__FM_CONFIG['module']['menu']['Settings']['URL']}">Settings</a> 
			menu item.</p>
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
?>