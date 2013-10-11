<?php

class fm_module_settings {

	/**
	 * Saves the options
	 */
	function save() {
		global $fmdb, $__FM_CONFIG;
		
		$exclude = array('save', 'item_type');
		
		$log_message = $log_message_head = "Set options to the following:\n";
		$new_array = null;
		$result = false;
		
		foreach ($_POST as $key => $data) {
			if (!in_array($key, $exclude)) {
				$data = trim($data);

				/** Ensure regions are not empty */
				if ($key == 'regions' && !strlen($data)) {
					$data = 'default';
				}
				
				/** Ensure non-empty option */
				if (empty($data)) return 'Empty values are not allowed.';
				
				/** Check if the option has changed */
				$current_value = getOption($key, $_SESSION['user']['account_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'options');
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
				} else $option_value = sanitize(trim($option_value));
				
				/** Update with the new value */
				$result = setOption($option, $option_value, $command, $_SESSION['user']['account_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'options');
	
				if (!$result) {
					if ($log_message != $log_message_head) addLogEntry($log_message);
					return 'These settings could not be saved because of a database error.';
				}
		
				if (is_array($option_value)) {
					$log_value = null;
					foreach ($option_value as $line) {
						if (trim($line)) {
							$log_value .= "$line, ";
						}
					}
					$log_value = rtrim(trim($log_value), ',');
				} elseif (strpos(strtolower($option), 'password')) {
					$log_value = str_repeat('*', 8);
				} else {
					$log_value = trim($option_value);
				}
				$log_message .= ucwords(str_replace('_', ' ', $option)) . ": $log_value\n";
			}
		}
		
		if ($log_message != $log_message_head) addLogEntry($log_message);
		return true;
	}
	
	
	/**
	 * Displays the form to modify options
	 */
	function printForm() {
		global $fmdb, $__FM_CONFIG, $allowed_to_manage_module_settings;
		
		$disabled = $allowed_to_manage_module_settings ? null : 'disabled';
		
		$save_button = $disabled ? null : '<input type="submit" name="save" id="save_module_settings" value="Save" class="button" />';
		
		$query = "SELECT * FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}options WHERE account_id={$_SESSION['user']['account_id']}";
		$fmdb->get_results($query);
		
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$saved_options[$result[$i]->option_name] = unserialize($result[$i]->option_value);
			}
		} else {
			$saved_options = array();
		}
		
		if (!$option_rows = @buildSettingsForm($saved_options, $__FM_CONFIG[$_SESSION['module']]['default']['options'])) return '<p>There are no settings for this module.</p>';
		
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

?>
