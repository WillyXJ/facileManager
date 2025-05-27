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
 | fmDHCP: Easily manage one or more ISC DHCP servers                      |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdhcp/                            |
 +-------------------------------------------------------------------------+
*/

require_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_objects.php');

class fm_dhcp_hosts extends fm_dhcp_objects {
	
	/**
	 * Deletes the selected server
	 *
	 * @since 0.1
	 * @package facileManager
	 * @subpackage fmDHCP
	 *
	 * @param integer $id ID to delete
	 * @return boolean|string
	 */
	function delete($id, $server_serial_no = 0) {
		global $fmdb, $__FM_CONFIG;
		
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'config_', 'config_id', 'config_data');

		/** Delete associated children */
		updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $id, 'config_', 'deleted', 'config_parent_id');
		
		/** Delete item */
		if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $id, 'config_', 'deleted', 'config_id') === false) {
			return formatError(__('This host could not be deleted because a database error occurred.'), 'sql');
		} else {
			setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
			addLogEntry(sprintf(__("Host '%s' was deleted."), $tmp_name));
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
		global $fmdb, $__FM_CONFIG;
		
		$class = ($row->config_status == 'disabled') ? 'disabled' : null;
		
		$edit_status = $checkbox = $fixed_address = '';
		$icons = array();
		
		if (currentUserCan('manage_hosts', $_SESSION['module'])) {
			$edit_status = '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a class="status_form_link" href="#" rel="';
			$edit_status .= ($row->config_status == 'active') ? 'disabled' : 'active';
			$edit_status .= '">';
			$edit_status .= ($row->config_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
			$edit_status .= '</a>';
			$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			$edit_status = '<td class="column-actions">' . $edit_status . '</td>';
			$checkbox = '<input type="checkbox" name="bulk_list[]" value="' . $row->config_id .'" />';
		}
		$icons[] = sprintf('<a href="config-options.php?item_id=%d" class="tooltip-bottom mini-icon" data-tooltip="%s"><i class="mini-icon fa fa-sliders" aria-hidden="true"></i></a>', $row->config_id, __('Configure Additional Options'));
		
		if ($class) $class = 'class="' . $class . '"';
		if (is_array($icons)) {
			$icons = implode(' ', $icons);
		}
		
		/** Display fixed address */
		$query = 'SELECT * FROM `fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config` WHERE `config_status`!="deleted" AND `account_id`="' . $_SESSION['user']['account_id'] . '" AND `config_type`="host" AND 
			`config_name`="host" AND `config_data`="' . $row->config_data . '"';
		$fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$query = 'SELECT * FROM `fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config` WHERE `config_status`!="deleted" AND `account_id`="' . $_SESSION['user']['account_id'] . '" AND `config_type`="host" AND 
				`config_name`="fixed-address" AND `config_parent_id`="' . $fmdb->last_result[0]->config_id . '"';
			$fmdb->get_results($query);
			$fixed_address = $fmdb->last_result[0]->config_data;
		}
		
		echo <<<HTML
		<tr id="$row->config_id" name="$row->config_data" $class>
			<td>$checkbox</td>
			<td>$row->config_data $icons</td>
			<td>$fixed_address</td>
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
	function printObjectForm($data = '', $action = 'add', $type = 'host', $addl_vars = null) {
		global $__FM_CONFIG;
		
		$config_id = $config_parent_id = 0;
		$config_name = $config_data = $config_comment = null;
		
		$hw_address_types = array('ethernet', 'token-ring', 'fddi');
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}

		if (is_array($addl_vars)) {
			extract($addl_vars);
		}

		$config_name = $config_data;

		/** Get child elements */
		if (!isset($hardware_address_entry)) {
			$hardware_address_entry = explode(' ', $this->getConfig($config_id, 'hardware'));
		}
		$hardware_address = isset($hardware_address_entry[1]) ? $hardware_address_entry[1] : null;
		$hw_address_types = buildSelect('hardware-type', 'hardware-type', $hw_address_types, $hardware_address_entry[0]);
		if (!isset($fixed_address)) {
			$fixed_address = $this->getConfig($config_id, 'fixed-address');
		}
		
		$return_form = sprintf('<tr>
								<th width="33&#37;" scope="row"><label for="config_name">%s</label></th>
								<td width="67&#37;"><input name="config_name" id="config_name" type="text" value="%s" placeholder="client1.local" class="required" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="hardware">%s</label></th>
								<td width="67&#37;">%s <input name="hardware" id="hardware" type="text" value="%s" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="fixed-address">%s</label></th>
								<td width="67&#37;"><input name="fixed-address" id="fixed-address" type="text" value="%s" /></td>
							</tr>',
				__('Host Name'), $config_name,
				__('Hardware Address'), $hw_address_types, $hardware_address,
				__('Fixed IP Address'), $fixed_address
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
	 * @return array|string
	 */
	function validatePost($post) {
		global $fmdb, $__FM_CONFIG;
		
		$post = $this->validateObjectPost($post);
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $post['config_name'], 'config_', 'config_data', "AND config_type='" . rtrim($post['config_type'], 's') . "' AND config_name='" . rtrim($post['config_type'], 's') . "' AND config_is_parent='yes' AND config_id!='{$post['config_id']}'");
		if ($fmdb->num_rows) return __('This host already exists.');
		
		// basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $post['fixed-address'], 'config_', 'config_data', "AND config_type='host' AND config_name='fixed-address' AND config_is_parent='no' AND config_parent_id!='{$post['config_id']}'");
		// if ($fmdb->num_rows) return __('This address already exists.');
		
		/** Valid MAC address? */
		if ($post['hardware-type'] == 'ethernet' && version_compare(PHP_VERSION, '5.5.0', '>=') && !verifySimpleVariable($post['hardware'], FILTER_VALIDATE_MAC)) {
			return __('The hardware address is invalid.');
		}
		
		/** Valid IP address? */
		if ($post['hardware-type'] == 'ethernet' && !verifyIPAddress($post['fixed-address'])) {
			return __('The IP address is invalid.');
		}
		
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
		return array(
			array('title' => __('Hostname'), 'rel' => 'config_data'),
			array('title' => __('Fixed Address'), 'class' => 'header-nosort')
		);
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
