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
 | Processes form posts                                                    |
 +-------------------------------------------------------------------------+
*/

if (!defined('AJAX')) define('AJAX', true);
require_once('../../../fm-init.php');

foreach (glob(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_*.php') as $filename) {
    include_once($filename);
}

/** Handle mass updates */
if (is_array($_POST) && array_key_exists('action', $_POST) && $_POST['action'] == 'process-all-updates') {
	$result .= processBulkDomainIDs(getZoneReloads('ids'));
	return;
}

$checks_array = array('servers' => 'manage_servers',
					'views' => 'manage_servers',
					'acls' => 'manage_servers',
					'keys' => 'manage_servers',
					'options' => 'manage_servers',
					'logging' => 'manage_servers',
					'controls' => 'manage_servers',
					'masters' => 'manage_servers',
					'domains' => 'manage_zones',
					'domain' => 'manage_zones',
					'zones' => 'manage_zones',
					'soa' => 'manage_zones',
					'rpz' => 'manage_zones',
					'http' => 'manage_servers',
					'tls' => 'manage_servers',
					'files' => 'manage_servers',
					'dnssec-policy' => 'manage_servers'
				);
$allowed_capabilities = array_unique($checks_array);

if (is_array($_POST) && count($_POST) && currentUserCan($allowed_capabilities, $_SESSION['module'])) {
	if (isset($_POST['page']) && !isset($_POST['item_type'])) {
		$_POST['item_type'] = $_POST['page'];
	}
	$perms = checkUserPostPerms($checks_array, $_POST['item_type']);
	
	if ($_POST['item_type'] == 'options' && !$perms) {
		if (array_key_exists('item_sub_type', $_POST) && $_POST['item_sub_type'] == 'domain_id') {
			$perms = zoneAccessIsAllowed(array($_POST['item_id']), 'manage_zones');
		} elseif ($_POST['item_type'] == 'options') {
			$perms = zoneAccessIsAllowed(array(getNameFromID($_POST['item_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_', 'cfg_id', 'domain_id')), 'manage_zones');
		}
	}
	if (!$perms) {
		returnUnAuth('text');
	}

	/* Handle SOA */
	if ($_POST['item_type'] == 'soa' && isset($_POST['action']) && $_POST['action'] == 'process-record-updates') {
		list($return, $errors) = $fm_dns_records->validateRecordUpdates('array');
		/* Submit if there are no errors */
		if (!count($errors)) {
			/* Set $_POST var from returned array */
			if (isset($_POST['create'])) {
				$_POST['create'][0] = $return;
			} else {
				$keys = array_keys($_POST['update']);
				$_POST['update'][$keys[0]] = $return;
			}
			include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/pages/zone-records-write.php');
		} else {
			header("Content-type: application/json");
			echo json_encode(array($return, $errors));
		}
		exit;
	}
	
	if (isset($_POST['item_id'])) $id = $_POST['item_id'];
	$server_serial_no = isset($_POST['server_serial_no']) ? $_POST['server_serial_no'] : null;
	$type = isset($_POST['item_sub_type']) ? $_POST['item_sub_type'] : null;
	$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . $_POST['item_type'];
	$item_type = $_POST['item_type'];
	$prefix = substr($item_type, 0, -1) . '_';
	
	/* Determine which class we need to deal with */
	switch($item_type) {
		case 'servers':
			$post_class = $fm_module_servers;
			$object = __('server');
			if ((isset($_POST['page']) && $_POST['page'] == 'groups') || (isset($_POST['url_var_type']) && $_POST['url_var_type'] == 'groups')) {
				$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'server_groups';
				$prefix = 'group_';
				$object = __('server group');
			}
			$server_serial_no = $type;
			break;
		case 'options':
			$post_class = $fm_module_options;
			$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config';
			$prefix = 'cfg_';
			$object = __('option');
			break;
		case 'domains':
			$post_class = $fm_dns_zones;
			$object = __('domain group');
			$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domain_groups';
			$prefix = 'group_';
			break;
		case 'logging':
			$post_class = $fm_module_logging;
			$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config';
			$prefix = 'cfg_';
			$type = isset($_POST['log_type']) ? $_POST['log_type'] : 'channel';
			$object = $type;
			$field_data = $prefix . 'data';
			break;
		case 'soa':
			$post_class = $fm_module_templates;
			$server_serial_no = $type = $item_type;
			$prefix = $item_type . '_';
			$object = $type;
			break;
		case 'domain':
			$post_class = $fm_module_templates;
			$server_serial_no = $item_type;
			$type = $item_type . 's';
			break;
		case 'zones':
			$post_class = $fm_dns_zones;
			$server_serial_no = $item_type;
			$type = $item_type . 's';
			break;
		case 'rpz':
		case 'http':
		case 'tls':
		case 'dnssec-policy':
			$post_class = (in_array($item_type, array('dnssec-policy'))) ? $fm_module_dnssec : ${'fm_module_' . $item_type};
			$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config';
			$prefix = 'cfg_';
			$type = $object = $item_type;
			$field_data = $prefix . 'data';
			break;
		default:
			$post_class = ${"fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}{$item_type}"};
			$object = substr($item_type, 0, -1);
	}
	
	if (!isset($field_data)) $field_data = $prefix . 'name';

	switch ($_POST['action']) {
		case 'add':
		case 'create':
			$response = $post_class->add($_POST);
			echo ($response !== true) ? $response : 'Success';
			break;
		case 'delete':
			if (isset($id)) {
				exit(parseAjaxOutput($post_class->delete($id, $server_serial_no, $type)));
			}
			break;
		case 'edit':
		case 'update':
			if (isset($_POST['item_status'])) {
				if (!updateStatus('fm_' . $table, $id, $prefix, $_POST['item_status'], $prefix . 'id')) {
					exit(sprintf(__('This item could not be set to %s.') . "\n", $_POST['item_status']));
				} else {
					setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
					$tmp_name = getNameFromID($id, 'fm_' . $table, $prefix, $prefix . 'id', $field_data);
					if ($object == 'rpz') {
						$tmp_name = ($tmp_name) ? getNameFromID($tmp_name, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name') : __('All Zones');
					}
					addLogEntry(sprintf(__('Set %s (%s) status to %s.'), $object, $tmp_name, $_POST['item_status']));
					exit('Success');
				}
			} else {
				$response = $post_class->update($_POST);
				echo ($response !== true) ? $response : 'Success';
			}
			break;
		case 'bulk':
			if (array_key_exists('bulk_action', $_POST) && in_array($_POST['bulk_action'], array('reload', 'enable', 'disable', 'delete'))) {
				switch($_POST['bulk_action']) {
					case 'reload':
						$popup_footer = buildPopup('footer', _('OK'), array('cancel_button' => 'cancel'), getMenuURL(ucfirst(getNameFromID($_POST['item_id'][0], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_mapping'))));

						echo buildPopup('header', __('Reload Results')) . '<pre>';
						echo transformOutput(processBulkDomainIDs($_POST['item_id']));
						echo "\n" . ucfirst($_POST['bulk_action']) . ' is complete.</pre>' . $popup_footer;
						break;
					case 'enable':
					case 'disable':
					case 'delete':
						$status = $_POST['bulk_action'] . 'd';
						if ($status == 'enabled') $status = 'active';
						foreach ((array) $_POST['item_id'] as $id) {
							$tmp_name = getNameFromID($id, 'fm_' . $table, $prefix, $prefix . 'id', $field_data);
							if (updateStatus('fm_' . $table, $id, $prefix, $status, $prefix . 'id')) {
								setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
								addLogEntry(sprintf(__('Set %s (%s) status to %s.'), $object, $tmp_name, $status));
							}
						}
						exit('Success');
				}
			}
			break;
		case 'update_sort':
			if (!empty($_POST)) {
				$result = $post_class->update($_POST);
				if ($result !== true) {
					exit($result);
				}
				exit('Success');
			}
			exit(__('The sort order could not be updated due to an invalid request.'));
	}

	exit;
}

returnUnAuth('text');

/**
 * Processes the array of domain ids for reload
 *
 * @since 2.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param array $domain_id_array Array of domain_ids to process
 * @return string
 */
function processBulkDomainIDs($domain_id_array) {
	global $fm_dns_zones;

	$return = '';
	if (is_array($domain_id_array)) {
		foreach ($domain_id_array as $domain_id) {
			if (!is_numeric($domain_id)) continue;
			
			$return .= $fm_dns_zones->doBulkZoneReload($domain_id) . "\n";
		}
	}
	
	return $return;
}
