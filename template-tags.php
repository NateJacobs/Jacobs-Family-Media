<?php

function jfg_get_image_size_links() {
	/* If not viewing an image attachment page, return. */
	if( !wp_attachment_is_image( get_the_ID() ) ) {
		return;
	}

	/* Set up an empty array for the links. */
	$links = [];

	/* Get the intermediate image sizes and add the full size to the array. */
	$sizes = get_intermediate_image_sizes();
	$sizes[] = 'full';

	/* Loop through each of the image sizes. */
	foreach( $sizes as $size ) {
		/* Get the image source, width, height, and whether it's intermediate. */
		$image = wp_get_attachment_image_src( get_the_ID(), $size );

		/* Add the link to the array if there's an image and if $is_intermediate (4th array value) is true or full size. */
		if ( !empty( $image ) && ( true == $image[3] || 'full' == $size ) ) {
			$size_name = ucwords($size);
			$links[] = "<a class='image-size-link' href='$image[0]'>$size_name ($image[1] &times; $image[2])</a>";
		}
	}

	/* Join the links in a string and return. */
	return join( ' <span class="sep">/ </span>', $links );
}