<?php
if (!defined('ABSPATH')) exit;

final class BH_Find {
    private static bool $booted = false;

    public static function boot(): void {
        if (self::$booted) return;
        self::$booted = true;
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void {
        register_rest_route('bubba-hub/v1', '/activities', [
            'methods' => 'GET',
            'callback' => [self::class, 'activities'],
            'permission_callback' => '__return_true',
            'args' => [
                'search' => ['type' => 'string', 'default' => ''],
                'region' => ['type' => 'string', 'default' => ''],
                'category' => ['type' => 'string', 'default' => ''],
                'age' => ['type' => 'string', 'default' => ''],
                'day' => ['type' => 'string', 'default' => ''],
                'free' => ['type' => 'boolean', 'default' => false],
                'page' => ['type' => 'integer', 'default' => 1, 'minimum' => 1],
                'per_page' => ['type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 50],
            ],
        ]);
    }

    public static function activities(WP_REST_Request $request): WP_REST_Response {
        $page = max(1, (int) $request->get_param('page'));
        $per_page = min(50, max(1, (int) $request->get_param('per_page')));

        $tax_query = [];
        foreach ([
            'bh_region' => 'region',
            'bh_category' => 'category',
            'bh_age' => 'age',
            'bh_day' => 'day',
        ] as $taxonomy => $param) {
            $value = sanitize_text_field((string) $request->get_param($param));
            if ($value !== '') {
                $tax_query[] = [
                    'taxonomy' => $taxonomy,
                    'field' => 'slug',
                    'terms' => sanitize_title($value),
                ];
            }
        }

        $query = new WP_Query([
            'post_type' => 'bh_activity',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            's' => sanitize_text_field((string) $request->get_param('search')),
            'tax_query' => $tax_query,
            'meta_query' => self::free_meta_query($request),
            'no_found_rows' => false,
        ]);

        $items = [];
        foreach ($query->posts as $post) {
            $items[] = [
                'id' => $post->ID,
                'title' => get_the_title($post),
                'url' => get_permalink($post),
                'excerpt' => wp_trim_words(wp_strip_all_tags($post->post_excerpt ?: $post->post_content), 35),
                'image' => get_the_post_thumbnail_url($post, 'medium'),
                'venue' => self::meta($post->ID, 'venue'),
                'town' => self::meta($post->ID, 'town'),
                'region' => self::meta($post->ID, 'region'),
                'price' => self::meta($post->ID, 'price'),
                'age_range' => self::meta($post->ID, 'age_range'),
                'session_length' => self::meta($post->ID, 'session_length'),
                'term_time' => self::meta($post->ID, 'term_time'),
                'latitude' => self::meta($post->ID, 'latitude'),
                'longitude' => self::meta($post->ID, 'longitude'),
            ];
        }

        return new WP_REST_Response([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'pages' => (int) $query->max_num_pages,
                'total' => (int) $query->found_posts,
            ],
        ]);
    }

    private static function free_meta_query(WP_REST_Request $request): array {
        if (!(bool) $request->get_param('free')) return [];
        return [[
            'key' => 'is_free',
            'value' => '1',
            'compare' => '=',
        ]];
    }

    private static function meta(int $post_id, string $key): string {
        return (string) get_post_meta($post_id, '_bh_' . $key, true);
    }
}
