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
$page_name_sub = 'Server Groups';

include(ABSPATH . 'fm-modules/fmSQLPass/classes/class_groups.php');

$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'add';
if ($allowed_to_manage_servers) {
	switch ($action) {
	case 'add':
		if (!empty($_POST)) {
			if (!$fm_sqlpass_groups->add($_POST)) {
				$response = 'This server group could not be added.'. "\n";
				$form_data = $_POST;
			} else header('Location: ' . $GLOBALS['basename']);
		}
		break;
	case 'delete':
		if (isset($_GET['id'])) {
			$delete_status = $fm_sqlpass_groups->delete(sanitize($_GET['id']));
			if ($delete_status !== true) {
				$response = $delete_status;
			} else header('Location: ' . $GLOBALS['basename']);
		}
		break;
	case 'edit':
		if (!empty($_POST)) {
			if (!$fm_sqlpass_groups->update($_POST)) {
				$response = 'This server group could not be updated.'. "\n";
				$form_data = $_POST;
			} else header('Location: ' . $GLOBALS['basename']);
		}
		if (isset($_GET['status'])) {
			if (!updateStatus('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'groups', $_GET['id'], 'group_', $_GET['status'], 'group_id')) {
				$response = 'This backup group could not be '. $_GET['status'] .'.'. "\n";
			} else {
				$tmp_name = getNameFromID($_GET['id'], 'fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'groups', 'group_', 'group_id', 'group_name');
				addLogEntry("Set server group '$tmp_name' status to " . $_GET['status'] . '.');
				header('Location: ' . $GLOBALS['basename']);
			}
		}
	}
}

printHeader();
@printMenu($page_name, $page_name_sub);

echo printPageHeader($response, 'Server Groups', $allowed_to_manage_servers);
	
$result = basicGetList('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'groups', 'group_id', 'group_');
$fm_sqlpass_groups->rows($result);

printFooter();

?>
