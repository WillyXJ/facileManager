<?php

/**
 * Processes main page
 *
 * @author		Jon LaBass
 * @version		$Id:$
 * @copyright	2013
 *
 */

$page_name = 'Config';
$page_name_sub = 'Passwords';

printHeader();
@printMenu($page_name, $page_name_sub);

include(ABSPATH . 'fm-modules/fmSQLPass/classes/class_passwords.php');
include(ABSPATH . 'fm-modules/facileManager/classes/class_users.php');

if (!empty($response)) echo '<div id="response"><p class="error">' . $response . "</p></div>\n";
echo '<div id="response" style="display: none;"></div>' . "\n";
echo '<div id="body_container"';
if (!empty($response)) echo ' style="margin-top: 4em;"';
echo '>
	<h2>Password Manager';

if ($allowed_to_manage_passwords) {
}

echo '</h2>' . "\n";

$result = basicGetList('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'groups', 'group_name', 'group_', 'active');
$fm_sqlpass_passwords->rows($result);

printFooter();

?>
