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
 | Processes module dashboard page                                         |
 +-------------------------------------------------------------------------+
*/

if (defined('CLIENT')) {
    header('Location: ' . $GLOBALS['RELPATH']);
    exit;
}

if (defined('NO_DASH')) {
    if (isset($__FM_CONFIG['homepage'])) {
        header('Location: ' . $__FM_CONFIG['homepage']);
    } else {
        list($filtered_menu, $filtered_submenu) = getCurrentUserMenu();
        ksort($filtered_menu);
        ksort($filtered_submenu);
        
        $i = 1;
        foreach ($filtered_menu as $position => $main_menu_array) {
            if (strpos($main_menu_array[4], '.php') !== false) {
                header('Location: ' . $main_menu_array[4]);
                break;
            }
        }
    }
    exit;
}

printHeader();
@printMenu();

$response = isset($response) ? $response : functionalCheck();

echo printPageHeader($response, null, false) . buildDashboard();

printFooter();

?>
