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
 * These functions are needed to load facileManager
 *
 * @internal This file must be parsable by PHP4.
 *
 * @package facileManager
 */

require_once(ABSPATH . 'fm-modules/facileManager/functions.php');

/**
 * Check for the required PHP version, and the MySQL extension or a database drop-in.
 *
 * Dies if requirements are not met.
 *
 * @access private
 * @since 1.0
 */
function checkAppVersions($single_check = true) {
	global $fm_name;
	
	require(ABSPATH . 'fm-includes/version.php');

	$requirement_check = null;
	$error = false;
	
	/** PHP Version */
	if (version_compare(PHP_VERSION, $required_php_version, '<')) {
		if ($single_check) {
			bailOut(sprintf('<p style="text-align: center;">Your server is running PHP version %1$s but %2$s %3$s requires at least %4$s.</p>', PHP_VERSION, $fm_name, $fm_version, $required_php_version));
		} else {
			$requirement_check .= displayProgress("PHP >= $required_php_version", false, false);
			$error = true;
		}
	} else {
		if (!$single_check) $requirement_check .= displayProgress("PHP >= $required_php_version", true, false);
	}

	/** PHP Extensions */
	$required_php_extensions = array('mysql', 'mysqli', 'curl', 'posix', 'filter', 'json');
	foreach ($required_php_extensions as $extenstion) {
		if (!extension_loaded($extenstion)) {
			if ($single_check) {
				bailOut(sprintf('<p style="text-align: center;">Your PHP installation appears to be missing the %1s extension which is required by %2s.</p>', $extenstion, $fm_name));
			} else {
				$requirement_check .= displayProgress("PHP $extenstion Extension", false, false);
				$error = true;
			}
		} else {
			if (!$single_check) $requirement_check .= displayProgress("PHP $extenstion Extension", true, false);
		}
	}
	
	/** Apache mod_rewrite module */
	if (!in_array('mod_rewrite', apache_get_modules())) {
		if ($single_check) {
			bailOut(sprintf('<p style="text-align: center;">Your Apache installation appears to be missing the mod_rewrite module which is required by %1s.</p>', $fm_name));
		} else {
			$requirement_check .= displayProgress('Apache mod_rewrite Loaded', false, false);
			$error = true;
		}
	} else {
		if (!$single_check) $requirement_check .= displayProgress('Apache mod_rewrite Loaded', true, false);
	}
	
	/** .htaccess file */
	if (!file_exists(ABSPATH . '.htaccess')) {
		if (is_writeable(ABSPATH)) {
			file_put_contents(ABSPATH . '.htaccess', '<IfModule mod_rewrite.c>
RewriteEngine On

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . index.php [L]
</IfModule>

');
		} else {
			if ($single_check) {
				bailOut(sprintf('<p style="text-align: center;">I cannot create the missing %1s.htaccess which is required by %2s so please create it with the following contents:</p>', ABSPATH, $fm_name) . 
				'<textarea rows="8">&lt;IfModule mod_rewrite.c&gt;
RewriteEngine On

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . index.php [L]
&lt;/IfModule&gt;
</textarea>');
			} else {
				$requirement_check .= displayProgress('.htaccess File Present', false, false);
				$error = true;
			}
		}
	} else {
		if (!$single_check) $requirement_check .= displayProgress('.htaccess File Present', true, false);
	}
	
	/** Test rewrites */
	if (!defined('INSTALL')) {
		if (dns_get_record($_SERVER['SERVER_NAME'], DNS_A + DNS_AAAA)) {
			$test_output = getPostData($GLOBALS['FM_URL'] . 'admin-accounts.php?verify', array('module_type' => 'CLIENT'));
			$test_output = isSerialized($test_output) ? unserialize($test_output) : $test_output;
			if (strpos($test_output, 'Account is not found.') === false) {
				if ($single_check) {
					bailOut(sprintf('<p style="text-align: center;">The required .htaccess file appears to not work with your Apache configuration which is required by %1s.</p>', $fm_name));
				} else {
					$requirement_check .= displayProgress('Test Rewrites', false, false);
					$error = true;
				}
			} else {
				if (!$single_check) $requirement_check .= displayProgress('Test Rewrites', true, false);
			}
		}
	}
	
	if ($error) {
		$requirement_check = <<<HTML
			<center><table class="form-table">
			<tr>
				<td colspan="2" id="install_module_list" class="bottom_line"><p><b>System Requirement Checks</p></td>
			</tr>
			$requirement_check
			</table></center>
			<p class="step"><a href="{$_SERVER['PHP_SELF']}" class="button">Try Again</a></p>

HTML;
	} else $requirement_check = null;
	
	return $requirement_check;
}

?>