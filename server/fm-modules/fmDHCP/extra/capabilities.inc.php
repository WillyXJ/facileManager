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
 | fmDHCP: Easily manage one or more ISC DHCP servers                      |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdhcp/                            |
 +-------------------------------------------------------------------------+
*/

/** Define any module-specific user capabilities */

$fm_user_caps['fmDHCP'] = array(
		'view_all'				=> __('View All'),
		'manage_servers'		=> __('Server Management'),
		'build_server_configs'	=> __('Build Server Configs'),
		'manage_hosts'			=> __('Host Management'),
		'manage_groups'			=> __('Group Management'),
		'manage_pools'			=> __('Pool Management'),
		'manage_networks'		=> __('Network Management'),
		'manage_peers'			=> __('Peer Management'),
		'manage_leases'			=> __('Lease Management'),
		'manage_settings'		=> _('Manage Settings')
	);

