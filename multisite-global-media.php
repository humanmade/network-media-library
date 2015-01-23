<?php
/**
 * Plugin Name: Multisite Global Media
 * Description: Share an media library across multisite network
 * Network:     true
 * Plugin URI:
 * Version:     0.0.1
 * Author:      Frank Bültge
 * Author URI:  http://bueltge.de/
 * License:     GPLv2+
 * License URI: ./assets/license.txt
 *
 * Php Version 5.3
 *
 * @package WordPress
 * @author  Frank Bültge <frank@bueltge.de>
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version 2015-01-22
 */

namespace Multisite_Global_Media;

// Exit if accessed directly
defined( 'ABSPATH' ) || die();

/**
 * Id of side inside the network, there store the global media
 *
 * @var    integer
 * @since  2015-01-22
 */
const blog_id = 3;

add_filter( 'media_upload_tabs', __NAMESPACE__ . '\add_tab' );
/**
 * Add tab in the modal of "Add media"
 *
 * @since  2015-01-22
 *
 * @param  $tabs
 *
 * @return array
 */
function add_tab( $tabs ) {

	$media_tab = array( 'global_media' => __( 'Global Media' ) );
	$tabs      = array_merge( $tabs, $media_tab );

	return $tabs;
}

// Hook on "media_upload_$type"
add_action( 'media_upload_global_media', __NAMESPACE__ . '\custom_upload' );
/**
 * Load custom process in media popup on custom tab
 *
 * @since  2015-01-22
 */
function custom_upload() {

	$errors = array();
	if ( ! empty( $_POST ) ) {
		$return = media_upload_form_handler();

		if ( is_string( $return ) ) {
			return $return;
		}
		if ( is_array( $return ) ) {
			$errors = $return;
		}
	}

	return wp_iframe( __NAMESPACE__ . '\media_process', $errors );
}

/*
 * Custom media process
 *
 * media_process() contains the code for what you want to display.
 * This function MUST start with the word 'media' in order for the proper CSS to load.
 *
 * Switch to site with ID 3 to get all media from this.
 * In this example is side with ID 3 the global library for the network.
 *
 * media_upload_library() show the library, a custom function is helpful, if the default is not helpful enough
 *
 * @since  2015-01-22
 */
function media_process( $errors ) {

	$blog_id = (int) blog_id;
	switch_to_blog( $blog_id );
	//	media_upload_library();
	media_upload_library_form( $errors );
	restore_current_blog();
}
