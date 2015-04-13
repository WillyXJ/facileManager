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

class fm_dns_zones {
	
	/**
	 * Displays the zone list
	 */
	function rows($result, $map, $reload_allowed, $page, $total_pages) {
		global $fmdb, $__FM_CONFIG;
		
		$all_num_rows = $num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;

		if (currentUserCan('reload_zones', $_SESSION['module'])) {
			$bulk_actions_list = array(__('Reload'));
			$checkbox[] = array(
								'title' => '<input type="checkbox" class="tickall" onClick="toggle(this, \'domain_list[]\')" />',
								'class' => 'header-tiny header-nosort'
							);
		} else {
			$checkbox = $bulk_actions_list = null;
		}

		if (!$result) {
			printf('<p id="table_edits" class="noresult" name="domains">%s</p>', __('There are no zones.'));
		} else {
			if (array_key_exists('attention', $_GET)) {
				$num_rows = $GLOBALS['zone_badge_counts'][$map];
				$total_pages = ceil($num_rows / $_SESSION['user']['record_count']);
				if ($page > $total_pages) $page = $total_pages;
			}

			$start = $_SESSION['user']['record_count'] * ($page - 1);
			$end = $_SESSION['user']['record_count'] * $page > $num_rows ? $num_rows : $_SESSION['user']['record_count'] * $page;

			$classes = (array_key_exists('attention', $_GET)) ? null : ' grey';
			$eye_attention = $GLOBALS['zone_badge_counts'][$map] ? '<i class="fa fa-eye fa-lg eye-attention' . $classes . '" title="' . __('Only view zones that need attention') . '"></i>' : null;
			$addl_blocks = array(@buildBulkActionMenu($bulk_actions_list, 'server_id_list'), $this->buildFilterMenu(), $eye_attention);
			$fmdb->num_rows = $num_rows;
			echo displayPagination($page, $total_pages, $addl_blocks);
			
			$table_info = array(
							'class' => 'display_results sortable',
							'id' => 'table_edits',
							'name' => 'domains'
						);

			$title_array = array(array('title' => __('ID'), 'class' => 'header-small header-nosort'), 
				array('title' => __('Domain'), 'rel' => 'domain_name'), 
				array('title' => __('Type'), 'rel' => 'domain_type'),
				array('title' => __('Views'), 'class' => 'header-nosort'),
				array('title' => __('Records'), 'class' => 'header-small  header-nosort'));
			$title_array[] = array('title' => __('Actions'), 'class' => 'header-actions header-nosort');
			
			if (is_array($checkbox)) {
				$title_array = array_merge($checkbox, $title_array);
			}

			echo displayTableHeader($table_info, $title_array, 'zones');
			
			$y = 0;
			for ($x=$start; $x<$all_num_rows; $x++) {
				if ($y == $_SESSION['user']['record_count']) break;
				if (array_key_exists('attention', $_GET)) {
					if (!$results[$x]->domain_clone_domain_id && $results[$x]->domain_type == 'master' && $results[$x]->domain_template == 'no' &&
						(!getSOACount($results[$x]->domain_id) || !getNSCount($results[$x]->domain_id) || $results[$x]->domain_reload != 'no')) {
							$this->displayRow($results[$x], $map, $reload_allowed);
							$y++;
					}
				} else {
					$this->displayRow($results[$x], $map, $reload_allowed);
					$y++;
				}
			}
			
			echo "</tbody>\n</table>\n";
		}
	}

	/**
	 * Adds the new zone
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate post */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains`";
		$sql_fields = '(';
		$sql_values = $domain_name_servers = $domain_views = null;
		
		$log_message = __('Added a zone with the following details:') . "\n";

		/** Format domain_view */
		$log_message_views = null;
		$domain_view_array = explode(';', $post['domain_view']);
		if (is_array($domain_view_array)) {
			$domain_view = null;
			foreach ($domain_view_array as $val) {
				if ($val == 0 || $val == '') {
					$domain_view = 0;
					break;
				}
				$domain_view .= $val . ';';
				$view_name = getNameFromID($val, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name');
				$log_message_views .= $val ? "$view_name; " : null;
			}
			$post['domain_view'] = rtrim($domain_view, ';');
			$log_message_views = rtrim(trim($log_message_views), ';');
		}
		if (!$post['domain_view']) $post['domain_view'] = 0;

		/** Format domain_name_servers */
		$log_message_name_servers = null;
		foreach ($post['domain_name_servers'] as $val) {
			if ($val == '0') {
				$domain_name_servers = 0;
				$log_message_name_servers = __('All Servers');
				break;
			}
			$domain_name_servers .= $val . ';';
			if ($val[0] == 's') {
				$server_name = getNameFromID(preg_replace('/\D/', null, $val), 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
			} elseif ($val[0] == 'g') {
				$server_name = getNameFromID(preg_replace('/\D/', null, $val), 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'server_groups', 'group_', 'group_id', 'group_name');
			}
			$log_message_name_servers .= $val ? "$server_name; " : null;
		}
		$post['domain_name_servers'] = rtrim($domain_name_servers, ';');
		if (!$post['domain_name_servers']) $post['domain_name_servers'] = 0;

		/** Get clone parent values */
		if ($post['domain_clone_domain_id']) {
			$query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE domain_id={$post['domain_clone_domain_id']}";
			$fmdb->query($query);
			if (!$fmdb->num_rows) return __('Cannot find cloned zone.');
			
			$parent_domain = $fmdb->last_result;
			foreach ($parent_domain[0] as $field => $value) {
				if (in_array($field, array('domain_id', 'domain_template_id'))) continue;
				if ($field == 'domain_clone_domain_id') {
					$sql_values .= sanitize($post['domain_clone_domain_id']) . ',';
				} elseif ($field == 'domain_name') {
					$log_message .= 'Name: ' . displayFriendlyDomainName(sanitize($post['domain_name'])) . "\n";
					$log_message .= "Clone of: $value\n";
					$sql_values .= "'" . sanitize($post['domain_name']) . "',";
				} elseif ($field == 'domain_view') {
					$log_message .= "Views: $log_message_views\n";
					$sql_values .= "'" . sanitize($post['domain_view']) . "',";
				} elseif ($field == 'domain_name_servers' && sanitize($post['domain_name_servers'])) {
					$log_message .= "Servers: $log_message_name_servers\n";
					$sql_values .= "'" . sanitize($post['domain_name_servers']) . "',";
				} elseif ($field == 'domain_reload') {
					$sql_values .= "'no',";
				} elseif ($field == 'domain_clone_dname') {
					$log_message .= "Use DNAME RRs: {$post['domain_clone_dname']}\n";
					$sql_values .= $post['domain_clone_dname'] ? "'" . sanitize($post['domain_clone_dname']) . "'," : 'NULL,';
				} else {
					$sql_values .= strlen(sanitize($value)) ? "'" . sanitize($value) . "'," : 'NULL,';
				}
				$sql_fields .= $field . ',';
			}
			$sql_fields = rtrim($sql_fields, ',') . ')';
			$sql_values = rtrim($sql_values, ',');
		} else {
			/** Format domain_view */
			$log_message_views = null;
			if (is_array($post['domain_view'])) {
				$domain_view = null;
				foreach ($post['domain_view'] as $val) {
					if ($val == 0 || $val == '') {
						$domain_view = 0;
						break;
					}
					$domain_view .= $val . ';';
					$view_name = getNameFromID($val, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name');
					$log_message_views .= $val ? "$view_name; " : null;
				}
				$post['domain_view'] = rtrim($domain_view, ';');
			}
			if (!$post['domain_view']) $post['domain_view'] = 0;
			
			$exclude = array('submit', 'action', 'domain_id', 'domain_required_servers', 'domain_forward', 'domain_clone_domain_id');
		
			foreach ($post as $key => $data) {
				if (!in_array($key, $exclude)) {
					$sql_fields .= $key . ',';
					if (is_array($data)) $data = implode(';', $data);
					$sql_values .= strlen(sanitize($data)) ? "'" . sanitize($data) . "'," : 'NULL,';
					if ($key == 'domain_view') $data = $log_message_views;
					if ($key == 'domain_name_servers') $data = rtrim($log_message_name_servers, '; ');
					if ($key == 'soa_id') {
						$soa_name = $data ? getNameFromID($data, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'soa', 'soa_', 'soa_id', 'soa_name') : 'Custom';
						$log_message .= formatLogKeyData('_id', $key, $soa_name);
					} elseif ($key == 'domain_template_id') {
						$log_message .= formatLogKeyData(array('domain_', '_id'), $key, getNameFromID($data, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name'));
					} else {
						$log_message .= $data ? formatLogKeyData('domain_', $key, $data) : null;
					}
					if ($key == 'domain_default' && $data == 'yes') {
						$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` SET $key = 'no' WHERE `account_id`='{$_SESSION['user']['account_id']}'";
						$result = $fmdb->query($query);
					}
				}
			}
			$sql_fields .= 'account_id)';
			$sql_values .= "'{$_SESSION['user']['account_id']}'";
		}
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) return __('Could not add zone because a database error occurred.');

		$insert_id = $fmdb->insert_id;
		
		/** Add mandatory config options */
		$query = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` 
			(account_id,domain_id,cfg_name,cfg_data) VALUES ({$_SESSION['user']['account_id']}, $insert_id, ";
		$required_servers = sanitize($post['domain_required_servers']);
		if (!$post['domain_template_id']) {
			if ($post['domain_type'] == 'forward') {
				$result = $fmdb->query($query . "'forwarders', '" . $required_servers . "')");
				$log_message .= formatLogKeyData('domain_', 'forwarders', $required_servers);

				$domain_forward = sanitize($post['domain_forward'][0]);
				$result = $fmdb->query($query . "'forward', '" . $domain_forward . "')");
				$log_message .= formatLogKeyData('domain_', 'forward', $domain_forward);
			} elseif (in_array($post['domain_type'], array('slave', 'stub'))) {
				$query .= "'masters', '" . $required_servers . "')";
				$result = $fmdb->query($query);
				$log_message .= formatLogKeyData('domain_', 'masters', $required_servers);
			}
		}
		if ($fmdb->sql_errors) return __('Could not add zone because a database error occurred.');
		
		$this->updateSOASerialNo($insert_id, 0);
		
		addLogEntry($log_message);
		
		/* Set the server_build_config flag for servers */
		if ($post['domain_clone_domain_id'] || $post['domain_template_id']) {
			if (getSOACount($insert_id) && getNSCount($insert_id)) {
				setBuildUpdateConfigFlag(getZoneServers($insert_id, array('masters', 'slaves')), 'yes', 'build');
			}
		}
		return $insert_id;
	}

	/**
	 * Updates the selected zone
	 */
	function update() {
		global $fmdb, $__FM_CONFIG;
		
		$domain_id = sanitize($_POST['domain_id']);
		
		/** Validate post */
		$_POST['domain_mapping'] = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_mapping');
		$_POST['domain_type'] = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_type');
		
		$post = $this->validatePost($_POST);
		if (!is_array($post)) return $post;
		
		$sql_edit = $domain_name_servers = $domain_view = null;
		
		$old_name = displayFriendlyDomainName(getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name'));
		$log_message = "Updated a zone ($old_name) with the following details:\n";

		/** If changing zone to clone or different domain_type, are there any existing associated records? */
		if ($post['domain_clone_domain_id'] || $post['domain_type'] != 'master') {
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $domain_id, 'record_', 'domain_id');
			if ($fmdb->num_rows) return __('There are associated records with this zone.');
		}
		
		/** Format domain_view */
		$log_message_views = null;
		if (is_array($post['domain_view'])) {
			foreach ($post['domain_view'] as $val) {
				if ($val == 0) {
					$domain_view = 0;
					break;
				}
				$domain_view .= $val . ';';
				$view_name = getNameFromID($val, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name');
				$log_message_views .= $val ? "$view_name; " : null;
			}
			$post['domain_view'] = rtrim($domain_view, ';');
		}
		
		/** Format domain_name_servers */
		$log_message_name_servers = null;
		foreach ($post['domain_name_servers'] as $val) {
			if ($val == '0') {
				$domain_name_servers = 0;
				break;
			}
			$domain_name_servers .= $val . ';';
			$server_name = getNameFromID($val, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
			$log_message_name_servers .= $val ? "$server_name; " : null;
		}
		$post['domain_name_servers'] = rtrim($domain_name_servers, ';');
		if (!$post['domain_name_servers']) $post['domain_name_servers'] = 0;
		
		$exclude = array('submit', 'action', 'domain_id', 'domain_required_servers', 'domain_forward');

		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= strlen(sanitize($data)) ? $key . "='" . mysql_real_escape_string($data) . "'," : $key . '=NULL,';
				if ($key == 'domain_view') $data = $log_message_views;
				if ($key == 'domain_name_servers') $data = $log_message_name_servers;
				$log_message .= $data ? formatLogKeyData('domain_', $key, $data) : null;
				if ($key == 'domain_default' && $data == 'yes') {
					$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` SET $key = 'no' WHERE `account_id`='{$_SESSION['user']['account_id']}'";
					$result = $fmdb->query($query);
				}
			}
		}
		$sql_edit .= "domain_reload='no'";
		
		/** Set the server_build_config flag for existing servers */
		if (getSOACount($domain_id) && getNSCount($domain_id)) {
			setBuildUpdateConfigFlag(getZoneServers($domain_id, array('masters', 'slaves')), 'yes', 'build');
		}

		/** Update the zone */
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` SET $sql_edit WHERE `domain_id`='$domain_id' AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) return __('Could not update the zone because a database error occurred.');
		
		$rows_affected = $fmdb->rows_affected;

		/** Add mandatory config options */
		$query = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` 
			(account_id,domain_id,cfg_name,cfg_data) VALUES ({$_SESSION['user']['account_id']}, $domain_id, ";
		$required_servers = sanitize($post['domain_required_servers']);
		if (!$post['domain_template_id']) {
			if ($post['domain_type'] == 'forward') {
				if (getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'domain_id', 'cfg_data', null, "AND cfg_name='forwarders'")) {
					basicUpdate("fm_{$__FM_CONFIG['fmDNS']['prefix']}config", getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'domain_id', 'cfg_id', null, "AND cfg_name='forwarders'"), 'cfg_data', $required_servers, 'cfg_id');
				} else {
					$result = $fmdb->query($query . "'forwarders', '" . $required_servers . "')");
				}
				$log_message .= formatLogKeyData('domain_', 'forwarders', $required_servers);

				$domain_forward = sanitize($post['domain_forward'][0]);
				if (getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'domain_id', 'cfg_data', null, "AND cfg_name='forward'")) {
					basicUpdate("fm_{$__FM_CONFIG['fmDNS']['prefix']}config", getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'domain_id', 'cfg_id', null, "AND cfg_name='forward'"), 'cfg_data', $domain_forward, 'cfg_id');
				} else {
					$result = $fmdb->query($query . "'forward', '" . $domain_forward . "')");
				}
				$log_message .= formatLogKeyData('domain_', 'forward', $domain_forward);
			} elseif (in_array($post['domain_type'], array('slave', 'stub'))) {
				if (getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'domain_id', 'cfg_data', null, "AND cfg_name='masters'")) {
					basicUpdate("fm_{$__FM_CONFIG['fmDNS']['prefix']}config", getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'domain_id', 'cfg_id', null, "AND cfg_name='masters'"), 'cfg_data', $required_servers, 'cfg_id');
				} else {
					$query .= "'masters', '" . $required_servers . "')";
					$result = $fmdb->query($query);
				}
				$log_message .= formatLogKeyData('domain_', 'masters', $required_servers);
			}
		} else {
			/** Remove all zone config options */
			basicDelete("fm_{$__FM_CONFIG['fmDNS']['prefix']}config", $domain_id, 'domain_id');
		}
		if ($fmdb->sql_errors) return __('Could not update zone because a database error occurred.') . ' ' . $fmdb->last_error;
		
		/** Return if there are no changes */
		if ($rows_affected + $fmdb->rows_affected = 0) return true;

		/** Set the server_build_config flag for new servers */
		if (getSOACount($domain_id) && getNSCount($domain_id)) {
			setBuildUpdateConfigFlag(getZoneServers($domain_id, array('masters', 'slaves')), 'yes', 'build');
		}

		/** Delete associated records from fm_{$__FM_CONFIG['fmDNS']['prefix']}track_builds */
		basicDelete('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'track_builds', $domain_id, 'domain_id', false);

		addLogEntry($log_message);
		return true;
	}
	
	
	/**
	 * Deletes the selected zone and all associated records
	 */
	function delete($domain_id) {
		global $fmdb, $__FM_CONFIG;
		
		/** Does the domain_id exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $domain_id, 'domain_', 'domain_id', 'active');
		if ($fmdb->num_rows) {
			$domain_result = $fmdb->last_result[0];
			unset($fmdb->num_rows);
			
			/** Delete all associated configs */
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', $domain_id, 'cfg_', 'domain_id');
			if ($fmdb->num_rows) {
				if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', $domain_id, 'cfg_', 'deleted', 'domain_id') === false) {
					return __('The associated configs for this zone could not be deleted because a database error occurred.');
				}
				unset($fmdb->num_rows);
			}
			
			/** Delete all associated records */
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $domain_id, 'record_', 'domain_id');
			if ($fmdb->num_rows) {
				if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $domain_id, 'record_', 'deleted', 'domain_id') === false) {
					return __('The associated records for this zone could not be deleted because a database error occurred.');
				}
				unset($fmdb->num_rows);
			}
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records_skipped', $domain_id, 'record_', 'domain_id');
			if ($fmdb->num_rows) {
				if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records_skipped', $domain_id, 'record_', 'deleted', 'domain_id') === false) {
					return __('The associated records for this zone could not be deleted because a database error occurred.');
				}
				unset($fmdb->num_rows);
			}
			
			/** Delete all associated SOA */
			if (!$domain_result->domain_clone_domain_id && $domain_result->soa_id) {
				basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'soa', $domain_result->soa_id, 'soa_', 'soa_id', "AND soa_template='no'");
				if ($fmdb->num_rows) {
					if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'soa', $domain_result->soa_id, 'soa_', 'deleted', 'soa_id') === false) {
						return __('The SOA for this zone could not be deleted because a database error occurred.');
					}
					unset($fmdb->num_rows);
				}
			}
			
			/** Delete associated records from fm_{$__FM_CONFIG['fmDNS']['prefix']}track_builds */
			if (basicDelete('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'track_builds', $domain_id, 'domain_id', false) === false) {
				return sprintf(__('The zone could not be removed from the %s table because a database error occurred.'), 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'track_builds');
			}
			
			/** Force buildconf for all associated DNS servers */
			setBuildUpdateConfigFlag(getZoneServers($domain_id, array('masters', 'slaves')), 'yes', 'build');
			
			/** Delete cloned zones */
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $domain_id, 'domain_', 'domain_clone_domain_id');
			if ($fmdb->num_rows) {
				unset($fmdb->num_rows);
				/** Delete cloned zone records first */
				basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_id', 'domain_', "AND domain_clone_domain_id=$domain_id");
				if ($fmdb->num_rows) {
					$clone_domain_result = $fmdb->last_result;
					$clone_domain_num_rows = $fmdb->num_rows;
					for ($i=0; $i<$clone_domain_num_rows; $i++) {
						if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $clone_domain_result[$i]->domain_id, 'record_', 'deleted', 'domain_id') === false) {
							return __('The associated records for the cloned zones could not be deleted because a database error occurred.');
						}
					}
					unset($fmdb->num_rows);
				}
				
				if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $domain_id, 'domain_', 'deleted', 'domain_clone_domain_id') === false) {
					return __('The associated clones for this zone could not be deleted because a database error occurred.');
				}
			}
			
			/** Delete zone */
			$tmp_name = displayFriendlyDomainName(getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name'));
			if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $domain_id, 'domain_', 'deleted', 'domain_id') === false) {
				return __('This zone could not be deleted because a database error occurred.');
			}
			
			addLogEntry("Deleted zone '$tmp_name' and all associated records.");
			
			return true;
		}
		
		return __('This zone does not exist.');
	}
	
	
	function displayRow($row, $map, $reload_allowed) {
		global $fmdb, $__FM_CONFIG;
		
		if (!currentUserCan(array('access_specific_zones', 'view_all'), $_SESSION['module'], array(0, $row->domain_id))) return;
		
		$zone_access_allowed = zoneAccessIsAllowed(array($row->domain_id));
		
		if ($row->domain_status == 'disabled') $classes[] = 'disabled';
		$response = $add_new = null;
		
		$checkbox = (currentUserCan('reload_zones', $_SESSION['module'])) ? '<td></td>' : null;
		
		$soa_count = getSOACount($row->domain_id);
		$ns_count = getNSCount($row->domain_id);
		$reload_allowed = reloadAllowed($row->domain_id);
		if (!$soa_count && $row->domain_type == 'master') {
			$response = __('The SOA record still needs to be created for this zone');
			$classes[] = 'attention';
		}
		if (!$ns_count && $row->domain_type == 'master' && !$response) {
			$response = __('One more more NS records still needs to be created for this zone');
			$classes[] = 'attention';
		}
		
		if ($row->domain_type == 'master' && currentUserCan('manage_zones', $_SESSION['module'])) {
			global $map;
			
			$add_new = displayAddNew($map, $row->domain_id, __('Clone this zone'), 'fa-plus-square-o');
		}
		
		$clones = $this->cloneDomainsList($row->domain_id);
		$clone_names = $clone_types = $clone_views = $clone_counts = null;
		foreach ($clones as $clone_id => $clone_array) {
			$clone_names .= '<p class="clone' . $clone_id . '"><a href="' . $clone_array['clone_link'] . '" title="' . __('Edit zone records') . '">' . $clone_array['clone_name'] . 
					'</a>' . $clone_array['clone_edit'] . $clone_array['clone_delete'] . "</p>\n";
			$clone_types .= '<p class="clone' . $clone_id . '">' . __('clone') . '</p>' . "\n";
			$clone_views .= '<p class="clone' . $clone_id . '">' . $this->IDs2Name($clone_array['clone_views'], 'view') . "</p>\n";
			$clone_counts_array = explode('|', $clone_array['clone_count']);
			$clone_counts .= '<p class="clone' . $clone_id . '" title="' . __('Differences from parent zone') . '">';
			if ($clone_counts_array[0]) $clone_counts .= '<span class="record-additions">' . $clone_counts_array[0] . '</span>&nbsp;';
			if ($clone_counts_array[1]) $clone_counts .= '&nbsp;<span class="record-subtractions">' . $clone_counts_array[1] . '</span> ';
			if (!array_sum($clone_counts_array)) $clone_counts .= '-';
			$clone_counts .= "</p>\n";
		}
		if ($clone_names) $classes[] = 'clones';
		
		if ($soa_count && $row->domain_reload == 'yes' && $reload_allowed) {
			if (currentUserCan('reload_zones', $_SESSION['module']) && $zone_access_allowed) {
				$reload_zone = '<form name="reload" id="' . $row->domain_id . '" method="post" action="' . $GLOBALS['basename'] . '?map=' . $map . '"><input type="hidden" name="action" value="reload" /><input type="hidden" name="domain_id" id="domain_id" value="' . $row->domain_id . '" />' . $__FM_CONFIG['icons']['reload'] . '</form>';
				$checkbox = '<td><input type="checkbox" name="domain_list[]" value="' . $row->domain_id .'" /></td>';
			} else {
				$reload_zone = __('Reload Available') . '<br />';
			}
		} else $reload_zone = null;
		if ($reload_zone) $classes[] = 'build';
		
/*
		$edit_status = <<<FORM
<form method="post" action="{$GLOBALS['basename']}?map={$map}">
	<input type="hidden" name="action" value="download" />
	<input type="hidden" name="domain_id" value="{$row->domain_id}" />
	{$__FM_CONFIG['icons']['export']}
	</form>
FORM;
*/
		$edit_status = null;
		
		if (!$soa_count && $row->domain_type == 'master' && currentUserCan('manage_zones', $_SESSION['module'])) $type = 'SOA';
		elseif (!$ns_count && $row->domain_type == 'master' && currentUserCan('manage_zones', $_SESSION['module'])) $type = 'NS';
		else {
			$type = ($row->domain_mapping == 'forward') ? 'A' : 'PTR';
		}
		if ($soa_count && $ns_count && $row->domain_type == 'master') {
			$edit_status = '<a href="preview.php" onclick="javascript:void window.open(\'preview.php?server_serial_no=-1&config=zone&domain_id=' . $row->domain_id . '\',\'1356124444538\',\'width=700,height=500,toolbar=0,menubar=0,location=0,status=0,scrollbars=1,resizable=1,left=0,top=0\');return false;">' . $__FM_CONFIG['icons']['preview'] . '</a>';
		}
		if (currentUserCan('manage_zones', $_SESSION['module']) && $zone_access_allowed) {
			$edit_status .= '<a class="edit_form_link" name="' . $map . '" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a class="delete" href="#">' . $__FM_CONFIG['icons']['delete'] . '</a>' . "\n";
		}
		$domain_name = displayFriendlyDomainName($row->domain_name);
		$edit_name = ($row->domain_type == 'master') ? "<a href=\"zone-records.php?map={$map}&domain_id={$row->domain_id}&record_type=$type\" title=\"" . __('Edit zone records') . "\">$domain_name</a>" : $domain_name;
		$domain_view = $this->IDs2Name($row->domain_view, 'view');

		$class = 'class="' . implode(' ', $classes) . '"';
		
		$record_count = null;
		if ($row->domain_type == 'master') {
			$query = "SELECT COUNT(*) record_count FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}records WHERE account_id={$_SESSION['user']['account_id']} AND domain_id={$row->domain_id} AND record_status!='deleted'";
			$fmdb->query($query);
			$record_count = $fmdb->last_result[0]->record_count;
		}
		
		$template_icon = ($domain_template_id = getNameFromID($row->domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_template_id')) ? sprintf('<i class="template-icon fa fa-picture-o" title="%s"></i>', sprintf(__('Based on %s'), getNameFromID($domain_template_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name'))) : null;
		
		echo <<<HTML
		<tr title="$response" id="$row->domain_id" $class>
			$checkbox
			<td>$row->domain_id</td>
			<td><b>$edit_name</b> $template_icon $add_new $clone_names</td>
			<td>$row->domain_type
				$clone_types</td>
			<td>$domain_view
				$clone_views</td>
			<td align="center">$record_count
				$clone_counts</td>
			<td id="edit_delete_img">
				$reload_zone
				$edit_status
			</td>
		</tr>

HTML;
	}

	/**
	 * Displays the form to add new zone
	 */
	function printForm($data = '', $action = 'create', $map = 'forward', $show = array('popup', 'template_menu', 'create_template')) {
		global $fmdb, $__FM_CONFIG, $fm_dns_acls, $fm_module_options;
		
		$ucaction = ucfirst($action);
		$domain_id = $domain_view = $domain_name_servers = 0;
		$domain_type = $domain_clone_domain_id = $domain_name = $template_name = null;
		$disabled = $action == 'create' ? null : 'disabled';
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST)) {
				$domain_id = $_POST[$action . 'Zone']['ZoneID'];
				extract($_POST[$action . 'Zone'][$domain_id]);
			}
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		} elseif (!empty($_POST) && array_key_exists('is_ajax', $_POST)) {
			extract($_POST);
			$domain_clone_dname = null;
			$domain_template_id = getNameFromID($domain_clone_domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_template_id');
			if ($domain_template_id) {
				$domain_name_servers = getNameFromID($domain_template_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name_servers');
			} else {
				$domain_name_servers = getNameFromID($domain_clone_domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name_servers');
			}
		}
		
		$domain_name = function_exists('idn_to_utf8') ? idn_to_utf8($domain_name) : $domain_name;
		
		/** Process multiple views */
		if (strpos($domain_view, ';')) {
			$domain_view = explode(';', rtrim($domain_view, ';'));
			if (in_array('0', $domain_view)) $domain_view = 0;
		}
		
		/** Process multiple domain name servers */
		if (strpos($domain_name_servers, ';')) {
			$domain_name_servers = explode(';', rtrim($domain_name_servers, ';'));
			if (in_array('0', $domain_name_servers)) $domain_name_servers = 0;
		}
		
		/** Get field length */
		$domain_name_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_name');

		$views = buildSelect('domain_view', 'domain_view', $this->availableViews(), $domain_view, 4, null, true);
		$zone_maps = buildSelect('domain_mapping', 'domain_mapping', enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains','domain_mapping'), $map, 1, $disabled);
		$domain_types = buildSelect('domain_type', 'domain_type', enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains','domain_type'), $domain_type, 1, $disabled);
		$clone = buildSelect('domain_clone_domain_id', 'domain_clone_domain_id', $this->availableCloneDomains($map, $domain_id), $domain_clone_domain_id, 1, $disabled);
		$name_servers = buildSelect('domain_name_servers', 'domain_name_servers', availableDNSServers('id'), $domain_name_servers, 1, null, true);

		$forwarders_show = $masters_show = 'none';
		$domain_forward_servers = $domain_master_servers = $domain_forward = null;
		$available_acls = json_encode(array());
		if ($domain_type == 'forward') {
			$forwarders_show = 'block';
			$domain_forward_servers = str_replace(';', "\n", rtrim(str_replace(' ', '', getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'domain_id', 'cfg_data', null, "AND cfg_name='forwarders'")), ';'));
			$domain_forward = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'domain_id', 'cfg_data', null, "AND cfg_name='forward'");
			$available_acls = $fm_dns_acls->buildACLJSON($domain_forward_servers, 0, 'none');
		} elseif (in_array($domain_type, array('slave', 'stub'))) {
			$masters_show = 'block';
			$domain_master_servers = str_replace(';', "\n", rtrim(str_replace(' ', '', getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'domain_id', 'cfg_data', null, "AND cfg_name='masters'")), ';'));
			$available_acls = $fm_dns_acls->buildACLJSON($domain_master_servers, 0, 'none');
		}
		
		/** Build forward options */
		$query = "SELECT def_type,def_dropdown FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}functions WHERE def_option = 'forward'";
		$fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$forward_dropdown = $fm_module_options->populateDefTypeDropdown($fmdb->last_result[0]->def_type, $domain_forward, 'domain_forward');
		}
		
		if ($action == 'create') {
			$domain_template_id = $this->getDefaultZone();
			$zone_show = $domain_template_id ? 'none' : 'block';
			global $fm_dns_records;
			if (!isset($fm_dns_records)) include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_records.php');
			$soa_templates = '<tr id="define_soa">
					<th>SOA</th>
					<td>' . buildSelect('soa_id', 'soa_id', $fm_dns_records->availableSOATemplates($map), $fm_dns_records->getDefaultSOA()) . '</td></tr>';
		} else {
			$zone_show = 'block';
			$soa_templates = $domain_templates = null;
		}
		
		/** Clone options */
		if ($domain_clone_domain_id) {
			$clone_override_show = 'block';
			$clone_dname_checked = $domain_clone_dname ? 'checked' : null;
			$clone_dname_options_show = $domain_clone_dname ? 'block' : 'none';
			if (isset($no_template)) {
				$domain_template_id = 0;
				$zone_show = 'block';
			}
		} else {
			$clone_override_show = $clone_dname_options_show = 'none';
			$clone_dname_checked = null;
		}
		$clone_dname_dropdown = buildSelect('domain_clone_dname', 'domain_clone_dname', enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains','domain_clone_dname'), $domain_clone_dname);
		
		$additional_config_link = ($action == 'create' || !in_array($domain_type, array('master', 'slave'))) ? null : sprintf('<tr class="include-with-template"><td></td><td><p><a href="config-options.php?domain_id=%d">%s</a></p></td></tr>', $domain_id, __('Configure Additional Options'));
		
		$popup_header = buildPopup('header', $ucaction . ' Zone');
		$popup_footer = buildPopup('footer');
		
		if (array_search('create_template', $show) !== false) {
			$template_name_show_hide = 'none';
			$create_template = sprintf('<tr id="create_template">
			<th>%s</th>
			<td><input type="checkbox" id="domain_create_template" name="domain_template" value="yes" /><label for="domain_create_template"> %s</label></td>
		</tr>', __('Create Template'), __('yes'));
		} else {
			$template_name_show_hide = 'table-row';
			$create_template = <<<HTML
			<input type="hidden" id="domain_create_template" name="domain_template" value="no" />
			<input type="hidden" name="domain_default" value="no" />
HTML;
		}
	
		if (array_search('template_menu', $show) !== false) {
			$classes = 'zone-form';
			$select_template = '<tr id="define_template" class="include-with-template">
					<th>' . __('Template') . '</th>
					<td>' . buildSelect('domain_template_id', 'domain_template_id', $this->availableZoneTemplates(), $domain_template_id);
			if ($action == 'edit') {
				$select_template .= sprintf('<p>%s</p>', __('Changing the template will delete all config options for this zone.'));
			}
			$select_template .= '</td></tr>';
		} else {
			$classes = 'zone-template-form';
			$select_template = null;
		}
		
		if (array_search('template_name', $show) !== false) {
			$default_checked = ($domain_id == $this->getDefaultZone()) ? 'checked' : null;
			$template_name = sprintf('<tr id="domain_template_default" style="display: %s">
			<th></th>
			<td><input type="checkbox" id="domain_default" name="domain_default" value="yes" %s /><label for="domain_default"> %s</label></td>
			<input type="hidden" id="domain_create_template" name="domain_template" value="yes" />
		</tr>', $template_name_show_hide, $default_checked, __('Make Default Template'));
		}
	
		$return_form = (array_search('popup', $show) !== false) ? '<form name="manage" id="manage" method="post" action="">' . $popup_header : null;
		
		$return_form .= sprintf('<input type="hidden" name="action" value="%s" />
			<input type="hidden" name="domain_id" value="%d" />
			<table class="form-table %s">
				<tr class="include-with-template">
					<th><label for="domain_name">%s</label></th>
					<td><input type="text" id="domain_name" name="domain_name" size="40" value="%s" maxlength="%d" /></td>
				</tr>
				%s
				<tr>
					<th><label for="domain_view">%s</label></th>
					<td>%s</td>
				</tr>
				<tr>
					<th><label for="domain_mapping">%s</label></th>
					<td>%s</td>
				</tr>
				<tr>
					<th><label for="domain_type">%s</label></th>
					<td>
						%s
						<div id="define_forwarders" style="display: %s">
							<p>%s</p>
							<input type="hidden" name="domain_required_servers[forwarders]" id="domain_required_servers" class="address_match_element" data-placeholder="%s" value="%s" /><br />
							( address_match_element )
						</div>
						<div id="define_masters" style="display: %s">
							<input type="hidden" name="domain_required_servers[masters]" id="domain_required_servers" class="address_match_element" data-placeholder="%s" value="%s" /><br />
							( address_match_element )
						</div>
					</td>
				</tr>
				<tr>
					<th><label for="domain_clone_domain_id">%s</label></th>
					<td>
						%s
						<div id="clone_override" style="display: %s">
							<p><input type="checkbox" id="domain_clone_dname_override" name="domain_clone_dname_override" value="yes" %s /><label for="domain_clone_dname_override"> %s</label></p>
							<div id="clone_dname_options" style="display: %s">
								%s
							</div>
						</div>
					</td>
				</tr>
				<tr>
					<th><label for="domain_name_servers">%s</label></th>
					<td>%s</td>
				</tr>
				%s
			</table>',
				$action, $domain_id, $classes,
				__('Domain Name'), $domain_name, $domain_name_length,
				$select_template,
				__('Views'), $views,
				__('Zone Map'), $zone_maps,
				__('Zone Type'), $domain_types,
				$forwarders_show, $forward_dropdown, __('Define forwarders'), $domain_forward_servers,
				$masters_show, __('Define masters'), $domain_master_servers,
				__('Clone Of (optional)'), $clone, $clone_override_show, $clone_dname_checked,
				__('Override DNAME Resource Record Setting'), $clone_dname_options_show, $clone_dname_dropdown,
				__('DNS Servers'), $name_servers,
				$soa_templates . $additional_config_link . $create_template . $template_name
				);

		$return_form .= (array_search('popup', $show) !== false) ? $popup_footer . '</form>' : null;

		$return_form .= <<<HTML
		<script>
			$(document).ready(function() {
				$("#manage select").select2({
					width: '100%',
					minimumResultsForSearch: 10,
					allowClear: true
				});
				$(".address_match_element").select2({
					createSearchChoice:function(term, data) { 
						if ($(data).filter(function() { 
							return this.text.localeCompare(term)===0; 
						}).length===0) 
						{return {id:term, text:term};} 
					},
					multiple: true,
					width: '300px',
					tokenSeparators: [",", " ", ";"],
					data: $available_acls
				});
				$("#domain_clone_dname_override").click(function() {
					if ($(this).is(':checked')) {
						$('#clone_dname_options').show('slow');
					} else {
						$('#clone_dname_options').slideUp();
					}
				});
				$("#domain_create_template").click(function() {
					if ($(this).is(':checked')) {
						$('#domain_template_name').show('slow');
					} else {
						$('#domain_template_name').slideUp();
					}
				});
				if ($('#domain_template_id').val() != '') {
					$('.zone-form > tbody > tr:not(.include-with-template, #domain_template_default)').slideUp();
				} else {
					$('.zone-form > tbody > tr:not(.include-with-template, #domain_template_default)').show('slow');
				}
				if ($('#domain_clone_domain_id').val() != '') {
					$('.zone-form > tbody > tr#define_soa').slideUp();
					$('.zone-form > tbody > tr#create_template').slideUp();
				} else {
					if($('#domain_template_id').val() == '') {
						$('.zone-form > tbody > tr#define_soa').show('slow');
						$('.zone-form > tbody > tr#create_template').show('slow');
					}
				}
			});
		</script>
HTML;

		return $return_form;
	}

	function cloneDomainsList($domain_id) {
		global $fmdb, $__FM_CONFIG;
		
		$return = null;
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $domain_id, 'domain_', 'domain_clone_domain_id', 'AND domain_template="no" ORDER BY domain_name');
		if ($fmdb->num_rows) {
			$count = $fmdb->num_rows;
			$clone_results = $fmdb->last_result;
			for ($i=0; $i<$count; $i++) {
				$clone_id = $clone_results[$i]->domain_id;
				$return[$clone_id]['clone_name'] = $clone_results[$i]->domain_name;
				$return[$clone_id]['clone_link'] = 'zone-records.php?map=' . $clone_results[$i]->domain_mapping . '&domain_id=' . $clone_id;
				
				/** Delete permitted? */
				if (currentUserCan(array('manage_zones'), $_SESSION['module'], array(0, $domain_id)) &&
					currentUserCan(array('access_specific_zones'), $_SESSION['module'], array(0, $domain_id))) {
					$return[$clone_id]['clone_edit'] = '<a class="clone_edit" name="' . $clone_results[$i]->domain_mapping . '" href="#" id="' . $clone_id . '">' . $__FM_CONFIG['icons']['edit'] . '</a>';
					$return[$clone_id]['clone_delete'] = ' ' . str_replace('__ID__', $clone_id, $__FM_CONFIG['module']['icons']['sub_delete']);
				} else {
					$return[$clone_id]['clone_delete'] = $return[$clone_id]['clone_edit'] = null;
				}
				
				/** Clone record counts */
				$query = "SELECT COUNT(*) record_count FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}records WHERE account_id={$_SESSION['user']['account_id']} AND domain_id={$clone_id} AND record_status!='deleted'";
				$fmdb->query($query);
				$return[$clone_id]['clone_count'] = $fmdb->last_result[0]->record_count;
				basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records_skipped', $clone_id, 'record_', 'domain_id');
				$return[$clone_id]['clone_count'] .= '|' . $fmdb->num_rows;
				
				/** Clone views */
				$return[$clone_id]['clone_views'] = $clone_results[$i]->domain_view;
			}
		}
		return $return;
	}
	
	function availableCloneDomains($map, $domain_id = null) {
		global $fmdb, $__FM_CONFIG;
		
		$return[0][] = '';
		$return[0][] = '';
		
		/** Domains containing clones cannot become a clone */
		if ($domain_id && $this->cloneDomainsList($domain_id)) return $return;
		
		$domain_id_sql = (!empty($domain_id)) ? "AND domain_id!=$domain_id" : null;
		
		$query = "SELECT domain_id,domain_name FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}domains WHERE domain_clone_domain_id=0 AND domain_mapping='$map' AND domain_type='master' AND domain_status='active' AND domain_template='no' $domain_id_sql ORDER BY domain_name ASC";
		$result = $fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$clone_results = $fmdb->last_result;
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$domain_names[] = $clone_results[$i]->domain_name;
			}
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$return[$i+1][] = count(array_keys($domain_names, $clone_results[$i]->domain_name)) > 1 ? $clone_results[$i]->domain_name . ' (' . $clone_results[$i]->domain_id . ')' : $clone_results[$i]->domain_name;
				$return[$i+1][] = $clone_results[$i]->domain_id;
			}
		}
		return $return;
	}
	
	function availableViews() {
		global $fmdb, $__FM_CONFIG;
		
		$return[0][] = __('All Views');
		$return[0][] = '0';
		
		$query = "SELECT view_id,view_name FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}views WHERE account_id='{$_SESSION['user']['account_id']}' AND view_status='active' ORDER BY view_name ASC";
		$result = $fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$results = $fmdb->last_result;
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$return[$i+1][] = $results[$i]->view_name;
				$return[$i+1][] = $results[$i]->view_id;
			}
		}
		return $return;
	}
	
	function buildZoneConfig($domain_id) {
		global $fmdb, $__FM_CONFIG, $fm_name;
		
		/** Check domain_id and soa */
		$parent_domain_ids = getZoneParentID($domain_id);
		if (!isset($parent_domain_ids[2])) {
			$query = "SELECT * FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}domains d, fm_{$__FM_CONFIG['fmDNS']['prefix']}soa s WHERE domain_status='active' AND d.account_id='{$_SESSION['user']['account_id']}' AND s.soa_id=d.soa_id AND d.domain_id IN (" . join(',', $parent_domain_ids) . ")";
		} else {
			$query = "SELECT * FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}domains d, fm_{$__FM_CONFIG['fmDNS']['prefix']}soa s WHERE domain_status='active' AND d.account_id='{$_SESSION['user']['account_id']}' AND
				s.soa_id=(SELECT soa_id FROM fm_dns_domains WHERE domain_id={$parent_domain_ids[2]})";
		}
		$result = $fmdb->query($query);
		if (!$fmdb->num_rows) return sprintf('<p class="error">%s</p>'. "\n", __('Failed: There was no SOA record found for this zone.'));

		$domain_details = $fmdb->last_result;
		extract(get_object_vars($domain_details[0]), EXTR_SKIP);
		
		$name_servers = $this->getNameServers($domain_name_servers, array('masters'));
		
		/** No name servers so return */
		if (!$name_servers) return sprintf('<p class="error">%s</p>'. "\n", __('There are no DNS servers hosting this zone.'));
		
		/** Loop through name servers */
		$name_server_count = $fmdb->num_rows;
		$response = '<textarea rows="12" cols="85">';
		$failures = false;
		for ($i=0; $i<$name_server_count; $i++) {
			switch($name_servers[$i]->server_update_method) {
				case 'cron':
					/** Add records to fm_{$__FM_CONFIG['fmDNS']['prefix']}track_reloads */
					foreach ($this->getZoneCloneChildren($domain_id) as $child_id) {
						$this->addZoneReload($name_servers[$i]->server_serial_no, $child_id);
					}
					
					/** Set the server_update_config flag */
					setBuildUpdateConfigFlag($name_servers[$i]->server_serial_no, 'yes', 'update');
					
					$response .= '[' . $name_servers[$i]->server_name . '] ' . __('This zone will be updated on the next cron run.') . "\n";
					break;
				case 'http':
				case 'https':
					/** Test the port first */
					if (!socketTest($name_servers[$i]->server_name, $name_servers[$i]->server_update_port, 10)) {
						$response .= '[' . $name_servers[$i]->server_name . '] ' . sprintf(__('Failed: could not access %s (tcp/%d).'), $name_servers[$i]->server_update_method, $name_servers[$i]->server_update_port) . "\n";
						$failures = true;
						break;
					}
					
					/** Remote URL to use */
					$url = $name_servers[$i]->server_update_method . '://' . $name_servers[$i]->server_name . ':' . $name_servers[$i]->server_update_port . '/' . $_SESSION['module'] . '/reload.php';
					
					/** Data to post to $url */
					$post_data = array('action'=>'reload', 'serial_no'=>$name_servers[$i]->server_serial_no, 'domain_id'=>$domain_id);
					
					$post_result = unserialize(getPostData($url, $post_data));
					
					if (!is_array($post_result)) {
						/** Something went wrong */
						return '<div class="error"><p>' . $post_result . '</p></div>'. "\n";
					} else {
						if (count($post_result) > 1) {
							/** Loop through and format the output */
							foreach ($post_result as $line) {
								$response .= '[' . $name_servers[$i]->server_name . "] $line\n";
								if (strpos(strtolower($line), 'fail')) $failures = true;
							}
						} else {
							$response .= "[{$name_servers[$i]->server_name}] " . $post_result[0] . "\n";
							if (strpos(strtolower($post_result[0]), 'fail')) $failures = true;
						}
					}
					/** Set the server_update_config flag */
					setBuildUpdateConfigFlag($name_servers[$i]->server_serial_no, 'yes', 'update');
					
					break;
				case 'ssh':
					/** Test the port first */
					if (!socketTest($name_servers[$i]->server_name, $name_servers[$i]->server_update_port, 10)) {
						$response .= '[' . $name_servers[$i]->server_name . '] ' . sprintf(__('Failed: could not access %s (tcp/%d).'), $name_servers[$i]->server_update_method, $name_servers[$i]->server_update_port) . "\n";
						$failures = true;
						break;
					}
					
					/** Get SSH key */
					$ssh_key = getOption('ssh_key_priv', $_SESSION['user']['account_id']);
					if (!$ssh_key) {
						return '<p class="error">' . sprintf(__('Failed: SSH key is not <a href="%s">defined</a>.'), getMenuURL(_('Settings'))) . '</p>'. "\n";
					}
					
					$temp_ssh_key = sys_get_temp_dir() . '/fm_id_rsa';
					if (file_exists($temp_ssh_key)) @unlink($temp_ssh_key);
					if (@file_put_contents($temp_ssh_key, $ssh_key) === false) {
						return '<p class="error">' . sprintf(__('Failed: could not load SSH key into %s.'), $temp_ssh_key) . '</p>'. "\n";
					}
					
					@chmod($temp_ssh_key, 0400);
					
					$ssh_user = getOption('ssh_user', $_SESSION['user']['account_id']);
					if (!$ssh_user) {
						return '<p class="error">' . sprintf(__('Failed: SSH user is not <a href="%s">defined</a>.'), getMenuURL(_('Settings'))) . '</p>'. "\n";
					}
		
					exec(findProgram('ssh') . " -t -i $temp_ssh_key -o 'StrictHostKeyChecking no' -p {$name_servers[$i]->server_update_port} -l $ssh_user {$name_servers[$i]->server_name} 'sudo php /usr/local/$fm_name/{$_SESSION['module']}/dns.php zones id=$domain_id'", $post_result, $retval);
					
					@unlink($temp_ssh_key);
					
					if (!is_array($post_result)) {
						/** Something went wrong */
						return sprintf('<p class="error">%s</p>'. "\n", $post_result);
					} else {
						if (!count($post_result)) $post_result[] = __('Zone reload was successful.');
						
						if (count($post_result) > 1) {
							/** Loop through and format the output */
							foreach ($post_result as $line) {
								$response .= '[' . $name_servers[$i]->server_name . "] $line\n";
								if (strpos(strtolower($line), 'fail')) $failures = true;
							}
						} else {
							$response .= "[{$name_servers[$i]->server_name}] " . $post_result[0] . "\n";
							if (strpos(strtolower($post_result[0]), 'fail')) $failures = true;
						}
					}
					/** Set the server_update_config flag */
					setBuildUpdateConfigFlag($name_servers[$i]->server_serial_no, 'yes', 'update');
					
					break;
			}
		}
		$response .= "</textarea>\n";
		
		/** Reset the domain_reload flag */
		if (!$failures) {
			global $fm_dns_records;
			if (!isset($fm_dns_records)) include(ABSPATH . 'fm-modules/fmDNS/classes/class_records.php');
			$fm_dns_records->updateSOAReload($domain_id, 'no');
		}

		addLogEntry(sprintf(__("Reloaded zone '%s'."), displayFriendlyDomainName($domain_name)));
		return $response;
	}

	function getNameServers($domain_name_servers, $server_types = array('masters', 'slaves')) {
		global $fmdb, $__FM_CONFIG;
		
		/** Check domain_name_servers */
		if ($domain_name_servers) {
			$name_servers = explode(';', rtrim($domain_name_servers, ';'));
			$sql_name_servers = 'AND `server_id` IN (';
			foreach($name_servers as $server) {
				if ($server[0] == 's') $server = str_replace('s_', '', $server);
				
				/** Process server groups */
				if ($server[0] == 'g') {
					foreach ($server_types as $type) {
						$group_servers = getNameFromID(preg_replace('/\D/', null, $server), 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'server_groups', 'group_', 'group_id', 'group_' . $type);

						foreach (explode(';', $group_servers) as $server_id) {
							if (!empty($server_id)) $sql_name_servers .= "'$server_id',";
						}
					}
				} else {
					if (!empty($server)) $sql_name_servers .= "'$server',";
				}
			}
			$sql_name_servers = rtrim($sql_name_servers, ',') . ')';
		} else $sql_name_servers = null;
		
		$query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` WHERE `server_status`='active' AND account_id='{$_SESSION['user']['account_id']}' $sql_name_servers ORDER BY `server_update_method`";
		$result = $fmdb->query($query);
		
		/** No name servers so return */
		if (!$fmdb->num_rows) return false;
		
		return $fmdb->last_result;
	}
	
	
	function addZoneReload($server_serial_no, $domain_id) {
		global $fmdb, $__FM_CONFIG;
		
		$query = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}track_reloads` VALUES($domain_id, $server_serial_no)";
		$result = $fmdb->query($query);
	}
	
	
	function updateSOASerialNo($domain_id, $soa_serial_no) {
		global $fmdb, $__FM_CONFIG;
		
		$current_date = date('Ymd');

		/** Ensure soa_serial_no is an integer */
		$soa_serial_no = (int) $soa_serial_no;
		
		/** Increment serial */
		$soa_serial_no = (strpos($soa_serial_no, $current_date) === false) ? $current_date . '00' : $soa_serial_no + 1;
		
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` SET `soa_serial_no`=$soa_serial_no WHERE `domain_id`=$domain_id";
		$result = $fmdb->query($query);
	}
	
	function availableZones($include_clones = false, $zone_type = null, $restricted = false) {
		global $fmdb, $__FM_CONFIG;
		
		/** Get restricted zones only */
		$restricted_sql = null;
		if ($restricted && !currentUserCan('do_everything')) {
			$user_capabilities = getUserCapabilities($_SESSION['user']['id']);
			if (array_key_exists('access_specific_zones', $user_capabilities[$_SESSION['module']])) {
				if (!in_array(0, $user_capabilities[$_SESSION['module']]['access_specific_zones'])) {
					$restricted_sql = "AND domain_id IN ('" . implode("','", $user_capabilities[$_SESSION['module']]['access_specific_zones']) . "')";
				}
			}
		}
		
		$include_clones_sql = $include_clones ? null : "AND domain_clone_domain_id=0";
		if ($zone_type) {
			if (is_array($zone_type)) {
				$zone_type_sql = "AND domain_type IN ('" . implode("','", $zone_type) . "')";
			} else {
				$zone_type_sql = "AND domain_type='$zone_type'";
			}
		} else {
			$zone_type_sql = null;
		}
		
		$query = "SELECT domain_id,domain_name FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}domains WHERE account_id='{$_SESSION['user']['account_id']}' AND domain_status!='deleted' $include_clones_sql $zone_type_sql $restricted_sql ORDER BY domain_name ASC";
		$result = $fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$results = $fmdb->last_result;
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$domain_names[] = $results[$i]->domain_name;
			}
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$return[$i][] = count(array_keys($domain_names, $results[$i]->domain_name)) > 1 ? $results[$i]->domain_name . ' (' . $results[$i]->domain_id . ')' : $results[$i]->domain_name;
				$return[$i][] = $results[$i]->domain_id;
			}
		}
		return $return;
	}
	
	function validateDomainName($domain_name, $domain_mapping) {
		if (substr($domain_name, -5) == '.arpa') {
			/** .arpa is only for reverse zones */
			if ($domain_mapping == 'forward') return false;
			
			$domain_pieces = explode('.', $domain_name);
			$domain_parts = count($domain_pieces);
			
			/** IPv4 checks */
			if ($domain_pieces[$domain_parts - 2] == 'in-addr') {
				/** The first digit of a reverse zone must be numeric */
				if (!is_numeric(substr($domain_name, 0, 1))) return false;
				
				/** Reverse zones with arpa must have at least three octets */
				if ($domain_parts < 3) return false;
				
				/** Second to last octet must be valid for arpa */
				if (!in_array($domain_pieces[$domain_parts - 2], array('e164', 'in-addr-servers', 'in-addr', 'ip6-servers', 'ip6', 'iris', 'uri', 'urn'))) return false;
				
				for ($i=0; $i<$domain_parts - 2; $i++) {
					/** Check if using classless */
					if ($i == 0) {
						if (preg_match("/^(\d{1,3})\-(\d{1,3})$/", $domain_pieces[$i])) {
							/** Validate octet range */
							$octet_range = explode('-', $domain_pieces[$i]);
							
							if ($octet_range[0] >= $octet_range[1]) return false;
							
							foreach ($octet_range as $octet) {
								if (filter_var($octet, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0, 'max_range' => 255))) === false) return false;
							}
							continue;
						}
					}
					
					/** Remaining octects must be numeric */
					if (filter_var($domain_pieces[$i], FILTER_VALIDATE_INT, array('options' => array('min_range' => 0, 'max_range' => 255))) === false) return false;
				}
			/** IPv6 checks */
			} elseif ($domain_pieces[$domain_parts - 2] == 'ip6') {
				return true;
				return verifyIPAddress(buildFullIPAddress(0, $domain_name));
			}
		} elseif ($domain_mapping == 'reverse') {
			/** If reverse zone does not contain arpa then it must only contain numbers, periods, letters, and colons */
			$domain_pieces = explode('.', $domain_name);
			
			/** IPv4 checks */
			if (strpos($domain_name, ':') === false) {
				foreach ($domain_pieces as $number) {
					if (filter_var($number, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0, 'max_range' => 255))) === false) return false;
				}
			/** IPv6 checks */
			} elseif (!preg_match('/^[a-z\d\:]+$/i', $domain_name)) {
				return false;
			}
		} else {
			/** Forward zones should only contain letters, numbers, periods, and hyphens */
			return (preg_match("/^(_*[a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $domain_name) // valid chars check
					&& preg_match("/^.{1,253}$/", $domain_name) // overall length check
					&& preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $domain_name)   ); // length of each label
		}
		
		return true;
	}
	
	function setReverseZoneName($domain_name) {
		if (substr($domain_name, -5) == '.arpa') {
			return $domain_name;
		}
		
		/** IPv4 */
		if (strpos($domain_name, ':') === false) {
			$domain_pieces = array_reverse(explode('.', $domain_name));
			$domain_parts = count($domain_pieces);
			
			$reverse_ips = null;
			for ($i=0; $i<$domain_parts; $i++) {
				$reverse_ips .= $domain_pieces[$i] . '.';
			}
			
			return $reverse_ips .= 'in-addr.arpa';
		/** IPv6 */
		} elseif (strpos($domain_name, ':')) {
			$addr = inet_pton($domain_name);
			$unpack = unpack('H*hex', $addr);
			$hex = $unpack['hex'];
			$arpa = implode('.', array_reverse(str_split($hex))) . '.ip6.arpa';
			
			return $arpa;
		}
	}
	
	function fixDomainTypos($domain_name) {
		$typo = array('in-adr');
		$fixed = array('in-addr');
		
		return str_replace($typo, $fixed, $domain_name);
	}
	
	/**
	 * Process bulk zone reloads
	 *
	 * @since 1.2
	 * @package facileManager
	 */
	function doBulkZoneReload($domain_id) {
		global $fmdb, $__FM_CONFIG;
		
		/** Check serial number */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', sanitize($domain_id), 'domain_', 'domain_id');
		if (!$fmdb->num_rows) return sprintf(__('%s is not a valid zone ID.'), $domain_id);

		$server_details = $fmdb->last_result;
		extract(get_object_vars($server_details[0]), EXTR_SKIP);
		
		$response[] = displayFriendlyDomainName($domain_name);
		
		/** Ensure domain is reloadable */
		if ($domain_reload != 'yes') {
			$response[] = ' --> ' . __('Failed: Zone is not available for reload.');
		}
		
		/** Ensure domain is master */
		if (count($response) == 1 && $domain_type != 'master') {
			$response[] = ' --> ' . __('Failed: Zone is not a master zone.');
		}
		
		/** Ensure user is allowed to reload zone */
		$zone_access_allowed = zoneAccessIsAllowed(array($domain_id), 'reload');
		
		if (count($response) == 1 && !$zone_access_allowed) {
			$response[] = ' --> ' . __('Failed: You do not have permission to reload this zone.');
		}
		
		/** Format output */
		if (count($response) == 1) {
			foreach (makePlainText($this->buildZoneConfig($domain_id), true) as $line) {
				$response[] = ' --> ' . $line;
			}
		}
		
		$response[] = "\n";
		
		return implode("\n", $response);
	}


	function validatePost($post) {
		global $fmdb, $__FM_CONFIG;
		
		if (!$post['domain_id']) unset($post['domain_id']);
		
		/** Empty domain names are not allowed */
		if (empty($post['domain_name'])) return __('No zone name defined.');
		
		if ($post['domain_template'] != 'yes') {
			$post['domain_name'] = rtrim(strtolower($post['domain_name']), '.');

			/** Perform domain name validation */
			if (!isset($post['domain_mapping'])) {
				global $map;
				$post['domain_mapping'] = $map;
			}
			if ($post['domain_mapping'] == 'reverse') {
				$post['domain_name'] = $this->fixDomainTypos($post['domain_name']);
			} else {
				$post['domain_name'] = function_exists('idn_to_ascii') ? idn_to_ascii($post['domain_name']) : $post['domain_name'];
			}
			if (!$this->validateDomainName($post['domain_name'], $post['domain_mapping'])) return __('Invalid zone name.');
		}
		
		/** Is this based on a template? */
		if ($post['domain_template_id']) {
			$include = array('action', 'domain_template_id' , 'domain_name', 'domain_template', 'domain_mapping');
			foreach ($include as $key) {
				$new_post[$key] = $post[$key];
			}
			$post = $new_post;
			unset($new_post, $post['domain_template']);
			$post['domain_type'] = getNameFromID($post['domain_template_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_type');

			return $post;
		} else {
			$post['domain_template_id'] = 0;
		}
		
		/** Format domain_clone_domain_id */
		if (!$post['domain_clone_domain_id'] && $post['action'] == 'add') $post['domain_clone_domain_id'] = 0;
		
		/** domain_clone_dname override */
		if (!$post['domain_clone_dname_override']) {
			$post['domain_clone_dname'] = null;
		} else {
			unset($post['domain_clone_dname_override']);
		}
		
		/** Ensure domain_view is set */
		if (!array_key_exists('domain_view', $post)) {
			$post['domain_view'] = ($post['domain_clone_domain_id']) ? -1 : 0;
		}

		/** Reverse zones should have form of x.x.x.in-addr.arpa */
		if ($post['domain_mapping'] == 'reverse') {
			$post['domain_name'] = $this->setReverseZoneName($post['domain_name']);
		}
		
		/** Does the record already exist for this account? */
		$domain_id_sql = (isset($post['domain_id'])) ? 'AND domain_id!=' . sanitize($post['domain_id']) : null;
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'views', $_SESSION['user']['account_id'], 'view_', 'account_id');
		if (!$fmdb->num_rows) { /** No views defined - all zones must be unique */
			basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', sanitize($post['domain_name']), 'domain_', 'domain_name', $domain_id_sql);
			if ($fmdb->num_rows) return __('Zone already exists.');
		} else { /** All zones must be unique per view */
			$defined_views = $fmdb->last_result;
			
			/** Format domain_view */
			if (!$post['domain_view'] || in_array(0, $post['domain_view'])) {
				basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', sanitize($post['domain_name']), 'domain_', 'domain_name', $domain_id_sql);
				if ($fmdb->num_rows) {
					/** Zone exists for views, but what about on the same server? */
					if (!$post['domain_name_servers'] || in_array('0', $post['domain_name_servers'])) {
						return __('Zone already exists for all views.');
					}
				}
			}
			if (is_array($post['domain_view'])) {
				$domain_view = null;
				foreach ($post['domain_view'] as $val) {
					if ($val == 0 || $val == '') {
						$domain_view = 0;
						break;
					}
					$domain_view .= $val . ';';
					basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', sanitize($post['domain_name']), 'domain_', 'domain_name', "AND (domain_view='$val' OR domain_view=0 OR domain_view LIKE '$val;%' OR domain_view LIKE '%;$val;%' OR domain_view LIKE '%;$val') $domain_id_sql");
					if ($fmdb->num_rows) {
						$view_name = getNameFromID($val, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'views', 'view_', 'view_id', 'view_name');
						return sprintf(__("Zone already exists for the '%s' view."), $view_name);
					}
				}
				$post['domain_view'] = rtrim($domain_view, ';');
			}
		}
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_name');
		if ($field_length !== false && strlen($post['domain_name']) > $field_length) return sprintf(ngettext('Zone name is too long (maximum %d character).', 'Zone name is too long (maximum %d characters).', 1), $field_length);
		
		/** No need to process more if zone is cloned */
		if ($post['domain_clone_domain_id']) {
			return $post;
		}
		
		/** Cleans up acl_addresses for future parsing **/
		$clean_fields = array('forwarders', 'masters');
		foreach ($clean_fields as $val) {
			$post['domain_required_servers'][$val] = verifyAndCleanAddresses($post['domain_required_servers'][$val], 'no-subnets-allowed');
			if (strpos($post['domain_required_servers'][$val], 'not valid') !== false) return $post['domain_required_servers'][$val];
		}

		/** Forward zones require forward servers */
		if ($post['domain_type'] == 'forward') {
			if (empty($post['domain_required_servers']['forwarders'])) return __('No forward servers defined.');
			$post['domain_required_servers'] = $post['domain_required_servers']['forwarders'];
		}

		/** Slave and stub zones require master servers */
		if (in_array($post['domain_type'], array('slave', 'stub'))) {
			if (empty($post['domain_required_servers']['masters'])) return __('No master servers defined.');
			$post['domain_required_servers'] = $post['domain_required_servers']['masters'];
		}

		return $post;
	}
	
	/**
	 * Converts view IDs to their respective names
	 *
	 * @since 2.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param id $ids IDs to convert to names
	 * @param id $type ID type to process
	 * @return string
	 */
	function IDs2Name ($ids, $type) {
		global $__FM_CONFIG;
		
		if ($ids) {
			if ($ids == -1) return sprintf('<i>%s</i>', __('inherited'));
			
			$table = $type;
			
			/** Process multiple IDs */
			if (strpos($ids, ';')) {
				$ids_array = explode(';', rtrim($ids, ';'));
				if (in_array('0', $ids_array)) $name = 'All ' . ucfirst($type) . 's';
				else {
					$name = null;
					foreach ($ids_array as $id) {
						if ($id[0] == 'g') {
							$table = 'server_group';
							$type = 'group';
						}
						$name .= getNameFromID(preg_replace('/\D/', null, $id), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . $table . 's', $type . '_', $type . '_id', $type . '_name') . ', ';
					}
					$name = rtrim($name, ', ');
				}
			} else {
				if ($ids[0] == 'g') {
					$table = 'server_group';
					$type = 'group';
				}
				$name = getNameFromID(preg_replace('/\D/', null, $ids), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . $table . 's', $type . '_', $type . '_id', $type . '_name');
			}
		} else $name = 'All ' . ucfirst($type) . 's';
		
		return $name;
	}
	
	/**
	 * Builds the zone listing JSON
	 *
	 * @since 2.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param id $ids IDs to convert to names
	 * @return json array
	 */
	function buildZoneJSON($saved_zones) {
		$temp_zones = $this->availableZones(true, array('master', 'slave', 'forward'));
		$available_zones = array(array('id' => 0, 'text' => 'All Zones'));
		$i = 1;
		foreach ($temp_zones as $temp_zone_array) {
			$available_zones[$i]['id'] = $temp_zone_array[1];
			$available_zones[$i]['text'] = $temp_zone_array[0];
			$i++;
		}
		$available_zones = json_encode($available_zones);
		unset($temp_zone_array, $temp_zones);
		
		return $available_zones;
	}
	
	/**
	 * Builds the zone listing filter menu
	 *
	 * @since 2.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param id $ids IDs to convert to names
	 * @return string
	 */
	function buildFilterMenu() {
		$domain_view = isset($_GET['domain_view']) ? $_GET['domain_view'] : 0;
		
		$available_views = $this->availableViews();
		if (count($available_views) > 1) {
			return '<form method="GET">' . buildSelect('domain_view', 'domain_view', $available_views, $domain_view, 1, null, true, null, null, __('Filter Views')) .
			'&nbsp;<input type="submit" name="" id="" value="' . __('Filter') . '" class="button" /></form>' . "\n";
		}
		return null;
	}
	
	
	/**
	 * Returns the default zone ID
	 *
	 * @since 2.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @return integer
	 */
	function getDefaultZone() {
		global $fmdb, $__FM_CONFIG;
		
		$query = "SELECT domain_id FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domains WHERE account_id='{$_SESSION['user']['account_id']}' 
			AND domain_status='active' AND domain_default='yes' LIMIT 1";
		$result = $fmdb->get_results($query);
		if ($fmdb->num_rows) {
			return $fmdb->last_result[0]->domain_id;
		}
		return false;
	}
	
	
	/**
	 * Builds an array of available zone templates
	 *
	 * @since 2.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @return array
	 */
	function availableZoneTemplates() {
		global $fmdb, $__FM_CONFIG;
		
		$return[0][] = null;
		$return[0][] = null;
		
		$query = "SELECT domain_id,domain_name FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}domains WHERE account_id='{$_SESSION['user']['account_id']}' 
			AND domain_status='active' AND domain_template='yes' ORDER BY domain_name ASC";
		$result = $fmdb->get_results($query);
		if ($fmdb->num_rows) {
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$return[$i+1][] = $fmdb->last_result[$i]->domain_name;
				$return[$i+1][] = $fmdb->last_result[$i]->domain_id;
			}
		}
		return $return;
	}
	
	
	/**
	 * Builds an array of available zone templates
	 *
	 * @since 2.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param int $domain_id Domain ID to get children
	 * @return array
	 */
	function getZoneTemplateChildren($domain_id) {
		global $fmdb, $__FM_CONFIG;
		
		if (getNameFromID($domain_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_template') == 'yes') {
			$children = array();
			basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_id', 'domain_', 'AND domain_template_id=' . $domain_id);
			if ($fmdb->num_rows) {
				for ($x=0; $x<$fmdb->num_rows; $x++) {
					$children[] = $fmdb->last_result[$x]->domain_id;
				}
			}
			return $children;
		}
		
		return array($domain_id);
	}
	
	
	/**
	 * Builds an array of available zone children
	 *
	 * @since 2.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param int $domain_id Domain ID to get children
	 * @return array
	 */
	function getZoneCloneChildren($domain_id) {
		global $fmdb, $__FM_CONFIG;
		
		$children = array($domain_id);
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_id', 'domain_', 'AND domain_clone_domain_id=' . $domain_id);
		if ($fmdb->num_rows) {
			for ($x=0; $x<$fmdb->num_rows; $x++) {
				$children[] = $fmdb->last_result[$x]->domain_id;
			}
		}
		return $children;
	}
	
	
}

if (!isset($fm_dns_zones))
	$fm_dns_zones = new fm_dns_zones();

?>
