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

class fm_dns_acls {
	
	/**
	 * Displays the acl list
	 */
	function rows($result, $page, $total_pages) {
		global $fmdb;
		
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;

		$start = $_SESSION['user']['record_count'] * ($page - 1);
		echo displayPagination($page, $total_pages);

		if (!$result) {
			printf('<p id="table_edits" class="noresult" name="acls">%s</p>', __('There are no ACLs.'));
		} else {
			$table_info = array(
							'class' => 'display_results sortable',
							'id' => 'table_edits',
							'name' => 'acls'
						);

			$title_array = array(array('title' => __('Name'), 'rel' => 'acl_name'), 
				array('title' => _('Comment'), 'class' => 'header-nosort'));
			if (currentUserCan('manage_servers', $_SESSION['module'])) $title_array[] = array('title' => __('Actions'), 'class' => 'header-actions header-nosort');

			echo displayTableHeader($table_info, $title_array, 'acls');
			
			$y = 0;
			for ($x=$start; $x<$num_rows; $x++) {
				if ($y == $_SESSION['user']['record_count']) break;
				$this->displayRow($results[$x]);
				$y++;
			}
			
			echo "</tbody>\n</table>\n";
		}
	}

	/**
	 * Adds the new acl
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_name');
		if ($field_length !== false && strlen($post['acl_name']) > $field_length) return sprintf(dngettext($_SESSION['module'], 'ACL name is too long (maximum %d character).', 'ACL name is too long (maximum %d characters).', $field_length), $field_length);
		
		/** Does the record already exist for this account? */
		if (array_key_exists('acl_name', $post)) {
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', sanitize($post['acl_name']), 'acl_', 'acl_name');
			if ($fmdb->num_rows) return __('This ACL already exists.');
		}
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}acls`";
		$sql_fields = '(';
		$sql_values = null;
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		
		/** Cleans up acl_addresses for future parsing **/
		if (in_array($post['acl_addresses'], $this->getPredefinedACLs())) {
			$post['acl_addresses'] = verifyAndCleanAddresses($post['acl_addresses']);
		}
		if (strpos($post['acl_addresses'], 'not valid') !== false) return $post['acl_addresses'];
		
		$post['acl_comment'] = trim($post['acl_comment']);
		
		$exclude = array('submit', 'action', 'server_id');

		foreach ($post as $key => $data) {
			$clean_data = sanitize($data);
			if ($key == 'acl_name' && empty($clean_data)) return __('No ACL name defined.');
			if (!in_array($key, $exclude)) {
				$sql_fields .= $key . ', ';
				$sql_values .= "'$clean_data', ";
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the ACL because a database error occurred.'), 'sql');
		}

		$log_message = sprintf(__("Added ACL:\nName: %s\nComment: %s"), $post['acl_name'], $post['acl_comment']);
		if (isset($post['acl_parent_id'])) {
			$log_message = sprintf(__("%s was added to the %s ACL"), $post['acl_addresses'], getNameFromID($post['acl_parent_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_', 'acl_id', 'acl_name'));
		}
		addLogEntry($log_message);
		return true;
	}

	/**
	 * Updates the selected acl
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_name');
		if ($field_length !== false && strlen($post['acl_name']) > $field_length) return sprintf(dngettext($_SESSION['module'], 'ACL name is too long (maximum %d character).', 'ACL name is too long (maximum %d characters).', $field_length), $field_length);
		
		/** Does the record already exist for this account? */
		if (array_key_exists('acl_name', $post)) {
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', sanitize($post['acl_name']), 'acl_', 'acl_name');
			if ($fmdb->num_rows) {
				$result = $fmdb->last_result;
				if ($result[0]->acl_id != $post['acl_id']) return __('This ACL already exists.');
			}
			
			if (empty($post['acl_name'])) return __('No ACL name defined.');
		}
		
		/** Cleans up acl_addresses for future parsing **/
		if (in_array($post['acl_addresses'], $this->getPredefinedACLs())) {
			$post['acl_addresses'] = verifyAndCleanAddresses($post['acl_addresses']);
		}
		if (strpos($post['acl_addresses'], 'not valid') !== false) return $post['acl_addresses'];
		
		$post['acl_comment'] = trim($post['acl_comment']);
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		
		$exclude = array('submit', 'action', 'server_id');

		$sql_edit = null;
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . sanitize($data) . "', ";
			}
		}
		$sql = rtrim($sql_edit, ', ');
		
		// Update the acl
		$old_name = getNameFromID($post['acl_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_', 'acl_id', 'acl_name');
		$old_address = getNameFromID($post['acl_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_', 'acl_id', 'acl_addresses');
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}acls` SET $sql WHERE `acl_id`={$post['acl_id']}";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not update the ACL because a database error occurred.'), 'sql');
		}

		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		$log_message = sprintf(__("Updated ACL '%s' to the following:\nName: %s\nComment: %s"), $old_name, $post['acl_name'], $post['acl_comment']);
		if (!$old_name) {
			$tmp_parent_id = getNameFromID($post['acl_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_', 'acl_id', 'acl_parent_id');
			$tmp_name = getNameFromID($tmp_parent_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_', 'acl_id', 'acl_name');
			$log_message = sprintf(__("%s was updated to %s on the %s ACL"), $old_address, $post['acl_addresses'], $tmp_name);
		}
		addLogEntry($log_message);
		return true;
	}
	
	
	/**
	 * Deletes the selected ACL
	 */
	function delete($id, $server_serial_no = 0) {
		global $fmdb, $__FM_CONFIG;
		
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_', 'acl_id', 'acl_name');
		$log_message = sprintf(__("ACL '%s' was deleted"), $tmp_name);
		if (!$tmp_name) {
			$tmp_parent_id = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_', 'acl_id', 'acl_parent_id');
			$tmp_address = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_', 'acl_id', 'acl_addresses');
			$tmp_name = getNameFromID($tmp_parent_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_', 'acl_id', 'acl_name');
			$log_message = sprintf(__("%s was deleted from the %s ACL"), $tmp_address, $tmp_name);
		} else {
			$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}acls` SET `acl_status`='deleted' WHERE account_id='{$_SESSION['user']['account_id']}' AND `acl_parent_id`='" . sanitize($id) . "'";
			$fmdb->query($query);
			if ($fmdb->sql_errors) {
				return formatError(__('The associated ACL elements could not be deleted because a database error occurred.'), 'sql');
			}
		}
		if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', $id, 'acl_', 'deleted', 'acl_id') === false) {
			return formatError(__('This ACL could not be deleted because a database error occurred.'), 'sql');
		} else {
			setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
			addLogEntry($log_message);
			return true;
		}
	}


	function displayRow($row) {
		global $__FM_CONFIG;
		
		if ($row->acl_status == 'disabled') $classes[] = 'disabled';
		
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$edit_status = '<td id="row_actions">';
			$edit_status .= '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			if (!getConfigAssoc($row->acl_id, 'acl')) {
				$edit_status .= '<a class="status_form_link" href="#" rel="';
				$edit_status .= ($row->acl_status == 'active') ? 'disabled' : 'active';
				$edit_status .= '">';
				$edit_status .= ($row->acl_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
				$edit_status .= '</a>';
				$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			}
			$edit_status .= '</td>';
		} else {
			$edit_status = null;
		}
		
		$edit_name = '<b>' . $row->acl_name . '</b>';
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$edit_name .= displayAddNew('acl', $row->acl_id, null, 'fa fa-plus-square-o');
		}
		$edit_addresses = nl2br(str_replace(',', "\n", $row->acl_addresses));
		$edit_addresses = $this->getACLElements($row->acl_id);
		$element_names = $element_comment = null;
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
		$acl_name = $acl_addresses = $acl_comment = null;
		$ucaction = ucfirst($action);
		$server_serial_no = (isset($_REQUEST['request_uri']['server_serial_no']) && ((is_int($_REQUEST['request_uri']['server_serial_no']) && $_REQUEST['request_uri']['server_serial_no'] > 0) || $_REQUEST['request_uri']['server_serial_no'][0] == 'g')) ? sanitize($_REQUEST['request_uri']['server_serial_no']) : 0;
		
		if (!empty($_POST) && array_key_exists('add_form', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		$acl_addresses = str_replace(',', "\n", rtrim(str_replace(' ', '', $acl_addresses), ';'));

		/** Get field length */
		$acl_name_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_name');

		if ($acl_parent_id) {
			$popup_title = $action == 'add' ? __('Add ACL Element') : __('Edit ACL Element');
		} else {
			$popup_title = $action == 'add' ? __('Add ACL') : __('Edit ACL');
		}
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		if (!$acl_parent_id) {
			$return_form = sprintf('<form name="manage" id="manage" method="post" action="">
		%s
			<input type="hidden" name="action" value="%s" />
			<input type="hidden" name="acl_id" value="%d" />
			<input type="hidden" name="server_serial_no" value="%s" />
			<table class="form-table">
				<tr>
					<th width="33&#37;" scope="row"><label for="acl_name">%s</label></th>
					<td width="67&#37;"><input name="acl_name" id="acl_name" type="text" value="%s" size="40" placeholder="%s" maxlength="%d" /></td>
				</tr>
				<tr>
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
			});
		</script>',
				$popup_header,
				$action, $acl_id, $server_serial_no,
				__('ACL Name'), $acl_name, __('internal'), $acl_name_length,
				_('Comment'), $acl_comment,
				$popup_footer
			);
		} else {
			$return_form = sprintf('<form name="manage" id="manage" method="post" action="">
		%s
			<input type="hidden" name="action" value="%s" />
			<input type="hidden" name="acl_id" value="%d" />
			<input type="hidden" name="acl_parent_id" value="%d" />
			<input type="hidden" name="server_serial_no" value="%s" />
			<table class="form-table">
				<tr>
					<th width="33&#37;" scope="row">%s</th>
					<td width="67&#37;">%s</td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="acl_addresses">%s</label></th>
					<td width="67&#37;"><input type="hidden" name="acl_addresses" class="address_match_element" value="%s" /></td>
				</tr>
				<tr>
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
			});
		</script>',
				$popup_header,
				$action, $acl_id, $acl_parent_id, $server_serial_no,
				__('ACL Name'), getNameFromID($acl_parent_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_', 'acl_id', 'acl_name'),
				__('Matched Address List'), $acl_addresses,
				_('Comment'), $acl_comment,
				$popup_footer,
				$this->getPredefinedACLs('JSON', $acl_addresses)
			);
		}

		return $return_form;
	}

	/**
	 * Gets the ACL listing
	 */
	function getACLList($server_serial_no = 0, $include = 'acl') {
		global $__FM_CONFIG, $fmdb;
		
		if ($include == 'none') return array();
		
		$acl_list = array();
		$i = 0;
		$serial_sql = $server_serial_no ? "AND server_serial_no IN ('0','$server_serial_no')" : "AND server_serial_no='0'";
		
		if (in_array($include, array('all', 'acl'))) {
			basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_id', 'acl_', $serial_sql . " AND acl_parent_id=0 AND acl_status='active'");
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
			if ($include == 'all') {
				/** Predefined ACLs */
				$acl_list = array_merge($acl_list, $this->getPredefinedACLs());
				$i = count($acl_list);
			}
			
			/** Keys */
			if ($include == 'tsig-keys') {
				$key_type = ' AND key_type="tsig"';
			} else {
				$view_id = (isset($_POST['view_id']) && is_numeric(sanitize($_POST['view_id']))) ? sanitize($_POST['view_id']) : 0;
				$key_type = 'AND key_view IN (0, ' . $view_id . ')';
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
		foreach (explode(',', $saved_acls) as $saved_acl) {
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
		$formatted_acls = null;
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
	function getPredefinedACLs($encode = null, $addl_address = null) {
		$i = 0;
		foreach (array('none', 'any', 'localhost', 'localnets') as $predefined) {
			$acl_list[$i]['id'] = $predefined;
			$acl_list[$i]['text'] = $predefined;
			$i++;
			if ($predefined != 'none') {
				$acl_list[$i]['id'] = '!' . $predefined;
				$acl_list[$i]['text'] = '!' . $predefined;
				$i++;
			}
		}
		
		if ($addl_address) {
			$acl_list[$i]['id'] = $addl_address;
			$acl_list[$i]['text'] = $addl_address;
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
	 * @return array
	 */
	function getACLElements($acl_parent_id) {
		global $fmdb, $__FM_CONFIG;
		
		$return = null;
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', $acl_parent_id, 'acl_', 'acl_parent_id', 'ORDER BY acl_id');
		if ($fmdb->num_rows) {
			$count = $fmdb->num_rows;
			$element_array = $fmdb->last_result;
			for ($i=0; $i<$count; $i++) {
				$element_id = $element_array[$i]->acl_id;
				$return[$element_id]['element_addresses'] = $element_array[$i]->acl_addresses;
				
				/** Delete permitted? */
				if (currentUserCan(array('manage_servers'), $_SESSION['module'])) {
					$return[$element_id]['element_edit'] = '<a class="subelement_edit" name="acl" href="#" id="' . $element_id . '">' . $__FM_CONFIG['icons']['edit'] . '</a>';
					$return[$element_id]['element_delete'] = ' ' . str_replace('__ID__', $element_id, $__FM_CONFIG['module']['icons']['sub_delete']);
				} else {
					$return[$element_id]['element_delete'] = $return[$element_id]['element_edit'] = null;
				}
				
				/** Element Comment */
				$return[$element_id]['element_comment'] = $element_array[$i]->acl_comment;
			}
		}
		return $return;
	}
	
}

if (!isset($fm_dns_acls))
	$fm_dns_acls = new fm_dns_acls();

?>
