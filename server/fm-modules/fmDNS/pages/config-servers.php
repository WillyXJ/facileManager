<?php

/**
 * Processes servers config page
 *
 * @author		Jon LaBass
 * @version		$Id:$
 * @copyright	2013
 *
 */

$page_name = 'Config';
$page_name_sub = 'Servers';

include(ABSPATH . 'fm-modules/fmDNS/classes/class_servers.php');

if ($allowed_to_manage_servers) {
	$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'add';
	switch ($action) {
	case 'add':
		if (!empty($_POST)) {
			$result = $fm_module_servers->add($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else header('Location: ' . $GLOBALS['basename']);
		}
		break;
	case 'delete':
		if (isset($_GET['id']) && !empty($_GET['id'])) {
			$server_delete_status = $fm_module_servers->delete(sanitize($_GET['id']));
			if ($server_delete_status !== true) {
				$response = $server_delete_status;
				$action = 'add';
			} else header('Location: ' . $GLOBALS['basename']);
		}
		break;
	case 'edit':
		if (!empty($_POST)) {
			$result = $fm_module_servers->update($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else header('Location: ' . $GLOBALS['basename']);
		}
		if (isset($_GET['status'])) {
			if (!updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', $_GET['id'], 'server_', $_GET['status'], 'server_id')) {
				$response = 'This server could not be ' . $_GET['status'] . '.';
			} else {
				/* set the server_build_config flag */
				$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` SET `server_build_config`='yes' WHERE `server_id`=" . sanitize($_GET['id']);
				$result = $fmdb->query($query);
				
				$tmp_name = getNameFromID($_GET['id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
				addLogEntry("Set server '$tmp_name' status to " . $_GET['status'] . '.');
				header('Location: ' . $GLOBALS['basename']);
			}
		}
		break;
	}
}

printHeader();
@printMenu($page_name, $page_name_sub);

if (!empty($response)) echo '<div id="response"><p class="error">' . $response . "</p></div>\n";
echo '<div id="response" style="display: none;"></div>' . "\n";
echo '<div id="body_container"';
if (!empty($response)) echo ' style="margin-top: 4em;"';
echo '>
	<h2>Name Servers';

if ($allowed_to_manage_servers) {
	echo '<a id="plus" href="#" title="Add New">' . $__FM_CONFIG['icons']['add'] . '</a>';
}

echo '</h2>' . "\n";
	
$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_name', 'server_');
$fm_module_servers->rows($result);

printFooter();

?>
