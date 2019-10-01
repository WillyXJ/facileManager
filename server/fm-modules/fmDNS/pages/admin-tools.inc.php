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
 | fmDNS: Easily manage one or more ISC BIND servers                       |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdns/                             |
 +-------------------------------------------------------------------------+
 | Includes module-specific tools                                          |
 +-------------------------------------------------------------------------+
*/

$module_tools_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class_tools.php';
if (file_exists($module_tools_file) && !class_exists('fm_module_tools')) {
	include($module_tools_file);
}
include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_zones.php');

$selected_zone = 0;

/** Process ad-hoc zone creations and record imports */
if (array_key_exists('submit', $_POST)) {
	switch($_POST['submit']) {
		case __('Import Records'):
		case __('Import Zones'):
			if (!empty($_FILES['import-file']['tmp_name'])) {
				$block_style = 'style="display: block;"';
				$output = ($_POST['submit'] == __('Import Records')) ? $fm_module_tools->zoneImportWizard(sanitize($_POST['domain_id'])) : $fm_module_tools->bulkZoneImportWizard();
				if (strpos($output, 'You do not have permission') === false) {
					$classes = 'wide';
				}
			}
			break;
		case __('Save'):
			if (currentUserCan('manage_zones', $_SESSION['module'])) {
				$insert_id = $fm_dns_zones->add($_POST);
				if (!is_numeric($insert_id)) {
					$response = $insert_id;
				} else {
					$selected_zone = $insert_id;
				}
			}
			break;
	}
}

$available_zones = array_reverse($fm_dns_zones->availableZones('all', 'master', 'restricted'));
$available_zones[] = array(null, null);
$available_zones = array_reverse($available_zones);
$button = null;
if ($available_zones) {
	$zone_options = buildSelect('domain_id', 'zone_import_domain_list', $available_zones, $selected_zone);
	if (currentUserCan('run_tools') && currentUserCan('manage_records', $_SESSION['module'])) {
		$button = '<p class="step"><input id="import-records" name="submit" type="submit" value="' . __('Import Zones') . '" class="button text-change" /></p>';
	}
} else {
	$zone_options = __('You need to define one or more zones first.');
}

$tools_option[] = sprintf('<div id="admin-tools-select">
			<h2>%s</h2>
			<p>%s</p>
			<table class="form-table">
				<tr>
					<th>%s:</th>
					<td><input id="import-file" name="import-file" type="file" /></td>
				</tr>
				<tr>
					<th>%s:</th>
					<td>
						%s<br />
						<p id="table_edits" name="domains"><a id="plus" href="#" title="%s" name="forward">+ %s</a></p>
					</td>
				</tr>
			</table>
			%s
			</div>
			<br />', __('Import Zone Files'), __('Import records from a BIND-compatible zone file into a single zone or import all views, zones, and records from a BIND-compatible zone dump file.') . ' <code>(rndc dumpdb -zones)</code>',
				__('File to import'), __('Zone to import to'), $zone_options, __('Add New'), __('Add New Zone'), $button);

$button = null;
if (currentUserCan('run_tools') && currentUserCan('manage_servers', $_SESSION['module'])) {
	$button = sprintf('<p class="step"><input id="dump-cache" name="submit" type="submit" value="%s" class="button" /> '
			. '<input id="clear-cache" name="submit" type="submit" value="%s" class="button" /></p>', __('Dump Cache'), __('Clear Cache'));
}

$name_servers = buildSelect('domain_name_servers', 'domain_name_servers', availableServers('id'), 0, 5, null, true, null, null, __('Select one or more servers'));

$tools_option[] = sprintf('<div id="admin-tools-select">
			<h2>%s</h2>
			<p>%s</p>
			%s
			%s
			</div>
			<br />', __('Cache Management'), __('Dump or clear server cache.'), $name_servers, $button);

?>
