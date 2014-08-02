jQuery(document).ready(function( $ ) {
	var img = $('.jfg-single-image');

	$(window).load(function() {
		var modalBody = $("#image-add-person .modal-dialog .modal-body");
		// when the image is clicked on
		$("p.attachment").click(function(e) {
			$("#image-add-person").modal({
				'backdrop': 'static'
			});
			
			// show the modal
			$("#image-add-person").modal('show');
			
			// reset the select option
			$("#image-add-person").on('hidden.bs.modal', function (e) {
				$("#photo-people-select").val(-1);
				$("h4#response-message").remove();
				$("button#submit-photo-tag").show();
			});
			
			// get width and height of image as displayed
			var width = $(img).width();
			var height = $(img).height();
			
			// get the x and y pixal coordinates
			var offset = $(this).offset();
			relativeX = (e.pageX - offset.left);
			relativeY = (e.pageY - offset.top);
			
			// convert pixal coordinates to range values from 0-1
			xCoord = relativeX / width;
			yCoord = relativeY / height;
			
			xSpot = xCoord.toPrecision(5);
			ySpot = yCoord.toPrecision(5);
			//console.log(" Before Click - X: " + xSpot + "  Y: " + ySpot);
			
			// when the Save button is clicked
			$("button#submit-photo-tag").unbind('click').click(function(event) {
				var selectedName = $("#photo-people-select").val();
				var personName = $("#person-name").val();
				var attachmentID = $("span#photo-attachment-id").data("photo");
				
				if(selectedName == '-1') {
					if(!personName) {
						name = '';
					} else {
						name = personName;
					}
				} else {
					name = selectedName;
				}
				
				var posting = $.post( jfg_ajax.ajaxurl, {
					action: 'jfg_photo_tag',
					x: xSpot,
					y: ySpot,
					person: name,
					image: attachmentID,
					security: jfg_ajax.ajaxnonce
				});
				
				// when the response comes back
				posting.done(function( response ) {
					// if it is a success display the success message, otherwise the error
					if( response.success ) {
						modalBody.append('<h4 id="response-message" class="text-danger">' +response.data.message+'</h4>');
						$("button#submit-photo-tag").hide();
					}
					else if( !response.sucess ) {
						modalBody.append('<h4 id="response-message" class="text-danger">' +response.data.message+'</h4>');
					}
				});
			});
		});
	});
});