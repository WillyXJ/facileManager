<?php

/**
 * facileManager Client Utility Common Functions
 *
 * @package facileManager
 * @subpackage Client
 *
 */


$compress = true;

/** Check for options */
if (in_array('-h', $argv) || in_array('help', $argv)) printHelp();
$debug = (in_array('-d', $argv) || in_array('debug', $argv)) ? true : false;
$proto = (in_array('-s', $argv) || in_array('no-ssl', $argv)) ? 'http' : 'https';

if ($debug) error_reporting(E_ALL ^ E_NOTICE);

/** Check if PHP version requirement is met */
if (version_compare(PHP_VERSION, '5.0.0', '<')) {
	echo 'Your server is running PHP version ' . PHP_VERSION . " but PHP >= 5.0.0 is required.\n";
	exit(1);
}

/** Check if zlib exists */
if (!function_exists('gzuncompress')) {
	if ($debug) echo "PHP 'zlib' module is missing; therefore, I'm not using compression and will attempt to enforce ssl.\n";
	$compress = false;
	$proto = 'https';
}

/** Check if openssl exists */
if ($proto == 'https') {
	$proto = function_exists('openssl_open') ? 'https' : 'http';
	if (($debug) && $proto == 'http') echo "PHP 'openssl' module is missing; therefore, I'm not using ssl.\n";
}

/** Check if curl exists */
if (!function_exists('curl_init')) {
	echo "PHP 'curl' module is missing; therefore, I'm not able to continue.\n";
	exit(1);
}

$config_file = dirname(__FILE__) . '/config.inc.php';

/** Include module functions */
$fm_client_functions = dirname(__FILE__) . '/'. $module_name . '/functions.php';
if (file_exists($fm_client_functions)) {
	require_once($fm_client_functions);
}

/** Detect OS */
$data['server_os'] = detectOSDistro();

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
				echo "$module_name is already installed.\n";
				exit;
			}
		}
	}
	installFM($proto, $compress);
}

/** Dependency on $config_file */
if (!file_exists($config_file)) {
	echo "The $module_name client is not installed. Please install it with the following:\nphp {$argv[0]} install\n";
	exit(1);
} else require($config_file);

/** Check if the port is alive first */
$port = ($proto == 'https') ? 443 : 80;
$server_path = getServerPath(FMHOST);
if (!socketTest($server_path['hostname'], $port, 20)) {
	if ($proto == 'https') {
		if (socketTest($server_path['hostname'], 80, 20)) {
			$proto = 'http';
		} else {
			echo $server_path['hostname'] . " is currently not available via tcp/$port.  Aborting.\n";
			exit(1);
		}
	} else {
		echo $server_path['hostname'] . " is currently not available via tcp/$port.  Aborting.\n";
		exit(1);
	}
}



/** ============================================================================================= */

function printHelp () {
	global $argv, $module_name;
	
	echo <<<HELP
{$argv[0]} [options]
  -h|help        Display this help
  -d|debug       Enter debug mode for more output
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

	echo "Welcome to the $module_name installer.\n\n";
	
	echo "Please answer the following questions and the necessary configurations will be\nperformed for you.\n\n";
	
	/** facileManager host **/
	echo "Please enter the location of the facileManager interface:\n";
	echo "    Examples include:\n";
	echo "\tfm.mydomain.com\n";
	echo "\tfm.mydomain.com:8443\n";
	echo "\tmydomain.com/fm\n";
	echo "\thttp://fm.mydomain.com/facileManager\n\n";
	echo 'Please enter the location of the facileManager interface: ';
	$serverhost = trim(fgets(STDIN));
	
	/** Get server name from input */
	$server_location = getServerPath($serverhost);
	extract($server_location);
	
	$data['config'] = array();

	/** Run tests */
	echo "  --> Testing $hostname via https...";
	if (socketTest($hostname, 443)) {
		echo "ok\n";
		$proto = 'https';
	} else {
		echo "failed\n";
		echo "  --> Testing $hostname via http...";
		if (socketTest($hostname, 80)) {
			echo "ok\n";
			$proto = 'http';
		} else {
			echo "failed\n\n";
			echo "Cannot access $hostname with http or https.  Please correct this before proceeding.\n";
			exit(1);
		}
	}
	
	$data['config'][] = array('FMHOST', 'facileManager server', $hostname . '/' . $path);
	
	/** Account key **/
	$key = 'default';
	while (!isset($key)) {
		echo 'Please enter your account key: ';
		$key = trim(fgets(STDIN));
	}
	
	$data['compress'] = $compress;
	$data['AUTHKEY'] = $key;
	$data['config'][] = array('AUTHKEY', 'Account number', $key);
	
	/** Test the authentication */
	echo "  --> Checking account details...";
	$url = "${proto}://${hostname}/${path}admin-accounts?verify";
	$raw_data = getPostData($url, $data);
	$raw_data = $data['compress'] ? @unserialize(gzuncompress($raw_data)) : @unserialize($raw_data);
	echo $raw_data . "\n\n";
	if ($raw_data != 'Success') {
		echo "Installation failed.  Please check your account key.\n";
		exit(1);
	}

	/** Server serial number **/
	$data['server_name'] = php_uname('n');
	$data['server_os'] = detectOSDistro();
	echo 'Please enter the serial number for ' . $data['server_name'] . ' (or leave blank to create new): ';
	$serialno = trim(fgets(STDIN));
	
	/** Process new server */
	if (empty($serialno)) {
		/** Generate new serial number */
		echo '  --> Generating new serial number: ';
		$url = "${proto}://${hostname}/${path}admin-servers?genserial";
		$serialno = $data['server_serial_no'] = generateSerialNo($url, $data);
		echo $serialno . "\n";

		/** Add new server */
		echo '  --> Adding ' . $data['server_name'] . ' to the database...';
		$add_server_result = moduleAddServer($url, $data);
		extract($add_server_result, EXTR_OVERWRITE);
		echo $add_result;
	}

	$data['SERIALNO'] = $serialno;
	$data['config'][] = array('SERIALNO', 'Server unique serial number', $serialno);

	$data = installFMModule($module_name, $proto, $compress, $data, $server_location, $url);

	/** Save the file */
	saveFMConfigFile($data);
	
	/** Complete installation */
	$url = "${proto}://${hostname}/${path}admin-servers?install";
	$raw_data = getPostData($url, $data);
	
	echo "Installation is complete. Please login to the UI to ensure the server settings\nare correct.\n";
	
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
	global $module_name;
	
	$data['module_name'] = $module_name;
	$data['module_type'] = 'CLIENT';
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, true);
//	curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	$result = curl_exec($ch);
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
		echo "\nInstallation failed.  Could not write $config_file.\n";
		exit(1);
	} else {
		echo "\nConfiguration file has been saved.\n\n";
	}
}


/**
 * Generates a serial number
 *
 * @since 1.0
 * @package facileManager
 */
function generateSerialNo($url, $data) {
	/** Generate a new serial number */
	$raw_data = getPostData($url, $data);
	$raw_data = $data['compress'] ? @unserialize(gzuncompress($raw_data)) : @unserialize($raw_data);
	if (!is_array($raw_data)) {
		if (empty($raw_data)) {
			echo "Failed to retrieve $url.\n";
		}
		echo $raw_data;
		exit(1);
	}
	
	return $raw_data['server_serial_no'];
}


/**
 * Detects OS and distribution
 *
 * @since 1.0
 * @package facileManager
 */
function detectOSDistro() {
	if (PHP_OS == 'Linux') {
		/** declare supported Linux distros */
		$distros = array(
			'Arch'       => 'arch-release',
			'Fubuntu'    => '/etc/fuduntu-release',
			'Ubuntu'     => 'lsb-release',
			'Fedora'     => 'fedora-release',
			'CentOS'     => 'centos-release',
			'ClearOS'    => 'clearos-release',
			'Oracle'     => 'oracle-release',
			'ALT'        => '/etc/altlinux-release',
			'Sabayon'    => '/etc/sabayon-release',
			'Redhat'     => 'redhat-release',
			'Debian'     => 'debian_version;debian_release',
			'Slackware'  => 'slackware-version;/etc/slackware-release',
			'SUSE'       => '/etc/SuSE-release;/etc/UnitedLinux-release',
			'Gentoo'     => '/etc/gentoo-release',
			'Mandrake'   => '/etc/mandrake-release;/etc/mandrakelinux-release;/etc/mandiva-release',
			'Vector'     => '/etc/vector-version',
			'Porteus'    => '/etc/porteus-version',
			'SMS'        => '/etc/sms-version',
			'PCLinuxOS'  => '/etc/pclinuxos-version',
			'Turbolinux' => '/etc/turbolinux-version'
			);
		
		foreach ($distros as $distro => $release_files) {
			$release_file_array = explode(';', $release_files);
			foreach ($release_file_array as $release_file) {
				if (file_exists('/etc/' . $release_file)) return $distro;
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


?>