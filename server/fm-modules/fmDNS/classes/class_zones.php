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
	function rows($result, $map, $reload_allowed) {
		global $fmdb, $__FM_CONFIG, $allowed_to_reload_zones;
		
		if ($allowed_to_reload_zones) {
			$bulk_actions_list = array('Reload');
			$checkbox[] = array(
								'title' => '<input type="checkbox" onClick="toggle(this, \'domain_list[]\')" />',
								'class' => 'header-tiny'
							);
		} else {
			$checkbox = $bulk_actions_list = null;
		}

		if (!$result) {
			echo '<p id="table_edits" class="noresult" name="domains">There are no zones.</p>';
		} else {
			$num_rows = $fmdb->num_rows;
			$results = $fmdb->last_result;
			echo @buildBulkActionMenu($bulk_actions_list, 'server_id_list');
			
			$table_info = array(
							'class' => 'display_results',
							'id' => 'table_edits',
							'name' => 'domains'
						);

			$title_array = array(array('title' => 'ID', 'class' => 'header-small'), 'Domain', 'Type', 'Clones', 'Views');
			$title_array[] = array('title' => 'Actions', 'class' => 'header-actions');
			
			if (is_array($checkbox)) {
				$title_array = array_merge($checkbox, $title_array);
			}

			echo displayTableHeader($table_info, $title_array, 'zones');
			
			for ($x=0; $x<$num_rows; $x++) {
				$this->displayRow($results[$x], $map, $reload_allowed);
			}
			
			echo "</tbody>\n</table>\n";
		}
	}

	/**
	 * Adds the new zone
	 */
	function add() {
		global $fmdb, $__FM_CONFIG;
		
		$id = $_POST['createZone']['ZoneID'];
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains`";
		$sql_fields = '(';
		$sql_values = $domain_name_servers = $domain_views = null;
		
		$log_message = "Added a zone with the following details:\n";

		/** Empty domain names are not allowed */
		if (empty($_POST['createZone'][$id]['domain_name'])) return 'No zone name defined.';
		
		$_POST['createZone'][$id]['domain_name'] = rtrim(strtolower($_POST['createZone'][$id]['domain_name']), '.');
		
		/** Perform domain name validation */
		if ($_POST['createZone'][$id]['domain_mapping'] == 'reverse') $_POST['createZone'][$id]['domain_name'] = $this->fixDomainTypos($_POST['createZone'][$id]['domain_name']);
		if (!$this->validateDomainName($_POST['createZone'][$id]['domain_name'], $_POST['createZone'][$id]['domain_mapping'])) return 'Invalid zone name.';
		
		/** Ensure domain_view is set */
		if (!array_key_exists('domain_view', $_POST['createZone'][$id])) $_POST['createZone'][$id]['domain_view'] = 0;

		/** Reverse zones should have form of x.x.x.in-addr.arpa */
		if ($_POST['createZone'][$id]['domain_mapping'] == 'reverse') {
			$_POST['createZone'][$id]['domain_name'] = $this->setReverseZoneName($_POST['createZone'][$id]['domain_name']);
		}
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', $_SESSION['user']['account_id'], 'view_', 'account_id');
		if (!$fmdb->num_rows) { /** No views defined - all zones must be unique */
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', sanitize($_POST['createZone'][$id]['domain_name']), 'domain_', 'domain_name');
			if ($fmdb->num_rows) return 'Zone already exists.';
		} else { /** All zones must be unique per view */
			$defined_views = $fmdb->last_result;
			
			/** Format domain_view */
			if (!$_POST['createZone'][$id]['domain_view'] || in_array(0, $_POST['createZone'][$id]['domain_view'])) {
				basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', sanitize($_POST['createZone'][$id]['domain_name']), 'domain_', 'domain_name');
				if ($fmdb->num_rows) return 'Zone already exists for all views.';
			}
			if (is_array($_POST['createZone'][$id]['domain_view'])) {
				$domain_view = null;
				foreach ($_POST['createZone'][$id]['domain_view'] as $val) {
					if ($val == 0 || $val == '') {
						$domain_view = 0;
						break;
					}
					$domain_view .= $val . ';';
					basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', sanitize($_POST['createZone'][$id]['domain_name']), 'domain_', 'domain_name', "AND (domain_view='$val' OR domain_view=0 OR domain_view LIKE '$val;%' OR domain_view LIKE '%;$val;%' OR domain_view LIKE '%;$val')");
					if ($fmdb->num_rows) {
						$view_name = getNameFromID($val, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name');
						return "Zone already exists for the '$view_name' view.";
					}
				}
				$_POST['createZone'][$id]['domain_view'] = rtrim($domain_view, ';');
			}
		}
		
		/** Format domain_clone_domain_id */
		if (!$_POST['createZone'][$id]['domain_clone_domain_id']) $_POST['createZone'][$id]['domain_clone_domain_id'] = 0;
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_name');
		if ($field_length !== false && strlen($_POST['createZone'][$id]['domain_name']) > $field_length) return 'Zone name is too long (maximum ' . $field_length . ' characters).';
		
		/** Get clone parent values */
		if ($_POST['createZone'][$id]['domain_clone_domain_id']) {
			$query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE domain_id={$_POST['createZone'][$id]['domain_clone_domain_id']}";
			$fmdb->query($query);
			if (!$fmdb->num_rows) return 'Cannot find cloned zone.';
			
			$parent_domain = $fmdb->last_result;
			foreach ($parent_domain[0] as $field => $value) {
				if ($field == 'domain_id') continue;
				if ($field == 'domain_clone_domain_id') {
					$sql_values .= sanitize($_POST['createZone'][$id]['domain_clone_domain_id']) . ',';
				} elseif ($field == 'domain_name') {
					$sql_values .= "'" . sanitize($_POST['createZone'][$id]['domain_name']) . "',";
				} elseif ($field == 'domain_reload') {
					$sql_values .= "'no',";
				} else {
					$sql_values .= strlen(sanitize($value)) ? "'" . sanitize($value) . "'," : 'NULL,';
				}
				$sql_fields .= $field . ',';
			}
			$sql_fields = rtrim($sql_fields, ',') . ')';
			$sql_values = rtrim($sql_values, ',');
			$query = "$sql_insert $sql_fields VALUES ($sql_values)";
			$result = $fmdb->query($query);
		} else {
			/** Cleans up acl_addresses for future parsing **/
			$clean_fields = array('domain_transfers_from', 'domain_updates_from', 'domain_master_servers', 'domain_forward_servers');
			foreach ($clean_fields as $val) {
				$_POST['createZone'][$id][$val] = verifyAndCleanAddresses($_POST['createZone'][$id][$val], true);
				if ($_POST['createZone'][$id][$val] === false) return 'Invalid address(es) specified';
			}
			
			/** Forward zones require forward servers */
			if ($_POST['createZone'][$id]['domain_type'] == 'forward' && empty($_POST['createZone'][$id]['domain_forward_servers'])) return 'No forward servers defined.';
			
			/** Slave zones require master servers */
			if (in_array($_POST['createZone'][$id]['domain_type'], array('slave', 'stub')) && empty($_POST['createZone'][$id]['domain_master_servers'])) return 'No master servers defined.';
			
			/** Format domain_view */
			$log_message_views = null;
			if (is_array($_POST['createZone'][$id]['domain_view'])) {
				$domain_view = null;
				foreach ($_POST['createZone'][$id]['domain_view'] as $val) {
					if ($val == 0 || $val == '') {
						$domain_view = 0;
						break;
					}
					$domain_view .= $val . ';';
					$view_name = getNameFromID($val, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name');
					$log_message_views .= $val ? "$view_name; " : null;
				}
				$_POST['createZone'][$id]['domain_view'] = rtrim($domain_view, ';');
			}
			if (!$_POST['createZone'][$id]['domain_view']) $_POST['createZone'][$id]['domain_view'] = 0;
			
			/** Format domain_name_servers */
			$log_message_name_servers = null;
			foreach ($_POST['createZone'][$id]['domain_name_servers'] as $val) {
				if ($val == 0) {
					$domain_name_servers = 0;
					break;
				}
				$domain_name_servers .= $val . ';';
				$server_name = getNameFromID($val, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
				$log_message_name_servers .= $val ? "$server_name; " : null;
			}
			$_POST['createZone'][$id]['domain_name_servers'] = rtrim($domain_name_servers, ';');
			if (!$_POST['createZone'][$id]['domain_name_servers']) $_POST['createZone'][$id]['domain_name_servers'] = 0;
			
			foreach ($_POST['createZone'][$id] as $key => $data) {
				$sql_fields .= $key . ',';
				$sql_values .= strlen(sanitize($data)) ? "'" . sanitize($data) . "'," : 'NULL,';
				if ($key == 'domain_view') $data = $log_message_views;
				if ($key == 'domain_name_servers') $data = $log_message_name_servers;
				$log_message .= $data ? ucwords(str_replace('_', ' ', str_replace('domain_', '', $key))) . ": $data\n" : null;
			}
			$sql_fields .= 'account_id)';
			$sql_values .= "'{$_SESSION['user']['account_id']}'";
			
			$query = "$sql_insert $sql_fields VALUES ($sql_values)";
			$result = $fmdb->query($query);
		}
		
		if (!$fmdb->result) return 'Could not add zone because a database error occurred.';

		$insert_id = $fmdb->insert_id;
		
		addLogEntry($log_message);
		return $insert_id;
	}

	/**
	 * Updates the selected zone
	 */
	function update() {
		global $fmdb, $__FM_CONFIG;
		
		$id = $_POST['editZone']['ZoneID'];
		$sql_edit = $domain_name_servers = $domain_view = null;
		
		$old_name = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name');
		$log_message = "Updated a zone ($old_name) with the following details:\n";

		/** Empty domain names are not allowed */
		if (empty($_POST['editZone'][$id]['domain_name'])) return 'No zone name defined.';
		
		/** Perform domain name validation */
		if ($_POST['editZone'][$id]['domain_mapping'] == 'reverse') $_POST['editZone'][$id]['domain_name'] = $this->fixDomainTypos($_POST['editZone'][$id]['domain_name']);
		if (!$this->validateDomainName($_POST['editZone'][$id]['domain_name'], $_POST['editZone'][$id]['domain_mapping'])) return 'Invalid zone name.';
		
		/** Reverse zones should have form of x.x.x.in-addr.arpa */
		if ($_POST['editZone'][$id]['domain_mapping'] == 'reverse') {
			$_POST['editZone'][$id]['domain_name'] = $this->setReverseZoneName($_POST['editZone'][$id]['domain_name']);
		}
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_name');
		if ($field_length !== false && strlen($_POST['editZone'][$id]['domain_name']) > $field_length) return 'Zone name is too long (maximum' . $field_length . ' characters).';
		
		/** Ensure domain_view is set */
		if (!array_key_exists('domain_view', $_POST['editZone'][$id])) $_POST['editZone'][$id]['domain_view'] = 0;

		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', $_SESSION['user']['account_id'], 'view_', 'account_id');
		if (!$fmdb->num_rows) { /** No views defined - all zones must be unique */
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', sanitize($_POST['editZone'][$id]['domain_name']), 'domain_', 'domain_name');
			if ($fmdb->num_rows) return 'Zone already exists.';
		} else { /** All zones must be unique per view */
			$defined_views = $fmdb->last_result;
			
			/** Format domain_view */
			if (!array_key_exists('domain_view', $_POST['editZone'][$id])) $_POST['editZone'][$id]['domain_view'] = 0;
			if (!$_POST['editZone'][$id]['domain_view'] || in_array(0, $_POST['editZone'][$id]['domain_view'])) {
				basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', sanitize($_POST['editZone'][$id]['domain_name']), 'domain_', 'domain_name', 'AND domain_id!=' . $id);
				if ($fmdb->num_rows) return 'Zone already exists for all views.';
			}
			if (is_array($_POST['editZone'][$id]['domain_view'])) {
				$domain_view = null;
				foreach ($_POST['editZone'][$id]['domain_view'] as $val) {
					if ($val == 0 || $val == '') {
						$domain_view = 0;
						break;
					}
					$domain_view .= $val . ';';
					basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', sanitize($_POST['editZone'][$id]['domain_name']), 'domain_', 'domain_name', "AND (domain_view='$val' OR domain_view=0 OR domain_view LIKE '$val;%' OR domain_view LIKE '%;$val;%' OR domain_view LIKE '%;$val') AND domain_id!=$id");
					if ($fmdb->num_rows) {
						$view_name = getNameFromID($val, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name');
						return "Zone already exists for the '$view_name' view.";
					}
				}
				$_POST['editZone'][$id]['domain_view'] = rtrim($domain_view, ';');
			}
		}
		
		/** If changing zone to clone or different domain_type, are there any existing associated records? */
		if ($_POST['editZone'][$id]['domain_clone_domain_id'] || $_POST['editZone'][$id]['domain_type'] != 'master') {
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $id, 'record_', 'domain_id');
			if ($fmdb->num_rows) return 'There are associated records with this zone.';
		}
		
		/** Forward zones require forward servers */
		if ($_POST['editZone'][$id]['domain_type'] == 'forward' && empty($_POST['editZone'][$id]['domain_forward_servers'])) return 'No forward servers defined.';
		
		/** Slave zones require master servers */
		if ($_POST['editZone'][$id]['domain_type'] == 'slave' && empty($_POST['editZone'][$id]['domain_master_servers'])) return 'No master servers defined.';
		
		/** Cleans up acl_addresses for future parsing **/
		$clean_fields = array('domain_transfers_from', 'domain_updates_from', 'domain_master_servers', 'domain_forward_servers');
		foreach ($clean_fields as $val) {
			$_POST['editZone'][$id][$val] = verifyAndCleanAddresses($_POST['editZone'][$id][$val], true);
			if ($_POST['editZone'][$id][$val] === false) return 'Invalid address(es) specified.';
		}
		
		/** Format domain_clone_domain_id */
		if (!$_POST['editZone'][$id]['domain_clone_domain_id']) $_POST['editZone'][$id]['domain_clone_domain_id'] = 0;
		
		/** Format domain_view */
		$log_message_views = null;
		if (is_array($_POST['editZone'][$id]['domain_view'])) {
			foreach ($_POST['editZone'][$id]['domain_view'] as $val) {
				if ($val == 0) {
					$domain_view = 0;
					break;
				}
				$domain_view .= $val . ';';
				$view_name = getNameFromID($val, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name');
				$log_message_views .= $val ? "$view_name; " : null;
			}
			$_POST['editZone'][$id]['domain_view'] = rtrim($domain_view, ';');
		}
		
		/** Format domain_name_servers */
		$log_message_name_servers = null;
		foreach ($_POST['editZone'][$id]['domain_name_servers'] as $val) {
			if ($val == 0) {
				$domain_name_servers = 0;
				break;
			}
			$domain_name_servers .= $val . ';';
			$server_name = getNameFromID($val, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
			$log_message_name_servers .= $val ? "$server_name; " : null;
		}
		$_POST['editZone'][$id]['domain_name_servers'] = rtrim($domain_name_servers, ';');
		if (!$_POST['editZone'][$id]['domain_name_servers']) $_POST['editZone'][$id]['domain_name_servers'] = 0;
		
		foreach ($_POST['editZone'][$id] as $key => $data) {
			$sql_edit .= strlen(sanitize($data)) ? $key . "='" . mysql_real_escape_string($data) . "'," : $key . '=NULL,';
			if ($key == 'domain_view') $data = $log_message_views;
			if ($key == 'domain_name_servers') $data = $log_message_name_servers;
			$log_message .= $data ? ucwords(str_replace('_', ' ', str_replace('domain_', '', $key))) . ": $data\n" : null;
		}
		$sql_edit = rtrim($sql_edit, ',');
		
		/* set the server_build_config flag for existing servers */
		if (getSOACount($id) && getNSCount($id)) {
			setBuildUpdateConfigFlag(getZoneServers($id), 'yes', 'build');
		}

		/** Update the zone */
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` SET $sql_edit WHERE `domain_id`='$id' AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		if (!$fmdb->result) return 'Could not update the zone because a database error occurred.';

		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		/* set the server_build_config flag for new servers */
		if (getSOACount($id) && getNSCount($id)) {
			setBuildUpdateConfigFlag(getZoneServers($id), 'yes', 'build');
		}

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
			/** Delete all associated records */
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $domain_id, 'record_', 'domain_id');
			if ($fmdb->num_rows) {
				if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $domain_id, 'record_', 'deleted', 'domain_id') === false) {
					return 'The associated records for this zone could not be deleted because a database error occurred.';
				}
			}
			
			/** Delete all associated SOA */
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'soa', $domain_id, 'soa_', 'domain_id');
			if ($fmdb->num_rows) {
				if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'soa', $domain_id, 'soa_', 'deleted', 'domain_id') === false) {
					return 'The SOA for this zone could not be deleted because a database error occurred.';
				}
			}
			
			/** Delete associated records from fm_{$__FM_CONFIG['fmDNS']['prefix']}track_builds */
			if (basicDelete('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'track_builds', $domain_id, 'domain_id', false) === false) {
				return 'The zone could not be removed from the fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'track_builds table because a database error occurred.';
			}
			
			/** Force buildconf for all associated DNS servers */
			setBuildUpdateConfigFlag(getZoneServers($domain_id), 'yes', 'build');
			
			/** Delete cloned zones */
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $domain_id, 'domain_', 'domain_clone_domain_id');
			if ($fmdb->num_rows) {
				if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $domain_id, 'domain_', 'deleted', 'domain_clone_domain_id') === false) {
					return 'The associated clones for this zone could not be deleted because a database error occurred.';
				}
			}
			
			/** Delete zone */
			$tmp_name = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name');
			if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $domain_id, 'domain_', 'deleted', 'domain_id') === false) {
				return 'This zone could not be deleted because a database error occurred.';
			}
			
			addLogEntry("Deleted zone '$tmp_name' and all associated records.");
			
			return true;
		}
		
		return 'This zone does not exist.';
	}
	
	
	function displayRow($row, $map, $reload_allowed) {
		global $__FM_CONFIG, $allowed_to_manage_zones, $allowed_to_reload_zones, $super_admin;
		
		$class = ($row->domain_status == 'disabled') ? 'disabled' : null;
		$response = null;
		
		$checkbox = ($allowed_to_reload_zones) ? '<td></td>' : null;
		
		$soa_count = getSOACount($row->domain_id);
		$ns_count = getNSCount($row->domain_id);
		$reload_allowed = reloadAllowed($row->domain_id);
		if (!$soa_count && $row->domain_type == 'master') {
			$response = 'The SOA record still needs to be created for this zone';
			$class = 'attention';
		}
		if (!$ns_count && $row->domain_type == 'master' && !$response) {
			$response = 'One more more NS records still needs to be created for this zone';
			$class = 'attention';
		}
		$clones = $this->cloneDomainsList($row->domain_id);
		$zone_access_allowed = true;
		
		if (isset($_SESSION['user']['module_perms']['perm_extra'])) {
			$module_extra_perms = isSerialized($_SESSION['user']['module_perms']['perm_extra']) ? unserialize($_SESSION['user']['module_perms']['perm_extra']) : $_SESSION['user']['module_perms']['perm_extra'];
			$zone_access_allowed = (is_array($module_extra_perms) && 
				!in_array(0, $module_extra_perms['zone_access']) && !$super_admin) ? in_array($row->domain_id, $module_extra_perms['zone_access']) : true;
		}
		if ($soa_count && $row->domain_reload == 'yes' && $reload_allowed) {
			if ($allowed_to_reload_zones && $zone_access_allowed) {
				$reload_zone = '<form name="reload" id="' . $row->domain_id . '" method="post" action="' . $GLOBALS['basename'] . '?map=' . $map . '"><input type="hidden" name="action" value="reload" /><input type="hidden" name="domain_id" id="domain_id" value="' . $row->domain_id . '" />' . $__FM_CONFIG['icons']['reload'] . '</form>';
				$checkbox = '<td><input type="checkbox" name="domain_list[]" value="' . $row->domain_id .'" /></td>';
			} else {
				$reload_zone = 'Reload Available<br />';
			}
		} else $reload_zone = null;
		if ($reload_zone) $class = 'build';
		
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
		
		if (!$soa_count && $row->domain_type == 'master' && $allowed_to_manage_zones) $type = 'SOA';
		elseif (!$ns_count && $row->domain_type == 'master' && $allowed_to_manage_zones) $type = 'NS';
		else {
			$type = ($row->domain_mapping == 'forward') ? 'A' : 'PTR';
		}
		if ($allowed_to_manage_zones && $zone_access_allowed) {
			$edit_status = '<a class="edit_form_link" name="' . $map . '" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a class="delete" href="#">' . $__FM_CONFIG['icons']['delete'] . '</a>' . "\n";
		}
		$edit_name = ($row->domain_type == 'master') ? "<a href=\"zone-records.php?map={$map}&domain_id={$row->domain_id}&record_type=$type\" title=\"Edit zone records\">{$row->domain_name}</a>" : $row->domain_name;
		if ($row->domain_view) {
			// Process multiple views
			if (strpos($row->domain_view, ';')) {
				$domain_views = explode(';', rtrim($row->domain_view, ';'));
				if (in_array('0', $domain_views)) $domain_view = 'All Views';
				else {
					$domain_view = null;
					foreach ($domain_views as $view_id) {
						$domain_view .= getNameFromID($view_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name') . ', ';
					}
					$domain_view = rtrim($domain_view, ', ');
				}
			} else $domain_view = getNameFromID($row->domain_view, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name');
		} else $domain_view = 'All Views';

		if ($class) $class = 'class="' . $class . '"';
		
		echo <<<HTML
		<tr title="$response" id="$row->domain_id" $class>
			$checkbox
			<td>$row->domain_id</td>
			<td>$edit_name</td>
			<td>$row->domain_type</td>
			<td id="clones">$clones</td>
			<td>$domain_view</td>
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
	function printForm($data = '', $action = 'create', $map = 'forward') {
		global $__FM_CONFIG;
		
		$ucf_action = ucfirst($action);
		$domain_id = $domain_view = $domain_name_servers = 0;
		$domain_type = $domain_check_names = $domain_notify_slaves = $domain_multi_masters = $domain_clone_domain_id = null;
		$domain_transfers_from = $domain_updates_from = $domain_master_servers = $domain_forward_servers = $domain_name = null;
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST)) {
				$domain_id = $_POST[$action . 'Zone']['ZoneID'];
				extract($_POST[$action . 'Zone'][$domain_id]);
			}
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		// Process multiple views
		if (strpos($domain_view, ';')) {
			$domain_view = explode(';', rtrim($domain_view, ';'));
			if (in_array('0', $domain_view)) $domain_view = 0;
		}
		
		// Process multiple domain name servers
		if (strpos($domain_name_servers, ';')) {
			$domain_name_servers = explode(';', rtrim($domain_name_servers, ';'));
			if (in_array('0', $domain_name_servers)) $domain_name_servers = 0;
		}
		
		/** Get field length */
		$domain_name_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_name');

		$views = buildSelect("{$action}Zone[$domain_id][domain_view]", 'domain_view', $this->availableViews(), $domain_view, 4, null, true);
		$zone_maps = buildSelect("{$action}Zone[$domain_id][domain_mapping]", 'domain_mapping', enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains','domain_mapping'), $map);
		$domain_types = buildSelect("{$action}Zone[$domain_id][domain_type]", 'domain_type', enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains','domain_type'), $domain_type);
		$check_names = buildSelect("{$action}Zone[$domain_id][domain_check_names]", 'domain_check_names', enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains','domain_check_names',''), $domain_check_names);
		$notify_slaves = buildSelect("{$action}Zone[$domain_id][domain_notify_slaves]", 'domain_notify_slaves', enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains','domain_notify_slaves',''), $domain_notify_slaves);
		$multi_masters = buildSelect("{$action}Zone[$domain_id][domain_multi_masters]", 'domain_multi_masters', enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains','multi_masters',''), $domain_multi_masters);
		$clone = buildSelect("{$action}Zone[$domain_id][domain_clone_domain_id]", 'domain_clone_domain_id', $this->availableCloneDomains($map, $domain_id), $domain_clone_domain_id, 1);
		$name_servers = buildSelect("{$action}Zone[$domain_id][domain_name_servers]", 'domain_name_servers', $this->availableDNSServers(), $domain_name_servers, 5, null, true);
		$domain_transfers_from = str_replace('; ', "\n", rtrim($domain_transfers_from, '; '));
		$domain_updates_from = str_replace('; ', "\n", rtrim($domain_updates_from, '; '));
		$domain_master_servers = str_replace('; ', "\n", rtrim($domain_master_servers, '; '));
		$domain_forward_servers = str_replace('; ', "\n", rtrim($domain_forward_servers, '; '));
		
		$return_form = <<<HTML
		<form name="manage" id="manage" method="post" action="?map=$map">
			<input type="hidden" name="action" value="$action" />
			<input type="hidden" name="{$action}Zone[ZoneID]" value="$domain_id" />
			<table class="form-table zone-form">
				<tr>
					<td>
						<table class="form-table">
							<tr>
								<th><label for="domain_name">Domain Name</label></th>
								<td><input type="text" id="domain_name" name="{$action}Zone[$domain_id][domain_name]" size="40" value="$domain_name" maxlength="$domain_name_length" /></td>
							</tr>
							<tr>
								<th><label for="domain_view">Views</label></th>
								<td>$views</td>
							</tr>
							<tr>
								<th><label for="domain_mapping">Zone Type</label></th>
								<td>$zone_maps</td>
							</tr>
							<tr>
								<th><label for="domain_type">Domain Type</label></th>
								<td>$domain_types</td>
							</tr>
							<tr>
								<th><label for="domain_clone_domain_id">Clone Of (optional)</label></th>
								<td>$clone</td>
							</tr>
							<tr>
								<th><label for="domain_check_names">Check Names (optional)</label></th>
								<td>$check_names</td>
							</tr>
							<tr>
								<th><label for="domain_notify_slaves">Notify Slaves (optional)</label></th>
								<td>$notify_slaves</td>
							</tr>
							<tr>
								<th><label for="domain_multi_masters">Multiple Masters (optional)</label></th>
								<td>$multi_masters</td>
							</tr>
						</table>
					</td>
					<td>
						<table class="form-table">
							<tr>
								<th><label for="domain_transfers_from">Transfers From (optional)</label></th>
								<td><textarea id="domain_transfers_from" name="{$action}Zone[$domain_id][domain_transfers_from]" rows="4" cols="30" placeholder="Addresses and subnets delimited by space, semi-colon, or newline">$domain_transfers_from</textarea></td>
							</tr>
							<tr>
								<th><label for="domain_updates_from">Updates From (optional)</label></th>
								<td><textarea id="domain_updates_from" name="{$action}Zone[$domain_id][domain_updates_from]" rows="4" cols="30" placeholder="Addresses and subnets delimited by space, semi-colon, or newline">$domain_updates_from</textarea></td>
							</tr>
							<tr>
								<th><label for="domain_master_servers">Master Servers (optional)</label></th>
								<td><textarea id="domain_master_servers" name="{$action}Zone[$domain_id][domain_master_servers]" rows="4" cols="30" placeholder="Addresses and subnets delimited by space, semi-colon, or newline">$domain_master_servers</textarea></td>
							</tr>
							<tr>
								<th><label for="domain_forward_servers">Forward Servers (optional)</label></th>
								<td><textarea id="domain_forward_servers" name="{$action}Zone[$domain_id][domain_forward_servers]" rows="4" cols="30" placeholder="Addresses and subnets delimited by space, semi-colon, or newline">$domain_forward_servers</textarea></td>
							</tr>
							<tr>
								<th><label for="domain_name_servers">DNS Servers</label></th>
								<td>$name_servers</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
			<input type="submit" name="submit" value="$ucf_action Zone" class="button" />
			<input type="button" value="Cancel" class="button" id="cancel_button" />
		</form>
HTML;

		return $return_form;
	}

	function cloneDomainsList($domain_id) {
		global $fmdb, $__FM_CONFIG, $allowed_to_manage_zones;
		
		$return = null;
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $domain_id, 'domain_', 'domain_clone_domain_id');
		if ($fmdb->num_rows) {
			$clone_results = $fmdb->last_result;
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$return .= '<p><a href="zone-records.php?map=' . $clone_results[$i]->domain_mapping . '&domain_id=' . $clone_results[$i]->domain_id . '" title="Edit zone records">' . $clone_results[$i]->domain_name . '</a>';
				if ($allowed_to_manage_zones) $return .= ' ' . str_replace('__ID__', $clone_results[$i]->domain_id, $__FM_CONFIG['module']['icons']['sub_delete']);
				$return .= "</p>\n";
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
		
		$query = "SELECT domain_id,domain_name FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}domains WHERE domain_clone_domain_id=0 AND domain_mapping='$map' AND domain_type='master' AND domain_status='active' $domain_id_sql ORDER BY domain_name ASC";
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
		
		$return[0][] = 'All Views';
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
	
	function availableDNSServers() {
		global $fmdb, $__FM_CONFIG;
		
		$return[0][] = 'All Servers';
		$return[0][] = '0';
		
		$query = "SELECT server_id,server_name FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}servers WHERE account_id='{$_SESSION['user']['account_id']}' AND server_status='active' ORDER BY server_name ASC";
		$result = $fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$results = $fmdb->last_result;
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$return[$i+1][] = $results[$i]->server_name;
				$return[$i+1][] = $results[$i]->server_id;
			}
		}
		return $return;
	}
	
	function buildZoneConfig($domain_id) {
		global $fmdb, $__FM_CONFIG, $fm_name;
		
		/** Check domain_id and soa */
		$query = "select * from fm_{$__FM_CONFIG['fmDNS']['prefix']}domains d, fm_{$__FM_CONFIG['fmDNS']['prefix']}soa s where domain_status='active' and d.account_id='{$_SESSION['user']['account_id']}' and s.domain_id=d.domain_id and d.domain_id=$domain_id";
		$result = $fmdb->query($query);
		if (!$fmdb->num_rows) return false;

		$domain_details = $fmdb->last_result;
		extract(get_object_vars($domain_details[0]), EXTR_SKIP);
		
		$name_servers = $this->getNameServers($domain_name_servers);
		
		/** No name servers so return */
		if (!$name_servers) return '<p class="error">There are no DNS servers hosting this zone.</p>'. "\n";
		
		/** Loop through name servers */
		$name_server_count = $fmdb->num_rows;
		$response = '<textarea rows="12" cols="85">';
		$failures = false;
		for ($i=0; $i<$name_server_count; $i++) {
			switch($name_servers[$i]->server_update_method) {
				case 'cron':
					/** Add records to fm_{$__FM_CONFIG['fmDNS']['prefix']}track_reloads */
					$this->addZoneReload($name_servers[$i]->server_serial_no, $domain_id, $soa_serial_no);
					
					/** Set the server_update_config flag */
					setBuildUpdateConfigFlag($name_servers[$i]->server_serial_no, 'yes', 'update');
					
					$response .= '[' . $name_servers[$i]->server_name . "] This zone will be updated on the next cron run.\n";
					break;
				case 'http':
				case 'https':
					/** Test the port first */
					if (!socketTest($name_servers[$i]->server_name, $name_servers[$i]->server_update_port, 10)) {
						$response .= '[' . $name_servers[$i]->server_name . '] Failed: could not access ' . $name_servers[$i]->server_update_method . ' (tcp/' . $name_servers[$i]->server_update_port . ").\n";
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
						$response .= '[' . $name_servers[$i]->server_name . '] Failed: could not access ' . $name_servers[$i]->server_update_method . ' (tcp/' . $name_servers[$i]->server_update_port . ").\n";
						$failures = true;
						break;
					}
					
					/** Get SSH key */
					$ssh_key = getOption('ssh_key_priv', $_SESSION['user']['account_id']);
					if (!$ssh_key) {
						return $response . '<p class="error">Failed: SSH key is not <a href="' . $__FM_CONFIG['menu']['Admin']['Settings'] . '">defined</a>.</p>'. "\n";
					}
					
					$temp_ssh_key = '/tmp/fm_id_rsa';
					if (@file_put_contents($temp_ssh_key, $ssh_key) === false) {
						return $response . '<p class="error">Failed: could not load SSH key into ' . $temp_ssh_key . '.</p>'. "\n";
					}
					
					@chmod($temp_ssh_key, 0400);
					
					exec(findProgram('ssh') . " -t -i $temp_ssh_key -o 'StrictHostKeyChecking no' -p {$name_servers[$i]->server_update_port} -l fm_user {$name_servers[$i]->server_name} 'sudo php /usr/local/$fm_name/{$_SESSION['module']}/dns.php zones id=$domain_id'", $post_result, $retval);
					
					@unlink($temp_ssh_key);
					
					if ($retval) {
						/** Something went wrong */
						return '<p class="error">Zone reload failed.</p>'. "\n";
					} else {
						if (!count($post_result)) $post_result[] = 'Zone reload was successful.';
						
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
		
		/** Update the SOA serial number */
//		$this->updateSOASerialNo($domain_id, $soa_serial_no);
		
		/** Reset the domain_reload flag */
		if (!$failures) {
			$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` SET `domain_reload`='no' WHERE `domain_status`='active' AND account_id='{$_SESSION['user']['account_id']}' AND `domain_id`=$domain_id";
			$result = $fmdb->query($query);
		}

		addLogEntry("Reloaded zone '$domain_name'.");
		return $response;
	}

	function getNameServers($domain_name_servers) {
		global $fmdb, $__FM_CONFIG;
		
		/** Check domain_name_servers */
		if ($domain_name_servers) {
			$name_servers = explode(';', rtrim($domain_name_servers, ';'));
			$sql_name_servers = 'AND `server_id` IN (';
			foreach($name_servers as $server) {
				if (!empty($server)) $sql_name_servers .= "'$server',";
			}
			$sql_name_servers = rtrim($sql_name_servers, ',') . ')';
		} else $sql_name_servers = null;
		
		$query = "select * from `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` where `server_status`='active' AND account_id='{$_SESSION['user']['account_id']}' $sql_name_servers ORDER BY `server_update_method`";
		$result = $fmdb->query($query);
		
		/** No name servers so return */
		if (!$fmdb->num_rows) return false;
		
		return $fmdb->last_result;
	}
	
	
	function addZoneReload($server_serial_no, $domain_id, $soa_serial_no) {
		global $fmdb, $__FM_CONFIG;
		
		$query = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}track_reloads` VALUES($domain_id, $server_serial_no, $soa_serial_no)";
		$result = $fmdb->query($query);
	}
	
	
	function updateSOASerialNo($domain_id, $soa_serial_no) {
		global $fmdb, $__FM_CONFIG;
		
		$soa_serial_no = ($soa_serial_no == 99) ? 10 : $soa_serial_no + 1;
		
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}soa` SET `soa_serial_no`=$soa_serial_no WHERE `domain_id`=$domain_id";
		$result = $fmdb->query($query);
	}
	
	function availableZones() {
		global $fmdb, $__FM_CONFIG;
		
		$query = "SELECT domain_id,domain_name FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}domains WHERE account_id='{$_SESSION['user']['account_id']}' AND domain_status!='deleted' AND domain_clone_domain_id=0 ORDER BY domain_name ASC";
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
			return (preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $domain_name) // valid chars check
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
		global $fmdb, $__FM_CONFIG, $super_admin;
		
		/** Check serial number */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', sanitize($domain_id), 'domain_', 'domain_id');
		if (!$fmdb->num_rows) return $domain_id . ' is not a valid zone ID.';

		$server_details = $fmdb->last_result;
		extract(get_object_vars($server_details[0]), EXTR_SKIP);
		
		$response[] = $domain_name;
		
		/** Ensure domain is reloadable */
		if ($domain_reload != 'yes') {
			$response[] = ' --> Failed: Zone is not available for reload.';
		}
		
		/** Ensure domain is master */
		if (count($response) == 1 && $domain_type != 'master') {
			$response[] = ' --> Failed: Zone is not a master zone.';
		}
		
		/** Ensure user is allowed to reload zone */
		$zone_access_allowed = true;
		
		if (isset($_SESSION['user']['module_perms']['perm_extra'])) {
			$module_extra_perms = isSerialized($_SESSION['user']['module_perms']['perm_extra']) ? unserialize($_SESSION['user']['module_perms']['perm_extra']) : $_SESSION['user']['module_perms']['perm_extra'];
			$zone_access_allowed = (is_array($module_extra_perms) && 
				!in_array(0, $module_extra_perms['zone_access']) && !$super_admin) ? in_array($domain_id, $module_extra_perms['zone_access']) : true;
		}
		if (count($response) == 1 && !$zone_access_allowed) {
			$response[] = ' --> Failed: You do not have permission to reload this zone.';
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

}

if (!isset($fm_dns_zones))
	$fm_dns_zones = new fm_dns_zones();

?>
