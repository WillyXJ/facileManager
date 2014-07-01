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
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/                                           |
 +-------------------------------------------------------------------------+
 | Processes admin logs page                                               |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

if (!currentUserCan('view_logs')) unAuth();

printHeader();
@printMenu();

$response = isset($response) ? $response : null;

$search_sql = $list = $log_search_query = $log_search_date_b = $log_search_date_e = null;
extract($_POST);

/** Module search */
if (isset($log_search_module) && is_array($log_search_module) && !in_array('All Modules', $log_search_module)) {
	foreach ($log_search_module as $search_module) {
		$list .= "'$search_module',";
	}
	$search_sql .= 'AND log_module IN (' . rtrim($list, ',') . ') ';
}
/** User search */
$default_timezone = getOption('timezone', $_SESSION['user']['account_id']);
$list = null;
if (isset($log_search_user) && is_array($log_search_user) && !in_array(0, $log_search_user)) {
	foreach ($log_search_user as $search_user) {
		$list .= "$search_user,";
	}
	$search_sql .= 'AND user_id IN (' . rtrim($list, ',') . ') ';
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
$pagination = displayPagination($page, $total_pages);

$log_search_module = isset($log_search_module) ? $log_search_module : 'All Modules';
$log_search_user = isset($log_search_user) ? $log_search_user : 0;

$module_list = buildSelect('log_search_module', 1, buildModuleList(), $log_search_module, 4, null, true);
$user_list = buildSelect('log_search_user', 1, buildUserList(), $log_search_user, 4, null, true);

$table_info = array('class' => 'display_results');
$title_array = array('Timestamp', 'Module', 'User', array('title' => 'Message', 'style' => 'width: 50%;'));
$header = displayTableHeader($table_info, $title_array);

echo printPageHeader($response);
echo <<<HTML
		<form class="search-form" id="date-range" action="" method="post">
		<table class="log_search_form" align="center">
			<tbody>
				<tr>
					<td>$module_list</td>
					<td>$user_list</td>
					<td><input name="log_search_date_b" value="$log_search_date_b" type="text" class="datepicker" placeholder="Date Begin" /></td>
					<td><input name="log_search_date_e" value="$log_search_date_e" type="text" class="datepicker" placeholder="Date End" /></td>
					<td><input type="text" name="log_search_query" value="$log_search_query" placeholder="Search Text" /></td>
					<td><input value="Search" type="submit" class="button" /></td>
				</tr>
			</tbody>
		</table>
		</form>
$pagination
		$header

HTML;

displayLogData($page, $search_sql);

echo <<<HTML
			</tbody>
		</table>

HTML;


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
		echo <<<ROW
				<tr>
					<td colspan="4"><p class="no_results">There are no log results.</p></td>
				</tr>

ROW;
	}

	for ($i=0; $i<$log_count; $i++) {
		extract(get_object_vars($result[$i]), EXTR_OVERWRITE);
		$log_data = nl2br($log_data);
		if (isset($_POST['log_search_query'])) $log_data = str_replace($_POST['log_search_query'], '<span class="highlighted">' . $_POST['log_search_query'] . '</span>', $log_data);
		$user_name = $user_id ? getNameFromID($user_id, 'fm_users', 'user_', 'user_id', 'user_login') : $fm_name;
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
	
	$array[0] = array_fill(0, 2, 'All Modules');
	
	$query = "SELECT DISTINCT log_module FROM fm_logs WHERE account_id IN (0,{$_SESSION['user']['account_id']})";
	$fmdb->get_results($query);
	if ($fmdb->num_rows) {
		$list = $fmdb->last_result;
		for ($i=0; $i<$fmdb->num_rows; $i++) {
			$array[$i+1] = array_fill(0, 2, $list[$i]->log_module);
		}
	}
	
	return $array;
}

function buildUserList() {
	global $fmdb;
	
	$array[0] = array('All Users', 0);
	
	$query = "SELECT user_id,user_login FROM fm_users WHERE account_id={$_SESSION['user']['account_id']} ORDER BY user_login";
	$fmdb->get_results($query);
	if ($fmdb->num_rows) {
		$list = $fmdb->last_result;
		for ($i=0; $i<$fmdb->num_rows; $i++) {
			$array[$i+1][] = $list[$i]->user_login;
			$array[$i+1][] = $list[$i]->user_id;
		}
	}
	
	return $array;
}

?>
