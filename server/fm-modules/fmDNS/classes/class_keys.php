<?php

class fm_dns_keys {
	
	/**
	 * Displays the key list
	 */
	function rows($result) {
		global $fmdb;
		
		echo '			<table class="display_results" id="table_edits" name="keys">' . "\n";
		if (!$result) {
			echo '<p id="noresult">There are no keys.</p>';
		} else {
			?>
				<thead>
					<tr>
						<th>Key</th>
						<th>Algorithm</th>
						<th>Secret</th>
						<th>View</th>
						<th width="110" style="text-align: center;">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php
					$num_rows = $fmdb->num_rows;
					$results = $fmdb->last_result;
					for ($x=0; $x<$num_rows; $x++) {
						$this->displayRow($results[$x]);
					}
					?>
				</tbody>
			<?php
		}
		echo '			</table>' . "\n";
	}

	/**
	 * Adds the new key
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG;
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_name');
		if ($field_length !== false && strlen($post['key_name']) > $field_length) return 'Key name is too long (maximum ' . $field_length . ' characters).';
		
		/** Does the key already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', sanitize($post['key_name']), 'key_', 'key_name');
		if ($fmdb->num_rows) return 'This key already exists.';
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}keys`";
		$sql_fields = '(';
		$sql_values = null;
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		
		$exclude = array('submit', 'action', 'key_id');

		foreach ($post as $key => $data) {
			$clean_data = sanitize($data);
			if (($key == 'key_name' || $key == 'key_secret') && empty($clean_data)) return 'No key defined.';
			if (!in_array($key, $exclude)) {
				$sql_fields .= $key . ',';
				$sql_values .= "'$clean_data',";
			}
		}
		$sql_fields = rtrim($sql_fields, ',') . ')';
		$sql_values = rtrim($sql_values, ',');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if (!$result) return 'Could not add the key because a database error occurred.';

		addLogEntry("Added key '{$post['key_name']}'.");
		return true;
	}

	/**
	 * Updates the selected key
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		if (empty($post['key_name']) || empty($post['key_secret'])) return 'No key defined.';

		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_name');
		if ($field_length !== false && strlen($post['key_name']) > $field_length) return 'Key name is too long (maximum ' . $field_length . ' characters).';
		
		/** Does the key already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', sanitize($post['key_name']), 'key_', 'key_name');
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			if ($result[0]->key_id != $post['key_id']) return 'This key already exists.';
		}
		
		$exclude = array('submit', 'action', 'key_id');

		$sql_edit = null;
		
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . sanitize($data) . "',";
			}
		}
		$sql = rtrim($sql_edit, ',');
		
		// Update the key
		$old_name = getNameFromID($post['key_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_', 'key_id', 'key_name');
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}keys` SET $sql WHERE `key_id`={$post['key_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		if (!$result) return 'Could not update the key because a database error occurred.';

		$view_name = $post['key_view'] ? getNameFromID($post['key_view'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name') : 'All Views';
		addLogEntry("Updated key '$old_name' to name: '{$post['key_name']}'; algorithm: {$post['key_algorithm']}; secret: '{$post['key_secret']}'; view: $view_name.");
		return true;
	}
	
	
	/**
	 * Deletes the selected key
	 */
	function delete($id, $server_serial_no = 0) {
		global $fmdb, $__FM_CONFIG;
		
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_', 'key_id', 'key_name');
		if (!updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', $id, 'key_', 'deleted', 'key_id')) {
			return 'This key could not be deleted because a database error occurred.';
		} else {
			setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
			addLogEntry("Deleted key '$tmp_name'.");
			return true;
		}
	}


	function displayRow($row) {
		global $__FM_CONFIG, $allowed_to_manage_servers;
		
		if ($allowed_to_manage_servers) {
			$edit_status = '<td id="edit_delete_img">';
			$edit_status .= '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a href="' . $GLOBALS['basename'] . '?action=edit&id=' . $row->key_id . '&status=';
			$edit_status .= ($row->key_status == 'active') ? 'disabled' : 'active';
			$edit_status .= '">';
			$edit_status .= ($row->key_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
			$edit_status .= '</a>';
			$edit_status .= '<a href="' . $GLOBALS['basename'] . '?action=delete&id=' . $row->key_id . '" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			$edit_status .= '</td>';
		} else {
			$edit_status = '<td style="text-align: center;">N/A</td>';
		}
		
		$edit_name = $row->key_name;
		$key_view = ($row->key_view) ? getNameFromID($row->key_view, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name') : 'none';
		
		echo <<<HTML
		<tr id="$row->key_id">
			<td>$edit_name</td>
			<td>$row->key_algorithm</td>
			<td>$row->key_secret</td>
			<td>$key_view</td>
			$edit_status
		</tr>
HTML;
	}

	/**
	 * Displays the form to add new key
	 */
	function printForm($data = '', $action = 'add') {
		global $__FM_CONFIG, $fm_dns_zones;
		
		include_once(ABSPATH . 'fm-modules/fmDNS/classes/class_zones.php');
		
		$key_id = 0;
		$key_name = $key_root_dir = $key_zones_dir = '';
		$ucaction = ucfirst($action);
		$key_algorithm = $key_view = $key_secret = null;
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}

		$key_algorithm = buildSelect('key_algorithm', 'key_algorithm', enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_algorithm'), $key_algorithm, 1);
		$key_view = buildSelect('key_view', 'key_view', $fm_dns_zones->availableViews(), $key_view);
		
		$return_form = <<<FORM
		<form name="manage" id="manage" method="post" action="config-keys">
			<input type="hidden" name="action" value="$action" />
			<input type="hidden" name="key_id" value="$key_id" />
			<table class="form-table">
				<tr>
					<th width="33%" scope="row"><label for="key_name">Key Name</label></th>
					<td width="67%"><input name="key_name" id="key_name" type="text" value="$key_name" size="40" /></td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="key_view">View</label></th>
					<td width="67%">$key_view</td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="key_algorithm">Algorithm</label></th>
					<td width="67%">$key_algorithm</td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="key_secret">Secret</label></th>
					<td width="67%"><input name="key_secret" id="key_secret" type="text" value="$key_secret" size="40" /></td>
				</tr>
			</table>
			<input type="submit" name="submit" value="$ucaction Key" class="button" />
			<input value="Cancel" class="button cancel" id="cancel_button" />
		</form>
FORM;

		return $return_form;
	}
	
}

if (!isset($fm_dns_keys))
	$fm_dns_keys = new fm_dns_keys();

?>
