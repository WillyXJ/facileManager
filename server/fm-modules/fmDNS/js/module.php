<?php
if (!defined('FM_NO_CHECKS')) define('FM_NO_CHECKS', true);
require_once('../../../fm-init.php');

$module_name = basename(dirname(dirname(__FILE__)));

header("Content-Type: text/javascript");

echo '
$(document).ready(function() {

	more_clicks = 0;

	if (onPage("zone-records.php")) {
		/* Select proper menu item */
		$zone_map = getUrlVars()["map"];
		if ($zone_map == "" || $zone_map == null) {
			$zone_map = "forward";
		}
		$menuitem = $("div#menu_subitems ul li a").filter(function(){
			return $(this).prop("href").indexOf("zones-" + $zone_map + ".php") != -1;
		});
		$menuitem.parent().addClass("current");

		/* Add body class */
		$("#plus").addClass("add-inline add-zone-records");

		$("select.record-type").select2({
			width: "80px"
		});
		
		/* Dynamic zone compare routine */
		var loadzone = getUrlVars()["load"];
		if (loadzone == "zone") {
			loadDynamicZone();
		}

		var KEYCODE_ENTER = 13;
		var KEYCODE_ESC = 27;
		
		$(document).keyup(function(e) {
			if (e.keyCode == KEYCODE_ESC) { 
				var $row_element = $(":focus").parents("tr");
				if ($row_element.hasClass("notice")) {
					$row_element.find(".inline-record-cancel").click();
				}
			} 
		});

		$(window).on("beforeunload", function() {
			var $unsaved_changes = $("#zone-records-form tr.record-changed");
			var $validate_changes = $("#zone-records-form tr.notice");
			if ($("#manage_item").is(":hidden") && ($unsaved_changes.length > 0 || $validate_changes.length > 0)) {
				return "You have unsaved changes.";
			}
		});

		/* Changing record values */
		$(".table-results-container .display_results").delegate("input:not([id^=\'record_delete_\']), select, textarea", "change input", function(e) {
			var $row_element = $(this).parents("tr");

			$row_element.not(".new-record").addClass("build");
			if (!$row_element.hasClass("attention")) {
				if ($(this).is(":checkbox") && $(this).attr("name").indexOf("record_skipped") > 0) {
					$row_element.removeClass("ok").addClass("build record-changed");
					$row_element.find(".inline-record-validate").hide();
					$row_element.find(".inline-record-actions").show();
				} else {
					$row_element.removeClass("record-changed ok").addClass("notice");
					$row_element.find(".inline-record-validate").show();
					$row_element.find(".inline-record-actions").show();
				}

				setSaveAllStatus();
			}
		});
	}

	if (onPage("zones-forward.php") || onPage("zones-reverse.php")) {
		$(function() {
			$("#pagination_container #domain_view, #pagination_container #domain_group").select2({
				containerCss: { "min-width": "180px", "max-width": "300px" },
				minimumResultsForSearch: 10
			});
		});
	}

	if (onPage("config-keys.php")) {
		$(function() {
			$("#pagination_container #domain_id").select2({
				containerCss: { "min-width": "200px" },
				minimumResultsForSearch: 10
			});
		});
	}

	/* Form adds */
	$("#plus.add-zone-records").unbind("click").bind("click", function() {
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
			url: "fm-modules/' . $module_name . '/ajax/addFormElements.php",
			data: form_data,
			success: function(response)
			{
				if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
					doLogout();
					return false;
				}
				if (response.length > 0) {
					if ($("table.display_results tbody tr").length <= 0) {
						$("table.display_results tbody").html(response);
					} else {
						$("table.display_results tbody tr:first").before(response);
					}
					$("table.display_results tbody tr:first div.inline-record-actions").show();
					$("table.display_results tbody tr:first input:text").first().focus();
					$("select").select2({
						minimumResultsForSearch: 10
					});
					$("select.record-type").select2({
						width: "80px"
					});
					more_clicks = more_clicks + 1;

					setValidateAllStatus();
				}
			}
		});

		return false;
	});

	/* New record type select change */
	$("#zone-records-form").delegate("select.record-type", "change", function() {
		var $this 		 = $(this);
		var $row_element = $(this).parents("tr");

		var form_data = {
			action: "get-record-value-form",
			domain_id: getUrlVars()["domain_id"],
			id_index: $this.attr("name"),
			record_type: $this.val(),
			page_record_type: getUrlVars()["record_type"],
			is_ajax: 1
		};

		$.ajax({
			type: "POST",
			url: "fm-modules/' . $module_name . '/ajax/addFormElements.php",
			data: form_data,
			success: function(response)
			{
				if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
					doLogout();
					return false;
				}
				$row_element.find("div.record-value-group").html(response);

				$("select").select2({
					minimumResultsForSearch: 10
				});
				$("select.record-type").select2({
					width: "80px"
				});
			}
		});

		return false;
	});

	/* Cancel record changes */
	$("#zone-records-form").delegate(".inline-record-cancel", "click tap", function() {
		var $row_element = $(this).parents("tr");
		if ($row_element.hasClass("new-record")) {
			$row_element.css({"background-color":"#D98085"});
			$row_element.fadeOut("slow", function() {
				$row_element.remove();
			});
		} else {
			/* Revert changes */
			$(this).parent().parent().find("input[type=checkbox]").prop("checked", false);

			var $this_row_inputs = $(this).parents("tr").find("input, select");
			$this_row_inputs.each(function() {
				if ($(this).is("select")) {
					$(this).val($(this).find("option[selected]").val());
					$(this).trigger("change");
				} else if ($(this).is("input[type=\"checkbox\"]")) {
					$(this).prop("checked", this.defaultChecked);
				} else {
					$(this).val(this.defaultValue);
				}
				$(this).removeClass("validate-error");
			});
			$row_element.removeClass("record-changed build attention notice");
			$(this).parent().hide();
			$row_element.find("input[id^=\'record_delete_\']").parent().show();
		}

		setTimeout(function() {
			setSaveAllStatus();
			if ($(".save-record-submit").hasClass("disabled") && $(".validate-all-records").hasClass("disabled")) {
				$("span.pending-changes").fadeOut(200);
			}
		}, 1000);
	});

	/* Validate the record and flag for saving */
	$("#zone-records-form").delegate(".inline-record-validate", "click tap", function(e) {
		e.preventDefault();
		if ($(this).checkRequiredFields("#zone-records-form") === false) {
			return false;
		}
		var $this = $(this);
		var $row_element = $(this).parents("tr");
		if (!$row_element.length) {
			var $row_element = $("#zone-records-form");
		}

		/** (un)check append box */
		var $record_value = $row_element.find("input[name*=\'record_value\']");
		var $record_append = $row_element.find("input[name*=\'record_append\']");
		if ($record_value.val() && $record_append.val()) {
			if ($record_value.val().substr($record_value.val().length - 1) == ".") {
				$record_append.prop("checked", false);
			}
		}

		/** Get element changes */
		var addl_form_data = {
			action: "validate-record-updates",
			is_ajax: 1
		};
		var uri_params = {"uri_params":getUrlVars()};
		var form_data = $row_element.find("input, select, textarea").serialize() + "&" + $.param(uri_params) + "&" + $.param(addl_form_data);

		$.ajax({
			type: "POST",
			url: "fm-modules/' . $module_name . '/ajax/processRecords.php",
			data: form_data,
			success: function(response)
			{
				if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
					doLogout();
					return false;
				}
				if ($.isArray(response)) {
					/* Set the auto-corrected value */
					$.each(response[0], function(key, value) {
						$row_element.find("input[name*=" + key + "][type!=\"checkbox\"]").val(value);
					});

					$row_element.find(".validate-error").removeClass("validate-error");
					$row_element.find(".validate-error-message").remove();

					/* Highlight any errors */
					if ("errors" in response[1]) {
						$.each(response[1]["errors"], function(key, value) {
							$element = $row_element.find("input[name*=" + key + "]");
							$element.addClass("validate-error");
							$element.after(" <a href=\"#\" class=\"validate-error-message tooltip-bottom\" data-tooltip=\"" + value + "\"><i class=\"fa fa-exclamation-triangle notice\" aria-hidden=\"true\"></i></a>");
						});
					} else {
						$row_element.removeClass("notice");
						$row_element.addClass("record-changed");
						if ($row_element.hasClass("new-record")) {
							$row_element.addClass("ok");
						}
						$this.hide();

						setSaveAllStatus();
					}
				} else if (response == "Success") {
					if (!$(".submit-success").length) {
						$this.after("<span class=\"submit-success\"><i class=\"fa fa-check\" aria-hidden=\"true\"></i></span>");
						$(".submit-success").delay(2000).fadeOut(200, function() {
							$(".submit-success").remove();
						});
					}
				} else if (response.indexOf("popup_response") >= 0) {
					$("body").addClass("fm-noscroll");
					$("#manage_item").fadeIn(200);
					$("#manage_item_contents").html(response);
				}
			}
		});
	});

	/* Validate all records and flag for saving */
	$(".validate-all-records").on("click tap", function() {
		$("#zone-records-form tr.notice").each(function() {
			$(this).find(".inline-record-validate").click();
		});

		setValidateAllStatus();
	});

	$(".save-record-submit").on("click tap", function(e) {
		e.preventDefault();
		var $unsaved_changes = $(".inline-record-validate").filter(":visible");
		if ($unsaved_changes.length > 0) {
			$("#manage_item").fadeIn(200);
			$("#manage_item_contents").html("' . addslashes(str_replace("\n", '', sprintf('%s<p>%s</p>%s',
				buildPopup('header', _('Error')),
				__('There are pending changes still to validate.'),
				buildPopup('footer', _('OK'), array('cancel_button' => 'cancel'))))) . '");
			$unsaved_changes.first().parents("tr").find("input").first().focus();
		} else if ($unsaved_changes.length == 0 && $(".display_results .build").filter(":visible").length == 0) {
		 	setSaveAllStatus();
			return;
		} else {
			$("body").addClass("fm-noscroll");
			$("#manage_item").fadeIn(200);
			$("#manage_item_contents").html("<p>' . _('Processing...please wait.') . ' <i class=\"fa fa-spinner fa-spin\"></i></p>");

			/** Get element changes */
			var addl_form_data = {
				action: "process-record-updates",
				is_ajax: 1
			};
			var uri_params = {"uri_params":getUrlVars()};
			var form_data = $("#zone-records-form tr.record-changed input, #zone-records-form tr.record-changed select, #zone-records-form tr.record-changed textarea, #zone-records-form.CUSTOM").serialize() + "&" + $.param(uri_params) + "&" + $.param(addl_form_data);
	
			/** Update the database */
			var $this				= $(this);
	
			$.ajax({
				type: "POST",
				url: "fm-modules/' . $module_name . '/ajax/processRecords.php",
				data: form_data,
				success: function(response)
				{
					if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
						doLogout();
						return false;
					}
					if (response != "Success" && !$.isNumeric(response)) {
						$("#manage_item").fadeOut(200);
						$("body").removeClass("fm-noscroll");

						$("#response").html("<p>" + response + "</p>");

						/* Popup response more link */
						$("#response").delegate("a.more", "click tap", function(e1) {
							e1.preventDefault();
							error_div = $("#response div#error")
							if (error_div.is(":visible")) {
								error_div.hide();
								$(this).text("' . _('more') . '");
							} else {
								error_div.show();
								$(this).text("' . _('less') . '");
							}
						});
						$("#response").delegate("#response_close i.close", "click tap", function(e2) {
							e2.preventDefault();
							$("#response").fadeOut(200, function() {
								$("#response").html();
							});
						});
					
						$("#response").fadeIn(200);

						if (response.indexOf("a class=\"more\"") <= 0) {
							$("#response").delay(2000).fadeOut(200, function() {
								$("#response").html();
							});
						}
					} else {
						setTimeout(function(){
							$("#manage_item").fadeOut(200);
							$("body").removeClass("fm-noscroll");
							location.reload();
						}, 1200);
					}
				}
			});
		}
	});

	/* Zone reload button */
	$("#zones").delegate("form", "click tap", function(e) {
		var $this 	= $(this);
		domain_id	= $this.attr("id");

		$(this).addClass("fa-spin");
		$("body").addClass("fm-noscroll");
		$("#manage_item").fadeIn(200);
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
				if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
					doLogout();
					return false;
				}
				$("#manage_item_contents").html(response);
				$this.removeClass("fa-spin");

				if (response.toLowerCase().indexOf("' . _('failed') . '") == -1 && response.toLowerCase().indexOf("' . __('you are not authorized') . '") == -1) {
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

		$("body").addClass("fm-noscroll");
		$("#manage_item").fadeIn(200);
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
				if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
					doLogout();
					return false;
				}
				$("#manage_item_contents").html(response);

				if (response.toLowerCase().indexOf("' . _('failed') . '") == -1 && response.toLowerCase().indexOf("' . __('you are not authorized') . '") == -1) {
					$("#response").delay(3000).fadeTo(200, 0.00, function() {
						$("#response").slideUp(400);
					});
				}
			}
		});

		return false;
	});

	/* Add clone or acl element */
	$("#zones,#acls,#masters").delegate("#plus_subelement", "click tap", function(e) {
		var $this 		= $(this);
		item_type		= $("#table_edits").attr("name");
		item_sub_type	= $this.attr("name");
		domain_clone_id	= $this.attr("rel");
		var queryParameters = {}, queryString = location.search.substring(1),
			re = /([^&=]+)=([^&]*)/g, m;
		while (m = re.exec(queryString)) {
			queryParameters[decodeURIComponent(m[1])] = decodeURIComponent(m[2]);
		}

		$("body").addClass("fm-noscroll");
		$("#manage_item").fadeIn(200);
		$(".popup-wait").show();
		$("#response").fadeOut();
		$this.parent().parent().removeClass("response");

		var form_data = {
			add_form: true,
			item_type: item_type,
			item_sub_type: item_sub_type,
			domain_clone_domain_id: domain_clone_id,
			acl_parent_id: domain_clone_id,
			parent_id: domain_clone_id,
			no_template: true,
			request_uri: queryParameters,
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
				$("#manage_item_contents").html(response);
				$(".datepicker").datepicker();
				$(".form-table input:text, .form-table select").first().focus();
				$(".popup-wait").hide();
			}
		});

		return false;
	});

	/* Zone subelement deletes */
	$("#table_edits").delegate("i.subelement_remove", "click tap", function(e) {
		var $this 		= $(this);
		var $subelement		= $this.parent().parent().attr("class");
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
					if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
						doLogout();
						return false;
					} else if (response == "' . _('Success') . '") {
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
						if (response.toLowerCase().indexOf("response_close") == -1) {
							$("#response").delay(3000).fadeTo(200, 0.00, function() {
								$("#response").slideUp(400);
							});
						}
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

		$("body").addClass("fm-noscroll");
		$("#manage_item").fadeIn(200);
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
				if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
					doLogout();
					return false;
				}
				$("#manage_item_contents").html(response);
				$(".datepicker").datepicker();
				$(".form-table input, .form-table select").first().focus();
				$(".popup-wait").hide();
			}
		});

		return false;
	});
	
	/* View Zone DNSSEC DS RRset */
	$("#table_edits").delegate(".tooltip-copy", "click tap", function(e) {
		copyText = $(this).find("textarea").select();
		
		document.execCommand("copy");
		
		$(this).find("p").html("' . __('Copied to clipboard') . '");
		
		return false;
	});
	
	/* Automatically select to set/update PTR */
	$(".table-results-container .display_results").delegate("input[name*=\'record_name\'], input[name*=\'record_value\']", "change input", function(e) {
		var $checkbox = $(this).parents("tr").find("input[name*=\'\[PTR\]\']");

		if ($checkbox.parents("label").text() == "' . __('Update PTR') . '") {
			$checkbox.prop("checked", true);
		}
	});

	/* Record delete checkbox */
	$(".display_results").delegate("input[id^=\'record_delete_\']", "click tap", function(e) {
		var $row_element = $(this).parents("tr");

		if ($(this).is(":checked")) {
			$row_element.removeClass("ok").addClass("build attention record-changed");
			$(this).parent().hide();
			$row_element.find(".inline-record-validate").hide();
			$row_element.find(".inline-record-actions").show();

			setSaveAllStatus();
		} else {
			$row_element.removeClass("attention record-changed");
			$row_element.find(".inline-record-cancel").click();
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
			$("#dynamic_updates").slideUp();
			$("#enable_dnssec").slideUp();
			$("#define_redirect_url").slideUp();
		} else if ($(this).val() == "secondary" || $(this).val() == "stub") {
			$("#define_forwarders").slideUp();
			$("#define_masters").show("slow");
			$("#define_soa").slideUp();
			$("#dynamic_updates").slideUp();
			$("#enable_dnssec").slideUp();
			$("#define_redirect_url").slideUp();
		} else if ($(this).val() == "primary" || $(this).val() == "url-redirect") {
			$("#define_forwarders").slideUp();
			$("#define_masters").slideUp();
			$("#define_soa").show("slow");
			if ($(this).val() == "primary") {
				$("#dynamic_updates").show("slow");
				$("#define_redirect_url").slideUp();
			} else {
				$("#dynamic_updates").slideUp();
				$("#define_redirect_url").show("slow");
			}
			$("#enable_dnssec").show("slow");
		} else {
			$("#define_forwarders").slideUp();
			$("#define_masters").slideUp();
			$("#define_soa").slideUp();
			$("#dynamic_updates").slideUp();
			$("#enable_dnssec").slideUp();
			$("#define_redirect_url").slideUp();
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
			url: "fm-modules/' . $module_name . '/ajax/getData.php",
			data: form_data,
			success: function(response)
			{
				$("#domain_clone_domain_id").html(response);
			}
		});

		return false;
	});
	
	$("#manage_item_contents").delegate("#domain_dynamic", "click", function(e) {
		if ($(this).is(":checked") && $("#domain_dnssec").is(":checked")) {
			$("#domain_dnssec").click();
		}
	});

	$("#manage_item_contents").delegate("#domain_dnssec", "click", function(e) {
		if ($(this).is(":checked")) {
			$("#dnssec_option").show("slow");
			$("#domain_dynamic").prop("checked", false);
			if ($("#domain_dnssec_generate_ds").is(":checked")) {
				$("#dnssec_ds_option").show("slow");
			}
		} else {
			$("#dnssec_option").slideUp();
			$("#dnssec_ds_option").slideUp();
		}
	});

	$("#manage_item_contents").delegate("#domain_dnssec_generate_ds", "click", function(e) {
		if ($(this).is(":checked")) {
			$("#dnssec_ds_option").show("slow");
		} else {
			$("#dnssec_ds_option").slideUp();
		}
	});

	$("#manage_item_contents").delegate(".import_skip", "click", function(e) {
		if ($(this).is(":checked")) {
			$(this).parent().parent().parent().addClass("disabled");
			$(this).parent().nextAll().has(":checkbox").first().find(":checkbox").prop("checked", false).prop("disabled", true);
		} else {
			$(this).parent().parent().parent().removeClass("disabled");
			$(this).parent().nextAll().has(":checkbox").first().find(":checkbox").prop("disabled", false);
		}
	});

	$("#admin-tools-form").delegate("#zone_import_domain_list", "change", function(e) {
		if ($(this).val() == 0) {
			$("#import-records").val("' . __('Import Zones') . '");
		} else {
			$("#import-records").val("' . __('Import Records') . '");
		}
	});

	$("#manage_item_contents").delegate("#master_addresses", "change", function(e) {
		if ($(this).val().indexOf("master_") > -1) {
			$("#master_port").val("");
			$("#master_port, #master_key_id").attr("disabled", "disabled");
			$("#master_port, #master_key_id").addClass("disabled");
		} else {
			$("#master_port, #master_key_id").removeAttr("disabled");
			$("#master_port, #master_key_id").removeClass("disabled");
		}
	});

	$("#manage_item_contents").delegate("#server_type", "change", function(e) {
		if ($("#server_type").val() == "remote") {
			$(".local_server_options").slideUp();
			$("#alternative_help").slideUp();
		} else if ($("#server_type").val() == "url-only") {
			$(".local_server_options").slideUp();
			$(".no_url_only_options").slideUp();
			$(".url_only_options").show("slow");
			$("#alternative_help").slideUp();
		} else {
			$(".local_server_options").show("slow");
			$(".url_only_options").show("slow");
			$(".no_url_only_options").show("slow");
			$("#alternative_help").show("slow");
		}
	});

	$("#enable_url_rr").click(function() {
		if ($(this).is(":checked")) {
			$("#enable_url_rr_options").show("slow");
		} else {
			$("#enable_url_rr_options").slideUp();
		}
	});

	$("#manage_item_contents").delegate("#domain_id", "change", function(e) {
		if ($(this).val() == "0") {
			$(".global_option").show("slow");
			$(".domain_option").slideUp();
		} else {
			$(".domain_option").show("slow");
			$(".global_option").slideUp();
		}
	});

	$("#manage_item_contents").delegate("#policy", "change", function(e) {
		if ($(this).val() == "cname") {
			$("#cname_option").show("slow");
		} else {
			$("#cname_option").slideUp();
		}
	});

});

function displayOptionPlaceholder(option_value) {
	var option_name = document.getElementById("cfg_name").value;
	var server_serial_no	= getUrlVars()["server_serial_no"];
	var view_id = getUrlVars()["view_id"];
	var domain_id = getUrlVars()["domain_id"];
	var cfg_type = document.getElementsByName("cfg_type")[0].value;
	var cfg_id = document.getElementsByName("cfg_id")[0].value;

	var form_data = {
		get_option_placeholder: true,
		option_name: option_name,
		option_value: option_value,
		server_serial_no: server_serial_no,
		view_id: view_id,
		domain_id: domain_id,
		cfg_type: cfg_type,
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
				$("#manage #cfg_data").select2({
					width: "100px",
					minimumResultsForSearch: 10
				});
			}
		}
	});
}

function loadDynamicZone() {
	$("body").addClass("fm-noscroll");
	$("#manage_item").fadeIn(200);
	$("#manage_item_contents").html("<p>' . __('Pulling the latest zone data from the server') . '... <i class=\"fa fa-spinner fa-spin\"></i></p>");

	var form_data = {
		get_dynamic_zone_data: true,
		domain_id: getUrlVars()["domain_id"],
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

			if (response.toLowerCase().indexOf("no records") > -1) {
				$("#manage_item").fadeOut(200);
				$("body").removeClass("fm-noscroll");
				return;
			}
			
			$("#manage_item_contents").html(response);
		}
	});

	return false;
}

function validateTimeFormat(event, that) {
	// Allow: backspace, delete, tab, escape, and enter
	if (event.keyCode == 46 || event.keyCode == 8 || event.keyCode == 9 || event.keyCode == 27 || event.keyCode == 13 || 
		// Allow: Ctrl+A
		(event.keyCode == 65 && event.ctrlKey === true) || 
		// Allow: home, end, left, right
		(event.keyCode >= 35 && event.keyCode <= 39)) {
			// let it happen, do not do anything
			return;
	}
	// Ensure that it is a s, m, h, d, w, or y
	else if (event.keyCode == 83 || event.keyCode == 77 || event.keyCode == 72 || event.keyCode == 68 || event.keyCode == 87 || event.keyCode == 89) {
		switch (event.keyCode) {
			case 83:
				if (that.value.match(/(s|[a-z]$)/gi)) {
					event.preventDefault();
				}
				break;
			case 77:
				if (that.value.match(/(m|[a-z]$)/gi)) {
					event.preventDefault();
				}
				break;
			case 72:
				if (that.value.match(/(h|[a-z]$)/gi)) {
					event.preventDefault();
				}
				break;
			case 68:
				if (that.value.match(/(d|[a-z]$)/gi)) {
					event.preventDefault();
				}
				break;
			case 87:
				if (that.value.match(/(w|[a-z]$)/gi)) {
					event.preventDefault();
				}
				break;
			case 89:
				if (that.value.match(/(y|[a-z]$)/gi)) {
					event.preventDefault();
				}
				break;
			}
		return;
	}
	// Ensure that it is a number and stop the keypress
	else {
		if (event.shiftKey || (event.keyCode < 48 || event.keyCode > 57) && (event.keyCode < 96 || event.keyCode > 105 )) {
			event.preventDefault();
		}
	}
}

function setSaveAllStatus() {
	/* Disable save all button if nothing is present to save */
	setValidateAllStatus();
	var $unsaved_changes = $("#zone-records-form tr.record-changed");
	if ($unsaved_changes.length <= 0) {
		$(".save-record-submit").addClass("disabled").attr("disabled", true);
	} else {
		$(".save-record-submit").removeClass("disabled").attr("disabled", false);
		$("span.pending-changes").fadeIn(200);
	}
}

function setValidateAllStatus() {
	/* Disable validate all button if nothing is present to validate */
	var $validate_changes = $("#zone-records-form tr.notice");
	if ($validate_changes.length <= 0) {
		$(".validate-all-records").addClass("disabled").attr("disabled", true);
	} else {
		$(".validate-all-records").removeClass("disabled").attr("disabled", false);
		$("span.pending-changes").fadeIn(200);
	}
}
';
