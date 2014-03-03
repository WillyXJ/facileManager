<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2013 The facileManager Team                               |
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
 | fmDNS: Easily manage one or more ISC BIND servers                       |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdns/                             |
 +-------------------------------------------------------------------------+
*/

/**
 * Checks the app functionality
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmDNS
 */
function moduleFunctionalCheck() {
	global $fmdb, $__FM_CONFIG;
	$html_checks = null;
	
	/** Count active name servers */
	$checks[] = (basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_', 'active')) ? null : '<p>You currently have no active name servers defined.  <a href="' . $__FM_CONFIG['menu']['Config']['Servers'] . '">Click here</a> to define one or more to manage.</p>';
	
	/** Count global options */
	$checks[] = (basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_id', 'cfg_')) ? null : '<p>You currently have no global options defined for named.conf.  <a href="' . $__FM_CONFIG['menu']['Config']['Options'] . '">Click here</a> to define one or more.</p>';
	
	/** Count zones */
	$checks[] = (basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_id', 'domain_')) ? null : '<p>You currently have no zones defined.  <a href="' . $__FM_CONFIG['menu']['Zones']['URL'] . '">Click here</a> to define one or more.</p>';
	
	foreach ($checks as $val) {
		$html_checks .= $val;
	}
	
	return $html_checks;
}

/**
 * Builds the dashboard for display
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmDNS
 */
function buildModuleDashboard() {
	global $fmdb, $__FM_CONFIG, $allowed_to_manage_zones;

	$dashboard = $errors = null;
	
	/** Name server stats */
	basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_');
	$server_count = $fmdb->num_rows;
	$server_results = $fmdb->last_result;
	for ($i=0; $i<$server_count; $i++) {
		if ($server_results[$i]->server_installed != 'yes') {
			$errors .= '<b>' . $server_results[$i]->server_name . '</b> client is not installed.' . "\n";
		}
		if ($server_results[$i]->server_build_config != 'no') {
			$errors .= '<a href="' . $__FM_CONFIG['menu']['Config']['Servers'] . '"><b>' . $server_results[$i]->server_name . '</b></a> needs a new configuration built.' . "\n";
		}
	}
	$server_error_display = ($server_errors) ? '<li>' . nl2br($server_errors) . '</li>' : null;

	/** Zone stats */
	basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_id', 'domain_');
	$domain_count = $fmdb->num_rows;
	$domain_results = $fmdb->last_result;
	for ($i=0; $i<$domain_count; $i++) {
		if (!getSOACount($domain_results[$i]->domain_id) && !$domain_results[$i]->domain_clone_domain_id && 
			$domain_results[$i]->domain_type == 'master') {
			$errors .= '<a href="zone-records.php?map=' . $domain_results[$i]->domain_mapping . '&domain_id=' . $domain_results[$i]->domain_id;
			if ($allowed_to_manage_zones) $errors .= '&record_type=SOA';
			$errors .= '">' . $domain_results[$i]->domain_name . '</a> does not have a SOA defined.' . "\n";
		} elseif (!getNSCount($domain_results[$i]->domain_id) && !$domain_results[$i]->domain_clone_domain_id && 
			$domain_results[$i]->domain_type == 'master') {
			$errors .= '<a href="zone-records.php?map=' . $domain_results[$i]->domain_mapping . '&domain_id=' . $domain_results[$i]->domain_id;
			if ($allowed_to_manage_zones) $errors .= '&record_type=NS';
			$errors .= '">' . $domain_results[$i]->domain_name . '</a> does not have any NS records defined.' . "\n";
		} elseif ($domain_results[$i]->domain_reload != 'no') {
			$errors .= '<a href="' . $__FM_CONFIG['menu']['Zones'][ucfirst($domain_results[$i]->domain_mapping)] . '"><b>' . $domain_results[$i]->domain_name . '</b></a> needs to be reloaded.' . "\n";
		}
	}
	if ($errors) {
		$error_display = '<li>' . str_replace("\n", "</li>\n<li>", $errors);
		$error_display = rtrim($error_display, '<li>');
	} else $error_display = null;

	/** Record stats */
	basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'records', 'record_id', 'record_');
	$record_count = $fmdb->num_rows;

	$dashboard = <<<DASH
	<div id="shadow_box" class="leftbox">
		<div id="shadow_container">
		<h3>Summary</h3>
		<li>You have <b>$server_count</b> name servers configured.</li>
		<li>You have <b>$domain_count</b> zones defined.</li>
		<li>You have <b>$record_count</b> records.</li>
		</div>
	</div>
DASH;

	if ($error_display) {
		$dashboard .= <<<DASH
	<div id="shadow_box" class="rightbox">
		<div id="shadow_container">
		<h3>Needs Attention</h3>
		$error_display
		</div>
	</div>
DASH;
	}

	return $dashboard;
}

/**
 * Builds the additional module menu for display
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmDNS
 */
function buildModuleToolbar() {
	global $__FM_CONFIG;
	
	if (isset($_GET['domain_id'])) {
		$domain = getNameFromID($_GET['domain_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name');
		$domain_menu = <<<HTML
		<div id="topheadpart">
			<span class="single_line">Domain:&nbsp;&nbsp; $domain</span>
		</div>
HTML;
	} else $domain_menu = null;
	
	return $domain_menu;
}

/**
 * Builds the help for display
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmDNS
 */
function buildModuleHelpFile() {
	global $__FM_CONFIG;
	
	$body = <<<HTML
<h3>{$_SESSION['module']}</h3>
<ul>
	<li>
		<a class="list_title" onclick="javascript:toggleLayer('fmdns_config_zones', 'block');">Configure Zones</a>
		<div id="fmdns_config_zones">
			<p>Zones (aka domains) can be managed from the <a href="{$__FM_CONFIG['module']['menu']['Zones']['URL']}">Zones</a> menu item. From 
			there you can add ({$__FM_CONFIG['icons']['add']}), edit ({$__FM_CONFIG['icons']['edit']}), delete ({$__FM_CONFIG['icons']['delete']}), 
			and reload ({$__FM_CONFIG['icons']['reload']}) zones depending on your user permissions.</p>
			<p><i>The 'Zone Management' or 'Super Admin' permission is required to add, edit, and delete zones.</i></p>
			<p><i>The 'Reload Zone' or 'Super Admin' permission is required for reloading zones.</i></p>
			<p>Reverse zones can be entered by either their subnet value (192.168.1) or by their arpa value (1.168.192.in-addr.arpa).</p>
			<p>Zones that are missing SOA records will be highlighted with a red background and will not be built or reloaded until the SOA exists.</p>
			<br />
		</div>
	</li>
	<li>
		<a class="list_title" onclick="javascript:toggleLayer('fmdns_manage_records', 'block');">Manage Zone Records</a>
		<div id="fmdns_manage_records">
			<p>Records are managed from the <a href="{$__FM_CONFIG['module']['menu']['Zones']['URL']}">Zones</a> menu item. From 
			there you can select the zone you want manage records for.  Select from the upper-right the type of record(s) you want to 
			manage and then you can add, modify, and delete records depending on your user permissions.</p>
			<p>IPv4 A type records and the IPv6 AAAA records are both managed under the same page. Select A from the upper-right and add your 
			IPv4 and IPv6 records and {$_SESSION['module']} will auto-detect their type.</p>
			<p>When adding CNAME, MX, SRV, SOA, or NS records, you have the option append the domain to the record. This means {$_SESSION['module']} 
			will automatically add the domain to the record so you don't have to give the fully qualified domain name in the record value.</p>
			<p><i>The 'Record Management' or 'Super Admin' permission is required to add, edit, and delete records.</i></p>
			<p>When adding or updating a SOA record for a zone, the domain can be appended to the Master Server and Email Address if selected. This
			means you could simply enter 'ns1' and 'username' for the Master Server and Email Address respectively. If you prefer to enter the entire
			entry, make sure you append a period (.) at the end of each and select 'no' for Append Domain.</p>
			<p>Adding A records provides the option of automatically creating the associated PTR record. However, the reverse zone must first
			exist in order for PTR records to automatically be created.</p>
			<p>You can also import BIND-compatible zone files instead of adding records individually. Go to Admin &rarr; 
			<a href="{$__FM_CONFIG['menu']['Admin']['Tools']}">Tools</a> and use the Import Zone Files utility. After selecting the file and zone 
			to import to, you have one final chance to review what gets imported before the records are actually imported.</p>
			<br />
		</div>
	</li>
	<li>
		<a class="list_title" onclick="javascript:toggleLayer('fmdns_config_servers', 'block');">Configure Servers</a>
		<div id="fmdns_config_servers">
			<p>All aspects of server configuration takes place in the <a href="{$__FM_CONFIG['module']['menu']['Config']['URL']}">Config</a> menu 
			item. From there you can add ({$__FM_CONFIG['icons']['add']}), edit ({$__FM_CONFIG['icons']['edit']}), delete ({$__FM_CONFIG['icons']['delete']}) 
			servers and options depending on your user permissions.</p>
			
			<p><b>Servers</b><br />
			DNS servers can be defined at Config &rarr; <a href="{$__FM_CONFIG['menu']['Config']['Servers']}">Servers</a>. In the add/edit server 
			window, select and define the server hostname, key (if applicable), system account the daemon runs as, update method, configuration file, 
			server root, and directory to keep the zone files in.</p>
			<p>The server can be updated via the following methods:</p>
			<ul>
				<li><i>http(s) -</i> $fm_name will initiate a http(s) connection to the DNS server which updates the configs.</li>
				<li><i>cron -</i> The DNS servers will initiate a http connection to $fm_name to update the configs.</li>
				<li><i>ssh -</i> $fm_name will SSH to the DNS server which updates the configs.</li>
			</ul>
			<p>In order for the server to be enabled, the client app needs to be installed on the DNS server.</p>
			<p><i>The 'Server Management' or 'Super Admin' permission is required to add, edit, and delete servers.</i></p>
			<p>Once a server is added or modified, the configuration files for the server will need to be built before zone reloads will be available. 
			Before building the configuration ({$__FM_CONFIG['icons']['build']}) you can preview ({$__FM_CONFIG['icons']['preview']}) the configs to 
			ensure they are how you desire them. Both the preview and the build will check the configuration files with named-checkconf and named-checkzone
			if enabled in the <a href="{$__FM_CONFIG['menu']['Settings']['URL']}">Settings</a>.</p>
			<p><i>The 'Build Server Configs' or 'Super Admin' permission is required to build the DNS server configurations.</i></p>
			<br />
			
			<p><b>Views</b><br />
			If you want to use views, they need to be defined at Config &rarr; <a href="{$__FM_CONFIG['menu']['Config']['Views']}">Views</a>. View names 
			can be defined globally for all DNS servers or on a per-server basis. This is controlled by the servers drop-down menu in the upper right.</p>
			<p>Once you define a view, you can select it in the list to manage the options for that view - either globally or server-based. See the section 
			on 'Options' for further details.</p>
			<p><i>The 'Server Management' or 'Super Admin' permission is required to manage views.</i></p>
			<br />
			
			<p><b>ACLs</b><br />
			Access Control Lists are defined at Config &rarr; <a href="{$__FM_CONFIG['menu']['Config']['ACLs']}">ACLs</a> and can be defined globally 
			for all DNS servers or on a per-server basis. This is controlled by the servers drop-down menu in the upper right.</p>
			<p>When defining an ACL, specify the name and the address list. You can use the pre-defined addresses or specify your own delimited by a space,
			semi-colon, or newline.</p>
			<p><i>The 'Server Management' or 'Super Admin' permission is required to manage ACLs.</i></p>
			<br />
			
			<p><b>Keys</b><br />
			Currently, {$_SESSION['module']} does not generate server keys (TSIG), but once you create them on your server, you can define them in the UI 
			at Config &rarr; <a href="{$__FM_CONFIG['menu']['Config']['Keys']}">Keys</a>.</p>
			<p><i>The 'Server Management' or 'Super Admin' permission is required to manage keys.</i></p>
			<br />
			
			<p><b>Options</b><br />
			Options can be defined globally or server-based which is controlled by the servers drop-down menu in the upper right. Currently, the options 
			configuration is rudimentary and can be defined at Config &rarr; <a href="{$__FM_CONFIG['menu']['Config']['Options']}">Options</a>.</p>
			<p>Server-level options always supercede global options (including global view options).</p>
			<p><i>The 'Server Management' or 'Super Admin' permission is required to manage server options.</i></p>
			<br />
			
			<p><b>Logging</b><br />
			Logging channels and categories can be defined globally or server-based which is controlled by the servers drop-down menu in the upper right. 
			To manage the logging configuration, go to Config &rarr; <a href="{$__FM_CONFIG['menu']['Config']['Logging']}">Logging</a>.</p>
			<p>Server-level channels and categories always supercede global ones.</p>
			<p><i>The 'Server Management' or 'Super Admin' permission is required to manage server logging.</i></p>
			<br />
		</div>
	</li>
	<li>
		<a class="list_title" onclick="javascript:toggleLayer('fm_module_settings', 'block');">Module Settings</a>
		<div id="fm_module_settings">
			<p>Settings for {$_SESSION['module']} can be updated from the <a href="{$__FM_CONFIG['module']['menu']['Settings']['URL']}">Settings</a> 
			menu item.</p>
			<br />
		</div>
	</li>
	
HTML;
	
	return $body;
}


/**
 * Builds the server listing in a dropdown menu
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmDNS
 */
function buildServerSubMenu($server_serial_no = 0, $class = null) {
	global $fmdb, $__FM_CONFIG;
	
	$server_array[0][] = 'All Servers';
	$server_array[0][] = '0';
	$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_name', 'server_');
	if ($fmdb->num_rows) {
		$results = $fmdb->last_result;
		for ($i=0; $i<$fmdb->num_rows; $i++) {
			$server_array[$i+1][] = $results[$i]->server_name;
			$server_array[$i+1][] = $results[$i]->server_serial_no;
		}
	}
	$server_list = buildSelect('server_serial_no', 'server_serial_no', $server_array, $server_serial_no, 1, null, false, 'this.form.submit()');
	
	$hidden_inputs = null;
	foreach ($GLOBALS['URI'] as $param => $value) {
		if ($param == 'server_serial_no') continue;
		$hidden_inputs .= '<input type="hidden" name="' . $param . '" value="' . $value . '" />' . "\n";
	}
	
	$class = $class ? 'class="' . $class . '"' : null;

	$return = <<<HTML
	<div id="configtypesmenu" $class>
		<form action="{$GLOBALS['basename']}" method="GET">
		$hidden_inputs
		$server_list
		</form>
	</div>
HTML;

	return $return;
}


function moduleAddServer($action) {
	include(ABSPATH . 'fm-modules/' . $_POST['module_name'] . '/classes/class_servers.php');
	
	return $fm_module_servers->$action($_POST);
}


function moduleCompleteClientInstallation() {
	setBuildUpdateConfigFlag($_POST['SERIALNO'], 'yes', 'build');
}


function reloadZoneSQL($domain_id, $reload_zone) {
	global $fmdb, $__FM_CONFIG;
	
	$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` SET `domain_reload`='$reload_zone' WHERE `domain_id`='$domain_id'";
	$fmdb->query($query);
}

function reloadZone($domain_id) {
	global $fmdb, $__FM_CONFIG;
	
	basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $domain_id, 'domain_', 'domain_id');
	if ($fmdb->num_rows) {
		$result = $fmdb->last_result;
		if ($result[0]->domain_reload == 'yes') return true;
	}
	return false;
}

function getSOACount($domain_id) {
	global $fmdb, $__FM_CONFIG;
	
	$query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}soa` WHERE `domain_id`='$domain_id' AND `soa_status`!='deleted'";
	$fmdb->get_results($query);
	return $fmdb->num_rows;
}

function getNSCount($domain_id) {
	global $fmdb, $__FM_CONFIG;
	
	$query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` WHERE `domain_id`='$domain_id' AND `record_type`='NS' AND `record_status`='active'";
	$fmdb->get_results($query);
	return $fmdb->num_rows;
}


/**
 * Cleans addresses for future parsing
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmDNS
 */
function verifyAndCleanAddresses($data, $allow_alpha = false) {
	$alpha = (!$allow_alpha) ? 'a-z' : null;
	
	/** Remove extra spaces */
	$data = preg_replace('/\s\s+/', ' ', $data);
	
	/** Check for bad chars */
	if (preg_match("([-_\!@#\$&\*\+\=\|:,'\"%^\(\)" . $alpha . "])", $data)) return false;
	
	/** Swap delimiters for ; */
	$data = str_replace(array("\n", ';', ' '), ';', $data);
	$data = str_replace(';;', ';', $data);
	$data = rtrim($data, ';');
	if (!empty($data)) $data .= ';';
	$data = str_replace(';', '; ', $data);
	
	/** Tried to do some IP validation, but it won't work.
	 *  People can enter 10. which is valid for named.
	$addresses = explode(';', $data);
	foreach ($addresses as $ip_address) {
		echo $ip_address . '<br />';
		if (ip2long($ip_address) === false || ip2long($ip_address) ==  -1) return false;
	}
	*/
	
	return $data;
}


function isDNSNameAcceptable($string) {
	if (preg_match('/[^a-z_\-0-9]/i', $string)) return false;
	
	return true;
}


/**
 * Posts the data to the DNS server
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmDNS
 */
function postReloadZones($server, $data, $proto = 'http') {
	$url = $proto . '://' . $server . '/facileManager/index.php';
	
	return getPostData($url, $data);
}



function buildFullIPAddress($partial_ip, $domain) {
	$domain_pieces = array_reverse(explode('.', $domain));
	$domain_parts = count($domain_pieces);
	
	$subnet_ips = null;
	for ($i=2; $i<$domain_parts; $i++) {
		$subnet_ips .= $domain_pieces[$i] . '.';
	}
	$record_octets = array_reverse(explode('.', str_replace($subnet_ips, '', $partial_ip)));
	$temp_record_value = null;
	for ($j=0; $j<count($record_octets); $j++) {
		$temp_record_value .= $record_octets[$j] . '.';
	}
	$subnet_ips .= rtrim($temp_record_value, '.');
	
	/** IPv6? */
	if (substr_count($subnet_ips, '.') > 3) {
		$pieces = explode('.', $subnet_ips);
		$pack = pack('H*', implode('', $pieces));
		$subnet_ips = inet_ntop($pack);
	}
	
	return $subnet_ips;
}


/**
 * Returns if the OS is debian-based or not
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param string $os OS to check
 * @return boolean
 */
function isDebianSystem($os) {
	return in_array(strtolower($os), array('debian', 'ubuntu', 'fubuntu'));
}


/**
 * Returns if a zone reload is allowed or not
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param id $domain_id Domain ID to check
 * @return boolean
 */
function reloadAllowed($domain_id = null) {
	global $fmdb, $__FM_CONFIG;
	
	basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'active', 'server_', 'server_status');
	if ($fmdb->num_rows) {
		if ($domain_id) {
			$query = 'SELECT * FROM `fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'track_builds` WHERE domain_id=' . $domain_id;
			$result = $fmdb->get_results($query);
			$reload_allowed = ($fmdb->num_rows) ? true : false;
		} else $reload_allowed = true;
	} else $reload_allowed = false;
	
	return $reload_allowed;
}
	

/**
 * Gets the menu badge counts
 *
 * @since 1.1
 * @package facileManager
 * @subpackage fmDNS
 *
 * @return boolean
 */
function getModuleBadgeCounts() {
	global $fmdb, $__FM_CONFIG;
	
	/** Zones */
	basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_id', 'domain_');
	$domain_count = $fmdb->num_rows;
	$domain_results = $fmdb->last_result;
	for ($i=0; $i<$domain_count; $i++) {
		if (!getSOACount($domain_results[$i]->domain_id) && !$domain_results[$i]->domain_clone_domain_id && 
			$domain_results[$i]->domain_type == 'master') {
			$badge_counts['Zones'][ucfirst($domain_results[$i]->domain_mapping)]++;
		} elseif (!getNSCount($domain_results[$i]->domain_id) && !$domain_results[$i]->domain_clone_domain_id && 
			$domain_results[$i]->domain_type == 'master') {
			$badge_counts['Zones'][ucfirst($domain_results[$i]->domain_mapping)]++;
		} elseif ($domain_results[$i]->domain_reload != 'no') {
			$badge_counts['Zones'][ucfirst($domain_results[$i]->domain_mapping)]++;
		}
	}
	
	/** Servers */
	basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_', "AND `server_status`='active' AND (`server_installed`!='yes' OR `server_build_config`='yes')");
	$domain_count = $fmdb->num_rows;
	$domain_results = $fmdb->last_result;
	for ($i=0; $i<$domain_count; $i++) {
		$badge_counts['Config']['Servers']++;
	}
	
	return $badge_counts;
}

?>