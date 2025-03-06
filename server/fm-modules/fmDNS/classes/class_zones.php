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

class fm_dns_zones {
	
	/**
	 * Displays the zone list
	 */
	function rows($result, $map, $reload_allowed, $page, $total_pages) {
		global $fmdb, $__FM_CONFIG;
		
		$all_num_rows = $num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;

		$bulk_actions_list = array();
		if (currentUserCan('reload_zones', $_SESSION['module']) && $map != 'groups') {
			$bulk_actions_list[] = __('Reload');
			$checkbox[] = array(
								'title' => '<input type="checkbox" class="tickall" onClick="toggle(this, \'domain_list[]\')" />',
								'class' => 'header-tiny header-nosort'
							);
		} else {
			$checkbox = '';
		}

		if (array_key_exists('attention', $_GET)) {
			$num_rows = $GLOBALS['zone_badge_counts'][$map];
			$total_pages = ceil($num_rows / $_SESSION['user']['record_count']);
			if ($page > $total_pages) $page = $total_pages;
		}

		$start = $_SESSION['user']['record_count'] * ($page - 1);
		$end = $_SESSION['user']['record_count'] * $page > $num_rows ? $num_rows : $_SESSION['user']['record_count'] * $page;

		$table_info = array(
						'class' => 'display_results sortable',
						'id' => 'table_edits',
						'name' => 'domains'
					);

		if ($map == 'groups') {
			if (is_array($bulk_actions_list)) {
				array_unshift($bulk_actions_list, _('Enable'), _('Disable'), _('Delete'));
				$title_array[] = array(
									'title' => '<input type="checkbox" class="tickall" onClick="toggle(this, \'bulk_list[]\')" />',
									'class' => 'header-tiny header-nosort'
								);
			} else {
				$title_array[] = array(
					'class' => 'header-tiny header-nosort'
				);
			}
			$title_array = array_merge((array) $title_array, array(array('title' => __('Group Name'), 'rel' => 'group_name'),
				array('title' => __('Associated Domains'), 'class' => 'header-nosort'),
				array('title' => _('Comment'), 'class' => 'header-nosort')
			));
		} else {
			$title_array = array(array('title' => __('ID'), 'class' => 'header-small header-nosort'), 
				array('title' => __('Domain'), 'rel' => 'domain_name'), 
				array('title' => __('Type'), 'rel' => 'domain_type'),
				array('title' => __('Views'), 'class' => 'header-nosort'),
				array('title' => __('Servers'), 'class' => 'header-nosort'),
				array('title' => _('Comment'), 'class' => 'header-nosort'),
				array('title' => __('Records'), 'class' => 'header-small  header-nosort')
			);
		}
		if ($map != 'groups' || currentUserCan('manage_zones', $_SESSION['module'])) {
			$title_array[] = array('title' => __('Actions'), 'class' => 'header-actions header-nosort');
		}
		
		if (is_array($checkbox)) {
			$title_array = array_merge($checkbox, $title_array);
		}

		if (!count($bulk_actions_list)) $bulk_actions_list = '';
		$classes = (array_key_exists('attention', $_GET)) ? null : ' grey';
		$eye_attention = $GLOBALS['zone_badge_counts'][$map] ? sprintf('<a href="JavaScript:void(0);" class="tooltip-top mini-icon" data-tooltip="%s"><i class="fa fa-eye fa-lg eye-attention %s"></i></a>', __('Only view zones that need attention'), $classes) : null;
		$addl_blocks = ($map != 'groups') ? array(@buildBulkActionMenu($bulk_actions_list, 'server_id_list'), $this->buildFilterMenu(), $eye_attention) : buildBulkActionMenu($bulk_actions_list);
		$fmdb->num_rows = $num_rows;
		echo displayPagination($page, $total_pages, $addl_blocks);
		echo '<div class="overflow-container">';

		echo '<div class="table-results-container">';
		echo displayTableHeader($table_info, $title_array, 'zones');
		
		if ($result) {
			$y = 0;
			for ($x=$start; $x<$all_num_rows; $x++) {
				if ($y == $_SESSION['user']['record_count']) break;
				if (array_key_exists('attention', $_GET)) {
					if (!$results[$x]->domain_clone_domain_id && $results[$x]->domain_type == 'primary' && $results[$x]->domain_template == 'no' &&
						(!getSOACount($results[$x]->domain_id) || !getNSCount($results[$x]->domain_id) || $results[$x]->domain_reload == 'yes' ||
						$results[$x]->domain_dnssec == 'yes')) {
							/** DNSSEC Check */
							if ($results[$x]->domain_dnssec == 'yes') {
								$domain_dnssec_sig_expires = getDNSSECExpiration($results[$x]);
								basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'keys', 'key_id', 'key_', 'AND domain_id=' . $results[$x]->domain_id);
								if ($fmdb->num_rows && $domain_dnssec_sig_expires >= strtotime('now + 7 days')) {
									continue;
								}
							}							
							$this->displayRow($results[$x], $map, $reload_allowed);
							$y++;
					}
				} else {
					$this->displayRow($results[$x], $map, $reload_allowed);
					$y++;
				}
			}
		}
			
		echo "</tbody>\n</table></div></div>\n";
		if (!$result) {
			$message = ($map == 'groups') ? __('There are no zone groups.') : __('There are no zones.');
			printf('<p id="table_edits" class="noresult" name="domains">%s</p>', $message);
		}
	}

	/**
	 * Adds the new zone
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG, $global_form_field_excludes;
		
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;

		/** Validate post */
		if (array_key_exists('group_name', $post)) {
			/** Zone groups */
			$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domain_groups`";
			$sql_fields = '(';
			$sql_values = '';

			$post['account_id'] = $_SESSION['user']['account_id'];
			
			$exclude = array_merge($global_form_field_excludes, array('group_domain_ids', 'group_id'));
		
			$log_message_domains = $this->getZoneLogDomainNames($post['group_domain_ids']);

			foreach ($post as $key => $data) {
				if (!in_array($key, $exclude)) {
					$sql_fields .= $key . ', ';
					$sql_values .= "'" . $data . "', ";
				}
			}
			$sql_fields = rtrim($sql_fields, ', ') . ')';
			$sql_values = rtrim($sql_values, ', ');

			$query = "$sql_insert $sql_fields VALUES ($sql_values)";
			$fmdb->query($query);

			if ($fmdb->sql_errors) {
				return formatError(__('Could not add the zone group because a database error occurred.'), 'sql');
			}

			$insert_id = $fmdb->insert_id;
			
			/** Update domains table */
			$retval = $this->setZoneGroupMembers($insert_id, $post['group_domain_ids']);
			if ($retval !== true) {
				return $retval;
			}
			
			addLogEntry(__('Added a zone group with the following details') . ":\n" . __('Name') . ": {$post['group_name']}\n" . __('Associated Zones') . ": $log_message_domains\n" . _('Comment') . ": {$post['group_comment']}");
			
			return $insert_id;
		}
		
		$dbupdate_error_msg = __('Could not add the zone because a database error occurred.');
		
		/** Zones */
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domains`";
		$sql_fields = '(';
		$sql_values = $domain_name_servers = $domain_views = '';
		
		$log_message = __('Added a zone with the following details') . ":\n";

		/** Format domain_view */
		$log_message_views = '';
		if (isset($post['domain_view'])) {
			$domain_view_array = explode(';', $post['domain_view']);
			if (is_array($domain_view_array)) {
				$domain_view = '';
				foreach ($domain_view_array as $val) {
					if ($val == 0 || $val == '') {
						$domain_view = 0;
						break;
					}
					$domain_view .= $val . ';';
					$view_name = getNameFromID($val, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'views', 'view_', 'view_id', 'view_name');
					$log_message_views .= $val ? "$view_name; " : null;
				}
				$post['domain_view'] = rtrim($domain_view, ';');
				$log_message_views = rtrim(trim($log_message_views), ';');
			}
		}
		if (!isset($post['domain_view']) || !$post['domain_view']) $post['domain_view'] = 0;

		/** Format domain_name_servers */
		$log_message_name_servers = '';
		if (isset($post['domain_name_servers'])) {
			foreach ($post['domain_name_servers'] as $val) {
				if ($val == '0') {
					$domain_name_servers = 0;
					$log_message_name_servers = getServerName($val);
					break;
				}
				$domain_name_servers .= $val . ';';
				$log_message_name_servers .= $val ? getServerName($val) . '; ' : null;
			}
			$post['domain_name_servers'] = rtrim($domain_name_servers, ';');
		}
		if (!isset($post['domain_name_servers']) || !$post['domain_name_servers']) $post['domain_name_servers'] = 0;

		/** Get clone parent values */
		if (isset($post['domain_clone_domain_id']) && $post['domain_clone_domain_id']) {
			$query = "SELECT * FROM `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domains` WHERE domain_id={$post['domain_clone_domain_id']}";
			$fmdb->query($query);
			if (!$fmdb->num_rows) return __('Cannot find cloned zone.');
			
			$parent_domain = $fmdb->last_result;
			foreach ($parent_domain[0] as $field => $value) {
				if (in_array($field, array('domain_id', 'domain_template_id'))) continue;
				if ($field == 'domain_clone_domain_id') {
					$sql_values .= $post['domain_clone_domain_id'] . ', ';
				} elseif ($field == 'domain_name') {
					$log_message .= 'Name: ' . displayFriendlyDomainName($post['domain_name']) . "\n";
					$log_message .= "Clone of: $value\n";
					$sql_values .= "'" . $post['domain_name'] . "', ";
				} elseif ($field == 'domain_view') {
					$log_message .= "Views: $log_message_views\n";
					$sql_values .= "'" . $post['domain_view'] . "', ";
				} elseif ($field == 'domain_name_servers' && $post['domain_name_servers']) {
					$log_message .= "Servers: $log_message_name_servers\n";
					$sql_values .= "'" . $post['domain_name_servers'] . "', ";
				} elseif ($field == 'domain_reload') {
					$sql_values .= "'no', ";
				} elseif ($field == 'domain_clone_dname') {
					$log_message .= "Use DNAME RRs: {$post['domain_clone_dname']}\n";
					$sql_values .= $post['domain_clone_dname'] ? "'" . $post['domain_clone_dname'] . "', " : 'NULL, ';
				} elseif ($field == 'domain_comment') {
					$log_message .= $post['domain_comment'] ? formatLogKeyData('domain_', 'domain_comment', $post['domain_comment']) : null;
					$sql_values .= $post['domain_comment'] ? "'" . $post['domain_comment'] . "', " : 'NULL, ';
				} else {
					$sql_values .= (isset($value) && strlen($value)) ? "'" . $value . "', " : 'NULL, ';
				}
				$sql_fields .= $field . ', ';
			}
			$sql_fields = rtrim($sql_fields, ', ') . ')';
			$sql_values = rtrim($sql_values, ', ');
		} else {
			$exclude = array_merge((array) $global_form_field_excludes, array('domain_id', 'domain_required_servers', 'domain_forward', 'domain_clone_domain_id', 'domain_redirect_url'));
		
			foreach ($post as $key => $data) {
				if (!in_array($key, $exclude)) {
					$sql_fields .= $key . ',';
					if (is_array($data)) $data = implode(';', $data);
					$sql_values .= (isset($data) && strlen($data)) ? "'" . $data . "'," : 'NULL,';
					if ($key == 'domain_view') $data = rtrim($log_message_views, '; ');
					if ($key == 'domain_name_servers') $data = rtrim($log_message_name_servers, '; ');
					if ($key == 'soa_id') {
						$soa_name = $data ? getNameFromID($data, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'soa', 'soa_', 'soa_id', 'soa_name') : 'Custom';
						$log_message .= formatLogKeyData('_id', $key, $soa_name);
					} elseif ($key == 'domain_template_id') {
						$log_message .= formatLogKeyData(array('domain_', '_id'), $key, getNameFromID($data, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name'));
					} elseif ($key == 'domain_key_id') {
						$log_message .= formatLogKeyData(array('domain_', '_id'), $key, getNameFromID($data, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'keys', 'key_', 'key_id', 'key_name'));
					} else {
						$log_message .= $data ? formatLogKeyData('domain_', $key, $data) : null;
					}
					if ($key == 'domain_default' && $data == 'yes') {
						$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domains` SET $key = 'no' WHERE `account_id`='{$_SESSION['user']['account_id']}'";
						$fmdb->query($query);
					}
				}
			}
			$sql_fields .= 'account_id)';
			$sql_values .= "'{$_SESSION['user']['account_id']}'";
		}
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError($dbupdate_error_msg, 'sql');
		}

		$insert_id = $fmdb->insert_id;
		
		/** Add mandatory config options */
		$query = "INSERT INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config` 
			(account_id,domain_id,cfg_name,cfg_data) VALUES ({$_SESSION['user']['account_id']}, $insert_id, ";
		if (!$post['domain_template_id']) {
			if (isset($post['domain_required_servers'])) $required_servers = $post['domain_required_servers'];
			if ($post['domain_type'] == 'forward') {
				$result = $fmdb->query($query . "'forwarders', '" . $required_servers . "')");
				$log_message .= formatLogKeyData('domain_', 'forwarders', $required_servers);

				$domain_forward = $post['domain_forward'][0];
				$result = $fmdb->query($query . "'forward', '" . $domain_forward . "')");
				$log_message .= formatLogKeyData('domain_', 'forward', $domain_forward);
			} elseif (in_array($post['domain_type'], array('secondary', 'stub'))) {
				$result = $fmdb->query($query . "'primaries', '" . $required_servers . "')");
				global $fm_dns_acls;
				if (!class_exists('fm_dns_acls')) {
					include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_acls.php');
				}
				$log_message .= formatLogKeyData('domain_', 'primaries', $fm_dns_acls->parseACL($required_servers));
			} elseif (isset($post['domain_redirect_url'])) {
				if (!class_exists('fm_dns_records')) {
					include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_records.php');
				}
				$record_array = array(
					'record_name' => '@',
					'record_value' => $post['domain_redirect_url']
				);
				$fm_dns_records->add($insert_id, 'URL', $record_array);
				$record_array = array(
					'record_name' => '*',
					'record_value' => '@',
					'record_append' => 'no'
				);
				$fm_dns_records->add($insert_id, 'CNAME', $record_array);
			}
		}

		if ($fmdb->sql_errors) {
			return formatError($dbupdate_error_msg, 'sql');
		}

		/** Add DNSSEC inline signing zone options */
		if (isset($post['domain_dnssec_sign_inline']) && $post['domain_dnssec_sign_inline'] == 'yes') {
			foreach (array('inline-signing' => 'yes', 'key-directory' => '"$ZONES/keys"', 'dnssec-policy' => 'default') as $param => $val) {
				$result = $fmdb->query($query . "'$param', '$val')");
				$log_message .= formatLogKeyData('domain_', $param, $val);

				if ($fmdb->sql_errors) {
					return formatError($dbupdate_error_msg, 'sql');
				}
			}
		}
		
		$this->updateSOASerialNo($insert_id, 0);
		
		addLogEntry($log_message);
		
		/* Set the server_build_config flag for servers */
		if ((((isset($post['domain_clone_domain_id']) && $post['domain_clone_domain_id']) ||
			(isset($post['domain_template_id']) && $post['domain_template_id'])) &&
			getSOACount($insert_id) && getNSCount($insert_id)) ||
			$post['domain_type'] != 'primary'){
				setBuildUpdateConfigFlag(getZoneServers($insert_id, array('masters', 'slaves')), 'yes', 'build');
		}
		
		/* Update the user/group limited access */
		$this->updateUserZoneAccess($insert_id);
		
		return $insert_id;
	}

	/**
	 * Updates the selected zone
	 */
	function update() {
		global $fmdb, $__FM_CONFIG, $global_form_field_excludes;
		
		/** Validate post */
		if (isset($_POST['domain_id'])) {
			$_POST['domain_mapping'] = getNameFromID($_POST['domain_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_mapping');
			$_POST['domain_type'] = getNameFromID($_POST['domain_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_type');
		}
		
		$post = $this->validatePost($_POST);
		if (!is_array($post)) return $post;

		if (array_key_exists('group_name', $_POST)) {
			$new_domain_ids = $post['group_domain_ids'];
			
			/** Get current domain_ids for group */
			$current_domain_ids = $this->getZoneGroupMembers($post['group_id']);
			
			/** Remove group from domain_ids for group */
			$remove_domain_ids = array_diff($current_domain_ids, $new_domain_ids);
			$retval = $this->setZoneGroupMembers($post['group_id'], $remove_domain_ids, 'remove');
			if ($retval !== true) {
				return $retval;
			}
			
			/** Add group to domain_ids */
			$add_domain_ids = array_diff($new_domain_ids, $current_domain_ids);
			$retval = $this->setZoneGroupMembers($post['group_id'], $add_domain_ids);
			if ($retval !== true) {
				return $retval;
			}
			
			/** Update group_name */
			$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domain_groups` SET `group_name`='" . $post['group_name'] . "', `group_comment`='" . $post['group_comment'] . "' WHERE account_id='{$_SESSION['user']['account_id']}' AND `group_id`='" . $post['group_id'] . "'";
			$fmdb->query($query);
			if ($fmdb->sql_errors) {
				return formatError(__('Could not update the zone group because a database error occurred.'), 'sql');
			}

			$old_name = getNameFromID($post['group_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domain_groups', 'group_', 'group_id', 'group_name');
			$log_message = sprintf(__('Updated a zone group (%s) with the following details'), $old_name) . "\n";
			addLogEntry($log_message . __('Name') . ": {$post['group_name']}\n" . __('Associated Zones') . ": " . $this->getZoneLogDomainNames($new_domain_ids) . "\n" . _('Comment') . ": {$post['group_comment']}");
			return true;
		}

		$domain_id = $_POST['domain_id'];

		$dbupdate_error_msg = __('Could not update the zone because a database error occurred.');
		
		$sql_edit = $domain_name_servers = $domain_view = '';
		
		$old_name = displayFriendlyDomainName(getNameFromID($domain_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name'));
		$log_message = sprintf(__('Updated a zone (%s) with the following details'), $old_name) . ":\n";

		/** If changing zone to clone or different domain_type, are there any existing associated records? */
		if (!empty($post['domain_clone_domain_id']) && $post['domain_clone_domain_id']) {
			basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'records', $domain_id, 'record_', 'domain_id');
			if ($fmdb->num_rows) return __('There are associated records with this zone.');
		}
		
		/** Format domain_view */
		$log_message_views = '';
		if ($post['domain_view']) {
			foreach (explode(';', $post['domain_view']) as $val) {
				if ($val == 0) {
					$domain_view = 0;
					break;
				}
				$domain_view .= $val . ';';
				$view_name = getNameFromID($val, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'views', 'view_', 'view_id', 'view_name');
				$log_message_views .= $val ? "$view_name; " : null;
			}
			$post['domain_view'] = rtrim($domain_view, ';');
		}
		
		/** Format domain_name_servers */
		$log_message_name_servers = '';
		foreach ($post['domain_name_servers'] as $val) {
			if ($val == '0') {
				$domain_name_servers = 0;
				$log_message_name_servers = getServerName($val);
				break;
			}
			$domain_name_servers .= $val . ';';
			$log_message_name_servers .= $val ? getServerName($val) . '; ' : null;
		}
		$post['domain_name_servers'] = rtrim($domain_name_servers, ';');
		
		$exclude = array_merge($global_form_field_excludes, array('domain_id', 'domain_required_servers', 'domain_forward'));

		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= (isset($data) && strlen($data)) ? $key . "='$data', " : $key . '=NULL, ';
				if ($key == 'domain_view') $data = trim($log_message_views, '; ');
				if ($key == 'domain_name_servers') $data = trim($log_message_name_servers, '; ');
				if ($key == 'domain_key_id') {
					$log_message .= formatLogKeyData(array('domain_', '_id'), $key, getNameFromID($data, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'keys', 'key_', 'key_id', 'key_name'));
				} else {
					$log_message .= $data ? formatLogKeyData('domain_', $key, $data) : null;
				}
				if ($key == 'domain_default' && $data == 'yes') {
					$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domains` SET $key = 'no' WHERE `account_id`='{$_SESSION['user']['account_id']}'";
					$fmdb->query($query);
				}
			}
		}
		$sql_edit .= "domain_reload='no', domain_check_config='yes'";
		
		/** Set the server_build_config flag for existing servers */
		if ((getSOACount($domain_id) && getNSCount($domain_id)) || $post['domain_type'] != 'primary') {
			setBuildUpdateConfigFlag(getZoneServers($domain_id, array('masters', 'slaves')), 'yes', 'build');
		}

		/** Update the zone */
		$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domains` SET $sql_edit WHERE `domain_id`='$domain_id' AND `account_id`='{$_SESSION['user']['account_id']}'";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError($dbupdate_error_msg, 'sql');
		}
		
		$rows_affected = $fmdb->rows_affected;

		/** Update the child zones */
		if ($post['domain_template'] == 'yes') {
			$query_arr[] = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domains` SET domain_view='{$post['domain_view']}', domain_check_config='yes' WHERE `domain_template_id`='$domain_id' AND `account_id`='{$_SESSION['user']['account_id']}'";
			$query_arr[] = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domains` SET domain_name_servers='{$post['domain_name_servers']}', domain_check_config='yes' WHERE `domain_template_id`='$domain_id' AND `account_id`='{$_SESSION['user']['account_id']}'";
			foreach ($query_arr as $query) {
				$fmdb->query($query);

				if ($fmdb->sql_errors) {
					return formatError(__('Could not update the child zones because a database error occurred.'), 'sql');
				}

				$rows_affected += $fmdb->rows_affected;
			}
		}

		/** Add mandatory config options */
		$query = "INSERT INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config` 
			(account_id,domain_id,cfg_name,cfg_data) VALUES ({$_SESSION['user']['account_id']}, $domain_id, ";
		if (!$post['domain_template_id']) {
			$required_servers = $post['domain_required_servers'];
			if ($post['domain_type'] == 'forward') {
				if (getNameFromID($domain_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_', 'domain_id', 'cfg_data', null, "AND cfg_name='forwarders'") !== false) {
					basicUpdate("fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config", getNameFromID($domain_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_', 'domain_id', 'cfg_id', null, "AND cfg_name='forwarders'"), 'cfg_data', $required_servers, 'cfg_id');
				} else {
					$result = $fmdb->query($query . "'forwarders', '" . $required_servers . "')");
				}
				$rows_affected += $fmdb->rows_affected;
				if ($fmdb->sql_errors) {
					return formatError($dbupdate_error_msg, 'sql');
				}
				$log_message .= formatLogKeyData('domain_', 'forwarders', $required_servers);

				$domain_forward = $post['domain_forward'][0];
				if (getNameFromID($domain_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_', 'domain_id', 'cfg_data', null, "AND cfg_name='forward'") !== false) {
					basicUpdate("fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config", getNameFromID($domain_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_', 'domain_id', 'cfg_id', null, "AND cfg_name='forward'"), 'cfg_data', $domain_forward, 'cfg_id');
				} else {
					$result = $fmdb->query($query . "'forward', '" . $domain_forward . "')");
				}
				$rows_affected += $fmdb->rows_affected;
				$log_message .= formatLogKeyData('domain_', 'forward', $domain_forward);
			} elseif (in_array($post['domain_type'], array('secondary', 'stub'))) {
				if (getNameFromID($domain_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_', 'domain_id', 'cfg_data', null, "AND cfg_name='primaries'") !== false) {
					basicUpdate("fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config", getNameFromID($domain_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_', 'domain_id', 'cfg_id', null, "AND cfg_name='primaries'"), 'cfg_data', $required_servers, 'cfg_id');
				} else {
					$result = $fmdb->query($query . "'primaries', '" . $required_servers . "')");
				}
				$rows_affected += $fmdb->rows_affected;
				include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_acls.php');
				$log_message .= formatLogKeyData('domain_', 'primaries', $fm_dns_acls->parseACL($required_servers));
			}
		} else {
			/** Remove all zone config options */
			basicDelete("fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config", $domain_id, 'domain_id');
		}
		if ($fmdb->sql_errors) {
			return formatError($dbupdate_error_msg, 'sql');
		}

		/** Add DNSSEC inline signing zone options */
		if (!empty($post['domain_dnssec_sign_inline']) && $post['domain_dnssec_sign_inline'] == 'yes') {
			foreach (array('inline-signing' => 'yes', 'key-directory' => '"$ZONES/keys"', 'dnssec-policy' => 'default') as $param => $val) {
				if (!getNameFromID($domain_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_', 'domain_id', 'cfg_data', null, "AND cfg_name='$param'")) {
					$result = $fmdb->query($query . "'$param', '$val')");
				}
				$rows_affected += $fmdb->rows_affected;
				$log_message .= formatLogKeyData('domain_', $param, $val);

				if ($fmdb->sql_errors) {
					return formatError($dbupdate_error_msg, 'sql');
				}
			}
		}
		
		/** Return if there are no changes */
		if ($rows_affected + $fmdb->rows_affected == 0) return true;

		/** Set the server_build_config flag for new servers */
		if ((getSOACount($domain_id) && getNSCount($domain_id)) || $post['domain_type'] != 'primary') {
			setBuildUpdateConfigFlag(getZoneServers($domain_id, array('masters', 'slaves')), 'yes', 'build');
		}

		/** Delete associated records from fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}track_builds */
		basicDelete('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'track_builds', $domain_id, 'domain_id', false);

		/** Update the SOA serial number */
		if ($post['domain_type'] == 'primary') {
			$this->updateSOASerialNo($domain_id, getNameFromID($domain_id, "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domains", 'domain_', 'domain_id', 'soa_serial_no'));
		}

		addLogEntry($log_message);
		return true;
	}
	
	
	/**
	 * Deletes the selected zone and all associated records
	 */
	function delete($domain_id) {
		global $fmdb, $__FM_CONFIG;
		
		if (!zoneAccessIsAllowed(array($domain_id))) {
			return __('You do not have permission to delete this zone.');
		}
		
		/** Zone groups */
		if (isset($_POST['item_sub_type']) && $_POST['item_sub_type'] == 'groups') {
			$retval = $this->setZoneGroupMembers($domain_id, $this->getZoneGroupMembers($domain_id), 'remove');
			if ($retval !== true) {
				return $retval;
			}
			
			/** Delete zone group */
			$tmp_name = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domain_groups', 'group_', 'group_id', 'group_name');
			if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domain_groups', $domain_id, 'group_', 'deleted', 'group_id') === false) {
				return formatError(__('This zone group could not be deleted because a database error occurred.'), 'sql');
			}
			
			addLogEntry(sprintf(__("Deleted zone group '%s' and all associated records."), $tmp_name));
			
			return true;
		}
		
		/** Does the domain_id exist for this account? */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', $domain_id, 'domain_', 'domain_id', 'active');
		if ($fmdb->num_rows) {
			$domain_result = $fmdb->last_result[0];
			unset($fmdb->num_rows);
			
			/** Delete all associated configs */
			basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $domain_id, 'cfg_', 'domain_id');
			if ($fmdb->num_rows) {
				if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $fmdb->last_result[0]->cfg_id, 'cfg_', 'deleted', 'cfg_parent') === false || 
					updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $fmdb->last_result[0]->cfg_id, 'cfg_', 'deleted', 'cfg_id') === false) {
					return formatError(__('The associated configs for this zone could not be deleted because a database error occurred.'), 'sql');
				}
				unset($fmdb->num_rows);
			}
			
			/** Delete all associated records */
			basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'records', $domain_id, 'record_', 'domain_id');
			if ($fmdb->num_rows) {
				if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'records', $domain_id, 'record_', 'deleted', 'domain_id') === false) {
					return formatError(__('The associated records for this zone could not be deleted because a database error occurred.'), 'sql');
				}
				unset($fmdb->num_rows);
			}
			basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'records_skipped', $domain_id, 'record_', 'domain_id');
			if ($fmdb->num_rows) {
				if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'records_skipped', $domain_id, 'record_', 'deleted', 'domain_id') === false) {
					return formatError(__('The associated records for this zone could not be deleted because a database error occurred.'), 'sql');
				}
				unset($fmdb->num_rows);
			}
			
			/** Delete all associated SOA */
			if (!$domain_result->domain_clone_domain_id && $domain_result->soa_id) {
				basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'soa', $domain_result->soa_id, 'soa_', 'soa_id', "AND soa_template='no'");
				if ($fmdb->num_rows) {
					if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'soa', $domain_result->soa_id, 'soa_', 'deleted', 'soa_id') === false) {
						return formatError(__('The SOA for this zone could not be deleted because a database error occurred.'), 'sql');
					}
					unset($fmdb->num_rows);
				}
			}
			
			/** Force buildconf for all built DNS servers if zone has been built */
			$fmdb->query("SELECT * FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}track_builds WHERE domain_id=" . sanitize($domain_id));
			if ($fmdb->num_rows) {
				foreach ($fmdb->last_result as $tmp_build_array) {
					$tmp_built_dns_servers[] = $tmp_build_array->server_serial_no;
				}
			}
			if (@is_array($tmp_built_dns_servers)) {
				setBuildUpdateConfigFlag(implode(',', $tmp_built_dns_servers), 'yes', 'build');
			}
			
			/** Delete associated records from fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}track_builds */
			if (basicDelete('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'track_builds', $domain_id, 'domain_id', false) === false) {
				return formatError(sprintf(__('The zone could not be removed from the %s table because a database error occurred.'), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'track_builds'));
			}
			
			/** Delete cloned zones */
			basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', $domain_id, 'domain_', 'domain_clone_domain_id');
			if ($fmdb->num_rows) {
				unset($fmdb->num_rows);
				/** Delete cloned zone records first */
				basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_id', 'domain_', "AND domain_clone_domain_id=$domain_id");
				if ($fmdb->num_rows) {
					$clone_domain_result = $fmdb->last_result;
					$clone_domain_num_rows = $fmdb->num_rows;
					for ($i=0; $i<$clone_domain_num_rows; $i++) {
						/** Delete all associated configs */
						basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $clone_domain_result[$i]->domain_id, 'cfg_', 'domain_id');
						if ($fmdb->num_rows) {
							if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $fmdb->last_result[0]->cfg_id, 'cfg_', 'deleted', 'cfg_parent') === false || 
								updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $fmdb->last_result[0]->cfg_id, 'cfg_', 'deleted', 'cfg_id') === false) {
								return formatError(__('The associated configs for the cloned zones could not be deleted because a database error occurred.'), 'sql');
							}
							unset($fmdb->num_rows);
						}
						
						/** Delete all associated records */
						basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'records', $clone_domain_result[$i]->domain_id, 'record_', 'domain_id');
						if ($fmdb->num_rows) {
							if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'records', $clone_domain_result[$i]->domain_id, 'record_', 'deleted', 'domain_id') === false) {
								return formatError(__('The associated records for the cloned zones could not be deleted because a database error occurred.'), 'sql');
							}
							unset($fmdb->num_rows);
						}
						basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'records_skipped', $clone_domain_result[$i]->domain_id, 'record_', 'domain_id');
						if ($fmdb->num_rows) {
							if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'records_skipped', $clone_domain_result[$i]->domain_id, 'record_', 'deleted', 'domain_id') === false) {
								return formatError(__('The associated records for the cloned zones could not be deleted because a database error occurred.'), 'sql');
							}
							unset($fmdb->num_rows);
						}
					}
					unset($fmdb->num_rows);
				}
				
				if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', $domain_id, 'domain_', 'deleted', 'domain_clone_domain_id') === false) {
					return formatError(__('The associated clones for this zone could not be deleted because a database error occurred.'), 'sql');
				}
			}
			
			/** Delete zone */
			$tmp_name = displayFriendlyDomainName(getNameFromID($domain_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name'));
			if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', $domain_id, 'domain_', 'deleted', 'domain_id') === false) {
				return formatError(__('This zone could not be deleted because a database error occurred.'), 'sql');
			}
			
			addLogEntry("Deleted zone '$tmp_name' and all associated records.");
			
			return true;
		}
		
		return __('This zone does not exist.');
	}
	
	
	function displayRow($row, $map, $server_reload_allowed) {
		global $fmdb, $__FM_CONFIG;

		$classes = array();
		
		/** Zones */
		if ($map != 'groups') {
			if (!currentUserCan(array('access_specific_zones', 'view_all'), $_SESSION['module'], array(0, $row->domain_id))) return;

			$zone_access_allowed = zoneAccessIsAllowed(array($row->domain_id));

			if ($row->domain_status == 'disabled') $classes[] = 'disabled';
			$response = $add_new = '';
			$icons = array();

			$checkbox = (currentUserCan('reload_zones', $_SESSION['module'])) ? '<td></td>' : null;

			$soa_count = getSOACount($row->domain_id);
			$ns_count = getNSCount($row->domain_id);
			$reload_allowed = $server_reload_allowed ? reloadAllowed($row->domain_id) : false;
			if (!$soa_count && $row->domain_type == 'primary') {
				$response = __('The SOA record still needs to be created for this zone');
				$classes[] = 'attention';
			}
			if (!$ns_count && $row->domain_type == 'primary' && !$response) {
				$response = __('One or more NS records still needs to be created for this zone');
				$classes[] = 'attention';
			}

			/* DNSSEC checks */
			if ($row->domain_dnssec == 'yes' && $row->domain_dnssec_signed) {
				/** Get datetime formatting */
				$date_format = getOption('date_format', $_SESSION['user']['account_id']);
				$time_format = getOption('time_format', $_SESSION['user']['account_id']);

				$domain_dnssec_sig_expires = getDNSSECExpiration($row);

				$message = sprintf(__('The DNSSEC signature expires on %s.'), date($date_format . ' ' . $time_format . ' e', $domain_dnssec_sig_expires));
				$response = $message;
				if ($domain_dnssec_sig_expires <= strtotime('now')) {
					$classes[] = 'attention';
				} elseif ($domain_dnssec_sig_expires <= strtotime('now + 7 days')) {
					$classes[] = 'notice';
				}
			}

			if ($row->domain_type == 'primary' && $row->domain_clone_domain_id == 0 && currentUserCan('manage_zones', $_SESSION['module'])) {
				$add_new = displayAddNew($map, $row->domain_id, __('Clone this zone'), 'fa fa-clone', 'plus_subelement', 'bottom');
			}

			$clones = $this->cloneDomainsList($row->domain_id);
			$clone_names = $clone_types = $clone_views = $clone_counts = $clone_servers = $clone_comment = '';
			foreach ($clones as $clone_id => $clone_array) {
				$clone_names .= '<p class="subelement' . $clone_id . '"><span><a href="' . $clone_array['clone_link'] . '" title="' . __('Edit zone records') . '">' . $clone_array['clone_name'] . 
						'</a></span>' . $clone_array['clone_options'] . $clone_array['dnssec'] . $clone_array['dynamic'] . $clone_array['clone_edit'] . $clone_array['clone_delete'] . "</p>\n";
				$clone_types .= '<p class="subelement' . $clone_id . '">' . __('clone') . '</p>' . "\n";
				$clone_views .= '<p class="subelement' . $clone_id . '">' . $this->IDs2Name($clone_array['clone_views'], 'view') . "</p>\n";
				$clone_servers .= '<p class="subelement' . $clone_id . '">' . $this->IDs2Name($clone_array['clone_servers'], 'server') . "</p>\n";
				$clone_comment .= '<p class="subelement' . $clone_id . '">' . $clone_array['clone_comment'] . "</p>\n";
				$clone_counts_array = explode('|', $clone_array['clone_count']);
				$clone_counts .= '<p class="subelement' . $clone_id . '" title="' . __('Differences from parent zone') . '">';
				if ($clone_counts_array[0]) $clone_counts .= '<span class="record-additions">' . $clone_counts_array[0] . '</span>&nbsp;';
				if ($clone_counts_array[1]) $clone_counts .= '&nbsp;<span class="record-subtractions">' . $clone_counts_array[1] . '</span> ';
				if (!array_sum($clone_counts_array)) $clone_counts .= '-';
				$clone_counts .= "</p>\n";
			}
			if ($clone_names) $classes[] = 'subelements';

			if ($soa_count && $row->domain_reload == 'yes' && $reload_allowed) {
				if (currentUserCan('reload_zones', $_SESSION['module']) && $zone_access_allowed) {
					$reload_zone = '<form name="reload" id="' . $row->domain_id . '" method="post" action="' . $GLOBALS['basename'] . '?map=' . $map . '"><input type="hidden" name="action" value="reload" /><input type="hidden" name="domain_id" id="domain_id" value="' . $row->domain_id . '" />' . $__FM_CONFIG['icons']['reload'] . '</form>';
					$checkbox = '<td><input type="checkbox" name="domain_list[]" value="' . $row->domain_id .'" /></td>';
				} else {
					$reload_zone = __('Reload Available') . '<br />';
				}
			} else $reload_zone = null;
			if ($reload_zone) $classes[] = 'build';

			$edit_status = null;

			if (!$soa_count && $row->domain_type == 'primary' && currentUserCan('manage_zones', $_SESSION['module'])) $type = 'SOA';
			elseif (!$ns_count && $row->domain_type == 'primary' && currentUserCan('manage_zones', $_SESSION['module'])) $type = 'NS';
			else {
				$type = ($row->domain_mapping == 'forward') ? 'ALL' : 'PTR';
			}
			if ($soa_count && $ns_count && $row->domain_type == 'primary') {
				$edit_status = '<a href="preview.php" onclick="javascript:void window.open(\'preview.php?server_serial_no=-1&config=zone&domain_id=' . $row->domain_id . '\',\'1356124444538\',\'' . $__FM_CONFIG['default']['popup']['dimensions'] . ',toolbar=0,menubar=0,location=0,status=0,scrollbars=1,resizable=1,left=0,top=0\');return false;">' . $__FM_CONFIG['icons']['preview'] . '</a>';
			}
			if (currentUserCan('manage_zones', $_SESSION['module']) && $zone_access_allowed) {
				$edit_status .= '<a class="edit_form_link" name="' . $map . '" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
				$edit_status .= '<a class="delete" href="#">' . $__FM_CONFIG['icons']['delete'] . '</a>' . "\n";
			}

			$dynamic_zone = getNameFromID($row->domain_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_dynamic');
			if (currentUserCan('manage_records', $_SESSION['module']) && $zone_access_allowed && $dynamic_zone == 'yes') {
				$type .= '&load=zone';
			}
			$domain_name = displayFriendlyDomainName($row->domain_name);
			$edit_name = ($row->domain_type == 'primary') ? "<a href=\"zone-records.php?map={$map}&domain_id={$row->domain_id}&record_type=$type\" title=\"" . __('Edit zone records') . "\">$domain_name</a>" : $domain_name;
			$domain_view = $this->IDs2Name($row->domain_view, 'view');

			$record_count = '';
			if ($row->domain_type == 'primary') {
				$query = "SELECT COUNT(*) record_count FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}records WHERE account_id={$_SESSION['user']['account_id']} AND domain_id={$row->domain_id} AND record_status!='deleted'";
				$fmdb->query($query);
				$record_count = formatNumber($fmdb->last_result[0]->record_count);
			}

			if (in_array($row->domain_type, array('primary', 'secondary')) && (currentUserCan(array('manage_zones', 'view_all'), $_SESSION['module']) || $zone_access_allowed)) {
				$icons[] = sprintf('<a href="config-options.php?domain_id=%d" class="tooltip-bottom mini-icon" data-tooltip="%s"><i class="mini-icon fa fa-sliders" aria-hidden="true"></i></a>', $row->domain_id, __('Configure Additional Options'));
			}

			if ($row->domain_type == 'primary') {
				basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'records', $row->domain_id, 'record_', 'domain_id', 'AND record_type="URL" AND record_name="@"');
				if ($fmdb->num_rows) {
					$domain_redirect_url = $fmdb->last_result[0]->record_value;
					$icons[] = sprintf('<a href="JavaScript:void(0);" class="tooltip-bottom mini-icon" data-tooltip="%s"><i class="fa fa-globe" aria-hidden="true"></i></a>', sprintf(__('This domain redirects to %s'), $domain_redirect_url));
					if (!getOption('url_rr_web_servers', $_SESSION['user']['account_id'], $_SESSION['module'])) {
						$response = __('There are no URL RR web servers defined in the Settings to support the URL resource records.');
						$classes[] = 'attention';
					}
				}
			}

			if (getNameFromID($row->domain_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_dnssec') == 'yes') {
				basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'keys', 'key_id', 'key_', 'AND domain_id=' . $row->domain_id);
				if ($fmdb->num_rows) {
					$icons[] = sprintf('<div class="mini-icon tooltip-copy nowrap"><span><b>%s:</b><br /><textarea>' . trim($row->domain_dnssec_ds_rr) . '</textarea><p>%s</p></span><i class="fa fa-lock secure"></i></div>', __('Zone DS RRset'), __('Click to copy'), __('Zone is secured with DNSSEC'));
				} else {
					$icons[] = sprintf('<a href="JavaScript:void(0);" class="tooltip-bottom mini-icon" data-tooltip="%s"><i class="mini-icon fa fa-lock insecure" aria-hidden="true"></i></a>', __('Zone is configured but not secured with DNSSEC'));
					$response = __('There are no DNSSEC keys defined for this zone.');
					$classes[] = 'attention';
				}
				$icons[] = sprintf('<a href="config-keys.php?type=dnssec&domain_id[]=%d" class="tooltip-bottom mini-icon" data-tooltip="%s"><i class="mini-icon fa fa-key secure" aria-hidden="true"></i></a>', $row->domain_id, __('Manage zone DNSSEC keys'));
			}
			if ($dynamic_zone == 'yes') {
				$icons[] = sprintf('<a href="JavaScript:void(0);" class="tooltip-bottom mini-icon" data-tooltip="%s"><i class="mini-icon fa fa-share-alt" aria-hidden="true"></i></a>', __('Zone supports dynamic updates'));
			}
			if ($domain_template_id = getNameFromID($row->domain_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_template_id')) {
				$icons[] = sprintf('<a href="JavaScript:void(0);" class="tooltip-bottom mini-icon" data-tooltip="%s"><i class="mini-icon fa fa-picture-o" aria-hidden="true"></i></a>', sprintf(__('Based on %s'), getNameFromID($domain_template_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name')));
			}

			$response = ($response) ? sprintf('<a href="#" class="tooltip-bottom mini-icon" data-tooltip="%s"><i class="fa fa-question-circle" aria-hidden="true"></i></a>', $response) : null;

			$class = 'class="' . implode(' ', $classes) . '"';
			if (is_array($icons)) {
				$icons = implode(' ', $icons);
			}
			$comments = ($row->domain_comment) ? nl2br($row->domain_comment) : '&nbsp;';
			$domain_servers = $this->IDs2Name($row->domain_name_servers, 'server');

			echo <<<HTML
		<tr id="$row->domain_id" name="$row->domain_name" $class>
			$checkbox
			<td>$row->domain_id</td>
			<td><b>$edit_name</b> $icons $add_new $response $clone_names</td>
			<td>$row->domain_type
				$clone_types</td>
			<td>$domain_view
				$clone_views</td>
			<td>$domain_servers
				$clone_servers</td>
			<td>$comments
				$clone_comment</td>
			<td align="center">$record_count
				$clone_counts</td>
			<td class="column-actions">
				$reload_zone
				$edit_status
			</td>
		</tr>

HTML;
		} else {
			if ($row->group_status == 'disabled') $classes[] = 'disabled';
			
			$class = 'class="' . implode(' ', $classes) . '"';
			$checkbox = null;

			/** Get domains_ids associated with group_id */
			$group_domain_ids = $this->getZoneGroupMembers($row->group_id);
			foreach ($group_domain_ids as $domain_id) {
				(string) $domain_names .= getNameFromID($domain_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name') . '<br />';
			}
			
			if (currentUserCan('manage_zones', $_SESSION['module'])) {
				$edit_status = '<td class="column-actions">';
				$edit_status .= '<a class="edit_form_link" name="' . $map . '" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
				$edit_status .= '<a class="status_form_link" href="#" rel="';
				$edit_status .= ($row->group_status == 'active') ? 'disabled' : 'active';
				$edit_status .= '">';
				$edit_status .= ($row->group_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
				$edit_status .= '</a>';
				$edit_status .= '<a href="#" name="' . $map . '" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
				$edit_status .= '</td>';
				$checkbox = '<input type="checkbox" name="bulk_list[]" value="' . $row->group_id .'" />';
			} else {
				$edit_status = null;
			}
			
			echo <<<HTML
		<tr id="$row->group_id" name="$row->group_name" $class>
			<td>$checkbox</td>
			<td>$row->group_name</td>
			<td>$domain_names</td>
			<td>$row->group_comment</td>
			$edit_status
		</tr>

HTML;
		}
	}

	/**
	 * Displays the form to add new zone
	 */
	function printForm($data = '', $action = 'create', $map = 'forward', $show = array('popup', 'template_menu', 'create_template')) {
		/** Zone groups */
		if ($map == 'groups') {
			return $this->printGroupsForm($data, $action);
		}
		
		global $fmdb, $__FM_CONFIG, $fm_dns_acls, $fm_module_options, $fm_dns_masters;
		
		$domain_id = $domain_name_servers = $domain_key_id = 0;
		$domain_view = -1;
		$domain_type = $domain_clone_domain_id = $domain_name = $template_name = $domain_ttl = $domain_comment = null;
		$addl_zone_options = $domain_dynamic = $domain_template = $domain_dnssec = null;
		$domain_dnssec_sig_expire = $domain_dnssec_sign_inline = $domain_dnssec_generate_ds = $domain_dnssec_parent_domain_id = null;
		$disabled = ($action == 'create') ? null : 'disabled';
		
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
			$domain_template_id = getNameFromID($domain_clone_domain_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_template_id');
			if ($domain_template_id) {
				$domain_name_servers = getNameFromID($domain_template_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name_servers');
			} else {
				$domain_name_servers = getNameFromID($domain_clone_domain_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name_servers');
			}
		}
		
		$domain_name = ($domain_name && function_exists('idn_to_utf8')) ? idn_to_utf8($domain_name, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) : $domain_name;
		
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
		$domain_name_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_name');
		$domain_ttl_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_ttl');

		$views = buildSelect('domain_view', 'domain_view', availableViews('active'), $domain_view, 4, null, true);
		$zone_maps = buildSelect('domain_mapping', 'domain_mapping', enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains','domain_mapping'), $map, 1, $disabled);
		$domain_types = buildSelect('domain_type', 'domain_type', array_merge(enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains','domain_type'), array('url-redirect')), $domain_type, 1, $disabled);
		$clone = buildSelect('domain_clone_domain_id', 'domain_clone_domain_id', $this->availableCloneDomains($map, $domain_id), $domain_clone_domain_id, 1, $disabled);
		$name_servers = buildSelect('domain_name_servers', 'domain_name_servers', availableServers('id'), $domain_name_servers, 1, null, true);

		$forwarders_show = $masters_show = 'none';
		$domain_forward_servers = $domain_master_servers = $domain_forward = '';
		$available_acls = $available_masters = json_encode(array());
		if ($action == 'create') {
			$available_masters = $fm_dns_masters->buildMasterJSON($domain_master_servers);
		}
		if ($domain_type == 'forward') {
			$forwarders_show = 'block';
			$domain_forward_servers = str_replace(';', "\n", rtrim(str_replace(' ', '', getNameFromID($domain_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_', 'domain_id', 'cfg_data', null, "AND cfg_name='forwarders'")), ';'));
			$domain_forward = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_', 'domain_id', 'cfg_data', null, "AND cfg_name='forward'");
			$available_acls = $fm_dns_acls->buildACLJSON($domain_forward_servers, 0, 'none');
		} elseif (in_array($domain_type, array('secondary', 'stub'))) {
			$masters_show = 'block';
			$domain_master_servers = str_replace(';', "\n", rtrim(str_replace(' ', '', getNameFromID($domain_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_', 'domain_id', 'cfg_data', null, "AND cfg_name='primaries'")), ';'));
			if ($domain_master_servers) $available_masters = $fm_dns_masters->buildMasterJSON($domain_master_servers);
		}

		$keys = ($domain_key_id) ? array($domain_key_id) : null;
		$keys = buildSelect('domain_key_id', 'domain_key_id', availableItems('key', 'blank', 'AND `key_type`="tsig"'), $keys, 1, '', false);

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
			if (getNameFromID($domain_clone_domain_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_template_id')) {
				$clone_override_show = $clone_dname_options_show = 'none';
				$clone_dname_checked = null;
			}
		} else {
			$clone_override_show = $clone_dname_options_show = 'none';
			$clone_dname_checked = null;
		}
		$clone_dname_dropdown = buildSelect('domain_clone_dname', 'domain_clone_dname', enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains','domain_clone_dname'), $domain_clone_dname);
		
		$additional_config_link = ($action == 'create' || !in_array($domain_type, array('primary', 'secondary'))) || !currentUserCan('manage_servers', $_SESSION['module']) ? null : sprintf('<tr class="include-with-template"><td></td><td><span><a href="config-options.php?domain_id=%d">%s</a></span></td></tr>', $domain_id, __('Configure Additional Options') . ' &raquo;');
		
		$popup_title = $action == 'create' ? __('Add Zone') : __('Edit Zone');
		$popup_header = buildPopup('header', $popup_title);
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
		} else {
			$dynamic_checked = ($domain_dynamic == 'yes') ? 'checked' : null;
			$dynamic_show = ($domain_type == 'primary' || $domain_template_id) ? 'table-row' : 'none';
			$addl_zone_options = sprintf('<tr class="include-with-template" id="dynamic_updates" style="display: %s">
			<th>%s</th>
			<td><input type="checkbox" id="domain_dynamic" name="domain_dynamic" value="yes" %s /><label for="domain_dynamic"> %s</label></td>
		</tr>', $dynamic_show, __('Support Dynamic Updates'), $dynamic_checked, __('yes (experimental)'));
			
			if ($domain_dnssec == 'yes') {
				$dnssec_checked = 'checked';
				$dnssec_style = 'block';
			} else {
				$dnssec_checked = null;
				$dnssec_style = 'none';
			}
			if ($domain_dnssec_generate_ds == 'yes') {
				$dnssec_ds_style = 'block';
				$dnssec_generate_ds_checked = 'checked';
			} else {
				$dnssec_ds_style = 'none';
				$dnssec_generate_ds_checked = null;
			}
			if (!$domain_dnssec_sig_expire) $domain_dnssec_sig_expire = null;
			$domain_dnssec_sign_inline_checked = ($domain_dnssec_sign_inline == 'yes') ? 'checked' : null;
			
			$available_zones = array_reverse($this->availableZones('all', 'primary', 'restricted'));
			$available_zones[] = array(null, 0);
			$available_zones = buildSelect('domain_dnssec_parent_domain_id', 'domain_dnssec_parent_domain_id', array_reverse($available_zones), $domain_dnssec_parent_domain_id);
			$dnssec_show = ($domain_type == 'primary' || $domain_template_id) ? 'table-row' : 'none';

			$addl_zone_options .= sprintf('<tr class="include-with-template" id="enable_dnssec" style="display: %s">
			<th>%s</th>
			<td>
				<input type="checkbox" id="domain_dnssec" name="domain_dnssec" value="yes" %s /><label for="domain_dnssec"> %s</label> <a href="#" class="tooltip-top" data-tooltip="%s"><i class="fa fa-question-circle"></i></a>
				<div id="dnssec_option" style="display: %s;">
					<h4>%s</h4>
					<label for="domain_dnssec_sig_expire">%s</label> <input type="text" id="domain_dnssec_sig_expire" name="domain_dnssec_sig_expire" value="%s" placeholder="%s" style="width: 5em;" onkeydown="return validateNumber(event)" /> 
					<a href="#" class="tooltip-top" data-tooltip="%s"><i class="fa fa-question-circle"></i></a><br />
					<input type="checkbox" id="domain_dnssec_sign_inline" name="domain_dnssec_sign_inline" value="yes" %s /><label for="domain_dnssec_sign_inline"> %s</label> <a href="#" class="tooltip-left" data-tooltip="%s"><i class="fa fa-question-circle"></i></a><br />
					<input type="checkbox" id="domain_dnssec_generate_ds" name="domain_dnssec_generate_ds" value="yes" %s /><label for="domain_dnssec_generate_ds"> %s</label>
				</div>
				<div id="dnssec_ds_option" style="display: %s;">
					<h4>%s:</h4>
					%s
				</div>
			</td>
		</tr>', $dnssec_show, __('Enable DNSSEC'), $dnssec_checked, __('yes (experimental)'), 
				sprintf(__('The dnssec-signzone and dnssec-keygen utilities must be installed on %s in order for this to work.'), php_uname('n')), $dnssec_style,
				__('Signature Expiry Override (optional)'), __('Days'), $domain_dnssec_sig_expire, getOption('dnssec_expiry', $_SESSION['user']['account_id'], $_SESSION['module']),
				sprintf(__('Enter the number of days to expire the signature if different from what is defined in the %s.'), _('Settings')),
				$domain_dnssec_sign_inline_checked, __('Automatically resign the zone'), __('This enables the inline-signing, dnssec-policy, and key-directory options which allows BIND to automatically resign the zone.'),
				$dnssec_generate_ds_checked, __('Generate DS RR during signing'),
				$dnssec_ds_style, __('Include DS RR in selected parent zone'), $available_zones
			);
		}
		
		$return_form = (array_search('popup', $show) !== false) ? $popup_header . '<form name="manage" id="manage">' : null;
		
		$return_form .= sprintf('<input type="hidden" name="page" value="zones" />
			<input type="hidden" name="action" value="%s" />
			<input type="hidden" name="domain_id" value="%d" />
			<table class="form-table %s">
				<tr class="include-with-template">
					<th><label for="domain_name">%s</label></th>
					<td><input type="text" id="domain_name" name="domain_name" size="40" value="%s" maxlength="%d" class="required" /></td>
				</tr>
				%s
				<tr class="include-with-template">
					<th><label for="domain_view">%s</label> <a href="#" class="tooltip-top" data-tooltip="%s"><i class="fa fa-question-circle"></i></a></th>
					<td>%s
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
							<input type="hidden" name="domain_required_servers[forwarders]" id="domain_required_servers" class="address_match_element required" data-placeholder="%s" value="%s" /><br />
							( address_match_element )
						</div>
						<div id="define_masters" style="display: %s">
							<input type="hidden" name="domain_required_servers[primaries]" id="domain_required_servers" class="address_match_element required" data-placeholder="%s" value="%s" /><br />
							( address_match_element )
						</div>
						<div id="define_redirect_url" style="display: none">
							<h4>%s <a href="JavaScript:void(0);" class="tooltip-top mini-icon" data-tooltip="%s"><i class="fa fa-question-circle" aria-hidden="true"></i></a></h4>
							<input type="text" id="domain_redirect_url" name="domain_redirect_url" size="40" value="" class="required" />
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
								<h4>%s</h4>
								%s
							</div>
						</div>
					</td>
				</tr>
				<tr>
					<th><label for="domain_name_servers">%s</label></th>
					<td>%s</td>
				</tr>
				<tr class="include-with-template">
					<th><label for="domain_ttl">%s</label> <a href="#" class="tooltip-top" data-tooltip="%s"><i class="fa fa-question-circle"></i></a></th>
					<td><input type="text" id="domain_ttl" name="domain_ttl" size="40" value="%s" maxlength="%d" onkeydown="return validateTimeFormat(event, this)" /></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="view_key_id">%s</label> <a href="#" class="tooltip-top" data-tooltip="%s"><i class="fa fa-question-circle"></i></a></th>
					<td width="67&#37;">%s</td>
				</tr>
				%s
				<tr class="include-with-template">
					<th width="33&#37;" scope="row"><label for="domain_comment">%s</label></th>
					<td width="67&#37;"><textarea id="domain_comment" name="domain_comment" rows="4" cols="40">%s</textarea></td>
				</tr>
				%s
			</table>',
				$action, $domain_id, $classes,
				__('Domain Name'), $domain_name, $domain_name_length,
				$select_template,
				__('Views'), __('Leave blank to use the views defined in the template.'), $views,
				__('Zone Map'), $zone_maps,
				__('Zone Type'), $domain_types,
				$forwarders_show, $forward_dropdown, __('Define forwarders'), $domain_forward_servers,
				$masters_show, __('Define primaries'), $domain_master_servers,
				__('Redirect to URL'), __('Requests to this zone will be redirected to the specified URL'),
				__('Clone Of (optional)'), $clone, $clone_override_show, $clone_dname_checked,
				__('Override DNAME Resource Record Setting'), $clone_dname_options_show,
				__('Use DNAME Resource Records for Clones'), $clone_dname_dropdown,
				__('DNS Servers'), $name_servers,
				__('Domain TTL'), __('Leave blank to use the $TTL from the SOA.'), $domain_ttl, $domain_ttl_length,
				__('Zone Transfer Key'), __('Optionally specify a key for transferring this zone (overrides this setting in views).'), $keys,
				$soa_templates,
				_('Comment'), $domain_comment,
				$addl_zone_options . $additional_config_link . $create_template . $template_name
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
				$("#define_masters .address_match_element").select2({
					createSearchChoice:function(term, data) { 
						if ($(data).filter(function() { 
							return this.text.localeCompare(term)===0; 
						}).length===0) 
						{return {id:term, text:term};} 
					},
					multiple: true,
					width: '300px',
					tokenSeparators: [",", ";"],
					data: $available_masters
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
		global $fmdb, $__FM_CONFIG, $user_capabilities;
		
		$return = array();
		$limited_ids = '';
		if (isset($user_capabilities)) {
			$limited_ids = (isset($user_capabilities[$_SESSION['module']]) && (array_key_exists('access_specific_zones', $user_capabilities[$_SESSION['module']]) && !array_key_exists('view_all', $user_capabilities[$_SESSION['module']]) && $user_capabilities[$_SESSION['module']]['access_specific_zones'][0])) ? 'AND domain_id IN (' . join(',', $this->getZoneAccessIDs($user_capabilities[$_SESSION['module']]['access_specific_zones'])) . ')' : null;
		}
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', $domain_id, 'domain_', 'domain_clone_domain_id', $limited_ids . ' AND domain_template="no" ORDER BY domain_name');
		if ($fmdb->num_rows) {
			$count = $fmdb->num_rows;
			$clone_results = $fmdb->last_result;
			for ($i=0; $i<$count; $i++) {
				$clone_id = $clone_results[$i]->domain_id;
				$return[$clone_id]['clone_name'] = $clone_results[$i]->domain_name;
				$return[$clone_id]['clone_link'] = 'zone-records.php?map=' . $clone_results[$i]->domain_mapping . '&domain_id=' . $clone_id;
				$return[$clone_id]['clone_options'] = sprintf('<a href="config-options.php?domain_id=%d" class="tooltip-bottom mini-icon" data-tooltip="%s"><i class="mini-icon fa fa-sliders" aria-hidden="true"></i></a>', $clone_id, __('Configure Additional Options'));
				
				/** Delete permitted? */
				if (currentUserCan(array('manage_zones'), $_SESSION['module'], array(0, $domain_id)) &&
					currentUserCan(array('access_specific_zones'), $_SESSION['module'], array(0, $domain_id))) {
					$return[$clone_id]['clone_edit'] = sprintf('<a class="subelement_edit tooltip-bottom mini-icon" name="' . $clone_results[$i]->domain_mapping . '" href="#" id="' . $clone_id . '" data-tooltip="%s">' . $__FM_CONFIG['icons']['edit'] . '</a>', _('Edit'));
					$return[$clone_id]['clone_delete'] = str_replace('__ID__', $clone_id, $__FM_CONFIG['module']['icons']['sub_delete']);
				} else {
					$return[$clone_id]['clone_delete'] = $return[$clone_id]['clone_edit'] = null;
				}
				
				/** Clone record counts */
				$query = "SELECT COUNT(*) record_count FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}records WHERE account_id={$_SESSION['user']['account_id']} AND domain_id={$clone_id} AND record_status!='deleted'";
				$fmdb->query($query);
				$return[$clone_id]['clone_count'] = $fmdb->last_result[0]->record_count;
				basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'records_skipped', $clone_id, 'record_', 'domain_id');
				$return[$clone_id]['clone_count'] .= '|' . $fmdb->num_rows;
				
				/** Clone views */
				$return[$clone_id]['clone_views'] = $clone_results[$i]->domain_view;
				
				/** Clone servers */
				$return[$clone_id]['clone_servers'] = $clone_results[$i]->domain_name_servers;

				/** Dynamic updates support */
				$return[$clone_id]['dynamic'] = (getNameFromID($clone_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_dynamic') == 'yes') ? sprintf('<a href="JavaScript:void(0);" class="tooltip-bottom mini-icon" data-tooltip="%s"><i class="mini-icon fa fa-share-alt" aria-hidden="true"></i></a>', __('Zone supports dynamic updates')) : null;
				
				/** DNSSEC support */
				$return[$clone_id]['dnssec'] = (getNameFromID($clone_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_dnssec') == 'yes') ? sprintf('<a href="JavaScript:void(0);" class="tooltip-bottom mini-icon" data-tooltip="%s"><i class="mini-icon fa fa-lock secure" aria-hidden="true"></i></a>', __('Zone is secured with DNSSEC')) : null;

				/** Clone comment */
				$return[$clone_id]['clone_comment'] = ($clone_results[$i]->domain_comment) ? $clone_results[$i]->domain_comment : '&nbsp;';
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
		
		/** Get zones based on access */
		if (!currentUserCan('do_everything')) {
			$user_capabilities = getUserCapabilities($_SESSION['user']['id'], 'all');
			if (array_key_exists('access_specific_zones', $user_capabilities[$_SESSION['module']]) && !array_key_exists('view_all', $user_capabilities[$_SESSION['module']]) && $user_capabilities[$_SESSION['module']]['access_specific_zones'][0]) {
				$domain_id_sql .= ' AND domain_id IN (' . join(',', $this->getZoneAccessIDs($user_capabilities[$_SESSION['module']]['access_specific_zones'])) . ')';
			}
		}
		
		$query = "SELECT domain_id,domain_name FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domains WHERE domain_clone_domain_id='0' AND domain_mapping='$map' AND domain_type='primary' AND domain_status='active' AND domain_template='no' $domain_id_sql ORDER BY domain_name ASC";
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
	
	function buildZoneConfig($domain_id) {
		global $fmdb, $__FM_CONFIG, $fm_name;
		
		/** Check domain_id and soa */
		$parent_domain_ids = getZoneParentID($domain_id);
		if (!isset($parent_domain_ids[2])) {
			$query = "SELECT * FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domains d, fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}soa s WHERE domain_status='active' AND d.account_id='{$_SESSION['user']['account_id']}' AND s.soa_id=d.soa_id AND d.domain_id IN (" . join(',', $parent_domain_ids) . ")";
		} else {
			$query = "SELECT * FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domains d, fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}soa s WHERE domain_status='active' AND d.account_id='{$_SESSION['user']['account_id']}' AND
				s.soa_id=(SELECT soa_id FROM fm_dns_domains WHERE domain_id={$parent_domain_ids[2]})";
		}
		$fmdb->query($query);
		if (!$fmdb->num_rows) return displayResponseClose(__('Failed: There was no SOA record found for this zone.'));

		$domain_details = $fmdb->last_result;
		extract(get_object_vars($domain_details[0]), EXTR_SKIP);
		
		$name_servers = $this->getNameServers($domain_name_servers, array('masters'));
		
		/** No name servers so return */
		if (!$name_servers) return displayResponseClose(__('There are no DNS servers hosting this zone.'));
		
		/** Loop through name servers */
		$name_server_count = $fmdb->num_rows;
		$response = '';
		$failures = false;
		for ($i=0; $i<$name_server_count; $i++) {
			/** Use server_address if available */
			if (!isset($name_servers[$i]->server_address) || !$name_servers[$i]->server_address) $name_servers[$i]->server_address = $name_servers[$i]->server_name;
			
			if (isset($post_result)) unset($post_result);
			switch($name_servers[$i]->server_update_method) {
				case 'cron':
					/** Add records to fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}track_reloads */
					foreach ($this->getZoneCloneChildren($domain_id) as $child_id) {
						$this->addZoneReload($name_servers[$i]->server_serial_no, $child_id);
					}
					
					$post_result[] = __('This zone will be updated on the next cron run.');
					break;
				case 'http':
				case 'https':
					/** Test the port first */
					if (!socketTest($name_servers[$i]->server_address, $name_servers[$i]->server_update_port, 10)) {
						$post_result = '[' . $name_servers[$i]->server_name . '] ' . sprintf(__('Failed: could not access %s (tcp/%d).'), $name_servers[$i]->server_update_method, $name_servers[$i]->server_update_port) . "\n";
						$response .= $post_result;
						$failures = true;
						break;
					}
					
					/** Remote URL to use */
					$url = $name_servers[$i]->server_update_method . '://' . $name_servers[$i]->server_address . ':' . $name_servers[$i]->server_update_port . '/fM/reload.php';
					
					/** Data to post to $url */
					$post_data = array('action' => 'reload',
						'serial_no' => $name_servers[$i]->server_serial_no,
						'domain_id' => $domain_id,
						'module' => $_SESSION['module']);
					
					$post_result = unserialize(getPostData($url, $post_data));
					
					break;
				case 'ssh':
					$server_remote = runRemoteCommand($name_servers[$i]->server_address, "sudo php /usr/local/facileManager/fmDNS/client.php zones id=$domain_id", 'return', $name_servers[$i]->server_update_port);

					if (is_array($server_remote)) {
						if (array_key_exists('output', $server_remote) && (!count($server_remote['output'])) || strpos($server_remote['output'][0], 'successful') !== false) {
							$server_remote['output'] = array();
						}
						extract($server_remote);
						$post_result = $output;
						unset($output);
					} else {
						$post_result = array($server_remote);
					}
					
					break;
			}

			if (!is_array($post_result)) {
				/** Something went wrong */
				return $post_result;
			} else {
				if (!count($post_result)) $post_result[] = __('Zone reload was successful.');

				if (count($post_result) > 1) {
					/** Loop through and format the output */
					foreach ($post_result as $line) {
						$response .= '[' . $name_servers[$i]->server_name . "] $line\n";
						if (strpos(strtolower($line), 'fail') !== false) $failures = true;
					}
				} else {
					$response .= "[{$name_servers[$i]->server_name}] " . $post_result[0] . "\n";
					if (strpos(strtolower($post_result[0]), 'fail') !== false) $failures = true;
				}
			}
			/** Set the server_update_config flag */
			setBuildUpdateConfigFlag($name_servers[$i]->server_serial_no, 'yes', 'update');
		}
		
		/** Reset the domain_reload flag */
		if (!$failures) {
			global $fm_dns_records;
			if (!isset($fm_dns_records)) include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_records.php');
			$fm_dns_records->updateSOAReload($domain_id, 'no', 'all');

			addLogEntry(sprintf(__("Reloaded zone '%s'."), displayFriendlyDomainName(getNameFromID($domain_id, 'fm_'. $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name'))));
		}
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
						$group_servers = getNameFromID(preg_replace('/\D/', '', $server), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'server_groups', 'group_', 'group_id', 'group_' . $type);

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
		
		$query = "SELECT * FROM `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}servers` WHERE `server_status`='active' AND account_id='{$_SESSION['user']['account_id']}' AND server_type NOT IN ('remote') $sql_name_servers ORDER BY `server_type` DESC, `server_url_server_type`, `server_update_method`";
		$fmdb->query($query);
		
		/** No name servers so return */
		if (!$fmdb->num_rows) return false;
		
		return $fmdb->last_result;
	}
	
	
	function addZoneReload($server_serial_no, $domain_id) {
		global $fmdb, $__FM_CONFIG;
		
		$query = "INSERT INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}track_reloads` VALUES($domain_id, $server_serial_no)";
		$fmdb->query($query);
	}
	
	
	function updateSOASerialNo($domain_id, $soa_serial_no, $increment = 'increment') {
		global $fmdb, $__FM_CONFIG;
		
		$current_date = date('Ymd');

		/** Ensure soa_serial_no is an integer */
		$soa_serial_no = (int) $soa_serial_no;
		
		/** Increment serial */
		if ($increment == 'increment') {
			$soa_serial_no = (strpos($soa_serial_no, $current_date) === false) ? $current_date . '00' : $soa_serial_no + 1;
		}
		
		$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domains` SET `soa_serial_no`=$soa_serial_no WHERE `domain_template`='no' AND `domain_id`=$domain_id";
		$fmdb->query($query);
	}
	
	function availableZones($include = 'no-clones', $zone_type = null, $limit = 'all', $extra = 'none', $exclude = array()) {
		global $fmdb, $__FM_CONFIG;
		
		if (!is_array($include)) {
			$include = (array) $include;
		}
		if (!is_array($exclude)) {
			$exclude = (array) $exclude;
		}

		$include_groups = in_array('groups', $include);
		if ($limit == 'restricted') {
			$user_capabilities = getUserCapabilities($_SESSION['user']['id'], 'all');
		}
		
		$start = 0;
		$return = array();
		
		if ($extra == 'all' && !@in_array(0, $exclude)) {
			$start = 1;
			$return = array(array(__('All Zones'), 0));

			if ($include_groups) {
				$return = array(null => $return);
			}
		}

		/** Zone Groups */
		if ($include_groups) {
			$j = 0;
			basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domain_groups', 'group_name', 'group_', 'active');
			if ($fmdb->num_rows) {
				$results = $fmdb->last_result;
				$count = $fmdb->num_rows;
				for ($i = 0; $i < $count; $i++) {
					if ($limit != 'restricted' ||
						($limit == 'restricted' && currentUserCan('do_everything')) ||
						($limit == 'restricted' && array_key_exists('access_specific_zones', $user_capabilities[$_SESSION['module']]) &&
							(in_array(0, $user_capabilities[$_SESSION['module']]['access_specific_zones']) || in_array('g_' . $results[$i]->group_id, $user_capabilities[$_SESSION['module']]['access_specific_zones']))
						)
					) {
						if (!in_array('g_' . $results[$i]->group_id, $exclude)) {
							$return[__('Groups')][$j][] = $results[$i]->group_name;
							$return[__('Groups')][$j][] = 'g_' . $results[$i]->group_id;
							$j++;
						}
					}
				}
			}
		}

		/** Get restricted zones only */
		$restricted_sql = null;
		if ($limit == 'restricted' && !currentUserCan('do_everything')) {
			if (array_key_exists('access_specific_zones', $user_capabilities[$_SESSION['module']])) {
				if (!in_array(0, $user_capabilities[$_SESSION['module']]['access_specific_zones'])) {
					$restricted_sql = "AND domain_id IN ('" . implode("','", $this->getZoneAccessIDs($user_capabilities[$_SESSION['module']]['access_specific_zones'])) . "')";
				}
			}
		}
		
		$include_sql = (in_array('no-clones', $include)) ? "AND domain_clone_domain_id='0' " : null;
		$include_sql .= (in_array('no-templates', $include)) ? "AND domain_template='no'" : null;
		if ($zone_type) {
			if (is_array($zone_type)) {
				$zone_type_sql = "AND domain_type IN ('" . implode("','", $zone_type) . "')";
			} else {
				$zone_type_sql = "AND domain_type='$zone_type'";
			}
		} else {
			$zone_type_sql = null;
		}

		$exclude_sql = ($exclude) ? trim("AND domain_id NOT IN(0," . implode(',', array_filter($exclude, function($value) {
			return strpos($value, 'g_') === false;
		})), ',') . ')' : null;
		
		$query = "SELECT domain_id,domain_name,domain_view FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domains WHERE account_id='{$_SESSION['user']['account_id']}' AND domain_status!='deleted' $include_sql $zone_type_sql $restricted_sql $exclude_sql ORDER BY domain_mapping,domain_name ASC";
		$fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$results = $fmdb->last_result;
			$count = $fmdb->num_rows;
			for ($i=0; $i<$count; $i++) {
				$domain_name = (!function_exists('displayFriendlyDomainName')) ? $results[$i]->domain_name : displayFriendlyDomainName($results[$i]->domain_name);
				if ($results[$i]->domain_view > 0) {
					$domain_name .= ' (' . getNameFromID($results[$i]->domain_view, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'views', 'view_', 'view_id', 'view_name') . ')';
				}
				if ($include_groups) {
					$return[__('Zones')][$i+$start][] = $domain_name;
					$return[__('Zones')][$i+$start][] = $results[$i]->domain_id;
				} else {
					$return[$i+$start][] = $domain_name;
					$return[$i+$start][] = $results[$i]->domain_id;
				}
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
						} elseif (preg_match("/^(\d{1,3})\/(\d{1,2})$/", $domain_pieces[$i])) {
							/** Validate octet range */
							$octet_range = explode('/', $domain_pieces[$i]);
							
							if ($octet_range[1] > 32) return false;
							
							foreach ($octet_range as $octet) {
								if (filter_var($octet, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0, 'max_range' => 255))) === false) return false;
							}
							continue;
						} elseif (preg_match("/^([a-z\d](-*[a-z\d])*)*$/i", $domain_pieces[$i])) {
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
					&& preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $domain_name)); // length of each label
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
			
			$reverse_ips = '';
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
		
		/** Ensure domain is primary */
		if (count($response) == 1 && $domain_type != 'primary') {
			$response[] = ' --> ' . __('Failed: Zone is not a primary zone.');
		}
		
		/** Ensure user is allowed to reload zone */
		$zone_access_allowed = zoneAccessIsAllowed(array($domain_id), 'reload_zones');
		
		if (count($response) == 1 && !$zone_access_allowed) {
			$response[] = ' --> ' . __('Failed: You do not have permission to reload this zone.');
		}
		
		/** Check the zone file for loadable syntax */
		$syntax_check = $this->singleZoneReloadSyntaxCheck($domain_id);
		if ($syntax_check) $response[] = $syntax_check;

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
		
		/** Zone groups */
		if (array_key_exists('group_name', $post)) {
			/** Empty domain names are not allowed */
			if (empty($post['group_name'])) return __('No group name defined.');
			
			/** Check if the group name already exists */
			basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domain_groups', $post['group_name'], 'group_', 'group_name', "AND group_id!={$post['group_id']}");
			if ($fmdb->num_rows) return __('Zone group already exists.');
			
			/** Check name field length */
			$field_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domain_groups', 'group_name');
			if ($field_length !== false && strlen($post['group_name']) > $field_length) return sprintf(dngettext($_SESSION['module'], 'Group name is too long (maximum %d character).', 'Group name is too long (maximum %d characters).', $field_length), $field_length);
			
			/** Ensure group_domain_ids is set */
			if (!array_key_exists('group_domain_ids', $post)) {
				return __('You must select one or more zones.');
			}
			
			return $post;
		}
		
		/** Zones */		
		if (!$post['domain_id']) unset($post['domain_id']);
		else {
			if (!zoneAccessIsAllowed(array($post['domain_id']))) {
				return __('You do not have permission to modify this zone.');
			}
		}
		
		/** Empty domain names are not allowed */
		if (empty($post['domain_name'])) return __('No zone name defined.');
		
		/** Reverse zones should have form of x.x.x.in-addr.arpa */
		if ($post['domain_mapping'] == 'reverse') {
			$post['domain_name'] = $this->setReverseZoneName($post['domain_name']);
		}
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_name');
		if ($field_length !== false && strlen($post['domain_name']) > $field_length) return sprintf(dngettext($_SESSION['module'], 'Zone name is too long (maximum %d character).', 'Zone name is too long (maximum %d characters).', $field_length), $field_length);
		
		if (isset($post['domain_template']) && $post['domain_template'] != 'yes') {
			$post['domain_name'] = rtrim(trim(strtolower($post['domain_name'])), '.');

			/** Perform domain name validation */
			if (!isset($post['domain_mapping'])) {
				global $map;
				$post['domain_mapping'] = $map;
			}
			if ($post['domain_mapping'] == 'reverse') {
				$post['domain_name'] = $this->fixDomainTypos($post['domain_name']);
			} else {
				$post['domain_name'] = function_exists('idn_to_ascii') ? idn_to_ascii($post['domain_name'], IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) : $post['domain_name'];
			}
			if (!$this->validateDomainName($post['domain_name'], $post['domain_mapping'])) return __('Invalid zone name.');
		}
		
		/** Dynamic updates */
		if (!isset($post['domain_dynamic']) || $post['domain_dynamic'] != 'yes') {
			$post['domain_dynamic'] = 'no';
		}
		
		/** DNSSEC */
		if (!isset($post['domain_dnssec']) || $post['domain_dnssec'] != 'yes') {
			$post['domain_dnssec'] = 'no';
		}
		if (!empty($post['domain_dnssec_sig_expire'])) {
			if (!verifyNumber($post['domain_dnssec_sig_expire'], 0, null, false)) return __('DNSSEC signature expiry must be a valid number of days.');
		} else {
			$post['domain_dnssec_sig_expire'] = 0;
		}
		if (!isset($post['domain_dnssec_generate_ds']) || $post['domain_dnssec_generate_ds'] != 'yes') {
			$post['domain_dnssec_generate_ds'] = 'no';
		}
		if (!isset($post['domain_dnssec_sign_inline']) || $post['domain_dnssec_sign_inline'] != 'yes') {
			$post['domain_dnssec_sign_inline'] = 'no';
		} else {
			$post['domain_dnssec_signed'] = 0;
		}
		
		/** Ensure domain_view is set */
		$tmp_domain_clone_domain_id = (isset($post['domain_id'])) ? getNameFromID($post['domain_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_clone_domain_id') : null;
		if (!array_key_exists('domain_view', $post) && $tmp_domain_clone_domain_id) {
			$post['domain_view'] = ($post['domain_clone_domain_id'] || $post['domain_template_id'] || $tmp_domain_clone_domain_id) ? -1 : 0;
		} elseif (!isset($post['domain_view']) || (is_array($post['domain_view']) && in_array(0, $post['domain_view']))) {
			$post['domain_view'] = 0;
		}

		/** Is this based on a template? */
		if (isset($post['domain_template_id']) && $post['domain_template_id'] && !is_array($post['domain_view']) && $post['domain_view'] < 0) {
			$post['domain_view'] = getNameFromID($post['domain_template_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_view');
		}
		
		/** Format domain_clone_domain_id */
		if (isset($post['domain_clone_domain_id']) && !$post['domain_clone_domain_id'] && $post['action'] == 'add') $post['domain_clone_domain_id'] = 0;
		
		/** domain_clone_dname override */
		if (!isset($post['domain_clone_dname_override']) || !$post['domain_clone_dname_override']) {
			$post['domain_clone_dname'] = null;
		} else {
			unset($post['domain_clone_dname_override']);
		}

		/** Ensure domain_name_servers is set */
		if (!isset($post['domain_name_servers']) || !$post['domain_name_servers']) {
			$post['domain_name_servers'] = array(0);
		}
		
		/** Does the record already exist for this account? */
		$domain_id_sql = (isset($post['domain_id'])) ? 'AND domain_id!=' . $post['domain_id'] : null;
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'views', $_SESSION['user']['account_id'], 'view_', 'account_id');
		if (!$fmdb->num_rows) { /** No views defined - all zones must be unique */
			basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', $post['domain_name'], 'domain_', 'domain_name', $domain_id_sql);
			if ($fmdb->num_rows) return __('Zone already exists.');
		} else { /** All zones must be unique per view */
			/** Does the domain exist in all views? */
			if (!$post['domain_view'] || in_array(0, (array) $post['domain_view'])) {
				basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', $post['domain_name'], 'domain_', 'domain_name', $domain_id_sql);
				if ($fmdb->num_rows) {
					/** Zone exists for views, but what about on all servers? */
					if (!$post['domain_name_servers'] || in_array('0', $post['domain_name_servers'])) {
						return __('Zone already exists for all views on all servers.');
					}
					/** Zone exists for views, but what about on the same server? */
					if ($post['domain_name_servers']) {
						$existing_name_servers = $this->getNameServers($fmdb->last_result[0]->domain_name_servers);
						$submitted_name_servers = $this->getNameServers(implode(';', $post['domain_name_servers']));
						if ($submitted_name_servers && $existing_name_servers) {
							$name_server_ids = array();
							foreach ($existing_name_servers as $key => $obj) {
								$name_server_ids[] = $obj->server_id;
							}
							foreach ($submitted_name_servers as $val) {
								if (in_array($val->server_id, $name_server_ids)) return __('Zone already exists for all views on selected servers.');
							}
						}
					}
				}
			}
			if (is_array($post['domain_view'])) {
				$domain_view = '';
				$domain_servers_sql = '';
				if ($post['domain_name_servers']) {
					foreach ($post['domain_name_servers'] as $val) {
						$domain_servers_sql .= "domain_name_servers='$val' OR domain_name_servers LIKE '$val;%' OR domain_name_servers LIKE '%;$val;%' OR domain_name_servers LIKE '%;$val' OR ";
					}
				}
				if ($domain_servers_sql) {
					$domain_servers_sql = sprintf("AND (domain_name_servers='0' OR %s)", trim($domain_servers_sql, ' OR '));
				}
				foreach ($post['domain_view'] as $val) {
					if ($val == 0 || $val == '') {
						$domain_view = 0;
						break;
					}
					$domain_view .= $val . ';';
					basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', $post['domain_name'], 'domain_', 'domain_name', "AND (domain_view='$val' OR domain_view='0' OR domain_view LIKE '$val;%' OR domain_view LIKE '%;$val;%' OR domain_view LIKE '%;$val') $domain_servers_sql $domain_id_sql");
					if ($fmdb->num_rows) {
						$view_name = getNameFromID($val, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'views', 'view_', 'view_id', 'view_name');
						return sprintf(__("Zone already exists for the '%s' view."), $view_name);
					}
				}
				$post['domain_view'] = rtrim($domain_view, ';');
			}
		}
		
		/** Is this based on a template? */
		if (isset($post['domain_template_id']) && $post['domain_template_id']) {
			$include = array('action', 'domain_template_id' , 'domain_name', 'domain_template', 'domain_mapping', 
				'domain_dynamic', 'domain_dnssec', 'domain_dnssec_sig_expire', 'domain_dnssec_generate_ds',
				'domain_dnssec_sign_inline', 'domain_dnssec_signed', 'domain_dnssec_parent_domain_id', 'domain_view',
				'domain_ttl', 'domain_comment');
			foreach ($include as $key) {
				if (isset($post[$key])) {
					$new_post[$key] = $post[$key];
				}
			}
			$post = $new_post;
			unset($new_post, $post['domain_template']);
			$post['domain_type'] = getNameFromID($post['domain_template_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_type');
			if (!is_array($post['domain_view']) && (!isset($post['domain_view']) || $post['domain_view'] < 0)) $post['domain_view'] = getNameFromID($post['domain_template_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_view');
			$post['domain_name_servers'] = explode(';', getNameFromID($post['domain_template_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name_servers'));

			return $post;
		} else {
			$post['domain_template_id'] = 0;
		}

		/** Redirect zone to URL? */
		if ($post['domain_type'] == 'url-redirect') {
			if (!$post['domain_redirect_url']) {
				return sprintf(__('You must specify a URL to redirect %s to.'), $post['domain_name']);
			}
			if (!filter_var($post['domain_redirect_url'], FILTER_VALIDATE_URL)) {
				return sprintf(__('%s is not a valid URL.'), $post['domain_redirect_url']);
			}
			$post['domain_type'] = 'primary';
		} else {
			unset($post['domain_redirect_url']);
		}
		
		/** No need to process more if zone is cloned */
		if (isset($post['domain_clone_domain_id']) && $post['domain_clone_domain_id'] || $tmp_domain_clone_domain_id) {
			return $post;
		}
		
		/** Cleans up acl_addresses for future parsing **/
		$clean_fields = array('forwarders', 'primaries');
		foreach ($clean_fields as $val) {
			if (isset($post['domain_required_servers']) && strpos($post['domain_required_servers'][$val], 'master_') === false) {
				$post['domain_required_servers'][$val] = verifyAndCleanAddresses($post['domain_required_servers'][$val], 'no-subnets-allowed');
				if (strpos($post['domain_required_servers'][$val], 'not valid') !== false) return $post['domain_required_servers'][$val];
			}
		}

		/** Forward servers */
		if ($post['domain_type'] == 'forward') {
			if (empty($post['domain_required_servers']['forwarders'])) return __('No forward servers defined.');
			$post['domain_required_servers'] = $post['domain_required_servers']['forwarders'];
		}
		
		/** Slave and stub zones require master servers */
		if (in_array($post['domain_type'], array('secondary', 'stub'))) {
			if (empty($post['domain_required_servers']['primaries'])) return __('No master servers defined.');
			$post['domain_required_servers'] = $post['domain_required_servers']['primaries'];
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
	 * @param string $ids IDs to convert to names
	 * @param string $type ID type to process
	 * @return string
	 */
	function IDs2Name ($ids, $type) {
		global $__FM_CONFIG;
		
		$all_text = $type == 'view' ? __('All Views') : __('All Servers');
		
		if ($ids) {
			if ($ids == -1) return sprintf('<i>%s</i>', __('inherited'));
			
			$table = $type;
			
			/** Process multiple IDs */
			if (strpos($ids, ';')) {
				$ids_array = explode(';', rtrim($ids, ';'));
				if (in_array('0', $ids_array)) $name = $all_text;
				else {
					$name = '';
					foreach ($ids_array as $id) {
						if ($id[0] == 'g') {
							$table = 'server_group';
							$type = 'group';
						}
						$name .= getNameFromID(preg_replace('/\D/', '', $id), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . $table . 's', $type . '_', $type . '_id', $type . '_name') . ', ';
					}
					$name = rtrim($name, ', ');
				}
			} else {
				if ($ids[0] == 'g') {
					$table = 'server_group';
					$type = 'group';
				}
				$name = getNameFromID(preg_replace('/\D/', '', $ids), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . $table . 's', $type . '_', $type . '_id', $type . '_name');
			}
		} else $name = $all_text;
		
		return $name;
	}
	
	/**
	 * Builds the zone listing JSON
	 *
	 * @since 2.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param string $zones Include all zones in the list
	 * @param array $exclude Domain IDs to exclude
	 * @return string|array
	 */
	function buildZoneJSON($zones = 'all', $exclude = null, $additional_zones = null) {
		$get_zones = ($zones == 'all') ? array('no-templates', 'groups') : 'no-templates';
		$temp_zones = $this->availableZones($get_zones, array('primary', 'secondary', 'forward'), 'all', $zones, $exclude);

		if ($additional_zones) {
			if (array_key_exists(__('Groups'), $temp_zones)) {
				foreach ($additional_zones as $a => $tmp_addl_array) {
					foreach ($temp_zones[__('Groups')] as $b => $c) {
						if (in_array($tmp_addl_array[1], $c)) {
							unset($additional_zones[$a]);
						}
					}
				}
				$temp_zones[__('Zones')] = array_merge($additional_zones, $temp_zones[__('Zones')]);
			} else {
				$temp_zones = array_merge($additional_zones, $temp_zones);
			}
		}

		$i = 0;
		foreach ($temp_zones as $parent => $children) {
			$j = 0;
			foreach ($children as $temp_zone_array) {
				list($tmp_zone_data[$j]['text'], $tmp_zone_data[$j]['id']) = $temp_zone_array;
				$j++;
			}
			if ($tmp_zone_data[$j-1]['id'] === null) {
				list($available_zones[$i]['text'], $available_zones[$i]['id']) = $children;
			} else {
				$available_zones[$i] = array('text' => $parent, 'children' => $tmp_zone_data);
			}
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
	 * @return string
	 */
	function buildFilterMenu() {
		$domain_view = isset($_GET['domain_view']) ? $_GET['domain_view'] : 0;
		$domain_group = isset($_GET['domain_group']) ? $_GET['domain_group'] : 0;
		
		/** Get zones based on access */
		$user_capabilities = getUserCapabilities($_SESSION['user']['id'], 'all');

		$available_views = availableViews('active');
		if (currentUserCan('do_everything', $_SESSION['module']) || (array_key_exists('access_specific_zones', $user_capabilities[$_SESSION['module']]) && $user_capabilities[$_SESSION['module']]['access_specific_zones'][0] == '0')) $available_groups = $this->availableGroups();
		$filters = '';
		
		if (count($available_views) > 1) {
			$filters .= buildSelect('domain_view', 'domain_view', $available_views, $domain_view, 1, null, true, null, null, __('Filter Views'));
		}
		if (count((array) $available_groups) > 1) {
			$filters .= buildSelect('domain_group', 'domain_group', $available_groups, $domain_group, 1, null, true, null, null, __('Filter Zones'));
		}
		
		if ($filters) {
			$filters = sprintf('<form method="GET">%s <input type="submit" name="" id="" value="%s" class="button" /></form>' . "\n", $filters, __('Filter'));
		}
		return $filters;
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
		
		$query = "SELECT domain_id,domain_name FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domains WHERE account_id='{$_SESSION['user']['account_id']}' 
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
	
	
	/**
	 * Updates limited user access with newly created domain_id
	 *
	 * @since 3.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param int $domain_id Domain ID to add to allowed list
	 * @return void
	 */
	function updateUserZoneAccess($domain_id) {
		global $fmdb;
		
		$user_capabilities = getUserCapabilities($_SESSION['user']['id'], 'all');
		
		if (array_key_exists($_SESSION['module'], $user_capabilities)) {
			if (array_key_exists('access_specific_zones', $user_capabilities[$_SESSION['module']])) {
				$user_capabilities[$_SESSION['module']]['access_specific_zones'][] = $domain_id;
				
				if (getUserCapabilities($_SESSION['user']['id'])) {
					$user_or_group = 'user';
					$id = $_SESSION['user']['id'];
				} else {
					$user_or_group = 'group';
					$id = getNameFromID($_SESSION['user']['id'], 'fm_users', 'user_', 'user_id', 'user_group');
				}
				
				$sql = $user_or_group . "_caps='" . serialize($user_capabilities) . "'";
				
				/** Update the user or group capabilities */
				$query = "UPDATE `fm_{$user_or_group}s` SET $sql WHERE `{$user_or_group}_id`=$id AND `account_id`='{$_SESSION['user']['account_id']}'";
				$fmdb->query($query);
			}
		}
	}
	
	
	/**
	 * Updates limited user access with newly created domain_id
	 *
	 * @since 3.1
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param array $data Form data
	 * @param string $action Create or Edit
	 * @return string
	 */
	function printGroupsForm($data, $action) {
		global $fmdb, $__FM_CONFIG;
		
		$return_form = $group_name = $group_comment = '';
		$group_id = 0;
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		$popup_title = $action == 'create' ? __('Add Group') : __('Edit Group');
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		/** Get domains_ids associated with group_id */
		$group_domain_ids = $this->getZoneGroupMembers($group_id);
		
		$group_name_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'server_groups', 'group_name');
		$group_domain_ids = buildSelect('group_domain_ids', 'group_domain_ids', $this->availableZones('no-templates'), (array) $group_domain_ids, 1, null, true, null, 'required', __('Select one or more zones'));

		$return_form .= sprintf('
		%s
		<form name="manage" id="manage">
			<input type="hidden" name="page" value="domains" />
			<input type="hidden" name="action" value="%s" />
			<input type="hidden" name="group_id" value="%d" />
			<table class="form-table">
				<tr>
					<th width="33&#37;" scope="row"><label for="group_name">%s</label></th>
					<td width="67&#37;"><input name="group_name" id="group_name" type="text" value="%s" size="40" maxlength="%d" class="required" /></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="group_masters">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="group_comment">%s</label></th>
					<td width="67&#37;"><textarea id="group_comment" name="group_comment" rows="4" cols="30">%s</textarea></td>
				</tr>
			</table>
		%s
		</form>
		<script>
			$(document).ready(function() {
				$("#manage select").select2({
					minimumResultsForSearch: 10,
					allowClear: true,
					width: "230px"
				});
			});
		</script>', 
				$popup_header, $action, $group_id,
				__('Group Name'), $group_name, $group_name_length,
				__('Associated Zones'), $group_domain_ids,
				_('Comment'), $group_comment,
				$popup_footer);
		
		
		return $return_form;
	}
	
	
	/**
	 * Builds an array of available zone groups
	 *
	 * @since 3.1
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @return array
	 */
	function availableGroups() {
		global $fmdb, $__FM_CONFIG;
		
		$return[0][] = __('All Zones');
		$return[0][] = '0';
		
		$query = "SELECT group_id,group_name FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domain_groups WHERE account_id='{$_SESSION['user']['account_id']}' AND group_status='active' ORDER BY group_name ASC";
		$result = $fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$results = $fmdb->last_result;
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$return[$i+1][] = $results[$i]->group_name;
				$return[$i+1][] = $results[$i]->group_id;
			}
		}
		return $return;
	}
	
	
	/**
	 * Builds an array of zone group members
	 *
	 * @since 3.1
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param int $group_id Zone group ID
	 * @return array
	 */
	function getZoneGroupMembers($group_id) {
		global $fmdb, $__FM_CONFIG;
		
		if ($group_id == 0) {
			return array();
		}
		
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] .'domains', 'domain_mapping`,`domain_name', 'domain_', "AND ((domain_groups='$group_id' OR domain_groups LIKE '$group_id;%' OR domain_groups LIKE '%;$group_id;%' OR domain_groups LIKE '%;$group_id'))");
		for ($x=0; $x<$fmdb->num_rows; $x++) {
			$group_domain_ids[] = $fmdb->last_result[$x]->domain_id;
		}
		
		return (array) $group_domain_ids;
	}
	
	
	/**
	 * Sets the zone group members
	 *
	 * @since 3.1
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param int $group_id Zone group ID
	 * @param array $domain_ids Domain ID
	 * @param string $action Add or Remove
	 * @return string|boolean
	 */
	function setZoneGroupMembers($group_id, $domain_ids, $action = 'add') {
		global $fmdb, $__FM_CONFIG;
		
		foreach ($domain_ids as $val) {
			if ($val == 0 || $val == '') {
				break;
			}
			$current_domain_groups = getNameFromID($val, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_groups');
			$domain_groups = explode(';', $current_domain_groups);
			if ($action == 'add') {
				$domain_groups[] = $group_id;
				$index = 0;
			} else {
				$index = $group_id;
			}
			$key = array_search($index, $domain_groups);
			if ($key !== false) unset($domain_groups[$key]);
			$domain_groups = join(';', array_unique($domain_groups));

			basicUpdate('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', $val, 'domain_groups', $domain_groups, 'domain_id');
			if ($fmdb->sql_errors) {
				return formatError(__('Could not associate the zones with this group because a database error occurred.'), 'sql');
			}
		}
		
		return true;
	}
	
	
	/**
	 * Builds an array of domain IDs the user can access
	 *
	 * @since 3.1
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param array $ids User capability IDs
	 * @return array
	 */
	function getZoneAccessIDs($ids) {
		foreach ($ids as $limited_id) {
			/** Zone groups */
			if ($limited_id[0] == 'g') {
				foreach ($this->getZoneGroupMembers(substr($limited_id, 2)) as $group_limited_id) {
					$temp_domain_ids[] = $group_limited_id;
				}
			} else {
				$temp_domain_ids[] = $limited_id;
			}
		}
		
		return array_unique((array) $temp_domain_ids);
	}
	
	
	/**
	 * Converts domain_ids into domain_names for logging
	 *
	 * @since 3.1
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param array $ids Domain IDs
	 * @return string
	 */
	function getZoneLogDomainNames($ids) {
		global $__FM_CONFIG;
		
		$log_message_domains = '';
		foreach ($ids as $id) {
			$log_message_domains .= getNameFromID($id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name') . "; ";
		}
		
		return rtrim(trim($log_message_domains), ";");
	}
	
	
	/**
	 * Gets config item data from key
	 *
	 * @since 4.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param integer $domain_id Domain ID to get the config option for
	 * @param string $config_opt Config option to retrieve
	 * @return string
	 */
	function getConfig($domain_id, $config_opt = null) {
		global $fmdb, $__FM_CONFIG;
		
		$return = null;
		
		/** Get the data from $config_opt */
		$query = "SELECT cfg_data FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config WHERE account_id='{$_SESSION['user']['account_id']}' AND cfg_status!='deleted' AND domain_id='{$domain_id}' AND cfg_name='$config_opt' LIMIT 1";
		$result = $fmdb->get_results($query);
		if (!$fmdb->sql_errors && $fmdb->num_rows) {
			$return = $fmdb->last_result[0]->cfg_data;
		}
		
		return $return;
	}
	
	
	/**
	 * Processes zone check on single zone before reload
	 *
	 * @since 7.0.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param integer $domain_id Domain ID to get the zone data for
	 * @return string
	 */
	function singleZoneReloadSyntaxCheck($domain_id) {
		global $__FM_CONFIG, $fmdb;

		$response = null;

		if (getOption('enable_config_checks', $_SESSION['user']['account_id'], $_SESSION['module']) == 'yes') {
			global $fm_module_buildconf;
			include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_buildconf.php');

			basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', $domain_id, 'domain_', 'domain_id');
			$zone_result = $fmdb->last_result[0];
			
			/** Is this a clone id? */
			if ($zone_result->domain_clone_domain_id) $zone_result = $fm_module_buildconf->mergeZoneDetails($zone_result, 'clone');
			elseif ($zone_result->domain_template_id) $zone_result = $fm_module_buildconf->mergeZoneDetails($zone_result, 'template');

			$zone_file_contents = str_replace('$INCLUDE', ';', @$fm_module_buildconf->buildZoneFile($zone_result, 0));

			if (method_exists($fm_module_buildconf, 'processConfigsChecks')) {
				$response = @$fm_module_buildconf->processConfigsChecks(array('server_serial_no' => 0, 'files' => array($zone_result->domain_name . '.conf' => $zone_file_contents)), 'checkzone');
			}
			if (strpos($response, @$fm_module_buildconf->getSyntaxCheckMessage('loadable')) !== false) {
				$response = false;
			}
		}

		return $response;
	}

}

if (!isset($fm_dns_zones))
	$fm_dns_zones = new fm_dns_zones();
