<?php
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
 * @link      https://github.com/humanmade/network-media-library
 * @author    John Blackbourn <john@johnblackbourn.com>, Dominik Schilling <d.schilling@inpsyde.com>, Frank Bültge <f.bueltge@inpsyde.com>
 * @copyright 2019 Human Made
 * @license   https://opensource.org/licenses/MIT
 *
 * Plugin Name: Network Media Library
 * Description: Network Media Library provides a central media library that's shared across all sites on the Multisite network.
 * Network:     true
 * Plugin URI:  https://github.com/humanmade/network-media-library
 * Version:     1.6.0
 * Author:      John Blackbourn, Dominik Schilling, Frank Bültge
 * Author URI:  https://github.com/humanmade/network-media-library/graphs/contributors
 * License:     MIT
 * License URI: ./LICENSE
 * Text Domain: network-media-library
 * Domain Path: /languages
 * Requires PHP: 7.0
 */

declare( strict_types=1 );

namespace Network_Media_Library;

use WP_Post;

/**
 * Don't call this file directly.
 */
defined( 'ABSPATH' ) || die();

/**
 * Don't run if multisite not enabled
 */
if ( ! is_multisite() ) {
	return;
}

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
 *
 * @param mixed $value An optional value used when this function is used as a hook filter.
 * @return mixed The value of the `$value` parameter.
 */
function switch_to_media_site( $value = null ) {
	switch_to_blog( get_site_id() );

	return $value;
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

	$post              = get_post( $post_id );
	$post_type_object  = get_post_type_object( $post->post_type );
	$has_thumbnail_url = get_the_post_thumbnail_url( $post_id ) !== false;

	if ( false === $has_thumbnail_url ) {
		$search  = 'class="thickbox"></a>';
		$replace = 'class="thickbox">' . esc_html( $post_type_object->labels->set_featured_image ) . '</a>';
	} else {
		$search  = '<p class="hide-if-no-js"><a href="#" id="remove-post-thumbnail"></a></p>';
		$replace = '<p class="hide-if-no-js"><a href="#" id="remove-post-thumbnail">' . esc_html( $post_type_object->labels->remove_featured_image ) . '</a></p>';
	}

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

// Allow access to the "List" mode on the Media screen.
add_action( 'parse_request', function() {
	if ( is_media_site() ) {
		return;
	}

	if ( ! function_exists( 'get_current_screen' ) || ! get_current_screen() ) {
		return;
	}

	if ( 'upload' !== get_current_screen()->id ){
		return;
	}

	switch_to_media_site();

	add_filter( 'posts_pre_query', function( $value ) {
		restore_current_blog();

		return $value;
	} );

	add_action( 'loop_start', __NAMESPACE__ . '\switch_to_media_site', 0 );
	add_action( 'loop_stop', 'restore_current_blog', 999 );

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
add_filter( 'map_meta_cap', __NAMESPACE__ . '\allow_media_library_access', 10, 4 );

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
	if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
		return $result;
	}

	if ( is_media_site() ) {
		return $result;
	}

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
 * Restores WordPress's standard embeddable featured-media link for a shared network attachment.
 *
 * Core omits this link on secondary sites because it checks the attachment before Network Media
 * Library switches to the central media site. Restoring the standard relation allows WordPress's
 * existing REST embedding pipeline to retrieve the attachment through the media route.
 *
 * @param \WP_REST_Response $response Prepared REST response for the post.
 * @param \WP_Post          $post     Post represented by the REST response.
 * @param \WP_REST_Request  $request  Current REST request.
 * @return \WP_REST_Response Prepared REST response with the shared featured-media relation.
 */
function restore_featured_media_rest_link( \WP_REST_Response $response, \WP_Post $post, \WP_REST_Request $request ) : \WP_REST_Response {
	unset( $request );

	$relation = 'https://api.w.org/featuredmedia';
	$links    = $response->get_links();

	if ( ! empty( $links[ $relation ] ) ) {
		return $response;
	}

	$attachment_id = (int) get_post_meta( $post->ID, '_thumbnail_id', true );

	if ( $attachment_id <= 0 ) {
		return $response;
	}

	$switched = ! is_media_site();

	if ( $switched ) {
		switch_to_media_site();
	}

	$attachment = get_post( $attachment_id );
	$is_image   = $attachment instanceof \WP_Post
		&& 'attachment' === $attachment->post_type
		&& wp_attachment_is_image( $attachment );

	if ( $switched ) {
		restore_current_blog();
	}

	if ( ! $is_image ) {
		return $response;
	}

	$response->add_link(
		$relation,
		rest_url( sprintf( '/wp/v2/media/%d', $attachment_id ) ),
		[ 'embeddable' => true ]
	);

	return $response;
}

/**
 * Registers shared featured-media compatibility for REST-visible post types that support thumbnails.
 *
 * @return void
 */
function register_featured_media_rest_filters() {
	$post_types = get_post_types( [ 'show_in_rest' => true ], 'objects' );

	foreach ( $post_types as $post_type ) {
		if ( ! post_type_supports( $post_type->name, 'thumbnail' ) ) {
			continue;
		}

		add_filter( "rest_prepare_{$post_type->name}", __NAMESPACE__ . '\\restore_featured_media_rest_link', 10, 3 );
	}
}

add_action( 'rest_api_init', __NAMESPACE__ . '\\register_featured_media_rest_filters' );

/**
 * Filter REST API responses for post saves which include featured_media
 * field and force the post meta update even if the id doesn't exist on the
 * current site.
 *
 * @param \WP_HTTP_Response|\WP_Error $response
 * @param array                       $handler
 * @param \WP_REST_Request            $request
 *
 * @return \WP_HTTP_Response|\WP_Error
 *
 * @wp-hook rest_request_after_callbacks
 */
add_filter( 'rest_request_after_callbacks', function ( $response, array $handler, \WP_REST_Request $request ) {
	if ( is_media_site() ) {
		return $response;
	}

	$featuredImage = (int) $request['featured_media'] ?? null;

	if ( $featuredImage ) {
		switch_to_media_site();
		$attachment = get_post( $featuredImage );
		restore_current_blog();

		$post_id = (int) $request['id'] ?? null;

		if ( $attachment ) {
			update_post_meta( $post_id, '_thumbnail_id', $featuredImage );
		} else {
			delete_post_meta( $post_id, '_thumbnail_id' );
		}

		$data                   = $response->get_data();
		$data['featured_media'] = $featuredImage;
		$response->set_data( $data );
	}

	return $response;
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
 * Apply the current site's `upload_files` capability to the network media site.
 *
 * This grants a user access to the network media site's library, if that user has access to
 * the media library of the current site (whichever site the request has been made from).
 *
 * @param string[] $caps    Capabilities for meta capability.
 * @param string   $cap     Capability name.
 * @param int      $user_id The user ID.
 * @param array    $args    Adds the context to the cap. Typically the object ID.
 *
 * @return string[] Updated capabilities.
 */
function allow_media_library_access( array $caps, string $cap, int $user_id, array $args ) : array {
	if ( get_current_blog_id() !== get_site_id() ) {
		return $caps;
	}

	if ( ! in_array( $cap, [ 'edit_post', 'upload_files' ], true ) ) {
		return $caps;
	}

	if ( 'edit_post' === $cap ) {
		$content = get_post( $args[0] );
		// return if post is null
		if ( ! $content ) {
			return $caps;
		}
		if ( 'attachment' !== $content->post_type ) {
			return $caps;
		}

		// Substitute edit_post because the attachment exists only on the network media site.
		$cap = get_post_type_object( $content->post_type )->cap->create_posts;
	}

	/*
	 * By the time this function is called, we've already switched context to the network media site.
	 * Switch back to the original site -- where the initial request came in from.
	 */
	switch_to_blog( (int) $GLOBALS['current_blog']->blog_id );
	remove_filter( 'map_meta_cap', __NAMESPACE__ . '\allow_media_library_access', 10 );

	$user_has_permission = user_can( $user_id, $cap );

	add_filter( 'map_meta_cap', __NAMESPACE__ . '\allow_media_library_access', 10, 4 );
	restore_current_blog();

	return ( $user_has_permission ? [ 'exist' ] : $caps );
}

/**
 * Filters 'img' elements in post content to add 'srcset' and 'sizes' attributes.
 *
 * @see wp_make_content_images_responsive()
 *
 * @param string $content The raw post content to be filtered.
 * @return string Converted content with 'srcset' and 'sizes' attributes added to images.
 */
function make_content_images_responsive( $content ) {
	if ( is_media_site() ) {
		return $content;
	}

	switch_to_media_site();

	$content = wp_make_content_images_responsive( $content );

	restore_current_blog();

	return $content;
}

remove_filter( 'the_content', 'wp_make_content_images_responsive' );
add_filter( 'the_content', __NAMESPACE__ . '\make_content_images_responsive' );

/**
 * A class which encapsulates the filtering of ACF field values.
 */
class ACF_Value_Filter {

	/**
	 * Stores the value of the field.
	 *
	 * @var mixed Field value.
	 */
	protected $value = [];

	/**
	 * Sets up the necessary action and filter callbacks.
	 */
	public function __construct() {
		$field_types = [
			'image',
			'file',
		];

		foreach ( $field_types as $type ) {
			add_filter( "acf/load_value/type={$type}", [ $this, 'filter_acf_attachment_load_value' ], 0, 3 );
			add_filter( "acf/format_value/type={$type}", [ $this, 'filter_acf_attachment_format_value' ], 9999, 3 );
		}
	}

	/**
	 * Fiters the return value when using field retrieval functions in Advanced Custom Fields.
	 *
	 * @param mixed      $value   The field value.
	 * @param int|string $post_id The post ID for this value.
	 * @param array      $field   The field array.
	 * @return mixed The updated value.
	 */
	public function filter_acf_attachment_load_value( $value, $post_id, array $field ) {
		$image = $value;

		if ( ! is_media_site() && ! is_admin() ) {
			switch_to_media_site();

			switch ( $field['return_format'] ) {
				case 'url':
					$image = wp_get_attachment_url( $value );
					break;
				case 'array':
					$image = acf_get_attachment( $value );
					break;
			}

			restore_current_blog();
		}

		$this->value[ $field['name'] ] = $image;

		return $image;
	}

	/**
	 * Fiters the optionally formatted value when using field retrieval functions in Advanced Custom Fields.
	 *
	 * @param mixed      $value   The field value.
	 * @param int|string $post_id The post ID for this value.
	 * @param array      $field   The field array.
	 * @return mixed The updated value.
	 */
	public function filter_acf_attachment_format_value( $value, $post_id, array $field ) {
		return $this->value[ $field['name'] ];
	}
}

new ACF_Value_Filter();

/**
 * A class which encapsulates the rendering of ACF field controls.
 */
class ACF_Field_Rendering {

	/**
	 * Stored the site switching state between instances of fields.
	 *
	 * @var bool Whether the previous field triggered a switch to the central media site.
	 */
	protected $switched = false;

	/**
	 * Sets up the necessary action and filter callbacks.
	 */
	public function __construct() {
		add_action( 'acf/render_field', [ $this, 'maybe_restore_current_blog' ], -999 );
		add_action( 'acf/render_field/type=file', [ $this, 'maybe_switch_to_media_site' ], 0 );
	}

	/**
	 * Switches to the central media site.
	 */
	public function maybe_switch_to_media_site() {
		$this->switched = true;

		switch_to_media_site();
	}

	/**
	 * Switches back to the current site if the previous field triggered a switch to the central media site.
	 */
	public function maybe_restore_current_blog() {
		if ( ! empty( $this->switched ) ) {
			restore_current_blog();
		}

		$this->switched = false;
	}
}

new ACF_Field_Rendering();

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
		if ( ! empty( $this->thumbnail_ids[ $post->ID ] ) && ( -1 !== $this->thumbnail_ids[ $post->ID ] ) ) {
			update_post_meta( $post->ID, '_thumbnail_id', $this->thumbnail_ids[ $post->ID ] );
		}
	}

}

new Post_Thumbnail_Saver();

/**
 * Compatibility with the MultilingualPress plugin.
 *
 * MultilingualPress copies attachments from the source site to the target site when a
 * post is translated — featured images, in-content media blocks, and ACF image/file/gallery
 * fields all funnel through its attachment copier. With a shared network media library that
 * is both unnecessary and harmful: the copy either fails (the file does not exist on the
 * sub-site) leaving an empty reference, or it creates a duplicate attachment outside the
 * central library.
 *
 * Because every site already shares one media library, the correct behaviour is for a
 * translation to reference the *same* attachment IDs as its source. This class hooks
 * MultilingualPress's public filters to keep those shared IDs instead of duplicating, for
 * each of the three vectors. It is only instantiated when MultilingualPress is active.
 */
class MultilingualPress_Compatibility {

	/**
	 * The source post content captured before MultilingualPress rewrites media blocks.
	 *
	 * @var string|null
	 */
	protected $source_content = null;

	/**
	 * Sets up the necessary filter callbacks against MultilingualPress's hooks.
	 */
	public function __construct() {
		// Featured image: hide MLP's "copy featured image" field (it would duplicate) and
		// instead inherit the source's shared thumbnail ID.
		add_filter( 'multilingualpress.translation_ui_show_field', [ $this, 'hide_copy_featured_image_field' ], 10, 2 );
		add_filter( 'multilingualpress.post_meta_data', [ $this, 'share_featured_image' ], 99, 2 );

		// ACF image/file/gallery fields: undo MLP's duplication, keeping the shared IDs.
		add_filter( 'multilingualpress.post_meta_data', [ $this, 'share_acf_attachment_fields' ], 99, 2 );

		// In-content media blocks: keep the source content verbatim (shared IDs and URLs)
		// instead of MLP's per-site rewritten copy.
		add_filter( 'multilingualpress.content_before_update_remote_post', [ $this, 'capture_source_content' ], 1, 1 );
		add_filter( 'multilingualpress.content_before_update_remote_post', [ $this, 'keep_source_content' ], 99, 1 );

		// Media modal / attachment edit screen: remove MLP's "Copy to connected site(s)"
		// panel — copying attachments per-site is meaningless (and harmful) with a shared
		// library. Removing the fields also disables the copy, which runs on their submission.
		add_filter( 'attachment_fields_to_edit', [ $this, 'remove_media_library_fields' ], 99 );
	}

	/**
	 * Hides MultilingualPress's "copy featured image" field.
	 *
	 * The field triggers a per-site attachment copy; with a shared library the featured
	 * image is inherited by reference instead (see self::share_featured_image()).
	 *
	 * @param bool   $enabled Whether the field is shown.
	 * @param object $field   The MultilingualPress metabox field.
	 * @return bool Whether the field is shown.
	 */
	public function hide_copy_featured_image_field( $enabled, $field ) {
		if ( is_object( $field ) && method_exists( $field, 'key' ) && 'remote-thumbnail-copy' === $field->key() ) {
			return false;
		}

		return $enabled;
	}

	/**
	 * Inherits the source post's featured image as a shared reference.
	 *
	 * Runs on MultilingualPress's metadata-sync filter (remote-site context). The source
	 * thumbnail is a central-library attachment that resolves on every site, so the
	 * translation simply points at the same ID. An existing per-translation featured image
	 * is left untouched.
	 *
	 * @param array  $values  Meta values to sync to the remote post, keyed by meta key.
	 * @param object $context MultilingualPress relationship context.
	 * @return array The meta values to sync.
	 */
	public function share_featured_image( $values, $context ) {
		if ( ! is_array( $values ) ) {
			$values = [];
		}
		if ( ! $this->is_post_context( $context ) ) {
			return $values;
		}

		$remote_post_id = $context->remotePostId();
		if ( $remote_post_id && get_post_meta( $remote_post_id, '_thumbnail_id', true ) ) {
			return $values; // Respect an existing per-translation featured image.
		}

		switch_to_blog( $context->sourceSiteId() );
		$thumbnail_id = (int) get_post_meta( $context->sourcePostId(), '_thumbnail_id', true );
		restore_current_blog();

		if ( $thumbnail_id ) {
			$values['_thumbnail_id'] = [ $thumbnail_id ];
		}

		return $values;
	}

	/**
	 * Keeps ACF image/file/gallery fields pointing at the shared source attachments.
	 *
	 * MultilingualPress's ACF field copier duplicates these attachments and overrides the
	 * synced value (at priority 10 on the same filter). Running later, this restores the
	 * verbatim source values for file-type fields only, leaving post-object, relationship,
	 * and taxonomy fields — which MultilingualPress legitimately remaps to the translated
	 * objects — untouched.
	 *
	 * @param array  $values  Meta values to sync to the remote post, keyed by meta key.
	 * @param object $context MultilingualPress relationship context.
	 * @return array The meta values to sync.
	 */
	public function share_acf_attachment_fields( $values, $context ) {
		if ( ! is_array( $values ) || ! $values || ! function_exists( 'acf_get_field' ) ) {
			return $values;
		}
		if ( ! $this->is_post_context( $context ) ) {
			return $values;
		}

		$source_post_id   = $context->sourcePostId();
		$file_field_types = [ 'image', 'file', 'gallery' ];

		switch_to_blog( $context->sourceSiteId() );
		foreach ( array_keys( $values ) as $key ) {
			if ( '_' === substr( (string) $key, 0, 1 ) ) {
				continue; // ACF stores the field-key reference under the underscore-prefixed twin.
			}
			$field_key = get_post_meta( $source_post_id, '_' . $key, true );
			if ( ! is_string( $field_key ) || 0 !== strpos( $field_key, 'field_' ) ) {
				continue;
			}
			$field = acf_get_field( $field_key );
			if ( ! $field || ! in_array( $field['type'] ?? '', $file_field_types, true ) ) {
				continue;
			}
			// Restore the shared source attachment ID(s) for this field.
			$values[ $key ] = get_post_meta( $source_post_id, $key, false );
		}
		restore_current_blog();

		return $values;
	}

	/**
	 * Captures the source post content before MultilingualPress rewrites its media blocks.
	 *
	 * @param string $content The source post content.
	 * @return string The unmodified source post content.
	 */
	public function capture_source_content( $content ) {
		$this->source_content = $content;

		return $content;
	}

	/**
	 * Restores the original source content, discarding MultilingualPress's media-block copy.
	 *
	 * In-content image/gallery/cover/file/media-text/audio/video blocks reference shared
	 * attachment IDs and absolute media-site URLs that already resolve on every site, so the
	 * translation keeps the source markup verbatim rather than the per-site rewritten copy.
	 *
	 * @param string $content The (possibly rewritten) post content.
	 * @return string The original source post content.
	 */
	public function keep_source_content( $content ) {
		return null === $this->source_content ? $content : $this->source_content;
	}

	/**
	 * Removes MultilingualPress's "Copy to connected site(s)" panel from the media modal.
	 *
	 * The panel lets an editor duplicate an attachment to other sites; with a shared media
	 * library there is nothing to copy. MultilingualPress adds the panel via
	 * `multilingualpress-media-library-*` form fields (a heading and the related-sites
	 * select); removing them hides the panel and, because the copy runs on submission of the
	 * select, disables the duplication too.
	 *
	 * @param array $form_fields The attachment form fields.
	 * @return array The attachment form fields without MultilingualPress's media fields.
	 */
	public function remove_media_library_fields( $form_fields ) {
		if ( ! is_array( $form_fields ) ) {
			return $form_fields;
		}

		foreach ( array_keys( $form_fields ) as $key ) {
			if ( is_string( $key ) && 0 === strpos( $key, 'multilingualpress-' ) ) {
				unset( $form_fields[ $key ] );
			}
		}

		return $form_fields;
	}

	/**
	 * Whether the given relationship context exposes the post accessors we rely on.
	 *
	 * @param mixed $context MultilingualPress relationship context.
	 * @return bool
	 */
	protected function is_post_context( $context ) : bool {
		return is_object( $context )
			&& method_exists( $context, 'sourcePostId' )
			&& method_exists( $context, 'sourceSiteId' )
			&& method_exists( $context, 'remotePostId' );
	}
}

add_action(
	'plugins_loaded',
	static function () {
		if ( function_exists( 'Inpsyde\\MultilingualPress\\resolve' ) ) {
			new MultilingualPress_Compatibility();
		}
	},
	20
);
