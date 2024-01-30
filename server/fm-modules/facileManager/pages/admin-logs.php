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
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/                                           |
 +-------------------------------------------------------------------------+
 | Processes admin logs page                                               |
 +-------------------------------------------------------------------------+
*/

if (!currentUserCan('view_logs')) unAuth();

printHeader();
@printMenu();

$response = isset($response) ? $response : null;

extract($_REQUEST);
$search_sql = null;

/** Module search */
if (isset($log_search_module) && is_array($log_search_module) && !in_array('All Modules', $log_search_module)) {
	$search_sql .= 'AND log_module IN ("' . join('","', $log_search_module) . '") ';
}
/** User search */
$default_timezone = getOption('timezone', $_SESSION['user']['account_id']);
if (isset($log_search_user) && is_array($log_search_user) && !in_array('0', $log_search_user)) {
	$search_sql .= 'AND user_login IN ("' . join('","', $log_search_user) . '") ';
}
/** Begin date search */
if (isset($log_search_date_b) && !empty($log_search_date_b)) {
	$search_sql .= "AND log_timestamp > " . strtotime($log_search_date_b . ' 00:00:00' . $default_timezone) . ' ';
}
/** End date search */
if (isset($log_search_date_e) && !empty($log_search_date_e)) {
	$search_sql .= "AND log_timestamp < " . strtotime($log_search_date_e . ' 23:23:59' . $default_timezone) . ' ';
}
/** Query search */
if (isset($log_search_query) && !empty($log_search_query)) {
	$search_sql .= "AND log_data LIKE '%" . sanitize($log_search_query) . "%' ";
}

$query = "SELECT * FROM fm_logs WHERE account_id IN (0,{$_SESSION['user']['account_id']}) $search_sql ORDER BY log_timestamp DESC";
$fmdb->query($query);
$log_count = $fmdb->num_rows;

$total_pages = ceil($log_count / $_SESSION['user']['record_count']);
if ($page > $total_pages) $page = $total_pages;
if ($page < 1) $page = 1;
$pagination = displayPagination($page, $total_pages);

$log_search_module = isset($log_search_module) ? $log_search_module : _('All Modules');
$log_search_user = isset($log_search_user) ? $log_search_user : 0;

$module_list = buildSelect('log_search_module', 1, buildModuleList(), $log_search_module, 4, null, true);
$user_list = buildSelect('log_search_user', 1, buildUserList(), $log_search_user, 4, null, true);

$table_info = array('class' => 'display_results');
$title_array = array(_('Timestamp'), _('Module'), _('User'), array('title' => _('Message'), 'style' => 'width: 50%;'));
$header = displayTableHeader($table_info, $title_array);

echo printPageHeader($response);
printf('<form class="search-form" id="date-range" action="" method="get">
		<table class="log_search_form" align="center">
			<tbody>
				<tr>
					<td>%s</td>
					<td>%s</td>
					<td><input name="log_search_date_b" value="%s" type="text" class="datepicker" placeholder="%s" /></td>
					<td><input name="log_search_date_e" value="%s" type="text" class="datepicker" placeholder="%s" /></td>
					<td><input type="text" name="log_search_query" value="%s" placeholder="%s" /></td>
					<td><input value="%s" type="submit" class="button" /></td>
				</tr>
			</tbody>
		</table>
		</form>
		%s %s',
		$module_list, $user_list,
		$log_search_date_b, _('Date Begin'),
		$log_search_date_e, _('Date End'),
		$log_search_query, _('Search Text'),
		_('Search'),
		$pagination, $header
		);

displayLogData($page, $search_sql);

echo <<<HTML
			</tbody>
		</table>

HTML;

printFooter(null, $output);


function displayLogData($page, $search_sql = null) {
	global $fmdb, $fm_name, $__FM_CONFIG;
	
	/** Get datetime formatting */
	$date_format = getOption('date_format', $_SESSION['user']['account_id']);
	$time_format = getOption('time_format', $_SESSION['user']['account_id']);
	
	$query = "SELECT * FROM fm_logs WHERE account_id IN (0,{$_SESSION['user']['account_id']}) $search_sql ORDER BY log_timestamp DESC LIMIT " . (($page - 1) * $_SESSION['user']['record_count']) . ", {$_SESSION['user']['record_count']}";
	$fmdb->query($query);
	$result = $fmdb->last_result;
	$log_count = $fmdb->num_rows;
	
	if (!$log_count) {
		printf('<tr><td colspan="4"><p class="no_results">%s</p></td></tr>', _('There are no log results.'));
	}

	for ($i=0; $i<$log_count; $i++) {
		extract(get_object_vars($result[$i]), EXTR_OVERWRITE);
		$log_data = nl2br(wordwrap($log_data, 80, "\n", true));
		if (isset($_POST['log_search_query'])) $log_data = str_replace($_POST['log_search_query'], '<span class="highlighted">' . $_POST['log_search_query'] . '</span>', $log_data);
		$user_name = is_numeric($user_login) ? $fm_name : $user_login;
		$log_timestamp = date($date_format . ' ' . $time_format . ' e', $log_timestamp);
		echo <<<ROW
				<tr>
					<td>$log_timestamp</td>
					<td>$log_module</td>
					<td>$user_name</td>
					<td>$log_data</td>
				</tr>

ROW;
	}
}

function buildModuleList() {
	global $fmdb;
	
	$array[0] = array_fill(0, 2, _('All Modules'));
	
	$query = "SELECT DISTINCT log_module FROM fm_logs WHERE account_id IN (0,{$_SESSION['user']['account_id']})";
	$list = $fmdb->get_results($query);
	if ($fmdb->num_rows) {
		for ($i=0; $i<$fmdb->num_rows; $i++) {
			$array[$i+1] = array_fill(0, 2, $list[$i]->log_module);
		}
	}
	
	return $array;
}

function buildUserList() {
	global $fmdb;
	
	$array[0] = array(_('All Users'), '0');
	
	$query = "SELECT user_id,user_login FROM fm_users WHERE account_id={$_SESSION['user']['account_id']} ORDER BY user_login";
	$list = $fmdb->get_results($query);
	if ($fmdb->num_rows) {
		for ($i=0; $i<$fmdb->num_rows; $i++) {
			$array[$i+1][] = $list[$i]->user_login;
			$array[$i+1][] = $list[$i]->user_login;
		}
	}
	
	return $array;
}

?>
