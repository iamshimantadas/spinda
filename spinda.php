<?php
/**
 * Plugin Name: Spinda – Export & Import Posts, Post Meta, Products, Taxonomies, Users & User Meta
 * Plugin URI: https://wordpress.org/plugins/spinda-exportimport-data/
 * Description: Export and import post-types, products, users, and more between WordPress sites
 * Version: 2.0.1
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
define('SPINEXIM_VERSION', '2.0.1');
define('SPINEXIM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SPINEXIM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SPINEXIM_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('SPINEXIM_PLUGIN_FILE', __FILE__);
define('SPINEXIM_TEXT_DOMAIN','spinda-exportimport-data');

/**
 * Main plugin class
 */
final class Spinda_Main {

    /**
     * Single instance
     *
     * @var Spinda_Main
     */
    private static $instance = null;

    /**
     * Get single instance
     *
     * @return Spinda_Main
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->define_hooks();
    }

    /**
     * Define hooks
     */
    private function define_hooks() {
        // Check WooCommerce dependency
        add_action('admin_init', array($this, 'check_woocommerce_dependency'));
        
        // Add admin menu
        add_action('admin_menu', array($this, 'spinexim_add_admin_menu'), 99);
        
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', array($this, 'spinexim_enqueue_admin_assets'));
        
        // Include modules
        add_action('plugins_loaded', array($this, 'spinexim_include_modules'));
        
        // Register activation hook
        register_activation_hook(SPINEXIM_PLUGIN_FILE, array($this, 'spinda_activate'));
        
        // Register deactivation hook
        register_deactivation_hook(SPINEXIM_PLUGIN_FILE, array($this, 'spinexim_deactivate'));
    }

    /**
     * Check WooCommerce dependency
     */
    public function check_woocommerce_dependency() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
        }
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        $class = 'notice notice-warning';
        $message = sprintf(
            /* translators: %s: WooCommerce plugin URL */
            __('Spinda works best with WooCommerce. Some features require WooCommerce to be installed and active. You can download %s here.','spinda-exportimport-data'),
            '<a href="' . esc_url('https://wordpress.org/plugins/woocommerce/') . '" target="_blank">WooCommerce</a>'
        );

        printf(
            '<div class="%1$s"><p>%2$s</p></div>',
            esc_attr($class),
            wp_kses(
                $message,
                array(
                    'a' => array(
                        'href' => array(),
                        'target' => array(),
                    ),
                )
            )
        );
    }

    /**
     * Include modules
     */
    public function spinexim_include_modules() {
        // Always include all modules
        require_once SPINEXIM_PLUGIN_DIR . 'includes/class-spinexim-woo-products-export.php';
        require_once SPINEXIM_PLUGIN_DIR . 'includes/class-spinexim-woo-products-import.php';
        require_once SPINEXIM_PLUGIN_DIR . 'includes/class-spinexim-post-types-export.php';
        require_once SPINEXIM_PLUGIN_DIR . 'includes/class-spinexim-post-types-import.php';
        require_once SPINEXIM_PLUGIN_DIR . 'includes/class-spinexim-taxonomies-export.php';
        require_once SPINEXIM_PLUGIN_DIR . 'includes/class-spinexim-taxonomies-import.php';
        require_once SPINEXIM_PLUGIN_DIR . 'includes/class-spinexim-users-export.php';
        require_once SPINEXIM_PLUGIN_DIR . 'includes/class-spinexim-users-import.php';
    }

    /**
     * Add admin menu
     */
    public function spinexim_add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('Spinda','spinda-exportimport-data'),
            __('Spinda','spinda-exportimport-data'),
            // 'manage_options',
            '',
            'spinda-exportimport-data',
            array($this, 'render_main_page'),
            SPINEXIM_PLUGIN_URL . 'assets/icon.png',
            56
        );

        // WooCommerce Export submenu (always show)
        add_submenu_page(
            'spinda-exportimport-data',
            __('Spinda Export','spinda-exportimport-data'),
            __('Woo Products Export','spinda-exportimport-data'),
            'manage_options',
            'spinexim-export',
            array($this, 'spinexim_woo_render_export_page')
        );

        // WooCommerce Import submenu (always show)
        add_submenu_page(
            'spinda-exportimport-data',
            __('Spinda Import','spinda-exportimport-data'),
            __('Woo Products Import','spinda-exportimport-data'),
            'manage_options',
            'spinexim-import',
            array($this, 'spinexim_woo_render_import_page')
        );

        // Post Types Export submenu
        add_submenu_page(
            'spinda-exportimport-data',
            __('Spinda - Post Types Export','spinda-exportimport-data'),
            __('Post Types Export','spinda-exportimport-data'),
            'manage_options',
            'spinexim-post-export',
            array($this, 'spinexim_render_post_export_page')
        );

        // Post Types Import submenu
        add_submenu_page(
            'spinda-exportimport-data',
            __('Spinda - Post Types Import','spinda-exportimport-data'),
            __('Post Types Import','spinda-exportimport-data'),
            'manage_options',
            'spinexim-post-import',
            array($this, 'spinexim_render_post_import_page')
        );

        // Taxonomies Export submenu
        add_submenu_page(
            'spinda-exportimport-data',
            __('Spinda - Taxonomies Export','spinda-exportimport-data'),
            __('Taxonomies Export','spinda-exportimport-data'),
            'manage_options',
            'spinexim-taxonomies-export',
            array($this, 'spinexim_render_taxonomies_export_page')
        );

        // Taxonomies Import submenu
        add_submenu_page(
            'spinda-exportimport-data',
            __('Spinda - Taxonomies Import','spinda-exportimport-data'),
            __('Taxonomies Import','spinda-exportimport-data'),
            'manage_options',
            'spinexim-taxonomies-import',
            array($this, 'spinexim_render_taxonomies_import_page')
        );

        // Users Export submenu
        add_submenu_page(
            'spinda-exportimport-data',
            __('Spinda - Users Export','spinda-exportimport-data'),
            __('Users Export','spinda-exportimport-data'),
            'manage_options',
            'spinexim-users-export',
            array($this, 'spinexim_render_users_export_page')
        );

        // Users Import submenu
        add_submenu_page(
            'spinda-exportimport-data',
            __('Spinda - Users Import','spinda-exportimport-data'),
            __('Users Import','spinda-exportimport-data'),
            'manage_options',
            'spinexim-users-import',
            array($this, 'spinexim_render_users_import_page')
        );

        // Remove default first submenu that duplicates main menu
        remove_submenu_page('spinexim','spinda-exportimport-data');
    }

    /**
     * Render main page
     */
    public function render_main_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.','spinda-exportimport-data'));
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Spinda - Export Import Data','spinda-exportimport-data'); ?></h1>
            
            <div class="spinexim-card">
                <h2><?php echo esc_html__('Welcome to Spinda','spinda-exportimport-data'); ?></h2>
                <p><?php echo esc_html__('Export and import your data easily with Spinda.','spinda-exportimport-data'); ?></p>
                <p><?php echo esc_html__('Choose an option below to get started:','spinda-exportimport-data'); ?></p>
                
                <div class="spinexim-grid">
                    <div class="spinexim-grid-item">
                        <span class="dashicons dashicons-download spinexim-grid-icon"></span>
                        <h3><?php echo esc_html__('Export Products','spinda-exportimport-data'); ?></h3>
                        <p><?php echo esc_html__('Export WooCommerce products including categories, tags, attributes, reviews, and images.','spinda-exportimport-data'); ?></p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=spinexim-export')); ?>" class="button button-primary">
                            <?php echo esc_html__('Go to Export','spinda-exportimport-data'); ?>
                        </a>
                    </div>
                    
                    <div class="spinexim-grid-item">
                        <span class="dashicons dashicons-upload spinexim-grid-icon"></span>
                        <h3><?php echo esc_html__('Import Products','spinda-exportimport-data'); ?></h3>
                        <p><?php echo esc_html__('Import WooCommerce products from an export file. Supports ACF fields, galleries, and more.','spinda-exportimport-data'); ?></p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=spinexim-import')); ?>" class="button button-primary">
                            <?php echo esc_html__('Go to Import','spinda-exportimport-data'); ?>
                        </a>
                    </div>
                    
                    <div class="spinexim-grid-item">
                        <span class="dashicons dashicons-admin-post spinexim-grid-icon"></span>
                        <h3><?php echo esc_html__('Export Post Types','spinda-exportimport-data'); ?></h3>
                        <p><?php echo esc_html__('Export any post type content including taxonomies, meta fields, and comments.','spinda-exportimport-data'); ?></p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=spinexim-post-export')); ?>" class="button button-primary">
                            <?php echo esc_html__('Post Types Export','spinda-exportimport-data'); ?>
                        </a>
                    </div>
                    
                    <div class="spinexim-grid-item">
                        <span class="dashicons dashicons-category spinexim-grid-icon"></span>
                        <h3><?php echo esc_html__('Export Taxonomies','spinda-exportimport-data'); ?></h3>
                        <p><?php echo esc_html__('Export taxonomy terms including categories, tags with their metadata and hierarchy.','spinda-exportimport-data'); ?></p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=spinexim-taxonomies-export')); ?>" class="button button-primary">
                            <?php echo esc_html__('Taxonomies Export','spinda-exportimport-data'); ?>
                        </a>
                    </div>
                    
                    <div class="spinexim-grid-item">
                        <span class="dashicons dashicons-admin-users spinexim-grid-icon"></span>
                        <h3><?php echo esc_html__('Export Users','spinda-exportimport-data'); ?></h3>
                        <p><?php echo esc_html__('Export users by role including all metadata, ACF fields, and profile information.','spinda-exportimport-data'); ?></p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=spinexim-users-export')); ?>" class="button button-primary">
                            <?php echo esc_html__('Users Export','spinda-exportimport-data'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render export page
     */
    public function spinexim_woo_render_export_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.','spinda-exportimport-data'));
        }
        
        if (class_exists('Spinexim_Woo_Products_Export')) {
            $export = new Spinexim_Woo_Products_Export();
            $export->render_page();
        }
    }

    /**
     * Render import page
     */
    public function spinexim_woo_render_import_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.','spinda-exportimport-data'));
        }
        
        if (class_exists('Spinexim_Woo_Products_Import')) {
            $import = new Spinexim_Woo_Products_Import();
            $import->render_page();
        }
    }

    /**
     * Render post export page
     */
    public function spinexim_render_post_export_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.','spinda-exportimport-data'));
        }
        
        if (class_exists('Spinexim_Post_Types_Export')) {
            $export = new Spinexim_Post_Types_Export();
            $export->render_page();
        }
    }

    /**
     * Render post import page
     */
    public function spinexim_render_post_import_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.','spinda-exportimport-data'));
        }
        
        if (class_exists('Spinexim_Post_Types_Import')) {
            $import = new Spinexim_Post_Types_Import();
            $import->render_page();
        }
    }

    /**
     * Render taxonomies export page
     */
    public function spinexim_render_taxonomies_export_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.','spinda-exportimport-data'));
        }
        
        if (class_exists('Spinexim_Taxonomies_Export')) {
            $export = new Spinexim_Taxonomies_Export();
            $export->render_page();
        }
    }

    /**
     * Render taxonomies import page
     */
    public function spinexim_render_taxonomies_import_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.','spinda-exportimport-data'));
        }
        
        if (class_exists('Spinexim_Taxonomies_Import')) {
            $import = new Spinexim_Taxonomies_Import();
            $import->render_page();
        }
    }

    /**
     * Render users export page
     */
    public function spinexim_render_users_export_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.','spinda-exportimport-data'));
        }
        
        if (class_exists('Spinexim_Users_Export')) {
            $export = new Spinexim_Users_Export();
            $export->render_page();
        }
    }

    /**
     * Render users import page
     */
    public function spinexim_render_users_import_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.','spinda-exportimport-data'));
        }
        
        if (class_exists('Spinexim_Users_Import')) {
            $import = new Spinexim_Users_Import();
            $import->render_page();
        }
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook.
     */
    public function spinexim_enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        $spinexim_pages = array(
            'toplevel_page_spinexim',
            'spinda_page_spinexim-export',
            'spinda_page_spinexim-import',
            'spinda_page_spinexim-post-export',
            'spinda_page_spinexim-post-import',
            'spinda_page_spinexim-taxonomies-export',
            'spinda_page_spinexim-taxonomies-import',
            'spinda_page_spinexim-users-export',
            'spinda_page_spinexim-users-import',
        );

        if (!in_array($hook, $spinexim_pages, true)) {
            return;
        }

        // Enqueue Dashicons
        wp_enqueue_style('dashicons');

        // Enqueue CSS
        wp_enqueue_style(
            'spinexim-admin',
            SPINEXIM_PLUGIN_URL . 'assets/css/spinda.css',
            array(),
            SPINEXIM_VERSION
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'spinexim-admin',
            SPINEXIM_PLUGIN_URL . 'assets/js/spinda.js',
            array('jquery'),
            SPINEXIM_VERSION,
            true
        );

        // Localize script with secure data
        wp_localize_script(
            'spinexim-admin',
            'spineximData',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('spinexim_admin_nonce'),
                'post_export_nonce' => wp_create_nonce('spinexim_post_export_ajax'),
                'tax_export_nonce' => wp_create_nonce('spinexim_tax_export_ajax'),
                'user_export_nonce' => wp_create_nonce('spinexim_user_export_ajax'),
                'i18n' => array(
                    'confirm_import' => esc_html__('Are you sure you want to start the import? This may overwrite existing data.','spinda-exportimport-data'),
                    'confirm_post_import' => esc_html__('Are you sure you want to start the import? This may take a while for large files.','spinda-exportimport-data'),
                    'confirm_tax_import' => esc_html__('Are you sure you want to start the taxonomies import? This may take a while for large files.','spinda-exportimport-data'),
                    'confirm_user_import' => esc_html__('Are you sure you want to start the users import? This may take a while for large files.','spinda-exportimport-data'),
                    'confirm_reset' => esc_html__('Are you sure you want to reset all running imports?','spinda-exportimport-data'),
                    'import_complete' => esc_html__('Import completed successfully!','spinda-exportimport-data'),
                    'import_error' => esc_html__('An error occurred during import.','spinda-exportimport-data'),
                    'processing' => esc_html__('Processing...','spinda-exportimport-data'),
                    'export_started' => esc_html__('Export started. Your download will begin shortly.','spinda-exportimport-data'),
                    'loading_taxonomies' => esc_html__('Loading taxonomies...','spinda-exportimport-data'),
                    'select_all' => esc_html__('Select All','spinda-exportimport-data'),
                    'no_taxonomies' => esc_html__('No taxonomies available for this post type.','spinda-exportimport-data'),
                    'error_loading_taxonomies' => esc_html__('Error loading taxonomies. Please try again.','spinda-exportimport-data'),
                    'terms' => esc_html__('terms','spinda-exportimport-data'),
                    'hierarchical' => esc_html__('hierarchical','spinda-exportimport-data'),
                    'select_file' => esc_html__('Please select a file to upload.','spinda-exportimport-data'),
                ),
            )
        );
    }

    /**
     * Plugin activation
     */
    public function spinda_activate() {
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            wp_die(
                esc_html__('Spinda requires WordPress version 5.0 or higher.','spinda-exportimport-data'),
                esc_html__('Plugin Activation Error','spinda-exportimport-data'),
                array('back_link' => true)
            );
        }

        // Check PHP version
        if (version_compare(PHP_VERSION, '7.2', '<')) {
            wp_die(
                esc_html__('Spinda requires PHP version 7.2 or higher.','spinda-exportimport-data'),
                esc_html__('Plugin Activation Error','spinda-exportimport-data'),
                array('back_link' => true)
            );
        }

        // Set version
        add_option('spinexim_version', SPINEXIM_VERSION);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function spinexim_deactivate() {
        // Clean up transients
        $this->cleanup_transients();
        
        // Clear scheduled hooks
        wp_clear_scheduled_hook('spinexim_import_process_next');
        wp_clear_scheduled_hook('spinexim_import_process_batch');
        wp_clear_scheduled_hook('spinexim_post_import_batch');
        wp_clear_scheduled_hook('spinexim_taxonomies_import_batch');
        wp_clear_scheduled_hook('spinexim_users_import_batch');
        wp_clear_scheduled_hook('spinexim_update_ratings');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Clean up transients
     */
    private function cleanup_transients() {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '%_transient_spinexim_%',
                '%_transient_timeout_spinexim_%'
            )
        );

        wp_cache_flush();
    }
}

/**
 * Initialize the plugin
 *
 * @return Spinda_Main
 */
function spinexim_init() {
    return Spinda_Main::instance();
}

// Start the plugin
spinexim_init();