<?php

class fm_accounts {
	
	function verify($data) {
		global $fmdb, $__FM_CONFIG;
		
		if (!isset($data['AUTHKEY'])) return "Account is not found.\n";
		extract($data);
		
		include(ABSPATH . 'fm-modules/' . $module_name . '/variables.inc.php');

		/** Check account key */
		$account_status = $this->verifyAccount($AUTHKEY);
		if ($account_status !== true) return $account_status;
		
		/** Check serial number */
		if (isset($data['SERIALNO'])) {
			basicGet('fm_' . $__FM_CONFIG[$module_name]['prefix'] . 'servers', sanitize($SERIALNO), 'server_', 'server_serial_no', null, getAccountID($AUTHKEY));
			if (!$fmdb->num_rows) return "Server is not found.\n";
		}
		
		return 'Success';
	}
	
	function verifyAccount($AUTHKEY) {
		global $fmdb;
		
		if (!isset($AUTHKEY)) return "Account is not found.\n";

		$query = "select * from fm_accounts where account_key='" . sanitize($AUTHKEY) . "'";
		$result = $fmdb->get_results($query);
		if (!$fmdb->num_rows) return "Account is not found.\n";
		
		$acct_results = $fmdb->last_result;
		/** Ensure account is active */
		if ($acct_results[0]->account_status != 'active') return 'Account is ' . $acct_results[0]->account_status . ".\n";
		
		$_SESSION['user']['account_id'] = $acct_results[0]->account_id;
		
		return true;
	}

}

if (!isset($fm_accounts))
	$fm_accounts = new fm_accounts();

?>
