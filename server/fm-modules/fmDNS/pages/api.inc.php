<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) The facileManager Team                                    |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | facileManager: Easy System Administration                               |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdns/                             |
 +-------------------------------------------------------------------------+
 | Processes API requests                                                  |
 +-------------------------------------------------------------------------+
*/

if (!isset($_POST['api'])) {
    $data = __('API call received incomplete data.') . "\n";
    return;
}
extract($_POST['api']);

if (isset($_POST['domain_id'])) {
    $domain_id = intval($_POST['domain_id']);
}

$exclude = array('action', 'domain_id', 'autoupdate');

foreach ($_POST['api'] as $key => $val) {
    if (!in_array($key, $exclude)) $record_data[$key] = $val;
}
/** Remove double quotes */
if (isset($record_data['record_value'])) $record_data['record_value'] = str_replace('"', '', $record_data['record_value']);

/** Should the user be here? */
if (!currentUserCan('manage_records', $_SESSION['module'])) {
    $data = throwAPIError(1000);
    return;
}
if (!zoneAccessIsAllowed(array($domain_id))) {
    $data = throwAPIError(1001);
    return;
}
if (in_array($record_data['record_type'], $__FM_CONFIG['records']['require_zone_rights']) && !currentUserCan('manage_zones', $_SESSION['module'])) {
    $data = throwAPIError(1002);
    return;
}

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_records.php');

$error = true;
$code = null;
switch ($_POST['api']['action']) {
    case 'add':
        if (!$_POST['dryrun']) {
            $retval = $fm_dns_records->add($domain_id, $record_data['record_type'], $record_data);
            if (is_bool($retval)) {
                if ($retval === true) {
                    $data = true;
                    $error = false;
                }
            } else {
                /** Record already exists */
                $code = 1004;
            }
        } else {
            $code = 3000;
        }
        break;
    case 'update':
        /** Get record_id from array values */
        basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $record_data['record_name'], 'record_', 'record_name', 'AND record_value="' . $record_data['record_value'] . '" AND record_type="' . $record_data['record_type'] . '"');
        if ($fmdb->num_rows == 1) {
            $record_type = $record_data['record_type'];
            if (isset($record_data['record_newname'])) {
                $record_data['record_name'] = $record_data['record_newname'];
                unset($record_data['record_newname']);
            }
            if (isset($record_data['record_newvalue'])) {
                $record_data['record_value'] = $record_data['record_newvalue'];
                unset($record_data['record_newvalue']);
            }
            if (!$_POST['dryrun']) {
                if (!is_bool($fm_dns_records->update($domain_id, $fmdb->last_result[0]->record_id, $record_type, $record_data))) {
                    $data = true;
                    $error = false;
                }
            } else {
                $code = 3000;
            }
        } else {
            $code = 1005;
        }

        break;
    case 'delete':
        /** Get record_id from array values */
        $record_value_sql = (isset($record_data['record_value'])) ? 'AND record_value="' . $record_data['record_value'] . '"' : null;
        basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $record_data['record_name'], 'record_', 'record_name', $record_value_sql . ' AND record_type="' . $record_data['record_type'] . '"');
        if ($fmdb->num_rows) {
            $num_rows = $fmdb->num_rows;
            $last_result = $fmdb->last_result;
            $record_type = $record_data['record_type'];
            unset($record_data);
            $record_data['record_status'] = 'deleted';
            if (!$_POST['dryrun']) {
                for ($i=0; $i<$num_rows; $i++) {
                    if (!is_bool($fm_dns_records->update($domain_id, $last_result[$i]->record_id, $record_type, $record_data))) {
                        $data = true;
                        $error = false;
                    }
                }
            } else {
                $code = 3000;
            }
        } else {
            $code = 1005;
        }

        break;
}

if ($error) {
    $data = throwAPIError($code);
    // $data .= $fmdb->last_error;
} elseif ($data === true) {
    if ($_POST['api']['autoupdate'] == "yes") {
        reloadZoneSQL($domain_id, 'no', 'single');
        $fm_dns_zones->buildZoneConfig($domain_id);
    }
    $data = _('Success') . "\n";
}

// /** Are we auto-creating a PTR record? */
// autoManagePTR($domain_id, $record_type, $record_data);
