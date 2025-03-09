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
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/                                           |
 +-------------------------------------------------------------------------+
 +-------------------------------------------------------------------------+
*/

/** Handle client installations */
if (arrayKeysExist(array('genserial', 'addserial', 'install', 'upgrade', 'ssh'), $_GET)) {
	if (!defined('CLIENT')) define('CLIENT', true);

	if (!isset($global_form_field_excludes)) $global_form_field_excludes = array('dryrun', 'compress', 'config', 'AUTHKEY', 'update_from_client', 'module_name', 'module_type');
	
	require_once('fm-init.php');
	if (file_exists(ABSPATH . 'fm-modules/' . $_POST['module_name'] . '/variables.inc.php')) {
		include(ABSPATH . 'fm-modules/' . $_POST['module_name'] . '/variables.inc.php');
	}
	include(ABSPATH . 'fm-includes/version.php');
	
	/** Check account key */
	include(ABSPATH . 'fm-modules/facileManager/classes/class_accounts.php');
	$account_status = $fm_accounts->verifyAccount($_POST['AUTHKEY']);

	if ($account_status !== true) {
		$data = $account_status;
	} else {
		if (in_array($_POST['module_name'], getActiveModules())) {
			if (array_key_exists('genserial', $_GET)) {
				$module = ($_POST['module_name']) ? $_POST['module_name'] : $_SESSION['module'];
				$data['server_serial_no'] = generateSerialNo($module);
			}
			
			if (array_key_exists('addserial', $_GET)) {
				/** Client expects an array for a good return */
				$data = $_POST;

				/** Does the record already exist for this account? */
				basicGet('fm_' . $__FM_CONFIG[$_POST['module_name']]['prefix'] . 'servers', $_POST['server_name'], 'server_', 'server_name');
				if ($fmdb->num_rows) {
					$server_array = $fmdb->last_result;
					$_POST['server_id'] = $server_array[0]->server_id;
					if ($_POST['update_from_client'] == true) $update_server = moduleAddServer('update');
				} else {
					if (getOption('client_auto_register')) {
						/** Add new server */
						$add_server = moduleAddServer('add');
						if ($add_server !== true) {
							$data = _('Could not add server to account.') . "\n" . $add_server;
						}
					} else {
						$data = _('Client automatic registration is not allowed.') . "\n";
					}
				}
			}
			
			/** Client installs */
			if (array_key_exists('install', $_GET)) {
				/** Set flags */
				$data = basicUpdate('fm_' . $__FM_CONFIG[$_POST['module_name']]['prefix'] . 'servers', $_POST['SERIALNO'], 'server_installed', 'yes', 'server_serial_no');
				if (function_exists('moduleCompleteClientInstallation')) {
					moduleCompleteClientInstallation();
				}
				require_once(ABSPATH . 'fm-modules/shared/classes/class_servers.php');
				if (!$fm_module_servers) {
					$fm_module_servers = new fm_shared_module_servers();
				}
				$fm_module_servers->updateClientVersion();
			}
			
			/** Client upgrades */
			if (array_key_exists('upgrade', $_GET)) {
				if (!isset($__FM_CONFIG[$_POST['module_name']]['min_client_auto_upgrade_version'])) {
					$__FM_CONFIG[$_POST['module_name']]['min_client_auto_upgrade_version'] = 0;
				}
				$current_module_version = getOption('client_version', 0, $_POST['module_name']);
				if ($_POST['server_client_version'] == $current_module_version) {
					$data = sprintf(_("Latest version: %s\nNo upgrade available."), $current_module_version) . "\n";
				} elseif (version_compare($_POST['server_client_version'], $__FM_CONFIG[$_POST['module_name']]['min_client_auto_upgrade_version'], '<')) {
					$data = sprintf(_("Latest version: %s\nThis upgrade requires a manual installation."), $current_module_version) . "\n";
				} else {
					$data = array(
								'latest_core_version' => $fm_version,
								'latest_module_version' => $current_module_version
							);
					
					/** Get proxy server information to pass to the client */
					$data['proxy_info'] = array();
					if (getOption('proxy_enable')) {
						$proxyauth = getOption('proxy_user') . ':' . getOption('proxy_pass');
						if ($proxyauth == ':') $proxyauth = null;
						$data['proxy_info'] = array(
							CURLOPT_PROXY => getOption('proxy_host') . ':' . getOption('proxy_port'),
							CURLOPT_PROXYUSERPWD => $proxyauth
						);
					}
				}
				
				// Probably need to move/remove this
				require_once(ABSPATH . 'fm-modules/shared/classes/class_servers.php');
				if (!$fm_module_servers) {
					$fm_module_servers = new fm_shared_module_servers();
				}
				$fm_module_servers->updateClientVersion();
			}
			
			if (array_key_exists('ssh', $_GET)) {
				$data = getOption('ssh_' . sanitize($_GET['ssh']), getAccountID($_POST['AUTHKEY']));
			}
		} else {
			$data = sprintf(_("failed\n\nInstallation aborted. %s is not an active module."), $_POST['module_name']) . "\n";
		}
	}
	
	if ($_POST['compress']) echo gzcompress(serialize($data));
	else echo serialize($data);
	exit;
}
