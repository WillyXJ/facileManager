$(document).ready(function() {
	
	more_clicks = 0;
	
	/* Zone reload button */
    $('#zones').delegate('form', 'click tap', function(e) {
        var $this 	= $(this);
        domain_id	= $this.attr('id');

		$('#manage_item').fadeIn(200);
		$('#manage_item_contents').fadeIn(200);
		$('#manage_item_contents').html('<p>Processing Reload...</p>');
		
		var form_data = {
			domain_id: domain_id,
			is_ajax: 1
		};

		$.ajax({
			type: 'POST',
			url: 'fm-modules/facileManager/ajax/processReload.php',
			data: form_data,
			success: function(response)
			{
				$('#manage_item_contents').html(response);
		
				if (response.toLowerCase().indexOf("failed") == -1 && response.toLowerCase().indexOf("you are not authorized") == -1) {
					$this.fadeOut(400);
					$this.parent().parent().removeClass('build');
				}
			}
		});
		
		return false;
    });

	/* Zone reload link */
    $('a.zone_reload').click(function(e) {
        var $this 	= $(this);
        domain_id	= $this.attr('id');

		$('#manage_item').fadeIn(200);
		$('#manage_item_contents').fadeIn(200);
		$('#manage_item_contents').html('<p>Processing Reload...</p>');
		
		var form_data = {
			domain_id: domain_id,
			is_ajax: 1
		};

		$.ajax({
			type: 'POST',
			url: 'fm-modules/facileManager/ajax/processReload.php',
			data: form_data,
			success: function(response)
			{
				$('#manage_item_contents').html(response);
		
				if (response.toLowerCase().indexOf("failed") == -1 && response.toLowerCase().indexOf("you are not authorized") == -1) {
					$('#response').delay(3000).fadeTo(200, 0.00, function() {
						$('#response').slideUp(400);
					});
				}
			}
		});
		
		return false;
    });

	/* Add more records */
    $('#add_records').click(function() {
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
			type: 'POST',
			url: 'fm-modules/fmDNS/ajax/addFormElements.php',
			data: form_data,
			success: function(response)
			{
				$('#more_records').append(response);
				more_clicks = more_clicks + 1;
			}
		});
		
		return false;
    });
    
	/* Zone clone deletes */
    $('#table_edits').delegate('img.clone_remove', 'click tap', function(e) {
        var $this 		= $(this);
        var $clone		= $this.parent();
        item_type		= $('#table_edits').attr('name');
        item_id			= $this.attr('id');

		var form_data = {
			item_id: item_id,
			item_type: item_type,
			action: 'delete',
			is_ajax: 1
		};

		if (confirm('Are you sure you want to delete this item?')) {
			$.ajax({
				type: 'POST',
				url: 'fm-modules/facileManager/ajax/processPost.php',
				data: form_data,
				success: function(response)
				{
					if (response == 'Success') {
						$clone.css({"background-color":"#D98085"});
						$clone.fadeOut("slow", function() {
							$clone.remove();
						});
					} else {
						$('#response').html('<p class="error">'+response+'</p>');
						$('#response')
							.css('opacity', 0)
							.slideDown(400, function() {
								$('#response').animate(
									{ opacity: 1 },
									{ queue: false, duration: 200 }
								);
							});
						$('#response').delay(3000).fadeTo(200, 0.00, function() {
							$('#response').slideUp(400);
						});
					}
				}
			});
		}
		
		return false;
    });

	$("#manage_item_contents").delegate('#server_update_method', 'change', function(e) {
		if ($(this).val() == 'cron') {
			$('#server_update_port_option').slideUp();
		} else {
			$('#server_update_port_option').show('slow');
		}
	});
	
	$("#manage_item_contents").delegate('#cfg_destination', 'change', function(e) {
		if ($(this).val() == 'file') {
			$('#syslog_options').slideUp();
			$('#destination_option').show('slow');
		} else if ($(this).val() == 'syslog') {
			$('#destination_option').slideUp();
			$('#syslog_options').show('slow');
		} else {
			$('#syslog_options').slideUp();
			$('#destination_option').slideUp();
		}
	});
	
});


function displayOptionPlaceholder(option_value) {
	var option_name = document.getElementById('cfg_name').value;
	var server_serial_no	= getUrlVars()['server_serial_no'];

	var form_data = {
		get_option_placeholder: true,
		option_name: option_name,
		option_value: option_value,
		server_serial_no: server_serial_no,
		is_ajax: 1
	};

	$.ajax({
		type: 'POST',
		url: 'fm-modules/fmDNS/ajax/getData.php',
		data: form_data,
		success: function(response)
		{
			$('.value_placeholder').html(response);
			if (response.toLowerCase().indexOf("address_match_element") == -1) {
				$("#manage #cfg_data").select2({
					width: '100px',
					minimumResultsForSearch: 10
				});
			}
		}
	});
}

