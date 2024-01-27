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
		echo displayPagination($page, $total_pages);

		$table_info = array(
						'class' => 'display_results',
						'id' => 'table_edits',
						'name' => 'rpz'
					);

		$title_array = array(array('class' => 'header-tiny'), array('title' => __('Zone')), array('title' => __('Policy')), array('title' => __('Options')), array('title' => _('Comment'), 'class' => 'header-nosort'));
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$title_array[] = array('title' => __('Actions'), 'class' => 'header-actions header-nosort');
			if ($num_rows > 1) $table_info['class'] .= ' grab1';
		}

		echo displayTableHeader($table_info, $title_array);
		
		if ($result) {
			$grabbable = true;
			$y = 0;
			for ($x=$start; $x<$num_rows; $x++) {
				if ($y == $_SESSION['user']['record_count']) break;
				if ($results[$x]->cfg_name == 'zone' && !$results[$x]->domain_id && $grabbable) {
					echo '</tbody><tbody class="no-grab">';
					$grabbable = false;
				}
				if ($results[$x]->cfg_name == 'zone' && $results[$x]->domain_id && !$grabbable) {
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

		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;

		$post['cfg_data'] = null;

		/** Insert the parent */
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config`";
		$sql_fields = '(';
		$sql_values = null;
		
		$exclude = array('submit', 'action', 'cfg_id', 'tab-group-1', 'policy', 'cname_domain_name',
					'recursive-only', 'max-policy-ttl', 'log', 'break-dnssec', 'min-ns-dots',
					'qname-wait-recurse', 'nsip-wait-recurse');
		
		/** Get cfg_order_id */
		if (!isset($post['cfg_order_id']) || $post['cfg_order_id'] == 0) {
			basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $post['server_serial_no'], 'cfg_', 'server_serial_no', 'AND cfg_type="rpz" ORDER BY cfg_order_id DESC LIMIT 1');
			if ($fmdb->num_rows) {
				$post['cfg_order_id'] = $fmdb->last_result[0]->cfg_order_id + 1;
			} else $post['cfg_order_id'] = 1;
		}
		
		/** Insert the category parent */
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$clean_data = sanitize($data, '_');
				$sql_fields .= $key . ', ';
				$sql_values .= "'$clean_data', ";
				if ($key == 'cfg_comment') {
					$log_message[] = sprintf('Comment: %s', $clean_data);
				}
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the response policy zone because a database error occurred.'), 'sql');
		}

		$domain_name = $post['domain_id'] ? getNameFromID($post['domain_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name') : __('All Zones');

		/** Insert child configs */
		$post['cfg_isparent'] = 'no';
		$post['cfg_parent'] = $fmdb->insert_id;
		$post['cfg_name'] = '';
		unset($post['domain_id']);
		unset($post['cfg_order_id']);
		unset($post['cfg_comment']);
		$include = array('policy', 'recursive-only', 'max-policy-ttl', 'log', 'break-dnssec', 'min-ns-dots',
					'qname-wait-recurse', 'nsip-wait-recurse');
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config`";
		$sql_fields = '(';
		$sql_values = '(';
		
		$i = 1;
		foreach ($include as $handler) {
			$post['cfg_data'] = $post[$handler];
			/** Logic checking */
			if ($handler == 'policy' && $post[$handler] == 'cname') {
				$post[$handler] = 'cname ' . $post['cname_domain_name'];
			}
			$post['cfg_name'] = $handler;
			$post['cfg_data'] = $post[$handler];
			
			foreach ($post as $key => $data) {
				if (!in_array($key, $exclude)) {
					$clean_data = sanitize($data);
					if ($i) $sql_fields .= $key . ', ';
					
					$sql_values .= "'$clean_data', ";
				}
			}
			$i = 0;
			$sql_values = rtrim($sql_values, ', ') . '), (';

			if ($post['cfg_data']) {
				$log_message[] = sprintf('%s: %s', $post['cfg_name'], $post['cfg_data']);
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', (');
		
		$query = "$sql_insert $sql_fields VALUES $sql_values";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the response policy zone because a database error occurred.'), 'sql');
		}
		
		$log_message = sprintf("Added RPZ:\nZone: %s\n%s", $domain_name, join("\n", (array) $log_message));
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
			
			$post['server_serial_no'] = (!isset($post['server_serial_no'])) ? 0 : sanitize($post['server_serial_no']);
			
			/** Get listing for server */
			basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_order_id', 'cfg_', "AND cfg_type='rpz' AND cfg_name='zone' AND cfg_isparent='yes' AND server_serial_no='{$post['server_serial_no']}'");
			$count = $fmdb->num_rows;
			$results = $fmdb->last_result;
			for ($i=0; $i<$count; $i++) {
				$order_id = array_search($results[$i]->cfg_id, $new_sort_order);
				if ($order_id === false) return __('The sort order could not be updated due to an invalid request.');
				$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config` SET `cfg_order_id`=$order_id WHERE `cfg_id`={$results[$i]->cfg_id} AND `server_serial_no`={$post['server_serial_no']} AND `account_id`='{$_SESSION['user']['account_id']}' LIMIT 1";
				$result = $fmdb->query($query);
				if ($fmdb->sql_errors) {
					return formatError(__('Could not update the order because a database error occurred.'), 'sql');
				}
			}
			
			setBuildUpdateConfigFlag($post['server_serial_no'], 'yes', 'build');
		
			$servername = $post['server_serial_no'] ? getNameFromID($post['server_serial_no'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name') : _('All Servers');
			addLogEntry('Updated RPZ order for ' . $servername);
			return true;
		}

		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;

		$domain_name = $post['domain_id'] ? getNameFromID($post['domain_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name') : __('All Zones');

		/** Update the parent */
		$sql_start = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config` SET ";
		$sql_values = null;
		
		$include = array('cfg_isparent', 'cfg_name', 'cfg_type', 'cfg_comment', 'domain_id');
		
		/** Insert the category parent */
		foreach ($post as $key => $data) {
			if (in_array($key, $include)) {
				$clean_data = sanitize($data);
				$sql_values .= "$key='$clean_data', ";
				if ($key == 'cfg_comment') {
					$log_message[] = sprintf('Comment: %s', $clean_data);
				}
			}
		}
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_start $sql_values WHERE cfg_id={$post['cfg_id']} LIMIT 1";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not update the item because a database error occurred.'), 'sql');
		}

		/** Update config children */
		$include = array_diff(array_keys($post), $include, array('cfg_id', 'action', 'account_id', 'cfg_order_id', 'view_id', 'server_serial_no'));
		$sql_start = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config` SET ";
		
		foreach ($include as $handler) {
			$sql_values = null;
			$child['cfg_name'] = $handler;
			$child['cfg_data'] = $post[$handler];
			
			foreach ($child as $key => $data) {
				$clean_data = sanitize($data);
				$sql_values .= "$key='$clean_data', ";
			}
			$sql_values = rtrim($sql_values, ', ');
			
			if ($child['cfg_data']) {
				$log_message[] = sprintf('%s: %s', $child['cfg_name'], $child['cfg_data']);
			}

			$query = "$sql_start $sql_values WHERE cfg_parent={$post['cfg_id']} AND cfg_name='$handler' LIMIT 1";
			$result = $fmdb->query($query);

			if ($fmdb->sql_errors) {
				return formatError(__('Could not update the item because a database error occurred.'), 'sql');
			}
		}
		
		$log_message = sprintf("Updated RPZ:\nZone: %s\n%s", $domain_name, join("\n", (array) $log_message));
		addLogEntry($log_message);

		return true;
	}
	
	
	/**
	 * Deletes the selected rpz channel/category
	 */
	function delete($id, $server_serial_no, $type) {
		global $fmdb, $__FM_CONFIG;
		
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_', 'cfg_id', 'cfg_data');
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
			addLogEntry(sprintf(__("RPZ '%s' was deleted."), $tmp_name));
			return true;
		}
	}


	function displayRow($row, $num_rows) {
		global $__FM_CONFIG, $fmdb;
		
		if ($row->cfg_status == 'disabled') $class[] = 'disabled';
		$bars_title = __('Click and drag to reorder');
		
		$server_serial_no .= $row->server_serial_no ? '&server_serial_no=' . $row->server_serial_no : null;
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$edit_status = '<td id="row_actions">';
			$edit_status .= '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a class="status_form_link" href="#" rel="';
			$edit_status .= ($row->cfg_status == 'active') ? 'disabled' : 'active';
			$edit_status .= '">';
			$edit_status .= ($row->cfg_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
			$edit_status .= '</a>';
			$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			$edit_status .= '</td>';
			if ($row->domain_id) {
				$grab_bars = ($num_rows > 1) ? '<i class="fa fa-bars mini-icon" title="' . $bars_title . '"></i>' : null;
			} else {
				$grab_bars = null;
				$class[] = 'no-grab';
			}
		} else {
			$edit_status = $grab_bars = null;
		}
		
		$domain_name = $row->domain_id ? getNameFromID($row->domain_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name') : sprintf('<span>%s</span>', __('All Zones'));
		$comments = nl2br($row->cfg_comment);

		$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', array('cfg_name'), 'cfg_', 'AND cfg_type="rpz" AND cfg_parent="' . $row->cfg_id . '" AND cfg_isparent="no" AND server_serial_no="' . $row->server_serial_no. '"', null, false, $sort_direction);
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
		global $fmdb, $__FM_CONFIG, $fm_dns_zones;
		
		$cfg_id = $cfg_order_id = $domain_id = 0;
		$cfg_name = $cfg_comment = null;
		$server_serial_no = (isset($_REQUEST['request_uri']['server_serial_no']) && (intval($_REQUEST['request_uri']['server_serial_no']) > 0 || $_REQUEST['request_uri']['server_serial_no'][0] == 'g')) ? sanitize($_REQUEST['request_uri']['server_serial_no']) : 0;
		$cfg_data = $cname_domain_name = $zone_sql = $excluded_domain_ids = null;

		$yes_no = array(null, 'yes', 'no');
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}

		/** Build the available zones list */
		if (isset($_POST['request_uri'])) {
			foreach ((array) $_POST['request_uri'] as $key => $val) {
				$zone_sql .= sprintf(" AND %s='%s'", sanitize($key), sanitize($val));
			}
		}
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', array('cfg_order_id', 'cfg_data'), 'cfg_', "AND cfg_type='rpz' $zone_sql AND cfg_name='zone' AND cfg_isparent='yes'");
		if ($fmdb->num_rows) {
			foreach ($fmdb->last_result as $row) {
				if ($action == 'edit' && $row->domain_id == $domain_id) continue;
				$excluded_domain_ids[] = $row->domain_id;
			}
		}
		$available_zones = $fm_dns_zones->buildZoneJSON('all', $excluded_domain_ids);

		/** Get child elements */
		$child_config = getConfigChildren($cfg_id, array_fill_keys(array('policy', 'recursive-only', 'max-policy-ttl', 'log', 'break-dnssec', 'qname-wait-recurse', 'nsip-wait-recurse', 'min-ns-dots'), null));
		if (array_key_exists('policy', $child_config)) {
			@list($child_config['policy'], $cname_domain_name) = explode(' ', $child_config['policy']);
		}
		$policy = buildSelect('policy', 'policy', array('', 'given', 'disabled', 'passthru', 'drop', 'nxdomain', 'nodata', 'tcp-only', 'cname'), $child_config['policy']);
		$recursive_only = buildSelect('recursive-only', 'recursive-only', $yes_no, $child_config['recursive-only']);
		$max_policy_ttl = $child_config['max-policy-ttl'];
		$log = buildSelect('log', 'log', $yes_no, $child_config['log']);
		$break_dnssec = buildSelect('break-dnssec', 'break-dnssec', $yes_no, $child_config['break-dnssec']);
		$qname_wait_recurse = buildSelect('qname-wait-recurse', 'qname-wait-recurse', $yes_no, $child_config['qname-wait-recurse']);
		$nsip_wait_recurse = buildSelect('nsip-wait-recurse', 'nsip-wait-recurse', $yes_no, $child_config['nsip-wait-recurse']);
		$min_ns_dots = $child_config['min-ns-dots'];
		
		/** Show/hide divs */
		if (!$domain_id) {
			$global_show = 'table-row';
			$domain_show = 'none';
			$domain_name_show = 'none';
		} else {
			$global_show = 'none';
			$domain_show = 'table-row';
			if ($child_config['policy'] == 'cname') {
				$domain_name_show = 'block';
			} else {
				$domain_name_show = 'none';
			}
		}

		$popup_title = $action == 'add' ? __('Add RPZ') : __('Edit RPZ');
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		$return_form = sprintf('<form name="manage" id="manage" method="post" action="">
		%s
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
								<td width="67&#37;"><input type="hidden" id="domain_id" name="domain_id" class="domain_name" value="%d" /><br /><span class="note">%s</span></td>
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
							<tr class="domain_option" style="display: %s">
								<th width="33&#37;" scope="row"><label for="policy">%s</label></th>
								<td width="67&#37;">
									%s
									<div id="cname_option" style="display: %s"><input name="cname_domain_name" id="cname_domain_name" type="text" value="%s" size="40" placeholder="domainname.com" /></div></td>
								</td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="recursive-only">%s</label></th>
								<td width="67&#37;">%s</td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="max-policy-ttl">%s</label></th>
								<td width="67&#37;"><input name="max-policy-ttl" id="max-policy-ttl" type="text" value="%s" style="width: 5em;" onkeydown="return validateNumber(event)" /> %s</td>
							</tr>
							<tr class="domain_option" style="display: %s">
								<th width="33&#37;" scope="row"><label for="log">%s</label></th>
								<td width="67&#37;">%s<br /><span class="note">%s</span></td>
							</tr>
							<tr class="global_option" style="display: %s">
								<th width="33&#37;" scope="row"><label for="break-dnssec">%s</label></th>
								<td width="67&#37;">%s</td>
							</tr>
							<tr class="global_option" style="display: %s">
								<th width="33&#37;" scope="row"><label for="min-ns-dots">%s</label></th>
								<td width="67&#37;"><input name="min-ns-dots" id="min-ns-dots" type="text" value="%s" style="width: 5em;" onkeydown="return validateNumber(event)" /></td>
							</tr>
							<tr class="global_option" style="display: %s">
								<th width="33&#37;" scope="row"><label for="qname-wait-recurse">%s</label></th>
								<td width="67&#37;">%s</td>
							</tr>
							<tr class="global_option" style="display: %s">
								<th width="33&#37;" scope="row"><label for="nsip-wait-recurse">%s</label></th>
								<td width="67&#37;">%s<br /><span class="note">%s</span></td>
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
					width: "200px",
					minimumResultsForSearch: 10,
					allowClear: true
				});
			});
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
		</script>',
				$popup_header, $action, $cfg_id, $cfg_order_id, $cfg_type_id, $server_serial_no,
				__('Basic'),
				__('Zone'), $domain_id, sprintf(__('This feature requires BIND %s or later.'), '9.10.0'),
				_('Comment'), $cfg_comment,
				__('Advanced'),
				$domain_show, __('Policy'), $policy, $domain_name_show, $cname_domain_name,
				__('Recursive only'), $recursive_only,
				__('Maximum policy TTL'), $max_policy_ttl, __('seconds'),
				$domain_show, __('Log'), $log, sprintf(__('This option requires BIND %s or later.'), '9.11.0'),
				$global_show, __('Break DNSSEC'), $break_dnssec,
				$global_show, __('Minimum NS Dots'), $min_ns_dots,
				$global_show, __('QNAME wait recurse'), $qname_wait_recurse,
				$global_show, __('NSIP wait recurse'), $nsip_wait_recurse, sprintf(__('This option requires BIND %s or later.'), '9.11.0'),
				$popup_footer,
				$available_zones
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
	 * @return array|boolean
	 */
	function validatePost($post) {
		global $fmdb, $__FM_CONFIG;
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		$post['cfg_isparent'] = 'yes';
		$post['cfg_name'] = 'zone';
		$post['cfg_comment'] = trim($post['cfg_comment']);
		$post['cfg_type'] = 'rpz';
		$post['cfg_id'] = sanitize($post['cfg_id']);

		unset($post['tab-group-1']);
		
		if ($post['policy'] == 'cname') {
			if (empty($post['cname_domain_name'])) return __('No CNAME domain defined.');
		}
		
		return $post;
	}

}

if (!isset($fm_module_rpz))
	$fm_module_rpz = new fm_module_rpz();

?>
