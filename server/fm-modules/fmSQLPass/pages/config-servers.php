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
$page_name_sub = 'Servers';

include(ABSPATH . 'fm-modules/fmSQLPass/classes/class_servers.php');

$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'add';
if ($allowed_to_manage_servers) {
	switch ($action) {
	case 'add':
		if (!empty($_POST)) {
			if (!$fm_module_servers->add($_POST)) {
				$response = 'This database server could not be added.'. "\n";
				$form_data = $_POST;
			} else header('Location: ' . $GLOBALS['basename']);
		}
		break;
	case 'delete':
		if (isset($_GET['id'])) {
			$delete_status = $fm_module_servers->delete(sanitize($_GET['id']));
			if ($delete_status !== true) {
				$response = $delete_status;
			} else header('Location: ' . $GLOBALS['basename']);
		}
		break;
	case 'edit':
		if (!empty($_POST)) {
			if (!$fm_module_servers->update($_POST)) {
				$response = 'This database server could not be updated.'. "\n";
				$form_data = $_POST;
			} else header('Location: ' . $GLOBALS['basename']);
		}
		if (isset($_GET['status'])) {
			if (!updateStatus('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'servers', $_GET['id'], 'server_', $_GET['status'], 'server_id')) {
				$response = 'This database server could not be '. $_GET['status'] .'.'. "\n";
			} else {
				$tmp_name = getNameFromID($_GET['id'], 'fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
				addLogEntry("Set database server '$tmp_name' status to " . $_GET['status'] . '.');
				header('Location: ' . $GLOBALS['basename']);
			}
		}
	}
}

printHeader();
@printMenu($page_name, $page_name_sub);

if (!empty($response)) echo '<div id="response"><p class="error">' . $response . "</p></div>\n";
echo '<div id="response" style="display: none;"></div>' . "\n";
echo '<div id="body_container"';
if (!empty($response)) echo ' style="margin-top: 4em;"';
echo '>
	<h2>Database Servers';

if ($allowed_to_manage_servers) {
	echo '<a id="plus" href="#" title="Add New">' . $__FM_CONFIG['icons']['add'] . '</a>';
}

echo '</h2>' . "\n";
	
$result = basicGetList('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'servers', 'server_name', 'server_');
$fm_module_servers->rows($result);

printFooter();

?>
