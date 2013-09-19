$(document).ready(function() {
	
	var KEYCODE_ENTER = 13;
	var KEYCODE_ESC = 27;
	
	$(document).keyup(function(e) {
		if (e.keyCode == KEYCODE_ESC) { $('#cancel_button').click(); } 
	});    
	    

	$(function() {
		$( ".datepicker" ).datepicker();
	});
	
	$('input:text, input:password, select').first().focus();
	
	$("#loginbtn").click(function() {
	
		var action = $("#loginform").attr('action');
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
				if(response == 'failed') {
					$("#password").val('');
					if ($('#username').val() == '') {
						$('#username').focus();
					} else {
						$('#password').focus();
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
	
		$("#message").html('<p class="success">Processing...please wait.</p>');
		
		var action = $("#forgotpwd").attr('action');
		var form_data = {
			user_login: $("#user_login").val(),
			is_ajax: 1
		};
		
		$("#user_login").val('');	
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
	
	/* Form adds */
    $('#plus').click(function() {
        var $this 		= $(this);
        item_type		= $('#table_edits').attr('name');
        item_sub_type	= $this.attr('name');
        item_id			= $('#plus').attr('name');
		var server_serial_no	= getUrlVars()["server_serial_no"];

		$('#manage_item').fadeIn(200);
		$('#manage_item_contents').fadeIn(200);
		$('#response').fadeOut();
		$this.parent().parent().removeClass("response");
		
		var form_data = {
			add_form: true,
			item_type: item_type,
			item_sub_type: item_sub_type,
			item_id: item_id,
			server_serial_no: server_serial_no,
			is_ajax: 1
		};

		$.ajax({
			type: 'POST',
			url: 'fm-modules/facileManager/ajax/getData.php',
			data: form_data,
			success: function(response)
			{
				$('#manage_item_contents').html(response);
				$('.form-table input:text, .form-table select').first().focus();
			}
		});
		
		return false;
    });
    
	/* Form edits */
    $('#table_edits').delegate('a.edit_form_link', 'click tap', function(e) {
        var $this 		= $(this);
        var $row_id		= $this.parent().parent();
        item_id			= $row_id.attr('id');
        item_type		= $('#table_edits').attr('name');
        item_sub_type	= $this.attr('name');
        var server_serial_no	= getUrlVars()["server_serial_no"];
        var view_id		= getUrlVars()["view_id"];

		$('#manage_item').fadeIn(200);
		$('#manage_item_contents').fadeIn(200);
		$('#response').fadeOut();
		$row_id.parent().parent().parent().removeClass("response");
		
		var form_data = {
			item_id: item_id,
			item_type: item_type,
			item_sub_type: item_sub_type,
			server_serial_no: server_serial_no,
			view_id: view_id,
			is_ajax: 1
		};

		$.ajax({
			type: 'POST',
			url: 'fm-modules/facileManager/ajax/getData.php',
			data: form_data,
			success: function(response)
			{
				$('#manage_item_contents').html(response);
				$('.form-table input, .form-table select').first().focus();
			}
		});
		
		return false;
    });

	/* Form deletes */
    $('#table_edits').delegate('a.delete', 'click tap', function(e) {
        var $this 		= $(this);
        var $row_id		= $this.parent().parent();
        item_id			= $row_id.attr('id');
        item_type		= $('#table_edits').attr('name');
        item_sub_type	= $this.attr('name');
        var log_type	= getUrlVars()["type"];
        var server_serial_no	= getUrlVars()["server_serial_no"];
//        server_serial_no		= $('#configtypesmenu').attr('name');

		var form_data = {
			item_id: item_id,
			item_type: item_type,
			item_sub_type: item_sub_type,
			log_type: log_type,
			server_serial_no: server_serial_no,
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
						$row_id.css({"background-color":"#D98085"});
						$row_id.fadeOut("slow", function() {
							$row_id.remove();
						});
					} else {
						var eachLine = response.split("\n");
						if (eachLine.length <= 2) {
							$('#body_container').animate({marginTop: '4em'}, 200);
							$('#response').html('<p class="error">'+response+'</p>');
							$('#response').fadeIn(200);
							$('#response').delay(3000).fadeOut(400, function() {
								$('#body_container').animate({marginTop: '2.2em'}, 200);
							});
						} else {
							$('#manage_item').fadeIn(200);
							$('#manage_item_contents').fadeIn(200);
							$('#manage_item_contents').html('<h2>Delete Results</h2>' + response + '<br /><input type="submit" value="OK" class="button cancel" id="cancel_button" />');
						}
					}
				}
			});
		}
		
		return false;
    });

    /* Cancel button */
    $('#manage_item_contents').delegate('#cancel_button', 'click tap', function(e) {
		$('#manage_item').fadeOut(200);
		$('#manage_item_contents').fadeOut(200).html();
	});
	
	
	$('#save_fm_settings, #save_module_settings').click(function() {
		$.ajax({
			type: "POST",
			url: 'fm-modules/facileManager/ajax/processPost.php',
			data: $('#manage').serialize(),
			success: function(response)
			{
				if(response == 'force_logout') {
					window.location = '?logout';
				} else {
					$('#body_container').animate({marginTop: '4em'}, 200);
					$('#response').html(response);
					$('#response').fadeIn(200);
					$('#response').delay(3000).fadeOut(400, function() {
						$('#body_container').animate({marginTop: '2.2em'}, 200);
					});
				}
			}
		});
		
		return false;
	});
	
	/* Account settings */
    $('.account_settings').click(function() {
        var $this 		= $(this);
        user_id			= $this.attr('id');

		$('#manage_item').fadeIn(200);
		$('#manage_item_contents').fadeIn(200);
		$('#response').fadeOut();
		
		var form_data = {
			user_id: user_id,
			is_ajax: 1
		};

		$.ajax({
			type: 'POST',
			url: 'fm-modules/facileManager/ajax/getData.php',
			data: form_data,
			success: function(response)
			{
				$('#manage_item_contents').html(response);
				$('.form-table input').first().focus();
			}
		});
		
		return false;
    });

	/* Account password reset */
    $('.reset_password').click(function() {
        var $this 		= $(this);
        user_id			= $this.attr('id');

		var form_data = {
			user_id: user_id,
			reset_pwd: true,
			is_ajax: 1
		};

		$.ajax({
			type: 'POST',
			url: 'fm-modules/facileManager/ajax/getData.php',
			data: form_data,
			success: function(response)
			{
				$('#body_container').animate({marginTop: '4em'}, 200);
				$('#response').html(response);
				$('#response').fadeIn(200);
				$('#response').delay(3000).fadeOut(400, function() {
					$('#body_container').animate({marginTop: '2.2em'}, 200);
				});
			}
		});
		
		return false;
    });

	/* Admin Tools */
    $('#admin-tools').delegate('form input.button:not("#import-records, #cancel, #import, #db-backup")','click tap',function(e){
        var $this 	= $(this);
        task		= $this.attr('id');
        item		= $this.attr('name');

		var form_data = {
			task: task,
			item: item,
			is_ajax: 1
		};

		$('#manage_item').fadeIn(200);
		$('#manage_item_contents').fadeIn(200);
		$('#manage_item_contents').html('<p>Processing...</p>');
		
		$.ajax({
			type: 'POST',
			url: 'fm-modules/facileManager/ajax/processTools.php',
			data: form_data,
			success: function(response)
			{
				$('#manage_item_contents').html(response);
			}
		});
		
		return false;
    });

	$("#topheadpartright .help_link").click(function() {
		var $body_right		= $('#body_container').css('right');
		var $help_right		= $('#help').css('right');
		
		if ($body_right == '304px') {
			$('#body_container').animate({right: '0'}, 500);
			$('#help').hide("slide", { direction: "right" }, 500);
		} else {
			$('#body_container').animate({right: '19em'}, 500);
			$('#help').show("slide", { direction: "right" }, 500);
		}
		
		return false;
	});
	
	$("#manage_item_contents").delegate('#user_template_only', 'click tap', function(e) {
		$('input[type="submit"]').removeAttr('disabled');
	});
	
	$("#auth_method").change(function() {
		if ($(this).val() == 2) {
			$('#auth_ldap_options').show('slow');
		} else {
			$('#auth_ldap_options').slideUp();
		}
	});
	
	$("#ldap_group_require").click(function() {
		if ($(this).is(':checked')) {
			$('#ldap_group_require_options').show('slow');
		} else {
			$('#ldap_group_require_options').slideUp();
		}
	});
	
	$("#mail_enable").click(function() {
		if ($(this).is(':checked')) {
			$('#fm_mailing_options').show('slow');
		} else {
			$('#fm_mailing_options').slideUp();
		}
	});
	
	$("#mail_smtp_auth").click(function() {
		if ($(this).is(':checked')) {
			$('#mail_smtp_auth_options').show('slow');
		} else {
			$('#mail_smtp_auth_options').slideUp();
		}
	});
	
	$("#help_topbar img.popout").click(function() {
		$("#topheadpartright .help_link").click();
		window.open('help','1356124444538','width=700,height=500,toolbar=0,menubar=0,location=0,status=0,scrollbars=1,resizable=1,left=0,top=0');
		return false;
	});
	
	$("#help_topbar img.close").click(function() {
		$("#topheadpartright .help_link").click();
	});
	
	$(function () {
		$('.checkall').on('click', function () {
			$(this).closest('fieldset').find(':checkbox').prop('checked', this.checked);
		});
	});

	$(function() {
		$( ".datepicker" ).datepicker();
	});

});

function del(msg){
	return confirm(msg);
}

function checkPasswd(pass, pwdbutton) {
	var user = document.getElementById('user_login');
	var strength = document.getElementById('passwd_check');
	var button = document.getElementById(pwdbutton);
	var strongRegex = new RegExp("^(?=.{8,})(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*\\W).*$", "g");
	var mediumRegex = new RegExp("^(?=.{7,})(((?=.*[A-Z])(?=.*[a-z]))|((?=.*[A-Z])(?=.*[0-9]))|((?=.*[a-z])(?=.*[0-9]))).*$", "g");
	var enoughRegex = new RegExp("(?=.{6,}).*", "g");
	var pass = document.getElementById(pass);
	var pwd1 = document.getElementById('user_password');
	var pwd2 = document.getElementById('cpassword');
	if (pwd1.value.length==0) {
		strength.innerHTML = 'No Password';
		strength.style.color = 'black';
		button.disabled = true;
	} else {
		if (false == enoughRegex.test(pwd1.value)) {
			strength.innerHTML = 'More Characters';
			strength.style.color = 'black';
			button.disabled = true;
		} else if (strongRegex.test(pwd1.value)) {
			strength.innerHTML = 'Strong Password!';
			strength.style.color = 'green';
			button.disabled = false;
		} else if (mediumRegex.test(pwd1.value)) {
			strength.innerHTML = 'Medium Password!';
			strength.style.color = 'orange';
			button.disabled = true;
		} else {
			strength.innerHTML = 'Weak Password!';
			strength.style.color = 'red';
			button.disabled = true;
		}
	}
	if (pwd2.value.length!=0 && pwd1.value!=pwd2.value) {
		strength.innerHTML = 'Passwords do not match';
		strength.style.color = 'red';
		button.disabled = true;
	} else if (pwd2.value.length==0) {
		button.disabled = true;
	} else if (user.value.length==0) {
		strength.innerHTML = 'No Username Specified';
		strength.style.color = 'black';
		button.disabled = true;
	}
}

function exchange(el){
	var ie=document.all&&!document.getElementById? document.all : 0;
	var toObjId=/b$/.test(el.id)? el.id.replace(/b$/,'') : el.id+'b';
	var toObj=ie? ie[toObjId] : document.getElementById(toObjId);
	if(/b$/.test(el.id))
		toObj.innerHTML=el.value;
	else{
		toObj.style.width=el.offsetWidth+7+'px';
		toObj.value=el.innerHTML;
	}
	el.style.display='none';
	toObj.style.display='inline';
}

function toggleLayer(whichLayer, view) {
   if (document.getElementById) {
      // this is the way the standards work
      var style2 = document.getElementById(whichLayer).style;
      style2.display = style2.display? "":view;
   }
   else if (document.all) {
      // this is the way old msie versions work
      var style2 = document.all[whichLayer].style;
      style2.display = style2.display? "":view;
   }
   else if (document.layers) {
      // this is the way nn4 works
      var style2 = document.layers[whichLayer].style;
      style2.display = style2.display? "":view;
   }
}

function validateNumber(event) {
	// Allow: backspace, delete, tab, escape, and enter
	if ( event.keyCode == 46 || event.keyCode == 8 || event.keyCode == 9 || event.keyCode == 27 || event.keyCode == 13 || 
		// Allow: Ctrl+A
		(event.keyCode == 65 && event.ctrlKey === true) || 
		// Allow: home, end, left, right
		(event.keyCode >= 35 && event.keyCode <= 39)) {
			// let it happen, don't do anything
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
		inputbox.style.display = 'block';
	} else {
		inputbox.style.display = 'none';
	}
}

function getUrlVars() {
	var vars = {};
	var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function(m,key,value) {
		vars[key] = value;
	});
	return vars;
}

