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
 | fmFirewall: Easily manage one or more software firewalls                |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmfirewall/                        |
 +-------------------------------------------------------------------------+
*/

class fm_module_templates {
	
	/**
	 * Displays the list
	 *
	 * @since 2.0
	 * @package facileManager
	 * @subpackage fmFirewall
	 *
	 * @param array $result Record rows of all items
	 * @param string $type
	 * @param integer $page Page number of items to display
	 * @param integer $total_pages Total number of pages of results
	 * @return null
	 */
	function rows($result, $type, $page, $total_pages) {
		global $fmdb;
		
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;

		$table_info = array(
						'class' => 'display_results sortable',
						'id' => 'table_edits',
						'name' => $type
					);

		if (currentUserCan('manage_policies', $_SESSION['module'])) {
			if ($num_rows > 1) $table_info['class'] .= ' grab';
			
			$bulk_actions_list = array(_('Enable'), _('Disable'), _('Delete'));
		}

		$fmdb->num_rows = $num_rows;

		$start = $_SESSION['user']['record_count'] * ($page - 1);
		echo displayPagination($page, $total_pages, @buildBulkActionMenu($bulk_actions_list));
		echo '<div class="overflow-container">';

		if (is_array($bulk_actions_list)) {
			$title_array[] = array(
								'title' => '<input type="checkbox" class="tickall" onClick="toggle(this, \'bulk_list[]\')" />',
								'class' => 'header-tiny header-nosort'
							);
			if ($num_rows > 1) $title_array[] = array('class' => 'header-tiny header-nosort');
		}
		$title_array = array_merge((array) $title_array, array(__('Name'), __('Stack'), __('Targets'),
								array('title' => _('Comment'), 'style' => 'width: 20%;')));
		if (is_array($bulk_actions_list)) $title_array[] = array('title' => _('Actions'), 'class' => 'header-actions');

		echo '<div class="table-results-container">';
		echo displayTableHeader($table_info, $title_array);

		if ($result) {
			$y = 0;
			for ($x=$start; $x<$num_rows; $x++) {
				if ($y == $_SESSION['user']['record_count']) break;
				$this->displayRow($results[$x], $num_rows);
				$y++;
			}
		}

		echo "</tbody>\n</table>\n";
		if (!$result) {
			printf('<p id="table_edits" class="noresult" name="%s">%s</p>', $type, __('There are no templates.'));
		}
	}
	
	/**
	 * Adds the policy template
	 *
	 * @since 2.0
	 * @package facileManager
	 * @subpackage fmfmFirewall
	 *
	 * @param array $post $_POST data
	 * @return boolean|string
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG, $global_form_field_excludes;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}policies`";
		$sql_fields = '(';
		$sql_values = '';
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		
		$exclude = array_merge($global_form_field_excludes, array('policy_id'));

		$log_message = "Added a policy template for with the following details:\n";

		/** Format policy_targets */
		$log_message_servers = $policy_servers = null;
		foreach ($post['policy_targets'] as $val) {
			if (!$val) {
				$policy_servers = 0;
				$log_message_servers = __('All Servers');
				break;
			}
			if ($val == -1) {
				$policy_servers = -1;
				$log_message_servers = __('None');
				break;
			}
			$policy_servers .= $val . ';';
			$server_name = getServerName($val);
			$log_message_servers .= $val ? "$server_name; " : null;
		}
		$post['policy_targets'] = rtrim($policy_servers, ';');
		if (!$post['policy_targets']) $post['policy_targets'] = 0;

		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_fields .= $key . ', ';
				$sql_values .= "'$data', ";
				if ($key == 'policy_targets' || $data && !in_array($key, array('account_id', 'server_serial_no', 'policy_type'))) {
					if ($key == 'policy_targets') {
						$key = __('Firewalls');
						$data = $log_message_servers;
					}
					if ($key == 'policy_template_stack') {
						$data = $this->IDs2Name($data, 'policy');
					}
					$log_message .= formatLogKeyData('policy_', $key, $data);
				}
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the template because a database error occurred.'), 'sql');
		}

//		setBuildUpdateConfigFlag($post['server_serial_no'], 'yes', 'build');
		
		addLogEntry($log_message);
		return true;
	}

	/**
	 * Updates the policy template
	 *
	 * @since 2.0
	 * @package facileManager
	 * @subpackage fmfmFirewall
	 *
	 * @param array $post $_POST data
	 * @return boolean|string
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG, $global_form_field_excludes;
		
		/** Update sort order */
		if ($post['action'] == 'update_sort') {
			/** Make new order in array */
			$new_sort_order = explode(';', rtrim($post['sort_order'], ';'));
			
			/** Get policy listing for server */
			basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_order_id', 'policy_', 'AND server_serial_no=0 AND policy_type="template"');
			$count = $fmdb->num_rows;
			$policy_result = $fmdb->last_result;
			for ($i=0; $i<$count; $i++) {
				$order_id = array_search($policy_result[$i]->policy_id, $new_sort_order);
				if ($order_id === false) return __('The sort order could not be updated due to an invalid request.');
				$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}policies` SET `policy_order_id`=$order_id WHERE `policy_id`={$policy_result[$i]->policy_id} AND `server_serial_no`=0 AND policy_type='template' AND `account_id`='{$_SESSION['user']['account_id']}'";
				$fmdb->query($query);
				if ($fmdb->sql_errors) {
					return formatError(__('Could not update the template order because a database error occurred.'), 'sql');
				}
			}
			
//			setBuildUpdateConfigFlag($post['server_serial_no'], 'yes', 'build');
		
			addLogEntry(__('Updated policy template order.'));
			return true;
		}
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		$exclude = array_merge($global_form_field_excludes, array('policy_id'));

		$sql_edit = '';
		
		$log_message = "Updated a policy template for with the following details:\n";

		/** Format policy_targets */
		$log_message_servers = $policy_servers = null;
		foreach ($post['policy_targets'] as $val) {
			if (!$val) {
				$policy_servers = 0;
				$log_message_servers = __('All Servers');
				break;
			}
			if ($val == -1) {
				$policy_servers = -1;
				$log_message_servers = __('None');
				break;
			}
			$policy_servers .= $val . ';';
			$server_name = getServerName($val);
			$log_message_servers .= $val ? "$server_name; " : null;
		}
		$post['policy_targets'] = rtrim($policy_servers, ';');
		if (!$post['policy_targets']) $post['policy_targets'] = 0;

		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . $data . "', ";
				if ($key == 'policy_targets' || $data && !in_array($key, array('account_id', 'server_serial_no', 'policy_type'))) {
					if ($key == 'policy_targets') {
						$key = __('Firewalls');
						$data = $log_message_servers;
					}
					if ($key == 'policy_template_stack') {
						$data = $this->IDs2Name($data, 'policy');
					}
					$log_message .= formatLogKeyData('policy_', $key, $data);
				}
			}
		}
		$sql = rtrim($sql_edit, ', ');
		
		/** Update the policy */
		$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}policies` SET $sql WHERE `policy_id`={$post['policy_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not update the firewall policy because a database error occurred.'), 'sql');
		}
		
		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		setBuildUpdateConfigFlag($post['server_serial_no'], 'yes', 'build');
		
		addLogEntry($log_message);
		return true;
	}
	
	/**
	 * Deletes the selected template
	 *
	 * @since 2.0
	 * @package facileManager
	 * @subpackage fmFirewall
	 *
	 * @param integer $server_id Server ID to delete
	 * @return boolean|string
	 */
	function delete($id) {
		global $fmdb, $__FM_CONFIG;
		
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_', 'policy_id', 'policy_name');
		if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', $id, 'policy_', 'deleted', 'policy_id') === false) {
			return formatError(__('This template could not be deleted because a database error occurred.'), 'sql');
		} else {
			addLogEntry("Deleted policy template '$tmp_name'.");
			return true;
		}
	}

	
	/**
	 * Displays the entry table row
	 *
	 * @since 2.0
	 * @package facileManager
	 * @subpackage fmFirewall
	 *
	 * @param object $row Single data row from $results
	 * @param integer $num_rows Number of rows in the result
	 * @return null
	 */
	function displayRow($row, $num_rows) {
		global $__FM_CONFIG;
		
//		$disabled_class = ($row->policy_status == 'disabled') ? ' class="disabled"' : null;
		
		$edit_status = $checkbox = $grab_bars = null;
		$bars_title = __('Click and drag to reorder');
		
		if (currentUserCan('manage_policies', $_SESSION['module'])) {
			$edit_status = '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
//			$edit_status .= '<a class="status_form_link" href="#" rel="';
//			$edit_status .= ($row->policy_status == 'active') ? 'disabled' : 'active';
//			$edit_status .= '">';
//			$edit_status .= ($row->policy_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
//			$edit_status .= '</a>';
			$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			$edit_status = '<td class="column-actions">' . $edit_status . '</td>';
			$checkbox = '<td><input type="checkbox" name="bulk_list[]" value="' . $row->policy_id .'" /></td>';
			$grab_bars = ($num_rows > 1) ? '<td><i class="fa fa-bars mini-icon" title="' . $bars_title . '"></i></td>' : null;
		}
		
		$stacks = $this->IDs2Name($row->policy_template_stack, 'policy');
		$targets = $this->IDs2Name($row->policy_targets, 'server');
		$comments = nl2br($row->policy_comment);

		echo <<<HTML
		<tr id="$row->policy_id" name="$row->policy_name">
			$checkbox
			$grab_bars
			<td><a href="config-policy.php?server_serial_no=t_{$row->policy_id}">$row->policy_name</a></td>
			<td>$stacks</td>
			<td>$targets</td>
			<td>$comments</td>
			$edit_status
		</tr>

HTML;
	}
	
	
	/**
	 * Displays the add/edit form
	 *
	 * @since 2.0
	 * @package facileManager
	 * @subpackage fmFirewall
	 *
	 * @param array $data Either $_POST data or returned SQL results
	 * @param string $action Add or edit
	 * @return string|void
	 */
	function printForm($data = '', $action = 'add') {
		$popup_title = $action == 'add' ? __('Add Template') : __('Edit Template');
		$popup_header = buildPopup('header', $popup_title);
		$action == 'add' ? 'create' : 'update';

		global $fm_module_policies;

		$policy_id = 0;
		$policy_targets = -1;
		$policy_name = $policy_comment = null;

		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}

		/** Process multiple policy targets */
		if (strpos($policy_targets, ';')) {
			$policy_targets = explode(';', rtrim($policy_targets, ';'));
			if (in_array('0', $policy_targets)) $policy_targets = 0;
		}

		$targets = buildSelect('policy_targets', 'policy_targets', availableServers('id'), $policy_targets, 1, null, true);

		/** Build template stack lists */
		$all_templates = $this->getTemplateList();
		$selected_templates = array();
		if ($policy_id) {
			$i = 0;
			foreach ($all_templates as $template_array_key => $template) {
				if ($policy_id == $template[1]) {
					unset ($all_templates[$template_array_key]);
				}
				foreach (explode(';', $policy_template_stack) as $selected_template_id) {
					if ($selected_template_id == $template[1]) {
						$selected_templates[$i] = $template;
						unset ($all_templates[$template_array_key]);
						$i++;
					}
				}
			}
		}
		$available_templates = $all_templates;
		
		$available_templates = buildSelect('select-from', 'select-from', array_merge($available_templates, array()), null, 1, null, true, null, 'select-stack');
		$selected_templates = buildSelect('policy_template_stack', 'policy_template_stack', array_merge($selected_templates, array()), null, 1, null, true, null, 'select-stack');
		
		$form = sprintf('
			%s
		<form name="manage" id="manage">
			<input type="hidden" name="page" value="policy" />
			<input type="hidden" name="action" value="%s" />
			<input type="hidden" name="policy_id" value="%s" />
	<div id="tabs">
		<div id="tab">
			<input type="radio" name="tab-group-1" id="tab-1" checked />
			<label for="tab-1">%s</label>
			<div id="tab-content">
			<table class="form-table policy-form">
				<tr>
					<th width="33&#37;" scope="row"><label for="policy_name">%s</label></th>
					<td width="67&#37;"><input name="policy_name" id="policy_name" type="text" value="%s" class="required" /></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="policy_comment">%s</label></th>
					<td width="67&#37;"><textarea id="policy_comment" name="policy_comment" rows="4" cols="30">%s</textarea></td>
				</tr>
			</table>
			</div>
		</div>
		<div id="tab">
			<input type="radio" name="tab-group-1" id="tab-2" />
			<label for="tab-2">%s</label>
			<div id="tab-content">
			<table class="form-table">
				<tr>
					<td>
						<span id="stack-label">%s</span>
						%s
					</td>
					<td>
						<p><a href="JavaScript:void(0);" id="button-add" class="button"><i class="fa fa-chevron-right"></i></a></p>
						<p><a href="JavaScript:void(0);" id="button-remove" class="button"><i class="fa fa-chevron-left"></i></a></p>
					</td>
					<td>
						<span id="stack-label">%s</span>
						%s
					</td>
					<td>
						<p><a href="JavaScript:void(0);" id="button-up" class="button"><i class="fa fa-chevron-up"></i></a></p>
						<p><a href="JavaScript:void(0);" id="button-down" class="button"><i class="fa fa-chevron-down"></i></a></p>
					</td>
				</tr>
			</table>
		</div>
		</div>
		<div id="tab">
			<input type="radio" name="tab-group-1" id="tab-3" />
			<label for="tab-3">%s</label>
			<div id="tab-content">
			<table class="form-table">
				<tr>
					<th width="33&#37;" scope="row"><label for="policy_targets">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
			</table>
			</div>
		</div>
	</div>',
			$popup_header, $action, $policy_id,
			__('Basic'),
			__('Template Name'), $policy_name,
			_('Comment'), $policy_comment,
			__('Stack'),
			__('Available'), $available_templates,
			__('Selected'), $selected_templates,
			__('Targets'),
			__('Firewalls'), $targets
		);
		
		$form .= buildPopup('footer');
		$form .= '</form>
		<script>
			$(document).ready(function() {
				$("#manage select").not(".select-stack").select2({
					width: "100%",
					minimumResultsForSearch: 10,
					allowClear: true
				});
				$("#button-add").not(".grey").click(function(){
					$("#select-from option:selected").each(function() {
						$("#policy_template_stack").append("<option value=\""+$(this).val()+"\">"+$(this).text()+"</option>");
						$(this).remove();
					});
				});
				$("#button-remove").not(".grey").click(function(){
					$("#policy_template_stack option:selected").each(function() {
						$("#select-from").append("<option value=\""+$(this).val()+"\">"+$(this).text()+"</option>");
						$(this).remove();
					});
				});
				$("#button-up").bind("click", function() {
					$("#policy_template_stack option:selected").each(function() {
						var newPos = $("#policy_template_stack option").index(this) - 1;
						if (newPos > -1) {
							$("#policy_template_stack option").eq(newPos).before("<option value=\""+$(this).val()+"\" selected=\"selected\">"+$(this).text()+"</option>");
							$(this).remove();
						}
					});
				});
				$("#button-down").bind("click", function() {
					var countOptions = $("#policy_template_stack option").length;
					$("#policy_template_stack option:selected").each(function() {
						var newPos = $("#policy_template_stack option").index(this) + 1;
						if (newPos < countOptions) {
							$("#policy_template_stack option").eq(newPos).after("<option value=\""+$(this).val()+"\" selected=\"selected\">"+$(this).text()+"</option>");
							$(this).remove();
						}
					});
				});
				$("input[type=submit].primary:not(.follow-action)").click(function(event) {
					$("#policy_template_stack option").each(function() {
						$(this).prop("selected", true);
					});
				});
			});
		</script>';			
		
		echo $form;
	}
	
	
	/**
	 * Validates the user-submitted data (for add and edit)
	 *
	 * @since 2.0
	 * @package facileManager
	 * @subpackage fmFirewall
	 *
	 * @param array $post Posted data to validate
	 * @return array|string
	 */
	function validatePost($post) {
		global $fmdb, $__FM_CONFIG;
		
		$post['policy_type'] = 'template';
		$post['server_serial_no'] = 0;
		if (!array_key_exists('policy_targets', $post)) {
			$post['policy_targets'] = array(-1);
		}

		if (empty($post['policy_name'])) return __('No name is defined.');

		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_name');
		if ($field_length !== false && strlen($post['policy_name']) > $field_length) return sprintf(dngettext($_SESSION['module'], 'Name is too long (maximum %d character).', 'Name is too long (maximum %d characters).', $field_length), $field_length);
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', $post['policy_name'], 'policy_', 'policy_name', "AND policy_id!={$post['policy_id']}");
		if ($fmdb->num_rows) return __('This template name already exists.');
		
		/** Remove unused */
		unset($post['tab-group-1'], $post['select-from']);
		
		$post['policy_template_stack'] = (!isset($post['policy_template_stack'])) ? null : join(';', $post['policy_template_stack']);
		
		return $post;
	}
	
	
	/**
	 * Converts IDs to their respective names
	 *
	 * @since 2.0
	 * @package facileManager
	 * @subpackage fmFirewall
	 *
	 * @param string $ids IDs to convert to names
	 * @param string $type ID type to process
	 * @return string
	 */
	function IDs2Name($ids, $type) {
		global $__FM_CONFIG;
		
		$all_text = $type == 'policy' ? sprintf('<i>%s</i>', __('None')) : __('All Servers');
		
		if ($ids) {
			if ($ids == -1) return sprintf('<i>%s</i>', __('None'));
			
			$table = ($type == 'policy') ? 'policie' : $type;
			
			/** Process multiple IDs */
			if (strpos($ids, ';')) {
				$ids_array = explode(';', rtrim($ids, ';'));
				if (in_array('0', $ids_array)) $name = $all_text;
				else {
					$name = '';
					foreach ($ids_array as $id) {
						if ($id[0] == 'g') {
							$table = 'server_group';
							$type = 'group';
						}
						$name .= getNameFromID(preg_replace('/\D/', '', $id), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . $table . 's', $type . '_', $type . '_id', $type . '_name') . ', ';
					}
					$name = rtrim($name, ', ');
				}
			} else {
				if ($ids[0] == 'g') {
					$table = 'server_group';
					$type = 'group';
				}
				$name = getNameFromID(preg_replace('/\D/', '', $ids), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . $table . 's', $type . '_', $type . '_id', $type . '_name');
			}
		} else $name = $all_text;
		
		return $name;
	}
	
	
	/**
	 * Gets the template listings
	 *
	 * @since 2.0
	 * @package facileManager
	 * @subpackage fmFirewall
	 *
	 * @return array
	 */
	function getTemplateList() {
		global $__FM_CONFIG, $fmdb;
		
		$list = array();
		$i = 0;
		
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_name', 'policy_', "AND policy_type='template'");
//		basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'masters', 'master_id', 'master_', $serial_sql . $parent_sql . " AND master_parent_id=0 AND master_status='active'");
		if ($fmdb->num_rows) {
			$last_result = $fmdb->last_result;
			for ($j=0; $j<$fmdb->num_rows; $j++) {
				$list[$i][0] = $last_result[$j]->policy_name;
				$list[$i][1] = $last_result[$j]->policy_id;
				$i++;
			}
		}
		
		return $list;
	}
	
}

if (!isset($fm_module_templates))
	$fm_module_templates = new fm_module_templates();
