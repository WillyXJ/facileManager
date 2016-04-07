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

class fm_shared_module_buildconf {
	
	/**
	 * Displays server configs
	 *
	 * @since 2.2
	 * @package facileManager
	 *
	 * @param array $raw_data Array containing files and contents
	 * @return string
	 */
	function processConfigs($raw_data) {
		global $fm_module_buildconf;
		
		$preview = $check_status = null;
		
		if (method_exists($fm_module_buildconf, 'processConfigsChecks')) {
			$check_status = $fm_module_buildconf->processConfigsChecks($raw_data);
		}
		foreach ($raw_data['files'] as $filename => $contents) {
			$preview .= str_repeat('=', 75) . "\n";
			$preview .= $filename . ":\n";
			$preview .= str_repeat('=', 75) . "\n";
			if (strpos($check_status, 'error') !== false) {
				$i = 1;
				$contents_array = explode("\n", $contents);
				foreach ($contents_array as $line) {
					$preview .= '<font color="#ccc">' . str_pad($i, strlen(count($contents_array)), ' ', STR_PAD_LEFT) . '</font> ';
					if (strpos($check_status, "$filename:$i:") !== false) {
						$preview .= sprintf('<font color="red">%s</font>', $line);
					} else {
						$preview .= $line;
					}
					$preview .= "\n";
					$i++;
				}
				$preview .= "\n";
			} else {
				$preview .= "$contents\n\n";
			}
		}
		
		return array($preview, $check_status);
	}
	
	/**
	 * Generates the server config and updates the firewall server
	 *
	 * @since 2.2
	 * @package facileManager
	 */
	function buildServerConfig($post_data) {
		global $fmdb, $__FM_CONFIG;
		
		/** Get datetime formatting */
		$date_format = getOption('date_format', $_SESSION['user']['account_id']);
		$time_format = getOption('time_format', $_SESSION['user']['account_id']);
		
		setTimezone();
		
		$files = array();
		$server_serial_no = sanitize($post_data['SERIALNO']);
		extract($post_data);

		$data->server_build_all = true;
		
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', $server_serial_no, 'server_', 'server_serial_no');
		if ($fmdb->num_rows) {
			$server_result = $fmdb->last_result;
			$data = $server_result[0];
			extract(get_object_vars($data), EXTR_SKIP);
			
			/** Disabled server */
			if ($server_status != 'active') {
				$error = "Server is $server_status.\n";
				if ($compress) echo gzcompress(serialize($error));
				else echo serialize($error);
				
				exit;
			}
			
			include(ABSPATH . 'fm-includes/version.php');
			
			$config = '# This file was built using ' . $_SESSION['module'] . ' ' . $__FM_CONFIG[$_SESSION['module']]['version'] . ' on ' . date($date_format . ' ' . $time_format . ' e') . "\n\n";

			basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_order_id', 'policy_', "AND server_serial_no=$server_serial_no AND policy_status='active'");
			if ($fmdb->num_rows) {
				$policy_count = $fmdb->num_rows;
				$policy_result = $fmdb->last_result;
				
				$function = $server_type . 'BuildConfig';
				$config .= $this->$function($policy_result, $policy_count);
			}




			$data->files[$server_config_file] = $config;
			if (is_array($files)) {
				$data->files = array_merge($data->files, $files);
			}
			
			return array(get_object_vars($data), null);
		}
		
		/** Bad server */
		$error = "Server is not found.\n";
		if ($compress) echo gzcompress(serialize($error));
		else echo serialize($error);
	}
	
	
	/**
	 * Figures out what files to update on the firewall
	 *
	 * @since 2.2
	 * @package facileManager
	 *
	 * @param array $post_data Array containing data posted by client
	 * @return string
	 */
	function buildCronConfigs($post_data) {
		global $fmdb, $__FM_CONFIG;
		
		$server_serial_no = sanitize($post_data['SERIALNO']);
		extract($post_data);
		
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', $server_serial_no, 'server_', 'server_serial_no');
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			$data = $result[0];
			extract(get_object_vars($data), EXTR_SKIP);
			
			/** Check if this server is configured for cron updates */
			if ($server_update_method != 'cron') {
				$error = "This server is not configured to receive updates via cron.\n";
				if ($compress) echo gzcompress(serialize($error));
				else echo serialize($error);
				return;
			}
			
			/** Check if there are updates */
			if ($server_update_config == 'no') {
				$error = "No updates found.\n";
				if ($compress) echo gzcompress(serialize($error));
				else echo serialize($error);
				return;
			}
			
			/** process server config build */
			return $this->buildServerConfig($post_data);
			
		}
		
		/** Bad server */
		$error = "Server is not found.\n";
		if ($compress) echo gzcompress(serialize($error));
		else echo serialize($error);
	}
	

	/**
	 * Updates tables to reset flags
	 *
	 * @since 2.2
	 * @package facileManager
	 *
	 * @param array $post_data Array containing data posted by client
	 * @return null
	 */
	function updateReloadFlags($post_data) {
		global $fmdb, $__FM_CONFIG, $fm_module_buildconf;
		
		$server_serial_no = sanitize($post_data['SERIALNO']);
		extract($post_data);
		
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', $server_serial_no, 'server_', 'server_serial_no');
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			$data = $result[0];
			extract(get_object_vars($data), EXTR_SKIP);
			
			$reset_build = setBuildUpdateConfigFlag($server_serial_no, 'no', 'build');
			$reset_update = setBuildUpdateConfigFlag($server_serial_no, 'no', 'update');
			$msg = "Success.\n";
			
			if (method_exists($fm_module_buildconf, 'moduleUpdateReloadFlags')) {
				$fm_module_buildconf->moduleUpdateReloadFlags($server_serial_no, $post_data);
			}
		} else $msg = "Server is not found.\n";
		
		if ($compress) echo gzcompress(serialize($msg));
		else echo serialize($msg);
	}
	

}

if (!isset($fm_shared_module_buildconf))
	$fm_shared_module_buildconf = new fm_shared_module_buildconf();

?>
