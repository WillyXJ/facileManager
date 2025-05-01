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
 * Contains variables for fmDNS
 *
 * @package fmDNS
 *
 */

if (!@is_array($__FM_CONFIG)) $__FM_CONFIG = array();

/** Module Information */
$__FM_CONFIG['fmDNS'] = array(
		'version'							=> '7.1.2',
		'client_version'					=> '7.1.2',
		'description'						=> __('Easily manage one or more ISC BIND servers through a web interface. No more editing configuration and zone files manually.', 'fmDNS'),
		'prefix'							=> 'dns_',
		'required_dns_version'				=> '9.3',
		'required_fm_version'				=> '5.2.0',
		'min_client_auto_upgrade_version'	=> '2.2'
	);

/** Images */
if (isset($__FM_CONFIG['module']['path'])) {
	$__FM_CONFIG['module']['icons']['export']		= '<input type="image" src="' . $__FM_CONFIG['module']['path']['images'] . '/export24.png" border="0" alt="Export Config" title="Export Config" width="20" />';
	$__FM_CONFIG['module']['icons']['reload']		= sprintf('<i class="fa fa-refresh preview" alt="%1$s" title="%1$s" aria-hidden="true"></i>', _('Reload Zone'));
}
if (isset($fm_name)) {
	$__FM_CONFIG['module']['icons']['sub_delete']	= sprintf('<a href="JavaScript:void(0);" class="tooltip-bottom mini-icon" data-tooltip="%s"><i id="__ID__" class="fa fa-trash delete subelement_remove" aria-hidden="true"></i></a>', _('Delete'));
}

$__FM_CONFIG['icons'] = @array_merge((array) $__FM_CONFIG['module']['icons'], (array) $__FM_CONFIG['icons']);

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
											array('RSA/SHA-512', 10),
											array('GOST R 34.10-2001', 12)
											);
$__FM_CONFIG['records']['sshfp_algorithms'] = array(
											array('RSA', 1),
											array('DSA', 2),
											array('ECDSA', 3),
											array('ED25519', 4)
											);
$__FM_CONFIG['records']['naptr_flags']	= array('U', 'S', 'A', 'P', '');
$__FM_CONFIG['records']['flags']		= array('0', '256', '257');
$__FM_CONFIG['records']['digest_types'] = array(
											array('SHA-1', 1),
											array('SHA-256', 2)
											);
$__FM_CONFIG['records']['tlsa_flags'] 	= array('0', '1', '2', '3');
$__FM_CONFIG['records']['caa_flags']	= array('0', '128');
$__FM_CONFIG['records']['caa_tags']		= array('issue', 'issuewild', 'iodef');

$__FM_CONFIG['servers']['avail_types']    = array('servers' => _('Servers'), 'groups' => _('Groups'));
$__FM_CONFIG['options']['avail_types']    = array('global' => __('Global'), 'ratelimit' => __('Rate Limit'), 'rrset' => __('RRSet'), 'rpz' => __('Response Policy'));
$__FM_CONFIG['logging']['avail_types']    = array('channel' => __('Channels'), 'category' => __('Categories'));
$__FM_CONFIG['operations']['avail_types'] = array('controls' => __('Controls'), 'statistics' => __('Statistics'));
$__FM_CONFIG['keys']['avail_types']       = array('tsig' => 'TSIG', 'dnssec' => 'DNSSEC');
$__FM_CONFIG['keys']['avail_sizes']       = array(4096, 2048, 1024, 768, 512);
$__FM_CONFIG['dnssec']['avail_types']     = array('dnssec-policy' => __('DNSSEC Policies'), 'trust-anchors' => __('Trust Anchors'));
$__FM_CONFIG['dnssec']['avail_types']     = array('dnssec-policy' => __('DNSSEC Policies'));

/** SOA Default Values */
$__FM_CONFIG['soa']['soa_master_server']	= '';
$__FM_CONFIG['soa']['soa_email_address']	= '';
$__FM_CONFIG['soa']['soa_ttl']				= '1d';
$__FM_CONFIG['soa']['soa_refresh']			= '2h';
$__FM_CONFIG['soa']['soa_retry']			= '1h';
$__FM_CONFIG['soa']['soa_expire']			= '2w';

/** Name Server Default Values */
$__FM_CONFIG['ns']['named_root_dir']		= '/var/named';
$__FM_CONFIG['ns']['named_chroot_dir']		= '/var/named/chroot';
$__FM_CONFIG['ns']['named_zones_dir']		= '/etc/named/zones';
$__FM_CONFIG['ns']['named_slave_zones_dir']	= '/var/cache/bind';
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
$__FM_CONFIG['logging']['categories']					= array('client', 'config', 'database', 'default', 'delegation-only', 'dispatch', 'dnssec', 'general', 
															'lame-servers', 'network', 'notify', 'queries', 'resolver', 'rpz', 'rate-limit', 'security', 'unmatched',
															'update', 'update-security', 'xfer-in', 'xfer-out', 'query-errors', 'cname', 'zoneload', 'edns-disabled',
															'dnstap', 'trust-anchor-telemetry', 'spill', 'nsid', 'rpz-passthru', 'server-stale', 'sslkeylog');
$__FM_CONFIG['logging']['channels']['reserved']			= array('null', 'default_syslog', 'default_debug', 'default_stderr');


/** Cleanup options */
$__FM_CONFIG['module']['clean']['prefixes']	= array('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls'=>'acl', 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config'=>'cfg',
											'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains'=>'domain', 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'files'=>'file',
											'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys'=>'key', 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records'=>'record', 
											'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers'=>'server', 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'soa'=>'soa', 
											'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views'=>'view', 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records_skipped'=>'record'
											);
$__FM_CONFIG['clean']['prefixes']			= @array_merge((array) $__FM_CONFIG['clean']['prefixes'], (array) $__FM_CONFIG['module']['clean']['prefixes']);

/** Default values */
$named_check_utils = findProgram('named-checkconf') ? findProgram('named-checkconf') . ', ' . findProgram('named-checkzone') : '/path/to/named-checkconf, /path/to/named-checkzone';
$__FM_CONFIG['fmDNS']['default']['options'] = @array(
		'enable_config_checks' => array(
				'description' => array(__('Enable named Checks'), __('Enable or disable named-checkconf and named-checkzone utilities.') . '</p>
								<p>' . sprintf(__('sudo must be installed on %s with the following in sudoers:'), php_uname('n')) . '</p>
								<pre>' . $__FM_CONFIG['webserver']['user_info']['name'] . ' ALL=(root) NOPASSWD: ' . $named_check_utils . '</pre>'),
				'default_value' => 'no',
				'type' => 'checkbox'),
		'purge_config_files' => array(
				'description' => array(__('Purge Configuration Files'), __('When enabled, configuration files will be deleted on the DNS servers before building the server config. This can be handy if you want to remove unused files.')),
				'default_value' => 'no',
				'type' => 'checkbox'),
		'use_named_keys_with_rndc' => array(
				'description' => array(__('Use Defined Keys with rndc'), __('Use keys defined in named.conf.keys with rndc actions (each server can override this).')),
				'default_value' => 'no',
				'type' => 'checkbox'),
		'zone_file_format' => array(
				'description' => array(__('Zone Filename Format'), __('The filename structure for the zone files. {ZONENAME} will be replaced with the name of the zone.')),
				'default_value' => 'db.{ZONENAME}.hosts',
				'type' => 'text'),
		'auto_create_ptr_zones' => array(
				'description' => array(__('Create Reverse Zones Automatically'), __('While creating A records and choosing to create the associated PTR record, reverse zones can be automatically created if they are missing.')),
				'default_value' => 'no',
				'type' => 'checkbox'),
		'clones_use_dnames' => array(
				'description' => array(__('Use DNAME Resource Records for Clones'), __('When creating cloned zones, use the DNAME resource record rather than a full clone (when available).')),
				'default_value' => 'yes',
				'type' => 'checkbox'),
		'zone_sort_hierarchical' => array(
				'description' => array(__('Sort Zone Names Hierarchically'), __('Sort zone names with a hierarchy to group sub-zones together. For example:') . "
								<pre>domain.com\nbar.domain.com\nfoo.bar.domain.com</pre>"),
				'default_value' => 'no',
				'type' => 'checkbox'),
		'dnssec_expiry' => array(
				'description' => array(__('Default DNSSEC Signature Expiry'), __('Define the number of days the DNSSEC signatures should be valid for (each zone can override this).')),
				'default_value' => 30,
				'type' => 'number',
				'size' => 10,
				'addl' => 'onkeydown="return validateNumber(event)"'),
		'url_rr_web_servers' => array(
				'description' => array(__('Define URL RR Web Servers'), __('This feature will enable the fmDNS URL resource record which allows DNS records to redirect the user to a URL. For example:') .
								"<pre>foo.bar.com  IN  URL  http://www.foobar.com/some/landing/page.html</pre>" . 
								__('List the (public) IP addresses or hostnames the URL RRs should resolve to in order to handle the web redirects (semi-colon or comma delimited).')),
				'default_value' => null,
				'type' => 'text',
				'function' => 'resetURLServerConfigStatus')
			);

/** Array sorts */
sort($__FM_CONFIG['logging']['categories']);
