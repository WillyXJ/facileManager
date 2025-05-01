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

if (!@is_array($__FM_CONFIG)) $__FM_CONFIG = array();

/** Module Version */
$__FM_CONFIG['fmFirewall'] = array(
		'version'							=> '3.2.4',
		'client_version'					=> '3.2.3',
		'description'						=> __('Managing software firewalls should not be difficult. Manage one or more software firewall servers (iptables, ipfw, ipf, pf) through a web interface rather than configuration files individually.', 'fmFirewall'),
		'prefix'							=> 'fw_',
		'required_fm_version'				=> '5.0.0',
		'min_client_auto_upgrade_version'	=> '1.3'
	);

if($_SESSION['module'] == 'fmFirewall' && !defined('NO_DASH')) define('NO_DASH', true);
$__FM_CONFIG['homepage'] = 'config-servers.php';

/** Images */
if (isset($__FM_CONFIG['module']['path'])) {
	$__FM_CONFIG['module']['icons']['action']['pass']		= sprintf('<span class="tooltip-bottom mini-icon" data-tooltip="%s"><i class="fa fa-arrow-up __action__" aria-hidden="true"></i></span>', __('Pass the packet'));
	$__FM_CONFIG['module']['icons']['action']['block']		= sprintf('<span class="tooltip-bottom mini-icon" data-tooltip="%s"><i class="fa fa-times __action__" aria-hidden="true"></i></span>', __('Block the packet'));
	$__FM_CONFIG['module']['icons']['action']['reject']		= sprintf('<span class="tooltip-bottom mini-icon" data-tooltip="%s"><i class="fa fa-times __action__" aria-hidden="true"></i></span>', __('Reject the packet'));
	$__FM_CONFIG['module']['icons']['action']['log']		= sprintf('<span class="tooltip-bottom mini-icon" data-tooltip="%s"><i class="fa fa-file-text-o __action__" aria-hidden="true"></i></span>', __('Log the packet'));
	$__FM_CONFIG['module']['icons']['negated']				= sprintf('<span class="tooltip-bottom" data-tooltip="%s"><i class="fa fa-exclamation-circle block" aria-hidden="true"></i></span>', __('Negated'));
	$__FM_CONFIG['module']['icons']['search']				= sprintf('<a href="#" class="global-search"><i class="fa fa-search preview" alt="%1$s" title="%1$s" aria-hidden="true"></i></a>', __('Global Search'));
}

$__FM_CONFIG['icons'] = @array_merge((array) $__FM_CONFIG['module']['icons'], (array) $__FM_CONFIG['icons']);

/** TCP Flags */
$__FM_CONFIG['tcp_flags']				= array('URG' => 1, 'ACK' => 2, 'PSH' => 4,
												'RST' => 8, 'SYN' => 16, 'FIN' => 32);
/** Weekdays */
$__FM_CONFIG['weekdays']				= array(__('Mon') => 1, __('Tue') => 2, __('Wed') => 4,
												__('Thu') => 8, __('Fri') => 16, __('Sat') => 32, __('Sun') => 64);
/** Policy options */
$__FM_CONFIG['fw']['policy_options']	= array(
											'log' => array(
												'firewalls' => array('iptables', 'ipfw', 'ipfilter', 'pf'),
												'bit' => 1,
												'desc' => __('Log packets processed by this rule')
											),
											'established' => array(
												'firewalls' => array('ipfw', 'ipfilter'),
												'bit' => 2,
												'desc' => __('Established connection packets')
											),
											'frag' => array(
												'firewalls' => array('iptables', 'ipfw', 'ipfilter'),
												'bit' => 4,
												'desc' => __('Matches packets that are fragments and not the first fragment of an IP datagram')
											),
											'quick' => array(
												'firewalls' => array('pf'),
												'bit' => 8,
												'desc' => __('Cancel further rule processing with the "quick" keyword')
											)
										);

/** Policy states */
$__FM_CONFIG['fw']['policy_states']	= array(
		'pf' => array('no state', 'keep state', 'modulate state', 'synproxy state'),
		'ipfw' => array('keep-state'),
		'iptables' => array('INVALID', 'ESTABLISHED', 'NEW', 'RELATED', 'UNTRACKED'),
		'ipfilter' => array('keep state')
	);

/** Default values */
$__FM_CONFIG['fw']['config_file'] 		= array(
											'pf' => '/etc/pf.conf',
											'ipfw' => '/etc/ipfw.rules',
											'iptables' => array(
												'Arch'      => '/etc/iptables/iptables.rules',
												'Fedora'    => '/etc/sysconfig/iptables',
												'Redhat'    => '/etc/sysconfig/iptables',
												'CentOS'    => '/etc/sysconfig/iptables',
												'ClearOS'   => '/etc/sysconfig/iptables',
												'Oracle'    => '/etc/sysconfig/iptables',
												'Gentoo'    => '/var/lib/iptables/rules-save',
												'Slackware' => '/etc/rc.d/rc.firewall'
											),
											'ipfilter' => array(
												'FreeBSD'   => '/etc/ipf.rules',
												'SunOS'     => '/etc/ipf/ipf.conf'
											),
											'default' => '/usr/local/facileManager/fmFirewall/fw.rules'
										);

/** Firewall notes */
$__FM_CONFIG['fw']['notes'] 			= array(
												'iptables' => __('Rules in this policy are evaluated on a first-match basis and everything that is not explicitly blocked will be passed by default.'),
												'pf' => __('Rules in this policy are evaluated on a last-match basis and everything that is not explicitly blocked will be allowed by default.'),
												'ipfw' => __('Rules in this policy are evaluated on a first-match basis and everything that is not explicitly passed will be blocked by default.'),
												'ipfilter' => __('Rules in this policy are evaluated on a first-match basis and everything that is not explicitly blocked will be passed by default.')
											);

$__FM_CONFIG['policy']['avail_types'] = array('filter' => 'Filter', 'nat' => 'NAT');

/** Cleanup options */
$__FM_CONFIG['module']['clean']['prefixes']	= array('fm_' . $__FM_CONFIG['fmFirewall']['prefix'] . 'groups'=>'group', 'fm_' . $__FM_CONFIG['fmFirewall']['prefix'] . 'objects'=>'object',
											'fm_' . $__FM_CONFIG['fmFirewall']['prefix'] . 'policies'=>'policy', 'fm_' . $__FM_CONFIG['fmFirewall']['prefix'] . 'servers'=>'server',
											'fm_' . $__FM_CONFIG['fmFirewall']['prefix'] . 'services'=>'service', 'fm_' . $__FM_CONFIG['fmFirewall']['prefix'] . 'time'=>'time'
											);
$__FM_CONFIG['clean']['prefixes']			= @array_merge((array) $__FM_CONFIG['clean']['prefixes'], (array) $__FM_CONFIG['module']['clean']['prefixes']);
