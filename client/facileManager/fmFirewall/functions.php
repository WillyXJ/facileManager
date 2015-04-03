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

	echo fM('  --> Detecting firewall...');
	$app = detectFWVersion(true);
	if ($app === null) {
		echo "failed\n\n";
		echo fM("Cannot find a supported firewall - please check the README document for supported firewalls.  Aborting.\n");
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
	$data['server_interfaces'] = implode(';', getInterfaceNames(PHP_OS));
	
	echo fM("\n  --> Detection complete.  Continuing installation.\n\n");
	
	/** Update via cron or http/s? */
	$update_choices = array('c', 's', 'h');
	while (!isset($update_method)) {
		echo fM('Will ' . $data['server_name'] . ' get updates via cron, ssh, or http(s) [c|s|h]? ');
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
	global $proto, $debug;
	
	if ($data['dryrun'] && $debug) echo fM("Dryrun mode (nothing will be written to disk)\n\n");
	
	$raw_data = getPostData($url, $data);
	$raw_data = $data['compress'] ? @unserialize(gzuncompress($raw_data)) : @unserialize($raw_data);
	if (!is_array($raw_data)) {
		if ($debug) echo fM($raw_data);
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
	
	$runas = 'root';
	$chown_files = array($server_root_dir);
	
	/** Install the new files */
	installFiles($runas, $chown_files, $files, $data['dryrun']);
	
	$message = "Reloading the server\n";
	if ($debug) echo fM($message);
	$rc_script = str_replace('__FILE__', $server_config_file, getStartupScript($server_type));
	$message = "$rc_script\n";
	if ($debug) echo fM($message);
	if (!$data['dryrun']) {
		addLogEntry($message);
		$rc_script = str_replace('__FILE__', $server_config_file, getStartupScript($server_type));
		if ($rc_script === false) {
			$last_line = "Cannot locate the start script\n";
			if ($debug) echo fM($last_line);
			addLogEntry($last_line);
			$retval = true;
		} else {
			$last_line = system($rc_script . ' 2>&1', $retval);
			addLogEntry($last_line);
		}
		if ($retval) {
			$message = "There was an error reloading the firewall - please check the logs for details\n";
			if ($debug) echo fM($message);
			addLogEntry($message);
			return false;
		} else {
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
								'ipfw' => 'ipfw',
								'ipfilter' => 'ipf',
								'pf' => 'pf'
							);
	
	foreach($supported_firewalls as $type => $app) {
		if (findProgram($app)) return array('type'=>$type, 'app'=>$app);
	}
	
	return null;
}


function detectFWVersion($return_array = false) {
	$fw = detectFirewallType();
	$fw_flags = array('iptables' => '-V | awk -Fv "{print \$NF}"',
						'pf' => null,
						'ipfw' => null,
						'ipf' => '-V | head -1 | awk -Fv \'{print $NF}\''
					);
	
	if ($fw) {
		$version = ($fw_flags[$fw['app']]) ? trim(shell_exec(findProgram($fw['app']) . ' ' . $fw_flags[$fw['app']])) : null;
		if ($return_array) {
			return array('server' => $fw, 'app_version' => $version);
		} else return trim($version);
	}
	
	return null;
}


function moduleAddServer($url, $data) {
	/** Add the server to the account */
	$app = detectFWVersion(true);
	if ($app === null) {
		echo "failed\n\n";
		echo fM("Cannot find a supported firewall - please check the README document for supported firewalls.  Aborting.\n");
		exit(1);
	}
	$data['server_type'] = $app['server']['type'];
	$data['server_version'] = $app['app_version'];
	$raw_data = getPostData(str_replace('genserial', 'addserial', $url), $data);
	$raw_data = $data['compress'] ? @unserialize(gzuncompress($raw_data)) : @unserialize($raw_data);
	if (!is_array($raw_data)) {
		if (!$raw_data) echo "An error occurred\n";
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


function getStartupScript($fw) {
	$distros = array(
		'iptables' => array(
			'Arch'      => findProgram('systemctl') . ' reload iptables',
			'Debian'    => findProgram('iptables-restore') . ' < __FILE__',
			'Ubuntu'    => findProgram('iptables-restore') . ' < __FILE__',
			'Fubuntu'   => findProgram('iptables-restore') . ' < __FILE__',
			'Fedora'    => '/etc/init.d/iptables restart',
			'Redhat'    => '/etc/init.d/iptables restart',
			'CentOS'    => '/etc/init.d/iptables restart',
			'ClearOS'   => '/etc/init.d/iptables restart',
			'Oracle'    => '/etc/init.d/iptables restart',
			'SUSE'      => findProgram('service') . ' SuSEfirewall2 restart',
			'Gentoo'    => findProgram('iptables-restore') . ' < __FILE__',
			'Slackware' => '/etc/rc.d/rc.iptables restart'
		),
		'pf' => array(
			'FreeBSD'   => findProgram('pfctl') . ' -d -F all -f __FILE__',
			'OpenBSD'   => findProgram('pfctl') . ' -d -F all -f __FILE__'
		),
		'ipfilter' => array(
			'FreeBSD'   => findProgram('ipf') . ' -Fa -f __FILE__',
			'SunOS'     => findProgram('ipf') . ' -Fa -f __FILE__'
		),
		'ipfw' => array(
			'FreeBSD'   => findProgram('sh') . ' __FILE__',
			'Apple'     => findProgram('sh') . ' __FILE__'
		)
	);
	
	$os = detectOSDistro();
	
	if (array_key_exists($os, $distros[$fw])) {
		return $distros[$fw][$os];
	}
	
	return false;
}


function getInterfaceNames($os) {
	$interfaces = null;
	
	switch(PHP_OS) {
		case 'Linux':
			$command = findProgram('ifconfig') . ' | grep Link';
			break;
		case 'Darwin':
		case 'FreeBSD':
		case 'OpenBSD':
		case 'NetBSD':
			$command = findProgram('netstat') . ' -i | grep Link';
			break;
		case 'SunOS':
			$command = findProgram('ifconfig') . ' -a | grep flags | sed -e \'s/://g\'';
			break;
		default:
			return null;
			break;
	}
	
	exec($command . ' | awk "{print \$1}" | sort | uniq', $interfaces);
	
	return $interfaces;
}


?>