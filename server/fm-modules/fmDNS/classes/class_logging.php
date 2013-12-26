<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2013 The facileManager Team                               |
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
	function rows($result, $channel_category) {
		global $fmdb, $__FM_CONFIG;
		
		echo '			<table class="display_results" id="table_edits" name="logging">' . "\n";
		if (!$result) {
			echo '<p id="noresult">There are no ' . $__FM_CONFIG['logging']['avail_types'][$channel_category] . ' defined.</p>';
		} else {
			?>
				<thead>
					<tr>
						<th>Name</th>
						<?php if ($channel_category == 'category') echo '<th>Channels</th>'; ?>
						<th>Comment</th>
						<th width="110" style="text-align: center;">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php
					$num_rows = $fmdb->num_rows;
					$results = $fmdb->last_result;
					for ($x=0; $x<$num_rows; $x++) {
						$this->displayRow($results[$x], $channel_category);
					}
					?>
				</tbody>
			<?php
		}
		echo '			</table>' . "\n";
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
		
		if (empty($channel_name)) return 'No channel name defined.';
		
		/** Ensure unique channel names */
		if (!$this->validateChannel($post)) return 'This channel already exists.';
		
		if ($post['cfg_destination'] == 'file') {
			if (empty($post['cfg_file_path'][0])) return 'No file path defined.';
		}
		$exclude = array('submit', 'action', 'cfg_id', 'sub_type', 'temp_data', 'cfg_destination',
					'cfg_file_path', 'cfg_syslog', 'severity', 'print-category', 'print-severity',
					'print-time');
		
		/** Insert the category parent */
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$clean_data = sanitize($data, '_');
				$sql_fields .= $key . ',';
				$sql_values .= "'$clean_data',";
			}
		}
		$sql_fields = rtrim($sql_fields, ',') . ')';
		$sql_values = rtrim($sql_values, ',');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if (!$result) return 'Could not add the channel because a database error occurred.';

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
					if ($i) $sql_fields .= $key . ',';
					
					$sql_values .= "'$clean_data',";
				}
			}
			$i = 0;
			$sql_values = rtrim($sql_values, ',') . '), (';
		}
		$sql_fields = rtrim($sql_fields, ',') . ')';
		$sql_values = rtrim($sql_values, ', (');
		
		$query = "$sql_insert $sql_fields VALUES $sql_values";
		$result = $fmdb->query($query);
		
		if (!$fmdb->result) return 'Could not add the channel because a database error occurred.';
		
		addLogEntry("Added logging channel '$channel_name'.");
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
		
		if (!isset($post['cfg_data'])) return 'No channel selected.';

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
				$sql_fields .= $key . ',';
				$sql_values .= "'$clean_data',";
			}
		}
		$sql_fields = rtrim($sql_fields, ',') . ')';
		$sql_values = rtrim($sql_values, ',');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if (!$result) return 'Could not add the category because a database error occurred.';

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
				$sql_fields .= $key . ',';
				$sql_values .= "'$clean_data',";
			}
		}
		$sql_fields = rtrim($sql_fields, ',') . ')';
		$sql_values = rtrim($sql_values, ',');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if (!$fmdb->result) return 'Could not add the category because a database error occurred.';
		
		addLogEntry("Added logging category '$category_name'.");
		return true;
	}

	/**
	 * Updates the selected logging type
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Ensure no empty inputs */
		if ($post['sub_type'] == 'channel') {
			if (empty($post['cfg_name'])) return 'No channel defined.';
			if ($post['cfg_destination'] == 'file') {
				if (empty($post['cfg_file_path'][0])) return 'No file path defined.';
			}
		}
		if ($post['sub_type'] == 'category' && !isset($post['cfg_data'])) return 'No channel defined.';

		$post['cfg_comment'] = trim($post['cfg_comment']);

		/** First delete all children since they will be replaced */
		$query = "SELECT cfg_id FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` WHERE `cfg_parent`={$post['cfg_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		if ($fmdb->num_rows) {
			$query = "DELETE FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` WHERE `cfg_parent`={$post['cfg_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
			$result = $fmdb->query($query);
			
			if (!$result) return 'Could not update the ' . $post['sub_type'] . ' because a database error occurred.';
		}
		
		/** Update category parent */
		$post['temp_data'] = $post['cfg_data'];
		$post['cfg_data'] = $post['cfg_name'];
		$post['cfg_name'] = $post['sub_type'];
		
		/** Ensure unique channel names */
		if ($post['sub_type'] == 'channel') {
			if (!$this->validateChannel($post)) return 'This channel already exists.';
		}
		
		$exclude = array('submit', 'action', 'cfg_id', 'sub_type', 'temp_data', 'cfg_destination', 'cfg_file_path', 'cfg_syslog', 'severity', 'print-category',
					'print-severity', 'print-time');

		$sql_edit = null;
		
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$clean_data = sanitize($data);
				$sql_edit .= $key . "='" . $clean_data . "',";
			}
		}
		$sql = rtrim($sql_edit, ',');
		
		/** Update the category */
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
					$sql_fields .= $key . ',';
					$sql_values .= "'$clean_data',";
				}
			}
			$sql_fields = rtrim($sql_fields, ',') . ')';
			$sql_values = rtrim($sql_values, ',');
			
			$query = "$sql_insert $sql_fields VALUES ($sql_values)";
			$result = $fmdb->query($query);
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
						if ($i) $sql_fields .= $key . ',';
						
						$sql_values .= "'$clean_data',";
					}
				}
				$i = 0;
				$sql_values = rtrim($sql_values, ',') . '), (';
			}
			$sql_fields = rtrim($sql_fields, ',') . ')';
			$sql_values = rtrim($sql_values, ', (');
			
			$query = "$sql_insert $sql_fields VALUES $sql_values";
			$result = $fmdb->query($query);
		}
		
		if (!$fmdb->result) return 'Could not update the ' . $post['sub_type'] . ' because a database error occurred.';
		
		return true;
	}
	
	
	/**
	 * Deletes the selected logging channel/category
	 */
	function delete($id, $server_serial_no = 0, $type) {
		global $fmdb, $__FM_CONFIG;
		
		/** Check if channel is currently associated with category */
		if ($type == 'channel' && is_array($this->getAssocCategories($id))) {
			return 'This ' . $type . ' could not be deleted because it is associated with one or more categories.';
		}
		
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_', 'cfg_id', 'cfg_data');

		/** Delete associated children */
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}config` SET `cfg_status`='deleted' WHERE `cfg_parent`=$id";
		$fmdb->query($query);
		
		/** Delete item */
		if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', $id, 'cfg_', 'deleted', 'cfg_id') === false) {
			return 'This ' . $type . ' could not be deleted because a database error occurred.';
		} else {
			setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
			addLogEntry("Deleted logging $type '$tmp_name'.");
			return true;
		}
	}


	function displayRow($row, $channel_category) {
		global $__FM_CONFIG, $allowed_to_manage_servers;
		
		$disabled_class = ($row->cfg_status == 'disabled') ? ' class="disabled"' : null;
		
		$edit_name = ($row->cfg_parent) ? '&nbsp;&nbsp;&nbsp;' : null;
		if ($allowed_to_manage_servers) {
			$edit_uri = (strpos($_SERVER['REQUEST_URI'], '?')) ? $_SERVER['REQUEST_URI'] . '&' : $_SERVER['REQUEST_URI'] . '?';
			$edit_status = '<td id="edit_delete_img">';
			$edit_status .= '<a class="edit_form_link" name="' . $channel_category . '" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a href="' . $edit_uri . 'action=edit&id=' . $row->cfg_id . '&status=';
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
			$edit_status = '<td style="text-align: center;">N/A</td>';
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
		
		echo <<<HTML
		<tr id="$row->cfg_id"$disabled_class>
			<td>$edit_name</td>
			$channels_row
			<td>$row->cfg_comment</td>
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
		$ucaction = ucfirst($action);
		$server_serial_no = (isset($_REQUEST['server_serial_no']) && $_REQUEST['server_serial_no'] > 0) ? sanitize($_REQUEST['server_serial_no']) : 0;
		$cfg_data = null;
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		$return_form = <<<FORM
			<form name="manage" id="manage" method="post" action="config-logging.php?type=$type">
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
	
			$return_form .= <<<FORM
				<table class="form-table">
					<tr>
						<th width="33%" scope="row"><label for="cfg_name">Channel Name</label></th>
						<td width="67%"><input name="cfg_name" id="cfg_name" type="text" value="$cfg_data" size="40" /></td>
					</tr>
					<tr>
						<th width="33%" scope="row"><label for="cfg_destination">Logging Destination</label></th>
						<td width="67%">
							$cfg_destination
							<div id="destination_option" style="display: $fileshow">
								<input type="text" name="cfg_file_path[]" value="$cfg_file_path" placeholder="/path/to/file" /><br />
								versions $cfg_file_versions <input type="number" name="cfg_file_path[]" value="$cfg_file_size" style="width: 5em;" onkeydown="return validateNumber(event)" /> 
								$cfg_file_size_spec
							</div>
							<div id="syslog_options" style="display: $syslogshow">$cfg_syslog</div></td>
					</tr>
					</span>
					<tr>
						<th width="33%" scope="row"><label for="cfg_severity">Severity</label></th>
						<td width="67%">$cfg_severity</td>
					</tr>
					<tr>
						<th width="33%" scope="row"><label for="print-category">Print Category (optional)</label></th>
						<td width="67%">$cfg_print_category</td>
					</tr>
					<tr>
						<th width="33%" scope="row"><label for="print-severity">Print Severity (optional)</label></th>
						<td width="67%">$cfg_print_severity</td>
					</tr>
					<tr>
						<th width="33%" scope="row"><label for="print-time">Print Time (optional)</label></th>
						<td width="67%">$cfg_print_time</td>
					</tr>
					<tr>
						<th width="33%" scope="row"><label for="cfg_comment">Comment</label></th>
						<td width="67%"><textarea id="cfg_comment" name="cfg_comment" rows="4" cols="30">$cfg_comment</textarea></td>
					</tr>
				</table>
				<input type="submit" name="submit" value="$ucaction Channel" class="button" />
				<input value="Cancel" class="button cancel" id="cancel_button" />
			</form>
FORM;
		} elseif ($type == 'category') {
			$cfg_name = buildSelect('cfg_name', 'cfg_name', $this->availableCategories($cfg_data), $cfg_data);
			$cfg_data = buildSelect('cfg_data', 'cfg_data', $this->availableChannels(), $this->getAssocChannels($cfg_id), 4, null, true);
	
			$return_form .= <<<FORM
				<table class="form-table">
					<tr>
						<th width="33%" scope="row"><label for="cfg_name">Category</label></th>
						<td width="67%">$cfg_name</td>
					</tr>
					<tr>
						<th width="33%" scope="row"><label for="cfg_data">Channels</label></th>
						<td width="67%">$cfg_data</td>
					</tr>
					<tr>
						<th width="33%" scope="row"><label for="cfg_comment">Comment</label></th>
						<td width="67%"><textarea id="cfg_comment" name="cfg_comment" rows="4" cols="30">$cfg_comment</textarea></td>
					</tr>
				</table>
				<input type="submit" name="submit" value="$ucaction Category" class="button" />
				<input value="Cancel" class="button cancel" id="cancel_button" />
			</form>
FORM;
		} else {
			$return_form = <<<FORM
			<p>Invalid request.</p>
			<input type="submit" value="OK" class="button" id="cancel_button" />
FORM;
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
