$(document).ready(function() {
	
	/* Zone reloads */
    $('#zones').delegate('form','click tap',function(e){
        var $this 	= $(this);
        domain_id	= $this.attr('id');

//		toggleLayer('display_zone_reload', 'block');
//		toggleLayer('display_zone_reload_contents', 'block');
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
    $('#table_edits').delegate('img.clone_remove','click tap',function(e){
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
//						$row_id.parent().parent().parent().addClass("response");
					}
				}
			});
		}
		
		return false;
    });

});


function manageDestinations() {
	var desttype = document.getElementById('cfg_destination').value;
	var dest = document.getElementById('destination_option');
	var syslog = document.getElementById('syslog_options');
	
	if (desttype == 'file') {
		dest.style.display = 'block';
		syslog.style.display = 'none';
	} else if (desttype == 'syslog') {
		dest.style.display = 'none';
		syslog.style.display = 'block';
	} else {
		dest.style.display = 'none';
		syslog.style.display = 'none';
	}
}

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

