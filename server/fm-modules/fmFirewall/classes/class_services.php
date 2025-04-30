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
 | fmFirewall: Easily manage one or more software firewalls                |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmfirewall/                        |
 +-------------------------------------------------------------------------+
*/

class fm_module_services {
	
	/**
	 * Displays the service list
	 */
	function rows($result, $page, $total_pages) {
		global $fmdb;
		
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;

		if (currentUserCan('manage_services', $_SESSION['module'])) {
			$bulk_actions_list = array(_('Delete'));
		}

		$start = $_SESSION['user']['record_count'] * ($page - 1);
		echo displayPagination($page, $total_pages, @buildBulkActionMenu($bulk_actions_list));
		echo '<div class="overflow-container">';

		$table_info = array(
						'class' => 'display_results sortable',
						'id' => 'table_edits',
						'name' => 'services'
					);

		if (is_array($bulk_actions_list)) {
			$title_array[] = array(
								'title' => '<input type="checkbox" class="tickall" onClick="toggle(this, \'bulk_list[]\')" />',
								'class' => 'header-tiny header-nosort'
							);
		}
		$title_array = array_merge((array) $title_array, array(
			array('title' => __('Service Name'), 'rel' => 'service_name'),
			array('title' => __('Type'), 'rel' => 'service_type'),
			array('title' => __('Source Ports'), 'class' => 'header-nosort'),
			array('title' => __('Dest Ports'), 'class' => 'header-nosort'),
			array('title' => __('Flags'), 'class' => 'header-nosort'),
			array('title' => _('Comment'), 'class' => 'header-nosort'),
			array('title' => _('Actions'), 'class' => 'header-actions header-nosort')
		));

		echo '<div class="table-results-container">';
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
			printf('<p id="table_edits" class="noresult" name="services">%s</p>', __('There are no services defined.'));
		}
	}

	/**
	 * Adds the new service
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG, $global_form_field_excludes;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}services`";
		$sql_fields = '(';
		$sql_values = '';
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		
		$exclude = array_merge($global_form_field_excludes, array('service_id', 'port_src', 'port_dest'));

		$log_message = __("Added service with the following") . ":\n";
		$logging_excluded_fields = array('account_id');

		foreach ($post as $key => $data) {
			if (($key == 'service_name') && empty($data)) return __('No service name defined.');
			if (!in_array($key, $exclude)) {
				$sql_fields .= $key . ', ';
				$sql_values .= "'$data', ";
				if ($data && !in_array($key, $logging_excluded_fields)) {
					if ($key == 'service_tcp_flags') {
						$data = $this->getTCPFlags($data);
					}
					$log_message .= formatLogKeyData('service_', $key, $data);
				}
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the service because a database error occurred.'), 'sql');
		}

		addLogEntry($log_message);
		return true;
	}

	/**
	 * Updates the selected service
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG, $global_form_field_excludes;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		$exclude = array_merge($global_form_field_excludes, array('service_id', 'port_src', 'port_dest'));

		$sql_edit = '';
		$old_name = getNameFromID($post['service_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', 'service_', 'service_id', 'service_name');
		
		$log_message = sprintf(__("Updated service '%s' to the following"), $old_name) . ":\n";

		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . $data . "', ";
				if ($data) {
					if ($key == 'service_tcp_flags') {
						$data = $this->getTCPFlags($data);
					}
					$log_message .= formatLogKeyData('service_', $key, $data);
				}
			}
		}
		$sql = rtrim($sql_edit, ', ');
		
		// Update the service
		$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}services` SET $sql WHERE `service_id`={$post['service_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not update the service because a database error occurred.'), 'sql');
		}
		
		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

//		setBuildUpdateConfigFlag(getServerSerial($post['service_id'], $_SESSION['module']), 'yes', 'build');
		
		addLogEntry($log_message);
		return true;
	}
	
	/**
	 * Deletes the selected service
	 */
	function delete($service_id) {
		global $fmdb, $__FM_CONFIG;
		
		/** Does the service_id exist for this account? */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', $service_id, 'service_', 'service_id');
		if ($fmdb->num_rows) {
			/** Is the service_id present in a policy? */
			if (isItemInPolicy($service_id, 'service')) return __('This service could not be deleted because it is associated with one or more policies.');
			
			/** Delete service */
			$tmp_name = getNameFromID($service_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', 'service_', 'service_id', 'service_name');
			if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', $service_id, 'service_', 'deleted', 'service_id')) {
				addLogEntry("Deleted service '$tmp_name'.");
				return true;
			}
		}
		
		return formatError(__('This service could not be deleted.'), 'sql');
	}


	function displayRow($row) {
		global $__FM_CONFIG;
		
		$disabled_class = ($row->service_status == 'disabled') ? ' class="disabled"' : null;
		
		$checkbox = null;
		$edit_status = sprintf('<span rel="s%s">%s</span>', $row->service_id, $__FM_CONFIG['module']['icons']['search']);

		if (currentUserCan('manage_services', $_SESSION['module'])) {
			$edit_status .= '<a class="edit_form_link" name="' . $row->service_type . '" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			if (!isItemInPolicy($row->service_id, 'service')) {
				$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
				$checkbox = '<td><input type="checkbox" name="bulk_list[]" value="' . $row->service_id .'" /></td>';
			} else {
				$checkbox = '<td></td>';
			}
		}
		$edit_status = '<td class="column-actions">' . $edit_status . '</td>';
		
		/** Process TCP Flags */
		if ($row->service_type == 'tcp') {
			$service_tcp_flags = $this->getTCPFlags($row->service_tcp_flags);
		} elseif ($row->service_type == 'icmp') {
			$icmp_type = ($row->service_icmp_type == -1) ? 'any' : $row->service_icmp_type;
			$icmp_code = ($row->service_icmp_code == -1) ? 'any' : $row->service_icmp_code;
			$service_tcp_flags = "$icmp_type : $icmp_code";
		} else $service_tcp_flags = null;
		
		echo <<<HTML
			<tr id="$row->service_id" name="$row->service_name"$disabled_class>
				$checkbox
				<td>$row->service_name</td>

HTML;
		$src_ports = ($row->service_src_ports) ? str_replace(':', ' &rarr; ', $row->service_src_ports) : 'any';
		$dest_ports = ($row->service_dest_ports) ? str_replace(':', ' &rarr; ', $row->service_dest_ports) : 'any';
		
		echo <<<HTML
			<td>$row->service_type</td>
			<td>$src_ports</td>
			<td>$dest_ports</td>
			<td>$service_tcp_flags</td>

HTML;
		$comments = nl2br($row->service_comment);
		echo <<<HTML
				<td>$comments</td>
				$edit_status
			</tr>

HTML;
	}

	/**
	 * Displays the form to add new service
	 */
	function printForm($data = '', $action = 'add', $type = 'tcp') {
		global $__FM_CONFIG;

		if (!$type) $type = 'tcp';
		
		$service_id = 0;
		$service_name = $service_tcp_flags = $service_comment = null;
		$service_icmp_type = $service_icmp_code = null;
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}

		/** Show/hide divs */
		if ($type == 'icmp') {
			$icmp_option = 'block';
			$tcpudp_option = $tcp_option = 'none';
		} elseif ($type == 'tcp') {
			$icmp_option = 'none';
			$tcpudp_option = $tcp_option = 'block';
		} else {
			$icmp_option = $tcp_option = 'none';
			$tcpudp_option = 'block';
		}

		$service_name_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', 'service_name');
		$service_type = buildSelect('service_type', 'service_type', enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', 'service_type'), $type, 1);
		
		@list($port_src_start, $port_src_end) = explode(':', $service_src_ports);
		@list($port_dest_start, $port_dest_end) = explode(':', $service_dest_ports);
		
		/** Process TCP Flags */
		@list($tcp_flag_mask, $tcp_flag_settings) = explode(':', $service_tcp_flags);
		$tcp_flags_mask_form = $tcp_flags_settings_form = $tcp_flags_head = null;
		foreach ($__FM_CONFIG['tcp_flags'] as $flag => $bit) {
			$tcp_flags_head .= '<th title="' . $flag .'">' . $flag . "</th>\n";
			
			$tcp_flags_mask_form .= '<td><input type="checkbox" name="service_tcp_flags[mask][' . $bit . ']" ';
			if ($bit & (integer) $tcp_flag_mask) $tcp_flags_mask_form .= 'checked';
			$tcp_flags_mask_form .= "/></td>\n";

			$tcp_flags_settings_form .= '<td><input type="checkbox" name="service_tcp_flags[settings][' . $bit . ']" ';
			if ($bit & $tcp_flag_settings) $tcp_flags_settings_form .= 'checked';
			$tcp_flags_settings_form .= "/></td>\n";
		}
		
		$popup_title = $action == 'add' ? __('Add Service') : __('Edit Service');
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		$return_form = sprintf('
		%s
		<form name="manage" id="manage">
			<input type="hidden" name="page" value="services" />
			<input type="hidden" name="action" value="%s" />
			<input type="hidden" name="service_id" value="%s" />
			<table class="form-table">
				<tr>
					<th width="33&#37;" scope="row"><label for="service_name">%s</label></th>
					<td width="67&#37;"><input name="service_name" id="service_name" type="text" value="%s" size="40" placeholder="http" maxlength="%d" class="required" /></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="service_type">%s</label></th>
					<td width="67&#37;">
						%s
						<div id="icmp_option" style="display: %s;">
							<label for="service_icmp_type">Type</label> <input type="number" name="service_icmp_type" value="%s" style="width: 5em;" onkeydown="return validateNumber(event)" placeholder="0" max="40" /><br />
							<label for="service_icmp_code">Code</label> <input type="number" name="service_icmp_code" value="%s" style="width: 5em;" onkeydown="return validateNumber(event)" placeholder="0" max="15" />
						</div>
						<div id="tcpudp_option" style="display: %s;">
							<h4>%s</h4>
							<label for="port_src_start">%s</label> <input type="number" name="port_src[]" value="%s" placeholder="0" style="width: 5em;" onkeydown="return validateNumber(event)" max="65535" /> 
							<label for="port_src_end">%s</label> <input type="number" name="port_src[]" value="%s" placeholder="0" style="width: 5em;" onkeydown="return validateNumber(event)" max="65535" />
							<h4>%s</h4>
							<label for="port_dest_start">%s</label> <input type="number" name="port_dest[]" value="%s" placeholder="0" style="width: 5em;" onkeydown="return validateNumber(event)" max="65535" /> 
							<label for="port_dest_end">%s</label> <input type="number" name="port_dest[]" value="%s" placeholder="0" style="width: 5em;" onkeydown="return validateNumber(event)" max="65535" />
						</div>
						<div id="tcp_option" style="display: %s;">
							<h4>%s <a href="JavaScript:void(0);" class="tooltip-top" data-tooltip="%s"><i class="fa fa-question-circle" aria-hidden="true"></i></a></h4>
							<table class="form-table tcp-flags">
								<tbody>
									<tr>
										<th></th>
										%s
									</tr>
									<tr>
										<th style="text-align: right;" title="%s">%s</th>
										%s
									</tr>
									<tr>
										<th style="text-align: right;">%s</th>
										%s
									</tr>
								</tbody>
							</table>
						</div>
					</td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="service_comment">%s</label></th>
					<td width="67&#37;"><textarea id="service_comment" name="service_comment" rows="4" cols="30">%s</textarea></td>
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
				$popup_header,
				$action, $service_id,
				__('Service Name'), $service_name, $service_name_length,
				__('Service Type'), $service_type,
				$icmp_option, $service_icmp_type, $service_icmp_code,
				$tcpudp_option, __('Source Port Range'), __('Start'), $port_src_start, __('End'), $port_src_end,
				__('Destination Port Range'), __('Start'), $port_dest_start, __('End'), $port_dest_end,
				$tcp_option, __('TCP Flags'), __('TCP flags set here will override the policy TCP flags'), $tcp_flags_head, __('Only iptables uses the Mask bit'), __('Mask'),
				$tcp_flags_mask_form, __('Settings'), $tcp_flags_settings_form,
				_('Comment'), $service_comment,
				$popup_footer
			);

		return $return_form;
	}
	
	
	function validatePost($post) {
		global $fmdb, $__FM_CONFIG;
		
		if (empty($post['service_name'])) return __('No service name defined.');
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', 'service_name');
		if ($field_length !== false && strlen($post['service_name']) > $field_length) return sprintf(dngettext($_SESSION['module'], 'Service name is too long (maximum %d character).', 'Service name is too long (maximum %d characters).', $field_length), $field_length);
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', $post['service_name'], 'service_', 'service_name', "AND service_type='{$post['service_type']}' AND service_id!={$post['service_id']}");
		if ($fmdb->num_rows) return __('This service name already exists.');
		
		/** Set ports */
		if ($post['service_type'] != 'icmp') {
			foreach ($post['port_src'] as $port) {
				if (!empty($port) && !verifyNumber($port, 0, 65535, false)) return sprintf(__('Source ports must be a valid %s port range.'), strtoupper($post['service_type']));
				if (empty($port) || $port == 0) {
					$post['port_src'] = array('', '');
					break;
				}
			}
			sort($post['port_src']);
			$post['service_src_ports'] = implode(':', $post['port_src']);
			if ($post['service_src_ports'] == ':') $post['service_src_ports'] = null;
			
			foreach ($post['port_dest'] as $port) {
				if (!empty($port) && !verifyNumber($port, 0, 65535, false)) return sprintf(__('Destination ports must be a valid %s port range.'), strtoupper($post['service_type']));
				if (empty($port) || $port == 0) {
					$post['port_dest'] = array('', '');
					break;
				}
			}
			sort($post['port_dest']);
			$post['service_dest_ports'] = implode(':', $post['port_dest']);
			if ($post['service_dest_ports'] == ':') $post['service_dest_ports'] = null;
			
			unset($post['service_icmp_code']);
			unset($post['service_icmp_type']);
		} else {
			if (!empty($post['service_icmp_type']) && !verifyNumber($post['service_icmp_type'], -1, 40, false)) return __('ICMP type is invalid.');
			if (empty($post['service_icmp_type'])) $post['service_icmp_type'] = 0;
			
			if (!empty($post['service_icmp_code']) && !verifyNumber($post['service_icmp_code'], -1, 15, false)) return __('ICMP code is invalid.');
			if (empty($post['service_icmp_code'])) $post['service_icmp_code'] = 0;
		}
		
		/** Process TCP Flags */
		if (@is_array($post['service_tcp_flags']) && $post['service_type'] == 'tcp') {
			$decimals['settings'] = $decimals['mask'] = 0;
			foreach ($post['service_tcp_flags'] as $type_array => $dec_array) {
				foreach ($dec_array as $dec => $checked) {
					$decimals[$type_array] += $dec;
				}
			}
			$post['service_tcp_flags'] = implode(':', $decimals);
		} else $post['service_tcp_flags'] = null;
		
		return $post;
	}
	
	
	function getTCPFlags($flag_bits, $type = 'display') {
		global $__FM_CONFIG;

		if (!$flag_bits) return null;
		
		@list($tcp_flag_mask, $tcp_flag_settings) = explode(':', $flag_bits);
		$tcp_flag_mask = (int) $tcp_flag_mask;
		$tcp_flag_settings = (int) $tcp_flag_settings;
		$service_tcp_flags['mask'] = $service_tcp_flags['settings'] = null;
		
		foreach ($__FM_CONFIG['tcp_flags'] as $flag => $bit) {
			if (in_array($type, array('iptables', 'display')) && ($bit & $tcp_flag_mask)) $service_tcp_flags['mask'] .= $flag . ',';
			if ($type == 'ipfw' && (($bit & $tcp_flag_mask) && !($bit & $tcp_flag_settings))) $service_tcp_flags['settings'] .= '!' . strtolower($flag) . ',';
			if ($type == 'ipfilter' && ($bit & $tcp_flag_mask)) $service_tcp_flags['mask'] .= substr($flag, 0, 1);
			if ($bit & $tcp_flag_settings) {
				switch ($type) {
					case 'iptables':
					case 'display':
						$service_tcp_flags['settings'] .= $flag . ',';
						break;
					case 'ipfw':
						$service_tcp_flags['settings'] .= strtolower($flag) . ',';
						break;
					case 'ipfilter':
						$service_tcp_flags['settings'] .= substr($flag, 0, 1);
						break;
				}
			}

			if (!$tcp_flag_settings) {
				$service_tcp_flags['settings'] = in_array($type, array('iptables', 'display')) ? 'NONE' : null;
			}

			if (in_array($type, array('iptables', 'display'))) {
				if (!$tcp_flag_mask) $service_tcp_flags['mask'] = 'NONE';
				if ($tcp_flag_mask == array_sum($__FM_CONFIG['tcp_flags'])) $service_tcp_flags['mask'] = 'ALL';
				if ($tcp_flag_settings == array_sum($__FM_CONFIG['tcp_flags'])) $service_tcp_flags['settings'] = 'ALL';
			}
		}
		
		$service_tcp_flags['mask'] = rtrim($service_tcp_flags['mask'], ',');
		$service_tcp_flags['settings'] = rtrim($service_tcp_flags['settings'], ',');
		if ($type == 'ipfilter') krsort($service_tcp_flags);
		else ksort($service_tcp_flags);
		
		$service_tcp_flags = trim(implode(' ', $service_tcp_flags));

		switch ($type) {
			case 'iptables':
				return (substr_count($service_tcp_flags, 'NONE') != 2) ? ' --tcp-flags ' . $service_tcp_flags : null;
			case 'ipfw':
				$service_tcp_flags = str_replace(' ', ',', $service_tcp_flags);
				if (in_array($service_tcp_flags, array('!ack,syn', 'syn,!ack'))) return ' setup';
				return ' tcpflags ' . $service_tcp_flags;
			case 'ipfilter':
				$service_tcp_flags = str_replace(' ', '/', $service_tcp_flags);
				return ' flags ' . $service_tcp_flags;
			default:
				return $service_tcp_flags;
		}
	}
	
}

if (!isset($fm_module_services))
	$fm_module_services = new fm_module_services();
