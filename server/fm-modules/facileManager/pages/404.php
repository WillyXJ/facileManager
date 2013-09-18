<?php

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

printFooter();
exit;


?>