<?php
echo '
$(document).ready(function() {
	
	$("#manage_item_contents").delegate("#server_update_method", "change", function(e) {
		if ($(this).val() == "cron") {
			$("#server_update_port_option").slideUp();
		} else {
			$("#server_update_port_option").show("slow");
		}
	});
	
	$("#manage_item_contents").delegate("#service_type", "change", function(e) {
		if ($(this).val() == "icmp") {
			$("#tcpudp_option").slideUp();
			$("#tcp_option").slideUp();
			$("#icmp_option").show("slow");
		} else if ($(this).val() == "tcp") {
			$("#icmp_option").slideUp();
			$("#tcpudp_option").show("slow");
			$("#tcp_option").show("slow");
		} else {
			$("#icmp_option").slideUp();
			$("#tcp_option").slideUp();
			$("#tcpudp_option").show("slow");
		}
	});
	
	$("#manage_item_contents").delegate("#object_type", "change", function(e) {
		if ($(this).val() == "host") {
			$("#netmask_option").slideUp();
		} else {
			$("#netmask_option").show("slow");
		}
	});
	
	$("#manage_item_contents").delegate("#buttonRight", "click", function(e) {
		var box_id = $(this).attr("class");
		if (box_id == null) {
			box_id = "group";
		}
		var selectedOpts = $("#" + box_id + "_items_assigned option:selected");
		if (selectedOpts.length == 0) {
			e.preventDefault();
		}
		
		$("#" + box_id + "_items_available").append($(selectedOpts).clone());
		$(selectedOpts).remove();
		e.preventDefault();
	});
	
	$("#manage_item_contents").delegate("#buttonLeft", "click", function(e) {
		var box_id = $(this).attr("class");
		if (box_id == null) {
			box_id = "group";
		}
		var selectedOpts = $("#" + box_id + "_items_available option:selected");
		if (selectedOpts.length == 0) {
			e.preventDefault();
		}
		
		$("#" + box_id + "_items_assigned").append($(selectedOpts).clone());
		$(selectedOpts).remove();
		e.preventDefault();
	});
	
	$("#manage_item_contents").delegate("#submit_items", "click", function(e) {
		var arr = [ "group", "source", "destination", "services" ];
		$.each(arr, function(index, box_id) {
			var options = $("#" + box_id + "_items_assigned option");
			var items = "";
			
			for (i=0; i<options.length; i++) {
				items += options[i].value + ";";
			}
			$("#" + box_id + "_items").val(items);
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

	$("#table_edits.grab tbody").sortable({
		helper: fixHelperModified,
		start: function() {
			$(this).parent().addClass("grabbing");
		},
		stop: function() {
			$(this).parent().removeClass("grabbing");
			updateIndex;
			
			var items = $("#table_edits.grab tr");
			var linkIDs = [items.size()];
			var index = 0;
			
			items.each(function(intIndex) {
				linkIDs[index] = $(this).attr("id");
				index++;
			});
			var new_sort_order = linkIDs.join(";");
			
			/** Update the database */
	        var $this 				= $(this);
	        var item_type			= $("#table_edits").attr("name");
	        var policy_type			= getUrlVars()["type"];
	        var server_serial_no	= getUrlVars()["server_serial_no"];
	
			var form_data = {
				item_id: "",
				item_type: item_type,
				policy_type: policy_type,
				server_serial_no: server_serial_no,
				sort_order: new_sort_order,
				action: "update_sort",
				is_ajax: 1
			};
	
			$.ajax({
				type: "POST",
				url: "fm-modules/facileManager/ajax/processPost.php",
				data: form_data,
				success: function(response)
				{
					if (response != "Success") {
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
							$("#manage_item_contents").html("<h2>Sort Order Results</h2>" + response + "<br /><input type=\"submit\" value=\"OK\" class=\"button\" id=\"cancel_button\" />");
						}
					}
				}
			});
		}
	}).disableSelection();
	
});
';
?>