<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2013-2019 The facileManager Team                          |
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
	$checks[] = (basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_', 'active')) ? null : sprintf('<p>' . _('You currently have no active servers defined. <a href="%s">Click here</a> to define one or more to manage.') . '</p>', getMenuURL(_('Servers')));
	
	/** Count global options */
	$checks[] = (basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_id', 'cfg_')) ? null : sprintf('<p>' . __('You currently have no global options defined for named.conf. <a href="%s">Click here</a> to define one or more.') . '</p>', getMenuURL(__('Options')));
	
	/** Count zones */
	$checks[] = (basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_id', 'domain_')) ? null : sprintf('<p>' . __('You currently have no zones defined. <a href="%s">Click here</a> to define one or more.') . '</p>', getMenuURL(__('Zones')));
	
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
	if (currentUserCan('manage_servers', $_SESSION['module'])) {
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_', 'AND server_type!="remote"');
		$server_count = $fmdb->num_rows;
		$server_results = $fmdb->last_result;
		for ($i=0; $i<$server_count; $i++) {
			if ($server_results[$i]->server_installed != 'yes') {
				$errors .= sprintf('<b>%s</b> - %s' . "\n", $server_results[$i]->server_name, __('Client is not installed.'));
			} elseif (isset($server_results[$i]->server_client_version) && $server_results[$i]->server_client_version != getOption('client_version', 0, $_SESSION['module'])) {
				$errors .= sprintf('<a href="%s"><b>%s</b></a> - %s' . "\n", getMenuURL(_('Servers')), $server_results[$i]->server_name, __('Client needs to be upgraded.'));
			} elseif ($server_results[$i]->server_build_config != 'no' && $server_results[$i]->server_status == 'active') {
				$errors .= sprintf('<a href="%s"><b>%s</b></a> - %s' . "\n", getMenuURL(_('Servers')), $server_results[$i]->server_name, __('Server needs a new configuration built.'));
			}
		}
	}
	
	/** Zone stats */
	basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'no', 'domain_', 'domain_template');
	$domain_count = $fmdb->num_rows;
	$domain_results = $fmdb->last_result;
	for ($i=0; $i<$domain_count; $i++) {
		if (!getSOACount($domain_results[$i]->domain_id) && !$domain_results[$i]->domain_clone_domain_id && 
				$domain_results[$i]->domain_type == 'master' && 
				currentUserCan(array('access_specific_zones'), $_SESSION['module'], array(0, $domain_results[$i]->domain_id))) {
			$errors .= '<a href="zone-records.php?map=' . $domain_results[$i]->domain_mapping . '&domain_id=' . $domain_results[$i]->domain_id;
			if (currentUserCan('manage_zones', $_SESSION['module'])) $errors .= '&record_type=SOA';
			$errors .= '">' . displayFriendlyDomainName($domain_results[$i]->domain_name) . '</a> - ' . __('Zone does not have a SOA defined.') . "\n";
		} elseif (!getNSCount($domain_results[$i]->domain_id) && !$domain_results[$i]->domain_clone_domain_id && 
				$domain_results[$i]->domain_type == 'master' && 
				currentUserCan(array('access_specific_zones'), $_SESSION['module'], array(0, $domain_results[$i]->domain_id))) {
			$errors .= '<a href="zone-records.php?map=' . $domain_results[$i]->domain_mapping . '&domain_id=' . $domain_results[$i]->domain_id;
			if (currentUserCan('manage_zones', $_SESSION['module'])) $errors .= '&record_type=NS';
			$errors .= '">' . displayFriendlyDomainName($domain_results[$i]->domain_name) . '</a> - ' . __('Zone does not have any NS records defined.') . "\n";
		} elseif ($domain_results[$i]->domain_reload != 'no' && 
				currentUserCan(array('access_specific_zones'), $_SESSION['module'], array(0, $domain_results[$i]->domain_id)) &&
				currentUserCan('reload_zones', $_SESSION['module'])) {
			$errors .= '<a href="' . getMenuURL(ucfirst($domain_results[$i]->domain_mapping)) . '"><b>' . displayFriendlyDomainName($domain_results[$i]->domain_name) . '</b></a> - ' . __('Zone needs to be reloaded.') . "\n";
		}
	}
	if ($errors) {
		$error_display = '<li>' . str_replace("\n", "</li>\n<li>", $errors);
		$error_display = rtrim($error_display, '<li>');
	} else $error_display = null;

	/** Record stats */
	$query = 'SELECT COUNT(*) record_count FROM fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'records WHERE record_status!="deleted" AND account_id=' . $_SESSION['user']['account_id'] .
			' AND domain_id NOT IN (SELECT domain_id FROM fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains WHERE domain_status!="deleted" AND account_id=' . $_SESSION['user']['account_id'] .
			' AND domain_template="yes")';
	$fmdb->get_results($query);
	$record_count = $fmdb->last_result[0]->record_count;

	$dashboard = sprintf('<div>
	<div id="shadow_box">
		<div id="shadow_container">
		<h3>%s</h3>
		<li>%s</li>
		<li>%s</li>
		<li>%s</li>
		</div>
	</div>
	</div>', __('Summary'),
			sprintf(ngettext('You have <b>%s</b> name server configured.', 'You have <b>%s</b> name servers configured.', $server_count), formatNumber($server_count)),
			sprintf(ngettext('You have <b>%s</b> zone defined.', 'You have <b>%s</b> zones defined.', $domain_count), formatNumber($domain_count)),
			sprintf(ngettext('You have <b>%s</b> record.', 'You have <b>%s</b> records.', $record_count), formatNumber($record_count))
			);

	if ($error_display) {
		$dashboard .= sprintf('<div>
	<div id="shadow_box">
		<div id="shadow_container">
		<h3>%s</h3>
		%s
		</div>
	</div>
	</div>', __('Needs Attention'), $error_display);
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
		$icon = (getNameFromID($_REQUEST['domain_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_dnssec') == 'yes') ? sprintf('&nbsp; <i class="mini-icon fa fa-lock" title="%s"></i>', __('Zone is secured with DNSSEC')) : null;
		$domain_menu = '<div id="topheadpart">
			<span class="single_line">' . __('Domain') . ':&nbsp;&nbsp; ' . $domain . $icon . '</span>
		</div>';
		if ($parent_domain_id = getNameFromID($_GET['domain_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_clone_domain_id')) {
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $parent_domain_id, 'domain_', 'domain_id');
			extract(get_object_vars($fmdb->last_result[0]));
			$domain_name = displayFriendlyDomainName($domain_name);
			$record_type_uri = array_key_exists('record_type', $_GET) ? '&record_type=' . $_GET['record_type'] : null;
			$domain_menu .= sprintf('<div id="topheadpart">
			<span class="single_line">%s:&nbsp;&nbsp; <a href="zone-records.php?map=%s&domain_id=%s%s" title="%s">%s</a></span>
		</div>', __('Clone of'), $domain_mapping, $parent_domain_id, $record_type_uri, __('Edit parent zone records'), $domain_name);
		}
		if ($parent_domain_id = getNameFromID($_GET['domain_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_template_id')) {
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $parent_domain_id, 'domain_', 'domain_id');
			extract(get_object_vars($fmdb->last_result[0]));
			$domain_name = displayFriendlyDomainName($domain_name);
			$record_type_uri = array_key_exists('record_type', $_GET) ? '&record_type=' . $_GET['record_type'] : null;
			$domain_menu .= sprintf('<div id="topheadpart">
			<span class="single_line">%s:&nbsp;&nbsp; <a href="zone-records.php?map=%s&domain_id=%s%s" title="%s">%s</a></span>
		</div>', __('Based on template'), $domain_mapping, $parent_domain_id, $record_type_uri, __('Edit template zone records'), $domain_name);
		}
	} else $domain_menu = null;
	
	return array($domain_menu, null);
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
			there you can add, edit {$__FM_CONFIG['icons']['edit']}, delete 
			{$__FM_CONFIG['icons']['delete']}, and reload {$__FM_CONFIG['icons']['reload']} zones depending on your user permissions.</p>
			<p>You can define a zone as a clone <i class="mini-icon fa fa-clone"></i> of another previously defined master zone.  The cloned zone will contain all of the same records
			present in the parent zone.  This is useful if you have multiple zones with identical records as you won't have to repeat the record
			definitions.  You can also skip records and define new ones inside clone zones for those that are slightly different than the parent.</p>
			<p>Zones can also be saved as a template and applied to an unlimited number of zones. This can speed up your zone additions and
			management if you have several zones with a similar framework. You can create a zone template when creating a new zone or you can 
			completely manage them from <a href="__menu{Zone Templates}">Templates</a>. All zones based on a template will be shown with the
			<i class="mini-icon fa fa-picture-o"></i> icon. Zone templates can only be deleted when there are no zones associated 
			with them. In addition, clones of a zone based on a template cannot be shortened to a DNAME RR.</p>
			<p>Zones can support dynamic updates only if the checkbox is ticked while creating or editing individual zones. This will cause 
			{$_SESSION['module']} to compare the zone file from the DNS server with that in the database and make any necessary changes. This option
			will increase processing time while reloading zones.</p>
			<p>Zones can support DNSSEC signing only if the checkbox is ticked while creating or editing individual zones. You must create the KSK and ZSK
			before zones will be signed (offline and inline signing are supported). During a configuration build or zone reload, the ZSK and KSK files will
			stored on the name servers in the directory defined by the most specific key-directory option defined (global, view, zone, server-override,
			etc.). This option will increase processing time while reloading zones.</p>
			<p><i>The 'Zone Management' or 'Super Admin' permission is required to add, edit, and delete zones and templates.</i></p>
			<p><i>The 'Reload Zone' or 'Super Admin' permission is required for reloading zones.</i></p>
			<p>Reverse zones can be entered by either their subnet value (192.168.1) or by their arpa value (1.168.192.in-addr.arpa). You can also
			delegate reverse zones by specifying the classless IP range in the zone name (1-128.168.192.in-addr.arpa).</p>
			<p>Zones that are missing SOA and NS records will be highlighted with a red background and will not be built or reloaded until the 
			records exists.</p>
			<p>You can also import BIND-compatible zone dump files instead of adding records individually. Go to Admin &rarr; 
			<a href="__menu{Tools}">Tools</a> and use the Import Zone Files utility. Select your dump file and click 'Import Zones'
			which will import any views, zones, and records listed in the file.</p>
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
			in the record value. {$_SESSION['module']} will attemnpt to auto-detect whether or not the domain should be appended if no choice is
			made at the time of record creation.</p>
			<p><i>The 'Record Management' or 'Super Admin' permission is required to add, edit, and delete records.</i></p>
			<p>When adding or updating a SOA record for a zone, the domain can be appended to the Master Server and Email Address if selected. This
			means you could simply enter 'ns1' and 'username' for the Master Server and Email Address respectively. If you prefer to enter the entire
			entry, make sure you select 'no' for Append Domain.</p>
			<p>SOA records can also be saved as a template and applied to an unlimited number of zones. This can speed up your zone additions and
			management. You can create a SOA template when managing zone records or you can completely manage them from 
			<a href="__menu{SOA Templates}">Templates</a>. SOA templates can only be deleted when there are no zones associated with them.</p>
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
			<p>Certain zone records can be managed from the client script on the name servers via an API. Reference the client helpfile for supported uses.</p>
			<p><b>URL Resource Records</b><br />
			The custom URL resource record allows domains or records to redirect users to a web page. This is enabled by defining one or more web servers
			that will handle the web redirects at Admin &rarr; <a href="__menu{{$_SESSION['module']} Settings}">Settings</a>. The supporting web servers
			will also need the client installed to enable URL redirects (reference client help file). Once defined, the URL RR will be available when 
			managing zone records.</p>
			<br />
		</div>
	</li>
	<li>
		<a class="list_title">Configure Servers</a>
		<div>
			<p>All aspects of server configuration takes place in the Config menu 
			item. From there you can add, edit {$__FM_CONFIG['icons']['edit']}, 
			delete {$__FM_CONFIG['icons']['delete']} servers and options depending on your user permissions.</p>
			
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
			Before building the configuration {$__FM_CONFIG['icons']['build']} you can preview {$__FM_CONFIG['icons']['preview']} the configs to 
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
			at Config &rarr; <a href="__menu{Keys}">Keys</a>. DNSSEC keys, however, can be automatically generated and managed by {$_SESSION['module']}.
			DNSSEC keys can only be deleted when they are not used for signing and/or have been revoked.</p>
			<p><i>The 'Server Management' or 'Super Admin' permission is required to manage keys.</i></p>
			<br />
			
			<p><b>Masters</b><br />
			Masters can be defined globally or server-based which is controlled by the servers drop-down menu in the upper right. To define the masters, 
			go to Config &rarr; <a href="__menu{Masters}">Masters</a>.</p>
			<p>Masters can then be used when defining zones and defining the <i>masters</i> and <i>also-notify</i> options.</p>
			<p>Server-level masters always supercede global ones.</p>
			<p><i>The 'Server Management' or 'Super Admin' permission is required to manage the masters.</i></p>
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
			
			<p><b>Operations</b><br />
			Controls and Statistics Channels can be defined globally or server-based which is controlled by the servers drop-down menu in the upper right. 
			To manage the controls and statistics configuration, go to Config &rarr; <a href="__menu{Operations}">Operations</a>.</p>
			<p>Server-level controls always supercede global ones.</p>
			<p><i>The 'Server Management' or 'Super Admin' permission is required to manage server operations.</i></p>
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


function moduleAddServer($action) {
	include(ABSPATH . 'fm-modules/' . $_POST['module_name'] . '/classes/class_servers.php');
	
	$action .= 'Server';
	return $fm_module_servers->$action($_POST);
}


function moduleCompleteClientInstallation() {
	/** Install default config option overrides based on OS distro */
	setDefaultOverrideOptions();
	
	setBuildUpdateConfigFlag($_POST['SERIALNO'], 'yes', 'build');
}


function reloadZoneSQL($domain_ids, $reload_zone, $associated) {
	global $fmdb, $__FM_CONFIG;
	
	if ($associated == 'all') {
		if (!is_array($domain_ids)) $domain_ids = array($domain_ids);

		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` SET `domain_reload`='$reload_zone', `domain_check_config`='no' WHERE `domain_template` = 'no' AND
				(`domain_id` IN (" . join(',', $domain_ids) . ") OR `domain_clone_domain_id` IN (" . join(',', $domain_ids) . ") OR `domain_template_id` IN (" . join(',', $domain_ids) . '))';
	} else {
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` SET `domain_reload`='$reload_zone', `domain_check_config`='no' WHERE `domain_template` = 'no' AND `domain_id`=$domain_ids";
	}
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
		$query = "SELECT soa_id FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}soa` WHERE (`domain_id`='$domain_id' OR
			`domain_id` = (SELECT `domain_clone_domain_id` FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE
				`domain_id`='$domain_id')
		) AND `soa_status`!='deleted'";
	} else {
		$query = "SELECT soa_id FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}soa` WHERE `soa_id`= (SELECT DISTINCT(`soa_id`) FROM 
			`fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE `soa_id`!=0 AND (`domain_id`='$domain_id' OR
				`domain_id` = (SELECT `domain_clone_domain_id` FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE
					`domain_id`='$domain_id') OR
				`domain_id` = (SELECT `domain_template_id` FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE
					`domain_id`='$domain_id') OR
				`domain_id` = (SELECT `domain_template_id` FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE
					`domain_id`=(SELECT `domain_clone_domain_id` FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE
					`domain_id`='$domain_id'))
			)) AND `soa_status`!='deleted'";
	}
	$fmdb->get_results($query);
	return $fmdb->num_rows;
}

function getNSCount($domain_id) {
	global $fmdb, $__FM_CONFIG;
	
	$query = "SELECT record_id FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` WHERE (`domain_id`='$domain_id' OR
			`domain_id` = (SELECT `domain_clone_domain_id` FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE
				`domain_id`='$domain_id') OR
			`domain_id` = (SELECT `domain_template_id` FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE
				`domain_id`='$domain_id') OR
			`domain_id` = (SELECT `domain_template_id` FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE
				`domain_id`=(SELECT `domain_clone_domain_id` FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE
				`domain_id`='$domain_id'))
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
function verifyAndCleanAddresses($data, $subnets_allowed = 'subnets-allowed') {
	/** Remove extra spaces */
	$data = preg_replace('/\s\s+/', ' ', $data);
	
	/** Swap delimiters for ; */
	$data = str_replace(array("\n", ';', ' ', ','), ',', $data);
	$data = str_replace(',,', ',', $data);
	$data = trim($data, ',');
	
	$addresses = explode(',', $data);
	foreach ($addresses as $ip_address) {
		$cidr = null;

		$ip_address = rtrim(trim($ip_address), '.');
		if (!strlen($ip_address)) continue;
		
		/** Handle negated addresses */
		if (strpos($ip_address, '!') === 0) {
			$ip_address = substr($ip_address, 1);
		}
		
		if (strpos($ip_address, '/') !== false && $subnets_allowed == 'subnets-allowed') {
			$cidr_array = explode('/', $ip_address);
			list($ip_address, $cidr) = $cidr_array;
		}
		
		/** IPv4 checks */
		if (strpos($ip_address, ':') === false) {
			/** Valid CIDR? */
			if ($cidr && !checkCIDR($cidr, 32)) return sprintf(__('%s is not valid.'), "$ip_address/$cidr");
			
			/** Create full IP */
			$ip_octets = explode('.', $ip_address);
			if (count($ip_octets) < 4) {
				$ip_octets = array_merge($ip_octets, array_fill(count($ip_octets), 4 - count($ip_octets), 0));
			}
			$ip_address = implode('.', $ip_octets);
		} else {
			/** IPv6 checks */
			if ($cidr && !checkCIDR($cidr, 128)) return sprintf(__('%s is not valid.'), "$ip_address/$cidr");
		}
		
		if (verifyIPAddress($ip_address) === false) return sprintf(__('%s is not valid.'), $ip_address);
	}
	
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
			$query = 'SELECT domain_id FROM `fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'track_builds` WHERE domain_id=' . $domain_id . ' LIMIT 1';
			$result = $fmdb->get_results($query);
			$reload_allowed = ($fmdb->num_rows) ? true : false;
		} else $reload_allowed = true;
	} else $reload_allowed = false;
	
	return $reload_allowed;
}
	

/**
 * Removed trailing periods
 *
 * @since 1.0
 * @package facileManager
 */
function trimFullStop($value){
	return rtrim($value, '.');
}


/**
 * Gets the menu badge counts
 *
 * @since 1.1
 * @package facileManager
 * @subpackage fmDNS
 *
 * @return integer
 */
function getModuleBadgeCounts($type) {
	global $fmdb, $__FM_CONFIG;
	
	$badge_counts = null;
	if ($type == 'zones') {
		$badge_counts = array('forward' => 0, 'reverse' => 0);
		
		/** Zones */
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_id', 'domain_');
		$domain_count = $fmdb->num_rows;
		if ($domain_count) $domain_results = $fmdb->last_result;
		for ($i=0; $i<$domain_count; $i++) {
			if ($domain_results[$i]->domain_template == 'no' && !$domain_results[$i]->domain_clone_domain_id && 
					$domain_results[$i]->domain_type == 'master') {
				if (currentUserCan(array('access_specific_zones'), $_SESSION['module'], array(0, $domain_results[$i]->domain_id))) {
					if (!getSOACount($domain_results[$i]->domain_id)) {
						$badge_counts[$domain_results[$i]->domain_mapping]++;
					} elseif (!getNSCount($domain_results[$i]->domain_id)) {
						$badge_counts[$domain_results[$i]->domain_mapping]++;
					} elseif ($domain_results[$i]->domain_reload != 'no') {
						$badge_counts[$domain_results[$i]->domain_mapping]++;
					} elseif ($domain_results[$i]->domain_dnssec != 'no') {
						basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_id', 'key_', 'AND domain_id=' . $domain_results[$i]->domain_id);
						if (!$fmdb->num_rows || ($domain_results[$i]->domain_dnssec_signed && getDNSSECExpiration($domain_results[$i]) <= strtotime('now + 7 days'))) {
							$badge_counts[$domain_results[$i]->domain_mapping]++;
						}
					}
				}
			}
		}
		unset($domain_results, $domain_count);
	} elseif ($type == 'servers' && currentUserCan('manage_servers', $_SESSION['module'])) {
		$server_builds = array();
		
		/** Servers */
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_', " AND `server_type`!='remote' AND (`server_installed`!='yes' OR (`server_status`='active' AND `server_build_config`='yes'))");
		$server_count = $fmdb->num_rows;
		if ($server_count) $server_results = $fmdb->last_result;
		for ($i=0; $i<$server_count; $i++) {
			$server_builds[] = $server_results[$i]->server_name;
		}
		if (version_compare(getOption('version', 0, $_SESSION['module']), '1.1', '>=')) {
			basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_', "AND `server_type`!='remote' AND `server_client_version`!='" . getOption('client_version', 0, $_SESSION['module']) . "'");
			$server_count = $fmdb->num_rows;
			if ($server_count) $server_results = $fmdb->last_result;
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
 * @param array $server_types Type of servers to pull
 * @return string
 */
function getZoneServers($domain_id, $server_types = array('masters')) {
	global $__FM_CONFIG, $fmdb, $fm_dns_zones;
	
	$serial_no = null;
	
	if ($domain_id) {
		/** Force buildconf for all associated DNS servers */
		if ($domain_template_id = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_template_id')) {
			$domain_id = $domain_template_id;
		}
		$domain_name_servers = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name_servers');
		if ($domain_name_servers) {
			if (!isset($fm_dns_zones)) {
				include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_zones.php');
			}
			$name_servers = $fm_dns_zones->getNameServers($domain_name_servers, $server_types);
			
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
		if (!isset($fm_module_options)) include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_options.php');
		
		foreach ($config as $config_data) {
			$fm_module_options->add($config_data);
		}
	}
}


/**
 * Returns if a RR should be allowed or not
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
	$GLOBALS['zone_badge_counts'] = $badge_counts;
	addObjectPage(__('Zones'), __('Zones'), array('manage_zones', 'manage_records', 'reload_zones', 'view_all'), $_SESSION['module'], 'zones.php');
		addSubmenuPage('zones.php', __('Forward'), __('Forward Zones'), null, $_SESSION['module'], 'zones-forward.php', null, null, $badge_counts['forward']);
		addSubmenuPage('zones.php', __('Reverse'), __('Reverse Zones'), null, $_SESSION['module'], 'zones-reverse.php', null, null, $badge_counts['reverse']);
		addSubmenuPage('zones.php', __('Groups'), __('Zones Groups'), array('view_all'), $_SESSION['module'], 'zones-groups.php');
		addSubmenuPage('zones.php', null, __('Records'), null, $_SESSION['module'], 'zone-records.php');
		addSubmenuPage('zones.php', null, __('Record Validation'), null, $_SESSION['module'], 'zone-records-validate.php');
		addSubmenuPage('zones.php', __('Response Policy'), __('Response Policy Zones'), array('manage_zones', 'view_all'), $_SESSION['module'], 'config-rpz.php');
	
	addObjectPage(__('Config'), __('Name Servers'), array('manage_servers', 'build_server_configs', 'view_all'), $_SESSION['module'], 'config-servers.php');
		addSubmenuPage('config-servers.php', _('Servers'), __('Name Servers'), array('manage_servers', 'build_server_configs', 'view_all'), $_SESSION['module'], 'config-servers.php', null, null, getModuleBadgeCounts('servers'));
		addSubmenuPage('config-servers.php', __('Views'), __('Views'), array('manage_servers', 'view_all'), $_SESSION['module'], 'config-views.php');
		addSubmenuPage('config-servers.php', __('ACLs'), __('Access Control Lists'), array('manage_servers', 'view_all'), $_SESSION['module'], 'config-acls.php');
		addSubmenuPage('config-servers.php', __('Keys'), __('Keys'), array('manage_servers', 'view_all'), $_SESSION['module'], 'config-keys.php');
		addSubmenuPage('config-servers.php', __('Masters'), __('Masters'), array('manage_servers', 'view_all'), $_SESSION['module'], 'config-masters.php');
		addSubmenuPage('config-servers.php', __('Options'), __('Options'), array('manage_servers', 'view_all'), $_SESSION['module'], 'config-options.php');
		addSubmenuPage('config-servers.php', __('Logging'), __('Logging'), array('manage_servers', 'view_all'), $_SESSION['module'], 'config-logging.php');
		addSubmenuPage('config-servers.php', __('Operations'), __('Operations'), array('manage_servers', 'view_all'), $_SESSION['module'], 'config-controls.php');
	
	addObjectPage(__('Templates'), __('Zones'), array('manage_zones', 'view_all'), $_SESSION['module'], 'templates-soa.php');
		addSubmenuPage('templates-soa.php', __('SOA'), __('SOA Templates'), array('manage_zones', 'view_all'), $_SESSION['module'], 'templates-soa.php');
		addSubmenuPage('templates-soa.php', __('Zones'), __('Zone Templates'), array('manage_zones', 'manage_records', 'view_all'), $_SESSION['module'], 'templates-zones.php');

	addSettingsPage($_SESSION['module'], sprintf(__('%s Settings'), $_SESSION['module']), array('manage_settings', 'view_all'), $_SESSION['module'], 'module-settings.php');
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
	$new_domain_name = function_exists('idn_to_utf8') ? idn_to_utf8($domain_name, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) : $domain_name;
	if ($new_domain_name != $domain_name) $new_domain_name = $domain_name . ' (' . $new_domain_name . ')';
	
	return $new_domain_name;
}


/**
 * Gets the parent domain ID
 *
 * @since 2.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param id $domain_id Domain ID to check
 * @param string $level How deep to traverse (clone, template)
 * @return integer
 */
function getParentDomainID($domain_id, $level = 'clone') {
	global $__FM_CONFIG;
	
	/** Clone */
	$parent_id = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_clone_domain_id');
	$domain_id = ($parent_id) ? $parent_id : $domain_id;
	if ($level == 'clone') {
		return $domain_id;
	}
	
	/** Template */
	if ($level == 'template') {
		$parent_id = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_template_id');

		return ($parent_id) ? $parent_id : $domain_id;
	}
}


/**
 * Gets the count for zones requiring a reload
 *
 * @since 2.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param string $return_what What to return (count|ids)
 * @return integer|array
 */
function getZoneReloads($return_what) {
	global $fmdb, $__FM_CONFIG;
	
	$zone_count = 0;
	$zone_ids = array();
	
	basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_id', 'domain_', 'AND domain_reload!="no"');
	if ($fmdb->num_rows) {
		$num_rows = $fmdb->num_rows;
		$domain_list = $fmdb->last_result;
		for ($i=0; $i<$num_rows; $i++) {
			$zone_access_allowed = zoneAccessIsAllowed(array($domain_list[$i]->domain_id, $domain_list[$i]->domain_clone_domain_id));
			if (currentUserCan('reload_zones', $_SESSION['module']) && $zone_access_allowed) {
				$zone_count++;
				$zone_ids[] = $domain_list[$i]->domain_clone_domain_id ? $domain_list[$i]->domain_clone_domain_id : $domain_list[$i]->domain_id;
			}
		}
	}
	
	return $return_what == 'count' ? $zone_count : $zone_ids;
}


/**
 * Returns if access to a zone is allowed
 *
 * @since 2.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param array $domain_ids Domain IDs to check
 * @param string $included_action Included action to check against
 * @return boolean
 */
function zoneAccessIsAllowed($domain_ids, $included_action = null) {
	if ($included_action) {
		return currentUserCan('access_specific_zones', $_SESSION['module'], array_merge(array(0), $domain_ids)) & 
			currentUserCan($included_action, $_SESSION['module']);
	} else {
		return currentUserCan('access_specific_zones', $_SESSION['module'], array_merge(array(0), $domain_ids));
	}
}


/**
 * Returns the parent ID of the domain
 *
 * @since 2.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param array $domain_id Domain ID to check
 * @return integer
 */
function getZoneParentID($domain_id) {
	global $__FM_CONFIG;
	
	$parent_domain_id[] = $domain_id;
	$parent_domain_id[] = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_clone_domain_id');
	if ($parent_domain_id[1]) {
		$parent_domain_id[] = getNameFromID($parent_domain_id[1], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_template_id');
	} else {
		$parent_domain_id[1] = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_template_id');
	}
	if (!$parent_domain_id[count($parent_domain_id) - 1]) array_pop($parent_domain_id);
	
	return $parent_domain_id;
}


/**
 * Returns whether the config is in use or not
 *
 * @since 2.0
 * @package fmDNS
 *
 * @param integer $id ID to get associations
 * @param string $type Type of config to search
 * @return boolean
 */
function getConfigAssoc($id, $type) {
	global $fmdb, $__FM_CONFIG;

	$return = null;

	/** Config options */
	$query = "SELECT cfg_id FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}config WHERE account_id='{$_SESSION['user']['account_id']}' AND cfg_status!='deleted' AND 
			(cfg_data='{$type}_{$id}' OR cfg_data LIKE '{$type}_{$id};%' OR cfg_data LIKE '%;{$type}_{$id};%' OR cfg_data LIKE '%;{$type}_{$id}' OR
			cfg_data='!{$type}_{$id}' OR cfg_data LIKE '!{$type}_{$id};%' OR cfg_data LIKE '%;!{$type}_{$id};%' OR cfg_data LIKE '%;!{$type}_{$id}')";
	$result = $fmdb->get_results($query);
	if ($fmdb->num_rows) {
		return true;
	}

	/** Controls */
	$query = "SELECT control_id FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}controls WHERE account_id='{$_SESSION['user']['account_id']}' AND control_status!='deleted' AND 
			(control_addresses='{$type}_{$id}' OR control_addresses LIKE '{$type}_{$id};%' OR control_addresses LIKE '%;{$type}_{$id};%' OR control_addresses LIKE '%;{$type}_{$id}')";
	$result = $fmdb->get_results($query);
	if ($fmdb->num_rows) {
		return true;
	}

	/** Masters */
	$query = "SELECT master_id FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}masters WHERE account_id='{$_SESSION['user']['account_id']}' AND master_status!='deleted' AND 
			(master_addresses='{$type}_{$id}' OR master_addresses LIKE '{$type}_{$id};%' OR master_addresses LIKE '%;{$type}_{$id};%' OR master_addresses LIKE '%;{$type}_{$id}')";
	$result = $fmdb->get_results($query);
	if ($fmdb->num_rows) {
		return true;
	}

	return false;
}


/**
 * Returns whether the CIDR is valid or not
 *
 * @since 2.1
 * @package fmDNS
 *
 * @param string $cidr CIDR to check
 * @param integer $max_bits Maximum valid bits
 * @return boolean
 */
function checkCIDR($cidr, $max_bits) {
	return verifyNumber($cidr, 0, $max_bits);
}


/**
 * Builds the view listing in a dropdown menu
 *
 * @since 3.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param integer $view_id View ID to select
 * $param string $class Class name to apply to the div
 * @return string
 */
function buildViewSubMenu($view_id = 0, $server_serial_no = 0, $class = null) {
	$server_list = buildSelect('view_id', 'view_id', availableViews(), $view_id, 1, null, false, 'this.form.submit()');
	
	$hidden_inputs = null;
	foreach ($GLOBALS['URI'] as $param => $value) {
		if ($param == 'view_id') continue;
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


/**
 * Returns an array of views
 *
 * @since 3.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param string $status What view status to pull results with
 * @return array
 */
function availableViews($status = 'any') {
	global $fmdb, $__FM_CONFIG;
	
	$array[0][] = null;
	$array[0][0][] = __('All Views');
	$array[0][0][] = '0';
	
	$j = $k = $l = 0;
	/** Views */
	$status = ($status == 'any') ? null : "AND v.view_status='$status'";
	$fmdb->query("SELECT v.server_serial_no,v.view_id,v.view_name,s.server_name FROM `fm_dns_views` v LEFT JOIN `fm_dns_servers` s USING(server_serial_no) WHERE v.`view_status`!='deleted' $status AND v.account_id='1' ORDER BY v.`server_serial_no`  ASC,v.view_name ASC");
	if ($fmdb->num_rows && !$fmdb->sql_errors) {
		$last_result = $fmdb->last_result;
		foreach ($last_result as $results) {
			if (!$results->server_serial_no) {
				if (!array_key_exists(_('All Servers'), $array)) {
					$array[_('All Servers')][] = null;
				}
				$array[_('All Servers')][$j][] = $results->view_name;
				$array[_('All Servers')][$j][] = $results->view_id;
				$j++;
			} elseif (strpos($results->server_serial_no, 'g_') !== false) {
				$group_name = getNameFromID(str_replace('g_', '', $results->server_serial_no), 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'server_groups', 'group_', 'group_id', 'group_name');
				if (!array_key_exists($group_name, $array)) {
					$array[$group_name][] = null;
					$k = 0;
				}
				$array[$group_name][$k][] = $results->view_name;
				$array[$group_name][$k][] = $results->view_id;
				$k++;
			} elseif ($results->server_serial_no) {
				if (!array_key_exists($results->server_name, $array)) {
					$array[$results->server_name][] = null;
					$l = 0;
				}
				$array[$results->server_name][$l][] = $results->view_name;
				$array[$results->server_name][$l][] = $results->view_id;
				$l++;
			}
		}
	}
	
	return $array;
}


/**
 * Returns an array of resource records from SQL
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param string $record_type
 * @param integer $domain_id
 * @return array
 */
function buildSQLRecords($record_type, $domain_id) {
	global $fmdb, $__FM_CONFIG;
	
	if ($record_type == 'SOA') {
		$soa_query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}soa` WHERE `account_id`='{$_SESSION['user']['account_id']}' AND
			`soa_id`=(SELECT `soa_id` FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE `domain_id`='$domain_id') AND 
			`soa_template`='no' AND `soa_status`='active'";
		$result = $fmdb->get_results($soa_query);
		if (!$fmdb->num_rows) return null;
		
		foreach (get_object_vars($result[0]) as $key => $val) {
			$sql_results[$result[0]->soa_id][$key] = $val;
		}
		array_shift($sql_results[$result[0]->soa_id]);
		array_shift($sql_results[$result[0]->soa_id]);
		return $sql_results;
	} else {
		$valid_domain_ids = 'IN (' . join(',', getZoneParentID($domain_id)) . ')';
		
		$record_sql = "AND domain_id $valid_domain_ids ";
		if ($record_type != 'all') {
			if (in_array($record_type, array('A', 'AAAA'))) {
				$record_sql .= "AND record_type IN ('A', 'AAAA')";
			} else {
				$record_sql .= "AND record_type='$record_type'";
			}
		}
		$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', 'record_name', 'record_', $record_sql);
		if ($result) {
			$results = $fmdb->last_result;

			for ($i=0; $i<$result; $i++) {
				$static_array = array('record_name', 'record_ttl', 'record_class',
					'record_type', 'record_value', 'record_comment', 'record_status');
				$optional_array = array('record_priority', 'record_weight', 'record_port',
					'record_os', 'record_cert_type', 'record_key_tag', 'record_algorithm',
					'record_flags', 'record_text', 'record_params', 'record_regex',
					'record_append');
				
				foreach ($static_array as $field) {
					$sql_results[$results[$i]->record_id][$field] = $results[$i]->$field;
				}
				foreach ($optional_array as $field) {
					if ($results[$i]->$field != null) {
						$sql_results[$results[$i]->record_id][$field] = $results[$i]->$field;
					}
				}
				
				/** Skipped record? */
				basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records_skipped', $results[$i]->record_id, 'record_', 'record_id', "AND domain_id=$domain_id");
				$sql_results[$results[$i]->record_id]['record_skipped'] = ($fmdb->num_rows) ? 'on' : 'off';
			}
		}
		return $sql_results;
	}
}


/**
 * Returns an array of compared array values
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param array $data_array
 * @param array $sql_records
 * @return array
 */
function compareValues($data_array, $sql_records) {
	$changes = array();
	foreach ($data_array as $key => $val) {
		$diff = array_diff_assoc($data_array[$key], $sql_records[$key]);
		if ($diff) {
			$changes[$key] = $diff;
		}
	}

	return $changes;
}


/**
 * Returns the DNSSEC expiration date for a zone
 *
 * @since 3.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param array $data Domain details array
 * @param string $type What expiration should be processed
 * @return integer
 */
function getDNSSECExpiration($data, $type = 'calculated') {
	$domain_dnssec_sig_expires = ($data->domain_dnssec_sig_expire) ? $data->domain_dnssec_sig_expire : getOption('dnssec_expiry', $_SESSION['user']['account_id'], $_SESSION['module']);
	$domain_dnssec_sig_expires = ($type == 'calculated') ? strtotime(date('YmdHis', $data->domain_dnssec_signed) . ' + ' . $domain_dnssec_sig_expires . ' days') : date('YmdHis', strtotime('now + ' . $domain_dnssec_sig_expires . ' days'));
	
	return $domain_dnssec_sig_expires;
}


/**
 * Returns the members of the group
 *
 * @since 3.1
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param string $group_id Group ID
 * @param string $capability Capability to process
 * @return integer
 */
function moduleExplodeGroup($group_id, $capability) {
	global $fmdb, $__FM_CONFIG;
	
	$return = false;
	
	if ($capability == 'access_specific_zones') {
		$group_id = substr($group_id, 2);
		$domain_sql = "AND (domain_groups='$group_id' OR domain_groups LIKE '$group_id;%' OR domain_groups LIKE '%;$group_id;%' OR domain_groups LIKE '%;$group_id')";
		$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_id', 'domain_', $domain_sql);
		for ($x=0; $x<$fmdb->num_rows; $x++) {
			$return[] = $fmdb->last_result[$x]->domain_id;
		}
	}
	
	return $return;
}


/**
 * Checks the PTR zone for auto-PTR creation
 *
 * @since 3.2
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param string $ip Full IP address to parse
 * @param integer $domain_id Forward domain ID to link to
 * @return array
 */
function checkPTRZone($ip, $domain_id) {
	global $fmdb, $__FM_CONFIG;

	$octet = explode('.', $ip);
	$zone = "'{$octet[2]}.{$octet[1]}.{$octet[0]}.in-addr.arpa', '{$octet[1]}.{$octet[0]}.in-addr.arpa', '{$octet[0]}.in-addr.arpa'";

	basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $zone, 'domain_', 'domain_name', "OR domain_name IN ($zone) AND domain_status!='deleted'");
	if ($fmdb->num_rows) {
		$result = $fmdb->last_result;
		return array($result[0]->domain_id, null);
	} else {
		basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_name', 'domain_', "AND domain_mapping='reverse' AND domain_name LIKE '%-%-%'");
		if ($fmdb->num_rows) {
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$domain_name = $fmdb->last_result[$i]->domain_name;
				$range = array();
				foreach (array_reverse(explode('.', $domain_name)) as $key => $tmp_octect) {
					if (in_array($key, array(0, 1))) continue;
					
					if (strpos($tmp_octect, '-') !== false) {
						list($start, $end) = explode('-', $tmp_octect);
						$range['start'][] = $start;
						$range['end'][] = $end;
					} else {
						$range['start'][] = $tmp_octect;
						$range['end'][] = $tmp_octect;
					}
				}
				$range['start'] = array_pad($range['start'], 4, 0);
				$range['end'] = array_pad($range['end'], 4, 255);

				if (ip2long(join('.', $range['start'])) <= ip2long($ip) && ip2long(join('.', $range['end'])) >= ip2long($ip)) {
					return array($fmdb->last_result[$i]->domain_id, null);
				}
			}
		}
	}
	
	/** No match so auto create if allowed */
	if (getOption('auto_create_ptr_zones', $_SESSION['user']['account_id'], $_SESSION['module']) == 'yes') {
		return autoCreatePTRZone($zone, $domain_id);
	}
	return array(null, __('Reverse zone does not exist.'));
}

/**
 * Create the reverse zone if it does not exist
 *
 * @since 3.2
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param string $new_zones Zone names to check
 * @param integer $fwd_domain_id Forward domain ID to link to
 * @return array
 */
function autoCreatePTRZone($new_zones, $fwd_domain_id) {
	global $__FM_CONFIG, $fmdb;

	basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $fwd_domain_id, 'domain_', 'domain_id');
	if ($fmdb->num_rows) {
		$result = $fmdb->last_result;

		$new_zone = explode(",", $new_zones);

		$ptr_array['domain_id'] = 0;
		$ptr_array['domain_name'] = trim($new_zone[0], "'");
		$ptr_array['domain_mapping'] = 'reverse';
		$ptr_array['domain_name_servers'] = explode(';', $result[0]->domain_name_servers);

		$copy_fields = array('soa_id', 'domain_view', 'domain_type', 'domain_template_id', 'domain_name_servers');
		foreach ($copy_fields as $field) {
			$ptr_array[$field] = $result[0]->$field;
		}

		global $fm_dns_zones;

		if (!class_exists('fm_dns_zones')) {
			include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_zones.php');
		}
		$retval = $fm_dns_zones->add($ptr_array);

		return !is_int($retval) ? array(null, $retval) : array($retval, __('Created reverse zone.'));
	}

	return array(null, __('Forward domain not found.'));
}

/**
 * Resets the URL RR servers config builds status
 *
 * @since 4.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param string $build_update Build or Update
 * @return null
 */
function resetURLServerConfigStatus($build_update = 'build') {
	global $__FM_CONFIG, $fmdb;

	$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}servers` SET `server_{$build_update}_config`='yes' WHERE `server_url_config_file`!='' AND `server_status`!='deleted' AND `server_installed`='yes' AND `account_id`='{$_SESSION['user']['account_id']}'";
	$fmdb->query($query);

	return;
}


?>
