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
 | Processes form posts                                                    |
 +-------------------------------------------------------------------------+
*/

if (!defined('AJAX')) define('AJAX', true);
require_once('../../../fm-init.php');

if (is_array($_POST) && count($_POST) && currentUserCan('run_tools')) {
	if (isset($_POST['task']) && !empty($_POST['task'])) {
		switch($_POST['task']) {
			case 'import-zones':
				$response = buildPopup('header', __('Bulk Zone Import'));
				if (!empty($_FILES['import-file']['tmp_name'])) {
					$block_style = 'style="display: block;"';
					$response = $fm_module_tools->bulkZoneImportWizard();
					if (strpos($output, 'You do not have permission') === false) {
						$classes = 'wide';
					}
				}
				break;
			case 'dump-cache':
			case 'clear-cache':
				$response = buildPopup('header', __('Cache Management Results'));
				if (!currentUserCan('manage_servers')) {
					$_POST = array();
					break;
				}
				if (!empty($_POST['domain_name_servers'])) {
					include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_servers.php');
					
					/** All servers */
					if (in_array('0', $_POST['domain_name_servers'])) {
						basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_name', 'server_');
						if ($fmdb->num_rows) {
							$result = $fmdb->last_result;
							for ($i=0; $i<$fmdb->num_rows; $i++) {
								$all_servers[] = $result[$i]->server_id;
							}
							$_POST['domain_name_servers'] = $all_servers;
						} else {
							global $menu;
							
							$response = buildPopup('header', _('Error'));
							$response .= sprintf('<p>' . _('You currently have no active servers defined. <a href="%s">Click here</a> to define one or more to manage.') . '</p>', $menu[getParentMenuKey(_('Servers'))][4]);
							break;
						}
					}
					
					/** Get unique servers */
					foreach ($_POST['domain_name_servers'] as $server_id) {
						if (strpos($server_id, 's_') !== false) {
							$server_id = str_replace('s_', '', $server_id);
						}
						if (strpos($server_id, 'g_') !== false) {
							$group_id = str_replace('g_', '', $server_id);
							foreach ($fm_module_servers->getGroupServerIDs($group_id) as $group_server_id) {
								if ($group_server_id) {
									$tmp_domain_name_servers[] = $group_server_id;
								}
							}
						} else {
							$tmp_domain_name_servers[] = $server_id;
						}
					}
					$_POST['domain_name_servers'] = array_unique($tmp_domain_name_servers);
					
					$response .= '<pre>';
					foreach ($_POST['domain_name_servers'] as $server_id) {
						$response .= $fm_module_servers->manageCache($server_id, $_POST['task']) . "\n\n";
					}
					$response = trim($response, "\n"). '</pre>';
				} else {
					$response = buildPopup('header', _('Error'));
					$response .= sprintf('<p>%s</p>', __('Please specify at least one server.'));
				}
				break;
		}
	}
}

?>