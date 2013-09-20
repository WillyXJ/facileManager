<?php

/**
 * Contains variables for fmSQLPass
 *
 * @package fmSQLPass
 *
 */

/** Module Version */
$__FM_CONFIG['fmSQLPass']['version']				= '1.0-b1';
$__FM_CONFIG['fmSQLPass']['description']			= 'Change database user passwords across multiple servers.';
$__FM_CONFIG['fmSQLPass']['prefix']					= 'sqlpass_';
$__FM_CONFIG['fmSQLPass']['required_fm_version']	= '1.0-rc3';

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
				'default_value' => PWD_STRENGTH,
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

if (file_exists(dirname(__FILE__) . '/permissions.inc.php')) {
	include(dirname(__FILE__) . '/permissions.inc.php');
}

?>
