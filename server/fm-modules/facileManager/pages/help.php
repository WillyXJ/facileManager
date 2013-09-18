<?php

/**
 * Shows the user help files
 *
 * @author		Jon LaBass
 * @version		$Id:$
 * @copyright	2013
 *
 */

define('CLIENT', true);

require_once('fm-init.php');

require_once(ABSPATH . 'fm-modules/facileManager/classes/class_logins.php');

/** Enforce authentication */
if (!$fm_login->isLoggedIn()) {
	echo '<script>close();</script>';
	echo '<pre>You must be logged in to view these files.</pre>';
	exit;
}

printHeader('fmHelp', 'facileManager', true);

echo '<div id="help_file_container" style="padding-top: 5em;">' . "\n";
echo buildHelpFile();
echo '</div>' . "\n";

printFooter();

?>
