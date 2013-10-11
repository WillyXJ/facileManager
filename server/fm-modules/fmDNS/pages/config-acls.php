<?php

/**
 * Processes ACLs config page
 *
 * @author		Jon LaBass
 * @version		$Id:$
 * @copyright	2013
 *
 */

$page_name = 'Config';
$page_name_sub = 'ACLs';

include(ABSPATH . 'fm-modules/fmDNS/classes/class_acls.php');

$server_serial_no = (isset($_REQUEST['server_serial_no'])) ? sanitize($_REQUEST['server_serial_no']) : 0;
$response = isset($response) ? $response : null;

if ($allowed_to_manage_servers) {
	$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'add';
	$server_serial_no_uri = (array_key_exists('server_serial_no', $_REQUEST) && $server_serial_no) ? '?server_serial_no=' . $server_serial_no : null;
	switch ($action) {
	case 'add':
		if (!empty($_POST)) {
			$result = $fm_dns_acls->add($_POST);
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
			$delete_status = $fm_dns_acls->delete(sanitize($_GET['id']), $server_serial_no);
			if ($delete_status !== true) {
				$response = $delete_status;
			} else {
				header('Location: ' . $GLOBALS['basename'] . $server_serial_no_uri);
			}
		}
		break;
	case 'edit':
		if (!empty($_POST)) {
			$result = $fm_dns_acls->update($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else {
				setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
				header('Location: ' . $GLOBALS['basename'] . $server_serial_no_uri);
			}
		}
		if (isset($_GET['status'])) {
			if (!updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', $_GET['id'], 'acl_', $_GET['status'], 'acl_id')) {
				$response = 'This item could not be ' . $_GET['status'] . '.';
			} else {
				setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
				$tmp_name = getNameFromID($_GET['id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_', 'acl_id', 'acl_name');
				addLogEntry("Set ACL '$tmp_name' status to " . $_GET['status'] . '.');
				header('Location: ' . $GLOBALS['basename'] . $server_serial_no_uri);
			}
		}
	}
}

printHeader();
@printMenu($page_name, $page_name_sub);

$avail_servers = buildServerSubMenu($server_serial_no);

echo printPageHeader($response, 'Access Control Lists', $allowed_to_manage_servers);
echo "\n$avail_servers\n";
	
$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'acls', 'acl_id', 'acl_', "AND server_serial_no=$server_serial_no");
$fm_dns_acls->rows($result);

printFooter();

?>
