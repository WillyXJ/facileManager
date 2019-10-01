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

class fm_module_logging {
	
	/**
	 * Displays the logging list
	 */
	function rows($result, $channel_category, $page, $total_pages) {
		global $fmdb, $__FM_CONFIG;
		
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;

		$start = $_SESSION['user']['record_count'] * ($page - 1);
		echo displayPagination($page, $total_pages);

		if (!$result) {
			printf('<p id="table_edits" class="noresult" name="logging">%s</p>', sprintf(__('There are no %s defined.'), strtolower($__FM_CONFIG['logging']['avail_types'][$channel_category])));
		} else {
			$table_info = array(
							'class' => 'display_results sortable',
							'id' => 'table_edits',
							'name' => 'logging'
						);

			$title_array[] = array('title' => __('Name'), 'rel' => 'cfg_data');
			if ($channel_category == 'category') $title_array[] = array('title' => __('Channels'), 'class' => 'header-nosort');
			$title_array[] = array('title' => _('Comment'), 'class' => 'header-nosort');
			if (currentUserCan('manage_servers', $_SESSION['module'])) $title_array[] = array('title' => __('Actions'), 'class' => 'header-actions header-nosort');

			echo displayTableHeader($table_info, $title_array);
			
			$y = 0;
			for ($x=$start; $x<$num_rows; $x++) {
				if ($y == $_SESSION['user']['record_count']) break;
				$this->displayRow($results[$x], $channel_category);
				$y++;
			}
			
			echo "</tbody>\n</table>\n";
		}
	}

	/**
	 * Adds the new channel
	 */
	function addChannel($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Insert the parent */
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}config`";
		$sql_fields = '(';
		$sql_values = null;
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		$post['cfg_isparent'] = 'yes';
		$post['cfg_data'] = $channel_name = $post['cfg_name'];
		$post['cfg_name'] = $post['sub_type'];
		$post['cfg_comment'] = trim($post['cfg_comment']);
		
		if (empty($channel_name)) return __('No channel name defined.');
		
		/** Ensure unique channel names */
		if (!$this->validateChannel($post)) return __('This channel already exists.');
		
		if ($post['cfg_destination'] == 'file') {
			if (empty($post['cfg_file_path'][0])) return __('No file path defined.');
		}
		$exclude = array('submit', 'action', 'cfg_id', 'sub_type', 'temp_data', 'cfg_destination',
					'cfg_file_path', 'cfg_syslog', 'severity', 'print-category', 'print-severity',
					'print-time');
		
		/** Insert the category parent */
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$clean_data = sanitize($data, '_');
				$sql_fields .= $key . ', ';
				$sql_values .= "'$clean_data', ";
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the channel because a database error occurred.'), 'sql');
		}

		/** Insert channel children */
		$post['cfg_isparent'] = 'no';
		$post['cfg_parent'] = $fmdb->insert_id;
		$post['cfg_data'] = null;
		$post['cfg_name'] = '';
		$include = array('cfg_destination', 'severity', 'print-category', 'print-severity', 'print-time');
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}config`";
		$sql_fields = '(';
		$sql_values = '(';
		
		$i = 1;
		foreach ($include as $handler) {
			$post['cfg_data'] = $post[$handler];
			/** Logic checking */
			if ($handler == 'cfg_destination' && $post[$handler] == 'syslog') {
				$post['cfg_data'] = $post['cfg_syslog'];
			} elseif ($handler == 'cfg_destination' && $post[$handler] == 'file') {
				list($file_path, $file_versions, $file_size, $file_size_spec) = $post['cfg_file_path'];
				$filename = str_replace('"', '', $file_path);
				$post['cfg_data'] = '"' . $filename . '"';
				if ($file_versions) $post['cfg_data'] .= ' versions ' . $file_versions;
				if (!empty($file_size) && $file_size > 0) $post['cfg_data'] .= ' size ' . $file_size . $file_size_spec;
			}
			if ($handler == 'cfg_destination') {
				$post['cfg_name'] = $post['cfg_destination'];
			} elseif (in_array($handler, array('print-category', 'print-severity', 'print-time')) && !sanitize($post['cfg_data'])) {
				continue;
			} else {
				$post['cfg_name'] = $handler;
				$post['cfg_data'] = $post[$handler];
			}
			
			foreach ($post as $key => $data) {
				if (!in_array($key, $exclude)) {
					$clean_data = sanitize($data);
					if ($i) $sql_fields .= $key . ', ';
					
					$sql_values .= "'$clean_data', ";
				}
			}
			$i = 0;
			$sql_values = rtrim($sql_values, ', ') . '), (';
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', (');
		
		$query = "$sql_insert $sql_fields VALUES $sql_values";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the channel because a database error occurred.'), 'sql');
		}
		
		$log_message = "Added logging channel:\nName: $channel_name\nDestination: {$post['cfg_destination']}";
		if ($post['cfg_destination'] == 'syslog') $log_message .= " {$post['cfg_syslog']}";
		if ($post['cfg_destination'] == 'file') {
			$log_message .= "\nFile: {$post['cfg_file_path'][0]}";
			if ($post['cfg_file_path'][1]) {
				$log_message .= "\nVersions: {$post['cfg_file_path'][1]}";
				if ($post['cfg_file_path'][2]) {
					$log_message .= "\nFile Size: " . $post['cfg_file_path'][2] . $post['cfg_file_path'][3];
				}
			}
		}
		$log_message .= "\nSeverity: {$post['severity']}\nPrint Category: {$post['print-category']}\nPrint Severity: {$post['print-severity']}\nPrint Time: {$post['print-time']}\nComment: {$post['cfg_comment']}";
		addLogEntry($log_message);
		return true;
	}
	
	
	/**
	 * Adds the new category
	 */
	function addCategory($post) {
		global $fmdb, $__FM_CONFIG;
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}config`";
		$sql_fields = '(';
		$sql_values = null;
		
		if (!isset($post['cfg_data'])) return __('No channel selected.');

		$post['account_id'] = $_SESSION['user']['account_id'];
		$post['cfg_isparent'] = 'yes';
		$post['temp_data'] = $post['cfg_data'];
		$post['cfg_data'] = $category_name = $post['cfg_name'];
		$post['cfg_name'] = $post['sub_type'];
		$post['cfg_comment'] = trim($post['cfg_comment']);
		
		$exclude = array('submit', 'action', 'cfg_id', 'sub_type', 'temp_data');
		
		/** Insert the category parent */
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$clean_data = sanitize($data);
				$sql_fields .= $key . ', ';
				$sql_values .= "'$clean_data', ";
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the category because a database error occurred.'), 'sql');
		}

		/** Insert category children */
		$post['cfg_isparent'] = 'no';
		$post['cfg_parent'] = $fmdb->insert_id;
		$post['cfg_data'] = null;
		$post['cfg_name'] = '';
		foreach ($post['temp_data'] as $channel) {
			$post['cfg_data'] .= "$channel;";
		}
		$post['cfg_data'] = rtrim($post['cfg_data'], ';');
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}config`";
		$sql_fields = '(';
		$sql_values = null;
		
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$clean_data = sanitize($data);
				$sql_fields .= $key . ', ';
				$sql_values .= "'$clean_data', ";
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the category because a database error occurred.'), 'sql');
		}
		
		addLogEntry("Added logging category:\nName: $category_name\nChannels: " . implode(', ', $post['temp_data']) . "\nComment: {$post['cfg_comment']}");
		return true;
	}

	/**
	 * Updates the selected logging type
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Ensure no empty inputs */
		if ($post['sub_type'] == 'channel') {
			if (empty($post['cfg_name'])) return __('No channel defined.');
			if ($post['cfg_destination'] == 'file') {
				if (empty($post['cfg_file_path'][0])) return __('No file path defined.');
			}
		}
		if ($post['sub_type'] == 'category' && !isset($post['cfg_data'])) return __('No channel defined.');

		$post['cfg_comment'] = trim($post['cfg_comment']);

		/** First delete all children since they will be replaced */
		$query = "SELECT cfg_id FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` WHERE `cfg_parent`={$post['cfg_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		if ($fmdb->num_rows) {
			$query = "DELETE FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` WHERE `cfg_parent`={$post['cfg_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
			$result = $fmdb->query($query);
			
			if ($fmdb->sql_errors) {
				return formatError(sprintf(__('Could not update the %s because a database error occurred.'), $post['sub_type']), 'sql');
			}
		}
		
		/** Update category parent */
		$post['temp_data'] = $post['cfg_data'];
		$post['cfg_data'] = $name = $post['cfg_name'];
		$post['cfg_name'] = $post['sub_type'];
		
		/** Ensure unique channel names */
		if ($post['sub_type'] == 'channel') {
			if (!$this->validateChannel($post)) return __('This channel already exists.');
		}
		
		$exclude = array('submit', 'action', 'cfg_id', 'sub_type', 'temp_data', 'cfg_destination', 'cfg_file_path', 'cfg_syslog', 'severity', 'print-category',
					'print-severity', 'print-time');

		$sql_edit = null;
		
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$clean_data = sanitize($data);
				$sql_edit .= $key . "='" . $clean_data . "', ";
			}
		}
		$sql = rtrim($sql_edit, ', ');
		
		/** Update the category */
		$old_name = getNameFromID($post['cfg_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'cfg_id', 'cfg_data');
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` SET $sql WHERE `cfg_id`={$post['cfg_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		/** Insert category children */
		$post['cfg_isparent'] = 'no';
		$post['cfg_parent'] = $post['cfg_id'];
		$post['cfg_data'] = null;
		$post['cfg_name'] = '';
		
		if ($post['sub_type'] == 'category') {
			foreach ($post['temp_data'] as $channel) {
				$post['cfg_data'] .= "$channel;";
			}
			$post['cfg_data'] = rtrim($post['cfg_data'], ';');
			
			$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}config`";
			$sql_fields = '(';
			$sql_values = null;
			
			foreach ($post as $key => $data) {
				if (!in_array($key, $exclude)) {
					$clean_data = sanitize($data);
					$sql_fields .= $key . ', ';
					$sql_values .= "'$clean_data', ";
				}
			}
			$sql_fields = rtrim($sql_fields, ', ') . ')';
			$sql_values = rtrim($sql_values, ', ');
			
			$query = "$sql_insert $sql_fields VALUES ($sql_values)";
			$result = $fmdb->query($query);
		
			if ($fmdb->sql_errors) {
				return formatError(sprintf(__('Could not update the %s because a database error occurred.'), $post['sub_type']), 'sql');
			}
			
			addLogEntry("Updated logging category '$old_name' to the following:\nName: $name\nChannels: " . implode(', ', $post['temp_data']) . "\nComment: {$post['cfg_comment']}");
		} else {
			/** Insert channel children */
			$include = array('cfg_destination', 'severity', 'print-category', 'print-severity', 'print-time');
			
			$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}config`";
			$sql_fields = '(';
			$sql_values = '(';
			
			$i = 1;
			foreach ($include as $handler) {
				$post['cfg_data'] = $post[$handler];
				/** Logic checking */
				if ($handler == 'cfg_destination' && $post[$handler] == 'syslog') {
					$post['cfg_data'] = $post['cfg_syslog'];
				} elseif ($handler == 'cfg_destination' && $post[$handler] == 'file') {
					@list($file_path, $file_versions, $file_size, $file_size_spec) = $post['cfg_file_path'];
					$filename = str_replace('"', '', $file_path);
					$post['cfg_data'] = '"' . $filename . '"';
					if ($file_versions) $post['cfg_data'] .= ' versions ' . $file_versions;
					if (!empty($file_size) && $file_size > 0) $post['cfg_data'] .= ' size ' . $file_size . $file_size_spec;
				}
				if ($handler == 'cfg_destination') {
					$post['cfg_name'] = $post['cfg_destination'];
				} elseif (in_array($handler, array('print-category', 'print-severity', 'print-time')) && !sanitize($post['cfg_data'])) {
					continue;
				} else {
					$post['cfg_name'] = $handler;
					$post['cfg_data'] = $post[$handler];
				}
				
				foreach ($post as $key => $data) {
					if (!in_array($key, $exclude)) {
						$clean_data = sanitize($data);
						if ($i) $sql_fields .= $key . ', ';
						
						$sql_values .= "'$clean_data', ";
					}
				}
				$i = 0;
				$sql_values = rtrim($sql_values, ', ') . '), (';
			}
			$sql_fields = rtrim($sql_fields, ', ') . ')';
			$sql_values = rtrim($sql_values, ', (');
			
			$query = "$sql_insert $sql_fields VALUES $sql_values";
			$result = $fmdb->query($query);
		
			if ($fmdb->sql_errors) {
				return formatError(sprintf(__('Could not update the %s because a database error occurred.'), $post['sub_type']), 'sql');
			}

			$log_message = "Updated logging channel '$old_name' to the following:\nName: $name\nDestination: {$post['cfg_destination']}";
			if ($post['cfg_destination'] == 'syslog') $log_message .= " {$post['cfg_syslog']}";
			if ($post['cfg_destination'] == 'file') {
				$log_message .= "\nFile: {$post['cfg_file_path'][0]}";
				if ($post['cfg_file_path'][1]) {
					$log_message .= "\nVersions: {$post['cfg_file_path'][1]}";
					if ($post['cfg_file_path'][2]) {
						$log_message .= "\nFile Size: " . $post['cfg_file_path'][2] . $post['cfg_file_path'][3];
					}
				}
			}
			$log_message .= "\nSeverity: {$post['severity']}\nPrint Category: {$post['print-category']}\nPrint Severity: {$post['print-severity']}\nPrint Time: {$post['print-time']}\nComment: {$post['cfg_comment']}";
			addLogEntry($log_message);
		}
		
		return true;
	}
	
	
	/**
	 * Deletes the selected logging channel/category
	 */
	function delete($id, $server_serial_no = 0, $type) {
		global $fmdb, $__FM_CONFIG;
		
		/** Check if channel is currently associated with category */
		if ($type == 'channel' && is_array($this->getAssocCategories($id))) {
			return sprintf(__('This %s could not be deleted because it is associated with one or more categories.'), $type);
		}
		
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'cfg_id', 'cfg_data');

		/** Delete associated children */
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` SET `cfg_status`='deleted' WHERE `cfg_parent`=$id";
		$fmdb->query($query);
		
		/** Delete item */
		if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', $id, 'cfg_', 'deleted', 'cfg_id') === false) {
			return formatError(sprintf(__('This %s could not be deleted because a database error occurred.'), $type), 'sql');
		} else {
			setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
			addLogEntry(sprintf(__("Logging %s '%s' was deleted."), $type, $tmp_name));
			return true;
		}
	}


	function displayRow($row, $channel_category) {
		global $__FM_CONFIG;
		
		$disabled_class = ($row->cfg_status == 'disabled') ? ' class="disabled"' : null;
		
		$edit_name = ($row->cfg_parent) ? '&nbsp;&nbsp;&nbsp;' : null;
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$edit_status = '<td id="row_actions">';
			$edit_status .= '<a class="edit_form_link" name="' . $channel_category . '" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a class="status_form_link" href="#" rel="';
			$edit_status .= ($row->cfg_status == 'active') ? 'disabled' : 'active';
			$edit_status .= '">';
			$edit_status .= ($row->cfg_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
			$edit_status .= '</a>';
			if ($channel_category == 'channel' && is_array($this->getAssocCategories($row->cfg_id))) {
				$edit_status .= null;
			} else {
				$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			}
			$edit_status .= '</td>';
		} else {
			$edit_status = null;
		}
		
		$edit_name .= $row->cfg_data;
		
		if ($channel_category == 'category') {
			$channels = null;
			$assoc_channels = $this->getAssocChannels($row->cfg_id);
			foreach ($assoc_channels as $channel) {
				if (is_numeric($channel)) {
					$channel = getNameFromID($channel, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'cfg_id', 'cfg_data');
				}
				$channels .= "$channel, ";
			}
			$channels = rtrim($channels, ', ');
			$channels_row = '<td>' . $channels . '</td>';
		} else $channels_row = null;
		
		$comments = nl2br($row->cfg_comment);

		echo <<<HTML
		<tr id="$row->cfg_id" name="$row->cfg_data"$disabled_class>
			<td>$edit_name</td>
			$channels_row
			<td>$comments</td>
			$edit_status
		</tr>
HTML;
	}

	/**
	 * Displays the form to add/edit logging types
	 */
	function printForm($data = '', $action = 'add', $type = 'channel') {
		global $__FM_CONFIG;
		
		$cfg_id = 0;
		$cfg_name = $cfg_root_dir = $cfg_zones_dir = $cfg_comment = null;
		$server_serial_no = (isset($_REQUEST['request_uri']['server_serial_no']) && (intval($_REQUEST['request_uri']['server_serial_no']) > 0 || $_REQUEST['request_uri']['server_serial_no'][0] == 'g')) ? sanitize($_REQUEST['request_uri']['server_serial_no']) : 0;
		$cfg_data = null;
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		if ($action == 'add') {
			$popup_title = $type == 'channel' ? __('Add Channel') : __('Add Category');
		} else {
			$popup_title = $type == 'channel' ? __('Edit Channel') : __('Edit Category');
		}
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		$return_form = <<<FORM
			<form name="manage" id="manage" method="post" action="?type=$type">
			$popup_header
				<input type="hidden" name="action" value="$action" />
				<input type="hidden" name="cfg_id" value="$cfg_id" />
				<input type="hidden" name="cfg_type" value="logging" />
				<input type="hidden" name="sub_type" value="$type" />
				<input type="hidden" name="server_serial_no" value="$server_serial_no" />
FORM;
		if ($type == 'channel') {
			$dest = $this->getChannel($cfg_id);
			$cfg_syslog = buildSelect('cfg_syslog', 'cfg_syslog', $__FM_CONFIG['logging']['options']['syslog'], $this->getChannel($cfg_id, 'syslog'));
			$cfg_destination = buildSelect('cfg_destination', 'cfg_destination', $__FM_CONFIG['logging']['options']['destinations'], $dest, 1);
			$cfg_severity = buildSelect('severity', 'severity', $__FM_CONFIG['logging']['options']['severity'], $this->getChannel($cfg_id, 'severity'));
			$cfg_print_category = buildSelect('print-category', 'print-category', $__FM_CONFIG['logging']['options']['print-category'], $this->getChannel($cfg_id, 'print-category'));
			$cfg_print_severity = buildSelect('print-severity', 'print-severity', $__FM_CONFIG['logging']['options']['print-severity'], $this->getChannel($cfg_id, 'print-severity'));
			$cfg_print_time = buildSelect('print-time', 'print-time', $__FM_CONFIG['logging']['options']['print-time'], $this->getChannel($cfg_id, 'print-time'));
			$raw_cfg_file_path = explode(' ', str_replace('"', '', $this->getChannel($cfg_id, 'file')));
			$cfg_file_path = $raw_cfg_file_path[0];
			$cfg_file_versions = @buildSelect('cfg_file_path[]', 'cfg_file_path[]', $__FM_CONFIG['logging']['options']['file_versions'], $raw_cfg_file_path[array_search('versions', $raw_cfg_file_path) + 1]);
			$cfg_file_size = (isset($raw_cfg_file_path[array_search('size', $raw_cfg_file_path) + 1])) ? substr($raw_cfg_file_path[array_search('size', $raw_cfg_file_path) + 1], 0, -1) : null;
			$cfg_file_size_spec = @buildSelect('cfg_file_path[]', 'cfg_file_path[]', $__FM_CONFIG['logging']['options']['file_sizes'], substr($raw_cfg_file_path[array_search('size', $raw_cfg_file_path) + 1], -1, 1));
			
			/** Show/hide divs */
			if ($dest == 'file' || !$dest) {
				$fileshow = 'block';
				$syslogshow = 'none';
			} elseif ($dest == 'syslog') {
				$fileshow = 'none';
				$syslogshow = 'block';
			} else {
				$fileshow = 'none';
				$syslogshow = 'none';
			}
	
			$return_form .= sprintf('<table class="form-table">
					<tr>
						<th width="33&#37;" scope="row"><label for="cfg_name">%s</label></th>
						<td width="67&#37;"><input name="cfg_name" id="cfg_name" type="text" value="%s" size="40" /></td>
					</tr>
					<tr>
						<th width="33&#37;" scope="row"><label for="cfg_destination">%s</label></th>
						<td width="67&#37;">
							%s
							<div id="destination_option" style="display: %s">
								<input type="text" name="cfg_file_path[]" value="%s" placeholder="/path/to/file" /><br />
								versions %s <input type="number" name="cfg_file_path[]" value="%s" style="width: 5em;" onkeydown="return validateNumber(event)" /> 
								%s
							</div>
							<div id="syslog_options" style="display: %s">%s</div></td>
					</tr>
					</span>
					<tr>
						<th width="33&#37;" scope="row"><label for="cfg_severity">%s</label></th>
						<td width="67&#37;">%s</td>
					</tr>
					<tr>
						<th width="33&#37;" scope="row"><label for="print-category">%s</label></th>
						<td width="67&#37;">%s</td>
					</tr>
					<tr>
						<th width="33&#37;" scope="row"><label for="print-severity">%s</label></th>
						<td width="67&#37;">%s</td>
					</tr>
					<tr>
						<th width="33&#37;" scope="row"><label for="print-time">%s</label></th>
						<td width="67&#37;">%s</td>
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
					allowClear: true,
					minimumResultsForSearch: 10
				});
			});
		</script>',
				__('Channel Name'), $cfg_data,
				__('Logging Destination'), $cfg_destination, $fileshow, $cfg_file_path,
					$cfg_file_versions, $cfg_file_size, $cfg_file_size_spec, $syslogshow, $cfg_syslog,
				__('Severity'), $cfg_severity,
				__('Print Category (optional)'), $cfg_print_category,
				__('Print Severity (optional)'), $cfg_print_severity,
				__('Print Time (optional)'), $cfg_print_time,
				_('Comment'), $cfg_comment,
				$popup_footer
			);
		} elseif ($type == 'category') {
			$cfg_name = buildSelect('cfg_name', 'cfg_name', $this->availableCategories($cfg_data), $cfg_data);
			$cfg_data = buildSelect('cfg_data', 'cfg_data', $this->availableChannels(), $this->getAssocChannels($cfg_id), 4, null, true);
	
			$return_form .= sprintf('<table class="form-table">
					<tr>
						<th width="33&#37;" scope="row"><label for="cfg_name">%s</label></th>
						<td width="67&#37;">%s</td>
					</tr>
					<tr>
						<th width="33&#37;" scope="row"><label for="cfg_data">%s</label></th>
						<td width="67&#37;">%s</td>
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
					allowClear: true,
					width: "235px",
					minimumResultsForSearch: 10
				});
			});
		</script>',
				__('Category'), $cfg_name,
				__('Channels'), $cfg_data,
				_('Comment'), $cfg_comment,
				$popup_footer
			);
		} else {
			$return_form = buildPopup('header', _('Error'));
			$return_form .= sprintf('<h3>%s</h3><p>%s</p>', __('Oops!'), __('Invalid request.'));
			$return_form .= buildPopup('footer', _('OK'), array('cancel'));
		}

		return $return_form;
	}
	
	
	function availableCategories($cfg_name = null) {
		global $fmdb, $__FM_CONFIG;
		
		/** Get previously used categories */
		$query = "SELECT cfg_id,cfg_name,cfg_data FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}config WHERE account_id='{$_SESSION['user']['account_id']}' AND cfg_status!='deleted' AND cfg_isparent='yes' AND cfg_name='category' AND cfg_type='logging' ORDER BY cfg_name,cfg_data ASC";
		$result = $fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$results = $fmdb->last_result;
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$previously_used[] = $results[$i]->cfg_data;
			}
		}
		
		/** Build available category list */
		foreach ($__FM_CONFIG['logging']['categories'] as $category) {
			if (!@in_array($category, $previously_used) || $cfg_name == $category) {
				$return[] = $category;
			}
		}
		
		return $return;
	}
	
	
	function availableChannels() {
		global $fmdb, $__FM_CONFIG;
		
		$i = 0;
		foreach ($__FM_CONFIG['logging']['channels']['reserved'] as $channel) {
			$return[$i][] = $channel;
			$return[$i][] = $channel;
			$i++;
		}
		
		$query = "SELECT cfg_id,cfg_name,cfg_data FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}config WHERE account_id='{$_SESSION['user']['account_id']}' AND cfg_status!='deleted' AND cfg_isparent='yes' AND cfg_name='channel' AND cfg_type='logging' ORDER BY cfg_name,cfg_data ASC";
		$result = $fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$results = $fmdb->last_result;
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$return[$i+4][] = $results[$i]->cfg_data;
				$return[$i+4][] = $results[$i]->cfg_id;
			}
		}
		
		return $return;
	}
	
	
	function getAssocChannels($cfg_id) {
		if (!$cfg_id) return null;
		global $fmdb, $__FM_CONFIG;
		
		$return = null;
		
		$query = "SELECT cfg_id,cfg_data FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}config WHERE account_id='{$_SESSION['user']['account_id']}' AND cfg_status!='deleted' AND cfg_parent='{$cfg_id}' ORDER BY cfg_id ASC";
		$result = $fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$results = $fmdb->last_result;
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				foreach (explode(';', $results[$i]->cfg_data) as $channel) {
					$return[] = $channel;
				}
			}
		}
		
		return $return;
	}
	
	
	function getAssocCategories($cfg_id) {
		global $fmdb, $__FM_CONFIG;
		
		$return = null;
		
		$query = "SELECT cfg_id,cfg_parent FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}config WHERE account_id='{$_SESSION['user']['account_id']}' AND cfg_status!='deleted' AND 
				(cfg_data={$cfg_id} OR cfg_data LIKE '{$cfg_id};%' OR cfg_data LIKE '%;{$cfg_id};%' OR cfg_data LIKE '%;{$cfg_id}') ORDER BY cfg_id ASC";
		$result = $fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$results = $fmdb->last_result;
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				foreach (explode(';', $results[$i]->cfg_parent) as $category) {
					$return[] = $category;
				}
			}
		}
		
		return $return;
	}
	
	
	function getChannel($cfg_id, $channel_opt = null) {
		global $fmdb, $__FM_CONFIG;
		
		$return = null;
		
		/** Determine what type of channel destination is used */
		if (!$channel_opt) {
			$dest_opt = null;
			foreach ($__FM_CONFIG['logging']['options']['destinations'] as $opt) {
				$dest_opt .= "'$opt',";
			}
			$dest_opt = rtrim($dest_opt, ',');
			$query = "SELECT cfg_id,cfg_name FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}config WHERE account_id='{$_SESSION['user']['account_id']}' AND cfg_status!='deleted' AND cfg_parent='{$cfg_id}' AND cfg_name IN ($dest_opt) ORDER BY cfg_id ASC";
			$result = $fmdb->get_results($query);
			if ($fmdb->num_rows) {
				$results = $fmdb->last_result;
				$return = $results[0]->cfg_name;
			}
		} else {
			/** Get the data from $channel_opt */
			$query = "SELECT cfg_id,cfg_data FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}config WHERE account_id='{$_SESSION['user']['account_id']}' AND cfg_status!='deleted' AND cfg_parent='{$cfg_id}' AND cfg_name='$channel_opt' ORDER BY cfg_id ASC";
			$result = $fmdb->get_results($query);
			if ($fmdb->num_rows) {
				$results = $fmdb->last_result;
				$return = $results[0]->cfg_data;
			}
		}
		
		return $return;
	}
	
	
	function validateChannel($post) {
		global $fmdb, $__FM_CONFIG;
		
		$query = "SELECT * FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}config WHERE account_id='{$_SESSION['user']['account_id']}' AND cfg_status!='deleted' AND cfg_type='logging' AND cfg_name='{$post['cfg_name']}' AND cfg_data='{$post['cfg_data']}' AND cfg_id!='{$post['cfg_id']}'";
		$fmdb->get_results($query);
		if ($fmdb->num_rows) return false;
		
		return true;
	}

}

if (!isset($fm_module_logging))
	$fm_module_logging = new fm_module_logging();

?>
