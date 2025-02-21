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
$__FM_CONFIG['icons']['fail']			= sprintf('<i class="fa fa-times fa-lg fail" alt="%1$s" title="%1$s" aria-hidden="true"></i>', _('Failed'));
$__FM_CONFIG['icons']['caution']		= sprintf('<i class="fa fa-exclamation-triangle fa-lg notice" alt="%1$s" title="%1$s" aria-hidden="true"></i>', _('Caution'));
$__FM_CONFIG['icons']['ok']				= sprintf('<i class="fa fa-check fa-lg ok" alt="%1$s" title="%1$s" aria-hidden="true"></i>', _('OK'));
$__FM_CONFIG['icons']['edit']			= sprintf('<i class="fa fa-pencil-square-o preview" alt="%1$s" title="%1$s" aria-hidden="true"></i>', _('Edit'));
$__FM_CONFIG['icons']['delete']			= sprintf('<i class="fa fa-trash delete" alt="%1$s" title="%1$s" aria-hidden="true"></i>', _('Delete'));
$__FM_CONFIG['icons']['copy']			= sprintf('<i class="fa fa-copy" alt="%1$s" title="%1$s" aria-hidden="true"></i>', _('Duplicate'));
$__FM_CONFIG['icons']['enable']			= sprintf('<i class="fa fa-toggle-off toggle" alt="%1$s" title="%1$s" aria-hidden="true"></i>', _('Enable (currently disabled)'));
$__FM_CONFIG['icons']['disable']		= sprintf('<i class="fa fa-toggle-on toggle" alt="%1$s" title="%1$s" aria-hidden="true"></i>', _('Disable (currently enabled)'));
$__FM_CONFIG['icons']['popout']			= sprintf('<i class="fa fa-external-link-square fa-lg popout" alt="%1$s" title="%1$s" aria-hidden="true"></i>', _('Popout'));
$__FM_CONFIG['icons']['close']			= sprintf('<i class="fa fa-window-close fa-lg close" alt="%1$s" title="%1$s" aria-hidden="true"></i>', _('Close'));
$__FM_CONFIG['icons']['pwd_change']		= sprintf('<i class="fa fa-user preview" alt="%1$s" title="%1$s" aria-hidden="true"></i>', _('Edit Profile'));
$__FM_CONFIG['icons']['pwd_reset']		= sprintf('<i class="fa fa-unlock-alt" alt="%1$s" title="%1$s" aria-hidden="true"></i>', _('Send Password Reset Email'));
$__FM_CONFIG['icons']['account']		= sprintf('<img src="fm-modules/%1$s/images/account24.png" border="0" alt="%2$s" title="%2$s" width="20" />', $fm_name, _('Account Settings'));
$__FM_CONFIG['icons']['star']			= sprintf('<i class="fa fa-star star" alt="%1$s" title="%1$s" aria-hidden="true"></i>', _('Super Admin'));
$__FM_CONFIG['icons']['template_user']	= sprintf('<i class="fa fa-user" alt="%1$s" title="%1$s" aria-hidden="true"></i>', _('Template Account'));
$__FM_CONFIG['icons']['shield_error']	= sprintf('<img src="fm-modules/%1$s/images/redshield64.png" border="0" alt="%2$s" title="%2$s" />', $fm_name, _('Error'));
$__FM_CONFIG['icons']['shield_info']	= sprintf('<img src="fm-modules/%1$s/images/yellowshield64.png" border="0" alt="%2$s" title="%2$s" />', $fm_name, _('Information'));
$__FM_CONFIG['icons']['shield_ok']		= sprintf('<img src="fm-modules/%1$s/images/greenshield64.png" border="0" alt="%2$s" title="%2$s" />', $fm_name, _('OK'));

/** Module variables */
$__FM_CONFIG['module']['icons']['preview'] = sprintf('<i class="fa fa-search preview" alt="%1$s" title="%1$s" aria-hidden="true"></i>', _('Preview Config'));
$__FM_CONFIG['module']['icons']['build']   = sprintf('<i class="fa fa-wrench preview" id="build" alt="%1$s" title="%1$s" aria-hidden="true"></i>', _('Build Config'));
if (isset($_SESSION['module'])) {
	foreach (array('ajax', 'classes', 'css', 'extra', 'images', 'js') as $dir) {
		$__FM_CONFIG['module']['path'][$dir]   = sprintf('fm-modules/%s/%s', $_SESSION['module'], $dir);
	}
}

/** Cleanup options */
$__FM_CONFIG['clean']['prefixes']	= array('fm_accounts' => 'account', 'fm_users' => 'user');
$__FM_CONFIG['clean']['time']		= '15 minutes';

/** Text string variables */
$__FM_CONFIG['password_hint']['medium']		= array(_('Medium'), _('The password must be at least seven (7) characters long containing letters and numbers.'));
$__FM_CONFIG['password_hint']['strong']		= array(_('Strong'), _('The password must be at least eight (8) characters long containing uppercase and lowercase letters, numbers, and special characters (\'&\', \'$\', \'@\', etc.).'));
$__FM_CONFIG['users']['avail_types']        = array('users' => _('Users'), 'groups' => _('Groups'));
if (!currentUserCan('manage_users')) unset($__FM_CONFIG['users']['avail_types']['groups']);
if (getOption('api_token_support')) {
	$__FM_CONFIG['users']['avail_types']['keys'] = _('API Keys');
}

/** Limits */
$__FM_CONFIG['limit']['records']	= array(20, 35, 50, 75, 100, 200);

/** Options */
$__FM_CONFIG['options']['auth_method']					= array(array(_('None'), 0), array(_('Built-in Authentication'), 1));
$__FM_CONFIG['options']['ldap_version']					= array(array(_('Version 2'), 2), array(_('Version 3'), 3));
$__FM_CONFIG['options']['ldap_encryption']				= array(_('None'), 'SSL', 'TLS');
$__FM_CONFIG['options']['ldap_referrals']				= array(array(_('Disabled'), 0), array(_('Enabled'), 1));
$__FM_CONFIG['options']['date_format']					= array(array(date('F j, Y'), 'F j, Y'), array(date('j F, Y'), 'j F, Y'), array(date('d/m/Y'), 'd/m/Y'), array(date('d.m.Y'), 'd.m.Y'), array(date('m/d/Y'), 'm/d/Y'), array(date('Y/m/d'), 'Y/m/d'), array(date('Y-m-d'), 'Y-m-d'), array(date('D, d M Y'), 'D, d M Y'));
$__FM_CONFIG['options']['time_format']					= array(array(date('g:i a'), 'g:i a'), array(date('g:i:s a'), 'g:i:s a'), array(date('g:i A'), 'g:i A'), array(date('g:i:s A'), 'g:i:s A'), array(date('H:i'), 'H:i'), array(date('H:i:s'), 'H:i:s'), array(date('H:i:s O'), 'H:i:s O'), array(date('H:i:s T'), 'H:i:s T'));
$__FM_CONFIG['options']['software_update_interval']		= array(array(_('Hourly'), 'hour'), array(_('Daily'), 'day'), array(_('Weekly'), 'week'), array(_('Monthly'), 'month'));
$__FM_CONFIG['options']['software_update_tree']			= array(_('Stable'), _('Release Candidate'), _('Beta'), _('Alpha'));
$__FM_CONFIG['options']['log_method']					= array(array(_('Built-in'), 0), array('syslog', 1), array(_('Built-in + syslog'), 2));
$__FM_CONFIG['options']['syslog_facilities']			= array(array('auth', 32), array('authpriv', 80), array('cron', 72), array('daemon', 24), array('kern', 0), array('lpr', 48), array('mail', 16), array('news', 56), array('syslog', 40), array('user', 8), array('uucp', 64),
															array('local0', 128), array('local1', 136), array('local2', 144), array('local3', 152), array('local4', 160), array('local5', 168), array('local6', 176), array('local7', 184));

if (function_exists('ldap_connect')) array_push($__FM_CONFIG['options']['auth_method'], array(_('LDAP Authentication'), 2));

/** Defaults */
$__FM_CONFIG['default']['theme']				= 'Ocean';
$__FM_CONFIG['default']['popup']['dimensions'] 	= 'width=700,height=800';

/** Webserver Runas */
if (function_exists('posix_getpwuid')) {
	$__FM_CONFIG['webserver']['user_info'] = posix_getpwuid(posix_geteuid());
}

/** Constants */
if (!defined('TMP_FILE_EXPORTS')) {
	define('TMP_FILE_EXPORTS', sys_get_temp_dir());
}

/** PWD_STRENGTH */
if (class_exists('fmdb')) $auth_fm_pw_strength = getOption('auth_fm_pw_strength');
$GLOBALS['PWD_STRENGTH'] = (isset($auth_fm_pw_strength) && !empty($auth_fm_pw_strength)) ? $auth_fm_pw_strength : 'strong';
