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
 * Contains variables for fmDNS
 *
 * @package fmDNS
 *
 */

if (!is_array($__FM_CONFIG)) $__FM_CONFIG = array();

/** Module Information */
$__FM_CONFIG['fmDNS']['version']				= '1.2.2';
$__FM_CONFIG['fmDNS']['client_version']			= '1.2.2';
$__FM_CONFIG['fmDNS']['description']			= 'Easily manage one or more ISC BIND servers through a web interface.  No more editing configuration
													and zone files manually.';
$__FM_CONFIG['fmDNS']['prefix']					= 'dns_';
$__FM_CONFIG['fmDNS']['required_dns_version']	= '9.3';
$__FM_CONFIG['fmDNS']['required_fm_version']	= '1.2';

/** Dashboard Menu Options */
$__FM_CONFIG['module']['menu']['Dashboard']['URL']	= '';

/** Zones Menu Options */
$__FM_CONFIG['module']['menu']['Zones']['URL']		= 'zones.php';
$__FM_CONFIG['module']['menu']['Zones']['Forward']	= 'zones.php';
$__FM_CONFIG['module']['menu']['Zones']['Reverse']	= 'zones.php?map=reverse';

/** Config Menu Options */
$__FM_CONFIG['module']['menu']['Config']['URL']		= 'config-servers.php';
$__FM_CONFIG['module']['menu']['Config']['Servers']	= 'config-servers.php';
$__FM_CONFIG['module']['menu']['Config']['Views']	= 'config-views.php';
$__FM_CONFIG['module']['menu']['Config']['ACLs']	= 'config-acls.php';
$__FM_CONFIG['module']['menu']['Config']['Keys']	= 'config-keys.php';
$__FM_CONFIG['module']['menu']['Config']['Options']	= 'config-options.php';
$__FM_CONFIG['module']['menu']['Config']['Logging']	= 'config-logging.php';

$__FM_CONFIG['menu'] = array_merge($__FM_CONFIG['module']['menu'], $__FM_CONFIG['menu']);

/** Settings Menu Options */
$__FM_CONFIG['menu']['Settings']['fmDNS']	= 'module-settings.php';

/** Images */
$__FM_CONFIG['module']['icons']['export']		= '<input type="image" src="fm-modules/' . $_SESSION['module'] . '/images/export24.png" border="0" alt="Export Config" title="Export Config" width="20" />';
$__FM_CONFIG['module']['icons']['reload']		= '<input type="image" src="fm-modules/' . $_SESSION['module'] . '/images/reload256.png" border="0" alt="Reload Zone" title="Reload Zone" width="20" />';
if (isset($fm_name)) {
	$__FM_CONFIG['module']['icons']['sub_delete']	= '<img class="clone_remove" id="__ID__" src="fm-modules/' . $fm_name . '/images/error24.png" border="0" alt="Delete" title="Delete" width="12" />';
}

$__FM_CONFIG['icons'] = array_merge($__FM_CONFIG['module']['icons'], $__FM_CONFIG['icons']);

$__FM_CONFIG['records']['common_types'] = (isset($map) && $map == 'forward') ? array('A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SOA') : array('PTR', 'NS', 'SOA');
$__FM_CONFIG['records']['require_zone_rights'] = array('SOA', 'NS');
$__FM_CONFIG['records']['cert_types'] = array(
											array('X.509', 1),
											array('SKPI', 2),
											array('OpenPGP', 3),
											array('IPKIX', 4),
											array('ISPKI', 5),
											array('IPGP', 6),
											array('ACPKIX', 7),
											array('IACPKIX', 8)
											);
$__FM_CONFIG['records']['cert_algorithms'] = array(
											array('Diffie-Hellman', 2),
											array('DSA/SHA-1', 3),
											array('RSA/SHA-1', 5),
											array('DSA-NSEC3-SHA1', 6),
											array('RSASHA1-NSEC3-SHA1', 7),
											array('RSA/SHA-256', 8),
											array('RSA/SHA-512', 10)
											);

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
$__FM_CONFIG['logging']['options']['file_versions'] 	= array_merge(array('', 'unlimited'), range(1, 10));
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
											'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'soa'=>'soa', 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views'=>'view',
											'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records_skipped'=>'record'
											);
$__FM_CONFIG['clean']['prefixes']			= array_merge($__FM_CONFIG['clean']['prefixes'], $__FM_CONFIG['module']['clean']['prefixes']);

/** Default values */
$named_check_utils = findProgram('named-checkconf') ? findProgram('named-checkconf') . ', ' . findProgram('named-checkzone') : '/path/to/named-checkconf, /path/to/named-checkzone';
$__FM_CONFIG['fmDNS']['default']['options'] = array(
		'enable_named_checks' => array(
				'description' => array('Enable named Checks', 'Enable or disable named-checkconf and named-checkzone utilities.</p>
								<p>sudo must be installed on ' . php_uname('n') . ' with the following in sudoers:</p>
								<pre>' . $__FM_CONFIG['webserver']['user_info']['name'] . ' ALL=(root) NOPASSWD: ' . $named_check_utils . '</pre>'),
				'default_value' => 'no',
				'type' => 'checkbox'),
		'purge_config_files' => array(
				'description' => array('Purge Configuration Files', 'When enabled, configuration files will be deleted on the DNS
								servers before building the server config. This can be handy if you want to remove unused files.'),
				'default_value' => 'no',
				'type' => 'checkbox')
	);

/** Array sorts */
sort($__FM_CONFIG['logging']['categories']);

?>
