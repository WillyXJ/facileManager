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
 | fmFirewall: Easily manage one or more software firewalls                |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmfirewall/                        |
 +-------------------------------------------------------------------------+
*/

/**
 * Contains variables for fmFirewall
 *
 * @package fmFirewall
 *
 */

/** Module Version */
$__FM_CONFIG['fmFirewall']['version']				= '1.0-a1';
$__FM_CONFIG['fmFirewall']['description']			= 'Manage on or more software firewall servers through a web UI rather than configuration files individually.';
$__FM_CONFIG['fmFirewall']['prefix']				= 'fw_';
$__FM_CONFIG['fmFirewall']['required_fm_version']	= '1.0-rc3';

/** Dashboard Menu Options */
$__FM_CONFIG['module']['menu']['Dashboard']['URL']			= '';

/** Firewalls Menu Options */
$__FM_CONFIG['module']['menu']['Firewalls']['URL']			= 'config-servers';

/** Objects Menu Options */
$__FM_CONFIG['module']['menu']['Objects']['URL']			= 'object-groups';
$__FM_CONFIG['module']['menu']['Objects']['Groups']			= 'object-groups';
$__FM_CONFIG['module']['menu']['Objects']['Addresses']		= 'objects?type=address';
$__FM_CONFIG['module']['menu']['Objects']['Hosts']			= 'objects?type=host';
$__FM_CONFIG['module']['menu']['Objects']['Networks']		= 'objects?type=network';

/** Firewalls Menu Options */
$__FM_CONFIG['module']['menu']['Services']['URL']			= 'service-groups';
$__FM_CONFIG['module']['menu']['Services']['Groups']		= 'service-groups';
$__FM_CONFIG['module']['menu']['Services']['ICMP']			= 'services?type=icmp';
$__FM_CONFIG['module']['menu']['Services']['TCP']			= 'services?type=tcp';
$__FM_CONFIG['module']['menu']['Services']['UDP']			= 'services?type=udp';

/** Time Menu Options */
$__FM_CONFIG['module']['menu']['Time']['URL']				= 'config-time';

/** Settings Menu Options */
//$__FM_CONFIG['module']['menu']['Settings']['URL']			= 'module-settings';

$__FM_CONFIG['menu'] = array_merge($__FM_CONFIG['module']['menu'], $__FM_CONFIG['menu']);

/** Images */
$__FM_CONFIG['module']['icons']['action']['active']		= '<img src="fm-modules/' . $_SESSION['module'] . '/images/__action__.png" border="0" alt="__Action__" title="__Action__" width="12" />';
$__FM_CONFIG['module']['icons']['action']['disabled']	= '<img src="fm-modules/' . $_SESSION['module'] . '/images/__action___d.png" border="0" alt="__Action__ (disabled)" title="__Action__ (disabled)" width="12" />';

$__FM_CONFIG['icons'] = array_merge($__FM_CONFIG['module']['icons'], $__FM_CONFIG['icons']);

/** TCP Flags */
$__FM_CONFIG['tcp_flags']				= array('URG' => 1, 'ACK' => 2, 'PSH' => 4,
												'RST' => 8, 'SYN' => 16, 'FIN' => 32);
/** Weekdays */
$__FM_CONFIG['weekdays']				= array('Mon' => 1, 'Tue' => 2, 'Wed' => 4,
												'Thu' => 8, 'Fri' => 16, 'Sat' => 32, 'Sun' => 64);
/** Policy options */
$__FM_CONFIG['fw']['policy_options']	= array('log' => 1);

/** Default values */
$__FM_CONFIG['fw']['config_file'] 		= array('iptables' => '/etc/sysconfig/iptables',
												'pf' => '/etc/pf.conf',
												'ipfw' => '/etc/ipfw.rules',
												'ipfilter' => '/etc/ipf.rules'
											);
$__FM_CONFIG['policy']['avail_types'] = array('rules' => 'Rules', 'nat' => 'NAT');

/** Module Permissions */
if (file_exists(dirname(__FILE__) . '/permissions.inc.php')) {
	include(dirname(__FILE__) . '/permissions.inc.php');
}

?>