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
 | Processes server keys management page                                   |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

if (!isset($template_type)) header('Location: ' . $GLOBALS['RELPATH']);

$tpl_perms = array('manage_zones', 'view_all');
if (isset($tpl_extra_perm)) $tpl_perms[] = $tpl_extra_perm;

if (!currentUserCan($tpl_perms, $_SESSION['module'])) unAuth();

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_templates.php');

$response = isset($response) ? $response : null;

printHeader();
@printMenu();

echo printPageHeader($response, null, currentUserCan('manage_zones', $_SESSION['module']));
	
$sort_direction = null;
$sort_field = $template_type . '_name';
$table = !isset($table) ? $template_type : $table;
if (isset($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']])) {
	extract($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']], EXTR_OVERWRITE);
}

$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . $table, array($sort_field, $template_type . '_name'), $template_type . '_', "AND {$template_type}_template='yes' " . (string) $limited_domain_ids, null, false, $sort_direction);
$fm_module_templates->rows($result, $template_type);

printFooter();

?>
