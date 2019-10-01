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
 | fmSQLPass: Change database user passwords across multiple servers.      |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmsqlpass/                         |
 +-------------------------------------------------------------------------+
*/

class fm_module_tools {
	
	/**
	 * Tests server connectivity
	 */
	function connectTests() {
		global $fmdb, $__FM_CONFIG;
		
		$return = null;
		
		/** Get server list */
		$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_name', 'server_');
		
		if (!$fmdb->num_rows) return __('There are no servers defined.');
		
		/** Process server list */
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;
		for ($x=0; $x<$num_rows; $x++) {
			$return .= sprintf(__("Running tests for %s\n"), $results[$x]->server_name);
			
			/** ping tests */
			$return .= "\t" . str_pad(__('Ping:'), 15);
			if (pingTest($results[$x]->server_name)) $return .=  _('success');
			else $return .=  _('failed');
			$return .=  "\n";

			/** SQL tests */
			$return .= "\t" . str_pad($results[$x]->server_type. ':', 15);
			$port = $results[$x]->server_port ? $results[$x]->server_port : $__FM_CONFIG['fmSQLPass']['default']['ports'][$results[$x]->server_type];

			if (socketTest($results[$x]->server_name, $port, 10)) $return .=  _('success') . ' (tcp/' . $port . ')';
			else $return .=  _('failed') . ' (tcp/' . $port . ')';
			$return .=  "\n\n";
		}
		
		return $return;
	}
	
}

if (!isset($fm_module_tools))
	$fm_module_tools = new fm_module_tools();

?>