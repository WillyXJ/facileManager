<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) The facileManager Team                                    |
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

if (!currentUserCan('manage_modules')) unAuth();

include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $fm_name . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class_tools.php');

$output = $avail_modules = $response = $update_core = '';
$allow_update_core = true;
$import_output = sprintf('<p>%s <i class="fa fa-spinner fa-spin"></i></p>', _('Processing...'));

if (array_key_exists('action', $_GET) && array_key_exists('module', $_GET)) {
	if (currentUserCan('manage_modules')) {
		if ($_GET['action'] == 'activate') {
			$action_verb = _('activated');
		} elseif ($_GET['action'] == 'deactivate') {
			$action_verb = _('deactivated');
		} elseif ($_GET['action'] == 'uninstall') {
			$action_verb = _('uninstalled');
		}
		if ($fm_tools->manageModule(sanitize($_GET['module']), sanitize($_GET['action']))) {
			$response = sprintf(_('%s was %s'), $_GET['module'], $action_verb);
			addLogEntry($response, $fm_name);
			
			if ($_GET['module'] == $_SESSION['module']) $_SESSION['module'] = $fm_name;
			
			header('Location: ' . getMenuURL(_('Modules')));
			exit;
		} else {
			$response = sprintf(_('Could not %s this module.'), sanitize($_GET['action']));
		}
	} else {
		header('Location: ' . getMenuURL(_('Modules')));
		exit;
	}
}

require(ABSPATH . 'fm-includes/version.php');
$fm_new_version_available = isNewVersionAvailable($fm_name, $fm_version);

if (!empty($fm_new_version_available)) {
	list($fm_temp_directory, $allow_update_core) = clearUpdateDir();
	
	if (!is_writable_r(ABSPATH, 'config.inc.php')) $allow_update_core = false;
	
	extract($fm_new_version_available);

	$buttons = sprintf('<a href="%s" class="button" />%s</a> ', $link, sprintf(_('Download %s'), $version));
	if ($allow_update_core) {
		$buttons .= sprintf('<input type="button" name="update_core" id="update_core" value="%s" class="button primary" />', _('Update Core'));
	} else {
		$response .= sprintf(_('%s and %s need to be writeable by %s in order for the core and modules to be updated automatically.'), $fm_temp_directory, ABSPATH, $__FM_CONFIG['webserver']['user_info']['name']);
	}

	$update_core = sprintf('<div class="upgrade_notice"><p>%s</p></div><p>%s</p><br />', $text, $buttons);
}

printHeader();
@printMenu();

$table_info = array(
				'class' => 'display_results modules',
				'id' => 'table_edits',
				'name' => 'modules'
			);

if (currentUserCan('manage_modules')) {
	$bulk_actions_list = array(_('Activate'), _('Deactivate'), _('Update'));
}
if (count((array) $bulk_actions_list)) {
	$title_array[] = array(
						'title' => '<input type="checkbox" class="tickall" onClick="toggle(this, \'module_list[]\')" />',
						'class' => 'header-tiny header-nosort'
					);
}
$title_array[] = _('Module');
$title_array[] = _('Description');

$header = displayTableHeader($table_info, $title_array);

$modules = getAvailableModules();
if (count($modules)) {
	$module_display = $header;

	foreach ($modules as $module_name) {
		/** Include module variables */
		@include(ABSPATH . 'fm-modules/' . $module_name . '/variables.inc.php');
		
		$activate_link = $upgrade_link = $status_options = '';
		$class = array();
		
		$uninstall_link = sprintf('<a href="?action=uninstall&module=%s"><span class="not_installed" onClick="return del(\'%s\');">%s</span></a>' . "\n", $module_name, _('Are you sure you want to delete this module?'), _('Uninstall'));
		
		/** Get module status */
		$module_version = getOption('version', 0, $module_name);
		if ($module_version !== false) {
			$active_modules = getActiveModules();
			if (in_array($module_name, $active_modules)) {
				$activate_link = sprintf('<a href="?action=deactivate&module=%s">%s</a>' . "\n", $module_name, _('Deactivate'));
				$class[] = 'active';
			}
			if (version_compare($module_version, $__FM_CONFIG[$module_name]['version'], '>=')) {
				if (!in_array($module_name, $active_modules)) {
					if (version_compare($fm_version, $__FM_CONFIG[$module_name]['required_fm_version'], '<')) {
						$activate_link .= sprintf('<p>' . _('%s v%s or later is required.') . '</p>', $fm_name, $__FM_CONFIG[$module_name]['required_fm_version']);
					} else {
						$activate_link = sprintf('<span class="activate_link"><a href="?action=activate&module=%s">%s</a></span>' . "\n", $module_name, _('Activate'));
					}
					$activate_link .= $uninstall_link;
				}
			} else {
				include(ABSPATH . 'fm-includes/version.php');
				if (version_compare($fm_version, $__FM_CONFIG[$module_name]['required_fm_version'], '<')) {
					$upgrade_link .= sprintf('<span class="upgrade_link">' . _('%s v%s or later is required<br />to upgrade this module.') . '</span>', $fm_name, $__FM_CONFIG[$module_name]['required_fm_version']);
				}
				$activate_link = $uninstall_link;
				$class[] = 'upgrade';
			}
			$status_options = $activate_link . "\n";
		} else {
			$module_version = $__FM_CONFIG[$module_name]['version'];
			include(ABSPATH . 'fm-includes/version.php');
			if (version_compare($fm_version, $__FM_CONFIG[$module_name]['required_fm_version'], '>=')) {
				$status_options .= sprintf('<a href="#" id="module_install" name="%s" />%s</a>', $module_name, _('Install Now'));
			} else {
				$status_options .= sprintf(_('%s v%s or later is required.'), $fm_name, $__FM_CONFIG[$module_name]['required_fm_version']);
			}
		}
		
		if ($module_new_version_available = isNewVersionAvailable($module_name, $module_version)) {
			$module_new_version_available = '<div class="upgrade_notice">' . $module_new_version_available['text'] . '</div>';
			$class[] = 'upgrade';
		}
		$class = implode(' ', array_unique($class));
		
		$checkbox = (currentUserCan('manage_modules')) ? '<td><input type="checkbox" name="module_list[]" value="' . $module_name .'" class="modules" /></td>' : null;
		
		$avail_modules .= <<<MODULE
					<tr class="$class">
						$checkbox
						<td><h3>$module_name</h3><div class="module_actions">$status_options</div></td>
						<td><p>{$__FM_CONFIG[$module_name]['description']}</p><p>Version $module_version $upgrade_link</p>
						$module_new_version_available</td>
					</tr>

MODULE;
	}
	
	$module_display .= <<<HTML
					$avail_modules
				</tbody>
			</table>
HTML;
} else {
	$module_display = sprintf(_('<p>There are no modules detected. You must first install the files in %s and then return to this page.</p>') . "\n", '<code>' . ABSPATH . 'fm-modules</code>');
	$module_display .= sprintf(_('<p>If you don\'t have any modules, you can download them from the %smodule directory</a>.</p>') . "\n", '<a href="http://www.facilemanager.com/modules/">');
}

/** Set maintenance mode toggle */
$maintenance_mode = getOption('maintenance_mode');
$maintenance_mode_toggle = '<a class="toggle-maintenance-mode" href="#" rel="';
$maintenance_mode_toggle .= ($maintenance_mode) ? 'disabled' : 'active';
$maintenance_mode_toggle .= '">';
$maintenance_mode_toggle .= ($maintenance_mode) ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
$maintenance_mode_toggle .= '</a>';

printf('
	<div id="admin-tools">
		<form enctype="multipart/form-data" method="post" action="">
			%s
			%s
			%s
			%s
		</form>
	</div>
</div>' . "\n",
		$update_core,
		printPageHeader($response), displayPagination(0, 0, array(buildBulkActionMenu($bulk_actions_list, 'module_list'), sprintf('<b>%s:</b> %s', _('Maintenance Mode'), $maintenance_mode_toggle))),
		$module_display);

printFooter(null, $output);
