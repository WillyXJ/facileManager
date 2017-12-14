<?php
if (!defined('FM_NO_CHECKS')) define('FM_NO_CHECKS', true);
require_once('../../../fm-init.php');

header("Content-Type: text/javascript");

echo '
$(document).ready(function() {
	
	$("#set_sql_password").click(function() {
		if ($("#verbose").is(":checked") == false) {
			$("#response").html("<p>' . _('Processing...please wait.') . ' <i class=\"fa fa-spinner fa-spin\"></i></p>");
			$("#response")
				.css("opacity", 0)
				.slideDown(400, function() {
					$("#response").animate(
						{ opacity: 1 },
						{ queue: false, duration: 200 }
					);
				});
		} else {
			$("body").addClass("fm-noscroll");
			$("#manage_item").fadeIn(200);
			$("#manage_item_contents").fadeIn(200);
			$("#manage_item_contents").html("<p>' . _('Processing...please wait.') . ' <i class=\"fa fa-spinner fa-spin\"></i></p>");
		}
		
		$.ajax({
			type: "POST",
			url: "fm-modules/' . $_SESSION['module'] . '/ajax/processPost.php",
			data: $("#manage").serialize(),
			success: function(response)
			{
				if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
					doLogout();
					return false;
				}
				if ($("#verbose").is(":checked") == false) {
					$("#response").html(response);
					if (response.toLowerCase().indexOf("response_close") == -1) {
						$("#response").delay(3000).fadeTo(200, 0.00, function() {
							$("#response").slideUp(400);
						});
					}
				} else {
					$("#manage_item_contents").html(response);
				}
			}
		});
		
		return false;
	});
	
	$("#manage_item_contents").delegate("#server_type", "change", function(e) {
		var server_ports = ' . json_encode($__FM_CONFIG['fmSQLPass']['default']['ports']) . ';
		$("#server_port").val(server_ports[$(this).val()]);
	});
	
});
';
?>