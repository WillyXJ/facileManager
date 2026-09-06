<?php
if (!defined('FM_NO_CHECKS')) define('FM_NO_CHECKS', true);
require_once('../../../fm-init.php');

header("Content-Type: text/javascript");

echo '$(document).ready(function() {
	$("#show_password").click(function(e) {
		// Get closest input
		var input_field = $(this).parent().find("input");

		// Swap between password and text field
		if (input_field.attr("type") == "password") {
			input_field.attr("type", "text");
			$(this).addClass("fa-eye-slash");
		} else {
			input_field.attr("type", "password");
			$(this).removeClass("fa-eye-slash");
		}
	});
';

if (!isset($__FM_CONFIG)) {
	// Installer jquery
	echo '
	$("#install_enable_ssl").click(function() {
		$("#install_ssl_options").slideToggle("slow");
	});

	$("#btn_install_config_submit").click(function() {
		$.ajax({
			type: "POST",
			url: "fm-modules/facileManager/ajax/installer.php",
			data: $("#window").find("input").serialize() + "&" + $.param({task: "install_config_test"}),
			success: function(response)
			{
				if (response.indexOf("ERROR") >=0) {
					$("#message").prepend(response);
				} else if (response.indexOf("?step=") == 0) {
					window.location = response;
				} else if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
					doLogout();
					return false;
				} else {
					$("div.container").replaceWith(response);
				}
			}
		});
		
		return false;
	});
});
';
} else {
	echo '
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

	// Call on load and resize using jQuery
	$(window).on("load resize", setSubmenuHeight);

	var KEYCODE_ENTER = 13;
	var KEYCODE_ESC = 27;
	
	$(document).keyup(function(e) {
		if (e.keyCode == KEYCODE_ESC) { $("#cancel_button").click(); }
		if (e.keyCode == KEYCODE_ENTER && $(":focus").is("input[type=text], input[type=password], input[type=checkbox]")) { $("#primary_button, #loginbtn, #forgotbtn, #verify_otpbtn").click(); }
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
		// Match any element whose id starts with password_reset_expiry_ or otp_expiry_
		$("[id^=\"password_reset_expiry_\"], [id^=\"otp_expiry_\"]").select2({
			width: "60px"
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
	
	function displayHideProcessAll() {
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
	}
	$(function() {
		displayHideProcessAll();
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
	
	$("#btn_install_create_account").click(function() {
		$("#message").html("");
		var pass_check = $("#passwd_check").html();
		$.ajax({
			type: "POST",
			url: "fm-modules/facileManager/ajax/installer.php",
			data: $("#window").find("input").serialize() + "&" + $.param({passwd_check: pass_check, task: "install_create_account"}),
			success: function(response)
			{
				if (response.indexOf("ERROR") >=0) {
					$("#message").prepend(response);
				} else if (response.indexOf("?step=") == 0) {
					window.location = response;
				} else if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
					doLogout();
					return false;
				} else {
					$("div.container").replaceWith(response);
				}
			}
		});
		
		return false;
	});

	$("#login_form input, #form_messaging input").on("change", function() {
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
		/* Ensure username and terms are entered */
		if (!$("#username").length || !$("#username").val()) {
			$("#login_form").effect("shake");
			return false;
		}
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
					$("#login_form").effect("shake");	
				} else if (response.indexOf("failed") >=0) {
					$("#message").prepend(response);
				} else if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
					doLogout();
					return false;
				} else {
					window.location = response;
				}
			}
		});
		
		return false;
	});
	
	$("#forgotbtn").click(function() {
		/* Ensure user_login is entered */
		if (!$("#user_login").length || !$("#user_login").val()) {
			$("#user_login").focus();
			$("#login_form").effect("shake");	
		} else {
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
					if(response === false) {
						$("#user_login").focus();
						$("#login_form").effect("shake");	
					} else {
						$("#message").html(response);
					}
				}
			});
		}
		
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
					displayHideProcessAll();
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
			$this.html("<i class=\"fa fa-spinner fa-spin\" aria-hidden=\"true\"></i>");
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
							if ($("#table_edits tbody tr").length < 1) {
								$("#table_edits").after("<p id=\"table_edits\" class=\"noresult\">' . _('There are no items defined.') . '</p>");
							}
						});
						displayHideProcessAll();
					} else {
						setTimeout(function() {
					    	$this.html("<i class=\"fa fa-trash delete\" alt=\"Delete\" title=\"Delete\" aria-hidden=\"true\"></i>");
						}, 2000);
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

	/* Branding image upload */
	$("#btn_brand_img_upload").click(function() {
		var $this = $(this);
		const file = $("#brand_img_upload")[0].files[0];

		/* Ensure there is a file */
		if (!file) {
			$this.html("' . _('Upload') . '");
			return;
		}
		
		const form_data = new FormData();
		form_data.append("file", file);
		form_data.append("item_type", "fm_settings");
		form_data.append("upload_image", true);

		$.ajax({
			type: "POST",
			url: "fm-modules/facileManager/ajax/processPost.php",
			data: form_data,
			contentType: false,
			processData: false,
			success: function(response)
			{
				if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
					doLogout();
					return false;
				} else if (response == "Success") {
					$("#response").slideUp(400);
					$("#sm_brand_img").val("/fm-modules/' . $fm_name . '/images/upload/" + file.name).keyup();
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
				$this.delay(3000).html("' . _('Upload') . '");
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

		var form_data = new FormData($("div.popup-contents form")[0]);
		form_data.append("uri_params", JSON.stringify(getUrlVars()));
		var import_file = $("#import-file");
		if (import_file.length) {
			var file = import_file[0].files[0];
			if (file) {
				form_data.append("import-file", file);
			}
		}

		$.ajax({
			type: "POST",
			url: "fm-modules/facileManager/ajax/processPost.php",
			data: form_data,
			processData: false,      // Prevent jQuery from processing the data
			contentType: false,      // Prevent jQuery from setting content type header
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
					/* Response contains popup */
					if (response.indexOf("popup-header") >= 0) {
						$("#manage_item_contents").html(response);
					} else {
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

	/* Account 2FA changes */
	$("#manage_item_contents").delegate("#enable_2fa", "click", function(e) {
		if ($(this).is(":checked")) {
			$("tr.user_2fa_method_row").show();
			$("#user_2fa_method").change();
		} else {
			$("tr.user_2fa_method_row").hide();
			$("tr.user_2fa_setup_row").hide();
		}
	});

	/* Account 2FA method changes */
	$("#manage_item_contents").delegate("#user_2fa_method", "change", function(e) {
		if ($(this).val() == "app") {
			$("tr.user_2fa_setup_row").show();
		} else {
			$("tr.user_2fa_setup_row").hide();
		}
	});

	/* Account group association changes */
	$("#manage_item_contents").delegate("#user_group", "change", function(e) {
		if ($(this).val() == 0) {
			$("#tab.user_permissions").show();
		} else {
			$("#tab.user_permissions").hide();
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
				if (response.toLowerCase().indexOf("response_close") >= 0) {
					$("#response").html(response);
					$("#response")
						.css("opacity", 0)
						.slideDown(400, function() {
							$("#response").animate(
								{ opacity: 1 },
								{ queue: false, duration: 200 }
							);
						});
					$this.html(\'' . $__FM_CONFIG['icons']['fail'] . '\').fadeIn(200);
				} else {
					$this.html(\'' . $__FM_CONFIG['icons']['ok'] . '\').fadeIn(200);
				}
				setTimeout(function() {
					$this.stop(true,true).fadeOut(200, function() {
						$this.html(\'' . $__FM_CONFIG['icons']['pwd_reset'] . '\').fadeIn(200);
					});
				}, 3000);
			}
		});
		
		return false;
    });

	/* Setup user 2FA app */
	$("#manage_item_contents").delegate("#setup_2fa_app_button", "click", function(e) {
		var $this 		= $(this);

		$this.html("<i class=\"fa fa-spinner fa-spin\" aria-hidden=\"true\"></i> ' . _('Generating QR Code...') . '");
		$this.addClass("disabled").attr("disabled", "disabled");

		var form_data = {
			otp_2fa: "setup-app",
			is_ajax: 1
		};

		$.ajax({
			type: "POST",
			url: "' . $GLOBALS['RELPATH'] . '../ajax/getData.php",
			data: form_data,
			success: function(response)
			{
				if (response.toLowerCase().indexOf("failed") >= 0) {
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
				} else if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
					doLogout();
					return false;
				} else {
					$("#2fa_setup_container").html(response);
				}
			}
		});
		
		return false;
	});

	/* Generate 2FA recovery code */
	$("#manage_item_contents").delegate("#generate_2fa_recovery_code", "click", function(e) {
		var $this 		= $(this);

		$this.html("<i class=\"fa fa-spinner fa-spin\" aria-hidden=\"true\"></i> ' . _('Generating Recovery Code...') . '");
		$this.addClass("disabled").attr("disabled", "disabled");

		var form_data = {
			otp_2fa: "generate-recovery-code",
			is_ajax: 1
		};

		$.ajax({
			type: "POST",
			url: "' . $GLOBALS['RELPATH'] . '../ajax/getData.php",
			data: form_data,
			success: function(response)
			{
				if (response.toLowerCase().indexOf("failed") >= 0) {
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
				} else if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
					doLogout();
					return false;
				} else {
					$this.parent().html(response);
				}
			}
		});
		
		return false;
	});

	/* Prevent 2fa_form submission on enter keypress and only accept numbers and ctrl-v or cmd-v */
	$("#2fa_form").on("keypress", function(e) {
		if (e.which == KEYCODE_ENTER) {
			return false;
		}
		var charCode = (e.which) ? e.which : e.keyCode;
		/* Allow capital letters */
		if ($("#verify_otp").val() == "recovery") {
			if ((charCode >= 65 && charCode <= 90) || (charCode >= 97 && charCode <= 122)) {
				return true;
			}
		}
		/* Allow numbers */
		if (charCode >= 48 && charCode <= 57) {
			return true;
		}
		/* Allow ctrl-v or cmd-v */
		if (e.ctrlKey && charCode == 118) {
			return true;
		}
		
		/* Disallow all other characters */
		return false;
	});

	/* Format recovery code input with spaces every 4 characters */
	$("#app_otp").on("input", function() {
		if ($("#verify_otp").val() == "recovery") {
			var otp_field = $(this);
			var otp_value = otp_field.val().split(" ").join(""); // remove hyphens
			if (otp_value.length > 0) {
				otp_value = otp_value.match(new RegExp(".{1,4}", "g")).join(" ");
			}
			otp_field.val(otp_value);
		}
	});

	/* Generate 2FA code when two-factor is in request uri */
	function generateOtpIfOnTwoFactor() {
		if (window.location.pathname.replace(/[?#].*$/,"").replace(/\/+$/,"").split("/").pop() == "two-factor") {
			var form_data = {
				otp_2fa: "generate",
				is_ajax: 1
			};

			$.ajax({
				type: "POST",
				url: "' . $GLOBALS['RELPATH'] . '../ajax/getData.php",
				data: form_data,
				success: function(response)
				{
					// optional: handle response
				}
			});
		}
	}

	// Run on initial load
	generateOtpIfOnTwoFactor();

	// Run when user navigates via history API (back/forward)
	window.addEventListener("popstate", generateOtpIfOnTwoFactor);

	/* Submit 2FA when six numbers are entered */
	$("#app_otp").on("input", function() {
		var $this 		= $(this);
		var $code		= $this.val();

		if ($("#verify_otp").val() == "recovery") {
			var otp_field = $(this);
			var otp_value = otp_field.val().split(" ").join(""); // remove hyphens
			if (otp_value.length > 0) {
				otp_value = otp_value.match(new RegExp(".{1,4}", "g")).join(" ");
			}
			otp_field.val(otp_value);

			var max_length = 14;
		} else {
			var max_length = 6;
		}
		if ($code.length == max_length) {
			$("#verify_otpbtn").click();
		}
	});

	/* 2FA verification */
	$.fn.verify2FAButtonClick = function() {
		var $button		= $("#verify_otpbtn");
		var $code		= $("#app_otp").val();
		var $secret		= $("#user_2fa_secret").val();
		var $otp_method = $("#verify_otp").val();
		var $form		= $("#login_form");
		if ($form.length > 0) {
			var $form		= $("#app_otp");
		}

		$button.html("<i class=\"fa fa-spinner fa-spin\" aria-hidden=\"true\"></i> ' . _('Verifying...') . '");
		$button.addClass("disabled").attr("disabled", "disabled");

		var form_data = {
			code: $code,
			secret: $secret,
			otp_2fa: $otp_method,
			is_ajax: 1
		};

		$.ajax({
			type: "POST",
			url: "' . $GLOBALS['RELPATH'] . '../ajax/getData.php",
			data: form_data,
			success: function(response)
			{
				setTimeout(function() {
					if (response.indexOf("failed") >= 0) {
						$form.effect("shake");
						$("#app_otp").addClass("validate-error");
						$button.removeClass("disabled").removeAttr("disabled");
						$button.html("<i class=\"fa fa-check\" aria-hidden=\"true\"></i> ' . _('Verify') . '");
						$("#app_otp").val("").focus();
						if (typeof $secret !== "undefined") {
							$("#message").html("' . _('The code you entered is invalid. Please try again.') . '").addClass("failed");
							$("#message").fadeIn(200);
							$("#message").delay(4000).fadeOut(200, function() {
								$("#message").html("");
							});
						}
					} else if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
						doLogout();
						return false;
					} else if (typeof $secret !== "undefined") {
						/* 2FA setup successful */
						$("#tfa_app_setup_form").html("<p id=\"message\" class=\"success\"><i class=\"fa fa-check ok\" aria-hidden=\"true\"></i> ' . _('Code was verified successfully.') . '</p>");
					} else {
						window.location = response;
					}
				}, 300);
			}
		});
		
		return false;
	};
	$("#verify_otpbtn").click(function() {
		$(this).verify2FAButtonClick();
	});
	$("#manage_item_contents").delegate("#app_otp", "input", function(e) {
		if ($(this).val().length == 6) {
			$(this).verify2FAButtonClick();
		}
	});
	$("#manage_item_contents").delegate("#verify_otpbtn", "click tap", function(e) {
		$(this).verify2FAButtonClick();
	});

	/* 2FA more options */
	$("#message.more-options").click(function() {
		$("#more_options").slideToggle("slow");
		return false;
	});

	/* Resend 2FA OTP */
	$("#resend_otp").click(function() {
		var $this 		= $(this);

		$this.html("<i class=\"fa fa-spinner fa-spin\" aria-hidden=\"true\"></i> ' . _('Resending code...') . '");
		$this.addClass("disabled").attr("disabled", "disabled");

		var form_data = {
			otp_2fa: "resend",
			is_ajax: 1
		};

		$.ajax({
			type: "POST",
			url: "' . $GLOBALS['RELPATH'] . '../ajax/getData.php",
			data: form_data,
			success: function(response)
			{
				$this.removeClass("disabled").removeAttr("disabled");
				$this.find(".fa").removeClass("fa-spinner fa-spin").addClass("fa-check ok");
				$("#app_otp").val("").focus();
				setTimeout(function() {
					$this.stop(true,true).fadeOut(200, function() {
						$(this).html("' . _('Resend code') . '").fadeIn(200);
					});
				}, 3000);
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
				} else if (response != "Success") {
					$("#manage_item_contents").html(response);
				} else {
					location.reload();
				}
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
		$("#ldap_group_require_options").slideToggle("slow");
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
		$("#fm_mailing_options").slideToggle("slow");
	});
	
	$("#mail_smtp_auth").click(function() {
		$("#mail_smtp_auth_options").slideToggle("slow");
	});
	
	$("#proxy_enable").click(function() {
		$("#fm_proxy_options").slideToggle("slow");
	});
	
	$("#log_method").change(function() {
		if ($(this).val() == 0) {
			$("#log_syslog_options").slideUp();
		} else {
			$("#log_syslog_options").show("slow");
		}
	});
	
	$("#software_update").click(function() {
		$("#software_update_options").slideToggle("slow");
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
	$("#pagination_container .eye-attention").click(function() {
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
		// Get module name from URL
		let moduleName = changelog_href.replace(/[?#].*$/,"").replace(/\/+$/,"").split("/").pop();

		$("body").addClass("fm-noscroll");
		$("#manage_item").fadeIn(200);
		$("#manage_item_contents").html(\'' . str_replace(array(PHP_EOL, "\t"), '', preg_replace('~\R~u', '', buildPopup('header', _('Changelog')))) . '<input type="hidden" name="module" value="\'+moduleName+\'" /><iframe src=\'+changelog_href+\' ></iframe>'. str_replace(array(PHP_EOL, "\t"), '', preg_replace('~\R~u', '', buildPopup('footer', _('Update'), array('update_module' => 'submit', 'cancel_button' => 'cancel')))) .'\');

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

function setSubmenuHeight() {
	var $submenus = $(".submenu");
	if (!$submenus.length) return;

	// Use the full page/document height in case the page scrolls
	var pageHeight = $(document).height();

	// Iterate per submenu and compute a unique remaining height
	$submenus.each(function() {
		var $thisSub = $(this);

		// Anchor: the parent of the submenu
		var $anchor = $thisSub.parent();

		// Compute submenu top as distance from top of document and round down
		var submenuTop = Math.floor($anchor.offset().top + 10);

		// remainingHeight is distance from anchor to bottom of the document
		var remainingHeight = Math.floor(pageHeight - submenuTop);
		if (remainingHeight < 0) remainingHeight = 0;

		$thisSub.css({
			"max-height": remainingHeight + "px"
		});
	});
}
';
}
