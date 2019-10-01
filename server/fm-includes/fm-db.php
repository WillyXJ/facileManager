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

class fmdb {
	/**
	 * Class based on wpdb from Wordpress
	 */

	/**
	 * The last error during query.
	 *
	 * @since 1.0
	 * @access public
	 * @var string
	 */
	public $last_error = null;

	/**
	 * Count of rows returned by previous query
	 *
	 * @since 1.0
	 * @access public
	 * @var int
	 */
	public $num_rows = 0;

	/**
	 * Count of affected rows by previous query
	 *
	 * @since 1.0
	 * @access private
	 * @var int
	 */
	var $rows_affected = 0;

	/**
	 * The ID generated for an AUTO_INCREMENT column by the previous query (usually INSERT).
	 *
	 * @since 1.0
	 * @access public
	 * @var int
	 */
	public $insert_id = 0;

	/**
	 * Last query made
	 *
	 * @since 1.0
	 * @access private
	 * @var array
	 */
	var $last_query;

	/**
	 * Results of the last query made
	 *
	 * @since 1.0
	 * @access private
	 * @var array|null
	 */
	var $last_result;

	/**
	 * MySQL result, which is either a resource or boolean.
	 *
	 * @since 1.0
	 * @access protected
	 * @var mixed
	 */
	public $result;

	/**
	 * Whether MySQL errors occurred or not.
	 *
	 * @since 1.0
	 * @access public
	 * @var boolean
	 */
	public $sql_errors = false;

	/**
	 * Database Handle
	 *
	 * @since 1.0
	 * @access protected
	 * @var string
	 */
	public $dbh;

	/**
	 * Whether to use mysqli over mysql.
	 *
	 * @since 3.0
	 * @access private
	 * @var bool
	 */
	public $use_mysqli = false;


	/**
	 * Connects to the database server and selects a database
	 * Class based on wpdb from Wordpress
	 * 
	 * @param string $dbuser
	 * @param string $dbpassword
	 * @param string $dbname
	 * @param string $dbhost
	 */
	function __construct($dbuser, $dbpassword, $dbname, $dbhost, $connect_options = 'full check') {
		/* Use ext/mysqli if it exists and:
		 *  - We are a development version of facileManager, or
		 *  - We are running PHP 5.5 or greater, or
		 *  - ext/mysql is not loaded.
		 */
		$this->use_mysqli = useMySQLi();
		
		$this->connect($dbuser, $dbpassword, $dbname, $dbhost, $connect_options);
	}

	private function connect($dbuser, $dbpassword, $dbname, $dbhost, $connect_options = 'full check') {
		global $__FM_CONFIG;
		
		if ($this->use_mysqli) {
			$this->dbh = @mysqli_connect($dbhost, $dbuser, $dbpassword);
		} else {
			$this->dbh = @mysql_connect($dbhost, $dbuser, $dbpassword);
		}
		
		if (!$this->dbh) {
			if ($connect_options == 'silent connect') {
				return false;
			}
			bailOut(_('The connection to the database has failed. Please check the configuration.'), 'try again', _('Database Connection'));
		}

		if ($connect_options == 'full check') {
			$this->select($dbname);
			if (!$this->query("SELECT * FROM `fm_options`")) {
				bailOut(_('The database is installed; however, the associated application tables are missing. Click \'Start Setup\' to start the installation process.') . '<p class="step"><a href="' . $GLOBALS['RELPATH'] . 'fm-install.php" class="button click_once">' . _('Start Setup') . '</a></p>', 'no button');
			}

			/** Check if there is an admin account */
			$this->query("SELECT * FROM `fm_users` WHERE user_auth_type=1 ORDER BY user_id ASC LIMIT 1");
			if (!$this->num_rows) {
				bailOut(_('The database is installed; however, an administrative account was not created. Click \'Continue Setup\' to continue the installation process.') . '<p class="step"><a href="' . $GLOBALS['RELPATH'] . 'fm-install.php?step=4" class="button">' . _('Continue Setup') . '</a></p>', 'no button');
			}
		}
		
		return true;
	}

	/**
	 * Selects a database using the current class's $this->dbh
	 * @param string $db name
	 */
	public function select($db, $verbose = 'verbose') {
		global $__FM_CONFIG;
		
		if ($this->use_mysqli) {
			$success = mysqli_select_db($this->dbh, $db);
		} else {
			$success = mysql_select_db($db, $this->dbh);
		}
		
		if ($verbose == 'verbose') {
			if (!$success) {
				bailOut(_("The database is not installed. Click 'Start Setup' to start the installation process.") . '<p class="step"><a href="' . $GLOBALS['RELPATH'] . 'fm-install.php" class="button click_once">' . _('Start Setup') . '</a></p>', 'no button');
			}
		}
		
		return $success;
	}
	
	/**
	 * Perform the mysql query
	 */
	public function query($query) {
		$this->sql_errors = false;
		$this->last_error = null;
		if (strpos($query, 'show_errors') === false) {
			$this->last_query = $query;
		}
		
		if ($this->use_mysqli) {
			$this->result = @mysqli_query($this->dbh, $query);
		} else {
			$this->result = @mysql_query($query, $this->dbh);
		}
		
		// If there is an error then take note of it..
		if ($this->use_mysqli) {
			$this->last_error = mysqli_error($this->dbh);
		} else {
			$this->last_error = mysql_error($this->dbh);
		}
		if ($this->last_error) {
			$this->print_error($query);
			$this->sql_errors = true;
			return false;
		}
		
		if (preg_match("/^\\s*(insert|delete|update|replace) /i",$query)) {
			if ($this->use_mysqli) {
				$this->rows_affected = mysqli_affected_rows($this->dbh);
			} else {
				$this->rows_affected = mysql_affected_rows($this->dbh);
			}
			// Take note of the insert_id
			if (preg_match("/^\\s*(insert|replace) /i",$query)) {
				if ($this->use_mysqli) {
					$this->insert_id = mysqli_insert_id($this->dbh);
				} else {
					$this->insert_id = mysql_insert_id($this->dbh);
				}
			}
			// Return number of rows affected
			$return_val = $this->rows_affected;
		} else {
			$num_rows = 0;
			unset($this->last_result);
			if ($this->use_mysqli && $this->result instanceof mysqli_result) {
				while ($row = mysqli_fetch_object($this->result)) {
					$this->last_result[$num_rows] = $row;
					$num_rows++;
				}
			} elseif (is_resource($this->result)) {
				while ($row = mysql_fetch_object($this->result)) {
					$this->last_result[$num_rows] = $row;
					$num_rows++;
				}
			}

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
	public function get_results($query = null) {
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
	private function print_error($query = '') {
		$str = $this->last_error . ' | ' . _('Query') . ": [$query]";
		$str = htmlspecialchars($str, ENT_QUOTES);

		// Is error output turned on or not..
		if (defined('INSTALL') || defined('UPGRADE')) {
			if ($query) {
				$this->last_error = $str;
			}
		} elseif (getOption('show_errors')) {
			// If there is an error then take note of it
			if ($query) {
				$this->last_error = "<div id='error'>
				<p><strong>" . _('Database error') . ":</strong> [$str]</p>
				</div>";
			}
		} else {
			return false;
		}
	}

}


?>