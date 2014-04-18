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
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/                                           |
 +-------------------------------------------------------------------------+
*/

include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . 'facileManager' . DIRECTORY_SEPARATOR . 'variables.inc.php');

/** Include shared classes */
$shared_classes_dir = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'classes';
foreach (scandir($shared_classes_dir) as $file) {
	if (is_file($shared_classes_dir . DIRECTORY_SEPARATOR . $file)) {
		include_once($shared_classes_dir . DIRECTORY_SEPARATOR . $file);
	}
}

/**
 * Includes the template file
 *
 * @since 1.0
 * @package facileManager
 */
function includeModuleFile($module = null, $file = '') {
	global $fm_name;
	if (!$module) $module = $fm_name;
	
	if (!file_exists(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . $file)) {
		$module = (file_exists(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . $file)) ? 'shared' : $fm_name;
	}

	return ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . $file;
}

/**
 * Throws HTTP Error
 *
 * @since 1.0
 * @package facileManager
 */
function throwHTTPError($code = '404') {
	$status_codes = array('404' => 'Not Found');
	
	header( "HTTP/1.0 $code " . $status_codes[$code] );
	include(includeModuleFile(null, $code . '.php'));
}

/**
 * Checks if there's a database upgrade
 *
 * @since 1.0
 * @package facileManager
 */
function isUpgradeAvailable() {
	global $fmdb;
	
	include(ABSPATH . 'fm-includes/version.php');
	
	$running_db_version = getOption('fm_db_version');
	
	/** If the record does not exist then run the installer */
	if (!$running_db_version) {
		header('Location: ' . $GLOBALS['RELPATH'] . 'fm-install.php');
		exit;
	}
	
	if ($running_db_version < $fm_db_version) return true;
	
	return false;
}


/**
 * Checks if there's a new version of facileManager available
 *
 * @since 1.0
 * @package facileManager
 */
function isNewVersionAvailable($package, $version) {
	$fm_site_url = 'http://www.facilemanager.com/check/';
	
	$data['package'] = $package;
	$data['version'] = $version;
	
	$method = 'update';
	
	/** Are software updates enabled? */
	if (!getOption('software_update', 0)) return false;
	
	/** Should we be running this check now? */
	$last_version_check = getOption($package . '_version_check', 0);
	if (!$software_update_interval = getOption('software_update_interval', 0)) $software_update_interval = 'week';
	if (!$last_version_check) {
		$last_version_check['timestamp'] = 0;
		$last_version_check['data'] = null;
		$method = 'insert';
	} elseif (strpos($last_version_check['data'], $version)) {
		$last_version_check['timestamp'] = 0;
	}
	if (strtotime($last_version_check['timestamp']) < strtotime("1 $software_update_interval ago")) {
		$data['software_update_tree'] = getOption('software_update_tree');
		$result = getPostData($fm_site_url, $data);
		
		setOption($package . '_version_check', array('timestamp' => date("Y-m-d H:i:s"), 'data' => $result), $method);
		
		return $result;
	}
	
	return $last_version_check['data'];
}


/**
 * Sanitizes the post
 *
 * @since 1.0
 * @package facileManager
 */
function sanitize($data, $replace = null) {
	if ($replace) {
		$strip_chars = array("'", "\"", "`", "$", "?", "*", "&", "^", "!", "#");
		$replace_chars = array(" ", "\\", "_", "(", ")", ",", ".", "-");

		$data = str_replace($strip_chars, '', $data);
		$data = str_replace($replace_chars, $replace, $data);
		$data = str_replace('--', '-', $data);
		return $data;
	} else return @mysql_real_escape_string($data);
}


/**
 * Prints the header
 *
 * @since 1.0
 * @package facileManager
 */
function printHeader($subtitle = null, $css = 'facileManager', $help = false, $menu = true) {
	global $fm_name, $__FM_CONFIG;
	
	include(ABSPATH . 'fm-includes/version.php');
	
	$title = ($subtitle) ? "$subtitle &lsaquo; " : null;
	
	$head = $logo = null;
	
	if ($css == 'facileManager') {
		$head = $menu ? getTopHeader($help) : null;
	} else {
		$logo = '<h1 id="logo"><img alt="' . $fm_name . '" src="' . $GLOBALS['RELPATH'] . 'fm-includes/images/logo.png" /></h1>' . "\n";
	}
	
	/** Module css and js includes */
	if (isset($_SESSION['module'])) {
		$module_css_file = 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'module.css';
		$module_css = (file_exists(ABSPATH . $module_css_file)) ? '<link rel="stylesheet" href="' . $GLOBALS['RELPATH'] . $module_css_file . '?ver=' . $__FM_CONFIG[$_SESSION['module']]['version'] . '" type="text/css" />' : null;
		$module_js_file = 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'module.js';
		$module_js = (file_exists(ABSPATH . $module_js_file)) ? '<script src="' . $GLOBALS['RELPATH'] . $module_js_file . '?ver=' . $__FM_CONFIG[$_SESSION['module']]['version'] . '" type="text/javascript" charset="utf-8"></script>' : null;
	} else {
		$module_css = $module_js = null;
	}
	
	echo <<<HTML
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title>$title$fm_name</title>
		<link rel="shortcut icon" href="{$GLOBALS['RELPATH']}fm-modules/$fm_name/images/favicon.png" />
		<link rel="stylesheet" href="{$GLOBALS['RELPATH']}fm-modules/$fm_name/css/$css.css?ver=$fm_version" type="text/css" />
		<link rel="stylesheet" href="https://code.jquery.com/ui/1.10.2/themes/smoothness/jquery-ui.css" />
		<link href='http://fonts.googleapis.com/css?family=Open+Sans:300italic,400italic,600italic,400,600,300&ver=$fm_version' rel='stylesheet' type='text/css'>
		<script src="https://code.jquery.com/jquery-1.9.1.js"></script>
		<script src="https://code.jquery.com/ui/1.10.2/jquery-ui.js"></script>
		$module_css
		<script src="{$GLOBALS['RELPATH']}fm-modules/$fm_name/js/$fm_name.js?ver=$fm_version" type="text/javascript" charset="utf-8"></script>
		$module_js
	</head>
<body>
$head
<a href="#" id="scroll-to-top" class=""></a>
HTML;
}

/**
 * Prints the footer
 *
 * @since 1.0
 * @package facileManager
 */
function printFooter($text = null, $block_style = null) {
	echo <<<FOOT
	</div>
<div class="manage_form_container" id="manage_item" $block_style></div>
<div class="manage_form_contents" id="manage_item_contents" $block_style>
$text
</div>
</body></html>
FOOT;
}

/**
 * Prints the top header
 *
 * @since 1.0
 * @package facileManager
 */
function getTopHeader($help) {
	global $fm_login, $__FM_CONFIG, $super_admin;
	include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . 'facileManager' . DIRECTORY_SEPARATOR . 'variables.inc.php');
	include(ABSPATH . 'fm-includes' . DIRECTORY_SEPARATOR . 'version.php');
	
	$module_toolbar = $fm_new_version_available = $account_menu = $user_account_menu = $module_menu = null;
	
	if (!$help) {
		$auth_method = getOption('auth_method');
		if ($auth_method) {
			if ($_SESSION['user']['account_id'] != 1) {
				$account = getNameFromID($_SESSION['user']['account_id'], 'fm_accounts', 'account_', 'account_id', 'account_name');
				$account_menu = <<<HTML
		<div id="topheadpart">
			<span style="line-height: 18pt;">Account:&nbsp;&nbsp; $account</span>
		</div>
HTML;
			}

			$star = $super_admin ? $__FM_CONFIG['icons']['star'] . ' ' : null;
			$change_pwd_link = ($auth_method == 1) ? '<li><a class="account_settings" id="' . $_SESSION['user']['id'] . '" href="#"><span>Change Password</span></a></li>' . "\n" : null;
			$user_account_menu = <<<HTML
		<div id="topheadpartright" style="padding: 0 1px 0 0;">
			<div id="cssmenu">
			<ul>
				<li class="has-sub has-image"><a href="#"><span>{$__FM_CONFIG['icons']['account']}</span></a>
					<ul class="sub-right">
						<li class="text-only"><span>$star{$_SESSION['user']['name']}</span></li>
						$change_pwd_link
						<li class="last"><a href="{$GLOBALS['RELPATH']}?logout"><span>Logout</span></a></li>
					</ul>
				</li>
			</ul>
			</div>
		</div>
HTML;
		}
		
		// Build app dropdown menu
		$modules = getAvailableModules();
		$avail_modules = null;
		
		if (count($modules)) {
			foreach ($modules as $module_name) {
				if ($module_name == $_SESSION['module']) continue;
				if (in_array($module_name, getActiveModules())) {
					$module_perms = $fm_login->getModulePerms($_SESSION['user']['id'], $module_name);
					include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $module_name . DIRECTORY_SEPARATOR . 'permissions.inc.php');
					if ($module_perms & PERM_MODULE_ACCESS_DENIED) continue;
					$avail_modules .= "<li class='last'><a href='{$GLOBALS['RELPATH']}?module=$module_name'><span>$module_name</span></a></li>\n";
				}
			}
			
			if ($avail_modules) {
				$module_menu = <<<HTML
		<div id="topheadpartright" style="padding: 0;">
			<div id="cssmenu">
			<ul>
				<li class="has-sub last"><a href="#"><span>{$_SESSION['module']}</span></a>
					<ul>
					$avail_modules
					</ul>
				</li>
			</ul>
			</div>
		</div>
HTML;
			}
			
			/** Include module toolbar items */
			if (function_exists('buildModuleToolbar')) {
				$module_toolbar = @buildModuleToolbar();
			}
		} else {
			$module_menu = null;
			$fm_name = isset($_SESSION['module']) ? $_SESSION['module'] : $fm_name;
		}
	
		$help_file = buildHelpFile();
		
		$return = <<<HTML
	<div id="tophead">
		<div id="topheadpart">
			<img src="fm-modules/$fm_name/images/fm.png" alt="$fm_name" title="$fm_name" />
			$fm_name<br />
			v$fm_version
		</div>
$account_menu
$module_toolbar
$user_account_menu
		<div id="topheadpartright">
			<a class="single_line help_link" href="#">Help</a>
		</div>
$module_menu
	</div>
	<div id="help">
		<div id="help_topbar">
			<p class="title">fmHelp</p>
			<p id="help_buttons">{$__FM_CONFIG['icons']['popout']} {$__FM_CONFIG['icons']['close']}</p>
		</div>
		<div id="help_file_container">
		$help_file
		</div>
	</div>

HTML;
	} else {
		$return = <<<HTML
	<div id="tophead">
		<div id="topheadpart">
			fmHelp<br />
			v$fm_version
		</div>
	</div>

HTML;
	}

	return $return;
}

/**
 * Prints the menu system
 *
 * @since 1.0
 * @package facileManager
 */
function printMenu($page_name, $page_name_sub) {
	global $__FM_CONFIG;
	
	$main_menu_html = $sub_menu_html = $domain = null;
	
	/** Get badge counts */
	$badge_array = getBadgeCounts();
	
	if (count($__FM_CONFIG['menu']['Settings']) > 1) {
		$temp_menu['General'] = $temp_menu['URL'] = $__FM_CONFIG['menu']['Settings']['URL'];
		foreach ($__FM_CONFIG['menu']['Settings'] as $item => $link) {
			if ($item == 'URL') continue;
			$temp_menu[$item] = $link;
		}
		$__FM_CONFIG['menu']['Settings'] = $temp_menu;
		unset($temp_menu);
	}

	foreach ($__FM_CONFIG['menu'] as $top_menu => $sub_menu) {
		if ($top_menu == 'Break' && $main_menu_html == null) continue;
		
		$class = ($page_name == $top_menu) ? ' class="current"' : null;
		
		$arrow = (!empty($class)) ? '<div class="arrow current"></div>' : '<div class="arrow"></div>';
		
		if ((empty($class) || count($sub_menu) <= 1) && array_key_exists($top_menu, $badge_array)) {
			$badge = '<span class="menu_badge';
			if (!empty($class) && count($sub_menu) <= 1) $badge .= ' badge_top_selected';
			$badge .= '"><p>' . array_sum($badge_array[$top_menu]) . '</p></span>';
		} else {
			$badge = null;
		}
		
		$sub_menu_html = null;

		/** Handle the styled break */
		if ($top_menu == 'Break') {
			$main_menu_html .= '<li><div class="separator"></div></li>' . "\n";
		} else {
			if (empty($class) && count($sub_menu) > 1) {
				$main_menu_html .= '<li class="has-sub"><a' . $class . ' href="' . $GLOBALS['RELPATH'] . $sub_menu['URL'] . '">' . $top_menu . $badge . '</a>' . "\n";
				unset($sub_menu['URL']);
				foreach ($sub_menu as $sub_menu_name => $sub_menu_url) {
					$sub_badge = (array_key_exists($sub_menu_name, $badge_array[$top_menu])) ? '<span class="menu_badge menu_badge_count badge_top_selected"><p>' . $badge_array[$top_menu][$sub_menu_name] . '</p></span>' : null;
					$sub_menu_html .= '<li><a' . $class . ' href="' . $GLOBALS['RELPATH'] . $sub_menu_url . '">' . $sub_menu_name . $sub_badge . '</a></li>' . "\n";
				}
				$main_menu_html .= <<<HTML
				<div class="arrow $class"></div>
				<ul>
$sub_menu_html

				</ul>
</li>

HTML;
			} else {
				$main_menu_html .= '<li><a' . $class . ' href="' . $GLOBALS['RELPATH'] . $sub_menu['URL'] . '">' . $top_menu . $badge . '</a>' . $arrow . '</li>' . "\n";
				if ($top_menu == $page_name) {
					unset($sub_menu['URL']);
					foreach ($sub_menu as $sub_menu_name => $sub_menu_url) {
						$class = ($page_name_sub == $sub_menu_name) ? ' class="current"' : null;
						$sub_badge = (array_key_exists($sub_menu_name, $badge_array[$top_menu])) ? '<span class="menu_badge menu_badge_count"><p>' . $badge_array[$top_menu][$sub_menu_name] . '</p></span>' : null;
						
						$sub_menu_html .= '<li><a' . $class . ' href="' . $GLOBALS['RELPATH'] . $sub_menu_url . '">' . $sub_menu_name . $sub_badge . '</a></li>' . "\n";
					}
					$main_menu_html .= <<<HTML
					<div id="submenu">
						<div id="subitems">
							<ul>
$sub_menu_html
							</ul>
						</div>
					</div>
HTML;
				}
			}
		}
	}
	
	echo <<<MENU
	<div id="menuback"></div>
	<div id="menu">
		<div id="mainitems">
			<ul>
$main_menu_html
			</ul>
		</div>
	</div>

MENU;
}

/**
 * Handles the config pages
 *
 * @since 1.0
 * @package facileManager
 */
function outputConfig($config = 'users') {
	if (empty($config)) $config = 'users';
	$action = (isset($_GET['action'])) ? $_GET['action'] : 'add';
	include(ABSPATH . 'fm-includes/class_' . $config . '.php');
	
	if ($config == 'users') {
	?>

	<div id="body_container">
		<h2>Users</h2>
		<div id="response"><?php if (!empty($response)) echo $response; else echo '<br />'; ?></div>
		<?php
		$result = basic_get_list('fm_users', 'user_id', 'user_');
		$fm_users->rows($result);
		?>
		<br /><br />
		<a name="#manage"></a>
		<h2><?php echo ucfirst($action); ?> User</h2>
		<?php $fm_users->print_users_form($form_data, $action); ?>
	</div>

	<?php
	}
}


/**
 * Gets the selected object
 *
 * @since 1.0
 * @package facileManager
 */
function basicGet($table, $id, $prefix = '', $field = 'id', $sql = '', $account_id = null) {
	global $fmdb;
	$id = sanitize($id);
	
	if (!$account_id) {
		$account_id = $_SESSION['user']['account_id'];
	}
	
	switch($sql) {
		case 'active':
			$sql = "AND `{$prefix}status`='active'";
			break;
		default:
			break;
	}
	
	$get_query = "SELECT * FROM `$table` WHERE `{$prefix}status`!='deleted' AND `account_id`='$account_id' AND `$field`='$id' $sql";
	return $fmdb->get_results($get_query);
}

/**
 * Gets the object list
 *
 * @since 1.0
 * @package facileManager
 */
function basicGetList($table, $id = 'id', $prefix = '', $sql = null, $limit = null, $ip_sort = false, $direction = 'ASC') {
	global $fmdb;
	
	switch($sql) {
		case 'active':
			$sql = "AND `{$prefix}status`='active'";
			break;
		default:
			break;
	}
	
	if (is_array($id)) {
		$primary_field = sanitize($id[0]);
		$secondary_fields = implode(',', $id);
		$secondary_fields = $direction . ' ' . sanitize(substr($secondary_fields, strlen($primary_field)));
	} else {
		$primary_field = sanitize($id);
		$secondary_fields = null;
	}
	
	if ($ip_sort) {
		$sort = "ORDER BY INET_ATON(`$primary_field`)" . $secondary_fields;
	} else {
		$sort = "ORDER BY `$primary_field`" . $secondary_fields;
	}
	
	$disp_query = "SELECT * FROM `$table` WHERE `{$prefix}status`!='deleted' AND account_id='{$_SESSION['user']['account_id']}' $sql $sort $direction $limit";
	return $fmdb->query($disp_query);
}

/**
 * Updates the record status
 *
 * @since 1.0
 * @package facileManager
 */
function updateStatus($table, $id, $prefix, $status, $field = 'id') {
	global $fmdb;
	
	$query = "UPDATE `$table` SET `{$prefix}status`='" . sanitize($status) . "' WHERE `$field`=" . sanitize($id);

	return $fmdb->query($query);
}


/**
 * Deletes the selected object
 *
 * @since 1.0
 * @package facileManager
 */
function basicDelete($table, $id, $field = 'id', $include_account_id = true) {
	global $fmdb;

	$account_id = $include_account_id ? "account_id='{$_SESSION['user']['account_id']}' AND" : null;
	
	$query = "DELETE FROM `$table` WHERE $account_id `$field`='" . sanitize($id) . "'";

	return $fmdb->query($query);
}


/**
 * Updates the selected object
 *
 * @since 1.0
 * @package facileManager
 */
function basicUpdate($table, $id, $update_field, $update_value, $field = 'id') {
	global $fmdb;
	
	$query = "UPDATE `$table` SET `$update_field`='" . sanitize($update_value) . "' WHERE account_id='{$_SESSION['user']['account_id']}' AND `$field`='" . sanitize($id) . "'";

	return $fmdb->query($query);
}


/**
 * Builds an array from mysql enum values
 *
 * @since 1.0
 * @package facileManager
 */
function enumMYSQLSelect($tbl_name, $column_name, $head = null) {
	global $fmdb;
	
	$query = "show columns from $tbl_name like '$column_name';";
	$result = $fmdb->get_results($query);
	
	$result = $fmdb->last_result;
	$thisrow = $result[0];
	$valuestring = $thisrow->Type;
	$valuestring = str_replace(array('enum', '(', ')', "'"), '', $valuestring);
	if (isset($head)) {
		$valuestring = "{$head},{$valuestring}";
	}
	$values = explode(',', $valuestring);
	
	return $values;
}

/**
 * Builds a drop down menu
 *
 * @since 1.0
 * @package facileManager
 */
function buildSelect($select_name, $select_id, $options, $option_select = null, $size = '1', $disabled = '', $multiple = false, $onchange = null, $class = null) {
	$type_options = null;
	if (is_array($options[0])) {
		for ($i = 0; $i < count($options); $i++) {
			if (is_array($option_select)) {
				foreach ($option_select as $key) {
					if ($key == $options[$i][1]) {
						$selected = ' selected';
						break;
					} else $selected = '';
				}
			} else $selected = ($option_select == $options[$i][1]) ? ' selected' : '';
			$type_options.="<option$selected value=\"{$options[$i][1]}\">{$options[$i][0]}</option>\n";
		}
	} else {
		for ($i = 0; $i < count($options); $i++) {
			$selected = ($option_select == $options[$i]) ? ' selected' : '';
			$type_options.="<option$selected>$options[$i]</option>\n";
		}
	}
	$build_select = "<select ";
	if ($class) $build_select .= "class=\"$class\" ";
	$build_select .= "size=\"$size\" name=\"{$select_name}";
	if ($multiple) $build_select .= '[]';
	$build_select .= "\" id=\"$select_id\"";
	if ($multiple) $build_select .= ' multiple';
	if ($onchange) $build_select .= ' onchange="' . $onchange . '" ';
	$build_select .= "$disabled>$type_options</select>\n";
	return $build_select;
}

/**
 * Removed trailing periods
 *
 * @since 1.0
 * @package facileManager
 */
function trimFullStop($value){
	return rtrim($value, '.');
}


/**
 * Gets name from an id
 *
 * @since 1.0
 * @package facileManager
 */
function getNameFromID($id, $table, $prefix, $field, $data, $account_id = null) {
	global $fmdb;
	
	if (!$account_id) {
		$account_id = $_SESSION['user']['account_id'];
	}
	
	basicGet($table, $id, $prefix, $field, null, $account_id);
	if ($fmdb->num_rows) {
		$result = $fmdb->last_result;
		return $result[0]->$data;
	}
}


/**
 * Gets account id from key
 *
 * @since 1.0
 * @package facileManager
 */
function getAccountID($value, $field = 'account_key', $key = 'account_id') {
	global $fmdb;
	
	$query = "SELECT $key FROM `fm_accounts` WHERE $field='$value'";
	$result = $fmdb->get_results($query);
	if ($fmdb->num_rows) {
		$result = $fmdb->last_result;
		return $result[0]->$key;
	}
}


/**
 * Checks the app functionality
 *
 * @since 1.0
 * @package facileManager
 */
function functionalCheck() {
	global $fm_name;
	
	if (isset($_SESSION['module'])) {
		$functions_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'functions.php';
		if (is_file($functions_file)) {
			if (!function_exists('moduleFunctionalCheck') && $_SESSION['module'] != $fm_name) {
				include($functions_file);
			}
			$html_checks = @moduleFunctionalCheck();
		} else {
			$html_checks = '<p>You have no modules installed.</p>';
		}
	}
	
	return $html_checks;
}


/**
 * Pings the $server to check if it's alive
 *
 * @since 1.0
 * @package facileManager
 */
function pingTest($server) {
	$program = findProgram('ping');
	if (PHP_OS == 'FreeBSD' || PHP_OS == 'Darwin') {
		$ping = shell_exec("$program -t 2 -c 3 $server 2>/dev/null");
	} elseif (PHP_OS == 'Linux') {
		$ping = shell_exec("$program -W 2 -c 3 $server 2>/dev/null");
	} else {
		$ping = shell_exec("$program -c 3 $server 2>/dev/null");
	}
	if (preg_match('/64 bytes from/', $ping)) {
		return true;
	}
	return false;
}


/**
 * Searches for the full path of the $program
 *
 * @since 1.0
 * @package facileManager
 */
function findProgram($program) {
	$path = array('/bin', '/sbin', '/usr/bin', '/usr/sbin', '/usr/local/bin', '/usr/local/sbin');

	if (function_exists('is_executable')) {
		while ($this_path = current($path)) {
			if (is_executable("$this_path/$program")) {
				return "$this_path/$program";
			}
			next($path);
		}
	} else {
		return strpos($program, '.exe');
	}

	return;
}


/**
 * Tests a $port on $host
 *
 * @since 1.0
 * @package facileManager
 */
function socketTest($host, $port, $timeout) {
	$fm = @fsockopen($host, $port, $errno, $errstr, $timeout);
	if (!$fm) return false;
	else {
		fclose($fm);
		return true;
	}
}


/**
 * Gets post data from a url
 *
 * @since 1.0
 * @package facileManager
 */
function getPostData($url, $data) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	$result = curl_exec($ch);
	curl_close($ch);
	return $result;
}


/**
 * Gets the user information
 *
 * @since 1.0
 * @package facileManager
 */
function getUserInfo($fm_login, $field = 'user_id') {
	global $fmdb;
	
	$query = "SELECT * FROM `fm_users` WHERE $field='$fm_login' AND `user_status`!='deleted' LIMIT 1";
	$fmdb->get_results($query);
	
	/** Matching results returned as an array */
	if ($fmdb->num_rows) {
		$user_results = $fmdb->last_result;
		$user_info = get_object_vars($user_results[0]);
		
		return $user_info;
	}
	
	/** No matching results */
	return false;
}


/**
 * Process password reset user form.
 *
 * @since 1.0
 * @package facileManager
 */
function genRandomString($length) {
	$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$string = null;
	for ($p = 0; $p < $length; $p++) {
		$string .= $characters[mt_rand(0, strlen($characters)-1)];
	}
	return $string;
}


/**
 * Converts $_SERVER['REQUEST_URI'] to an array
 *
 * @since 1.0
 * @package facileManager
 */
function convertURIToArray() {
	$uri = explode('?', $_SERVER['REQUEST_URI']);
	if (count($uri) > 1) {
		$raw_params = explode('&', $uri[1]);
		
		for ($i=0; $i<count($raw_params); $i++) {
			if (strpos($raw_params[$i], '=')) {
				$param = explode('=', $raw_params[$i]);
				$return_array[$param[0]] = $param[1];
			} else {
				$return_array[$raw_params[$i]] = null;
			}
		}
		return $return_array;
	}
	
	return array();
}


/**
 * Builds the dashboard for display
 *
 * @since 1.0
 * @package facileManager
 */
function buildDashboard() {
	global $fm_name;
	
	require(ABSPATH . 'fm-includes/version.php');
	$fm_new_version_available = isNewVersionAvailable($fm_name, $fm_version);
	
	if ($fm_new_version_available) {
		$dashboard = <<<DASH
	<div id="shadow_box" class="fullwidthbox">
		<div id="shadow_container" class="fullwidthbox">
		$fm_new_version_available
		</div>
	</div>
	<br />
DASH;
	} else $dashboard = null;
	
	if (isset($_SESSION['module'])) {
		$functions_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'functions.php';
		if (is_file($functions_file)) {
			if (!function_exists('buildModuleDashboard')) {
				include($functions_file);
			}
			$body = @buildModuleDashboard();
		} else {
			$body = '<p>You have no modules installed.</p>';
		}
	}

	return $dashboard . $body;
}


/**
 * Builds the help for display
 *
 * @since 1.0
 * @package facileManager
 */
function buildHelpFile() {
	global $fm_name, $__FM_CONFIG;
	
	/** facileManager help */
	$body = <<<HTML
<h3>$fm_name</h3>
<ul>
	<li>
		<a class="list_title" onclick="javascript:toggleLayer('fm_config_modules', 'block');">Configure Modules</a>
		<div id="fm_config_modules">
			<p>Modules are what gives $fm_name purpose. They can be installed, activated, upgraded, deactivated, and uninstalled.</p>
			
			<p><b>Install</b><br />
			Just extract the module into the 'fm-modules' directory on the server host (if not already present), go to Admin &rarr; 
			<a href="{$__FM_CONFIG['menu']['Modules']['URL']}">Modules</a>, and then click the 'Install' button next to the module 
			you wish to install.</p>
			<p><i>The 'Module Management' or 'Super Admin' permission is required for this action.</i></p>
			<br />
			<p><b>Activate</b><br />
			In order for the module to be usable, it needs to be active in the UI.</p>
			<p>Go to Admin &rarr; <a href="{$__FM_CONFIG['menu']['Modules']['URL']}">Modules</a> and click the 'Activate' link next 
			to the module you wish to activate.</p>
			<p><i>The 'Module Management' or 'Super Admin' permission is required for this action.</i></p>
			<br />
			<p><b>Upgrade</b><br />
			Anytime module files are individually updated in the 'fm-modules' directory on the server host apart from updating $fm_name 
			as a whole, they will need to be upgraded to ensure full compatibility and functionality.</p>
			<p>Go to Admin &rarr; <a href="{$__FM_CONFIG['menu']['Modules']['URL']}">Modules</a> and click the 'Upgrade' button next 
			to the module you wish to upgrade. This will upgrade the database with any required changed.</p>
			<p><i>The 'Module Management' or 'Super Admin' permission is required for this action.</i></p>
			<br />
			<p><b>Deactivate</b><br />
			If you no longer want a module to be usable, it can be deactived in the UI.</p>
			<p>Go to Admin &rarr; <a href="{$__FM_CONFIG['menu']['Modules']['URL']}">Modules</a> and click the 'Deactivate' link next 
			to the module you wish to deactivate.</p>
			<p><i>The 'Module Management' or 'Super Admin' permission is required for this action.</i></p>
			<br />
			<p><b>Uninstall</b><br />
			If you no longer want a module to be installed, it can be uninstalled via the UI.</p>
			<p>Go to Admin &rarr; <a href="{$__FM_CONFIG['menu']['Modules']['URL']}">Modules</a>, ensure the module is already 
			deactivated, and then click the 'Uninstall' button next to the module you wish to remove. This will remove all associated 
			entries and tables from the database.</p>
			<p><i>The 'Module Management' or 'Super Admin' permission is required for this action.</i></p>
		</div>
	</li>
	<li>
		<a class="list_title" onclick="javascript:toggleLayer('fm_config_users', 'block');">Manage Users</a>
		<div id="fm_config_users">
			<p>$fm_name incorporates the use of multiple user accounts with granular permissions. This way you can limit access to your 
			environment.</p>
			
			<p>You can add, modify, and delete user accounts at Admin &rarr; <a href="{$__FM_CONFIG['menu']['Admin']['Users']}">Users</a>.</p>
			
			<p>For non-LDAP users, there are some options you can select:</p>
			<ul>
				<li><b>Force Password Change at Next Login</b><br />
				Tick this box the user should be forced to change their password next time they try to login.</li>
				<li><b>Template User</b><br />
				Tick this box if this user should be a template user only. These users cannot be enabled and cannot login to $fm_name. Any user 
				account of this type will be depicted with a {$__FM_CONFIG['icons']['template_user']} next to the user name.</li>
			</ul>
			
			<p>Each permission checkbox will grant or deny access to certain functionalities within $fm_name.</p>
			<ul>
				<li><b>Super Admin</b><br />
				This permission will grant the user unrestricted access to the entire facileManager environment. There must be at least one
				Super Admin. Any user account with this privilege will be depicted with a {$__FM_CONFIG['icons']['star']} next to the user name.</li>
				<li><b>Module Management</b><br />
				With this permission, the user will be able to activate, deactivate, install, upgrade, and uninstall modules within $fm_name.</li>
				<li><b>User Management</b><br />
				This permission allows the user to add, modify, and delete user accounts.</li>
				<li><b>Run Tools</b><br />
				This permission grants the user access to run the various tools in Admin &rarr; <a href="{$__FM_CONFIG['menu']['Admin']['Tools']}">Tools</a>.</li>
				<li><b>Manage Settings</b><br />
				This permission grants the user access to change system settings at Admin &rarr; <a href="{$__FM_CONFIG['menu']['Admin']['Settings']}">Settings</a>.</li>
			</ul>
			<p><i>The 'User Management' or 'Super Admin' permission is required for these actions.</i></p>
		</div>
	</li>
	<li>
		<a class="list_title" onclick="javascript:toggleLayer('fm_config_settings', 'block');">Manage Settings</a>
		<div id="fm_config_settings">
			<p>There are several settings available to set at Admin &rarr; <a href="{$__FM_CONFIG['menu']['Admin']['Settings']}">Settings</a>.</p>
			<p><i>The 'Manage Settings' or 'Super Admin' permission is required to change settings.</i></p>
			<p><b>Authentication</b><br />
			There are three types of authentication supported by $fm_name:</p>
			<ul>
				<li><b>None</b><br />
				Every user will be automatically logged in as the default super-admin account that was created during the installation process.</li>
				<li><b>Built-in Authentication</b><br />
				Authenticates against the $fm_name database using solely the users defined at Admin &rarr; <a href="{$__FM_CONFIG['menu']['Admin']['Users']}">Users</a>.</li>
				<li><b>LDAP Authentication</b><br />
				Users are authenticated against a defined LDAP server. Upon success, users are created in the $fm_name database using the selected 
				template account for granular permissions within the environment. These users cannot be disabled nor can their passwords be changed 
				within $fm_name. The PHP LDAP extensions have to be installed before this option is available.</li>
			</ul>
			<p><b>SSL</b><br />
			You can choose to have $fm_name enforce the use of SSL when a user tries to access the web app.</p>
			<p><b>Mailing</b><br />
			There are a few things $fm_name and its modules may need to send an e-mail about (such as password reset links). These settings allow
			you to configure the mailing settings to use for your environment and enable/disable mailing altogether.</p>
			<p><b>Date and Time</b><br />
			Set your preferred timezone, date format, and time format for $fm_name to use throughout all aspects of the app. What you select is
			how all dates and times will be display including any client configuration files.</p>
			<p><b>Show Errors</b><br />
			Choose whether you want $fm_name errors to be displayed as they occur or not. This can be useful if you are having trouble
			adding or editing opjects.</p>
			<p><b>Temporary Directory</b><br />
			Periodically $fm_name and its modules may need to create temporary files or directories on your webserver. Specify the local path for it to use.</p>
			<p><b>Software Update</b><br />
			Choose whether you want $fm_name to automatically check for software updates or not.</p>
			<p><b>SSH Key Pair</b><br />
			In order for client configs to be updated via SSH, $fm_name needs a 2048-bit passwordless key pair generated. Without this key pair, 
			clients cannot use the SSH update method. Click the 'Generate' button to have $fm_name automatically generate the necessary key pair.</p>
		</div>
	</li>
	<li>
		<a class="list_title" onclick="javascript:toggleLayer('fm_config_logs', 'block');">Review Logs</a>
		<div id="fm_config_logs">
			<p>Every action performed within the $fm_name UI will be logged for auditing purposes.</p>
			<p>You can view and search the logs at Admin &rarr; <a href="{$__FM_CONFIG['menu']['Admin']['Logs']}">Logs</a></p>
		</div>
	</li>
</ul>
	
HTML;
	
	/** Get module help file */
	if (isset($_SESSION['module']) && $_SESSION['module'] != $fm_name) {
		$functions_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'functions.php';
		if (is_file($functions_file)) {
			if (!function_exists('buildModuleHelpFile')) {
				include($functions_file);
			}
			$body .= @buildModuleHelpFile();
		} else {
			$body .= '<p>You have no modules installed.</p>';
		}
	}

	return $body;
}


/**
 * Adds a UI log entry to the database
 *
 * @since 1.0
 * @package facileManager
 */
function addLogEntry($log_data, $module = null, $link = null) {
	global $fmdb, $__FM_CONFIG;
	
	$account_id = isset($_SESSION['user']['account_id']) ? $_SESSION['user']['account_id'] : 0;
	$user_id = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : 0;
	$module = isset($module) ? $module : $_SESSION['module'];
	
	$insert = "INSERT INTO `{$__FM_CONFIG['db']['name']}`.`fm_logs` VALUES (NULL, $user_id, $account_id, '$module', " . time() . ", '" . sanitize($log_data) . "');";
	if ($link) {
		$result = @mysql_query($insert, $link) or die(mysql_error());
	} else {
		$fmdb->query($insert);
	}
}


/**
 * Builds an array of available modules
 *
 * @since 1.0
 * @package facileManager
 */
function getAvailableModules() {
	global $fm_name;
	
	$module_dir = ABSPATH . 'fm-modules';
	if ($handle = opendir($module_dir)) {
		$blacklist = array('.', '..', 'shared', strtolower($fm_name));
		while (false !== ($file = readdir($handle))) {
			if (!in_array(strtolower($file), $blacklist)) {
				if (is_dir($module_dir . DIRECTORY_SEPARATOR . $file)) {
					$modules[] = $file;
				}
			}
		}
		closedir($handle);

		if (count($modules)) {
			sort($modules);
			return $modules;
		}
	}
	
	return null;
}

/**
 * Returns an option value
 *
 * @since 1.0
 * @package facileManager
 */
function getOption($option = null, $account_id = 0, $table = 'fm_options', $prefix = 'option_') {
	global $fmdb;
	
	$value = $prefix . 'value';
	
	$query = "SELECT * FROM $table WHERE {$prefix}name='$option' AND account_id=$account_id LIMIT 1";
	$fmdb->get_results($query);
	
	if ($fmdb->num_rows) {
		$results = $fmdb->last_result;
		
		if (isSerialized($results[0]->$value)) {
			return unserialize($results[0]->$value);
		}
		
		return $results[0]->$value;
	}
	
	return false;
}

/**
 * Sets an option value
 *
 * @since 1.0
 * @package facileManager
 */
function setOption($option = null, $value = null, $insert_update = 'auto', $auto_serialize = true, $account_id = 0, $table = 'fm_options', $prefix = 'option_') {
	global $fmdb;
	
	if ($auto_serialize) {
		$value = isSerialized($value) ? sanitize($value) : serialize($value);
	} else sanitize($value);
	$option = sanitize($option);
	
	if ($insert_update == 'auto') {
		$query = "SELECT * FROM $table WHERE {$prefix}name='$option' AND account_id=$account_id";
		$result = $fmdb->query($query);
		$insert_update = ($fmdb->num_rows) ? 'update' : 'insert';
	}
	
	if ($insert_update == 'insert') {
		$query = "INSERT INTO $table (account_id, {$prefix}name, {$prefix}value) VALUES ($account_id, '$option', '$value')";
	} else {
		$query = "UPDATE $table SET {$prefix}name='$option', {$prefix}value='$value' WHERE {$prefix}name='$option' AND account_id=$account_id";
	}
	$result = $fmdb->query($query);
	
	return $result;
}

/**
 * Check value to find if it was serialized.
 *
 * If $data is not an string, then returned value will always be false.
 * Serialized data is always a string.
 *
 * @since 1.0
 * @package facileManager
 *
 * @param mixed $data Value to check to see if was serialized.
 * @return bool False if not serialized and true if it was.
 */
function isSerialized($data) {
	// if it isn't a string, it isn't serialized
	if (!is_string($data))
		return false;
	$data = trim($data);
	if ('N;' == $data)
		return true;
	$length = strlen($data);
	if ($length < 4)
		return false;
	if (':' !== $data[1])
		return false;
	$lastc = $data[$length-1];
	if (';' !== $lastc && '}' !== $lastc)
		return false;
	$token = $data[0];
	switch ($token ) {
		case 's' :
			if ('"' !== $data[$length-2])
				return false;
		case 'a' :
		case 'O' :
			return (bool) preg_match("/^{$token}:[0-9]+:/s", $data);
		case 'b' :
		case 'i' :
		case 'd' :
			return (bool) preg_match("/^{$token}:[0-9.E-]+;\$/", $data);
	}
	return false;
}

/**
 * Builds an array of active modules
 *
 * @since 1.0
 * @package facileManager
 */
function getActiveModules($allowed_modules = false) {
	global $fm_login;
	
	$modules = getOption('fm_active_modules', $_SESSION['user']['account_id']);
	
	if ($modules !== false) {
		@sort($modules);
		if (!$allowed_modules) {
			return $modules;
		}
		
		$excluded_modules = array();
		foreach ($modules as $module_name) {
			$module_perms = $fm_login->getModulePerms($_SESSION['user']['id'], $module_name);
			include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $module_name . DIRECTORY_SEPARATOR . 'permissions.inc.php');
			if ($module_perms & PERM_MODULE_ACCESS_DENIED) $excluded_modules[] = $module_name;
		}
		return array_merge(array_diff($modules, $excluded_modules), array());
	} else {
		return array();
	}
}

/**
 * Uninstalls a module
 *
 * @since 1.0
 * @package facileManager
 */
function uninstallModuleSchema($database, $module) {
	global $fmdb, $__FM_CONFIG;
	
	$removes[] = <<<REMOVE
DELETE FROM $database.`fm_options` WHERE `option_name` LIKE '{$module}_%';
REMOVE;

	foreach ($removes as $query) {
		$result = $fmdb->query($query);
	}
	
	/** Include module variables */
	@include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'variables.inc.php');
	
	$query = "SHOW TABLES FROM `{$__FM_CONFIG['db']['name']}` LIKE 'fm_{$__FM_CONFIG[$module]['prefix']}%'";
	$result = $fmdb->get_results($query);
	$tables = $fmdb->last_result;
	$table_count = $fmdb->num_rows;
	
	for ($i=0; $i<$table_count; $i++) {
		$table_info = get_object_vars($tables[$i]);
		sort($table_info);
		$drop_query = "DROP TABLE `{$__FM_CONFIG['db']['name']}`.`{$table_info[0]}`";
		$result = $fmdb->query($drop_query);
		if (!$fmdb->rows_affected) return false;
	}
	
	return 'Success';
}

/**
 * Formats filesize
 *
 * @since 1.0
 * @package facileManager
 *
 * @param string $size Number to format
 * @return string
 */
function formatSize($size) {
	$sizes = array(" Bytes", " KB", " MB", " GB", " TB", " PB", " EB", " ZB", " YB");
	if ($size == 0) {
		return('n/a');
	} else {
		return (round($size/pow(1024, ($i = floor(log($size, 1024)))), $i > 1 ? 2 : 0) . $sizes[$i]);
	}
}


/**
 * Forms the date menu
 *
 * @since 1.0
 * @package facileManager
 *
 * @param string $date Date to format
 * @return string
 */
function buildDateMenu($date = null) {
	$uri = $hidden = null;
	foreach ($GLOBALS['URI'] as $key => $value) {
		if (empty($key)) continue;
		if ($key == 'date') continue;
		$uri .= $key . '=' . $value . '&';
		$hidden .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
	}
	$uri = rtrim($uri, '&');
	
	if ($date) {
		$current_date = date("m/d/Y", strtotime($date));
		$next_date = date("Y-m-d", strtotime("$current_date + 1 day"));
		$previous_date = date("Y-m-d", strtotime("$current_date - 1 day"));
	} else {
		$current_date = date("m/d/Y");
		$next_date = date("Y-m-d", strtotime("tomorrow"));
		$previous_date = date("Y-m-d", strtotime("yesterday"));
	}

	$next = '<a href="?' . $uri . '&date=' . $next_date . '">next &rarr;</a>';
	$previous = '<a href="?' . $uri . '&date=' . $previous_date . '">&larr; previous</a>';
	
	$date_menu = <<<HTML
	<div id="datemenu">
		<form action="" method="get">
			$hidden
			$previous
			<input name="date" type="text" class="datepicker" value="$current_date" style="width: 7em;" onchange="this.form.submit()" />
			$next
		</form>
	</div>
HTML;

	return $date_menu;
}


/**
 * Generates a server serial number
 *
 * @since 1.0
 * @package facileManager
 *
 * @param string $module Module to use
 * @return int
 */
function generateSerialNo($module = null) {
	global $fmdb, $__FM_CONFIG;

	if ($module) {
		while(1) {
			if (array_key_exists('server_name', $_POST) && defined('CLIENT')) {
				$get_query = "SELECT * FROM `fm_{$__FM_CONFIG[$module]['prefix']}servers` WHERE `server_status`!='deleted' AND account_id='" . getAccountID(sanitize($_POST['AUTHKEY'])) . "' AND `server_name`='" . sanitize($_POST['server_name']) . "'";
				$fmdb->get_results($get_query);
				if ($fmdb->num_rows) {
					$array = $fmdb->last_result;
					return $array[0]->server_serial_no;
				}
			}
			$serialno = rand(100000000, 999999999);
			
			/** Ensure the serial number does not exist in any of the server tables */
			$all_tables = $fmdb->get_results("SELECT table_name FROM information_schema.tables t WHERE t.table_schema = '{$__FM_CONFIG['db']['name']}' AND t.table_name LIKE 'fm_%_servers'");
			$table_count = $fmdb->num_rows;
			$result = $fmdb->last_result;
			$taken = true;
			for ($i=0; $i<$table_count; $i++) {
				basicGet($result[$i]->table_name, $serialno, 'server_', 'server_serial_no', null, 1);
				if (!$fmdb->num_rows) $taken = false;
			}
			if (!$taken) return $serialno;
		}
	}
}


/**
 * Returns the server serial number
 *
 * @since 1.0
 * @package facileManager
 *
 * @param int $server_id Server ID to process
 * @param string $module Module to use
 * @return string
 */
function getServerSerial($server_id, $module = null) {
	global $fmdb, $__FM_CONFIG;
	
	if ($module) {
		basicGet('fm_' . $__FM_CONFIG[$module]['prefix'] . 'servers', $server_id, 'server_', 'server_id');
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			return $result[0]->server_serial_no;
		}
	}
}


/**
 * Returns the server ID
 *
 * @since 1.0
 * @package facileManager
 *
 * @param int $server_serial_no Server serial number to process
 * @param string $module Module to use
 * @return string
 */
function getServerID($server_serial_no, $module = null) {
	global $fmdb, $__FM_CONFIG;
	
	if ($module) {
		basicGet('fm_' . $__FM_CONFIG[$module]['prefix'] . 'servers', $server_serial_no, 'server_', 'server_serial_no');
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			return $result[0]->server_id;
		}
	}
}


/**
 * Checks multiple keys in an array
 *
 * @since 1.0
 * @package facileManager
 *
 * @param array $keys Keys to check
 * @param array $array Array to search
 * @return bool
 */
function arrayKeysExist($keys, $array) {
	foreach ($keys as $key) {
		if (array_key_exists($key, $array)) {
			return true;
		}
	}
	return false;
}


/**
 * Displays pagination
 *
 * @since 1.0
 * @package facileManager
 *
 * @param integer $page Current page
 * @param integer $total_pages Total number of pages
 * @return string
 */
function displayPagination($page, $total_pages) {
	if ($total_pages <= 1) return;
	
	$search = null;
	
	$page_links = array();
	$end_size = 1;
	$mid_size = 2;
	$dots = false;
	$page_links[] = '<div id="pagination">';

	// Previous link
	if ($page > 1 && $total_pages > 1) {
		$page_links[] = '<a href="' . $GLOBALS['basename'] . "?{$search}p=" . ($page - 1) . '">« Previous</a>';
	}
	// Page number
	for ($p=1; $p<=$total_pages; $p++) {
		if ($p == $page) {
			$page_links[] = '<span class="current">' . $p . '</span>';
			$dots = true;
		} else {
			if ($p <= $end_size || ($page && $p >= $page - $mid_size && $p <= $page + $mid_size) || $p > $total_pages - $end_size) {
				$page_links[] = '<a href="' . $GLOBALS['basename'] . "?{$search}p=" . $p . '">' . $p . '</a>';
				$dots = true;
			} elseif ($dots) {
				$page_links[] = '<span class="text">...</span>';
				$dots = false;
			}
		}
	}
	// Next link
	if ($page < $total_pages) {
		$page_links[] = '<a href="' . $GLOBALS['basename'] . "?{$search}p=" . ($page + 1) . '">Next »</a>';
	}

	$page_links[] = '</div>';
	
	return join("\n", $page_links);
}


/**
 * Displays error message
 *
 * @since 1.0
 * @package facileManager
 *
 * @param string $message Message to display
 * @return null
 */
function bailOut($message, $title = 'Requirement Error') {
	printHeader($title, 'install');
	echo $message;
	exit(printFooter());
}


/**
 * Displays progress
 *
 * @since 1.0
 * @package facileManager
 *
 * @param string $step Step message
 * @param string $result Result of step
 * @param boolean $noisy Whether the result should be echoed
 * @return string
 */
function displayProgress($step, $result, $noisy = true) {
	$output = ($result == true) ? 'Success' : 'Failed';
	$color = strtolower($output);
	
	$message = <<<HTML
	<tr>
		<th>$step</th>
		<td class="status $color">$output!</td>
	</tr>

HTML;

	if ($noisy) {
		echo $message;
		return $output;
	} else return $message;
}


/**
 * Checks if PEAR is installed
 *
 * @since 1.0
 * @package facileManager
 *
 * @param string $package Additional PEAR package to check
 * @return boolean
 */
/**
function isPearInstalled($packages = null){
	require_once 'System.php';
	if (!class_exists('System', false)) return false;
	
	if ($packages) {
		$packages = is_array($packages) ? $packages : array($packages);
		foreach ($packages as $pear_package) {
			exec(findProgram('pear') . ' info ' . $pear_package, $output, $retval);
			if ($retval) return false;
		}
	}
	
	return true;
}
 */


/**
 * Checks if an email address is valid
 *
 * @since 1.0
 * @package facileManager
 *
 * @param string $address Email address to validate
 * @return boolean
 */
function isEmailAddressValid($address){
	return filter_var($address, FILTER_VALIDATE_EMAIL);
}


/**
 * Checks if fM is running behind SSL or not supports load-balancers
 *
 * @since 1.0
 * @package facileManager
 *
 * @return boolean
 */
function isSiteSecure(){
	if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
	    return true;
	} elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
	    return true;
	}
	
	return false;
}


/**
 * Gets the table column length
 *
 * @since 1.0
 * @package facileManager
 *
 * @param string $tbl_name Table name to check
 * @param string $column_name Column name to check
 * @return integer
 */
function getColumnLength($tbl_name, $column_name) {
	global $fmdb;
	
	$query = "show columns from $tbl_name like '$column_name';";
	$result = $fmdb->get_results($query);
	
	$result = $fmdb->last_result;
	$thisrow = $result[0];
	$valuestring = $thisrow->Type;
	
	/** No limit */
	if (strpos($thisrow->Type, 'varchar') === false) return false;
	
	return str_replace(array('varchar', '(', ')', "'"), '', $valuestring);
}


/**
 * Checks if an IP Address is valid
 *
 * @since 1.0
 * @package facileManager
 *
 * @param string $ip_address IP Address to check
 * @return boolean
 */
function verifyIPAddress($ip_address) {
	return filter_var($ip_address, FILTER_VALIDATE_IP);
}


/**
 * Checks number validity
 *
 * @since 1.0
 * @package facileManager
 *
 * @param string $number Number to check
 * @param string $min_range Minimum number in the range
 * @param string $max_range Maximum number in the range
 * @param boolean $decimal_allowed Are decimals allowed
 * @return boolean
 */
function verifyNumber($number, $min_range = 0, $max_range = null, $decimal_allowed = true) {
	if ($min_range >= 0 && $max_range != null) {
		if (!$decimal_allowed) {
			return filter_var($number, FILTER_VALIDATE_INT, array('options' => array('min_range' => $min_range, 'max_range' => $max_range)));
		} else {
			
			return filter_var($number, FILTER_VALIDATE_INT, array('options' => array('min_range' => $min_range, 'max_range' => $max_range)));
		}
	} else {
		return filter_var($number, FILTER_VALIDATE_INT);
	}
}


/**
 * Builds a form for app settings
 *
 * @since 1.0
 * @package facileManager
 *
 * @param string $saved_options Settings pulled from the database
 * @param string $default_options Default settings
 * @return string
 */
function buildSettingsForm($saved_options, $default_options) {
	$option_rows = null;
	
	foreach ($default_options as $option => $options_array) {
		$option_value = array_key_exists($option, $saved_options) ? $saved_options[$option] : $options_array['default_value'];
		
		if (is_array($option_value)) {
			$temp_value = null;
			foreach ($option_value as $value) {
				$temp_value .= $value . "\n";
			}
			$option_value = rtrim($temp_value);
		}
		
		switch($options_array['type']) {
			case 'textarea':
				$input_field = '<textarea name="' . $option . '" id="' . $option . '" type="' . $options_array['type'] . '">' . $option_value . '</textarea>';
				break;
			case 'checkbox':
				$checked = $option_value == 'yes' ? 'checked' : null;
				$input_field = '<input name="' . $option . '" id="' . $option . '" type="hidden" value="no" />';
				$input_field .= '<label><input name="' . $option . '" id="' . $option . '" type="' . $options_array['type'] . '" value="yes" ' . $checked . ' />' . $options_array['description'][0] . '</label>';
				break;
			case 'select':
				$input_field = buildSelect($option, $option, $options_array['options'], $option_value);
				break;
			default:
				$input_field = '<input name="' . $option . '" id="' . $option . '" type="' . $options_array['type'] . '" value="' . $option_value . '" size="40" />';
		}
		$option_rows .= <<<ROW
			<div id="setting-row">
				<div class="description">
					<label for="$option">{$options_array['description'][0]}</label>
					<p>{$options_array['description'][1]}</p>
				</div>
				<div class="choices">
					$input_field
				</div>
			</div>

ROW;
	}
	
	return $option_rows;
}


/**
 * Compresses a file
 *
 * @since 1.0
 * @package facileManager
 *
 * @param string $filename Name of the file to compress
 * @param string $contents File contents to compress
 * @return null
 */
function compressFile($filename, $contents) {
	$compressed_filename = $filename . '.gz';
	
	$fp = gzopen($compressed_filename, 'w9');
	gzwrite($fp, file_get_contents($filename));
	gzclose($fp);
}


/**
 * Sends a file to the browser for download
 *
 * @since 1.0
 * @package facileManager
 *
 * @param string $filename Name of the file to compress
 * @param string $contents File contents to compress
 * @return file
 */
function sendFileToBrowser($filename) {
	if (is_file($filename)) {
		header('Content-type: application/octet-stream');
		header('Content-Disposition: attachment; filename=' . basename($filename));
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . filesize($filename));
		ob_clean();
		flush();
		readfile($filename);
		@unlink($filename);
		exit;
	}
}


/**
 * Returns an icon for the server OS
 *
 * @since 1.0
 * @package facileManager
 *
 * @param string $server_os Server OS to return the icon for
 * @return string
 */
function setOSIcon($server_os) {
	global $fm_name;
	
	$os_name = array('openSUSE');
	$os_image = array('SUSE');
	
	$os = file_exists(ABSPATH . 'fm-modules/' . $fm_name . '/images/os/' . str_replace($os_name, $os_image, $server_os) . '.png') ? $server_os : 'unknown';
	$os_image = '<img src="fm-modules/' . $fm_name . '/images/os/' . str_replace($os_name, $os_image, $os) . '.png" border="0" alt="' . $os . '" title="' . $os . '" width="18" />';
	
	return $os_image;
}


/**
 * Returns an icon for the server OS
 *
 * @since 1.0
 * @package facileManager
 *
 * @param string $server_os Server OS to return the icon for
 * @return string
 */
function printPageHeader($response, $title, $allowed_to_add = false, $name = null) {
	global $__FM_CONFIG;
	
	echo '<div id="body_container">' . "\n";
	if (!empty($response)) echo '<div id="response"><p class="error">' . $response . "</p></div>\n";
	else echo '<div id="response" style="display: none;"></div>' . "\n";
	echo "<h2>$title";
	
	if ($allowed_to_add) {
		if ($name) $name = ' name="' . $name . '"';
		echo '<a id="plus" href="#" title="Add New"' . $name . '>' . $__FM_CONFIG['icons']['add'] . '</a>';
	}
	
	echo '</h2>' . "\n";
}


/**
 * Sets server build config flag
 *
 * @since 1.0
 * @package facileManager
 *
 * @param integer $serial_no Server serial number
 * @param string $flag Flag to set (yes or no)
 * @param string $build_update Are we building or updating
 * @param integer $domain_id Domain ID to update DNS servers for
 * @return boolean
 */
function setBuildUpdateConfigFlag($serial_no = null, $flag, $build_update) {
	global $fmdb, $__FM_CONFIG, $fm_dns_zones;
	
	$serial_no = sanitize($serial_no);
	if ($serial_no) {
		$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}servers` SET `server_" . $build_update . "_config`='" . $flag . "' WHERE `server_serial_no` IN (" . $serial_no . ") AND `server_installed`='yes'";
	} else {
		$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}servers` SET `server_" . $build_update . "_config`='" . $flag . "' WHERE `server_installed`='yes' AND `server_status`='active'";
	}
	$result = $fmdb->query($query);
	
	if ($fmdb->result) return true;
	return false;
}


/**
 * Sets the timezone
 *
 * @since 1.0
 * @package facileManager
 *
 * @return null
 */
function setTimezone() {
	if (isset($_SESSION['user'])) {
		$default_timezone = getOption('timezone', $_SESSION['user']['account_id']);
	}
	if (!empty($default_timezone)) {
		date_default_timezone_set($default_timezone);
	} else {
		if (ini_get('date.timezone')) {
			date_default_timezone_set(ini_get('date.timezone'));
		} else {
			date_default_timezone_set('Europe/London');
		}
	}
}


/**
 * Gets menu badge counts
 *
 * @since 1.1
 * @package facileManager
 *
 * @return array
 */
function getBadgeCounts() {
	global $fm_name;
	
	$badge_count = array();
	
	/** Get fM badge counts */
	$modules = getAvailableModules();
	foreach ($modules as $module_name) {
		/** Include module variables */
		@include(ABSPATH . 'fm-modules/' . $module_name . '/variables.inc.php');
		
		/** Upgrades waiting */
		$module_version = getOption(strtolower($module_name) . '_version', 0);
		if ($module_version !== false) {
			if (version_compare($module_version, $__FM_CONFIG[$module_name]['version'], '<')) {
				$badge_count['Modules']['URL']++;
				continue;
			}
		}
		
		/** New versions available */
		if (isNewVersionAvailable($module_name, $module_version)) $badge_count['Modules']['URL']++;
	}
	
	$module_badge_count = null;
	/** Get module badge counts */
	if (isset($_SESSION['module']) && $_SESSION['module'] != $fm_name) {
		$functions_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'functions.php';
		if (is_file($functions_file)) {
			if (!function_exists('moduleFunctionalCheck')) {
				@include($functions_file);
			}
			if (function_exists('getModuleBadgeCounts')) {
				$module_badge_count = getModuleBadgeCounts();
			}
			if (count($module_badge_count)) $badge_count = array_merge($badge_count, $module_badge_count);
		}
	}
	
	return $badge_count;
}


/**
 * Builds bulk action menu
 *
 * @since 1.1
 * @package facileManager
 *
 * @return array
 */
function buildBulkActionMenu($bulk_actions_list = null, $id = 'bulk_action') {
	if (is_array($bulk_actions_list)) {
		$bulk_actions[] = 'Bulk Actions';
		
		return buildSelect($id, 'bulk_action', array_merge($bulk_actions, $bulk_actions_list), null, 1) . 
			'<input type="submit" name="bulk_apply" id="bulk_apply" value="Apply" class="button" />' . "\n";
	}
}


/**
 * Takes text and strips html and whitespace
 *
 * @since 1.2
 * @package facileManager
 *
 * @param string $text Text to clean up
 * @param boolean $make_array Whether the output should be an array or not
 * @return array
 */
function makePlainText($text, $make_array = false) {
	$text = strip_tags($text);
	$text = trim($text);
	
	if ($make_array == true) return explode("\n", $text);
	
	return $text;
}


/**
 * Displays a table header
 *
 * @since 1.2
 * @package facileManager
 *
 * @param array $table_info Values to build the <table> tag
 * @param array $head_values Values to build the <th> tags
 * @param string $tbody_id id for <tbody>
 * @return string
 */
function displayTableHeader($table_info, $head_values, $tbody_id = null) {
	if ($tbody_id) $tbody_id = ' id="' . $tbody_id . '"';
	
	$sort_direction = isset($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']]['sort_direction']) ? strtolower($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']]['sort_direction']) : 'asc';
	
	$parameters = null;
	if (is_array($table_info)) {
		foreach ($table_info as $parameter => $value) {
			$parameters .= ' ' . $parameter . '="' . $value . '"';
		}
	}
	$html = '<table' . $parameters . ">\n";
	$html .= "<thead>\n<tr>\n";
	
	foreach ($head_values as $thead) {
		$parameters = null;
		if (is_array($thead)) {
			$temp_array = $thead;
			$thead = null;
			foreach ($temp_array as $parameter => $value) {
				if ($parameter == 'title') {
					$thead = $value;
					continue;
				}
				$parameters .= (is_null($value)) ? ' ' . $parameter : ' ' . $parameter . '="' . $value . '"';
				if ($parameter == 'rel') {
					if (isset($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']]['sort_field']) &&
						$value == $_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']]['sort_field']) {
							$parameters .= ' id="header-sorted"';
					}
				}
			}
		}
		$html .= "<th$parameters>$thead <i class=\"$sort_direction\"></i></th>\n";
	}
	$html .= "</tr>\n</thead>\n<tbody$tbody_id>\n";
	
	return $html;
}


/**
 * Displays a table header
 *
 * @since 1.2
 * @package facileManager
 *
 * @param array $table_info Values to build the <table> tag
 * @param array $head_values Values to build the <th> tags
 * @param string $tbody_id id for <tbody>
 * @return string
 */
function handleSortOrder() {
	if (array_key_exists('sort_by', $_GET)) {
		$swap_direction = array('ASC' => 'DESC', 'DESC' => 'ASC');

		if (isset($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']]['sort_field']) &&
			$_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']]['sort_field'] != $_GET['sort_by']) {
			$sort_direction = $swap_direction[$_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']]['sort_direction']];
		} elseif (isset($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']]['sort_direction'])) {
			$sort_direction = $_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']]['sort_direction'];
		} else {
			$sort_direction = 'DESC';
		}
		$_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']] = array(
				'sort_field' => $_GET['sort_by'], 'sort_direction' => $swap_direction[$sort_direction]
			);
	}
	
	$temp_uri = str_replace(array('?sort_by=' . $_GET['sort_by'], '&sort_by=' . $_GET['sort_by']), '', $_SERVER['REQUEST_URI']);
	
	header('Location: ' . $temp_uri);
}


/**
 * Formats log data
 *
 * @since 1.2
 * @package facileManager
 *
 * @param string $strip Text to strip out
 * @param string $key Logging key
 * @param string $data Logging data
 * @return string
 */
function formatLogKeyData($strip, $key, $data) {
	return ucwords(str_replace('_', ' ', str_replace($strip, '', $key))) . ": $data\n";
}


?>
