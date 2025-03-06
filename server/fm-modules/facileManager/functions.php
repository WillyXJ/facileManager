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
	if (is_file($shared_classes_dir . DIRECTORY_SEPARATOR . $file) && $file[0] != '.') {
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
function isNewVersionAvailable($package, $version, $interval = 'schedule') {
	$fm_site_url = 'http://www.facilemanager.com/check/';
	
	$data['package'] = $package;
	$data['version'] = $version;
	$data['format']  = 'array';
	
	$method = 'update';
	
	/** Are software updates enabled? */
	if (!getOption('software_update')) return false;
	
	/** Disable check until user has upgraded database to 1.2 */
	if (getOption('fm_db_version') < 32) return false;
	
	/** Should we be running this check now? */
	$last_version_check = getOption('version_check', 0, $package);
	if (!$software_update_interval = getOption('software_update_interval')) $software_update_interval = 'week';
	if (!$last_version_check) {
		unset($last_version_check);
		$last_version_check['timestamp'] = 0;
		$last_version_check['data'] = null;
		$method = 'insert';
	} elseif (isset($last_version_check['data']['version']) && $last_version_check['data']['version'] == $version) {
		$last_version_check['timestamp'] = 0;
	}
	if ($interval == 'force') {
		$last_version_check['timestamp'] = 0;
		$last_version_check['data'] = null;
	}
	if ($last_version_check['timestamp'] < strtotime("1 $software_update_interval ago")) {
		$data['software_update_tree'] = getOption('software_update_tree');
		
		/** Use file_get_contents if allowed else use POST */
		if (ini_get('allow_url_fopen')) {
			$result = file_get_contents($fm_site_url . '?' . http_build_query($data));
		} else {
			$result = getPostData($fm_site_url . '?' . http_build_query($data), $data, 'get', array(CURLOPT_CONNECTTIMEOUT => 1));
		}
		
		if (isSerialized($result)) {
			$result = unserialize($result);
		}
		
		setOption('version_check', array('timestamp' => time(), 'data' => $result), $method, true, 0, $package);
		
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
	global $fmdb;
	
	if ($replace) {
		$strip_chars = array("'", "\"", "`", "$", "?", "*", "&", "^", "!", "#");
		$replace_chars = array(" ", "\\", "_", "(", ")", ",", ".", "-");

		$data = str_replace($strip_chars, '', $data);
		$data = str_replace($replace_chars, $replace, $data);
		$data = str_replace($replace . $replace, $replace, $data);
		
		return $data;
	} else {
		if (is_string($data)) {
			$data = htmlspecialchars(strip_tags($data, '<username>'), ENT_NOQUOTES);
			if ($fmdb->use_mysqli) {
				return html_entity_decode(str_replace('\r\n', "\n", mysqli_real_escape_string($fmdb->dbh, $data)));
			} else {
				return html_entity_decode(str_replace('\r\n', "\n", @mysql_real_escape_string($data)));
			}
		}
		return $data;
	}
}


/**
 * Prints the header
 *
 * @since 1.0
 * @package facileManager
 */
function printHeader($subtitle = 'auto', $css = 'facileManager', $help = 'no-help', $menu = 'menu') {
	global $fm_name, $__FM_CONFIG;
	
	include(ABSPATH . 'fm-includes/version.php');
	
	$title = $fm_name;
	
	if (!empty($subtitle)) {
		if ($subtitle == 'auto') $subtitle = getPageTitle();
		$title = "$subtitle &lsaquo; $title";
	}

	$theme = (isset($_SESSION['user']['theme'])) ? $_SESSION['user']['theme'] : getOption('theme');
	$theme_mode = (isset($_SESSION['user']['theme_mode'])) ? $_SESSION['user']['theme_mode'] : '';
	
	$head = $logo = null;
	
	if ($css == 'facileManager') {
		$head = ($menu == 'menu') ? getTopHeader($help) : null;
	} else {
		$logo = '<h1 class="center"><img alt="' . $fm_name . '" src="' . $GLOBALS['RELPATH'] . 'fm-includes/images/logo.png" /></h1>' . "\n";
	}
	
	/** Module css and js includes */
	if (isset($_SESSION['module']) && isset($__FM_CONFIG['module']['path'])) {
		$module_css_file = $__FM_CONFIG['module']['path']['css'] . DIRECTORY_SEPARATOR . 'module.css';
		$module_css = (file_exists(ABSPATH . $module_css_file) && array_key_exists($_SESSION['module'], $__FM_CONFIG)) ? '<link rel="stylesheet" href="' . $GLOBALS['RELPATH'] . $module_css_file . '?ver=' . $__FM_CONFIG[$_SESSION['module']]['version'] . '" type="text/css" />' : null;
		$module_js_dir = $__FM_CONFIG['module']['path']['js'] . DIRECTORY_SEPARATOR;
		$module_js_file = $module_js_dir . 'module.php';
		$module_js = (file_exists(ABSPATH . $module_js_file) && array_key_exists($_SESSION['module'], $__FM_CONFIG)) ? '<script src="' . $GLOBALS['RELPATH'] . $module_js_file . '?ver=' . $__FM_CONFIG[$_SESSION['module']]['version'] . '" type="text/javascript" charset="utf-8"></script>' : null;
		
		/** Include any .js files */
		foreach (scandir($module_js_dir) as $module_js_file) {
			if (in_array($module_js_file, array('.', '..', 'module.php'))) continue;
			$module_js_file = $module_js_dir . $module_js_file;
			$module_js .= (file_exists(ABSPATH . $module_js_file) && array_key_exists($_SESSION['module'], $__FM_CONFIG)) ? "\n\t\t" . '<script src="' . $GLOBALS['RELPATH'] . $module_js_file . '" type="text/javascript" charset="utf-8"></script>' : null;
		}
	} else {
		$module_css = $module_js = null;
	}
	
	echo <<<HTML
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" class="default-theme $theme $theme_mode">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title>$title</title>
		<link rel="shortcut icon" href="{$GLOBALS['RELPATH']}fm-modules/$fm_name/images/favicon.png" />
		<link rel="stylesheet" href="{$GLOBALS['RELPATH']}fm-modules/$fm_name/css/$css.css?ver=$fm_version" type="text/css" />
		<link rel="stylesheet" href="{$GLOBALS['RELPATH']}fm-modules/$fm_name/css/themes.css?ver=$fm_version" type="text/css" />
		<link rel="stylesheet" href="{$GLOBALS['RELPATH']}fm-modules/$fm_name/css/no-dark.css?ver=$fm_version" type="text/css" />
		<link rel="stylesheet" href="{$GLOBALS['RELPATH']}fm-includes/extra/jquery-ui.min.css" />
		<link rel="stylesheet" href="{$GLOBALS['RELPATH']}fm-includes/extra/font-awesome/css/font-awesome.min.css" />
		<link rel="stylesheet" href="{$GLOBALS['RELPATH']}fm-includes/extra/open-sans.css" type="text/css" />
		<link rel="stylesheet" href="{$GLOBALS['RELPATH']}fm-includes/extra/tooltip.css" type="text/css" />
		<script src="{$GLOBALS['RELPATH']}fm-includes/js/jquery-3.6.0.min.js"></script>
		<script src="{$GLOBALS['RELPATH']}fm-includes/js/jquery-ui.min.js"></script>
		<script src="{$GLOBALS['RELPATH']}fm-includes/extra/select2/select2.min.js" type="text/javascript"></script>
		<link rel="stylesheet" href="{$GLOBALS['RELPATH']}fm-includes/extra/select2/select2.css?ver=$fm_version" type="text/css" />
		$module_css
		<script src="{$GLOBALS['RELPATH']}fm-modules/$fm_name/js/$fm_name.php?ver=$fm_version" type="text/javascript" charset="utf-8"></script>
		$module_js
	</head>
<body>
<a href="#" id="scroll-to-top" class=""></a>
$head
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
	</div>
</div>
</div>
</div>
<div class="manage_form_container" id="manage_item" $block_style>
	<div class="manage_form_container_flex">
		<div class="manage_form_contents $classes" id="manage_item_contents">
		$text
		</div>
	</div>
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
	
	$fm_new_version_available = $account_menu = $user_account_menu = $module_menu = $module_version_info = $return_extra = null;
	$banner = null;

	$sections = array('left' => array(), 'right' => array());
	
	if ($help != 'help-file') {
		$banner = sprintf('<div class="fm-header-container top-banner" style="display: %s;">%s</div>', isMaintenanceMode() ? 'block' : 'none', sprintf(_('%s is currently in maintenance mode.'), $fm_name)) . "\n";

		$branding_logo = getBrandLogo();
		
		if (isset($_SESSION['module']) && $_SESSION['module'] != $fm_name) {
			$module_version_info = sprintf('<br />%s v%s', $_SESSION['module'], $__FM_CONFIG[$_SESSION['module']]['version']);
			$fm_version_info = "$fm_name v$fm_version";
		} else {
			$fm_version_info = sprintf('<span>%s v%s</span>', $fm_name, $fm_version);
		}
		
		$sections['left'][] = sprintf('<img src="%s" alt="%s" title="%s" />%s%s', 
				$branding_logo, $fm_name, $fm_name, 
				$fm_version_info,
				$module_version_info
		);
		
		/** Build app dropdown menu */
		$modules = getAvailableModules();
		$avail_modules_array = getActiveModules(true);
		$avail_modules = '';
		
		if (count($modules)) {
			foreach ($modules as $module_name) {
				if ($module_name == $_SESSION['module']) continue;
				if (in_array($module_name, $avail_modules_array)) {
					$avail_modules .= sprintf('<li><a href="%1$s?module=%2$s"><span class="menu-icon"><i class="fa fa-cube" aria-hidden="true"></i></span><span>%2$s</span></a></li>' . "\n",
						$GLOBALS['RELPATH'], $module_name);
				}
			}
			if ($avail_modules) $avail_modules = "<hr />\n" . $avail_modules;
		}
			
		$help = _('Help');
		$github_issues = _('GitHub Issues');
		$module_menu = <<<HTML
			<div id="menu_mainitems" class="module-menu">
			<ul>
				<li class="has-sub"><a href="#"><i class="fa fa-bars fa-lg menu-icon" aria-hidden="true"></i></a>
					<ul class="sub-right">
						<li><a class="help_link" href="#"><span class="menu-icon"><i class="fa fa-life-ring" aria-hidden="true"></i></span><span>$help</a></span></li>
						<li><a href="https://github.com/WillyXJ/facileManager/issues" target="_blank"><span class="menu-icon"><i class="fa fa-github" aria-hidden="true"></i></span><span>$github_issues</a></span></li>
						$avail_modules
					</ul>
				</li>
			</ul>
			</div>
HTML;
		$sections['right'][] = array('module-menu', $module_menu);
		
		/** Include module toolbar items */
		if (function_exists('buildModuleToolbar')) {
			list($module_toolbar_left, $module_toolbar_right) = @buildModuleToolbar();
			$sections['left'][] = $module_toolbar_left;
			$sections['right'][] = $module_toolbar_right;
		}
	
		$help_file = buildHelpFile();
	
		if (defined('FM_INCLUDE_SEARCH') && FM_INCLUDE_SEARCH === true) {
			$sections['right'][] = sprintf('<div class="flex-apart"><a class="search" href="#" title="%s"><i class="fa fa-search fa-lg"></i></a>%s</div>', _('Search this page'), displaySearchForm());
		}

		$sections['right'][] = sprintf('<a class="process_all_updates tooltip-bottom" href="#" data-tooltip="%s"><i class="fa fa-refresh fa-lg"></i></a><span class="update_count"></span>', _('Process all available updates now'));

		$return_extra .= <<<HTML
	<div id="help">
		<div id="help_topbar" class="flex-apart">
			<div><p class="title">fmHelp</p></div>
			<div><p>{$__FM_CONFIG['icons']['popout']} {$__FM_CONFIG['icons']['close']}</p></div>
		</div>
		<div id="help_file_container">
		$help_file
		</div>
	</div>

HTML;
	} else {
		$sections['left'][] = sprintf('fmHelp<br />v%s', $fm_version);
	}

	$return_parts = '';
	foreach ($sections as $class => $section_array) {
		$return_parts .= sprintf('<div class="header-container flex-%s">' . "\n", $class);
		foreach ($section_array as $section) {
			if ($section) {
				if (is_array($section)) {
					list($klass, $section) = $section;
				}
				$class = isset($klass) ? sprintf(' class="%s"', $klass) : '';
				$return_parts .= sprintf("<div%s>\n%s</div>\n", $class, $section);
				unset($klass);
			}
		}
		$return_parts .= '</div>' . "\n";
	}
	$return = sprintf("<div id=\"tophead\" class=\"fm-header-container flex-apart\">\n%s</div>\n", $return_parts);

	return '<div class="fm-site-container flex-column">' . $banner . $return . '<div class="fm-body flex">' . $return_extra;
}

/**
 * Prints the menu system
 *
 * @since 1.0
 * @package facileManager
 */
function printMenu() {
	global $__FM_CONFIG;

	$main_menu_html = $account_info = '';
	
	list($filtered_menu, $filtered_submenu) = getCurrentUserMenu();
	ksort($filtered_menu);
	ksort($filtered_submenu);
	
	$i = 1;
	foreach ($filtered_menu as $position => $main_menu_array) {
		$sub_menu_html = '';
		$show_top_badge_count = true;
		
		list($menu_title, $page_title, $menu_icon, $capability, $module, $slug, $classes, $badge_count) = $main_menu_array;
		if (!is_array($classes)) {
			$classes = !empty($classes) ? array_fill(0, 1, $classes) : array();
		}
		if ($badge_count > 100) {
			$badge_count = '99+';
		}
		
		/** Check if menu item is current page */
		if ($slug == findTopLevelMenuSlug($filtered_submenu)) {
			array_push($classes, 'current');
			
			if (array_key_exists($slug, $filtered_submenu)) {
				$show_top_badge_count = false;
				$k = 0;
				foreach ($filtered_submenu[$slug] as $submenu_array) {
					$sub_menu_icon = $submenu_array[2];
					if (!empty($submenu_array[0])) {
						$submenu_class = ($submenu_array[5] == $GLOBALS['basename']) ? ' class="current"' : null;
						if ($submenu_array[7]) {
							if ($submenu_array[7] > 100) {
								$submenu_array[7] = '99+';
							}
							$submenu_array[0] = sprintf($submenu_array[0] . ' <span class="menu-badge"><p>%s</p></span>', $submenu_array[7]);
						}
						$sub_menu_icon = ($sub_menu_icon) ? sprintf('<i class="fa fa-%s" aria-hidden="true"></i>', $sub_menu_icon) : null;
						$sub_menu_html .= sprintf('<li%s><a href="%s"><span class="menu-icon">%s</span><span>%s</span></a></li>' . "\n", $submenu_class, $submenu_array[5], $sub_menu_icon, $submenu_array[0]);
					} elseif (!$k) {
						$show_top_badge_count = true;
					}
					$k++;
				}
				
				$sub_menu_html = <<<HTML
					<div id="menu_subitems">
						<ul>
						$sub_menu_html
						</ul>
					</div>
HTML;
			}
		}
		
		/** Build submenus */
		if (count((array) $filtered_submenu[$slug])) {
			array_push($classes, 'has-sub');
			if (!in_array('current', $classes)) {
				foreach ($filtered_submenu[$slug] as $submenu_array) {
					$sub_menu_icon = 'fa-filter';
					if (!empty($submenu_array[0])) {
						if ($submenu_array[7]) {
							if ($submenu_array[7] > 100) {
								$submenu_array[7] = '99+';
							}
							$submenu_array[0] = sprintf($submenu_array[0] . ' <span class="menu-badge"><p>%s</p></span>', $submenu_array[7]);
						}
						$sub_menu_html .= sprintf('<li><a href="%s">%s</a></li>' . "\n", $submenu_array[5], $submenu_array[0]);
					}
				}
			
			$sub_menu_html = <<<HTML
				<ul>
				$sub_menu_html
				</ul>
</li>

HTML;
			}
		}
		
		$arrow = null;
		$main_menu_item_link = $slug;
		if (in_array('has-sub', $classes)) {
			$arrow = sprintf('<span class="menu-arrow"><i class="fa fa-caret-%s menu-icon" aria-hidden="true"></i></span>', (in_array('current', $classes)) ? 'down' : 'right');
			$main_menu_item_link = '#';
		}
		$menu_icon = ($menu_icon) ? sprintf('<i class="fa fa-%s" aria-hidden="true"></i>', $menu_icon) : null;
		
		/** Join all of the classes */
		if (count($classes)) $class = ' class="' . implode(' ', $classes) . '"';
		else $class = null;
		
		if (empty($slug) && !empty($class)) {
			/** Ideally this should be the separator */
			if ($i != count($filtered_menu)) {
				$main_menu_html .= '<li' . $class . '><hr /></li>' . "\n";
			}
		} else {
			/** Display the menu item if allowed */
			if (currentUserCan($capability, $module) || in_array('has-sub', $classes)) {
				if ($badge_count && $show_top_badge_count) $menu_title = sprintf($menu_title . ' <span class="menu-badge"><p>%s</p></span>', $badge_count);
				$main_menu_html .= sprintf('<li%s><a href="%s"><span class="menu-icon">%s</span><span>%s</span>%s</a>%s</li>' . "\n", $class, $main_menu_item_link, $menu_icon, $menu_title, $arrow, $sub_menu_html);
			}
		}
		
		$i++;
	}
	
	$donate_text = _('Donate');
	
	$auth_method = getOption('auth_method');
	if ($auth_method) {
		$star = currentUserCan('do_everything') ? $__FM_CONFIG['icons']['star'] . ' ' : null;
		$profile_link = ($auth_method) ? sprintf('<div><a class="account_settings" id="%s" href="#"><i class="fa fa-user-circle-o" aria-hidden="true"></i>%s</a></div>' . "\n", $_SESSION['user']['id'], _('Edit Profile')) : null;
		$logout = _('Logout');
		$account_info = <<<HTML
			<div><span>{$star}{$_SESSION['user']['name']}</span></div>
			<div id="account_info_actions" class="flex-apart">
				$profile_link
				<div><a href="{$GLOBALS['RELPATH']}?logout"><i class="fa fa-sign-out" aria-hidden="true"></i>$logout</a></div>
			</div>
			<div><hr /></div>
HTML;
	}
	
echo <<<MENU
	<div id="menuback" class="flex-apart">
	<div id="menu">
		<div id="account_info" class="flex-apart">
		$account_info
		</div>
		<div id="menu_mainitems" class="drop-right">
			<ul>
$main_menu_html
			</ul>
		</div>
	</div>
	<div id="menu_footer">
		<ul>
			<li><a href="http://www.facilemanager.com/donate/" target="_blank"><i class="fa fa-heart menu-icon" aria-hidden="true"></i>$donate_text</a></li>
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
	return currentUserCan($element[3], $element[4]);
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
	
	$filtered_menus[0] = array_filter($menu, 'filterMenu');
	
	unset($temp_menu, $element, $submenu_array, $slug, $position, $single_element);

	/** Handle module settings, but no fM settings permissions */
	if (array_key_exists('admin-settings.php', $filtered_menus[1]) && !array_key_exists('70', $filtered_menus[0])) {
		$filtered_menus[0][70] = $menu[70];
	}

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
		$secondary_fields = implode(" $direction,", $id);
		$secondary_fields = ' ' . sanitize(substr($secondary_fields, strlen($primary_field)));
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
	
	$query = "UPDATE `$table` SET `{$prefix}status`='" . sanitize($status) . "' WHERE account_id='{$_SESSION['user']['account_id']}' AND `$field`='" . sanitize($id) . "'";

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
 * 
 * @param string $tbl_name Table name
 * @param string $column_name Column name
 * @param string $sort Optional sort function
 * 
 * return array
 */
function enumMYSQLSelect($tbl_name, $column_name, $sort = 'unsorted') {
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
	
	if ($sort != 'unsorted') {
		$sort($values);
	}
	
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
	$type_options = '';
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
			$selected = ($option_select == $options[$i] || (is_array($option_select) && @in_array($options[$i], $option_select))) ? ' selected' : '';
			$type_options.="<option$selected>$options[$i]</option>\n";
		}
	}
	$class = ($classes) ? sprintf('class="%s"', $classes) : null;
	$build_select = "<select $class data-placeholder=\"$placeholder\" ";
	$build_select .= "size=\"$size\" name=\"{$select_name}";
	if ($multiple) $build_select .= '[]';
	$build_select .= "\" id=\"$select_id\"";
	if ($multiple) $build_select .= ' multiple';
	if ($onchange) $build_select .= ' onchange="' . $onchange . '" ';
	$build_select .= "$disabled>$type_options</select>\n";
	return $build_select;
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
		if (is_array($data)) {
			foreach ($data as $name) {
				if (isset($result[0]->$name)) $array[] = $result[0]->$name;
			}
			if (is_array($array)) return $array;
		} else {
			if (isset($result[0]->$data)) return $result[0]->$data;
		}
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
	
	$query = sprintf('SELECT %s FROM `fm_accounts` WHERE %s="%s"', $key, $field, $value);
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
		$ping = shell_exec("$program -t 2 -c 3 " . escapeshellarg($server) . " 2>/dev/null");
	} elseif (PHP_OS == 'Linux') {
		$ping = shell_exec("$program -W 2 -c 3 " . escapeshellarg($server) . " 2>/dev/null");
	} else {
		$ping = shell_exec("$program -c 3 " . escapeshellarg($server) . " 2>/dev/null");
	}
	if ($ping && preg_match('/64 bytes from/', $ping)) {
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
function getPostData($url, $data = null, $post = 'post', $options = array()) {
	if ($post == 'post') {
		$options = array(
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $data
		);
	}
	$defaults = array (
		CURLOPT_HEADER => false,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FRESH_CONNECT => true,
		CURLOPT_FORBID_REUSE => true,
		CURLOPT_CONNECTTIMEOUT => 2,
		CURLOPT_TIMEOUT => 5,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_SSL_VERIFYHOST => false,
		CURLOPT_FAILONERROR => true,
		CURLOPT_URL => $url
	);
	
	$proxy = array();
	if (getOption('proxy_enable')) {
		$proxyauth = getOption('proxy_user') . ':' . getOption('proxy_pass');
		if ($proxyauth == ':') $proxyauth = null;
		$proxy = array(
			CURLOPT_PROXY => getOption('proxy_host') . ':' . getOption('proxy_port'),
			CURLOPT_PROXYUSERPWD => $proxyauth
		);
	}
	$ch = curl_init();
	curl_setopt_array($ch, ($options + $proxy + $defaults));
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
	$user_results = $fmdb->get_results($query);
	
	/** Matching results returned as an array */
	if ($fmdb->num_rows) {
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
	$string = '';
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
		<p>{$fm_new_version_available['text']}</p>
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
	<p>Have an idea for a new feature? Found a bug? Submit a report with the <a href="https://github.com/WillyXJ/facileManager/issues" target="_blank">issue tracker</a>.</p>
</div>
<div>
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
			
			<p>You can add, modify, and delete user accounts at Admin &rarr; <a href="__menu{Users & Groups}">Users</a>.</p>
			
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

			<p>User groups can also be created to easily provide the same level of access to multiple user accounts.</p>
			<p><i>The 'User Management' or 'Super Admin' permission is required for these actions.</i></p>

			<p>When API Support is enabled at Settings &rarr; <a href="__menu{Settings}">General</a>, each user may create an API keypair
			by editing their user profile. Privileged users will be able change the status of any keypair through Admin &rarr; 
			<a href="__menu{Users & Groups}">Users</a>. This keypair allows the user to authenticate via the API through the client scripts.</p> 
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
				Authenticates against the $fm_name database using solely the users defined at Admin &rarr; <a href="__menu{Users & Groups}">Users</a>.</li>
				<li><b>LDAP Authentication</b><br />
				Users are authenticated against a defined LDAP server. Upon success, users are created in the $fm_name database using the selected 
				template account for granular permissions within the environment. If no template is selected then user authentication will fail 
				(this is another method of controlling access to $fm_name). These users cannot be disabled nor can their passwords be changed 
				within $fm_name. The PHP LDAP extensions have to be installed before this option is available.</li>
			</ul>
			<p><i>You can reset the authentication method by setting the following in config.inc.php:</i></p>
			<p><i>define('FM_NO_AUTH', true);</i></p>
			<p><b>Login Message</b><br />
			Define a message to be displayed at login (such as a terms and conditions) and optionally require users to acknowledge the message
			for authenication to succeed.</p>
			<p><b>Client Registration</b><br />
			You can choose to allow clients to automatically register in the database or not.</p>
			<p><b>API Support</b><br />
			By enabling API support, users are able to create keypairs to authenticate with through the client scripts. This opens up the ability
			to make a limited selection of module changes without using the web interface.</p>
			<p><b>SSL</b><br />
			You can choose to have $fm_name enforce the use of SSL when a user tries to access the web app.</p>
			<p><b>Mailing</b><br />
			There are a few things $fm_name and its modules may need to send an e-mail about (such as password reset links). These settings allow
			you to configure the mailing settings to use for your environment and enable/disable mailing altogether.</p>
			<p><b>Proxy Server</b><br />
			Set the appropriate configuration if $fm_name is behind a proxy server for Internet access.</p>
			<p><b>Date and Time</b><br />
			Set your preferred timezone, date format, and time format for $fm_name to use throughout all aspects of the app. What you select is
			how all dates and times will be display including any client configuration files.</p>
			<p><b>Logging Method</b><br />
			There are three ways logging methods supported by $fm_name:</p>
			<ul>
				<li><b>Built-in</b><br />
				Events will only be logged to $fm_name.</li>
				<li><b>syslog</b><br />
				Events will only be looged to syslog.</li>
				<li><b>Built-in + syslog</b><br />
				Events will be logged to $fm_name and syslog.</li>
			</ul>
			<p><b>Show Errors</b><br />
			Choose whether you want $fm_name errors to be displayed as they occur or not. This can be useful if you are having trouble
			adding or editing objects.</p>
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
			<p><b>Image Branding</b><br />
			Add your own image to brand $fm_name. This image will be used on the login screen, navigation header, and automated e-mails.</p>
			<p><b>Enable Maintenance Mode</b><br />
			Only users with Super Admin or Module Management privileges are able to authenticate. This is useful during application upgrades.</p>
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

	return parseMenuLinks($body) . '</div>';
}


/**
 * Adds a UI log entry to the database
 *
 * @since 1.0
 * @package facileManager
 */
function addLogEntry($log_data, $module = null) {
	global $fmdb, $__FM_CONFIG, $fm_name;
	
	$account_id = isset($_SESSION['user']['account_id']) ? $_SESSION['user']['account_id'] : 0;
	$user_name = isset($_SESSION['user']['name']) ? $_SESSION['user']['name'] : 0;
	$module = isset($module) ? $module : $_SESSION['module'];
	
	$log_method = getOption('log_method');
	
	if ($log_method != 1) {
		$insert = "INSERT INTO `{$__FM_CONFIG['db']['name']}`.`fm_logs` VALUES (NULL, '$user_name', $account_id, '$module', " . time() . ", '" . sanitize($log_data) . "')";
		if (is_object($fmdb)) {
			$fmdb->query($insert);
		} else {
			die(_('Lost connection to the database.'));
		}
	}
	
	if ($log_method) {
		addSyslogEntry(trim($log_data), $module);
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
	
	$modules = array();
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
	if (!$fmdb) return false;
	
	$module_sql = ($module_name) ? "AND module_name='$module_name'" : null;

	$query = "SELECT * FROM fm_options WHERE option_name='$option' AND account_id=$account_id $module_sql LIMIT 1";
	$results = $fmdb->get_results($query);
	
	if ($fmdb->num_rows && !$fmdb->sql_errors) {
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
		$value = isSerialized($value) ? $value : serialize($value);
	};
	$option = sanitize($option);
	
	$module_sql = ($module_name) ? "AND module_name='$module_name'" : null;
	
	if ($insert_update == 'auto') {
		$query = "SELECT * FROM fm_options WHERE option_name='$option' AND account_id=$account_id $module_sql";
		$fmdb->query($query);
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
		$query = "UPDATE fm_options SET option_value='$value' WHERE option_name='$option' AND account_id=$account_id $module_sql";
	}
	
	return $fmdb->query($query);
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
		$fmdb->query($query);
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
		if (array_key_exists($module, (array) $current_caps)) {
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
	$uri = $hidden = '';
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
 * @return int|void
 */
function generateSerialNo($module = null) {
	global $fmdb, $__FM_CONFIG;

	if ($module) {
		while(1) {
			if (array_key_exists('server_name', $_POST) && defined('CLIENT')) {
				$get_query = "SELECT * FROM `fm_{$__FM_CONFIG[$module]['prefix']}servers` WHERE `server_status`!='deleted' AND account_id='" . getAccountID(sanitize($_POST['AUTHKEY'])) . "' AND `server_name`='" . sanitize($_POST['server_name']) . "'";
				$array = $fmdb->get_results($get_query);
				if ($fmdb->num_rows) {
					return $array[0]->server_serial_no;
				}
			}
			$serialno = rand(100000000, 999999999);
			
			/** Ensure the serial number does not exist in any of the server tables */
			$result = $fmdb->get_results("SELECT table_name AS table_name FROM information_schema.tables t WHERE t.table_schema = '{$__FM_CONFIG['db']['name']}' AND t.table_name LIKE 'fm_%_servers'");
			$table_count = $fmdb->num_rows;
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
 * @return string|void
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
 * @return string|void
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
	
	$page_params = '';
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
	$page_links[] = '<div class="flex-left">';
	if (isset($addl_blocks)) {
		foreach ((array) $addl_blocks as $block) {
			if ($block) $page_links[] = '<div>' . $block . '</div>';
		}
	}

	$page_links[] = '</div>';
	if ($total_pages) {
		$page_links[] = '<div class="flex-right">';
		$page_links[] = buildPaginationCountMenu(0, 'pagination');
		$page_links[] = '<div id="pagination" class="' . $classes . '">';
		$page_links[] = '<form id="pagination_search" method="GET" action="' . $GLOBALS['basename'] . '?' . $page_params . '">';
		$page_links[] = sprintf('<span>%s</span>', sprintf(ngettext('%d item', '%d items', $fmdb->num_rows), formatNumber($fmdb->num_rows)));

		/** Previous link */
		if ($page > 1 && $total_pages > 1) {
			$page_links[] = '<a href="' . $GLOBALS['basename'] . "?{$page_params}p=1\">&laquo;</a>";
			$page_links[] = '<a href="' . $GLOBALS['basename'] . "?{$page_params}p=" . ($page - 1) . '">&lsaquo;</a>';
		}
		
		/** Page number */
		$page_links[] = '<input id="paged" type="text" value="' . $page . '" /> of ' . formatNumber($total_pages);
		
		/** Next link */
		if ($page < $total_pages) {
			$page_links[] = '<a href="' . $GLOBALS['basename'] . "?{$page_params}p=" . ($page + 1) . '">&rsaquo;</a>';
			$page_links[] = '<a href="' . $GLOBALS['basename'] . "?{$page_params}p=" . $total_pages . '">&raquo;</a>';
		}

		$page_links[] = '</form>';
		$page_links[] = '</div>';
		$page_links[] = '</div>';
	}
	$page_links[] = '</div>';
	
	return join("\n", $page_links);
}


/**
 * Builds the server listing in a dropdown menu
 *
 * @since 1.0
 * @package facileManager
 */
function buildPaginationCountMenu($server_serial_no = 0, $class = null) {
	global $fmdb, $__FM_CONFIG;
	
	$record_count = buildSelect('rc', 'rc', $__FM_CONFIG['limit']['records'], $_SESSION['user']['record_count'], 1, null, false, 'this.form.submit()');
	
	$hidden_inputs = '';
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
 * @param string $tryagain Include the Try Again button
 * @param string $title Error message title to display
 * @return null
 */
function bailOut($message, $tryagain = 'try again', $title = null) {
	global $fm_name;
	
	if (!$title) $title = _('Requirement Error');
	
	if (strpos($message, '<') != 0) {
		$message = "<p>$message</p>";
	}
	
	if ($tryagain == 'try again') {
		$tryagain = sprintf('<p class="step"><a href="%s" class="button">%s</a></p>',
			$_SERVER['PHP_SELF'], _('Try Again'));
	} else {
		$tryagain = null;
	}
	
	printHeader($title, 'install');
	printf('<div id="fm-branding">
		<img src="%s" /><span>%s</span>
	</div>
	<div id="window">%s%s</div>', getBrandLogo(), $title, $message, $tryagain);
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
 * @param string $error Message to display as the tooltip
 * @return string
 */
function displayProgress($step, $result, $process = 'noisy', $error = null) {
	if ($result === true) {
		$output = '<i class="fa fa-check fa-lg"></i>';
		$status = 'success';
	} else {
		global $fmdb;
		
		if (!$error) {
			if (is_object($fmdb)) {
				$error = $fmdb->last_error;
			}
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
 * @return string
 */
function getColumnLength($tbl_name, $column_name) {
	global $fmdb;
	
	$query = "SHOW COLUMNS FROM $tbl_name LIKE '$column_name'";
	$result = $fmdb->get_results($query);
	
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
	return verifySimpleVariable($ip_address, FILTER_VALIDATE_IP);
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
 * Runs data through a filter check
 *
 * @since 3.1
 * @package facileManager
 *
 * @param string $data Address to check
 * @param mixed $filter_type PHP filter type to use
 * @param array $filter_options PHP filter options to pass
 * @return boolean
 */
function verifySimpleVariable($data, $filter_type, $filter_options = array()) {
	return filter_var($data, $filter_type, $filter_options);
}


/**
 * Builds a form for app settings
 *
 * @since 1.0
 * @package facileManager
 *
 * @param array $saved_options Settings pulled from the database
 * @param array $default_options Default settings
 * @return string
 */
function buildSettingsForm($saved_options, $default_options) {
	$option_rows = $current_parent = '';
	
	foreach ($default_options as $option => $options_array) {
		$option_row_head = null;
		$option_value = array_key_exists($option, $saved_options) ? $saved_options[$option] : $options_array['default_value'];
		
		if (is_array($option_value)) {
			$temp_value = '';
			foreach ($option_value as $value) {
				$temp_value .= $value . "\n";
			}
			$option_value = rtrim($temp_value);
		}

		$div_style = 'style="display: none;"';
		
		switch($options_array['type']) {
			case 'textarea':
				$input_field = '<textarea name="' . $option . '" id="' . $option . '" type="' . $options_array['type'] . '">' . $option_value . '</textarea>';
				break;
			case 'checkbox':
				$checked = $option_value == 'yes' ? 'checked' : null;
				$input_field = '<input name="' . $option . '" type="hidden" value="no" />';
				$input_field .= '<label><input name="' . $option . '" id="' . $option . '" type="' . $options_array['type'] . '" value="yes" ' . $checked . ' />' . $options_array['description'][0] . '</label>';
				if (isset($options_array['show_children_when_value']) && $options_array['show_children_when_value'] == $checked) {
					$show_children[$option] = 'style="display: block;"';
				}
				break;
			case 'select':
				$input_field = buildSelect($option, $option, $options_array['options'], $option_value);
				break;
			default:
				$size = (isset($options_array['size'])) ? $options_array['size'] : 40;
				$addl = (isset($options_array['addl'])) ? $options_array['addl'] : null;
				$input_field = '<input name="' . $option . '" id="' . $option . '" type="' . $options_array['type'] . '" value="' . $option_value . '" size="' . $size . '" ' . $addl . ' />';
		}
		if (array_key_exists('parent', $options_array)) {
			if ($options_array['parent'] === true && $option_rows) {
				$option_row_head = '</div><div id="settings-section">' . "\n";
			} elseif ($options_array['parent'] !== true) {
				if ($current_parent != $options_array['parent']) {
					if ($show_children[$options_array['parent']]) {
						$div_style = $show_children[$options_array['parent']];
					}
					$option_row_head = sprintf('<div id="%s_options" %s>', $options_array['parent'], $div_style);
					$current_parent = $options_array['parent'];
				}
			}
		} else {
			if ($current_parent) {
				$option_row_head = "</div>\n";
			}
			$current_parent = '';
		}
		$option_rows .= <<<ROW
			$option_row_head
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
	
	return ($option_rows) ? '<div id="settings-section">' . $option_rows . '</div>' : null;
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
	
	$os_name = array('openSUSE', 'Raspberry Pi', 'Raspbian');
	$os_image = array('SUSE', 'RaspberryPi', 'RaspberryPi');
	
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
 * @param string|array $message Page form response
 * @param string $title The page title
 * @param bool|array $allowed_to_add Whether the user can add new
 * @param string $name Name value of plus sign
 * @param string $rel Rel value of plus sign
 * @param string $scroll Scroll or noscroll
 * @param array $addl_title_blocks Addition blocks that will be displayed on the right side
 * @return void
 */
function printPageHeader($message = null, $title = null, $allowed_to_add = false, $name = null, $rel = null, $scroll = null, $addl_title_blocks = array()) {
	global $__FM_CONFIG;
	
	$class = $addl_buttons = null;

	if (is_array($message)) {
		if (array_key_exists('message', $message)) {
			extract($message, EXTR_OVERWRITE);
		} else {
			list($message, $comment) = $message;
		}
	}

	if (is_array($allowed_to_add)) {
		list($allowed_to_add, $addl_buttons) = $allowed_to_add;
	}

	if (empty($title)) $title = getPageTitle();
	
	$style = (empty($message)) ? 'style="display: none;"' : null;
	if (strpos($message, '</p>') === false || strpos($message, _('Database error')) !== false) {
		$message = displayResponseClose($message);
	}

	echo '<div id="body_container" class="flex-column';
	if ($scroll == 'noscroll') echo ' fm-noscroll';
	echo '">' . "\n";
	printf('<div id="body_top_container" class="flex-column">
	<div id="response" class="%s" %s>%s</div>
	<div id="page_title_container" class="flex-apart">
		<div class="flex-left">
			<div><h2>%s</h2></div>
			%s
			%s
			<div>%s</div>
		</div>
		<div class="flex-right">
			%s
		</div>
	</div>
	',
		$class, $style, $message, $title,
		($allowed_to_add) ? sprintf('<div>%s</div>', displayAddNew($name, $rel)) : null,
		($allowed_to_add && $addl_buttons) ? sprintf('<div>%s</div>', $addl_buttons) : null,
		(isset($comment)) ? sprintf('<a href="#" class="tooltip-right" data-tooltip="%s"><i class="fa fa-exclamation-triangle fa-lg notice grey" aria-hidden="true"></i></a>', $comment) : null,
		implode("\n", $addl_title_blocks)
	);
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
function setBuildUpdateConfigFlag($serial_no, $flag, $build_update, $__FM_CONFIG = null) {
	global $fmdb;
	
	if (!$__FM_CONFIG) global $__FM_CONFIG;
	
	$serial_no = sanitize($serial_no);
	/** Process server group */
	if (!empty($serial_no) && $serial_no[0] == 'g') {
		global $fm_module_servers;

		if (!class_exists('fm_module_servers')) {
			include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_servers.php');
		}
		
		$group_servers = $fm_module_servers->getGroupServers(substr($serial_no, 2));

		if (!is_array($group_servers)) return false;

		setBuildUpdateConfigFlag(implode(',', $group_servers), $flag, $build_update, $__FM_CONFIG);

		return true;
	}

	if ($serial_no) {
		$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}servers` SET `server_" . $build_update . "_config`='" . $flag . "' WHERE `server_serial_no` IN (" . $serial_no . ") AND `server_installed`='yes'";
	} else {
		$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}servers` SET `server_" . $build_update . "_config`='" . $flag . "' WHERE `server_installed`='yes' AND `server_status`='active'";
	}
	$fmdb->query($query);
	
	if ($fmdb->result) {
		if (isset($GLOBALS[$_SESSION['module']]['DNSSEC'])) {
			foreach ($GLOBALS[$_SESSION['module']]['DNSSEC'] as $items) {
				basicUpdate("fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}domains", $items['domain_id'], 'domain_dnssec_signed', $items['domain_dnssec_signed'], 'domain_id');
				if ($fmdb->sql_errors || !$fmdb->result) {
					return false;
				}
			}
		}
		return true;
	}
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
			date_default_timezone_set('UTC');
		}
	}
}


/**
 * Resets the user password.
 *
 * @since 1.0
 * @package facileManager
 */
function resetPassword($fm_login, $user_password) {
	global $fmdb;
	
	if ($user_info = getUserInfo($fm_login, 'user_login')) {
		$fm_login_id = $user_info['user_id'];

		/** Check if password is different */
		if (password_verify($user_password, getNameFromID($fm_login_id, 'fm_users', 'user_', 'user_id', 'user_password', $user_info['account_id'])))
			return _('The new password cannot be the same as the current one.');

		/** Update password */
		$query = "UPDATE `fm_users` SET `user_password`='" . password_hash($user_password, PASSWORD_DEFAULT) . "', `user_force_pwd_change`='no' WHERE `user_id`='$fm_login_id'";
		$fmdb->query($query);

		if ($fmdb->rows_affected) {
			/** Remove entry from fm_pwd_resets table */
			$query = "DELETE FROM `fm_pwd_resets` WHERE `pwd_login`='$fm_login_id'";
			$fmdb->query($query);

			return true;
		}
	}
	
	return false;
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
		if ($type == 'core') {
			include(ABSPATH . 'fm-includes/version.php');
			
			/** New versions available */
			if (isNewVersionAvailable($fm_name, $fm_version)) $badge_count++;
		}
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
 * @return string|void
 */
function buildBulkActionMenu($bulk_actions_list = null, $id = 'bulk_action') {
	if (is_array($bulk_actions_list)) {
		
		return buildSelect($id, 'bulk_action', array_merge(array(''), $bulk_actions_list), null, 1, '', false, null, null, _('Bulk Actions')) . 
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
 * @return array|string
 */
function makePlainText($text, $make_array = false) {
	$text = strip_tags((string) $text);
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
	
	$parameters = '';
	if (is_array($table_info)) {
		foreach ($table_info as $parameter => $value) {
			$parameters .= ' ' . $parameter . '="' . $value . '"';
		}
	}
	$html = '<table' . $parameters . ">\n";
	$html .= "<thead>\n<tr>\n";
	
	foreach ($head_values as $thead) {
		$parameters = '';
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
							$parameters .= ' class="header-sorted"';
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
			$sort_direction = 'ASC';
		}
		@session_start();
		$_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']] = array(
				'sort_field' => $_GET['sort_by'], 'sort_direction' => $swap_direction[$sort_direction]
			);
		session_write_close();
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
 * @param string|array) $strip Text to strip out
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
function fMDie($message = null, $link_display = 'show', $title = null) {
	global $fm_name;
	
	if (!$message) $message = _('An unknown error occurred.');
	if (!$title) $title = _('Oops!');
	
	printHeader('Error', 'install', 'no-help', 'no-menu');
	
	printf('<div id="fm-branding"><img src="%s" /><span>%s</span></div>
		<div id="window"><p>%s</p>', getBrandLogo(), $title, $message);
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
	fMDie(_('You do not have permission to view this page. Please contact your administrator for access.'), $link_display, _('Forbidden'));
}


/**
 * Whether current user has capability
 *
 * @since 1.2
 * @package facileManager
 *
 * @param string|array $capability Capability name.
 * @param string $module Module name to check capability for
 * @param string|array $extra_perm Extra capability to check
 * @return boolean
 */
function currentUserCan($capability, $module = 'facileManager', $extra_perm = null) {
	if (!isset($_SESSION['user'])) {
		return false;
	}
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
	
	return (array) $user_capabilities;
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
 * @param string $menu_icon Icon name to use for the menu
 */
function addMenuPage($menu_title, $page_title, $capability, $module, $menu_slug, $class = null, $sticky = false, $position = null, $badge_count = 0, $menu_icon = null) {
	global $menu;
	
	$new_menu = array($menu_title, $page_title, $menu_icon, $capability, $module, $menu_slug, $class, $badge_count, $sticky);
	
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
 * @param string|array $menu_title Text used to display the menu item
 * @param string $page_title Text used to display the page title when the page loads
 * @param string|array $capability Minimum capability required for the menu item to be visible to the user
 * @param string $module Module name the menu item is for
 * @param string $menu_slug Menu item slug name to used to reference this item
 * @param string $class Class name to apply to the menu item
 * @param bool $sticky Whether or not to keep the menu title when there's only one submenu item or to take on the submenu item title
 * @param integer $badge_count Number of items to display in the badge
 */
function addObjectPage($menu_title, $page_title, $capability, $module, $menu_slug, $class = null, $sticky = false, $badge_count = 0) {
	global $_fm_last_object_menu;

	$menu_icon = null;
	if (is_array($menu_title)) {
		list($menu_title, $menu_icon) = $menu_title;
	}
	
	$_fm_last_object_menu++;
	
	addMenuPage($menu_title, $page_title, $capability, $module, $menu_slug, $class, $sticky, $_fm_last_object_menu, $badge_count, $menu_icon);
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
 * @param string|array $capability Minimum capability required for the menu item to be visible to the user
 * @param string $module Module name the menu item is for
 * @param string $menu_slug Menu item slug name to used to reference this item
 * @param string $class Class name to apply to the menu item
 * @param integer $position Menu position for the item
 * @param integer $badge_count Number of items to display in the badge
 */
function addSubmenuPage($parent_slug, $menu_title, $page_title, $capability, $module, $menu_slug, $class = null, $position = null, $badge_count = 0) {
	global $submenu;
	
	$menu_icon = null;
	if (is_array($menu_title)) {
		list($menu_title, $menu_icon) = $menu_title;
	}
	
	$new_menu = array($menu_title, $page_title, $menu_icon, $capability, $module, $menu_slug, $class, $badge_count);
	
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
			$menu[$parent_menu_key][7] += $badge_count;
		}
	}
}


/**
 * Adds a submenu item to the Settings menu
 *
 * @since 1.2
 * @package facileManager
 *
 * @param string $menu_title Text used to display the menu item
 * @param string $page_title Text used to display the page title when the page loads
 * @param string|array $capability Minimum capability required for the menu item to be visible to the user
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
		fMDie(sprintf(_('PHP max_input_vars (%1$d) has been reached and %2$s or more are required. Please increase the limit to fulfill this request. Two possible methods include setting the following:') .
			'<p>%3$s.htaccess:<br /><code>php_value max_input_vars %2$s</code></p>
			<p>%3$s.user.ini:<br /><code>max_input_vars = %2$s</code></p>', ini_get('max_input_vars'), $required_input_vars, ABSPATH));
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
	
	if (!is_array($menu)) return false;
	
	if (!$search_slug) $search_slug = $GLOBALS['basename'];
	if (is_array($search_slug)) $search_slug = $search_slug[1];
	
	foreach ($menu as $position => $menu_items) {
		$parent_key = array_search($search_slug, $menu_items, true);
		if ($parent_key !== false) {
			return $menu[$position][5];
		}
	}
	
	foreach ($submenu as $parent_slug => $menu_items) {
		foreach ($menu_items as $submenu_id => $element) {
			if (array_search($search_slug, $element, true) !== false) {
				return $submenu[$parent_slug][$submenu_id][5];
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
function buildPopup($section, $text = null, $buttons = array('primary_button' => 'submit', 'cancel_button' => 'cancel'), $link = null) {
	global $__FM_CONFIG;
	
	if (!$text) $text = _('Save');
	
	if ($section == 'header') {
		return <<<HTML
		<div id="popup_response" style="display: none;"></div>
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
		<script>
			$(document).ready(function() {
				$("form .required").closest("tr").children("th").children("label").addClass("required");
				$("form[method=post]").parent().parent().find("input[type=submit].primary").addClass("follow-action");
			});
		</script>

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
	$string = preg_replace_callback("/__menu{(.+?)}/", 'getMenuURL', $html);
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
		if (!$fmdb->sql_errors && $fmdb->num_rows) return formatNumber($fmdb->last_result[0]->count);
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
		$_GET['q'] = html_entity_decode(str_replace(array('%2520'), ' ', $_GET['q']));
		$_GET['q'] = html_entity_decode(str_replace(array('%2522', '%22', '%2527', '%27', "'"), '"', $_GET['q']));
		$cleaned_q = str_replace('%20', ' ', htmlentities($_GET['q']));
		$placeholder = sprintf(_('Searched for %s'), $cleaned_q);
		$search_remove = '<i class="search_remove fa fa-remove fa-lg text_icon" title="' . _('Clear this search') . '"></i>';
		$display = ' style="display:block"';
	} else {
		$placeholder = _('Search this page by keyword');
		$search_remove = $display = null;
	}
	
	$form = <<<HTML
	<div id="search_form_container"$display>
		<div>
			<div id="search_form">
				<form id="search" method="GET" action="{$GLOBALS['basename']}?{$page_params}">
					<input type="text" class="text_icon" placeholder="$placeholder" value="$cleaned_q" />
					$search_remove
				</form>
			</div>
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
	if (is_array($array)) {
		if (is_array(@reset($array))) {
			$count = countArrayDimensions(reset($array)) + 1;
		} else {
			$count = 1;
		}
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
function displayAddNew($name = null, $rel = null, $title = null, $style = 'default', $id = 'plus', $position = 'top') {
	global $__FM_CONFIG;
	
	if (empty($title)) $title = _('Add New');
	$contents = ($style == 'default') ? $title : null;
	
	if ($name) $name = ' name="' . $name . '"';
	if ($rel) $rel = ' rel="' . $rel . '"';
	
	$image = '<i class="mini-icon ' . $style . '" title="' . $title . '">' . $contents . '</i>';
	if ($style != 'default') {
		$title = 'null" class="tooltip-' . $position . ' mini-icon" data-tooltip="' . $title;
	}
	
	return sprintf('<a id="%s" href="#" title="%s"%s%s>%s</a>', $id, $title, $name, $rel, $image);
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
	$search_query = '';
	if (isset($_GET['q'])) {
		$q = $_GET['q'];
		$absolute_q = trim($q, '"');
		$search_query = ' AND (';
		if ("\"$absolute_q\"" == $q) {
			$search_text = sprintf("='%s'", sanitize($absolute_q));
		} else {
			$search_text = sprintf("LIKE '%s'", '%' . sanitize($absolute_q) . '%');
		}
		foreach ($fields as $field) {
			$search_query .= "$prefix$field $search_text OR ";
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
	if (function_exists('dgettext') && isset($_SESSION['module'])) {
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
	
	$fm_user_caps = array();
	
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
	if (@array_key_exists('do_everything', (array) $allowed_capabilities[$fm_name])) return true;
		
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
	if (@array_key_exists($capability, (array) $allowed_capabilities[$module])) {
		if (is_array($allowed_capabilities[$module][$capability])) {
			/** Explode module groups */
			foreach ($allowed_capabilities[$module][$capability] as $cap_id) {
				if (strpos($cap_id, 'g_') !== false && function_exists('moduleExplodeGroup')) {
					if ($new_cap = moduleExplodeGroup($cap_id, $capability)) {
						$allowed_capabilities[$module][$capability] = array_merge($allowed_capabilities[$module][$capability], $new_cap);
					}
				}
			}
			if (is_array($extra_perm)) {
				$found = false;
				
				foreach ($extra_perm as $needle) {
					if (in_array((string) $needle, $allowed_capabilities[$module][$capability])) {
						$found = true;
					}
				}
				
				return $found;
			} else {
				return in_array((string) $extra_perm, $allowed_capabilities[$module][$capability]);
			}
		}
		
		return true;
	}
	
	return false;
}


/**
 * Returns if the OS is debian-based or not
 *
 * @since 2.2
 * @package facileManager
 *
 * @param string $os OS to check
 * @return boolean
 */
function isDebianSystem($os) {
	return ($os) ? in_array(strtolower($os), array('debian', 'ubuntu', 'fubuntu', 'raspbian', 'raspberry pi os')) : false;
}


/**
 * Run command on remote machines via SSH
 * 
 * @since 3.0
 * @package facileManager
 *
 * @param array $host_array Hostname of remote machine
 * @param string $command Command to run on $host
 * @param string $format Be silent or verbose with output
 * @param integer $port Remote port to connect to
 * @param string $client_check 'include' or 'skip' the client file check
 * @param string $response Response to include a close button or plaintext
 * @return array|string
 */
function runRemoteCommand($host_array, $command, $format = 'silent', $port = 22, $client_check = 'include', $response = 'close') {
	global $fm_name;
	
	$failures = false;
	
	/** Convert $host to an array */
	if (!is_array($host_array)) {
		$host_array = array($host_array);
	}
	
	/** Get SSH key */
	$ssh_key = getOption('ssh_key_priv', $_SESSION['user']['account_id']);
	if (!$ssh_key) {
		return ($response == 'close') ? displayResponseClose(noSSHDefined('key')) : noSSHDefined('key');
	}

	$temp_ssh_key = getOption('fm_temp_directory') . '/fm_id_rsa';
	if (file_exists($temp_ssh_key)) @unlink($temp_ssh_key);
	if (@file_put_contents($temp_ssh_key, $ssh_key) === false) {
		$message = sprintf(_('Failed: could not load SSH key into %s.'), $temp_ssh_key);
		return ($response == 'close') ? displayResponseClose($message) : $message;
	}

	@chmod($temp_ssh_key, 0400);

	/** Get SSH user */
	$ssh_user = getOption('ssh_user', $_SESSION['user']['account_id']);
	if (!$ssh_user) {
		if (file_exists($temp_ssh_key)) @unlink($temp_ssh_key);
		return ($response == 'close') ? displayResponseClose(noSSHDefined('user')) : noSSHDefined('user');
	}

	/** Run remote command */
	foreach ($host_array as $host) {
		/** Test the port first */
		if (!socketTest($host, $port, 10)) {
			if (file_exists($temp_ssh_key)) @unlink($temp_ssh_key);
			$message = sprintf(_('Failed: could not access %s (tcp/%d).'), $host, $port);
			return ($response == 'close') ? displayResponseClose($message) : $message;
		}

		/** Test SSH authentication */
		exec(findProgram('ssh') . " -T -i $temp_ssh_key -o 'StrictHostKeyChecking no' -p $port -l $ssh_user $host 'ls /usr/local/$fm_name/{$_SESSION['module']}/client.php'", $output, $rc);
		if ($rc) {
			/** Something went wrong */
			if ($rc == 255 || $client_check == 'include') {
				@unlink($temp_ssh_key);
			}

			/** Handle error codes */
			if ($rc == 255) {
				$message = _('Failed: Could not login via SSH. Check the system logs on the client for the reason.');
				return ($response == 'close') ? displayResponseClose($message) : $message;
			} elseif ($client_check == 'include') {
				$message = _('Failed: Client file is not present - is the client software installed?');
				return ($response == 'close') ? displayResponseClose($message) : $message;
			}
		}
		unset($output);

		exec(findProgram('ssh') . " -T -i $temp_ssh_key -o 'StrictHostKeyChecking no' -p $port -l $ssh_user $host \"$command\"", $output, $rc);
	
		if ($rc) {
			$failures = true;
		} elseif ($format == 'silent') {
			$output = array();
		}

		if ($format == 'verbose') {
			if (isset($output)) {
				echo "<p><b>$host</b><br />" . join('<br />', $output) . '</p>';
			}
		}
	}

	@unlink($temp_ssh_key);
	
	return array('failures' => $failures, 'output' => $output);
}


/**
 * Use MySQLi or not
 *
 * @since 3.0
 * @package facileManager
 *
 * @return boolean
 */
function useMySQLi() {
	include(ABSPATH . 'fm-includes/version.php');
		
	if (function_exists('mysqli_connect')) {
		if (version_compare(phpversion(), '5.5', '>=') || ! function_exists('mysql_connect')) {
			return true;
		} elseif (strpos($fm_version, '-') !== false) {
			return true;
		}
	}
	
	return false;
}


/**
 * Send log message to syslog
 *
 * @since 3.0
 * @package facileManager
 *
 * @param string $message Message to send
 * @param string $module Module name sending the message
 * @return null
 */
function addSyslogEntry($message, $module) {
	$syslog_facility = getOption('syslog_facility');
	
	if ($syslog_facility) {
		openlog($module, LOG_PERROR, $syslog_facility);
		$x = 0;
		foreach (explode("\n", $message) as $line) {
			if ($x) {
				$line = "  --> $line";
			}
			syslog(LOG_INFO, $line);
			$x++;
		}
		closelog();
	}
}


/**
 * Performs a recursive is_writable
 *
 * @since 3.0
 * @package facileManager
 *
 * @param string $dir Top level directory to check
 * @param string|array $exclude Filenames to exclude
 * @return boolean
 */
function is_writable_r($dir, $exclude = array()) {
	if (!is_array($exclude)) {
		$exclude = array($exclude);
	}
	if (is_dir($dir)) {
		if (is_writable($dir)) {
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object != '.' && $object != '..') {
					if (!is_writable_r($dir . DIRECTORY_SEPARATOR . $object) && !in_array($object, $exclude)) return false;
					else continue;
				}
			}    
			return true;    
		} else {
			return false;
		}
	} elseif (file_exists($dir)) {
		return is_writable($dir);
	}
}


/**
 * Downloads a file from the fM website
 *
 * @since 3.0
 * @package facileManager
 *
 * @param string $file File to download
 * @return array|string
 */
function downloadfMFile($file) {
	global $__FM_CONFIG;
	
	$message = "Downloading $file\n";
	
	list($tmp_dir, $created) = clearUpdateDir();
	if (!$created) {
		return sprintf('<p>' . _('%s and %s need to be writeable by %s in order for the core and modules to be updated automatically.') . "</p>\n", $tmp_dir, ABSPATH, $__FM_CONFIG['webserver']['user_info']['name']);
	}

	$local_file = $tmp_dir . basename($file);
	@unlink($local_file);
	
	$fh = fopen($local_file, 'w+');
	$options = array(
		CURLOPT_URL				=> $file,
		CURLOPT_TIMEOUT			=> 3600,
		CURLOPT_HEADER			=> false,
		CURLOPT_FOLLOWLOCATION	=> true,
		CURLOPT_SSL_VERIFYPEER  => false,
		CURLOPT_RETURNTRANSFER  => true
	);
	
	$proxy = array();
	if (getOption('proxy_enable')) {
		$proxyauth = getOption('proxy_user') . ':' . getOption('proxy_pass');
		if ($proxyauth == ':') $proxyauth = null;
		$proxy = array(
			CURLOPT_PROXY => getOption('proxy_host') . ':' . getOption('proxy_port'),
			CURLOPT_PROXYUSERPWD => $proxyauth
		);
	}
	$ch = curl_init();
	curl_setopt_array($ch, ($options + $proxy));
	$result = curl_exec($ch);
	@fputs($fh, $result);
	@fclose($fh);
	if ($result === false || curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
		$message .= "Unable to download file.\n";
		$message .= "\n" . curl_error($ch) . "\n";
		
		curl_close($ch);
		$local_file = false;
	}
	
	curl_close($ch);
	
	return array($message, $local_file);
}


/**
 * Extracts files
 *
 * @since 3.0
 * @package facileManager
 *
 * @param array $package Package names to extract
 */
function extractPackage($package) {
	$message = '';
	
	if (!is_array($package)) {
		$package = array($package);
	}
	
	foreach ($package as $filename) {
		$message .= sprintf(_('Extracting %s') . "\n", basename($filename));

		$tmp_dir = dirname($filename);
		if (!file_exists($filename)) {
			return sprintf(_('%s does not exist!') . "\n", $filename);
		}

		$path_parts = pathinfo($filename);
		$untar_opt = '-C ' . $tmp_dir . ' -x';
		switch($path_parts['extension']) {
			case 'bz2':
				$untar_opt .= 'j';
				break;
			case 'tgz':
			case 'gz':
				$untar_opt .= 'z';
				break;
		}
		$untar_opt .= 'f';

		$command = findProgram('tar') . " $untar_opt $filename";
		@system($command, $retval);
		if ($retval) {
			$message .= sprintf(_('Failed to extract %s!') . "\n", $filename);
			return $message;
		}
	}
		
	/** Move files */
	$message .= sprintf(_('Moving files to %s') . "\n", ABSPATH);
	$command = findProgram('cp') . " -r $tmp_dir/facileManager/server/* " . ABSPATH;
	@system($command, $retval);
	if ($retval) {
		$message .= _('Failed to save files!') . "\n";
	}
	
	if ($tmp_dir != '/') {
		@system(findProgram('rm') . " -rf $tmp_dir");
	}
	
	return $message;
}


/**
 * Clears out the temporary update directory
 *
 * @since 3.0
 * @package facileManager
 *
 * @return array Temp directory and if it was created
 */
function clearUpdateDir() {
	return createTempDir('fm_updates');
}


/**
 * Generic mailing function
 *
 * @since 3.0
 * @package facileManager
 *
 * @param string $sendto Email address to send to
 * @param string $subject Email subject
 * @param string $body Email body
 * @param string $altbody Email alternate body (plaintext)
 * @param string|array $from From name and address
 * @param array $images Images to embed in the email
 * @return boolean|string
 */
function sendEmail($sendto, $subject, $body, $altbody = null, $from = null, $images = null, $options = array(), $output_format = 'hidden') {
	global $fm_name;

	$phpmailer_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $fm_name . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'PHPMailer' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'PHPMailer.php';
	if (!file_exists($phpmailer_file)) {
		return _('Unable to send email - PHPMailer class is missing.');
	}

	extract($options);

	require($phpmailer_file);
	require(dirname($phpmailer_file) . DIRECTORY_SEPARATOR . 'SMTP.php');
	require(dirname($phpmailer_file) . DIRECTORY_SEPARATOR . 'Exception.php');
	$mail = new PHPMailer\PHPMailer\PHPMailer;

	/** Set PHPMailer options */
	if ($output_format == 'debug') $mail->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_CONNECTION;
	$mail->Host = isset($mail_smtp_host) ? $mail_smtp_host : getOption('mail_smtp_host');
	$mail->Port = isset($mail_smtp_port) ? $mail_smtp_port : getOption('mail_smtp_port');
	$mail->SMTPAuth = isset($mail_smtp_auth) ? $mail_smtp_auth : getOption('mail_smtp_auth');
	if ($mail->SMTPAuth) {
		$mail->Username = isset($mail_smtp_user) ? $mail_smtp_user : getOption('mail_smtp_user');
		$mail->Password = isset($mail_smtp_pass) ? $mail_smtp_pass : getOption('mail_smtp_pass');
	}
	$secure = isset($mail_smtp_tls) ? $mail_smtp_tls : getOption('mail_smtp_tls');
	if ($secure) $mail->SMTPSecure = strtolower($secure);

	if ($from) {
		if (is_array($from)) {
			list($from_name, $from_addr) = $from;
			$mail->FromName = $from_name;
		} else {
			$from_addr = $from;
		}
		$mail->From = $from_addr;
	} else {
		$mail->FromName = $fm_name;
		$mail->From = isset($mail_from) ? $mail_from : getOption('mail_from');
	}
	$mail->AddAddress($sendto);

	$mail->Subject = $subject;
	$mail->Body = $body;
	if ($altbody) {
		$mail->AltBody = $altbody;
	}
	$mail->IsHTML(true);
	
	if (is_array($images)) {
		foreach ($images as $filename) {
			$image_parts = pathinfo($filename);
			$mail->AddEmbeddedImage($filename, $image_parts['filename'], $image_parts['basename'], 'base64', "image/{$image_parts['extension']}");
		}
	}

	$mail->IsSMTP();

	if(!$mail->Send()) {
		return (getOption('show_errors')) ? sprintf(_('Mailer Error: %s'), $mail->ErrorInfo) : true;
	}

	return true;
}


/**
 * Create a temporary directory
 *
 * @since 3.0
 * @package facileManager
 *
 * @param string $subdir Sub directory name
 * @param string $append String to append to directory name
 * @return array Temp directory and if it was created
 */
function createTempDir($subdir, $append = null) {
	$created = true;
	
	if ($append) {
		$subdir .= ($append == 'datetime') ? '_' . date("YmdHis") : "_$append";
	}
	
	$fm_temp_directory = '/' . ltrim(getOption('fm_temp_directory'), '/');
	$tmp_dir = rtrim($fm_temp_directory, '/') . "/$subdir/";
	system('rm -rf ' . $tmp_dir);
	if (!is_dir($tmp_dir)) {
		if (!@mkdir($tmp_dir, 0777, true)) {
			$created = false;
		}
	}

	return array($tmp_dir, $created);
}


/**
 * Displays a close button in the response
 *
 * @since 3.0
 * @package facileManager
 *
 * @param string $message Error message to include
 * @param string $class Class to apply to message
 * @return string
 */
function displayResponseClose($message, $class = 'error') {
	return sprintf('<div id="response_close"><p><i class="fa fa-close close" aria-hidden="true" title="%s"></i></p></div><p class="%s">%s</p>', _('Close'), $class, $message);
}


/**
 * Generates URI params from current params
 *
 * @since 3.0
 * @package facileManager
 *
 * @param array $params Params to exclude or include
 * @param string $direction Exclude or include
 * @param string $character Starting character
 * @param array $null_params Params to return null with
 * @return string
 */
function generateURIParams($params = array(), $direction = 'include', $character = '?', $null_params = array()) {
	$uri_params = array();
	
	foreach ($GLOBALS['URI'] as $param => $val) {
		if (in_array($param, (array) $null_params)) return null;
		if ($direction == 'include') {
			if (!in_array($param, (array) $params)) continue;
		} else {
			if (in_array($param, (array) $params)) continue;
		}
		$uri_params[] = "$param=$val";
	}
	$uri_params = ($uri_params) ? $character . implode('&', $uri_params) : '';
	
	return $uri_params;
}


/**
 * Builds the page sub menu items
 *
 * @since 3.0
 * @package facileManager
 *
 * @param string $selected Selected option type
 * @param array $avail_types Available option types
 * @param array $null_params
 * @param array $params
 * @param string $direction
 * @param string $character
 * @return string
 */
function buildSubMenu($selected, $avail_types, $null_params = array(), $params = array('type', 'action', 'id', 'status'), $direction = 'exclude', $character = '&') {
	global $__FM_CONFIG;

	if (count($avail_types) <= 1) return '';
	
	$menu_selects = '';
	
	$uri_params = generateURIParams($params, $direction, $character, $null_params);
	
	foreach ($avail_types as $general => $type) {
		$select = ($selected == $general) ? ' class="selected"' : '';
		$menu_selects .= "<li$select><a$select href=\"{$GLOBALS['basename']}?type=$general$uri_params\">" . ucfirst($type) . "</a></li>\n";
	}
	
	return '<div class="tab-strip"><ul>' . $menu_selects . '</ul></div>';
}


/**
 * Formats an error message
 *
 * @since 3.0
 * @package facileManager
 *
 * @param string $message Error message to format
 * @param string $option Display option (sql | null)
 * @return string
 */
function formatError($message, $option = null) {
	global $fmdb;
	
	$addl_text = null;
	
	if ($option == 'sql') {
		$addl_text = ($fmdb->last_error) ? sprintf(' [<a class="more" href="#">%s</a>]', _('more')) . $fmdb->last_error : null;
		$message = displayResponseClose($message . $addl_text);
	}
	
	return $message;
}


/**
 * Builds the server listing in a dropdown menu
 *
 * @since 3.0
 * @package facileManager
 *
 * @param integer $server_serial_no Selected server serial number
 * @param array $available_servers Available servers for the list
 * @param array $class Additional classes to pass to the div
 * @return string
 */
function buildServerSubMenu($server_serial_no = 0, $available_servers = null, $class = null, $placeholder = null) {
	if (!$available_servers) $available_servers = availableServers();
	$server_list = buildSelect('server_serial_no', 'server_serial_no', $available_servers, $server_serial_no, 1, null, false, 'this.form.submit()', null, $placeholder);
	
	$hidden_inputs = '';
	foreach ($GLOBALS['URI'] as $param => $value) {
		if ($param == 'server_serial_no') continue;
		$hidden_inputs .= '<input type="hidden" name="' . $param . '" value="' . $value . '" />' . "\n";
	}
	
	$class = $class ? 'class="' . join(' ', (array) $class) . '"' : null;

	$return = <<<HTML
	<div id="configtypesmenu" $class>
		<form action="{$GLOBALS['basename']}" method="GET">
		$hidden_inputs
		$server_list
		</form>
	</div>
HTML;

	return $return;
}


/**
 * Returns an array of servers and groups
 *
 * @since 3.0
 * @package facileManager
 *
 * @param string $server_id_type What server ID should be used (serial|id)
 * @param array|string $include What items to include
 * @param string $module Module name to limit list to
 * @return array
 */
function availableServers($server_id_type = 'serial', $include = array('all'), $module = null) {
	global $fmdb, $__FM_CONFIG;
	
	$server_array = array();
	
	if (!$module) {
		$module = $_SESSION['module'];
	}
	
	if (!is_array($include)) {
		$include = (array) $include;
	}
	
	if (in_array('null', $include)) {
		$server_array[0][] = null;
		$server_array[0][0][] = null;
		$server_array[0][0][] = null;
	}
	
	if (in_array('all', $include)) {
		$server_array[0][] = null;
		$server_array[0][0][] = _('All Servers');
		$server_array[0][0][] = '0';
	}
	
	if (in_array('all', $include) || in_array('groups', $include)) {
		$j = 0;
		/** Server Groups */
		$result = basicGetList('fm_' . $__FM_CONFIG[$module]['prefix'] . 'server_groups', 'group_name', 'group_');
		if ($fmdb->num_rows && !$fmdb->sql_errors) {
			$server_array[_('Groups')][] = null;
			foreach ($fmdb->last_result as $results) {
				$server_array[_('Groups')][$j][] = $results->group_name;
				$server_array[_('Groups')][$j][] = 'g_' . $results->group_id;
				$j++;
			}
		}
	}
	if (in_array('all', $include) || in_array('servers', $include)) {
		$j = 0;
		/** Server names */
		$result = basicGetList('fm_' . $__FM_CONFIG[$module]['prefix'] . 'servers', 'server_name', 'server_', 'active');
		if ($fmdb->num_rows && !$fmdb->sql_errors) {
			$server_array[_('Servers')][] = null;
			foreach ($fmdb->last_result as $results) {
				if (property_exists($results, 'server_menu_display') && $results->server_menu_display == 'exclude') continue;
				$server_array[_('Servers')][$j][] = $results->server_name;
				if ($server_id_type == 'serial') {
					$server_array[_('Servers')][$j][] = $results->server_serial_no;
				} elseif ($server_id_type == 'id') {
					$server_array[_('Servers')][$j][] = 's_' . $results->server_id;
				}
				$j++;
			}
		}
	}
	
	return $server_array;
}


/**
 * Returns a SSH error message
 *
 * @since 3.0
 * @package facileManager
 *
 * @param string $type What is not defined
 * @return string
 */
function noSSHDefined($type = 'user') {
	if ($type == 'user') {
		return sprintf(_('Failed: SSH user is not defined. You can define the user in the <a href="%s">Settings</a>.'), getMenuURL(_('General')));
	}
	if ($type == 'key') {
		return sprintf(_('Failed: SSH key is not defined. You can generate a keypair in the <a href="%s">Settings</a>.'), getMenuURL(_('General')));
	}
}


/**
 * Returns the branding logo
 *
 * @since 3.0
 * @package facileManager
 *
 * @return string
 */
function getBrandLogo($size = 'sm_brand_img') {
	global $fm_name;
	
	$branding_logo = getOption($size);
	
	if (!$branding_logo) {
		$branding_logo = $GLOBALS['RELPATH'] . 'fm-modules/' . $fm_name . '/images/fm.png';
	}
	
	return $branding_logo;
}


/**
 * Automatically run remote commands
 *
 * @since 3.3
 * @package facileManager
 *
 * @param object $server_info Server info object from db query
 * @param array $command_args Action and command arguments
 * @param string $output_type Return as popup or something else (popup|return)
 * @return string
 */
function autoRunRemoteCommand($server_info, $command_args, $output_type = 'popup') {
	extract(get_object_vars($server_info), EXTR_SKIP);

	/** Disabled server */
	if ($server_status != 'active') {
		return null;
	}
	
	if (is_array($command_args)) {
		list($action, $command_args) = $command_args;
	}

	/** Get zone data via ssh */
	if ($server_update_method == 'ssh') {
		$server_remote = runRemoteCommand($server_name, 'sudo php /usr/local/facileManager/' . $_SESSION['module'] . '/client.php ' . $command_args, 'return', $server_update_port, 'include', 'plaintext');
	} elseif (in_array($server_update_method, array('http', 'https'))) {
		/** Get data via http(s) */
		/** Test the port first */
		if (socketTest($server_name, $server_update_port, 10)) {
			/** Remote URL to use */
			$url = $server_update_method . '://' . $server_name . ':' . $server_update_port . '/fM/reload.php';

			/** Data to post to $url */
			$post_data = array('action' => $action,
				'serial_no' => $server_serial_no,
				'module' => $_SESSION['module'],
				'command_args' => $command_args
			);

			$server_remote = getPostData($url, $post_data);
			if (isSerialized($server_remote)) {
				$server_remote = unserialize($server_remote);
			}
		}
	}

	if (isset($server_remote) && $server_remote !== false) {
		if (is_array($server_remote)) {
			if (isset($server_remote['failures']) && !$server_remote['failures']) {
				if (@isSerialized($server_remote['output'][0])) {
					$server_remote['output'] = unserialize($server_remote['output'][0]);
				}
				return $server_remote['output'];
			}
		} else {
			return (strpos($server_remote, 'popup') === false || $output_type != 'popup') ? $server_remote : buildPopup('header', _('Error')) . '<p>' . $server_remote . '</p>' . buildPopup('footer', _('OK'), array('cancel_button' => 'cancel'));
		}
	} else {
		/** Return if the items did not get dumped from the server */
		if (!isset($server_remote['output'])) {
			$return = sprintf('<p>%s</p>', __('The data from the server could not be retrieved or managed. Possible causes include:'));
			$return .= sprintf('<ul><li>%s</li><li>%s</li></ul>',
					__('The update ports on the server are not accessible'),
					__('This server is updated via cron (only SSH and http/https are supported)'));
			return $return;
		}
	}
}


/**
 * Returns the ID of the default super admin
 *
 * @since 3.3.1
 * @package facileManager
 *
 * @return integer
 */
function getDefaultAdminID() {
	global $fmdb;
	
	$result = $fmdb->query("SELECT user_id FROM `fm_users` WHERE `user_auth_type`=1 ORDER BY user_id ASC LIMIT 1");

	return $fmdb->last_result[0]->user_id;
}


/**
 * Returns the first array key
 *
 * @since 4.0
 * @package facileManager
 *
 * @return mixed
 */
if (!function_exists('array_key_first')) {
	function array_key_first(array $arr) {
		foreach($arr as $key => $unused) {
			return $key;
		}
		return null;
	}
}


/**
 * Throws an API error
 *
 * @since 4.0
 * @package facileManager
 *
 * @param integer $code Error code to throw
 * @return mixed
 */
function throwAPIError($code) {
	switch ($code) {
		case 1000:
		case 1001:
		case 1002:
			$message = _('Permission denied.');
			break;
		case 1004:
			$message = _('The record already exists.');
			break;
		case 1005:
			$message = _('The record was not found.');
			break;
		case 3000:
			$message = _('Dryrun was successful.');
			break;
		default:
			$code = 2000;
			$message = _('Something was wrong with the request.');
			break;
		}
	return array($code, $message);
}


/**
 * Trims and sanitizes post input
 *
 * @since 4.7.0
 * @package facileManager
 *
 * @param array $post Data to clean
 * @return array
 */
function cleanAndTrimInputs($post) {
	/** Trim and sanitize inputs */
	foreach($post as $k => $v) {
		if (is_array($post[$k])) {
			$post[$k] = cleanAndTrimInputs($post[$k]);
		} else {
			$post[$k] = sanitize(trim($v));
		}
	}
	
	return $post;	
}


/**
 * Gets the server/group name
 *
 * @since 5.0.0
 * @package facileManager
 *
 * @param string|integer $id Serial number or group ID
 * @return string
 */
function getServerName($id) {
	global $__FM_CONFIG;

	if (!$id) {
		return _('All Servers');
	} elseif (strpos($id, 'g_') !== false) {
		return getNameFromID(str_replace('g_', '', $id), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'server_groups', 'group_', 'group_id', 'group_name');
	} elseif (strpos($id, 's_') !== false) {
		return getNameFromID(str_replace('s_', '', $id), 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
	} elseif (intval($id)) {
		return getNameFromID($id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name');
	}

	return false;
}


/**
 * Gets the available themes
 *
 * @since 5.0.0
 * @package facileManager
 *
 * @return array
 */
function getThemes() {
	$theme_css = dirname(__FILE__) . '/css/themes.css';
	$theme_contents = file_get_contents($theme_css);

	$themes = array();

	preg_match_all('/\.([A-Za-z0-9_\-])+ {/', $theme_contents, $selectors);
	if (is_array($selectors[0])) {
		$themes = str_replace(array('.', ' {'), '', $selectors[0]);
	}
	$themes = array_unique($themes);
	sort($themes);
	
	return $themes;
}


/**
 * Gets the maintenance mode status
 *
 * @since 5.0.0
 * @package facileManager
 *
 * @return bool
 */
function isMaintenanceMode() {
	return getOption('maintenance_mode');
}
