<?php
/**
 * Plugin Name: Spinda – Export Import Data
 * Plugin URI: https://wordpress.org/plugins/spinda-exportimport-data/
 * Description: Export and import post-types, products, post-meta, users, and more between WordPress sites
 * Version: 1.0.1
 * Author: microcodes
 * Author URI: https://microcodes.in
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: spinexim
 * 
 * @package Spinda
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('SPINEXIM_VERSION', '1.0.1');
define('SPINEXIM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SPINEXIM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SPINEXIM_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('SPINEXIM_TEXT_DOMAIN', 'spinda-exportimport-data');

// Include main class
require_once SPINEXIM_PLUGIN_DIR . 'includes/class-spinexim.php';

/**
 * Initialize plugin
 * 
 * @since 1.0.0
 * @return void
 */
function spinexim_init() {
    Spinexim::get_instance();
}
add_action('plugins_loaded', 'spinexim_init');