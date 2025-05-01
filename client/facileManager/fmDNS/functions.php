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
	echo <<<HELP
   -D zone-name               Name of zone to dump (required by dump-zone)
   -f /path/to/file           Filename hosting the zone data (required by dump-zone)
   -z|zones                   Build all associated zone files
     dump-cache               Dump the DNS cache
     dump-zone                Dump the specified zone data to STDOUT
     clear-cache              Clear the DNS cache
     id=XX                    Specify the individual ZoneID to build and reload
	 
     setHost                  Invokes the API functionality
     action=XX                Defines API action to take on a record (add, update, delete)
     type=XX                  Defines the RR type (A, AAAA, CNAME, DNAME, MX, NS, PTR, TXT)
     name=XX                  Defines the name of the RR
     value=XX                 Defines the value of the RR
     ttl=XX                   Defines the TTL of the RR
     priority=XX              Defines the priority of the RR (MX only)
     append=XX                Defines whether to append the zone name or not (yes, no)
     comment=XX               Defines the record comment
     status=XX                Defines the record status (active, disabled)
     newname=XX               Defines the new record name (when action=update)
     newvalue=XX              Defines the new record value (when action=update)
     reload=XX                Defines whether to reload the zone or not (yes, no)

     install url-only         Installs the client app to be a URL RR web server only
     enable url               Enables the URL RR web server support on a previous installation
  
HELP;
}


function installFMModule($module_name, $proto, $compress, $data, $server_location, $url) {
	global $argv, $module_name, $update_method;
	
	extract($server_location);

	if (in_array('url-only', $argv)) {
		$data['server_type'] = 'url-only';

		$data = array_merge($data, setURLConfig());
	} else {
		echo fM('  --> Running version tests...');
		if (versionCheck($data['server_version'], $proto . '://' . $hostname . '/' . $path, $compress) == true) {
			echo "ok\n";
		} else {
			echo "failed\n\n";
			echo "{$data['server_version']} is not supported.\n";
			exit(1);
		}
	}
	
	return $data;
}


function buildConf($url, $data) {
	global $proto, $debug, $purge;
	
	$raw_data = getPostData($url, $data);
	$raw_data = $data['compress'] ? @unserialize(gzuncompress($raw_data)) : @unserialize($raw_data);
	if (!is_array($raw_data)) {
		if ($debug) echo fM($raw_data);
		addLogEntry($raw_data);
		exit(1);
	}
	extract($raw_data, EXTR_SKIP);
	$chroot_environment = false;
	
	if (dirname($server_chroot_dir)) {
		$server_root_dir = $server_chroot_dir . $server_root_dir;
		$server_zones_dir = $server_chroot_dir . $server_zones_dir;
		$server_config_file = $server_chroot_dir . $server_config_file;
		foreach ($files as $filename => $contents) {
			$new_files[$server_chroot_dir . $filename] = $contents;
		}
		$files = $new_files;
		unset($new_files);
		$chroot_environment = true;
		
		/** Add key file to chroot list */
		addChrootFiles();
	}
	
	if ($debug) {
		foreach ($files as $filename => $fileinfo) {
			if (is_array($fileinfo)) {
				extract($fileinfo, EXTR_OVERWRITE);
			} else {
				$contents = $fileinfo;
			}
			echo str_repeat('=', 50) . "\n";
			echo $filename . ":\n";
			echo str_repeat('=', 50) . "\n";
			echo $contents . "\n\n";
		}
	}
	
	$runas = ($server_run_as_predefined == 'as defined:') ? $server_run_as : $server_run_as_predefined;
	$chown_dirs = array($server_zones_dir);
	
	/** Freeze zones */
	if (!$data['dryrun'] && isDaemonRunning('named')) {
		/** Handle dynamic zones to support reloading */
		$retval = runRndcActions('freeze', $server_config_file, $server_key_with_rndc);
		if ($retval) {
			return $retval;
		}
	}
	
	/** Remove previous files so there are no stale files */
	if ($purge || (isset($purge_config_files) && $purge_config_files == 'yes' && $server_update_config == 'conf')) {
		/** Server config files */
		$path_parts = pathinfo($server_config_file);
		if (version_compare(PHP_VERSION, '5.2.0', '<')) {
			$path_parts['filename'] = str_replace('.' . $path_parts['extension'], '', $path_parts['basename']);
		}
		$config_file_pattern = $path_parts['dirname'] . DIRECTORY_SEPARATOR . $path_parts['filename'] . '.*';
		exec('ls ' . $config_file_pattern, $config_file_match);
		foreach ($config_file_match as $config_file) {
			deleteFile($config_file, $debug, $data['dryrun']);
		}
		
		/** Zone files */
		deleteFile($server_zones_dir, $debug, $data['dryrun']);
	}
	
	/** Install the new files */
	installFiles($files, $data['dryrun'], $chown_dirs, $runas);
	
	/** Reload the server */
	if (!$data['dryrun']) {
		/** Reload web server */
		if ($server_url_server_type) {
			$message = "Reloading $server_url_server_type\n";
			if ($debug) echo fM($message);
			addLogEntry($message);

			$rc_script = getStartupScript($server_url_server_type);
			if ($rc_script === false) {
				$last_line = "Cannot locate the start script\n";
				$retval = true;
			} else {
				if (!isDaemonRunning($server_url_server_type)) {
					$message = "The server is not running - attempting to start it\n";
					if ($debug) echo fM($message);
					addLogEntry($message);

					$last_line = system($rc_script . ' 2>&1', $retval);
				} else {
					$last_line = system(str_replace('start', 'reload', $rc_script) . ' 2>&1', $retval);
				}
			}
			if ($retval) {
				return processReloadFailure($last_line);
			}
		}

		/** Reload the dns server */
		if ($server_type == 'bind9') {
			$message = "Reloading $server_type\n";
			if ($debug) echo fM($message);
			addLogEntry($message);
			$retval = false;

			if (isDaemonRunning('named')) {
				$rndc_actions = array('reload', 'thaw');
				
				/** Handle dynamic zones to support reloading */
				$retval = runRndcActions($rndc_actions, $server_config_file, $server_key_with_rndc);
				if ($retval) {
					return $retval;
				}
			} else {
				$message = "The server is not running - attempting to start it\n";
				if ($debug) echo fM($message);
				addLogEntry($message);
				$named_rc_script = getStartupScript($server_type, $chroot_environment);
				if ($named_rc_script === false) {
					$last_line = "Cannot locate the start script\n";
					$retval = true;
				} else {
					$last_line = system($named_rc_script . ' 2>&1', $retval);
				}
			}
			if ($retval) {
				return processReloadFailure($last_line);
			} else {
				/** Only update reloaded zones */
				if (isset($reload_domain_ids)) {
					$data['reload_domain_ids'] = $reload_domain_ids;
				}
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
	}
	return true;
}


function detectServerType() {
	$supported_servers = array('bind9'=>'named');
	
	foreach($supported_servers as $type => $app) {
		if (findProgram($app)) return array('type'=>$type, 'app'=>$app);
	}
	
	return null;
}


function moduleAddServer() {
	global $argv;

	if (in_array('url-only', $argv)) {
		$data['server_type'] = 'url-only';
	} else {
		/** Attempt to determine default variables */
		$named_conf = findFile('named.conf', array('/etc/named', '/etc/namedb', '/etc/bind'));
		$data['server_run_as_predefined'] = 'named';
		if ($named_conf) {
			if (function_exists('posix_getgrgid')) {
				if ($run_as = posix_getgrgid(filegroup($named_conf))) {
					$data['server_run_as_predefined'] = $run_as['name'];
				}
			}
			$data['server_config_file'] = $named_conf;
			$server_root = getParameterValue('directory', $named_conf, '"');
			
			if ($server_root === false) {
				if (file_exists($named_conf . '.options')) {
					$server_root = getParameterValue('directory', $named_conf . '.options', '"');
				}
			}
			$data['server_root_dir'] = $server_root;
			
			$data['server_zones_dir'] = (dirname($named_conf) == '/etc') ? null : dirname($named_conf) . '/zones';

			if (file_exists('/etc/apparmor.d/usr.sbin.named')) {
				$slave_dir_line = getLineWithString('/etc/apparmor.d/usr.sbin.named', 'slave');
				if ($slave_dir_line) {
					$slave_dir_line_arr = explode(' ', trim($slave_dir_line));
					if (is_dir($slave_dir_line_arr[1])) $data['server_slave_zones_dir'] = $slave_dir_line_arr[1];
				}
			}
		}
		$data['server_chroot_dir'] = detectChrootDir();
	}
	
	return $data;
}


function detectAppVersion($return_array = false) {
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


function getStartupScript($app, $chroot_environment = false) {
	$distros = array(
		'bind9' => array(
			'Arch'      => array('/etc/rc.d/named start', findProgram('systemctl') . ' start named.service'),
			'Debian'    => array('/etc/init.d/bind9 start', findProgram('systemctl') . ' start bind9.service'),
			'Redhat'    => array('/etc/init.d/named start', findProgram('systemctl') . ' start named.service', findProgram('systemctl') . ' start named-chroot.service'),
			'SUSE'      => array('/etc/init.d/named start', findProgram('systemctl') . ' start named.service'),
			'Gentoo'    => array('/etc/init.d/named start', findProgram('systemctl') . ' start named.service'),
			'Slackware' => array('/etc/rc.d/rc.bind start', findProgram('systemctl') . ' start bind.service'),
			'FreeBSD'   => array('/usr/local/etc/rc.d/named start' , '/etc/rc.d/named start'),
			'OpenBSD'   => array('/usr/local/etc/rc.d/named start' , '/etc/rc.d/named start'),
			'Apple'     => findProgram('launchctl') . ' start org.isc.named'
		),
		'httpd' => array(
			'Arch'      => array('/etc/rc.d/httpd start', findProgram('systemctl') . ' start httpd.service'),
			'Debian'    => array('/etc/init.d/httpd start', findProgram('systemctl') . ' start httpd.service'),
			'Redhat'    => array('/etc/init.d/httpd start', findProgram('systemctl') . ' start httpd.service'),
			'SUSE'      => array('/etc/init.d/httpd start', findProgram('systemctl') . ' start httpd.service'),
			'Gentoo'    => array('/etc/init.d/httpd start', findProgram('systemctl') . ' start httpd.service'),
			'Slackware' => array('/etc/rc.d/rc.httpd start', findProgram('systemctl') . ' start httpd.service'),
			'FreeBSD'   => array('/usr/local/etc/rc.d/httpd start' , '/etc/rc.d/httpd start'),
			'OpenBSD'   => array('/usr/local/etc/rc.d/httpd start' , '/etc/rc.d/httpd start')
		),
		'lighttpd' => array(
			'Arch'      => array('/etc/rc.d/lighttpd start', findProgram('systemctl') . ' start lighttpd.service'),
			'Debian'    => array('/etc/init.d/lighttpd start', findProgram('systemctl') . ' start lighttpd.service'),
			'Redhat'    => array('/etc/init.d/lighttpd start', findProgram('systemctl') . ' start lighttpd.service'),
			'SUSE'      => array('/etc/init.d/lighttpd start', findProgram('systemctl') . ' start lighttpd.service'),
			'Gentoo'    => array('/etc/init.d/lighttpd start', findProgram('systemctl') . ' start lighttpd.service'),
			'Slackware' => array('/etc/rc.d/rc.lighttpd start', findProgram('systemctl') . ' start lighttpd.service'),
			'FreeBSD'   => array('/usr/local/etc/rc.d/lighttpd start' , '/etc/rc.d/lighttpd start'),
			'OpenBSD'   => array('/usr/local/etc/rc.d/lighttpd start' , '/etc/rc.d/lighttpd start')
		)
	);

	if (!array_key_exists($app, $distros)) {
		return false;
	}
	
	/** Debian-based distros */
	$distros[$app]['Raspbian'] = $distros[$app]['Ubuntu'] = $distros[$app]['Fubuntu'] = $distros[$app]['Debian'];
	
	/** Redhat-based distros */
	$distros[$app]['Fedora'] = $distros[$app]['CentOS'] = $distros[$app]['ClearOS'] = $distros[$app]['Oracle'] = $distros[$app]['Scientific'] = $distros[$app]['Redhat'];

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


function detectChrootDir() {
	switch (PHP_OS) {
		case 'Linux':
			$os = detectOSDistro();
			if (in_array($os, array('Redhat', 'CentOS', 'ClearOS', 'Oracle', 'Scientific'))) {
				if ($chroot_dir = getParameterValue('^ROOTDIR', '/etc/sysconfig/named')) return $chroot_dir;
				/** systemd unit file */
				addChrootFiles();
				if ($chroot_dir = getParameterValue('ExecStart=/usr/libexec/setup-named-chroot.sh', '/usr/lib/systemd/system/named-chroot-setup.service', ' ')) return $chroot_dir;
			}
			if (in_array($os, array('Debian', 'Ubuntu', 'Fubuntu'))) {
				if ($flags = getParameterValue('^OPTIONS', '/etc/default/bind9')) {
					$flags = explode(' ', $flags);
					if (in_array('-t', $flags)) return $flags[array_search('-t', $flags) + 1];
				}
			}
			break;
		case 'OpenBSD':
			$chroot_dir = '/var/named';
			foreach (array('/etc/rc.conf.local', '/etc/rc.conf') as $rcfile) {
				if ($chroot_dir = getParameterValue('^named_chroot', $rcfile)) break;
			}
			return $chroot_dir;
		case 'FreeBSD':
			if ($chroot_dir = getParameterValue('^named_chroot', '/etc/rc.conf')) return $chroot_dir;
			
			if ($flags = getParameterValue('^named_flags', '/etc/rc.conf')) {
				$flags = explode(' ', $flags);
				if (in_array('-t', $flags)) return $flags[array_search('-t', $flags) + 1];
			}
	}
	
	return null;
}


function manageCache($action, $message) {
	global $debug;

	addLogEntry($message);
	if (shell_exec('ps -A | grep named | grep -vc grep') > 0) {
		$last_line = system(findProgram('rndc') . ' ' . $action . ' 2>&1', $retval);
		if ($last_line) addLogEntry($last_line);

		if ($action == 'dumpdb -cache') {
			/** Get dump-file location */
			$dump_file = system('grep dump-file /etc/named.conf* | awk \'{print $NF}\'', $retval);
			$dump_file = str_replace(array('"', ';'), '', $dump_file);

			if (file_exists($dump_file)) {
				echo file_get_contents($dump_file);
			}
		}
		
		$message = $retval ? $message . ' failed' : $message . ' completed successfully';
		echo fM($message);
		addLogEntry($message);
	} else {
		$error_msg = "The server is not running\n";
		if ($debug) echo fM($error_msg);
		addLogEntry($error_msg);
	}
	if ($retval) {
		addLogEntry($last_line);
		$message = "There was an error " . strtolower($message) . " - please check the logs for details\n";
		if ($debug) echo fM($message);
		addLogEntry($message);
		exit(1);
	}
	
	exit;
}


/**
 * Logs and outputs error messages
 *
 * @since 2.0
 * @package fmDNS
 *
 * @param string $last_line Output from previously run command
 * @return boolean
 */
function processReloadFailure($last_line) {
	global $debug;
	
	if ($debug) echo fM($last_line) . "\n";
	addLogEntry($last_line);
	$message = "There was an error reloading the server - please check the logs for details\n";
	if ($debug) echo fM($message);
	addLogEntry($message);
	return false;
}


/**
 * Processes module-specific web action requests
 *
 * @since 2.2
 * @package fmDNS
 *
 * @return array
 */
function moduleInitWebRequest() {
	$output = array();
	
	switch ($_POST['action']) {
		case 'reload':
			if (!isset($_POST['domain_id']) || !is_numeric($_POST['domain_id'])) {
				exit(serialize('Zone ID is not found.'));
			}

			exec(findProgram('sudo') . ' ' . findProgram('php') . ' ' . dirname(__FILE__) . '/client.php zones id=' . $_POST['domain_id'] . ' 2>&1', $rawoutput, $rc);
			if ($rc) {
				/** Something went wrong */
				$output[] = 'Zone reload failed.';
				$output = array_merge($output, $rawoutput);
			}
			break;
		case 'get_zone_contents':
			if (!isset($_POST['domain_id']) || !is_numeric($_POST['domain_id'])) {
				exit(serialize('Zone ID is not found.'));
			}
			$output['failures'] = false;
			$output['output'] = array();

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
 * Dumps the specified zone data to STDOUT
 *
 * @since 3.0
 * @package fmDNS
 *
 * @param string $domain Domain name
 * @param string $zonefile Filename of zone file
 * @return boolean
 */
function dumpZone($domain, $zonefile) {
	passthru(findProgram('named-checkzone') . " -j -D $domain $zonefile");
	
	exit;
}


/**
 * Runs a rndc action
 *
 * @since 3.0
 * @package fmDNS
 *
 * @param array|string $rndc_actions rndc actions to run
 * @param string $server_config_file Server's configuration file
 * @param string $server_key_with_rndc Option to use server named key with rndc
 * @return boolean
 */
function runRndcActions($rndc_actions, $server_config_file, $server_key_with_rndc) {
	if (!is_array($rndc_actions)) $rndc_actions = array($rndc_actions);
	
	$rndc = findProgram('rndc');

	$rndc .= ($server_key_with_rndc == 'yes' && $server_config_file && file_exists(dirname($server_config_file) . '/named.conf.keys')) ? sprintf(' -k %s/named.conf.keys', dirname($server_config_file)) : null;
	
	foreach ($rndc_actions as $action) {
		$last_line = system("$rndc $action 2>&1", $retval);
		if ($retval) {
			processReloadFailure($last_line);
			return $retval;
		}
	}
	
	return false;
}


/**
 * Ensures chroot files are added
 *
 * @since 3.0
 * @package fmDNS
 */
function addChrootFiles() {
	if (file_exists('/usr/libexec/setup-named-chroot.sh') && !exec('grep -c ' . escapeshellarg('named.conf.keys') . ' /usr/libexec/setup-named-chroot.sh')) {
		file_put_contents('/usr/libexec/setup-named-chroot.sh', str_replace('rndc.key', 'rndc.key /etc/named.conf.keys', file_get_contents('/usr/libexec/setup-named-chroot.sh')));
	}
}


/**
 * Makes the API call
 *
 * @since 4.0
 * @package fmDNS
 */
function callAPI($url, $data) {
	list($url, $data) = loadAPICredentials($url, $data);

	$retval = 0;

	$raw_data = getPostData($url, $data);
	$raw_data = $data['compress'] ? @unserialize(gzuncompress($raw_data)) : @unserialize($raw_data);
	if (is_array($raw_data)) {
		list($retval, $message) = $raw_data;
		$raw_data = ($retval == 3000) ? "$message\n" : sprintf("ERROR (%s) %s\n", $retval, $message);
		$retval = 1;
	}
	echo $raw_data;

	exit($retval);
}


/**
 * Validates the API parameters
 *
 * @since 4.0
 * @package fmDNS
 *
 * @param string $param Parameter name
 * @param string $value Parameter value
 */
function validateAPIParam($param, $value) {
	$api_quick_validation = array(
		'append' => array('yes', 'no'),
		'action' => array('add', 'update', 'delete'),
		'status' => array('active', 'disabled'),
		'reload' => array('yes', 'no')
	);

	if (array_key_exists($param, $api_quick_validation)) {
		if (!in_array($value, $api_quick_validation[$param])) {
			echo fM(sprintf("Supported values for the '%s' parameter: %s\n", $param, join(', ', $api_quick_validation[$param])));
			exit(1);
		}
	} else {
		if (in_array($param, array('ttl', 'priority')) && !is_numeric($value)) {
			echo fM(sprintf("'%s' must be an integer.\n", $param));
			exit(1);
		}
	}
}


/**
 * Sets web server config for URL RR hosting
 *
 * @since 4.0
 * @package fmDNS
 *
 * @param string $param Parameter name
 * @param string $value Parameter value
 * @return array
 */
function setURLConfig() {
	global $module_name;

	/** Detect which web server is running */
	list($web_server, $web_server_conf, $docroot) = getWebServerInfo();
	$data['server_url_server_type'] = $web_server['app'];

	/** Get location of .htaccess file (or equivalent) */
	echo fM("  --> Setting the URL RR rewrite file...");
	switch ($web_server['app']) {
		case 'httpd':
			$data['server_url_config_file'] = $docroot . '/.htaccess';
			break;
		case 'lighttpd':
			$data['server_url_config_file'] = dirname($web_server_conf) . '/' . $module_name . '.conf';

			/** Update lighttpd.conf */
			addToConfigFile($web_server_conf, 'include "' . $module_name . '.conf"');
			break;
		case 'nginx':
			break;
	}

	echo $data['server_url_config_file'] . "\n";

	echo fM("\n  --> Starting {$web_server['app']}...");
	if (isDaemonRunning($web_server['app'])) {
		echo "already running\n";
	} else {
		$rc_script = getStartupScript($web_server['app']);
		if ($rc_script === false) {
			$last_line = "Cannot locate the start script";
			$retval = true;
		} else {
			$last_line = system($rc_script . ' 2>&1', $retval);
		}
	}
	if ($retval) {
		echo "$last_line\n";
	} else {
		echo "running\n";
	}

	return $data;
}


/**
 * Enables URL RR hosting
 *
 * @since 4.0
 * @package fmDNS
 *
 * @param string $param Parameter name
 * @param string $value Parameter value
 * @return boolean
 */
function enableURL() {
	global $module_name, $url, $data, $debug;

	if (!array_key_exists('server_name', $data)) {
		$data['server_name'] = gethostname();
	}

	echo fM("Enabling URL RR hosting...");

	$data = array_merge($data, setURLConfig());

	$raw_data = getPostData(str_replace('buildconf.php', 'admin-servers.php?addserial', $url), $data);
	$raw_data = $data['compress'] ? @unserialize(gzuncompress($raw_data)) : @unserialize($raw_data);

	if ($raw_data) {
		if ($debug) exit($raw_data);
	}

	exit(fM("\nConfiguration complete.\n"));
}


/**
 * Attempts to install required packages
 *
 * @since 6.5.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param string $url URL to post data to
 * @param array $data Array of existing installation data
 * @return array
 */
function moduleInstallApp($url, $data) {
	$packages[] = (isDebianSystem($data['server_os_distro'])) ? 'bind9' : 'bind';
	$services[] = (isDebianSystem($data['server_os_distro'])) ? 'bind9' : '';
	
	installApp($packages, $services);

	return addServer($url, $data, true);
}
