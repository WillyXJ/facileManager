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
 | Common AJAX processing functions                                        |
 +-------------------------------------------------------------------------+
*/

/**
 * Displays an error
 *
 * @since 1.0
 * @package facileManager
 */
function returnError($addl_msg = null, $display = 'window') {
	$msg = _('There was a problem with your request.');
	if ($addl_msg) $msg .= $addl_msg;
	
	if ($display == 'window') {
		echo buildPopup('header', _('Error'));
		echo "<p>$msg</p>\n";
		echo buildPopup('footer', _('OK'), array('cancel_button' => 'cancel'));
	} else {
		echo displayResponseClose($msg);
	}
	exit;
}


/**
 * Displays unauthorized message
 *
 * @since 1.0
 * @package facileManager
 */
function returnUnAuth($format = 'window') {
	$msg = _('You do not have permission to make these changes.');
	switch ($format) {
		case 'window':
			echo buildPopup('header', _('Error'));
			echo "<p>$msg</p>\n";
			echo buildPopup('footer', _('OK'), array('cancel_button' => 'cancel'));
			break;
		case 'response-close':
			echo displayResponseClose($msg);
			break;
		default:
			echo $msg;
	}
	exit;
}


/**
 * Hightlights failures and successes
 * 
 * @since 4.7.0
 * @package facileManager
 * 
 * @param $text Text to transform
 * @return string
 */
function transformOutput($text) {
	global $__FM_CONFIG;

	foreach (explode("\n", $text) as $line) {
		if (strpos(strtolower($line), _('failed')) !== false) {
			$line = str_replace('-->', '', $line);
			$line = sprintf(' %s %s', $__FM_CONFIG['icons']['fail'], trim($line));
		} elseif (strpos(strtolower($line), _('successful')) !== false) {
			$line = str_replace('-->', '', $line);
			$line = sprintf(' %s %s', $__FM_CONFIG['icons']['ok'], trim($line));
		} elseif (strpos(strtolower($line), _('notice')) !== false) {
			$line = str_replace('-->', '', $line);
			$line = sprintf(' %s %s', $__FM_CONFIG['icons']['caution'], trim($line));
		}
		$tmp_output[] = str_replace('-->', $__FM_CONFIG['icons']['ok'], $line);
	}

	return join("\n", $tmp_output);
}
