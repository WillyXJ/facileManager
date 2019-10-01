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

/**
 * Constructs the menu.
 *
 * The elements in the array are :
 *     0: Menu item name
 *     1: Page title
 *     2: Minimum level or capability required
 *     3: Module the menu item is for
 *     4: The URL of the item's file
 *     5: CSS class
 *     6: Menu title sticky bit
 *     7: Badge count (optional)
 *
 * @global array $menu
 */

$menu[2] = array(_('Dashboard'), _('Dashboard'), null, $fm_name, 'index.php', null, null, true);

$_fm_last_object_menu = 2;

$menu[45] = array(null, null, null, null, null, 'separator');

$menu[50] = array(_('Admin'), null, 'run_tools', $fm_name, 'admin-tools.php');
	$submenu['admin-tools.php'][5] = array(_('Tools'), _('Tools'), 'run_tools', $fm_name, 'admin-tools.php');
	if (!defined('AJAX') && getOption('auth_method')) {
		$submenu['admin-tools.php'][10] = array(_('Users & Groups'), _('Users & Groups'), 'manage_users', $fm_name, 'admin-users.php');
	}
	$submenu['admin-tools.php'][15] = array(_('Logs'), _('Logs'), 'view_logs', $fm_name, 'admin-logs.php');

$menu[70] = array(_('Settings'), _('General Settings'), 'manage_settings', $fm_name, 'admin-settings.php', null, null, true);
	$submenu['admin-settings.php'][5] = array(_('General'), _('General Settings'), 'manage_settings', $fm_name, 'admin-settings.php');

$badge_counts = (!defined('AJAX')) ? getBadgeCounts('modules') + getBadgeCounts('core') : null;
$menu[99] = array(_('Modules'), _('Module Configuration'), 'manage_modules', $fm_name, 'admin-modules.php', null, $badge_counts, true);

?>