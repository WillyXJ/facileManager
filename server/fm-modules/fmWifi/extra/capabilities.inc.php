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
 | fmWifi: Easily manage one or more access points                         |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmwifi/                            |
 +-------------------------------------------------------------------------+
*/

/** Define any module-specific user capabilities */

$fm_user_caps['fmWifi'] = array(
		'view_all'				=> _('View All'),
		'manage_servers'		=> _('Server Management'),
		'build_server_configs'	=> _('Build Server Configs'),
		'manage_wlans'			=> __('Manage WLANs'),
		'manage_wlan_users'		=> __('Manage WLAN Users'),
		'manage_settings'		=> _('Manage Settings')
	);

