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
 | fmDNS: Easily manage one or more ISC BIND servers                       |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdns/                             |
 +-------------------------------------------------------------------------+
*/

/**
 * Print module users permission form
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param string $user_module_perms Existing permissions
 * @param string $module_name Module name
 * @return string
 */
function printfmDNSUsersForm($user_module_perms, $module_name) {
	global $__FM_CONFIG, $fmdb;
	
	if (!array_key_exists($module_name, $__FM_CONFIG)) {
		/** Include module variables */
		@include(dirname(__FILE__) . '/variables.inc.php');
	}
	
	$available_zones_perms = 0;
	
	if (isSerialized($user_module_perms)) {
		$user_module_perms = unserialize($user_module_perms);
	}
	$available_zones_perms = isset($user_module_perms[$module_name]['access_specific_zones']) ? $user_module_perms[$module_name]['access_specific_zones'] : 0;
	
	/** Get available zones */
	$available_zones[0][] = null;
	$available_zones[0][0][] = 'All Zones';
	$available_zones[0][0][] = '0';
	
	/** Zone Groups */
	$j = 0;
	basicGetList('fm_' . $__FM_CONFIG[$module_name]['prefix'] .'domain_groups', 'group_name', 'group_');
	if ($fmdb->num_rows) {
		$results = $fmdb->last_result;
		$count = $fmdb->num_rows;
		for ($i=0; $i<$count; $i++) {
			$available_zones[__('Groups')][$j][] = $results[$i]->group_name;
			$available_zones[__('Groups')][$j][] = 'g_' . $results[$i]->group_id;
			$j++;
		}
	}

	/** Zones */
	$j = 0;
	basicGetList('fm_' . $__FM_CONFIG[$module_name]['prefix'] .'domains', 'domain_mapping`,`domain_name', 'domain_');
	if ($fmdb->num_rows) {
		$results = $fmdb->last_result;
		$count = $fmdb->num_rows;
		for ($i=0; $i<$count; $i++) {
			$domain_name = (!function_exists('displayFriendlyDomainName')) ? $results[$i]->domain_name : displayFriendlyDomainName($results[$i]->domain_name);
			if ($results[$i]->domain_view) {
				$domain_name .= ' (' . getNameFromID($results[$i]->domain_view, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name') . ')';
			}
			$available_zones[__('Zones')][$j][] = $domain_name;
			$available_zones[__('Zones')][$j][] = $results[$i]->domain_id;
			$j++;
		}
	}

	$zones_list = buildSelect("user_caps[$module_name][access_specific_zones]", 1, $available_zones, $available_zones_perms, 5, null, true, null, 'wide_select', __('Select one or more zones'));
	
	return sprintf('
							<tr class="user_permissions">
								<th></th>
								<td><strong>%s</strong><br />%s</td>
							</tr>
', __('Limit access to the following zones:'), $zones_list);
	
}

/**
 * Get user permissions for logging
 *
 * @since 6.3.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param array $fm_user_caps All user permissions
 * @param string $perm Non-standard permission
 * @param array $value_array Array of permissions
 * @return string
 */
function getfmDNSUsersPermLogging($fm_user_caps, $perm, $value_array) {
	global $__FM_CONFIG;

	$specific_perms = ucwords(str_replace('_', ' ', $perm));
	if ($perm == 'access_specific_zones') {
		if ($value_array[0] == '0') {
			$specific_items[] = __('All Zones');
		} else {
			foreach ($value_array as $domain_id) {
				if (strpos($domain_id, 'g_') !== false) {
					$id = substr($domain_id, 2);
					$type = 'group';
					$table = 'domain_groups';
				} else {
					$id = $domain_id;
					$type = 'domain';
					$table = 'domains';
				}
				if (!isset($__FM_CONFIG['fmDNS'])) {
					include(ABSPATH . 'fm-modules/fmDNS/variables.inc.php');
				}
				$specific_items[] = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . $table, "{$type}_", "{$type}_id", "{$type}_name");
			}
		}
	}

	return (is_array($specific_items)) ? sprintf('%s (%s)', $specific_perms, join(', ', (array) $specific_items)) : null;
}