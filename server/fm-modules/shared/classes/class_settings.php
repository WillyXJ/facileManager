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

class fm_module_settings {

	/**
	 * Saves the options
	 */
	function save() {
		global $fmdb, $__FM_CONFIG;
		
		if (!currentUserCan('manage_settings', $_SESSION['module'])) return _('You do not have permission to make these changes.');
		
		$exclude = array('save', 'item_type');
		
		$log_message = $log_message_head = _('Set options to the following:') . "\n";
		$new_array = array();
		$result = false;
		
		foreach ($_POST as $key => $data) {
			if (!in_array($key, $exclude)) {
				$data = trim($data);

				/** Ensure regions are not empty */
				if ($key == 'regions' && !strlen($data)) {
					$data = 'default';
				}
				
				/** Ensure non-empty option */
				// if (empty($data)) return _('Empty values are not allowed.');
				if (empty($data) && array_key_exists('default_value', $__FM_CONFIG[$_SESSION['module']]['default']['options'][$key])) {
					$data = $__FM_CONFIG[$_SESSION['module']]['default']['options'][$key]['default_value'];
				}
				
				/** Check if the option has changed */
				$current_value = getOption($key, $_SESSION['user']['account_id'], $_SESSION['module']);
				if (is_array($current_value)) $current_value = implode("\n", $current_value);
				if ($current_value == $data) continue;
				
				$new_array[$key] = ($current_value === false) ? array($data, 'insert') : array($data, 'update');
			}
		}
		
		if (is_array($new_array)) {
			foreach ($new_array as $option => $value) {
				list($option_value, $command) = $value;
				if (is_array($__FM_CONFIG[$_SESSION['module']]['default']['options'][$option]['default_value'])) {
					$temp_array = explode("\n", $option_value);
					$temp_value = array();
					foreach ($temp_array as $line) {
						if (trim($line)) {
							$temp_value[] = trim($line);
						}
					}
					$option_value = $temp_value;
				} elseif (!is_null($option_value)) {
					$option_value = trim($option_value);
				}
				
				/** Update with the new value */
				$result = setOption($option, $option_value, $command, true, $_SESSION['user']['account_id'], $_SESSION['module']);
	
				if (!$result) {
					if ($log_message != $log_message_head) addLogEntry($log_message);
					return _('These settings could not be saved because of a database error.');
				}
		
				if (is_array($option_value)) {
					$log_value = '';
					foreach ($option_value as $line) {
						if (trim($line)) {
							$log_value .= "$line, ";
						}
					}
					$log_value = rtrim(trim($log_value), ',');
				} elseif (strpos(strtolower($option), 'password')) {
					$log_value = str_repeat('*', 8);
				} else {
					$log_value = (!is_null($option_value)) ? trim($option_value) : $option_value;
				}
				$log_message .= ucwords(str_replace('_', ' ', $option)) . ": $log_value\n";

				/** Run optional function after save */
				if (array_key_exists('function', $__FM_CONFIG[$_SESSION['module']]['default']['options'][$key])) {
					if (function_exists($__FM_CONFIG[$_SESSION['module']]['default']['options'][$key]['function'])) {
						$__FM_CONFIG[$_SESSION['module']]['default']['options'][$key]['function']();
					}
				}
			}
		}
		
		if ($log_message != $log_message_head) addLogEntry($log_message);
		return true;
	}
	
	
	/**
	 * Displays the form to modify options
	 */
	function printForm() {
		global $fmdb, $__FM_CONFIG;
		
		$save_button = currentUserCan('manage_settings', $_SESSION['module']) ? sprintf('<input type="submit" name="save" id="save_module_settings" value="%s" class="button primary" />', _('Save')) : null;
		
		$query = "SELECT * FROM fm_options WHERE account_id={$_SESSION['user']['account_id']} AND module_name='{$_SESSION['module']}'";
		$result = $fmdb->get_results($query);
		
		if ($fmdb->num_rows) {
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$saved_options[$result[$i]->option_name] = unserialize($result[$i]->option_value);
			}
		} else {
			$saved_options = array();
		}
		
		if (!$option_rows = @buildSettingsForm($saved_options, $__FM_CONFIG[$_SESSION['module']]['default']['options'])) return sprintf('<p>%s</p>', _('There are no settings for this module.'));
		
		$return_form = <<<FORM
		<form name="manage" id="manage" method="post" action="{$GLOBALS['basename']}">
			<input type="hidden" name="item_type" value="module_settings" />
			<div id="settings">
			$option_rows
			$save_button
			</div>
		</form>

FORM;

		return $return_form;
	}

}

if (!isset($fm_module_settings))
	$fm_module_settings = new fm_module_settings();
