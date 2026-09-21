<?php
/**
 * Plugin Name: Bubba Hub
 * Description: Family activity discovery, planning and booking platform for WordPress.
 * Version: 0.1.0
 * Author: Bubba Hub
 * License: GPL-2.0-or-later
 * Requires at least: 6.4
 * Requires PHP: 8.0
 */
defined('ABSPATH') || exit;
define('BHPLUGIN_VERSION','0.1.0');
define('BHPLUGIN_FILE',__FILE__);
define('BHPLUGIN_DIR',plugin_dir_path(__FILE__));
define('BHPLUGIN_URL',plugin_dir_url(__FILE__));
require_once BHPLUGIN_DIR.'includes/class-bhplugin-dependencies.php';
require_once BHPLUGIN_DIR.'includes/class-bhplugin-core.php';
require_once BHPLUGIN_DIR.'includes/class-bhplugin-discovery.php';
add_action('plugins_loaded',static function(){BHPlugin_Dependencies::init();BHPlugin_Core::init();BHPlugin_Discovery::init();});
register_activation_hook(BHPLUGIN_FILE,['BHPlugin_Core','activate']);
register_deactivation_hook(BHPLUGIN_FILE,['BHPlugin_Core','deactivate']);
