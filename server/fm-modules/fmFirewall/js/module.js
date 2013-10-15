$(document).ready(function() {
	
	$("#manage_item_contents").delegate('#server_update_method', 'change', function(e) {
		if ($(this).val() == 'cron') {
			$('#server_update_port_option').slideUp();
		} else {
			$('#server_update_port_option').show('slow');
		}
	});
	
	$("#manage_item_contents").delegate('#service_type', 'change', function(e) {
		if ($(this).val() == 'icmp') {
			$('#tcpudp_option').slideUp();
			$('#tcp_option').slideUp();
			$('#icmp_option').show('slow');
		} else if ($(this).val() == 'tcp') {
			$('#icmp_option').slideUp();
			$('#tcpudp_option').show('slow');
			$('#tcp_option').show('slow');
		} else {
			$('#icmp_option').slideUp();
			$('#tcp_option').slideUp();
			$('#tcpudp_option').show('slow');
		}
	});
	
	$("#manage_item_contents").delegate('#object_type', 'change', function(e) {
		if ($(this).val() == 'address') {
			$('#netmask_option').slideUp();
		} else {
			$('#netmask_option').show('slow');
		}
	});
	
	$("#manage_item_contents").delegate('#buttonRight', 'click', function(e) {
		var selectedOpts = $('#group_items_assigned option:selected');
		if (selectedOpts.length == 0) {
			e.preventDefault();
		}
		
		$('#group_items_available').append($(selectedOpts).clone());
		$(selectedOpts).remove();
		e.preventDefault();
	});
	
	$("#manage_item_contents").delegate('#buttonLeft', 'click', function(e) {
		var selectedOpts = $('#group_items_available option:selected');
		if (selectedOpts.length == 0) {
			e.preventDefault();
		}
		
		$('#group_items_assigned').append($(selectedOpts).clone());
		$(selectedOpts).remove();
		e.preventDefault();
	});
	
	$("#manage_item_contents").delegate('#submit_items', 'click', function(e) {
		var options = $('#group_items_assigned option');
		var items = '';
		
		for (i=0; i<options.length; i++) {
			items += options[i].value + ';';
		}
		$('#group_items').val(items);
	});
	
});


