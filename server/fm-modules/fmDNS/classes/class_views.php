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

class fm_dns_views {
	
	/**
	 * Displays the view list
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
						'class' => 'display_results',
						'id' => 'table_edits',
						'name' => 'views'
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
		$title_array = array_merge((array) $title_array, array(array('class' => 'header-tiny'), array('title' => __('View Name')), array('title' => __('Zone Transfer Key')), array('title' => _('Comment'), 'class' => 'header-nosort')));
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$title_array[] = array('title' => __('Actions'), 'class' => 'header-actions header-nosort');
			if ($num_rows > 1) $table_info['class'] .= ' grab1';
		}

		echo '<div class="overflow-container">';
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
			printf('<p id="table_edits" class="noresult" name="views">%s</p>', __('There are no views.'));
		}
	}

	/**
	 * Adds the new view
	 */
	function add($data) {
		global $fmdb, $__FM_CONFIG;
		
		/** Validate entries */
		$data = $this->validatePost($data);
		if (!is_array($data)) return $data;

		extract($data, EXTR_SKIP);
		
		/** Get view_order_id */
		if (!isset($view_order_id) || $view_order_id == 0) {
			basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'views', $server_serial_no, 'view_', 'server_serial_no', 'ORDER BY view_order_id DESC LIMIT 1');
			if ($fmdb->num_rows) {
				$view_order_id = $fmdb->last_result[0]->view_order_id + 1;
			} else $view_order_id = 1;
		}
		
		$query = "INSERT INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}views` (`account_id`, `server_serial_no`, `view_order_id`, `view_name`, `view_key_id`, `view_comment`) VALUES('{$_SESSION['user']['account_id']}', '$server_serial_no', '$view_order_id', '$view_name', '$view_key_id', '$view_comment')";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the view because a database error occurred.'), 'sql');
		}

		$log_message = sprintf(__('Added view') . ":\nName: %s\nZone Transfer Key: %s\nComment: %s\nServer: %s\n",
	$view_name,
			($view_key_id) ? getNameFromID($view_key_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'keys', 'key_', 'key_id', 'key_name') : _('None'),
			$view_comment, getServerName($server_serial_no)
		);

		addLogEntry($log_message);
		return true;
	}

	/**
	 * Updates the selected view
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG, $global_form_field_excludes;
		
		if (!isset($post['server_serial_no'])) {
			$post['server_serial_no'] = 0;
		}

		/** Update sort order */
		if ($post['action'] == 'update_sort') {
			/** Make new order in array */
			$new_sort_order = explode(';', rtrim($post['sort_order'], ';'));
			
			/** Get view listing for server */
			basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'views', 'view_order_id', 'view_', "AND server_serial_no='{$post['server_serial_no']}'");
			$count = $fmdb->num_rows;
			$view_result = $fmdb->last_result;
			for ($i=0; $i<$count; $i++) {
				$order_id = array_search($view_result[$i]->view_id, $new_sort_order);
				if ($order_id === false) return __('The sort order could not be updated due to an invalid request.');
				$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}views` SET `view_order_id`=$order_id WHERE `view_id`={$view_result[$i]->view_id} AND `server_serial_no`='{$post['server_serial_no']}' AND `account_id`='{$_SESSION['user']['account_id']}'";
				$fmdb->query($query);
				if ($fmdb->sql_errors) {
					return formatError(__('Could not update the order because a database error occurred.'), 'sql');
				}
			}
			
			setBuildUpdateConfigFlag($post['server_serial_no'], 'yes', 'build');
		
			$log_message = __('Updated view order') . ":\n";
			$log_message .= formatLogKeyData('', 'server', getServerName($post['server_serial_no']));

			addLogEntry($log_message);
			return true;
		}
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;

		$exclude = array_merge($global_form_field_excludes, array('view_id', 'view_order_id'));

		$sql_edit = '';
		$old_name = getNameFromID($post['view_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name');

		$log_message = sprintf(__("Updated view '%s' to the following"), $old_name) . ":\n";

		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . $data . "', ";
				if ($key == 'server_serial_no') {
					$log_message .= formatLogKeyData('', 'server', getServerName($data));
				} elseif ($key == 'view_key_id') {
					$log_message .= ($data) ? formatLogKeyData('', 'Zone Transfer Key', getNameFromID($data, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'keys', 'key_', 'key_id', 'key_name')) : _('None');
				} else {
					$log_message .= formatLogKeyData('view_', $key, $data);
				}
			}
		}
		$sql = rtrim($sql_edit, ', ');
		
		/** Update the view */
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}views` SET $sql WHERE `view_id`={$post['view_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not update the view because a database error occurred.'), 'sql');
		}

		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		addLogEntry($log_message);
		return true;
	}
	
	
	/**
	 * Deletes the selected view
	 */
	function delete($id, $server_serial_no) {
		global $fmdb, $__FM_CONFIG;
		
		/** Are there any associated zones? */
		basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_id', 'domain_', "AND (`domain_view`=$id or `domain_view` LIKE '$id;%' or `domain_view` LIKE '%;$id' or `domain_view` LIKE '%;$id;%')");
		if ($fmdb->num_rows) {
			return __('There are zones associated with this view.');
		}
		
		/** Are there any corresponding configs to delete? */
		basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_id', 'cfg_', 'AND view_id=' . $id);
		if ($fmdb->num_rows) {
			/** Delete corresponding configs */
			if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', $id, 'cfg_', 'deleted', 'view_id') === false) {
				return formatError(__('The corresponding configs could not be deleted.'), 'sql');
			}
		}
		
		/** Delete view */
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name');
		if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', $id, 'view_', 'deleted', 'view_id') === false) {
			return formatError(__('This view could not be deleted because a database error occurred.'), 'sql');
		} else {
//			setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
			$log_message = __('Deleted a view') . ":\n";
			$log_message .= formatLogKeyData('', 'Name', $tmp_name);
			$log_message .= formatLogKeyData('_serial_no', 'server_serial_no', getServerName($server_serial_no));
			addLogEntry($log_message);
			return true;
		}
	}


	function displayRow($row, $num_rows) {
		global $__FM_CONFIG, $fmdb;
		
		$disabled_class = ($row->view_status == 'disabled') ? ' class="disabled"' : null;
		$bars_title = __('Click and drag to reorder');
		
		$server_serial_no = $row->server_serial_no ? '&server_serial_no=' . $row->server_serial_no : null;
		$icons = sprintf('<a href="config-options.php?view_id=%d%s" class="tooltip-bottom mini-icon" data-tooltip="%s"><i class="mini-icon fa fa-sliders" aria-hidden="true"></i></a>', $row->view_id, $server_serial_no, __('Configure Additional Options'));
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$edit_status = '<td class="column-actions">';
			$edit_status .= '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';

			/** Are there any associated zones? */
			basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_id', 'domain_', "AND (`domain_view`={$row->view_id} or `domain_view` LIKE '{$row->view_id};%' or `domain_view` LIKE '%;{$row->view_id}' or `domain_view` LIKE '%;{$row->view_id};%')");
			if (!$fmdb->num_rows) {
				$edit_status .= '<a class="status_form_link" href="#" rel="';
				$edit_status .= ($row->view_status == 'active') ? 'disabled' : 'active';
				$edit_status .= '">';
				$edit_status .= ($row->view_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
				$edit_status .= '</a>';

				$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
				$edit_status .= '</td>';
				$checkbox = '<input type="checkbox" name="bulk_list[]" value="' . $row->view_id .'" />';
			}

			$grab_bars = ($num_rows > 1) ? '<i class="fa fa-bars mini-icon" title="' . $bars_title . '"></i>' : null;
		} else {
			$edit_status = $grab_bars = $checkbox = null;
		}
		
		$tmp_key_name = _('None');
		if ($row->view_key_id) {
			$tmp_key_name = getNameFromID($row->view_key_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'keys', 'key_', 'key_id', 'key_name');
		}
		$comments = nl2br($row->view_comment);

		echo <<<HTML
		<tr id="$row->view_id" name="$row->view_name"$disabled_class>
			<td>$checkbox</td>
			<td>$grab_bars</td>
			<td>$row->view_name $icons</td>
			<td>$tmp_key_name</td>
			<td>$comments</td>
			$edit_status
		</tr>
HTML;
	}

	/**
	 * Displays the form to add new view
	 */
	function printForm($data = '', $action = 'add') {
		global $__FM_CONFIG;
		
		$view_id = $view_order_id = $view_key_id = 0;
		$view_name = $view_comment = null;
		$server_serial_no = (isset($_REQUEST['request_uri']['server_serial_no']) && (intval($_REQUEST['request_uri']['server_serial_no']) > 0 || $_REQUEST['request_uri']['server_serial_no'][0] == 'g')) ? sanitize($_REQUEST['request_uri']['server_serial_no']) : 0;
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($data))
				extract($data);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		/** Get field length */
		$view_name_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_name');

		$keys = ($view_key_id) ? array($view_key_id) : null;
		$keys = buildSelect('view_key_id', 'view_key_id', availableItems('key', 'blank', 'AND `key_type`="tsig"'), $keys, 1, '', false);

		$popup_title = ($action == 'add') ? __('Add View') : __('Edit View');

		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		$return_form = sprintf('
		%s
		<form name="manage" id="manage">
			<input type="hidden" name="page" id="page" value="views" />
			<input type="hidden" name="action" id="action" value="%s" />
			<input type="hidden" name="view_id" id="view_id" value="%d" />
			<input type="hidden" name="view_order_id" value="%d" />
			<input type="hidden" name="server_serial_no" value="%s" />
			<table class="form-table">
				<tr>
					<th width="33&#37;" scope="row"><label for="view_name">%s</label></th>
					<td width="67&#37;"><input name="view_name" id="view_name" type="text" value="%s" size="40" placeholder="internal" maxlength="%d" class="required" /></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="view_key_id">%s</label> <a href="#" class="tooltip-top" data-tooltip="%s"><i class="fa fa-question-circle"></i></a></th>
					<td width="67&#37;">%s</td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="view_comment">%s</label></th>
					<td width="67&#37;"><textarea id="view_comment" name="view_comment" rows="4" cols="30">%s</textarea></td>
				</tr>
			</table>
		%s
		</form>
		<script>
			$(document).ready(function() {
				$("#manage select").select2({
					minimumResultsForSearch: 10,
					allowClear: true,
					width: "200px"
				});
			});
		</script>',
				$popup_header,
				$action, $view_id, $view_order_id, $server_serial_no,
				__('View Name'), $view_name, $view_name_length,
				__('Zone Transfer Key'), __('Optionally specify a key for all zone transfers in this view to use.'), $keys,
				_('Comment'), $view_comment, $popup_footer
			);

		return $return_form;
	}

	/**
	 * Validates the submitted form
	 *
	 * @since 7.0.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param array $post Posted array
	 * @return array|string|boolean
	 */
	function validatePost($post) {
		global $fmdb, $__FM_CONFIG;
		
		$post['view_key_id'] = intval($post['view_key_id']);
		
		if (empty($post['view_name'])) return __('No view name defined.');
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_name');
		if ($field_length !== false && strlen($post['view_name']) > $field_length) return sprintf(__('View name is too long (maximum %d characters).'), $field_length);
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', $post['view_name'], 'view_', 'view_name', "AND view_id!='{$post['view_id']}'");
		if ($fmdb->num_rows) return __('This view already exists.');

		return $post;
	}
}

if (!isset($fm_dns_views))
	$fm_dns_views = new fm_dns_views();
