function createCookie(name,value,days) {
	if (days) {
		var date = new Date();
		date.setTime(date.getTime()+(days*24*60*60*1000));
		var expires = "; expires="+date.toGMTString();
	}
	else var expires = "";
	document.cookie = name+"="+value+expires+"; path=/";
}

function removeMessageBoxForever() {
	var $ = jQuery;
	$('#darkbackground').remove();
	var id = $(this).parents('.visiblebox').data('id');
	$(this).parents('.visiblebox').remove();
	// createCookie('popover_never_view', 'hidealways', 365);
	createCookie('popover-' + id, 'hidealways', 1);
	return false;
}

function removeMessageBox() {
	var $ = jQuery;
	$('#darkbackground').remove();
	$(this).parents('.visiblebox').remove();
	return false;
}

function showMessageBox() {
	var $ = jQuery;
	$('.visiblebox').css('visibility', 'visible');
	$('#darkbackground').css('visibility', 'visible');
}

function newShowMessageBox() {
}

function boardReady() {
	var $ = jQuery;
	$('.clearforever').click(removeMessageBoxForever);
	$('.nwp-msg').hover( function() {
		$(this).find('.claimbutton').removeClass('hide');
	}, function() {
		$(this).find('.claimbutton').addClass('hide');
	});
	window.setTimeout( showMessageBox, popover.messagedelay );
}

jQuery(window).load(boardReady);
jQuery(document).ready(function(){
	jQuery('.closebox').click(removeMessageBox);
});