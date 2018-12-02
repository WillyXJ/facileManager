<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2013-2018 The facileManager Team                               |
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
 | fmWifi: Brief module description                                      |
 +-------------------------------------------------------------------------+
 | http://URL                                                              |
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
		'version'							=> '0.1',
		'client_version'					=> '0.1',
		'description'						=> __('Manage wifi access points with hostapd.', 'fmWifi'),
		'prefix'							=> 'wifi_',
		'required_fm_version'				=> '3.2',
		'required_daemon_version'			=> '2.4',
		'min_client_auto_upgrade_version'	=> '1.0'
	);

/** Module-specific Images */
if (isset($__FM_CONFIG['module']['path'])) {
	$__FM_CONFIG['module']['icons']['action']['active']		= '<img src="' . $__FM_CONFIG['module']['path']['images'] . '/__action__.png" border="0" alt="__Action__" title="__Action__" width="12" />';
	$__FM_CONFIG['module']['icons']['action']['disabled']	= '<img src="' . $__FM_CONFIG['module']['path']['images'] . '/__action___d.png" border="0" alt="__Action__ (' . __('disabled') . ')" title="__Action__ (' . __('disabled') . ')" width="12" />';
}

$__FM_CONFIG['icons'] = @array_merge($__FM_CONFIG['module']['icons'], $__FM_CONFIG['icons']);

$__FM_CONFIG['servers']['avail_types']    = array('servers' => _('Access Points'), 'groups' => _('AP Groups'));
$__FM_CONFIG['acls']['actions']           = array('accept' => __('Allow'), 'deny' => __('Deny'));

/** Cleanup options */
$__FM_CONFIG['module']['clean']['prefixes']	= array('fm_' . $__FM_CONFIG['fmWifi']['prefix'] . 'table'=>'prefix');

$__FM_CONFIG['clean']['prefixes']			= @array_merge($__FM_CONFIG['clean']['prefixes'], $__FM_CONFIG['module']['clean']['prefixes']);

/** Module settings */
$__FM_CONFIG['fmWifi']['default']['options'] = @array(
		'use_ebtables' => array(
				'description' => array(__('Use ebtables'), __('Block clients with ebtables in addition to deny list. The ebtables package is required on the access point.')),
				'default_value' => 'no',
				'type' => 'checkbox')
	);

?>
