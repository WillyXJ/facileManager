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

if (!currentUserCan(array('manage_zones', 'manage_records', 'reload_zones', 'view_all'), $_SESSION['module'])) unAuth();

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_zones.php');

if (!isset($map)) {
	header('Location: zones-forward.php');
	exit;
}
if (isset($_GET['map'])) {
	header('Location: zones-' . sanitize(strtolower($_GET['map'])) . '.php');
	exit;
}
$map = (isset($_POST['createZone'][0]['domain_mapping'])) ? sanitize(strtolower($_POST['createZone'][0]['domain_mapping'])) : $map;

if (currentUserCan('manage_zones', $_SESSION['module'])) {
	$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'create';
	switch ($action) {
	case 'create':
		if (!empty($_POST)) {
			$insert_id = $fm_dns_zones->add($_POST);
			if (!is_numeric($insert_id)) {
				$response = displayResponseClose($insert_id);
			} else {
				if ($map == 'groups') {
					header('Location: zones-groups.php');
					exit;
				} elseif ($_POST['domain_template'] == 'yes') {
					header('Location: templates-zones.php');
					exit;
				}
				$redirect_record_type = (isset($_POST['soa_id']) && $_POST['soa_id']) ? 'NS' : 'SOA';
				header('Location: zone-records.php?map=' . $map . '&domain_id=' . $insert_id . '&record_type=' . $redirect_record_type);
				exit;
			}
		}
		break;
	case 'edit':
		if (!empty($_POST)) {
			$zone_update_status = $fm_dns_zones->update();
			if ($zone_update_status !== true) {
				$response = displayResponseClose($zone_update_status);
			} else {
				header('Location: ' . $GLOBALS['basename'] . '?map=' . $map);
				exit;
			}
		}
		if (isset($_GET['status'])) {
			if (!updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $_GET['domain_id'], 'domain_', $_GET['status'], 'domain_id')) {
				$response = displayResponseClose(sprintf(__('This item could not be set to %s.'), $_GET['status']));
			} else {
				header('Location: ' . $GLOBALS['basename']);
				exit;
			}
		}
		break;
	case 'download':
		if (array_key_exists('domain_id', $_POST) && is_numeric($_POST['domain_id'])) {
			include(ABSPATH . 'fm-modules/facileManager/classes/class_accounts.php');
			include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_buildconf.php');
			
			$data['SERIALNO'] = -1;
			$data['compress'] = 0;
			$data['dryrun'] = true;
			$data['domain_id'] = sanitize($_POST['domain_id']);
		
			basicGet('fm_accounts', $_SESSION['user']['account_id'], 'account_', 'account_id');
			$account_result = $fmdb->last_result;
			$data['AUTHKEY'] = $account_result[0]->account_key;
		
			$raw_data = $fm_module_buildconf->buildZoneConfig($data);
		
			if (!is_array($raw_data)) {
				$zone_contents = unserialize($raw_data);
			} else {
				$zone_contents = null;
				foreach ($raw_data['files'] as $filename => $contents) {
					$zone_contents .= $contents . "\n\n";
				}
				$tmp_file = TMP_FILE_EXPORTS . $filename . date("Ymdhis");
				if (!file_put_contents($tmp_file, $zone_contents)) {
					$response = sprintf('<p>%s</p>', sprintf(__('Zone file export failed to write to temp file: %s. Please correct and try again.'), $tmp_file));
					break;
				}
				
				if (is_file($tmp_file)) {
//					exec(findProgram('zip') . ' -j ' . $tmp_file . '.zip ' . $tmp_file);
//					echo findProgram('zip') . ' -j ' . $tmp_file . '.zip ' . $tmp_file; exit;
//					$gzdata = gzencode($zone_contents, 9);
					file_put_contents($tmp_file . '.gz', gzencode($zone_contents, 9));
//					$fm = fopen($tmp_file . '.gz', 'w');
//					gzwrite($fm, $zone_contents);
//					gzclose($fm);
					header('Content-type: application/x-download');
					header('Content-Encoding: gzip');
					header('Content-Disposition: attachment; filename=' . basename($filename) . '.gz');
//					header("Content-Length: " . strlen($gzdata));
					header('Content-Transfer-Encoding: binary');
//					echo $gzdata;
					readfile($tmp_file . '.gz');
					unlink($tmp_file . '.gz');
					unlink($tmp_file);
					exit;
				}
			}
		} else {
			header('Location: ' . $GLOBALS['basename']);
			exit;
		}
		break;
	}
}

define('FM_INCLUDE_SEARCH', true);

printHeader();
@printMenu();

$search_query = createSearchSQL(array('name', 'mapping', 'type'), 'domain_');

/** Check if any servers need their configs built first */
$reload_allowed = reloadAllowed();
if (!$reload_allowed && !$response) $response = '<p>' . sprintf(__('You currently have no name servers hosting zones. <a href="%s">Click here</a> to manage one or more servers.'), getMenuURL(_('Servers'))) . '</p>';

echo printPageHeader((string) $response, null, currentUserCan('manage_zones', $_SESSION['module']), $map, null, 'noscroll');

$sort_direction = null;
if ($map == 'groups') {
	$sort_field = 'group_name';
	$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domain_groups', 'group_name', 'group_', null, null, false, $sort_direction);
} else {
	$sort_field = 'domain_name';
	if (isset($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']])) {
		extract($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']], EXTR_OVERWRITE);
	}

	/** Get zones based on access */
	$user_capabilities = getUserCapabilities($_SESSION['user']['id'], 'all');

	$limited_domain_ids = ')';
	if (array_key_exists('access_specific_zones', $user_capabilities[$_SESSION['module']]) && !array_key_exists('view_all', $user_capabilities[$_SESSION['module']]) && $user_capabilities[$_SESSION['module']]['access_specific_zones'][0]) {
		$limited_domain_ids = "OR domain_clone_domain_id>0) AND domain_id IN (";
		$limited_domain_ids .= join(',', $fm_dns_zones->getZoneAccessIDs($user_capabilities[$_SESSION['module']]['access_specific_zones'])) . ')';
	}

	/** Process domain_view filtering */
	if (isset($_GET['domain_view']) && !in_array(0, $_GET['domain_view'])) {
		foreach (array_merge(array(0), (array) $_GET['domain_view']) as $view_id) {
			$view_id = sanitize($view_id);
			(string) $domain_view_sql .= " (domain_view='$view_id' OR domain_view LIKE '$view_id;%' OR domain_view LIKE '%;$view_id;%' OR domain_view LIKE '%;$view_id') OR";
		}
		if ($domain_view_sql) {
			$domain_view_sql = ' AND (' . rtrim($domain_view_sql, ' OR') . ')';
		}
	}

	/** Process domain_group filtering */
	if (isset($_GET['domain_group']) && !in_array(0, $_GET['domain_group'])) {
		foreach (array_merge((array) $_GET['domain_group']) as $group_id) {
			$group_id = sanitize($group_id);
			(string) $domain_group_sql .= " (domain_groups='$group_id' OR domain_groups LIKE '$group_id;%' OR domain_groups LIKE '%;$group_id;%' OR domain_groups LIKE '%;$group_id') OR";
		}
		if ($domain_group_sql) {
			$domain_group_sql = ' AND (' . rtrim($domain_group_sql, ' OR') . ')';
		}
		$limited_domain_ids = "OR domain_clone_domain_id>0)";
	}

	if (getOption('zone_sort_hierarchical', $_SESSION['user']['account_id'], $_SESSION['module']) == 'yes') {
		$query = "SELECT *,SUBSTRING_INDEX(`domain_name`, '.', -2) AS a,SUBSTRING_INDEX(`domain_name`, '.', 2) AS b FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE `domain_status`!='deleted' AND account_id='1' AND domain_template='no' AND domain_mapping='{$map}' AND (domain_clone_domain_id='0' " . $limited_domain_ids . (string) $domain_view_sql . (string) $domain_group_sql . (string) $search_query . " ORDER BY a $sort_direction, b $sort_direction, `domain_name` $sort_direction";
		$result = $fmdb->query($query);
	} else {
		$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', array($sort_field, 'domain_name'), 'domain_', "AND domain_template='no' AND domain_mapping='$map' AND (domain_clone_domain_id='0' $limited_domain_ids " . (string) $domain_view_sql . (string) $domain_group_sql . (string) $search_query, null, false, $sort_direction, false, "SUBSTRING_INDEX(`domain_name`, '.', -2),SUBSTRING_INDEX(`domain_name`, '.', 2),`domain_name`");
	}
}

$total_pages = ceil($fmdb->num_rows / $_SESSION['user']['record_count']);
if ($page > $total_pages) $page = $total_pages;

$fm_dns_zones->rows($result, $map, $reload_allowed, $page, $total_pages);

printFooter();

?>
