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

/**
 * facileManager Client Utility Common Functions
 *
 * @package facileManager
 * @subpackage Client
 *
 */

error_reporting(0);
$compress = true;

/** Check if PHP is CGI */
if (strpos(php_sapi_name(), 'cgi') !== false) {
	echo fM("Your server is running a CGI version of PHP and the CLI version is required.\n\n");
	exit(1);
}

/** Check for options */
if (in_array('-h', $argv) || in_array('help', $argv)) printHelp();
$debug = (in_array('-d', $argv) || in_array('debug', $argv)) ? true : false;
$proto = (in_array('-s', $argv) || in_array('no-ssl', $argv)) ? 'http' : 'https';
$purge = (in_array('-p', $argv) || in_array('purge', $argv)) ? true : false;

if ($debug) error_reporting(E_ALL ^ E_NOTICE);

/** Display the client version */
if (in_array('-v', $argv) || in_array('version', $argv)) {
	exit(fM($module_name . ' ' . $data['server_client_version'] . "\n"));
}

/** Check if PHP version requirement is met */
if (version_compare(PHP_VERSION, '5.0.0', '<')) {
	echo fM('Your server is running PHP version ' . PHP_VERSION . " but PHP >= 5.0.0 is required.\n");
	exit(1);
}

/** Check if zlib exists */
if (!function_exists('gzuncompress')) {
	if ($debug) echo fM("PHP 'zlib' module is missing; therefore, I'm not using compression and will attempt to enforce ssl.\n");
	$compress = false;
	$proto = 'https';
}

/** Check if openssl exists */
if ($proto == 'https') {
	$proto = function_exists('openssl_open') ? 'https' : 'http';
	if (($debug) && $proto == 'http') echo fM("PHP 'openssl' module is missing; therefore, I'm not using ssl.\n");
}

/** Check if curl exists */
if (!function_exists('curl_init')) {
	echo fM("PHP 'curl' module is missing; therefore, I'm not able to continue.\n");
	exit(1);
}

$config_file = dirname(__FILE__) . '/config.inc.php';

/** Include module functions */
$fm_client_functions = dirname(__FILE__) . '/'. $module_name . '/functions.php';
if (file_exists($fm_client_functions)) {
	require_once($fm_client_functions);
}

/** Detect OS */
$data['server_os'] = PHP_OS;
$data['server_os_distro'] = detectOSDistro();

/** Run the installer */
if (in_array('install', $argv)) {
	if (file_exists($config_file)) {
		require ($config_file);
		if (defined('FMHOST') && defined('AUTHKEY') && defined('SERIALNO')) {
			$proto = (socketTest(FMHOST, 443)) ? 'https' : 'http';
			$url = "${proto}://" . FMHOST . "admin-accounts?verify";
			$data['compress'] = $compress;
			$data['AUTHKEY'] = AUTHKEY;
			$data['SERIALNO'] = SERIALNO;
			$raw_data = getPostData($url, $data);
			$raw_data = $data['compress'] ? @unserialize(gzuncompress($raw_data)) : @unserialize($raw_data);
			if ($raw_data == 'Success') {
				exit(fM("$module_name is already installed.\n"));
			}
		}
	}
	installFM($proto, $compress);
}

/** Dependency on $config_file */
if (!file_exists($config_file)) {
	echo fM("The $module_name client is not installed. Please install it with the following:\nphp {$argv[0]} install\n");
	exit(1);
} else require($config_file);

$data['AUTHKEY'] = AUTHKEY;
$data['SERIALNO'] = SERIALNO;
$data['compress'] = $compress;

/** Check if the port is alive first */
$port = ($proto == 'https') ? 443 : 80;
$server_path = getServerPath(FMHOST);
if (!socketTest($server_path['hostname'], $port, 20)) {
	if ($proto == 'https') {
		if (socketTest($server_path['hostname'], 80, 20)) {
			$proto = 'http';
		} else {
			echo fM($server_path['hostname'] . " is currently not available via tcp/$port.  Aborting.\n");
			exit(1);
		}
	} else {
		echo fM($server_path['hostname'] . " is currently not available via tcp/$port.  Aborting.\n");
		exit(1);
	}
}

/** Run the upgrader */
if (in_array('upgrade', $argv)) {
	upgradeFM($proto . '://' . FMHOST . 'admin-servers?upgrade', $data);
}



/** ============================================================================================= */

function printHelp () {
	global $argv, $module_name;
	
	echo <<<HELP
php {$argv[0]} [options]
  -h|help        Display this help
  -v|version     Display the client version
  -d|debug       Enter debug mode for more output
  -p|purge       Delete old configuration files before writing
  -s|no-ssl      Do not use SSL to retrieve the configs

HELP;

	/** Include module functions */
	$fm_client_functions = dirname(__FILE__) . '/'. $module_name . '/functions.php';
	if (file_exists($fm_client_functions)) {
		require_once($fm_client_functions);
	}
	
	if (function_exists('printModuleHelp')) {
		printModuleHelp();
	}
	
	echo <<<HELP
  
     install     Install the client components
     upgrade     Upgrade the client components

HELP;
	exit;
}


/**
 * Runs the installer
 *
 * @since 1.0
 * @package facileManager
 */
function installFM($proto, $compress) {
	global $argv, $module_name;

	echo fM("Welcome to the $module_name installer.\n\n");
	
	echo fM("Please answer the following questions and the necessary configurations will be performed for you.\n\n");
	
	/** facileManager host **/
	echo "Please enter the location of the facileManager interface:\n";
	echo "    Examples include:\n";
	echo "\tfm.mydomain.com\n";
	echo "\tfm.mydomain.com:8443\n";
	echo "\tmydomain.com/fm\n";
	echo "\thttp://fm.mydomain.com/facileManager\n\n";
	echo 'Please enter the location of the facileManager interface: ';
	if (defined('FMHOST')) {
		$serverhost = FMHOST;
		echo FMHOST . "\n";
	} else {
		$serverhost = trim(fgets(STDIN));
	}
	
	/** Get server name from input */
	$server_location = getServerPath($serverhost);
	extract($server_location);
	
	$data['config'] = array();

	/** Run tests */
	echo fM("  --> Testing $hostname via https...");
	if (socketTest($hostname, 443)) {
		echo "ok\n";
		$proto = 'https';
	} else {
		echo "failed\n";
		echo fM("  --> Testing $hostname via http...");
		if (socketTest($hostname, 80)) {
			echo "ok\n";
			$proto = 'http';
		} else {
			echo "failed\n\n";
			echo fM("Cannot access $hostname with http or https.  Please correct this before proceeding.\n");
			exit(1);
		}
	}
	
	$data['config'][] = array('FMHOST', 'facileManager server', $hostname . '/' . $path);
	
	/** Account key **/
	$key = 'default';
	while (!isset($key)) {
		echo fM('Please enter your account key: ');
		$key = trim(fgets(STDIN));
	}
	
	$data['compress'] = $compress;
	$data['AUTHKEY'] = $key;
	$data['config'][] = array('AUTHKEY', 'Account number', $key);
	
	/** Test the authentication */
	echo fM('  --> Checking account details...');
	$url = "${proto}://${hostname}/${path}admin-accounts?verify";
	$raw_data = getPostData($url, $data);
	$raw_data = $data['compress'] ? @unserialize(gzuncompress($raw_data)) : @unserialize($raw_data);
	echo $raw_data . "\n\n";
	if ($raw_data != 'Success') {
		echo "Installation failed.  ";
		echo (!strlen($raw_data)) ? fM("Could not communicate properly with $hostname.  Failed to access $url.") : fM('Please check your account key.');
		echo "\n";
		exit(1);
	}

	/** Server serial number **/
	$data['server_name'] = php_uname('n');
	$data['server_os'] = PHP_OS;
	$data['server_os_distro'] = detectOSDistro();
	echo fM('Please enter the serial number for ' . $data['server_name'] . ' (or leave blank to create new): ');
	if (defined('SERIALNO')) {
		$serialno = $data['server_serial_no'] = SERIALNO;
		echo SERIALNO . "\n";
	} else {
		$serialno = trim(fgets(STDIN));
	}
	
	$url = "${proto}://${hostname}/${path}admin-servers?genserial";
	
	/** Process new server */
	if (empty($serialno)) {
		/** Generate new serial number */
		echo fM('  --> Generating new serial number: ');
		$serialno = $data['server_serial_no'] = generateSerialNo($url, $data);
		echo $serialno . "\n";
	}

	/** Add new server */
	echo fM('  --> Adding ' . $data['server_name'] . ' to the database...');
	$add_server_result = moduleAddServer($url, $data);
	extract($add_server_result, EXTR_OVERWRITE);
	echo fM($add_result);

	$data['SERIALNO'] = $serialno;
	$data['config'][] = array('SERIALNO', 'Server unique serial number', $serialno);

	$data = installFMModule($module_name, $proto, $compress, $data, $server_location, $url);

	/** Save the file */
	saveFMConfigFile($data);
	
	/** Complete installation */
	$url = "${proto}://${hostname}/${path}admin-servers?install";
	$raw_data = getPostData($url, $data);
	
	/** Add log entry */
	addLogEntry('Client installed successfully.');
	
	echo fM("Installation is complete. Please login to the UI to ensure the server settings are correct.\n");
	
	/** chmod and prepend php to this file */
	chmod($argv[0], 0755);
	$contents = file_get_contents($argv[0]);
	$bin = '#!' . findProgram('php');
	if (strpos($contents, $bin) === false) {
		$contents = $bin . "\n" . $contents;
		file_put_contents($argv[0], $contents);
	}
	
	exit;
}


/**
 * Runs the upgrader
 *
 * @since 1.1
 * @package facileManager
 */
function upgradeFM($url, $data) {
	global $argv, $module_name, $proto;
	
	addLogEntry('Performing client upgrade');
	$message = 'Currently installed version: ' . $data['server_client_version'] . "\n";
	echo fM($message);
	addLogEntry($message);
	
	$raw_data = getPostData($url, $data);
	$raw_data = $data['compress'] ? @unserialize(gzuncompress($raw_data)) : @unserialize($raw_data);
	if (!is_array($raw_data)) {
		echo fM($raw_data);
		addLogEntry($raw_data);
		exit(1);
	}
	
	extract($raw_data);
	
	echo fM('Latest version: ' . $latest_module_version . "\n");
	
	/** Download latest core files */
	echo fM("Downloading ");
	$core_file = 'facilemanager-core-latest.tar.gz';
	downloadfMFile($core_file);
	
	/** Download latest module files */
	echo fM("Downloading ");
	$module_file = strtolower($module_name) . '-' . $latest_module_version . '.tar.gz';
	downloadfMFile($module_file, true);
	
	/** Extract client files */
	$message = "Extracting client files.\n";
	echo fM($message);
	addLogEntry($message);
	extractFiles(array('/tmp/' . $core_file, '/tmp/' . $module_file));
	
	/** Cleanup */
	$message = "Cleaning up.\n";
	echo fM($message);
	addLogEntry($message);
	@unlink('/tmp/' . $core_file);
	@unlink('/tmp/' . $module_file);
	
	$message = "Client upgrade complete.\n";
	echo fM($message);
	addLogEntry($message);
	
	/** Update the database with the new version */
	$data['server_client_version'] = $latest_module_version;
	$raw_data = getPostData($url, $data);
	die();
}


/**
 * Finds the path for $program
 *
 * @since 1.0
 * @package facileManager
 */
function findProgram($program) {
	$path = array('/bin', '/sbin', '/usr/bin', '/usr/sbin', '/usr/local/bin', '/usr/local/sbin');

	if (function_exists('is_executable')) {
		while ($this_path = current($path)) {
			if (is_executable("$this_path/$program")) {
				return "$this_path/$program";
			}
			next($path);
		}
	}

	return false;
}


/**
 * Tests a $port on $host
 *
 * @since 1.0
 * @package facileManager
 */
function socketTest($host, $port, $timeout = '20') {
	$fm = @fsockopen($host, $port, $errno, $errstr, $timeout);
	if (!$fm) return false;
	else {
		fclose($fm);
		return true;
	}
}


/**
 * Returns result from a http post
 *
 * @since 1.0
 * @package facileManager
 */
function getPostData($url, $data) {
	global $module_name, $debug;
	
	$data['module_name'] = $module_name;
	$data['module_type'] = 'CLIENT';
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	$result = curl_exec($ch);
	if ($debug && $result === false) {
		echo "\n\n" . curl_error($ch);
	}
	curl_close($ch);
	return $result;
}


/**
 * Returns the server path of the facileManager app
 *
 * @since 1.0
 * @package facileManager
 */
function getServerPath($server) {
	if (!strpos($server, '/')) return array('hostname'=>$server, 'path'=>null);
	
	$server = str_replace('http://', '', $server);
	$server = str_replace('https://', '', $server);
	$server_array = explode('/', $server);
	
	$return['hostname'] = $server_array[0];
	$return['path'] = null;
	
	for ($i=1; $i<count($server_array); $i++) {
		if ($server_array[$i]) $return['path'] .= $server_array[$i] . '/';
	}
	
	return $return;
}


/**
 * Pings the $server to check if it's alive
 *
 * @since 1.0
 * @package facileManager
 *
 * @param string $server Server hostname to ping
 * @return boolean
 */
function pingTest($server) {
	$program = findProgram('ping');
	if (PHP_OS == 'FreeBSD' || PHP_OS == 'Darwin') {
		$ping = shell_exec("$program -t 2 -c 3 $server 2>/dev/null");
	} elseif (PHP_OS == 'Linux') {
		$ping = shell_exec("$program -W 2 -c 3 $server 2>/dev/null");
	} else {
		$ping = shell_exec("$program -c 3 $server 2>/dev/null");
	}
	if (preg_match('/64 bytes from/', $ping)) {
		return true;
	}
	return false;
}


/**
 * Builds the client config.inc.php
 *
 * @since 1.0
 * @package facileManager
 */
function buildFMConfigFile($data) {
	/** Create config.inc.php */
	$contents = <<<CONFIG
<?php

/**
 * Contains configuration details for facileManager
 *
 * @package facileManager
 *
 */

CONFIG;

	foreach ($data['config'] as $array) {
		list($key, $description, $value) = $array;
		
		/** Replace value if it already exists */
		if (strpos('', "define('$key'")) {
			$contents .= null;
		} else {
			$contents .= <<<CONFIG

/** $description */
define('$key', '$value');

CONFIG;
		}
	}
	$contents .= "\n" . '?>';
	
//	echo $contents;

	return $contents;
}


/**
 * Saves the client config.inc.php
 *
 * @since 1.0
 * @package facileManager
 */
function saveFMConfigFile($data) {
	global $config_file;
	
	if (file_put_contents($config_file, buildFMConfigFile($data)) === false) {
		echo fM("\nInstallation failed.  Could not write $config_file.\n");
		exit(1);
	} else {
		echo fM("\nConfiguration file has been saved.\n\n");
	}
}


/**
 * Generates a serial number
 *
 * @since 1.0
 * @package facileManager
 *
 * @param string $url URL to generate serial number
 * @param array $data Array to pass to the server
 * @return integer
 */
function generateSerialNo($url, $data) {
	/** Generate a new serial number */
	$raw_data = getPostData($url, $data);
	$raw_data = $data['compress'] ? @unserialize(gzuncompress($raw_data)) : @unserialize($raw_data);
	if (!is_array($raw_data)) {
		if (empty($raw_data)) {
			echo fM("Failed to retrieve $url.\n");
		}
		echo $raw_data;
		exit(1);
	}
	
	return $raw_data['server_serial_no'];
}


/**
 * Detects web server daemon
 *
 * @since 1.0
 * @package facileManager
 *
 * @return string
 */
function detectHttpd() {
	$httpd_choices = array(
							'httpd'=>'httpd.conf',
							'httpd2'=>'httpd.conf',
							'apache2'=>'apache2.conf',
							'lighttpd'=>''
						);
	
	foreach ($httpd_choices as $app => $file) {
		if (findProgram($app)) return array('app'=>$app, 'file'=>$file);
	}
	
	return false;
}


/**
 * Detects OS and distribution
 *
 * @since 1.0
 * @package facileManager
 *
 * @return string
 */
function detectOSDistro() {
	if (PHP_OS == 'Linux') {
		/** declare supported Linux distros */
		$distros = array(
			'Arch'       => '/etc/arch-release',
			'ALT'        => '/etc/altlinux-release',
			'Sabayon'    => '/etc/sabayon-release',
			'Slackware'  => '/etc/slackware-version;/etc/slackware-release',
			'SUSE'       => '/etc/SuSE-release;/etc/UnitedLinux-release',
			'Gentoo'     => '/etc/gentoo-release',
			'Mandrake'   => '/etc/mandrake-release;/etc/mandrakelinux-release;/etc/mandiva-release',
			'Vector'     => '/etc/vector-version',
			'Porteus'    => '/etc/porteus-version',
			'SMS'        => '/etc/sms-version',
			'PCLinuxOS'  => '/etc/pclinuxos-version',
			'Turbolinux' => '/etc/turbolinux-version'
			);
		
		/** Debian-based systems */
		if ($program = findProgram('lsb_release')) {
			$lsb_release = shell_exec($program . ' -a 2>/dev/null | grep -i distributor');
			if ($lsb_release && trim($lsb_release) != '') {
				$distrib = explode(':', $lsb_release);
				$distrib_id = explode(' ', trim($distrib[1]));
				return $distrib_id[0];
			} elseif (file_exists($filename = '/etc/lsb-release')
				&& $lsb_release = file_get_contents($filename)
				&& preg_match('/^DISTRIB_ID="?([^"\n]+)"?/m', $lsb_release, $id)) {
				 return trim($id[1]);
			}
		}
		
		/** Redhat-based systems */
		if (file_exists($filename = '/etc/redhat-release')
			&& $rh_release = file_get_contents($filename)) {
			 $rh_release = explode(' ', $rh_release);
			 return $rh_release[0];
		}
		
		/** All other systems */
		foreach ($distros as $distro => $release_files) {
			$release_file_array = explode(';', $release_files);
			foreach ($release_file_array as $release_file) {
				if (file_exists($release_file)) return $distro;
			}
		}
	} elseif (PHP_OS == 'Darwin') {
		@exec(findProgram('system_profiler') . ' SPSoftwareDataType 2>/dev/null', $output, $retval);
		if (!$retval) {
			foreach ($output as $line) {
				$array_line = explode(':', $line);
				if (trim($array_line[0]) == 'System Version') {
					$distro = trim($array_line[1]);
					if (preg_match('/(^Mac OS)|(^OS X)/', $distro)) return 'Apple';
				}
			}
		}
	}
	
	return PHP_OS;
}


/**
 * Initializes web requests for client interaction
 *
 * @since 1.0
 * @package facileManager
 */
function initWebRequest() {
	if (empty($_POST)) {
		exit(fM('Incorrect parameters defined.'));
	}
	
	/** Get the config file */
	if (file_exists(dirname(__FILE__) . '/config.inc.php')) {
		require(dirname(__FILE__) . '/config.inc.php');
	}
	
	if (!defined('SERIALNO')) {
		exit(serialize(fM('Cannot find the serial number for ' . php_uname('n') . '.')));
	}
	
	extract($_POST, EXTR_SKIP);
	
	/** Ensure the serial numbers match so we don't work on the wrong server */
	if ($serial_no != SERIALNO) {
		exit(serialize(fM('The serial numbers do not match for ' . php_uname('n') . '.')));
	}
}


/**
 * Processes the update method and prepares the system
 *
 * @since 1.0
 * @package facileManager
 *
 * @param string $module_name Module currently being used
 * @param string $update_method User entered update method
 * @return string
 */
function processUpdateMethod($module_name, $update_method, $data, $url) {
	global $argv;
	
	switch($update_method) {
		/** cron */
		case 'c':
			$tmpfile = '/tmp/crontab.facileManager';
			$dump = shell_exec('crontab -l | grep -v ' . $argv[0] . '> ' . $tmpfile . ' 2>/dev/null');
			
			/** Handle special cases */
			if (PHP_OS == 'SunOS') {
				for ($x = 0; $x < 12; $x++) {
					$minopt[] = sprintf("%02d", $x*5);
				}
				$minutes = implode(',', $minopt);
				unset($minopt);
			} else $minutes = '*/5';
			
			$cmd = "echo '" . $minutes . ' * * * * ' . findProgram('php') . ' ' . $argv[0] . " cron' >> $tmpfile && " . findProgram('crontab') . ' ' . $tmpfile;
			$cron_update = system($cmd, $retval);
			unlink($tmpfile);
			
			if ($retval) echo fM("  --> The crontab cannot be created.\n  --> $cmd\n");
			else echo fM("  --> The crontab has been created.\n");
			
			return 'cron';

			break;
		/** ssh */
		case 's':
			$user = 'fm_user';
			
			/** Get local users */
			$passwd_users = explode("\n", preg_replace('/:.*/', '', @file_get_contents('/etc/passwd')));
			
			/** Add fm_user */
			echo fM("  --> Attempting to create system user ($user)...");
			if (! $ssh_dir = addUser(array($user, 'facileManager'), $passwd_users)) {
				echo "failed\n";
				echo fM("\nInstallation aborted.\n");
				exit(1);
			} else echo "ok\n";
			
			/** Add ssh public key */
			echo fM("  --> Installing SSH key...");
			$raw_data = getPostData(str_replace('genserial', 'sshkey', $url), $data);
			$raw_data = $data['compress'] ? @unserialize(gzuncompress($raw_data)) : @unserialize($raw_data);
			if (strpos($raw_data, 'ssh-rsa') !== false) {
				$result = (strpos(@file_get_contents($ssh_dir . '/authorized_keys2'), $raw_data) === false) ? @file_put_contents($ssh_dir . '/authorized_keys2', $raw_data, FILE_APPEND) : true;
				@chown($ssh_dir . '/authorized_keys2', $user);
				@chmod($ssh_dir . '/authorized_keys2', 0600);
				if ($result !== false) $result = 'ok';
			} else {
				$result = 'failed';
			}
			echo $result . "\n\n";
			if ($result == 'failed') {
				echo fM("Installation failed.  No SSH key found for this account.\n");
				exit(1);
			}
			
			/** Add an entry to sudoers */
			$sudoers = findFile('sudoers');
			$sudoers_line = "$user\tALL=(root)\tNOPASSWD: " . findProgram('php') . ' ' . $argv[0] . ' *';
			
			if (!$sudoers) {
				echo fM("  --> It does not appear sudo is installed.  Please install it and add the following to the sudoers file:\n");
				echo fM("\n      $sudoers_line\n");
				
				echo fM("\nInstallation aborted.\n");
				exit(1);
			} else {
				$cmd = "echo '$sudoers_line' >> $sudoers 2>/dev/null";
				if (strpos(file_get_contents($sudoers), $sudoers_line) === false) {
					$sudoers_update = system($cmd, $retval);
				
					if ($retval) echo fM("  --> The sudoers entry cannot be added.\n$cmd\n");
					else echo fM("  --> The sudoers entry has been added.\n");
				} else echo fM("  --> The sudoers entry already exists...skipping\n");
				
				/** Check for bad settings and disable */
				$bad_settings = array('requiretty', 'env_reset');
				foreach ($bad_settings as $setting) {
					$found_bad = shell_exec("grep $setting $sudoers | grep -cv '^#'");
					if ($found_bad != 0) {
						echo fM("  --> Disabling 'Defaults $setting' in $sudoers...\n");
						shell_exec("sed -i 's/.*$setting/#&/' $sudoers");
					}
				}
			}

			return 'ssh';
			
			break;
		/** http(s) */
		case 'h':
			/** Detect which web server is running */
			$web_server = detectHttpd();
			if (!is_array($web_server)) {
				echo fM("\nCannot find a supported web server - please check the README document for supported web servers.  Aborting.\n");
				exit(1);
			}
			
			/** Add a symlink to the docroot */
			$httpdconf = findFile($web_server['file']);
			if (!$httpdconf) {
				echo fM("\nCannot find " . $web_server['file'] . '.  Please enter the full path of ' . $web_server['file'] . ' (/etc/httpd/conf/httpd.conf): ');
				$httpdconf = trim(strtolower(fgets(STDIN)));
				
				/** Check if the file exists */
				if (!is_file($httpdconf)) {
					echo fM("  --> $httpdconf does not exist.  Aborting.\n");
					exit(1);
				}
			}
			$raw_root = explode('"', shell_exec('grep ^DocumentRoot ' . $httpdconf));
			/** Get the docroot from STDIN if it's not found */
			if (count($raw_root) <= 1) {
				echo fM("\nCannot find DocumentRoot in " . $web_server['file'] . ".  Please enter the full path of your default DocumentRoot (/var/www/html): ");
				$docroot = rtrim(trim(strtolower(fgets(STDIN))), '/');
			} else $docroot = trim($raw_root[1]);
				
			/** Check if the docroot exists */
			if (!is_dir($docroot)) {
				echo fM("  --> $docroot does not exist.  Aborting.\n");
				exit(1);
			}
			$link_name = $docroot . DIRECTORY_SEPARATOR . $module_name;
			
			echo fM("  --> Creating $link_name link.\n");
			
			if (!is_link($link_name)) {
				symlink(dirname(__FILE__) . '/' . $module_name . '/www', $link_name);
			} else echo fM("      --> $link_name already exists...skipping\n");
			
			/** Add an entry to sudoers */
			$sudoers = findFile('sudoers');
			$raw_user = explode(' ', shell_exec('grep ^User ' . $httpdconf));
			$user = trim($raw_user[1]);
			if ($user[0] == '$') {
				$user_var = preg_replace(array('/\$/', '/{/', '/}/'), '', $user);
				$raw_user = explode('=', shell_exec('grep ' . $user_var . ' ' . findFile('envvars')));
				if (count($raw_user)) {
					$user = trim($raw_user[1]);
				}
			}
			$sudoers_line = "$user\tALL=(root)\tNOPASSWD: " . findProgram('php') . ' ' . $argv[0] . ' *';
			
			echo fM('  --> Detected ' . $web_server['app'] . " runs as '$user'\n");
			
			if (!$sudoers) {
				echo fM("  --> It does not appear sudo is installed.  Please install it and add the following to the sudoers file:\n");
				echo fM("\n      $sudoers_line\n");
				
				echo fM("\nInstallation aborted.\n");
				exit(1);
			} else {
				$cmd = "echo '$sudoers_line' >> $sudoers 2>/dev/null";
				if (strpos(file_get_contents($sudoers), $sudoers_line) === false) {
					$sudoers_update = system($cmd, $retval);
				
					if ($retval) echo fM("  --> The sudoers entry cannot be added.\n$cmd\n");
					else echo fM("  --> The sudoers entry has been added.\n");
				} else echo fM("  --> The sudoers entry already exists...skipping\n");
				
				/** Check for bad settings and disable */
				$bad_settings = array('requiretty', 'env_reset');
				foreach ($bad_settings as $setting) {
					$found_bad = shell_exec("grep $setting $sudoers | grep -cv '^#'");
					if ($found_bad != 0) {
						echo fM("  --> Disabling 'Defaults $setting' in $sudoers...\n");
						shell_exec("sed -i 's/.*$setting/#&/' $sudoers");
					}
				}
			}

			return 'http';

			break;
	}
}


/**
 * Attempts to add a system user account
 *
 * @since 1.0
 * @package facileManager
 *
 * @param string $user Username to add
 * @return boolean
 */
function addUser($user_info, $passwd_users) {
	list($user, $user_name) = $user_info;
	
	$retval = false;
	
	switch (PHP_OS) {
		case 'Linux':
			if (!in_array($user, $passwd_users)) {
				$result = system(findProgram('useradd') . " -m -c '$username' $user", $retval);
			}
			if (!$retval) {
				if (!is_dir("/home/$user/.ssh")) {
					@mkdir("/home/$user/.ssh");
					@chown("/home/$user/.ssh", $user);
					@chgrp("/home/$user/.ssh", $user);
				}
				return "/home/$user/.ssh";
			}
			break;
		case 'FreeBSD':
			break;
		case 'Darwin':
			break;
	}
	
	return false;
}


/**
 * Adds a log entry
 *
 * @since 1.1
 * @package facileManager
 *
 * @param string $log_data Data to add to the log file
 * @return boolean
 */
function addLogEntry($log_data) {
	global $module_name;
	
	$log_file = '/var/log/fm.log';
	$date = date('M d H:i:s');
	
	$log_data = explode("\n", trim($log_data));
	foreach ($log_data as $log_line) {
		@file_put_contents($log_file, $date . ' ' . $module_name . ': ' . trim($log_line) . "\n", FILE_APPEND | LOCK_EX);
	}
}


/**
 * Writes files to the filesystem
 *
 * @since 1.1
 * @package facileManager
 *
 * @param string $user User dirs/files should be chowned as
 * @param array $chown_files dirs/files that should be chowned prior to writing
 * @param array $files Files and contents to write
 * @param boolean $dryrun Whether or not files should be written
 * @return boolean
 */
function installFiles($user, $chown_files, $files, $dryrun) {
	$message = "Setting directory and file permissions for $user.\n";
	if ($debug) echo $message;
	if (!$dryrun) {
		addLogEntry($message);
		/** chown the files/dirs */
		foreach($chown_files as $file) {
			@chown($file, $user);
		}
	}
		
	/** Process the files */
	if (count($files)) {
		foreach($files as $filename => $contents) {
			$message = "Writing $filename.\n";
			if ($debug) echo $message;
			if (!$dryrun) {
				addLogEntry($message);
				@mkdir(dirname($filename), 0755, true);
				@chown(dirname($filename), $user);
				file_put_contents($filename, $contents);
				@chown($filename, $user);
			}
		}
	} else {
		$message = fM("There are no files to save. Aborting.\n");
		echo $message;
		addLogEntry($message);
		exit(1);
	}
	
	return true;
}


/**
 * Formats a string for output
 *
 * @since 1.1
 * @package facileManager
 *
 * @param string $message Data to format
 * @return string
 */
function fM($message) {
	return wordwrap($message, 90, "\n");
}


/**
 * Downloads a file from the fM website
 *
 * @since 1.1
 * @package facileManager
 *
 * @param string $file File to download
 * @param boolean $module Whether or not this is a module download
 */
function downloadfMFile($file, $module = false) {
	$base_url = 'http://www.facilemanager.com/download/';
	if ($module) $base_url .= 'module/';
	$base_url .= $file;
	
	echo fM($base_url . "\n");
	addLogEntry("Downloading $base_url\n");
	
	$local_file = '/tmp/' . $file;
	@unlink($local_file);
	
	$ch = curl_init();
	$options = array(
		CURLOPT_URL				=> $base_url,
		CURLOPT_FILE			=> $local_file,
		CURLOPT_TIMEOUT			=> 3600,
		CURLOPT_RETURNTRANSFER	=> 1,
		CURLOPT_FOLLOWLOCATION	=> true
	);
	curl_setopt_array($ch, $options);
	$result = curl_exec($ch);
	if ($result === false || curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
		$message = "Unable to download file.\n";
		echo fM($message . "\n" . curl_error($ch) . "\n");
		addLogEntry($message . "\n" . curl_error($ch));
		exit(1);
	}
	curl_close($ch);
	
	file_put_contents($local_file, $result);
}


/**
 * Extracts files
 *
 * @since 1.1
 * @package facileManager
 *
 * @param array $files Files to extract
 */
function extractFiles($files = array()) {
	$tmp_dir = '/tmp/fM_files';
	mkdir($tmp_dir);
	
	foreach ($files as $filename) {
		$path_parts = pathinfo($filename);
		$untar_opt = '-C ' . $tmp_dir . ' -x';
		switch($path_parts['extension']) {
			case 'bz2':
				$untar_opt .= 'j';
				break;
			case 'tgz':
			case 'gz':
				$untar_opt .= 'z';
				break;
		}
		$untar_opt .= 'f';
		
		$command = findProgram('tar') . " $untar_opt $filename";
		@system($command, $retval);
		if ($retval) {
			$message = "Failed to extract $filename. Exiting.\n";
			echo fM($message);
			addLogEntry($message);
			exit(1);
		}
	}
		
	/** Move files */
	$command = findProgram('cp') . " -r $tmp_dir/facileManager/client/facileManager " . dirname(dirname(__FILE__));
	@system($command, $retval);
	if ($retval) {
		$message = "Failed to save files. Exiting.\n";
		echo fM($message);
		addLogEntry($message);
		exit(1);
	}
	
	if ($tmp_dir != '/') {
		@system(findProgram('rm') . " -rf $tmp_dir");
	}
}


?>
