<?php
if (!defined('FM_NO_CHECKS')) define('FM_NO_CHECKS', true);
require_once('../../../fm-init.php');

echo '$(document).ready(function() {
	
	var KEYCODE_ENTER = 13;
	var KEYCODE_ESC = 27;
	
	$(document).keyup(function(e) {
		if (e.keyCode == KEYCODE_ESC) { $("#cancel_button").click(); } 
	});

	$(function() {
		$(".datepicker").datepicker();
		$("select").select2({minimumResultsForSearch: 10});
		$("#bulk_action").select2({minimumResultsForSearch: -1, width: "120px", allowClear: true});
		$("#server_serial_no").select2({minimumResultsForSearch: 10});
		$("#settings select").select2({
			width: "200px",
			minimumResultsForSearch: 10
		});
		$("#admin-tools-select select").select2({
			containerCss: { "min-width": "300px" },
			minimumResultsForSearch: 10
		});
		$(function() {
			$( "#manage_item_contents" ).draggable();
		});
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
					if (response == 0 || ! $.isNumeric(response)) {
						$(".process_all_updates").parent().fadeOut(400);
					} else {
						$("#topheadpartright span.update_count").html(response);
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
	
	$("input:text, input:password, select").first().focus();
	
	// Everything we need for scrolling up and down.
	$(window).scroll( function(){
		if($(window).scrollTop() > 150) $("#scroll-to-top").addClass("displayed");
		else $("#scroll-to-top").removeClass("displayed");
	} );
	
	$("#scroll-to-top").click( function(){
		$("html, body").animate( { scrollTop: "0px" } );
		return false;
	} );
	
	$("#loginbtn").click(function() {
	
		var action = $("#loginform").attr("action");
		var form_data = {
			username: $("#username").val(),
			password: $("#password").val(),
			is_ajax: 1
		};
		
		$.ajax({
			type: "POST",
			url: action,
			data: form_data,
			success: function(response)
			{
				if(response == "failed") {
					$("#password").val("");
					if ($("#username").val() == "") {
						$("#username").focus();
					} else {
						$("#password").focus();
					}
					$("#login_form").effect("shake", { times:3 }, 15);	
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
    $("#plus").click(function() {
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

		$("#manage_item").fadeIn(200);
		$("#manage_item_contents").fadeIn(200);
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
				$("#manage_item_contents").html(response);
				if ($("#manage_item_contents").width() >= 700) {
					$("#manage_item_contents").addClass("wide");
				}
				$(".datepicker").datepicker();
				$(".form-table input:text, .form-table select").first().focus();
			}
		});
		
		return false;
    });
    
	/* Form edits */
    $("#table_edits").delegate("a.edit_form_link", "click tap", function(e) {
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

		$("#manage_item").fadeIn(200);
		$("#manage_item_contents").fadeIn(200);
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
				if (response == "Success") {
					$row_id.removeClass();
					$row_id.addClass(item_status);
					if (item_status == "disabled") {
						$this.attr("rel", "active");
						$this.html("' . addslashes($__FM_CONFIG['icons']['enable']) . '");
					} else {
						$this.attr("rel", "disabled");
						$this.html("' . addslashes($__FM_CONFIG['icons']['disable']) . '");
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
						$("#response").delay(3000).fadeTo(200, 0.00, function() {
							$("#response").slideUp(400);
						});
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
		
		return false;
    });

	/* Form deletes */
    $("#table_edits").delegate("a.delete", "click tap", function(e) {
        var $this 		= $(this);
        var $row_id		= $this.parent().parent();
        item_id			= $row_id.attr("id");
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

		if (confirm("' . _('Are you sure you want to delete this item?') . '")) {
			$.ajax({
				type: "POST",
				url: "fm-modules/facileManager/ajax/processPost.php",
				data: form_data,
				success: function(response)
				{
					if (response == "Success") {
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
							$("#response").delay(3000).fadeTo(200, 0.00, function() {
								$("#response").slideUp(400);
							});
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

    /* Cancel button */
    $("#manage_item_contents").delegate("#cancel_button, .close", "click tap", function(e) {
		e.preventDefault();
		$("#manage_item").fadeOut(200);
		$("#manage_item_contents").fadeOut(200).html();
		$("#manage_item_contents").removeClass("wide");
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
				if (response.indexOf("force_logout") >=0) {
					window.location = "?logout";
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
					$("#response").delay(3000).fadeTo(200, 0.00, function() {
						$("#response").slideUp(400);
					});
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
				if (response == "Success") {
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
					$("#response").delay(3000).fadeTo(200, 0.00, function() {
						$("#response").slideUp(400);
					});
				}
			}
		});
		
		return false;
	});
	
	/* Account settings */
    $(".account_settings").click(function() {
        var $this 		= $(this);
        user_id			= $this.attr("id");

		$("#manage_item").fadeIn(200);
		$("#manage_item_contents").fadeIn(200);
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
				$("#manage_item_contents").html(response);
				$(".form-table input").first().focus();
			}
		});
		
		return false;
    });
    
    /* Account password changes */
    $("#manage_item_contents").delegate("#update_user_profile", "click tap", function(e) {
		var form_data = $("#fm_user_profile").serialize();

		$.ajax({
			type: "POST",
			url: "fm-modules/facileManager/ajax/processPost.php",
			data: form_data,
			success: function(response)
			{
				if (response == "Success") {
					$("#popup_response").html("<p>' . _('Profile has been updated.') . '</p>");
				} else {
					$("#popup_response").html("<p>" + response + "</p>");
				}
				$("#popup_response").fadeIn(200);
				
				if (response == "Success") {
					$("#manage_item_contents").delay(2000).fadeOut(200, function() {
						$("#manage_item_contents").html();
						$("#manage_item").fadeOut(200);
					});
				} else {
					$("#popup_response").delay(2000).fadeOut(200, function() {
						$("#popup_response").html();
					});
				}
			}
		});
    });

	/* Account password reset */
    $(".reset_password").click(function() {
        var $this 		= $(this);
        user_id			= $this.attr("id");

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
				$("#response").html(response);
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
		});
		
		return false;
    });

	/* Admin Tools */
    $("#admin-tools").delegate("form input.button:not(\"#import-records, #import, #db-backup\"), #module_install, #module_upgrade",
    "click tap",function(e){
        var $this 	= $(this);
        task		= $this.attr("id");
        item		= $this.attr("name");
		var form_data = $("#admin-tools-form").serialize();

		form_data += "&task=" + task + "&item=" + item + "&is_ajax=1";

		$("#manage_item").fadeIn(200);
		$("#manage_item_contents").fadeIn(200);
		$("#manage_item_contents").html("<p>' . _('Processing...please wait.') . ' <i class=\"fa fa-spinner fa-spin\"></i></p>");
		
		$.ajax({
			type: "POST",
			url: "fm-modules/facileManager/ajax/processTools.php",
			data: form_data,
			success: function(response)
			{
				$("#manage_item_contents").html(response);
			}
		});
		
		return false;
    });

	$("#topheadpartright .help_link").click(function() {
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
	
	$("#manage_item_contents").delegate("#user_template_only", "click tap", function(e) {
		$("input[type=\"submit\"]").removeAttr("disabled");
	});
	
	$("#auth_method").change(function() {
		if ($(this).val() == 1) {
			$("#auth_fm_options").show("slow");
			$("#auth_ldap_options").slideUp();
		} else if ($(this).val() == 2) {
			$("#auth_ldap_options").show("slow");
			$("#auth_fm_options").slideUp();
		} else {
			$("#auth_ldap_options").slideUp();
			$("#auth_fm_options").slideUp();
		}
	});
	
	$("#ldap_group_require").click(function() {
		if ($(this).is(":checked")) {
			$("#ldap_group_require_options").show("slow");
		} else {
			$("#ldap_group_require_options").slideUp();
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
	
	$("#software_update").click(function() {
		if ($(this).is(":checked")) {
			$("#software_update_options").show("slow");
		} else {
			$("#software_update_options").slideUp();
		}
	});
	
	$("#help_topbar img.popout").click(function() {
		$("#topheadpartright .help_link").click();
		window.open("help.php","1356124444538","width=700,height=500,toolbar=0,menubar=0,location=0,status=0,scrollbars=1,resizable=1,left=0,top=0");
		return false;
	});
	
	$("#help_topbar .close").click(function() {
		$("#topheadpartright .help_link").click();
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
						var eachLine = response.split("\n");
						if (eachLine.length <= 2) {
							var myDelay = 6000;
							$("#response").html(response);
							
							$("#response").delay(3000).fadeTo(200, 0.00, function() {
								$("#response").slideUp(400);
							});
						} else {
							var myDelay = 0;
		
							$("#manage_item").fadeIn(200);
							$("#manage_item_contents").fadeIn(200);
							$("#manage_item_contents").html(response);
							if ($("#manage_item_contents").width() >= 700) {
								$("#manage_item_contents").addClass("wide");
							}
							
							$("#response").delay(300).fadeTo(200, 0.00, function() {
								$("#response").slideUp(400);
							});
						}
						
						if (response.toLowerCase().indexOf("failed") == -1 && 
							response.toLowerCase().indexOf("one or more errors") == -1 && 
							response.toLowerCase().indexOf("you are not authorized") == -1 && 
							response.toLowerCase().indexOf("does not have php configured") == -1
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
    
	$("#mainitems .has-sub").hover(function() {
		$(this).find("span.arrow").show();
	}, function() {
		$("span.arrow").hide();
	});
	
	/* Bulk items */
	$("#bulk_apply").click(function(event) {
		/* Do not process if no action is selected */
		if ($("#bulk_action").val() == "' . _('Bulk Actions') . '") {
			return;
		}
		
		/* Build array of checked items */
		event.preventDefault();
		var serverIDs = $("#table_edits input:checkbox:checked").not(".tickall").map(function() {
			return $(this).val();
		}).get();
		
		/* Process items and action */
        item_type		= $("#table_edits").attr("name");
		if (serverIDs.length == 0) {
			alert("You must select at least one " + item_type.slice(0,-1) + ".");
		} else {
			if (confirm("Are you sure you want to " + $("#bulk_action").val().toLowerCase() + " these selected " + item_type + "?")) {
				$("#manage_item").fadeIn(200);
				$("#manage_item_contents").fadeIn(200);
				$("#manage_item_contents").html("<p>' . _('Processing Bulk Action') . '... <i class=\"fa fa-spinner fa-spin\"></i></p>");
		
				var form_data = {
					item_id: serverIDs,
					action: "bulk",
					bulk_action: $("#bulk_action").val().toLowerCase(),
					item_type: item_type,
					is_ajax: 1
				};
				
				$.ajax({
					type: "POST",
					url: "fm-modules/facileManager/ajax/processPost.php",
					data: form_data,
					success: function(response)
					{
						$("#manage_item_contents").html(response);
						if ($("#manage_item_contents").width() >= 700) {
							$("#manage_item_contents").addClass("wide");
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
			$("#manage_item").fadeIn(200);
			$("#manage_item_contents").fadeIn(200);
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
					$this.find("i").removeClass("fa-spin");
					$("#manage_item_contents").html(response);
					if ($("#manage_item_contents").width() >= 700) {
						$("#manage_item_contents").addClass("wide");
					}
					if (response.toLowerCase().indexOf("failed") == -1 && 
						response.toLowerCase().indexOf("one or more errors") == -1 && 
						response.toLowerCase().indexOf("you are not authorized") == -1 && 
						response.toLowerCase().indexOf("does not have php configured") == -1
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
	
	/* Search input box */
	$("#record_search input").keypress(function (e) {
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
			if (m[1] == "p" || m[1] == "q") continue;
			queryParameters[decodeURIComponent(m[1])] = decodeURIComponent(m[2]);
		}

		location.search = $.param(queryParameters);
		return false;
	});
	
	$(".disable-auto-complete").attr("autocomplete", "off");

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
		strength.innerHTML = "No Password";
		strength.style.background = "";
		button.disabled = true;
	} else {
		strength.style.color = "white";
		if (false == enoughRegex.test(pwd1.value)) {
			strength.innerHTML = "More Characters";
			strength.style.background = "#878787";
			button.disabled = true;
		} else if (strongRegex.test(pwd1.value)) {
			strength.innerHTML = "Strong";
			strength.style.background = "green";
			button.disabled = false;
		} else if (mediumRegex.test(pwd1.value)) {
			strength.innerHTML = "Medium";
			strength.style.background = "orange";
			if (pwdtype == "strong") {
				button.disabled = true;
			} else {
				button.disabled = false;
			}
		} else {
			strength.innerHTML = "Weak";
			strength.style.background = "red";
			button.disabled = true;
		}
	}
	if (pwd2.value.length!=0 && pwd1.value!=pwd2.value) {
		strength.innerHTML = "' . _("Passwords don't match") . '";
		strength.style.background = "red";
		button.disabled = true;
	} else if (pwd2.value.length==0) {
		button.disabled = true;
	} else if (user.value.length==0) {
		strength.innerHTML = "' . _('No Username Specified') . '";
		strength.style.background = "#878787";
		button.disabled = true;
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
';
?>