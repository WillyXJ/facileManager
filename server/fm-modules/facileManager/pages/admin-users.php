<?php

/**
 * Processes users config page
 *
 * @author		Jon LaBass
 * @version		$Id:$
 * @copyright	2013
 *
 */

$page_name = 'Admin';
$page_name_sub = 'Users';

include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . 'facileManager' . DIRECTORY_SEPARATOR . 'permissions.inc.php');
include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . 'facileManager' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class_users.php');

$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'add';

$form_data = null;

if ($allowed_to_manage_users) {
	switch ($action) {
	case 'add':
		if (!empty($_POST)) {
			$response = $fm_users->add($_POST);
			if ($response !== true) {
				$form_data = $_POST;
			} else header('Location: ' . $GLOBALS['basename']);
		}
		break;
	case 'edit':
		if (!empty($_POST)) {
			$response = $fm_users->update($_POST);
			if ($response !== true) {
				$form_data = $_POST;
			} else header('Location: ' . $GLOBALS['basename']);
		}
		if (isset($_GET['status'])) {
			if ($_GET['id'] == 1) $_GET['id'] = 0;
			$user_info = getUserInfo($_GET['id']);
			if ($user_info) {
				if ($user_info['user_template_only'] == 'no') {
					if (updateStatus('fm_users', $_GET['id'], 'user_', $_GET['status'], 'user_id')) {
						addLogEntry("Set user '" . $user_info['user_login'] . "' status to " . $_GET['status'] . '.', $fm_name);
						header('Location: ' . $GLOBALS['basename']);
					}
				}
			}
			$response = 'This user could not be set to '. $_GET['status'] .'.'. "\n";
		}
	}
}

printHeader();
@printMenu($page_name, $page_name_sub);

echo printPageHeader($response, 'Users', $allowed_to_manage_users);

if ($allowed_to_manage_users) {
	$result = basicGetList('fm_users', 'user_id', 'user_');
} else {
	$result = basicGet('fm_users', $_SESSION['user']['id'], 'user_', 'user_id');
}
$fm_users->rows($result);

printFooter();

?>