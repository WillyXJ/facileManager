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

class fm_module_tools {
	
	/**
	 * Tests server connectivity
	 */
	function connectTests() {
		global $fmdb, $__FM_CONFIG;
		
		$return = null;
		
		/** Load ssh key for use */
		$ssh_key = getOption('ssh_key_priv', $_SESSION['user']['account_id']);
		$temp_ssh_key = sys_get_temp_dir() . '/fm_id_rsa';
		if ($ssh_key) {
			$ssh_key_loaded = @file_put_contents($temp_ssh_key, $ssh_key);
			@chmod($temp_ssh_key, 0400);
		}

		/** Get server list */
		$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_name', 'server_');
		
		/** Process server list */
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;
		for ($x=0; $x<$num_rows; $x++) {
			$return .= 'Running tests for ' . $results[$x]->server_name . "\n";
			
			/** ping tests */
			$return .= "\tPing:\t\t";
			if (pingTest($results[$x]->server_name)) $return .=  'success';
			else $return .=  'failed';
			$return .=  "\n";

			/** remote port tests */
			$return .= "\tRemote Port:\t";
			if ($results[$x]->server_update_method != 'cron') {
				if (socketTest($results[$x]->server_name, $results[$x]->server_update_port, 10)) {
					$return .= 'success (tcp/' . $results[$x]->server_update_port . ")\n";
					
					if ($results[$x]->server_update_method == 'ssh') {
						$return .= "\tSSH Login:\t";
						if (!$ssh_key) {
							$return .= 'no SSH key defined';
						} elseif ($ssh_key_loaded === false) {
							$return .= 'could not load SSH key into ' . $temp_ssh_key;
						} else {
							exec(findProgram('ssh') . " -t -i $temp_ssh_key -o 'StrictHostKeyChecking no' -p {$results[$x]->server_update_port} -l fm_user {$results[$x]->server_name} uptime", $post_result, $retval);
							if ($retval) {
								$return .= 'ssh key login failed';
							} else {
								$return .= 'success';
							}
						}
					} else {
						/** php tests */
						$return .= "\thttp page:\t\t";
						$php_result = getPostData($results[$x]->server_update_method . '://' . $results[$x]->server_name . '/' .
									$_SESSION['module'] . '/reload.php', null);
						if ($php_result == 'Incorrect parameters defined.') $return .= 'success';
						else $return .= 'failed';
					}
					
				} else $return .=  'failed (tcp/' . $results[$x]->server_update_port . ')';
			} else $return .= 'skipping (host updates via cron)';
			$return .=  "\n\n";
		}
		
		return $return;
	}
	
}

if (!isset($fm_module_tools))
	$fm_module_tools = new fm_module_tools();

?>
