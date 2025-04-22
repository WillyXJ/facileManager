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
 | http://www.facilemanager.com/modules/fmdhcp/                            |
 +-------------------------------------------------------------------------+
*/

/**
 * Contains variables for fmDHCP
 *
 * @package fmDHCP
 *
 */

if (!@is_array($__FM_CONFIG)) $__FM_CONFIG = array();

/** Module Version */
$__FM_CONFIG['fmDHCP'] = array(
		'version'							=> '0.10.1',
		'client_version'					=> '0.10.1',
		'description'						=> __('Easily manage one or more ISC DHCP servers through a web interface. No longer edit configuration files manually.', 'fmDHCP'),
		'prefix'							=> 'dhcp_',
		'required_daemon_version'			=> '4.1',
		'required_fm_version'				=> '5.0.0',
		'min_client_auto_upgrade_version'	=> '0.1'
	);
if($_SESSION['module'] == 'fmDHCP' && !defined('NO_DASH')) define('NO_DASH', true);
$__FM_CONFIG['homepage'] = 'object-hosts.php';
	
/** Module-specific Images */
if (isset($__FM_CONFIG['module']['path'])) {
	$__FM_CONFIG['module']['icons']['action']['active']		= '<img src="' . $__FM_CONFIG['module']['path']['images'] . '/__action__.png" border="0" alt="__Action__" title="__Action__" width="12" />';
	$__FM_CONFIG['module']['icons']['action']['disabled']	= '<img src="' . $__FM_CONFIG['module']['path']['images'] . '/__action___d.png" border="0" alt="__Action__ (' . __('disabled') . ')" title="__Action__ (' . __('disabled') . ')" width="12" />';
}

$__FM_CONFIG['icons'] = @array_merge((array) $__FM_CONFIG['module']['icons'], (array) $__FM_CONFIG['icons']);

$__FM_CONFIG['networks']['avail_types']    = array('subnets' => __('Subnets'), 'shared' => __('Shared Networks'));

/** Cleanup options */
$__FM_CONFIG['module']['clean']['prefixes']	= array('fm_' . $__FM_CONFIG['fmDHCP']['prefix'] . 'config'=>'config', 'fm_' . $__FM_CONFIG['fmDHCP']['prefix'] . 'servers'=>'server'
											);

$__FM_CONFIG['clean']['prefixes']			= @array_merge((array) $__FM_CONFIG['clean']['prefixes'], (array) $__FM_CONFIG['module']['clean']['prefixes']);

$check_utils = findProgram('dhcpd') ? findProgram('dhcpd') : '/path/to/dhcpd';
$__FM_CONFIG['fmDHCP']['default']['options'] = @array(
		'enable_config_checks' => array(
				'description' => array(__('Enable dhcpd Checks'), __('Enable or disable dhcpd syntax checks.') . '</p>
								<p>' . sprintf(__('sudo must be installed on %s with the following in sudoers:'), php_uname('n')) . '</p>
								<pre>' . $__FM_CONFIG['webserver']['user_info']['name'] . ' ALL=(root) NOPASSWD: ' . $check_utils . ' -t -cf *</pre>'),
				'default_value' => 'no',
				'type' => 'checkbox')
	);

$__FM_CONFIG['dhcpd']['config_file']['default'] = '/etc/dhcp/dhcpd.conf';
