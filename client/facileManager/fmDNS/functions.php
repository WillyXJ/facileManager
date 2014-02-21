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
*/

/**
 * fmDNS Functions
 *
 * @package fmDNS
 * @subpackage Client
 *
 */


/**
 * Prints the module help file
 *
 * @since 1.0
 * @package facileManager
 *
 * @return null
 */
function printModuleHelp () {
	global $argv;
	
	echo <<<HELP
  -n|dryrun      Do not save - just output what will happen
  -b|buildconf   Build named config and zone files
  -z|zones       Build all associated zone files
  -c|cron        Run in cron mode
     id=XX       Specify the individual DomainID to build and reload
  
HELP;
}


function installFMModule($module_name, $proto, $compress, $data, $server_location, $url) {
	global $argv;
	
	extract($server_location);

	echo "  --> Running version tests...";
	$app = detectDaemonVersion(true);
	if ($app === null) {
		echo "failed\n\n";
		echo "Cannot find a supported DNS server - please check the README document for supported DNS servers.  Aborting.\n";
		exit(1);
	}
	extract($app);
	$data['server_type'] = $server['type'];
	if (versionCheck($app_version, $proto . '://' . $hostname . '/' . $path, $compress) == true) {
		echo "ok\n";
	} else {
		echo "failed\n\n";
		echo "$app_version is not supported.\n";
		exit(1);
	}
	$data['server_version'] = $app_version;
	
	echo "\n  --> Tests complete.  Continuing installation.\n\n";
	
	/** Update via cron or http/s? */
	$update_choices = array('c', 's', 'h');
	while (!isset($update_method)) {
		echo 'Will ' . $data['server_name'] . ' get updates via cron, ssh, or http(s) [c|s|h]? ';
		$update_method = trim(strtolower(fgets(STDIN)));
		
		/** Must be a valid option */
		if (!in_array($update_method, $update_choices)) unset($update_method);
	}
	
	/** Handle the update method */
	$data['server_update_method'] = processUpdateMethod($module_name, $update_method, $data, $url);

	$raw_data = getPostData(str_replace('genserial', 'addserial', $url), $data);
	$raw_data = $data['compress'] ? @unserialize(gzuncompress($raw_data)) : @unserialize($raw_data);
	
	return $data;
}


function buildConf($url, $data) {
	global $proto, $debug, $purge;
	
	if ($data['dryrun'] && $debug) echo "Dryrun mode (nothing will be written to disk).\n\n";
	
	$raw_data = getPostData($url, $data);
	$raw_data = $data['compress'] ? @unserialize(gzuncompress($raw_data)) : @unserialize($raw_data);
	if (!is_array($raw_data)) {
		if ($debug) echo $raw_data;
		addLogEntry($raw_data);
		exit(1);
	}
	if ($debug) {
		foreach ($raw_data['files'] as $filename => $contents) {
			echo str_repeat('=', 50) . "\n";
			echo $filename . ":\n";
			echo str_repeat('=', 50) . "\n";
			echo $contents . "\n\n";
		}
	}
	
	extract($raw_data, EXTR_SKIP);
	
	$runas = ($server_run_as_predefined == 'as defined:') ? $server_run_as : $server_run_as_predefined;
	$chown_files = array($server_root_dir, $server_zones_dir);
		
	/** Remove previous files so there are no stale files */
	if ($purge || ($purge_config_files == 'yes' && $server_update_config == 'conf')) {
		/** Server config files */
		$path_parts = pathinfo($server_config_file);
		if (version_compare(PHP_VERSION, '5.2.0', '<')) {
			$path_parts['filename'] = str_replace('.' . $path_parts['extension'], '', $path_parts['basename']);
		}
		$config_file_pattern = $path_parts['dirname'] . DIRECTORY_SEPARATOR . $path_parts['filename'] . '.*';
		exec('ls ' . $config_file_pattern, $config_file_match);
		foreach ($config_file_match as $config_file) {
			$message = "Deleting $config_file.\n";
			if ($debug) echo $message;
			if 	(!$data['dryrun']) {
				addLogEntry($message);
				unlink($config_file);
			}
		}
		
		/** Zone files */
		foreach (scandir($server_zones_dir) as $item) {
			if (in_array($item, array('.', '..'))) continue;
			$full_path_file = $server_zones_dir . DIRECTORY_SEPARATOR . $item;
			$message = "Deleting $full_path_file.\n";
			if ($debug) echo $message;
			if 	(!$data['dryrun']) {
				addLogEntry($message);
				unlink($full_path_file);
			}
		}
	}
	
	/** Install the new files */
	installFiles($runas, $chown_files, $files, $data['dryrun']);
	
	/** Reload the server */
	$message = "Reloading the server.\n";
	if ($debug) echo $message;
	if (!$data['dryrun']) {
		addLogEntry($message);
		if (shell_exec('ps -A | grep named | grep -vc grep') > 0) {
			$last_line = system(findProgram('rndc') . ' reload 2>&1', $retval);
			addLogEntry($last_line);
		} else {
			$message = "The server is not running. Attempting to start it.\n";
			if ($debug) echo $message;
			addLogEntry($message);
			$named_rc_script = getStartupScript();
			if ($named_rc_script === false) {
				$last_line = "Cannot locate the start script.\n";
				if ($debug) echo $last_line;
				addLogEntry($last_line);
				$retval = true;
			} else {
				$last_line = system($named_rc_script . ' 2>&1', $retval);
			}
		}
		if ($retval) {
			addLogEntry($last_line);
			$message = "There was an error reloading the server.  Please check the logs for details.\n";
			if ($debug) echo $message;
			addLogEntry($message);
			return false;
		} else {
			/** Only update reloaded zones */
			$data['reload_domain_ids'] = $reload_domain_ids;
			if (!isset($server_build_all)) {
				$data['zone'] = 'update';
			}
			
			/** Update the server with a successful reload */
			$data['action'] = 'update';
			$raw_update = getPostData($url, $data);
			$raw_update = $data['compress'] ? @unserialize(gzuncompress($raw_update)) : @unserialize($raw_update);
			if ($debug) echo $raw_update;
		}
	}
	return true;
}


function findFile($file) {
	$path = array('/etc/httpd/conf', '/usr/local/etc/apache', '/usr/local/etc/apache2', '/usr/local/etc/apache22',
				'/etc', '/usr/local/etc', '/etc/apache2', '/etc', '/etc/named', '/etc/namedb', '/etc/bind'
				);

	while ($this_path = current($path)) {
		if (is_file("$this_path/$file")) {
			return "$this_path/$file";
		}
		next($path);
	}

	return false;
}


function detectServerType() {
	$supported_servers = array('bind9'=>'named');
	
	foreach($supported_servers as $type => $app) {
		if (findProgram($app)) return array('type'=>$type, 'app'=>$app);
	}
	
	return null;
}


function moduleAddServer($url, $data) {
	/** Attempt to determine default variables */
	$named_conf = findFile('named.conf');
	$data['server_run_as_predefined'] = 'named';
	if ($named_conf) {
		if (function_exists('posix_getgrgid')) {
			if ($run_as = posix_getgrgid(filegroup($named_conf))) {
				$data['server_run_as_predefined'] = $run_as['name'];
			}
		}
		$data['server_config_file'] = $named_conf;
		$raw_root = explode('"', shell_exec('grep directory ' . $named_conf));
		
		if (count($raw_root) <= 1) {
			if (file_exists($named_conf . '.options')) {
				$raw_root = explode('"', shell_exec('grep directory ' . $named_conf . '.options'));
			}
		}
		$data['server_root_dir'] = @trim($raw_root[1]);
		
		$data['server_zones_dir'] = (dirname($named_conf) == '/etc') ? null : dirname($named_conf) . '/zones';
	}
	
	/** Add the server to the account */
	$app = detectDaemonVersion(true);
	if ($app === null) {
		echo "failed\n\n";
		echo "Cannot find a supported DNS server - please check the README document for supported DNS servers.  Aborting.\n";
		exit(1);
	}
	$data['server_type'] = $app['server']['type'];
	$data['server_version'] = $app['app_version'];
	$raw_data = getPostData(str_replace('genserial', 'addserial', $url), $data);
	$raw_data = $data['compress'] ? @unserialize(gzuncompress($raw_data)) : @unserialize($raw_data);
	if (!is_array($raw_data)) {
		if (!$raw_data) echo "An error occurred.\n";
		else echo $raw_data;
		exit(1);
	}
	
	return array('data' => $data, 'add_result' => "Success\n");
}


function detectDaemonVersion($return_array = false) {
	$dns_server = detectServerType();
	$dns_flags = array('named'=>'-v | sed "s/BIND //"');
	
	if ($dns_server) {
		$version = trim(shell_exec(findProgram($dns_server['app']) . ' ' . $dns_flags[$dns_server['app']]));
		if ($return_array) {
			return array('server' => $dns_server, 'app_version' => $version);
		} else return trim($version);
	}
	
	return null;
}


function versionCheck($app_version, $serverhost, $compress) {
	$url = $serverhost . '/buildconf';
	$data['action'] = 'version_check';
	$server_type = detectServerType();
	$data['server_type'] = $server_type['type'];
	$data['server_version'] = $app_version;
	$data['compress'] = $compress;
	
	$raw_data = getPostData($url, $data);
	$raw_data = $compress ? @unserialize(gzuncompress($raw_data)) : @unserialize($raw_data);
	
	return $raw_data;
}


function getStartupScript() {
	$distros = array(
		'Arch'      => '/etc/rc.d/named start',
		'Debian'    => '/etc/init.d/bind9 start',
		'Ubuntu'    => '/etc/init.d/bind9 start',
		'Fubuntu'   => '/etc/init.d/bind9 start',
		'Fedora'    => '/etc/init.d/named start',
		'Redhat'    => '/etc/init.d/named start',
		'CentOS'    => '/etc/init.d/named start',
		'ClearOS'   => '/etc/init.d/named start',
		'Oracle'    => '/etc/init.d/named start',
		'SUSE'      => '/etc/init.d/named start',
		'Gentoo'    => '/etc/init.d/named start',
		'Slackware' => '/etc/rc.d/rc.bind start',
		'FreeBSD'   => '/etc/rc.d/named start',
		'Apple'     => findProgram('launchctl') . ' start org.isc.named'
		);
	
	$os = detectOSDistro();
	
	if (array_key_exists($os, $distros)) {
		return $distros[$os];
	}
	
	return false;
}


?>