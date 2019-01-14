<?php
if (!defined('FM_NO_CHECKS')) define('FM_NO_CHECKS', true);
require_once('../../../fm-init.php');

header("Content-Type: text/javascript");

echo '
$(document).ready(function() {
	
	/** Dashboard auto-reload */
	if (onPage("index.php") || onPage("")) {
		$(function loadDashboard() {
			var form_data = {
				action: "get-dashboard"
			};

			$.ajax({
				type: "GET",
				url: "fm-modules/facileManager/ajax/getData.php",
				timeout: 2000,
				data: form_data,
				success: function(response) {
					if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
						doLogout();
						return false;
					}

					$("div.fm-table").html("<div>" + response + "</div>");
					window.setTimeout(loadDashboard, 20000 + Math.random() * (60 - 20) * 1000);
				}
			});
		});
	}
	
	/* Block client */
	$("#body_container").delegate("#block-wifi-client", "click", function(e) {
		var $this 		= $(this);
		var $row_id		= $this.parent().parent();
		item_id				= $row_id.attr("id");
		ssid			= $row_id.attr("rel");
		item_type		= $("#table_edits").attr("name");
		mac	= $this.attr("rel");

		var form_data = {
			item_id: item_id,
			ssid: ssid,
			item_type: item_type,
			action: "block-wifi-client",
			is_ajax: 1
		};

		if (confirm("' . _('Are you sure you want to block this client?') . ' ("+ item_id +")")) {
			var orig_html = $(this).parent().html();
			$(this).addClass("hidden");
			$(this).parent().append("<i class=\"fa fa-circle-o-notch fa-spin grey\" aria-hidden=\"true\"></i>");
			$.ajax({
				type: "POST",
				url: "fm-modules/facileManager/ajax/processPost.php",
				data: form_data,
				success: function(response) {
					if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
						doLogout();
						return false;
					} else if (response == "' . _('Success') . '") {
						$row_id.css({"background-color":"#D98085"});
						$row_id.fadeOut("slow", function() {
							$row_id.remove();
						});
					} else {
						$this.parent().html(orig_html);
						var eachLine = response.split("\n");
						if (eachLine.length <= 2) {
							$("#response").html("<p class=\"error\">"+response+"</p>");
							$("#response")
								.css("opacity", 0)
								.slideDown(400, function() {
									$("#response").animate(
										{ opacity: 1 },
										{ queue: false, duration: 200 }
									);
								});
							if (response.toLowerCase().indexOf("response_close") == -1) {
								$("#response").delay(3000).fadeTo(200, 0.00, function() {
									$("#response").slideUp(400);
								});
							}
						} else {
							$("#manage_item").fadeIn(200);
							$("#manage_item_contents").fadeIn(200);
							$("#manage_item_contents").html(response);
							if ($("#manage_item_contents").width() >= 700) {
								$("#manage_item_contents").addClass("wide");
							}
						}
					}
				}
			});
		}

		return false;
	});

	if (onPage("config-servers.php")) {
		$(function checkAPStatus() {
			var item_type = getUrlVars()["type"];
			if (item_type == "servers" || item_type == undefined) {
				$(function() {
					$("#table_edits tr").each(function() {
						ap_id = this.id;
						if (ap_id && !$(this).hasClass("disabled")) {
							loadAPStatus(ap_id);
						}
					});
				});
				window.setTimeout(checkAPStatus, 10000);
			}
		});
	}

	if (onPage("config-acls.php")) {
		$(function() {
			$("#pagination_container #wlan_ids").select2({
				containerCss: { "min-width": "180px", "max-width": "300px" },
				minimumResultsForSearch: 10
			});
		});
	}

	$("#manage_item_contents").delegate("#hw_mode", "change", function(e) {
		if (jQuery.inArray($(this).val(), ["a", "b", "g"]) !== -1) {
			$("#hw_mode_option").show("slow");
			if ($(this).val() == "a") {
				$("#ieee80211n_entry").show("slow");
				$("#ieee80211ac_entry").show("slow");
				$("#wmm_entry").show("slow");
				$("#preamble_entry").hide();
			} else if ($(this).val() == "b") {
				$("#preamble_entry").show("slow");
				$("#ieee80211ac_entry").hide();
				$("#ieee80211n_entry").hide();
				$("#wmm_entry").hide();
			} else {
				$("#preamble_entry").hide();
				$("#ieee80211ac_entry").hide();
				$("#ieee80211n_entry").show("slow");
				$("#wmm_entry").show("slow");
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

function loadAPStatus(APid) {
	var form_data = {
		get_ap_status: true,
		ap_id: APid,
		is_ajax: 1
	};

	$.ajax({
		type: "POST",
		url: "fm-modules/facileManager/ajax/getData.php",
		data: form_data,
		success: function(response)
		{
			if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
				doLogout();
				return false;
			}
			
			$("#table_edits tr#" + APid + " > td#ap_status").html(response);
		}
	});
}

';
?>