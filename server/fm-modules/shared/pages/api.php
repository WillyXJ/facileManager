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
 | https://www.facilemanager.com/                                           |
 +-------------------------------------------------------------------------+
 | Processes API requests                                                  |
 +-------------------------------------------------------------------------+
*/

/** Ensure we have data to process */
if (!isset($_POST) || !count($_POST)) {
	exit;
}

/** Handle client interactions */
if (!defined('CLIENT')) define('CLIENT', true);

require_once('fm-init.php');
include(ABSPATH . 'fm-modules/facileManager/classes/class_accounts.php');

/** Ensure we have a valid account */
$account_verify = $fm_accounts->verify($_POST);
if ($account_verify != 'Success') {
	if ($_POST['compress']) echo gzcompress(serialize($account_verify));
	else echo serialize($account_verify);
	exit;
}

/** Authenticate token */
require_once(ABSPATH . 'fm-modules/facileManager/classes/class_logins.php');
$logged_in = @$fm_login->doAPIAuth(sanitize($_POST['APIKEY']), sanitize($_POST['APISECRET']));

if (!$logged_in) {
	$message = _('Invalid credentials.') . "\n";
	if ($_POST['compress']) echo gzcompress(serialize($message));
	else echo serialize($message);
	exit;
}

if (isset($_POST['test'])) {
	$message = _('API functionality tests were successful.') . "\n";
	if ($_POST['compress']) echo gzcompress(serialize($message));
	else echo serialize($message);
	exit;
}

/** Include actions from module */
$module_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_POST['module_name'] . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'api.inc.php';
if (file_exists($module_file)) {
	include($module_file);
}

/** Output $data */
if (!empty($data)) {
	if ($_POST['compress']) echo gzcompress(serialize($data));
	else echo serialize($data);
}

exit;
