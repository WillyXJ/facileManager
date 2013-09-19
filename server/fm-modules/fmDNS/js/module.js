$(document).ready(function() {
	
	/* Server config builds */
    $('#table_edits').delegate('#build', 'click tap', function(e) {
        var $this 	= $(this);
        server_id	= $this.parent().parent().attr('id');

		$('#body_container').animate({marginTop: '4em'}, 200);
		$('#response').html('<p>Processing Config Build...</p>');
		$('#response').fadeIn(200);
		
		var form_data = {
			server_id: server_id,
			action: 'build',
			is_ajax: 1
		};

		setTimeout(function() {
			$.ajax({
				type: 'POST',
				url: 'fm-modules/fmDNS/ajax/processReload.php',
				data: form_data,
				success: function(response)
				{
					var eachLine = response.split("\n");
					if (eachLine.length <= 2) {
						var myDelay = 6000;
						$('#response').html(response);
					} else {
						var myDelay = 0;
	
						$('#manage_item').fadeIn(200);
						$('#manage_item_contents').fadeIn(200);
						$('#manage_item_contents').html('<h2>Configuration Build Results</h2>' + response + '<br /><input type="submit" value="OK" class="button cancel" id="cancel_button" />');
					}
					
					$('#response').delay(myDelay).fadeOut(400, function() {
						$('#body_container').animate({marginTop: '2.2em'}, 200);
					});
				}
			});
		}, 500);
		
		return false;
    });

	/* Zone reloads */
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
			url: 'fm-modules/fmDNS/ajax/processReload.php',
			data: form_data,
			success: function(response)
			{
				$('#manage_item_contents').html(response);
		
				if (response.indexOf("failed") == -1) {
					$this.hide();
				}
			}
		});
		
		return false;
    });

	$('#show_output').click(function() {
		toggleLayer('import_output', 'block');
	});

	/* Add more records */
    $('#add_records').click(function() {
        var $this 		= $(this);
		var item_id		= getUrlVars()["domain_id"];
		var item_type	= getUrlVars()["record_type"];

		var form_data = {
			domain_id: item_id,
			record_type: item_type,
			is_ajax: 1
		};

		$.ajax({
			type: 'POST',
			url: 'fm-modules/fmDNS/ajax/addFormElements.php',
			data: form_data,
			success: function(response)
			{
				$('#more_records').append(response);
				$this.hide();
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
						$('#body_container').animate({marginTop: '4em'}, 200);
						$('#response').html('<p class="error">'+response+'</p>');
						$('#response').fadeIn(200);
						$('#response').delay(3000).fadeOut(400, function() {
							$('#body_container').animate({marginTop: '2.2em'}, 200);
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


function displayOptionPlaceholder() {
	var option_name = document.getElementById('cfg_name').value;

	var form_data = {
		get_option_placeholder: true,
		option_name: option_name,
		is_ajax: 1
	};

	$.ajax({
		type: 'POST',
		url: 'fm-modules/fmDNS/ajax/getData.php',
		data: form_data,
		success: function(response)
		{
			$('.value_placeholder').html(response);
		}
	});
}

