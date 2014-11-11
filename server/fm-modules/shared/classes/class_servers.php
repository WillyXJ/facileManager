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
			echo buildPopup('header', 'Error');
			echo '<p>You do not have permission to manage servers.</p>';
			echo buildPopup('footer', 'OK', array('cancel_button' => 'cancel'));
			exit;
		}
		
		/** Check serial number */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', sanitize($serial_no), 'server_', 'server_serial_no');
		if (!$fmdb->num_rows) return $serial_no . ' is not a valid serial number.';

		$server_details = $fmdb->last_result;
		extract(get_object_vars($server_details[0]), EXTR_SKIP);
		
		$response[] = $server_name;
		
		if ($server_installed != 'yes') {
			$response[] = ' --> Failed: Client is not installed.' . "\n";
		}
		
		if (count($response) == 1) {
			switch($server_update_method) {
				case 'cron':
					/* Servers updated via cron require manual upgrades */
					$response[] = ' --> This server needs to be upgraded manually with the following command:';
					$response[] = " --> sudo php /usr/local/$fm_name/{$_SESSION['module']}/\$(ls /usr/local/$fm_name/{$_SESSION['module']} | grep php | grep -v functions) upgrade";
					addLogEntry('Upgraded client scripts on ' . $server_name . '.');
					break;
				case 'http':
				case 'https':
					/** Test the port first */
					if (!socketTest($server_name, $server_update_port, 10)) {
						$response[] = ' --> Failed: could not access ' . $server_name . ' using ' . $server_update_method . ' (tcp/' . $server_update_port . ').';
						break;
					}
					
					/** Remote URL to use */
					$url = $server_update_method . '://' . $server_name . ':' . $server_update_port . '/' . $_SESSION['module'] . '/reload.php';
					
					/** Data to post to $url */
					$post_data = array('action'=>'upgrade', 'serial_no'=>$server_serial_no);
					
					$post_result = @unserialize(getPostData($url, $post_data));
					
					if (!is_array($post_result)) {
						/** Something went wrong */
						if (empty($post_result)) {
							$response[] = ' --> It appears ' . $server_name . ' does not have php configured properly within httpd or httpd is not running.';
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
						addLogEntry('Upgraded client scripts on ' . $server_name . '.');
					}
					break;
				case 'ssh':
					/** Test the port first */
					if (!socketTest($server_name, $server_update_port, 10)) {
						$response[] = ' --> Failed: could not access ' . $server_name . ' using ' . $server_update_method . ' (tcp/' . $server_update_port . ').';
						break;
					}
					
					/** Get SSH key */
					$ssh_key = getOption('ssh_key_priv', $_SESSION['user']['account_id']);
					if (!$ssh_key) {
						$response[] = ' --> Failed: SSH key is not <a href="' . getMenuURL('General') . '">defined</a>.';
						break;
					}
					
					$temp_ssh_key = sys_get_temp_dir() . '/fm_id_rsa';
					if (@file_put_contents($temp_ssh_key, $ssh_key) === false) {
						$response[] = ' --> Failed: could not load SSH key into ' . $temp_ssh_key . '.';
						break;
					}
					
					@chmod($temp_ssh_key, 0400);
					
					exec(findProgram('ssh') . " -t -i $temp_ssh_key -o 'StrictHostKeyChecking no' -p $server_update_port -l fm_user $server_name 'sudo php /usr/local/$fm_name/{$_SESSION['module']}/\$(ls /usr/local/$fm_name/{$_SESSION['module']} | grep php | grep -v functions) upgrade 2>&1'", $post_result, $retval);
					
					@unlink($temp_ssh_key);
					
					if ($retval) {
						/** Something went wrong */
						$post_result[] = 'Client upgrade failed.';
					} else {
						if (!count($post_result)) {
							$post_result[] = 'Config build was successful.';
							addLogEntry('Upgraded client scripts on ' . $server_name . '.');
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
			echo buildPopup('header', 'Error');
			echo '<p>You do not have permission to build server configs.</p>';
			echo buildPopup('footer', 'OK', array('cancel_button' => 'cancel'));
			exit;
		}
		
		/** Check serial number */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', sanitize($server_serial_no), 'server_', 'server_serial_no');
		if (!$fmdb->num_rows) return $server_serial_no . ' is not a valid serial number.';

		$server_details = $fmdb->last_result;
		extract(get_object_vars($server_details[0]), EXTR_SKIP);
		
		$response[] = $server_name;
		
		if ($server_installed != 'yes') {
			$response[] = ' --> Failed: Client is not installed.';
		}
		
		if (count($response) == 1 && $server_status != 'active') {
			$response[] = ' --> Failed: Server is ' . $server_status . '.';
		}
		
		if (count($response) == 1) {
			foreach (makePlainText($fm_module_servers->buildServerConfig($server_serial_no), true) as $line) {
				$response[] = ' --> ' . $line;
			}
		}
		
		$response[] = "\n";
		
		return implode("\n", $response);
	}
	
}

if (!isset($fm_shared_module_servers))
	$fm_shared_module_servers = new fm_shared_module_servers();

?>
