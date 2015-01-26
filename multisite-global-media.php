<?php
/**
 * Plugin Name: Global Media
 * Version: 0.1
 * Description: Share a media library across a multisite network.
 * Author: Dominik Schilling, Frank Bültge
 * Author URI: http://wphelper.de/
 * Plugin URI:
 *
 * Text Domain: global-media
 * Domain Path: /languages
 * Network: true
 *
 * License: GPLv2 or later
 *
 *	Copyright (C) 2015 Dominik Schilling
 *
 *	This program is free software; you can redistribute it and/or
 *	modify it under the terms of the GNU General Public License
 *	as published by the Free Software Foundation; either version 2
 *	of the License, or (at your option) any later version.
 *
 *	This program is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with this program; if not, write to the Free Software
 *	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

/**
 * Don't call this file directly.
 */
if ( ! class_exists( 'WP' ) ) {
	die();
}


define( 'GM_BLOG_ID', 2 );

/**
 * [gm_enqueue_scripts description]
 * @return [type] [description]
 */
function gm_enqueue_scripts() {
	if ( 'post' !== get_current_screen()->base ) {
		return;
	}

	wp_enqueue_script( 'global-media', plugins_url( 'assets/js/global-media.js', __FILE__ ), array( 'media-views' ), '0.1', true );
}
add_action( 'admin_enqueue_scripts', 'gm_enqueue_scripts' );

/**
 * [gm_media_strings description]
 * @param  [type] $strings [description]
 * @return [type]          [description]
 */
function gm_media_strings( $strings ) {
	$strings['globalMediaTitle'] = __( 'Global Media', 'global-media' );

	return $strings;
}
add_filter( 'media_view_strings', 'gm_media_strings' );

/**
 * [gm_fake_attachment_ids description]
 * @param  [type] $response [description]
 * @return [type]           [description]
 */
function gm_prepare_attachment_for_js( $response ) {
	$id_prefix = GM_BLOG_ID . '00000';

	$response['id'] = $id_prefix. $response['id']; // Unique ID, must be a number.
	$response['nonces']['update'] = false;
	$response['nonces']['edit'] = false;
	$response['nonces']['delete'] = false;
	$response['editLink'] = false;

	return $response;
}

/**
 * Same as wp_ajax_query_attachments() but with switch_to_blog support.
 *
 * @return void
 */
function gm_ajax_query_attachments() {
	$query = isset( $_REQUEST['query'] ) ? (array) $_REQUEST['query'] : array();

	if ( ! empty( $query['global-media'] ) ) {
		switch_to_blog( GM_BLOG_ID );

		add_filter( 'wp_prepare_attachment_for_js', 'gm_prepare_attachment_for_js' );
	}

	wp_ajax_query_attachments();
	exit;
}
add_action( 'wp_ajax_query-attachments' , 'gm_ajax_query_attachments', 0 );

/**
 * [gm_media_send_to_editor description]
 * @param  [type] $html [description]
 * @param  [type] $id   [description]
 * @return [type]       [description]
 */
function gm_media_send_to_editor( $html, $id ) {
	$id_prefix = GM_BLOG_ID . '00000';
	$new_id = $id_prefix . $id; // Unique ID, must be a number.

	$search = 'wp-image-' . $id;
	$replace = 'wp-image-' . $new_id;
	$html = str_replace( $search, $replace, $html );

	return $html;
}

/**
 * [gm_ajax_send_attachment_to_editor description]
 * @return [type] [description]
 */
function gm_ajax_send_attachment_to_editor() {
	$attachment = wp_unslash( $_POST['attachment'] );
	$id = $attachment['id'];
	$id_prefix = GM_BLOG_ID . '00000';

	if ( false !== strpos( $id, $id_prefix ) ) {
		$attachment['id'] = str_replace( $id_prefix, '', $id ); // Unique ID, must be a number.
		$_POST['attachment'] = wp_slash( $attachment );

		switch_to_blog( GM_BLOG_ID );

		add_filter( 'media_send_to_editor', 'gm_media_send_to_editor', 10, 2 );
	}

	wp_ajax_send_attachment_to_editor();
	exit;
}
add_action( 'wp_ajax_send-attachment-to-editor' , 'gm_ajax_send_attachment_to_editor', 0 );

/**
 * [gm_ajax_get_attachment description]
 * @return [type] [description]
 */
function gm_ajax_get_attachment() {
	$id = $_REQUEST['id'];
	$id_prefix = GM_BLOG_ID . '00000';

	if ( false !== strpos( $id, $id_prefix ) ) {
		$id = str_replace( $id_prefix, '', $id ); // Unique ID, must be a number.
		$_REQUEST['id'] = $id;

		switch_to_blog( GM_BLOG_ID );

		add_filter( 'wp_prepare_attachment_for_js', 'gm_prepare_attachment_for_js' );
	}

	wp_ajax_get_attachment();
	exit;
}
add_action( 'wp_ajax_get-attachment' , 'gm_ajax_get_attachment', 0 );
