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

class fmdb {
	
	/**
	 * Connects to the database server and selects a database
	 * @param string $dbuser
	 * @param string $dbpassword
	 * @param string $dbname
	 * @param string $dbhost
	 */
	function fmdb($dbuser, $dbpassword, $dbname, $dbhost) {
		$this->sql_errors = false;
		$this->last_error = null;
		return $this->connect($dbuser, $dbpassword, $dbname, $dbhost);
	}

	function connect($dbuser, $dbpassword, $dbname, $dbhost) {
		global $__FM_CONFIG;
		$this->dbh = @mysql_connect($dbhost, $dbuser, $dbpassword);
		if (!$this->dbh) {
			bailOut('<center>The connection to the database has failed.  Please check the configuration.</center><p class="step"><a href="' . $_SERVER['PHP_SELF'] . '" class="button">Try Again</a></p>');
		}

		$this->select($dbname);
		if (!@mysql_query("SELECT * FROM `fm_options`", $this->dbh)) {
			bailOut('<center>The database is installed; however, the associated application tables are missing.  Click \'Start Setup\' to start the installation process.<center><p class="step"><a href="' . $GLOBALS['RELPATH'] . 'fm-install.php" class="button click_once">Start Setup</a></p>');
		}
		
		/** Check if there is an admin account */
		$this->query("SELECT * FROM `fm_users` WHERE `user_id`=1");
		if (!$this->num_rows) {
			bailOut('<center>The database is installed; however, an administrative account was not created.  Click \'Continue Setup\' to continue the installation process.<center><p class="step"><a href="' . $GLOBALS['RELPATH'] . 'fm-install.php?step=4" class="button">Continue Setup</a></p>');
		}
	}

	/**
	 * Selects a database using the current class's $this->dbh
	 * @param string $db name
	 */
	function select($db) {
		global $__FM_CONFIG;
		if (!@mysql_select_db($db, $this->dbh)) {
			bailOut('<center>The database is not installed.  Click \'Start Setup\' to start the installation process.<center><p class="step"><a href="' . $GLOBALS['RELPATH'] . 'fm-install.php" class="button click_once">Start Setup</a></p>');
		}
	}
	
	/**
	 * Perform the mysql query
	 */
	function query($query) {
		$this->result = @mysql_query($query, $this->dbh);
		
		// If there is an error then take note of it..
		if (mysql_error($this->dbh)) {
			$this->print_error($query);
			$this->sql_errors = true;
			return false;
		}
		
		if (preg_match("/^\\s*(insert|delete|update|replace) /i",$query)) {
			$this->rows_affected = mysql_affected_rows($this->dbh);
			// Take note of the insert_id
			if (preg_match("/^\\s*(insert|replace) /i",$query)) {
				$this->insert_id = mysql_insert_id($this->dbh);
			}
			// Return number of rows affected
			$return_val = $this->rows_affected;
		} else {
			$i = 0;
			while ($i < @mysql_num_fields($this->result)) {
				$this->col_info[$i] = @mysql_fetch_field($this->result);
				$i++;
			}
			$num_rows = 0;
			while ($row = @mysql_fetch_object($this->result)) {
				$this->last_result[$num_rows] = $row;
				$num_rows++;
			}

			@mysql_free_result($this->result);

			// Log number of rows the query returned
			$this->num_rows = $num_rows;

			// Return number of rows selected
			$return_val = $this->num_rows;
		}

		return $return_val;
	}	 
	
	/**
	 * Return an entire result set from the database
	 */
	function get_results($query = null) {
		if ($query)
			$this->query($query);
		else
			return null;

		if (!isset ($this->last_result))
			return null;
			
		return $this->last_result;
	}

	/**
	 * Print SQL/DB error.
	 */
	function print_error($query = '') {
		$this->last_error = mysql_error($this->dbh);
		if ($query) $str = "{$this->last_error} | Query: [$query]";
		$str = htmlspecialchars($str, ENT_QUOTES);

		// Is error output turned on or not..
		if (getOption('show_errors')) {
			// If there is an error then take note of it
			print "<div id='error'>
			<p class='wpdberror'><strong>Database error:</strong> [$str]</p>
			</div>";
		} else {
			return false;
		}
	}

}


if (!isset($fmdb))
	$fmdb = new fmdb($__FM_CONFIG['db']['user'], $__FM_CONFIG['db']['pass'], $__FM_CONFIG['db']['name'], $__FM_CONFIG['db']['host']);

?>