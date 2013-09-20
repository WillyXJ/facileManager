$(document).ready(function() {
	
	$('#set_sql_password').click(function() {
		if ($('#verbose').is(":checked") == false) {
			$('#body_container').animate({marginTop: '4em'}, 200);
			$('#response').html('<p>Processing...please wait.</p>');
			$('#response').fadeIn(200);
		} else {
			$('#manage_item').fadeIn(200);
			$('#manage_item_contents').fadeIn(200);
			$('#manage_item_contents').html('<h2>Password Change Results</h2><p>Processing...please wait.</p>');
		}
		
		$.ajax({
			type: "POST",
			url: 'fm-modules/fmSQLPass/ajax/processPost.php',
			data: $('#manage').serialize(),
			success: function(response)
			{
				if ($('#verbose').is(":checked") == false) {
					$('#response').html(response);
					$('#response').delay(3000).fadeOut(400, function() {
						$('#body_container').animate({marginTop: '2.2em'}, 200);
					});
				} else {
					$('#manage_item_contents').html(response);
				}
			}
		});
		
		return false;
	});
	
});

function toggle(source) {
	checkboxes = document.getElementsByName('group[]');
	for(var i in checkboxes)
		checkboxes[i].checked = source.checked;
}
