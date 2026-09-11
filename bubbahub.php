<?php
/**
 * Plugin Name: BubbaHub App
 * Plugin URI: https://bubbahub.co.uk
 * Description: BubbaHub Figma frontend powered by native WordPress data.
 * Version: 1.2.1
 * Author: BubbaHub
 * License: GPL-2.0+
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'BUBBAHUB_APP_VERSION' ) ) define( 'BUBBAHUB_APP_VERSION', '1.2.1' );
if ( ! defined( 'BUBBAHUB_APP_DIR' ) ) define( 'BUBBAHUB_APP_DIR', plugin_dir_path( __FILE__ ) );
if ( ! defined( 'BUBBAHUB_APP_URL' ) ) define( 'BUBBAHUB_APP_URL', plugin_dir_url( __FILE__ ) );

require_once BUBBAHUB_APP_DIR . 'includes/class-bubbahub-data.php';
register_activation_hook( __FILE__, array( 'BubbaHub_Data', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'BubbaHub_Data', 'deactivate' ) );

function bubbahub_app_meta( $id, $keys, $default = '' ) {
    foreach ( (array) $keys as $key ) {
        $v = get_post_meta( $id, $key, true );
        if ( $v !== '' && $v !== null && $v !== array() ) return $v;
    }
    return $default;
}

function bubbahub_app_listing_to_app( WP_Post $post ) {
    $id = $post->ID;
    $tags = wp_get_post_tags( $id, array( 'fields' => 'names' ) );
    $raw = bubbahub_app_meta( $id, array( 'Tags', 'tags', 'keywords', 'post_tags' ), '' );
    if ( is_string( $raw ) && $raw !== '' ) $tags = array_merge( $tags, preg_split( '/[,;]+/', $raw ) );
    $tags = array_values( array_unique( array_filter( array_map( 'trim', array_map( 'strval', $tags ) ) ) ) );
    $price = bubbahub_app_meta( $id, array( 'price', 'Price', 'pricing', 'listing_price' ), '' );

    return array(
        'ID' => $id,
        'id' => (string) $id,
        'title' => get_the_title( $id ),
        'description' => apply_filters( 'the_content', $post->post_content ),
        'url' => get_permalink( $id ),
        'postType' => $post->post_type,
        'status' => $post->post_status,
        'image' => get_the_post_thumbnail_url( $id, 'large' ),
        'Tags' => implode( ', ', $tags ),
        'tags' => $tags,
        'category' => bubbahub_app_meta( $id, array( 'category', 'Category', 'listing_category' ), '' ),
        'Address' => bubbahub_app_meta( $id, array( 'Address', 'address', 'street' ), '' ),
        'city' => bubbahub_app_meta( $id, array( 'city', 'City', 'town' ), '' ),
        'region' => bubbahub_app_meta( $id, array( 'region', 'Region', 'county' ), '' ),
        'zip' => bubbahub_app_meta( $id, array( 'zip', 'Zip', 'postcode' ), '' ),
        'ageRange' => bubbahub_app_meta( $id, array( 'ageRange', 'Age Range', 'age_range' ), '' ),
        'sessionLength' => bubbahub_app_meta( $id, array( 'sessionLength', 'Session Length', 'session_length' ), '' ),
        'termTime' => bubbahub_app_meta( $id, array( 'termTime', 'Term Time', 'term_time' ), '' ),
        'timetable' => bubbahub_app_meta( $id, array( 'timetable', 'Timetable' ), '' ),
        'price' => $price,
        'isFree' => in_array( strtoupper( trim( (string) $price ) ), array( 'FREE', '£0', '0' ), true ),
        'isFeatured' => (bool) filter_var( bubbahub_app_meta( $id, array( 'isFeatured', 'featured', 'listing_featured' ), false ), FILTER_VALIDATE_BOOLEAN ),
        'isClaimed' => (bool) filter_var( bubbahub_app_meta( $id, array( 'isClaimed', 'is_claimed', 'claimed' ), true ), FILTER_VALIDATE_BOOLEAN ),
        'hasBooking' => (bool) filter_var( bubbahub_app_meta( $id, array( 'hasBooking', 'has_booking', 'booking_enabled' ), true ), FILTER_VALIDATE_BOOLEAN ),
        'organizer_username' => bubbahub_app_meta( $id, array( 'organizer_username', 'organizerUsername' ), '' ),
        'leader_user' => bubbahub_app_meta( $id, array( 'leader_user', 'leaderUser', 'leader' ), '' ),
        'email' => bubbahub_app_meta( $id, array( 'email', 'Email', 'contact' ), '' ),
        'website' => bubbahub_app_meta( $id, array( 'website', 'Website' ), '' ),
        'facebook' => bubbahub_app_meta( $id, array( 'facebook', 'Facebook' ), '' ),
        'instagram' => bubbahub_app_meta( $id, array( 'instagram', 'Instagram' ), '' ),
        'lat' => (float) bubbahub_app_meta( $id, array( 'latitude', 'manual_lat', 'lat' ), 0 ),
        'lng' => (float) bubbahub_app_meta( $id, array( 'longitude', 'manual_lng', 'lng' ), 0 ),
        'openingHours' => bubbahub_app_meta( $id, array( 'business_hours', 'openingHours', 'opening_hours', 'schedule', 'hours' ), array() ),
    );
}

function bubbahub_app_rest_listings( WP_REST_Request $r ) {
    $args = array(
        'post_type' => 'bubba_listing',
        'post_status' => 'publish',
        'posts_per_page' => min( 100, max( 1, absint( $r->get_param( 'per_page' ) ?: 50 ) ) ),
        'paged' => max( 1, absint( $r->get_param( 'page' ) ?: 1 ) ),
        'ignore_sticky_posts' => true,
    );
    $search = sanitize_text_field( (string) $r->get_param( 'search' ) );
    if ( $search ) $args['s'] = $search;
    $q = new WP_Query( $args );
    $out = array();
    foreach ( $q->posts as $p ) {
        $i = bubbahub_app_listing_to_app( $p );
        $ok = true;
        foreach ( array( 'category' => 'category', 'city' => 'city', 'region' => 'region', 'age' => 'ageRange' ) as $param => $field ) {
            $v = sanitize_text_field( (string) $r->get_param( $param ) );
            if ( $v && strcasecmp( $v, 'All' ) !== 0 && stripos( (string) $i[ $field ], $v ) === false ) $ok = false;
        }
        if ( $r->get_param( 'featured' ) !== null && $r->get_param( 'featured' ) !== '' && filter_var( $r->get_param( 'featured' ), FILTER_VALIDATE_BOOLEAN ) && ! $i['isFeatured'] ) $ok = false;
        if ( $ok ) $out[] = $i;
    }
    return rest_ensure_response( $out );
}

function bubbahub_app_rest_save_listing( WP_REST_Request $r ) {
    $d = $r->get_json_params();
    if ( ! is_array( $d ) ) return new WP_Error( 'invalid_payload', 'Invalid listing payload', array( 'status' => 400 ) );
    $id = absint( $d['ID'] ?? $d['id'] ?? 0 );
    if ( $id ) {
        $existing = get_post( $id );
        if ( ! $existing || 'bubba_listing' !== $existing->post_type ) return new WP_Error( 'not_found', 'Listing not found', array( 'status' => 404 ) );
        if ( ! current_user_can( 'edit_post', $id ) ) return new WP_Error( 'forbidden', 'You cannot edit this listing', array( 'status' => 403 ) );
    } elseif ( ! current_user_can( 'edit_posts' ) ) {
        return new WP_Error( 'forbidden', 'You cannot create listings', array( 'status' => 403 ) );
    }

    $post = array(
        'post_type' => 'bubba_listing',
        'post_title' => sanitize_text_field( $d['title'] ?? $d['Title'] ?? 'Untitled Activity' ),
        'post_content' => wp_kses_post( $d['description'] ?? $d['Description'] ?? '' ),
    );
    if ( $id ) $post['ID'] = $id;
    else {
        $post['post_status'] = current_user_can( 'publish_posts' ) ? 'publish' : 'draft';
        $post['post_author'] = get_current_user_id();
    }
    $saved = $id ? wp_update_post( wp_slash( $post ), true ) : wp_insert_post( wp_slash( $post ), true );
    if ( is_wp_error( $saved ) ) return $saved;

    foreach ( array( 'Tags'=>'Tags', 'category'=>'category', 'Address'=>'Address', 'city'=>'city', 'region'=>'region', 'zip'=>'zip', 'ageRange'=>'ageRange', 'sessionLength'=>'sessionLength', 'termTime'=>'termTime', 'timetable'=>'timetable', 'price'=>'price', 'organizer_username'=>'organizer_username', 'leader_user'=>'leader_user', 'website'=>'website', 'email'=>'email', 'facebook'=>'facebook', 'instagram'=>'instagram', 'latitude'=>'latitude', 'longitude'=>'longitude', 'isFeatured'=>'isFeatured', 'isClaimed'=>'isClaimed', 'hasBooking'=>'hasBooking', 'business_hours'=>'business_hours' ) as $source => $meta_key ) {
        if ( array_key_exists( $source, $d ) ) update_post_meta( $saved, $meta_key, is_scalar( $d[ $source ] ) ? sanitize_text_field( (string) $d[ $source ] ) : $d[ $source ] );
    }
    if ( ! empty( $d['Tags'] ) ) wp_set_post_tags( $saved, array_filter( array_map( 'trim', preg_split( '/[,;]+/', (string) $d['Tags'] ) ) ), false );
    return rest_ensure_response( array( 'success'=>true, 'id'=>$saved, 'listing'=>bubbahub_app_listing_to_app( get_post( $saved ) ) ) );
}

add_action( 'rest_api_init', function () {
    register_rest_route( 'bubbahub/v1', '/listings', array(
        array( 'methods'=>WP_REST_Server::READABLE, 'callback'=>'bubbahub_app_rest_listings', 'permission_callback'=>'__return_true' ),
        array( 'methods'=>WP_REST_Server::CREATABLE, 'callback'=>'bubbahub_app_rest_save_listing', 'permission_callback'=>function(){ return is_user_logged_in(); } ),
    ) );
} );

/**
 * Deliberately uniquely named to avoid collisions with older BubbaHub plugin copies.
 */
function bubbahub_app_enqueue_assets_121() {
    $base = BUBBAHUB_APP_URL . 'assests/';
    $v = BUBBAHUB_APP_VERSION;
    wp_enqueue_style( 'bubbahub-app-css', $base . 'index-CLI2PE_D.css', array(), $v );
    wp_enqueue_script( 'bubbahub-app-wp-bridge', $base . 'wp-bridge.js', array(), $v, true );
    wp_localize_script( 'bubbahub-app-wp-bridge', 'BubbaHubWP', array(
        'apiUrl' => esc_url_raw( rest_url( 'bubbahub/v1/listings' ) ),
        'eventsUrl' => esc_url_raw( rest_url( 'bubbahub/v1/events' ) ),
        'meUrl' => esc_url_raw( rest_url( 'bubbahub/v1/me' ) ),
        'nonce' => wp_create_nonce( 'wp_rest' ),
    ) );
    wp_enqueue_script( 'bubbahub-app-js', $base . 'index-CkDzJHE0.js', array( 'bubbahub-app-wp-bridge' ), $v, true );
}
add_action( 'wp_enqueue_scripts', 'bubbahub_app_enqueue_assets_121' );

add_shortcode( 'bubbahub', function () {
    return '<div id="root" style="width:100%;min-height:100vh;"></div>';
} );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
    $links[] = '<a href="https://bubbahub.co.uk" target="_blank">Website</a>';
    $links[] = '<a href="' . esc_url( rest_url( 'bubbahub/v1/listings' ) ) . '" target="_blank">Listings API</a>';
    return $links;
} );
