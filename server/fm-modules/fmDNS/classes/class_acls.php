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

class fm_dns_acls {
	
	/**
	 * Displays the acl list
	 */
	function rows($result, $page, $total_pages) {
		global $fmdb;
		
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;

		$start = $_SESSION['user']['record_count'] * ($page - 1);

		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$bulk_actions_list = array(_('Enable'), _('Disable'), _('Delete'));
		}
		echo displayPagination($page, $total_pages, buildBulkActionMenu($bulk_actions_list));

		$table_info = array(
						'class' => 'display_results sortable',
						'id' => 'table_edits',
						'name' => 'acls'
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
		$title_array = array_merge((array) $title_array, array(array('title' => __('Name'), 'rel' => 'acl_name'), 
			array('title' => _('Comment'), 'class' => 'header-nosort')));
		if (currentUserCan('manage_servers', $_SESSION['module'])) $title_array[] = array('title' => __('Actions'), 'class' => 'header-actions header-nosort');

		echo '<div class="overflow-container">';
		echo displayTableHeader($table_info, $title_array, 'acls');
		
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
			printf('<p id="table_edits" class="noresult" name="acls">%s</p>', __('There are no ACLs.'));
		}
	}

	/**
	 * Adds the new acl
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG, $global_form_field_excludes;
		
		/** Validate post */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}acls`";
		$sql_fields = '(';
		$sql_values = '';
		
		$exclude = array_merge($global_form_field_excludes, array('server_id', 'acl_bulk'));
		$logging_include = array_diff(array_keys($post), $exclude, array('acl_id', 'acl_parent_id', 'account_id', 'tab-group-1'));

		if (isset($post['acl_parent_id']) && $post['acl_parent_id']) {
			$log_message = sprintf(__("Added an address list to ACL '%s' with the following"), getNameFromID($post['acl_parent_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_', 'acl_id', 'acl_name')) . ":\n";
		} else {
			$log_message = __("Added ACL with the following") . ":\n";
		}

		foreach ($post as $key => $data) {
			if ($key == 'acl_name' && empty($data)) return __('No ACL name defined.');
			if (!in_array($key, $exclude)) {
				$sql_fields .= $key . ', ';
				$sql_values .= "'$data', ";
				if (!empty($data) && in_array($key, $logging_include)) {
					if ($key == 'server_serial_no') {
						$log_message .= formatLogKeyData('', 'server', getServerName($data));
					} elseif (!empty($data)) {
						$log_message .= formatLogKeyData('acl_', $key, $this->parseACL($data));
					}
				}
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$fmdb->query($query);
		$new_acl_id = $fmdb->insert_id;
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the ACL because a database error occurred.'), 'sql');
		}

		addLogEntry($log_message);

		if (isset($post['acl_bulk']) && !empty($post['acl_bulk'])) {
			unset($post['acl_name']);
			$post['acl_parent_id'] = $new_acl_id;
			return $this->addBulkACL($post);
		}

		setBuildUpdateConfigFlag($post['server_serial_no'], 'yes', 'build');

		return true;
	}

	/**
	 * Updates the selected acl
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG, $global_form_field_excludes;
		
		/** Validate post */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		$exclude = array_merge($global_form_field_excludes, array('server_id'));
		$logging_include = array_diff(array_keys($post), $exclude, array('acl_id', 'acl_parent_id', 'account_id', 'tab-group-1'));

		$old_name = getNameFromID($post['acl_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_', 'acl_id', 'acl_name');
		if (!$old_name) {
			$old_address = $this->parseACL(getNameFromID($post['acl_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_', 'acl_id', 'acl_addresses'));
			$tmp_parent_id = getNameFromID($post['acl_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_', 'acl_id', 'acl_parent_id');
			$tmp_name = getNameFromID($tmp_parent_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_', 'acl_id', 'acl_name');
			$log_message = sprintf(__("Updated %s on ACL '%s' to the following"), $old_address, $tmp_name) . ":\n";
		} else {
			$log_message = sprintf(__('Updated ACL (%s) to the following details'), $old_name) . ":\n";
		}

		$sql_edit = '';
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . $data . "', ";
				if (strlen($data) && in_array($key, $logging_include)) {
					if ($key == 'server_serial_no') {
						$log_message .= formatLogKeyData('', 'server', getServerName($data));
					} elseif (strlen($data)) {
						$log_message .= formatLogKeyData('acl_', $key, $this->parseACL($data));
					}
				}
			}
		}
		$sql = rtrim($sql_edit, ', ');
		
		/** Update the acl */
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}acls` SET $sql WHERE `acl_id`={$post['acl_id']}";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not update the ACL because a database error occurred.'), 'sql');
		}

		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		addLogEntry($log_message);
		setBuildUpdateConfigFlag($post['server_serial_no'], 'yes', 'build');

		return true;
	}
	
	
	/**
	 * Deletes the selected ACL
	 */
	function delete($id, $server_serial_no = 0) {
		global $fmdb, $__FM_CONFIG, $fm_dns_acls;
		
		/** Are there any corresponding configs? */
		if (getConfigAssoc($id, 'acl')) {
			return formatError(__('This item is still being referenced and could not be deleted.'), 'sql');
		}

		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_', 'acl_id', 'acl_name');
		$log_message = sprintf(__("ACL '%s' was deleted"), $tmp_name);
		if (!$tmp_name) {
			$tmp_parent_id = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_', 'acl_id', 'acl_parent_id');
			$tmp_address = $this->parseACL(getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_', 'acl_id', 'acl_addresses'));
			$tmp_name = getNameFromID($tmp_parent_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_', 'acl_id', 'acl_name');
			$log_message = __('Deleted an address list from an ACL') . ":\n";
		} else {
			$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}acls` SET `acl_status`='deleted' WHERE account_id='{$_SESSION['user']['account_id']}' AND `acl_parent_id`='" . sanitize($id) . "'";
			$fmdb->query($query);
			if ($fmdb->sql_errors) {
				return formatError(__('The associated ACL elements could not be deleted because a database error occurred.'), 'sql');
			}
			$log_message = __('Deleted an ACL') . ":\n";
		}
		if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', $id, 'acl_', 'deleted', 'acl_id') === false) {
			return formatError(__('This item could not be deleted because a database error occurred.'), 'sql');
		} else {
			setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
			$log_message .= formatLogKeyData('', 'Name', $tmp_name);
			$log_message .= (isset($tmp_address)) ? formatLogKeyData('', 'Address', $fm_dns_acls->parseACL($tmp_address)) : null;
			$log_message .= formatLogKeyData('_serial_no', 'server_serial_no', getServerName($server_serial_no));
			addLogEntry($log_message);
			return true;
		}
	}


	function displayRow($row) {
		global $__FM_CONFIG;

		$classes = array();
		
		if ($row->acl_status == 'disabled') $classes[] = 'disabled';
		
		$checkbox = null;
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$edit_status = '<td class="column-actions">';
			$edit_status .= '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			if (!getConfigAssoc($row->acl_id, 'acl')) {
				$edit_status .= '<a class="status_form_link" href="#" rel="';
				$edit_status .= ($row->acl_status == 'active') ? 'disabled' : 'active';
				$edit_status .= '">';
				$edit_status .= ($row->acl_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
				$edit_status .= '</a>';
				$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
				$checkbox = '<input type="checkbox" name="bulk_list[]" value="' . $row->acl_id .'" />';
			}
			$edit_status .= '</td>';
		} else {
			$edit_status = null;
		}
		
		$edit_name = '<b>' . $row->acl_name . '</b>';
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$edit_name .= displayAddNew('acl', $row->acl_id, null, 'fa fa-plus-square-o', 'plus_subelement', 'bottom');
		}
		$edit_addresses = nl2br(str_replace(',', "\n", $row->acl_addresses));
		$edit_addresses = $this->getACLElements($row->acl_id);
		$element_names = $element_comment = '';
		foreach ($edit_addresses as $element_id => $element_array) {
			$comment = $element_array['element_comment'] ? $element_array['element_comment'] : '&nbsp;';
			$element_names .= '<p class="subelement' . $element_id . '"><span>' . $element_array['element_addresses'] . 
					'</span>' . $element_array['element_edit'] . $element_array['element_delete'] . "</p>\n";
			$element_comment .= '<p class="subelement' . $element_id . '">' . $comment . '</p>' . "\n";
		}
		if ($element_names) $classes[] = 'subelements';
		
		$comments = nl2br($row->acl_comment) . '&nbsp;';

		$class = 'class="' . implode(' ', $classes) . '"';

		echo <<<HTML
		<tr id="$row->acl_id" name="$row->acl_name" $class>
			<td>$checkbox</td>
			<td>$edit_name $element_names</td>
			<td>$comments $element_comment</td>
			$edit_status
		</tr>
HTML;
	}

	/**
	 * Displays the form to add new acl
	 */
	function printForm($data = '', $action = 'add') {
		global $__FM_CONFIG;
		
		$acl_id = $acl_parent_id = 0;
		$acl_name = $acl_addresses = $acl_comment = '';
		$server_serial_no = (isset($_REQUEST['request_uri']['server_serial_no']) && ((is_int($_REQUEST['request_uri']['server_serial_no']) && $_REQUEST['request_uri']['server_serial_no'] > 0) || $_REQUEST['request_uri']['server_serial_no'][0] == 'g')) ? sanitize($_REQUEST['request_uri']['server_serial_no']) : 0;
		
		if (!empty($_POST) && array_key_exists('add_form', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		$acl_addresses = str_replace(',', "\n", rtrim(str_replace(' ', '', (string) $acl_addresses), ';'));

		/** Get field length */
		$acl_name_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_name');

		if ($acl_parent_id) {
			$popup_title = $action == 'add' ? __('Add ACL Element') : __('Edit ACL Element');
		} else {
			$popup_title = $action == 'add' ? __('Add ACL') : __('Edit ACL');
		}
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		$acl_matched_addresses = ($action == 'add' || ($action == 'edit' && $acl_parent_id)) ? sprintf('<tr class="bulkhide">
		<th width="33&#37;" scope="row"><label for="acl_addresses">%s</label></th>
		<td width="67&#37;"><input type="hidden" name="acl_addresses" class="address_match_element" value="%s" /></td>
	</tr>', __('Matched Address List'), $acl_addresses) : '';

		if (!$acl_parent_id) {
			$acl_name = sprintf('<input name="acl_name" id="acl_name" type="text" value="%s" size="40" placeholder="%s" maxlength="%d" class="required" />', $acl_name, __('internal'), $acl_name_length);
		} else {
			$acl_name = getNameFromID($acl_parent_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_', 'acl_id', 'acl_name');
		}

		$acl_elements = $this->getACLList($server_serial_no, 'all', "AND acl_id!='$acl_parent_id'");
		/** Remove used elements from the dropdown */
		$previously_used_addresses = $this->getACLElements($acl_parent_id, 'ids-only');
		if (is_array($previously_used_addresses)) {
			foreach ($acl_elements as $k => $item) {
				if (in_array($item['id'], $previously_used_addresses)) {
					unset($acl_elements[$k]);
				}
			}
		}
		$found = false;
		foreach ($acl_elements as $item) {
			if ($acl_addresses == $item['id']) {
				$found = true;
				break;
			}
		}
		if (!$found) $acl_elements = array_merge(array(array('id' => $acl_addresses, 'text' => $this->parseACL($acl_addresses))), $acl_elements);

		$acl_note = ($action == 'add') ? sprintf('<tr>
		<th width="33&#37;" scope="row"></th>
		<td width="67&#37;"><span><a href="#" id="acl_bulk_add">%s</a></span></td>
	</tr>
	<tr class="bulkshow" style="display: none">
	<th width="33&#37;" scope="row"><label for="acl_bulk">%s</label> <a href="#" class="tooltip-top" data-tooltip="%s"><i class="fa fa-question-circle"></i></a></th>
	<td width="67&#37;"><textarea id="acl_bulk" name="acl_bulk" rows="4" cols="26"></textarea></td>
</tr>
', __('Configure multiple addresses in bulk') . ' &raquo;', __('Matched Address List'), __('On each line enter an address and comment delimited by a comma or semi-colon.')) : null;

		$return_form = sprintf('
	%s
	<form name="manage" id="manage">
		<input type="hidden" name="page" id="page" value="acls" />
		<input type="hidden" name="action" value="%s" />
		<input type="hidden" name="acl_id" value="%d" />
		<input type="hidden" name="acl_parent_id" value="%d" />
		<input type="hidden" name="server_serial_no" value="%s" />
		<table class="form-table">
			<tr>
				<th width="33&#37;" scope="row"><label for="acl_name">%s</label></th>
				<td width="67&#37;">%s</td>
			</tr>
			%s
			%s
			<tr class="bulkhide">
				<th width="33&#37;" scope="row"><label for="acl_comment">%s</label></th>
				<td width="67&#37;"><textarea id="acl_comment" name="acl_comment" rows="4" cols="26">%s</textarea></td>
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
			$(".address_match_element").select2({
				createSearchChoice:function(term, data) { 
					if ($(data).filter(function() { 
						return this.text.localeCompare(term)===0; 
					}).length===0) 
					{return {id:term, text:term};} 
				},
				multiple: true,
				maximumSelectionSize: 1,
				width: "200px",
				tokenSeparators: [",", " ", ";"],
				data: %s
			});
			$("#acl_bulk_add").click(function(e) {
				if ($("input.address_match_element").val()) {
					$("#acl_bulk").val($("input.address_match_element").val()+";"+$("#acl_comment").val());
				}
				$(".bulkhide").slideUp().remove();
				$(".bulkshow").show("slow");
				$(this).parent().parent().parent().slideUp().remove();
			});
		});
	</script>',
			$popup_header,
			$action, $acl_id, $acl_parent_id, $server_serial_no,
			__('ACL Name'), $acl_name,
			$acl_note,
			$acl_matched_addresses,
			_('Comment'), $acl_comment,
			$popup_footer,
			json_encode($acl_elements)
		);

		return $return_form;
	}

	/**
	 * Gets the ACL listing
	 */
	function getACLList($server_serial_no = 0, $include = 'acl', $sql = null) {
		global $__FM_CONFIG, $fmdb;
		
		if ($include == 'none') return array();
		
		$acl_list = array();
		$i = 0;
		$serial_sql = $server_serial_no ? "AND server_serial_no IN ('0','$server_serial_no')" : "AND server_serial_no='0'";
		
		if ($include == 'all') {
			/** Predefined ACLs */
			$acl_list = array_merge($acl_list, $this->getPredefinedACLs());
			$i = count($acl_list);
		}
		
		if (in_array($include, array('all', 'acl'))) {
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_id', 'acl_', $serial_sql . " $sql AND acl_parent_id=0 AND acl_status='active'");
			if ($fmdb->num_rows) {
				$last_result = $fmdb->last_result;
				for ($j=0; $j<$fmdb->num_rows; $j++) {
					$acl_list[$i]['id'] = 'acl_' . $last_result[$j]->acl_id;
					$acl_list[$i]['text'] = $last_result[$j]->acl_name;
					$i++;
					$acl_list[$i]['id'] = '!acl_' . $last_result[$j]->acl_id;
					$acl_list[$i]['text'] = '!' . $last_result[$j]->acl_name;
					$i++;
				}
			}
		}
		
		if (in_array($include, array('all', 'tsig-keys'))) {
			/** Keys */
			$key_type = ' AND key_type="tsig"';
			if ($include == 'all') {
				$view_id = (isset($_POST['view_id']) && is_numeric($_POST['view_id'])) ? $_POST['view_id'] : 0;
				$key_type .= ' AND key_view IN (0, ' . $view_id . ')';
			}
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_id', 'key_', $key_type . ' AND key_status="active"');
			for ($j=0; $j<$fmdb->num_rows; $j++) {
				$acl_list[$i]['id'] = 'key_' . $fmdb->last_result[$j]->key_id;
				$acl_list[$i]['text'] = 'key "' . $fmdb->last_result[$j]->key_name . '"';
				$i++;
				if ($include != 'tsig-keys') {
					$acl_list[$i]['id'] = '!key_' . $fmdb->last_result[$j]->key_id;
					$acl_list[$i]['text'] = '!key "' . $fmdb->last_result[$j]->key_name . '"';
					$i++;
				}
			}
		}
		
		return $acl_list;
	}

	/**
	 * Builds the ACL listing JSON
	 */
	function buildACLJSON($saved_acls, $server_serial_no = 0, $include = 'all') {
		$available_acls = $this->getACLList($server_serial_no, $include);
		$temp_acls = array();
		foreach ($available_acls as $temp_acl_array) {
			$temp_acls[] = $temp_acl_array['id'];
		}
		$i = count($available_acls);
		foreach (explode(',', (string) $saved_acls) as $saved_acl) {
			if (!$saved_acl) continue;
			if (array_search($saved_acl, $temp_acls) === false) {
				$available_acls[$i]['id'] = $saved_acl;
				$available_acls[$i]['text'] = $saved_acl;
				$i++;
			}
		}
		$available_acls = json_encode($available_acls);
		unset($temp_acl_array, $temp_acls);
		
		return $available_acls;
	}
	
	
	function parseACL($address_match_list) {
		global $__FM_CONFIG, $fm_dns_keys;
		
		$acls = explode(',', $address_match_list);
		$formatted_acls = array();
		foreach ($acls as $address) {
			if (strpos($address, 'key_') !== false) {
				if (!class_exists('fm_dns_keys')) {
					include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_keys.php');
				}
				if ($address[0] == '!') {
					$address = str_replace('!', '', $address);
					$negate = '!';
				} else {
					$negate = null;
				}
				$formatted_acls[] = $negate . 'key "' . $fm_dns_keys->parseKey($address, '') . '"';
			} elseif (strpos($address, 'acl_') !== false) {
				$acl_id = str_replace('acl_', '', $address);
				if ($acl_id[0] == '!') {
					$acl_id = str_replace('!', '', $acl_id);
					$negate = '!';
				} else {
					$negate = null;
				}
				$acl_name = getNameFromID($acl_id, "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}acls", 'acl_', 'acl_id', 'acl_name', null, 'active');
				if (strpos($acl_name, ' ') !== false) {
					$acl_name = "\"$acl_name\"";
				}
				$formatted_acls[] = $negate . $acl_name;
			} elseif (strpos($address, 'domain_') !== false) {
				$domain_id = str_replace('domain_', '', $address);
				$formatted_acls[] = getNameFromID($domain_id, "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domains", 'domain_', 'domain_id', 'domain_name', null, 'active');
			} elseif (strpos($address, 'master_') !== false) {
				$master_id = str_replace('master_', '', $address);
				$master_name = getNameFromID($master_id, "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}masters", 'master_', 'master_id', 'master_name', null, 'active');
				if (strpos($master_name, ' ') !== false) {
					$master_name = "\"$master_name\"";
				}
				$formatted_acls[] = $master_name;
			} elseif (strpos($address, 'http_') !== false || strpos($address, 'tls_') !== false) {
				$tmp_array = explode(' ', $address);
				foreach (array('http', 'tls') as $param) {
					$key = array_search($param, $tmp_array);
					if ($key !== false) {
						$cfg_id = str_replace(array('http_', 'tls_'), '', $tmp_array[$key + 1]);
					}
					if (is_numeric($cfg_id)) {
						$address = str_replace($tmp_array[$key + 1], getNameFromID($cfg_id, "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config", 'cfg_', 'cfg_id', 'cfg_data', null, 'active'), $address);
					}
				}
				$formatted_acls[] = $address;
			} elseif (strpos($address, 'dnssec_') !== false) {
				$cfg_id = str_replace('dnssec_', '', $address);
				$formatted_acls[] = getNameFromID($cfg_id, "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config", 'cfg_', 'cfg_id', 'cfg_data', null, 'active');
			} elseif (strpos($address, 'file_') !== false) {
				$id = trim(str_replace('file_', '', $address), '"');
				$formatted_acls[] = sprintf('"%s/include.d/%s"', getNameFromID($id, "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}files", 'file_', 'file_id', 'file_location', null, 'active'), getNameFromID($id, "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}files", 'file_', 'file_id', 'file_name', null, 'active'));
			} else {
				$formatted_acls[] = str_replace(';', '', $address);
			}
		}
		
		return implode('; ', $formatted_acls);
	}
	
	/**
	 * Build array of predefined ACLs
	 *
	 * @since 3.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param string $encode Whether return is encoded (JSON)
	 * @param string $addl_address Add any additional addresses to the array
	 * @return array
	 */
	function getPredefinedACLs($encode = null, $addl_address = null, $return = 'all') {
		$i = 0;
		foreach (array('none', 'any', 'localhost', 'localnets') as $predefined) {
			$acl_list[$i]['id'] = $acl_list[$i]['text'] = $acl_list_names[$i] = $predefined;
			$i++;
			if ($predefined != 'none') {
				$acl_list[$i]['id'] = $acl_list[$i]['text'] = $acl_list_names[$i] = '!' . $predefined;
				$i++;
			}
		}
		
		if ($addl_address) {
			$acl_list[$i]['id'] = $acl_list[$i]['text'] = $acl_list_names[$i] = $addl_address;
		}
		
		if ($return == 'names-only') {
			$acl_list = $acl_list_names;
		}

		return ($encode == 'JSON') ? json_encode($acl_list) : $acl_list;
	}

	/**
	 * Build array of predefined ACLs
	 *
	 * @since 3.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param integer $acl_id ACL ID to query
	 * @param string $format What format to return (display|ids-only)
	 * @return array
	 */
	function getACLElements($acl_parent_id, $format = 'display') {
		global $fmdb, $__FM_CONFIG;
		
		$return = array();
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', $acl_parent_id, 'acl_', 'acl_parent_id', 'ORDER BY acl_id');
		if ($fmdb->num_rows) {
			$count = $fmdb->num_rows;
			$element_array = $fmdb->last_result;
			for ($i=0; $i<$count; $i++) {
				if ($format == 'display') {
					$element_id = $element_array[$i]->acl_id;
					$return[$element_id]['element_addresses'] = $this->parseACL($element_array[$i]->acl_addresses);
					
					/** Delete permitted? */
					if (currentUserCan(array('manage_servers'), $_SESSION['module'])) {
						$return[$element_id]['element_edit'] = '<a class="subelement_edit tooltip-bottom mini-icon" name="acl" href="#" id="' . $element_id . '" data-tooltip="' . _('Edit') . '">' . $__FM_CONFIG['icons']['edit'] . '</a>';
						$return[$element_id]['element_delete'] = ' ' . str_replace('__ID__', $element_id, $__FM_CONFIG['module']['icons']['sub_delete']);
					} else {
						$return[$element_id]['element_delete'] = $return[$element_id]['element_edit'] = null;
					}
					
					/** Element Comment */
					$return[$element_id]['element_comment'] = $element_array[$i]->acl_comment;
				} elseif ($format == 'ids-only') {
					$return[] = $element_array[$i]->acl_addresses;
				}
			}
		}
		return $return;
	}
	
	/**
	 * Validates the submitted form
	 *
	 * @since 6.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param array $post Posted array
	 * @return array|string
	 */
	function validatePost($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Check name field length */
		if (isset($post['acl_name'])) {
			$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_name');
			if ($field_length !== false && strlen($post['acl_name']) > $field_length) return sprintf(dngettext($_SESSION['module'], 'ACL name is too long (maximum %d character).', 'ACL name is too long (maximum %d characters).', $field_length), $field_length);
		}
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		$post['acl_id'] = intval($post['acl_id']);
		$post['acl_parent_id'] = intval($post['acl_parent_id']);

		if ((isset($post['acl_bulk']) && $post['acl_bulk']) && isset($post['acl_addresses'])) unset($post['acl_addresses']);

		/** Does the record already exist for this account? */
		if (array_key_exists('acl_name', $post)) {
			$addl_sql = ($post['server_serial_no']) ? " AND `server_serial_no`!='{$post['server_serial_no']}'" : null;
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', $post['acl_name'], 'acl_', 'acl_name', "AND acl_id!='{$post['acl_id']}'" . $addl_sql);
			if ($fmdb->num_rows) return __('This ACL already exists.');
		} else {
			if (!isset($post['acl_addresses']) || !$post['acl_addresses']) {
				if (!isset($post['acl_bulk']) || (!isset($post['acl_bulk']) && !$post['acl_bulk'])) {
					return __('No ACL address defined nor bulk.');
				} elseif ($post['acl_parent_id']) {
					return $this->addBulkACL($post);
				}
			}
		}

		/** Cleans up acl_addresses for future parsing **/
		if (isset($post['acl_addresses']) && !in_array($post['acl_addresses'], $this->getPredefinedACLs(null, null, 'names-only')) && strpos($post['acl_addresses'], 'acl_') === false && strpos($post['acl_addresses'], 'key_') === false) {
			$ip_check = verifyAndCleanAddresses($post['acl_addresses']);
			if ($ip_check != $post['acl_addresses']) return $ip_check;
		}

		/** Ensure we aren't duplicating ACL entries */
		if (isset($post['acl_addresses']) && $post['acl_parent_id']) {
			if ($post['action'] == 'add' || ($post['action'] == 'edit' && $post['acl_addresses'] != getNameFromID($post['acl_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_', 'acl_id', 'acl_addresses'))) {
				$previously_used_addresses = $this->getACLElements(intval($post['acl_parent_id']), 'ids-only');
				if (is_array($previously_used_addresses)) {
					if (in_array($post['acl_addresses'], $previously_used_addresses)) {
						return sprintf(__("'%s' is already defined in the ACL."), $post['acl_addresses']);
					}
				}
			}
		}

		if (isset($post['acl_addresses']) && $post['acl_addresses'] && !$post['acl_parent_id']) {
			$post['acl_bulk'] = $post['acl_addresses'];
		}
		
		return $post;
	}

	/**
	 * Adds ACL elements in bulk
	 *
	 * @since 6.1
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param array $post Posted array
	 * @return boolean|string
	 */
	function addBulkACL($post) {
		$tmp_post = $post;
		unset($tmp_post['acl_bulk']);
		foreach (explode("\n", $post['acl_bulk']) as $tmp_acl_line) {
			@list($tmp_post['acl_addresses'], $tmp_post['acl_comment']) = explode(';', str_replace(',', ';', trim($tmp_acl_line)));

			// Prevent never-ending loop
			if (!$tmp_post['acl_addresses']) return __('No ACL address defined.');

			$result = $this->add($tmp_post);
			if ($result !== true) return $result;
		}
		return true;
}

}

if (!isset($fm_dns_acls))
	$fm_dns_acls = new fm_dns_acls();
