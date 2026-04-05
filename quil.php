<?php
/**
 * Plugin Name: Quil – Export/Import WordPress Data
 * Plugin URI: https://microcodes.in
 * Description: Export and import post-types, products, post-meta, users, and more between WordPress sites
 * Version: 1.0.0
 * Author: microcodes
 * Author URI: https://microcodes.in
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: quil
 * 
 * @package Quil
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('QUIL_VERSION', '1.0.0');
define('QUIL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('QUIL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('QUIL_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include main class
require_once QUIL_PLUGIN_DIR . 'includes/class-quil.php';

/**
 * Initialize plugin
 * 
 * @since 1.0.0
 * @return void
 */
function mc_quil_init() {
    Quil::get_instance();
}
add_action('plugins_loaded', 'mc_quil_init');

/**
 * Activation hook
 * 
 * @since 1.0.0
 * @return void
 */
function mc_quil_activate() {
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'mc_quil_activate');

/**
 * Deactivation hook
 * 
 * @since 1.0.0
 * @return void
 */
function mc_quil_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'mc_quil_deactivate');