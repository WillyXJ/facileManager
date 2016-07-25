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
 | Processes form posts                                                    |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

if (!defined('AJAX')) define('AJAX', true);
require_once('../../../fm-init.php');

include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_servers.php');
include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_views.php');
include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_acls.php');
include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_keys.php');
include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_options.php');
include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_zones.php');
include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_logging.php');
include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_controls.php');
include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_templates.php');

if (is_array($_POST) && array_key_exists('action', $_POST) && $_POST['action'] == 'bulk' &&
	array_key_exists('bulk_action', $_POST) && in_array($_POST['bulk_action'], array('reload'))) {
	
	$popup_footer = buildPopup('footer', __('OK'), array('cancel_button' => 'cancel'), getMenuURL(ucfirst(getNameFromID($_POST['item_id'][0], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_mapping'))));

	echo buildPopup('header', __('Reload Results')) . '<pre>';
	echo processBulkDomainIDs($_POST['item_id']);
	echo "\n" . ucfirst($_POST['bulk_action']) . ' is complete.</pre>' . $popup_footer;
	
	exit;

	/** Handle mass updates */
} elseif (is_array($_POST) && array_key_exists('action', $_POST) && $_POST['action'] == 'process-all-updates') {
	$result .= processBulkDomainIDs(getZoneReloads('ids'));
	return;
}

$unpriv_message = __('You do not have sufficient privileges.');
$checks_array = array('servers' => 'manage_servers',
					'views' => 'manage_servers',
					'acls' => 'manage_servers',
					'keys' => 'manage_servers',
					'options' => 'manage_servers',
					'logging' => 'manage_servers',
					'controls' => 'manage_servers',
					'domains' => 'manage_zones',
					'domain' => 'manage_zones',
					'soa' => 'manage_zones'
				);
$allowed_capabilities = array_unique($checks_array);

if (is_array($_POST) && count($_POST) && currentUserCan($allowed_capabilities, $_SESSION['module'])) {
	if (!checkUserPostPerms($checks_array, $_POST['item_type'])) {
		echo $unpriv_message;
		exit;
	}
	
	$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . sanitize($_POST['item_type']);

	$id = sanitize($_POST['item_id']);
	$server_serial_no = isset($_POST['server_serial_no']) ? sanitize($_POST['server_serial_no']) : null;
	$type = isset($_POST['item_sub_type']) ? sanitize($_POST['item_sub_type']) : null;
	$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . $_POST['item_type'];
	$item_type = $_POST['item_type'];
	$prefix = substr($item_type, 0, -1) . '_';
	
	/* Determine which class we need to deal with */
	switch($_POST['item_type']) {
		case 'servers':
			$post_class = $fm_module_servers;
			$object = __('server');
			if (isset($_POST['url_var_type']) && sanitize($_POST['url_var_type']) == 'groups') {
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
			break;
		case 'logging':
			$post_class = $fm_module_logging;
			$table = $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config';
			$prefix = 'cfg_';
			$type = isset($_POST['url_var_type']) ? sanitize($_POST['url_var_type']) : 'channel';
			$object = $type;
			$field_data = $prefix . 'data';
			break;
		case 'soa':
			$post_class = $fm_module_templates;
			$server_serial_no = $type = sanitize($_POST['item_type']);
			break;
		case 'domain':
			$post_class = $fm_module_templates;
			$server_serial_no = $_POST['item_type'];
			$type = sanitize($_POST['item_type']) . 's';
			break;
		default:
			$post_class = ${"fm_dns_${_POST['item_type']}"};
			$object = substr($item_type, 0, -1);
	}
	
	if (!isset($field_data)) $field_data = $prefix . 'name';

	switch ($_POST['action']) {
		case 'add':
			if (!empty($_POST[$table . '_name'])) {
				if (!$post_class->add($_POST)) {
					echo '<div class="error"><p>This ' . $table . ' could not be added.</p></div>'. "\n";
					$form_data = $_POST;
				} else exit('Success');
			}
			break;
		case 'delete':
			if (isset($id)) {
				exit(parseAjaxOutput($post_class->delete(sanitize($id), $server_serial_no, $type)));
			}
			break;
		case 'edit':
			if (isset($_POST['item_status'])) {
				if (!updateStatus('fm_' . $table, $id, $prefix, sanitize($_POST['item_status']), $prefix . 'id')) {
					exit(sprintf(__('This item could not be set to %s.') . "\n", $_POST['item_status']));
				} else {
					setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
					$tmp_name = getNameFromID($id, 'fm_' . $table, $prefix, $prefix . 'id', $field_data);
					addLogEntry(sprintf(__('Set %s (%s) status to %s.'), $object, $tmp_name, sanitize($_POST['item_status'])));
					exit('Success');
				}
			}
			break;
	}

	exit;
}

echo $unpriv_message;

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

	$return = null;
	if (is_array($domain_id_array)) {
		foreach ($domain_id_array as $domain_id) {
			if (!is_numeric($domain_id)) continue;
			
			$return .= $fm_dns_zones->doBulkZoneReload($domain_id) . "\n";
		}
	}
	
	return $return;
}

?>