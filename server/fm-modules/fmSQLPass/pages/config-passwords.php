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

$response = isset($response) ? $response : null;

printHeader();
@printMenu($page_name, $page_name_sub);

include(ABSPATH . 'fm-modules/fmSQLPass/classes/class_passwords.php');
include(ABSPATH . 'fm-modules/facileManager/classes/class_users.php');

echo printPageHeader($response, 'Password Manager');

$result = basicGetList('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'groups', 'group_name', 'group_', 'active');
$fm_sqlpass_passwords->rows($result);

printFooter();

?>
