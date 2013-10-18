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

/**
 * fmFirewall Functions
 *
 * @package fmFirewall
 * @subpackage Client
 *
 */


/**
 * Prints the module help file
 *
 * @since 1.0
 * @package fmFirewall
 *
 * @return null
 */
function printModuleHelp () {
	global $argv;
	
	echo <<<HELP
  -n|dryrun      Do not save - just output what will happen
  -b|buildconf   Build named config and zone files
  -c|cron        Run in cron mode
  
HELP;
}


function installFMModule($module_name, $proto, $compress, $data, $server_location, $url) {
	global $argv;
	
	extract($server_location);

	echo "  --> Detecting firewall...";
	$app = detectFWVersion(true);
	if ($app === null) {
		echo "failed\n\n";
		echo "Cannot find a supported firewall - please check the README document for supported firewalls.  Aborting.\n";
		exit(1);
	}
	extract($app);
	$data['server_type'] = $server['type'];
	if (versionCheck($app_version, $proto . '://' . $hostname . '/' . $path, $compress) == true) {
		echo 'ok (' . $server['type'] . ")\n";
	} else {
		echo "failed\n\n";
		echo $server['type'] . ' ' . $app_version . " is not supported.\n";
		exit(1);
	}
	$data['server_version'] = $app_version;
	
	echo "\n  --> Detection complete.  Continuing installation.\n\n";
	
	/** Update via cron or http/s? */
	$update_choices = array('c', 'h');
	while (!isset($update_method)) {
		echo 'Will ' . $data['server_name'] . ' get updates via cron or http(s) [c|h]? ';
		$update_method = trim(strtolower(fgets(STDIN)));
		
		/** Must be a valid option */
		if (!in_array($update_method, $update_choices)) unset($update_method);
	}
	
	/** Handle the update method */
	$data['server_update_method'] = processUpdateMethod($module_name, $update_method);

	$raw_data = getPostData(str_replace('genserial', 'addserial', $url), $data);
	$raw_data = $data['compress'] ? @unserialize(gzuncompress($raw_data)) : @unserialize($raw_data);
	
	return $data;
}


function buildConf($url, $data) {
	global $proto, $debug;
	
	if ($data['dryrun'] && $debug) echo "Dryrun mode (nothing will be written to disk).\n\n";
	
	$raw_data = getPostData($url, $data);
	$raw_data = $data['compress'] ? @unserialize(gzuncompress($raw_data)) : @unserialize($raw_data);
	if (!is_array($raw_data)) {
		if ($debug) echo $raw_data;
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
		
	if ($debug) echo "Setting directory and file permissions for $runas.\n";
	if (!$data['dryrun']) {
		/** chown the files/dirs */
		$chown_files = array($server_root_dir, $server_zones_dir);
		foreach($chown_files as $file) {
			@chown($file, $runas);
		}
	}
		
		/** Remove previous files so there are no stale files */
//		foreach (scandir($server_zones_dir) as $item) {
//			if ($item == '.' || $item == '..') continue;
//			unlink($server_zones_dir . DIRECTORY_SEPARATOR . $item);
//		}
		
	/** Process the files */
	if (count($files)) {
		foreach($files as $filename => $contents) {
			if ($debug) echo "Writing $filename.\n";
			if (!$data['dryrun']) {
				@mkdir(dirname($filename), 0755, true);
				@chown(dirname($filename), $runas);
				file_put_contents($filename, $contents);
				@chown($filename, $runas);
			}
		}
	} else {
		echo "There are no files to save. Aborting.\n";
		exit(1);
	}
	
	/** Reload the server */
	if ($debug) echo "Reloading the server.\n";
	if (!$data['dryrun']) {
		if (shell_exec('ps -A | grep named | grep -vc grep') > 0) {
			system(findProgram('rndc') . ' reload 2>&1 > /dev/null', $retval);
		} else {
			if ($debug) echo "The server is not running. Attempting to start it.\n";
			$named_rc_script = getStartupScript();
			if ($named_rc_script === false) {
				if ($debug) echo "Cannot locate the start script.\n";
				$retval = true;
			} else {
				system($named_rc_script . ' 2>&1', $retval);
			}
		}
		if ($retval) {
			if ($debug) echo "There was an error reloading the server.  Please check the logs for details.\n";
			return false;
		} else {
			/** Only update reloaded zones */
			$data['built_domain_ids'] = $built_domain_ids;
			if (!isset($server_build_all)) {
				$data['zone'] = 'update';
			}
			
			/** Update the server with a successful reload */
			$data['action'] = 'update';
			$raw_update = getPostData($url, $data);
			$raw_update = $data['compress'] ? @unserialize(gzuncompress($raw_update)) : @unserialize($raw_update);
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


function detectFirewallType() {
	$supported_firewalls = array('iptables'=>'iptables',
								'pf' => 'pfctl',
								'ipfw' => 'ipfw',
								'ipfilter' => 'ipf'
							);
	
	foreach($supported_firewalls as $type => $app) {
		if (findProgram($app)) return array('type'=>$type, 'app'=>$app);
	}
	
	return null;
}


function detectFWVersion($return_array = false) {
	$fw = detectFirewallType();
	$fw_flags = array('iptables'=>'-V | awk -F v "{print \$NF}"',
						'pf'=>'-v',
						'ipfw'=>'-v',
						'ipf'=>'-v'
					);
	
	if ($fw) {
		$version = trim(shell_exec(findProgram($fw['app']) . ' ' . $fw_flags[$fw['app']]));
		if ($return_array) {
			return array('server' => $fw, 'app_version' => $version);
		} else return trim($version);
	}
	
	return null;
}


function moduleAddServer($url, $data) {
	/** Add the server to the account */
	$servertype = detectFirewallType();
//	$data['server_type'] = is_array($servertype) ? $servertype['type'] : $servertype;
	$app = detectFWVersion(true);
	if ($app === null) {
		echo "failed\n\n";
		echo "Cannot find a supported firewall - please check the README document for supported firewalls.  Aborting.\n";
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


function versionCheck($app_version, $serverhost, $compress) {
	$url = $serverhost . '/buildconf';
	$data['action'] = 'version_check';
	$server_type = detectFirewallType();
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
		'Apple'     => 'launchctl start org.isc.named'
		);
	
	$os = detectOSDistro();
	
	if (array_key_exists($os, $distros)) {
		return $distros[$os];
	}
	
	return false;
}


?>