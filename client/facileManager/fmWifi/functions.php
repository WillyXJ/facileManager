<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2013-2018 The facileManager Team                               |
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
 | fmWifi: Easily manage hostapd on one or more systems                    |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmWifi/                            |
 +-------------------------------------------------------------------------+
*/

/**
 * fmWifi Functions
 *
 * @package fmWifi
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
    -l           Specify what to do with the leases (dump|delete)
                   Examples: client.php -l dump
                             client.php -l delete=10.1.1.100
    -o           Specify the output type (human|web) (default: human)
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
	global $argv;
	
	/**
	 * Add any module-specific installation checks here
	 */
	
	extract($server_location);
	
	if (findProgram('iw')) {
		if (!isAPModeSupported()) {
			fM("The installed wireless card does not support AP mode. Aborting.\n");
			exit(1);
		}
	}

	echo fM('  --> Running version tests...');
	$app = detectDaemonVersion(true);
	if ($app === null) {
		echo "failed\n\n";
		echo fM("Cannot find a supported AP servers - please check the README document for supported AP servers.\n");
		echo fM('Would you like me to try installing hostapd? [Y/n] ');
		$auto_install = strtolower(trim(fgets(STDIN)));
		if (!$auto_install) {
			$auto_install = 'y';
		}
		
		if ($auto_install != 'y') {
			echo "Aborting.\n";
			exit(1);
		}
		
		installPackage(array('hostapd', 'iw'));
		return installFMModule($module_name, $proto, $compress, $data, $server_location, $url);
	}
	
	extract($app);
	$data['server_type'] = $server['type'];
	$output = versionCheck($app_version, $proto . '://' . $hostname . '/' . $path, $compress);
	if ($output === true) {
		echo "ok\n";
	} else {
		echo "failed\n\n";
		echo "$app_version is not supported.\n";
		exit(1);
	}
	$data['server_version'] = $app_version;
	
	echo fM("\n  --> Tests complete.  Continuing installation.\n\n");
	
	/** Configure AP mode */
	while (!$ap_mode) {
		echo fM('Will this access point be a router or bridge? [R/b] ');
		$ap_mode = strtolower(trim(fgets(STDIN)));
		if (!$ap_mode) {
			$ap_mode = 'r';
		}

		/** Ensure a valid selection was made */
		if (!in_array($ap_mode, array('r', 'b'))) {
			echo fM("This option is not supported.\n\n");
			unset($ap_mode);
		}
	}
	
	/** Process mode choice */
	switch($ap_mode) {
		case 'r':
			$data['server_mode'] = 'router';
			break;
		case 'b':
			$data['server_mode'] = 'bridge';
			break;
	}
	echo fM(sprintf("\nNote: If you select 'n' now, the installer will assume this device is already configured as a %s.\n", $data['server_mode']));
	echo fM(sprintf('Would you like me to configure your device to be a %s? [Y/n] ', $data['server_mode']));
	$configure_mode = strtolower(trim(fgets(STDIN)));
	if (!$configure_mode) {
		$configure_mode = 'y';
	}

	if ($configure_mode == 'y') {
		list($data['server_wlan_interface'], $data['server_bridge_interface']) = configureAPMode($data['server_mode']);
	}
	
	$data['server_interfaces'] = implode(';', getInterfaceNames(PHP_OS));

	/** Handle the update method */
	$data['server_update_method'] = processUpdateMethod($module_name, $update_method, $data, $url);

	$raw_data = getPostData(str_replace('genserial', 'addserial', $url), $data);
	$raw_data = $data['compress'] ? @unserialize(gzuncompress($raw_data)) : @unserialize($raw_data);
	
	return $data;
}


/**
 * Finish building the server config with module-specific data
 *
 * @since 0.1
 * @package facileManager
 * @subpackage fmWifi
 *
 * @param string $url URL to post data to
 * @param array $data Data to post
 * @return array
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
 * @subpackage fmWifi
 *
 * @return array
 */
function moduleAddServer() {
	/**
	 * Define additional array elements to be passed to the database
	 */
	
	$data['server_config_file'] = findFile('hostapd.conf', array('/etc/hostapd', '/etc'));
	
	return $data;
}


/**
 * Processes module-specific web requests
 *
 * @since 0.1
 * @package facileManager
 * @subpackage fmWifi
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
 * @subpackage fmWifi
 *
 * @return array
 */
function detectServerType() {
	$supported_servers = array('hostapd'=>'hostapd');
	
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
 * @subpackage fmWifi
 *
 * @return array
 */
function detectDaemonVersion($return_array = false) {
	$server = detectServerType();
	$flags = array('hostapd'=>'-v 2>&1 | head -1 | awk "{print \$NF}" | sed \'s/v//\'');
	
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
 * @subpackage fmWifi
 *
 * @return array
 */
function getStartupScript($app) {
	$distros = array(
		'hostapd' => array(
			'Arch'      => findProgram('systemctl') . ' restart hostapd.service',
			'Debian'    => array(findProgram('service') . ' hostapd restart', findProgram('systemctl') . ' restart hostapd.service'),
			'Redhat'    => array('/etc/init.d/hostapd restart', findProgram('systemctl') . ' restart hostapd.service'),
			'SUSE'      => findProgram('rchostapd') . ' restart',
			'Gentoo'    => '/etc/init.d/hostapd restart',
			'Slackware' => '/etc/rc.d/rc.hostapd restart'
		),
		'dhcpcd' => array(
			'Arch'      => findProgram('systemctl') . ' restart dhcpcd.service',
			'Debian'    => array(findProgram('service') . ' dhcpcd restart', findProgram('systemctl') . ' restart dhcpcd.service'),
			'Redhat'    => array('/etc/init.d/dhcpcd restart', findProgram('systemctl') . ' restart dhcpcd.service'),
			'SUSE'      => findProgram('rcdhcpcd') . ' restart',
			'Gentoo'    => '/etc/init.d/dhcpcd restart',
			'Slackware' => '/etc/rc.d/rc.dhcpcd restart'
		),
		'dnsmasq' => array(
			'Arch'      => findProgram('systemctl') . ' restart dnsmasq.service',
			'Debian'    => array(findProgram('service') . ' dnsmasq restart', findProgram('systemctl') . ' restart dnsmasq.service'),
			'Redhat'    => array('/etc/init.d/dnsmasq restart', findProgram('systemctl') . ' restart dnsmasq.service'),
			'SUSE'      => findProgram('rcdnsmasq') . ' restart',
			'Gentoo'    => '/etc/init.d/dnsmasq restart',
			'Slackware' => '/etc/rc.d/rc.dnsmasq restart'
		)
	);
	$distros['hostapd']['Ubuntu'] = $distros['hostapd']['Fubuntu'] = $distros['hostapd']['Debian'];
	$distros['dhcpcd']['Ubuntu'] = $distros['dhcpcd']['Fubuntu'] = $distros['dhcpcd']['Debian'];
	$distros['dnsmasq']['Ubuntu'] = $distros['dnsmasq']['Fubuntu'] = $distros['dnsmasq']['Debian'];
	
	$distros['hostapd']['Fedora'] = $distros['hostapd']['CentOS'] = $distros['hostapd']['ClearOS'] = $distros['hostapd']['Oracle'] = $distros['hostapd']['Redhat'];
	$distros['dhcpcd']['Fedora'] = $distros['dhcpcd']['CentOS'] = $distros['dhcpcd']['ClearOS'] = $distros['dhcpcd']['Oracle'] = $distros['dhcpcd']['Redhat'];
	$distros['dnsmasq']['Fedora'] = $distros['dnsmasq']['CentOS'] = $distros['dnsmasq']['ClearOS'] = $distros['dnsmasq']['Oracle'] = $distros['dnsmasq']['Redhat'];
	
	$os = detectOSDistro();

	if (array_key_exists($os, $distros[$app])) {
		if (is_array($distros[$app][$os])) {
			foreach ($distros[$app][$os] as $rcscript) {
				$script = preg_split('/\s+/', $rcscript);
				if (file_exists($script[0])) {
					if ($chroot_environment) {
						if (strpos($distros[$app][$os][count($distros[$app][$os])-1], $script[0]) !== false) {
							return $distros[$app][$os][count($distros[$app][$os])-1];
						}
					}
					
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
		echo "MAC\t\t\tIP\t\tHostname\tState\tExpires\n";
		echo str_repeat('=', 80) . "\n";
	}
	
	/** Break up each lease into a multidimensional array */
	$new_leases = null;
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
			
			echo "{$new_leases[$ip]['hardware']}\t$ip\t{$new_leases[$ip]['hostname']}\t\t{$new_leases[$ip]['state']}\t{$new_leases[$ip]['ends']}\n";
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
 * @param string $remove_lease IP address to delete
 * @return boolean
 */
function deleteLease($leasefile, $remove_lease) {
	global $debug, $output_type;
	
	if ($debug) {
		echo fM("Deleting $remove_lease from $leasefile...\n");
	}
	$pattern = '/^lease ' . $remove_lease . '(.*?)}$/sm';
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
 * Detect if wireless card is supported
 *
 * @since 0.1
 * @package facileManager
 * @subpackage fmWifi
 *
 * @return boolean
 */
function isAPModeSupported() {
	return shell_exec(findProgram('iw') . " list | grep -A 7 'Supported interface modes' | grep -c '* AP\$'");
}


/**
 * Configure AP mode
 *
 * @since 0.1
 * @package facileManager
 * @subpackage fmWifi
 *
 * @param string $mode Type of mode (router, bridge)
 * @return boolean
 */
function configureAPMode($mode) {
	$function = 'configureAP' . ucfirst($mode);

	/** Install common packages */
	$program = 'iw';
	if (!findProgram($program)) {
		installPackage($program);
	}
	
	/** Get wireless device names */
	exec(findProgram($program) . ' dev | awk \'$1=="Interface"{print $2}\'', $wdevices, $rc);
	if (count($wdevices) == 1) {
		$wdev = $wdevices[0];
	} else {
		while (!$wdev) {
			echo fM('Which wireless interface will be used? [' . join('/', $wdevices) . '] ');
			$wdev = strtolower(trim(fgets(STDIN)));
			if (!in_array($wdev, $wdevices)) {
				unset($wdev);
			}
		}
	}
	
	return $function($wdev);
}


/**
 * Configure AP as Bridge
 *
 * @since 0.1
 * @package facileManager
 * @subpackage fmWifi
 *
 * @param $string $wdev Wireless device name
 * @return boolean
 */
function configureAPBridge($wdev) {
	global $module_name, $data;
	
	$header = sprintf('# This file was built using %s v%s',
		$module_name, $data['server_client_version']
	);
	$bridge_conf = sprintf('/etc/network/interfaces.d/vlan-%s', $module_name);
	
	/** Get interface or create new */
	echo fM("\nYou currently have the following interfaces for default routes:\n");
	exec('netstat -rn | awk \'$1=="0.0.0.0"{print $NF}\'', $interfaces, $rc);
	unset($interfaces[array_search($wdev, $interfaces)]);
	echo join("\n", $interfaces) . "\n";
	while (!$default_interface) {
		echo fM("\nEnter the name of the interface you want to use as the default route for your bridge (or leave blank to define a new virtual interface): ");
		$default_interface = strtolower(trim(fgets(STDIN)));
		if (!$default_interface) {
			$default_interface = 'new';
		}
		
		if ($default_interface != 'new' && !in_array($default_interface, $interfaces)) {
			echo fM(sprintf("\n'%s' is not a valid choice.\n", $default_interface));
			unset($default_interface);
		}
	}
	
	if ($default_interface == 'new') {
		/** Create vlan interface */
		while (!$vlan_id) {
			echo fM("\n\nEnter the VLAN ID to use: ");
			$vlan_id = strtolower(trim(fgets(STDIN)));
			if (!is_numeric($vlan_id)) {
				echo fM("A VLAN ID must be a number.");
				unset($vlan_id);
			}
		}
		unset($default_interface);
		while (!$default_interface) {
			foreach ($interfaces as $int) {
				echo fM(sprintf("Do you want to use %s.%s for the interface (Y/n)? ", $int, $vlan_id));
				$int_confirm = strtolower(trim(fgets(STDIN)));
				if (!$int_confirm) {
					$default_interface = "$int.$vlan_id";
					break;
				}
			}
			reset($interfaces);
		}
		sleep(1);

		$program = 'vlan';
		if (!findProgram('vconfig')) {
			installPackage($program);
		}

		echo fM(sprintf('  --> Configuring interface %s...', $default_interface));
		$vlan_conf_config = sprintf('%s

auto %s
iface %s inet manual
',
			$header,
			$default_interface, $default_interface
		);
		file_put_contents($bridge_conf, $vlan_conf_config);
		
		shell_exec("ifup $default_interface > /dev/null 2>&1");
		echo "done\n";
	}
	
	/** Update dhcpcd.conf */
	echo fM('  --> Configuring dhcpcd.conf...');
	$dhcpcd_conf = findFile('dhcpcd.conf');
	if (!$dhcpcd_conf) {
		echo fM("Failed to find dhcpcd.conf. Aborting.\n");
		exit(1);
	}
	$dhcpcd_conf_config = file_get_contents($dhcpcd_conf);
	if (strpos($dhcpcd_conf_config, $module_name) === false) {
		$vlan_conf_config = sprintf('
# This section was built using %s v%s
denyinterfaces %s
denyinterfaces %s
# End %s section
',
			$module_name, $data['server_client_version'],
			$wdev, $default_interface,
			$module_name
		);
		
		$dhcpcd_conf_config = explode("\n", $dhcpcd_conf_config);
		foreach ($dhcpcd_conf_config as $key => $line) {
			if (!$line) {
				$last_empty_key = $key;
				continue;
			}
			$pos = strpos($line, 'interface');
			if ($pos !== false && $pos == 0) {
				break;
			}
		}
		$dhcpcd_conf_config[$last_empty_key] = $vlan_conf_config;
		$dhcpcd_conf_config = join("\n", $dhcpcd_conf_config);
		
		file_put_contents($dhcpcd_conf, $dhcpcd_conf_config);
		echo "done\n\n";
	} else {
		echo "skipping - already configured\n\n";
	}
	
	
	/** Create bridge */
	$program = 'bridge-utils';
	if (!findProgram('brctl')) {
		installPackage($program);
	}
	
	exec(findProgram('brctl') . ' show | awk \'NF>1 && NR>1 {print $1}\'', $existing_bridges, $rc);
	for ($i=0; $i<=10; $i++) {
		if (!in_array("br$i", $existing_bridges)) {
			$bridge_interface = "br$i";
			break;
		}
	}
	if (!$bridge_interface) {
		echo fM("Could not find a suitable bridge device name to create. Aborting.\n");
		exit(1);
	}
	
	echo fM(sprintf('  --> Configuring bridge interface %s with %s and %s...',
			$bridge_interface, $default_interface, $wdev
		));

	echo shell_exec(findProgram('brctl') . ' addbr ' . $bridge_interface);
	echo shell_exec(findProgram('brctl') . ' addif ' . $bridge_interface . ' ' . $default_interface);
	
	if (strpos(@file_get_contents($bridge_conf), $module_name) !== false) {
		$header = null;
	}
	$bridge_config = sprintf('%s
auto %s
iface %s inet manual
bridge_ports %s %s',
		$header,
		$bridge_interface, $bridge_interface,
		$default_interface, $wdev
	);
	file_put_contents($bridge_conf, $bridge_config, FILE_APPEND);
	echo "done\n\n";
	
	return array($wdev, $bridge_interface);
}


/**
 * Configure AP as Router
 *
 * @since 0.1
 * @package facileManager
 * @subpackage fmWifi
 *
 * @param $string $wdev Wireless device name
 * @return boolean
 */
function configureAPRouter($wdev) {
	global $module_name, $data;
	
	/** Prompts */
	echo fM(sprintf('Enter static IP address for %s: (default: 192.168.3.1/24) ', $wdev));
	$wdev_ip = strtolower(trim(fgets(STDIN)));
	if (!$wdev_ip) {
		$wdev_ip = '192.168.3.1/24';
	}
	
	echo fM(sprintf('Enter DHCP pool address range for %s: (default: 192.168.3.2-20) ', $wdev));
	$wdev_dhcp_range = strtolower(trim(fgets(STDIN)));
	if (!$wdev_dhcp_range) {
		$wdev_dhcp_range = '192.168.3.2-20';
	}
	$wdev_dhcp_range = explode('-', $wdev_dhcp_range);
	$wdev_dhcp_range['net'] = explode('.', $wdev_dhcp_range[0]);
	$wdev_dhcp_range['start'] = $wdev_dhcp_range[0];
	$wdev_dhcp_range['net'][count($wdev_dhcp_range['net'])] = $wdev_dhcp_range[1];
	$wdev_dhcp_range['end'] = join('.', $wdev_dhcp_range['net']);
	
	echo fM(sprintf('Enter DHCP pool lease time for %s: (default: 24h) ', $wdev));
	$wdev_dhcp_lease = strtolower(trim(fgets(STDIN)));
	if (!$wdev_dhcp_lease) {
		$wdev_dhcp_lease = '24h';
	}
	
	/** Install dnsmasq package */
	$program = 'dnsmasq';
	if (!findProgram($program)) {
		installPackage($program);
	}
	echo fM("\n  --> Configuring dnsmasq...");
	$dnsmasq_conf = sprintf('/etc/dnsmasq.d/01-%s.conf', $module_name);
	$wdev_dhcp_conf = sprintf('# This file was built using %s v%s

interface=%s
  dhcp-range=%s,%s,255.255.255.0,%s
  dhcp-option=option:router,%s',
			$module_name, $data['server_client_version'],
			$wdev, $wdev_dhcp_range['start'], $wdev_dhcp_range['end'], $wdev_dhcp_lease,
			substr($wdev_ip, 0, strpos($wdev_ip, '/'))
		);
	file_put_contents($dnsmasq_conf, $wdev_dhcp_conf);
	unset($wdev_dhcp_range);
	echo "done\n";
	
	echo fM('  --> Configuring dhcpcd.conf...');
	$dhcpcd_conf = findFile('dhcpcd.conf');
	if (!$dhcpcd_conf) {
		echo fM("Failed to find dhcpcd.conf. Aborting.\n");
		exit(1);
	}
	if (strpos(file_get_contents($dhcpcd_conf), $module_name) === false) {
		$wdev_conf = sprintf('

# This section was built using %s v%s
interface=%s
    static ip_address=%s
    nohook wpa_supplicant
# End %s section
',
			$module_name, $data['server_client_version'],
			$wdev, $wdev_ip, $module_name
		);
		file_put_contents($dhcpcd_conf, $wdev_conf, FILE_APPEND);
		echo "done\n";
	} else {
		echo "skipping - already configured\n";
	}
	
	/** Set iptables rules */
	if (PHP_OS == 'Linux') {
		echo fM('  --> Configuring sysctl...');
		$sysctl_conf = '/etc/sysctl.d/01-fmWifi.conf';
		file_put_contents($sysctl_conf, 'net.ipv4.ip_forward=1');
		echo "done\n";
		
		echo fM('  --> Configuring iptables NAT rule...');
		file_put_contents('/etc/iptables.ipv4.' . $module_name, 'iptables-save');
		exec('iptables -t nat -C POSTROUTING -o eth0 -j MASQUERADE > /dev/null 2>&1', $output, $rc);
		if ($rc) {
			shell_exec('iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE');
		}
		shell_exec('sh -c "iptables-save > /etc/iptables.ipv4.' . $module_name . '"');
		if (strpos(file_get_contents('/etc/rc.local'), $module_name) === false) {
			shell_exec('sed -i "s#^exit 0\$#iptables-restore < /etc/iptables.ipv4.' . $module_name . '\\nexit 0#" /etc/rc.local');
		}
		echo "done\n\n";
	}
	
	return array($wdev, null);
}


?>