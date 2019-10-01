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
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/                                           |
 +-------------------------------------------------------------------------+
*/

/**
 * facileManager Upgrader
 *
 * @package facileManager
 * @subpackage Administration
 *
 */

/** Define ABSPATH as this files directory */
define('ABSPATH', dirname(__FILE__) . '/');

/** Set installation variable */
define('UPGRADE', true);

/** Enforce authentication */
require_once(ABSPATH . 'fm-modules/facileManager/classes/class_logins.php');

require_once('fm-init.php');
ini_set('display_errors', false);
error_reporting(0);

if (!$fm_login->isLoggedIn() || (!currentUserCan('do_everything') && getOption('fm_db_version') >= 32)) {
	header('Location: ' . dirname($_SERVER['PHP_SELF']));
	exit;
}

/** Ensure we meet the requirements */
require_once(ABSPATH . 'fm-includes/init.php');
require_once(ABSPATH . 'fm-includes/version.php');
if ($app_compat = checkAppVersions(false)) {
	bailOut($app_compat);
}

$step = isset($_GET['step']) ? $_GET['step'] : 0;

if (array_key_exists('backup', $_GET)) {
	if (!class_exists('fm_tools')) {
		include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . 'facileManager' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class_tools.php');
	}
	$fm_tools->backupDatabase();
	header('Location: ' . $GLOBALS['basename']);
	exit;
}

$branding_logo = getBrandLogo();

printHeader(_('Upgrade'), 'install');

switch ($step) {
	case 0:
	case 1:
		if (!file_exists(ABSPATH . 'config.inc.php') || !file_get_contents(ABSPATH . 'config.inc.php')) {
			header('Location: /fm-install.php');
			exit;
		}
		printf('<div id="fm-branding">
		<img src="%s" /><span>%s</span>
	</div>
	<div id="window"><p>', $branding_logo, _('Upgrade'));
		$backup_button = findProgram('mysqldump') ? sprintf('<a href="?backup" class="button">%s</a>', _('Backup Database')) : null;
		printf(_("I have detected you recently upgraded %s and its modules, but have not upgraded the database. Click 'Upgrade' to start the upgrade process."), $fm_name);
		printf('</p><p class="step"><a href="?step=2" class="button click_once">%s</a> %s</p></div>', _('Upgrade'), $backup_button);
		break;
	case 2:
		if (!file_exists(ABSPATH . 'config.inc.php') || !file_get_contents(ABSPATH . 'config.inc.php')) {
			header('Location: /fm-install.php');
			exit;
		}
		require_once(ABSPATH . 'fm-modules/facileManager/upgrade.php');

		include(ABSPATH . 'config.inc.php');
		include_once(ABSPATH . 'fm-includes/fm-db.php');
		$fmdb = new fmdb($__FM_CONFIG['db']['user'], $__FM_CONFIG['db']['pass'], $__FM_CONFIG['db']['name'], $__FM_CONFIG['db']['host']);

		fmUpgrade($__FM_CONFIG['db']['name']);
		break;
}

printFooter();

?>