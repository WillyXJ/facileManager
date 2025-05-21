<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2013-2020 The facileManager Team                          |
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

class fm_module_rpz {
	
	/**
	 * Displays the rpz list
	 */
	function rows($result, $page, $total_pages) {
		global $fmdb, $__FM_CONFIG;
		
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;

		$start = $_SESSION['user']['record_count'] * ($page - 1);

		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$bulk_actions_list = array(_('Enable'), _('Disable'), _('Delete'));
		}
		echo displayPagination($page, $total_pages, buildBulkActionMenu($bulk_actions_list));

		$table_info = array(
						'class' => 'display_results',
						'id' => 'table_edits',
						'name' => 'rpz'
					);

		if (is_array($bulk_actions_list)) {
			$title_array[] = array(
								'title' => '<input type="checkbox" class="tickall" onClick="toggle(this, \'bulk_list[]\')" />',
								'class' => 'header-tiny header-nosort'
							);
		} else {
			$title_array[] = array(
				'class' => 'header-tiny header-nosort'
			);
		}
		$title_array = array_merge((array) $title_array, array(array('class' => 'header-tiny'), array('title' => __('Zone')), array('title' => __('Policy')), array('title' => __('Options')), array('title' => _('Comment'), 'class' => 'header-nosort')));
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$title_array[] = array('title' => __('Actions'), 'class' => 'header-actions header-nosort');
			if ($num_rows > 1) $table_info['class'] .= ' grab1';
		}

		echo '<div class="overflow-container">';
		echo displayTableHeader($table_info, $title_array);
		
		if ($result) {
			$grabbable = true;
			$y = 0;
			for ($x=$start; $x<$num_rows; $x++) {
				if ($y == $_SESSION['user']['record_count']) break;
				if ($results[$x]->cfg_name == '!config_name!' && !$results[$x]->domain_id && $grabbable) {
					echo '</tbody><tbody class="no-grab">';
					$grabbable = false;
				}
				if ($results[$x]->cfg_name == '!config_name!' && $results[$x]->domain_id && !$grabbable) {
					echo '</tbody><tbody>';
					$grabbable = true;
				}
				$this->displayRow($results[$x], $num_rows);
				$y++;
			}
		}
			
		echo "</tbody>\n</table>\n";
		if (!$result) {
			printf('<p id="table_edits" class="noresult" name="rpz">%s</p>', __('There are no response policy zones defined.'));
		}
	}

	/**
	 * Adds the new RPZ
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG;

		$query = "SELECT * FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}functions WHERE def_function = 'options' AND def_option_type = 'rpz'";
		$fmdb->query($query);
		foreach ($fmdb->last_result as $k => $def) {
			$include_sub_configs[] = $def->def_option;
		}

		/** Validate entries */
		$post = $this->validatePost($post, $include_sub_configs);
		if (!is_array($post)) return $post;

		$post['cfg_data'] = null;

		/** Insert the parent */
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config`";
		$sql_fields = '(';
		$sql_values = '';
		
		/** Get cfg_order_id */
		if ($post['domain_id'] && (!isset($post['cfg_order_id']) || $post['cfg_order_id'] == 0)) {
			basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $post['server_serial_no'], 'cfg_', 'server_serial_no', 'AND cfg_type="rpz" AND view_id="' . $post['view_id'] . '" ORDER BY cfg_order_id DESC LIMIT 1');
			if ($fmdb->num_rows) {
				$post['cfg_order_id'] = $fmdb->last_result[0]->cfg_order_id + 1;
			} else $post['cfg_order_id'] = 1;
		}
		
		$type = (strpos($post['domain_id'], 'g_') !== false) ? 'group' : 'domain';
		$domain_name = $post['domain_id'] ? getNameFromID(str_replace('g_', '', $post['domain_id']), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . str_replace('domain_domain', 'domain', "domain_{$type}s"), "{$type}_", "{$type}_id", "{$type}_name") : __('All Zones');

		$include = array('cfg_isparent', 'cfg_order_id', 'domain_id', 'view_id', 'cfg_type', 'server_serial_no', 'cfg_name', 'cfg_data', 'cfg_comment');
		$log_message = __('Added an RPZ with the following details') . ":\n";
		$log_message .= formatLogKeyData('', 'Zone', $domain_name);

		/** Insert the category parent */
		foreach ($post as $key => $data) {
			if (in_array($key, $include)) {
				// $clean_data = ($key == 'cfg_data') ? sanitize($data, '_') : $data;
				$clean_data = $data;
				$sql_fields .= $key . ', ';
				$sql_values .= "'$clean_data', ";
				if ($key == 'view_id') {
					$log_message .= formatLogKeyData('_id', $key, ($clean_data) ? getNameFromID($clean_data, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name') : 'All Views');
				}
				if ($key == 'server_serial_no') {
					$log_message .= formatLogKeyData('_serial_no', $key, getServerName($clean_data));
				}
				if ($key == 'cfg_comment') {
					$log_message .= formatLogKeyData('cfg_', $key, $clean_data);
				}
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the response policy zone because a database error occurred.'), 'sql');
		}

		/** Insert child configs */
		$post['cfg_isparent'] = 'no';
		$post['cfg_parent'] = $fmdb->insert_id;
		$post['cfg_name'] = '';
		unset($post['domain_id']);
		unset($post['cfg_order_id']);
		unset($post['cfg_comment']);
		$include[] = 'cfg_parent';
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config`";
		$sql_fields = '(';
		$sql_values = '(';
		
		$i = 1;
		foreach ($include_sub_configs as $handler) {
			$post['cfg_name'] = $handler;
			$post['cfg_data'] = $post[$handler];
			
			foreach ($post as $key => $data) {
				if (!in_array($key, $include)) continue;
				if ($i) $sql_fields .= $key . ', ';
				$sql_values .= "'$data', ";
			}
			$i = 0;
			$sql_values = rtrim($sql_values, ', ') . '), (';

			if ($post['cfg_data']) {
				$log_message .= formatLogKeyData('cfg_', $post['cfg_name'], $post['cfg_data']);
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', (');
		
		$query = "$sql_insert $sql_fields VALUES $sql_values";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the response policy zone because a database error occurred.'), 'sql');
		}
		
		setBuildUpdateConfigFlag($post['server_serial_no'], 'yes', 'build');

		addLogEntry($log_message);
		return true;
	}
	
	
	/**
	 * Updates the selected rpz type
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Update sort order */
		if ($post['action'] == 'update_sort') {
			/** Make new order in array */
			$new_sort_order = explode(';', rtrim($post['sort_order'], ';'));
			
			$post['server_serial_no'] = (!isset($post['server_serial_no'])) ? 0 : $post['server_serial_no'];
			$view_id = (!isset($post['uri_params']['view_id'])) ? 0 : $post['uri_params']['view_id'];
			
			/** Get listing for server */
			basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_order_id', 'cfg_', "AND cfg_type='rpz' AND cfg_name='!config_name!' AND cfg_isparent='yes' AND view_id='{$view_id}' AND server_serial_no='{$post['server_serial_no']}'");
			$count = $fmdb->num_rows;
			$results = $fmdb->last_result;
			for ($i=0; $i<$count; $i++) {
				$order_id = array_search($results[$i]->cfg_id, $new_sort_order);
				if ($order_id === false) return __('The sort order could not be updated due to an invalid request.');
				$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config` SET `cfg_order_id`=$order_id WHERE `cfg_id`={$results[$i]->cfg_id} AND `server_serial_no`='{$post['server_serial_no']}' AND `account_id`='{$_SESSION['user']['account_id']}' LIMIT 1";
				$fmdb->query($query);
				if ($fmdb->sql_errors) {
					return formatError(__('Could not update the order because a database error occurred.'), 'sql');
				}
			}
			
			setBuildUpdateConfigFlag($post['server_serial_no'], 'yes', 'build');
		
			$log_message = __('Updated RPZ order') . ":\n";
			$log_message .= formatLogKeyData('', 'View', ($view_id) ? getNameFromID($view_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name') : 'All Views');
			$log_message .= formatLogKeyData('_serial_no', 'server_serial_no', getServerName($post['server_serial_no']));

			addLogEntry($log_message);

			return true;
		}

		$query = "SELECT * FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}functions WHERE def_function = 'options' AND def_option_type = 'rpz'";
		$fmdb->query($query);
		foreach ($fmdb->last_result as $k => $def) {
			$include_sub_configs[] = $def->def_option;
		}

		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;

		unset($post['cfg_name']);

		$type = (strpos($post['domain_id'], 'g_') !== false) ? 'group' : 'domain';
		$domain_name = $post['domain_id'] ? getNameFromID(str_replace('g_', '', $post['domain_id']), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . str_replace('domain_domain', 'domain', "domain_{$type}s"), "{$type}_", "{$type}_id", "{$type}_name") : __('All Zones');

		$log_message = __('Updated an RPZ with the following details') . ":\n";
		$log_message .= formatLogKeyData('', 'Zone', $domain_name);

		/** Update the parent */
		$sql_start = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config` SET ";
		$sql_values = '';
		
		$include = array('cfg_isparent', 'cfg_name', 'cfg_type', 'cfg_comment', 'domain_id', 'view_id', 'server_serial_no');
		
		/** Insert the category parent */
		foreach ($post as $key => $data) {
			if (in_array($key, $include)) {
				$sql_values .= "$key='$data', ";
				if ($key == 'view_id') {
					$log_message .= formatLogKeyData('_id', $key, ($data) ? getNameFromID($data, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name') : 'All Views');
				}
				if ($key == 'server_serial_no') {
					$log_message .= formatLogKeyData('_serial_no', $key, getServerName($data));
				}
				if ($key == 'cfg_comment') {
					$log_message .= formatLogKeyData('cfg_', $key, $data);
				}
			}
		}
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_start $sql_values WHERE cfg_id={$post['cfg_id']} LIMIT 1";
		$fmdb->query($query);
		$rows_affected = $fmdb->rows_affected;
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not update the item because a database error occurred.'), 'sql');
		}

		/** Update config children */
		$sql_start = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config` SET ";
		
		foreach ($include_sub_configs as $handler) {
			$handler = sanitize($handler);
			$sql_values = '';
			$child['cfg_data'] = $post[$handler];
			
			foreach ($child as $key => $data) {
				$sql_values .= "$key='$data', ";
			}
			$sql_values = rtrim($sql_values, ', ');
			
			if ($child['cfg_data']) {
				$log_message .= formatLogKeyData('cfg_', $handler, $child['cfg_data']);
			}

			$query = "$sql_start $sql_values WHERE cfg_parent={$post['cfg_id']} AND cfg_name='$handler' LIMIT 1";
			$fmdb->query($query);
			$rows_affected += $fmdb->rows_affected;

			if ($fmdb->sql_errors) {
				return formatError(__('Could not update the item because a database error occurred.'), 'sql');
			}
		}
		if (!$rows_affected) return true;
		
		setBuildUpdateConfigFlag($post['server_serial_no'], 'yes', 'build');

		addLogEntry($log_message);

		return true;
	}
	
	
	/**
	 * Deletes the selected rpz channel/category
	 */
	function delete($id, $server_serial_no, $type) {
		global $fmdb, $__FM_CONFIG;
		
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_', 'cfg_id', 'domain_id');
		$tmp_name = ($tmp_name) ? getNameFromID($tmp_name, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name') : __('All Zones');

		/** Delete associated children */
		if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $id, 'cfg_', 'deleted', 'cfg_parent') === false) {
			return formatError(__('This RPZ could not be deleted because a database error occurred.'), 'sql');
		}
		
		/** Delete item */
		if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $id, 'cfg_', 'deleted', 'cfg_id') === false) {
			return formatError(__('This RPZ could not be deleted because a database error occurred.'), 'sql');
		} else {
			setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
			$log_message = __('Deleted an RPZ') . ":\n";
			$log_message .= formatLogKeyData('', 'Name', $tmp_name);
			$view_id = getNameFromID($id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_', 'cfg_id', 'view_id');
			$log_message .= formatLogKeyData('', 'View', ($view_id) ? getNameFromID($view_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name') : 'All Views');
			$log_message .= formatLogKeyData('_serial_no', 'server_serial_no', getServerName($server_serial_no));
			addLogEntry($log_message);
			return true;
		}
	}


	function displayRow($row, $num_rows) {
		global $__FM_CONFIG, $fmdb;
		
		if ($row->cfg_status == 'disabled') $class[] = 'disabled';
		$bars_title = __('Click and drag to reorder');
		$checkbox = null;
		
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$edit_status = '<td class="column-actions">';
			$edit_status .= '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a class="status_form_link" href="#" rel="';
			$edit_status .= ($row->cfg_status == 'active') ? 'disabled' : 'active';
			$edit_status .= '">';
			$edit_status .= ($row->cfg_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
			$edit_status .= '</a>';
			$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			$edit_status .= '</td>';
			$checkbox = '<input type="checkbox" name="bulk_list[]" value="' . $row->cfg_id .'" />';
			if ($row->domain_id) {
				$grab_bars = ($num_rows > 1) ? '<i class="fa fa-bars mini-icon" title="' . $bars_title . '"></i>' : null;
			} else {
				$grab_bars = null;
				$class[] = 'no-grab';
			}
		} else {
			$edit_status = $grab_bars = null;
		}
		
		$type = (strpos($row->domain_id, 'g_') !== false) ? 'group' : 'domain';
		$domain_name = $row->domain_id ? getNameFromID(str_replace('g_', '', $row->domain_id), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . str_replace('domain_domain', 'domain', "domain_{$type}s"), "{$type}_", "{$type}_id", "{$type}_name") : sprintf('<span>%s</span>', __('All Zones'));
		if ($type == 'group') {
			$domain_name = sprintf('<b>%s</b>', $domain_name);
		}
		$comments = nl2br($row->cfg_comment);

		$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', array('cfg_name'), 'cfg_', 'AND cfg_type="rpz" AND cfg_parent="' . $row->cfg_id . '" AND cfg_isparent="no" AND server_serial_no="' . $row->server_serial_no. '"', null, false);
		foreach ($fmdb->last_result as $record) {
			if ($record->cfg_data) {
				if ($record->cfg_name == 'policy') {
					$policy = $record->cfg_data;
				} else {
					(string) $policy_options .= sprintf('<b>%s</b> %s<br />', $record->cfg_name, $record->cfg_data);
				}
			}
		}

		if ($class) $class = 'class="' . join(' ', $class) . '"';

		echo <<<HTML
		<tr id="$row->cfg_id" name="rpz" $class>
			<td>$checkbox</td>
			<td>$grab_bars</td>
			<td>$domain_name</td>
			<td>$policy</td>
			<td>$policy_options</td>
			<td>$comments</td>
			$edit_status
		</tr>
HTML;
	}

	/**
	 * Displays the form to add/edit rpz types
	 */
	function printForm($data = '', $action = 'add', $cfg_type = 'rpz', $cfg_type_id = null) {
		global $fmdb, $__FM_CONFIG, $fm_dns_zones, $fm_module_options;
		
		$cfg_id = $cfg_order_id = $domain_id = 0;
		$cfg_name = $cfg_comment = null;
		$server_serial_no = (isset($_REQUEST['request_uri']['server_serial_no']) && (intval($_REQUEST['request_uri']['server_serial_no']) > 0 || $_REQUEST['request_uri']['server_serial_no'][0] == 'g')) ? sanitize($_REQUEST['request_uri']['server_serial_no']) : 0;
		$cfg_data = $cname_domain_name = $zone_sql = $excluded_domain_ids = null;

		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}

		/** Build the available zones list */
		if (isset($_POST['request_uri'])) {
			foreach ((array) $_POST['request_uri'] as $key => $val) {
				if (in_array($key, array('type', 'p'))) continue;
				$zone_sql .= sprintf(" AND %s='%s'", $key, $val);
			}
		}
		if (!isset($_POST['request_uri']['view_id'])) {
			$zone_sql .= " AND view_id='0'";
		}
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', array('cfg_order_id', 'cfg_data'), 'cfg_', "AND cfg_type='rpz' $zone_sql AND cfg_name='!config_name!' AND cfg_isparent='yes'");
		if ($fmdb->num_rows) {
			foreach ($fmdb->last_result as $row) {
				if ($action == 'edit' && $row->domain_id == $domain_id) continue;
				$excluded_domain_ids[] = $row->domain_id;
			}
		}
		$available_zones = $fm_dns_zones->buildZoneJSON('all', $excluded_domain_ids);

		$available_zones_array = json_decode($available_zones);
		$first_available_domain_id = $available_zones_array[array_key_first($available_zones_array)]->children[0]->id;
		$domain_id = ($domain_id) ? $domain_id : $first_available_domain_id;
		$auto_select_jq = sprintf('$(".domain_name").select2("val", "%s");', $domain_id);

		/** Get child elements */
		$query = "SELECT * FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}functions WHERE def_function = 'options' AND def_option_type='{$cfg_type}' ORDER BY def_zone_support DESC,def_option ASC";
		$fmdb->query($query);
		foreach ($fmdb->last_result as $k => $def) {
			$auto_fill_children[] = $def->def_option;
			$config_parameters[$def->def_zone_support][] = $def->def_option;
			
			if ($def->def_dropdown == 'yes') {
				if (!class_exists('fm_module_options')) {
					include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_options.php');
				}
				$form_addl_html[$def->def_option] = 'select:' . $def->def_type;
			} else {
				switch (trim(str_replace(array('(', ')'), '', $def->def_type))) {
					case 'integer':
						$form_addl_html[$def->def_option] = 'maxlength="5" style="width: 5em;" onkeydown="return validateNumber(event)"';
						break;
					default:
						$form_addl_html[$def->def_option] = null;
				}
			}
		}

		$child_config = getConfigChildren($cfg_id, $cfg_type, array_fill_keys($auto_fill_children, null));
		if (array_key_exists('policy', $child_config)) {
			@list($child_config['policy'], $cname_domain_name) = explode(' ', $child_config['policy']);
		}
		foreach ($child_config as $k => $v) {
			$child_config[$k] = str_replace(array('"', "'"), '', (string) $v);
			if (isset($form_addl_html[$k]) && strpos($form_addl_html[$k], 'select:') !== false) {
				$form_field = $fm_module_options->populateDefTypeDropdown(str_replace('select:', '', $form_addl_html[$k]), $child_config[$k], $k, 'include-blank');
			} else {
				$form_field = sprintf('<input name="%1$s" id="%1$s" type="text" value="%2$s" %3$s/>',
					$k, $child_config[$k], $form_addl_html[$k]);
			}
			foreach ($config_parameters as $param_type => $key_array) {
				$param = array_search($k, $key_array);
				if ($param !== false) break;
			}
			$child_config_form[] = sprintf('
				<tr class="%s_option">
					<th width="33&#37;" scope="row"><label for="%s">%s</label></th>
					<td width="67&#37;">%s%s</td>
				</tr>', $param_type, $k, str_replace('-', ' ', $k), $form_field,
					($k == 'policy') ? sprintf('<div id="cname_option" style="display: %s"><input name="cname_domain_name" id="cname_domain_name" type="text" value="%s" size="40" placeholder="domainname.com" class="required" /></div>', ($v == 'cname') ? 'block' : 'none', $cname_domain_name) : null
			);
		}

		$popup_title = $action == 'add' ? __('Add RPZ') : __('Edit RPZ');
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		$return_form = sprintf('
		%s
		<form name="manage" id="manage">
			<input type="hidden" name="page" value="%s" />
			<input type="hidden" name="action" value="%s" />
			<input type="hidden" name="cfg_id" value="%d" />
			<input type="hidden" name="cfg_order_id" value="%d" />
			<input type="hidden" name="view_id" value="%d" />
			<input type="hidden" name="server_serial_no" value="%s" />
			<div id="tabs">
				<div id="tab">
					<input type="radio" name="tab-group-1" id="tab-1" checked />
					<label for="tab-1">%s</label>
					<div id="tab-content">
						<table class="form-table">
							<tr>
								<th width="33&#37;" scope="row"><label for="cfg_name">%s</label></th>
								<td width="67&#37;"><input type="hidden" id="domain_id" name="domain_id" class="domain_name" value="%d" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="cfg_comment">%s</label></th>
								<td width="67&#37;"><textarea id="cfg_comment" name="cfg_comment" rows="4" cols="30">%s</textarea></td>
							</tr>
						</table>
					</div>
				</div>
				<div id="tab">
					<input type="radio" name="tab-group-1" id="tab-2" />
					<label for="tab-2">%s</label>
					<div id="tab-content">
						<table class="form-table">
							%s
						</table>
					</div>
				</div>
			</div>
		%s
		</form>
		<script>
			$(document).ready(function() {
				$("#manage select").select2({
					width: "200px",
					minimumResultsForSearch: 10,
					allowClear: true
				});
				$(".domain_name").select2({
					createSearchChoice:function(term, data) { 
						if ($(data).filter(function() { 
							return this.text.localeCompare(term)===0; 
						}).length===0) 
						{return {id:term, text:term};} 
					},
					multiple: false,
					width: "%s",
					tokenSeparators: [",", " ", ";"],
					data: %s
				});
				%s
				$("#domain_id").trigger("change");
			});
		</script>',
				$popup_header, $cfg_type, $action, $cfg_id, $cfg_order_id, $cfg_type_id, $server_serial_no,
				__('Basic'),
				__('Zone'), $domain_id,
				_('Comment'), $cfg_comment,
				__('Advanced'),
				implode("\n", $child_config_form),
				$popup_footer, '85%',
				$available_zones,
				$auto_select_jq
			);

		return $return_form;
	}
	
	
	/**
	 * Validates the submitted form
	 *
	 * @since 4.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param array $post Posted array
	 * @param array $include_sub_configs Array of sub configs to validate
	 * @return array|string|boolean
	 */
	function validatePost($post, $include_sub_configs = null) {
		global $fmdb, $__FM_CONFIG, $fm_module_options;
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		$post['cfg_isparent'] = 'yes';
		$post['cfg_name'] = '!config_name!';
		if (!empty($post['cfg_data'])) $post['cfg_data'] = sanitize($post['cfg_data'], '-');
		$post['cfg_type'] = 'rpz';

		/** Ensure policy is defined */
		if (!isset($post['policy']) || !$post['policy'][0]) {
			return __('The policy needs to be selected.');
		}

		unset($post['tab-group-1']);
		
		$query = "SELECT * FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config WHERE account_id='{$_SESSION['user']['account_id']}' AND cfg_status!='deleted' AND cfg_type='{$post['cfg_type']}' AND cfg_name='!config_name!' AND view_id='{$post['view_id']}' AND domain_id='{$post['domain_id']}' AND server_serial_no='{$post['server_serial_no']}' AND cfg_id!='{$post['cfg_id']}'";
		$fmdb->get_results($query);
		if ($fmdb->num_rows) return __('This item already exists.');

		if ($include_sub_configs === null) {
			$query = "SELECT * FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}functions WHERE def_function = 'options' AND def_option_type = 'rpz'";
			$fmdb->query($query);
			foreach ($fmdb->last_result as $k => $def) {
				$include_sub_configs[] = $def->def_option;
			}
		}

		include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_options.php');

		foreach ($post as $key => $val) {
			if (!$val) continue;
			if (in_array($key, $include_sub_configs)) {
				$post2['cfg_name'] = $key;
				if (is_array($val)) $val = $val[0];
				$post2['cfg_data'] = $val;
				$def_check = $fm_module_options->validateDefType($post2);
				if (!is_array($def_check)) {
					return $def_check;
				} else {
					$post[$key] = $def_check['cfg_data'];
				}
			}
		}

		if ($post['policy'] == 'cname') {
			if (empty($post['cname_domain_name'])) return __('No CNAME domain defined.');

			$post['policy'] = sprintf('cname %s', $post['cname_domain_name']);
			unset($post['cname_domain_name']);
		}

		return $post;
	}

}

if (!isset($fm_module_rpz))
	$fm_module_rpz = new fm_module_rpz();
