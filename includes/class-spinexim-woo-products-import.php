<?php
/**
 * Spinda - WooCommerce Products Import Module
 *
 * @package Spinda
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Import module class
 */
class Spinexim_Woo_Products_Import {

    /**
     * Constructor
     */
    public function __construct() {
        // Handle import request
        add_action('admin_init', array($this, 'handle_import'));
        
        // Handle stop/resume/cancel import
        add_action('admin_init', array($this, 'handle_stop_import'));
        
        // Process import batches
        add_action('spinexim_import_process_next', array($this, 'process_next_batch'));
        
        // Update ratings
        add_action('spinexim_update_ratings', array($this, 'update_product_ratings'));
        
        // Force cleanup
        add_action('admin_init', array($this, 'force_cleanup'));
        
        // AJAX handler for stopping import
        add_action('wp_ajax_spinexim_stop_import', array($this, 'ajax_stop_import'));
    }

    /**
     * Render import page
     */
    public function render_page() {
        $import_running = get_transient('spinexim_import_running');
        $import_paused = get_transient('spinexim_import_paused');
        $progress = get_transient('spinexim_import_progress') ?: 0;
        $message = get_transient('spinexim_import_message') ?: __('Ready to import','spinda-exportimport-data');
        $current_index = get_transient('spinexim_import_current_index') ?: 0;
        $total_products = get_transient('spinexim_import_total_products') ?: 0;
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Spinda - WooCommerce Products Import','spinda-exportimport-data'); ?></h1>
            
            <?php if ($import_running): ?>
                <div class="notice notice-info spinexim-import-notice">
                    <h3>
                        <?php if ($import_paused): ?>
                            <?php echo esc_html__('⏸️ Import Paused','spinda-exportimport-data'); ?>
                        <?php else: ?>
                            <?php echo esc_html__('⏳ Import in Progress...','spinda-exportimport-data'); ?>
                        <?php endif; ?>
                    </h3>
                    
                    <div class="spinexim-progress-bar">
                        <div class="spinexim-progress-fill" style="width:<?php echo esc_attr($progress); ?>%;">
                            <?php echo esc_html($progress); ?>%
                        </div>
                    </div>
                    
                    <p>
                        <strong><?php echo esc_html__('Status:','spinda-exportimport-data'); ?></strong> 
                        <?php echo esc_html($message); ?>
                    </p>
                    
                    <p>
                        <strong><?php echo esc_html__('Progress:','spinda-exportimport-data'); ?></strong>
                        <?php 
                        printf(
                            /* translators: 1: Current index, 2: Total products */
                            esc_html__('%1$d of %2$d products processed','spinda-exportimport-data'),
                            intval($current_index),
                            intval($total_products)
                        ); 
                        ?>
                    </p>
                    
                    <?php if (!$import_paused): ?>
                        <p><em><?php echo esc_html__('Please wait. Do not close this page. This page will refresh automatically.','spinda-exportimport-data'); ?></em></p>
                        
                        <p>
                            <button type="button" class="button button-secondary spinexim-stop-import" id="spinexim-stop-import-btn">
                                <span class="dashicons dashicons-controls-pause"></span>
                                <?php echo esc_html__('Stop/Pause Import','spinda-exportimport-data'); ?>
                            </button>
                        </p>
                    <?php else: ?>
                        <div class="spinexim-pause-notice">
                            <p><strong><?php echo esc_html__('Import has been paused. What would you like to do?','spinda-exportimport-data'); ?></strong></p>
                            <p>
                                <?php
                                $resume_url = wp_nonce_url(
                                    add_query_arg(
                                        array(
                                            'page' => 'spinexim-import',
                                            'spinexim_resume_import' => 1,
                                        ),
                                        admin_url('admin.php')
                                    ),
                                    'spinexim_resume_import'
                                );
                                $cancel_url = wp_nonce_url(
                                    add_query_arg(
                                        array(
                                            'page' => 'spinexim-import',
                                            'spinexim_cancel_import' => 1,
                                        ),
                                        admin_url('admin.php')
                                    ),
                                    'spinexim_cancel_import'
                                );
                                ?>
                                <a href="<?php echo esc_url($resume_url); ?>" class="button button-primary">
                                    <span class="dashicons dashicons-controls-play"></span>
                                    <?php echo esc_html__('Resume Import','spinda-exportimport-data'); ?>
                                </a>
                                <a href="<?php echo esc_url($cancel_url); ?>" class="button button-secondary" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to cancel the import? Processed data will be kept, but remaining items will not be imported.','spinda-exportimport-data')); ?>');">
                                    <span class="dashicons dashicons-no"></span>
                                    <?php echo esc_html__('Cancel Import','spinda-exportimport-data'); ?>
                                </a>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
                
            <?php else: ?>
            
                <div class="spinexim-card">
                    <h2><?php echo esc_html__('Import Products from Export File','spinda-exportimport-data'); ?></h2>
                    <p><?php echo esc_html__('Upload the export file to import ALL product data including variations, attributes, ACF fields, reviews, and images.','spinda-exportimport-data'); ?></p>
                    
                    <?php
                    // Display import results if available
                    $nonce = isset($_GET['import_nonce']) ? sanitize_text_field(wp_unslash($_GET['import_nonce'])) : '';
                    if (isset($_GET['import_results']) && !empty($nonce) && wp_verify_nonce($nonce, 'spinexim_import_results')):
                        $results = get_transient('spinexim_import_results');
                        if ($results):
                    ?>
                        <div class="spinexim-import-results <?php echo !empty($results['errors']) ? 'spinexim-error' : 'spinexim-success'; ?>">
                            <h3>
                                <?php if (!empty($results['stopped'])): ?>
                                    <?php echo esc_html__('Import Stopped!','spinda-exportimport-data'); ?>
                                <?php else: ?>
                                    <?php echo esc_html__('Import Complete!','spinda-exportimport-data'); ?>
                                <?php endif; ?>
                            </h3>
                            <ul>
                                <li><strong><?php echo esc_html__('Products Processed:','spinda-exportimport-data'); ?></strong> <?php echo esc_html($results['products_processed'] ?? 0); ?></li>
                                <li><strong><?php echo esc_html__('New Products Created:','spinda-exportimport-data'); ?></strong> <?php echo esc_html($results['products_created'] ?? 0); ?></li>
                                <li><strong><?php echo esc_html__('Products Updated:','spinda-exportimport-data'); ?></strong> <?php echo esc_html($results['products_updated'] ?? 0); ?></li>
                                <li><strong><?php echo esc_html__('Variations Imported:','spinda-exportimport-data'); ?></strong> <?php echo esc_html($results['variations_imported'] ?? 0); ?></li>
                                <li><strong><?php echo esc_html__('Categories Created:','spinda-exportimport-data'); ?></strong> <?php echo esc_html($results['categories_created'] ?? 0); ?></li>
                                <li><strong><?php echo esc_html__('Tags Created:','spinda-exportimport-data'); ?></strong> <?php echo esc_html($results['tags_created'] ?? 0); ?></li>
                                <li><strong><?php echo esc_html__('Brands Created:','spinda-exportimport-data'); ?></strong> <?php echo esc_html($results['brands_created'] ?? 0); ?></li>
                                <li><strong><?php echo esc_html__('Attributes Created:','spinda-exportimport-data'); ?></strong> <?php echo esc_html($results['attributes_created'] ?? 0); ?></li>
                                <li><strong><?php echo esc_html__('Reviews Imported:','spinda-exportimport-data'); ?></strong> <?php echo esc_html($results['reviews_imported'] ?? 0); ?></li>
                                <li><strong><?php echo esc_html__('Images Imported:','spinda-exportimport-data'); ?></strong> <?php echo esc_html($results['images_imported'] ?? 0); ?></li>
                                <li><strong><?php echo esc_html__('Meta Fields Processed:','spinda-exportimport-data'); ?></strong> <?php echo esc_html($results['meta_fields_processed'] ?? 0); ?></li>
                                <li><strong><?php echo esc_html__('ACF Gallery Images Imported:','spinda-exportimport-data'); ?></strong> <?php echo esc_html($results['acf_gallery_imported'] ?? 0); ?></li>
                                <?php if (!empty($results['errors'])): ?>
                                    <li><strong><?php echo esc_html__('Errors:','spinda-exportimport-data'); ?></strong> <?php echo count($results['errors']); ?></li>
                                <?php endif; ?>
                                <?php if (!empty($results['stopped'])): ?>
                                    <li><strong style="color: #d63638;"><?php echo esc_html__('Note:','spinda-exportimport-data'); ?></strong> <?php echo esc_html__('Import was stopped by user. Some items may not have been imported.','spinda-exportimport-data'); ?></li>
                                <?php endif; ?>
                            </ul>
                            <?php if (!empty($results['errors'])): ?>
                                <details>
                                    <summary><strong><?php echo esc_html__('Error Details','spinda-exportimport-data'); ?></strong></summary>
                                    <pre><?php echo esc_html(implode("\n", array_slice($results['errors'], 0, 50))); ?></pre>
                                </details>
                            <?php endif; ?>
                        </div>
                        <?php
                        delete_transient('spinexim_import_results');
                        endif;
                    endif;
                    ?>
                    
                    <form method="post" enctype="multipart/form-data" id="spinexim-import-form">
                        <?php wp_nonce_field('spinexim_import_nonce', 'spinexim_import_nonce_field'); ?>
                        <input type="hidden" name="spinexim_import_action" value="1">
                        
                        <div class="spinexim-import-options">
                            <p>
                                <label><strong><?php echo esc_html__('Choose Export File:','spinda-exportimport-data'); ?></strong></label><br>
                                <input type="file" name="spinexim_import_file" accept=".json" required>
                            </p>
                            
                            <p>
                                <label>
                                    <input type="checkbox" name="spinexim_overwrite_existing" value="1" checked>
                                    <strong><?php echo esc_html__('Update existing products','spinda-exportimport-data'); ?></strong> 
                                    <?php echo esc_html__('(match by SKU or slug)','spinda-exportimport-data'); ?>
                                </label>
                            </p>
                            
                            <p>
                                <label>
                                    <input type="checkbox" name="spinexim_import_images" value="1" checked>
                                    <strong><?php echo esc_html__('Download and import images','spinda-exportimport-data'); ?></strong>
                                </label>
                            </p>
                            
                            <p>
                                <label>
                                    <input type="checkbox" name="spinexim_import_reviews" value="1" checked>
                                    <strong><?php echo esc_html__('Import reviews','spinda-exportimport-data'); ?></strong>
                                </label>
                            </p>
                        </div>
                        
                        <p>
                            <button type="submit" class="button button-primary button-hero" id="spinexim-import-button">
                                <span class="dashicons dashicons-upload"></span>
                                <?php echo esc_html__('Start Import','spinda-exportimport-data'); ?>
                            </button>
                        </p>
                    </form>
                </div>
            <?php endif; ?>
            
            <?php if (!$import_running): ?>
            <!-- Reset button -->
            <div class="spinexim-card spinexim-card-danger">
                <h3><?php echo esc_html__('Troubleshooting','spinda-exportimport-data'); ?></h3>
                <p><?php echo esc_html__('If import is stuck, click reset:','spinda-exportimport-data'); ?></p>
                <?php
                $reset_url = wp_nonce_url(
                    admin_url('admin.php?page=spinexim-import&spinexim_reset_import=1'),
                    'spinexim_reset_import'
                );
                ?>
                <a href="<?php echo esc_url($reset_url); ?>" class="button button-secondary">
                    <?php echo esc_html__('Reset Stuck Import','spinda-exportimport-data'); ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Handle import request
     */
    public function handle_import() {
        // Check if import action is set
        if (!isset($_POST['spinexim_import_action'])) {
            return;
        }

        // Verify nonce
        if (!isset($_POST['spinexim_import_nonce_field']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spinexim_import_nonce_field'])), 'spinexim_import_nonce')) {
            wp_die(esc_html__('Security check failed. Please refresh the page and try again.','spinda-exportimport-data'));
        }

        // Check capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.','spinda-exportimport-data'));
        }

        // Validate file upload
        if (!isset($_FILES['spinexim_import_file'])) {
            wp_die(esc_html__('No file was uploaded.','spinda-exportimport-data'));
        }

        $file = $_FILES['spinexim_import_file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_die(
                sprintf(
                    /* translators: %d: Upload error code */
                    esc_html__('File upload error code: %d','spinda-exportimport-data'),
                    intval($file['error'])
                )
            );
        }

        // Validate file type
        $file_info = wp_check_filetype($file['name'], array('json' => 'application/json'));
        if ('json' !== $file_info['ext']) {
            wp_die(esc_html__('Invalid file type. Please upload a JSON file.','spinda-exportimport-data'));
        }

        // Check if import is already running
        if (get_transient('spinexim_import_running')) {
            wp_die(esc_html__('Import is already running. Please wait for it to complete or reset it.','spinda-exportimport-data'));
        }

        // Read and parse JSON
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }
        
        $file_content = $wp_filesystem->get_contents($file['tmp_name']);
        if (false === $file_content) {
            wp_die(esc_html__('Failed to read uploaded file.','spinda-exportimport-data'));
        }

        $import_data = json_decode($file_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_die(
                sprintf(
                    /* translators: %s: JSON error message */
                    esc_html__('Invalid JSON file: %s','spinda-exportimport-data'),
                    json_last_error_msg()
                )
            );
        }

        // Validate import data structure
        if (!isset($import_data['products']) || !is_array($import_data['products'])) {
            wp_die(esc_html__('Invalid export file format. Missing products data.','spinda-exportimport-data'));
        }

        // Clear any old cron jobs and paused flag
        wp_clear_scheduled_hook('spinexim_import_process_next');
        wp_clear_scheduled_hook('spinexim_import_process_batch');
        delete_transient('spinexim_import_paused');

        // Set transients for processing
        set_transient('spinexim_import_running', true, 7200);
        set_transient('spinexim_import_progress', 0, 7200);
        set_transient('spinexim_import_message', __('Starting import...','spinda-exportimport-data'), 7200);

        // Get import options
        $options = array(
            'overwrite' => !empty($_POST['spinexim_overwrite_existing']),
            'import_images' => !empty($_POST['spinexim_import_images']),
            'import_reviews' => !empty($_POST['spinexim_import_reviews']),
        );

        // Store taxonomy and attribute data separately
        if (!empty($import_data['taxonomies'])) {
            set_transient('spinexim_import_taxonomies', $import_data['taxonomies'], 7200);
        }
        if (!empty($import_data['attributes'])) {
            set_transient('spinexim_import_attributes', $import_data['attributes'], 7200);
        }
        
        // Store products individually
        $total_products = count($import_data['products']);
        if ($total_products > 0) {
            foreach ($import_data['products'] as $index => $product) {
                set_transient('spinexim_import_product_' . $index, $product, 7200);
            }
        }
        
        // Store import state
        set_transient('spinexim_import_total_products', $total_products, 7200);
        set_transient('spinexim_import_options', $options, 7200);
        set_transient('spinexim_import_results', array(), 7200);
        set_transient('spinexim_import_current_index', 0, 7200);
        set_transient('spinexim_import_finished', false, 7200);
        
        // Clear memory
        unset($import_data, $file_content);
        
        // Schedule first batch
        wp_schedule_single_event(time() + 3, 'spinexim_import_process_next');
        
        // Redirect to import page
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page' => 'spinexim-import',
                    'import_started' => 1,
                ),
                admin_url('admin.php')
            )
        );
        exit;
    }

    /**
     * Handle stop/resume/cancel import
     */
    public function handle_stop_import() {
        // Handle resume import
        if (isset($_GET['spinexim_resume_import']) && current_user_can('manage_woocommerce')) {
            $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
            if (!wp_verify_nonce($nonce, 'spinexim_resume_import')) {
                wp_die(esc_html__('Security check failed.','spinda-exportimport-data'));
            }
            
            // Clear paused flag
            delete_transient('spinexim_import_paused');
            
            // Update message
            set_transient('spinexim_import_message', __('Resuming import...','spinda-exportimport-data'), 7200);
            
            // Resume processing
            wp_schedule_single_event(time() + 3, 'spinexim_import_process_next');
            
            wp_safe_redirect(
                add_query_arg(
                    array('page' => 'spinexim-import', 'import_resumed' => 1),
                    admin_url('admin.php')
                )
            );
            exit;
        }
        
        // Handle cancel import
        if (isset($_GET['spinexim_cancel_import']) && current_user_can('manage_woocommerce')) {
            $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
            if (!wp_verify_nonce($nonce, 'spinexim_cancel_import')) {
                wp_die(esc_html__('Security check failed.','spinda-exportimport-data'));
            }
            
            // Get current results
            $results = get_transient('spinexim_import_results') ?: array();
            $results['stopped'] = true;
            
            // Store results for display
            set_transient('spinexim_import_results', $results, 300);
            
            // Clean up import data
            $this->cleanup_import_data();
            
            // Schedule rating updates for processed products
            if (!empty($results['products_processed'])) {
                wp_schedule_single_event(time() + 10, 'spinexim_update_ratings');
                set_transient('spinexim_ratings_to_update', true, 3600);
            }
            
            wp_safe_redirect(
                add_query_arg(
                    array(
                        'page' => 'spinexim-import',
                        'import_results' => 1,
                        'import_nonce' => wp_create_nonce('spinexim_import_results'),
                    ),
                    admin_url('admin.php')
                )
            );
            exit;
        }
    }

    /**
     * AJAX handler to stop import
     */
    public function ajax_stop_import() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'spinexim_admin_nonce')) {
            wp_send_json_error(array('message' => esc_html__('Security check failed.','spinda-exportimport-data')));
        }
        
        // Check capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => esc_html__('Unauthorized.','spinda-exportimport-data')));
        }
        
        // Set paused flag
        set_transient('spinexim_import_paused', true, 7200);
        
        // Clear scheduled hooks
        wp_clear_scheduled_hook('spinexim_import_process_next');
        wp_clear_scheduled_hook('spinexim_import_process_batch');
        
        // Update message
        set_transient('spinexim_import_message', esc_html__('Import paused by user. Click Resume to continue.','spinda-exportimport-data'), 7200);
        
        wp_send_json_success(array('message' => esc_html__('Import stopped successfully.','spinda-exportimport-data')));
    }

    /**
     * Cleanup import data
     */
    private function cleanup_import_data() {
        delete_transient('spinexim_import_running');
        delete_transient('spinexim_import_paused');
        delete_transient('spinexim_import_progress');
        delete_transient('spinexim_import_message');
        delete_transient('spinexim_import_current_index');
        delete_transient('spinexim_import_total_products');
        delete_transient('spinexim_import_options');
        delete_transient('spinexim_import_taxonomies');
        delete_transient('spinexim_import_attributes');
        delete_transient('spinexim_import_finished');
        
        // Clear product transients
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '%_transient_spinexim_import_product_%'
            )
        );
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '%_transient_timeout_spinexim_import_product_%'
            )
        );
        
        // Clear scheduled hooks
        wp_clear_scheduled_hook('spinexim_import_process_next');
        wp_clear_scheduled_hook('spinexim_import_process_batch');
    }

    /**
     * Process next import batch
     */
    public function process_next_batch() {
        // Check if paused - if so, stop and don't schedule next
        if (get_transient('spinexim_import_paused')) {
            wp_clear_scheduled_hook('spinexim_import_process_next');
            wp_clear_scheduled_hook('spinexim_import_process_batch');
            return;
        }
        
        // Check if already finished
        if (get_transient('spinexim_import_finished')) {
            wp_clear_scheduled_hook('spinexim_import_process_next');
            wp_clear_scheduled_hook('spinexim_import_process_batch');
            return;
        }
        
        // Check if import is still running
        if (!get_transient('spinexim_import_running')) {
            wp_clear_scheduled_hook('spinexim_import_process_next');
            wp_clear_scheduled_hook('spinexim_import_process_batch');
            return;
        }
        
        // Memory management
        wp_raise_memory_limit('admin');
        @set_time_limit(120);
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', 120);
        
        $current_index = intval(get_transient('spinexim_import_current_index') ?: 0);
        $total_products = intval(get_transient('spinexim_import_total_products') ?: 0);
        $options = get_transient('spinexim_import_options') ?: array();
        $results = get_transient('spinexim_import_results') ?: array();
        
        // Check if complete
        if ($current_index >= $total_products || 0 === $total_products) {
            $this->import_complete($results);
            return;
        }
        
        // Process taxonomies first (only once)
        static $taxonomies_processed = false;
        static $attributes_processed = false;
        
        if (!$taxonomies_processed) {
            $taxonomies = get_transient('spinexim_import_taxonomies');
            if (!empty($taxonomies)) {
                foreach ($taxonomies as $taxonomy_name => $taxonomy_data) {
                    $this->import_taxonomy($taxonomy_name, $taxonomy_data, $options, $results);
                }
            }
            $taxonomies_processed = true;
            delete_transient('spinexim_import_taxonomies');
        }
        
        if (!$attributes_processed) {
            $attributes = get_transient('spinexim_import_attributes');
            if (!empty($attributes)) {
                foreach ($attributes as $attr_data) {
                    $this->import_attribute($attr_data, $options, $results);
                }
            }
            $attributes_processed = true;
            delete_transient('spinexim_import_attributes');
        }
        
        // Process single product
        $product_data = get_transient('spinexim_import_product_' . $current_index);
        if ($product_data) {
            $this->import_product($product_data, $options, $results);
            delete_transient('spinexim_import_product_' . $current_index);
        }
        
        $current_index++;
        $progress = ($total_products > 0) ? min(round(($current_index / $total_products) * 100), 99) : 100;
        
        set_transient('spinexim_import_current_index', $current_index, 7200);
        set_transient('spinexim_import_results', $results, 7200);
        set_transient('spinexim_import_progress', $progress, 7200);
        set_transient(
            'spinexim_import_message',
            sprintf(
                /* translators: 1: Current index, 2: Total products */
                __('Processing product %1$d of %2$d...','spinda-exportimport-data'),
                $current_index,
                $total_products
            ),
            7200
        );
        
        // Clear cache periodically
        if (0 === $current_index % 5) {
            wp_cache_flush();
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
        
        // Check again for pause before scheduling next batch
        if (get_transient('spinexim_import_paused')) {
            wp_clear_scheduled_hook('spinexim_import_process_next');
            return;
        }
        
        // Schedule next batch or complete
        if ($current_index < $total_products) {
            wp_schedule_single_event(time() + 3, 'spinexim_import_process_next');
        } else {
            $this->import_complete($results);
        }
    }

    /**
     * Complete the import process
     *
     * @param array $results Import results.
     */
    private function import_complete($results) {
        // Mark as finished
        set_transient('spinexim_import_finished', true, 300);
        
        // Clean up import state
        delete_transient('spinexim_import_running');
        delete_transient('spinexim_import_paused');
        delete_transient('spinexim_import_progress');
        delete_transient('spinexim_import_message');
        delete_transient('spinexim_import_current_index');
        delete_transient('spinexim_import_total_products');
        delete_transient('spinexim_import_options');
        
        // Clear all pending cron jobs
        wp_clear_scheduled_hook('spinexim_import_process_next');
        wp_clear_scheduled_hook('spinexim_import_process_batch');
        
        // Store results for display
        set_transient('spinexim_import_results', $results, 300);
        set_transient('spinexim_import_message', __('Import completed successfully!','spinda-exportimport-data'), 300);
        
        // Schedule rating updates
        if (!empty($results['products_processed'])) {
            wp_schedule_single_event(time() + 10, 'spinexim_update_ratings');
            set_transient('spinexim_ratings_to_update', true, 3600);
        }
    }

    /**
     * Update product ratings in background
     */
    public function update_product_ratings() {
        if (!get_transient('spinexim_ratings_to_update')) {
            return;
        }
        
        $products = get_posts(array(
            'post_type' => 'product',
            'posts_per_page' => 100,
            'fields' => 'ids',
        ));
        
        foreach ($products as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $product->sync_average_rating();
                $product->sync_rating_count();
            }
        }
        
        $total_products = wp_count_posts('product')->publish;
        if ($total_products > 100) {
            wp_schedule_single_event(time() + 30, 'spinexim_update_ratings');
        } else {
            delete_transient('spinexim_ratings_to_update');
        }
    }

    /**
     * Import taxonomy
     *
     * @param string $taxonomy_name Taxonomy name.
     * @param array  $taxonomy_data Taxonomy data.
     * @param array  $options       Import options.
     * @param array  $results       Results array (passed by reference).
     */
    private function import_taxonomy($taxonomy_name, $taxonomy_data, $options, &$results) {
    if (!taxonomy_exists($taxonomy_name)) {
        register_taxonomy($taxonomy_name, 'product', array(
            'label' => $taxonomy_data['label'] ?? $taxonomy_name,
            'hierarchical' => $taxonomy_data['hierarchical'] ?? true,
            'public' => true,
            'show_ui' => true,
        ));
        flush_rewrite_rules();
    }
    
    if (!empty($taxonomy_data['terms'])) {
        foreach ($taxonomy_data['terms'] as $term_data) {
            $term = get_term_by('slug', sanitize_title($term_data['slug']), $taxonomy_name);
            $parent_id = 0;
            
            if (!empty($term_data['parent_slug'])) {
                $parent = get_term_by('slug', sanitize_title($term_data['parent_slug']), $taxonomy_name);
                if ($parent && !is_wp_error($parent)) {
                    $parent_id = $parent->term_id;
                }
            }
            
            if (!$term) {
                $result = wp_insert_term(
                    $term_data['name'],
                    $taxonomy_name,
                    array(
                        'slug' => sanitize_title($term_data['slug']),
                        'description' => wp_kses_post($term_data['description'] ?? ''),
                        'parent' => $parent_id,
                    )
                );
                
                if (!is_wp_error($result)) {
                    $term_id = $result['term_id'];
                    
                    // Set term order
                    if (isset($term_data['term_order']) && $term_data['term_order'] > 0) {
                        update_term_meta($term_id, 'order', absint($term_data['term_order']));
                    }
                    
                    // Import thumbnail
                    if (!empty($term_data['thumbnail']) && $options['import_images']) {
                        $image_id = $this->import_image($term_data['thumbnail'], 0);
                        if ($image_id) {
                            update_term_meta($term_id, 'thumbnail_id', $image_id);
                            $results['images_imported'] = ($results['images_imported'] ?? 0) + 1;
                        }
                    }
                    
                    // ===== NEW: Import term meta data =====
                    if (!empty($term_data['meta_data']) && is_array($term_data['meta_data'])) {
                        foreach ($term_data['meta_data'] as $meta_key => $meta_value) {
                            $this->import_meta_value($term_id, $meta_key, $meta_value, 'term', $options, $results);
                        }
                    }
                    
                    // Update counts
                    if ('product_cat' === $taxonomy_name) {
                        $results['categories_created'] = ($results['categories_created'] ?? 0) + 1;
                    } elseif ('product_tag' === $taxonomy_name) {
                        $results['tags_created'] = ($results['tags_created'] ?? 0) + 1;
                    } elseif (in_array($taxonomy_name, array('product_brand', 'brand', 'pwb-brand', 'brands'), true)) {
                        $results['brands_created'] = ($results['brands_created'] ?? 0) + 1;
                    }
                }
            } else {
                // ===== NEW: Update existing term meta if overwrite is enabled =====
                if ($options['overwrite'] && !empty($term_data['meta_data']) && is_array($term_data['meta_data'])) {
                    $term_id = $term->term_id;
                    foreach ($term_data['meta_data'] as $meta_key => $meta_value) {
                        $this->import_meta_value($term_id, $meta_key, $meta_value, 'term', $options, $results);
                    }
                }
            }
        }
    }
}

    /**
     * Import attribute
     *
     * @param array $attr_data Attribute data.
     * @param array $options   Import options.
     * @param array $results   Results array (passed by reference).
     */
    private function import_attribute($attr_data, $options, &$results) {
        global $wpdb;
        
        $attr_name = sanitize_title($attr_data['name']);
        
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s",
                $attr_name
            )
        );
        
        if (!$existing) {
            $wpdb->insert(
                $wpdb->prefix . 'woocommerce_attribute_taxonomies',
                array(
                    'attribute_name' => $attr_name,
                    'attribute_label' => $attr_data['label'] ?? $attr_data['name'],
                    'attribute_type' => $attr_data['type'] ?? 'select',
                    'attribute_orderby' => $attr_data['orderby'] ?? 'menu_order',
                    'attribute_public' => absint($attr_data['has_archives'] ?? 0),
                )
            );
            $results['attributes_created'] = ($results['attributes_created'] ?? 0) + 1;
            delete_transient('wc_attribute_taxonomies');
            flush_rewrite_rules();
        }
        
        $taxonomy = wc_attribute_taxonomy_name($attr_name);
        if (!taxonomy_exists($taxonomy)) {
            register_taxonomy($taxonomy, 'product');
            flush_rewrite_rules();
        }
        
        if (!empty($attr_data['terms'])) {
            foreach ($attr_data['terms'] as $term_data) {
                $term = get_term_by('slug', sanitize_title($term_data['slug']), $taxonomy);
                if (!$term) {
                    wp_insert_term(
                        $term_data['name'],
                        $taxonomy,
                        array(
                            'slug' => sanitize_title($term_data['slug']),
                            'description' => wp_kses_post($term_data['description'] ?? ''),
                        )
                    );
                }
            }
        }
    }

    /**
     * Import single product - FIXED VERSION
     *
     * @param array $product_data Product data.
     * @param array $options      Import options.
     * @param array $results      Results array (passed by reference).
     */
    private function import_product($product_data, $options, &$results) {
        try {
            $existing_id = null;
            
            // Find existing product
            if (!empty($product_data['sku'])) {
                $existing_id = wc_get_product_id_by_sku($product_data['sku']);
            }
            
            if (!$existing_id && !empty($product_data['slug'])) {
                $existing_post = get_page_by_path(sanitize_title($product_data['slug']), OBJECT, 'product');
                if ($existing_post) {
                    $existing_id = $existing_post->ID;
                }
            }
            
            // Skip if exists and not overwriting
            if ($existing_id && !$options['overwrite']) {
                return;
            }
            
            // Prepare post data
            $post_data = array(
                'post_type' => 'product',
                'post_title' => sanitize_text_field($product_data['name']),
                'post_name' => sanitize_title($product_data['slug']),
                'post_content' => wp_kses_post($product_data['description'] ?? ''),
                'post_excerpt' => wp_kses_post($product_data['short_description'] ?? ''),
                'post_status' => sanitize_key($product_data['status'] ?? 'publish'),
                'post_date' => sanitize_text_field($product_data['date_created'] ?? current_time('mysql')),
                'menu_order' => absint($product_data['menu_order'] ?? 0),
                'post_author' => absint($product_data['author_id'] ?? 1),
            );
            
            if ($existing_id) {
                $post_data['ID'] = $existing_id;
                $product_id = wp_update_post($post_data, true);
                $results['products_updated'] = ($results['products_updated'] ?? 0) + 1;
            } else {
                $product_id = wp_insert_post($post_data, true);
                $results['products_created'] = ($results['products_created'] ?? 0) + 1;
            }
            
            if (is_wp_error($product_id)) {
                throw new Exception(esc_html($product_id->get_error_message()));
            }
            
            $results['products_processed'] = ($results['products_processed'] ?? 0) + 1;
            
            // Set product type
            $product_type = sanitize_key($product_data['type'] ?? 'simple');
            wp_set_object_terms($product_id, $product_type, 'product_type');
            
            // Import product attributes (CRITICAL)
            if (!empty($product_data['product_attributes']) && is_array($product_data['product_attributes'])) {
                update_post_meta($product_id, '_product_attributes', $product_data['product_attributes']);
            }
            
            // Import meta, taxonomies, attributes
            $this->import_product_meta($product_id, $product_data, $options, $results);
            $this->import_product_taxonomies($product_id, $product_data, $options);
            
            // Import taxonomies
            if (!empty($product_data['taxonomies'])) {
                $this->import_product_taxonomy_assignments($product_id, $product_data['taxonomies']);
            }
            
            // Import images
            if (!empty($product_data['featured_image']) && $options['import_images']) {
                $image_id = $this->import_image($product_data['featured_image']['url'], $product_id);
                if ($image_id) {
                    set_post_thumbnail($product_id, $image_id);
                    $results['images_imported'] = ($results['images_imported'] ?? 0) + 1;
                }
            }
            
            if (!empty($product_data['gallery_images']) && $options['import_images']) {
                $gallery_ids = array();
                foreach ($product_data['gallery_images'] as $gallery) {
                    $image_id = $this->import_image($gallery['url'], $product_id);
                    if ($image_id) {
                        $gallery_ids[] = $image_id;
                        $results['images_imported'] = ($results['images_imported'] ?? 0) + 1;
                    }
                }
                if (!empty($gallery_ids)) {
                    update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
                }
            }
            
            // Import variations for variable products - FIXED
            if ('variable' === $product_type && !empty($product_data['variations'])) {
                // Set variation attributes
                if (!empty($product_data['variation_attributes'])) {
                    update_post_meta($product_id, '_variation_attributes', $product_data['variation_attributes']);
                }
                if (!empty($product_data['default_attributes'])) {
                    update_post_meta($product_id, '_default_attributes', $product_data['default_attributes']);
                }
                
                // Import each variation with attributes
                foreach ($product_data['variations'] as $variation_data) {
                    $this->import_variation_complete($product_id, $variation_data, $options, $results);
                }
            }
            
            // Import reviews
            if ($options['import_reviews'] && !empty($product_data['reviews']) && is_array($product_data['reviews'])) {
                foreach ($product_data['reviews'] as $review_data) {
                    try {
                        $this->import_single_review($product_id, $review_data, $options, $results);
                    } catch (Exception $e) {
                        // Skip failed reviews
                    }
                }
            }
            
            wc_delete_product_transients($product_id);
            clean_post_cache($product_id);
            
            unset($product_data);
            
        } catch (Exception $e) {
            $results['errors'][] = sprintf(
                /* translators: 1: Product name, 2: Error message */
                __("Product '%1\$s': %2\$s",'spinda-exportimport-data'),
                $product_data['name'] ?? 'Unknown',
                $e->getMessage()
            );
        }
    }

    /**
     * Import product meta
     *
     * @param int   $product_id   Product ID.
     * @param array $product_data Product data.
     * @param array $options      Import options.
     * @param array $results      Results array (passed by reference).
     */
    private function import_product_meta($product_id, $product_data, $options, &$results) {
        $meta_mapping = array(
            '_sku' => 'sku',
            '_regular_price' => 'regular_price',
            '_sale_price' => 'sale_price',
            '_manage_stock' => 'manage_stock',
            '_stock' => 'stock_quantity',
            '_stock_status' => 'stock_status',
            '_backorders' => 'backorders',
            '_sold_individually' => 'sold_individually',
            '_weight' => 'weight',
            '_length' => 'length',
            '_width' => 'width',
            '_height' => 'height',
            '_tax_status' => 'tax_status',
            '_tax_class' => 'tax_class',
            '_featured' => 'featured',
            '_visibility' => 'catalog_visibility',
            '_downloadable' => 'downloadable',
            '_virtual' => 'virtual',
            '_download_limit' => 'download_limit',
            '_download_expiry' => 'download_expiry',
            '_purchase_note' => 'purchase_note',
            '_low_stock_amount' => 'low_stock_amount',
        );
        
        foreach ($meta_mapping as $meta_key => $data_key) {
            if (isset($product_data[$data_key]) && '' !== $product_data[$data_key]) {
                $value = $product_data[$data_key];
                if (is_bool($value)) {
                    $value = $value ? 'yes' : 'no';
                }
                update_post_meta($product_id, $meta_key, $value);
            }
        }
        
        // Sale price dates
        if (!empty($product_data['sale_price_from'])) {
            update_post_meta($product_id, '_sale_price_dates_from', strtotime($product_data['sale_price_from']));
        }
        if (!empty($product_data['sale_price_to'])) {
            update_post_meta($product_id, '_sale_price_dates_to', strtotime($product_data['sale_price_to']));
        }
        
        // Shipping class
        if (!empty($product_data['shipping_class'])) {
            $shipping_term = get_term_by('slug', sanitize_title($product_data['shipping_class']), 'product_shipping_class');
            if ($shipping_term) {
                wp_set_object_terms($product_id, $shipping_term->term_id, 'product_shipping_class');
            }
        }
        
        // Reviews allowed
        if (isset($product_data['reviews_allowed'])) {
            update_post_meta($product_id, 'comment_status', $product_data['reviews_allowed'] ? 'open' : 'closed');
        }
        
        // Custom meta data including ACF
        if (!empty($product_data['meta_data'])) {
            foreach ($product_data['meta_data'] as $meta_key => $meta_value) {
                $this->import_meta_value($product_id, $meta_key, $meta_value, 'post', $options, $results);
            }
        }
    }

    /**
     * Import meta value
     *
     * @param int    $object_id  Object ID.
     * @param string $meta_key   Meta key.
     * @param mixed  $meta_value Meta value.
     * @param string $type       Object type (post or term).
     * @param array  $options    Import options.
     * @param array  $results    Results array (passed by reference).
     */
    private function import_meta_value($object_id, $meta_key, $meta_value, $type = 'post', $options, &$results) {
        $sanitized_key = sanitize_text_field($meta_key);
        $is_term = ('term' === $type);
        
        // Skip WooCommerce internal meta for products
        if (!$is_term) {
            $woo_skip = array(
                '_regular_price', '_sale_price', '_sku', '_stock', '_stock_status',
                '_weight', '_length', '_width', '_height', '_tax_status', '_tax_class',
                '_variation_attributes', '_product_attributes', '_default_attributes',
                '_product_image_gallery'
            );
            if (in_array($sanitized_key, $woo_skip)) {
                return;
            }
        }
        
        if (is_array($meta_value) && isset($meta_value['type'])) {
            switch ($meta_value['type']) {
                case 'acf_gallery':
                    if (!empty($meta_value['value'])) {
                        $gallery_ids = array();
                        foreach ($meta_value['value'] as $image_data) {
                            if (isset($image_data['type']) && 'image' === $image_data['type'] && !empty($image_data['url'])) {
                                $image_id = $this->import_image($image_data['url'], $object_id);
                                if ($image_id) {
                                    $gallery_ids[] = $image_id;
                                    $results['acf_gallery_imported'] = ($results['acf_gallery_imported'] ?? 0) + 1;
                                    $results['images_imported'] = ($results['images_imported'] ?? 0) + 1;
                                }
                            }
                        }
                        if (!empty($gallery_ids)) {
                            if ($is_term) {
                                update_term_meta($object_id, $sanitized_key, $gallery_ids);
                            } else {
                                update_post_meta($object_id, $sanitized_key, $gallery_ids);
                            }
                            $results['meta_fields_processed'] = ($results['meta_fields_processed'] ?? 0) + 1;
                        }
                    }
                    return;
                    
                case 'acf_link':
                    if (!empty($meta_value['value'])) {
                        $link = array(
                            'title' => $meta_value['value']['title'] ?? '',
                            'url' => esc_url_raw($meta_value['value']['url'] ?? ''),
                            'target' => $meta_value['value']['target'] ?? '',
                        );
                        
                        if ($is_term) {
                            update_term_meta($object_id, $sanitized_key, $link);
                        } else {
                            update_post_meta($object_id, $sanitized_key, $link);
                        }
                        $results['meta_fields_processed'] = ($results['meta_fields_processed'] ?? 0) + 1;
                    }
                    return;
                    
                case 'array':
                    if (!empty($meta_value['value'])) {
                        $array_data = $meta_value['value'];
                        
                        if ($is_term) {
                            update_term_meta($object_id, $sanitized_key, $array_data);
                        } else {
                            update_post_meta($object_id, $sanitized_key, $array_data);
                        }
                        $results['meta_fields_processed'] = ($results['meta_fields_processed'] ?? 0) + 1;
                    }
                    return;
                    
                case 'image':
                    if ($options['import_images'] && !empty($meta_value['url'])) {
                        $image_id = $this->import_image($meta_value['url'], $object_id);
                        if ($image_id) {
                            if ($is_term) {
                                update_term_meta($object_id, $sanitized_key, $image_id);
                            } else {
                                update_post_meta($object_id, $sanitized_key, $image_id);
                            }
                            $results['images_imported'] = ($results['images_imported'] ?? 0) + 1;
                            $results['meta_fields_processed'] = ($results['meta_fields_processed'] ?? 0) + 1;
                        }
                    }
                    return;
                    
                case 'file':
                    if ($options['import_images'] && !empty($meta_value['url'])) {
                        $file_id = $this->import_file($meta_value['url'], $object_id);
                        if ($file_id) {
                            if ($is_term) {
                                update_term_meta($object_id, $sanitized_key, $file_id);
                            } else {
                                update_post_meta($object_id, $sanitized_key, $file_id);
                            }
                            $results['meta_fields_processed'] = ($results['meta_fields_processed'] ?? 0) + 1;
                        }
                    }
                    return;
                    
                case 'serialized':
                    $serialized = $meta_value['value'];
                    $modified = false;
                    $this->process_serialized_for_import($serialized, $object_id, $options, $results, $modified);
                    if ($modified) {
                        if ($is_term) {
                            update_term_meta($object_id, $sanitized_key, $serialized);
                        } else {
                            update_post_meta($object_id, $sanitized_key, $serialized);
                        }
                        $results['meta_fields_processed'] = ($results['meta_fields_processed'] ?? 0) + 1;
                    }
                    return;
            }
        }
        
        // Handle plain values
        if (!empty($meta_value) || '0' === $meta_value || 0 === $meta_value) {
            $value = is_array($meta_value) ? $meta_value : $meta_value;
            if ($is_term) {
                update_term_meta($object_id, $sanitized_key, $value);
            } else {
                update_post_meta($object_id, $sanitized_key, $value);
            }
            $results['meta_fields_processed'] = ($results['meta_fields_processed'] ?? 0) + 1;
        }
    }


    /**
     * Process serialized data for import
     *
     * @param array $data      Data to process (passed by reference).
     * @param int   $object_id Object ID.
     * @param array $options   Import options.
     * @param array $results   Results array (passed by reference).
     * @param bool  $modified  Modified flag (passed by reference).
     */
    private function process_serialized_for_import(&$data, $object_id, $options, &$results, &$modified) {
        if (is_array($data)) {
            foreach ($data as $key => &$value) {
                if (is_array($value)) {
                    if (isset($value['type'])) {
                        if ('image' === $value['type'] && $options['import_images'] && !empty($value['url'])) {
                            $image_id = $this->import_image($value['url'], $object_id);
                            if ($image_id) {
                                $value = $image_id;
                                $modified = true;
                                $results['images_imported'] = ($results['images_imported'] ?? 0) + 1;
                                $results['meta_fields_processed'] = ($results['meta_fields_processed'] ?? 0) + 1;
                            }
                        } elseif ('file' === $value['type'] && $options['import_images'] && !empty($value['url'])) {
                            $file_id = $this->import_file($value['url'], $object_id);
                            if ($file_id) {
                                $value = $file_id;
                                $modified = true;
                                $results['meta_fields_processed'] = ($results['meta_fields_processed'] ?? 0) + 1;
                            }
                        }
                    } else {
                        $this->process_serialized_for_import($value, $object_id, $options, $results, $modified);
                    }
                }
            }
        }
    }

    /**
     * Import product taxonomies
     *
     * @param int   $product_id   Product ID.
     * @param array $product_data Product data.
     * @param array $options      Import options.
     */
    private function import_product_taxonomies($product_id, $product_data, $options) {
        if (!empty($product_data['taxonomies'])) {
            foreach ($product_data['taxonomies'] as $tax_name => $terms) {
                if (!taxonomy_exists($tax_name)) {
                    continue;
                }
                
                $term_ids = array();
                foreach ($terms as $term) {
                    $term_obj = get_term_by('slug', sanitize_title($term['slug']), $tax_name);
                    if ($term_obj) {
                        $term_ids[] = $term_obj->term_id;
                    }
                }
                if (!empty($term_ids)) {
                    wp_set_object_terms($product_id, $term_ids, $tax_name);
                }
            }
        }
    }

    /**
     * Import product taxonomy assignments
     *
     * @param int   $product_id Product ID.
     * @param array $taxonomies Taxonomies data.
     */
    private function import_product_taxonomy_assignments($product_id, $taxonomies) {
        foreach ($taxonomies as $tax_name => $terms) {
            if (!taxonomy_exists($tax_name)) {
                continue;
            }
            
            $term_ids = array();
            foreach ($terms as $term) {
                $term_obj = get_term_by('slug', sanitize_title($term['slug']), $tax_name);
                if ($term_obj) {
                    $term_ids[] = $term_obj->term_id;
                }
            }
            if (!empty($term_ids)) {
                wp_set_object_terms($product_id, $term_ids, $tax_name);
            }
        }
    }

    /**
     * Import variation with complete attributes - FIXED VERSION
     *
     * @param int   $parent_id      Parent product ID.
     * @param array $variation_data Variation data.
     * @param array $options        Import options.
     * @param array $results        Results array (passed by reference).
     */
    private function import_variation_complete($parent_id, $variation_data, $options, &$results) {
        try {
            $variation_id = null;
            if (!empty($variation_data['sku'])) {
                $variation_id = wc_get_product_id_by_sku(sanitize_text_field($variation_data['sku']));
            }
            
            $post_data = array(
                'post_type' => 'product_variation',
                'post_parent' => $parent_id,
                'post_title' => sprintf(
                    /* translators: %d: Parent product ID */
                    __('Variation #%d','spinda-exportimport-data'),
                    $parent_id
                ),
                'post_status' => sanitize_key($variation_data['status'] ?? 'publish'),
                'post_date' => sanitize_text_field($variation_data['date_created'] ?? current_time('mysql')),
                'menu_order' => absint($variation_data['menu_order'] ?? 0),
            );
            
            if ($variation_id) {
                $post_data['ID'] = $variation_id;
                wp_update_post($post_data);
            } else {
                $variation_id = wp_insert_post($post_data);
                if (is_wp_error($variation_id)) {
                    throw new Exception(__('Failed to create variation','spinda-exportimport-data'));
                }
            }
            
            $results['variations_imported'] = ($results['variations_imported'] ?? 0) + 1;
            
            // CRITICAL: Set variation attributes with their values
            if (!empty($variation_data['attributes']) && is_array($variation_data['attributes'])) {
                $attributes = array();
                foreach ($variation_data['attributes'] as $attr_name => $attr_value) {
                    $clean_attr_name = $attr_name;
                    $clean_attr_value = $attr_value;
                    $attributes[$clean_attr_name] = $clean_attr_value;
                }
                update_post_meta($variation_id, '_variation_attributes', $attributes);
            }
            
            // Set meta
            $meta_mapping = array(
                '_sku' => 'sku',
                '_regular_price' => 'regular_price',
                '_sale_price' => 'sale_price',
                '_manage_stock' => 'manage_stock',
                '_stock' => 'stock_quantity',
                '_stock_status' => 'stock_status',
                '_backorders' => 'backorders',
                '_weight' => 'weight',
                '_length' => 'length',
                '_width' => 'width',
                '_height' => 'height',
                '_tax_class' => 'tax_class',
                '_downloadable' => 'downloadable',
                '_virtual' => 'virtual',
                '_download_limit' => 'download_limit',
                '_download_expiry' => 'download_expiry',
            );
            
            foreach ($meta_mapping as $meta_key => $data_key) {
                if (isset($variation_data[$data_key]) && '' !== $variation_data[$data_key]) {
                    $value = $variation_data[$data_key];
                    if (is_bool($value)) {
                        $value = $value ? 'yes' : 'no';
                    }
                    update_post_meta($variation_id, $meta_key, $value);
                }
            }
            
            // Variation description
            if (!empty($variation_data['description'])) {
                wp_update_post(array(
                    'ID' => $variation_id,
                    'post_content' => wp_kses_post($variation_data['description']),
                ));
            }
            
            // Variation image
            if (!empty($variation_data['image']) && $options['import_images']) {
                $image_id = $this->import_image($variation_data['image']['url'], $parent_id);
                if ($image_id) {
                    set_post_thumbnail($variation_id, $image_id);
                    $results['images_imported'] = ($results['images_imported'] ?? 0) + 1;
                }
            }
            
            // Variation meta - import all meta including _variation_attributes if not already set
            if (!empty($variation_data['meta_data'])) {
                foreach ($variation_data['meta_data'] as $meta_key => $meta_value) {
                    // Skip _variation_attributes if we already set it above
                    if ($meta_key === '_variation_attributes') {
                        continue;
                    }
                    $this->import_meta_value($variation_id, $meta_key, $meta_value, 'post', $options, $results);
                }
            }
            
            $parent = wc_get_product($parent_id);
            if ($parent && $parent->is_type('variable')) {
                wc_delete_product_transients($parent_id);
            }
            
        } catch (Exception $e) {
            $results['errors'][] = sprintf(
                /* translators: 1: Variation SKU, 2: Error message */
                __("Variation '%1\$s': %2\$s",'spinda-exportimport-data'),
                $variation_data['sku'] ?? 'N/A',
                $e->getMessage()
            );
        }
    }

    /**
     * Import single review
     *
     * @param int   $product_id  Product ID.
     * @param array $review_data Review data.
     * @param array $options     Import options.
     * @param array $results     Results array (passed by reference).
     */
    private function import_single_review($product_id, $review_data, $options, &$results) {
        // Check if review already exists
        $existing = get_comments(array(
            'post_id' => $product_id,
            'author_email' => sanitize_email($review_data['author_email']),
            'content' => wp_kses_post($review_data['content']),
            'number' => 1,
        ));
        
        if (!empty($existing)) {
            $results['reviews_imported'] = ($results['reviews_imported'] ?? 0) + 1;
            return;
        }
        
        $user_id = 0;
        
        // Find or create user
        if (!empty($review_data['user_id'])) {
            $user = get_user_by('ID', absint($review_data['user_id']));
            if ($user) {
                $user_id = $user->ID;
            }
        }
        
        if (!$user_id && !empty($review_data['author_email'])) {
            $user = get_user_by('email', sanitize_email($review_data['author_email']));
            if ($user) {
                $user_id = $user->ID;
            } else {
                $username = sanitize_user(str_replace(' ', '_', $review_data['author']));
                $username = substr($username, 0, 60);
                $base = $username;
                $counter = 1;
                while (username_exists($username)) {
                    $username = $base . $counter;
                    $counter++;
                }
                
                $user_id = wp_create_user(
                    $username,
                    wp_generate_password(12, true, true),
                    sanitize_email($review_data['author_email'])
                );
                
                if (!is_wp_error($user_id)) {
                    wp_update_user(array(
                        'ID' => $user_id,
                        'display_name' => sanitize_text_field($review_data['author']),
                    ));
                } else {
                    $user_id = 0;
                }
            }
        }
        
        if (!$user_id) {
            $user_id = 1;
        }
        
        $comment_data = array(
            'comment_post_ID' => $product_id,
            'comment_author' => sanitize_text_field($review_data['author']),
            'comment_author_email' => sanitize_email($review_data['author_email']),
            'comment_author_url' => esc_url_raw($review_data['author_url'] ?? ''),
            'comment_author_IP' => sanitize_text_field($review_data['author_ip'] ?? ''),
            'comment_content' => wp_kses_post($review_data['content']),
            'comment_date' => sanitize_text_field($review_data['date'] ?? current_time('mysql')),
            'comment_date_gmt' => sanitize_text_field($review_data['date_gmt'] ?? current_time('mysql', 1)),
            'comment_approved' => absint($review_data['approved'] ?? 1),
            'comment_type' => 'review',
            'user_id' => $user_id,
        );
        
        if (!empty($review_data['id'])) {
            $comment_data['comment_ID'] = absint($review_data['id']);
        }
        
        $comment_id = wp_insert_comment($comment_data);
        
        if ($comment_id) {
            if (isset($review_data['rating']) && $review_data['rating'] > 0) {
                update_comment_meta($comment_id, 'rating', absint($review_data['rating']));
            }
            
            if (isset($review_data['verified'])) {
                update_comment_meta($comment_id, 'verified', $review_data['verified']);
            }
            
            if (!empty($review_data['meta'])) {
                foreach ($review_data['meta'] as $meta_key => $meta_value) {
                    update_comment_meta($comment_id, $meta_key, $meta_value);
                }
            }
            
            $results['reviews_imported'] = ($results['reviews_imported'] ?? 0) + 1;
        }
    }

    /**
     * Import image from URL
     *
     * @param string $image_url Image URL.
     * @param int    $parent_id Parent post ID.
     * @return int|false Attachment ID or false on failure.
     */
    private function import_image($image_url, $parent_id = 0) {
        if (empty($image_url)) {
            return false;
        }
        
        $sanitized_url = esc_url_raw($image_url);
        
        // Check if already imported
        $existing = $this->get_attachment_by_url($sanitized_url);
        if ($existing) {
            return $existing;
        }
        
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        
        $tmp = false;
        $retries = 3;
        while ($retries > 0 && !$tmp) {
            $tmp = download_url($sanitized_url, 30);
            if (!is_wp_error($tmp)) {
                break;
            }
            $retries--;
            sleep(1);
        }
        
        if (is_wp_error($tmp) || !$tmp) {
            return false;
        }
        
        $file_array = array(
            'name' => basename(wp_parse_url($sanitized_url, PHP_URL_PATH) ?: 'image.jpg'),
            'tmp_name' => $tmp,
        );
        
        $attachment_id = media_handle_sideload($file_array, $parent_id);
        
        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            return false;
        }
        
        update_post_meta($attachment_id, '_source_url', $sanitized_url);
        return $attachment_id;
    }

    /**
     * Import file from URL
     *
     * @param string $file_url  File URL.
     * @param int    $parent_id Parent post ID.
     * @return int|false Attachment ID or false on failure.
     */
    private function import_file($file_url, $parent_id = 0) {
        if (empty($file_url)) {
            return false;
        }
        
        $sanitized_url = esc_url_raw($file_url);
        
        $existing = $this->get_attachment_by_url($sanitized_url);
        if ($existing) {
            return $existing;
        }
        
        return $this->import_image($sanitized_url, $parent_id);
    }

    /**
     * Get attachment by URL
     *
     * @param string $url Attachment URL.
     * @return int|false Attachment ID or false.
     */
    private function get_attachment_by_url($url) {
        global $wpdb;
        
        $sanitized_url = esc_url_raw($url);
        
        $id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_source_url' AND meta_value = %s",
                $sanitized_url
            )
        );
        if ($id) {
            return absint($id);
        }
        
        $id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE guid = %s",
                $sanitized_url
            )
        );
        if ($id) {
            return absint($id);
        }
        
        return false;
    }

    /**
     * Force cleanup of stuck imports
     */
    public function force_cleanup() {
        if (!isset($_GET['spinexim_reset_import']) || !current_user_can('manage_options')) {
            return;
        }

        // Verify nonce
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'spinexim_reset_import')) {
            wp_die(esc_html__('Security check failed.','spinda-exportimport-data'));
        }
        
        $this->cleanup_import_data();
        
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page' => 'spinexim-import',
                    'import_reset' => 1,
                ),
                admin_url('admin.php')
            )
        );
        exit;
    }
}

// Initialize import module
new Spinexim_Woo_Products_Import();