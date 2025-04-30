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
 * Displays 404 Error
 */

$branding_logo = '<i class="fa fa-exclamation-triangle" aria-hidden="true"></i>';

printHeader(_('File Not Found'), 'install');

printf('<div id="fm-branding">
<span>%s %s</span></div>
<div id="window"><p>%s</p>
<p>%s</p>
<p id="forgotton_link"><a href="javascript:history.back();">%s</a></p></div>',
		$branding_logo, _('File Not Found'),
		sprintf(_('The file you tried (%s) is not found at this location. The URL or link may be outdated or incorrect.'), $_SERVER['REQUEST_URI']),
		_('If you typed the URL in the address bar, please make sure the spelling is correct.'),
		_('&larr; Back')
	);

printFooter();
exit();
