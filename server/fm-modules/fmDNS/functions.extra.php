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
 | fmDNS: Easily manage one or more ISC BIND servers                       |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdns/                             |
 +-------------------------------------------------------------------------+
*/

/**
 * Checks if an email address is valid
 *
 * @since 1.0
 * @package facileManager
 *
 * @param string $address Email address to validate
 * @return boolean
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
	$available_zones[0][] = 'All Zones';
	$available_zones[0][] = '0';
	
	basicGetList('fm_' . $__FM_CONFIG[$module_name]['prefix'] .'domains', 'domain_mapping`,`domain_name', 'domain_', 'AND domain_clone_domain_id=0');
	if ($fmdb->num_rows) {
		$results = $fmdb->last_result;
		for ($i=0; $i<$fmdb->num_rows; $i++) {
			$available_zones[$i+1][] = displayFriendlyDomainName($results[$i]->domain_name);
			$available_zones[$i+1][] = $results[$i]->domain_id;
		}
	}
	$zones_list = buildSelect("user_caps[$module_name][access_specific_zones]", 1, $available_zones, $available_zones_perms, 5, null, true, null, 'wide_select', _('Select one or more zones'));
	
	return sprintf('
							<tr>
								<th></th>
								<td><strong>%s</strong><br />%s</td>
							</tr>
', _('Limit access to the following zones:'), $zones_list);
	
}


?>