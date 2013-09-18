<?php

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
	
	$available_zones_perms = 0;
	
	if (isSerialized($user_module_perms)) {
		$user_module_perms = unserialize($user_module_perms);
		$available_zones_perms = $user_module_perms['zone_access'];
	}
	
	/** Get available zones */
	$available_zones[0][] = 'All Zones';
	$available_zones[0][] = '0';
	
	basicGetList('fm_' . $__FM_CONFIG[$module_name]['prefix'] .'domains', 'domain_mapping`,`domain_name', 'domain_', 'AND domain_clone_domain_id=0');
	if ($fmdb->num_rows) {
		$results = $fmdb->last_result;
		for ($i=0; $i<$fmdb->num_rows; $i++) {
			$available_zones[$i+1][] = $results[$i]->domain_name;
			$available_zones[$i+1][] = $results[$i]->domain_id;
		}
	}
	$zones_list = buildSelect("fm_perm[$module_name][extra_zone_access]", 1, $available_zones, $available_zones_perms, 5, null, true);
	
	return <<<HTML
							<tr>
								<th></th>
								<td><strong>Limit access to the following zones:</strong><br />$zones_list</td>
							</tr>

HTML;
	
}


?>