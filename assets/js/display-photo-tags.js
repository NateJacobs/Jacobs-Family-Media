jQuery(document).ready(function( $ ) {
	var img = $('.jfg-single-image');
	
	var data = [];
	var settings = [];
	
	$(window).load(function() {
		// loop through each image and add the settings and data elements
		$(img).each(function(i, e) {
			var $e = $(e);
			$e.taggd(settings[i]);
			$e.taggd('items', data[i]);
		});
		
		// hide each taggd item on page load
		$('.taggd-item').each(function(i, e){
			$(e).hide();
		});
		
		// when the taggd wrapper is hovered over, show the taggd items
		// when taggd wrapper is no longer hovered over, turn off tags
		$(".taggd-wrapper").hover(function() {
		  $('.taggd-item').show();
		}, function(){
			$('.taggd-item').hide();
		});
	});
	
	// add the taggd items
	var nameJson = $.parseJSON($("div#photo-people-json").text());
	
	if(nameJson == null) {
		data.push([{x:0,y:0}]);
	} else {
		data.push(nameJson);
	}
	
	// set the settings
	settings.push({
		align: { 'y': 'bottom' },
		offset: { 'top': -15 },
		handlers: {
			'mouseenter': 'show',
			'mouseleave': 'hide'
		}
	});
});