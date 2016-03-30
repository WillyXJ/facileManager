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
*/

class fm_shared_module_servers {
	
	/**
	 * Upgrades the client sotware
	 *
	 * @since 1.1
	 * @package facileManager
	 */
	function doClientUpgrade($serial_no) {
		global $fmdb, $__FM_CONFIG, $fm_name;
		
		/** Check permissions */
		if (!currentUserCan('manage_servers', $_SESSION['module'])) {
			echo buildPopup('header', _('Error'));
			printf('<p>%s</p>', _('You do not have permission to manage servers.'));
			echo buildPopup('footer', _('OK'), array('cancel_button' => 'cancel'));
			exit;
		}
		
		/** Process server group */
		if ($serial_no[0] == 'g') {
			$group_servers = $this->getGroupServers(substr($serial_no, 1));
			
			if (!is_array($group_servers)) return $group_servers;
			
			$response = null;
			foreach ($group_servers as $serial_no) {
				if (is_numeric($serial_no)) $response .= $this->doClientUpgrade($serial_no) . "\n";
			}
			return $response;
		}
		
		/** Check serial number */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', sanitize($serial_no), 'server_', 'server_serial_no');
		if (!$fmdb->num_rows) return sprintf(_('%d is not a valid serial number.'), $serial_no);

		$server_details = $fmdb->last_result;
		extract(get_object_vars($server_details[0]), EXTR_SKIP);
		
		$response[] = $server_name;
		
		if ($server_installed != 'yes') {
			$response[] = ' --> ' . _('Failed: Client is not installed.') . "\n";
		}
		
		if (count($response) == 1) {
			switch($server_update_method) {
				case 'cron':
					/* Servers updated via cron require manual upgrades */
					$response[] = ' --> ' . _('This server needs to be upgraded manually with the following command:');
					$response[] = " --> sudo php /usr/local/$fm_name/{$_SESSION['module']}/\$(ls /usr/local/$fm_name/{$_SESSION['module']} | grep php | grep -v functions) upgrade";
					addLogEntry(sprintf(_('Upgraded client scripts on %s.'), $server_name));
					break;
				case 'http':
				case 'https':
					/** Test the port first */
					if (!socketTest($server_name, $server_update_port, 10)) {
						$response[] = ' --> ' . sprintf(_('Failed: could not access %s using %s (tcp/%d).'), $server_name, $server_update_method, $server_update_port);
						break;
					}
					
					/** Remote URL to use */
					$url = $server_update_method . '://' . $server_name . ':' . $server_update_port . '/fM/reload.php';
					
					/** Data to post to $url */
					$post_data = array('action' => 'upgrade',
						'serial_no' => $server_serial_no,
						'module' => $_SESSION['module']);
					
					$post_result = @unserialize(getPostData($url, $post_data));
					
					if (!is_array($post_result)) {
						/** Something went wrong */
						if (empty($post_result)) {
							$response[] = ' --> ' . sprintf(_('It appears %s does not have php configured properly within httpd or httpd is not running.'), $server_name);
							break;
						}
					} else {
						if (count($post_result) > 1) {
							/** Loop through and format the output */
							foreach ($post_result as $line) {
								if (strlen(trim($line))) $response[] = " --> $line";
							}
						} else {
							$response[] = " --> " . $post_result[0];
						}
						addLogEntry(sprintf(_('Upgraded client scripts on %s.'), $server_name));
					}
					break;
				case 'ssh':
					/** Test the port first */
					if (!socketTest($server_name, $server_update_port, 10)) {
						$response[] = ' --> ' . sprintf(_('Failed: could not access %s using %s (tcp/%d).'), $server_name, $server_update_method, $server_update_port);
						break;
					}
					
					/** Get SSH key */
					$ssh_key = getOption('ssh_key_priv', $_SESSION['user']['account_id']);
					if (!$ssh_key) {
						$response[] = ' --> ' . sprintf(_('Failed: SSH key is not %sdefined</a>.'), '<a href="' . getMenuURL(_('General')) . '">');
						break;
					}
					
					$temp_ssh_key = getOption('fm_temp_directory') . '/fm_id_rsa';
					if (file_exists($temp_ssh_key)) @unlink($temp_ssh_key);
					if (@file_put_contents($temp_ssh_key, $ssh_key) === false) {
						$response[] = ' --> ' . sprintf(_('Failed: could not load SSH key into %s.'), $temp_ssh_key);
						break;
					}
					
					@chmod($temp_ssh_key, 0400);
					
					$ssh_user = getOption('ssh_user', $_SESSION['user']['account_id']);
					if (!$ssh_user) {
						return sprintf('<p class="error">%s</p>'. "\n", sprintf(_('Failed: SSH user is not <a href="%s">defined</a>.'), getMenuURL(_('General'))));
					}

					unset($post_result);
					exec(findProgram('ssh') . " -t -i $temp_ssh_key -o 'StrictHostKeyChecking no' -p $server_update_port -l $ssh_user $server_name 'sudo php /usr/local/$fm_name/{$_SESSION['module']}/\$(ls /usr/local/$fm_name/{$_SESSION['module']} | grep php | grep -v functions) upgrade 2>&1'", $post_result, $retval);
					
					@unlink($temp_ssh_key);
					
					if ($retval) {
						/** Something went wrong */
						$post_result[] = _('Client upgrade failed.');
					} else {
						if (!count($post_result)) {
							$post_result[] = _('Config build was successful.');
							addLogEntry(sprintf(_('Upgraded client scripts on %s.'), $server_name));
						}
					}
					if (count($post_result) > 1) {
						/** Loop through and format the output */
						foreach ($post_result as $line) {
							if (strlen(trim($line))) $response[] = " --> $line";
						}
					} else {
						$response[] = " --> " . $post_result[0];
					}
					break;
			}
			$response[] = null;
		}

		return implode("\n", $response);
	}
	
	
	/**
	 * Updates the daemon version number in the database
	 *
	 * @since 2.2
	 * @package facileManager
	 */
	function updateServerVersion() {
		global $fmdb, $__FM_CONFIG;
		
		$query = "UPDATE `fm_{$__FM_CONFIG[$_POST['module_name']]['prefix']}servers` SET `server_version`='" . $_POST['server_version'] . "', `server_os`='" . $_POST['server_os'] . "' WHERE `server_serial_no`='" . $_POST['SERIALNO'] . "' AND `account_id`=
			(SELECT account_id FROM `fm_accounts` WHERE `account_key`='" . $_POST['AUTHKEY'] . "')";
		$fmdb->query($query);
	}
	
	
	/**
	 * Updates the fM client version number in the database
	 *
	 * @since 1.1
	 * @package facileManager
	 */
	function updateClientVersion() {
		global $fmdb, $__FM_CONFIG;
		
		if (array_key_exists('server_client_version', $_POST)) {
			$query = "UPDATE `fm_{$__FM_CONFIG[$_POST['module_name']]['prefix']}servers` SET `server_client_version`='" . $_POST['server_client_version'] . "' WHERE `server_serial_no`='" . $_POST['SERIALNO'] . "' AND `account_id`=
				(SELECT account_id FROM `fm_accounts` WHERE `account_key`='" . $_POST['AUTHKEY'] . "')";
			$fmdb->query($query);
		}
		
		if (array_key_exists('server_os_distro', $_POST)) {
			$query = "UPDATE `fm_{$__FM_CONFIG[$_POST['module_name']]['prefix']}servers` SET `server_os_distro`='" . $_POST['server_os_distro'] . "' WHERE `server_serial_no`='" . $_POST['SERIALNO'] . "' AND `account_id`=
				(SELECT account_id FROM `fm_accounts` WHERE `account_key`='" . $_POST['AUTHKEY'] . "')";
			$fmdb->query($query);
		}
	}
	
	
	/**
	 * Process bulk server config build
	 *
	 * @since 1.2
	 * @package facileManager
	 */
	function doBulkServerBuild($server_serial_no) {
		global $fmdb, $__FM_CONFIG, $fm_module_servers;
		
		/** Check permissions */
		if (!currentUserCan('build_server_configs', $_SESSION['module'])) {
			echo buildPopup('header', _('Error'));
			printf('<p>%s</p>', _('You do not have permission to build server configs.'));
			echo buildPopup('footer', _('OK'), array('cancel_button' => 'cancel'));
			exit;
		}
		
		/** Process server group */
		if ($server_serial_no[0] == 'g') {
			$group_servers = $this->getGroupServers(substr($server_serial_no, 1));
			
			if (!is_array($group_servers)) return $group_servers;
			
			$response = null;
			foreach ($group_servers as $serial_no) {
				if (is_numeric($serial_no)) $response .= $this->doBulkServerBuild($serial_no) . "\n";
			}
			return $response;
		}
		
		/** Check serial number */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', sanitize($server_serial_no), 'server_', 'server_serial_no');
		if (!$fmdb->num_rows) return sprintf(_('%d is not a valid serial number.'), $server_serial_no);

		$server_details = $fmdb->last_result;
		extract(get_object_vars($server_details[0]), EXTR_SKIP);
		
		$response[] = $server_name;
		
		if ($server_installed != 'yes') {
			$response[] = ' --> ' . _('Failed: Client is not installed.');
		}
		
		if (count($response) == 1 && $server_status != 'active') {
			$response[] = ' --> ' . sprintf(_('Failed: Server is %s.'), $server_status);
		}
		
		if (count($response) == 1) {
			if (!isset($fm_module_servers)) {
				include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_servers.php');
			}
			foreach (makePlainText($this->buildServerConfig($server_serial_no), true) as $line) {
				$response[] = ' --> ' . $line;
			}
		}
		
		$response[] = null;
		
		return implode("\n", $response);
	}

	
	/**
	 * Gets all servers in a group
	 *
	 * @since 2.1
	 * @package facileManager
	 */
	function getGroupServers($id) {
		global $fmdb, $__FM_CONFIG;
		
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'server_groups', sanitize($id), 'group_', 'group_id');
		if (!$fmdb->num_rows) return sprintf(_('%d is not a valid group number.'), $id);

		$group_details = $fmdb->last_result[0];
		$group_masters = (isset($group_details->group_masters)) ? explode(';', $group_details->group_masters) : null;
		$group_slaves  = (isset($group_details->group_slaves)) ? explode(';', $group_details->group_slaves) : null;

		$group_servers = array_merge($group_masters, $group_slaves);
		
		foreach ($group_servers as $key => $id) {
			$server_serial_nos[] = getNameFromID($id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_id', 'server_serial_no');
		}
		
		return (array) $server_serial_nos;
	}

	/**
	 * Builds the server configuration
	 *
	 * @since 2.2
	 * @package facileManager
	 *
	 * @param integer $serial_no Server serial number to build the config for
	 * @param string $action buildconf or other
	 * @param string $friendly_action Friendly version of $action for user display
	 * @return string
	 */
	function buildServerConfig($serial_no, $action = 'buildconf', $friendly_action = 'Configuration Build') {
		global $fmdb, $__FM_CONFIG, $fm_name;
		
		/** Check serial number */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', sanitize($serial_no), 'server_', 'server_serial_no');
		if (!$fmdb->num_rows) return sprintf('<p class="error">%s</p>', _('This server is not found.'));

		$server_details = $fmdb->last_result;
		extract(get_object_vars($server_details[0]), EXTR_SKIP);
		$options[] = $response = null;
		
		$popup_footer = buildPopup('footer', 'OK', array('cancel_button' => 'cancel'));
		
		if ($action == 'buildconf') {
			if (getOption('enable_named_checks', $_SESSION['user']['account_id'], $_SESSION['module']) == 'yes') {
				global $fm_module_buildconf;
				include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_buildconf.php');

				$data['SERIALNO'] = $server_serial_no;
				$data['compress'] = 0;
				$data['dryrun'] = true;

				basicGet('fm_accounts', $_SESSION['user']['account_id'], 'account_', 'account_id');
				$account_result = $fmdb->last_result;
				$data['AUTHKEY'] = $account_result[0]->account_key;

				list($raw_data, $response) = $fm_module_buildconf->buildServerConfig($data);

				$response .= @$fm_module_buildconf->namedSyntaxChecks($raw_data);
				if (strpos($response, 'error') !== false) return buildPopup('header', $friendly_action . ' Results') . $response . $popup_footer;
			}

			if (getOption('purge_config_files', $_SESSION['user']['account_id'], $_SESSION['module']) == 'yes') {
				$options[] = 'purge';
			}
		}
		
		switch($server_update_method) {
			case 'cron':
				if ($action == 'buildconf') {
					/* set the server_update_config flag */
					setBuildUpdateConfigFlag($serial_no, 'conf', 'update');
					$response = sprintf('<p>%s</p>'. "\n", _('This server will be updated on the next cron run.'));
				} else {
					$response = sprintf('<p>%s</p>'. "\n", _('This server receives updates via cron - please manage the server manually.'));
				}
				break;
			case 'http':
			case 'https':
				/** Test the port first */
				if (!socketTest($server_name, $server_update_port, 10)) {
					return sprintf('<p class="error">%s</p>'. "\n", sprintf(_('Failed: could not access %s using %s (tcp/%d).'), $server_name, $server_update_method, $server_update_port));
				}
				
				/** Remote URL to use */
				$url = $server_update_method . '://' . $server_name . ':' . $server_update_port . '/fM/reload.php';
				
				/** Data to post to $url */
				$post_data = array('action' => $action,
					'serial_no' => $server_serial_no,
					'options' => implode(' ', $options),
					'module' => $_SESSION['module']);
				
				$post_result = @unserialize(getPostData($url, $post_data));
				
				if (!is_array($post_result)) {
					/** Something went wrong */
					if (empty($post_result)) {
						return sprintf('<p class="error">%s</p>', sprintf(_('It appears %s does not have php configured properly within httpd or httpd is not running.'), $server_name));
					}
					return '<p class="error">' . $post_result . '</p>';
				} else {
					if (count($post_result) > 1) {
						$response .= "<pre>\n";
						
						/** Loop through and format the output */
						foreach ($post_result as $line) {
							$response .= "[$server_name] $line\n";
						}
						
						$response .= "</pre>\n";
					} else {
						$response = "<p>[$server_name] " . $post_result[0] . '</p>';
					}
				}
				break;
			case 'ssh':
				/** Test the port first */
				if (!socketTest($server_name, $server_update_port, 10)) {
					return sprintf('<p class="error">%s</p>'. "\n", sprintf(_('Failed: could not access %s using %s (tcp/%d).'), $server_name, $server_update_method, $server_update_port));
				}
				
				/** Get SSH key */
				$ssh_key = getOption('ssh_key_priv', $_SESSION['user']['account_id']);
				if (!$ssh_key) {
					return sprintf('<p class="error">%s</p>'. "\n", sprintf(_('Failed: SSH key is not <a href="%s">defined</a>.'), getMenuURL(_('General'))));
				}
				
				$temp_ssh_key = getOption('fm_temp_directory') . '/fm_id_rsa';
				if (file_exists($temp_ssh_key)) @unlink($temp_ssh_key);
				if (@file_put_contents($temp_ssh_key, $ssh_key) === false) {
					return sprintf('<p class="error">%s</p>'. "\n", sprintf(_('Failed: could not load SSH key into %s.'), $temp_ssh_key));
				}
				
				@chmod($temp_ssh_key, 0400);
				
				$ssh_user = getOption('ssh_user', $_SESSION['user']['account_id']);
				if (!$ssh_user) {
					return sprintf('<p class="error">%s</p>'. "\n", sprintf(_('Failed: SSH user is not <a href="%s">defined</a>.'), getMenuURL(_('General'))));
				}
		
				/** Test SSH authentication */
				exec(findProgram('ssh') . " -t -i $temp_ssh_key -o 'StrictHostKeyChecking no' -p $server_update_port -l $ssh_user $server_name 'ls /usr/local/$fm_name/{$_SESSION['module']}/client.php'", $post_result, $retval);
				if ($retval) {
					/** Something went wrong */
					@unlink($temp_ssh_key);
					
					/** Handle error codes */
					return ($retval == 255) ? sprintf('<p class="error">%s</p>'. "\n", _('Failed: Could not login via SSH.')) : sprintf('<p class="error">%s</p>'. "\n", _('Failed: Client file is not present.'));
				}
				unset($post_result);
				
				/** Run build */
				exec(findProgram('ssh') . " -t -i $temp_ssh_key -o 'StrictHostKeyChecking no' -p $server_update_port -l $ssh_user $server_name 'sudo php /usr/local/$fm_name/{$_SESSION['module']}/client.php $action " . implode(' ', $options) . "'", $post_result, $retval);
				
				@unlink($temp_ssh_key);
				
				if ($retval) {
					/** Something went wrong */
					return '<p class="error">' . ucfirst($friendly_action) . ' failed.</p>'. "\n";
				}
				
				if (!count($post_result)) $post_result[] = ucfirst($friendly_action) . ' was successful.';

				if (count($post_result) > 1) {
					$response = "<pre>\n";

					/** Loop through and format the output */
					foreach ($post_result as $line) {
						$response .= "[$server_name] $line\n";
					}

					$response .= "</pre>\n";
				} else {
					$response = "<p>[$server_name] " . $post_result[0] . '</p>';
				}

				break;
		}
		
		if ($action == 'buildconf') {
			/* reset the server_build_config flag */
			if (!strpos($response, strtolower('failed'))) {
				setBuildUpdateConfigFlag($serial_no, 'no', 'build');
			}
		}

		$tmp_name = getNameFromID($serial_no, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name');
		addLogEntry(ucfirst($friendly_action) . " was performed on server '$tmp_name'.");

		if (strpos($response, '<pre>') !== false) {
			$response = buildPopup('header', $friendly_action . ' Results') . $response . $popup_footer;
		}
		return $response;
	}

}

if (!isset($fm_shared_module_servers))
	$fm_shared_module_servers = new fm_shared_module_servers();

?>