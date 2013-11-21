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
 | Processes main page                                                     |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

/* Redirect to activate modules if none are active */
if ($_SESSION['module'] == $fm_name) {
	header('Location: admin-modules');
}

$page_name = 'Dashboard';
$page_name_sub = null;

printHeader();
@printMenu($page_name, $page_name_sub);

$response = functionalCheck();
$line_count = substr_count($response, '<p>');
$line_height = $line_count * 1.5 + .8;
$margin = ($line_height - 1 < 1.5) ? 4 : $line_height + 1.5;
$style = ($line_count > 1) ? ' style="height: ' . $line_height . 'em;"' : null;

$dashboard = buildDashboard();

echo '<div id="body_container">' . "\n";
if (!empty($response)) echo '<div id="response"' . $style . '>' . $response . "</div>\n";
echo <<<HTML
	<h2>$page_name</h2>
	$dashboard
<pre>
HTML;

echo '</pre><div>';
printFooter();

?>
