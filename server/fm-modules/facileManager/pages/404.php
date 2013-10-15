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
 * Displays 404 Error
 *
 * @author		Jon LaBass
 * @version		$Id:$
 * @copyright	2013
 *
 */

printHeader('File Not Found', 'install');

$referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $GLOBALS['RELPATH'];

echo <<<HTML
<div id="message"><p class="failed">file not found</p></div>
<p>The file you tried ({$_SERVER['REQUEST_URI']}) is not found at this location.  The URL or link may be outdated or incorrect.</p>
<p>If you typed the URL in the address bar, please make sure the spelling is correct.</p>
<p id="forgotton_link"><a href="$referrer">&larr; Back</a></p>

HTML;

exit(printFooter());

?>