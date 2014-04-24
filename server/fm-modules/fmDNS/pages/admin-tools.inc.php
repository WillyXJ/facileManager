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
 | fmDNS: Easily manage one or more ISC BIND servers                       |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdns/                             |
 +-------------------------------------------------------------------------+
 | Includes module-specific tools                                          |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

$module_tools_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class_tools.php';
if (file_exists($module_tools_file) && !class_exists('fm_module_tools')) {
	include($module_tools_file);
}
include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_zones.php');
$available_zones = $fm_dns_zones->availableZones(true, 'master', true);
$button = null;
if ($available_zones) {
	$zone_options = buildSelect('domain_id', 1, $available_zones);
	if (currentUserCan('run_tools') && currentUserCan('manage_records', $_SESSION['module'])) {
		$button = '<p class="step"><input id="import-records" name="submit" type="submit" value="Import Records" class="button" /></p>';
	}
} else {
	$zone_options = 'You need to define one or more zones first.';
}

$tools_option[] = <<<HTML
			<h2>Import Zone Files</h2>
			<p>Import records from BIND-compatible zone files.</p>
			<table class="form-table">
				<tr>
					<th>File to import:</th>
					<td><input id="import-file" name="import-file" type="file" $disabled /></td>
				</tr>
				<tr>
					<th>Zone to import to:</th>
					<td>
						$zone_options
					</td>
			</table>
			$button
			<br />
HTML;

if (array_key_exists('submit', $_POST)) {
	switch($_POST['submit']) {
		case 'Import Records':
			if (!empty($_FILES['import-file']['tmp_name'])) {
				$block_style = 'style="display: block;"';
				$output = $fm_module_tools->zoneImportWizard();
			}
			break;
	}
}

?>
