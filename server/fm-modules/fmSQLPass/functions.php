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

/**
 * Checks the app functionality
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmSQLPass
 */
function moduleFunctionalCheck() {
	global $fmdb, $__FM_CONFIG;
	$html_checks = null;
	$checks = array();
	
	/** Count active database servers */
	$checks[] = (basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_', 'active')) ? null : sprintf(__('<p>You currently have no active database servers defined. <a href="%s">Click here</a> to define one or more to manage.</p>'), getMenuURL(_('Servers')));
	
	/** Count groups */
	$checks[] = (basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'groups', 'group_id', 'group_')) ? null : sprintf(__('<p>You currently have no database server groups defined. <a href="%s">Click here</a> to define one or more.</p>'), getMenuURL(__('Server Groups')));
	
	foreach ($checks as $val) {
		$html_checks .= $val;
	}
	
	return $html_checks;
}

/**
 * Builds the dashboard for display
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmSQLPass
 */
function buildModuleDashboard() {
	global $fmdb, $__FM_CONFIG;

	/** Server stats */
	basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_');
	$server_count = $fmdb->num_rows;
	
	/** Group stats */
	basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'groups', 'group_id', 'group_');
	$group_count = $fmdb->num_rows;
	
	$dashboard = sprintf('<div class="fm-half">
	<div id="shadow_box">
		<div id="shadow_container">
		<h3>%s</h3>
		<li>%s</li>
		<li>%s</li>
		</div>
	</div>
	</div>', __('Summary'),
			sprintf(ngettext('You have <b>%s</b> database server configured.', 'You have <b>%s</b> database servers configured.', $server_count), formatNumber($server_count)),
			sprintf(ngettext('You have <b>%s</b> group defined.', 'You have <b>%s</b> groups defined.', $group_count), formatNumber($group_count))
			);


	return $dashboard;
}


/**
 * Builds the help for display
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmSQLPass
 */
function buildModuleHelpFile() {
	global $__FM_CONFIG;
	
	$body = <<<HTML
<h3>{$_SESSION['module']}</h3>
<ul>
	<li>
		<a class="list_title">Configure Servers</a>
		<div>
			<p>Database servers can be managed from the Config &rarr; <a href="__menu{Servers}">Servers</a> menu item. From 
			there you can add, edit {$__FM_CONFIG['icons']['edit']}, and delete {$__FM_CONFIG['icons']['delete']} 
			servers depending on your user permissions.</p>
			<p><i>The 'Server Management' or 'Super Admin' permission is required to add, edit, and delete servers.</i></p>
			<p>Select the database server type from the list and associate the server with a group. You can also override the user credentials 
			{$_SESSION['module']} should use for this server. If the credentials are left blank, {$_SESSION['module']} will use the credentials 
			defined in the module settings.</p>
			<br />
		</div>
	</li>
	<li>
		<a class="list_title">Configure Server Groups</a>
		<div>
			<p>Server groups are used so you can change the user passwords on a subset of servers rather than all (or selecting individual servers on
			each run). An example would be to create a group for each data center hosting your servers so you can change the password for all database
			servers within that data center.</p>
			<p>Database server groups can be managed from the Config &rarr; <a href="__menu{Server Groups}">Server Groups</a> 
			menu item. From there you can add, edit {$__FM_CONFIG['icons']['edit']}, and delete 
			{$__FM_CONFIG['icons']['delete']} servers depending on your user permissions.</p>
			<p><i>The 'Server Management' or 'Super Admin' permission is required to add, edit, and delete server groups.</i></p>
			<br />
		</div>
	</li>
	<li>
		<a class="list_title">Set Passwords</a>
		<div>
			<p>Database user passwords can be updated from the Config &rarr; <a href="__menu{Passwords}">Passwords</a> 
			menu item. From there you can select the server groups, enter the username to change the password for, and enter the new password.</p>
			<p><i>The 'Password Management' or 'Super Admin' permission is required to update database user passwords.</i></p>
			<p>You can enter the username as "<code>username</code>" which will change the password for all users matching that string. If you want to 
			change the password for "<code>username@localhost</code>" only and leave the password in tact for "<code>username@%</code>" then you can 
			enter the username as "<code>username@localhost</code>"</p>
			<br />
		</div>
	</li>
	<li>
		<a class="list_title">Module Settings</a>
		<div>
			<p>Settings for {$_SESSION['module']} can be updated from the <a href="__menu{{$_SESSION['module']} Settings}">Settings</a> 
			menu item.</p>
			<br />
		</div>
	</li>
	
HTML;
	
	return $body;
}


/**
 * Returns an option value
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmSQLPass
 */
function getServerCredentials($account_id = 0, $server_serial_no) {
	global $fmdb, $__FM_CONFIG;
	
	$query = "SELECT * FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}servers WHERE server_serial_no=$server_serial_no AND account_id=$account_id";
	$results = $fmdb->get_results($query);
	
	if ($fmdb->num_rows) {
		if (isSerialized($results[0]->server_credentials)) {
			return unserialize($results[0]->server_credentials);
		}
		
		return $results[0]->server_credentials;
	}
	
	return false;
}


/**
 * Changes a MySQL user password
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmSQLPass
 *
 * @param string $server_name Hostname of the database server
 * @param integer $server_port Server port to connect to
 * @param string $admin_user User to login with
 * @param string $admin_pass User password to login with
 * @param string $user Database user to change
 * @param string $user_password New password
 * @param string $server_group Server group to process
 * @return string
 */
function changeMySQLUserPassword($server_name, $server_port, $admin_user, $admin_pass, $user, $user_password, $server_group) {
	global $__FM_CONFIG;
	
	/** Connect to remote server */
	$verbose_output = sprintf(' --> %s ', __('Connecting to MySQL '));
	if (!socketTest($server_name, $server_port, 5)) {
		return $verbose_output . sprintf("[%s] - %s\n", __('failed'), sprintf(__('Could not connect to %s on tcp/%s'), $server_name, $server_port));
	}

	$remote_connection = @new mysqli($server_name, $admin_user, $admin_pass, null, $server_port);
	if (!$remote_connection->connect_error) $verbose_output .= sprintf("[%s]\n", __('ok'));
	else {
		return $verbose_output . sprintf('[%s] - ' . $remote_connection->connect_error . "\n", __('failed'));
	}
	
	/** Ensure database user exists before changing the password */
	$verbose_output .= sprintf(' --> %s ', sprintf(__('Verifying user account (%s) exists'), $user));
	list($user_login, $user_host) = explode('@', $user);
	$user_host_query = !empty($user_host) ? "AND Host='$user_host'" : null;
	if ($result = $remote_connection->query("SELECT User FROM mysql.user WHERE User='$user_login' $user_host_query")) {
		if ($result->num_rows) {
			$verbose_output .= sprintf("[%s]\n", __('ok'));
			$verbose_output .= sprintf(' --> %s ', sprintf(__('Updating the password for user account (%s)'), $user));
			$remote_connection->query("UPDATE mysql.user SET Password=PASSWORD('$user_password') WHERE User='$user_login' $user_host_query");
			if ($remote_connection->affected_rows > 0) {
				$verbose_output .= sprintf("[%s]\n", __('ok'));
				
				/** Update last changed */
				basicUpdate('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'groups', $server_group, 'group_pwd_change', time(), 'group_id');
				
				/** Flush privileges */
				$verbose_output = sprintf(' --> %s ', __('Flushing privileges '));
				$remote_connection->query('FLUSH PRIVILEGES');
				$verbose_output .= ($remote_connection->error) ? sprintf('[%s] - ', __('failed')) . $remote_connection->error . "\n" : sprintf("[%s]\n", __('ok'));
				
				/** Log entry */
				addLogEntry(sprintf('%s (%s : %s).'), __('Updated MySQL Account'), $server_name, $user);
			} else {
				$verbose_output .= sprintf('[%s] - ', __('failed'));
				$verbose_output .= ($remote_connection->error) ? $remote_connection->error . "\n" : sprintf(__('Password for user account (%s) was not different.'), $user) . "\n";
			}
		} else {
			$verbose_output .= sprintf("[%s] - %s\n", __('failed'), sprintf(__('User account (%s) does not exist.'), $user));
		}
	} else {
		$verbose_output .= sprintf('[%s] - ', __('failed')) . $remote_connection->error . "\n";
	}
	$remote_connection->close();
	
	return $verbose_output;
}


/**
 * Changes a PostgreSQL user password
 *
 * @since 1.3
 * @package facileManager
 * @subpackage fmSQLPass
 *
 * @param string $server_name Hostname of the database server
 * @param integer $server_port Server port to connect to
 * @param string $admin_user User to login with
 * @param string $admin_pass User password to login with
 * @param string $user Database user to change
 * @param string $user_password New password
 * @param string $server_group Server group to process
 * @return string
 */
function changePostgreSQLUserPassword($server_name, $server_port, $admin_user, $admin_pass, $user, $user_password, $server_group) {
	global $__FM_CONFIG;
	
	/** Connect to remote server */
	$verbose_output = sprintf(' --> %s ', __('Connecting to PostgreSQL '));
	if (!socketTest($server_name, $server_port, 5)) {
		return $verbose_output . sprintf("[%s] - %s\n", __('failed'), sprintf(__('Could not connect to %s on tcp/%s'), $server_name, $server_port));
	}
	
	$remote_connection = pg_connect("host='$server_name' port='$server_port' user='$admin_user' password='$admin_pass' dbname='postgres'");
	if (pg_connection_status($remote_connection) === PGSQL_CONNECTION_OK) $verbose_output .= sprintf("[%s]\n", __('ok'));
	else {
		return $verbose_output . sprintf('[%s] - ' . pg_last_error() . "\n", __('failed'));
	}
	
	/** Ensure database user exists before changing the password */
	$verbose_output .= sprintf(' --> %s ', sprintf(__('Verifying user account (%s) exists'), $user));
	if ($result = pg_query($remote_connection, "SELECT 1 FROM pg_roles WHERE rolname='$user_login'")) {
		if (pg_num_rows($result)) {
			$verbose_output .= sprintf("[%s]\n", __('ok'));
			$verbose_output .= sprintf(' --> %s ', sprintf(__('Updating the password for user account (%s)'), $user));
			pg_query($remote_connection, "ALTER USER $user_login WITH PASSWORD '$user_password' WHERE User='$user'");
			if ($remote_connection->affected_rows > 0) {
				$verbose_output .= sprintf("[%s]\n", __('ok'));
				
				/** Update last changed */
				basicUpdate('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'groups', $server_group, 'group_pwd_change', time(), 'group_id');
				
				/** Log entry */
				addLogEntry(sprintf('%s (%s : %s).'), __('Updated PostgreSQL Account'), $server_name, $user);
			} else {
				$verbose_output .= sprintf('[%s] - ', __('failed'));
				$verbose_output .= (pg_last_error()) ? pg_last_error() . "\n" : sprintf(__('Password for user account (%s) was not different.'), $user) . "\n";
			}
		} else {
			$verbose_output .= sprintf("[%s] - %s\n", __('failed'), sprintf(__('User account (%s) does not exist.'), $user));
		}
	} else {
		$verbose_output .= sprintf('[%s] - ', __('failed')) . pg_last_error() . "\n";
	}
	@pg_close($remote_connection);
	
	return $verbose_output;
}


/**
 * Adds the module menu items
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmSQLPass
 */
function buildModuleMenu() {
	addObjectPage(__('Config'), __('Database Servers'), array('manage_servers', 'view_all'), $_SESSION['module'], 'config-servers.php');
		addSubmenuPage('config-servers.php', _('Servers'), __('Database Servers'), array('manage_servers', 'view_all'), $_SESSION['module'], 'config-servers.php');
		addSubmenuPage('config-servers.php', __('Groups'), __('Server Groups'), array('manage_servers', 'view_all'), $_SESSION['module'], 'config-groups.php');

	addObjectPage(__('Passwords'), __('Passwords'), array('manage_passwords', 'view_all'), $_SESSION['module'], 'config-passwords.php');

	addSettingsPage($_SESSION['module'], sprintf(__('%s Settings'), $_SESSION['module']), array('manage_settings', 'view_all'), $_SESSION['module'], 'module-settings.php');
}


?>