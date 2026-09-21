<?php
if (!defined('ABSPATH')) exit;

final class BH_Core {
    private static bool $booted = false;

    public static function boot(): void {
        if (self::$booted) return;
        self::$booted = true;

        add_action('init', [self::class, 'register_content']);
        add_action('init', [self::class, 'register_taxonomies']);
        add_action('rest_api_init', [self::class, 'register_rest']);
        add_shortcode('bubba_hub', [self::class, 'shortcode']);
        add_action('wp_enqueue_scripts', [self::class, 'assets']);
    }

    public static function activate(): void {
        self::register_content();
        self::register_taxonomies();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    public static function register_content(): void {
        register_post_type('bh_activity', [
            'labels' => [
                'name' => 'Activities',
                'singular_name' => 'Activity',
                'add_new_item' => 'Add Activity',
                'edit_item' => 'Edit Activity',
            ],
            'public' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-groups',
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'author'],
            'has_archive' => true,
            'rewrite' => ['slug' => 'activities'],
        ]);

        register_post_type('bh_venue', [
            'labels' => [
                'name' => 'Venues',
                'singular_name' => 'Venue',
            ],
            'public' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-location',
            'supports' => ['title', 'editor', 'thumbnail', 'author'],
            'has_archive' => true,
            'rewrite' => ['slug' => 'venues'],
        ]);
    }

    public static function register_taxonomies(): void {
        $taxonomies = [
            'bh_category' => 'Category',
            'bh_region' => 'Region',
            'bh_age' => 'Age Range',
            'bh_day' => 'Day',
        ];

        foreach ($taxonomies as $taxonomy => $label) {
            register_taxonomy($taxonomy, ['bh_activity'], [
                'labels' => ['name' => $label, 'singular_name' => $label],
                'public' => true,
                'show_in_rest' => true,
                'hierarchical' => true,
                'rewrite' => ['slug' => sanitize_title($label)],
            ]);
        }
    }

    public static function register_rest(): void {
        register_rest_route('bubba-hub/v1', '/health', [
            'methods' => 'GET',
            'callback' => static fn() => [
                'success' => true,
                'plugin' => 'Bubba Hub',
                'version' => BH_VERSION,
            ],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function shortcode(): string {
        return '<div class="bh-app" data-bh-app="1"><div class="bh-app__header"><h2>Find your perfect group</h2><p>Discover family activities, groups and classes near you.</p></div><div id="bh-find-app"></div></div>';
    }

    public static function assets(): void {
        wp_enqueue_style('bh-style', BH_URL . 'assets/css/bh.css', [], BH_VERSION);
        wp_enqueue_script('bh-find', BH_URL . 'assets/js/bh-find.js', [], BH_VERSION, true);
        wp_localize_script('bh-find', 'BubbaHub', [
            'restUrl' => esc_url_raw(rest_url('bubba-hub/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }
}
