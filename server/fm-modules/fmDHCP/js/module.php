<?php
if (!defined('FM_NO_CHECKS')) define('FM_NO_CHECKS', true);
require_once('../../../fm-init.php');

$module_name = basename(dirname(dirname(__FILE__)));

header("Content-Type: text/javascript");

echo '
$(document).ready(function() {
	
//	if (onPage("leases.php")) {
//		/* Dynamic zone compare routine */
//		var server_serial_no = getUrlVars()["server_serial_no"];
//		if (server_serial_no > 0) {
//			loadServerLeases();
//		}
//	}

	$(function() {
		$("#item_id").select2({minimumResultsForSearch: 10, containerCss: { "min-width": "180px", "text-align": "left" }});
	});

	/* Add more */
	$("#manage_item_contents").delegate("a#add_more", "click tap", function(e) {
		var $this 		= $(this);

		var form_data = {
			clicks: $("div[class=\'range_input\']").length,
			is_ajax: 1
		};

		$.ajax({
			type: "POST",
			url: "fm-modules/' . $module_name . '/ajax/addFormElements.php",
			data: form_data,
			success: function(response)
			{
				$this.parent().before(response);
			}
		});

		return false;
	});

	$("#purge-leases").click(function() {
		if (confirm("' . __('Are you sure you want to delete all leases?') . '")) {
			$("#manage_item").fadeIn(200);
			$("#manage_item_contents").html("<p>' . __('Processing') . '... <i class=\"fa fa-spinner fa-spin\"></i></p>");

			var server_serial_no	= getUrlVars()["server_serial_no"];
			var form_data = {
				server_serial_no: server_serial_no,
				item_type: "purge-leases"
			};
			$.ajax({
				type: "POST",
				url: "fm-modules/facileManager/ajax/processPost.php",
				data: form_data,
				success: function(response)
				{
					if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
						doLogout();
						return false;
					} else {
						if (response.length > 0) {
							$("#manage_item_contents").html(response);
						} else {
							window.location = response;
						}
					}
				}
			});
		}
		
		return false;
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
		url: "fm-modules/' . $module_name . '/ajax/getData.php",
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

// function loadServerLeases() {
// 	$("body").addClass("fm-noscroll");
// 	$("#manage_item").fadeIn(200);
// 	$("#manage_item_contents").html("<p>' . __('Pulling the leases from the server') . '... <i class=\"fa fa-spinner fa-spin\"></i></p>");

// 	var form_data = {
// 		get_leases: true,
// 		server_serial_no: getUrlVars()["server_serial_no"],
// 		is_ajax: 1
// 	};

// 	$.ajax({
// 		type: "POST",
// 		url: "fm-modules/facileManager/ajax/getData.php",
// 		data: form_data,
// 		success: function(response)
// 		{
// 			$("body").removeClass("fm-noscroll");
			
// 			if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
// 				doLogout();
// 				return false;
// 			}

// 			if (response.toLowerCase().indexOf("no records") > -1) {
// 				$("#manage_item").fadeOut(200);
// 				$("body").removeClass("fm-noscroll");
// 				return;
// 			}
			
// 			if (response.toLowerCase().indexOf("popup-header") > -1) {
// 				$("#manage_item_contents").html(response);
// 				return;
// 			}
			
// 			$("#lease_container").html(response);
// 			$("#manage_item").fadeOut(200);
// 			$("#lease_container select").select2({minimumResultsForSearch: 10});
// 			$("body").removeClass("fm-noscroll");
// 			return;
// 		}
// 	});

// 	return false;
// }

';
