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

if (!$fm_login->isLoggedIn() || (!currentUserCan('do_everything') && getOption('fm_db_version') >= 32)) header('Location: ' . dirname($_SERVER['PHP_SELF']));

/** Ensure we meet the requirements */
require_once(ABSPATH . 'fm-includes/init.php');
require_once(ABSPATH . 'fm-includes/version.php');
$app_compat = checkAppVersions(false);

if ($app_compat) {
	bailOut($app_compat);
}

$step = isset($_GET['step']) ? $_GET['step'] : 0;

printHeader('Upgrade', 'install');

switch ($step) {
	case 0:
	case 1:
		if (!file_exists(ABSPATH . 'config.inc.php') || !file_get_contents(ABSPATH . 'config.inc.php')) {
			header('Location: /fm-install.php');
		}
		echo <<<HTML
	<center>
	<p>I have detected you recently upgraded $fm_name, but have not upgraded the database.<br />Click 'Upgrade' to start the upgrade process.</p>
	<p class="step"><a href="?step=2" class="button click_once">Upgrade</a></p>
	</center>

HTML;
		break;
	case 2:
		if (!file_exists(ABSPATH . 'config.inc.php') || !file_get_contents(ABSPATH . 'config.inc.php')) {
			header('Location: /fm-install.php');
		}
		require_once(ABSPATH . 'fm-modules/facileManager/upgrade.php');

		include(ABSPATH . 'config.inc.php');
		include_once(ABSPATH . 'fm-includes/fm-db.php');

		fmUpgrade($__FM_CONFIG['db']['name']);
		break;
}

printFooter();

?>