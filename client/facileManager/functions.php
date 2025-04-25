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
$privileged_user = 'root';
$url = null;

if (!isset($module_name) && isset($_POST['module'])) $module_name = $_POST['module'];

/** Check if PHP is CGI for CLI operations */
if (strpos(php_sapi_name(), 'cgi') !== false && count($argv)) {
	echo fM("Your server is running a CGI version of PHP and the CLI version is required.\n\n");
	exit(1);
}

/** Check for options */
if (in_array('-h', $argv) || in_array('help', $argv)) printHelp();
$debug		= (in_array('-d', $argv) || in_array('debug', $argv)) ? true : false;
$proto		= (in_array('-s', $argv) || in_array('no-ssl', $argv)) ? 'http' : 'https';
$purge		= (in_array('-p', $argv) || in_array('purge', $argv)) ? true : false;
$no_sudoers	= (in_array('no-sudoers', $argv)) ? true : false;
$dryrun		= (in_array('-n', $argv) || in_array('dryrun', $argv)) ? true : false;
$buildconf	= (in_array('-b', $argv) || in_array('buildconf', $argv)) ? true : false;
$cron		= (in_array('-c', $argv) || in_array('cron', $argv)) ? true : false;

$apitest	= (in_array('apitest', $argv)) ? true : false;
$no_ssl		= ($proto == 'http') ? true : false;

if ($debug) error_reporting(E_ALL ^ E_NOTICE);

/** Display the client version */
if (in_array('-v', $argv) || in_array('version', $argv)) {
	exit(fM($module_name . ' ' . $data['server_client_version'] . "\n"));
}

/** Check if PHP version requirement is met */
if (version_compare(PHP_VERSION, '5.0.0', '<')) {
	echo fM('This system has PHP version ' . PHP_VERSION . " installed and PHP >= 5.0.0 is required.\n");
	exit(1);
}

/** Check if zlib exists */
if (!function_exists('gzuncompress')) {
	if ($debug) echo fM("PHP 'zlib' module is missing; therefore, compression cannot be used and enforcing SSL will be attempted.\n");
	$compress = false;
	$proto = 'https';
}

/** Check if openssl exists */
if ($proto == 'https') {
	$proto = function_exists('openssl_open') ? 'https' : 'http';
	if (($debug) && $proto == 'http') echo fM("PHP 'openssl' module is missing; therefore, SSL cannot be used.\n");
}

/** Check if curl exists */
if (!function_exists('curl_init')) {
	echo fM("PHP 'curl' module is missing. Aborting.\n");
	exit(1);
}

/** Define sys_get_temp_dir function */
if (!function_exists('sys_get_temp_dir')) {
	function sys_get_temp_dir() {
		return '/tmp';
	}
}

/** Check running user */
if (exec(findProgram('whoami')) != $privileged_user && !$dryrun && count($argv)) {
	echo fM("This script must run as $privileged_user.\n");
	exit(1);
}

/** Build everything required via cron */
if ($cron) {
	$data['action'] = 'cron';
}

/** Build the server config */
if ($buildconf) {
	$data['action'] = 'buildconf';
}

$data['dryrun'] = $dryrun;

$config_file = dirname(__FILE__) . '/config.inc.php';

/** Include module functions */
$fm_client_functions = dirname(__FILE__) . '/'. $module_name . '/functions.php';
if (file_exists($fm_client_functions)) {
	require_once($fm_client_functions);
}

/** Detect OS */
$data['server_os'] = PHP_OS;
$data['server_os_distro'] = detectOSDistro();

$data['update_from_client']	= (in_array('no-update', $argv)) ? false : true;

/** Run the installer */
if (in_array('install', $argv) || in_array('reinstall', $argv)) {
	if (file_exists($config_file)) {
		require($config_file);
		if (defined('FMHOST') && defined('AUTHKEY') && defined('SERIALNO') && in_array('install', $argv)) {
			$proto = (socketTest(FMHOST, 443)) ? 'https' : 'http';
			$url = "{$proto}://" . FMHOST . "admin-accounts.php?verify";
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
} else require_once($config_file);

$data['AUTHKEY'] = AUTHKEY;
$data['SERIALNO'] = SERIALNO;
$data['compress'] = $compress;

/** Check if the port is alive first */
$server_path = getServerPath(FMHOST);
if (!$server_path['port']) {
	$port = ($proto == 'https') ? 443 : 80;
} else {
	$port = $server_path['port'];
}
if (!socketTest($server_path['hostname'], $port)) {
	$socket_failure = $server_path['hostname'] . " is currently not available via tcp/$port.  Aborting.\n";
	if ($proto == 'https') {
		if (socketTest($server_path['hostname'], 80)) {
			$proto = 'http';
		} else {
			echo fM($socket_failure);
			exit(1);
		}
	} else {
		echo fM($socket_failure);
		exit(1);
	}
}

/** Set variables to pass */
$url = $proto . '://' . FMHOST . 'buildconf.php';

/** Run the upgrader */
if (in_array('upgrade', $argv)) {
	upgradeFM($proto . '://' . FMHOST . 'admin-servers.php?upgrade', $data);
}

/** Test API functionality */
if ($apitest) {
	/** Run the API tests */
	doAPITest($url, $data);
}

/** Display dry-run messaging */
if ($dryrun && $debug) echo fM("Dryrun mode (nothing will be written to disk)\n\n");


/** ============================================================================================= */

function printHelp () {
	global $argv, $module_name;
	
	echo <<<HELP
php {$argv[0]} [options]

  -b|buildconf                Build server configuration and associated files
  -c|cron                     Run in cron mode
  -d|debug                    Enter debug mode for more output
  -h|help                     Display this help
  -n|dryrun                   Do not save any files - just output what will happen
  -p|purge                    Delete old configuration files before writing
  -s|no-ssl                   Do not use SSL to retrieve the configs
  -v|version                  Display the client version
     no-sudoers               Do not create/update the sudoers file at install time
     no-update                Do not update the server configuration from the client
     apitest                  Perform basic API functionality tests

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
  
     install                  Install the client components
  -o|options                  Installation options comma-delimited to avoid prompts
                                Valid options: 
                                    FMHOST    fM host url
                                    SERIALNO  Server serial number (or 0 to auto-generate)
                                    method    Update method to use (cron, ssh, http)
                                Examples: install -o FMHOST=https://example.com/fm/,method=cron
                                          install options FMHOST=fm.example.com,SERIALNO=0,method=ssh
     upgrade                  Upgrade the client components
     reinstall                Reinstall the client components

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
	global $argv, $module_name, $data, $no_ssl, $debug;
	
	/** Get long options */
	for ($i=0; $i < count($argv); $i++) {
		if ($argv[$i] == '-o' || $argv[$i] == 'options') {
			$install_options = trim($argv[$i+1], '"');
			if ($debug) echo fM("Setting install options ($install_options).\n");
			foreach (explode(',', $install_options) as $full_option) {
				list($key, $value) = explode('=', trim($full_option));
				$key = strtoupper($key);
				$value = strtolower($value);
				if (!defined($key)) {
					if ($key == 'METHOD') {
						$choices = array('cron', 'ssh', 'http');
						if (in_array($value, $choices)) {
							$value = $value[0];
						} else {
							echo fM("Invalid value for {$key}.\n");
							exit(1);
						}
					}
					if ($debug) echo fM("$key = $value\n");
					define($key, $value);
				} else {
					if ($debug) echo fM("$key is already defined in config.inc.php.\n");
				}
			}
			break;
		}
	}

	unset($data['SERIALNO']);

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
	$connection_test_success = false;
	if (isset($proto_def)) {
		if (!$port) {
			$port = ($proto_def == 'https') ? 443 : 80;
		}
		echo fM("  --> Testing $hostname:$port via $proto_def...");
		if (socketTest($hostname, $port)) {
			echo "ok\n";
			$proto = $proto_def;
			$connection_test_success = true;
		} else {
			echo "failed\n\n";
		}
	}
	if (!$connection_test_success) {
		if (!$no_ssl) echo fM("  --> Testing $hostname via https...");
		if (!$no_ssl && !$port) $port = 443;
		if (!$no_ssl && socketTest($hostname, $port)) {
			echo "ok\n";
			$proto = 'https';
		} else {
			echo "failed\n";
			echo fM("  --> Testing $hostname via http...");
			if ($port == 443) $port = 80;
			if (socketTest($hostname, $port)) {
				echo "ok\n";
				$proto = 'http';
			} else {
				echo "failed\n\n";
				echo fM("Cannot access $hostname with http or https.  Please correct this before proceeding.\n");
				exit(1);
			}
		}
	}
	
	if (!in_array($port, array(80, 443))) {
		$hostname .= ':' . $port;
		$server_location['hostname'] = $hostname;
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
	$url = "{$proto}://{$hostname}/{$path}admin-accounts.php?verify";
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
	$data['server_name'] = gethostname();

	$data['server_os'] = PHP_OS;
	$data['server_os_distro'] = detectOSDistro();
	echo fM('Please enter the serial number for ' . $data['server_name'] . ' (or leave blank to create new): ');
	if (defined('SERIALNO')) {
		$serialno = $data['server_serial_no'] = intval(SERIALNO);
		echo SERIALNO . "\n";
	} else {
		$serialno = intval(trim(fgets(STDIN)));
	}
	
	$url = "{$proto}://{$hostname}/{$path}admin-servers.php?genserial";
	
	/** Process new server */
	if (empty($serialno) || $serialno < 1) {
		/** Generate new serial number */
		echo fM('  --> Generating new serial number: ');
		$serialno = $data['server_serial_no'] = generateSerialNo($url, $data);
		echo $serialno . "\n";
	}

	/** Add new server */
	$data = addServer($url, $data);

	$data['SERIALNO'] = $serialno;
	$data['config'][] = array('SERIALNO', 'Server unique serial number', $serialno);

	/** Get module-specific data */
	if (function_exists('installFMModule')) {
		$data = installFMModule($module_name, $proto, $compress, $data, $server_location, $url);
	}

	/** Handle the update method */
	$data['server_update_method'] = processUpdateMethod($module_name, $update_method, $data, $url);

	$raw_data = getPostData(str_replace('genserial', 'addserial', $url), $data);
	$raw_data = $data['compress'] ? @unserialize(gzuncompress($raw_data)) : @unserialize($raw_data);
	
	/** Save the file */
	saveFMConfigFile($data);
	
	/** Complete installation */
	$url = "{$proto}://{$hostname}/{$path}admin-servers.php?install";
	$raw_data = getPostData($url, $data);
	
	/** Add log entry */
	addLogEntry('Client installed successfully.');
	
	echo fM("Installation is complete. Please login to the UI to ensure the server settings are correct.\n");
	
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
	$message = 'Installed version: ' . $data['server_client_version'] . "\n";
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
	$core_file = 'facilemanager-core-' . $latest_core_version . '.tar.gz';
	downloadfMFile($core_file, $proxy_info);
	
	/** Download latest module files */
	$module_file = strtolower($module_name) . '-' . $latest_module_version . '.tar.gz';
	downloadfMFile($module_file, $proxy_info, true);
	
	/** Extract client files */
	$message = "Extracting client files\n";
	echo fM($message);
	addLogEntry($message);
	extractFiles(array(sys_get_temp_dir() . '/' . $core_file, sys_get_temp_dir() . '/' . $module_file));
	
	/** Cleanup */
	$message = "Cleaning up\n";
	echo fM($message);
	addLogEntry($message);
	@unlink(sys_get_temp_dir() . '/' . $core_file);
	@unlink(sys_get_temp_dir() . '/' . $module_file);
	
	$message = "Client upgrade complete\n";
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
			if (file_exists("$this_path/$program")) {
				$perms = fileperms("$this_path/$program");
				if (is_executable("$this_path/$program") || ($perms & 0x0040) || ($perms & 0x0008) || ($perms & 0x0001)) {
					return "$this_path/$program";
				}
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
	global $debug;

	$fm = @fsockopen($host, $port, $errno, $errstr, $timeout);
	if (!$fm) {
		if ($debug) {
			echo fM(sprintf("Socket test failed to %s tcp/%s.\n", $host, $port));
			echo fM(sprintf("Error [%s]: %s\n", $errno, $errstr));
		}
		return false;
	} else {
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
	return ltrim($result);
}


/**
 * Returns the server path of the facileManager app
 *
 * @since 1.0
 * @package facileManager
 */
function getServerPath($server) {
	if (strpos($server, '://') !== false) {
		$return['proto_def'] = substr($server, 0, strpos($server, '://'));
	}
	$server = str_replace(array('http://', 'https://'), '', $server);
	$server_array = explode('/', $server);
	
	$return['hostname'] = $server_array[0];
	$return['path'] = null;
	
	for ($i=1; $i<count($server_array); $i++) {
		if ($server_array[$i]) $return['path'] .= $server_array[$i] . '/';
	}
	
	$return['port'] = null;
	if (strpos($return['hostname'], ':')) {
		list($return['hostname'], $return['port']) = explode(':', $return['hostname']);
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
		$ping = shell_exec("$program -t 2 -c 3 " . escapeshellarg($server) . " 2>/dev/null");
	} elseif (PHP_OS == 'Linux') {
		$ping = shell_exec("$program -W 2 -c 3 " . escapeshellarg($server) . " 2>/dev/null");
	} else {
		$ping = shell_exec("$program -c 3 " . escapeshellarg($server) . " 2>/dev/null");
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
 * @return array|boolean
 */
function detectWebServer() {
	$httpd_choices = array(
		'httpd'=>'httpd.conf',
		'httpd2'=>'httpd.conf',
		'apache2'=>'apache2.conf',
		'lighttpd'=>'lighttpd.conf',
		'nginx'=>'nginx.conf'
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
		
		$raspberry_pi = array('Raspbian', 'Raspberry Pi OS', 'Pidora');
		
		/** Debian-based systems */
		if ($program = findProgram('lsb_release')) {
			$lsb_release = shell_exec($program . ' -a 2>/dev/null | grep -i distributor');
			if ($lsb_release && trim($lsb_release) != '') {
				$distrib = explode(':', $lsb_release);
				$distrib_id = explode(' ', trim($distrib[1]));
				return (substr($distrib_id[0], 0, 3) == 'Red') ? 'Redhat' : $distrib_id[0];
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
			 return ($rh_release[0] == 'Red') ? 'Redhat' : $rh_release[0];
		}
		
		/** OS-release systems */
		if (file_exists($filename = '/etc/os-release')
			&& $os_release = parse_ini_file($filename)) {
			 $os_release = explode(' ', $os_release['NAME']);
			 
			 if (in_array(ucfirst($os_release[0]), $raspberry_pi)) {
				 return 'Raspberry Pi';
			 }
			 
			 return $os_release[0];
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
	
	/** Process action request */
	if (isset($_POST['action'])) {
		switch ($_POST['action']) {
			case 'buildconf':
				exec(findProgram('sudo') . ' ' . findProgram('php') . ' ' . dirname(__FILE__) . '/' . $_POST['module'] .  '/client.php buildconf' . $_POST['options'] . ' 2>&1', $output, $rc);
				if ($rc) {
					/** Something went wrong */
					$output[] = 'Config build failed.';
				}
				break;
			case 'upgrade':
				exec(findProgram('sudo') . ' ' . findProgram('php') . ' ' . dirname(__FILE__) . '/' . $_POST['module'] . '/client.php upgrade 2>&1', $output);
				break;
			default:
				/** Process module-specific requests */
				if (function_exists('moduleInitWebRequest')) {
					$output = moduleInitWebRequest();
				}
		}
	}

	echo serialize($output);
}


/**
 * Processes the update method and prepares the system
 *
 * @since 1.0
 * @package facileManager
 *
 * @param string $module_name Module currently being used
 * @param string $update_method User entered update method
 * @param array $data Data array containing client information
 * @param string $url URL to post data to
 * @return string|void
 */
function processUpdateMethod($module_name, $update_method, $data, $url) {
	global $argv;
	
	/** Update via cron or http/s? */
	$update_choices = array('c', 's', 'h');
	while (!isset($update_method) || !in_array($update_method, $update_choices)) {
		echo fM("\nWill {$data['server_name']} get updates via cron, ssh, or http(s) [c|s|h]? ");
		if (defined('METHOD')) {
			$update_method = METHOD;
			echo METHOD . "\n";
		} else {
			$update_method = trim(strtolower(fgets(STDIN)));
		}
		
		/** Must be a valid option */
		if (!in_array($update_method, $update_choices)) unset($update_method);
	}
	
	switch($update_method) {
		/** cron */
		case 'c':
			$tmpfile = sys_get_temp_dir() . '/crontab.facileManager';
			$dump = shell_exec('crontab -l 2>/dev/null | grep -v ' . escapeshellarg($module_name) . '> ' . $tmpfile . ' 2>/dev/null');
			
			/** Handle special cases */
			if (PHP_OS == 'SunOS') {
				for ($x = 0; $x < 12; $x++) {
					$minopt[] = sprintf("%02d", $x*5);
				}
				$minutes = implode(',', $minopt);
				unset($minopt);
			} else $minutes = '*/5';
			
			$cmd = "echo '" . $minutes . ' * * * * ' . findProgram('php') . ' ' . dirname(__FILE__) . '/' . $module_name . "/client.php cron' >> $tmpfile && " . findProgram('crontab') . ' ' . $tmpfile;
			$cron_update = system($cmd, $retval);
			unlink($tmpfile);
			
			if ($retval) echo fM("  --> The crontab cannot be created.\n  --> $cmd\n");
			else echo fM("  --> The crontab has been created.\n");
			
			return 'cron';
		/** ssh */
		case 's':
			$raw_data = getPostData(str_replace('genserial', 'ssh=user', $url), $data);
			$user = $data['compress'] ? @unserialize(gzuncompress($raw_data)) : @unserialize($raw_data);
			$result = ($user) ? 'ok' : 'failed';
			if ($result == 'failed') {
				echo fM("Installation failed.  No SSH user found for this account.  Please define the user in the General Settings first.\n");
				exit(1);
			}
			
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
			$raw_data = getPostData(str_replace('genserial', 'ssh=key_pub', $url), $data);
			$raw_data = $data['compress'] ? @unserialize(gzuncompress($raw_data)) : @unserialize($raw_data);
			if (strpos($raw_data, 'ssh-rsa') !== false) {
				$result = (strpos(@file_get_contents($ssh_dir . '/authorized_keys'), $raw_data) === false) ? @file_put_contents($ssh_dir . '/authorized_keys', trim($raw_data) . "\n", FILE_APPEND) : true;
				@chown($ssh_dir . '/authorized_keys', $user);
				@chmod($ssh_dir . '/authorized_keys', 0644);
				@chmod($ssh_dir, 0700);
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
			$sudoers_line = "$user\tALL=(root)\tNOPASSWD: " . findProgram('php') . ' ' . dirname(__FILE__) . '/' . $module_name . '/client.php *';
			addSudoersConfig($module_name, $sudoers_line, $user);

			return 'ssh';
		/** http(s) */
		case 'h':
			/** Detect which web server is running */
			list($web_server, $config_file, $docroot) = getWebServerInfo();

			/** Add a symlink to the docroot */
			$link_name = $docroot . DIRECTORY_SEPARATOR . 'fM';
			
			echo fM("  --> Creating $link_name link.\n");
			
			if (!is_link($link_name)) {
				symlink(dirname(__FILE__) . '/www', $link_name);
			} else echo fM("      --> $link_name already exists...skipping\n");
			
			/** Add an entry to sudoers */
			$user = getParameterValue('^User', $config_file, ' ');
			if ($user[0] == '$') {
				$user_var = preg_replace(array('/\$/', '/{/', '/}/'), '', $user);
				$user = preg_replace(array('/\$/', '/{/', '/}/'), '', getParameterValue($user_var, findFile('envvars'), '='));
			}
			echo fM('  --> Detected ' . $web_server['app'] . " runs as '$user'\n");
			$sudoers_line = "$user\tALL=(root)\tNOPASSWD: " . findProgram('php') . ' ' . dirname(__FILE__) . '/' . $module_name . '/client.php *';
			
			addSudoersConfig($module_name, $sudoers_line, $user);

			return 'http';
	}
}


/**
 * Attempts to add a system user account
 *
 * @since 1.0
 * @package facileManager
 *
 * @param array $user_info User information to add
 * @param array $passwd_users Array of existing system users
 * @return boolean|string
 */
function addUser($user_info, $passwd_users) {
	list($user_name, $user_comment) = $user_info;
	
	$retval = false;
	
	switch (PHP_OS) {
		case 'Linux':
		case 'OpenBSD':
			$cmd = findProgram('useradd') . " -m -c '$user_comment' $user_name";
			break;
		case 'FreeBSD':
			$cmd = findProgram('pw') . " useradd $user_name -m -c '$user_comment'";
			break;
		case 'Darwin':
			/** Not yet supported */
			$cmd = null;
			break;
	}

	if (!in_array($user_name, $passwd_users) && $cmd) {
		$result = system($cmd, $retval);
	}
	
	if (!$retval) {
		$ssh_dir = trim(shell_exec('grep -w ' . escapeshellarg($user_name) . " /etc/passwd | awk -F: '{print $6}'")) . '/.ssh';
		if ($ssh_dir && $ssh_dir != '/') {
			createDir($ssh_dir, $user_name);
			return $ssh_dir;
		}
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
 * @return void
 */
function addLogEntry($log_data) {
	global $module_name;
	
	$log_file = '/var/log/fm.log';
	$date = @date('M d H:i:s');
	
	$log_data = explode("\n", trim($log_data));
	foreach ($log_data as $log_line) {
		if (trim($log_line)) @file_put_contents($log_file, $date . ' ' . $module_name . ': ' . trim($log_line) . "\n", FILE_APPEND | LOCK_EX);
	}
}


/**
 * Writes files to the filesystem
 *
 * @since 1.1
 * @package facileManager
 *
 * @param array $files Files and contents to write
 * @param boolean $dryrun Whether or not files should be written
 * @param array $chown_dirs dirs that should be chowned prior to writing
 * @param string $user User dirs/files should be chowned as
 * @return boolean
 */
function installFiles($files = array(), $dryrun = false, $chown_dirs = array(), $user = 'root') {
	global $debug;
	
	/** Process the files */
	if (count($files)) {
		foreach($files as $filename => $fileinfo) {
			if (is_array($fileinfo)) {
				extract($fileinfo, EXTR_OVERWRITE);
			} else {
				$contents = $fileinfo;
			}
			$message = "Writing $filename\n";
			if ($debug) echo $message;
			if (!$dryrun) {
				addLogEntry($message);
				
				$directory = dirname($filename);
				@mkdir($directory, 0755, true);
				chown($directory, $user);
				@chgrp($directory, $user);
				file_put_contents($filename, $contents);
				
				/** chown and chmod if applicable */
				$runas = (isset($chown)) ? $chown : $user;
				chown($filename, $runas);
				@chgrp($filename, $runas);
				unset($chown);
				if (isset($mode)) {
					chmod($filename, intval($mode));
					unset($mode);
				}
			}
		}
		
		/** chown the dirs */
		if (count($chown_dirs)) {
			foreach($chown_dirs as $dir) {
				if (!is_dir($dir)) continue;
				$message = "Setting directory permissions on $dir\n";
				if ($debug) echo $message;
				if (!$dryrun) {
					addLogEntry($message);
					chown($dir, $user);
					@chgrp($dir, $user);
				}
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
	list($rows, $columns) = explode(' ', shell_exec(findProgram('stty') . ' size 2>/dev/null'));
	if (!$columns = intval($columns)) {
		$columns = 90;
	}
	return wordwrap($message, $columns, "\n");
}


/**
 * Downloads a file from the fM website
 *
 * @since 1.1
 * @package facileManager
 *
 * @param string $file File to download
 * @param array $proxy_info Proxy server information
 * @param boolean $module Whether or not this is a module download
 */
function downloadfMFile($file, $proxy_info, $module = false) {
	$base_url = 'http://www.facilemanager.com/download/';
	if ($module) $base_url .= 'module/';
	$base_url .= $file;
	
	$message = "Downloading $base_url\n";
	echo fM($message);
	addLogEntry($message);
	
	$local_file = sys_get_temp_dir() . '/' . $file;
	@unlink($local_file);
	
	$fh = fopen($local_file, 'w+');
	$ch = curl_init();
	$options = array(
		CURLOPT_URL				=> $base_url,
		CURLOPT_TIMEOUT			=> 3600,
		CURLOPT_HEADER			=> false,
		CURLOPT_FOLLOWLOCATION	=> true,
		CURLOPT_SSL_VERIFYPEER  => false,
		CURLOPT_RETURNTRANSFER  => true
	);
	@curl_setopt_array($ch, ($options + $proxy_info));
	$result = curl_exec($ch);
	@fputs($fh, $result);
	@fclose($fh);
	if ($result === false || curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
		$message = "Unable to download file.\n";
		echo fM($message . "\n" . curl_error($ch) . "\n");
		addLogEntry($message . "\n" . curl_error($ch));
		
		curl_close($ch);
		exit(1);
	}
	
	curl_close($ch);
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
	$tmp_dir = sys_get_temp_dir() . '/fM_files';
	if (!is_dir($tmp_dir)) mkdir($tmp_dir);
	
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


/**
 * Returns the value of a parameter in a file
 *
 * @since 1.3
 * @package facileManager
 *
 * @param string $param Parameter to search for
 * @param string $file File to search in
 * @param string $delimiter Delimiter to use
 * 
 * @return string
 */
function getParameterValue($param, $file, $delimiter = '=') {
	$raw_line = shell_exec('grep ' . escapeshellarg($param) . ' ' . escapeshellarg($file) . ' 2>/dev/null | grep ' . escapeshellarg($delimiter));
	if (!$raw_line) {
		return false;
	}
	
	$raw_line = explode($delimiter, $raw_line);
	if (!count($raw_line)) {
		return false;
	}
	
	return trim(str_replace(array('"', "'"), '', $raw_line[1]));
}


/**
 * Added sudoers entries
 *
 * @since 2.0
 * @package facileManager
 *
 * @param string $module_name Module to add line for
 * @param string $sudoers_line Sudo lines
 * @param string $user User with permissions
 */
function addSudoersConfig($module_name, $sudoers_line, $user) {
	global $no_sudoers;
	
	if ($no_sudoers) {
		echo fM("  --> no-sudoers parameter is specified...skipping\n");
		return;
	}
	$sudoers_file = findFile('sudoers');
	$sudoers_options[] = "Defaults:$user  !requiretty";
	$sudoers_options[] = "Defaults:$user  !env_reset";
	$sudoers_line = implode("\n", $sudoers_options) . "\n" . $sudoers_line;
	unset($sudoers_options);

	if (!$sudoers_file) {
		echo fM("  --> It does not appear sudo is installed.  Please install it and add the following to the sudoers file:\n");
		echo fM("\n      $sudoers_line\n");

		echo fM("\nInstallation aborted.\n");
		exit(1);
	} else {
		$includedir = getParameterValue('includedir', $sudoers_file, ' ');
		if ($includedir) {
			if (is_dir($includedir)) {
				$sudoers_file = $includedir . '/99_' . $module_name;
			}
		}
		$cmd = "echo '$sudoers_line' >> $sudoers_file 2>/dev/null";
		if (strpos(@file_get_contents($sudoers_file), $sudoers_line) === false) {
			$sudoers_update = system($cmd, $retval);

			if ($retval) echo fM("  --> The sudoers entry cannot be added.\n$cmd\n");
			else echo fM("  --> The sudoers entry has been added.\n");
			
			@chmod($sudoers_file, 0440);
		} else echo fM("  --> The sudoers entry already exists...skipping\n");
	}
}


/**
 * Creates directory and sets permissions
 *
 * @since 2.0
 * @package facileManager
 *
 * @param string $dir Directory to work with
 * @param string $user Username to set ownership of
 * @return boolean
 */
function createDir($dir, $user) {
	if (!is_dir($dir)) {
		@mkdir($dir);
		@chown($dir, $user);
		@@chgrp($dir, $user);
	}
	
	return true;
}


/**
 * Deletes specified file and directory contents
 *
 * @since 2.0.1
 * @package facileManager
 *
 * @param string $file Filename to delete
 * @param boolean $debug Debug mode or not
 * @param boolean $dryrun Whether it's a dry-run or not
 * @return boolean
 */
function deleteFile($file, $debug = false, $dryrun = false) {
	if (is_dir($file)) {
		if ($file == '/') return false;
		
		foreach (scandir($file) as $item) {
			if (in_array($item, array('.', '..'))) continue;
			$full_path_file = $file . DIRECTORY_SEPARATOR . $item;
			deleteFile($full_path_file, $debug, $dryrun);
		}
	} else {
		$message = "Deleting $file.\n";
		if ($debug) echo fM($message);
		if (!$dryrun) {
			addLogEntry($message);
			unlink($file);
		}
	}
	
	return true;
}


/**
 * Finds specified file
 *
 * @since 2.2
 * @package facileManager
 *
 * @param string $file Filename to find
 * @param array $addl_path Additional paths to search
 * @return string or boolean
 */
function findFile($file, $addl_path = null) {
	$path = array('/etc/httpd/conf', '/etc/httpd2/conf', '/usr/local/etc/apache', '/usr/local/etc/apache2',
				'/usr/local/etc/apache22', '/etc/apache2', '/etc', '/usr/local/etc',
				'/etc/lighttpd', '/usr/local/nginx/conf', '/etc/nginx', '/usr/local/etc/nginx');
	
	if (is_array($addl_path)) {
		$path = array_unique(array_merge($path, $addl_path));
	}

	while ($this_path = current($path)) {
		if (is_file("$this_path/$file")) {
			return "$this_path/$file";
		}
		next($path);
	}

	return false;
}


/**
 * Adds the server to the database
 *
 * @since 2.2
 * @package facileManager
 *
 * @param string $url URL to post data to
 * @param array $data Data to post
 * @return array
 */
function addServer($url, $data, $repeat = false) {
	$app = array();
	
	echo fM('  --> Adding ' . $data['server_name'] . ' to the database...');

	/** Get module-specific data */
	if (function_exists('moduleAddServer')) {
		$module_data = moduleAddServer();
		if (is_array($module_data)) {
			$data = array_merge($data, $module_data);
		}
	}
	
	/** Detect the app version of the module to manage */
	if (function_exists('detectAppVersion')) {
		$app = detectAppVersion(true);
	}
	if ($app === null) {
		echo "failed\n\n";
		echo fM("Cannot find a supported application to manage - please check the README document for supported applications.\n");
		if (function_exists('moduleInstallApp')) {
			$data = moduleInstallApp($url, $data);
		} else {
			echo fM("Aborting.\n");
			exit(1);
		}
	}
	if (!isset($data['server_type']) && is_array($app) && count($app) > 1) {
		$data['server_type'] = $app['server']['type'];
		$data['server_version'] = $app['app_version'];
	}

	if (!$repeat) {
		/** Add the server to the account */
		$raw_data = getPostData(str_replace('genserial', 'addserial', $url), $data);
		$raw_data = $data['compress'] ? @unserialize(gzuncompress($raw_data)) : @unserialize($raw_data);
		if (!is_array($raw_data)) {
			if (!$raw_data) echo "An error occurred\n";
			else echo $raw_data;
			exit(1);
		}
		
		echo fM("Success\n");
	}

	return $data;
}


/**
 * Returns whether a daemon is running or not
 *
 * @since 3.0
 * @package facileManager
 *
 * @param string $daemon Daemon name to check
 * @return boolean
 */
function isDaemonRunning($daemon) {
	return intval(shell_exec('ps -A | grep ' . escapeshellarg($daemon) . ' | grep -vc grep'));
}


/**
 * Returns whether a daemon is running or not
 *
 * @since 3.0
 * @package facileManager
 *
 * @param string $app_version Detected application version
 * @param string $serverhost FMHOST
 * @param boolean $compress Compress the request or not
 * @return string
 */
function versionCheck($app_version, $serverhost, $compress) {
	$url = str_replace(':/', '://', str_replace('//', '/', $serverhost . '/buildconf.php'));
	$data['action'] = 'version_check';
	$server_type = detectServerType();
	$data['server_type'] = $server_type['type'];
	$data['server_version'] = $app_version;
	$data['compress'] = $compress;
	
	$raw_data = getPostData($url, $data);
	$raw_data = $compress ? @unserialize(gzuncompress($raw_data)) : @unserialize($raw_data);
	
	return $raw_data;
}


/**
 * Gets the network interface names
 *
 * @since 3.2.1
 * @package facileManager
 *
 * @return array
 */
function getInterfaceNames() {
	$interfaces = array();
	
	switch(PHP_OS) {
		case 'Linux':
			if ($ifcfg = findProgram('ip')) {
				$command = $ifcfg . ' maddr | grep "^[0-9]*:" | awk \'{print $2}\'';
			} elseif ($ifcfg = findProgram('ifconfig')) {
				$command = $ifcfg . ' | grep "Link "';
			}
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
	}
	
	exec($command . ' | awk "{print \$1}" | sort | uniq', $interfaces);
	
	return $interfaces;
}


/**
 * Gets the network interface addresses
 *
 * @since 3.5.0
 * @package facileManager
 *
 * @param string Interface name
 * @return string
 */
function getInterfaceAddresses($interface = null) {
	$addresses = array();
	
	switch(PHP_OS) {
		case 'Linux':
			if ($ifcfg = findProgram('ip')) {
				$command = $ifcfg . ' addr';
			} elseif ($ifcfg = findProgram('ifconfig')) {
				$command = $ifcfg;
			}
			break;
		case 'Darwin':
		case 'FreeBSD':
		case 'OpenBSD':
		case 'NetBSD':
			$command = findProgram('ifconfig');
			break;
		case 'SunOS':
			$command = findProgram('ifconfig');
			break;
		default:
			return null;
	}
	
	exec($command . $interface . ' | grep inet | awk \'{print $2}\' | egrep -v \'127.0.0.1|^::1|^169.254.\' | sort | uniq', $addresses);
	
	return $addresses;
}


/**
 * Attempts to install packages
 *
 * @since 3.2.1
 * @package facileManager
 *
 * @param string|array $packages Package names to install
 * @return boolean
 */
function installPackage($packages) {
	$errors = false;

	if (!is_array($packages)) {
		$packages = array($packages);
	}
	
	/** Get package manager */
	$package_managers = array('apt-get', 'yum');
	foreach ($package_managers as $app) {
		if ($package_manager = findProgram($app)) {
			break;
		}
	}
	if (!$package_manager) {
		echo fM("OS is not supported for automated package installations. Aborting.\n");
		exit(1);
	}
	
	/** Install the packages */
	foreach ($packages as $app) {
		echo fM(sprintf('Installing the %s package...', $app));
		exec($package_manager . ' -y install ' . $app . ' 2>&1', $output, $rc);

		if ($rc) {
			echo fM("failed. Please install it manually.\n");
			$errors = true;
		} else echo "done\n";
	}
	
	if ($errors == true) {
		echo fM("Not all packages could be installed. Aborting.\n");
		exit(1);
	}

	return true;
}


/**
 * Returns if the OS is debian-based or not
 *
 * @since 3.3
 * @package facileManager
 *
 * @param string $os OS to check
 * @return boolean
 */
function isDebianSystem($os) {
	return in_array(strtolower($os), array('debian', 'ubuntu', 'fubuntu', 'raspbian'));
}


/**
 * Loads the API credentials for use
 *
 * @since 4.0
 * @package facileManager
 *
 * @param array $data Information to modify
 * @return array
 */
function loadAPICredentials($url, $data) {
	global $proto, $server_path;

	/** Ensure $proto = https */
	if ($proto != 'https') {
		echo fM($server_path['hostname'] . " must be configured with https.\n");
		exit(1);
	}

	/** Set the API URL */
	$url = str_replace('buildconf.php', 'api/', $url);

	if (!defined('APIKEY') || !defined('APISECRET')) {
		echo fM("API credentials are not found in config.inc.php. Please add them using the following format:\n\ndefine('APIKEY', 'MY_KEY');\ndefine('APISECRET', 'MY_KEY_SECRET');\n\n");
		exit(1);
	}

	$data['APIKEY'] = APIKEY;
	$data['APISECRET'] = APISECRET;

	return array($url, $data);
}


/**
 * Performs basic API functionality tests
 *
 * @since 4.0
 * @package facileManager
 *
 * @param string $url URL to query
 * @param array $data Information to submit
 * @return boolean
 */
function doAPITest($url, $data) {
	list($url, $data) = loadAPICredentials($url, $data);

	$data['test'] = true;

	$raw_data = getPostData($url, $data);
	$raw_data = $data['compress'] ? @unserialize(gzuncompress($raw_data)) : @unserialize($raw_data);
	echo $raw_data;

	exit;
}


/**
 * Gets web server information through auto-detect and user input
 *
 * @since 4.0
 * @package facileManager
 *
 * @param 
 * @return array
 */
function getWebServerInfo() {
	while (!is_array($web_server)) {
		echo fM("\n  --> Detecting the web server...");
		$web_server = detectWebServer();
		if (!is_array($web_server)) {
			echo "none\n\n";
			echo fM("Cannot find a supported web server - please check the README document for supported web servers.\n");
			echo fM('Which server would you like me to try installing? [(H)ttpd/(L)ighttpd/(N)ginx] ');
			$package = strtolower(trim(fgets(STDIN)));
			$package_names = array('h' => 'httpd', 'l' => 'lighttpd', 'n' => 'nginx');
			if (!$package || !in_array($package, array_merge(array('h', 'l', 'n'), $package_names))) {
				echo "No package selected. Aborting.\n";
				exit(1);
			}

			if (strlen($package) == 1) {
				$package = $package_names[$package];
			}
			
			installPackage($package);
		} else {
			echo $web_server['app'] . "\n";
		}
	}
	
	/** Get web server config file */
	echo fM("  --> Detecting the web server configuration file...");
	$config_file = findFile($web_server['file']);
	if (!$config_file) {
		echo fM("\n\nCannot find " . $web_server['file'] . '.  Please enter the full path of ' . $web_server['file'] . ' (/etc/httpd/conf/httpd.conf): ');
		$config_file = trim(strtolower(fgets(STDIN)));
		
		/** Check if the file exists */
		if (!is_file($config_file)) {
			echo fM("  --> $config_file does not exist.  Aborting.\n");
			exit(1);
		}
	} else {
		echo $config_file . "\n";
	}

	/** Get the docroot from STDIN if it's not found */
	echo fM("  --> Detecting the web server document root...");
	switch ($web_server['app']) {
		case 'httpd' || 'httpd2' || 'apache2':
			$docroot_parameter = 'DocumentRoot';
			break;
		case 'lighttpd':
			$docroot_parameter = 'server.document-root';
			break;
		case 'nginx':
			$docroot_parameter = 'document root';
			break;
	}
	if (! $docroot = getParameterValue('^' . $docroot_parameter, $config_file, '"')) {
		echo fM("\nCannot find $docroot_parameter in " . $web_server['file'] . ".  Please enter the full path of your default $docroot_parameter (/var/www/html): ");
		$docroot = rtrim(trim(strtolower(fgets(STDIN))), '/');
	} else {
		echo $docroot . "\n";
	}
		
	/** Check if the docroot exists */
	if (!is_dir($docroot)) {
		echo fM("  --> $docroot does not exist.  Creating directory.\n");
		@mkdir($docroot, 0755, true);
	}

	return array($web_server, $config_file, $docroot);
}


/**
 * Adds content to a configuration file
 *
 * @since 4.0
 * @package facileManager
 *
 * @param string $url URL to query
 * @param array $data Information to submit
 * @return void
 */
function addToConfigFile($config_file, $content, $break_on_string = null) {
	global $module_name, $data;

	$config_basename = basename($config_file);

	echo fM(sprintf('  --> Configuring %s...', $config_basename));
	/** Check if the file exists */
	if (!is_file($config_file)) {
		echo "failed\n";
		echo fM("  --> $config_file does not exist.  Aborting.\n");
		exit(1);
	}

	$current_file_contents = file_get_contents($config_file);
	if (strpos($current_file_contents, $module_name) === false) {
		$new_content = sprintf('
# This section was built using %s v%s
%s
# End %s section
',
			$module_name, $data['server_client_version'],
			$content,
			$module_name
		);
		
		$current_file_contents = explode("\n", $current_file_contents);
		foreach ($current_file_contents as $key => $line) {
			if (!$line) {
				$last_empty_key = $key;
				continue;
			}
			if ($break_on_string) {
				$pos = strpos($line, $break_on_string);
				if ($pos !== false && $pos == 0) {
					break;
				}
			}
		}
		$current_file_contents[$last_empty_key] = $new_content;
		$current_file_contents = join("\n", $current_file_contents);
		
		file_put_contents($config_file, $current_file_contents);
		echo "done\n\n";
	} else {
		echo "skipping - already configured\n\n";
	}
}


/**
 * Gets the hostname
 *
 * @since 4.0
 * @package facileManager
 *
 * @return string
 */
if (!function_exists('gethostname')) {
	function gethostname() {
		$hostname = exec(findProgram('hostname') . ' -f', $output, $rc);
		if ($rc > 0 || empty($hostname)) {
			$hostname = php_uname('n');
		}

		return $hostname;
	}
}


/**
 * Returns the line in a file that matches a string
 *
 * @since 4.1.3
 * @package facileManager
 *
 * @param string $filename File name to search
 * @param string $needle Search text
 * @return string
 * 
 * Based on https://stackoverflow.com/a/9722200
 */
function getLineWithString($filename, $needle) {
	$lines = file($filename);
	foreach ($lines as $line) {
		if (strpos($line, $needle) !== false) {
			return $line;
		}
	}
	return null;
}


/**
 * Attempts to install required packages
 *
 * @since 4.7.0
 * @package facileManager
 *
 * @param array $packages Array of packages to install
 * @param array $services Array of services to enable
 * @return void
 */
function installApp($packages, $services = array()) {
	if (!is_array($packages)) {
		$packages = array($packages);
	}
	if (!is_array($services)) {
		$services = array($services);
	}

	echo fM(sprintf('Would you like an installation attempt be made for %s? [Y/n] ', $packages[0]));
	$auto_install = strtolower(trim(fgets(STDIN)));
	if (!$auto_install) {
		$auto_install = 'y';
	}
	
	if ($auto_install != 'y') {
		echo "Aborting.\n";
		exit(1);
	}
	
	installPackage($packages);
	foreach ($services as $svc) {
		shell_exec("update-rc.d $svc enable > /dev/null 2>&1");
	}
}
