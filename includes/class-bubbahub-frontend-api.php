<?php
/**
 * BubbaHub frontend listings API.
 * Provides a stable read-only endpoint for the Figma frontend.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'BubbaHubPlugin_Frontend_API' ) ) {
class BubbaHubPlugin_Frontend_API {
    public static function boot() {
        add_action( 'rest_api_init', array( __CLASS__, 'register' ) );
    }

    public static function register() {
        register_rest_route( 'bubbahub/v1', '/frontend-listings', array(
            'methods'  => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => array( __CLASS__, 'listings' ),
        ) );
    }

    private static function meta( $id, $key, $default = '' ) {
        $value = get_post_meta( $id, '_bubbahub_' . $key, true );
        return $value === '' || $value === null ? $default : $value;
    }

    private static function bool_meta( $id, $key ) {
        return self::meta( $id, $key, '0' ) === '1';
    }

    public static function listings() {
        $posts = get_posts( array(
            'post_type'      => 'bh_group',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );

        $out = array();
        foreach ( $posts as $post ) {
            $id = $post->ID;
            $image = get_the_post_thumbnail_url( $id, 'large' );
            if ( ! $image ) {
                $images = self::meta( $id, 'images' );
                if ( is_array( $images ) ) {
                    $image = (string) reset( $images );
                } elseif ( $images ) {
                    $parts = preg_split( '/[,;\\n]+/', (string) $images );
                    $image = trim( $parts[0] ?? '' );
                }
            }

            $city   = self::meta( $id, 'city' );
            $region = self::meta( $id, 'region' );
            $street = self::meta( $id, 'street' );
            $zip    = self::meta( $id, 'zip' );
            $location = implode( ', ', array_filter( array( $street, $city, $zip ) ) );

            $tags = self::meta( $id, 'tags' );
            $keywords = array();
            if ( is_array( $tags ) ) {
                $keywords = array_values( array_filter( array_map( 'strval', $tags ) ) );
            } elseif ( $tags !== '' ) {
                $keywords = array_values( array_filter( array_map( 'trim', preg_split( '/[,;]+/', (string) $tags ) ) ) );
            }

            $out[] = array(
                'ID'                 => (string) $id,
                'id'                 => (string) $id,
                'listing_id'         => (string) $id,
                'title'              => get_the_title( $id ),
                'description'        => $post->post_content,
                'Address'            => $street,
                'address'            => $street,
                'city'               => $city,
                'region'             => $region,
                'zip'                => $zip,
                'postcode'           => $zip,
                'location'           => $location,
                'price'              => self::meta( $id, 'price' ),
                'category'           => self::meta( $id, 'category', 'General' ),
                'ageRange'           => self::meta( $id, 'age_range' ),
                'age range'          => self::meta( $id, 'age_range' ),
                'sessionLength'      => self::meta( $id, 'session_length' ),
                'session length'     => self::meta( $id, 'session_length' ),
                'termTime'           => self::meta( $id, 'term_time' ),
                'term time'          => self::meta( $id, 'term_time' ),
                'timetable'          => self::meta( $id, 'timetable' ),
                'openingHours'       => self::meta( $id, 'business_hours' ),
                'opening_hours'      => self::meta( $id, 'business_hours' ),
                'business_hours'     => self::meta( $id, 'business_hours' ),
                'email'              => self::meta( $id, 'email' ),
                'website'            => self::meta( $id, 'website' ),
                'facebook'           => self::meta( $id, 'facebook' ),
                'instagram'          => self::meta( $id, 'instagram' ),
                'organizer_username' => self::meta( $id, 'organizer_username', self::meta( $id, 'google_wp_username' ) ),
                'isFeatured'         => self::bool_meta( $id, 'featured' ),
                'isfeatured'         => self::bool_meta( $id, 'featured' ),
                'isFree'             => self::bool_meta( $id, 'is_free' ),
                'sen'                => self::meta( $id, 'sen' ),
                'Tags'               => implode( ', ', $keywords ),
                'tags'               => implode( ', ', $keywords ),
                'keywords'           => $keywords,
                'image'              => $image,
                'images'             => $image,
                'featured'           => self::bool_meta( $id, 'featured' ),
                'google_id'          => self::meta( $id, 'google_id' ),
            );
        }

        return rest_ensure_response( array(
            'success' => true,
            'data'    => $out,
            'listings'=> $out,
            'results' => $out,
            'total'   => count( $out ),
            'pages'   => 1,
        ) );
    }
}
}
