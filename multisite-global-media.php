<?php
/**
 * Plugin Name: Multisite Global Media
 * Description: Multisite Global Media is a WordPress plugin which shares media across the Multisite network.
 * Network:     true
 * Plugin URI:  https://github.com/bueltge/Multisite-Global-Media
 * Version:     0.0.3
 * Author:      Dominik Schilling, Frank Bültge
 * Author URI:  https://bueltge.de/
 * License:     MIT
 * License URI: ./LICENSE
 * Text Domain: global_media
 * Domain Path: /languages
 *
 * Php Version 5.3
 *
 * @package WordPress
 * @author  Dominik Schilling <d.schilling@inpsyde.com>, Frank Bültge <f.bueltge@inpsyde.com>
 * @license https://opensource.org/licenses/MIT
 * @version 2017-12-01
 */
namespace Multisite_Global_Media;

/**
 * Don't call this file directly.
 */
defined( 'ABSPATH' ) || die();

/**
 * Id of side inside the network, there store the global media.
 * Select the ID of the site/blog to where you want media
 *  that will be shared across the network to be stored.
 * Alternative change this value with the help
 *  of the filter hook 'global_media.site_id'.
 *
 * @var    integer
 * @since  2015-01-22
 */
const SITE_ID = 3;

/**
 * Return the ID of site that store the media files.
 *
 * @since  2017-12-01
 * @return integer The site ID.
 */
function get_site_id() {

	return (int) apply_filters( 'global_media.site_id', SITE_ID );
}

add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_scripts' );
/**
 * Enqueue script for media modal
 *
 * @since  2015-01-26
 */
function enqueue_scripts() {

	if ( 'post' !== get_current_screen()->base ) {
		return;
	}

	wp_enqueue_script(
		'global_media',
		plugins_url( 'assets/js/global-media.js', __FILE__ ),
		array( 'media-views' ),
		'0.1',
		TRUE
	);
	wp_enqueue_script( 'global_media' );
}

add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_styles' );
/**
 * Enqueue script for media modal
 *
 * @since   2015-02-27
 */
function enqueue_styles() {

	if ( 'post' !== get_current_screen()->base ) {
		return;
	}

	wp_register_style(
		'global_media',
		plugins_url( 'assets/css/global-media.css', __FILE__ ),
		array(),
		'0.1'
	);
	wp_enqueue_style( 'global_media' );
}

add_filter( 'media_view_strings', __NAMESPACE__ . '\get_media_strings' );
/**
 * Define Strings for translation
 *
 * @since   2015-01-26
 * @param $strings
 *
 * @return mixed
 */
function get_media_strings( $strings ) {

	$strings[ 'globalMediaTitle' ] = esc_html__( 'Global Media', 'global_media' );
	return $strings;
}

/**
 * Prepare media for javascript
 *
 * @since   2015-01-26
 * @param $response
 *
 * @return mixed
 */
function prepare_attachment_for_js( $response ) {

	$id_prefix = get_site_id() . '00000';

	$response[ 'id' ]                 = $id_prefix . $response[ 'id' ]; // Unique ID, must be a number.
	$response[ 'nonces' ][ 'update' ] = FALSE;
	$response[ 'nonces' ][ 'edit' ]   = FALSE;
	$response[ 'nonces' ][ 'delete' ] = FALSE;
	$response[ 'editLink' ]           = FALSE;

	return $response;
}

add_action( 'wp_ajax_query-attachments', __NAMESPACE__ . '\ajax_query_attachments', 0 );
/**
 * Same as wp_ajax_query_attachments() but with switch_to_blog support.
 *
 * @since   2015-01-26
 * @return void
 */
function ajax_query_attachments() {

	$query = isset( $_REQUEST[ 'query' ] ) ? (array) $_REQUEST[ 'query' ] : array();

	if ( ! empty( $query[ 'global_media' ] ) ) {
		switch_to_blog( get_site_id() );

		add_filter( 'wp_prepare_attachment_for_js', __NAMESPACE__ . '\prepare_attachment_for_js' );
	}

	wp_ajax_query_attachments();
	exit;
}

/**
 * Send media to editor
 *
 * @since   2015-01-26
 * @param $html
 * @param $id
 *
 * @return mixed
 */
function media_send_to_editor( $html, $id ) {

	$id_prefix = get_site_id() . '00000';
	$new_id    = $id_prefix . $id; // Unique ID, must be a number.

	$search  = 'wp-image-' . $id;
	$replace = 'wp-image-' . $new_id;
	$html    = str_replace( $search, $replace, $html );

	return $html;
}

add_action( 'wp_ajax_send-attachment-to-editor', __NAMESPACE__ . '\ajax_send_attachment_to_editor', 0 );
/**
 * Send media via AJAX call to editor
 *
 * @since   2015-01-26
 * @return  void
 */
function ajax_send_attachment_to_editor() {

	$attachment = wp_unslash( $_POST[ 'attachment' ] );
	$id         = $attachment[ 'id' ];
	$id_prefix  = get_site_id() . '00000';

	if ( FALSE !== strpos( $id, $id_prefix ) ) {
		$attachment[ 'id' ]    = str_replace( $id_prefix, '', $id ); // Unique ID, must be a number.
		$_POST[ 'attachment' ] = wp_slash( $attachment );

		switch_to_blog( get_site_id() );

		add_filter( 'media_send_to_editor', __NAMESPACE__ . '\media_send_to_editor', 10, 2 );
	}

	wp_ajax_send_attachment_to_editor();
	exit();
}

add_action( 'wp_ajax_get-attachment', __NAMESPACE__ . '\ajax_get_attachment', 0 );
/**
 * Get attachment
 *
 * @since   2015-01-26
 * @return  void
 */
function ajax_get_attachment() {

	$id        = $_REQUEST[ 'id' ];
	$id_prefix = get_site_id() . '00000';

	if ( FALSE !== strpos( $id, $id_prefix ) ) {
		$id               = str_replace( $id_prefix, '', $id ); // Unique ID, must be a number.
		$_REQUEST[ 'id' ] = $id;

		switch_to_blog( get_site_id() );
		add_filter( 'wp_prepare_attachment_for_js', __NAMESPACE__ . '\prepare_attachment_for_js' );
		restore_current_blog();
	}

	wp_ajax_get_attachment();
	exit();
}
