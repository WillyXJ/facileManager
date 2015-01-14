<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2014 The facileManager Team                               |
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
 * facileManager language translation functions
 *
 * @package facileManager
 * @subpackage i18n
 */

$directory = ABSPATH . 'fm-modules/' . $fm_name . '/languages';
$domain = $fm_name;
$encoding = 'UTF-8';

$_SESSION['language'] = getLanguage($directory);

putenv('LANG=' . $_SESSION['language']); 
setlocale(LC_ALL, $_SESSION['language']);
 
bindtextdomain($domain, $directory); 
bind_textdomain_codeset($domain, $encoding);
 
textdomain($domain);


/**
 * Returns if access to a zone is allowed
 *
 * @since 2.0
 * @package facileManager
 *
 * @param string $directory Directory where language files are located
 * @return string
 */
function getLanguage($directory) {
	session_start();

	if (isset($_SESSION['language'])) $_SESSION['language'];
	
	$supported_languages = scandir($directory);
	$languages = explode(',', str_replace('-', '_', $_SERVER['HTTP_ACCEPT_LANGUAGE']));
	
	foreach ($languages as $lang) {
		if (in_array($lang, $supported_languages)) {
			return $lang;
		}
	}
	
	return 'en_US';
}
