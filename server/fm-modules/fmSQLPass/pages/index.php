<?php

/**
 * Processes main page
 *
 * @author		Jon LaBass
 * @version		$Id:$
 * @copyright	2013
 *
 */

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

if (!empty($response)) echo '<div id="response"' . $style . '>' . $response . "</div>\n";
echo '<div id="body_container"';
if (!empty($response)) echo ' style="margin-top: ' . $margin . 'em;"';
echo <<<HTML
>
	<h2>$page_name</h2>
	$dashboard
</div>
HTML;

printFooter();

?>
