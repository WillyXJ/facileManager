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

/**
 * Dummy function in case gettext is not installed
 *
 * @since 2.0
 * @package facileManager
 *
 * @param string $text Text to translate
 * @return string $text
 */
if (!function_exists('_')) {
	function _($text) {
		return $text;
	}
}

/**
 * Dummy function in case gettext is not installed
 *
 * @since 2.0
 * @package facileManager
 *
 * @param string $single_text Singular text to translate
 * @param string $plural_text Plural text to translate
 * @param integer $number Number to determine if its singular or plural
 * @return string $text
 */
if (!function_exists('ngettext')) {
	function ngettext($single_text, $plural_text, $number) {
		return $number == 1 ? $single_text : $plural_text;
	}
}

/**
 * Dummy function in case gettext is not installed
 *
 * @since 2.0
 * @package facileManager
 *
 * @param string $single_text Singular text to translate
 * @param string $plural_text Plural text to translate
 * @param integer $number Number to determine if its singular or plural
 * @return string $text
 */
if (!function_exists('dngettext')) {
	function dngettext($module, $single_text, $plural_text, $number) {
		return ngettext($single_text, $plural_text, $number);
	}
}

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
	
	/** fM Core */
	include(ABSPATH . 'fm-includes/version.php');
	
	$running_db_version = getOption('fm_db_version');
	
	/** If the record does not exist then run the installer */
	if (!$running_db_version) {
		header('Location: ' . $GLOBALS['RELPATH'] . 'fm-install.php');
		exit;
	}
	
	if ($running_db_version < $fm_db_version) return true;
	
	/** Module upgrades */
	$fmdb->get_results("SELECT module_name,option_value FROM fm_options WHERE option_name='version'");
	for ($x=0; $x<$fmdb->num_rows; $x++) {
		$module_name = $fmdb->last_result[$x]->module_name;
		include(ABSPATH . 'fm-modules/' . $module_name . '/variables.inc.php');
		if (version_compare($fmdb->last_result[$x]->option_value, $__FM_CONFIG[$module_name]['version'], '<')) return true;
	}
	
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
	if (!getOption('software_update')) return false;
	
	/** Disable check until user has upgraded database to 1.2 */
	if (getOption('fm_db_version') < 32) return false;
	
	/** Should we be running this check now? */
	$last_version_check = getOption('version_check', 0, $package);
	if (!$software_update_interval = getOption('software_update_interval')) $software_update_interval = 'week';
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
		
		setOption('version_check', array('timestamp' => date("Y-m-d H:i:s"), 'data' => $result), $method, true, 0, $package);
		
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
function printHeader($subtitle = 'auto', $css = 'facileManager', $help = false, $menu = true) {
	global $fm_name, $__FM_CONFIG;
	
	include(ABSPATH . 'fm-includes/version.php');
	
	$title = $fm_name;
	
	if (!empty($subtitle)) {
		if ($subtitle == 'auto') $subtitle = getPageTitle();
		$title = "$subtitle &lsaquo; $title";
	}
	
	$head = $logo = null;
	
	if ($css == 'facileManager') {
		$head = $menu ? getTopHeader($help) : null;
	} else {
		$logo = '<h1 class="center"><img alt="' . $fm_name . '" src="' . $GLOBALS['RELPATH'] . 'fm-includes/images/logo.png" /></h1>' . "\n";
	}
	
	/** Module css and js includes */
	if (isset($_SESSION['module'])) {
		$module_css_file = 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'module.css';
		$module_css = (file_exists(ABSPATH . $module_css_file) && array_key_exists($_SESSION['module'], $__FM_CONFIG)) ? '<link rel="stylesheet" href="' . $GLOBALS['RELPATH'] . $module_css_file . '?ver=' . $__FM_CONFIG[$_SESSION['module']]['version'] . '" type="text/css" />' : null;
		$module_js_file = 'fm-modules' . DIRECTORY_SEPARATOR . $_SESSION['module'] . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'module.php';
		$module_js = (file_exists(ABSPATH . $module_js_file) && array_key_exists($_SESSION['module'], $__FM_CONFIG)) ? '<script src="' . $GLOBALS['RELPATH'] . $module_js_file . '?ver=' . $__FM_CONFIG[$_SESSION['module']]['version'] . '" type="text/javascript" charset="utf-8"></script>' : null;
	} else {
		$module_css = $module_js = null;
	}
	
	echo <<<HTML
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title>$title</title>
		<link rel="shortcut icon" href="{$GLOBALS['RELPATH']}fm-modules/$fm_name/images/favicon.png" />
		<link rel="stylesheet" href="{$GLOBALS['RELPATH']}fm-modules/$fm_name/css/$css.css?ver=$fm_version" type="text/css" />
		<link rel="stylesheet" href="{$GLOBALS['RELPATH']}fm-includes/extra/jquery-ui-1.10.2.min.css" />
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css">
		<link href="{$GLOBALS['RELPATH']}fm-includes/extra/open-sans.css" rel="stylesheet" type="text/css">
		<script src="{$GLOBALS['RELPATH']}fm-includes/js/jquery-1.9.1.min.js"></script>
		<script src="{$GLOBALS['RELPATH']}fm-includes/js/jquery-ui-1.10.2.min.js"></script>
		<script src="{$GLOBALS['RELPATH']}fm-includes/extra/select2/select2.min.js" type="text/javascript"></script>
		<link rel="stylesheet" href="{$GLOBALS['RELPATH']}fm-includes/extra/select2/select2.css?ver=$fm_version" type="text/css" />
		$module_css
		<script src="{$GLOBALS['RELPATH']}fm-modules/$fm_name/js/$fm_name.php?ver=$fm_version" type="text/javascript" charset="utf-8"></script>
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
function printFooter($classes = null, $text = null, $block_style = null) {
	echo <<<FOOT
	</div>
<div class="manage_form_container" id="manage_item" $block_style></div>
<div class="manage_form_contents $classes" id="manage_item_contents" $block_style>
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
	global $fm_login, $__FM_CONFIG;
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

			$star = currentUserCan('do_everything') ? $__FM_CONFIG['icons']['star'] . ' ' : null;
			$change_pwd_link = ($auth_method) ? sprintf('<li><a class="account_settings" id="%s" href="#"><span>%s</span></a></li>' . "\n", $_SESSION['user']['id'], _('Edit Profile')) : null;
			$logout = _('Logout');
			$user_account_menu = <<<HTML
		<div id="topheadpartright" style="padding: 0 1px 0 0;">
			<div id="cssmenu">
			<ul>
				<li class="has-sub has-image"><a href="#"><span>{$__FM_CONFIG['icons']['account']}</span></a>
					<ul class="sub-right">
						<li class="text-only"><span>$star{$_SESSION['user']['name']}</span></li>
						$change_pwd_link
						<li class="last"><a href="{$GLOBALS['RELPATH']}?logout"><span>$logout</span></a></li>
					</ul>
				</li>
			</ul>
			</div>
		</div>
HTML;
		}
		
		/** Build app dropdown menu */
		$modules = getAvailableModules();
		$avail_modules = null;
		
		if (count($modules)) {
			foreach ($modules as $module_name) {
				if ($module_name == $_SESSION['module']) continue;
				if (in_array($module_name, getActiveModules(true))) {
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
				list($module_toolbar_left, $module_toolbar_right) = @buildModuleToolbar();
			}
		} else {
			$module_menu = null;
			$fm_name = isset($_SESSION['module']) ? $_SESSION['module'] : $fm_name;
		}
	
		$help_file = buildHelpFile();
		$help_text = _('Help');
	
		$process_all_text = _('Process all available updates now');
		$process_all = <<<HTML
		<div id="topheadpartright" style="display: none;">
			<a class="single_line process_all_updates" href="#" title="$process_all_text"><i class="fa fa-refresh fa-lg"></i></a>
			<span class="update_count"></span>
		</div>
HTML;
	
		if (FM_INCLUDE_SEARCH === true) {
			$search = '<div id="topheadpartright">
			<a class="single_line search" href="#" title="' . _('Search this page') . '"><i class="fa fa-search fa-lg"></i></a>' .
				displaySearchForm() . '</div>';
		} else $search = null;

		$return = <<<HTML
	<div id="tophead">
		<div id="topheadpart">
			<img src="fm-modules/$fm_name/images/fm.png" alt="$fm_name" title="$fm_name" />
			$fm_name<br />
			v$fm_version
		</div>
$account_menu
$module_toolbar_left
$user_account_menu
		<div id="topheadpartright">
			<a class="single_line help_link" href="#">$help_text</a>
		</div>
$module_menu
$module_toolbar_right
$search
$process_all
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
function printMenu() {
	global $__FM_CONFIG, $menu, $submenu;
	
	$main_menu_html = null;
	
	list($filtered_menu, $filtered_submenu) = getCurrentUserMenu();
	ksort($filtered_menu);
	ksort($filtered_submenu);
	
	$i = 1;
	foreach ($filtered_menu as $position => $main_menu_array) {
		$sub_menu_html = '</li>';
		$show_top_badge_count = true;
		
		list($menu_title, $page_title, $capability, $module, $slug, $classes, $badge_count) = $main_menu_array;
		if (!is_array($classes)) {
			$classes = !empty($classes) ? array_fill(0, 1, $classes) : array();
		}
		
		/** Check if menu item is current page */
		if ($slug == findTopLevelMenuSlug($filtered_submenu)) {
			array_push($classes, 'current', 'arrow');
			
			if (array_key_exists($slug, $filtered_submenu)) {
				$show_top_badge_count = false;
				$k = 0;
				foreach ($filtered_submenu[$slug] as $submenu_array) {
					if (!empty($submenu_array[0])) {
						$submenu_class = ($submenu_array[4] == $GLOBALS['basename']) ? ' class="current"' : null;
						if ($submenu_array[6]) $submenu_array[0] = sprintf($submenu_array[0] . ' <span class="menu_badge"><p>%d</p></span>', $submenu_array[6]);
						$sub_menu_html .= sprintf('<li%s><a href="%s">%s</a></li>' . "\n", $submenu_class, $submenu_array[4], $submenu_array[0]);
					} elseif (!$k) {
						$show_top_badge_count = true;
					}
					$k++;
				}
				
				$sub_menu_html = <<<HTML
					</li>
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
		
		/** Build submenus */
		if (!count($classes) && count($filtered_submenu[$slug]) > 1) {
			array_push($classes, 'has-sub');
			foreach ($filtered_submenu[$slug] as $submenu_array) {
				if (!empty($submenu_array[0])) {
					if ($submenu_array[6]) $submenu_array[0] = sprintf($submenu_array[0] . ' <span class="menu_badge"><p>%d</p></span>', $submenu_array[6]);
					$sub_menu_html .= sprintf('<li><a href="%s">%s</a></li>' . "\n", $submenu_array[4], $submenu_array[0]);
				}
			}
			
			$sub_menu_html = <<<HTML
				<div class="arrow"></div>
				<ul>
				$sub_menu_html
				</ul>
</li>

HTML;
		}
		
		$arrow = (in_array('arrow', $classes)) ? '<u></u>' : null;
		
		/** Join all of the classes */
		if (count($classes)) $class = ' class="' . implode(' ', $classes) . '"';
		else $class = null;
		
		if (empty($slug) && !empty($class)) {
			/** Ideally this should be the separator */
			if ($i != count($filtered_menu)) {
				$main_menu_html .= '<li' . $class . '></li>' . "\n";
			}
		} else {
			/** Display the menu item if allowed */
			if (currentUserCan($capability, $module)) {
				if ($badge_count && $show_top_badge_count) $menu_title = sprintf($menu_title . ' <span class="menu_badge"><p>%d</p></span>', $badge_count);
				$main_menu_html .= sprintf('<li%s><a href="%s">%s</a>%s%s' . "\n", $class, $slug, $menu_title, $arrow, $sub_menu_html);
			}
		}
		
		$i++;
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
 * Removes non-permitted menu items
 *
 * @since 1.2
 * @package facileManager
 *
 * @param array $element Menu array to check.
 * @return bool
 */
function filterMenu($element) {
	return currentUserCan($element[2], $element[3]);
}


/**
 * Finds the top level menu for selection
 *
 * @since 1.2
 * @package facileManager
 *
 * @param array $menu_array Menu array to search.
 * @return string
 */
function findTopLevelMenuSlug($menu_array) {
	foreach ($menu_array as $slug => $menu_items) {
		foreach ($menu_items as $element) {
			if (array_search($GLOBALS['basename'], $element, true)) {
				return $slug;
			}
		}
	}
	
	return $GLOBALS['basename'];
}


/**
 * Gets the user menu based on capabilities
 *
 * @since 1.2
 * @package facileManager
 *
 * @return array
 */
function getCurrentUserMenu() {
	global $menu, $submenu;
	
	$filtered_menus = array(null, null);
	
	/** Submenus */
	foreach ($submenu as $slug => $submenu_array) {
		ksort($submenu_array);
		$filtered_menus[1][$slug] = array_filter($submenu_array, 'filterMenu');
	}
	
	/** Main menu */
	$temp_menu = $menu;
	foreach ($menu as $position => $element) {
		list($menu_title, $page_title, $capability, $module, $slug, $class) = $element;
		if (array_key_exists($slug, $filtered_menus[1])) {
			if (count($filtered_menus[1][$slug]) == 1) {
				$single_element = array_values($filtered_menus[1][$slug]);
				if (!empty($single_element[0][0])) {
					$temp_menu[$position] = array_shift($filtered_menus[1][$slug]);
					if (isset($element[7]) && $element[7]) $temp_menu[$position][0] = $menu_title;
				}
			}
		}
	}
	
	$filtered_menus[0] = array_filter($temp_menu, 'filterMenu');
	
	unset($temp_menu, $element, $submenu_array, $slug, $position, $single_element);

	return $filtered_menus;
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
function basicGetList($table, $id = 'id', $prefix = '', $sql = null, $limit = null, $ip_sort = false, $direction = 'ASC', $count_only = false) {
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
		$secondary_fields = ' ' . $direction . sanitize(substr($secondary_fields, strlen($primary_field)));
	} else {
		$primary_field = sanitize($id);
		$secondary_fields = null;
	}
	
	if ($ip_sort) {
		$sort = "ORDER BY INET_ATON(`$primary_field`)" . $secondary_fields;
	} else {
		$sort = "ORDER BY `$primary_field`" . $secondary_fields;
	}
	
	$disp_query = 'SELECT ';
	$disp_query .= $count_only ? 'COUNT(*) count' : '*';
	$disp_query .= " FROM `$table` WHERE `{$prefix}status`!='deleted' AND account_id='{$_SESSION['user']['account_id']}' $sql $sort $direction $limit";
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
	
	$query = "UPDATE `$table` SET `{$prefix}status`='" . sanitize($status) . "' WHERE account_id='{$_SESSION['user']['account_id']}' AND `$field`=" . sanitize($id);

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
	
	$query = "SHOW COLUMNS FROM $tbl_name LIKE '$column_name'";
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
function buildSelect($select_name, $select_id, $options, $option_select = null, $size = '1', $disabled = '', $multiple = false, $onchange = null, $classes = null, $placeholder = null) {
	if (!$placeholder) $placeholder = _('Select an option');
	$type_options = null;
	if (countArrayDimensions($options) == 3) {
		foreach ($options as $optgroup => $optarray) {
			if (is_string($optgroup)) $type_options .= '<optgroup label="' . $optgroup . '">';
			for ($i = 0; $i < count($optarray); $i++) {
				$selected = null;
				if (is_array($option_select)) {
					foreach ($option_select as $key) {
						if (isset($key) && $key == $optarray[$i][1]) {
							$selected = ' selected';
							break;
						}
					}
				} elseif (isset($option_select) && (string)$option_select === (string)$optarray[$i][1]) {
					$selected = ' selected';
				}
				$type_options.="<option$selected value=\"{$optarray[$i][1]}\">{$optarray[$i][0]}</option>\n";
			}
			if (is_string($optgroup)) $type_options .= '</optgroup>';
		}
	} elseif (countArrayDimensions($options) == 2) {
		for ($i = 0; $i < count($options); $i++) {
			$selected = null;
			if (is_array($option_select)) {
				foreach ($option_select as $key) {
					if (isset($key) && $key == $options[$i][1]) {
						$selected = ' selected';
						break;
					}
				}
			} elseif (isset($option_select) && (string)$option_select === (string)$options[$i][1]) {
				$selected = ' selected';
			}
			$type_options.="<option$selected value=\"{$options[$i][1]}\">{$options[$i][0]}</option>\n";
		}
	} else {
		for ($i = 0; $i < count($options); $i++) {
			$selected = ($option_select == $options[$i]) ? ' selected' : '';
			$type_options.="<option$selected>$options[$i]</option>\n";
		}
	}
	$build_select = "<select class=\"$classes\" data-placeholder=\"$placeholder\" ";
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
function getNameFromID($id, $table, $prefix, $field, $data, $account_id = null, $status = null) {
	global $fmdb;
	
	if (!$account_id) {
		$account_id = $_SESSION['user']['account_id'];
	}
	
	basicGet($table, $id, $prefix, $field, $status, $account_id);
	if ($fmdb->num_rows) {
		$result = $fmdb->last_result;
		if (isset($result[0]->$data)) return $result[0]->$data;
	}
	
	return false;
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
			$html_checks = sprintf('<p>%s</p>', _('You have no modules installed.'));
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
			$body = sprintf('<div class="fm-table"><div>%s</div></div>', @buildModuleDashboard());
		} else {
			$body = sprintf('<p>%s</p>', _('You have no modules installed.'));
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
<div id="issue_tracker">
	<p>Have an idea for a new feature? Find a bug? Submit a report with the <a href="https://github.com/WillyXJ/facileManager/issues" target="_blank">issue tracker</a>.</p>
</div>
<h3>$fm_name</h3>
<ul>
	<li>
		<a class="list_title">Configure Modules</a>
		<div>
			<p>Modules are what gives $fm_name purpose. They can be installed, activated, upgraded, deactivated, and uninstalled.</p>
			
			<p><b>Install</b><br />
			Just extract the module into the 'fm-modules' directory on the server host (if not already present), go to 
			<a href="__menu{Modules}">Modules</a>, and then click the 'Install' button next to the module 
			you wish to install.</p>
			<p><i>The 'Module Management' or 'Super Admin' permission is required for this action.</i></p>
			<p><b>Activate</b><br />
			In order for the module to be usable, it needs to be active in the UI.</p>
			<p>Go to <a href="__menu{Modules}">Modules</a> and click the 'Activate' link next 
			to the module you wish to activate.</p>
			<p><i>The 'Module Management' or 'Super Admin' permission is required for this action.</i></p>
			<p><b>Upgrade</b><br />
			Anytime module files are individually updated in the 'fm-modules' directory on the server host apart from updating $fm_name 
			as a whole, they will need to be upgraded to ensure full compatibility and functionality.</p>
			<p>Go to <a href="__menu{Modules}">Modules</a> and click the 'Upgrade' button next 
			to the module you wish to upgrade. This will upgrade the database with any required changed.</p>
			<p><i>The 'Module Management' or 'Super Admin' permission is required for this action.</i></p>
			<p><b>Deactivate</b><br />
			If you no longer want a module to be usable, it can be deactived in the UI.</p>
			<p>Go to <a href="__menu{Modules}">Modules</a> and click the 'Deactivate' link next 
			to the module you wish to deactivate.</p>
			<p><i>The 'Module Management' or 'Super Admin' permission is required for this action.</i></p>
			<p><b>Uninstall</b><br />
			If you no longer want a module to be installed, it can be uninstalled via the UI.</p>
			<p>Go to <a href="__menu{Modules}">Modules</a>, ensure the module is already 
			deactivated, and then click the 'Uninstall' button next to the module you wish to remove. This will remove all associated 
			entries and tables from the database.</p>
			<p><i>The 'Module Management' or 'Super Admin' permission is required for this action.</i></p>
		</div>
	</li>
	<li>
		<a class="list_title">Manage Users</a>
		<div>
			<p>$fm_name incorporates the use of multiple user accounts with granular permissions. This way you can limit access to your 
			environment.</p>
			
			<p>You can add, modify, and delete user accounts at Admin &rarr; <a href="__menu{Users}">Users</a>.</p>
			
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
				This permission grants the user access to run the various tools in Admin &rarr; <a href="__menu{Tools}">Tools</a>.</li>
				<li><b>Manage Settings</b><br />
				This permission grants the user access to change system settings at Settings &rarr; <a href="__menu{Settings}">General</a>.</li>
			</ul>
			
			<p>New user accounts can be created quickly from a template by duplicating the template user. This will prompt you for the new 
			username and password while giving you the ability to change any other settings prior to user creation.</p>
			<p><i>The 'User Management' or 'Super Admin' permission is required for these actions.</i></p>
		</div>
	</li>
	<li>
		<a class="list_title">Manage Settings</a>
		<div>
			<p>There are several settings available to set at Settings &rarr; <a href="__menu{Settings}">General</a>.</p>
			<p><i>The 'Manage Settings' or 'Super Admin' permission is required to change settings.</i></p>
			<p><b>Authentication</b><br />
			There are three types of authentication supported by $fm_name:</p>
			<ul>
				<li><b>None</b><br />
				Every user will be automatically logged in as the default super-admin account that was created during the installation process.</li>
				<li><b>Built-in Authentication</b><br />
				Authenticates against the $fm_name database using solely the users defined at Admin &rarr; <a href="__menu{Users}">Users</a>.</li>
				<li><b>LDAP Authentication</b><br />
				Users are authenticated against a defined LDAP server. Upon success, users are created in the $fm_name database using the selected 
				template account for granular permissions within the environment. If no template is selected then user authentication will fail 
				(this is another method of controlling access to $fm_name). These users cannot be disabled nor can their passwords be changed 
				within $fm_name. The PHP LDAP extensions have to be installed before this option is available.</li>
			</ul>
			<p><i>You can reset the authentication method by setting the following in config.inc.php:</i></p>
			<p><i>define('FM_NO_AUTH', true);</i></p>
			<p><b>Client Registration</b><br />
			You can choose to allow clients to automatically register in the database or not.</p>
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
			<p><b>SSH Username</b><br />
			When servers are configured to receive updates via SSH, this username will be created (if not already present) on your clients
			and will be used for the client interaction.</p>
			<p><b>SSH Key Pair</b><br />
			In order for client configs to be updated via SSH, $fm_name needs a 2048-bit passwordless key pair generated. Without this key pair, 
			clients cannot use the SSH update method. Click the 'Generate' button to have $fm_name automatically generate the necessary key pair.</p>
		</div>
	</li>
	<li>
		<a class="list_title">Review Logs</a>
		<div>
			<p>Every action performed within the $fm_name UI will be logged for auditing purposes.</p>
			<p>You can view and search the logs at Admin &rarr; <a href="__menu{Logs}">Logs</a></p>
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
			$body .= sprintf('<p>%s</p>', _('You have no modules installed.'));
		}
	}

	return parseMenuLinks($body) . '<br />';
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
	
	$insert = "INSERT INTO `{$__FM_CONFIG['db']['name']}`.`fm_logs` VALUES (NULL, $user_id, $account_id, '$module', " . time() . ", '" . sanitize($log_data) . "')";
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
	
	$modules = null;
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
	
	return array();
}

/**
 * Returns an option value
 *
 * @since 1.0
 * @package facileManager
 */
function getOption($option = null, $account_id = 0, $module_name = null) {
	global $fmdb;
	
	$module_sql = ($module_name) ? "AND module_name='$module_name'" : null;

	$query = "SELECT * FROM fm_options WHERE option_name='$option' AND account_id=$account_id $module_sql LIMIT 1";
	$fmdb->get_results($query);
	
	if ($fmdb->num_rows) {
		$results = $fmdb->last_result;
		
		if (isSerialized($results[0]->option_value)) {
			return unserialize($results[0]->option_value);
		}
		
		return $results[0]->option_value;
	}
	
	return false;
}


/**
 * Sets an option value
 *
 * @since 1.0
 * @package facileManager
 */
function setOption($option = null, $value = null, $insert_update = 'auto', $auto_serialize = true, $account_id = 0, $module_name = null) {
	global $fmdb;
	
	if ($auto_serialize) {
		$value = isSerialized($value) ? sanitize($value) : serialize($value);
	} else $value = sanitize($value);
	$option = sanitize($option);
	
	$module_sql = ($module_name) ? "AND module_name='$module_name'" : null;
	
	if ($insert_update == 'auto') {
		$query = "SELECT * FROM fm_options WHERE option_name='$option' AND account_id=$account_id $module_sql";
		$result = $fmdb->query($query);
		$insert_update = ($fmdb->num_rows) ? 'update' : 'insert';
	}
	
	if ($insert_update == 'insert') {
		$keys = array('account_id', 'option_name', 'option_value');
		$values = array($account_id, $option, $value);
		if ($module_name) {
			$keys[] = 'module_name';
			$values[] = $module_name;
		}
		$query = "INSERT INTO fm_options (" . implode(',', $keys) . ") VALUES ('" . implode("','", $values) . "')";
	} else {
		$query = "UPDATE fm_options SET option_name='$option', option_value='$value' WHERE option_name='$option' AND account_id=$account_id $module_sql";
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
		
		$user_capabilities = getUserCapabilities($_SESSION['user']['id'], 'all');
		$excluded_modules = array();
		foreach ($modules as $module_name) {
			if (!array_key_exists($module_name, $user_capabilities) && !currentUserCan('do_everything')) $excluded_modules[] = $module_name;
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
DELETE FROM $database.`fm_options` WHERE `module_name` = '$module';
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
	
	/** Delete entries from fm_options */
	$query = "DELETE FROM `{$__FM_CONFIG['db']['name']}`.`fm_options` WHERE `module_name`='{$module}'";
	$fmdb->query($query);
	
	/** Delete capability entries from fm_users */
	$query = "SELECT * FROM `{$__FM_CONFIG['db']['name']}`.`fm_users`";
	$fmdb->query($query);
	$count = $fmdb->num_rows;
	$result = $fmdb->last_result;
	for ($i=0; $i<=$count; $i++) {
		$current_caps = isSerialized($result[$i]->user_caps) ? unserialize($result[$i]->user_caps) : $result[$i]->user_caps;
		if (array_key_exists($module, $current_caps)) {
			unset($current_caps[$module]);
			$fmdb->query("UPDATE `{$__FM_CONFIG['db']['name']}`.`fm_users` SET user_caps='" . serialize($current_caps) . "' WHERE user_id=" . $result[$i]->user_id);
			if (!$fmdb->rows_affected) return false;
		}
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

	$next = sprintf('<a href="?%s&date=%s">%s</a>', $uri, $next_date, _('next &rarr;'));
	$previous = sprintf('<a href="?%s&date=%s">%s</a>', $uri, $previous_date, _('&larr; previous'));
	
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
 * @param string $classes Additional classes to apply to the div
 * @return string
 */
function displayPagination($page, $total_pages, $addl_blocks = null, $classes = null) {
	global $fmdb;
	
	$page_params = null;
	foreach ($GLOBALS['URI'] as $key => $val) {
		if (!$key || $key == 'p') continue;
		$page_params .= $key . '=' . $val . '&';
	}
	
	if ($page < 1) {
		$page = 1;
	}
	if ($page > $total_pages) {
		$page = $total_pages;
	}
	
	$page_links = array();
	$page_links[] = '<div id="pagination_container">';
	$page_links[] = '<div>';
	if (isset($addl_blocks)) {
		if (is_array($addl_blocks)) {
			foreach ($addl_blocks as $block) {
				$page_links[] = '<div>' . $block . '</div>';
			}
		} else {
			$page_links[] = '<div>' . $addl_blocks . '</div>';
		}
	}
	$page_links[] = buildPaginationCountMenu(0, 'pagination');

	$page_links[] = '<div id="pagination" class="' . $classes . '">';
	$page_links[] = '<form id="pagination_search" method="GET" action="' . $GLOBALS['basename'] . '?' . $page_params . '">';
	$page_links[] = sprintf('<span>%s</span>', sprintf(ngettext('%d item', '%d items', $fmdb->num_rows), $fmdb->num_rows));

	/** Previous link */
	if ($page > 1 && $total_pages > 1) {
		$page_links[] = '<a href="' . $GLOBALS['basename'] . "?{$page_params}p=1\">&laquo;</a>";
		$page_links[] = '<a href="' . $GLOBALS['basename'] . "?{$page_params}p=" . ($page - 1) . '">&lsaquo;</a>';
	}
	
	/** Page number */
	$page_links[] = '<input id="paged" type="text" value="' . $page . '" /> of ' . $total_pages;
	
	/** Next link */
	if ($page < $total_pages) {
		$page_links[] = '<a href="' . $GLOBALS['basename'] . "?{$page_params}p=" . ($page + 1) . '">&rsaquo;</a>';
		$page_links[] = '<a href="' . $GLOBALS['basename'] . "?{$page_params}p=" . $total_pages . '">&raquo;</a>';
	}

	$page_links[] = '</form>';
	$page_links[] = '</div>';
	$page_links[] = '</div>';
	$page_links[] = '</div>';
	
	return join("\n", $page_links);
}


/**
 * Builds the server listing in a dropdown menu
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmDNS
 */
function buildPaginationCountMenu($server_serial_no = 0, $class = null) {
	global $fmdb, $__FM_CONFIG;
	
	$record_count = buildSelect('rc', 'rc', $__FM_CONFIG['limit']['records'], $_SESSION['user']['record_count'], 1, null, false, 'this.form.submit()');
	
	$hidden_inputs = null;
	foreach ($GLOBALS['URI'] as $param => $value) {
		if ($param == 'rc') continue;
		$hidden_inputs .= '<input type="hidden" name="' . $param . '" value="' . $value . '" />' . "\n";
	}
	
	$class = $class ? 'class="' . $class . '"' : null;

	$return = sprintf('<div id="configtypesmenu" %s>
		<form action="%s" method="GET">
		%s
		%s %s
		</form>
	</div>',
			$class, $GLOBALS['basename'], $hidden_inputs,
			$record_count, _('items per page')
		);

	return $return;
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
function bailOut($message, $title = null) {
	global $fm_name;
	
	$branding_logo = $GLOBALS['RELPATH'] . 'fm-modules/' . $fm_name . '/images/fm.png';

	if (!$title) $title = _('Requirement Error');
	printHeader($title, 'install');
	printf('<div id="fm-branding">
		<img src="%s" /><span>%s</span>
	</div>
	<div id="window"><p>%s</p></div>', $branding_logo, $title, $message);
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
function displayProgress($step, $result, $process = 'noisy', $error = null) {
	global $fmdb;
	
	if ($result == true) {
		$output = '<i class="fa fa-check fa-lg"></i>';
		$status = 'success';
	} else {
		global $fmdb;
		
		if (!$error) {
			$error = is_object($fmdb) ? $fmdb->last_error : mysql_error();
		}
		if ($error) {
			$output = '<a href="#" class="error-message tooltip-right" data-tooltip="' . $error . '"><i class="fa fa-times fa-lg"></i></a>';
		} else {
			$output = '<i class="fa fa-times fa-lg"></i>';
		}
		$status = 'failed';
	}
	
	$message = <<<HTML
	<tr>
		<th>$step</th>
		<td class="status $status">$output</td>
	</tr>

HTML;

	if ($process == 'noisy') {
		echo $message;
		return $result;
	} elseif ($process == 'display') {
		return $message;
	} else return $result;
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
	
	$query = "SHOW COLUMNS FROM $tbl_name LIKE '$column_name'";
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
	
	$os_name = array('openSUSE', 'Raspberry Pi');
	$os_image = array('SUSE', 'RaspberryPi');
	
	$os = file_exists(ABSPATH . 'fm-modules/' . $fm_name . '/images/os/' . str_replace($os_name, $os_image, $server_os) . '.png') ? $server_os : 'unknown';
	$os_image = '<img src="fm-modules/' . $fm_name . '/images/os/' . str_replace($os_name, $os_image, $os) . '.png" border="0" alt="' . $os . '" title="' . $os . '" width="18" />';
	
	return $os_image;
}


/**
 * Displays the page header
 *
 * @since 1.0
 * @package facileManager
 *
 * @param string $response Page form response
 * @param string $title The page title
 * @param bool $allowed_to_add Whether the user can add new
 * @param string $name Name value of plus sign
 * @param string $rel Rel value of plus sign
 * @return string
 */
function printPageHeader($response = null, $title = null, $allowed_to_add = false, $name = null, $rel = null) {
	global $__FM_CONFIG;
	
	if (empty($title)) $title = getPageTitle();
	
	echo '<div id="body_container">' . "\n";
	if (!empty($response)) echo '<div id="response"><p class="error">' . $response . "</p></div>\n";
	else echo '<div id="response" style="display: none;"></div>' . "\n";
	echo '<h2>' . $title;
	
	if ($allowed_to_add) {
		echo displayAddNew($name, $rel);
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
function setBuildUpdateConfigFlag($serial_no = null, $flag, $build_update, $__FM_CONFIG = null) {
	global $fmdb, $fm_dns_zones;
	
	if (!$__FM_CONFIG) global $__FM_CONFIG;
	
	$serial_no = sanitize($serial_no);
	/** Process server group */
	if ($serial_no[0] == 'g') {
		global $fm_shared_module_servers;
		
		$group_servers = $fm_shared_module_servers->getGroupServers(substr($serial_no, 2));

		if (!is_array($group_servers)) return false;

		setBuildUpdateConfigFlag(implode(',', $group_servers), $flag, $build_update, $__FM_CONFIG);

		return true;
	}

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
function getBadgeCounts($type) {
	global $fm_name;
	
	$badge_count = 0;
	
	if (!defined('INSTALL') && !defined('UPGRADE')) {
		if ($type == 'modules') {
			/** Get fM badge counts */
			$modules = getAvailableModules();
			foreach ($modules as $module_name) {
				/** Include module variables */
				@include(ABSPATH . 'fm-modules/' . $module_name . '/variables.inc.php');
				
				/** Upgrades waiting */
				$module_version = getOption('version', 0, $module_name);
				if ($module_version !== false) {
					if (version_compare($module_version, $__FM_CONFIG[$module_name]['version'], '<')) {
						$badge_count++;
						continue;
					}
				} else {
					$module_version = $__FM_CONFIG[$module_name]['version'];
				}
				
				/** New versions available */
				if (isNewVersionAvailable($module_name, $module_version)) $badge_count++;
			}
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
		$bulk_actions[] = null;
		
		return buildSelect($id, 'bulk_action', array_merge($bulk_actions, $bulk_actions_list), null, 1, '', false, null, null, _('Bulk Actions')) . 
			'<input type="submit" name="bulk_apply" id="bulk_apply" value="' . _('Apply') . '" class="button" />' . "\n";
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


/**
 * Displays an error page message
 *
 * @since 1.2
 * @package facileManager
 *
 * @param string $message Text to display
 * @param string $link_display Show or Hide the page back link
 * @return string
 */
function fMDie($message = null, $link_display = 'show') {
	global $fm_name;
	
	$branding_logo = $GLOBALS['RELPATH'] . 'fm-modules/' . $fm_name . '/images/fm.png';

	if (!$message) $message = _('An unknown error occurred.');
	
	printHeader('Error', 'install', false, false);
	
	printf('<div id="fm-branding"><img src="%s" /><span>%s</span></div>
		<div id="window"><p>%s</p>', $branding_logo, _('Oops!'), $message);
	if ($link_display == 'show') echo '<p><a href="javascript:history.back();">' . _('&larr; Back') . '</a></p>';
	echo '</div>';
	
	exit;
}


/**
 * Displays an error page message
 *
 * @since 1.2
 * @package facileManager
 *
 * @param string $link_display Show or Hide the page back link
 * @return string
 */
function unAuth($link_display = 'show') {
	fMDie(_('You do not have permission to view this page. Please contact your administrator for access.'), $link_display);
}


/**
 * Whether current user has capability
 *
 * @since 1.2
 * @package facileManager
 *
 * @param string $capability Capability name.
 * @param string $module Module name to check capability for
 * @param string $extra_perm Extra capability to check
 * @return boolean
 */
function currentUserCan($capability, $module = 'facileManager', $extra_perm = null) {
	return userCan($_SESSION['user']['id'], $capability, $module, $extra_perm);
}


/**
 * Whether a user has capability
 *
 * @since 1.2
 * @package facileManager
 *
 * @param integer $user_id User ID to check.
 * @param string|array $capability Capability name.
 * @param string $module Module name to check capability for
 * @param string $extra_perm Extra capability to check
 * @return boolean
 */
function userCan($user_id, $capability, $module = 'facileManager', $extra_perm = null) {
	global $fm_name;
	
	/** If no capability defined then return true */
	if ($capability === null) return true;
	
	/** If no authentication then return full access */
	if (!getOption('auth_method')) return true;
	
	/** Return true if group can */
	if ($group_id = getNameFromID($user_id, 'fm_users', 'user_', 'user_id', 'user_group')) {
		if (groupCan($group_id, $capability, $module, $extra_perm)) return true;
	}
	
	$user_capabilities = getUserCapabilities($user_id);
	
	return userGroupCan($user_id, $capability, $module, $extra_perm, $user_capabilities);
}


/**
 * Gets the user capabilities
 *
 * @since 1.2
 * @package facileManager
 *
 * @param integer $user_id User ID to retrieve.
 * @param string $type User, group, or all
 * @return array
 */
function getUserCapabilities($user_id, $type = 'user') {
	if ($type == 'all') {
		if ($group_id = getNameFromID($user_id, 'fm_users', 'user_', 'user_id', 'user_group')) {
			return getUserCapabilities($group_id, 'group');
		}
		$type = 'user';
	}
	$user_capabilities = getNameFromID($user_id, 'fm_' . $type . 's', $type . '_', $type . '_id', $type . '_caps');
	if (isSerialized($user_capabilities)) $user_capabilities = unserialize($user_capabilities);
	
	return $user_capabilities;
}


/**
 * Handles features defined in config.inc.php
 *
 * @since 1.2
 * @package facileManager
 */
function handleHiddenFlags() {
	global $fm_name;
	
	/** Recover authentication in case of lockout */
	if (defined('FM_NO_AUTH') && FM_NO_AUTH) {
		setOption('auth_method', 0);
		@addLogEntry(_('Manually reset authentication method.'), $fm_name);
	}
}


/**
 * Adds a menu item
 *
 * @since 1.2
 * @package facileManager
 *
 * @param string $menu_title Text used to display the menu item
 * @param string $page_title Text used to display the page title when the page loads
 * @param string $capability Minimum capability required for the menu item to be visible to the user
 * @param string $module Module name the menu item is for
 * @param string $menu_slug Menu item slug name to used to reference this item
 * @param string $class Class name to apply to the menu item
 * @param bool $sticky Whether or not to keep the menu title when there's only one submenu item or to take on the submenu item title
 * @param integer $position Menu position for the item
 * @param integer $badge_count Number of items to display in the badge
 */
function addMenuPage($menu_title, $page_title, $capability, $module, $menu_slug, $class = null, $sticky = false, $position = null, $badge_count = 0) {
	global $menu;
	
	$new_menu = array($menu_title, $page_title, $capability, $module, $menu_slug, $class, $badge_count, $sticky);
	
	if ($position === null) {
		$menu[] = $new_menu;
	} else {
		$menu[$position] = $new_menu;
	}
}


/**
 * Adds a menu item under the objects section
 *
 * @since 1.2
 * @package facileManager
 *
 * @param string $menu_title Text used to display the menu item
 * @param string $page_title Text used to display the page title when the page loads
 * @param string $capability Minimum capability required for the menu item to be visible to the user
 * @param string $module Module name the menu item is for
 * @param string $menu_slug Menu item slug name to used to reference this item
 * @param string $class Class name to apply to the menu item
 * @param bool $sticky Whether or not to keep the menu title when there's only one submenu item or to take on the submenu item title
 * @param integer $badge_count Number of items to display in the badge
 */
function addObjectPage($menu_title, $page_title, $capability, $module, $menu_slug, $class = null, $sticky = false, $badge_count = 0) {
	global $_fm_last_object_menu;
	
	$_fm_last_object_menu++;
	
	addMenuPage($menu_title, $page_title, $capability, $module, $menu_slug, $class, $sticky, $_fm_last_object_menu, $badge_count);
}


/**
 * Adds a submenu item
 *
 * @since 1.2
 * @package facileManager
 *
 * @param string $parent_slug The slug name for the parent menu
 * @param string $menu_title Text used to display the menu item
 * @param string $page_title Text used to display the page title when the page loads
 * @param string $capability Minimum capability required for the menu item to be visible to the user
 * @param string $module Module name the menu item is for
 * @param string $menu_slug Menu item slug name to used to reference this item
 * @param string $class Class name to apply to the menu item
 * @param integer $position Menu position for the item
 * @param integer $badge_count Number of items to display in the badge
 */
function addSubmenuPage($parent_slug, $menu_title, $page_title, $capability, $module, $menu_slug, $class = null, $position = null, $badge_count = 0) {
	global $submenu;
	
	$new_menu = array($menu_title, $page_title, $capability, $module, $menu_slug, $class, $badge_count);
	
	if ($position === null) {
		$submenu[$parent_slug][] = $new_menu;
	} else {
		$submenu[$parent_slug][$position] = $new_menu;
	}
	
	/** Update parent menu badge count */
	if ($badge_count) {
		global $menu;
		
		$parent_menu_key = getParentMenuKey($parent_slug);
		if ($parent_menu_key !== false) {
			$menu[$parent_menu_key][6] += $badge_count;
		}
	}
}


/**
 * Adds a submenu item to the Dashboard menu
 *
 * @since 1.2
 * @package facileManager
 *
 * @param string $menu_title Text used to display the menu item
 * @param string $page_title Text used to display the page title when the page loads
 * @param string $capability Minimum capability required for the menu item to be visible to the user
 * @param string $module Module name the menu item is for
 * @param string $menu_slug Menu item slug name to used to reference this item
 * @param string $class Class name to apply to the menu item
 * @param integer $position Menu position for the item
 * @param integer $badge_count Number of items to display in the badge
 */
function addDashboardPage($menu_title, $page_title, $capability, $module, $menu_slug, $class = null, $badge_count = 0, $position = null) {
	addSubmenuPage('index.php', $menu_title, $page_title, $capability, $module, $menu_slug, $class, $position, $badge_count);
}


/**
 * Adds a submenu item to the Admin menu
 *
 * @since 1.2
 * @package facileManager
 *
 * @param string $menu_title Text used to display the menu item
 * @param string $page_title Text used to display the page title when the page loads
 * @param string $capability Minimum capability required for the menu item to be visible to the user
 * @param string $module Module name the menu item is for
 * @param string $menu_slug Menu item slug name to used to reference this item
 * @param string $class Class name to apply to the menu item
 * @param integer $position Menu position for the item
 * @param integer $badge_count Number of items to display in the badge
 */
function addAdminPage($menu_title, $page_title, $capability, $module, $menu_slug, $class = null, $badge_count = 0, $position = null) {
	addSubmenuPage('admin-tools.php', $menu_title, $page_title, $capability, $module, $menu_slug, $class, $position, $badge_count);
}


/**
 * Adds a submenu item to the Settings menu
 *
 * @since 1.2
 * @package facileManager
 *
 * @param string $menu_title Text used to display the menu item
 * @param string $page_title Text used to display the page title when the page loads
 * @param string $capability Minimum capability required for the menu item to be visible to the user
 * @param string $module Module name the menu item is for
 * @param string $menu_slug Menu item slug name to used to reference this item
 * @param string $class Class name to apply to the menu item
 * @param integer $position Menu position for the item
 * @param integer $badge_count Number of items to display in the badge
 */
function addSettingsPage($menu_title, $page_title, $capability, $module, $menu_slug, $class = null, $badge_count = 0, $position = null) {
	addSubmenuPage('admin-settings.php', $menu_title, $page_title, $capability, $module, $menu_slug, $class, $position, $badge_count);
}


/**
 * Gets the page title from the menu item
 *
 * @since 1.2
 * @package facileManager
 *
 * @return string|bool
 */
function getPageTitle() {
	global $menu, $submenu;
	
	/** Search submenus first */
	foreach ($submenu as $slug => $submenu_items) {
		foreach ($submenu_items as $element) {
			if (array_search($GLOBALS['basename'], $element, true) !== false) {
				return $element[1];
			}
		}
	}
	
	/** Search menus */
	foreach ($menu as $position => $menu_items) {
		if (array_search($GLOBALS['basename'], $menu_items, true) !== false) {
			return $menu[$position][1];
		}
	}
	
	return false;
}


/**
 * Returns the top level menu key
 *
 * @since 1.2
 * @package facileManager
 *
 * @return integer|bool Returns the parent menu key or false if the menu item is not found
 */
function getParentMenuKey($search_slug = null) {
	global $menu, $submenu;
	
	if (!$search_slug) $search_slug = $GLOBALS['basename'];
	
	foreach ($menu as $position => $menu_items) {
		$parent_key = array_search($search_slug, $menu_items, true);
		if ($parent_key !== false) {
			return $position;
		}
	}
	
	foreach ($submenu as $parent_slug => $menu_items) {
		foreach ($menu_items as $element) {
			if (array_search($search_slug, $element, true) !== false) {
				return getParentMenuKey($parent_slug);
			}
		}
	}
	
	return false;
}


/**
 * Checks if max_input_vars has been exceeded
 *
 * @since 1.2
 * @package facileManager
 *
 * @return integer|bool Returns the number of input vars required or false if not exceeded
 */
function hasExceededMaxInputVars() {
	$max_input_vars = ini_get('max_input_vars') + 1;
	if ($max_input_vars == false) return false;
	
	$php_input = substr_count(file_get_contents('php://input'), '&');
	
	return $php_input > $max_input_vars ? $php_input : false;
}


/**
 * Checks if max_input_vars has been exceeded
 *
 * @since 1.2
 * @package facileManager
 */
function checkMaxInputVars() {
	if ($required_input_vars = hasExceededMaxInputVars()) {
		fMDie(sprintf(_('PHP max_input_vars (%1$d) has been reached and %2$s or more are required. Please increase the limit to fulfill this request. One method is to set the following in %3$s.htaccess:') .
			'<p><code>php_value max_input_vars %2$s</code></p>', ini_get('max_input_vars'), $required_input_vars, ABSPATH), true);
	}
}


/**
 * Checks if max_input_vars has been exceeded
 *
 * @since 1.2
 * @package facileManager
 *
 * @param array $checks_array Array of actions and capabilities
 * @param string $action Action to check
 *
 * @return bool
 */
function checkUserPostPerms($checks_array, $action) {
	if (array_key_exists($action, $checks_array)) {
		return currentUserCan($checks_array[$action], $_SESSION['module']);
	}
	
	return false;
}


/**
 * Checks if max_input_vars has been exceeded
 *
 * @since 1.2
 * @package facileManager
 *
 * @param string $user_default_module User's default module
 */
function setUserModule($user_default_module) {
	global $fm_name;
	
	$modules = @getActiveModules(true);
	if (@in_array($user_default_module, $modules)) {
		$_SESSION['module'] = $user_default_module;
	} else {
		$_SESSION['module'] = (is_array($modules) && count($modules)) ? $modules[0] : $fm_name;
	}
}


/**
 * Returns the menu item URL
 *
 * @since 1.2.3
 * @package facileManager
 *
 * @param string $search_slug Menu slug to query
 * @return integer|bool Returns the parent menu key or false if the menu item is not found
 */
function getMenuURL($search_slug = null) {
	global $menu, $submenu;
	
	if (!$search_slug) $search_slug = $GLOBALS['basename'];
	
	foreach ($menu as $position => $menu_items) {
		$parent_key = array_search($search_slug, $menu_items, true);
		if ($parent_key !== false) {
			return $menu[$position][4];
		}
	}
	
	foreach ($submenu as $parent_slug => $menu_items) {
		foreach ($menu_items as $submenu_id => $element) {
			if (array_search($search_slug, $element, true) !== false) {
				return $submenu[$parent_slug][$submenu_id][4];
			}
		}
	}
	
	return false;
}


/**
 * Builds the popup window
 *
 * @since 1.3
 * @package facileManager
 *
 * @param string $section Popup section to build (header or footer)
 * @param string $text Popup text to pass for header or buttons
 * @param array $buttons Buttons to include
 * @param string $link Link to provide for a button
 * @return string Returns the popup section
 */
function buildPopup($section, $text = null, $buttons = array('submit', 'cancel_button' => 'cancel'), $link = null) {
	global $__FM_CONFIG;
	
	if (!$text) $text = _('Save');
	
	if ($section == 'header') {
		return <<<HTML
		<div class="popup-header">
			{$__FM_CONFIG['icons']['close']}
			<h3>$text</h3>
		</div>
		<div class="popup-wait"><i class="fa fa-2x fa-spinner fa-spin"></i></div>
		<div class="popup-contents">

HTML;
	} elseif ($section == 'footer') {
		$id = array_search('submit', $buttons);
		if ($id !== false) {
			$id = !is_numeric($id) ? ' id="' . $id . '"' : null;
			$submit = '<input type="submit" name="submit" value="' . $text . '" class="button primary"' . $id . ' />';
		} else $submit = null;
		
		$id = array_search('cancel', $buttons);
		if ($id !== false) {
			$text = array_search('submit', $buttons) !== false ? _('Cancel') : $text;
			$id = is_numeric($id) ? 'cancel_button' : $id;
			if ($link !== null) {
				$cancel = '<a href="' . $link . '" class="button" id="' . $id . '">' . $text . '</a>';
			} else {
				$cancel = '<input type="button" value="' . $text . '" class="button ';
				$cancel .= count($buttons) > 1 ? 'left' : null;
				$cancel .= '" id="' . $id . '" />';
			}
		} else $cancel = null;
		
		return <<<HTML
		</div>
		<div class="popup-footer">
			$submit
			$cancel
		</div>

HTML;
	}
	
	return false;
}


/**
 * Parses the output for AJAX calls
 *
 * @since 1.3
 * @package facileManager
 *
 * @param string $output Output to parse for AJAX call
 * @return string Return for the AJAX call to display
 */
function parseAjaxOutput($output) {
	global $fmdb;
	
	$message_array['content'] = $output;
	if ($message_array['content'] !== true) {
		if (strpos($message_array['content'], "\n") !== false || isset($fmdb->last_error)) {
			unset($_POST);
			include_once(ABSPATH . 'fm-modules/facileManager/ajax/formatOutput.php');
		} else {
			echo $message_array['content'];
		}
	} else {
		echo _('Success');
	}
}


/**
 * Parses the output for AJAX calls
 *
 * @since 1.3
 * @package facileManager
 *
 * @param string $html HTML to set menu links in
 * @return string Parsed output
 */
function parseMenuLinks($html) {
	$string = preg_replace("/__menu{(.+?)}/esim", "getMenuURL('\\1')", $html);
	return $string;
}


/**
 * Gets the count for servers requiring a config build
 *
 * @since 2.0
 * @package facileManager
 *
 * @return integer Record count
 */
function countServerUpdates() {
	global $fmdb, $__FM_CONFIG;
	
	if (currentUserCan('manage_servers', $_SESSION['module'])) {
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_', 'AND (server_build_config!="no" OR server_client_version!="' . getOption('client_version', 0, $_SESSION['module']) . '") AND server_status="active" AND server_installed="yes"', null, false, null, true);
		if ($fmdb->num_rows) return $fmdb->last_result[0]->count;
	}
			
	return 0;
}


/**
 * Builds and displays the search form
 *
 * @since 2.0
 * @package facileManager
 *
 * @param string $page_params Current page parameters in URI
 * @return string Search form
 */
function displaySearchForm($page_params = null) {
	if (isset($_GET['q'])) {
		$placeholder = sprintf(_('Searched for %s'), sanitize($_GET['q']));
		$search_remove = '<div class="search_remove">
			<i class="fa fa-remove fa-lg"></i>
		</div>';
	} else {
		$placeholder = _('Search this page by keyword');
		$search_remove = null;
	}
	
	$form = <<<HTML
	<div id="search_form_container">
		<div>
			<div class="search_icon">
				<i class="fa fa-search fa-lg"></i>
			</div>
			<div id="search_form">
				<form id="search" method="GET" action="{$GLOBALS['basename']}?{$page_params}">
					<input type="text" placeholder="$placeholder" />
				</form>
			</div>
			$search_remove
		</div>
	</div>
HTML;
	
	return $form;
}


/**
 * Counts the number of dimensions in an array
 *
 * @since 2.0
 * @package facileManager
 *
 * @param array $array Array to count
 * @return int Number of dimensions
 */
function countArrayDimensions($array) {
	if (is_array(@reset($array))) {
		$count = countArrayDimensions(reset($array)) + 1;
	} else {
		$count = 1;
	}
	
	return $count;
}


/**
 * Displays the add new link
 *
 * @since 2.0
 * @package facileManager
 *
 * @param string $name Name value of plus sign
 * @param string $rel Rel value of plus sign
 * @param string $title The title of plus sign
 * @param string $style Use an image or font
 * @return string
 */
function displayAddNew($name = null, $rel = null, $title = null, $style = 'fa-plus-square-o') {
	global $__FM_CONFIG;
	
	if (empty($title)) $title = _('Add New');
	
	if ($name) $name = ' name="' . $name . '"';
	if ($rel) $rel = ' rel="' . $rel . '"';
	
	$image = '<i class="template-icon fa ' . $style . '" title="' . $title . '"></i>';
	
	return sprintf('<a id="plus" href="#" title="%s"%s%s>%s</a>', $title, $name, $rel, $image);
}


/**
 * Creates the SQL based on search input
 *
 * @since 2.0
 * @package facileManager
 *
 * @param array $fields Table fields to search
 * @param string $prefix Prefix of the table fields
 * @return string
 */
function createSearchSQL($fields = array(), $prefix = null) {
	$search_query = null;
	if (isset($_GET['q'])) {
		$search_query = ' AND (';
		$search_text = sanitize($_GET['q']);
		foreach ($fields as $field) {
			$search_query .= "$prefix$field LIKE '%$search_text%' OR ";
		}
		$search_query = rtrim($search_query, ' OR ') . ')';
	}
	
	return $search_query;
}


/**
 * Translates text using module domain
 *
 * @since 2.0
 * @package facileManager
 *
 * @param string $text Text to translate
 * @param string $domain Domain to use
 * @return string
 */
function __($text, $domain = null) {
	if (function_exists('dgettext')) {
		if (!$domain) $domain = $_SESSION['module'];

		return dgettext($domain, $text);
	}
	
	return $text;
}


/**
 * Gets all available user capabilities
 *
 * @since 2.0
 * @package facileManager
 *
 * @return array
 */
function getAvailableUserCapabilities() {
	global $fm_name;
	
	$fm_user_caps = null;
	
	if (file_exists(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $fm_name . DIRECTORY_SEPARATOR . 'extra' . DIRECTORY_SEPARATOR . 'capabilities.inc.php')) {
		include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $fm_name . DIRECTORY_SEPARATOR . 'extra' . DIRECTORY_SEPARATOR . 'capabilities.inc.php');
	}
	
	foreach (getActiveModules() as $module) {
		if (file_exists(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'extra' . DIRECTORY_SEPARATOR . 'capabilities.inc.php')) {
			include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'extra' . DIRECTORY_SEPARATOR . 'capabilities.inc.php');
		}
	}
	
	return $fm_user_caps;
}


/**
 * Whether a group has capability
 *
 * @since 2.1
 * @package facileManager
 *
 * @param integer $group_id Group ID to check.
 * @param string|array $capability Capability name.
 * @param string $module Module name to check capability for
 * @param string $extra_perm Extra capability to check
 * @return boolean
 */
function groupCan($group_id, $capability, $module = 'facileManager', $extra_perm = null) {
	global $fm_name;
	
	$group_capabilities = getUserCapabilities($group_id, 'group');
	
	return userGroupCan($group_id, $capability, $module, $extra_perm, $group_capabilities, 'group');
}


/**
 * Whether a group has capability
 *
 * @since 2.1
 * @package facileManager
 *
 * @param integer $group_id Group ID to check.
 * @param string|array $capability Capability name.
 * @param string $module Module name to check capability for
 * @param string $extra_perm Extra capability to check
 * @param array $allowed_capabilities Capabilities granted to the user or group
 * @param string $type User or Group
 * @return boolean
 */
function userGroupCan($id, $capability, $module = 'facileManager', $extra_perm = null, $allowed_capabilities = array(), $type = 'user') {
	global $fm_name;
	
	/** Check if super admin */
	if (@array_key_exists('do_everything', $allowed_capabilities[$fm_name])) return true;
		
	/** Handle multiple capabilities */
	if (is_array($capability)) {
		foreach ($capability as $cap) {
			if ($type == 'user') {
				if (userCan($id, $cap, $module, $extra_perm)) return true;
			} else {
				if (groupCan($id, $cap, $module, $extra_perm)) return true;
			}
		}
		return false;
	}
	
	/** Check capability */
	if (@array_key_exists($capability, $allowed_capabilities[$module])) {
		if (is_array($allowed_capabilities[$module][$capability])) {
			if (is_array($extra_perm)) {
				$found = false;
				
				foreach ($extra_perm as $needle) {
					if (in_array($needle, $allowed_capabilities[$module][$capability]))
						$found = true;
				}
				
				return $found;
			} else {
				return in_array($extra_perm, $allowed_capabilities[$module][$capability]);
			}
		}
		
		return true;
	}
	
	return false;
}


?>
