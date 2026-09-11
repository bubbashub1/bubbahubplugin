<?php
/**
 * Plugin Name: BubbaHub App
 * Plugin URI: https://bubbahub.co.uk
 * Description: BubbaHub Figma frontend with a native WordPress backend. WordPress stores listings, events and user data; Google Sheets is an import/sync source only.
 * Version: 1.3.3
 * Author: BubbaHub
 * License: GPL-2.0+
 * Requires at least: 6.4
 * Requires PHP: 8.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! defined( 'BUBBAHUB_APP_VERSION' ) ) define( 'BUBBAHUB_APP_VERSION', '1.3.3' );
if ( ! defined( 'BUBBAHUB_APP_DIR' ) ) define( 'BUBBAHUB_APP_DIR', plugin_dir_path( __FILE__ ) );
if ( ! defined( 'BUBBAHUB_APP_URL' ) ) define( 'BUBBAHUB_APP_URL', plugin_dir_url( __FILE__ ) );

require_once BUBBAHUB_APP_DIR . 'includes/class-bubbahub-backend.php';
require_once BUBBAHUB_APP_DIR . 'includes/class-bubbahub-meta.php';

// Boot the native WordPress backend exactly once from the main plugin file.
if ( class_exists( 'BubbaHubPlugin_Backend' ) && method_exists( 'BubbaHubPlugin_Backend', 'boot' ) ) {
    BubbaHubPlugin_Backend::boot();
}
if ( class_exists( 'BubbaHubPlugin_Meta' ) && method_exists( 'BubbaHubPlugin_Meta', 'boot' ) ) {
    BubbaHubPlugin_Meta::boot();
}

register_activation_hook( __FILE__, array( 'BubbaHubPlugin_Backend', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'BubbaHubPlugin_Backend', 'deactivate' ) );

function bubbahub_app_enqueue_assets_130() {
    static $loaded = false;
    if ( $loaded ) return;
    $loaded = true;

    $base = BUBBAHUB_APP_URL . 'assests/';
    $v = BUBBAHUB_APP_VERSION;

    wp_enqueue_style( 'bubbahub-app-css', $base . 'index-CLI2PE_D.css', array(), $v );

    if ( file_exists( BUBBAHUB_APP_DIR . 'assests/listing-card-fix.css' ) ) {
        wp_enqueue_style( 'bubbahub-listing-card-fix', $base . 'listing-card-fix.css', array( 'bubbahub-app-css' ), $v );
    }

    if ( file_exists( BUBBAHUB_APP_DIR . 'assests/wp-bridge.js' ) ) {
        wp_enqueue_script( 'bubbahub-app-wp-bridge', $base . 'wp-bridge.js', array(), $v, true );
        wp_localize_script( 'bubbahub-app-wp-bridge', 'BubbaHubWP', array(
            'apiUrl'      => esc_url_raw( rest_url( 'bubbahub/v1/listings' ) ),
            'eventsUrl'   => esc_url_raw( rest_url( 'bubbahub/v1/events' ) ),
            'meUrl'       => esc_url_raw( rest_url( 'bubbahub/v1/me' ) ),
            'profileUrl'  => esc_url_raw( rest_url( 'bubbahub/v1/profile' ) ),
            'nonce'       => wp_create_nonce( 'wp_rest' ),
        ) );
    }

    wp_enqueue_script( 'bubbahub-app-js', $base . 'index-CkDzJHE0.js', array( 'bubbahub-app-wp-bridge' ), $v, true );

    if ( file_exists( BUBBAHUB_APP_DIR . 'assests/listing-card-fix.js' ) ) {
        wp_enqueue_script( 'bubbahub-listing-card-fix', $base . 'listing-card-fix.js', array( 'bubbahub-app-js' ), $v, true );
    }
}

// Render exactly one React mount point per page. React 19 requires a single
// createRoot() for a given DOM container; duplicate shortcode instances can
// otherwise cause production error #299.
add_shortcode( 'bubbahub', function () {
    static $rendered = false;
    if ( $rendered ) return '';
    $rendered = true;

    bubbahub_app_enqueue_assets_130();
    return '<div id="root" style="width:100%;min-height:100vh;"></div>';
} );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
    $links[] = '<a href="' . esc_url( admin_url( 'edit.php?post_type=bh_group&page=bubbahub-plugin-import' ) ) . '">Import / Sync</a>';
    $links[] = '<a href="' . esc_url( rest_url( 'bubbahub/v1/listings' ) ) . '" target="_blank">Listings API</a>';
    return $links;
} );
