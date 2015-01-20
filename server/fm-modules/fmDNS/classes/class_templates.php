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
	 * Displays the template list
	 */
	function rows($result, $template_type) {
		global $fmdb;
		
		if (!$result) {
			if ($template_type == 'domain') {
				echo '<p id="table_edits" class="noresult" name="' . $template_type . '">Zone templates are not yet supported.</p>';
			} else {
				printf('<p id="table_edits" class="noresult" name="%s">%s</p>', $template_type, _('There are no templates.'));
			}
		} else {
			$num_rows = $fmdb->num_rows;
			$results = $fmdb->last_result;
			
			$table_info = array(
							'class' => 'display_results sortable',
							'id' => 'table_edits',
							'name' => $template_type
						);

			global $fm_dns_records;
			if (!isset($fm_dns_records)) include(ABSPATH . 'fm-modules/fmDNS/classes/class_records.php');
			$title_array = array_merge(array(array('title' => '', 'class' => 'header-nosort')), $fm_dns_records->getHeader(strtoupper($template_type)));
			if (currentUserCan('manage_servers', $_SESSION['module'])) $title_array[] = array('title' => _('Actions'), 'class' => 'header-actions header-nosort');

			echo displayTableHeader($table_info, $title_array);
			
			for ($x=0; $x<$num_rows; $x++) {
				$this->displayRow($results[$x], $template_type);
			}
			
			echo "</tbody>\n</table>\n";
		}
	}
	
	function displayRow($row, $prefix) {
		global $__FM_CONFIG, $fmdb, $fm_dns_zones;
		
		if (currentUserCan('manage_zones', $_SESSION['module'])) {
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
		if ($prefix == 'domain') {
			if (!getSOACount($row->domain_id) && $row->domain_type == 'master' && currentUserCan('manage_zones', $_SESSION['module'])) $type = 'SOA';
			elseif (!getNSCount($row->domain_id) && $row->domain_type == 'master' && currentUserCan('manage_zones', $_SESSION['module'])) $type = 'NS';
			else {
				$type = ($row->domain_mapping == 'forward') ? 'A' : 'PTR';
			}
			$edit_name = ($row->domain_type == 'master') ? "<a href=\"zone-records.php?map={$row->domain_mapping}&domain_id={$row->domain_id}&record_type=$type\" title=\"" . _('Edit zone records') . '">' . displayFriendlyDomainName($row->$field_name) . "</a>" : displayFriendlyDomainName($row->$field_name);
		} else {
			$edit_name = $row->$field_name;
		}
		$field_name = $prefix . '_default';
		$star = $row->$field_name == 'yes' ? str_replace(_('Super Admin'), _('Default Template'), $__FM_CONFIG['icons']['star']) : null;
		
		$field_id = $prefix . '_id';
		echo <<<HTML
		<tr id="{$row->$field_id}">
			<td>$star</td>
			<td>$edit_name</td>
HTML;
		$row = get_object_vars($row);
		
		$excluded_fields = array($prefix . '_id', 'account_id', $prefix . '_template', $prefix . '_default',
				$prefix . '_name', $prefix . '_status', $prefix . '_template_id');
		
		if ($prefix == 'soa') {
			$excluded_fields = array_merge($excluded_fields, array($prefix . '_append'));
		}
		if ($prefix == 'domain') {
			$excluded_fields = array_merge($excluded_fields, array('soa_serial_no', 'soa_id', $prefix . '_clone_domain_id', $prefix . '_reload', $prefix . '_clone_dname'));
		}

		foreach ($row as $key => $val) {
			if (in_array($key, $excluded_fields)) continue;

			if ($prefix == 'domain') {
				/** Friendly servers and view names */
				if (in_array($key, array($prefix . '_view', $prefix . '_name_servers'))) {
					if (!isset($fm_dns_zones)) {
						include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_zones.php');
					}
					if ($key == $prefix . '_view') {
						$val = $fm_dns_zones->IDs2Name($val, 'view');
					} elseif ($key == $prefix . '_name_servers') {
						$val = $fm_dns_zones->IDs2Name($val, 'server');
					}
				}
			}
			echo '<td>' . $val;
			if ($prefix == 'soa') {
				if (in_array($key, array('soa_master_server', 'soa_email_address')) && $row['soa_append'] == 'yes') {
					echo '<span class="grey">.mydomain.tld</span>';
				}
			}
			echo '</td>';
		}
		
		echo $edit_status . "</tr>\n";
	}
	
	function printForm($data = '', $action = 'add', $template_type) {
		$popup_header = buildPopup('header', ucfirst($action) . ' Template');
		$force_action = $action == 'add' ? 'create' : 'update';

		switch ($template_type) {
			case 'soa':
				global $fm_dns_records;
				if (!isset($fm_dns_records)) include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_records.php');

				$form = '<form method="POST" action="zone-records-validate.php">
					<input type="hidden" name="domain_id" value="0" />
					<input type="hidden" name="record_type" value="SOA" />' . "\n";
				$form .= $popup_header;

				$form .= $fm_dns_records->buildSOA($data, array('template_name'), $force_action);
				break;
			case 'domain':
				global $fm_dns_zones;
				$form = '<form name="manage" id="manage" method="post" action="">' . $popup_header;
				$form .= $fm_dns_zones->printForm($data, $force_action, 'forward', array('template_name'));
				break;
		}
		
		$form .= buildPopup('footer');
		$form .= '</form>';
		
		echo $form;
	}
	
	/**
	 * Deletes the selected template
	 */
	function delete($id, $table, $prefix) {
		global $fmdb, $__FM_CONFIG;
		
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . $table, $prefix . '_', $prefix . '_id', $prefix . '_name');
		if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . $table, $id, $prefix . '_', 'deleted', $prefix . '_id') === false) {
			return _('This template could not be deleted because a database error occurred.');
		} else {
			addLogEntry("Deleted $prefix template '$tmp_name'.");
			return true;
		}
	}
}

if (!isset($fm_module_templates))
	$fm_module_templates = new fm_module_templates();

?>