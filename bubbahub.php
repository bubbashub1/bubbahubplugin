<?php
/**
 * Plugin Name: Bubba Hub
 * Description: Clean modular foundation for the Bubba Hub family activity directory.
 * Version: 2.0.0
 * Author: Bubba Hub
 * Requires at least: 6.4
 * Requires PHP: 8.0
 */
if (!defined('ABSPATH')) exit;

define('BH_VERSION', '2.0.0');
define('BH_FILE', __FILE__);
define('BH_DIR', plugin_dir_path(__FILE__));
define('BH_URL', plugin_dir_url(__FILE__));

require_once BH_DIR . 'includes/class-bh-core.php';
require_once BH_DIR . 'includes/class-bh-find.php';

add_action('plugins_loaded', static function () {
    BH_Core::boot();
    BH_Find::boot();
});

register_activation_hook(__FILE__, ['BH_Core', 'activate']);
register_deactivation_hook(__FILE__, ['BH_Core', 'deactivate']);
