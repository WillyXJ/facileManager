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
 | fmDHCP: Easily manage one or more ISC DHCP servers                      |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmDHCP/                            |
 +-------------------------------------------------------------------------+
*/

/**
 * fmDHCP Functions
 *
 * @package fmDHCP
 * @subpackage Client
 *
 */


/**
 * Prints the module help file
 *
 * @since 0.1
 * @package facileManager
 *
 * @return null
 */
function printModuleHelp () {
	global $argv;
	
	echo <<<HELP
  -l (dump|delete)            Specify what to do with the leases
                                Examples: client.php -l dump
                                          client.php -l delete=10.1.1.100
  -o (human|web)              Specify the output type (default: human)
                                Example: client.php -l dump -o human

HELP;
}


/**
 * Adds the server to the database
 *
 * @since 0.1
 * @package facileManager
 *
 * @param string $url URL to post data to
 * @param array $data Data to post
 * @return array
 */
function installFMModule($module_name, $proto, $compress, $data, $server_location, $url) {
	global $argv, $update_method;
	
	/**
	 * Add any module-specific installation checks here
	 */

	 extract($server_location);

	echo fM('  --> Running version tests...');
	$output = versionCheck($data['server_version'], $proto . '://' . $hostname . '/' . $path, $compress);
	if ($output === true) {
		echo "ok\n";
	} else {
		echo "failed\n\n";
		echo "{$data['server_type']} {$data['server_version']} is not supported.\n";
		exit(1);
	}
	
	return $data;
}


/**
 * Finish building the server config with module-specific data
 *
 * @since 0.1
 * @package facileManager
 * @subpackage fmDHCP
 *
 * @param string $url URL to post data to
 * @param array $data Data to post
 * @return boolean
 */
function buildConf($url, $data) {
	global $proto, $debug;
	
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
	
	/** Install the new files */
	installFiles($files, $data['dryrun']);
	
	$message = "Reloading the server\n";
	if ($debug) echo fM($message);

	if (!$data['dryrun']) {
		addLogEntry($message);
		
		$server = detectServerType();
		$rc_script = getStartupScript($server['app']);
		if ($rc_script === false) {
			$last_line = "Cannot locate the init script\n";
			addLogEntry($last_line);
			$retval = true;
		} else {
			$last_line = system($rc_script . ' 2>&1', $retval);
			addLogEntry($last_line);
		}
		
		if ($retval) {
			$message = "There was an error reloading the server - please check the logs for details\n";
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


/**
 * Sets additional variables to add to the database
 *
 * @since 0.1
 * @package facileManager
 * @subpackage fmDHCP
 *
 * @return array
 */
function moduleAddServer() {
	/**
	 * Define additional array elements to be passed to the database
	 */
	
	$data['server_config_file'] = findFile('dhcpd.conf', array('/etc/dhcp', '/etc/dhcpd', '/etc'));;
	
	return $data;
}


/**
 * Processes module-specific web requests
 *
 * @since 0.1
 * @package facileManager
 * @subpackage fmDHCP
 *
 * @return array
 */
function moduleInitWebRequest() {
	$output['failures'] = false;
	$output['output'] = array();

	switch ($_POST['action']) {
		case 'manage_leases':
			exec(findProgram('sudo') . ' ' . findProgram('php') . ' ' . dirname(__FILE__) . '/client.php ' . $_POST['command_args'], $output['output'], $rc);
			if ($rc) {
				/** Something went wrong */
				$output['failures'] = true;
			}
			break;
	}
	
	return $output;
}


/**
 * Detects server type
 *
 * @since 0.1
 * @package facileManager
 * @subpackage fmDHCP
 *
 * @return array|null
 */
function detectServerType() {
	$supported_servers = array('dhcpd'=>'dhcpd');
	
	foreach($supported_servers as $type => $app) {
		if (findProgram($app)) return array('type'=>$type, 'app'=>$app);
	}
	
	return null;
}


/**
 * Detects daemon version
 *
 * @since 0.1
 * @package facileManager
 * @subpackage fmDHCP
 *
 * @return array|string|null
 */
function detectAppVersion($return_array = false) {
	$server = detectServerType();
	$flags = array('dhcpd'=>'--version 2>&1 | sed "s/isc-dhcpd-//"');
	
	if ($server) {
		$version = trim(shell_exec(findProgram($server['app']) . ' ' . $flags[$server['app']]));
		if ($return_array) {
			return array('server' => $server, 'app_version' => $version);
		} else return trim($version);
	}
	
	return null;
}


/**
 * Detects init script
 *
 * @since 0.1
 * @package facileManager
 * @subpackage fmDHCP
 *
 * @return array|boolean
 */
function getStartupScript($app) {
	$distros = array(
		'dhcpd' => array(
			'Arch'      => findProgram('systemctl') . ' restart dhcpd.service',
			'Debian'    => array(findProgram('service') . ' isc-dhcp-server restart', findProgram('systemctl') . ' restart isc-dhcp-server.service'),
			'Redhat'    => array('/etc/init.d/dhcpd restart', findProgram('systemctl') . ' restart dhcpd.service'),
			'SUSE'      => findProgram('rcdhcpd') . ' restart',
			'Gentoo'    => '/etc/init.d/dhcpd restart',
			'Slackware' => '/etc/rc.d/rc.dhcpd restart'
		)
	);

	/** Debian-based distros */
	$distros['dhcpd']['Raspbian'] = $distros['dhcpd']['Ubuntu'] = $distros['dhcpd']['Fubuntu'] = $distros['dhcpd']['Debian'];
	
	/** Redhat-based distros */
	$distros['dhcpd']['Fedora'] = $distros['dhcpd']['CentOS'] = $distros['dhcpd']['ClearOS'] = $distros['dhcpd']['Oracle'] = $distros['dhcpd']['Scientific'] = $distros['dhcpd']['Redhat'];

	$os = detectOSDistro();

	if (array_key_exists($os, $distros[$app])) {
		if (is_array($distros[$app][$os])) {
			foreach ($distros[$app][$os] as $rcscript) {
				$script = preg_split('/\s+/', $rcscript);
				if (file_exists($script[0])) {
					return $rcscript;
				}
			}
		} else {
			return $distros[$app][$os];
		}
	}
	
	return false;
}


/**
 * Dumps the lease file to STDOUT
 *
 * @since 0.1
 * @package fmDNS
 *
 * @param string $leasefile Filename of lease file
 * @return boolean
 */
function dumpLeases($leasefile) {
	global $debug, $output_type;
	
	if ($debug) {
		echo "Dumping $leasefile\n";
	}
	$pattern = '/^lease(.*?)}$/sm';
//	$pattern = '/^\s+([\.\d]+)\s+{.*starts \d+ ([\/\d\ \:]+);.*ends \d+ ([\/\d\ \:]+);.*ethernet ([a-f0-9:]+);(.*client-hostname \"(\S+)\";)*/sm';
	preg_match_all($pattern, file_get_contents($leasefile), $leases);
	
	/** Pretty display */
	if ($output_type == 'human') {
		$header = sprintf("%s %s %s %s %s\n", str_pad('MAC', 18, ' '), str_pad('IP Address', 18, ' '), str_pad('Hostname', 18, ' '), str_pad('State', 7, ' '), str_pad('Expires', 20, ' '));
		echo $header . str_repeat('=', strlen($header)) . "\n";
	}
	
	/** Break up each lease into a multidimensional array */
	$new_leases = array();
	foreach ($leases[0] as $lease_data) {
		$pattern = '/^lease\s+(.*?)\s+{.*starts \d+ (.*?);.*ends \d+ (.*?);.*binding state (.*?);.*ethernet (.*?);(.*client-hostname "(.*?)";)/s';
		$pattern = '/^lease\s+(.*?)\s+{.*starts \d+ (.*?);.*ends \d+ (.*?);.*  binding state (.*?);.*ethernet (.*?);/s';
		if (preg_match($pattern, $lease_data, $match)) {
			$ip = $match[1];
			$new_leases[$ip] = array('starts' => $match[2], 'ends' => $match[3], 'state' => $match[4], 'hardware' => $match[5]);
		}
		
		$new_leases[$ip]['hostname'] = 'N/A';
		if (preg_match('/.*client-hostname "(.*?)";/s', $lease_data, $match)) {
			$new_leases[$ip]['hostname'] = $match[1];
		}
		
		/** Pretty display */
		if ($output_type == 'human') {
			/** Skip expired */
			if (strtotime($new_leases[$ip]['ends']) < strtotime('now')) continue;
			
			printf("%s %s %s %s %s\n", str_pad($new_leases[$ip]['hardware'], 18, ' '), str_pad($ip, 18, ' '), str_pad(strlen($new_leases[$ip]['hostname']) > 18 ? substr($new_leases[$ip]['hostname'], 0, 15) . '...' : $new_leases[$ip]['hostname'], 18, ' '), str_pad($new_leases[$ip]['state'], 7, ' '), $new_leases[$ip]['ends']);
		}
	}
	
	if ($output_type == 'web') {
		echo serialize($new_leases);
	}
	
	exit;
}


/**
 * Deletes the lease from the file
 *
 * @since 0.1
 * @package fmDNS
 *
 * @param string $leasefile Filename of lease file
 * @param string $remove_lease IP address to delete (or all)
 * @return boolean
 */
function deleteLease($leasefile, $remove_lease) {
	global $debug, $output_type;
	
	if ($debug) {
		echo fM("Deleting $remove_lease from $leasefile...\n");
	}
	$lease_pattern = ($remove_lease == 'all') ? '' : $remove_lease;
	$pattern = '/^lease ' . $lease_pattern . '(.*?)}$/sm';
	$current_content = file_get_contents($leasefile);
	$new_content = preg_replace($pattern, '', $current_content);
	
	if ($new_content == $current_content) {
		echo fM("$remove_lease is not a valid lease.\n");
		exit(1);
	}
	
	$server = detectServerType();
	$rc_script = getStartupScript($server['app']);
	if ($rc_script === false) {
		$last_line = "Cannot locate the init script\n";
		addLogEntry($last_line);
		$retval = true;
	} else {
		foreach (array('stop', 'start') as $control) {
			$last_line = system(str_replace('restart', $control, $rc_script) . ' 2>&1', $retval);
			addLogEntry($last_line);
			if ($control == 'stop') {
				addLogEntry("Deleting $remove_lease from $leasefile\n");
				file_put_contents($leasefile, $new_content);
			}
		}
		
		$last_line = system($rc_script . ' 2>&1', $retval);
		addLogEntry($last_line);
	}

	if ($retval) {
		$message = "There was an error reloading the server - please check the logs for details\n";
		if ($debug) echo fM($message);
		addLogEntry($message);
		exit(1);
	} else {
		$message = "$remove_lease has been removed from $leasefile.\n";
		if ($debug) echo fM($message);
		addLogEntry($message);
	}

	exit;
}

/**
 * Attempts to install required packages
 *
 * @since 0.9.0
 * @package facileManager
 * @subpackage fmDHCP
 *
 * @param string $url URL to post data to
 * @param array $data Array of existing installation data
 * @return array
 */
function moduleInstallApp($url, $data) {
	$packages[] = (isDebianSystem($data['server_os_distro'])) ? 'isc-dhcp-server' : 'dhcp';
	$services[] = (isDebianSystem($data['server_os_distro'])) ? 'isc-dhcp-server' : '';
	
	installApp($packages, $services);

	return addServer($url, $data, true);
}
