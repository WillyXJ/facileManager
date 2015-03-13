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

if (!@is_array($__FM_CONFIG)) $__FM_CONFIG = array();

/** Images */
$__FM_CONFIG['icons']['fail']			= sprintf('<img src="fm-modules/%1$s/images/error24.png" border="0" alt="%2$s" title="%2$s" />', $fm_name, _('Failed'));
$__FM_CONFIG['icons']['caution']		= sprintf('<img src="fm-modules/%1$s/images/orangequestion.jpg" border="0" alt="%2$s" title="%2$s" width="20" />', $fm_name, _('Caution'));
$__FM_CONFIG['icons']['ok']				= sprintf('<img src="fm-modules/%1$s/images/ok24.png" border="0" alt="%2$s" title="%2$s" />', $fm_name, _('OK'));
$__FM_CONFIG['icons']['add']			= sprintf('<img src="fm-modules/%1$s/images/plus16.png" border="0" alt="_TITLE_" title="_TITLE_" />', $fm_name);
$__FM_CONFIG['icons']['edit']			= sprintf('<img src="fm-modules/%1$s/images/edit24.png" border="0" alt="%2$s" title="%2$s" width="20" />', $fm_name, _('Edit'));
$__FM_CONFIG['icons']['delete']			= sprintf('<img src="fm-modules/%1$s/images/delete24.png" border="0" alt="%2$s" title="%2$s" width="20" />', $fm_name, _('Delete'));
$__FM_CONFIG['icons']['copy']			= sprintf('<img src="fm-modules/%1$s/images/copy24.png" border="0" alt="%2$s" title="%2$s" width="20" />', $fm_name, _('Duplicate'));
$__FM_CONFIG['icons']['enable']			= sprintf('<img src="fm-modules/%1$s/images/enable24.png" border="0" alt="%2$s" title="%2$s" width="20" />', $fm_name, _('Enable'));
$__FM_CONFIG['icons']['disable']		= sprintf('<img src="fm-modules/%1$s/images/disable24.png" border="0" alt="%2$s" title="%2$s" width="20" />', $fm_name, _('Disable'));
$__FM_CONFIG['icons']['popout']			= sprintf('<img src="fm-modules/%1$s/images/popout24.png" border="0" alt="%2$s" title="%2$s" width="20" class="popout" />', $fm_name, _('Popout'));
$__FM_CONFIG['icons']['close']			= sprintf('<img src="fm-modules/%1$s/images/error24.png" alt="%2$s" title="%2$s" class="close" />', $fm_name, _('Close'));
$__FM_CONFIG['icons']['pwd_change']		= sprintf('<img src="fm-modules/%1$s/images/profile24.png" border="0" alt="%2$s" title="%2$s" width="20" />', $fm_name, _('Edit Profile'));
$__FM_CONFIG['icons']['pwd_reset']		= sprintf('<img src="fm-modules/%1$s/images/password-change24.png" border="0" alt="%2$s" title="%2$s" height="20" />', $fm_name, _('Send Password Reset Email'));
$__FM_CONFIG['icons']['account']		= sprintf('<img src="fm-modules/%1$s/images/account24.png" border="0" alt="%2$s" title="%2$s" width="20" />', $fm_name, _('Account Settings'));
$__FM_CONFIG['icons']['star']			= sprintf('<img src="fm-modules/%1$s/images/star16.png" border="0" alt="%2$s" title="%2$s" width="12" style="padding-right: 2px;" />', $fm_name, _('Super Admin'));
$__FM_CONFIG['icons']['template_user']	= sprintf('<img src="fm-modules/%1$s/images/template_user16.png" border="0" alt="%2$s" title="%2$s" width="12" style="padding-right: 2px;" />', $fm_name, _('Template Account'));
$__FM_CONFIG['icons']['fm_logo']		= sprintf('<img src="'. $GLOBALS['FM_URL'] . 'fm-modules/%1$s/images/fm.png" border="0" alt="%1$s" title="%1$s" style="padding-left: 17px;" />', $fm_name);
$__FM_CONFIG['icons']['shield_error']	= sprintf('<img src="fm-modules/%1$s/images/redshield64.png" border="0" alt="%2$s" title="%2$s" />', $fm_name, _('Error'));
$__FM_CONFIG['icons']['shield_info']	= sprintf('<img src="fm-modules/%1$s/images/yellowshield64.png" border="0" alt="%2$s" title="%2$s" />', $fm_name, _('Information'));
$__FM_CONFIG['icons']['shield_ok']		= sprintf('<img src="fm-modules/%1$s/images/greenshield64.png" border="0" alt="%2$s" title="%2$s" />', $fm_name, _('OK'));

$__FM_CONFIG['module']['icons']['preview']		= sprintf('<img src="fm-modules/%1$s/images/preview24.png" border="0" alt="%2$s" title="%2$s" width="20" />', $fm_name, _('Preview Config'));
$__FM_CONFIG['module']['icons']['build']		= sprintf('<input type="image" id="build" src="fm-modules/%1$s/images/build24.png" border="0" alt="%2$s" title="%2$s" width="20" />', $fm_name, _('Build Config'));

/** Cleanup options */
$__FM_CONFIG['clean']['prefixes']	= array('fm_accounts'=>'account', 'fm_users'=>'user');
$__FM_CONFIG['clean']['days']		= 7;

/** Text string variables */
$__FM_CONFIG['password_hint']['medium']		= _('You must choose a password with at least seven (7) characters containing letters and numbers.');
$__FM_CONFIG['password_hint']['strong']		= _('You must choose a password with at least eight (8) characters containing uppercase and lowercase letters, numbers, and special characters (\'&\', \'$\', \'@\', etc.).');

/** Limits */
$__FM_CONFIG['limit']['records']	= array(20, 35, 50, 75, 100, 200);

/** Options */
$__FM_CONFIG['options']['auth_method']					= array(array(_('None'), 0), array(_('Builtin Authentication'), 1));
$__FM_CONFIG['options']['ldap_version']					= array(array(_('Version 2'), 2), array(_('Version 3'), 3));
$__FM_CONFIG['options']['ldap_encryption']				= array(_('None'), 'SSL', 'TLS');
$__FM_CONFIG['options']['ldap_referrals']				= array(array(_('Disabled'), 0), array(_('Enabled'), 1));
$__FM_CONFIG['options']['date_format']					= array(array(date('F j, Y'), 'F j, Y'), array(date('j F, Y'), 'j F, Y'), array(date('d/m/Y'), 'd/m/Y'), array(date('m/d/Y'), 'm/d/Y'), array(date('Y/m/d'), 'Y/m/d'), array(date('Y-m-d'), 'Y-m-d'), array(date('D, d M Y'), 'D, d M Y'));
$__FM_CONFIG['options']['time_format']					= array(array(date('g:i a'), 'g:i a'), array(date('g:i:s a'), 'g:i:s a'), array(date('g:i A'), 'g:i A'), array(date('g:i:s A'), 'g:i:s A'), array(date('H:i'), 'H:i'), array(date('H:i:s'), 'H:i:s'), array(date('H:i:s O'), 'H:i:s O'), array(date('H:i:s T'), 'H:i:s T'));
$__FM_CONFIG['options']['software_update_interval']		= array(array(_('Hourly'), 'hour'), array(_('Daily'), 'day'), array(_('Weekly'), 'week'), array(_('Monthly'), 'month'));
$__FM_CONFIG['options']['software_update_tree']			= array(_('Stable'), _('Release Candidate'), _('Beta'), _('Alpha'));

if (function_exists('ldap_connect')) array_push($__FM_CONFIG['options']['auth_method'], array(_('LDAP Authentication'), 2));

/** Webserver Runas */
if (function_exists('posix_getpwuid')) {
	$__FM_CONFIG['webserver']['user_info'] = posix_getpwuid(posix_geteuid());
}

/** Array sorts */
@sort($__FM_CONFIG['logging']['categories']);


/** Constants */
if (!defined('TMP_FILE_EXPORTS')) {
	define('TMP_FILE_EXPORTS', sys_get_temp_dir());
}

/** PWD_STRENGTH */
if (class_exists('fmdb')) $auth_fm_pw_strength = getOption('auth_fm_pw_strength');
$GLOBALS['PWD_STRENGTH'] = (isset($auth_fm_pw_strength) && !empty($auth_fm_pw_strength)) ? $auth_fm_pw_strength : 'strong';

?>
