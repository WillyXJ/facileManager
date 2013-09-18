<?php

/**
 * Processes views config page
 *
 * @author		Jon LaBass
 * @version		$Id:$
 * @copyright	2013
 *
 */

$page_name = 'Config';
$page_name_sub = 'Views';

include(ABSPATH . 'fm-modules/fmDNS/classes/class_views.php');

$view_option = (isset($_GET['view_option'])) ? ucfirst($_GET['view_option']) : 'Views';
$server_serial_no = (isset($_REQUEST['server_serial_no'])) ? sanitize($_REQUEST['server_serial_no']) : 0;

$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'add';
if ($allowed_to_manage_servers) {
	$server_serial_no_uri = (array_key_exists('server_serial_no', $_REQUEST) && $server_serial_no) ? '?server_serial_no=' . $server_serial_no : null;
	switch ($action) {
	case 'add':
		if (!empty($_POST)) {
			$result = $fm_dns_views->add($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else {
				setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
				header('Location: ' . $GLOBALS['basename'] . $server_serial_no_uri);
			}
		}
		break;
	case 'delete':
		if (isset($_GET['id'])) {
			$delete_status = $fm_dns_views->delete(sanitize($_GET['id']));
			if ($delete_status !== true) {
				$response = $delete_status;
			} else header('Location: ' . $GLOBALS['basename'] . $server_serial_no_uri);
		}
		break;
	case 'edit':
		if (!empty($_POST)) {
			$result = $fm_dns_views->update($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else {
				setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
				header('Location: ' . $GLOBALS['basename'] . $server_serial_no_uri);
			}
		}
		if (isset($_GET['status'])) {
			if (!updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', $_GET['id'], 'view_', $_GET['status'], 'view_id')) {
				$response = 'This item could not be '. $_GET['status'] . '.';
			} else {
				setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
				$tmp_name = getNameFromID($_GET['id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_', 'view_id', 'view_name');
				addLogEntry("Set view '$tmp_name' status to " . $_GET['status'] . '.');
				header('Location: ' . $GLOBALS['basename'] . $server_serial_no_uri);
			}
		}
	}
}

printHeader();
@printMenu($page_name, $page_name_sub);

$avail_servers = buildServerSubMenu($server_serial_no);

if (!empty($response)) echo '<div id="response"><p class="error">' . $response . "</p></div>\n";
echo '<div id="response" style="display: none;"></div>' . "\n";
echo '<div id="body_container"';
if (!empty($response)) echo ' style="margin-top: 4em;"';
echo '>
	<h2>Views';

if ($allowed_to_manage_servers) {
	echo '<a id="plus" href="#" title="Add New">' . $__FM_CONFIG['icons']['add'] . '</a>';
}

echo '</h2>' . "\n$avail_servers\n";
	
$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', 'view_id', 'view_', "AND server_serial_no=$server_serial_no");
$fm_dns_views->rows($result);

printFooter();

?>
