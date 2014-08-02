<?php
/*
Plugin Name: Jacobs Family Genealogy Media Management
Version: 1.0
Description: Manage all the media for the Jacobs Family site
Author: Nate Jacobs
Text Domain: jacobs-family
Domain Path: /languages
*/

class Jacobs_Media_Management {
	
	public function __construct() {
		add_action( 'pre_get_posts',  [ $this, 'pre_get_posts' ] );
		add_action( 'add_attachment', [ $this, 'add_attachment' ] );
		add_action( 'generate_rewrite_rules', [ $this, 'attachment_rewrite_rules' ] );
		add_filter( 'attachment_link', [ $this, 'pre_attachment_permalink' ], 10, 2 );
		add_filter( 'wp_editor_settings', [ $this, 'add_tinymce_attachment_edit' ], 10, 2 );
		add_action( 'add_meta_boxes_attachment', [ $this, 'add_custom_meta_boxes' ] );
		add_action( 'init', [ $this, 'register_taxonomies' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'image_tag_scripts' ] );
		add_action( 'wp_ajax_jfg_photo_tag', [ $this, 'get_photo_tag' ] );
		add_action( 'deleted_term_relationships', [ $this, 'remove_photo_tag' ], 10, 2 );
		
		if( !is_admin() )
		{
			add_filter( 'get_image_tag_class', function( $class, $id, $align, $size ) {
				$class .= ' img-responsive';
				return $class;
			});
		}
		
		// load includes
		add_action( 'plugins_loaded', function(){
			require_once( trailingslashit( plugin_dir_path( __FILE__ ) ) . 'template-tags.php' );
		});
		
		// add a class to the paragraph tag for attachment types
		add_filter( 'the_content', function( $content ){
			if ( is_attachment() ) {
				return preg_replace('/<p([^>]+)?>/', '<p$1 class="lead">', $content, 1);
			} else {
				return $content;
			}
		});
		
		add_filter( 'posts_request', function($input){
			//var_dump($input);
		
		    return $input;
		});
	}
	
	// borrowed from https://github.com/helenhousandi/snap
	public function pre_get_posts( $query ) {
		if( $query->is_main_query() && ( isset($query->query['category_name']) && $query->query['category_name'] == 'family-photo' ) ) {
			$query->set( 'post_type', [ 'attachment' ] );
			$query->set( 'post_status', [ 'publish', 'inherit' ] );
		} elseif($query->is_main_query() && is_tax() && (isset($query->query_vars['photo-people']) || isset($query->query_vars['photo-location']) ) ) {
			$query->set( 'post_status', [ 'publish', 'inherit' ] );
		}
	}
	
	// borrowed from https://github.com/helenhousandi/snap
	public function add_attachment( $post_id ) {
		// Only alter the publish date when first uploading
		add_filter( 'wp_update_attachment_metadata', array( $this, 'update_image_date' ), 10, 2 );
	}
	
	// borrowed from https://github.com/helenhousandi/snap
	public function update_image_date( $data, $post_id ) {
		// No loops :)
		// We don't add this back because we only want it to run once per attachment
		remove_filter( 'wp_update_attachment_metadata', array( $this, 'update_image_date' ), 10, 2 );

		// If the created-date is saved in EXIF data
		if ( isset( $data['image_meta']['created_timestamp'] ) && 0 !== $data['image_meta']['created_timestamp'] ) {
			// Save the WordPress-generated publish date
			$original = get_post_field( 'post_date', $post_id );
			update_post_meta( $post_id, '_original_upload', $original );

			$post_array = array(
				'ID' => $post_id,
				'post_date' => date( 'Y-m-d H:i:s', $data['image_meta']['created_timestamp'] ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $data['image_meta']['created_timestamp'] ),
			);

			wp_update_post( $post_array );
		}

		return $data;
	}
	
	/** 
	 *	from http://wordpress.stackexchange.com/questions/14924/
	 *
	 *	@author		Nate Jacobs
	 *	@date		7/29/14
	 *	@since		1.0
	 *
	 *	@param		object $wp_rewrite
	 */
	public function attachment_rewrite_rules( $wp_rewrite ){
		$new_rules = [];
		$new_rules['media/(\d*)$'] = 'index.php?attachment_id=$matches[1]';
		$wp_rewrite->rules = $new_rules + $wp_rewrite->rules;
	}
	
	/** 
	 *	
	 *
	 *	@author		Nate Jacobs
	 *	@date		7/29/14
	 *	@since		1.0
	 *
	 *	@param		string $permalink
	 *	@param		int $post_id
	 */
	public function pre_attachment_permalink($permalink, $post_id) {
	    if(wp_attachment_is_image($post_id)) {
	        $permalink = trailingslashit(home_url('media')).$post_id; 
	    }
	    
	    return $permalink;      
	}
	
	/** 
	 *	Convert attachment description box to use full TinyMCE editor.
	 *
	 *	@author		Nate Jacobs
	 *	@date		7/29/14
	 *	@since		1.0
	 *
	 *	@param		array $settings
	 *	@param		string $editor_id
	 */
	public function add_tinymce_attachment_edit( $settings, $editor_id ) {
		if( 'attachment_content' === $editor_id ) {
			$settings['tinymce'] = true;
			$settings['quicktags'] = false;
			$settings['textarea_rows'] = 10;
		}
		
		return $settings;
	}
	
	/** 
	 *	
	 *
	 *	@author		Nate Jacobs
	 *	@date		7/29/14
	 *	@since		1.0
	 *
	 *	@param		
	 */
	public function add_custom_meta_boxes( $post ) {
		if(wp_attachment_is_image($post->ID)){
			add_meta_box(
		        'photo-date-taken',
		        'Photo Details',
		        function($post){
		        		$image_meta = wp_get_attachment_metadata();
					if(!empty($image_meta['image_meta']['created_timestamp'])) {
						$date = date( 'l F d, Y @ h:i a', $image_meta['image_meta']['created_timestamp'] );
					} else {
						$date = '';
					}
			        ?>
			        		<label for="photo_date_time">Date/Time of Photo</label>
			        		<input type="text" class="widefat" id="photo_date_time" name="photo_date_time" value="<?php echo $date; ?>">
			        <?php
		        },
		        'attachment',
		        'normal'
		    );
		}
	}
	
	/** 
	 *	
	 *
	 *	@author		Nate Jacobs
	 *	@date		7/29/14
	 *	@since		1.0
	 *
	 *	@param		
	 */
	public function register_taxonomies()
	{
		register_taxonomy_for_object_type( 'category', 'attachment' );
		register_taxonomy( 'photo-location', 'attachment',
		    [
		        'labels' =>  [
		            'name'              => 'Locations',
		            'singular_name'     => 'Location',
		            'search_items'      => 'Search Locations',
		            'all_items'         => 'All Locations',
		            'edit_item'         => 'Edit Locations',
		            'update_item'       => 'Update Location',
		            'add_new_item'      => 'Add New Location',
		            'new_item_name'     => 'New Location Name',
		            'menu_name'         => 'Locations'
		        ],
		        'hierarchical' => false,
		        'sort' => true,
		        'show_admin_column' => true
		    ]
		);
		
		register_taxonomy( 'photo-people', 'attachment',
		    [
		        'labels' =>  [
		            'name'              => 'People',
		            'singular_name'     => 'Person',
		            'search_items'      => 'Search People',
		            'all_items'         => 'All People',
		            'edit_item'         => 'Edit People',
		            'update_item'       => 'Update People',
		            'add_new_item'      => 'Add New Person',
		            'new_item_name'     => 'New Person Name',
		            'menu_name'         => 'People'
		        ],
		        'hierarchical' => false,
		        'sort' => true,
		        'show_admin_column' => true
		    ]
		);	
	}
	
	/** 
	 *	
	 *
	 *	@author		Nate Jacobs
	 *	@date		7/30/14
	 *	@since		1.0
	 *
	 *	@param		
	 */
	public function image_tag_scripts()
	{
		wp_enqueue_script( 'jfg-display-photo-tag', trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/js/display-photo-tags.js', [ 'jquery' ], '1.0.0', true );
		wp_enqueue_script( 'jquery-taggd', trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/js/jquery.taggd.js', [ 'jquery' ], '1.0.0', true );
		wp_enqueue_style( 'jquery-taggd-css', trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/css/jquery.taggd.css', [], '1.0.0' );
		
		if(is_user_logged_in()) {
			wp_enqueue_script( 'jfg-photo-tag', trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/js/photo-tag.js', [ 'jquery' ], '1.0.0', true );
			wp_localize_script( 
	             'jfg-photo-tag',
	             'jfg_ajax',
	             [
				 	'ajaxurl' => admin_url( 'admin-ajax.php' ),
				 	'ajaxnonce' => wp_create_nonce( 'ajax_validation' )
				 ]
	        );
        }
	}
	
	/** 
	 *	
	 *
	 *	@author		Nate Jacobs
	 *	@date		8/1/14
	 *	@since		1.0
	 */
	public function get_photo_tag()
	{
		check_ajax_referer('ajax_validation', 'security');
		$return = false;
		
		if(empty($_POST['person'])) {
			$return = false;
		} else {
			$person_id = $_POST['person'];
			
			if(!is_numeric($_POST['person'])) {
				$new_term = wp_insert_term($_POST['person'], 'photo-people');
				
				if(isset($new_term) && is_wp_error($new_term)) {
					$person_id = false;
				} else {
					$person_id = $new_term['term_id'];
				}
			}
			
			if(false !== $person_id) {
				wp_set_object_terms( (int) $_POST['image'], (int) $person_id, 'photo-people', true);
				
				$key_name = '_jfg_photo_people';
				$image_id = (int) $_POST['image'];
				$person_name = get_term_by('id', (int) $person_id, 'photo-people');
				
				// get post meta
				$existing_image_meta = get_post_meta($image_id, $key_name, true);
				
				if(!empty($existing_image_meta)) {
					// if it exists: decode the array, push the new array into it, and encode the array again
					$meta = json_decode($existing_image_meta, true);
					
					$meta[] = ['x' => $_POST['x'], 'y' => $_POST['y'], 'person_id' => $person_id, 'text' => $person_name->name];
					
					$updated_meta = json_encode($meta);
					$return = update_post_meta($image_id, $key_name, wp_slash($updated_meta));
				} else {
					// if not: add the new record by encoding it
					$image_data[] = ['x' => $_POST['x'], 'y' => $_POST['y'], 'person_id' => $person_id, 'text' => $person_name->name];
					$new_meta = json_encode($image_data);
					$return = add_post_meta($image_id, $key_name, wp_slash($new_meta), true);
					//$return = true;
				}
			}
			else {
				$return = false;
			}
			
		}
		
		if($return) {
			wp_send_json_success(['message' => 'The photo was tagged successfully!']);
		} else {
			wp_send_json_error(['message' => 'There was a problem adding the tag. Please refresh the page and try again.']);
		}
	}
	
	/** 
	 *	
	 *
	 *	@author		Nate Jacobs
	 *	@date		8/2/14
	 *	@since		1.0
	 *
	 *	@param		int	$object_id
	 *	@param		array	$tt_ids
	 */
	public function remove_photo_tag( $object_id, $tt_ids )
	{
		// check and see if the object is an image
		if(wp_attachment_is_image($object_id)){
			// get the image meta associated with the image
			$existing_image_meta = get_post_meta($object_id, '_jfg_photo_people', true);
			
			// if the meta exists for the image
			if(!empty($existing_image_meta)) {
				// decode the image tag meta
				$tax_meta = json_decode($existing_image_meta);
				// loop through the deleted tax terms
				foreach($tt_ids as $tax_ids) {
					// get the term id for the tax id
					$term = get_term_by('term_taxonomy_id', $tax_ids, 'photo-people');
					// loop through each of the image tag meta
					foreach($tax_meta as $key => $meta){
						// if the image meta person id is the same as the deleted term id
						if($term->term_id == $meta->person_id){
							// unset the array for that term id
							unset($tax_meta[$key]);
						}
					}
				}
				// encode the new meta
				$updated_meta = json_encode($tax_meta);
				// update the post meta
				update_post_meta($object_id, '_jfg_photo_people', wp_slash($updated_meta));
			}
		}
	}
}

$jacobs_media_manage = new Jacobs_Media_Management();