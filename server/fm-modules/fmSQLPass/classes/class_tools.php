<?php

class fm_module_tools {
	
	/**
	 * Tests server connectivity
	 */
	function connectTests() {
		global $fmdb, $__FM_CONFIG;
		
		$return = null;
		
		/** Get server list */
		$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_name', 'server_');
		
		if (!$fmdb->num_rows) return 'There are no servers defined.';
		
		/** Process server list */
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;
		for ($x=0; $x<$num_rows; $x++) {
			$return .= 'Running tests for ' . $results[$x]->server_name . "\n";
			
			/** ping tests */
			$return .= "\tPing:\t\t\t";
			if (pingTest($results[$x]->server_name)) $return .=  'success';
			else $return .=  'failed';
			$return .=  "\n";

			/** SQL tests */
			$return .= "\t" . $results[$x]->server_type . ":\t\t\t";
			switch ($results[$x]->server_type) {
				case 'MySQL':
					$port = 53;
					break;
				case 'Postgre':
					$port = 5432;
					break;
				case 'SQL Server':
					$port = 1433;
					break;
			}
			if (socketTest($results[$x]->server_name, $port, 10)) $return .=  'success (tcp/' . $port . ')';
			else $return .=  'failed (tcp/' . $port . ')';
			$return .=  "\n\n";
		}
		
		return $return;
	}
	
}

if (!isset($fm_module_tools))
	$fm_module_tools = new fm_module_tools();

?>