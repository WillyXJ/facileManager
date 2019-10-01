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
 | fmDHCP: Easily manage one or more ISC DHCP servers                      |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdhcp/                            |
 +-------------------------------------------------------------------------+
*/

require_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_objects.php');

class fm_dhcp_peers extends fm_dhcp_objects {
	
	/**
	 * Deletes the selected server
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmDHCP
	 *
	 * @param integer $id ID to delete
	 * @return boolean or string
	 */
	function delete($id, $server_serial_no = 0) {
		global $fmdb, $__FM_CONFIG;
		
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_', 'config_id', 'config_data');

		/** Delete associated children */
		updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $id, 'config_', 'deleted', 'config_parent_id');
		
		/** Delete item */
		if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $id, 'config_', 'deleted', 'config_id') === false) {
			return formatError(__('This failover peer could not be deleted because a database error occurred.'), 'sql');
		} else {
			setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
			addLogEntry(sprintf(__("Failover peer '%s' was deleted."), $tmp_name));
			return true;
		}
	}


	/**
	 * Displays the server entry table row
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmDHCP
	 *
	 * @param object $row Single data row from $results
	 * @return null
	 */
	function displayRow($row) {
		global $__FM_CONFIG;
		
		$class = ($row->config_status == 'disabled') ? 'disabled' : null;
		
		$edit_status = $edit_actions = $checkbox = $icons = null;
		
		if (currentUserCan('manage_peers', $_SESSION['module'])) {
			$edit_status = '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a class="status_form_link" href="#" rel="';
			$edit_status .= ($row->config_status == 'active') ? 'disabled' : 'active';
			$edit_status .= '">';
			$edit_status .= ($row->config_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
			$edit_status .= '</a>';
			$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			$edit_status = '<td id="row_actions">' . $edit_status . '</td>';
			$checkbox = '<td><input type="checkbox" name="bulk_list[]" value="' . $row->config_id .'" /></td>';
		}
		
		$edit_status = $edit_actions . $edit_status;
		
		$primary = getNameFromID($this->getConfig($row->config_id, 'address'), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name') . ':' . $this->getConfig($row->config_id, 'port');
		$secondary = getNameFromID($this->getConfig($row->config_id, 'peer-address'), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name') . ':' . $this->getConfig($row->config_id, 'peer-port');
		
		if ($class) $class = 'class="' . $class . '"';
		
		echo <<<HTML
		<tr id="$row->config_id" name="$row->config_data" $class>
			$checkbox
			<td>$row->config_data</td>
			<td>$primary</td>
			<td>$secondary</td>
			<td>$row->config_comment</td>
			$edit_status
		</tr>

HTML;
	}

	/**
	 * Displays the add/edit form
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmDHCP
	 *
	 * @param array $data Either $_POST data or returned SQL results
	 * @param string $action Add or edit
	 * @return string
	 */
	function printForm($data = '', $action = 'add', $type = 'host', $addl_vars = null) {
		global $fmdb, $__FM_CONFIG;
		
		$config_id = $config_parent_id = 0;
		$config_data = $config_comment = $load_balancing_hash = null;
		$server_serial_no = (isset($_REQUEST['request_uri']['server_serial_no']) && (intval($_REQUEST['request_uri']['server_serial_no']) > 0 || $_REQUEST['request_uri']['server_serial_no'][0] == 'g')) ? sanitize($_REQUEST['request_uri']['server_serial_no']) : 0;
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		$popup_title = ($action == 'add') ? __('Add Peer') : __('Edit Peer');
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		$avail_servers = availableServers('serial', 'servers');
		$primary = buildSelect('address', 'address', $avail_servers, $this->getConfig($config_id, 'address'), 1, null, true);
		$primary_port = $this->getConfig($config_id, 'port');
		$secondary = buildSelect('peer-address', 'peer-address', $avail_servers, $this->getConfig($config_id, 'peer-address'), 1, null, true);
		$secondary_port = $this->getConfig($config_id, 'peer-port');
		$load_balancing_entry = explode(' ', $this->getConfig($config_id, 'load-balancing'));
		$load_balancing_hash = isset($load_balancing_entry[1]) ? $load_balancing_entry[1] : null;
		$load_balancing = buildSelect('load-balancing', 'load-balancing', array('hba', 'split'), $load_balancing_entry[0]);
		$max_response_delay = $this->getConfig($config_id, 'max-response-delay');
		$max_unacked_updates = $this->getConfig($config_id, 'max-unacked-updates');
		$mclt = $this->getConfig($config_id, 'mclt');
		$load_balance_max_seconds = $this->getConfig($config_id, 'load balance max seconds');
		
		$return_form = sprintf('<form name="manage" id="manage" method="post" action="">
		%s
			<input type="hidden" name="action" value="%s" />
			<input type="hidden" name="config_id" value="%d" />
			<input type="hidden" name="config_type" value="%s" />
			<input type="hidden" name="server_serial_no" value="%s" />
			<div id="tabs">
				<div id="tab">
					<input type="radio" name="tab-group-1" id="tab-1" checked />
					<label for="tab-1">%s</label>
					<div id="tab-content">
						<table class="form-table">
							<tr>
								<th width="33&#37;" scope="row"><label for="config_name">%s</label></th>
								<td width="67&#37;"><input name="config_name" id="config_name" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="address">%s</label></th>
								<td width="67&#37;" nowrap>%s : <input name="port" id="port" type="number" value="%s" placeholder="847" style="width: 5em;" onkeydown="return validateNumber(event)" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="peer-address">%s</label></th>
								<td width="67&#37;" nowrap>%s : <input name="peer-port" id="peer-port" type="number" value="%s" placeholder="647" style="width: 5em;" onkeydown="return validateNumber(event)" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="load-balancing">%s</label></th>
								<td width="67&#37;" nowrap>
									%s
									<input name="load-balancing-hash" id="load-balancing-hash" type="text" value="%s" style="width: 11.5em;" />
								</td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="mclt">%s</label></th>
								<td width="67&#37;"><input name="mclt" id="mclt" type="number" value="%s" style="width: 5em;" onkeydown="return validateNumber(event)" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="config_comment">%s</label></th>
								<td width="67&#37;"><textarea id="config_comment" name="config_comment" rows="4" cols="30">%s</textarea></td>
							</tr>
						</table>
					</div>
				</div>
				<div id="tab">
					<input type="radio" name="tab-group-1" id="tab-2" />
					<label for="tab-2">%s</label>
					<div id="tab-content">
						<table class="form-table">
							<tr>
								<th width="33&#37;" scope="row"><label for="max-response-delay">%s</label></th>
								<td width="67&#37;"><input name="max-response-delay" id="max-response-delay" type="number" value="%s" style="width: 5em;" onkeydown="return validateNumber(event)" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="max-unacked-updates">%s</label></th>
								<td width="67&#37;"><input name="max-unacked-updates" id="max-unacked-updates" type="number" value="%s" style="width: 5em;" onkeydown="return validateNumber(event)" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="load balance max seconds">%s</label></th>
								<td width="67&#37;"><input name="load balance max seconds" id="load balance max seconds" type="number" value="%s" style="width: 5em;" onkeydown="return validateNumber(event)" /></td>
							</tr>
						</table>
					</div>
				</div>
			</div>
		%s
		</form>
		<script>
			$(document).ready(function() {
				$("#manage select").select2({
					width: "80px",
					minimumResultsForSearch: 10
				});
				$("#manage select#address, #manage select#peer-address").select2({
					width: "150px",
					minimumResultsForSearch: 10,
					maximumSelectionSize: 1
				});
			});
		</script>',
				$popup_header, $action, $config_id, $type, $server_serial_no,
				__('Basic'),
				__('Peer Name'), $config_data,
				__('Primary Host : Port'), $primary, $primary_port,
				__('Secondary Host : Port'), $secondary, $secondary_port,
				__('Load Balancing'), $load_balancing, $load_balancing_hash,
				__('Minimum Client Lead Time'), $mclt,
				_('Comment'), $config_comment,
				__('Advanced'),
				__('Maximum Response Delay'), $max_response_delay,
				__('Maximum Unacknowledges Updates'), $max_unacked_updates,
				__('Load Balance Max Seconds'), $load_balance_max_seconds,
				$popup_footer
			);

		return $return_form;
	}
	
	/**
	 * Validates the user-submitted data (for add and edit)
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmDHCP
	 *
	 * @param array $post Posted data to validate
	 * @return array
	 */
	function validatePost($post) {
		global $fmdb, $__FM_CONFIG;
		
		$post = $this->validateObjectPost($post);
		if (!is_array($post)) return $post;

		if (empty($post['load-balancing-hash'])) return __('No load balancing hash is defined.');
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $post['config_name'], 'config_', 'config_data', "AND config_type='" . rtrim($post['config_type'], 's') . "' AND config_name='" . rtrim($post['config_type'], 's') . "' AND config_is_parent='yes' AND config_id!='{$post['config_id']}'");
		if ($fmdb->num_rows) return __('This peer name already exists.');
		
		$post['address'] = $post['address'][0];
		$post['peer-address'] = $post['peer-address'][0];
		
		/** Set default ports */
		if (empty($post['port'])) {
			$post['port'] = 847;
		}
		if (empty($post['peer-port'])) {
			$post['peer-port'] = 647;
		}
		
		$post['load-balancing'] = $post['load-balancing'] . ' ' . $post['load-balancing-hash'];
		$post['load balance max seconds'] = $post['load_balance_max_seconds'];
		unset($post['load-balancing-hash'], $post['load_balance_max_seconds']);
		
		return $post;
	}
	
	/**
	 * Gets the item table header
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmDHCP
	 *
	 * @return array
	 */
	function getTableHeader() {
		return array(__('Name'), __('Primary'), __('Secondary'), _('Comment'));
	}
	
	/**
	 * Gets the item included fields
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmDHCP
	 *
	 * @return array
	 */
	function getIncludedFields() {
		return array();
	}

}

?>
