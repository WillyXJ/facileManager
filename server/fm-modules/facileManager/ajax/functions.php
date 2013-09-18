<?php

/**
 * Common AJAX processing functions
 *
 * @author		Jon LaBass
 * @version		$Id:$
 * @copyright	2013
 *
 */

function returnError($window = true) {
	$msg = 'There was a problem with your request.'; 
	if ($window) {
		echo '<h2>Error</h2>' . "\n";
		echo '<p>' . $msg . "</p>\n";
		echo '<br /><input type="submit" value="OK" class="button cancel" id="cancel_button" />' . "\n";
	} else {
		echo '<p class="error">' . $msg . "</p>\n";
	}
	exit;
}


function returnUnAuth($window = true) {
	$msg = 'You are not authorized to make changes.';
	if ($window) {
		echo '<h2>Error</h2>' . "\n";
		echo '<p>' . $msg . "</p>\n";
		echo '<br /><input type="submit" value="OK" class="button cancel" id="cancel_button" />' . "\n";
	} else {
		echo '<p class="error">' . $msg . "</p>\n";
	}
	exit;
}

?>