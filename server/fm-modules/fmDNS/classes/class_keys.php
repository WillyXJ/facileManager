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

class fm_dns_keys {
	
	/**
	 * Displays the key list
	 */
	function rows($result, $type, $page, $total_pages) {
		global $fmdb;
		
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;

		$bulk_actions_list = array(_('Enable'), _('Disable'), _('Delete'));

		$start = $_SESSION['user']['record_count'] * ($page - 1);
		$addl_blocks = ($type == 'dnssec') ? $this->buildFilterMenu() : null;
		
		$fmdb->num_rows = $num_rows;

		echo displayPagination($page, $total_pages, array(@buildBulkActionMenu($bulk_actions_list), $addl_blocks));

		if (!$result) {
			printf('<p id="table_edits" class="noresult" name="keys">%s</p>', __('There are no keys.'));
		} else {
			$table_info = array(
							'class' => 'display_results sortable',
							'id' => 'table_edits',
							'name' => 'keys'
						);

			if (is_array($bulk_actions_list)) {
				$title_array[] = array(
									'title' => '<input type="checkbox" class="tickall" onClick="toggle(this, \'bulk_list[]\')" />',
									'class' => 'header-tiny header-nosort'
								);
			}
			$title_array = array_merge((array) $title_array, array(array('class' => 'header-tiny header-nosort'), array('title' => __('Key'), 'rel' => 'key_name')));
			if ($type == 'tsig') {
				$title_array = array_merge($title_array, array(
					array('title' => __('Algorithm'), 'class' => 'header-nosort'),
					array('title' => __('Secret'), 'rel' => 'key_secret'),
					array('title' => __('View'), 'class' => 'header-nosort')));
			} else {
				$title_array = array_merge($title_array, array(
					array('title' => __('Type'), 'rel' => 'key_secret'),
					array('title' => __('Algorithm'), 'class' => 'header-nosort'),
					array('title' => __('Bits'), 'class' => 'header-nosort'),
					array('title' => __('Created'), 'rel' => 'key_created')));
			}
			$title_array[] = array('title' => _('Comment'), 'class' => 'header-nosort');
			if (currentUserCan('manage_servers', $_SESSION['module'])) $title_array[] = array('title' => __('Actions'), 'class' => 'header-actions header-nosort');

			echo displayTableHeader($table_info, $title_array);
			
			$y = 0;
			for ($x=$start; $x<$num_rows; $x++) {
				if ($y == $_SESSION['user']['record_count']) break;
				$this->displayRow($results[$x], $type);
				$y++;
			}
			
			echo "</tbody>\n</table>\n";
		}
	}

	/**
	 * Adds the new key
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG;
		
		$post['key_comment'] = trim($post['key_comment']);
		
		/** DNSSEC */
		if ($post['key_type'] == 'dnssec' && !isset($post['generate'])) {
			if (!$post['domain_id']) return __('You must specify a zone.');
			if (sanitize($post['key_secret']) && sanitize($post['key_subtype']) == __('Both')) return __('You must choose a key type.');
			
			$post['key_name'] = displayFriendlyDomainName(getNameFromID($post['domain_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name')) .
					'_' . strtolower(sanitize($post['key_subtype']));
			
			/** Generate keys and replace $post */
			if (!$post['key_secret']) {
				$post = $this->generateDNSSECKeys($post);
				if (!is_array($post)) return $post;
			}
		}

		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_name');
		if ($field_length !== false && strlen($post['key_name']) > $field_length) return sprintf(dngettext($_SESSION['module'], 'Key name is too long (maximum %d character).', 'Key name is too long (maximum %d characters).', $field_length), $field_length);
		
		/** Does the key already exist for this account? */
		if ($post['key_type'] == 'tsig') {
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', sanitize($post['key_name']), 'key_', 'key_name');
			if ($fmdb->num_rows) return __('This key already exists.');
		}
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}keys`";
		$sql_fields = '(';
		$sql_values = null;
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		$post['key_created'] = strtotime('now');
		
		$exclude = array('submit', 'action', 'key_id', 'generate');

		foreach ($post as $key => $data) {
			$clean_data = sanitize($data);
			if (($key == 'key_name' || $key == 'key_secret') && empty($clean_data)) return __('No key defined.');
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
			return formatError(__('Could not add the key because a database error occurred.'), 'sql');
		}

		$view_name = $post['key_view'] ? getNameFromID($post['key_view'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name') : 'All Views';
		addLogEntry("Added key:\nName: {$post['key_name']}\nType: " . strtoupper($post['key_type']) . "\nAlgorithm: {$post['key_algorithm']}\nView: $view_name\nComment: {$post['key_comment']}");
				
		return true;
	}

	/**
	 * Updates the selected key
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		if ($post['key_type'] == 'tsig' && (empty($post['key_name']) || empty($post['key_secret']))) return __('No key defined.');
		$post['key_comment'] = trim($post['key_comment']);
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_name');
		if ($field_length !== false && strlen($post['key_name']) > $field_length) return sprintf(dngettext($_SESSION['module'], 'Key name is too long (maximum %d character).', 'Key name is too long (maximum %d characters).', $field_length), $field_length);
		
		/** Does the key already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', sanitize($post['key_name']), 'key_', 'key_name');
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			if ($result[0]->key_id != $post['key_id']) return __('This key already exists.');
		}
		
		if (sanitize($post['key_status']) == 'revoked') {
			$post = $this->revokeDNSSECKey($post);
			if (!is_array($post)) return $post;
		}
		
		$exclude = array('submit', 'action', 'key_id');

		$sql_edit = null;
		
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . sanitize($data) . "', ";
			}
		}
		$sql = rtrim($sql_edit, ', ');
		
		// Update the key
		$old_name = getNameFromID($post['key_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_', 'key_id', 'key_name');
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}keys` SET $sql WHERE `key_id`={$post['key_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not update the key because a database error occurred.'), 'sql');
		}

		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		$view_name = $post['key_view'] ? getNameFromID($post['key_view'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name') : 'All Views';
		addLogEntry("Updated key '$old_name' to the following:\nName: {$post['key_name']}\nAlgorithm: {$post['key_algorithm']}\nSecret: {$post['key_secret']}\nView: $view_name\nComment: {$post['key_comment']}");
		return true;
	}
	
	
	/**
	 * Deletes the selected key
	 */
	function delete($id, $server_serial_no = 0) {
		global $fmdb, $__FM_CONFIG;
		
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_', 'key_id', 'key_name');
		if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', $id, 'key_', 'deleted', 'key_id') === false) {
			return formatError(__('This key could not be deleted because a database error occurred.'), 'sql');
		} else {
			setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
			addLogEntry(sprintf(__("Key '%s' was deleted."), $tmp_name));
			return true;
		}
	}


	function displayRow($row, $type) {
		global $__FM_CONFIG;
		
		$edit_status = $checkbox = null;
		
		if ($row->key_status == 'disabled') $classes[] = 'disabled';
		if ($row->key_status == 'revoked') $classes[] = 'attention';
		
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$edit_status = '<td id="row_actions">';
			$edit_status .= '<a class="edit_form_link" name="' . $row->key_type . '" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			if (!getConfigAssoc($row->key_id, 'key')) {
				if ($row->key_status != 'revoked') {
					$edit_status .= '<a class="status_form_link" href="#" rel="';
					$edit_status .= ($row->key_status == 'active') ? 'disabled' : 'active';
					$edit_status .= '">';
					$edit_status .= ($row->key_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
					$edit_status .= '</a>';
				}
				if ($row->key_signing == 'no' || $row->key_status == 'revoked') {
					$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
					$checkbox = '<input type="checkbox" name="bulk_list[]" value="' . $row->key_id .'" />';
				}
			}
			$edit_status .= '</td>';
		}
		
		$edit_name = $row->key_name;
		$rows = null;
		
		if ($type == 'tsig') {
			$key_view = ($row->key_view) ? getNameFromID($row->key_view, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name') : 'All Views';
			$rows .= "<td>$row->key_algorithm</td>\n";
			$rows .= "<td>$row->key_secret</td>\n";
			$rows .= "<td>$key_view</td>\n";
		} else {
			$rows .= "<td>$row->key_subtype</td>\n";
			$rows .= "<td>$row->key_algorithm</td>\n";
			$rows .= "<td>$row->key_size</td>\n";
			$rows .= '<td>' . date(getOption('date_format', $_SESSION['user']['account_id']) . ' ' . getOption('time_format', $_SESSION['user']['account_id']) . ' e', $row->key_created) . '</td>';
		}
		
		$comments = nl2br($row->key_comment);
		$star = ($row->key_signing == 'yes' && $row->key_status != 'revoked') ? sprintf('<i class="fa fa-star star" alt="%1$s" title="%1$s" aria-hidden="true"></i>', _('The zone is signed with this key')) : null;
		$star = ($row->key_status == 'revoked') ? sprintf('<a href="#" class="tooltip-top" data-tooltip="%s"><i class="fa fa-question-circle"></i></a>', __('This key has been revoked.')) : $star;
		$class = 'class="' . implode(' ', $classes) . '"';

		echo <<<HTML
		<tr id="$row->key_id" name="$row->key_name" $class>
			<td>$checkbox</td>
			<td>$star</td>
			<td>$edit_name</td>
			$rows
			<td>$comments</td>
			$edit_status
		</tr>
HTML;
	}

	/**
	 * Displays the form to add new key
	 */
	function printForm($data = '', $action = 'add', $type = 'tsig') {
		global $__FM_CONFIG, $fm_dns_zones;
		
		include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_zones.php');
		
		$key_id = 0;
		$key_name = $key_root_dir = $key_zones_dir = $key_comment = $key_signing = null;
		$ucaction = ucfirst($action);
		$key_view = $key_secret = $key_public = $key_signing_checked = null;
		$addl_options = $domain_id = $key_subtype = null;
		$key_algorithm = 'rsasha256';
		$key_size = 2048;
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}

		$key_algorithm = buildSelect('key_algorithm', 'key_algorithm', $this->getKeyAlgorithms($type), $key_algorithm, 1);

		$popup_title = $action == 'add' ? __('Add Key') : __('Edit Key');
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		if ($type == 'tsig') {
			/** Check name field length */
			$key_name_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_name');

			$key_view = buildSelect('key_view', 'key_view', $fm_dns_zones->availableViews(), $key_view);

			$key_options = sprintf('<tr>
					<th width="33&#37;" scope="row"><label for="key_name">%s</label></th>
					<td width="67&#37;"><input name="key_name" id="key_name" type="text" value="%s" size="40" maxlength="%d" /></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="key_view">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="key_algorithm">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="key_secret">%s</label></th>
					<td width="67&#37;"><input name="key_secret" id="key_secret" type="text" value="%s" size="40" /></td>
				</tr>',
					__('Key Name'), $key_name, $key_name_length,
					__('View'), $key_view,
					__('Algorithm'), $key_algorithm,
					__('Secret'), $key_secret
				);
		} elseif ($type == 'dnssec') {
			$available_zones = $fm_dns_zones->buildZoneJSON('defined only');
			$key_subtype = buildSelect('key_subtype', 'key_subtype', array_merge(array(__('Both')), enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys','key_subtype')), $key_subtype, 1);
			$key_secret_placeholder = __('The private key and associated DNSKEY RR will be automatically generated if this field is left blank.');
			$key_signing_checked = ($key_signing == 'yes') ? 'checked' : null;

			$key_options = null;
			
			if ($action == 'add') {
				$key_options = sprintf('
				<tr>
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
					</script>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"></th>
					<td width="67&#37;"><span><a href="#" id="dnssec_key_addl_options">%s</a></span></td>
				</tr>
				<tr class="dnssec-key-addl-options">
					<th width="33&#37;" scope="row"><label for="key_subtype">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
				<tr class="dnssec-key-addl-options">
					<th width="33&#37;" scope="row"><label for="key_algorithm">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
				<tr class="dnssec-key-addl-options">
					<th width="33&#37;" scope="row"><label for="key_size">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="key_secret">%s</label></th>
					<td width="67&#37;">
						<textarea id="key_secret" name="key_secret" rows="4" cols="30" placeholder="%s">%s</textarea>
					</td>
				</tr>
				<tr class="dnssec-key-public hidden">
					<th width="33&#37;" scope="row"><label for="key_public">%s</label></th>
					<td width="67&#37;">
						<textarea id="key_public" name="key_public" rows="4" cols="30" placeholder="%s">%s</textarea>
					</td>
				</tr>
				',
					__('Zone'), $domain_id, $available_zones,
					__('Configure Additional Options') . ' &raquo;',
					__('Key Type'), $key_subtype,
					__('Algorithm'), $key_algorithm,
					__('Key Size'), buildSelect('key_size', 'key_size', $__FM_CONFIG['keys']['avail_sizes'], $key_size),
					__('Secret'), $key_secret_placeholder, $key_secret,
					__('DNSKEY RR'), $key_secret_placeholder, $key_public
				);
			} else {
				$key_revoked_checked = ($key_status == 'revoked') ? 'checked disabled' : null;
				$key_options .= sprintf('<tr>
					<th></th>
					<td>
						<input type="checkbox" id="key_status" name="key_status" value="revoked" %s /><label for="key_status"> %s</label>
					</td>
				</tr>
				',
					$key_revoked_checked, __('Revoke this key')
				);
			}
			$key_options .= sprintf('<tr>
					<th></th>
					<td>
						<input type="checkbox" id="key_signing" name="key_signing" value="yes" %s /><label for="key_signing"> %s</label>
					</td>
				</tr>
				',
					$key_signing_checked, __('Sign zone with this key')
				);
		}
		
		$return_form = sprintf('<form name="manage" id="manage" method="post" action="">
		%s
			<input type="hidden" name="action" value="%s" />
			<input type="hidden" name="key_id" value="%d" />
			<input type="hidden" name="key_type" value="%s" />
			<input type="hidden" name="key_signing" value="no" />
			<table class="form-table">
				%s
				<tr>
					<th width="33&#37;" scope="row"><label for="key_comment">%s</label></th>
					<td width="67&#37;"><textarea id="key_comment" name="key_comment" rows="4" cols="30">%s</textarea></td>
				</tr>
			</table>
		%s
		</form>',
				$popup_header,
				$action, $key_id, $type, $key_options,
				_('Comment'), $key_comment,
				$popup_footer
			);
		$return_form .= <<< HTML
		<script>
			$(document).ready(function() {
				$("#manage select").select2({
					minimumResultsForSearch: 10, width: "175px"
				});
				$("#dnssec_key_addl_options").click(function() {
					$('.form-table > tbody > tr.dnssec-key-addl-options').show('slow');
					$(this).closest('tr').remove();
				});
				if ($(".dnssec-key-public").is(':hidden')) {
					$("#key_secret").keydown(function() {
						$('.form-table > tbody > tr.dnssec-key-public').show('slow');
					});
				}
			});
		</script>
HTML;

		return $return_form;
	}
	
	
	function parseKey($keys, $glue = '"; "') {
		global $__FM_CONFIG;
		
		$formatted_keys = null;
		foreach (explode(',', $keys) as $key_id) {
			$key_id = str_replace('key_', '', $key_id);
			$formatted_keys[] = getNameFromID($key_id, "fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}keys", 'key_', 'key_id', 'key_name', null, 'active');
		}
		
		return implode($glue, $formatted_keys);
	}
	
	
	/**
	 * Gets the available algorithms by type
	 *
	 * @since 3.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param string $type tsig or dnssec
	 * @return array
	 */
	function getKeyAlgorithms($type = 'tsig') {
		global $__FM_CONFIG;
		
		$available_keys = null;
		$all_keys = enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_algorithm');
		
		foreach ($all_keys as $key) {
			switch ($type) {
				case 'tsig':
					if (strpos($key, 'hmac') !== false) {
						$available_keys[] = $key;
					}
					break;
				case 'dnssec':
					if (strpos($key, 'hmac') === false) {
						$available_keys[] = $key;
					}
					break;
			}
		}
		
		return $available_keys;
	}


	/**
	 * Generates the DNSSEC keys
	 *
	 * @since 3.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param array $data POSTed data
	 * @return array
	 */
	private function generateDNSSECKeys($data) {
		global $__FM_CONFIG;
		
		if (!$dnssec_keygen = findProgram('dnssec-keygen')) return sprintf(__('The dnssec-keygen utility cannot be found on %s to generate the keys.'), php_uname('n'));
		
		list($tmp_dir, $created) = createTempDir($_SESSION['module'], 'datetime');
		if ($created === false) exit(sprintf(__('%s is not writeable by %s so DNSSEC keys cannot be created.'), $tmp_dir, $__FM_CONFIG['webserver']['user_info']['name']));
		
		$domain_name = displayFriendlyDomainName(getNameFromID($data['domain_id'], 'fm_'. $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name'));
		
		/** ZSK */
		if ($data['key_subtype'] == 'ZSK' || $data['key_subtype'] == __('Both')) {
			$zsk_output = shell_exec("$dnssec_keygen -K $tmp_dir -a {$data['key_algorithm']} -b {$data['key_size']} -n zone " . escapeshellarg($domain_name));
			$data['key_secret'] = file_get_contents($tmp_dir . DIRECTORY_SEPARATOR . trim($zsk_output) . '.private');
			$data['key_public'] = file_get_contents($tmp_dir . DIRECTORY_SEPARATOR . trim($zsk_output) . '.key');
			$data['key_name'] = trim($zsk_output);
			
			/** Remove temp files */
			@unlink($tmp_dir . DIRECTORY_SEPARATOR . trim($zsk_output) . '.private');
			@unlink($tmp_dir . DIRECTORY_SEPARATOR . trim($zsk_output) . '.key');
			unset($zsk_output);
			
			if ($data['key_subtype'] == __('Both')) {
				$data['generate'] = 'both';
				$data['key_subtype'] = 'ZSK';
				$create_key = $this->add($data);
				if ($create_key !== true) return $create_key;
				$data['key_subtype'] = 'KSK';
			}
		}
		
		/** KSK */
		if ($data['key_subtype'] == 'KSK') {
			$ksk_output = shell_exec("$dnssec_keygen -K $tmp_dir -a {$data['key_algorithm']} -b {$data['key_size']} -f KSK -n zone " . escapeshellarg($domain_name));
			$data['key_secret'] = file_get_contents($tmp_dir . DIRECTORY_SEPARATOR . trim($ksk_output) . '.private');
			$data['key_public'] = file_get_contents($tmp_dir . DIRECTORY_SEPARATOR . trim($ksk_output) . '.key');
			$data['key_name'] = trim($ksk_output);
			
			/** Remove temp files */
			@unlink($tmp_dir . DIRECTORY_SEPARATOR . trim($ksk_output) . '.private');
			@unlink($tmp_dir . DIRECTORY_SEPARATOR . trim($ksk_output) . '.key');
			unset($ksk_output);
		}
		
		system('rm -rf ' . $tmp_dir);
		
		return $data;
	}


	/**
	 * Revokes the DNSSEC key
	 *
	 * @since 3.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param array $data POSTed data
	 * @return array or string
	 */
	private function revokeDNSSECKey($data) {
		global $fmdb, $__FM_CONFIG;
		
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', $data['key_id'], 'key_', 'key_id');
		if (!$fmdb->num_rows) return _('There was a problem with your request.');

		$saved_data = $fmdb->last_result[0];
		
		
		if (!$dnssec_revoke = findProgram('dnssec-revoke')) return sprintf(__('The dnssec-revoke utility cannot be found on %s to revoke the key.'), php_uname('n'));
		
		list($tmp_dir, $created) = createTempDir($_SESSION['module'], 'datetime');
		if ($created === false) exit(sprintf(__('%s is not writeable by %s so the DNSSEC key cannot be revoked.'), $tmp_dir, $__FM_CONFIG['webserver']['user_info']['name']));
		
		file_put_contents($tmp_dir . $saved_data->key_name . '.private', $saved_data->key_secret);
		file_put_contents($tmp_dir . $saved_data->key_name . '.key', $saved_data->key_public);
		
		$output = shell_exec("$dnssec_revoke -K $tmp_dir " . escapeshellarg($saved_data->key_name) . ' 2>&1');
		$data['key_secret'] = file_get_contents(trim($output) . '.private');
		$data['key_public'] = file_get_contents(trim($output) . '.key');
		$data['key_name'] = str_replace($tmp_dir, '', trim($output));
		$data['key_signing'] = 'yes';
		
		system('rm -rf ' . $tmp_dir);
		
		return ($data['key_name'] && $data['key_secret'] && $data['key_public']) ? $data : sprintf(__('An error occured while revoking the key: %s'), trim($output));
	}


	/**
	 * Builds the key listing filter menu
	 *
	 * @since 3.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param id $ids IDs to convert to names
	 * @return string
	 */
	function buildFilterMenu() {
		global $fm_dns_zones;
		
		if (!class_exists('fm_dns_zones')) {
			include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_zones.php');
		}
		
		$domain_view = isset($_GET['domain_id']) ? $_GET['domain_id'] : 0;
		
		$available_zones = array_reverse($fm_dns_zones->availableZones('all', 'master', 'restricted'));
		$available_zones[] = array(null, null);
		$available_zones = array_reverse($available_zones);
		
		return sprintf('<form method="GET" action="">
				<input type="hidden" name="type" value="%s" />
				%s 
				<input type="submit" name="" id="" value="%s" class="button" /></form>' . "\n",
				sanitize($_GET['type']),
				buildSelect('domain_id', 'domain_id', $available_zones, $domain_view, 1, null, true, null, null, __('Filter Zones')),
				__('Filter')
			);
	}
	
	
}

if (!isset($fm_dns_keys))
	$fm_dns_keys = new fm_dns_keys();

?>
