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
 | Processes zone management page                                          |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

if (!currentUserCan(array('manage_zones', 'manage_records', 'reload_zones', 'view_all'), $_SESSION['module'])) unAuth();

include(ABSPATH . 'fm-modules/fmDNS/classes/class_zones.php');

if (!isset($map)) header('Location: zones-forward.php');
if (isset($_GET['map'])) header('Location: zones-' . sanitize(strtolower($_GET['map'])) . '.php');
$map = (isset($_POST['createZone'][0]['domain_mapping'])) ? sanitize(strtolower($_POST['createZone'][0]['domain_mapping'])) : $map;

$response = isset($response) ? $response : null;

if (currentUserCan('manage_zones', $_SESSION['module'])) {
	if (isset($_POST['action']) && $_POST['action'] == 'reload') {
		if (isset($_POST['domain_id']) && !empty($_POST['domain_id'])) {
//			$response = $fm_dns_zones->buildZoneConfig($_POST['domain_id']);
		} else header('Location: ' . $GLOBALS['basename'] . '?map=' . $map);
		unset($_POST);
	}
	
	$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'create';
	switch ($action) {
	case 'create':
		if (!empty($_POST)) {
			$insert_id = $fm_dns_zones->add($_POST);
			if (!is_numeric($insert_id)) {
				$response = '<p class="error">' . $insert_id . '</p>'. "\n";
				$form_data = $_POST;
			} else {
				$redirect_record_type = (isset($_POST['soa_id']) && $_POST['soa_id']) ? 'NS' : 'SOA';
				header('Location: zone-records.php?map=' . $map . '&domain_id=' . $insert_id . '&record_type=' . $redirect_record_type);
			}
		}
		break;
	case 'edit':
		if (!empty($_POST)) {
			$zone_update_status = $fm_dns_zones->update();
			if ($zone_update_status !== true) {
				$response = '<p class="error">' . $zone_update_status . '</p>'. "\n";
				$form_data = $_POST;
			} else header('Location: ' . $GLOBALS['basename'] . '?map=' . $map);
		}
		if (isset($_GET['status'])) {
			if (!updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $_GET['domain_id'], 'domain_', $_GET['status'], 'domain_id')) {
				$response = '<p class="error">This item could not be '. $_GET['status'] .'.</p>'. "\n";
			} else header('Location: ' . $GLOBALS['basename']);
		}
		break;
	case 'download':
		if (array_key_exists('domain_id', $_POST) && is_numeric($_POST['domain_id'])) {
			include(ABSPATH . 'fm-modules/facileManager/classes/class_accounts.php');
			include(ABSPATH . 'fm-modules/fmDNS/classes/class_buildconf.php');
			
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
					$response = '<p>Zone file export failed to write to temp file: ' . $tmp_file . '. Please correct and try again.</p>';
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
		} else header('Location: ' . $GLOBALS['basename']);
		break;
	}
}

printHeader();
@printMenu();

/** Check if any servers need their configs built first */
$reload_allowed = reloadAllowed();
if (!$reload_allowed && !$response) $response = '<p>You currently have no name servers hosting zones.  <a href="' . getMenuURL('Servers') . '">Click here</a> to manage one or more servers.</p>';

echo printPageHeader($response, null, currentUserCan('manage_zones', $_SESSION['module']), $map);
	
$sort_direction = null;
$sort_field = 'domain_name';
if (isset($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']])) {
	extract($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']], EXTR_OVERWRITE);
}

/** Get zones based on access */
$user_capabilities = getUserCapabilities($_SESSION['user']['id']);
$limited_domain_ids = (array_key_exists('access_specific_zones', $user_capabilities[$_SESSION['module']]) && $user_capabilities[$_SESSION['module']]['access_specific_zones'][0]) ? "AND domain_id IN (" . implode(',', $user_capabilities[$_SESSION['module']]['access_specific_zones']) . ")" : null;

$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', array($sort_field, 'domain_name'), 'domain_', "AND domain_mapping='$map' AND domain_clone_domain_id='0' $limited_domain_ids", null, false, $sort_direction);
$total_pages = ceil($fmdb->num_rows / $_SESSION['user']['record_count']);
if ($page > $total_pages) $page = $total_pages;
echo displayPagination($page, $total_pages);

$fm_dns_zones->rows($result, $map, $reload_allowed, $page);

printFooter();

?>
