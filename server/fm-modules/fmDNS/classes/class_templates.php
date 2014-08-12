<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2014 The facileManager Team                               |
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

class fm_module_templates {
	
	/**
	 * Displays the key list
	 */
	function rows($result, $prefix) {
		global $fmdb;
		
		if (!$result) {
			echo '<p id="table_edits" class="noresult" name="templates">There are no templates.</p>';
		} else {
			$num_rows = $fmdb->num_rows;
			$results = $fmdb->last_result;
			
			$table_info = array(
							'class' => 'display_results sortable',
							'id' => 'table_edits',
							'name' => $prefix
						);

			include(ABSPATH . 'fm-modules/fmDNS/classes/class_records.php');
			$title_array = $fm_dns_records->getHeader(strtoupper($prefix));
			if (currentUserCan('manage_servers', $_SESSION['module'])) $title_array[] = array('title' => 'Actions', 'class' => 'header-actions header-nosort');

			echo displayTableHeader($table_info, $title_array);
			
			for ($x=0; $x<$num_rows; $x++) {
				$this->displayRow($results[$x], $prefix);
			}
			
			echo "</tbody>\n</table>\n";
		}
	}
	
	function displayRow($row, $prefix) {
		global $__FM_CONFIG, $fmdb;
		
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$edit_status = '<td id="edit_delete_img">';
			$edit_status .= '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$show_delete = true;
			
			/** Cannot delete templates in use */
			if ($prefix == 'soa') {
				basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $row->soa_id, 'domain_', 'soa_id');
				if ($fmdb->num_rows) {
					$show_delete = false;
				}
			}
			
			$edit_status .= $show_delete ? '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>' : null;
			$edit_status .= '</td>';
		} else {
			$edit_status = null;
		}
		
		$field_name = $prefix . '_name';
		$edit_name = $row->$field_name;
		
		$field_id = $prefix . '_id';
		echo <<<HTML
		<tr id="{$row->$field_id}">
			<td>$edit_name</td>
HTML;
		if ($prefix == 'soa') {
			$row = get_object_vars($row);
			
			foreach ($row as $key => $val) {
				if (in_array($key, array('soa_id', 'account_id', 'soa_template', 'soa_name', 'soa_append', 'soa_status'))) continue;
				
				echo '<td>' . $val;
				if (in_array($key, array('soa_master_server', 'soa_email_address')) && $row['soa_append'] == 'yes') {
					echo '<span class="grey">.mydomain.tld</span>';
				}
				echo '</td>';
			}
		}
		
		echo $edit_status . "</tr>\n";
	}
	
	function printForm($data = '', $action = 'add') {
		include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_records.php');
		
		$force_action = $action == 'add' ? 'create' : 'update';
		
		$ucaction = ucfirst($action);
		$form = '<form method="POST" action="zone-records-validate.php">
<input type="hidden" name="domain_id" value="0" />
<input type="hidden" name="record_type" value="SOA" />' . "\n";
		$form .= buildPopup('header', $ucaction . ' Template');

		$form .= $fm_dns_records->buildSOA($data, array('template_name'), $force_action);
		
		$form .= buildPopup('footer');
		$form .= '</form>';
		
		echo $form;
	}
	
	/**
	 * Deletes the selected template
	 */
	function delete($id, $server_serial_no = 0, $prefix) {
		global $fmdb, $__FM_CONFIG;
		
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . $prefix, $prefix . '_', $prefix . '_id', $prefix . '_name');
		if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . $prefix, $id, $prefix . '_', 'deleted', $prefix . '_id') === false) {
			return 'This template could not be deleted because a database error occurred.';
		} else {
			addLogEntry("Deleted $prefix template '$tmp_name'.");
			return true;
		}
	}
}

if (!isset($fm_module_templates))
	$fm_module_templates = new fm_module_templates();

?>