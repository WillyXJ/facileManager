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
	function rows($result, $map, $reload_allowed, $page) {
		global $fmdb, $__FM_CONFIG;
		
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;

		if (currentUserCan('reload_zones', $_SESSION['module'])) {
			$bulk_actions_list = array('Reload');
			$checkbox[] = array(
								'title' => '<input type="checkbox" onClick="toggle(this, \'domain_list[]\')" />',
								'class' => 'header-tiny header-nosort'
							);
		} else {
			$checkbox = $bulk_actions_list = null;
		}

		if (!$result) {
			echo '<p id="table_edits" class="noresult" name="domains">There are no zones.</p>';
		} else {
			$start = $_SESSION['user']['record_count'] * ($page - 1);
			$end = $_SESSION['user']['record_count'] * $page > $num_rows ? $num_rows : $_SESSION['user']['record_count'] * $page;

			echo @buildBulkActionMenu($bulk_actions_list, 'server_id_list');
			
			$table_info = array(
							'class' => 'display_results sortable',
							'id' => 'table_edits',
							'name' => 'domains'
						);

			$title_array = array(array('title' => 'ID', 'class' => 'header-small header-nosort'), 
				array('title' => 'Domain', 'rel' => 'domain_name'), 
				array('title' => 'Type', 'rel' => 'domain_type'),
				'Clones', array('title' => 'Views', 'class' => 'header-nosort'),
				array('title' => 'Records', 'class' => 'header-small  header-nosort'));
			$title_array[] = array('title' => 'Actions', 'class' => 'header-actions header-nosort');
			
			if (is_array($checkbox)) {
				$title_array = array_merge($checkbox, $title_array);
			}

			echo displayTableHeader($table_info, $title_array, 'zones');
			
			for ($x=$start; $x<$end; $x++) {
				$this->displayRow($results[$x], $map, $reload_allowed);
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
		
		$log_message = "Added a zone with the following details:\n";

		/** Get clone parent values */
		if ($post['domain_clone_domain_id']) {
			$query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE domain_id={$post['domain_clone_domain_id']}";
			$fmdb->query($query);
			if (!$fmdb->num_rows) return 'Cannot find cloned zone.';
			
			$parent_domain = $fmdb->last_result;
			foreach ($parent_domain[0] as $field => $value) {
				if ($field == 'domain_id') continue;
				if ($field == 'domain_clone_domain_id') {
					$sql_values .= sanitize($post['domain_clone_domain_id']) . ',';
				} elseif ($field == 'domain_name') {
					$log_message .= 'Name: ' . displayFriendlyDomainName(sanitize($post['domain_name'])) . "\n";
					$log_message .= "Clone of: $value\n";
					$sql_values .= "'" . sanitize($post['domain_name']) . "',";
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
			
			/** Format domain_name_servers */
			$log_message_name_servers = null;
			foreach ($post['domain_name_servers'] as $val) {
				if ($val == 0) {
					$domain_name_servers = 0;
					break;
				}
				$domain_name_servers .= $val . ';';
				$server_name = getNameFromID($val, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
				$log_message_name_servers .= $val ? "$server_name; " : null;
			}
			$post['domain_name_servers'] = rtrim($domain_name_servers, ';');
			if (!$post['domain_name_servers']) $post['domain_name_servers'] = 0;
			
			$exclude = array('submit', 'action', 'domain_id', 'domain_required_servers');
		
			foreach ($post as $key => $data) {
				if (!in_array($key, $exclude)) {
					$sql_fields .= $key . ',';
					$sql_values .= strlen(sanitize($data)) ? "'" . sanitize($data) . "'," : 'NULL,';
					if ($key == 'domain_view') $data = $log_message_views;
					if ($key == 'domain_name_servers') $data = $log_message_name_servers;
					if ($key == 'soa_id') {
						$soa_name = $data ? getNameFromID($data, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'soa', 'soa_', 'soa_id', 'soa_name') : 'Custom';
						$log_message .= formatLogKeyData('_id', $key, $soa_name);
					} else {
						$log_message .= $data ? formatLogKeyData('domain_', $key, $data) : null;
					}
				}
			}
			$sql_fields .= 'account_id)';
			$sql_values .= "'{$_SESSION['user']['account_id']}'";
			
			$query = "$sql_insert $sql_fields VALUES ($sql_values)";
			$result = $fmdb->query($query);
		}
		
		if ($fmdb->sql_errors) return 'Could not add zone because a database error occurred.';

		$insert_id = $fmdb->insert_id;
		
		/** Add mandatory config options */
		$query = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` 
			(account_id,domain_id,cfg_name,cfg_data) VALUES ({$_SESSION['user']['account_id']}, $insert_id, ";
		$required_servers = sanitize($post['domain_required_servers']);
		if ($post['domain_type'] == 'forward') {
			$query .= "'forwarders', '" . $required_servers . "')";
			$result = $fmdb->query($query);
			$log_message .= formatLogKeyData('domain_', 'forwarders', $required_servers);
		} elseif (in_array($post['domain_type'], array('slave', 'stub'))) {
			$query .= "'masters', '" . $required_servers . "')";
			$result = $fmdb->query($query);
			$log_message .= formatLogKeyData('domain_', 'masters', $required_servers);
		}
		if ($fmdb->sql_errors) return 'Could not add zone because a database error occurred.';
		
		addLogEntry($log_message);
		
		/* Set the server_build_config flag for servers */
		if ($post['domain_clone_domain_id']) {
			if (getSOACount($post['domain_clone_domain_id']) && getNSCount($post['domain_clone_domain_id'])) {
				setBuildUpdateConfigFlag(getZoneServers($post['domain_clone_domain_id']), 'yes', 'build');
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
			if ($fmdb->num_rows) return 'There are associated records with this zone.';
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
			if ($val == 0) {
				$domain_name_servers = 0;
				break;
			}
			$domain_name_servers .= $val . ';';
			$server_name = getNameFromID($val, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
			$log_message_name_servers .= $val ? "$server_name; " : null;
		}
		$post['domain_name_servers'] = rtrim($domain_name_servers, ';');
		if (!$post['domain_name_servers']) $post['domain_name_servers'] = 0;
		
		$exclude = array('submit', 'action', 'domain_id', 'domain_required_servers');

		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= strlen(sanitize($data)) ? $key . "='" . mysql_real_escape_string($data) . "'," : $key . '=NULL,';
				if ($key == 'domain_view') $data = $log_message_views;
				if ($key == 'domain_name_servers') $data = $log_message_name_servers;
				$log_message .= $data ? formatLogKeyData('domain_', $key, $data) : null;
			}
		}
		$sql_edit .= "domain_reload='no'";
		
		/** Set the server_build_config flag for existing servers */
		if (getSOACount($domain_id) && getNSCount($domain_id)) {
			setBuildUpdateConfigFlag(getZoneServers($domain_id), 'yes', 'build');
		}

		/** Update the zone */
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` SET $sql_edit WHERE `domain_id`='$domain_id' AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) return 'Could not update the zone because a database error occurred.';
		
		$rows_affected = $fmdb->rows_affected;

		/** Add mandatory config options */
		$query = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` 
			(account_id,domain_id,cfg_name,cfg_data) VALUES ({$_SESSION['user']['account_id']}, $domain_id, ";
		$required_servers = sanitize($post['domain_required_servers']);
		if ($post['domain_type'] == 'forward') {
			if (getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'domain_id', 'cfg_data', null, "AND cfg_name='forwarders'")) {
				basicUpdate("fm_{$__FM_CONFIG['fmDNS']['prefix']}config", getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'domain_id', 'cfg_id', null, "AND cfg_name='forwarders'"), 'cfg_data', $required_servers, 'cfg_id');
			} else {
				$query .= "'forwarders', '" . $required_servers . "')";
				$result = $fmdb->query($query);
			}
			$log_message .= formatLogKeyData('domain_', 'forwarders', $required_servers);
		} elseif (in_array($post['domain_type'], array('slave', 'stub'))) {
			if (getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'domain_id', 'cfg_data', null, "AND cfg_name='masters'")) {
				basicUpdate("fm_{$__FM_CONFIG['fmDNS']['prefix']}config", getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'domain_id', 'cfg_id', null, "AND cfg_name='masters'"), 'cfg_data', $required_servers, 'cfg_id');
			} else {
				$query .= "'masters', '" . $required_servers . "')";
				$result = $fmdb->query($query);
			}
			$log_message .= formatLogKeyData('domain_', 'masters', $required_servers);
		}
		if ($fmdb->sql_errors) return 'Could not update zone because a database error occurred.' . $fmdb->last_error;
		
		/** Return if there are no changes */
		if ($rows_affected + $fmdb->rows_affected = 0) return true;

		/** Set the server_build_config flag for new servers */
		if (getSOACount($domain_id) && getNSCount($domain_id)) {
			setBuildUpdateConfigFlag(getZoneServers($domain_id), 'yes', 'build');
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
					return 'The associated configs for this zone could not be deleted because a database error occurred.';
				}
				unset($fmdb->num_rows);
			}
			
			/** Delete all associated records */
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $domain_id, 'record_', 'domain_id');
			if ($fmdb->num_rows) {
				if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $domain_id, 'record_', 'deleted', 'domain_id') === false) {
					return 'The associated records for this zone could not be deleted because a database error occurred.';
				}
				unset($fmdb->num_rows);
			}
			
			/** Delete all associated SOA */
			if ($domain_result->soa_id) {
				basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'soa', $domain_result->soa_id, 'soa_', 'soa_id', "AND soa_template='no'");
				if ($fmdb->num_rows) {
					if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'soa', $domain_result->soa_id, 'soa_', 'deleted', 'soa_id') === false) {
						return 'The SOA for this zone could not be deleted because a database error occurred.';
					}
					unset($fmdb->num_rows);
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
				unset($fmdb->num_rows);
				/** Delete cloned zone records first */
				basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_id', 'domain_', "AND domain_clone_domain_id=$domain_id");
				if ($fmdb->num_rows) {
					$clone_domain_result = $fmdb->last_result;
					$clone_domain_num_rows = $fmdb->num_rows;
					for ($i=0; $i<$clone_domain_num_rows; $i++) {
						if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $clone_domain_result[$i]->domain_id, 'record_', 'deleted', 'domain_id') === false) {
							return 'The associated records for the cloned zones could not be deleted because a database error occurred.';
						}
					}
					unset($fmdb->num_rows);
				}
				
				if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $domain_id, 'domain_', 'deleted', 'domain_clone_domain_id') === false) {
					return 'The associated clones for this zone could not be deleted because a database error occurred.';
				}
			}
			
			/** Delete zone */
			$tmp_name = displayFriendlyDomainName(getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name'));
			if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $domain_id, 'domain_', 'deleted', 'domain_id') === false) {
				return 'This zone could not be deleted because a database error occurred.';
			}
			
			addLogEntry("Deleted zone '$tmp_name' and all associated records.");
			
			return true;
		}
		
		return 'This zone does not exist.';
	}
	
	
	function displayRow($row, $map, $reload_allowed) {
		global $fmdb, $__FM_CONFIG;
		
		if (!currentUserCan(array('access_specific_zones', 'view_all'), $_SESSION['module'], array(0, $row->domain_id))) return;
		
		$zone_access_allowed = currentUserCan(array('access_specific_zones'), $_SESSION['module'], array(0, $row->domain_id));
		
		$class = ($row->domain_status == 'disabled') ? 'disabled' : null;
		$response = null;
		
		$checkbox = (currentUserCan('reload_zones', $_SESSION['module'])) ? '<td></td>' : null;
		
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
		
		if ($soa_count && $row->domain_reload == 'yes' && $reload_allowed) {
			if (currentUserCan('reload_zones', $_SESSION['module']) && $zone_access_allowed) {
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
		$edit_name = ($row->domain_type == 'master') ? "<a href=\"zone-records.php?map={$map}&domain_id={$row->domain_id}&record_type=$type\" title=\"Edit zone records\">$domain_name</a>" : $domain_name;
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
		
		$record_count = null;
		if ($row->domain_type == 'master') {
			$query = "SELECT COUNT(*) record_count FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}records WHERE account_id={$_SESSION['user']['account_id']} AND domain_id={$row->domain_id} AND record_status!='deleted'";
			$fmdb->query($query);
			$result = $fmdb->last_result;
			$record_count = $result[0]->record_count;
		}
		
		echo <<<HTML
		<tr title="$response" id="$row->domain_id" $class>
			$checkbox
			<td>$row->domain_id</td>
			<td>$edit_name</td>
			<td>$row->domain_type</td>
			<td id="clones">$clones</td>
			<td>$domain_view</td>
			<td align="center">$record_count</td>
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
		global $__FM_CONFIG, $fm_dns_acls;
		
		$ucaction = ucfirst($action);
		$domain_id = $domain_view = $domain_name_servers = 0;
		$domain_type = $domain_clone_domain_id = $domain_name = null;
		$disabled = $action == 'create' ? null : 'disabled';
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST)) {
				$domain_id = $_POST[$action . 'Zone']['ZoneID'];
				extract($_POST[$action . 'Zone'][$domain_id]);
			}
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
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
		$zone_maps = buildSelect('domain_mapping', 'domain_mapping', enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains','domain_mapping'), $map, 1, 'disabled');
		$domain_types = buildSelect('domain_type', 'domain_type', enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains','domain_type'), $domain_type, 1, $disabled);
		$clone = buildSelect('domain_clone_domain_id', 'domain_clone_domain_id', $this->availableCloneDomains($map, $domain_id), $domain_clone_domain_id, 1, $disabled);
		$name_servers = buildSelect('domain_name_servers', 'domain_name_servers', $this->availableDNSServers(), $domain_name_servers, 5, null, true);

		$forwarders_show = $masters_show = 'none';
		$domain_forward_servers = $domain_master_servers = null;
		$available_acls = json_encode(array());
		if ($domain_type == 'forward') {
			$forwarders_show = 'block';
			$domain_forward_servers = str_replace(';', "\n", rtrim(str_replace(' ', '', getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'domain_id', 'cfg_data', null, "AND cfg_name='forwarders'")), ';'));
			$available_acls = $fm_dns_acls->buildACLJSON($domain_forward_servers, 0, 'none');
		} elseif (in_array($domain_type, array('slave', 'stub'))) {
			$masters_show = 'block';
			$domain_master_servers = str_replace(';', "\n", rtrim(str_replace(' ', '', getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'domain_id', 'cfg_data', null, "AND cfg_name='masters'")), ';'));
			$available_acls = $fm_dns_acls->buildACLJSON($domain_master_servers, 0, 'none');
		}
		
		if ($action == 'create') {
			$soa_show = 'block';
			global $fm_dns_records;
			if (!isset($fm_dns_records)) include(ABSPATH . 'fm-modules/fmDNS/classes/class_records.php');
			$soa_templates = '<tr id="define_soa">
					<th>SOA</th>
					<td>' . buildSelect('soa_id', 'soa_id', $fm_dns_records->availableSOATemplates(), $fm_dns_records->getDefaultSOA()) . '</td></tr>';
		} else {
			$soa_show = 'none';
			$soa_templates = null;
		}
		$additional_config_link = ($action == 'create' || $domain_type != 'master') ? null : '<tr><td></td><td><p><a href="config-options.php?domain_id=' . $domain_id . '">Configure Additional Options</a></p></td></tr>';
		
		$popup_header = buildPopup('header', $ucaction . ' Zone');
		$popup_footer = buildPopup('footer');
		
		$return_form = <<<HTML
		<form name="manage" id="manage" method="post" action="">
		$popup_header
			<input type="hidden" name="action" value="$action" />
			<input type="hidden" name="domain_id" value="$domain_id" />
			<table class="form-table zone-form">
				<tr>
					<th><label for="domain_name">Domain Name</label></th>
					<td><input type="text" id="domain_name" name="domain_name" size="40" value="$domain_name" maxlength="$domain_name_length" /></td>
				</tr>
				<tr>
					<th><label for="domain_view">Views</label></th>
					<td>$views</td>
				</tr>
				<tr>
					<th><label for="domain_mapping">Zone Map</label></th>
					<td>$zone_maps</td>
				</tr>
				<tr>
					<th><label for="domain_type">Zone Type</label></th>
					<td>
						$domain_types
						<div id="define_forwarders" style="display: $forwarders_show">
							<input type="hidden" name="domain_required_servers[forwarders]" id="domain_required_servers" class="address_match_element" data-placeholder="Define forwarders" value="$domain_forward_servers" /><br />
							( address_match_element )
						</div>
						<div id="define_masters" style="display: $masters_show">
							<input type="hidden" name="domain_required_servers[masters]" id="domain_required_servers" class="address_match_element" data-placeholder="Define masters" value="$domain_master_servers" /><br />
							( address_match_element )
						</div>
					</td>
				</tr>
				<tr>
					<th><label for="domain_clone_domain_id">Clone Of (optional)</label></th>
					<td>$clone</td>
				</tr>
				<tr>
					<th><label for="domain_name_servers">DNS Servers</label></th>
					<td>$name_servers</td>
				</tr>
				$soa_templates
				$additional_config_link
			</table>
		$popup_footer
		</form>
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
			});
		</script>
HTML;

		return $return_form;
	}

	function cloneDomainsList($domain_id) {
		global $fmdb, $__FM_CONFIG;
		
		$return = null;
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $domain_id, 'domain_', 'domain_clone_domain_id');
		if ($fmdb->num_rows) {
			$count = $fmdb->num_rows;
			$clone_results = $fmdb->last_result;
			for ($i=0; $i<$count; $i++) {
				$return .= '<p><a href="zone-records.php?map=' . $clone_results[$i]->domain_mapping . '&domain_id=' . $clone_results[$i]->domain_id . '" title="Edit zone records">' . $clone_results[$i]->domain_name . '</a>';
				if (currentUserCan(array('manage_zones'), $_SESSION['module'], array(0, $domain_id)) &&
					currentUserCan(array('access_specific_zones'), $_SESSION['module'], array(0, $domain_id))) $return .= ' ' . str_replace('__ID__', $clone_results[$i]->domain_id, $__FM_CONFIG['module']['icons']['sub_delete']);
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
		$query = "SELECT * FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}domains d, fm_{$__FM_CONFIG['fmDNS']['prefix']}soa s where domain_status='active' and d.account_id='{$_SESSION['user']['account_id']}' and s.soa_id=d.soa_id and d.domain_id=$domain_id";
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
						return $response . '<p class="error">Failed: SSH key is not <a href="' . getMenuURL('Settings') . '">defined</a>.</p>'. "\n";
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
			global $fm_dns_records;
			if (!isset($fm_dns_records)) include(ABSPATH . 'fm-modules/fmDNS/classes/class_records.php');
			$fm_dns_records->updateSOAReload($domain_id, 'no');
		}

		addLogEntry("Reloaded zone '" . displayFriendlyDomainName($domain_name) . "'.");
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
		
		$soa_serial_no = ($soa_serial_no == 99) ? 0 : $soa_serial_no + 1;
		
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` SET `soa_serial_no`=" . sprintf('%02d', $soa_serial_no) . " WHERE `domain_id`=$domain_id";
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
		$zone_type_sql = $zone_type ? "AND domain_type='$zone_type'" : null;
		
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
		if (!$fmdb->num_rows) return $domain_id . ' is not a valid zone ID.';

		$server_details = $fmdb->last_result;
		extract(get_object_vars($server_details[0]), EXTR_SKIP);
		
		$response[] = displayFriendlyDomainName($domain_name);
		
		/** Ensure domain is reloadable */
		if ($domain_reload != 'yes') {
			$response[] = ' --> Failed: Zone is not available for reload.';
		}
		
		/** Ensure domain is master */
		if (count($response) == 1 && $domain_type != 'master') {
			$response[] = ' --> Failed: Zone is not a master zone.';
		}
		
		/** Ensure user is allowed to reload zone */
		$zone_access_allowed = currentUserCan('access_specific_zones', $_SESSION['module'], array(0, $domain_id)) & 
				currentUserCan('reload_zones');
		
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


	function validatePost($post) {
		global $fmdb, $__FM_CONFIG;
		
		if (!$post['domain_id']) unset($post['domain_id']);
		
		/** Empty domain names are not allowed */
		if (empty($post['domain_name'])) return 'No zone name defined.';
		
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
		if (!$this->validateDomainName($post['domain_name'], $post['domain_mapping'])) return 'Invalid zone name.';
		
		/** Ensure domain_view is set */
		if (!array_key_exists('domain_view', $post)) $post['domain_view'] = 0;

		/** Reverse zones should have form of x.x.x.in-addr.arpa */
		if ($post['domain_mapping'] == 'reverse') {
			$post['domain_name'] = $this->setReverseZoneName($post['domain_name']);
		}
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', $_SESSION['user']['account_id'], 'view_', 'account_id');
		if (!$fmdb->num_rows) { /** No views defined - all zones must be unique */
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', sanitize($post['domain_name']), 'domain_', 'domain_name');
			if ($fmdb->num_rows) return 'Zone already exists.';
		} else { /** All zones must be unique per view */
			$defined_views = $fmdb->last_result;
			
			/** Format domain_view */
			$domain_id_sql = (isset($post['domain_id'])) ? 'AND domain_id!=' . sanitize($post['domain_id']) : null;
			if (!$post['domain_view'] || in_array(0, $post['domain_view'])) {
				basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', sanitize($post['domain_name']), 'domain_', 'domain_name', $domain_id_sql);
				if ($fmdb->num_rows) return 'Zone already exists for all views.';
			}
			if (is_array($post['domain_view'])) {
				$domain_view = null;
				foreach ($post['domain_view'] as $val) {
					if ($val == 0 || $val == '') {
						$domain_view = 0;
						break;
					}
					$domain_view .= $val . ';';
					basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', sanitize($post['domain_name']), 'domain_', 'domain_name', "AND (domain_view='$val' OR domain_view=0 OR domain_view LIKE '$val;%' OR domain_view LIKE '%;$val;%' OR domain_view LIKE '%;$val') $domain_id_sql");
					if ($fmdb->num_rows) {
						$view_name = getNameFromID($val, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name');
						return "Zone already exists for the '$view_name' view.";
					}
				}
				$post['domain_view'] = rtrim($domain_view, ';');
			}
		}
		
		/** Format domain_clone_domain_id */
		if (!$post['domain_clone_domain_id']) $post['domain_clone_domain_id'] = 0;
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_name');
		if ($field_length !== false && strlen($post['domain_name']) > $field_length) return 'Zone name is too long (maximum ' . $field_length . ' characters).';
		
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
			if (empty($post['domain_required_servers']['forwarders'])) return 'No forward servers defined.';
			$post['domain_required_servers'] = $post['domain_required_servers']['forwarders'];
		}

		/** Slave and stub zones require master servers */
		if (in_array($post['domain_type'], array('slave', 'stub'))) {
			if (empty($post['domain_required_servers']['masters'])) return 'No master servers defined.';
			$post['domain_required_servers'] = $post['domain_required_servers']['masters'];
		}

		return $post;
	}

}

if (!isset($fm_dns_zones))
	$fm_dns_zones = new fm_dns_zones();

?>
