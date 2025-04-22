<?php
if (!defined('FM_NO_CHECKS')) define('FM_NO_CHECKS', true);
require_once('../../../fm-init.php');

if (isset($__FM_CONFIG)) {
	header("Content-Type: text/javascript");

	echo '$(document).ready(function() {

	// Set theme mode from System
	$.fn.setThemeMode = function() {
		if ($("html").hasClass("System") && window.matchMedia) {
			var match = window.matchMedia("(prefers-color-scheme: dark)");
			$("html").removeClass("Light Dark");
			if (match.matches === true) {
				$("html").addClass("Dark");
			} else {
				$("html").addClass("Light");
			}
		}
	}

	var KEYCODE_ENTER = 13;
	var KEYCODE_ESC = 27;
	
	$(document).keyup(function(e) {
		if (e.keyCode == KEYCODE_ESC) { $("#cancel_button").click(); }
		if (e.keyCode == KEYCODE_ENTER && $(":focus").is("input[type=text], input[type=password]")) { $("#primary_button").click(); }
	});

	$(function() {
		$(this).setThemeMode();
		$(".datepicker").datepicker();
		$("select:not([class])").select2({minimumResultsForSearch: 10});
		$("#bulk_action").select2({minimumResultsForSearch: -1, width: "120px", allowClear: true});
		$("#server_serial_no").select2({minimumResultsForSearch: 10, containerCss: { "min-width": "130px", "text-align": "left" }});
		$("#server_serial_no_extended").select2({minimumResultsForSearch: 10, containerCss: { "min-width": "230px", "text-align": "left", allowClear: true }});
		$("#settings select").select2({
			width: "200px",
			minimumResultsForSearch: 10
		});
		$("select.allow-clear").select2({
			width: "200px",
			minimumResultsForSearch: 10,
			allowClear: true
		});
		$("#admin-tools-select select").select2({
			containerCss: { "min-width": "300px" },
			minimumResultsForSearch: 10,
			allowClear: true
		});
		$(".log_search_form select").select2({
			containerCss: { "min-width": "165px" },
			minimumResultsForSearch: 10
		});
		$(function() {
			$( "#manage_item_contents" ).draggable({
				handle: "div.popup-header"
			});
		});
		if ($("table.sortable th.header-sorted").length == 0) {
			$("table.sortable th").not(".header-nosort").first().addClass("header-sorted");
		}
		$("#login_form input").change();
		$("form .required").closest("tr").children("th").children("label").addClass("required");
	});
	
	$(function displayHideProcessAll() {
		if ($("#tophead").is(":visible")) {
			var form_data = {
				action: "display-process-all"
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
					if (response == 0 || ! $.isNumeric(response)) {
						$(".process_all_updates").parent().fadeOut(400);
					} else {
						$("#tophead span.update_count").html(response);
						$(".process_all_updates").parent().fadeIn(400);
					}
					window.setTimeout(displayHideProcessAll, 45000);
				},
				error: function (XMLHttpRequest, textStatus, errorThrown) {
					window.setTimeout(displayHideProcessAll, 60000);
				}
			});
		}
	});
	
	if (!onPage("admin-logs.php")) {
		$("input:text, input:password, select, textarea").first().focus();
	}
	
	// Everything we need for scrolling up and down.
	$(window).scroll( function(){
		if($(window).scrollTop() > 150) $("#scroll-to-top").addClass("displayed");
		else $("#scroll-to-top").removeClass("displayed");
	} );
	
	$(".overflow-container").scroll( function(){
		if($(".overflow-container").scrollTop() > 150) $("#scroll-to-top").addClass("displayed");
		else $("#scroll-to-top").removeClass("displayed");
	} );
	
	$("#scroll-to-top").click( function(){
		$("html, body").animate( { scrollTop: "0px" } );
		return false;
	} );
	
	$("#scroll-to-top").click( function(){
		$(".overflow-container").animate( { scrollTop: "0px" } );
		return false;
	} );
	
	$("#login_form input").on("change", function() {
		if ($("#login_message_accept").length) {
			var button = document.getElementById("loginbtn");
			if ($("#login_message_accept").is(":checked") && $("#username").length && $("#password").length) {
				button.disabled = false;
			} else {
				button.disabled = true;
			}
		}
	});

	$("#loginbtn").click(function() {
		if ($("#login_message_accept").length) {
			if ($("#login_message_accept").prop("checked") != true) {
				$("#login_message_accept").parent().addClass("failed");
				return false;
			}
		}
	
		var action = $("#loginform").attr("action");
		var form_data = {
			username: $("#username").val(),
			password: $("#password").val(),
			login_message_accept: $("#login_message_accept").is(":checked"),
			is_ajax: 1
		};
		
		$.ajax({
			type: "POST",
			url: action,
			data: form_data,
			success: function(response)
			{
				$("#password").val("");
				if(response == "failed") {
					if ($("#username").val() == "") {
						$("#username").focus();
					} else {
						$("#password").focus();
					}
					$("#login_form table").effect("shake");	
				} else if (response.indexOf("failed") >=0) {
					$("#message").prepend(response);
				} else {
					window.location = response;
				}
			}
		});
		
		return false;
	});
	
	$("#forgotbtn").click(function() {
	
		$("#message").html("<p class=\"success\">' . _('Processing...please wait.') . ' <i class=\"fa fa-spinner fa-spin\"></i></p>");
		
		var action = $("#forgotpwd").attr("action");
		var form_data = {
			user_login: $("#user_login").val(),
			is_ajax: 1
		};
		
		$("#user_login").val("");	
		$.ajax({
			type: "POST",
			url: action,
			data: form_data,
			success: function(response)
			{
				$("#message").html(response);
			}
		});
		
		return false;
	});
	
	$("a.click_once").one("click", function() {
		$(this).html("' . _('Processing...please wait.') . ' <i class=\"fa fa-spinner fa-spin\"></i>");
		$(this).click(function() {
			return false;
		});
	});
	
	/* Form adds */
	$("#plus:not(.add-inline)").click(function() {
		var $this 		= $(this);
		item_type		= $("#table_edits").attr("name");
		item_sub_type	= $this.attr("name");
		item_id			= $this.attr("rel");
		var server_serial_no	= getUrlVars()["server_serial_no"];
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
			item_id: item_id,
			server_serial_no: server_serial_no,
			request_uri: queryParameters,
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
				$(".form-table input:text, .form-table select").first().focus();
				$(".popup-wait").hide();
			}
		});

		return false;
	});

	/* Form edits */
	$("#table_edits").delegate("a.edit_form_link, a.copy_form_link", "click tap", function(e) {
		var $this 		= $(this);
		var $row_id		= $this.parent().parent();
		item_id			= $row_id.attr("id");
		item_type		= $("#table_edits").attr("name");
		item_sub_type	= $this.attr("name");
		var server_serial_no	= getUrlVars()["server_serial_no"];
		var view_id		= getUrlVars()["view_id"];
		var domain_id		= getUrlVars()["domain_id"];
		var queryParameters = {}, queryString = location.search.substring(1),
			re = /([^&=]+)=([^&]*)/g, m;
		while (m = re.exec(queryString)) {
			queryParameters[decodeURIComponent(m[1])] = decodeURIComponent(m[2]);
		}

		$("body").addClass("fm-noscroll");
		$("#manage_item").fadeIn(200);
		$(".popup-wait").show();
		$("#response").fadeOut();
		$row_id.parent().parent().parent().removeClass("response");
		
		var form_data = {
			item_id: item_id,
			item_type: item_type,
			item_sub_type: item_sub_type,
			server_serial_no: server_serial_no,
			view_id: view_id,
			domain_id: domain_id,
			request_uri: queryParameters,
			is_ajax: 1
		};

		if ($(this).hasClass("copy_form_link") == true) {
			form_data["add_form"] = true;
		}
		
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

	/* Form status changes */
    $("#table_edits").delegate("a.status_form_link", "click tap", function(e) {
        var $this 		= $(this);
        var $row_id		= $this.parent().parent();
        item_id			= $row_id.attr("id");
        item_status		= $this.attr("rel");
        item_type		= $("#table_edits").attr("name");
		item_build		= $("#table_edits").attr("rel");
        var url_var_type = getUrlVars()["type"];
        var server_serial_no	= getUrlVars()["server_serial_no"];

		var form_data = {
			item_id: item_id,
			item_type: item_type,
			item_status: item_status,
			url_var_type: url_var_type,
			server_serial_no: server_serial_no,
			action: "edit",
			is_ajax: 1
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
				} else if (response == "Success") {
					$row_id.removeClass("active disabled build");
					$row_id.addClass(item_status);
					if (item_status == "disabled") {
						$this.attr("rel", "active");
						$this.html("' . addslashes($__FM_CONFIG['icons']['enable']) . '");
						$this.parent().find("#build").hide();
					} else {
						$this.attr("rel", "disabled");
						$this.html("' . addslashes($__FM_CONFIG['icons']['disable']) . '");
						if (item_type == "servers" && item_build != "no-build") {
							if ($row_id.hasClass("attention") === false) {
								$row_id.addClass("build");
								$this.parent().find("a.edit_form_link").first().before("' . addslashes($__FM_CONFIG['module']['icons']['build']) . '");
							}
						}
					}
				} else {
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
						$("#manage_item_contents").html(response);
					}
				}
			}
		});
		
		return false;
    });

	/* Form deletes */
    $("#table_edits").delegate("a.delete", "click tap", function(e) {
        var $this 		= $(this);
        var $row_id		= $this.parent().parent();
        item_id			= $row_id.attr("id");
        item_name		= $row_id.attr("name");
        item_type		= $("#table_edits").attr("name");
        item_sub_type	= $this.attr("name");
        var log_type	= getUrlVars()["type"];
        var server_serial_no	= getUrlVars()["server_serial_no"];

		var form_data = {
			item_id: item_id,
			item_type: item_type,
			item_sub_type: item_sub_type,
			log_type: log_type,
			server_serial_no: server_serial_no,
			action: "delete",
			is_ajax: 1
		};

		if (confirm("' . _('Are you sure you want to delete this item?') . ' ("+ item_name +")")) {
			$this.html("<i class=\"fa fa-spinner fa-spin\"></i>");
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
						$row_id.css({"background-color":"#D98085"});
						$row_id.fadeOut("slow", function() {
							$row_id.remove();
						});
					} else {
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
							$("#manage_item_contents").html(response);
						}
					}
				}
			});
		}
		
		return false;
    });

	/* Change the server update method */
	$("#manage_item_contents").delegate("#server_update_method", "change", function(e) {
		if ($(this).val() == "cron") {
			$("#server_update_port_option").slideUp();
		} else {
			$("#server_update_port_option").show("slow");
		}
	});

	/* Form submits */
	$("#manage_item_contents").delegate("form", "submit", function() {
		$("#primary_button").parent().html("' . _('Processing...please wait.') . ' <i class=\"fa fa-spinner fa-spin\"></i>");
	});
	
    /* Cancel button */
    $("#manage_item_contents").delegate("#cancel_button, .popup-header .close", "click tap", function(e) {
		e.preventDefault();
		$("#manage_item").fadeOut(200);
		$("#manage_item_contents").html();
		$("body").removeClass("fm-noscroll");
		var link = $(this).attr("href");
		if (link) {
			window.location = link;
		}
	});
	
	
	$("#save_fm_settings, #save_module_settings").click(function() {
		$.ajax({
			type: "POST",
			url: "fm-modules/facileManager/ajax/processPost.php",
			data: $("#manage").serialize(),
			success: function(response)
			{
				if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
					doLogout();
					return false;
				} else {
					$("#response").removeClass("static").html(response);
					$("#response")
						.addClass("static")
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
		
		return false;
	});
	
	$("#generate_ssh_key_pair").click(function() {
		var form_data = {
			item_type: "fm_settings",
			gen_ssh: true
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
				} else if (response == "Success") {
					$("#gen_ssh_action").html("<p>' . _('SSH key pair is generated.') . '</p>");
				} else {
					$("#response").html(response);
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
		
		return false;
	});
	
	$("#force_software_check").click(function() {
		var form_data = {
			item_type: "fm_software_update_check"
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
					window.location = response;
				}
			}
		});
		
		return false;
	});
	
	$("#test_mail_settings").click(function() {
		var $this = $(this);

		var form_data = $("#fm_mailing_options").find("input, select, textarea").serialize() + "&" + $.param({item_type: "fm_test_mail_settings"});
		
		$("#manage_item").fadeIn(200);
		$("#manage_item_contents").html("<p>' . _('Processing...please wait.') . ' <i class=\"fa fa-spinner fa-spin\"></i></p>");

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
					$("#manage_item_contents").html(response);
				}
			}
		});
		
		return false;
	});
	
	/** Maintenance Mode toggle */
	$(".toggle-maintenance-mode").click(function() {
        var $this 		= $(this);
		var mode_status = $this.attr("rel");

		var form_data = {
			item_type: "fm_maintenance_mode",
			mode_status: mode_status,
			is_ajax: 1
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
				} else if (response == "Success") {
					if (mode_status == "disabled") {
						$(".top-banner").slideUp();
						$this.attr("rel", "active");
						$this.html("' . addslashes($__FM_CONFIG['icons']['enable']) . '");
					} else {
						$(".top-banner").slideDown();
						$this.attr("rel", "disabled");
						$this.html("' . addslashes($__FM_CONFIG['icons']['disable']) . '");
					}
				} else {
					$("#response").removeClass("static").html(response);
					$("#response")
						.addClass("static")
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
		
		return false;
	});

	/* Account settings */
    $(".account_settings").click(function() {
        var $this 		= $(this);
        user_id			= $this.attr("id");

		$("body").addClass("fm-noscroll");
		$("#manage_item").fadeIn(200);
		$("#response").fadeOut();
		
		var form_data = {
			user_id: user_id,
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
				$(".form-table input").first().focus();
			}
		});
		
		return false;
    });
    
    /* Popup form submissions */
    $("#manage_item_contents").delegate("input[type=submit].primary:not(.follow-action)", "click tap", function(e) {
		e.preventDefault();
		if ($(this).checkRequiredFields("#manage_item_contents") === false) {
			return false;
		}
		$form_table = $("div.popup-contents table");

		var uri_params = {"uri_params":getUrlVars()};
		var form_data = $("div.popup-contents form").serialize() + "&" + $.param(uri_params);

		$.ajax({
			type: "POST",
			url: "fm-modules/facileManager/ajax/processPost.php",
			data: form_data,
			success: function(response)
			{
				if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
					doLogout();
					return false;
				} else if ($.isArray(response)) {
					/* Set the auto-corrected value */
					$.each(response[0], function(key, value) {
						$form_table.find("input[name*=" + key + "][type!=\"checkbox\"][type!=\"radio\"]").val(value);
					});

					$form_table.find(".validate-error").removeClass("validate-error");
					$form_table.find(".validate-error-message").remove();

					/* Highlight any errors */
					if ("errors" in response[1]) {
						$.each(response[1]["errors"], function(key, value) {
							$element = $form_table.find("input[name*=" + key + "]");
							$element.addClass("validate-error");
							$element.after(" <a href=\"#\" class=\"validate-error-message tooltip-bottom\" data-tooltip=\"" + value + "\"><i class=\"fa fa-exclamation-triangle notice\" aria-hidden=\"true\"></i></a>");
						});
					}
				} else if (response != "Success" && !$.isNumeric(response)) {
					$("#popup_response").html("<p>" + response + "</p>");

					/* Popup response more link */
					$("#popup_response").delegate("a.more", "click tap", function(e1) {
						e1.preventDefault();
						error_div = $("#popup_response div#error")
						if (error_div.is(":visible")) {
							error_div.hide();
							$(this).text("' . _('more') . '");
						} else {
							error_div.show();
							$(this).text("' . _('less') . '");
						}
					});
					$("#popup_response").delegate("#response_close i.close", "click tap", function(e2) {
						e2.preventDefault();
						$("#popup_response").fadeOut(200, function() {
							$("#popup_response").html();
						});
					});
				
					$("#popup_response").fadeIn(200);

					if (response.indexOf("a class=\"more\"") <= 0) {
						$("#popup_response").delay(2000).fadeOut(200, function() {
							$("#popup_response").html();
						});
					}
				} else {
					location.reload();
				}
			}
		});
    });

	$("#pre_upgrade_backup").click(function() {
		$("#response").html("");
		window.location = "?backup";
	});
	
	/* Account authentication method changes */
	$("#manage_item_contents").delegate("#user_auth_type", "change", function(e) {
		if ($(this).val() == 2) {
			$("tr.user_password").hide();
			$("input#submit").removeAttr("disabled");
			$("input#submit").removeClass("disabled");
		} else {
			$("tr.user_password").show();
			$("input#submit").attr("disabled", "disabled");
			$("input#submit").addClass("disabled");
		}
	});

	/* User theme changes */
	$("#manage_item_contents").delegate("#user_theme", "change", function(e) {
		$("html").removeClass();
		$("html").addClass("default-theme " + $(this).val());
		$("#user_theme_mode").change();
	});
	$("#manage_item_contents").delegate("#user_theme_mode", "change", function(e) {
		$("html").removeClass("Light Dark System");
		$("html").addClass($(this).val());
		$(this).setThemeMode();
	});

	/* Account group association changes */
	$("#manage_item_contents").delegate("#user_group", "change", function(e) {
		if ($(this).val() == 0) {
			$("tr.user_permissions").show();
		} else {
			$("tr.user_permissions").hide();
		}
	});

	/* Account template changes */
	$("#manage_item_contents").delegate("#user_template_only", "click", function(e) {
		if ($(this).is(":checked")) {
			$("#user_email.required").closest("tr").children("th").children("label").removeClass("required");
			$("#user_email").removeClass("required validate-error");
			$(".user_password").hide();
			$(".user_password").find("input[type=password]").removeClass("required validate-error");
		} else {
			$("#user_email").addClass("required");
			$("#user_email.required").closest("tr").children("th").children("label").addClass("required");
			$(".user_password").find("input[type=password]").addClass("required");
			$(".user_password").show();
		}
	});
	
	/* Account password reset */
    $(".reset_password").click(function() {
        var $this 		= $(this);
        user_id			= $this.attr("id");

		$this.html("<i class=\"fa fa-spinner fa-spin\"></i>");

		var form_data = {
			user_id: user_id,
			reset_pwd: true,
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
				$("#response").html(response);
				$("#response")
					.css("opacity", 0)
					.slideDown(400, function() {
						$("#response").animate(
							{ opacity: 1 },
							{ queue: false, duration: 200 }
						);
					});
				$this.html(\'' . $__FM_CONFIG['icons']['pwd_reset'] . '\');
				if (response.toLowerCase().indexOf("response_close") == -1) {
					$("#response").delay(3000).fadeTo(200, 0.00, function() {
						$("#response").slideUp(400);
					});
				}
			}
		});
		
		return false;
    });

	/* Admin Tools */
    $("#admin-tools").delegate("form input.button:not(\"#import-records, #import, #db-backup, #bulk_apply, .double-click\"), #module_install, #module_upgrade, #update_core",
    "click tap",function(e){
        var $this 	= $(this);
        task		= $this.attr("id");
        item		= $this.attr("name");
		var form_data = $("#admin-tools-form").serialize();

		form_data += "&task=" + task + "&item=" + item + "&is_ajax=1";

		$("body").addClass("fm-noscroll");
		$("#manage_item").fadeIn(200);
		$("#manage_item_contents").html("<p>' . _('Processing...please wait.') . ' <i class=\"fa fa-spinner fa-spin\"></i></p>");
		
		$.ajax({
			type: "POST",
			url: "fm-modules/facileManager/ajax/processTools.php",
			data: form_data,
			success: function(response)
			{
				if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
					doLogout();
					return false;
				}
				$("#manage_item_contents").html(response);
			}
		});
		
		return false;
    });
	
	$(".double-click").click(function(e) {
		e.preventDefault();
		if ($(this).hasClass("alert")) {
			$(this).hide();
			return;
		} else {
			$(this).addClass("alert");
			$(this).removeClass("double-click");
			$(this).prop("value", "' . _('Click to confirm') . '");
			return false;
		}
	});

	$("#tophead .help_link").click(function() {
		var body_right		= $("#body_container").css("right");
		var help_right		= $("#help").css("right");
		
		if (body_right == "300px") {
			$("#body_container").animate({right: "0"}, 500);
			$("#help").hide("slide", { direction: "right" }, 500);
		} else {
			$("#body_container").animate({right: "300px"}, 500);
			$("#help").show("slide", { direction: "right" }, 500);
		}
		
		return false;
	});
	
	$("#help_file_container a.list_title").click(function() {
		help_block = $(this).next();
		if ($(help_block).is(":visible")) {
			$(help_block).slideUp("slow");
		} else {
			$(help_block).slideDown("slow");
		}
	});
	
	$("#help_file_container ul li div a").click(function() {
		window.opener.location.href = $(this).attr("href");
		return false;
	});
	
	$("#auth_method").change(function() {
		if ($(this).val() == 1) {
			$("#auth_fm_options").show("slow");
			$("#auth_ldap_options").slideUp();
			$("#auth_message_option").show("slow");
		} else if ($(this).val() == 2) {
			$("#auth_ldap_options").show("slow");
			$("#auth_fm_options").slideUp();
			$("#auth_message_option").show("slow");
		} else {
			$("#auth_ldap_options").slideUp();
			$("#auth_fm_options").slideUp();
			$("#auth_message_option").slideUp();
		}
	});
	
	$("#ldap_group_require").click(function() {
		if ($(this).is(":checked")) {
			$("#ldap_group_require_options").show("slow");
		} else {
			$("#ldap_group_require_options").slideUp();
		}
	});
	
	$("#api_token_support").click(function() {
		if ($(this).is(":checked")) {
			$("#enforce_ssl").prop("checked", this.checked).attr("disabled", true);
			$("#enforce_ssl").addClass("disabled");
			$("#enforce_ssl_label").addClass("disabled");
		} else {
			$("#enforce_ssl").attr("disabled", false);
			$("#enforce_ssl").removeClass("disabled");
			$("#enforce_ssl_label").removeClass("disabled");
		}
	});
	
	$("#mail_enable").click(function() {
		if ($(this).is(":checked")) {
			$("#fm_mailing_options").show("slow");
		} else {
			$("#fm_mailing_options").slideUp();
		}
	});
	
	$("#mail_smtp_auth").click(function() {
		if ($(this).is(":checked")) {
			$("#mail_smtp_auth_options").show("slow");
		} else {
			$("#mail_smtp_auth_options").slideUp();
		}
	});
	
	$("#proxy_enable").click(function() {
		if ($(this).is(":checked")) {
			$("#fm_proxy_options").show("slow");
		} else {
			$("#fm_proxy_options").slideUp();
		}
	});
	
	$("#log_method").change(function() {
		if ($(this).val() == 0) {
			$("#log_syslog_options").slideUp();
		} else {
			$("#log_syslog_options").show("slow");
		}
	});
	
	$("#software_update").click(function() {
		if ($(this).is(":checked")) {
			$("#software_update_options").show("slow");
		} else {
			$("#software_update_options").slideUp();
		}
	});
	
	$("#help_topbar i.popout").click(function() {
		$("#tophead .help_link").click();
		window.open("help.php","1356124444538","' . $__FM_CONFIG['default']['popup']['dimensions'] . ',toolbar=0,menubar=0,location=0,status=0,scrollbars=1,resizable=1,left=0,top=0");
		return false;
	});
	
	$("#help_topbar .close").click(function() {
		$("#tophead .help_link").click();
	});
	
	$(function () {
		$(".checkall").on("click", function () {
			$(this).closest("fieldset").find(":checkbox").prop("checked", this.checked);
		});
	});

	/* Server config builds */
	$("#table_edits").delegate("#build", "click tap", function(e) {
		if (confirm("' . _('Are you sure you want to build the config for this server?') . '")) {
	        var $this 	= $(this);
	        server_id	= $this.parent().parent().attr("id");
	
			$("#response").html("<p>' . _('Processing Config Build') . '... <i class=\"fa fa-spinner fa-spin\"></i></p>");
			$("#response")
				.css("opacity", 0)
				.slideDown(400, function() {
					$("#response").animate(
						{ opacity: 1 },
						{ queue: false, duration: 200 }
					);
				});
			
			var form_data = {
				server_id: server_id,
				action: "build",
				is_ajax: 1
			};
	
			setTimeout(function() {
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
						var eachLine = response.split("\n");
						if (eachLine.length <= 2) {
							var myDelay = 6000;
							$("#response").html(response);
							
							if (response.toLowerCase().indexOf("response_close") == -1) {
								$("#response").delay(3000).fadeTo(200, 0.00, function() {
									$("#response").slideUp(400);
								});
							}
						} else {
							var myDelay = 0;
		
							$("body").addClass("fm-noscroll");
							$("#manage_item").fadeIn(200);
							$("#manage_item_contents").html(response);
							
							$("#response").delay(300).fadeTo(200, 0.00, function() {
								$("#response").slideUp(400);
							});
						}
						
						if (response.toLowerCase().indexOf("failed") == -1 && 
							response.toLowerCase().indexOf("one or more errors") == -1 && 
							response.toLowerCase().indexOf("you are not authorized") == -1 && 
							response.toLowerCase().indexOf("does not have php configured") == -1 && 
							response.toLowerCase().indexOf("response_close") == -1
							) {
							$this.fadeOut(400);
							$this.parent().parent().removeClass("build");
						}
					}
				});
			}, 500);
		}
		
		return false;
	});
    
	$("#response").delegate("#response_close i.close", "click tap", function(e) {
		$("#response").fadeTo(200, 0.00, function() {
			$("#response").slideUp(400);
		});
	});

	$("#menu_mainitems .has-sub").hover(function() {
		$(this).find("span.arrow").show();
	}, function() {
		$("span.arrow").hide();
	});
	
	/* Bulk items */
	$("#bulk_apply").click(function(event) {
		/* Do not process if no action is selected */
		if ($("#bulk_action").val() == "") {
			return;
		}
		
		/* Build array of checked items */
		event.preventDefault();
		var itemIDs = $("#table_edits input:checkbox:checked").not(".tickall").map(function() {
			return $(this).val();
		}).get();
		
		/* Process items and action */
		item_type = $("#table_edits").attr("name");
		bulk_action = $("#bulk_action").val().toLowerCase();
		if (itemIDs.length == 0) {
			alert("You must select at least one " + item_type.slice(0,-1) + ".");
		} else {
			if (confirm("Are you sure you want to " + $("#bulk_action").val().toLowerCase() + " these selected " + item_type + "?")) {
				var form_data = {
					item_id: itemIDs,
					action: "bulk",
					bulk_action: bulk_action,
					item_type: item_type,
					server_serial_no: getUrlVars()["server_serial_no"],
					rel_url: window.location.href,
					is_ajax: 1
				};

				$("body").addClass("fm-noscroll");
				$("#manage_item").fadeIn(200);
				$("#manage_item_contents").html("<p>' . _('Processing Bulk Action') . '... <i class=\"fa fa-spinner fa-spin\"></i></p>");
		
				$.ajax({
					type: "POST",
					url: "fm-modules/facileManager/ajax/processPost.php",
					data: form_data,
					success: function(response)
					{
						if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
							doLogout();
							return false;
						} else if (response != "Success") {
							$("#manage_item_contents").html(response);
						} else {
							if (bulk_action == "delete") {
								$("#manage_item").hide();
								$.each(itemIDs, function(key, val) {
									var $row_element = $("#table_edits tr#" + val);
									$row_element.css({"background-color":"#D98085"});
									$row_element.fadeOut("slow", function() {
										$row_element.remove();
									});
								});
								$("#bulk_action").val(null).trigger("change");
							} else {
								location.reload();
							}
						}
					}
				});
			}
		}
	});

	/* Mass rebuild from top menu */
	$(".process_all_updates").click(function(event) {
		if (confirm("' . _('Are you sure you want to process all updates?') . '")) {
	        var $this 	= $(this);
			$this.find("i").addClass("fa-spin");
			$("body").addClass("fm-noscroll");
			$("#manage_item").fadeIn(200);
			$("#manage_item_contents").html("<p>' . _('Processing Updates') . '... <i class=\"fa fa-spinner fa-spin\"></i></p>");

			var form_data = {
				action: "process-all-updates",
				is_ajax: 1
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
					}
					$this.find("i").removeClass("fa-spin");
					$("#manage_item_contents").html(response);
					if (response.toLowerCase().indexOf("failed") == -1 && 
						response.toLowerCase().indexOf("one or more errors") == -1 && 
						response.toLowerCase().indexOf("you are not authorized") == -1 && 
						response.toLowerCase().indexOf("does not have php configured") == -1 && 
						response.toLowerCase().indexOf("response_close") == -1
						) {
						$this.parent().fadeOut(400);
					}
				}
			});
		}
	});

	/* Sortable table headers */
    $(".sortable th:not(\".header-nosort\")").click(function() {
    	var sort_by_field = $(this).attr("rel");
    	
    	if (sort_by_field) {
    		if (window.location.href.indexOf("?") != -1) {
    			append_mark = "&";
    		} else {
    			append_mark = "?";
    		}
    		window.location = window.location.href + append_mark + "sort_by=" + sort_by_field;
    	}
    });
	
	/* Pagination search */
	$("#pagination_search input").keypress(function (e) {
		if (e.which == 13) {
			var newValue = $(this).val();
			var queryParameters = {}, queryString = location.search.substring(1),
				re = /([^&=]+)=([^&]*)/g, m;
			while (m = re.exec(queryString)) {
				queryParameters[decodeURIComponent(m[1])] = decodeURIComponent(m[2]);
			}
			queryParameters["p"] = newValue;

			location.search = $.param(queryParameters);
			return false;
		}
	});
	
	$("a.search").click(function() {
		if ($("#search_form_container").css("display") == "none") {
			$("#search_form_container").fadeIn();
			$("#search_form_container input:text").focus();
		} else {
			$("#search_form_container").fadeOut();
		}
	});
	$("a.search").mouseover(function() {
		$("#search_form_container").fadeIn();
		$("#search_form_container input:text").focus();
	});
	
	/* Search input box */
	$("#search input").keypress(function (e) {
		if (e.which == 13) {
			var newValue = $(this).val();
			var queryParameters = {}, queryString = location.search.substring(1),
				re = /([^&=]+)=([^&]*)/g, m;
			while (m = re.exec(queryString)) {
				if (m[1] == "p") continue;
				queryParameters[decodeURIComponent(m[1])] = decodeURIComponent(m[2]);
			}
			queryParameters["q"] = encodeURIComponent(newValue);

			location.search = $.param(queryParameters);
			return false;
		}
	});

	/* Search input box cancel */
    $(".search_remove").click(function() {
		var queryParameters = {}, queryString = location.search.substring(1),
			re = /([^&=]+)=([^&]*)/g, m;
		while (m = re.exec(queryString)) {
			if (m[1] == "p" || m[1] == "q" || m[1] == "rc") continue;
			queryParameters[decodeURIComponent(m[1])] = decodeURIComponent(m[2]);
		}

		location.search = $.param(queryParameters);
		return false;
	});
	
	$(".disable-auto-complete").attr("autocomplete", "off");
	
	/* Handle the eye-attention */
	$(".eye-attention").click(function() {
		var attention	= getUrlVars()["attention"];
		
		var queryParameters = {}, queryString = location.search.substring(1),
			re = /([^&=]+)=([^&]*)/g, m;
		while (m = re.exec(queryString)) {
			if (m[1] == "p" || m[1] == "rc" || m[1] == "attention") continue;
			queryParameters[decodeURIComponent(m[1])] = decodeURIComponent(m[2]);
		}
		if (attention != "only") queryParameters["attention"] = encodeURIComponent("only");

		location.search = $.param(queryParameters);
		return false;
	});
	
	/* Show branding image in the settings */
	$("#setting-row #sm_brand_img").keyup(function (e) {
		var url = $(this).val();
		$.ajax({
			url:url,
			type:"HEAD",
			error: function() {
				$("#setting-row #brand_img").html("<i class=\"fa fa-question fa-4x\"></i>");
			},
			success: function() {
				$("#setting-row #brand_img").html("<img src=\"" + url + "\" />");
			}
		});
	});

	var fixHelperModified = function(e, tr) {
		var $originals = tr.children();
		var $helper = tr.clone();
		$helper.children().each(function(index) {
			$(this).width($originals.eq(index).width())
		});
		return $helper;
	},
		updateIndex = function(e, ui) {
			$("td.index", ui.item.parent()).each(function (i) {
				$(this).html(i + 1);
			});
		};

	$("#table_edits.grab tbody, #table_edits.grab1 tbody").not(".no-grab").sortable({
		items: "tr",
		handle: ".fa-bars",
		helper: fixHelperModified,
		start: function() {
			$(this).parent().addClass("grabbing");
		},
		stop: function() {
			$(this).parent().removeClass("grabbing");
			updateIndex;
			
			var items = $("#table_edits.grab tr:not(.no-grab), #table_edits.grab1 tr");
			var linkIDs = [items.length];
			var index = 0;
			
			items.each(function(intIndex) {
				linkIDs[index] = $(this).attr("id");
				index++;
			});
			var new_sort_order = linkIDs.join(";");
			
			/** Update the database */
	        var $this 				= $(this);
	        var item_type			= $("#table_edits").attr("name");
	        var server_serial_no	= getUrlVars()["server_serial_no"];

			var form_data = {
				item_id: "",
				item_type: item_type,
				server_serial_no: server_serial_no,
				sort_order: new_sort_order,
				action: "update_sort",
				uri_params: getUrlVars(),
				is_ajax: 1
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
					} else if (response != "Success") {
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
							$("body").addClass("fm-noscroll");
							$("#manage_item").fadeIn(200);
							$("#manage_item_contents").html(\'' . str_replace(array(PHP_EOL, "\t"), '', preg_replace('~\R~u', '', buildPopup('header', __('Sort Order Results')))) . '\' + response + \'' . str_replace(array(PHP_EOL, "\t"), '', preg_replace('~\R~u', '', buildPopup('footer', _('OK'), array('cancel_button' => 'cancel')))) . '\');
						}
					}
				}
			});
		}
	}).disableSelection();
	
	/* Check if form submit button should be enabled */
	$.fn.setSubmitButtonStatus = function() {
		/** Submit button */
		var button = $("input[type=submit][name=submit].primary");

		/** Are there any validate-error elements? */
		var errors = $(".validate-error").length;

		if (errors) {
			$(button).attr("disabled", true);
			$(button).addClass("disabled");
		} else {
			$(button).attr("disabled", false);
			$(button).removeClass("disabled");
		}
	}

	/* Check if all required fields are filled */
	$.fn.checkRequiredFields = function(e) {
		isValid = true;
		$(e + " input.required").each(function() {
			if ($(this).is(":visible") && $(this).val() === "") {
				$(this).addClass("validate-error");
				isValid = false;
			}
		});
		
		// $(this).setSubmitButtonStatus();

		return isValid;
	}

	/* Inline form validation */
	$("#manage_item_contents, .form-table").delegate("input.required", "keyup blur", function(e) {
		var $this = $(this);

		if ($this.val() != "") {
			$this.removeClass("validate-error");
		} else {
			$this.addClass("validate-error");
		}
		// $this.setSubmitButtonStatus();
	});

	/* Software changelog */
	$(".upgrade_notice a").click(function() {
		var changelog_href = $(this).attr("href");

		$("body").addClass("fm-noscroll");
		$("#manage_item").fadeIn(200);
		$("#manage_item_contents").html(\'' . str_replace(array(PHP_EOL, "\t"), '', preg_replace('~\R~u', '', buildPopup('header', _('Changelog')))) . '<input type="hidden" name="module" value="\'+changelog_href.split("/").pop()+\'" /><iframe src=\'+changelog_href+\' ></iframe>'. str_replace(array(PHP_EOL, "\t"), '', preg_replace('~\R~u', '', buildPopup('footer', _('Update'), array('update_module' => 'submit', 'cancel_button' => 'cancel')))) .'\');

		return false;
	});

	/* Update module */
	$("#manage_item_contents").delegate("input#update_module", "click tap", function(e) {
		module_name = $("input[name=module]").val();
		if (module_name == "' . $fm_name . '") {
			task = "update_core";
			process_file = "processTools.php";
		} else {
			task = "module_upgrade";
			process_file = "processPost.php";
		}
		/* Process items and action */
		var form_data = {
			item: module_name,
			item_id: [module_name],
			task: task,
			action: "bulk",
			bulk_action: "update",
			is_ajax: 1
		};

		$("body").addClass("fm-noscroll");
		$("#manage_item").fadeIn(200);
		$("#manage_item_contents").html("<p>' . _('Processing Module Update') . '... <i class=\"fa fa-spinner fa-spin\"></i></p>");

		$.ajax({
			type: "POST",
			url: "fm-modules/facileManager/ajax/"+process_file,
			data: form_data,
			success: function(response)
			{
				if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
					doLogout();
					return false;
				} else if (response != "Success") {
					$("#manage_item_contents").html(response);
				} else {
					location.reload();
				}
			}
		});
	});
});

function del(msg){
	return confirm(msg);
}

function checkPasswd(pass, pwdbutton, pwdtype) {
	var user = document.getElementById("user_login");
	var strength = document.getElementById("passwd_check");
	var button = document.getElementById(pwdbutton);
	var strongRegex = new RegExp("^(?=.{8,})(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*\\\W).*$", "g");
	var mediumRegex = new RegExp("^(?=.{7,})(((?=.*[A-Z])(?=.*[a-z]))|((?=.*[A-Z])(?=.*[0-9]))|((?=.*[a-z])(?=.*[0-9]))).*$", "g");
	var enoughRegex = new RegExp("(?=.{6,}).*", "g");
	var pass = document.getElementById(pass);
	var pwd1 = document.getElementById("user_password");
	var pwd2 = document.getElementById("cpassword");
	if (pwd1.value.length==0) {
		strength.innerHTML = "' . _('No Password') . '";
		strength.style.background = "";
		button.disabled = true;
		$(button).addClass("disabled");
		$(pwd1).addClass("validate-error");
	} else {
		strength.style.color = "white";
		if (false == enoughRegex.test(pwd1.value)) {
			strength.innerHTML = "' . _('More Characters') . '";
			strength.style.background = "#878787";
			button.disabled = true;
			$(button).addClass("disabled");
			$(pwd1).addClass("validate-error");
		} else if (strongRegex.test(pwd1.value)) {
			strength.innerHTML = "' . _('Strong') . '";
			strength.style.background = "green";
			button.disabled = false;
			$(button).removeClass("disabled");
			$(pwd1).removeClass("validate-error");
		} else if (mediumRegex.test(pwd1.value)) {
			strength.innerHTML = "' . _('Medium') . '";
			strength.style.background = "orange";
			if (pwdtype == "strong") {
				button.disabled = true;
				$(button).addClass("disabled");
				$(pwd1).addClass("validate-error");
			} else {
				button.disabled = false;
				$(button).removeClass("disabled");
				$(pwd1).removeClass("validate-error");
			}
		} else {
			strength.innerHTML = "' . _('Weak') . '";
			strength.style.background = "red";
			button.disabled = true;
			$(button).addClass("disabled");
			$(pwd1).addClass("validate-error");
		}
	}
	if (pwd2.value.length!=0 && pwd1.value!=pwd2.value) {
		strength.innerHTML = "' . _("Passwords don't match") . '";
		strength.style.background = "red";
		button.disabled = true;
		$(button).addClass("disabled");
		$(pwd2).addClass("validate-error");
	} else if (pwd2.value.length==0) {
		button.disabled = true;
		$(button).addClass("disabled");
		$(pwd2).addClass("validate-error");
	} else if (user.value.length==0) {
		strength.innerHTML = "' . _('No Username Specified') . '";
		strength.style.background = "#878787";
		button.disabled = true;
		$(button).addClass("disabled");
	}
}

function exchange(el){
	var ie=document.all&&!document.getElementById? document.all : 0;
	var toObjId=/b$/.test(el.id)? el.id.replace(/b$/,"") : el.id+"b";
	var toObj=ie? ie[toObjId] : document.getElementById(toObjId);
	if(/b$/.test(el.id))
		toObj.innerHTML=el.value;
	else{
		toObj.style.width=el.offsetWidth+7+"px";
		toObj.value=el.innerHTML;
	}
	el.style.display="none";
	toObj.style.display="inline";
}

function validateNumber(event) {
	// Allow: backspace, delete, tab, escape, and enter
	if ( event.keyCode == 46 || event.keyCode == 8 || event.keyCode == 9 || event.keyCode == 27 || event.keyCode == 13 || 
		// Allow: Ctrl+A
		(event.keyCode == 65 && event.ctrlKey === true) || 
		// Allow: home, end, left, right
		(event.keyCode >= 35 && event.keyCode <= 39)) {
			// let it happen, do not do anything
			return;
	}
	else {
		// Ensure that it is a number and stop the keypress
		if (event.shiftKey || (event.keyCode < 48 || event.keyCode > 57) && (event.keyCode < 96 || event.keyCode > 105 )) {
		event.preventDefault(); 
		}   
	}
}

function showHideBox(div, selectbox, testvalue) {
	var dropvalue = document.getElementById(selectbox).value;
	var inputbox = document.getElementById(div);
	
	if (dropvalue == testvalue) {
		inputbox.style.display = "block";
	} else {
		inputbox.style.display = "none";
	}
}

function getUrlVars() {
	var vars = {};
	var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function(m,key,value) {
		vars[key] = value.replace(/#/gi, "");
	});
	return vars;
}

function toggle(source, element_id) {
	checkboxes = document.getElementsByName(element_id);
	for(var i in checkboxes)
		checkboxes[i].checked = source.checked;
}

function onPage(name) {
	var path = window.location.pathname;
	return name == path.substring(path.lastIndexOf("/") + 1);
}

function doLogout() {
	window.location = "?logout";
}
';
}
