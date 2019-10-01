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
 | fmWifi: Easily manage one or more access points                         |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmwifi/                            |
 +-------------------------------------------------------------------------+
*/

class fm_wifi_acls {
	
	/**
	 * Displays the item list
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param array $result Record rows of all items
	 * @return null
	 */
	function rows($result, $page, $total_pages, $type = 'acls') {
		global $fmdb;
		
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;
		
		$permission_type = (in_array($type, array('subnets', 'shared'))) ? 'networks' : $type;
		if (currentUserCan('manage_' . $permission_type, $_SESSION['module'])) {
			$bulk_actions_list = array(_('Enable'), _('Disable'), _('Delete'));
		}

		$fmdb->num_rows = $num_rows;

		$start = $_SESSION['user']['record_count'] * ($page - 1);
		echo displayPagination($page, $total_pages, array(@buildBulkActionMenu($bulk_actions_list), $this->buildFilterMenu()));

		$table_info = array(
						'class' => 'display_results',
						'id' => 'table_edits',
						'name' => $type
					);

		if (is_array($bulk_actions_list)) {
			$title_array[] = array(
								'title' => '<input type="checkbox" class="tickall" onClick="toggle(this, \'bulk_list[]\')" />',
								'class' => 'header-tiny header-nosort'
							);
		}
		$title_array = array_merge((array) $title_array, array(__('WLAN'), __('MAC Address'), __('Permission'), _('Comment')));
		if (is_array($bulk_actions_list)) $title_array[] = array('title' => _('Actions'), 'class' => 'header-actions');

		echo displayTableHeader($table_info, $title_array);

		if ($result) {
			$y = 0;
			for ($x=$start; $x<$num_rows; $x++) {
				if ($y == $_SESSION['user']['record_count']) break;
				$this->displayRow($results[$x]);
				$y++;
			}
		}

		echo "</tbody>\n</table>\n";
		if (!$result) {
			printf('<p id="table_edits" class="noresult" name="%s">%s</p>', $type, __('There are no items.'));
		}
	}

	/**
	 * Adds the new object
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param array $post $_POST data
	 * @return boolean or string
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
//		echo '<pre>';print_r($post); exit;
		
		/** Server groups */
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}acls`";
		$sql_fields = '(';
		$sql_values = null;

		$post['account_id'] = $_SESSION['user']['account_id'];

		$exclude = array('submit', 'action', 'acl_id', 'log_message_member_wlans');

		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_fields .= $key . ', ';
				$sql_values .= "'" . sanitize($data) . "', ";
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', ');

		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);

		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the ACL because a database error occurred.'), 'sql');
		}

		$insert_id = $fmdb->insert_id;

		addLogEntry(__('Added a MAC ACL with the following details') . ":\n" . __('Hardware Address') . ": {$post['acl_mac']}\n" . __('Action') . ": {$post['acl_action']}\n" . __('Associated APs') . ": ${post['log_message_member_wlans']}\n" . _('Comment') . ": {$post['acl_comment']}");

		return true;

		
		
		$log_message = "Added host:\nName: $name\nHardware Address: {$post['hardware']}\nFixed Address: {$post['fixed-address']}";
		$log_message .= "\nComment: {$post['config_comment']}";
		addLogEntry($log_message);
		
		setBuildUpdateConfigFlag(getWLANServers($insert_id), 'yes', 'build');
		
		return true;
	}

	/**
	 * Updates the selected host
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param array $post $_POST data
	 * @return boolean or string
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
//		echo '<pre>';print_r($post);exit;
		
		/** Update the parent */
		$sql_start = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}acls` SET ";
		$sql_values = null;
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		$post['config_comment'] = trim($post['config_comment']);
		
		$include = array('wlan_ids', 'acl_mac', 'acl_action', 'acl_comment');
		
		/** Insert the category parent */
		foreach ($post as $key => $data) {
			if (in_array($key, $include)) {
				$clean_data = sanitize($data);
				$sql_values .= "$key='$clean_data', ";
			}
		}
		$sql_values = rtrim($sql_values, ', ');
		
		$old_name = getNameFromID($post['acl_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'acls', 'acl_', 'acl_id', 'acl_mac');
		
		$query = "$sql_start $sql_values WHERE acl_id={$post['acl_id']} LIMIT 1";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not update the server because a database error occurred.'), 'sql');
		}
		
		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		/** Server changed so configuration needs to be built */
//		setBuildUpdateConfigFlag(getWLANServers($item_id), 'yes', 'build');
//		setBuildUpdateConfigFlag(getServerSerial($post['server_id'], $_SESSION['module']), 'yes', 'build');
		
		/** Add entry to audit log */
		addLogEntry(sprintf(__('Updated MAC ACL \'%s\' with the following details'), $old_name) . ":\n" . __('Hardware Address') . ": {$post['acl_mac']}\n" . __('Action') . ": {$post['acl_action']}\n" . __('Associated APs') . ": ${post['log_message_member_wlans']}\n" . _('Comment') . ": {$post['acl_comment']}");

		return true;
	}
	
	/**
	 * Deletes the selected server
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param integer $id ID to delete
	 * @return boolean or string
	 */
	function delete($id, $server_serial_no = 0) {
		global $fmdb, $__FM_CONFIG;
		
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'acls', 'acl_', 'acl_id', 'acl_mac');

		/** Delete item */
		if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'acls', $id, 'acl_', 'deleted', 'acl_id') === false) {
			return formatError(__('This item could not be deleted because a database error occurred.'), 'sql');
		} else {
			setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
			addLogEntry(sprintf(__("ACL '%s' was deleted."), $tmp_name));
			return true;
		}
	}


	/**
	 * Displays the server entry table row
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param object $row Single data row from $results
	 * @return null
	 */
	function displayRow($row) {
		global $fmdb, $__FM_CONFIG;
		
		$class = ($row->acl_status == 'disabled') ? 'disabled' : null;
		
		$edit_status = $edit_actions = $checkbox = null;
		
		if (currentUserCan('manage_hosts', $_SESSION['module'])) {
			$edit_status = '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a class="status_form_link" href="#" rel="';
			$edit_status .= ($row->acl_status == 'active') ? 'disabled' : 'active';
			$edit_status .= '">';
			$edit_status .= ($row->acl_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
			$edit_status .= '</a>';
			$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			$edit_status = '<td id="row_actions">' . $edit_status . '</td>';
			$checkbox = '<td><input type="checkbox" name="bulk_list[]" value="' . $row->acl_id .'" /></td>';
		}
		
		$edit_status = $edit_actions . $edit_status;
		
		if ($class) $class = 'class="' . $class . '"';
		
		$acl_action = $__FM_CONFIG['acls']['actions'][$row->acl_action];
		
		$associated_wlans = __('All WLANs');
		if ($row->wlan_ids) {
			$associated_wlans = null;
			foreach (explode(';', $row->wlan_ids) as $id) {
				$associated_wlans[] = getNameFromID($id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_', 'config_id', 'config_data');
			}
			$associated_wlans = join('; ', $associated_wlans);
		}
		
		echo <<<HTML
		<tr id="$row->acl_id" name="$row->acl_mac" $class>
			$checkbox
			<td>$associated_wlans</td>
			<td>$row->acl_mac</td>
			<td>$acl_action</td>
			<td>$row->acl_comment</td>
			$edit_status
		</tr>

HTML;
	}

	/**
	 * Displays the add/edit form
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param array $data Either $_POST data or returned SQL results
	 * @param string $action Add or edit
	 * @return string
	 */
	function printForm($data = '', $action = 'add', $type = null, $addl_vars = null) {
		global $__FM_CONFIG, $fm_wifi_wlans;
		
		$acl_id = $wlan_ids = $i = 0;
		$acl_comment = $acl_mac = $acl_action = null;
		$server_serial_no = (isset($_REQUEST['request_uri']['server_serial_no']) && (intval($_REQUEST['request_uri']['server_serial_no']) > 0 || $_REQUEST['request_uri']['server_serial_no'][0] == 'g')) ? sanitize($_REQUEST['request_uri']['server_serial_no']) : 0;
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		foreach ($__FM_CONFIG['acls']['actions'] as $key => $val) {
			$acl_action_opts[$i][] = $val;
			$acl_action_opts[$i][] = $key;
			$i++;
		}
		$acl_action_dropdown = buildSelect('acl_action', 'acl_action', $acl_action_opts, $acl_action);
		$assoc_wlans = buildSelect('wlan_ids', 'wlan_ids', $fm_wifi_wlans->getWLANList(), explode(';', $wlan_ids), 1, null, true);
		
		$popup_title = ($action == 'add') ? __('Add ACL') : __('Edit ACL');
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		$return_form = sprintf('<form name="manage" id="manage" method="post" action="">
		%s
			<input type="hidden" name="action" value="%s" />
			<input type="hidden" name="acl_id" value="%d" />
			<input type="hidden" name="server_serial_no" value="%s" />
			<table class="form-table">
				<tr>
					<th width="33&#37;" scope="row"><label for="wlan_ids">%s</label></th>
					<td width="67&#37;" nowrap>%s</td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="acl_mac">%s</label></th>
					<td width="67&#37;"><input name="acl_mac" id="acl_mac" type="text" value="%s" size="40" maxlength="17" /></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="acl_action">%s</label></th>
					<td width="67&#37;" nowrap>%s</td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="acl_comment">%s</label></th>
					<td width="67&#37;"><textarea id="acl_comment" name="acl_comment" rows="4" cols="30">%s</textarea></td>
				</tr>
			</table>
		%s
		</form>
		<script>
			$(document).ready(function() {
				$("#manage select").select2({
					width: "200px",
					minimumResultsForSearch: 10
				});
			});
		</script>',
				$popup_header, $action, $acl_id, $server_serial_no,
				__('Associated WLANs'), $assoc_wlans,
				__('MAC Address'), $acl_mac,
				__('Action'), $acl_action_dropdown,
				_('Comment'), $acl_comment,
				$popup_footer
			);

		return $return_form;
	}
	
	/**
	 * Validates the user-submitted data (for add and edit)
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param array $post Posted data to validate
	 * @return array
	 */
	function validatePost($post) {
		global $fmdb, $__FM_CONFIG, $fm_wifi_wlans;
		
		if (in_array('0', $post['wlan_ids']) || !isset($post['wlan_ids'])) {
			$post['wlan_ids'] = 0;
		} else {
			$post['wlan_ids'] = join(';', $post['wlan_ids']);
		}
		$post['acl_mac'] = sanitize($post['acl_mac']);

		/** Valid MAC address? */
		if (version_compare(PHP_VERSION, '5.5.0', '>=') && !verifySimpleVariable($post['acl_mac'], FILTER_VALIDATE_MAC)) {
			return __('The MAC address is invalid.');
		}
		
		/** Check if the group name already exists */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'acls', $post['acl_mac'], 'acl_', 'acl_mac', "AND acl_id!={$post['acl_id']}");
		if ($fmdb->num_rows) return __('This address already exists.');

		if ($post['acl_comment']) {
			$post['acl_comment'] = sanitize(trim($post['acl_comment']));
		}

		if (!isset($fm_wifi_wlans)) {
			if (!class_exists('fm_wifi_wlans')) {
				include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_wlans.php');
			}
		}
		/** Process WLAN selection */
		$post['log_message_member_wlans'] = $fm_wifi_wlans->getWLANLoggingNames(explode(';', $post['wlan_ids']));
		
		return $post;
		
		
		$log_message_member_wlans = null;
		foreach ($post['wlan_ids'] as $val) {
			if (!$val) {
				$wlan_members = 0;
				break;
			}
			$wlan_members .= $val . ';';
			$name = getNameFromID($val, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'acl_', 'acl_id', 'acl_mac');
			$log_message_member_wlans .= $val ? "$name; " : null;
		}
		$post['log_message_member_wlans'] = rtrim ($log_message_member_wlans, '; ');
		$post['wlan_ids'] = rtrim($wlan_members, ';');

		return $post;
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
		global $fm_wifi_wlans;
		
		if (!isset($fm_wifi_wlans)) {
			if (!class_exists('fm_wifi_wlans')) {
				include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_wlans.php');
			}
		}
		$wlan_ids = isset($_GET['wlan_ids']) ? $_GET['wlan_ids'] : 0;
		
		$filters = buildSelect('wlan_ids', 'wlan_ids', $fm_wifi_wlans->getWLANList(), $wlan_ids, 1, null, true, null, null, __('Filter WLANs'));
		
		$filters = sprintf('<form method="GET">%s <input type="submit" name="" id="" value="%s" class="button" /></form>' . "\n", $filters, __('Filter'));

		return $filters;
	}

	
	/**
	 * Blocks client from WLAN
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmWifi
	 *
	 * @param string $wlan WLAN to block the client from
	 * @param string $mac MAC address of the client to block
	 */
	function blockClient($wlan, $mac) {
		global $__FM_CONFIG, $fmdb, $fm_module_servers;
		
		$post = array(
			'action' => 'add',
			'acl_id' => 0,
			'server_serial_no' => 0,
			'wlan_ids' => array(0),
			'acl_mac' => $mac,
			'acl_action' => 'deny',
			'acl_comment' => __('Blocked from the dashboard')
		);
		
		/** Add the deny to the ACL database */
		$add_acl = $this->add($post);
		
//		sleep(2);
		
		
		// foreach ap hosting the ssid do
		//   client.php buildconf
		//   client.php block=mac
		
		/** Get APs hosting $wlan */
		$wlan_ap_ids = getNameFromID($wlan, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_', 'config_data', 'config_aps', null, 'AND config_name="ssid" AND config_status="active"');
		$wlan_aps = array();
		
		if (!class_exists('fm_module_servers')) {
			include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_servers.php');
		}
		
		/** Function to get all AP server_names from IDs (including from groups) */
		if (!$wlan_ap_ids) {
			basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_name', 'server_');
			foreach ($fmdb->last_result as $server_info) {
				$wlan_aps[] = $server_info->server_name;
			}
		} else {
			$associated_aps = null;
			foreach (explode(';', $wlan_ap_ids) as $server_id) {
				$wlan_aps = array_merge($wlan_aps, $fm_module_servers->getServerNames($server_id));
			}
		}
		$wlan_aps = array_unique($wlan_aps);
		
//		echo '<pre>';
//		var_dump($wlan_aps); exit;
		
		$block = false;
		$ebtables = getOption('use_ebtables', $_SESSION['user']['account_id'], $_SESSION['module']);
		if ($ebtables === false) {
			$ebtables = getOption('use_ebtables');
		}
		$ebtables = ($ebtables == 'yes') ? 'ebtables' : null;
		
		foreach ($wlan_aps as $ap) {
			basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', $ap, 'server_', 'server_name');
			if ($fmdb->num_rows) {
				$server_info = $fmdb->last_result[0];
				/** Buildconf each server to add client to deny list */
				$command_args = array('buildconf', 'buildconf');
				$build_conf = autoRunRemoteCommand($server_info, $command_args, 'return');
				
				/** Block current client connection */
				$command_args = array('block-wifi-client', '-o web block=' . $mac . ' ' . $ebtables);
				$block = autoRunRemoteCommand($server_info, $command_args, 'return');
			}
		}
		
		/** Add error detection */
		
		echo 'Success';
	}
	
}

if (!isset($fm_wifi_acls))
	$fm_wifi_acls = new fm_wifi_acls();

?>
