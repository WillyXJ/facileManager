<?php

class fm_tools {
	
	/**
	 * Installs a module
	 */
	function installModule($module_name = null) {
		global $__FM_CONFIG;
		
		if (!$module_name) {
			return '<p>No module was selected to be installed.</p>';
		}
		
		$install_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $module_name . DIRECTORY_SEPARATOR . 'install.php';
		if (file_exists($install_file)) {
			include($install_file);
			
			/** Include module variables */
			@include(ABSPATH . 'fm-modules/' . $module_name . '/variables.inc.php');
			
			$function = 'install' . $module_name . 'Schema';
			if (function_exists($function)) {
				$output = $function(null, $__FM_CONFIG['db']['name'], $module_name, false);
			}
			if (!strpos($output, 'Success!')) {
				return '<p>' . $module_name . ' installation failed!</p>';
			}
			
			addLogEntry("$module_name {$__FM_CONFIG[$module_name]['version']} was born.", $module_name);
		} else return '<p>No installation file found for ' . $module_name . '.</p>';
		
		return '<p>' . $module_name . ' was installed successfully!</p>';
	}
	
	/**
	 * Upgrades a module
	 */
	function upgradeModule($module_name = null) {
		global $fmdb;
		
		if (!$module_name) {
			return '<p>No module was selected to be upgraded.</p>';
		}
		
		$upgrade_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $module_name . DIRECTORY_SEPARATOR . 'upgrade.php';
		if (file_exists($upgrade_file)) {
			include($upgrade_file);
			
			/** Include module variables */
			@include(ABSPATH . 'fm-modules/' . $module_name . '/variables.inc.php');
			
			$function = 'upgrade' . $module_name . 'Schema';
			if (function_exists($function)) {
				$output = $function($module_name);
			}
			if ($output != 'Success') {
				return '<p>' . $module_name . ' upgrade failed!</p>';
			} else {
				$query = "UPDATE `fm_options` SET option_value='{$__FM_CONFIG[$module_name]['version']}' WHERE option_name='{$module_name}_version'";
				$fmdb->query($query);
				setOption($module_name . '_version_check', array('timestamp' => date("Y-m-d H:i:s", strtotime("2 days ago")), 'data' => null), 'update', 0);
			}

			addLogEntry("$module_name was upgraded to {$__FM_CONFIG[$module_name]['version']}.", $module_name);
		}
		
		return '<p>' . $module_name . ' was upgraded successfully! Make sure you upgrade your clients with the updated client files (if applicable).</p>';
	}
	
	/**
	 * Manages a module
	 */
	function manageModule($action = null, $module_name = null) {
		global $__FM_CONFIG;
		
		if (!$module_name || !in_array($module_name, getAvailableModules())) {
			return false;
		}
		
		$current_active_modules = getOption('fm_active_modules', $_SESSION['user']['account_id']);
		$command = is_array($current_active_modules) ? 'update' : 'insert';
		
		switch($action) {
			case 'activate':
				if (in_array($module_name, getActiveModules())) return;
				
//				if ($current_active_modules === false) $current_active_modules = array();
				
				$current_active_modules[] = $module_name;
				return setOption('fm_active_modules', $current_active_modules, $command, $_SESSION['user']['account_id']);

				break;
			case 'deactivate':
				if (!in_array($module_name, getActiveModules())) return;
				
				$new_array = array();
				foreach ($current_active_modules as $module) {
					if ($module == $module_name) continue;
					$new_array[] = $module;
				}

				return setOption('fm_active_modules', $new_array, 'update', $_SESSION['user']['account_id']);

				break;
			case 'uninstall':
				if (!in_array($module_name, getAvailableModules())) return;
				
				if (function_exists('uninstallModuleSchema')) {
					$output = uninstallModuleSchema($__FM_CONFIG['db']['name'], $module_name);
				}
				if ($output != 'Success') return false;
				
				return true;

				break;
		}
		
		return false;
	}
	
	/**
	 * Cleans up the database
	 */
	function cleanupDatabase() {
		global $fmdb, $__FM_CONFIG, $fm_name;
		
		$record_count = 0;
		
		/** Remove deleted items */
		$fmdb->get_results("SHOW TABLES");
		
		$raw_table_list = $fmdb->last_result;
		foreach ($raw_table_list as $table_object) {
			$table_array = get_object_vars($table_object);
			$table = $table_array['Tables_in_' . strtolower($__FM_CONFIG['db']['name'])];
			if (array_key_exists($table, $__FM_CONFIG['clean']['prefixes'])) {
				$query = 'DELETE FROM ' . $table  . ' WHERE ' . $__FM_CONFIG['clean']['prefixes'][$table] . '_status = "deleted"';
				$fmdb->query($query);
				$record_count += $fmdb->rows_affected;
			}
		}
		
		/** Remove old password reset requests */
		$time = date("Y-m-d H:i:s", strtotime($__FM_CONFIG['clean']['days'] . ' days ago'));
		$query = 'DELETE FROM `fm_pwd_resets` WHERE `pwd_timestamp`<"' . $time . '"';
		$fmdb->query($query);
		$record_count += $fmdb->rows_affected;
		
		/** Remove permissions for deleted users */
		$query = 'DELETE FROM `fm_perms` WHERE user_id NOT IN (SELECT user_id FROM fm_users)';
		$fmdb->query($query);
		$record_count += $fmdb->rows_affected;
		
		addLogEntry('Cleaned up the database.', $fm_name);
		return 'Total number of records purged from the database: <b>' . $record_count . '</b>';
	}

	/**
	 * Backs up the database
	 */
	function backupDatabase() {
		global $__FM_CONFIG, $allowed_to_run_tools, $fm_name;
		
		if (!$allowed_to_run_tools) return '<p class="error">You are not authorized to run these tools.</p>';
		
		/** Temporary fix for MySQL 5.6 warnings */
		$exclude_warnings = array('Warning: Using a password on the command line interface can be insecure.' . "\n");
		
		$curdate = date("Y-m-d_H.i.s");
		$sql_file = '/tmp/' . $__FM_CONFIG['db']['name'] . '_' . $curdate . '.sql';
		$error_log = str_replace('.sql', '.err', $sql_file);
		
		$mysqldump = findProgram('mysqldump');
		if (!$mysqldump) return '<p class="error">mysqldump is not found on ' . php_uname('n') . '.</p>';
		
		$command_string = "$mysqldump --opt -Q -h {$__FM_CONFIG['db']['host']} -u {$__FM_CONFIG['db']['user']} -p{$__FM_CONFIG['db']['pass']} {$__FM_CONFIG['db']['name']} > /tmp/{$__FM_CONFIG['db']['name']}_$curdate.sql 2>$error_log";
		@system($command_string, $retval);
		$retarr = @file_get_contents($error_log);
		
		if ($retval) {
			@unlink($error_log);
			@unlink($sql_file);
			return '<p class="error">' . nl2br(str_replace($exclude_warnings, '', $retarr)) . '</p>';
		}
		
		compressFile($sql_file, @file_get_contents($sql_file));
		@unlink($error_log);
		@unlink($sql_file);
		
		addLogEntry('Backed up the database.', $fm_name);

		sendFileToBrowser($sql_file . '.gz');
	}

}

if (!isset($fm_tools))
	$fm_tools = new fm_tools();

?>