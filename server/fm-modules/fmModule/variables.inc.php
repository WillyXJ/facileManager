<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2013-2019 The facileManager Team                          |
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
 | fmModule: Brief module description                                      |
 +-------------------------------------------------------------------------+
 | http://URL                                                              |
 +-------------------------------------------------------------------------+
*/

/**
 * Contains variables for fmModule
 *
 * @package fmModule
 *
 */

if (!@is_array($__FM_CONFIG)) $__FM_CONFIG = array();

/** Module Version */
$__FM_CONFIG['fmModule'] = array(
		'version'							=> '1.0',
		'client_version'					=> '1.0',
		'description'						=> __('This description appears on the modules page.', 'fmModule'),
		'prefix'							=> 'pre_',
		'required_fm_version'				=> '2.1.5',
		'min_client_auto_upgrade_version'	=> '1.0'
	);

/** Module-specific Images */
if (isset($__FM_CONFIG['module']['path'])) {
	$__FM_CONFIG['module']['icons']['action']['active']		= '<img src="' . $__FM_CONFIG['module']['path']['images'] . '/__action__.png" border="0" alt="__Action__" title="__Action__" width="12" />';
	$__FM_CONFIG['module']['icons']['action']['disabled']	= '<img src="' . $__FM_CONFIG['module']['path']['images'] . '/__action___d.png" border="0" alt="__Action__ (' . __('disabled') . ')" title="__Action__ (' . __('disabled') . ')" width="12" />';
}

$__FM_CONFIG['icons'] = @array_merge($__FM_CONFIG['module']['icons'], $__FM_CONFIG['icons']);

/** Cleanup options */
$__FM_CONFIG['module']['clean']['prefixes']	= array('fm_' . $__FM_CONFIG['fmModule']['prefix'] . 'table'=>'prefix');

$__FM_CONFIG['clean']['prefixes']			= @array_merge($__FM_CONFIG['clean']['prefixes'], $__FM_CONFIG['module']['clean']['prefixes']);

/** Module settings */
$__FM_CONFIG['fmModule']['default']['options'] = @array(
		'setting1_key' => array(
				'description' => array(__('Setting 1 Title'), __('Setting 1 description goes here.')),
				'default_value' => 'no',
				'type' => 'checkbox'),
		'setting2_key' => array(
				'description' => array(__('Setting 2 Title'), __('Setting 2 description goes here.')),
				'default_value' => 'yes',
				'type' => 'checkbox')
	);

?>
