<?php # -*- coding: utf-8 -*-

/**
 * Plugin Name: Multisite Global Media
 * Description: Multisite Global Media is a WordPress plugin which shares media across the Multisite network.
 * Network:     true
 * Plugin URI:  https://github.com/bueltge/multisite-global-media
 * Version:     0.0.5
 * Author:      Dominik Schilling, Frank Bültge
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
 * @version 2018-04-23
 */

namespace Multisite_Global_Media;

/**
 * Don't call this file directly.
 */
defined('ABSPATH') || die();

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
const SITE_ID = 1;

/**
 * Return the ID of site that store the media files.
 *
 * @since  2017-12-01
 * @return integer The site ID.
 */
function get_site_id()
{

    return (int)apply_filters('global_media.site_id', SITE_ID);
}

add_action('admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_scripts');
/**
 * Enqueue script for media modal
 *
 * @since  2015-01-26
 */
function enqueue_scripts()
{

    if ('post' !== get_current_screen()->base) {
        return;
    }

    wp_enqueue_script(
        'global_media',
        plugins_url('assets/js/global-media.js', __FILE__),
        array('media-views'),
        '0.1',
        true
    );
    wp_enqueue_script('global_media');
}

add_action('admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_styles');
/**
 * Enqueue script for media modal
 *
 * @since   2015-02-27
 */
function enqueue_styles()
{

    if ('post' !== get_current_screen()->base) {
        return;
    }

    wp_register_style(
        'global_media',
        plugins_url('assets/css/global-media.css', __FILE__),
        array(),
        '0.1'
    );
    wp_enqueue_style('global_media');
}

add_filter('media_view_strings', __NAMESPACE__ . '\get_media_strings');
/**
 * Define Strings for translation
 *
 * @since   2015-01-26
 * @param $strings
 *
 * @return mixed
 */
function get_media_strings($strings)
{

    $strings['globalMediaTitle'] = esc_html__('Global Media', 'global_media');
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
function prepare_attachment_for_js($response)
{

    $id_prefix = get_site_id() . '00000';

    $response['id']               = $id_prefix . $response['id']; // Unique ID, must be a number.
    $response['nonces']['update'] = false;
    $response['nonces']['edit']   = false;
    $response['nonces']['delete'] = false;
    $response['editLink']         = false;

    return $response;
}

add_action('wp_ajax_query-attachments', __NAMESPACE__ . '\ajax_query_attachments', 0);
/**
 * Same as wp_ajax_query_attachments() but with switch_to_blog support.
 *
 * @since   2015-01-26
 * @return void
 */
function ajax_query_attachments()
{

    $query = isset($_REQUEST['query']) ? (array)$_REQUEST['query'] : array();

    if (!empty($query['global_media'])) {
        switch_to_blog(get_site_id());

        add_filter('wp_prepare_attachment_for_js', __NAMESPACE__ . '\prepare_attachment_for_js');
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
function media_send_to_editor($html, $id)
{

    $id_prefix = get_site_id() . '00000';
    $new_id    = $id_prefix . $id; // Unique ID, must be a number.

    $search  = 'wp-image-' . $id;
    $replace = 'wp-image-' . $new_id;
    $html    = str_replace($search, $replace, $html);

    return $html;
}

add_action('wp_ajax_send-attachment-to-editor', __NAMESPACE__ . '\ajax_send_attachment_to_editor', 0);
/**
 * Send media via AJAX call to editor
 *
 * @since   2015-01-26
 * @return  void
 */
function ajax_send_attachment_to_editor()
{

    $attachment = wp_unslash($_POST['attachment']);
    $id         = $attachment['id'];
    $id_prefix  = get_site_id() . '00000';

    if (false !== strpos($id, $id_prefix)) {
        $attachment['id']    = str_replace($id_prefix, '', $id); // Unique ID, must be a number.
        $_POST['attachment'] = wp_slash($attachment);

        switch_to_blog(get_site_id());

        add_filter('media_send_to_editor', __NAMESPACE__ . '\media_send_to_editor', 10, 2);
    }

    wp_ajax_send_attachment_to_editor();
    exit();
}

add_action('wp_ajax_get-attachment', __NAMESPACE__ . '\ajax_get_attachment', 0);
/**
 * Get attachment
 *
 * @since   2015-01-26
 * @return  void
 */
function ajax_get_attachment()
{

    $id        = $_REQUEST['id'];
    $id_prefix = get_site_id() . '00000';

    if (false !== strpos($id, $id_prefix)) {
        $id             = str_replace($id_prefix, '', $id); // Unique ID, must be a number.
        $_REQUEST['id'] = $id;

        switch_to_blog(get_site_id());
        add_filter('wp_prepare_attachment_for_js', __NAMESPACE__ . '\prepare_attachment_for_js');
        restore_current_blog();
    }

    wp_ajax_get_attachment();
    exit();
}

add_action( 'save_post', __NAMESPACE__ . '\save_thumbnail_meta', 99);
/**
 * Fires once a post has been saved.
 *
 * @since 1.5.0
 *
 * @param int     $post_ID Post ID.
 * @param WP_Post $post    Post object.
 * @param bool    $update  Whether this is an existing post being updated or not.
 */
function save_thumbnail_meta($post_id) {

    $id_prefix = get_site_id() . '00000';
    if ( ! empty( $_POST['_thumbnail_id'] ) && false !== strpos( $_POST['_thumbnail_id'], $id_prefix ) ) {
        update_post_meta( $post_id, '_thumbnail_id', intval( $_POST['_thumbnail_id'] ) );
        update_post_meta( $post_id, 'global_media_site_id', get_site_id() );
    }

}

add_action('wp_ajax_get-post-thumbnail-html', __NAMESPACE__ . '\ajax_get_post_thumbnail_html', 99);
/**
 * Ajax handler for retrieving HTML for the featured image.
 *
 * @since 4.6.0
 */
function ajax_get_post_thumbnail_html()
{
    $id_prefix = get_site_id() . '00000';

    if (false !== strpos($thumbnail_id, $id_prefix)) {
        $thumbnail_id = str_replace($id_prefix, '', $thumbnail_id); // Unique ID, must be a number.

        switch_to_blog(get_site_id());
        $return = _wp_post_thumbnail_html( $thumbnail_id, $post_id );
        restore_current_blog();

        $post               = get_post( $post_ID );
        $post_type_object   = get_post_type_object( $post->post_type );

        $search  = '<p class="hide-if-no-js"><a href="#" id="remove-post-thumbnail"></a></p>';
        $replace = '<p class="hide-if-no-js"><a href="#" id="remove-post-thumbnail">' . esc_html( $post_type_object->labels->remove_featured_image ) . '</a></p>';
        $return = str_replace($search, $replace, $return);

    }
    else {
        $return = _wp_post_thumbnail_html( $thumbnail_id, $post_ID );
    }

    wp_send_json_success( $return );
}

add_filter( 'admin_post_thumbnail_html', __NAMESPACE__ . '\admin_post_thumbnail_html', 99, 3);
/**
     * Filters the admin post thumbnail HTML markup to return.
     *
     *
     * @param string $content      Admin post thumbnail HTML markup.
     * @param int    $post_id      Post ID.
     * @param int    $thumbnail_id Thumbnail ID.
     */

function admin_post_thumbnail_html ( $content, $post_id, $thumbnail_id ) {

    $site_id = get_post_meta( $post_id, 'global_media_site_id', true );
    if ( empty( $site_id ) ) {
        $site_id = get_site_id();
    }

    $id_prefix = get_site_id() . '00000';

    if (false !== strpos($thumbnail_id, $id_prefix)) {
        $thumbnail_id = str_replace($id_prefix, '', $thumbnail_id); // Unique ID, must be a number.

        switch_to_blog($site_id);
        $content = _wp_post_thumbnail_html( $thumbnail_id, $thumbnail_id ); //$thumbnail_id is passed instead of post_id to avoid warning messages of nonexistent post object.
        restore_current_blog();

        $search  = 'value="'.$thumbnail_id.'"';
        $replace = 'value="'.$id_prefix.$thumbnail_id.'"';
        $content = str_replace($search, $replace, $content);

        $post               = get_post( $post_id );
        $post_type_object   = get_post_type_object( $post->post_type );

        $search  = '<p class="hide-if-no-js"><a href="#" id="remove-post-thumbnail"></a></p>';
        $replace = '<p class="hide-if-no-js"><a href="#" id="remove-post-thumbnail">' . esc_html( $post_type_object->labels->remove_featured_image ) . '</a></p>';
        $content = str_replace($search, $replace, $content);
    }

    return $content;

}

add_filter( 'post_thumbnail_html', __NAMESPACE__ . '\post_thumbnail_html', 99, 5);
/**
     * Filters the post thumbnail HTML.
     *
     * @since 2.9.0
     *
     * @param string       $html              The post thumbnail HTML.
     * @param int          $post_id           The post ID.
     * @param string       $post_thumbnail_id The post thumbnail ID.
     * @param string|array $size              The post thumbnail size. Image size or array of width and height
     *                                        values (in that order). Default 'post-thumbnail'.
     * @param string       $attr              Query string of attributes.
     */

function post_thumbnail_html($html, $post_id, $post_thumbnail_id, $size, $attr) {

    $site_id = get_post_meta( $post_id, 'global_media_site_id', true );
    $thumbnail_id = get_post_meta($post_id, '_thumbnail_id', true);
    $id_prefix = $site_id . '00000';

    if (false !== strpos($thumbnail_id, $id_prefix)) {
        $thumbnail_id = str_replace($id_prefix, '', $thumbnail_id); // Unique ID, must be a number.

            if (intval($site_id) && intval($thumbnail_id)) {

            switch_to_blog($site_id);

            $html = wp_get_attachment_image( $thumbnail_id, $size, false, $attr );

            restore_current_blog();
        }

    }

    return $html;
}
