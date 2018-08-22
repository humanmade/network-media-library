<?php # -*- coding: utf-8 -*-
declare( strict_types=1 );

/**
 * Network Media Library plugin for WordPress
 *
 * This plugin originally started life as a fork of the Multisite Global Media plugin by Frank Bültge and Dominik
 * Schilling, but has since diverged entirely and retains little of the original functionality. If the Network Media
 * Library plugin doesn't suit your needs, try these alternatives:
 *
 * - [Multisite Global Media](https://github.com/bueltge/multisite-global-media)
 * - [Network Shared Media](https://wordpress.org/plugins/network-shared-media/)
 *
 * @package   network-media-library
 * @link      https://github.com/johnbillion/network-media-library
 * @author    John Blackbourn <john@johnblackbourn.com>, Dominik Schilling <d.schilling@inpsyde.com>, Frank Bültge <f.bueltge@inpsyde.com>
 * @copyright 2018 John Blackbourn
 * @license   https://opensource.org/licenses/MIT
 *
 * Plugin Name: Network Media Library
 * Description: Network Media Library provides a central media library that's shared across all sites on the Multisite network.
 * Network:     true
 * Plugin URI:  https://github.com/johnbillion/network-media-library
 * Version:     1.1.0
 * Author:      John Blackbourn, Dominik Schilling, Frank Bültge
 * License:     MIT
 * License URI: ./LICENSE
 * Text Domain: network-media-library
 * Domain Path: /languages
 * Requires PHP: 7.0
 */

namespace Network_Media_Library;

use WP_Post, WP_User;

/**
 * Don't call this file directly.
 */
defined( 'ABSPATH' ) || die();

/**
 * The ID of the site on the network which acts as the network media library. Change this value with the help
 * of the filter hook `network-media-library/site_id`.
 *
 * @var int The network media library site ID.
 */
const SITE_ID = 2;

/**
 * Returns the ID of the site which acts as the network media library.
 *
 * @return int The network media library site ID.
 */
function get_site_id() : int {
	$site_id = SITE_ID;

	/**
	 * Filters the ID of the site which acts as the network media library.
	 *
	 * @since 1.0.0
	 *
	 * @param int $site_id The network media library site ID.
	 */
	$site_id = (int) apply_filters( 'network-media-library/site_id', $site_id );

	/**
	 * Legacy filter which filters the ID of the site which acts as the network media library.
	 *
	 * This is provided for compatibility with the Multisite Global Media plugin.
	 *
	 * @since 0.0.3
	 *
	 * @param int $site_id The network media library site ID.
	 */
	$site_id = (int) apply_filters_deprecated( 'global_media.site_id', [ $site_id ], '1.0.0', 'network-media-library/site_id' );

	return $site_id;
}

/**
 * Switches the current site ID to the network media library site ID.
 */
function switch_to_media_site() {
	switch_to_blog( get_site_id() );
}

/**
 * Returns whether or not we're currently on the network media library site, regardless of any switching that's occurred.
 *
 * `$current_blog` can be used to determine the "actual" site as it doesn't change when switching sites.
 *
 * @return bool Whether we're on the network media library site.
 */
function is_media_site() : bool {
	return ( get_site_id() === (int) $GLOBALS['current_blog']->blog_id );
}

/**
 * Prevents attempts to attach an attachment to a post ID during upload.
 */
function prevent_attaching() {
	unset( $_REQUEST['post_id'] );
}

add_filter( 'user_has_cap', __NAMESPACE__ . '\filter_user_has_cap', 10, 4 );
/**
 * Filters a user's capabilities so they can be altered at runtime.
 *
 * This is used to prevent access to anything on the network media library site except for managing media.
 *
 * Important: This does not get called for Super Admins.
 *
 * @param bool[]   $user_caps     Concerned user's capabilities.
 * @param string[] $required_caps Required primitive capabilities for the requested capability.
 * @param array    $args {
 *     Arguments that accompany the requested capability check.
 *
 *     @type string    $0 Requested capability.
 *     @type int       $1 Concerned user ID.
 *     @type mixed  ...$2 Optional second and further parameters.
 * }
 * @param WP_User  $user          Concerned user object.
 * @return bool[] Concerned user's capabilities.
 */
function filter_user_has_cap( array $user_caps, array $required_caps, array $args, WP_User $user ) : array {
	if ( ! is_media_site() ) {
		return $user_caps;
	}

	$allowed_on_media_site = [
		'read',
		'upload_files',
	];

	if ( in_array( $args[0], $allowed_on_media_site, true ) ) {
		return $user_caps;
	}

	if ( 'edit_post' === $args[0] ) {
		$post = get_post( $args[2] );
		if ( $post && 'attachment' === $post->post_type ) {
			return $user_caps;
		}
	}

	return [];
}

add_action( 'admin_head-upload.php', __NAMESPACE__ . '\enqueue_styles' );
/**
 * Outputs some styles on the Media screen when we're not on the network media library site.
 */
function enqueue_styles() {
	if ( is_media_site() ) {
		return;
	}

	?>
	<style>
		.wp-filter .media-grid-view-switch a {
			width: 0;
		}

		.wp-core-ui .select-mode-toggle-button,
		.wp-filter .media-grid-view-switch a::before {
			display: none;
		}
	</style>
	<?php
}

add_filter( 'admin_post_thumbnail_html', __NAMESPACE__ . '\admin_post_thumbnail_html', 99, 3 );
/**
 * Filters the admin post thumbnail HTML markup to return.
 *
 * @param string   $content      Admin post thumbnail HTML markup.
 * @param int      $post_id      Post ID.
 * @param int|null $thumbnail_id Thumbnail attachment ID, or null if there isn't one.
 */
function admin_post_thumbnail_html( string $content, $post_id, $thumbnail_id ) : string {
	static $switched = false;

	if ( $switched ) {
		return $content;
	}

	if ( ! $thumbnail_id ) {
		return $content;
	}

	switch_to_blog( get_site_id() );
	$switched = true;
	// $thumbnail_id is passed instead of post_id to avoid warning messages of nonexistent post object.
	$content  = _wp_post_thumbnail_html( $thumbnail_id, $thumbnail_id );
	$switched = false;
	restore_current_blog();

	$post             = get_post( $post_id );
	$post_type_object = get_post_type_object( $post->post_type );

	$search  = '<p class="hide-if-no-js"><a href="#" id="remove-post-thumbnail"></a></p>';
	$replace = '<p class="hide-if-no-js"><a href="#" id="remove-post-thumbnail">' . esc_html( $post_type_object->labels->remove_featured_image ) . '</a></p>';
	$content = str_replace( $search, $replace, $content );

	return $content;
}

/**
 * Filters the image src result so its URL points to the network media library site.
 *
 * @param array|false  $image         Either array with src, width & height, icon src, or false.
 * @param int          $attachment_id Image attachment ID.
 * @param string|array $size          Size of image. Image size or array of width and height values.
 * @param bool         $icon          Whether the image should be treated as an icon.
 * @return array|false Either array with src, width & height, icon src, or false.
 */
add_filter( 'wp_get_attachment_image_src', function( $image, $attachment_id, $size, bool $icon ) {
	static $switched = false;

	if ( $switched ) {
		return $image;
	}

	if ( is_media_site() ) {
		return $image;
	}

	switch_to_media_site();

	$switched = true;
	$image    = wp_get_attachment_image_src( $attachment_id, $size, $icon );
	$switched = false;

	restore_current_blog();

	return $image;
}, 999, 4 );

/**
 * Filters the default gallery shortcode output so it shows media from the network media library site.
 *
 * @param string $output   The gallery output.
 * @param array  $attr     Attributes of the gallery shortcode.
 * @param int    $instance Unique numeric ID of this gallery shortcode instance.
 * @return string The gallery output.
 */
function filter_post_gallery( string $output, array $attr, int $instance ) : string {
	remove_filter( 'post_gallery', __NAMESPACE__ . '\filter_post_gallery', 0 );

	switch_to_media_site();
	$output = gallery_shortcode( $attr );
	restore_current_blog();

	add_filter( 'post_gallery', __NAMESPACE__ . '\filter_post_gallery', 0, 3 );

	return $output;
}
add_filter( 'post_gallery', __NAMESPACE__ . '\filter_post_gallery', 0, 3 );

// Allow users to upload attachments.
add_action( 'load-async-upload.php', __NAMESPACE__ . '\switch_to_media_site', 0 );
add_action( 'wp_ajax_upload-attachment', __NAMESPACE__ . '\switch_to_media_site', 0 );

// Allow attachments to be uploaded without a corresponding post on the network media library site.
add_action( 'load-async-upload.php', __NAMESPACE__ . '\prevent_attaching', 0 );
add_action( 'wp_ajax_upload-attachment', __NAMESPACE__ . '\prevent_attaching', 0 );

// Disallow access to the "List" mode on the Media screen.
add_action( 'load-upload.php', function() {
	if ( is_media_site() ) {
		return;
	}

	$_GET['mode'] = 'grid';
}, 0 );

// Allow attachment details to be fetched and saved.
add_action( 'wp_ajax_get-attachment', __NAMESPACE__ . '\switch_to_media_site', 0 );
add_action( 'wp_ajax_save-attachment', __NAMESPACE__ . '\switch_to_media_site', 0 );
add_action( 'wp_ajax_save-attachment-compat', __NAMESPACE__ . '\switch_to_media_site', 0 );
add_action( 'wp_ajax_set-attachment-thumbnail', __NAMESPACE__ . '\switch_to_media_site', 0 );

// Allow images to be edited and previewed.
add_action( 'wp_ajax_image-editor', __NAMESPACE__ . '\switch_to_media_site', 0 );
add_action( 'wp_ajax_imgedit-preview', __NAMESPACE__ . '\switch_to_media_site', 0 );
add_action( 'wp_ajax_crop-image', __NAMESPACE__ . '\switch_to_media_site', 0 );

// Allow attachments to be queried and inserted.
add_action( 'wp_ajax_query-attachments', __NAMESPACE__ . '\switch_to_media_site', 0 );
add_action( 'wp_ajax_send-attachment-to-editor', __NAMESPACE__ . '\switch_to_media_site', 0 );

// Support for the WP User Avatars plugin.
add_action( 'wp_ajax_assign_wp_user_avatars_media', __NAMESPACE__ . '\switch_to_media_site', 0 );

/**
 * Filters the attachment data prepared for JavaScript.
 *
 * @param array      $response   Array of prepared attachment data.
 * @param WP_Post    $attachment Attachment ID or object.
 * @param array|bool $meta       Array of attachment meta data, or boolean false if there is none.
 * @return array Array of prepared attachment data.
 */
add_filter( 'wp_prepare_attachment_for_js', function( array $response, \WP_Post $attachment, $meta ) : array {
	if ( is_media_site() ) {
		return $response;
	}

	// Prevent media from being deleted from any site other than the network media library site.
	// This is needed in order to prevent incorrect posts from being deleted on the local site.
	unset( $response['nonces']['delete'] );

	return $response;
}, 0, 3 );

/**
 * Filters the pre-dispatch value of REST API requests in order to switch to the network media library site when querying media.
 *
 * @param mixed           $result  Response to replace the requested version with. Can be anything
 *                                 a normal endpoint can return, or null to not hijack the request.
 * @param WP_REST_Server  $this    Server instance.
 * @param WP_REST_Request $request Request used to generate the response.
 */
add_filter( 'rest_pre_dispatch', function( $result, \WP_REST_Server $server, \WP_REST_Request $request ) {
	$media_routes = [
		'/wp/v2/media',
		'/regenerate-thumbnails/',
	];

	foreach ( $media_routes as $route ) {
		if ( 0 === strpos( $request->get_route(), $route ) ) {
			$request->set_param( 'post', null );
			switch_to_media_site();
			break;
		}
	}

	return $result;
}, 0, 3 );

/**
 * Fires after the XML-RPC user has been authenticated, but before the rest of the method logic begins, in order to
 * switch to the network media library site when querying media.
 *
 * @param string $name The method name.
 */
add_action( 'xmlrpc_call', function( string $name ) {
	$media_methods = [
		'metaWeblog.newMediaObject',
		'wp.getMediaItem',
		'wp.getMediaLibrary',
	];

	if ( in_array( $name, $media_methods, true ) ) {
		switch_to_media_site();
	}
}, 0 );

/**
 * A class which handles saving the post's featured image ID.
 *
 * This handling is required because `wp_insert_post()` checks the validity of the featured image
 * ID before saving it to post meta, and deletes it if it's not an image/audio/video. In order to
 * override this handling, two consecutive hooks are required to temporarily store the ID of the
 * selected featured image and then to save it again after the post has been saved.
 */
class Post_Thumbnail_Saver {

	/**
	 * Stores the featured image ID for a post ID.
	 *
	 * @var int[] Array of featured image IDs keyed by their post ID.
	 */
	protected $thumbnail_ids = [];

	/**
	 * Sets up the necessary action and filter callbacks.
	 */
	public function __construct() {
		add_filter( 'wp_insert_post_data', [ $this, 'filter_wp_insert_post_data' ], 10, 2 );
		add_action( 'save_post', [ $this, 'action_save_post' ], 10, 3 );
	}

	/**
	 * Temporarily stores the ID of the featured image for the given post ID when the post is saved.
	 *
	 * @param array $data    An array of slashed post data.
	 * @param array $postarr An array of sanitized, but otherwise unmodified post data.
	 * @return array An array of slashed post data.
	 */
	public function filter_wp_insert_post_data( array $data, array $postarr ) : array {
		if ( ! empty( $postarr['_thumbnail_id'] ) ) {
			$this->thumbnail_ids[ $postarr['ID'] ] = intval( $postarr['_thumbnail_id'] );
		}

		return $data;
	}

	/**
	 * Re-saves the featured image ID for the given post.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated or not.
	 */
	public function action_save_post( $post_id, WP_Post $post, bool $update ) {
		if ( empty( $this->thumbnail_ids[ $post->ID ] ) || -1 === $this->thumbnail_ids[ $post->ID ] ) {
			delete_post_meta( $post->ID, '_thumbnail_id' );
		} else {
			update_post_meta( $post->ID, '_thumbnail_id', $this->thumbnail_ids[ $post->ID ] );
		}
	}

}

new Post_Thumbnail_Saver();
