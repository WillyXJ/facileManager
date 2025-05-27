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
 | fmWifi: Easily manage one or more access points                         |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmwifi/                            |
 +-------------------------------------------------------------------------+
*/

/**
 * Contains variables for fmWifi
 *
 * @package fmWifi
 *
 */

if (!@is_array($__FM_CONFIG)) $__FM_CONFIG = array();

/** Module Version */
$__FM_CONFIG['fmWifi'] = array(
		'version'							=> '0.7.1',
		'client_version'					=> '0.7.0',
		'description'						=> __('Manage wifi access points with hostapd.', 'fmWifi'),
		'prefix'							=> 'wifi_',
		'required_fm_version'				=> '5.0.0',
		'required_daemon_version'			=> '2.4',
		'min_client_auto_upgrade_version'	=> '0.1'
	);

/** Module-specific Images */
if (isset($__FM_CONFIG['module']['path'])) {
	$__FM_CONFIG['module']['icons']['action']['active']		= '<img src="' . $__FM_CONFIG['module']['path']['images'] . '/__action__.png" border="0" alt="__Action__" title="__Action__" width="12" />';
	$__FM_CONFIG['module']['icons']['action']['disabled']	= '<img src="' . $__FM_CONFIG['module']['path']['images'] . '/__action___d.png" border="0" alt="__Action__ (' . __('disabled') . ')" title="__Action__ (' . __('disabled') . ')" width="12" />';
}
$__FM_CONFIG['module']['icons']['fail']			= sprintf('<i class="fa fa-times-circle fa-lg fail" alt="%1$s" title="%1$s" aria-hidden="true"></i>', _('Failed'));
$__FM_CONFIG['module']['icons']['ok']			= sprintf('<i class="fa fa-check-circle fa-lg ok" alt="%1$s" title="%1$s" aria-hidden="true"></i>', _('OK'));
$__FM_CONFIG['module']['icons']['notice']		= sprintf('<i class="fa fa-question-circle fa-lg notice" alt="%1$s" title="%1$s" aria-hidden="true"></i>', _('OK'));
$__FM_CONFIG['module']['icons']['block']		= sprintf('<i class="fa fa-ban fa-lg" alt="%1$s" title="%1$s" aria-hidden="true"></i>', __('Block Client'));

$__FM_CONFIG['icons'] = @array_merge((array) $__FM_CONFIG['module']['icons'], (array) $__FM_CONFIG['icons']);

$__FM_CONFIG['servers']['avail_types']    = array('servers' => _('Access Points'), 'groups' => _('AP Groups'));
$__FM_CONFIG['acls']['actions']           = array('accept' => __('Allow'), 'deny' => __('Deny'));

/** Cleanup options */
$__FM_CONFIG['module']['clean']['prefixes']	= array('fm_' . $__FM_CONFIG['fmWifi']['prefix'] . 'acls'=>'acl', 'fm_' . $__FM_CONFIG['fmWifi']['prefix'] . 'config'=>'config',
											'fm_' . $__FM_CONFIG['fmWifi']['prefix'] . 'servers'=>'server', 'fm_' . $__FM_CONFIG['fmWifi']['prefix'] . 'server_groups'=>'group',
											'fm_' . $__FM_CONFIG['fmWifi']['prefix'] . 'wlan_users'=>'wlan_user',);

$__FM_CONFIG['clean']['prefixes']			= @array_merge((array) $__FM_CONFIG['clean']['prefixes'], (array) $__FM_CONFIG['module']['clean']['prefixes']);

/** Module settings */
$__FM_CONFIG['fmWifi']['default']['options'] = @array(
		'include_wlan_psk' => array(
				'description' => array(__('Include WLAN PSK'), __('Always include the WLAN PSK even when users are defined.')),
				'default_value' => 'no',
				'type' => 'checkbox'),
		'use_ebtables' => array(
				'description' => array(__('Use ebtables'), 
					str_replace('ebtables', '<a href="http://ebtables.netfilter.org/" target="_blank">ebtables</a>', __('Block clients with ebtables in addition to deny list. The ebtables package is required on the access point (AP) and the AP must be configured as a bridge.<p>This option is recommended for Raspbian systems.')) .
					sprintf(' <a href="#" class="tooltip-right" data-tooltip="%s"><i class="fa fa-question-circle"></i></a></p>', __('The ACL functionality of hostapd (macaddr_acl) does not seem to work with Raspbian. Therefore, the use of ebtables is recommended to deny clients.'))),
				'default_value' => 'yes',
				'type' => 'checkbox')
	);
