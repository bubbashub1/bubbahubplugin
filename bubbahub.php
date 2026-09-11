<?php
/**
 * Plugin Name: BubbaHub App
 * Plugin URI:  https://bubbahub.co.uk
 * Description: BubbaHub family & community hub — Figma UI powered by live WordPress listing data.
 * Version:     1.1.0
 * Author:      BubbaHub
 * License:     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BUBBAHUB_APP_VERSION', '1.1.0' );
define( 'BUBBAHUB_APP_DIR', plugin_dir_path( __FILE__ ) );
define( 'BUBBAHUB_APP_URL', plugin_dir_url( __FILE__ ) );

function bubbahub_listing_post_types() {
    $preferred = array( 'bubba_listing', 'at_biz_dir', 'bubba_group' );
    $types     = array();

    foreach ( $preferred as $post_type ) {
        if ( post_type_exists( $post_type ) ) {
            $types[] = $post_type;
        }
    }

    if ( empty( $types ) ) {
        $types = array( 'post' );
    }

    return apply_filters( 'bubbahub_listing_post_types', array_values( array_unique( $types ) ) );
}

function bubbahub_meta( $post_id, $keys, $default = '' ) {
    foreach ( (array) $keys as $key ) {
        $value = get_post_meta( $post_id, $key, true );
        if ( $value !== '' && $value !== null && $value !== array() ) {
            return $value;
        }
    }
    return $default;
}

function bubbahub_normalize_hours( $value ) {
    $days = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );

    if ( is_string( $value ) && $value !== '' ) {
        $decoded = json_decode( $value, true );
        if ( json_last_error() === JSON_ERROR_NONE ) {
            $value = $decoded;
        }
    }

    if ( is_array( $value ) ) {
        $result = array();
        foreach ( $value as $key => $item ) {
            if ( is_string( $item ) && strpos( $item, '-' ) !== false ) {
                $parts = array_map( 'trim', explode( '-', $item, 2 ) );
                $day   = ucfirst( strtolower( (string) $key ) );
                if ( in_array( $day, $days, true ) && count( $parts ) === 2 ) {
                    $result[] = array( 'day' => $day, 'startTime' => $parts[0], 'endTime' => $parts[1] );
                }
                continue;
            }

            if ( is_array( $item ) ) {
                $day = isset( $item['day'] ) ? $item['day'] : ( is_string( $key ) ? $key : '' );
                $day = ucfirst( strtolower( (string) $day ) );
                if ( ! in_array( $day, $days, true ) ) continue;

                $start = $item['startTime'] ?? $item['start'] ?? $item['open'] ?? '';
                $end   = $item['endTime'] ?? $item['end'] ?? $item['close'] ?? '';
                if ( $start || $end ) {
                    $result[] = array( 'day' => $day, 'startTime' => (string) $start, 'endTime' => (string) $end );
                }
            }
        }
        return $result;
    }

    if ( is_string( $value ) && $value !== '' ) {
        $result = array();
        foreach ( preg_split( '/[;\n]+/', $value ) as $row ) {
            $row = trim( $row );
            if ( ! $row || strpos( $row, ':' ) === false ) continue;
            list( $day, $times ) = array_map( 'trim', explode( ':', $row, 2 ) );
            $day = ucfirst( strtolower( $day ) );
            if ( ! in_array( $day, $days, true ) || strpos( $times, '-' ) === false ) continue;
            list( $start, $end ) = array_map( 'trim', explode( '-', $times, 2 ) );
            $result[] = array( 'day' => $day, 'startTime' => $start, 'endTime' => $end );
        }
        return $result;
    }

    return array();
}

function bubbahub_listing_to_app( WP_Post $post ) {
    $post_id = $post->ID;
    $tags    = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );
    $raw_tags = bubbahub_meta( $post_id, array( 'Tags', 'tags', 'keywords', 'post_tags' ), '' );

    if ( is_string( $raw_tags ) && $raw_tags !== '' ) {
        $tags = array_merge( $tags, array_map( 'trim', preg_split( '/[,;]+/', $raw_tags ) ) );
    } elseif ( is_array( $raw_tags ) ) {
        $tags = array_merge( $tags, $raw_tags );
    }
    $tags = array_values( array_unique( array_filter( array_map( 'strval', $tags ) ) ) );

    $image = get_the_post_thumbnail_url( $post_id, 'large' );
    if ( ! $image ) {
        $image = bubbahub_meta( $post_id, array( 'image', 'images', 'featured_image', 'listing_image' ), '' );
    }

    $city    = bubbahub_meta( $post_id, array( 'city', 'City', 'town', 'location_city' ), '' );
    $region  = bubbahub_meta( $post_id, array( 'region', 'Region', 'county', 'location_region' ), '' );
    $address = bubbahub_meta( $post_id, array( 'Address', 'address', 'street', 'street_address' ), '' );
    $zip     = bubbahub_meta( $post_id, array( 'zip', 'Zip', 'postcode', 'postal_code' ), '' );
    $location = implode( ', ', array_filter( array( $address, $city, $zip ) ) );
    $price = bubbahub_meta( $post_id, array( 'price', 'Price', 'pricing', 'listing_price' ), '' );
    $is_free = strtoupper( trim( (string) $price ) ) === 'FREE' || (string) $price === '£0' || (string) $price === '0';
    $featured = bubbahub_meta( $post_id, array( 'isFeatured', 'isfeatured', 'featured', 'listing_featured' ), false );
    $hours = bubbahub_meta( $post_id, array( 'business_hours', 'openingHours', 'opening_hours', 'OpeningHours', 'schedule', 'hours' ), array() );

    return array(
        'ID' => $post_id,
        'id' => (string) $post_id,
        'title' => get_the_title( $post_id ),
        'description' => apply_filters( 'the_content', $post->post_content ),
        'url' => get_permalink( $post_id ),
        'postType' => $post->post_type,
        'status' => $post->post_status,
        'Tags' => implode( ', ', $tags ),
        'tags' => $tags,
        'keywords' => $tags,
        'category' => bubbahub_meta( $post_id, array( 'category', 'Category', 'listing_category' ), 'General' ),
        'Address' => $address,
        'address' => $address,
        'city' => $city,
        'region' => $region,
        'zip' => $zip,
        'postcode' => $zip,
        'location' => $location,
        'image' => $image,
        'images' => $image,
        'ageRange' => bubbahub_meta( $post_id, array( 'ageRange', 'Age Range', 'agerange', 'age_range' ), '' ),
        'sessionLength' => bubbahub_meta( $post_id, array( 'sessionLength', 'Session Length', 'sessionlength', 'session_length' ), '' ),
        'termTime' => bubbahub_meta( $post_id, array( 'termTime', 'Term Time', 'termtime', 'term_time' ), '' ),
        'timetable' => bubbahub_meta( $post_id, array( 'timetable', 'Timetable' ), '' ),
        'price' => $price,
        'isFree' => $is_free,
        'isFeatured' => filter_var( $featured, FILTER_VALIDATE_BOOLEAN ),
        'isClaimed' => filter_var( bubbahub_meta( $post_id, array( 'isClaimed', 'is_claimed', 'claimed' ), true ), FILTER_VALIDATE_BOOLEAN ),
        'hasBooking' => filter_var( bubbahub_meta( $post_id, array( 'hasBooking', 'has_booking', 'booking_enabled' ), true ), FILTER_VALIDATE_BOOLEAN ),
        'organizer_username' => bubbahub_meta( $post_id, array( 'organizer_username', 'organizerUsername', 'organisers_username' ), '' ),
        'leader_user' => bubbahub_meta( $post_id, array( 'leader_user', 'leaderUser', 'leader' ), '' ),
        'contact' => bubbahub_meta( $post_id, array( 'email', 'Email', 'contact' ), '' ),
        'email' => bubbahub_meta( $post_id, array( 'email', 'Email', 'contact' ), '' ),
        'website' => bubbahub_meta( $post_id, array( 'website', 'Website', 'url' ), '' ),
        'facebook' => bubbahub_meta( $post_id, array( 'facebook', 'Facebook' ), '' ),
        'instagram' => bubbahub_meta( $post_id, array( 'instagram', 'Instagram' ), '' ),
        'lat' => (float) bubbahub_meta( $post_id, array( 'manual_lat', 'latitude', 'lat' ), 0 ),
        'lng' => (float) bubbahub_meta( $post_id, array( 'manual_lng', 'longitude', 'lng' ), 0 ),
        'openingHours' => bubbahub_normalize_hours( $hours ),
    );
}

function bubbahub_rest_get_listings( WP_REST_Request $request ) {
    $per_page = min( 1000, max( 1, absint( $request->get_param( 'per_page' ) ?: 1000 ) ) );
    $args = array(
        'post_type' => bubbahub_listing_post_types(),
        'post_status' => 'publish',
        'posts_per_page' => $per_page,
        'paged' => max( 1, absint( $request->get_param( 'page' ) ?: 1 ) ),
        'ignore_sticky_posts' => true,
    );

    $search = sanitize_text_field( (string) $request->get_param( 'search' ) );
    if ( $search !== '' ) $args['s'] = $search;

    $query = new WP_Query( $args );
    $items = array();

    foreach ( $query->posts as $post ) {
        $item = bubbahub_listing_to_app( $post );
        $category = sanitize_text_field( (string) $request->get_param( 'category' ) );
        $city = sanitize_text_field( (string) $request->get_param( 'city' ) );
        $region = sanitize_text_field( (string) $request->get_param( 'region' ) );
        $age = sanitize_text_field( (string) $request->get_param( 'age' ) );
        $featured = $request->get_param( 'featured' );

        if ( $category && strcasecmp( $category, 'All' ) !== 0 && strcasecmp( $category, (string) $item['category'] ) !== 0 ) continue;
        if ( $city && strcasecmp( $city, 'All' ) !== 0 && stripos( (string) $item['city'], $city ) === false ) continue;
        if ( $region && strcasecmp( $region, 'All' ) !== 0 && stripos( (string) $item['region'], $region ) === false ) continue;
        if ( $age && strcasecmp( $age, 'All' ) !== 0 && stripos( (string) $item['ageRange'], $age ) === false ) continue;
        if ( $featured !== null && $featured !== '' && filter_var( $featured, FILTER_VALIDATE_BOOLEAN ) && ! $item['isFeatured'] ) continue;

        $items[] = $item;
    }

    return rest_ensure_response( $items );
}

function bubbahub_rest_save_listing( WP_REST_Request $request ) {
    $data = $request->get_json_params();
    if ( ! is_array( $data ) ) {
        return new WP_Error( 'invalid_payload', 'Invalid listing payload.', array( 'status' => 400 ) );
    }

    $post_id = absint( $data['ID'] ?? $data['id'] ?? 0 );
    $post_types = bubbahub_listing_post_types();
    $post_type = $post_types[0];

    if ( $post_id ) {
        $existing = get_post( $post_id );
        if ( ! $existing || ! in_array( $existing->post_type, $post_types, true ) ) {
            return new WP_Error( 'not_found', 'Listing not found.', array( 'status' => 404 ) );
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission to edit this listing.', array( 'status' => 403 ) );
        }
        $post_type = $existing->post_type;
    } elseif ( ! current_user_can( 'edit_posts' ) ) {
        return new WP_Error( 'forbidden', 'You must be logged in with permission to create listings.', array( 'status' => 403 ) );
    }

    $postarr = array(
        'post_type' => $post_type,
        'post_title' => sanitize_text_field( $data['title'] ?? $data['Title'] ?? 'Untitled Activity' ),
        'post_content' => wp_kses_post( $data['description'] ?? $data['Description'] ?? '' ),
    );

    if ( $post_id ) {
        $postarr['ID'] = $post_id;
        $saved_id = wp_update_post( wp_slash( $postarr ), true );
    } else {
        $postarr['post_status'] = current_user_can( 'publish_posts' ) ? 'publish' : 'draft';
        $saved_id = wp_insert_post( wp_slash( $postarr ), true );
    }

    if ( is_wp_error( $saved_id ) ) return $saved_id;

    $meta_map = array(
        'Tags' => 'Tags', 'category' => 'category', 'Address' => 'Address', 'city' => 'city',
        'region' => 'region', 'zip' => 'zip', 'ageRange' => 'ageRange', 'sessionLength' => 'sessionLength',
        'termTime' => 'termTime', 'timetable' => 'timetable', 'price' => 'price',
        'organizer_username' => 'organizer_username', 'leader_user' => 'leader_user', 'website' => 'website',
        'email' => 'email', 'facebook' => 'facebook', 'instagram' => 'instagram', 'manual_lat' => 'manual_lat',
        'manual_lng' => 'manual_lng', 'isFeatured' => 'isFeatured', 'isClaimed' => 'isClaimed', 'hasBooking' => 'hasBooking',
    );

    foreach ( $meta_map as $source => $meta_key ) {
        if ( array_key_exists( $source, $data ) ) {
            update_post_meta( $saved_id, $meta_key, is_scalar( $data[ $source ] ) ? sanitize_text_field( (string) $data[ $source ] ) : $data[ $source ] );
        }
    }

    if ( ! empty( $data['Tags'] ) ) {
        wp_set_post_tags( $saved_id, array_filter( array_map( 'trim', preg_split( '/[,;]+/', (string) $data['Tags'] ) ) ), false );
    }

    return rest_ensure_response( array(
        'success' => true,
        'id' => $saved_id,
        'listing' => bubbahub_listing_to_app( get_post( $saved_id ) ),
    ) );
}

add_action( 'rest_api_init', function () {
    register_rest_route( 'bubbahub/v1', '/listings', array(
        array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'bubbahub_rest_get_listings',
            'permission_callback' => '__return_true',
        ),
        array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'bubbahub_rest_save_listing',
            'permission_callback' => function () { return is_user_logged_in(); },
        ),
    ) );
} );

function bubbahub_enqueue_assets() {
    $base = BUBBAHUB_APP_URL . 'assests/';
    $version = BUBBAHUB_APP_VERSION;

    wp_enqueue_style( 'bubbahub-css', $base . 'index-CLI2PE_D.css', array(), $version );

    wp_enqueue_script( 'bubbahub-wp-bridge', $base . 'wp-bridge.js', array(), $version, true );
    wp_localize_script( 'bubbahub-wp-bridge', 'BubbaHubWP', array(
        'apiUrl' => esc_url_raw( rest_url( 'bubbahub/v1/listings' ) ),
        'nonce' => wp_create_nonce( 'wp_rest' ),
    ) );

    wp_enqueue_script( 'bubbahub-js', $base . 'index-CkDzJHE0.js', array( 'bubbahub-wp-bridge' ), $version, true );
}
add_action( 'wp_enqueue_scripts', 'bubbahub_enqueue_assets' );

function bubbahub_shortcode() {
    ob_start();
    ?>
    <div id="root" style="width:100%;min-height:100vh;"></div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'bubbahub', 'bubbahub_shortcode' );

function bubbahub_plugin_links( $links ) {
    $links[] = '<a href="https://bubbahub.co.uk" target="_blank">Website</a>';
    $links[] = '<a href="' . esc_url( rest_url( 'bubbahub/v1/listings' ) ) . '" target="_blank">Listings API</a>';
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'bubbahub_plugin_links' );
