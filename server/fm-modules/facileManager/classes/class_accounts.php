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

class fm_accounts {
	
	/**
	 * Verifies server account
	 *
	 * @since 1.0
	 * @package facileManager
	 */
	function verify($data) {
		global $fmdb, $__FM_CONFIG;
		
		if (!isset($data['AUTHKEY'])) return _('Account is not found.') . "\n";
		extract($data);
		
		include(ABSPATH . 'fm-modules/' . $module_name . '/variables.inc.php');

		/** Check account key */
		$account_status = $this->verifyAccount($AUTHKEY);
		if ($account_status !== true) return $account_status;
		
		/** Check serial number */
		if (isset($data['SERIALNO'])) {
			basicGet('fm_' . $__FM_CONFIG[$module_name]['prefix'] . 'servers', sanitize($SERIALNO), 'server_', 'server_serial_no', "AND server_installed='yes'", getAccountID($AUTHKEY));
			if (!$fmdb->num_rows) return _('Server is not found.') . "\n";
		}
		
		return _('Success');
	}
	
	/**
	 * Verifies account
	 *
	 * @since 1.0
	 * @package facileManager
	 */
	function verifyAccount($AUTHKEY) {
		global $fmdb;
		
		if (!isset($AUTHKEY)) return _('Account is not found.') . "\n";

		$query = "SELECT * FROM fm_accounts WHERE account_key='" . sanitize($AUTHKEY) . "'";
		$result = $fmdb->get_results($query);
		if (!$fmdb->num_rows) return _('Account is not found.') . "\n";
		
		$acct_results = $fmdb->last_result;
		/** Ensure account is active */
		if ($acct_results[0]->account_status != 'active') return printf(_('Account is %s.') . "\n", $acct_results[0]->account_status);
		
		session_start();
		$_SESSION['user']['account_id'] = $acct_results[0]->account_id;
		session_write_close();
		
		return true;
	}

}

if (!isset($fm_accounts))
	$fm_accounts = new fm_accounts();

?>
