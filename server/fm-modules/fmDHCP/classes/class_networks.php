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

class fm_dhcp_networks extends fm_dhcp_objects {
	
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
			return formatError(__('This network could not be deleted because a database error occurred.'), 'sql');
		} else {
			setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
			addLogEntry(sprintf(__("Network '%s' was deleted."), $tmp_name));
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
		
		$edit_status  = $checkbox = '';
		$icons = array();
		
		if (currentUserCan('manage_networks', $_SESSION['module'])) {
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
		
		echo <<<HTML
		<tr id="$row->config_id" name="$row->config_data" $class>
			<td>$checkbox</td>
			<td>$row->config_data $icons</td>
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
	 * @return string|void
	 */
	function printObjectForm($data = '', $action = 'add', $type = 'subnet', $addl_vars = null) {
		$function = 'print' . ucfirst($type) . 'Form';
		if (!method_exists($this, $function)) {
			return returnError();
		}
		return $this->$function($data, $action, $type, $addl_vars);
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
	function printSubnetsForm($data = '', $action = 'add', $type = 'subnet', $addl_vars = null) {
		global $fmdb, $__FM_CONFIG;
		
		$config_id = 0;
		$config_data = $netmask = $config_comment = $range_input_forms = null;
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		if (is_array($addl_vars)) {
			extract($addl_vars);
		}
		
		/** Get child elements */
		if ($config_id) {
			@list($config_data, $null, $netmask) = explode(' ', $config_data);
			
			$range = str_replace(array('range', ';'), '', $this->getConfig($config_id, 'range'));
			$i = 1;
			foreach (explode("\n", $range) as $range_entry) {
				$checked = null;
				$line = explode(' ', trim($range_entry));
				if ($line[0] == 'dynamic-bootp') {
					$checked = 'checked';
					@list($null, $start, $end) = $line;
				} else {
					@list($start, $end) = $line;
				}
				$range_input_forms .= $this->getRangeInputForm($i, array($start, $end, $checked));
				$i++;
			}
		} else {
			$range_input_forms = $this->getRangeInputForm();
		}
		$authoritative = ($this->getConfig($config_id, 'authoritative') == 'on') ? 'checked' : null;
		
		$return_form = sprintf('<input type="hidden" name="authoritative" value="off" />
							<tr>
								<th width="33&#37;" scope="row"></th>
								<td width="67&#37;"><input name="authoritative" id="authoritative" type="checkbox" value="on" %s /> <label for="authoritative">%s</label></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="config_name">%s</label></th>
								<td width="67&#37;"><input name="config_name" id="config_name" type="text" value="%s" placeholder="10.1.2.0" class="required" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="netmask">%s</label></th>
								<td width="67&#37;"><input name="netmask" id="netmask" type="text" value="%s" placeholder="255.255.255.0" class="required" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="range[]">%s</label></th>
								<td width="67&#37;" id="more">
									%s
									<p class="add_more"><a id="add_more" href="#">+ %s</a></p>
								</td>
							</tr>',
				$authoritative, __('Authoritative for this subnet'),
				__('Network Address'), $config_data,
				__('Netmask'), $netmask,
				__('Address Ranges'), $range_input_forms, __('Add another range')
			);

		return $return_form;
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
	function printSharedForm($data = '', $action = 'add', $type = 'shared', $addl_vars = null) {
		global $__FM_CONFIG;
		
		$config_id = 0;
		$config_data = $config_comment = null;
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		if (is_array($addl_vars)) {
			extract($addl_vars);
		}

		/** Get child elements */
		$authoritative = ($this->getConfig($config_id, 'authoritative') == 'on') ? 'checked' : null;
		
		$return_form = sprintf('<input type="hidden" name="authoritative" value="off" />
							<tr>
								<th width="33&#37;" scope="row"></th>
								<td width="67&#37;"><input name="authoritative" id="authoritative" type="checkbox" value="on" %s /> <label for="authoritative">%s</label></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="config_name">%s</label></th>
								<td width="67&#37;"><input name="config_name" id="config_name" type="text" value="%s" placeholder="10.1.2-5" class="required" /></td>
							</tr>',
				$authoritative, __('Authoritative for this shared network'),
				__('Shared Network'), $config_data
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
		if (!is_array($post)) return $post;
		
		if ($post['config_type'] == 'subnets') {
			if (empty($post['config_name'])) return __('No network is defined.');
			if (empty($post['netmask'])) return __('No network mask is defined.');
			
			if (!verifyIPAddress($post['config_name'])) return sprintf(__('%s is not valid.'), $post['config_name']);
			if (!verifyIPAddress($post['netmask'])) return sprintf(__('%s is not valid.'), $post['netmask']);

			$post['config_name'] = $post['config_name'] . ' netmask ' . $post['netmask'];
			unset($post['netmask']);
			
			/** Process subnet ranges */
			$clean_range = '';
			foreach ($post['range'] as $range_array) {
				if (!$range_array['start']) continue;
				if (isset($range_array['dynamic_bootp'])) {
					$clean_range .= $range_array['dynamic_bootp'] . ' ';
				}
				if (!verifyIPAddress($range_array['start'])) return sprintf(__('%s is not valid.'), $range_array['start']);
				if (!verifyIPAddress($range_array['end'])) return sprintf(__('%s is not valid.'), $range_array['end']);
				$clean_range .= $range_array['start'] . ' ' . $range_array['end'] . ";\n";
			}
			$post['range'] = rtrim(trim($clean_range), ';');
		}
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $post['config_name'], 'config_', 'config_data', "AND config_type='" . rtrim($post['config_type'], 's') . "' AND config_name='" . rtrim($post['config_type'], 's') . "' AND config_is_parent='yes' AND config_id!='{$post['config_id']}'");
		if ($fmdb->num_rows) return __('This network already exists.');

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
			array('title' => __('Name'), 'rel' => 'config_data')
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
