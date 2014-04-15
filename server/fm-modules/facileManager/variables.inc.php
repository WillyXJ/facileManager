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
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/                                           |
 +-------------------------------------------------------------------------+
*/

/**
 * Contains variables for facileManager
 *
 * @package facileManager
 *
 */

setTimezone();

/**
 * The facileManager name string
 *
 * @global string $fm_name
 */
$fm_name = 'facileManager';

/** Set global variables */
$GLOBALS['REQUEST_PROTOCOL'] = isSiteSecure() ? 'https' : 'http';
$GLOBALS['FM_URL'] = $GLOBALS['REQUEST_PROTOCOL'] . '://' . $_SERVER['HTTP_HOST'] . $GLOBALS['RELPATH'];

/** Styled break in menu listing */
$__FM_CONFIG['menu']['Break'][]				= null;

/** Admin Menu Options */
$__FM_CONFIG['menu']['Admin']['URL']		= 'admin-tools.php';
$__FM_CONFIG['menu']['Admin']['Tools']		= 'admin-tools.php';
$__FM_CONFIG['menu']['Admin']['Users']		= 'admin-users.php';
$__FM_CONFIG['menu']['Admin']['Logs']		= 'admin-logs.php';
$__FM_CONFIG['menu']['Settings']['URL']		= 'admin-settings.php';
$__FM_CONFIG['menu']['Modules']['URL']		= 'admin-modules.php';

/** Images */
$__FM_CONFIG['icons']['fail']			= '<img src="fm-modules/' . $fm_name . '/images/error24.png" border="0" alt="Failed" title="Failed" />';
$__FM_CONFIG['icons']['caution']		= '<img src="fm-modules/' . $fm_name . '/images/orangequestion.jpg" border="0" alt="Caution" title="Caution" width="20" />';
$__FM_CONFIG['icons']['ok']				= '<img src="fm-modules/' . $fm_name . '/images/ok24.png" border="0" alt="OK" title="OK" />';
$__FM_CONFIG['icons']['add']			= '<img src="fm-modules/' . $fm_name . '/images/plus16.png" border="0" alt="Add New" title="Add New" />';
$__FM_CONFIG['icons']['edit']			= '<img src="fm-modules/' . $fm_name . '/images/edit24.png" border="0" alt="Edit" title="Edit" width="20" />';
$__FM_CONFIG['icons']['delete']			= '<img src="fm-modules/' . $fm_name . '/images/delete24.png" border="0" alt="Delete" title="Delete" width="20" />';
$__FM_CONFIG['icons']['enable']			= '<img src="fm-modules/' . $fm_name . '/images/enable24.png" border="0" alt="Enable" title="Enable" width="20" />';
$__FM_CONFIG['icons']['disable']		= '<img src="fm-modules/' . $fm_name . '/images/disable24.png" border="0" alt="Disable" title="Disable" width="20" />';
$__FM_CONFIG['icons']['popout']			= '<img src="fm-modules/' . $fm_name . '/images/popout24.png" border="0" alt="Popout" title="Popout" width="20" class="popout" />';
$__FM_CONFIG['icons']['close']			= '<img src="fm-modules/' . $fm_name . '/images/error24.png" border="0" alt="Close" title="Close" width="20" class="close" />';
$__FM_CONFIG['icons']['pwd_change']		= '<img src="fm-modules/' . $fm_name . '/images/password-change24.png" border="0" alt="Change Password" title="Change Password" height="20" />';
$__FM_CONFIG['icons']['pwd_reset']		= '<img src="fm-modules/' . $fm_name . '/images/password-reset24.png" border="0" alt="Send Password Reset Email" title="Send Password Reset Email" width="20" />';
$__FM_CONFIG['icons']['account']		= '<img src="fm-modules/' . $fm_name . '/images/account24.png" border="0" alt="Account Settings" title="Account Settings" width="20" />';
$__FM_CONFIG['icons']['star']			= '<img src="fm-modules/' . $fm_name . '/images/star16.png" border="0" alt="Super Admin" title="Super Admin" width="12" style="padding-right: 2px;" />';
$__FM_CONFIG['icons']['template_user']	= '<img src="fm-modules/' . $fm_name . '/images/template_user16.png" border="0" alt="Template Account" title="Template Account" width="12" style="padding-right: 2px;" />';
$__FM_CONFIG['icons']['fm_logo']		= '<img src="'. $GLOBALS['FM_URL'] . 'fm-modules/' . $fm_name . '/images/fm.png" border="0" alt="' . $fm_name . '" title="' . $fm_name . '" style="padding-left: 17px;" />';
$__FM_CONFIG['icons']['shield_error']	= '<img src="fm-modules/' . $fm_name . '/images/redshield64.png" border="0" alt="Error" title="Error" />';
$__FM_CONFIG['icons']['shield_info']	= '<img src="fm-modules/' . $fm_name . '/images/yellowshield64.png" border="0" alt="Information" title="Information" />';
$__FM_CONFIG['icons']['shield_ok']		= '<img src="fm-modules/' . $fm_name . '/images/greenshield64.png" border="0" alt="OK" title="OK" />';

$__FM_CONFIG['module']['icons']['preview']		= '<img src="fm-modules/' . $fm_name . '/images/preview24.png" border="0" alt="Preview Config" title="Preview Config" width="20" />';
$__FM_CONFIG['module']['icons']['build']		= '<input type="image" id="build" src="fm-modules/' . $fm_name . '/images/build24.png" border="0" alt="Build Config" title="Build Config" width="20" />';

/** Cleanup options */
$__FM_CONFIG['clean']['prefixes']	= array('fm_accounts'=>'account', 'fm_users'=>'user');
$__FM_CONFIG['clean']['days']		= 7;

/** Text string variables */
$__FM_CONFIG['password_hint']['medium']		= 'You must choose a password with at least seven (7) characters containing letters and numbers.';
$__FM_CONFIG['password_hint']['strong']		= 'You must choose a password with at least eight (8) characters containing uppercase and lowercase letters, numbers, and special characters (\'&\', \'$\', \'@\', etc.).';

/** Limits */
$__FM_CONFIG['limit']['records']	= 50;

/** Options */
$__FM_CONFIG['options']['auth_method']					= array(array('None', 0), array('Builtin Authentication', 1));
$__FM_CONFIG['options']['ldap_version']					= array(array('Version 2', 2), array('Version 3', 3));
$__FM_CONFIG['options']['ldap_encryption']				= array('None', 'SSL', 'TLS');
$__FM_CONFIG['options']['ldap_referrals']				= array(array('Disabled', 0), array('Enabled', 1));
$__FM_CONFIG['options']['date_format']					= array(array(date('F j, Y'), 'F j, Y'), array(date('j F, Y'), 'j F, Y'), array(date('d/m/Y'), 'd/m/Y'), array(date('m/d/Y'), 'm/d/Y'), array(date('Y/m/d'), 'Y/m/d'), array(date('Y-m-d'), 'Y-m-d'), array(date('D, d M Y'), 'D, d M Y'));
$__FM_CONFIG['options']['time_format']					= array(array(date('g:i a'), 'g:i a'), array(date('g:i:s a'), 'g:i:s a'), array(date('g:i A'), 'g:i A'), array(date('g:i:s A'), 'g:i:s A'), array(date('H:i'), 'H:i'), array(date('H:i:s'), 'H:i:s'), array(date('H:i:s O'), 'H:i:s O'), array(date('H:i:s T'), 'H:i:s T'));
$__FM_CONFIG['options']['software_update_interval']		= array(array('Hourly', 'hour'), array('Daily', 'day'), array('Weekly', 'week'), array('Monthly', 'month'));
$__FM_CONFIG['options']['software_update_tree']			= array('Stable', 'Release Candidate', 'Beta', 'Alpha');

if (function_exists('ldap_connect')) array_push($__FM_CONFIG['options']['auth_method'], array('LDAP Authentication', 2));

/** Webserver Runas */
$__FM_CONFIG['webserver']['user_info'] = posix_getpwuid(posix_geteuid());

/** Array sorts */
@sort($__FM_CONFIG['logging']['categories']);


/** Constants */
if (!defined('TMP_FILE_EXPORTS')) {
	define('TMP_FILE_EXPORTS', '/tmp');
}

/** PWD_STRENGTH */
if (class_exists('fmdb')) $auth_fm_pw_strength = getOption('auth_fm_pw_strength');
$GLOBALS['PWD_STRENGTH'] = (isset($auth_fm_pw_strength) && !empty($auth_fm_pw_strength)) ? $auth_fm_pw_strength : 'strong';

include(dirname(__FILE__) . '/permissions.inc.php');

?>
