<?php
if (!defined('FM_NO_CHECKS')) define('FM_NO_CHECKS', true);
require_once('../../../fm-init.php');

header("Content-Type: text/javascript");

echo '
$(document).ready(function() {
	
	if (onPage("config-acls.php")) {
		$(function() {
			$("#pagination_container #wlan_ids").select2({
				containerCss: { "min-width": "180px", "max-width": "300px" },
				minimumResultsForSearch: 10
			});
		});
	}

	$("#manage_item_contents").delegate("#hw_mode", "change", function(e) {
		if (jQuery.inArray($(this).val(), ["a", "g"]) !== -1) {
			$("#hw_mode_option").show("slow");
			if ($(this).val() == "a") {
				$("#ieee80211ac_entry").show("slow");
			} else {
				$("#ieee80211ac_entry").hide();
			}
		} else {
			$("#hw_mode_option").slideUp();
			$("#ieee80211n").prop("checked", false);
			$("#ieee80211ac").prop("checked", false);
		}
	});

	$("#manage_item_contents").delegate("#auth_algs", "click", function(e) {
		if ($(this).is(":checked")) {
			$(".security_options").show("slow");
		} else {
			$(".security_options").hide("slow");
			$("#wpa_passphrase").val("");
		}
	});

	$("#manage_item_contents").delegate("#show_password", "click", function(e) {
		if ($("#wpa_passphrase").attr("type") == "password") {
			$("#wpa_passphrase").attr("type", "text");
		} else {
			$("#wpa_passphrase").attr("type", "password");
		}
	});

});

function displayOptionPlaceholder(option_value) {
	var option_name = document.getElementById("config_name").value;
	var server_serial_no	= getUrlVars()["server_serial_no"];
	var cfg_type = document.getElementsByName("config_type")[0].value;
	var cfg_id = document.getElementsByName("config_id")[0].value;

	var form_data = {
		get_option_placeholder: true,
		option_name: option_name,
		option_value: option_value,
		server_serial_no: server_serial_no,
		item_type: cfg_type,
		cfg_id: cfg_id,
		is_ajax: 1
	};

	$.ajax({
		type: "POST",
		url: "fm-modules/' . $_SESSION['module'] . '/ajax/getData.php",
		data: form_data,
		success: function(response)
		{
			if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
				doLogout();
				return false;
			}
			$(".value_placeholder").html(response);
			if (response.toLowerCase().indexOf("address_match_element") == -1) {
				$("#manage #config_data").select2({
					width: "100px",
					minimumResultsForSearch: 10
				});
			}
		}
	});
}

';
?>