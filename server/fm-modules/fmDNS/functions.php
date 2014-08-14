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
	$checks[] = (basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_', 'active')) ? null : '<p>You currently have no active name servers defined.  <a href="' . getMenuURL('Servers') . '">Click here</a> to define one or more to manage.</p>';
	
	/** Count global options */
	$checks[] = (basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_id', 'cfg_')) ? null : '<p>You currently have no global options defined for named.conf.  <a href="' . getMenuURL('Options') . '">Click here</a> to define one or more.</p>';
	
	/** Count zones */
	$checks[] = (basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_id', 'domain_')) ? null : '<p>You currently have no zones defined.  <a href="' . getMenuURL('Zones') . '">Click here</a> to define one or more.</p>';
	
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
	global $fmdb, $__FM_CONFIG;

	$dashboard = $errors = null;
	
	/** Name server stats */
	basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_');
	$server_count = $fmdb->num_rows;
	$server_results = $fmdb->last_result;
	for ($i=0; $i<$server_count; $i++) {
		if ($server_results[$i]->server_installed != 'yes') {
			$errors .= '<b>' . $server_results[$i]->server_name . '</b> client is not installed.' . "\n";
		} elseif (isset($server_results[$i]->server_client_version) && $server_results[$i]->server_client_version != getOption('client_version', 0, $_SESSION['module'])) {
			$errors .= '<a href="' . getMenuURL('Servers') . '"><b>' . $server_results[$i]->server_name . '</b></a> client needs to be upgraded.' . "\n";
		} elseif ($server_results[$i]->server_build_config != 'no' && $server_results[$i]->server_status == 'active') {
			$errors .= '<a href="' . getMenuURL('Servers') . '"><b>' . $server_results[$i]->server_name . '</b></a> needs a new configuration built.' . "\n";
		}
	}
	/** Zone stats */
	basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_id', 'domain_');
	$domain_count = $fmdb->num_rows;
	$domain_results = $fmdb->last_result;
	for ($i=0; $i<$domain_count; $i++) {
		if (!getSOACount($domain_results[$i]->domain_id) && !$domain_results[$i]->domain_clone_domain_id && 
			$domain_results[$i]->domain_type == 'master') {
			$errors .= '<a href="zone-records.php?map=' . $domain_results[$i]->domain_mapping . '&domain_id=' . $domain_results[$i]->domain_id;
			if (currentUserCan('manage_zones', $_SESSION['module'])) $errors .= '&record_type=SOA';
			$errors .= '">' . displayFriendlyDomainName($domain_results[$i]->domain_name) . '</a> does not have a SOA defined.' . "\n";
		} elseif (!getNSCount($domain_results[$i]->domain_id) && !$domain_results[$i]->domain_clone_domain_id && 
			$domain_results[$i]->domain_type == 'master') {
			$errors .= '<a href="zone-records.php?map=' . $domain_results[$i]->domain_mapping . '&domain_id=' . $domain_results[$i]->domain_id;
			if (currentUserCan('manage_zones', $_SESSION['module'])) $errors .= '&record_type=NS';
			$errors .= '">' . displayFriendlyDomainName($domain_results[$i]->domain_name) . '</a> does not have any NS records defined.' . "\n";
		} elseif ($domain_results[$i]->domain_reload != 'no') {
			$errors .= '<a href="' . getMenuURL(ucfirst($domain_results[$i]->domain_mapping)) . '"><b>' . displayFriendlyDomainName($domain_results[$i]->domain_name) . '</b></a> needs to be reloaded.' . "\n";
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
	global $__FM_CONFIG, $fmdb;
	
	if (isset($_REQUEST['domain_id'])) {
		$domain = displayFriendlyDomainName(getNameFromID($_REQUEST['domain_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name'));
		$domain_menu = <<<HTML
		<div id="topheadpart">
			<span class="single_line">Domain:&nbsp;&nbsp; $domain</span>
		</div>
HTML;
		if ($parent_domain_id = getNameFromID($_GET['domain_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_clone_domain_id')) {
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $parent_domain_id, 'domain_', 'domain_id');
			extract(get_object_vars($fmdb->last_result[0]));
			$domain_name = displayFriendlyDomainName($domain_name);
			$record_type_uri = array_key_exists('record_type', $_GET) ? '&record_type=' . $_GET['record_type'] : null;
			$domain_menu .= <<<HTML
		<div id="topheadpart">
			<span class="single_line">Clone of:&nbsp;&nbsp; <a href="zone-records.php?map=$domain_mapping&domain_id=$parent_domain_id$record_type_uri" title="Edit parent zone records">$domain_name</a></span>
		</div>
HTML;
		}
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
	global $menu, $__FM_CONFIG;
	
	$body = <<<HTML
<h3>{$_SESSION['module']}</h3>
<ul>
	<li>
		<a class="list_title">Configure Zones</a>
		<div>
			<p>Zones (aka domains) can be managed from the <a href="__menu{Zones}">Zones</a> menu item. From 
			there you can add ({$__FM_CONFIG['icons']['add']}), edit ({$__FM_CONFIG['icons']['edit']}), delete 
			({$__FM_CONFIG['icons']['delete']}), and reload ({$__FM_CONFIG['icons']['reload']}) zones depending on your user permissions.</p>
			<p>You can define a zone as a clone of another previously defined master zone.  The cloned zone will contain all of the same records
			present in the parent zone.  This is useful if you have multiple zones with identical records as you won't have to repeat the record
			definitions.  You can also skip records and define new ones inside clone zones for those that are slightly different than the parent.</p>
			<p><i>The 'Zone Management' or 'Super Admin' permission is required to add, edit, and delete zones.</i></p>
			<p><i>The 'Reload Zone' or 'Super Admin' permission is required for reloading zones.</i></p>
			<p>Reverse zones can be entered by either their subnet value (192.168.1) or by their arpa value (1.168.192.in-addr.arpa).</p>
			<p>Zones that are missing SOA and NS records will be highlighted with a red background and will not be built or reloaded until the 
			records exists.</p>
			<br />
		</div>
	</li>
	<li>
		<a class="list_title">Manage Zone Records</a>
		<div>
			<p>Records are managed from the <a href="__menu{Zones}">Zones</a> menu item. From 
			there you can select the zone you want manage records for.  Select from the upper-right the type of record(s) you want to 
			manage and then you can add, modify, and delete records depending on your user permissions.</p>
			<p>You can add IPv4 A type and IPv6 AAAA type records under the same page. Select A or AAAA from the upper-right and add your 
			IPv4 and IPv6 records and {$_SESSION['module']} will auto-detect their type.</p>
			<p>When adding certain records (such as CNAME, MX, SRV, SOA, NS, etc.), you have the option append the domain to the record. This 
			means {$_SESSION['module']} will automatically add the domain to the record so you don't have to give the fully qualified domain name 
			in the record value.</p>
			<p><i>The 'Record Management' or 'Super Admin' permission is required to add, edit, and delete records.</i></p>
			<p>When adding or updating a SOA record for a zone, the domain can be appended to the Master Server and Email Address if selected. This
			means you could simply enter 'ns1' and 'username' for the Master Server and Email Address respectively. If you prefer to enter the entire
			entry, make sure you select 'no' for Append Domain.</p>
			<p>SOA records can also be saved as a template and applied to an unlimited number of zones. This can speed up your zone additions and
			management. You can create a SOA template when managing zone records or you can completely manage them from 
			<a href="__menu{SOA}">Templates</a>. SOA templates can only be deleted when there are no zones associated with them.</p>
			<p><i>The 'Zone Management' or 'Super Admin' permission is required to add, edit, and delete SOA templates.</i></p>
			<p>Adding A and AAAA records provides the option of automatically creating the associated PTR record. However, the reverse zone must first
			exist in order for PTR records to automatically be created. You can enable the automatic reverse zone creation in the 
			<a href="__menu{{$_SESSION['module']} Settings}">Settings</a>. In this case, the reverse zone will inherit the same SOA as the 
			forward zone.</p>
			<p>When viewing the records of a cloned zone, the parent records will not be editable, but you can choose to skip them or add new records
			that impacts the cloned zone only.</p>
			<p>You can also import BIND-compatible zone files instead of adding records individually. Go to Admin &rarr; 
			<a href="__menu{Tools}">Tools</a> and use the Import Zone Files utility. After selecting the file and zone 
			to import to, you have one final chance to review what gets imported before the records are actually imported.</p>
			<br />
		</div>
	</li>
	<li>
		<a class="list_title">Configure Servers</a>
		<div>
			<p>All aspects of server configuration takes place in the Config menu 
			item. From there you can add ({$__FM_CONFIG['icons']['add']}), edit ({$__FM_CONFIG['icons']['edit']}), 
			delete ({$__FM_CONFIG['icons']['delete']}) servers and options depending on your user permissions.</p>
			
			<p><b>Servers</b><br />
			DNS servers can be defined at Config &rarr; <a href="__menu{Servers}">Servers</a>. In the add/edit server 
			window, select and define the server hostname, key (if applicable), system account the daemon runs as, update method, configuration file, 
			server root, chroot directory (if applicable), and directory to keep the zone files in.</p>
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
			if enabled in the <a href="__menu{{$_SESSION['module']} Settings}">Settings</a>.</p>
			<p><i>The 'Build Server Configs' or 'Super Admin' permission is required to build the DNS server configurations.</i></p>
			<br />
			
			<p><b>Views</b><br />
			If you want to use views, they need to be defined at Config &rarr; <a href="__menu{Views}">Views</a>. View names 
			can be defined globally for all DNS servers or on a per-server basis. This is controlled by the servers drop-down menu in the upper right.</p>
			<p>Once you define a view, you can select it in the list to manage the options for that view - either globally or server-based. See the section 
			on 'Options' for further details.</p>
			<p><i>The 'Server Management' or 'Super Admin' permission is required to manage views.</i></p>
			<br />
			
			<p><b>ACLs</b><br />
			Access Control Lists are defined at Config &rarr; <a href="__menu{ACLs}">ACLs</a> and can be defined globally 
			for all DNS servers or on a per-server basis. This is controlled by the servers drop-down menu in the upper right.</p>
			<p>When defining an ACL, specify the name and the address list. You can use the pre-defined addresses or specify your own delimited by a space,
			semi-colon, or newline.</p>
			<p><i>The 'Server Management' or 'Super Admin' permission is required to manage ACLs.</i></p>
			<br />
			
			<p><b>Keys</b><br />
			Currently, {$_SESSION['module']} does not generate server keys (TSIG), but once you create them on your server, you can define them in the UI 
			at Config &rarr; <a href="__menu{Keys}">Keys</a>.</p>
			<p><i>The 'Server Management' or 'Super Admin' permission is required to manage keys.</i></p>
			<br />
			
			<p><b>Options</b><br />
			Options can be defined globally or server-based which is controlled by the servers drop-down menu in the upper right. Currently, the options 
			configuration is rudimentary and can be defined at Config &rarr; <a href="__menu{Options}">Options</a>.</p>
			<p>Server-level options always supercede global options (including global view options).</p>
			<p><i>The 'Server Management' or 'Super Admin' permission is required to manage server options.</i></p>
			<br />
			
			<p><b>Logging</b><br />
			Logging channels and categories can be defined globally or server-based which is controlled by the servers drop-down menu in the upper right. 
			To manage the logging configuration, go to Config &rarr; <a href="__menu{Logging}">Logging</a>.</p>
			<p>Server-level channels and categories always supercede global ones.</p>
			<p><i>The 'Server Management' or 'Super Admin' permission is required to manage server logging.</i></p>
			<br />
			
			<p><b>Controls</b><br />
			Controls can be defined globally or server-based which is controlled by the servers drop-down menu in the upper right. 
			To manage the controls configuration, go to Config &rarr; <a href="__menu{Controls}">Controls</a>.</p>
			<p>Server-level controls always supercede global ones.</p>
			<p><i>The 'Server Management' or 'Super Admin' permission is required to manage server controls.</i></p>
			<br />
		</div>
	</li>
	<li>
		<a class="list_title">Module Settings</a>
		<div>
			<p>Settings for {$_SESSION['module']} can be updated from the <a href="__menu{{$_SESSION['module']} Settings}">Settings</a> menu item.</p>
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
	/** Install default config option overrides based on OS distro */
	setDefaultOverrideOptions();
	
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
	
	if (version_compare(getOption('version', 0, 'fmDNS'), '1.3-beta1', '<')) {
		$query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}soa` WHERE (`domain_id`='$domain_id' OR
			`domain_id` = (SELECT `domain_clone_domain_id` FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE
				`domain_id`='$domain_id')
		) AND `soa_status`!='deleted'";
	} else {
		$query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}soa` WHERE `soa_id`= (SELECT DISTINCT(`soa_id`) FROM 
			`fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE `soa_id`!=0 AND (`domain_id`='$domain_id' OR
				`domain_id` = (SELECT `domain_clone_domain_id` FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE
					`domain_id`='$domain_id')
			)) AND `soa_status`!='deleted'";
	}
	$fmdb->get_results($query);
	return $fmdb->num_rows;
}

function getNSCount($domain_id) {
	global $fmdb, $__FM_CONFIG;
	
	$query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` WHERE (`domain_id`='$domain_id' OR
			`domain_id` = (SELECT `domain_clone_domain_id` FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE
				`domain_id`='$domain_id')
		) AND `record_type`='NS' AND `record_status`='active'";
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
	if (preg_match("([-\!@#\$&\*\+\=\|:'\"%^\(\)" . $alpha . "])", $data)) return false;
	
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
	/** Remove range */
	$domain = preg_replace('/\d{1,3}\-\d{1,3}\./', '', $domain);
	
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
function getModuleBadgeCounts($type) {
	global $fmdb, $__FM_CONFIG;
	
	if ($type == 'zones') {
		$badge_counts = array('forward' => 0, 'reverse' => 0);
		
		/** Zones */
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_id', 'domain_');
		$domain_count = $fmdb->num_rows;
		$domain_results = $fmdb->last_result;
		for ($i=0; $i<$domain_count; $i++) {
			if (!getSOACount($domain_results[$i]->domain_id) && !$domain_results[$i]->domain_clone_domain_id && 
				$domain_results[$i]->domain_type == 'master') {
				$badge_counts[$domain_results[$i]->domain_mapping]++;
			} elseif (!getNSCount($domain_results[$i]->domain_id) && !$domain_results[$i]->domain_clone_domain_id && 
				$domain_results[$i]->domain_type == 'master') {
				$badge_counts[$domain_results[$i]->domain_mapping]++;
			} elseif ($domain_results[$i]->domain_reload != 'no') {
				$badge_counts[$domain_results[$i]->domain_mapping]++;
			}
		}
		unset($domain_results, $domain_count);
	} elseif ($type == 'servers') {
		$badge_counts = null;
		$server_builds = array();
		
		/** Servers */
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_', "AND `server_installed`!='yes' OR (`server_status`='active' AND `server_build_config`='yes')");
		$server_count = $fmdb->num_rows;
		$server_results = $fmdb->last_result;
		for ($i=0; $i<$server_count; $i++) {
			$server_builds[] = $server_results[$i]->server_name;
		}
		if (version_compare(getOption('version', 0, $_SESSION['module']), '1.1', '>=')) {
			basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_', "AND `server_client_version`!='" . getOption('client_version', 0, $_SESSION['module']) . "'");
			$server_count = $fmdb->num_rows;
			$server_results = $fmdb->last_result;
			for ($i=0; $i<$server_count; $i++) {
				$server_builds[] = $server_results[$i]->server_name;
			}
		}
		
		$servers = array_unique($server_builds);
		$badge_counts = count($servers);
		
		unset($server_builds, $servers, $server_count, $server_results);
	}
	
	return $badge_counts;
}


/**
 * Gets the name servers hosting a zone
 *
 * @since 1.1.1
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param id $domain_id Domain ID to check
 * @return string
 */
function getZoneServers($domain_id) {
	global $__FM_CONFIG, $fmdb, $fm_dns_zones;
	
	$serial_no = null;
	
	if ($domain_id) {
		/** Force buildconf for all associated DNS servers */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', $domain_id, 'domain_', 'domain_id');
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			$domain_name_servers = $result[0]->domain_name_servers;
			
			if (!isset($fm_dns_zones)) {
				include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_zones.php');
			}
			$name_servers = $fm_dns_zones->getNameServers($domain_name_servers);
			
			/** Loop through name servers */
			if ($name_servers) {
				$name_server_count = $fmdb->num_rows;
				for ($i=0; $i<$name_server_count; $i++) {
					$serial_no[] = $name_servers[$i]->server_serial_no;
				}
				$serial_no = implode(',', $serial_no);
			}
		}
	}
	
	return $serial_no;
}


/**
 * Sets default override configuration options based on OS distro
 *
 * @since 1.2
 * @package facileManager
 * @subpackage fmDNS
 */
function setDefaultOverrideOptions() {
	global $fm_module_options;
	
	$config = null;
	$server_os_distro = isDebianSystem($_POST['server_os_distro']) ? 'debian' : strtolower($_POST['server_os_distro']);
	
	switch ($server_os_distro) {
		case 'debian':
			$config = array(
							array('cfg_type' => 'global', 'server_serial_no' => $_POST['SERIALNO'], 'cfg_name' => 'pid-file', 'cfg_data' => '/var/run/named/named.pid')
						);
	}
	
	if (is_array($config)) {
		if (!isset($fm_module_options)) include(ABSPATH . 'fm-modules/fmDNS/classes/class_options.php');
		
		foreach ($config as $config_data) {
			$fm_module_options->add($config_data);
		}
	}
}


/**
 * Sets default override configuration options based on OS distro
 *
 * @since 1.2
 * @package facileManager
 * @subpackage fmDNS
 */
function removeRestrictedRR($rr) {
	global $__FM_CONFIG;
	
	return (!in_array($rr, $__FM_CONFIG['records']['require_zone_rights']) || currentUserCan('manage_zones', $_SESSION['module'])) ? true : false;
}


/**
 * Adds the module menu items
 *
 * @since 1.2
 * @package facileManager
 * @subpackage fmDNS
 */
function buildModuleMenu() {
	$badge_counts = getModuleBadgeCounts('zones');
	addObjectPage('Zones', 'Zones', array('manage_zones', 'manage_records', 'reload_zones', 'view_all'), $_SESSION['module'], 'zones.php');
		addSubmenuPage('zones.php', 'Forward', 'Forward Zones', null, $_SESSION['module'], 'zones-forward.php', null, null, $badge_counts['forward']);
		addSubmenuPage('zones.php', 'Reverse', 'Reverse Zones', null, $_SESSION['module'], 'zones-reverse.php', null, null, $badge_counts['reverse']);
		addSubmenuPage('zones.php', null, 'Records', null, $_SESSION['module'], 'zone-records.php');
		addSubmenuPage('zones.php', null, 'Record Validation', null, $_SESSION['module'], 'zone-records-validate.php');
	
	addObjectPage('Config', 'Name Servers', array('manage_servers', 'build_server_configs', 'view_all'), $_SESSION['module'], 'config-servers.php');
		addSubmenuPage('config-servers.php', 'Servers', 'Name Servers', array('manage_servers', 'build_server_configs', 'view_all'), $_SESSION['module'], 'config-servers.php', null, null, getModuleBadgeCounts('servers'));
		addSubmenuPage('config-servers.php', 'Views', 'Views', array('manage_servers', 'view_all'), $_SESSION['module'], 'config-views.php');
		addSubmenuPage('config-servers.php', 'ACLs', 'Access Control Lists', array('manage_servers', 'view_all'), $_SESSION['module'], 'config-acls.php');
		addSubmenuPage('config-servers.php', 'Keys', 'Keys', array('manage_servers', 'view_all'), $_SESSION['module'], 'config-keys.php');
		addSubmenuPage('config-servers.php', 'Options', 'Options', array('manage_servers', 'view_all'), $_SESSION['module'], 'config-options.php');
		addSubmenuPage('config-servers.php', 'Logging', 'Logging', array('manage_servers', 'view_all'), $_SESSION['module'], 'config-logging.php');
		addSubmenuPage('config-servers.php', 'Controls', 'Controls', array('manage_servers', 'view_all'), $_SESSION['module'], 'config-controls.php');
	
	addObjectPage('Templates', 'SOA', array('manage_servers', 'build_server_configs', 'view_all'), $_SESSION['module'], 'templates-soa.php');
		addSubmenuPage('templates-soa.php', 'Templates', 'SOA', array('manage_servers', 'build_server_configs', 'view_all'), $_SESSION['module'], 'templates-soa.php');

	addSettingsPage($_SESSION['module'], $_SESSION['module'] . ' Settings', array('manage_settings', 'view_all'), $_SESSION['module'], 'module-settings.php');
}


/**
 * Gets the name servers hosting a zone
 *
 * @since 1.3
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param id $domain_name Domain name to convert to utf8
 * @return string
 */
function displayFriendlyDomainName($domain_name) {
	$new_domain_name = function_exists('idn_to_utf8') ? idn_to_utf8($domain_name) : $domain_name;
	if ($new_domain_name != $domain_name) $new_domain_name = $domain_name . ' (' . $new_domain_name . ')';
	
	return $new_domain_name;
}
?>