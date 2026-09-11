<?php
/**
 * Plugin Name: BubbaHub App
 * Plugin URI:  https://bubbahub.co.uk
 * Description: BubbaHub family & community hub — discover local classes, events, childcare and activities.
 * Version:     1.0.0
 * Author:      BubbaHub
 * License:     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function bubbahub_enqueue_assets() {
    $base    = plugin_dir_url( __FILE__ ) . 'dist/assets/';
    $version = '1.0.0';

    wp_enqueue_style(
        'bubbahub-css',
        $base . 'index-CLI2PE_D.css',
        [],
        $version
    );

    wp_enqueue_script(
        'bubbahub-js',
        $base . 'index-CkDzJHE0.js',
        [],
        $version,
        true  // load in footer
    );
}
add_action( 'wp_enqueue_scripts', 'bubbahub_enqueue_assets' );

/**
 * [bubbahub] shortcode — place on any page to render the app.
 */
function bubbahub_shortcode() {
    ob_start();
    ?>
    <div id="root" style="width:100%;min-height:100vh;"></div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'bubbahub', 'bubbahub_shortcode' );

/**
 * Add a Settings link on the Plugins page.
 */
function bubbahub_plugin_links( $links ) {
    $links[] = '<a href="https://bubbahub.co.uk" target="_blank">Website</a>';
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'bubbahub_plugin_links' );
