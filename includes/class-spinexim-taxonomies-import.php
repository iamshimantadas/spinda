<?php
/**
 * Spinda - Taxonomies Import Module
 *
 * @package Spinda
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Taxonomies Import Module Class
 */
class Spinexim_Taxonomies_Import {

    /**
     * Constructor
     */
    public function __construct() {
        // Handle import submission
        add_action('admin_init', array($this, 'handle_import'));
        
        // Process import batches
        add_action('spinexim_taxonomies_import_batch', array($this, 'process_import_batch'));
        
        // Handle reset
        add_action('admin_init', array($this, 'handle_reset'));
    }

    /**
     * Render import page
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'spinda-exportimport-data'));
        }

        // Check if import is running
        $is_running = get_transient('spinexim_taxonomies_import_running');
        $progress = get_transient('spinexim_taxonomies_import_progress') ?: 0;
        $message = get_transient('spinexim_taxonomies_import_message') ?: '';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Spinda - Taxonomies Import', 'spinda-exportimport-data'); ?></h1>
            
            <?php if ($is_running): ?>
                <!-- Progress display when import is running -->
                <div class="notice notice-info spinexim-import-notice">
                    <h3><?php echo esc_html__('Taxonomies Import in Progress', 'spinda-exportimport-data'); ?></h3>
                    <div class="spinexim-progress-bar">
                        <div class="spinexim-progress-fill" style="width: <?php echo intval($progress); ?>%;">
                            <?php echo intval($progress); ?>%
                        </div>
                    </div>
                    <p><strong><?php echo esc_html__('Status:', 'spinda-exportimport-data'); ?></strong> <?php echo esc_html($message); ?></p>
                    <p><em><?php echo esc_html__('Do not close this page. Auto-refreshing in 3 seconds...', 'spinda-exportimport-data'); ?></em></p>
                </div>
                
            <?php elseif (isset($_GET['import_done'])): ?>
                <!-- Import complete -->
                <?php $results = get_transient('spinexim_taxonomies_import_results'); ?>
                <?php if ($results): ?>
                    <div class="spinexim-card spinexim-import-success">
                        <h2><?php echo esc_html__('Taxonomies Import Complete!', 'spinda-exportimport-data'); ?></h2>
                        <table class="widefat">
                            <tbody>
                                <tr>
                                    <td><strong><?php echo esc_html__('Taxonomies Processed:', 'spinda-exportimport-data'); ?></strong></td>
                                    <td><?php echo esc_html($results['taxonomies_processed'] ?? 0); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo esc_html__('Terms Created:', 'spinda-exportimport-data'); ?></strong></td>
                                    <td><?php echo esc_html($results['terms_created'] ?? 0); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo esc_html__('Terms Updated:', 'spinda-exportimport-data'); ?></strong></td>
                                    <td><?php echo esc_html($results['terms_updated'] ?? 0); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo esc_html__('Terms Skipped:', 'spinda-exportimport-data'); ?></strong></td>
                                    <td><?php echo esc_html($results['terms_skipped'] ?? 0); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo esc_html__('Meta Fields Processed:', 'spinda-exportimport-data'); ?></strong></td>
                                    <td><?php echo esc_html($results['meta_processed'] ?? 0); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo esc_html__('Images Downloaded:', 'spinda-exportimport-data'); ?></strong></td>
                                    <td><?php echo esc_html($results['images_imported'] ?? 0); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo esc_html__('Term-Post Relationships:', 'spinda-exportimport-data'); ?></strong></td>
                                    <td><?php echo esc_html($results['relationships_imported'] ?? 0); ?></td>
                                </tr>
                                <?php if (!empty($results['errors'])): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html__('Errors:', 'spinda-exportimport-data'); ?></strong></td>
                                        <td class="spinexim-error-text"><?php echo count($results['errors']); ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        
                        <?php if (!empty($results['errors'])): ?>
                            <details style="margin-top: 20px;">
                                <summary><?php printf(esc_html__('Error Details (%d)', 'spinda-exportimport-data'), count($results['errors'])); ?></summary>
                                <pre class="spinexim-error-log"><?php echo esc_html(implode("\n", array_slice($results['errors'], 0, 100))); ?></pre>
                            </details>
                        <?php endif; ?>
                    </div>
                    <?php delete_transient('spinexim_taxonomies_import_results'); ?>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- Normal import form -->
                <div class="spinexim-card">
                    <h2><?php echo esc_html__('Import Taxonomies Configuration', 'spinda-exportimport-data'); ?></h2>
                    <p><?php echo esc_html__('Upload a JSON export file to import taxonomies and their terms. The system will import all taxonomy data including terms, hierarchy, and metadata.', 'spinda-exportimport-data'); ?></p>
                    
                    <form method="post" enctype="multipart/form-data" id="spinexim-taxonomies-import-form">
                        <?php wp_nonce_field('spinexim_taxonomies_import_nonce', 'spinexim_taxonomies_import_nonce_field'); ?>
                        <input type="hidden" name="spinexim_taxonomies_import_action" value="1">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label><?php echo esc_html__('Export File (JSON)', 'spinda-exportimport-data'); ?></label></th>
                                <td>
                                    <input type="file" name="spinexim_import_file" accept=".json" required class="spinexim-file-input">
                                    <p class="spinexim-description"><?php echo esc_html__('Upload the JSON file generated by the Taxonomies Export module', 'spinda-exportimport-data'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Update Existing Terms', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_overwrite" value="1" checked>
                                        <?php echo esc_html__('Update existing terms (matched by slug)', 'spinda-exportimport-data'); ?>
                                    </label>
                                    <p class="spinexim-description"><?php echo esc_html__('If unchecked, existing terms will be skipped', 'spinda-exportimport-data'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Import Term Meta', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_import_term_meta" value="1" checked>
                                        <?php echo esc_html__('Import all term metadata (custom fields, thumbnails, etc.)', 'spinda-exportimport-data'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Download Images', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_import_images" value="1" checked>
                                        <?php echo esc_html__('Download and import images from URLs', 'spinda-exportimport-data'); ?>
                                    </label>
                                    <p class="spinexim-description"><?php echo esc_html__('Will download term thumbnails and image meta fields', 'spinda-exportimport-data'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Create Taxonomies', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_create_taxonomies" value="1" checked>
                                        <?php echo esc_html__('Auto-register taxonomies if they do not exist', 'spinda-exportimport-data'); ?>
                                    </label>
                                    <p class="spinexim-description"><?php echo esc_html__('If taxonomy is not registered, it will be created automatically', 'spinda-exportimport-data'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Assign to Post Type', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_assign_to_post_type" value="1" checked>
                                        <?php echo esc_html__('Register taxonomies for the post type specified in export file', 'spinda-exportimport-data'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Assign Terms to Posts', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_assign_terms_to_posts" value="1" checked>
                                        <?php echo esc_html__('Automatically assign terms to posts based on export relationships', 'spinda-exportimport-data'); ?>
                                    </label>
                                    <p class="spinexim-description"><?php echo esc_html__('Terms will be assigned to posts using slug matching', 'spinda-exportimport-data'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <p>
                            <button type="submit" class="button button-primary button-hero" id="spinexim-taxonomies-import-button">
                                <span class="dashicons dashicons-upload"></span>
                                <?php echo esc_html__('Start Taxonomies Import', 'spinda-exportimport-data'); ?>
                            </button>
                        </p>
                    </form>
                </div>
            <?php endif; ?>
            
            <!-- Reset button -->
            <div class="spinexim-card spinexim-card-danger">
                <h3><?php echo esc_html__('Troubleshooting', 'spinda-exportimport-data'); ?></h3>
                <p><?php echo esc_html__('If import is stuck, click reset:', 'spinda-exportimport-data'); ?></p>
                <?php
                $reset_url = wp_nonce_url(
                    admin_url('admin.php?page=spinexim-taxonomies-import&spinexim_reset_tax_import=1'),
                    'spinexim_reset_taxonomies_import',
                    'spinexim_reset_nonce'
                );
                ?>
                <a href="<?php echo esc_url($reset_url); ?>" class="button button-secondary" id="spinexim-reset-tax-import">
                    <?php echo esc_html__('Reset Stuck Import', 'spinda-exportimport-data'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Handle reset
     */
    public function handle_reset() {
        if (!isset($_GET['spinexim_reset_tax_import']) || !current_user_can('manage_options')) {
            return;
        }

        // Verify nonce
        $nonce = isset($_GET['spinexim_reset_nonce']) ? sanitize_text_field(wp_unslash($_GET['spinexim_reset_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'spinexim_reset_taxonomies_import')) {
            wp_die(esc_html__('Security check failed.', 'spinda-exportimport-data'));
        }

        global $wpdb;

        // Delete all import transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '%_transient_spinexim_taxonomies_import_%',
                '%_transient_timeout_spinexim_taxonomies_import_%'
            )
        );

        // Clear cron
        wp_clear_scheduled_hook('spinexim_taxonomies_import_batch');

        // Redirect
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page' => 'spinexim-taxonomies-import',
                    'reset_done' => 1,
                ),
                admin_url('admin.php')
            )
        );
        exit;
    }

    /**
     * Handle import submission
     */
    public function handle_import() {
        // Check if import action is set
        if (!isset($_POST['spinexim_taxonomies_import_action'])) {
            return;
        }

        // Verify nonce
        if (!isset($_POST['spinexim_taxonomies_import_nonce_field']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spinexim_taxonomies_import_nonce_field'])), 'spinexim_taxonomies_import_nonce')) {
            wp_die(esc_html__('Security check failed. Please refresh the page and try again.', 'spinda-exportimport-data'));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'spinda-exportimport-data'));
        }

        // Validate file upload
        if (!isset($_FILES['spinexim_import_file'])) {
            wp_die(esc_html__('No file was uploaded.', 'spinda-exportimport-data'));
        }

        $file = $_FILES['spinexim_import_file'];

        if (UPLOAD_ERR_OK !== $file['error']) {
            wp_die(
                sprintf(
                    /* translators: %d: Upload error code */
                    esc_html__('File upload error code: %d', 'spinda-exportimport-data'),
                    intval($file['error'])
                )
            );
        }

        // Validate file type
        $file_info = wp_check_filetype($file['name'], array('json' => 'application/json'));
        if ('json' !== $file_info['ext']) {
            wp_die(esc_html__('Invalid file type. Please upload a JSON file.', 'spinda-exportimport-data'));
        }

        // Check if import is already running
        if (get_transient('spinexim_taxonomies_import_running')) {
            $reset_url = wp_nonce_url(
                admin_url('admin.php?page=spinexim-taxonomies-import&spinexim_reset_tax_import=1'),
                'spinexim_reset_taxonomies_import',
                'spinexim_reset_nonce'
            );
            wp_die(
                sprintf(
                    /* translators: %s: Reset URL */
                    esc_html__('An import is already running. Please wait or %sreset it%s.', 'spinda-exportimport-data'),
                    '<a href="' . esc_url($reset_url) . '">',
                    '</a>'
                )
            );
        }

        // Read and parse JSON
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $file_content = $wp_filesystem->get_contents($file['tmp_name']);
        if (false === $file_content) {
            wp_die(esc_html__('Failed to read uploaded file.', 'spinda-exportimport-data'));
        }

        $import_data = json_decode($file_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_die(
                sprintf(
                    /* translators: %s: JSON error message */
                    esc_html__('Invalid JSON file: %s', 'spinda-exportimport-data'),
                    json_last_error_msg()
                )
            );
        }

        // Validate data
        if (empty($import_data['taxonomies']) || !is_array($import_data['taxonomies'])) {
            wp_die(esc_html__('Invalid or missing taxonomies data in export file.', 'spinda-exportimport-data'));
        }

        // Set options
        $options = array(
            'post_type' => sanitize_text_field($import_data['post_type'] ?? 'post'),
            'overwrite' => !empty($_POST['spinexim_overwrite']),
            'import_term_meta' => !empty($_POST['spinexim_import_term_meta']),
            'import_images' => !empty($_POST['spinexim_import_images']),
            'create_taxonomies' => !empty($_POST['spinexim_create_taxonomies']),
            'assign_to_post_type' => !empty($_POST['spinexim_assign_to_post_type']),
            'assign_terms_to_posts' => !empty($_POST['spinexim_assign_terms_to_posts']),
        );

        // Clear any old cron jobs
        wp_clear_scheduled_hook('spinexim_taxonomies_import_batch');

        // Store data in transients for batch processing
        set_transient('spinexim_taxonomies_import_options', $options, 3600);
        set_transient('spinexim_taxonomies_import_data', $import_data['taxonomies'], 3600);
        set_transient('spinexim_taxonomies_import_total_taxonomies', count($import_data['taxonomies']), 3600);
        set_transient('spinexim_taxonomies_import_current_taxonomy', 0, 3600);
        
        // Store relationships data
        if (!empty($import_data['term_relationships'])) {
            set_transient('spinexim_taxonomies_import_relationships', $import_data['term_relationships'], 3600);
        }
        
        set_transient('spinexim_taxonomies_import_results', array(
            'taxonomies_processed' => 0,
            'terms_created' => 0,
            'terms_updated' => 0,
            'terms_skipped' => 0,
            'meta_processed' => 0,
            'images_imported' => 0,
            'relationships_imported' => 0,
            'errors' => array(),
        ), 3600);
        set_transient('spinexim_taxonomies_import_running', true, 3600);
        set_transient('spinexim_taxonomies_import_progress', 0, 3600);
        set_transient('spinexim_taxonomies_import_message', esc_html__('Starting taxonomies import...', 'spinda-exportimport-data'), 3600);

        // Clear memory
        unset($import_data, $file_content);

        // Schedule first batch
        wp_schedule_single_event(time() + 2, 'spinexim_taxonomies_import_batch');

        // Redirect to progress page
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page' => 'spinexim-taxonomies-import',
                    'import_started' => 1,
                ),
                admin_url('admin.php')
            )
        );
        exit;
    }

    /**
     * Process import batch via cron
     */
    public function process_import_batch() {
        // Check if still running
        if (!get_transient('spinexim_taxonomies_import_running')) {
            return;
        }

        // Increase limits
        wp_raise_memory_limit('admin');
        @set_time_limit(120);
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', 120);

        $options = get_transient('spinexim_taxonomies_import_options') ?: array();
        $all_taxonomies = get_transient('spinexim_taxonomies_import_data') ?: array();
        $current_tax_index = intval(get_transient('spinexim_taxonomies_import_current_taxonomy') ?: 0);
        $total_taxonomies = intval(get_transient('spinexim_taxonomies_import_total_taxonomies') ?: 0);
        $results = get_transient('spinexim_taxonomies_import_results') ?: array();

        // Get taxonomy names as array
        $tax_names = array_keys($all_taxonomies);

        // Process 1 taxonomy per batch
        if (isset($tax_names[$current_tax_index])) {
            $tax_name = $tax_names[$current_tax_index];
            $tax_data = $all_taxonomies[$tax_name];
            
            $this->import_taxonomy($tax_name, $tax_data, $options, $results);
            
            $current_tax_index++;
        }

        $progress = ($total_taxonomies > 0) ? round(($current_tax_index / $total_taxonomies) * 100) : 100;

        // After all taxonomies are imported, process relationships
        $relationships = get_transient('spinexim_taxonomies_import_relationships');
        if ($current_tax_index >= $total_taxonomies && !empty($relationships) && $options['assign_terms_to_posts']) {
            // Process relationships
            $this->import_relationships($relationships, $options, $results);
            delete_transient('spinexim_taxonomies_import_relationships');
        }

        // Update transients
        set_transient('spinexim_taxonomies_import_current_taxonomy', $current_tax_index, 3600);
        set_transient('spinexim_taxonomies_import_results', $results, 3600);
        set_transient('spinexim_taxonomies_import_progress', $progress, 3600);
        
        if ($current_tax_index >= $total_taxonomies && !empty($relationships) && $options['assign_terms_to_posts']) {
            set_transient(
                'spinexim_taxonomies_import_message',
                esc_html__('Processing term-post relationships...', 'spinda-exportimport-data'),
                3600
            );
        } else {
            set_transient(
                'spinexim_taxonomies_import_message',
                sprintf(
                    /* translators: 1: Current taxonomy processed, 2: Total taxonomies */
                    esc_html__('Processed %1$d of %2$d taxonomies', 'spinda-exportimport-data'),
                    $current_tax_index,
                    $total_taxonomies
                ),
                3600
            );
        }

        // Clear cache periodically
        wp_cache_flush();
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        // Continue or finish
        if ($current_tax_index < $total_taxonomies) {
            wp_schedule_single_event(time() + 3, 'spinexim_taxonomies_import_batch');
        } else {
            // Import complete
            delete_transient('spinexim_taxonomies_import_running');
            delete_transient('spinexim_taxonomies_import_data');
            delete_transient('spinexim_taxonomies_import_current_taxonomy');
            delete_transient('spinexim_taxonomies_import_total_taxonomies');
            delete_transient('spinexim_taxonomies_import_progress');
            delete_transient('spinexim_taxonomies_import_message');

            set_transient('spinexim_taxonomies_import_results', $results, 300);

            // Redirect to results
            wp_safe_redirect(
                add_query_arg(
                    array(
                        'page' => 'spinexim-taxonomies-import',
                        'import_done' => 1,
                    ),
                    admin_url('admin.php')
                )
            );
            exit;
        }
    }

    /**
     * Import single taxonomy with all its terms and metadata
     *
     * @param string $tax_name  Taxonomy name.
     * @param array  $tax_data  Taxonomy data.
     * @param array  $options   Import options.
     * @param array  $results   Results array (passed by reference).
     */
    private function import_taxonomy($tax_name, $tax_data, $options, &$results) {
        try {
            // Register taxonomy if not exists and option is enabled
            if (!taxonomy_exists($tax_name) && $options['create_taxonomies']) {
                $post_types = $options['assign_to_post_type'] ? array($options['post_type']) : array('post');
                
                register_taxonomy($tax_name, $post_types, array(
                    'label' => $tax_data['label'] ?? $tax_name,
                    'singular_label' => $tax_data['singular_label'] ?? $tax_data['label'] ?? $tax_name,
                    'hierarchical' => $tax_data['hierarchical'] ?? true,
                    'public' => $tax_data['public'] ?? true,
                    'show_ui' => $tax_data['show_ui'] ?? true,
                    'show_in_menu' => $tax_data['show_in_menu'] ?? true,
                    'show_in_nav_menus' => $tax_data['show_in_nav_menus'] ?? true,
                    'show_tagcloud' => $tax_data['show_tagcloud'] ?? true,
                    'show_in_quick_edit' => $tax_data['show_in_quick_edit'] ?? true,
                    'show_admin_column' => $tax_data['show_admin_column'] ?? true,
                    'rewrite' => array('slug' => $tax_data['rewrite_slug'] ?? $tax_name),
                    'query_var' => $tax_data['query_var'] ?? true,
                ));
            }

            if (!taxonomy_exists($tax_name)) {
                throw new Exception(
                    sprintf(
                        /* translators: %s: Taxonomy name */
                        esc_html__('Taxonomy "%s" does not exist and auto-creation is disabled.', 'spinda-exportimport-data'),
                        $tax_name
                    )
                );
            }

            if (empty($tax_data['terms'])) {
                $results['taxonomies_processed']++;
                return;
            }

            // First pass: Create/update all terms
            foreach ($tax_data['terms'] as $term_data) {
                $this->import_term($tax_name, $term_data, $options, $results);
            }

            // Second pass: Set parents and hierarchy (after all terms exist)
            foreach ($tax_data['terms'] as $term_data) {
                $term = get_term_by('slug', $term_data['slug'], $tax_name);
                if (!$term || is_wp_error($term)) {
                    continue;
                }

                // Set parent
                if (!empty($term_data['parent_slug'])) {
                    $parent = get_term_by('slug', $term_data['parent_slug'], $tax_name);
                    if ($parent && !is_wp_error($parent)) {
                        wp_update_term($term->term_id, $tax_name, array('parent' => $parent->term_id));
                    }
                }
            }

            $results['taxonomies_processed']++;

        } catch (Exception $e) {
            $results['errors'][] = sprintf(
                /* translators: 1: Taxonomy name, 2: Error message */
                esc_html__("Error with taxonomy '%1\$s': %2\$s", 'spinda-exportimport-data'),
                $tax_name,
                $e->getMessage()
            );
        }
    }

    /**
     * Import single term
     *
     * @param string $tax_name  Taxonomy name.
     * @param array  $term_data Term data.
     * @param array  $options   Import options.
     * @param array  $results   Results array (passed by reference).
     */
    private function import_term($tax_name, $term_data, $options, &$results) {
        try {
            $term_slug = $term_data['slug'];
            $existing = term_exists($term_slug, $tax_name);

            if ($existing && !$options['overwrite']) {
                $results['terms_skipped']++;
                return;
            }

            $args = array(
                'slug' => $term_slug,
            );

            // Add description if available
            if (isset($term_data['description'])) {
                $args['description'] = wp_kses_post($term_data['description']);
            }

            // Add parent (will be handled in second pass)
            if (!empty($term_data['parent_slug'])) {
                $parent = get_term_by('slug', $term_data['parent_slug'], $tax_name);
                if ($parent && !is_wp_error($parent)) {
                    $args['parent'] = $parent->term_id;
                }
            }

            if ($existing) {
                // Update existing term
                $result = wp_update_term($existing['term_id'], $tax_name, $args);
                if (is_wp_error($result)) {
                    throw new Exception(esc_html($result->get_error_message()));
                }
                $term_id = $result['term_id'];
                $results['terms_updated']++;
            } else {
                // Create new term
                $result = wp_insert_term(
                    $term_data['name'],
                    $tax_name,
                    $args
                );
                if (is_wp_error($result)) {
                    throw new Exception(esc_html($result->get_error_message()));
                }
                $term_id = $result['term_id'];
                $results['terms_created']++;
            }

            // Import term meta
            if (!empty($term_data['meta']) && $options['import_term_meta']) {
                foreach ($term_data['meta'] as $meta_key => $meta_value) {
                    $this->import_meta_value($term_id, $meta_key, $meta_value, $options, $results);
                }
            }

        } catch (Exception $e) {
            $results['errors'][] = sprintf(
                /* translators: 1: Term name, 2: Error message */
                esc_html__("Error with term '%1\$s': %2\$s", 'spinda-exportimport-data'),
                $term_data['name'] ?? 'Unknown',
                $e->getMessage()
            );
        }
    }

    /**
     * Import meta value for term
     *
     * @param int    $term_id    Term ID.
     * @param string $meta_key   Meta key.
     * @param mixed  $meta_value Meta value.
     * @param array  $options    Import options.
     * @param array  $results    Results array (passed by reference).
     */
    private function import_meta_value($term_id, $meta_key, $meta_value, $options, &$results) {
        if (empty($meta_value) && '0' !== $meta_value && 0 !== $meta_value) {
            return;
        }

        $sanitized_key = sanitize_text_field($meta_key);

        if (is_array($meta_value) && isset($meta_value['type'])) {
            switch ($meta_value['type']) {
                case 'acf_gallery':
                    if ($options['import_images'] && !empty($meta_value['value'])) {
                        $gallery_ids = array();
                        foreach ($meta_value['value'] as $img_data) {
                            if (!empty($img_data['url'])) {
                                $img_id = $this->download_image(sanitize_url($img_data['url']), 0);
                                if ($img_id) {
                                    $gallery_ids[] = $img_id;
                                    $results['images_imported']++;
                                }
                            }
                        }
                        if (!empty($gallery_ids)) {
                            update_term_meta($term_id, $sanitized_key, $gallery_ids);
                            $results['meta_processed']++;
                        }
                    }
                    break;

                case 'acf_link':
                    $link = array(
                        'title' => $meta_value['value']['title'] ?? '',
                        'url' => esc_url_raw($meta_value['value']['url'] ?? ''),
                        'target' => $meta_value['value']['target'] ?? '',
                    );
                    update_term_meta($term_id, $sanitized_key, $link);
                    $results['meta_processed']++;
                    break;

                case 'array':
                    $array_value = $meta_value['value'];
                    update_term_meta($term_id, $sanitized_key, $array_value);
                    $results['meta_processed']++;
                    break;

                case 'image':
                    if ($options['import_images'] && !empty($meta_value['url'])) {
                        $img_id = $this->download_image(sanitize_url($meta_value['url']), 0);
                        if ($img_id) {
                            update_term_meta($term_id, $sanitized_key, $img_id);
                            $results['images_imported']++;
                            $results['meta_processed']++;
                        }
                    } elseif (!empty($meta_value['id'])) {
                        update_term_meta($term_id, $sanitized_key, intval($meta_value['id']));
                        $results['meta_processed']++;
                    }
                    break;

                case 'file':
                    if ($options['import_images'] && !empty($meta_value['url'])) {
                        $file_id = $this->download_file(sanitize_url($meta_value['url']), 0);
                        if ($file_id) {
                            update_term_meta($term_id, $sanitized_key, $file_id);
                            $results['meta_processed']++;
                        }
                    } elseif (!empty($meta_value['id'])) {
                        update_term_meta($term_id, $sanitized_key, intval($meta_value['id']));
                        $results['meta_processed']++;
                    }
                    break;

                case 'serialized':
                    $data = $meta_value['value'];
                    $this->process_serialized_recursive($data, 0, $options, $results);
                    update_term_meta($term_id, $sanitized_key, $data);
                    $results['meta_processed']++;
                    break;

                default:
                    $value = $meta_value['value'] ?? $meta_value;
                    update_term_meta($term_id, $sanitized_key, $value);
                    $results['meta_processed']++;
            }
        } else {
            $value = $meta_value;
            update_term_meta($term_id, $sanitized_key, $value);
            $results['meta_processed']++;
        }
    }

    /**
     * Process serialized data recursively
     *
     * @param array $data      Data to process (passed by reference).
     * @param int   $parent_id Parent ID.
     * @param array $options   Import options.
     * @param array $results   Results array (passed by reference).
     */
    private function process_serialized_recursive(&$data, $parent_id, $options, &$results) {
        if (!is_array($data)) {
            return;
        }

        foreach ($data as $key => &$value) {
            if (is_array($value) && isset($value['type'])) {
                if ('image' === $value['type'] && $options['import_images'] && !empty($value['url'])) {
                    $img_id = $this->download_image(sanitize_url($value['url']), $parent_id);
                    if ($img_id) {
                        $value = $img_id;
                        $results['images_imported']++;
                    }
                } elseif ('file' === $value['type'] && !empty($value['url'])) {
                    $file_id = $this->download_file(sanitize_url($value['url']), $parent_id);
                    if ($file_id) {
                        $value = $file_id;
                    }
                }
            } elseif (is_array($value)) {
                $this->process_serialized_recursive($value, $parent_id, $options, $results);
            }
        }
    }

    /**
     * Import all term-post relationships
     *
     * @param array $relationships All relationship data.
     * @param array $options Import options.
     * @param array $results Results array (passed by reference).
     */
    private function import_relationships($relationships, $options, &$results) {
        if (empty($relationships) || !is_array($relationships)) {
            return;
        }
        
        set_transient(
            'spinexim_taxonomies_import_message',
            esc_html__('Importing term-post relationships...', 'spinda-exportimport-data'),
            3600
        );
        
        foreach ($relationships as $tax_name => $tax_relationships) {
            $this->import_term_post_relationships($tax_name, $tax_relationships, $options, $results);
        }
        
        set_transient(
            'spinexim_taxonomies_import_message',
            esc_html__('Relationships imported successfully!', 'spinda-exportimport-data'),
            3600
        );
    }

    /**
     * Import term-post relationships
     *
     * @param string $tax_name Taxonomy name.
     * @param array  $relationships Relationship data.
     * @param array  $options Import options.
     * @param array  $results Results array (passed by reference).
     */
    private function import_term_post_relationships($tax_name, $relationships, $options, &$results) {
        if (empty($relationships) || !is_array($relationships)) {
            return;
        }
        
        foreach ($relationships as $term_slug => $posts) {
            // Get term by slug
            $term = get_term_by('slug', $term_slug, $tax_name);
            if (!$term || is_wp_error($term)) {
                $results['errors'][] = sprintf(
                    esc_html__('Term "%s" not found for relationship import.', 'spinda-exportimport-data'),
                    $term_slug
                );
                continue;
            }
            
            foreach ($posts as $post_data) {
                // Find post by slug or ID
                $post_id = 0;
                $post_type = $post_data['post_type'] ?? 'any';
                
                // Try by slug first
                if (!empty($post_data['post_slug'])) {
                    $existing_post = get_posts(array(
                        'name' => $post_data['post_slug'],
                        'post_type' => $post_type,
                        'post_status' => 'any',
                        'posts_per_page' => 1,
                    ));
                    if (!empty($existing_post)) {
                        $post_id = $existing_post[0]->ID;
                    }
                }
                
                // If not found by slug, try by ID
                if (!$post_id && !empty($post_data['post_id'])) {
                    $post = get_post($post_data['post_id']);
                    if ($post) {
                        $post_id = $post->ID;
                    }
                }
                
                // If post found, assign term
                if ($post_id) {
                    $result = wp_set_object_terms($post_id, $term->term_id, $tax_name, true);
                    if (!is_wp_error($result)) {
                        $results['relationships_imported'] = ($results['relationships_imported'] ?? 0) + 1;
                    }
                }
            }
        }
    }

    /**
     * Download image from URL
     *
     * @param string $url       Image URL.
     * @param int    $parent_id Parent post ID.
     * @return int|false Attachment ID or false on failure.
     */
    private function download_image($url, $parent_id = 0) {
        if (empty($url)) {
            return false;
        }

        $sanitized_url = esc_url_raw($url);

        // Check if already imported
        global $wpdb;
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_source_url' AND meta_value = %s LIMIT 1",
                $sanitized_url
            )
        );
        if ($existing) {
            return absint($existing);
        }

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Download with retry
        $tmp = false;
        for ($i = 0; $i < 3; $i++) {
            $tmp = download_url($sanitized_url, 30);
            if (!is_wp_error($tmp)) {
                break;
            }
            sleep(2);
        }

        if (is_wp_error($tmp) || !$tmp) {
            return false;
        }

        $file_array = array(
            'name' => basename(wp_parse_url($sanitized_url, PHP_URL_PATH)) ?: 'image.jpg',
            'tmp_name' => $tmp,
        );

        $id = media_handle_sideload($file_array, $parent_id);

        if (is_wp_error($id)) {
            @unlink($tmp);
            return false;
        }

        update_post_meta($id, '_source_url', $sanitized_url);
        return $id;
    }

    /**
     * Download file from URL
     *
     * @param string $url       File URL.
     * @param int    $parent_id Parent post ID.
     * @return int|false Attachment ID or false on failure.
     */
    private function download_file($url, $parent_id = 0) {
        if (empty($url)) {
            return false;
        }

        $sanitized_url = esc_url_raw($url);

        // Check if already imported
        global $wpdb;
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_source_url' AND meta_value = %s LIMIT 1",
                $sanitized_url
            )
        );
        if ($existing) {
            return absint($existing);
        }

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url($sanitized_url, 30);
        if (is_wp_error($tmp) || !$tmp) {
            return false;
        }

        $file_array = array(
            'name' => basename(wp_parse_url($sanitized_url, PHP_URL_PATH)) ?: 'file',
            'tmp_name' => $tmp,
        );

        $id = media_handle_sideload($file_array, $parent_id);

        if (is_wp_error($id)) {
            @unlink($tmp);
            return false;
        }

        update_post_meta($id, '_source_url', $sanitized_url);
        return $id;
    }
}

// Initialize taxonomies import module
new Spinexim_Taxonomies_Import();