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
 | fmSQLPass: Change database user passwords across multiple servers.      |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmsqlpass/                         |
 +-------------------------------------------------------------------------+
*/

/**
 * Contains variables for fmSQLPass
 *
 * @package fmSQLPass
 *
 */

/** Module Version */
$__FM_CONFIG['fmSQLPass']['version']				= '1.0-b6';
$__FM_CONFIG['fmSQLPass']['description']			= 'Change database user passwords across a server farm running multiple database server types.
														Password complexity requirements are enforced to ensure secure passwords. Currently supported
														database servers include MySQL.';
$__FM_CONFIG['fmSQLPass']['prefix']					= 'sqlpass_';
$__FM_CONFIG['fmSQLPass']['required_fm_version']	= '1.1';

/** Dashboard Menu Options */
$__FM_CONFIG['module']['menu']['Dashboard']['URL']			= '';

/** Config Menu Options */
$__FM_CONFIG['module']['menu']['Config']['URL']				= 'config-servers';
$__FM_CONFIG['module']['menu']['Config']['Servers']			= 'config-servers';
$__FM_CONFIG['module']['menu']['Config']['Server Groups']	= 'config-groups';
$__FM_CONFIG['module']['menu']['Config']['Passwords']		= 'config-passwords';

/** Settings Menu Options */
$__FM_CONFIG['module']['menu']['Settings']['URL']	= 'module-settings';

$__FM_CONFIG['menu'] = array_merge($__FM_CONFIG['module']['menu'], $__FM_CONFIG['menu']);

/** Default values */
$pwd_strength_desc = null;
foreach ($__FM_CONFIG['password_hint'] as $strength => $desc) {
	$pwd_strength_desc .= '<i>' . $strength . '</i> - ' . str_replace('You must choose a password with a', 'A', $__FM_CONFIG['password_hint'][$strength]) . "<br /><br />\n";
}
$__FM_CONFIG['fmSQLPass']['default']['options'] = array(
		'minimum_pwd_strength' => array(
				'description' => array('Minimum Password Strength', rtrim($pwd_strength_desc, "<br /><br />\n")),
				'default_value' => $GLOBALS['PWD_STRENGTH'],
				'type' => 'select',
				'options' => array_keys($__FM_CONFIG['password_hint'])),
		'admin_username' => array(
				'description' => array('Default Username', 'Default MySQL user to login as. This will be overridden if the user is defined at the server level.'),
				'default_value' => null,
				'type' => 'text'),
		'admin_password' => array(
				'description' => array('Default Password', 'Default MySQL user password to login with. This will be overridden if the password is defined at the server level.'),
				'default_value' => null,
				'type' => 'password')
	);
$__FM_CONFIG['fmSQLPass']['default']['ports'] = array('MySQL' => 3306,
													'PostgreSQL' => 5432,
													'MSSQL' => 1433
												);

/** Module Permissions */
if (file_exists(dirname(__FILE__) . '/permissions.inc.php')) {
	include(dirname(__FILE__) . '/permissions.inc.php');
}

?>
