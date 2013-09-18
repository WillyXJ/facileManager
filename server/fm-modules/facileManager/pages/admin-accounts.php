<?php

/**
 * Processes accounts management page
 *
 * @author		Jon LaBass
 * @version		$Id:$
 * @copyright	2013
 *
 */

/** Handle client installations */
if (array_key_exists('verify', $_GET)) {
	define('CLIENT', true);
	
	require_once('fm-init.php');
	include(ABSPATH . 'fm-modules/facileManager/classes/class_accounts.php');
	
	if ($_POST['compress']) echo gzcompress(serialize($fm_accounts->verify($_POST)));
	else echo serialize($fm_accounts->verify($_POST));
	exit;
}

header('Location: ' . $GLOBALS['RELPATH']);

?>