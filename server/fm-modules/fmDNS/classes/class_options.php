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
 | fmDNS: Easily manage one or more ISC BIND servers                       |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdns/                             |
 +-------------------------------------------------------------------------+
*/

class fm_module_options {
	
	/**
	 * Displays the option list
	 */
	function rows($result, $page, $total_pages) {
		global $fmdb;
		
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;

		$bulk_actions_list = array(_('Enable'), _('Disable'), _('Delete'));

		$start = $_SESSION['user']['record_count'] * ($page - 1);
		echo displayPagination($page, $total_pages, @buildBulkActionMenu($bulk_actions_list));

		if (!$result) {
			printf('<p id="table_edits" class="noresult" name="options">%s</p>', __('There are no options.'));
		} else {
			$table_info = array(
							'class' => 'display_results sortable',
							'id' => 'table_edits',
							'name' => 'options'
						);

			if (is_array($bulk_actions_list)) {
				$title_array[] = array(
									'title' => '<input type="checkbox" class="tickall" onClick="toggle(this, \'bulk_list[]\')" />',
									'class' => 'header-tiny header-nosort'
								);
			}
			if (isset($_GET['type']) && sanitize($_GET['type']) == 'ratelimit') {
				$title_array[] = array('title' => __('Zone'), 'rel' => 'domain_id');
			}
			$title_array[] = array('title' => __('Option'), 'rel' => 'cfg_name');
			$title_array[] = array('title' => __('Value'), 'rel' => 'cfg_data');
			$title_array[] = array('title' => _('Comment'), 'class' => 'header-nosort');
			
			$perms = ($results[0]->domain_id) ? zoneAccessIsAllowed(array($results[0]->domain_id), 'manage_zones') : currentUserCan('manage_servers', $_SESSION['module']);
			if ($perms) $title_array[] = array('title' => __('Actions'), 'class' => 'header-actions header-nosort');

			echo displayTableHeader($table_info, $title_array);
			
			$y = 0;
			for ($x=$start; $x<$num_rows; $x++) {
				if ($y == $_SESSION['user']['record_count']) break;
				$this->displayRow($results[$x], $perms);
				$y++;
			}
			
			echo "</tbody>\n</table>\n";
		}
	}

	/**
	 * Adds the new option
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate post */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		if (empty($post['cfg_name'])) return false;
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', sanitize($post['cfg_name']), 'cfg_', 'cfg_name', "AND cfg_type='{$post['cfg_type']}' AND server_serial_no='{$post['server_serial_no']}' AND view_id='{$post['view_id']}' AND domain_id='{$post['domain_id']}' AND server_id='{$post['server_id']}'");
		if ($fmdb->num_rows) {
			$num_same_config = $fmdb->num_rows;
			$query = "SELECT def_max_parameters FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}functions WHERE def_option='" . sanitize($post['cfg_name']) . "' AND def_option_type='{$post['cfg_type']}'";
			$fmdb->get_results($query);
			if ($fmdb->last_result[0]->def_max_parameters >= 0 && $num_same_config >= $fmdb->last_result[0]->def_max_parameters) {
				return __('This record already exists.');
			}
		}
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}config`";
		$sql_fields = '(';
		$sql_values = null;
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		
		$exclude = array('submit', 'action', 'cfg_id');
		
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$clean_data = sanitize($data);
				if (!strlen($clean_data) && ($key != 'cfg_comment') && ($post['cfg_name'] != 'forwarders')) return __('Empty values are not allowed.');
				if ($key == 'cfg_name' && !isDNSNameAcceptable($clean_data)) return sprintf(__('%s is not an acceptable option name.'), $clean_data);
				$sql_fields .= $key . ', ';
				$sql_values .= "'$clean_data', ";
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the option because a database error occurred.'), 'sql');
		}

		$tmp_name = $post['cfg_name'];
		$tmp_server_name = $post['server_serial_no'] ? getNameFromID($post['server_serial_no'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name') : 'All Servers';
		$tmp_view_name = $post['view_id'] ? getNameFromID($post['view_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name') : 'All Views';
		$tmp_domain_name = isset($post['domain_id']) ? "\nZone: " . displayFriendlyDomainName(getNameFromID($post['domain_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name')) : null;

		include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_acls.php');
		$cfg_data = (strpos($post['cfg_data'], 'acl_') !== false || strpos($post['cfg_data'], 'key_') !== false || \
			strpos($post['cfg_data'], 'domain_') !== false || \
			strpos($post['cfg_data'], 'master_') !== false) ? $fm_dns_acls->parseACL($post['cfg_data']) : $post['cfg_data'];
		addLogEntry("Added option:\nName: $tmp_name\nValue: $cfg_data\nServer: $tmp_server_name\nView: {$tmp_view_name}{$tmp_domain_name}\nComment: {$post['cfg_comment']}");
		return true;
	}

	/**
	 * Updates the selected option
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate post */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		if (isset($post['cfg_id']) && !isset($post['cfg_name'])) {
			$post['cfg_name'] = getNameFromID($post['cfg_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'cfg_id', 'cfg_name');
		}
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', sanitize($post['cfg_name']), 'cfg_', 'cfg_name', "AND cfg_id!={$post['cfg_id']} AND cfg_type='{$post['cfg_type']}' AND server_serial_no='{$post['server_serial_no']}' AND view_id='{$post['view_id']}' AND domain_id='{$post['domain_id']}' AND server_id='{$post['server_id']}'");
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			if ($result[0]->cfg_id != $post['cfg_id']) {
				$num_same_config = $fmdb->num_rows;
				$query = "SELECT def_max_parameters FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}functions WHERE def_option='" . sanitize($post['cfg_name']) . "' AND def_option_type='{$post['cfg_type']}'";
				$fmdb->get_results($query);
				if ($fmdb->last_result[0]->def_max_parameters >= 0 && $num_same_config > $fmdb->last_result[0]->def_max_parameters - 1) {
					return __('This record already exists.');
				}
			}
		}
		
		$exclude = array('submit', 'action', 'cfg_id');

		$sql_edit = null;
		
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$clean_data = sanitize($data);
				if (!strlen($clean_data) && ($key != 'cfg_comment') && ($post['cfg_name'] != 'forwarders')) return __('Empty values are not allowed.');
				if ($key == 'cfg_name' && !isDNSNameAcceptable($clean_data)) return sprintf(__('%s is not an acceptable option name.'), $clean_data);
				$sql_edit .= $key . "='" . $clean_data . "', ";
			}
		}
		$sql = rtrim($sql_edit, ', ');
		
		// Update the config
		$old_name = getNameFromID($post['cfg_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'cfg_id', 'cfg_name');
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` SET $sql WHERE `cfg_id`={$post['cfg_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not update the option because a database error occurred.'), 'sql');
		}

		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		$tmp_server_name = $post['server_serial_no'] ? getNameFromID($post['server_serial_no'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name') : 'All Servers';
		$tmp_view_name = $post['view_id'] ? getNameFromID($post['view_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name') : 'All Views';
		$tmp_domain_name = isset($post['domain_id']) ? "\nZone: " . displayFriendlyDomainName(getNameFromID($post['domain_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name')) : null;

		include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_acls.php');
		$cfg_data = (strpos($post['cfg_data'], 'acl_') !== false || strpos($post['cfg_data'], 'key_') !== false || \
			strpos($post['cfg_data'], 'domain_') !== false || \
			strpos($post['cfg_data'], 'master_') !== false) ? $fm_dns_acls->parseACL($post['cfg_data']) : $post['cfg_data'];
		addLogEntry("Updated option '$old_name' to:\nName: {$post['cfg_name']}\nValue: {$cfg_data}\nServer: $tmp_server_name\nView: {$tmp_view_name}{$tmp_domain_name}\nComment: {$post['cfg_comment']}");
		return true;
	}
	
	
	/**
	 * Deletes the selected option
	 */
	function delete($id, $server_serial_no = 0) {
		global $fmdb, $__FM_CONFIG;
		
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'cfg_id', 'cfg_name');
		$tmp_server_name = $server_serial_no ? getNameFromID($server_serial_no, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name') : 'All Servers';

		if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', $id, 'cfg_', 'deleted', 'cfg_id') === false) {
			return formatError(__('This option could not be deleted because a database error occurred.'), 'sql');
		} else {
			setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
			addLogEntry(sprintf(__("Option '%s' for %s was deleted."), $tmp_name, $tmp_server_name));
			return true;
		}
	}


	function displayRow($row, $perms) {
		global $fmdb, $__FM_CONFIG, $fm_dns_acls;
		
		if (!class_exists('fm_dns_acls')) {
			include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_acls.php');
		}
		
		$disabled_class = ($row->cfg_status == 'disabled') ? ' class="disabled"' : null;
		
		if ($perms) {
			$edit_uri = (strpos($_SERVER['REQUEST_URI'], '?')) ? $_SERVER['REQUEST_URI'] . '&' : $_SERVER['REQUEST_URI'] . '?';
			$edit_status = '<td id="row_actions">';
			$edit_status .= '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a class="status_form_link" href="#" rel="';
			$edit_status .= ($row->cfg_status == 'active') ? 'disabled' : 'active';
			$edit_status .= '">';
			$edit_status .= ($row->cfg_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
			$edit_status .= '</a>';
			$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			$edit_status .= '</td>';
			$checkbox = '<td><input type="checkbox" name="bulk_list[]" value="' . $row->cfg_id .'" /></td>';
		} else {
			$edit_status = null;
			$checkbox = '<td></td>';
		}
		
		$comments = nl2br($row->cfg_comment);
		
		/** Parse address_match_element configs */
		$cfg_data = $this->parseDefType($row->cfg_name, $row->cfg_data);
		
		$zone_row = null;
		if (isset($_GET['type']) && sanitize($_GET['type']) == 'ratelimit') {
			$domain_name = $row->domain_id ? getNameFromID($row->domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name') : '<span>All Zones</span>';
			$zone_row = '<td>' . $domain_name . '</td>';
			unset($domain_name);
		}
		$cfg_name = ($row->cfg_in_clause == 'yes') ? $row->cfg_name : '<b>' . $row->cfg_name . '</b>';

		echo <<<HTML
		<tr id="$row->cfg_id" name="$row->cfg_name"$disabled_class>
			$checkbox
			$zone_row
			<td>$cfg_name</td>
			<td>$cfg_data</td>
			<td>$comments</td>
			$edit_status
		</tr>
HTML;
	}

	/**
	 * Displays the form to add new option
	 */
	function printForm($data = '', $action = 'add', $cfg_type = 'global', $cfg_type_id = null) {
		global $fmdb, $__FM_CONFIG, $fm_dns_zones;
		
		$cfg_id = $domain_id = 0;
		if (!$cfg_type_id) $cfg_type_id = 0;
		$cfg_name = $cfg_root_dir = $cfg_zones_dir = $cfg_comment = null;
		$ucaction = ucfirst($action);
		$server_serial_no_field = $cfg_isparent = $cfg_parent = $cfg_data = null;
		
		switch(strtolower($cfg_type)) {
			case 'global':
			case 'ratelimit':
			case 'rrset':
				if (isset($_POST['item_sub_type'])) {
					$cfg_id_name = sanitize($_POST['item_sub_type']);
				} else {
					$cfg_id_name = isset($_POST['view_id']) ? 'view_id' : 'domain_id';
				}
				$data_holder = null;
				$server_serial_no = (isset($_REQUEST['request_uri']['server_serial_no']) && (intval($_REQUEST['request_uri']['server_serial_no']) > 0 || $_REQUEST['request_uri']['server_serial_no'][0] == 'g')) ? sanitize($_REQUEST['request_uri']['server_serial_no']) : 0;
				$server_serial_no_field = '<input type="hidden" name="server_serial_no" value="' . $server_serial_no . '" />';
				$request_uri = 'config-options.php';
				if (isset($_REQUEST['request_uri'])) {
					$request_uri .= '?';
					foreach ($_REQUEST['request_uri'] as $key => $val) {
						$request_uri .= $key . '=' . sanitize($val) . '&';
					}
					$request_uri = rtrim($request_uri, '&');
				}
				$disabled = $action == 'add' ? null : 'disabled';
				break;
			case 'logging':
				$name_holder = 'severity';
				$name_note = null;
				$data_holder = 'dynamic';
				$data_note = null;
				break;
			case 'keys':
				$name_holder = 'key';
				$name_note = null;
				$data_holder = 'rndc-key';
				$data_note = null;
				break;
		}
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		$cfg_isparent = buildSelect('cfg_isparent', 'cfg_isparent', enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_isparent'), $cfg_isparent, 1);
		$cfg_parent = buildSelect('cfg_parent', 'cfg_parent', $this->availableParents($cfg_id, $cfg_type), $cfg_parent);
		$avail_options_array = $this->availableOptions($action, $server_serial_no, $cfg_type, $cfg_name);
		$cfg_avail_options = buildSelect('cfg_name', 'cfg_name', $avail_options_array, $cfg_name, 1, $disabled, false, 'displayOptionPlaceholder()');

		$query = "SELECT def_type FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}functions WHERE def_function='$cfg_type' AND 
				def_option=";
		if ($action != 'add') {
			$query .= "'$cfg_name'";
		} else {
			$query .= "'{$avail_options_array[0]}'";
		}
		$results = $fmdb->get_results($query);
		
		if ($fmdb->num_rows) {
			$data_holder = $results[0]->def_type;
		}
		
		$cfg_data = sanitize($cfg_data);

		$popup_title = $action == 'add' ? __('Add Option') : __('Edit Option');
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		$addl_options = null;
		if ($cfg_type == 'ratelimit') {
			$available_zones = $fm_dns_zones->buildZoneJSON();

			$addl_options = sprintf('<tr>
					<th width="33&#37;" scope="row"><label for="cfg_name">%s</label></th>
					<td width="67&#37;"><input type="hidden" name="domain_id" class="domain_name" value="%d" /><br />
					<script>
					$(".domain_name").select2({
						createSearchChoice:function(term, data) { 
							if ($(data).filter(function() { 
								return this.text.localeCompare(term)===0; 
							}).length===0) 
							{return {id:term, text:term};} 
						},
						multiple: false,
						width: "200px",
						tokenSeparators: [",", " ", ";"],
						data: %s
					});
					$(".domain_name").change(function(){
						var $swap = $(this).parent().parent().next().find("td");
						var form_data = {
							server_serial_no: getUrlVars()["server_serial_no"],
							cfg_type: getUrlVars()["type"],
							cfg_name: $(this).parent().parent().next().find("td").find("select").val(),
							get_available_options: true,
							item_sub_type: "domain_id",
							item_id: $(this).val(),
							view_id: getUrlVars()["view_id"],
							is_ajax: 1
						};

						$.ajax({
							type: "POST",
							url: "fm-modules/%s/ajax/getData.php",
							data: form_data,
							success: function(response) {
								$swap.html(response);
								
								$("#manage select").select2({
									width: "200px",
									minimumResultsForSearch: 10
								});
							}
						});
					});
					</script>
				</tr>',
					__('Zone'), $domain_id, $available_zones, $_SESSION['module']
				);
		}
		
		$return_form = sprintf('<script>
			displayOptionPlaceholder("%s");
		</script>
		<form name="manage" id="manage" method="post" action="%s">
		%s
			<input type="hidden" name="action" value="%s" />
			<input type="hidden" name="cfg_id" value="%d" />
			<input type="hidden" name="cfg_type" value="%s" />
			<input type="hidden" name="%s" value="%s" />
			%s
			<table class="form-table">
				%s
				<tr>
					<th width="33&#37;" scope="row"><label for="cfg_name">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
				<tr class="value_placeholder">
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="cfg_comment">%s</label></th>
					<td width="67&#37;"><textarea id="cfg_comment" name="cfg_comment" rows="4" cols="30">%s</textarea></td>
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
				$cfg_data, $request_uri, $popup_header,
				$action, $cfg_id, $cfg_type, $cfg_id_name, $cfg_type_id, $server_serial_no_field,
				$addl_options,
				__('Option Name'), $cfg_avail_options,
				_('Comment'), $cfg_comment,
				$popup_footer
			);

		return $return_form;
	}
	
	
	function availableParents($cfg_id, $cfg_type) {
		global $fmdb, $__FM_CONFIG;
		
		$return[0][] = 'None';
		$return[0][] = '0';
		
		$query = "SELECT cfg_id,cfg_name,cfg_data FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}config WHERE 
				account_id='{$_SESSION['user']['account_id']}' AND cfg_status='active' AND cfg_isparent='yes' AND 
				cfg_id!=$cfg_id AND cfg_type='$cfg_type' ORDER BY cfg_name,cfg_data ASC";
		$result = $fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$results = $fmdb->last_result;
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$return[$i+1][] = $results[$i]->cfg_name . ' ' . $results[$i]->cfg_data;
				$return[$i+1][] = $results[$i]->cfg_id;
			}
		}
		
		return $return;
	}


	function availableViews() {
		global $fmdb, $__FM_CONFIG;
		
		$return[0][] = 'None';
		$return[0][] = '0';
		
		$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_id', 'view_');
		if ($fmdb->num_rows) {
			$results = $fmdb->last_result;
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$return[$i+1][] = $results[$i]->view_name;
				$return[$i+1][] = $results[$i]->view_id;
			}
		}
		
		return $return;
	}


	function availableOptions($action, $server_serial_no, $option_type = 'global', $cfg_name = null) {
		global $fmdb, $__FM_CONFIG;
		
		$temp_array = $return = null;
		
		if ($action == 'add') {
			if (isset($_POST['view_id'])) {
				$cfg_id_sql[] = 'view_id = ' . sanitize($_POST['view_id']);
			}
			if (isset($_POST['item_id'])) {
				if ($_POST['item_sub_type'] != 'domain_id') {
					$cfg_id_sql[] = 'domain_id=0';
				}
				$cfg_id_sql[] = sanitize($_POST['item_sub_type']) . ' = ' . sanitize($_POST['item_id']);
			}
			if (isset($cfg_id_sql)) {
				$cfg_id_sql = implode(' AND ', $cfg_id_sql);
			} else {
				$cfg_id_sql = 'view_id=0 AND domain_id=0';
			}
			
			$query = "SELECT cfg_name FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}config WHERE cfg_type='$option_type' AND cfg_status IN (
						'active', 'disabled'
					) AND account_id='{$_SESSION['user']['account_id']}' AND (
						(server_serial_no='$server_serial_no' AND $cfg_id_sql)
					)";
			$def_result = $fmdb->get_results($query);
			$def_result_count = $fmdb->num_rows;
			for ($i=0; $i<$def_result_count; $i++) {
				$temp_array[$i] = $def_result[$i]->cfg_name;
			}
			
			$query = "SELECT * FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}functions WHERE def_function='options'
				AND def_option_type='$option_type'";
			$query .= " AND def_clause_support LIKE '%";
			if (isset($option_type) && in_array($option_type, array_keys($__FM_CONFIG['options']['avail_types'])) &&
					isset($_POST['item_id']) && $_POST['item_id'] != 0) {
				switch ($_POST['item_sub_type']) {
					case 'view_id':
						$query .= 'V';
						break;
					case 'domain_id':
						$query .= 'Z';
						$domain_type = getNameFromID($_POST['item_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_type');
						$domain_type = ($domain_type == 'stub') ? 'B' : strtoupper($domain_type[0]);
						$query .= ($option_type != 'ratelimit') ? "%' AND def_zone_support LIKE '%$domain_type" : null;
						break;
					case 'server_id':
						$query .= 'S';
						break;
				}
			} else {
				$query .= 'O';
			}
			$query .= "%'";
		} else {
			$query = "SELECT * FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}functions WHERE def_function='options'
				AND def_option_type='$option_type' AND def_option='$cfg_name'";
		}
		$query .= " ORDER BY def_option ASC";

		$def_avail_result = $fmdb->get_results($query);
		$def_avail_result_count = $fmdb->num_rows;
		
		if ($def_avail_result_count) {
			$j=0;
			for ($i=0; $i<$def_avail_result_count; $i++) {
				$array_count_values = @array_count_values($temp_array);
				if ((is_array($temp_array) && array_search($def_avail_result[$i]->def_option, $temp_array) === false) ||
						!isset($temp_array) || $array_count_values[$def_avail_result[$i]->def_option] < $def_avail_result[$i]->def_max_parameters || 
						$def_avail_result[$i]->def_max_parameters < 0) {
					$return[$j] = $def_avail_result[$i]->def_option;
					$j++;
				}
			}
			return $return;
		}
		
		return;
	}
	
	
	function validatePost($post) {
		global $fmdb, $__FM_CONFIG;
		
		$post['cfg_comment'] = trim($post['cfg_comment']);
		
		if (is_array($post['cfg_data'])) $post['cfg_data'] = join(' ', $post['cfg_data']);
		
		/** Handle masters and also-notify formats */
		if ($post['cfg_data_port']) {
			$tmp_cfg_data[] = 'port ' . $post['cfg_data_port'];
		}
		if (!empty($post['cfg_data_dscp'])) {
			$tmp_cfg_data[] = 'dscp ' . $post['cfg_data_dscp'];
		}
		if (is_array($tmp_cfg_data)) {
			$tmp_cfg_data[] = '{ ' . $post['cfg_data'] . ',}';
			$post['cfg_data'] = join(' ', $tmp_cfg_data);
			unset($tmp_cfg_data);
		}
		unset($post['cfg_data_port'], $post['cfg_data_dscp']);
		$post['cfg_data'] = str_replace(',,', ',', $post['cfg_data']);
		
		if (isset($post['cfg_name'])) {
			$def_option = "'{$post['cfg_name']}'";
		} elseif (isset($post['cfg_id'])) {
			$def_option = "(SELECT cfg_name FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config WHERE cfg_id = {$post['cfg_id']})";
		} else return false;
		
		if (!isset($post['view_id'])) $post['view_id'] = 0;
		if (!isset($post['domain_id'])) $post['domain_id'] = 0;
		if (!isset($post['cfg_in_clause'])) $post['cfg_in_clause'] = 'yes';
		
		$query = "SELECT def_type,def_dropdown FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}functions WHERE def_option = $def_option";
		$result = $fmdb->get_results($query);
		if ($fmdb->num_rows) {
			if ($result[0]->def_dropdown == 'no') {
				$valid_types = trim(str_replace(array('(', ')'), '', $result[0]->def_type));
				
				switch ($valid_types) {
					case 'integer':
					case 'seconds':
					case 'minutes':
						if (!verifyNumber($post['cfg_data'])) return $post['cfg_data'] . ' is an invalid number.';
						break;
					case 'port':
						if (!verifyNumber($post['cfg_data'], 0, 65535)) return $post['cfg_data'] . ' is an invalid port number.';
						break;
					case 'quoted_string':
						$post['cfg_data'] = '"' . str_replace(array('"', "'"), '', $post['cfg_data']) . '"';
						break;
					case 'quoted_string | none':
					case 'quoted_string | none | hostname':
						$post['cfg_data'] = '"' . str_replace(array('"', "'"), '', $post['cfg_data']) . '"';
						if ($post['cfg_data'] == '"none"') $post['cfg_data'] = 'none';
						if ($post['cfg_data'] == '"hostname"') $post['cfg_data'] = 'hostname';
						break;
					case 'address_match_element':
						/** Need to check for valid ACLs or IP addresses */
						
						break;
					case 'ipv4_address | ipv6_address':
						if (!verifyIPAddress($post['cfg_data'])) return $post['cfg_data'] . ' is an invalid IP address.';
						break;
					case 'ipv4_address | *':
					case 'ipv6_address | *':
						if ($post['cfg_data'] != '*') {
							if (!verifyIPAddress($post['cfg_data'])) return $post['cfg_data'] . ' is an invalid IP address.';
						}
						break;
				}
			}
		}
		
		return $post;
	}
	

	/**
	 * Parses for address_match_element and formats
	 *
	 * @since 1.3
	 * @package fmDNS
	 *
	 * @param string $cfg_name Config name to query
	 * @param string $cfg_data Data to parse/format
	 * @return string Return formated data
	 */
	function parseDefType($cfg_name, $cfg_data) {
		global $fmdb, $__FM_CONFIG, $fm_dns_acls;
		
		$query = "SELECT def_type FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}functions WHERE def_option = '{$cfg_name}'";
		$result = $fmdb->get_results($query);
		if ($fmdb->num_rows) {
			if (isset($result[0]->def_type)) $def_type = $result[0]->def_type;
		} else $def_type = null;
		
		if (strpos($def_type, 'rrset_order_spec') !== false) {
			$order_spec_elements = explode(' ', $cfg_data);
			// class
			if ($order_spec_elements[0] != 'any') $order_spec[] = 'class ' . $order_spec_elements[0];
			// type
			if ($order_spec_elements[1] != 'any') $order_spec[] = 'type ' . $order_spec_elements[1];
			// name
			if ($order_spec_elements[2] != 0) $order_spec[] = 'name "' . displayFriendlyDomainName(getNameFromID($order_spec_elements[2], "fm_{$__FM_CONFIG['fmDNS']['prefix']}domains", 'domain_', 'domain_id', 'domain_name')) . '"';
			//order
			$order_spec[] = 'order ' . $order_spec_elements[3];
			
			return join(' ', $order_spec);
		}
		
		return (strpos($cfg_data, 'acl_') !== false || strpos($cfg_data, 'key_') !== false || \
			strpos($cfg_data, 'domain_') !== false || strpos($def_type, 'address_match_element') !== false || \
			strpos($cfg_data, 'master_') !== false || \
			strpos($def_type, 'domain_name') !== false) ? $fm_dns_acls->parseACL($cfg_data) : str_replace(',', '; ', $cfg_data);
	}
	

	/**
	 * Creates a drop down based on the def type options
	 *
	 * @since 1.3.5
	 * @package fmDNS
	 *
	 * @param string $cfg_name Config name to query
	 * @param string $cfg_data Data to parse/format
	 * @param string $select_name Name of the select object
	 * @param string $options Additional options
	 * @return string Return formated data
	 */
	function populateDefTypeDropdown($def_type, $cfg_data, $select_name = 'cfg_data', $options = null) {
		global $fmdb, $__FM_CONFIG, $fm_dns_acls;
		
		$raw_def_type_array = explode(')', str_replace('(', '', $def_type));
		$saved_data = explode(' ', $cfg_data);
		$i = 0;
		$dropdown = null;
		foreach ($raw_def_type_array as $raw_def_type) {
			$def_type_items = null;
			if (strlen(trim($raw_def_type))) {
				$raw_items = explode('|', $raw_def_type);
				if ($options == 'include-blank') {
					array_unshift($raw_items, '');
				}
				foreach ($raw_items as $item) {
					$def_type_items[] = trim($item);
				}
				$dropdown .= buildSelect($select_name . '[]', $select_name, $def_type_items, $saved_data[$i], 1);
			}
			$i++;
		}
		
		return $dropdown;
	}
}

if (!isset($fm_module_options))
	$fm_module_options = new fm_module_options();

?>
