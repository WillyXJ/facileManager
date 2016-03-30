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
 | fmFirewall: Easily manage one or more software firewalls                |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmfirewall/                        |
 +-------------------------------------------------------------------------+
*/

class fm_shared_module_tools {
	
	/**
	 * Tests client connectivity
	 *
	 * @since 2.2
	 * @package facileManager
	 *
	 * @return string
	 */
	function connectTests() {
		global $fmdb, $__FM_CONFIG, $fm_name, $fm_module_tools;
		
		$return = null;
		
		/** Load ssh key for use */
		$ssh_key = getOption('ssh_key_priv', $_SESSION['user']['account_id']);
		$temp_ssh_key = getOption('fm_temp_directory') . '/fm_id_rsa';
		if ($ssh_key) {
			if (file_exists($temp_ssh_key)) @unlink($temp_ssh_key);
			$ssh_key_loaded = @file_put_contents($temp_ssh_key, $ssh_key);
			@chmod($temp_ssh_key, 0400);
		}
		$ssh_user = getOption('ssh_user', $_SESSION['user']['account_id']);

		/** Get server list */
		$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_name', 'server_');
		
		/** Process server list */
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;
		for ($x=0; $x<$num_rows; $x++) {
			$return .= sprintf(__("Running tests for %s\n"), $results[$x]->server_name);
			
			/** ping tests */
			$return .= "\t" . str_pad(__('Ping:'), 15);
			if (pingTest($results[$x]->server_name)) $return .=  __('success');
			else $return .=  __('failed');
			$return .=  "\n";

			/** remote port tests */
			$return .= "\t" . str_pad(__('Remote Port:'), 15);
			if ($results[$x]->server_update_method != 'cron') {
				if (socketTest($results[$x]->server_name, $results[$x]->server_update_port, 10)) {
					$return .= __('success') . ' (tcp/' . $results[$x]->server_update_port . ")\n";
					
					if ($results[$x]->server_update_method == 'ssh') {
						$return .= "\t" . str_pad(__('SSH Login:'), 15);
						if (!$ssh_key) {
							$return .= __('no SSH key defined');
						} elseif ($ssh_key_loaded === false) {
							$return .= sprintf(__('could not load SSH key into %s'), $temp_ssh_key);
						} elseif (!$ssh_user) {
							$return .= __('no SSH user defined');
						} else {
							exec(findProgram('ssh') . " -t -i $temp_ssh_key -o 'StrictHostKeyChecking no' -p {$results[$x]->server_update_port} -l $ssh_user {$results[$x]->server_name} 'ls /usr/local/$fm_name/{$_SESSION['module']}/client.php'", $post_result, $retval);
							if ($retval) {
								$return .= ($retval == 255) ? __('ssh key login failed') : __('client file is not present');
							} else {
								$return .= __('success');
							}
						}
					} else {
						/** php tests */
						$return .= "\t" . str_pad(__('http page:'), 15);
						$php_result = getPostData($results[$x]->server_update_method . '://' . $results[$x]->server_name . '/' .
									$_SESSION['module'] . '/reload.php', null);
						if ($php_result == 'Incorrect parameters defined.') $return .= __('success');
						else $return .= __('failed');
					}
					
				} else $return .=  __('failed') . ' (tcp/' . $results[$x]->server_update_port . ')';
			} else $return .= __('skipping (host updates via cron)');
			$return .=  "\n";
			
			/** Module-specific connection tests */
			$module_tools_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class_tools.php';
			if (file_exists($module_tools_file)) {
				if (!class_exists('fm_module_tools')) include($module_tools_file);
				
				$return .= $fm_module_tools->connectTests($results[$x]);
			}
			
			$return .=  "\n";
		}
		
		@unlink($temp_ssh_key);
		
		return $return;
	}
	
}

if (!isset($fm_shared_module_tools))
	$fm_shared_module_tools = new fm_shared_module_tools();

?>