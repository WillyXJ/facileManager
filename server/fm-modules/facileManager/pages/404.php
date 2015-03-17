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

printHeader(_('File Not Found'), 'install');

printf('<div id="message"><p class="failed">' . _('File Not Found') . '</p></div>
<p>' . _('The file you tried (%s) is not found at this location. The URL or link may be outdated or incorrect.') . '</p>
<p>' . _('If you typed the URL in the address bar, please make sure the spelling is correct.') . '</p>
<p id="forgotton_link"><a href="javascript:history.back();">' . _('&larr; Back') . '</a></p>', $_SERVER['REQUEST_URI']);

exit(printFooter());

?>