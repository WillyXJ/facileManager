<?php

/**
 * Contains variables for fmDNS
 *
 * @package fmDNS
 *
 */

/** Module Information */
$__FM_CONFIG['fmDNS']['version'] = '1.0-rc4';
$__FM_CONFIG['fmDNS']['description'] = 'Easily manage one or more ISC BIND servers through a web UI.  No more editing configuration and zone files manually.';
$__FM_CONFIG['fmDNS']['prefix'] = 'dns_';
$__FM_CONFIG['fmDNS']['required_dns_version'] = '9.3';
$__FM_CONFIG['fmDNS']['required_fm_version'] = '1.0-rc3';

/** Dashboard Menu Options */
$__FM_CONFIG['module']['menu']['Dashboard']['URL']	= '';

/** Zones Menu Options */
$__FM_CONFIG['module']['menu']['Zones']['URL']		= 'zones';
$__FM_CONFIG['module']['menu']['Zones']['Forward']	= 'zones';
$__FM_CONFIG['module']['menu']['Zones']['Reverse']	= 'zones?map=reverse';

/** Config Menu Options */
$__FM_CONFIG['module']['menu']['Config']['URL']		= 'config-servers';
$__FM_CONFIG['module']['menu']['Config']['Servers']	= 'config-servers';
$__FM_CONFIG['module']['menu']['Config']['Views']	= 'config-views';
$__FM_CONFIG['module']['menu']['Config']['ACLs']	= 'config-acls';
$__FM_CONFIG['module']['menu']['Config']['Keys']	= 'config-keys';
$__FM_CONFIG['module']['menu']['Config']['Options']	= 'config-options';
$__FM_CONFIG['module']['menu']['Config']['Logging']	= 'config-logging';

/** Settings Menu Options */
$__FM_CONFIG['module']['menu']['Settings']['URL']	= 'module-settings';

$__FM_CONFIG['menu'] = array_merge($__FM_CONFIG['module']['menu'], $__FM_CONFIG['menu']);

/** Images */
$__FM_CONFIG['module']['icons']['preview']		= '<img src="fm-modules/' . $_SESSION['module'] . '/images/preview24.png" border="0" alt="Preview Config" title="Preview Config" width="20" />';
$__FM_CONFIG['module']['icons']['export']		= '<input type="image" src="fm-modules/' . $_SESSION['module'] . '/images/export24.png" border="0" alt="Export Config" title="Export Config" width="20" />';
$__FM_CONFIG['module']['icons']['build']		= '<input type="image" src="fm-modules/' . $_SESSION['module'] . '/images/build24.png" border="0" alt="Build Config" title="Build Config" width="20" />';
$__FM_CONFIG['module']['icons']['build']		= '<input type="image" id="build" src="fm-modules/' . $_SESSION['module'] . '/images/build24.png" border="0" alt="Build Config" title="Build Config" width="20" />';
$__FM_CONFIG['module']['icons']['reload']		= '<input type="image" src="fm-modules/' . $_SESSION['module'] . '/images/reload256.png" border="0" alt="Reload Zone" title="Reload Zone" width="20" />';
$__FM_CONFIG['module']['icons']['sub_delete']	= '<img class="clone_remove" id="__ID__" src="fm-modules/' . $fm_name . '/images/error24.png" border="0" alt="Delete" title="Delete" width="12" />';

$__FM_CONFIG['icons'] = array_merge($__FM_CONFIG['module']['icons'], $__FM_CONFIG['icons']);


$__FM_CONFIG['records']['avail_types'] = (isset($map) && $map == 'forward') ? array('A', 'CNAME', 'MX', 'TXT', 'SRV', 'SOA', 'NS') : array('PTR', 'SOA', 'NS');
$__FM_CONFIG['records']['require_zone_rights'] = array('SOA', 'NS');

$__FM_CONFIG['options']['avail_types'] = array('Global', 'Logging');
$__FM_CONFIG['options']['avail_types'] = array('Global');
$__FM_CONFIG['logging']['avail_types'] = array('channel' => 'channels', 'category' => 'categories');

/** SOA Default Values */
$__FM_CONFIG['soa']['soa_master_server']	= '';
$__FM_CONFIG['soa']['soa_email_address']	= '';
$__FM_CONFIG['soa']['soa_ttl']				= '5m';
$__FM_CONFIG['soa']['soa_refresh']			= '15m';
$__FM_CONFIG['soa']['soa_retry']			= '1h';
$__FM_CONFIG['soa']['soa_expire']			= '1w';

/** Name Server Default Values */
$__FM_CONFIG['ns']['named_root_dir']		= '/var/named';
$__FM_CONFIG['ns']['named_zones_dir']		= '/etc/named/zones';
$__FM_CONFIG['ns']['named_config_file']		= '/etc/named.conf';

/** Logging Channel Options */
$__FM_CONFIG['logging']['options']['destinations']		= array('file', 'syslog', 'stderr', 'null');
$__FM_CONFIG['logging']['options']['file']				= array('versions', 'size');
$__FM_CONFIG['logging']['options']['file_versions'] 	= array_merge(array('unlimited'), range(1, 10));
$__FM_CONFIG['logging']['options']['file_sizes']	 	= array('K', 'M', 'G');
$__FM_CONFIG['logging']['options']['syslog'] 			= array('kern', 'user', 'mail', 'daemon', 'auth', 'syslog', 'lpr', 'news', 'uucp', 'cron', 'authpriv',
															'ftp', 'local0', 'local1', 'local2', 'local3', 'local4', 'local5', 'local6', 'local7');
$__FM_CONFIG['logging']['options']['severity']			= array('critical', 'error', 'warning', 'notice', 'info', 'debug 0', 'debug 1', 'debug 2', 'debug 3', 
															'debug 4', 'debug 5', 'debug 6', 'debug 7', 'debug 8', 'debug 10', 'debug 50', 'debug 90', 'dynamic');
$__FM_CONFIG['logging']['options']['print-category']	= array('', 'yes', 'no');
$__FM_CONFIG['logging']['options']['print-severity']	= array('', 'yes', 'no');
$__FM_CONFIG['logging']['options']['print-time']		= array('', 'yes', 'no');
$__FM_CONFIG['logging']['categories']					= array('default', 'general', 'client', 'config', 'database', 'dnssec', 'lame-servers', 'network', 'notify',
															'queries', 'resolver', 'security', 'update', 'update-security', 'xfer-in', 'xfer-out');
$__FM_CONFIG['logging']['channels']['reserved']			= array('null', 'default_syslog', 'default_debug', 'default_stderr');


/** Cleanup options */
$__FM_CONFIG['module']['clean']['prefixes']	= array('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls'=>'acl', 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config'=>'cfg',
											'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains'=>'domain', 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys'=>'key',
											'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records'=>'record', 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers'=>'server',
											'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'soa'=>'soa', 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views'=>'view'
											);
$__FM_CONFIG['clean']['prefixes']			= array_merge($__FM_CONFIG['clean']['prefixes'], $__FM_CONFIG['module']['clean']['prefixes']);

/** Default values */
$named_check_utils = findProgram('named-checkconf') ? findProgram('named-checkconf') . ', ' . findProgram('named-checkzone') : '/path/to/named-checkconf, /path/to/named-checkzone';
$__FM_CONFIG['fmDNS']['default']['options'] = array(
		'enable_named_checks' => array(
				'description' => array('Enabled named Checks', 'Enable or disable named-checkconf and named-checkzone utilities.</p>
								<p>sudo must be installed on ' . php_uname('n') . ' with the following in sudoers:<br />
								<pre>' . $__FM_CONFIG['webserver']['user_info']['name'] . ' ALL=(root) NOPASSWD: ' . $named_check_utils . '</pre>'),
				'default_value' => 'no',
				'type' => 'checkbox')
	);

/** Array sorts */
sort($__FM_CONFIG['logging']['categories']);

/** Module Permissions */
if (file_exists(dirname(__FILE__) . '/permissions.inc.php')) {
	include(dirname(__FILE__) . '/permissions.inc.php');
}

?>
