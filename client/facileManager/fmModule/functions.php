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
 | fmModule: Brief module description                                      |
 +-------------------------------------------------------------------------+
 | http://URL                                                              |
 +-------------------------------------------------------------------------+
*/

/**
 * fmModule Functions
 *
 * @package fmModule
 * @subpackage Client
 *
 */


/**
 * Adds the server to the database
 *
 * @since 1.0
 * @package facileManager
 *
 * @param string $url URL to post data to
 * @param array $data Data to post
 * @return array
 */
function installFMModule($module_name, $proto, $compress, $data, $server_location, $url) {
	global $argv;
	
	/**
	 * Add any module-specific installation checks here
	 */
	
	/** Handle the update method */
	$data['server_update_method'] = processUpdateMethod($module_name, $update_method, $data, $url);

	$raw_data = getPostData(str_replace('genserial', 'addserial', $url), $data);
	$raw_data = $data['compress'] ? @unserialize(gzuncompress($raw_data)) : @unserialize($raw_data);
	
	return $data;
}


/**
 * Finish building the server config with module-specific data
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmModule
 *
 * @param string $url URL to post data to
 * @param array $data Data to post
 * @return array
 */
function buildConf($url, $data) {
	global $proto, $debug;
	
	$raw_data = getPostData($url, $data);
	$raw_data = $data['compress'] ? @unserialize(gzuncompress($raw_data)) : @unserialize($raw_data);
	if (!is_array($raw_data)) {
		if ($debug) echo fM($raw_data);
		addLogEntry($raw_data);
		exit(1);
	}
	if ($debug) {
		foreach ($raw_data['files'] as $filename => $contents) {
			echo str_repeat('=', 50) . "\n";
			echo $filename . ":\n";
			echo str_repeat('=', 50) . "\n";
			echo $contents . "\n\n";
		}
	}
	
	extract($raw_data, EXTR_SKIP);
	
	$runas = 'root';
	$chown_dirs = array($server_root_dir);
	
	/** Install the new files */
	installFiles($files, $data['dryrun'], $chown_dirs, $runas);
	
	$message = "Reloading the server\n";
	if ($debug) echo fM($message);

	/**
	 * Insert reload code here
	 */
	
	return true;
}


/**
 * Sets additional variables to add to the database
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmModule
 *
 * @return array
 */
function moduleAddServer() {
	/**
	 * Define additional array elements to be passed to the database
	 */
	
	$data['element'] = null;
	
	return $data;
}


/**
 * Processes module-specific web requests
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmModule
 *
 * @return array
 */
function moduleInitWebRequest() {
	/**
	 * Define additional array elements to be passed to the database
	 */
	
	$data['element'] = null;
	
	return $data;
}
