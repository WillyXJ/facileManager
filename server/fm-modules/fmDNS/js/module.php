<?php
if (!defined('FM_NO_CHECKS')) define('FM_NO_CHECKS', true);
require_once('../../../fm-init.php');

echo '
$(document).ready(function() {
	
	more_clicks = 0;

	/* Add body class */
	if (onPage("zone-records.php")) {
		$("body").addClass("fm-noscroll");
	}

	if (onPage("zones-forward.php") || onPage("zones-reverse.php")) {
		$(function() {
			$("#pagination_container #domain_view").select2({
				containerCss: { "min-width": "200px" },
				minimumResultsForSearch: 10
			});
		});
	}

	/* Zone reload button */
	$("#zones").delegate("form", "click tap", function(e) {
		var $this 	= $(this);
		domain_id	= $this.attr("id");

		$("#manage_item").fadeIn(200);
		$("#manage_item_contents").fadeIn(200);
		$("#manage_item_contents").html("<p>' . __('Processing Reload') . '... <i class=\"fa fa-spinner fa-spin\"></i></p>");

		var form_data = {
			domain_id: domain_id,
			is_ajax: 1
		};

		$.ajax({
			type: "POST",
			url: "fm-modules/facileManager/ajax/processReload.php",
			data: form_data,
			success: function(response)
			{
				$("#manage_item_contents").html(response);

				if (response.toLowerCase().indexOf("' . __('failed') . '") == -1 && response.toLowerCase().indexOf("' . __('you are not authorized') . '") == -1) {
					$this.fadeOut(400);
					$this.parent().parent().removeClass("build");
					$this.parent().parent().find("input:checkbox:first").remove();
				}
			}
		});

		return false;
	});

	/* Zone reload link */
	$("a.zone_reload").click(function(e) {
		var $this 	= $(this);
		domain_id	= $this.attr("id");

		$("#manage_item").fadeIn(200);
		$("#manage_item_contents").fadeIn(200);
		$("#manage_item_contents").html("<p>' . __('Processing Reload') . '... <i class=\"fa fa-spinner fa-spin\"></i></p>");

		var form_data = {
			domain_id: domain_id,
			is_ajax: 1
		};

		$.ajax({
			type: "POST",
			url: "fm-modules/facileManager/ajax/processReload.php",
			data: form_data,
			success: function(response)
			{
				$("#manage_item_contents").html(response);

				if (response.toLowerCase().indexOf("' . __('failed') . '") == -1 && response.toLowerCase().indexOf("' . __('you are not authorized') . '") == -1) {
					$("#response").delay(3000).fadeTo(200, 0.00, function() {
						$("#response").slideUp(400);
					});
				}
			}
		});

		return false;
	});

	/* Add clone or acl element */
	$("#zones,#acls").delegate("#plus", "click tap", function(e) {
		var $this 		= $(this);
		item_type		= $("#table_edits").attr("name");
		item_sub_type	= $this.attr("name");
		domain_clone_id	= $this.attr("rel");
		var queryParameters = {}, queryString = location.search.substring(1),
			re = /([^&=]+)=([^&]*)/g, m;
		while (m = re.exec(queryString)) {
			queryParameters[decodeURIComponent(m[1])] = decodeURIComponent(m[2]);
		}

		$("#manage_item").fadeIn(200);
		$("#manage_item_contents").fadeIn(200);
		$(".popup-wait").show();
		$("#response").fadeOut();
		$this.parent().parent().removeClass("response");

		var form_data = {
			add_form: true,
			item_type: item_type,
			item_sub_type: item_sub_type,
			domain_clone_domain_id: domain_clone_id,
			acl_parent_id: domain_clone_id,
			no_template: true,
			request_uri: queryParameters,
			is_ajax: 1
		};

		$.ajax({
			type: "POST",
			url: "fm-modules/fmDNS/ajax/getData.php",
			data: form_data,
			success: function(response)
			{
				$("#manage_item_contents").html(response);
				if ($("#manage_item_contents").width() >= 700) {
					$("#manage_item_contents").addClass("wide");
				}
				$(".datepicker").datepicker();
				$(".form-table input:text, .form-table select").first().focus();
				$(".popup-wait").hide();
			}
		});

		return false;
	});

	/* Add more records */
	$("#add_records").click(function() {
		var $this 		= $(this);
		var item_id		= getUrlVars()["domain_id"];
		var item_type	= getUrlVars()["record_type"];

		var form_data = {
			domain_id: item_id,
			record_type: item_type,
			clicks: more_clicks,
			is_ajax: 1
		};

		$.ajax({
			type: "POST",
			url: "fm-modules/fmDNS/ajax/addFormElements.php",
			data: form_data,
			success: function(response)
			{
				$("#more_records").append(response);
				$("select").select2({minimumResultsForSearch: 10});
				more_clicks = more_clicks + 1;
			}
		});

		return false;
	});

	/* Zone subelement deletes */
	$("#table_edits").delegate("img.subelement_remove", "click tap", function(e) {
		var $this 		= $(this);
		var $subelement		= $this.parent().attr("class");
		item_type		= $("#table_edits").attr("name");
		item_id			= $this.attr("id");

		var form_data = {
			item_id: item_id,
			item_type: item_type,
			action: "delete",
			is_ajax: 1
		};

		if (confirm("' . __('Are you sure you want to delete this item?') . '")) {
			$.ajax({
				type: "POST",
				url: "fm-modules/facileManager/ajax/processPost.php",
				data: form_data,
				success: function(response)
				{
					if (response == "' . __('Success') . '") {
						$("."+$subelement).css({"background-color":"#D98085"});
						$("."+$subelement).fadeOut("slow", function() {
							$("."+$subelement).remove();
						});
					} else {
						$("#response").html("<p class=\"error\">"+response+"</p>");
						$("#response")
							.css("opacity", 0)
							.slideDown(400, function() {
								$("#response").animate(
									{ opacity: 1 },
									{ queue: false, duration: 200 }
								);
							});
						$("#response").delay(3000).fadeTo(200, 0.00, function() {
							$("#response").slideUp(400);
						});
					}
				}
			});
		}

		return false;
	});

	/* Zone clone edits */
	$("#table_edits").delegate("a.subelement_edit", "click tap", function(e) {
		var $this 		= $(this);
		item_id			= $this.attr("id");
		item_type		= $("#table_edits").attr("name");
		item_sub_type	= $this.attr("name");

		$("#manage_item").fadeIn(200);
		$("#manage_item_contents").fadeIn(200);
		$(".popup-wait").show();
		$("#response").fadeOut();
		$("#body_container").removeClass("response");

		var form_data = {
			item_id: item_id,
			item_type: item_type,
			item_sub_type: item_sub_type,
			is_ajax: 1
		};

		$.ajax({
			type: "POST",
			url: "fm-modules/facileManager/ajax/getData.php",
			data: form_data,
			success: function(response)
			{
				$("#manage_item_contents").html(response);
				if ($("#manage_item_contents").width() >= 700) {
					$("#manage_item_contents").addClass("wide");
				}
				$(".datepicker").datepicker();
				$(".form-table input, .form-table select").first().focus();
				$(".popup-wait").hide();
			}
		});

		return false;
	});

	$(".existing-container .display_results").delegate("input:not([id^=\'record_delete_\']), select", "change", function(e) {
		if ($(this).attr("type") == "checkbox") {
			$(this).parent().parent().parent().addClass("build");
		} else {
			$(this).parent().parent().addClass("build");
		}
	});

	/* Record delete checkbox */
	$(".display_results").delegate("input[id^=\'record_delete_\']", "click tap", function(e) {
		if ($(this).is(":checked")) {
			$(this).parent().parent().parent().addClass("attention");
		} else {
			$(this).parent().parent().parent().removeClass("attention");
		}
	});

	$("#manage_item_contents").delegate("#server_update_method", "change", function(e) {
		if ($(this).val() == "cron") {
			$("#server_update_port_option").slideUp();
		} else {
			$("#server_update_port_option").show("slow");
		}
	});

	$("#manage_item_contents").delegate("#cfg_destination", "change", function(e) {
		if ($(this).val() == "file") {
			$("#syslog_options").slideUp();
			$("#destination_option").show("slow");
		} else if ($(this).val() == "syslog") {
			$("#destination_option").slideUp();
			$("#syslog_options").show("slow");
		} else {
			$("#syslog_options").slideUp();
			$("#destination_option").slideUp();
		}
	});

	$("#body_container").delegate("#soa_template_chosen", "change", function(e) {
		if ($(this).val() == "0") {
			$("#custom-soa-form").show("slow");
		} else {
			$("#custom-soa-form").slideUp();
		}
	});

	if ($("#soa_template_chosen").val() !== undefined) {
		if ($("#soa_template_chosen").val() != 0) {
			$("#custom-soa-form").hide();
		}
	}

	$("#soa_create_template").click(function() {
		if ($(this).is(":checked")) {
			$("#soa_template_name").show("slow");
		} else {
			$("#soa_template_name").slideUp();
		}
	});

	$("#manage_item_contents").delegate("#domain_template_id", "change", function(e) {
		if ($(this).val() != "") {
			$(".zone-form > tbody > tr:not(.include-with-template, #domain_template_default)").slideUp();
		} else {
			$(".zone-form > tbody > tr:not(.include-with-template, #domain_template_default)").show("slow");
		}
	});

	$("#manage_item_contents").delegate("#domain_type", "change", function(e) {
		if ($(this).val() == "forward") {
			$("#define_forwarders").show("slow");
			$("#define_masters").slideUp();
			$("#define_soa").slideUp();
		} else if ($(this).val() == "slave" || $(this).val() == "stub") {
			$("#define_forwarders").slideUp();
			$("#define_masters").show("slow");
			$("#define_soa").slideUp();
		} else if ($(this).val() == "master") {
			$("#define_forwarders").slideUp();
			$("#define_masters").slideUp();
			$("#define_soa").show("slow");
		} else {
			$("#define_forwarders").slideUp();
			$("#define_masters").slideUp();
			$("#define_soa").slideUp();
		}
	});

	$("#manage_item_contents").delegate("#domain_clone_domain_id", "change", function(e) {
		if ($(this).val() == 0) {
			$("#define_soa").show("slow");
			$("#create_template").show("slow");
			$("#clone_override").slideUp();
		} else {
			$("#define_soa").slideUp();
			$("#create_template").slideUp();
			$("#clone_override").show("slow");
		}
	});

	$("#manage_item_contents").delegate("#domain_mapping", "change", function(e) {
		var form_data = {
			get_available_clones: true,
			map: $(this).val(),
			is_ajax: 1
		};

		$.ajax({
			type: "POST",
			url: "fm-modules/fmDNS/ajax/getData.php",
			data: form_data,
			success: function(response)
			{
				$("#domain_clone_domain_id").html(response);
			}
		});

		return false;
	});
	
});


function displayOptionPlaceholder(option_value) {
	var option_name = document.getElementById("cfg_name").value;
	var server_serial_no	= getUrlVars()["server_serial_no"];
	var view_id = getUrlVars()["view_id"];

	var form_data = {
		get_option_placeholder: true,
		option_name: option_name,
		option_value: option_value,
		server_serial_no: server_serial_no,
		view_id: view_id,
		is_ajax: 1
	};

	$.ajax({
		type: "POST",
		url: "fm-modules/fmDNS/ajax/getData.php",
		data: form_data,
		success: function(response)
		{
			$(".value_placeholder").html(response);
			if (response.toLowerCase().indexOf("address_match_element") == -1) {
				$("#manage #cfg_data").select2({
					width: "100px",
					minimumResultsForSearch: 10
				});
			}
		}
	});
}
';
?>