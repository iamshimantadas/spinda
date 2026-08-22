<?php
/**
 * Spinda - Post Types Import Module
 *
 * @package Spinda
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Post Types Import Module Class
 */
class Spinexim_Post_Types_Import
{

    /**
     * Constructor
     */
    public function __construct()
    {
        // Handle import submission
        add_action('admin_init', array($this, 'spinexim_post_types_handle_import'));

        // Process import batches - FIX: Use correct hook name
        add_action('spinexim_post_import_batch', array($this, 'spinexim_post_types_process_import_batch'));

        // Handle reset
        add_action('admin_init', array($this, 'handle_reset'));

        // AJAX handler for stopping import
        add_action('wp_ajax_spinexim_stop_post_import', array($this, 'ajax_stop_import'));

        // Handle stop/cancel via GET
        add_action('admin_init', array($this, 'handle_stop_import'));
    }

    /**
     * AJAX handler to stop import
     */
    public function ajax_stop_import()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'spinexim_admin_nonce')) {
            wp_send_json_error(array('message' => esc_html__('Security check failed.', 'spinda-exportimport-data')));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Unauthorized.', 'spinda-exportimport-data')));
        }

        // Set paused flag
        set_transient('spinexim_post_import_paused', true, 7200);

        // Clear scheduled hooks
        wp_clear_scheduled_hook('spinexim_post_import_batch');

        // Update message
        set_transient('spinexim_post_import_message', esc_html__('Import paused by user. Click Resume to continue.', 'spinda-exportimport-data'), 7200);

        wp_send_json_success(array('message' => esc_html__('Import stopped successfully.', 'spinda-exportimport-data')));
    }

    /**
     * Handle stop/resume/cancel import
     */
    public function handle_stop_import()
    {
        // Handle resume import
        if (isset($_GET['spinexim_resume_post_import']) && current_user_can('manage_options')) {
            $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
            if (!wp_verify_nonce($nonce, 'spinexim_resume_post_import')) {
                wp_die(esc_html__('Security check failed.', 'spinda-exportimport-data'));
            }

            delete_transient('spinexim_post_import_paused');
            set_transient('spinexim_post_import_message', esc_html__('Resuming import...', 'spinda-exportimport-data'), 7200);
            wp_schedule_single_event(time() + 3, 'spinexim_post_import_batch');

            wp_safe_redirect(add_query_arg(array('page' => 'spinexim-post-import', 'import_resumed' => 1), admin_url('admin.php')));
            exit;
        }

        // Handle cancel import
        if (isset($_GET['spinexim_cancel_post_import']) && current_user_can('manage_options')) {
            $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
            if (!wp_verify_nonce($nonce, 'spinexim_cancel_post_import')) {
                wp_die(esc_html__('Security check failed.', 'spinda-exportimport-data'));
            }

            $results = get_transient('spinexim_post_import_results') ?: array();
            $results['stopped'] = true;
            set_transient('spinexim_post_import_results', $results, 300);

            $this->cleanup_import_data();

            wp_safe_redirect(add_query_arg(array('page' => 'spinexim-post-import', 'import_done' => 1), admin_url('admin.php')));
            exit;
        }
    }

    /**
     * Cleanup import data
     */
    private function cleanup_import_data()
    {
        global $wpdb;

        delete_transient('spinexim_post_import_running');
        delete_transient('spinexim_post_import_paused');
        delete_transient('spinexim_post_import_progress');
        delete_transient('spinexim_post_import_message');
        delete_transient('spinexim_post_import_current');
        delete_transient('spinexim_post_import_total');
        delete_transient('spinexim_post_import_options');
        delete_transient('spinexim_post_import_items');
        delete_transient('spinexim_post_import_taxonomies');
        delete_transient('spinexim_post_import_finished');

        // Clear product transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '%_transient_spinexim_post_import_%'
            )
        );
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '%_transient_timeout_spinexim_post_import_%'
            )
        );

        wp_clear_scheduled_hook('spinexim_post_import_batch');
    }

    /**
     * Render import page
     */
    public function render_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'spinda-exportimport-data'));
        }

        // Check if import is running
        $is_running = get_transient('spinexim_post_import_running');
        $progress = get_transient('spinexim_post_import_progress') ?: 0;
        $message = get_transient('spinexim_post_import_message') ?: '';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Spinda - Post Types Import', 'spinda-exportimport-data'); ?></h1>

            <?php if ($is_running): ?>
                <!-- Progress display when import is running -->
                <div class="notice notice-info spinexim-import-notice">
                    <h3><?php echo esc_html__('Import in Progress', 'spinda-exportimport-data'); ?></h3>
                    <div class="spinexim-progress-bar">
                        <div class="spinexim-progress-fill" style="width: <?php echo intval($progress); ?>%;">
                            <?php echo intval($progress); ?>%
                        </div>
                    </div>
                    <p><strong><?php echo esc_html__('Status:', 'spinda-exportimport-data'); ?></strong>
                        <?php echo esc_html($message); ?></p>
                    <p><em><?php echo esc_html__('Do not close this page. Auto-refreshing in 3 seconds...', 'spinda-exportimport-data'); ?></em>
                    </p>
                </div>

            <?php elseif (isset($_GET['import_done'])): ?>
                <!-- Import complete -->
                <?php $results = get_transient('spinexim_post_import_results'); ?>
                <?php if ($results): ?>
                    <div class="spinexim-card spinexim-import-success">
                        <h2><?php echo esc_html__('Import Complete!', 'spinda-exportimport-data'); ?></h2>
                        <table class="widefat">
                            <tbody>
                                <tr>
                                    <td><strong><?php echo esc_html__('Items Processed:', 'spinda-exportimport-data'); ?></strong></td>
                                    <td><?php echo esc_html($results['items_processed'] ?? 0); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo esc_html__('Items Created:', 'spinda-exportimport-data'); ?></strong></td>
                                    <td><?php echo esc_html($results['items_created'] ?? 0); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo esc_html__('Items Updated:', 'spinda-exportimport-data'); ?></strong></td>
                                    <td><?php echo esc_html($results['items_updated'] ?? 0); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo esc_html__('Terms Created:', 'spinda-exportimport-data'); ?></strong></td>
                                    <td><?php echo esc_html($results['terms_created'] ?? 0); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo esc_html__('Comments Imported:', 'spinda-exportimport-data'); ?></strong></td>
                                    <td><?php echo esc_html($results['comments_imported'] ?? 0); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo esc_html__('Images Downloaded:', 'spinda-exportimport-data'); ?></strong></td>
                                    <td><?php echo esc_html($results['images_imported'] ?? 0); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo esc_html__('Meta Fields Processed:', 'spinda-exportimport-data'); ?></strong>
                                    </td>
                                    <td><?php echo esc_html($results['meta_processed'] ?? 0); ?></td>
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
                                <summary>
                                    <?php printf(esc_html__('Error Details (%d)', 'spinda-exportimport-data'), count($results['errors'])); ?>
                                </summary>
                                <pre
                                    class="spinexim-error-log"><?php echo esc_html(implode("\n", array_slice($results['errors'], 0, 100))); ?></pre>
                            </details>
                        <?php endif; ?>
                    </div>
                    <?php delete_transient('spinexim_post_import_results'); ?>
                <?php endif; ?>

            <?php else: ?>
                <!-- Normal import form -->
                <div class="spinexim-card">
                    <h2><?php echo esc_html__('Import Configuration', 'spinda-exportimport-data'); ?></h2>
                    <p><?php echo esc_html__('Upload a JSON export file to import data.', 'spinda-exportimport-data'); ?></p>

                    <form method="post" enctype="multipart/form-data" id="spinexim-post-import-form">
                        <?php wp_nonce_field('spinexim_post_import_nonce', 'spinexim_post_import_nonce_field'); ?>
                        <input type="hidden" name="spinexim_post_import_action" value="1">

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label><?php echo esc_html__('Export File (JSON)', 'spinda-exportimport-data'); ?></label></th>
                                <td>
                                    <input type="file" name="spinexim_import_file" accept=".json" required
                                        class="spinexim-file-input">
                                    <p class="spinexim-description">
                                        <?php echo esc_html__('Upload the JSON file generated by the Export module', 'spinda-exportimport-data'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Update Existing', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_overwrite" value="1" checked>
                                        <?php echo esc_html__('Update existing items (matched by slug)', 'spinda-exportimport-data'); ?>
                                    </label>
                                    <p class="spinexim-description">
                                        <?php echo esc_html__('If unchecked, existing items will be skipped', 'spinda-exportimport-data'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Download Images', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_import_images" value="1" checked>
                                        <?php echo esc_html__('Download and import images from URLs', 'spinda-exportimport-data'); ?>
                                    </label>
                                    <p class="spinexim-description">
                                        <?php echo esc_html__('Will download featured images and ACF gallery images', 'spinda-exportimport-data'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Import Comments', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_import_comments" value="1" checked>
                                        <?php echo esc_html__('Import comments/reviews', 'spinda-exportimport-data'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Import Meta Fields', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_import_meta" value="1" checked>
                                        <?php echo esc_html__('Import custom fields and ACF data', 'spinda-exportimport-data'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <p>
                            <button type="submit" class="button button-primary button-hero" id="spinexim-import-button">
                                <span class="dashicons dashicons-upload"></span>
                                <?php echo esc_html__('Start Import', 'spinda-exportimport-data'); ?>
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
                // $reset_url = wp_nonce_url(
                //     admin_url('admin.php?page=spinexim-post-import&spinexim_reset_import=1'),
                //     'spinexim_reset_post_import',
                //     'spinexim_reset_nonce'
                // );
                $reset_url = wp_nonce_url(
                    admin_url('admin.php?page=spinexim-post-import&spinexim_reset_import=1'),
                    'spinexim_reset_post_import',
                    'spinexim_reset_nonce'
                );
                ?>
                <a href="<?php echo esc_url($reset_url); ?>" class="button button-secondary" id="spinexim-reset-import">
                    <?php echo esc_html__('Reset Stuck Import', 'spinda-exportimport-data'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Handle reset
     */
    public function handle_reset()
    {
        if (!isset($_GET['spinexim_reset_import']) || !current_user_can('manage_options')) {
            return;
        }

        // Verify nonce
        $nonce = isset($_GET['spinexim_reset_nonce']) ? sanitize_text_field(wp_unslash($_GET['spinexim_reset_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'spinexim_reset_post_import')) {
            wp_die(esc_html__('Security check failed.', 'spinda-exportimport-data'));
        }

        global $wpdb;

        // Delete all import transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '%_transient_spinexim_post_import_%',
                '%_transient_timeout_spinexim_post_import_%'
            )
        );

        // Clear cron
        wp_clear_scheduled_hook('spinexim_post_import_batch');

        // Redirect
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page' => 'spinexim-post-import',
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
    public function spinexim_post_types_handle_import()
    {
        // Check if import action is set
        if (!isset($_POST['spinexim_post_import_action'])) {
            return;
        }

        // Verify nonce
        if (
            !isset($_POST['spinexim_post_import_nonce_field']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spinexim_post_import_nonce_field'])), 'spinexim_post_import_nonce')
        ) {
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
        if (get_transient('spinexim_post_import_running')) {
            $reset_url = wp_nonce_url(
                admin_url('admin.php?page=spinexim-post-import&spinexim_reset_import=1'),
                'spinexim_reset_post_import',
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
        if (empty($import_data['post_type']) || !post_type_exists($import_data['post_type'])) {
            wp_die(esc_html__('Invalid or missing post type in export file.', 'spinda-exportimport-data'));
        }

        // Set options
        $options = array(
            'post_type' => sanitize_text_field($import_data['post_type']),
            'overwrite' => !empty($_POST['spinexim_overwrite']),
            'import_images' => !empty($_POST['spinexim_import_images']),
            'import_comments' => !empty($_POST['spinexim_import_comments']),
            'import_meta' => !empty($_POST['spinexim_import_meta']),
        );

        // Clear any old cron jobs
        wp_clear_scheduled_hook('spinexim_post_import_batch');

        // Store data in transients for batch processing
        set_transient('spinexim_post_import_options', $options, 3600);
        set_transient('spinexim_post_import_taxonomies', $import_data['taxonomies'] ?? array(), 3600);
        set_transient('spinexim_post_import_items', $import_data['items'] ?? array(), 3600);
        set_transient('spinexim_post_import_total', count($import_data['items'] ?? array()), 3600);
        set_transient('spinexim_post_import_current', 0, 3600);
        set_transient('spinexim_post_import_results', array(
            'items_processed' => 0,
            'items_created' => 0,
            'items_updated' => 0,
            'terms_created' => 0,
            'comments_imported' => 0,
            'images_imported' => 0,
            'meta_processed' => 0,
            'errors' => array(),
        ), 3600);
        set_transient('spinexim_post_import_running', true, 3600);
        set_transient('spinexim_post_import_progress', 0, 3600);
        set_transient('spinexim_post_import_message', esc_html__('Starting import...', 'spinda-exportimport-data'), 3600);

        // Clear memory
        unset($import_data, $file_content);

        // Schedule first batch
        wp_schedule_single_event(time() + 2, 'spinexim_post_import_batch');

        // Redirect to progress page
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page' => 'spinexim-post-import',
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
    public function spinexim_post_types_process_import_batch()
    {
        // // Check if still running
        // if (!get_transient('spinexim_post_import_running')) {
        //     return;
        // }

        // Check if paused
        if (get_transient('spinexim_post_import_paused')) {
            wp_clear_scheduled_hook('spinexim_post_import_batch');
            return;
        }

        // Check if still running
        if (!get_transient('spinexim_post_import_running')) {
            return;
        }

        // Increase limits
        wp_raise_memory_limit('admin');
        @set_time_limit(120);
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', 120);

        $options = get_transient('spinexim_post_import_options') ?: array();
        $all_items = get_transient('spinexim_post_import_items') ?: array();
        $current = intval(get_transient('spinexim_post_import_current') ?: 0);
        $total = intval(get_transient('spinexim_post_import_total') ?: 0);
        $results = get_transient('spinexim_post_import_results') ?: array();
        $taxonomies = get_transient('spinexim_post_import_taxonomies') ?: array();

        // Process taxonomies first (only once)
        static $taxonomies_done = false;
        if (!$taxonomies_done && !empty($taxonomies)) {
            foreach ($taxonomies as $tax_name => $tax_data) {
                $this->import_taxonomy($tax_name, $tax_data, $options, $results);
            }
            $taxonomies_done = true;
            delete_transient('spinexim_post_import_taxonomies');
        }

        // Process batch of items (5 per batch)
        $batch_size = 5;
        $batch_end = min($current + $batch_size, $total);

        for ($i = $current; $i < $batch_end; $i++) {
            if (isset($all_items[$i])) {
                $this->import_single_item($all_items[$i], $options, $results);
                unset($all_items[$i]);
            }
        }

        $current = $batch_end;
        $progress = ($total > 0) ? round(($current / $total) * 100) : 100;

        // Update transients
        set_transient('spinexim_post_import_current', $current, 3600);
        set_transient('spinexim_post_import_results', $results, 3600);
        set_transient('spinexim_post_import_progress', $progress, 3600);
        set_transient(
            'spinexim_post_import_message',
            sprintf(
                /* translators: 1: Current items processed, 2: Total items */
                esc_html__('Processed %1$d of %2$d items', 'spinda-exportimport-data'),
                $current,
                $total
            ),
            3600
        );

        // Clear cache periodically
        if (0 === $current % 10) {
            wp_cache_flush();
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        // Continue or finish
        if ($current < $total) {
            wp_schedule_single_event(time() + 3, 'spinexim_post_import_batch');
        } else {
            // Import complete
            delete_transient('spinexim_post_import_running');
            delete_transient('spinexim_post_import_items');
            delete_transient('spinexim_post_import_current');
            delete_transient('spinexim_post_import_total');
            delete_transient('spinexim_post_import_progress');
            delete_transient('spinexim_post_import_message');

            set_transient('spinexim_post_import_results', $results, 300);

            // Redirect to results
            wp_safe_redirect(
                add_query_arg(
                    array(
                        'page' => 'spinexim-post-import',
                        'import_done' => 1,
                    ),
                    admin_url('admin.php')
                )
            );
            exit;
        }
    }

    /**
     * Import taxonomy
     *
     * @param string $tax_name  Taxonomy name.
     * @param array  $tax_data  Taxonomy data.
     * @param array  $options   Import options.
     * @param array  $results   Results array (passed by reference).
     */
    private function import_taxonomy($tax_name, $tax_data, $options, &$results)
    {
        // Register taxonomy if not exists
        if (!taxonomy_exists($tax_name)) {
            register_taxonomy($tax_name, $options['post_type'], array(
                'label' => $tax_data['label'] ?? $tax_name,
                'hierarchical' => $tax_data['hierarchical'] ?? true,
                'public' => true,
            ));
        }

        if (empty($tax_data['terms'])) {
            return;
        }

        // First pass: create all terms
        foreach ($tax_data['terms'] as $term_data) {
            $existing = term_exists($term_data['slug'], $tax_name);
            if (!$existing) {
                $result = wp_insert_term(
                    $term_data['name'],
                    $tax_name,
                    array(
                        'slug' => $term_data['slug'],
                        'description' => wp_kses_post($term_data['description'] ?? ''),
                    )
                );
                if (!is_wp_error($result)) {
                    $results['terms_created']++;
                }
            }
        }

        // Second pass: set parents and meta
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

            // Set order
            if (!empty($term_data['term_order'])) {
                update_term_meta($term->term_id, 'order', absint($term_data['term_order']));
            }

            // Import meta
            if (!empty($term_data['meta'])) {
                foreach ($term_data['meta'] as $meta_key => $meta_value) {
                    $this->import_meta_value($term->term_id, $meta_key, $meta_value, 'term', $options, $results);
                }
            }

            // Import thumbnail
            if (!empty($term_data['thumbnail']) && $options['import_images']) {
                $img_id = $this->download_image($term_data['thumbnail'], 0);
                if ($img_id) {
                    update_term_meta($term->term_id, 'thumbnail_id', $img_id);
                    $results['images_imported']++;
                }
            }
        }
    }

    /**
     * Import single item
     *
     * @param array $item_data Item data.
     * @param array $options   Import options.
     * @param array $results   Results array (passed by reference).
     */
    private function import_single_item($item_data, $options, &$results)
    {
        try {
            $post_type = $options['post_type'];
            $existing_id = null;

            // Find existing by slug
            if (!empty($item_data['slug'])) {
                $existing = get_posts(array(
                    'name' => $item_data['slug'],
                    'post_type' => $post_type,
                    'post_status' => 'any',
                    'posts_per_page' => 1,
                ));
                if (!empty($existing)) {
                    $existing_id = $existing[0]->ID;
                }
            }

            // Skip if exists and not overwriting
            if ($existing_id && !$options['overwrite']) {
                $results['items_processed']++;
                return;
            }

            // Prepare post data
            $post_data = array(
                'post_type' => $post_type,
                'post_title' => sanitize_text_field($item_data['title'] ?? 'Untitled'),
                'post_name' => sanitize_title($item_data['slug'] ?? ''),
                'post_content' => wp_kses_post($item_data['content'] ?? ''),
                'post_excerpt' => wp_kses_post($item_data['excerpt'] ?? ''),
                'post_status' => sanitize_key($item_data['status'] ?? 'publish'),
                'menu_order' => absint($item_data['menu_order'] ?? 0),
                'comment_status' => sanitize_key($item_data['comment_status'] ?? 'open'),
                'ping_status' => sanitize_key($item_data['ping_status'] ?? 'open'),
            );

            // Password
            if (!empty($item_data['password'])) {
                $post_data['post_password'] = $item_data['password'];
            }

            // Dates
            if (!empty($item_data['date_created'])) {
                $post_data['post_date'] = sanitize_text_field($item_data['date_created']);
            }

            // Author
            if (!empty($item_data['author']['id'])) {
                $post_data['post_author'] = absint($item_data['author']['id']);
            }

            // Parent
            if (!empty($item_data['parent_slug'])) {
                $parent = get_page_by_path(sanitize_title($item_data['parent_slug']), OBJECT, $post_type);
                if ($parent) {
                    $post_data['post_parent'] = $parent->ID;
                }
            }

            // Insert or update
            if ($existing_id) {
                $post_data['ID'] = $existing_id;
                $item_id = wp_update_post($post_data, true);
                if (is_wp_error($item_id)) {
                    throw new Exception(esc_html($item_id->get_error_message()));
                }
                $results['items_updated']++;
            } else {
                $item_id = wp_insert_post($post_data, true);
                if (is_wp_error($item_id)) {
                    throw new Exception(esc_html($item_id->get_error_message()));
                }
                $results['items_created']++;
            }

            $results['items_processed']++;

            // Template
            if (!empty($item_data['template'])) {
                update_post_meta($item_id, '_wp_page_template', $item_data['template']);
            }

            // Featured image
            if (!empty($item_data['featured_image']['url']) && $options['import_images']) {
                $img_id = $this->download_image($item_data['featured_image']['url'], $item_id);
                if ($img_id) {
                    set_post_thumbnail($item_id, $img_id);
                    if (!empty($item_data['featured_image']['alt'])) {
                        update_post_meta($img_id, '_wp_attachment_image_alt', $item_data['featured_image']['alt']);
                    }
                    $results['images_imported']++;
                }
            }

            // Taxonomies
            if (!empty($item_data['taxonomies'])) {
                foreach ($item_data['taxonomies'] as $tax_name => $terms) {
                    if (!taxonomy_exists($tax_name)) {
                        register_taxonomy($tax_name, $post_type, array('public' => true));
                    }
                    $term_ids = array();
                    foreach ($terms as $term_data) {
                        $term = get_term_by('slug', $term_data['slug'], $tax_name);
                        if ($term && !is_wp_error($term)) {
                            $term_ids[] = $term->term_id;
                        }
                    }
                    if (!empty($term_ids)) {
                        wp_set_object_terms($item_id, $term_ids, $tax_name, false);
                    }
                }
            }

            // Meta
            if (!empty($item_data['meta']) && $options['import_meta']) {
                foreach ($item_data['meta'] as $meta_key => $meta_value) {
                    $this->import_meta_value($item_id, $meta_key, $meta_value, 'post', $options, $results);
                }
            }

            // Comments
            if (!empty($item_data['comments']) && $options['import_comments']) {
                foreach ($item_data['comments'] as $comment_data) {
                    $this->import_comment($item_id, $comment_data, $results);
                }
            }

            clean_post_cache($item_id);

        } catch (Exception $e) {
            $results['errors'][] = sprintf(
                /* translators: 1: Item title, 2: Error message */
                esc_html__("Error with '%1\$s': %2\$s", 'spinda-exportimport-data'),
                $item_data['title'] ?? 'Unknown',
                $e->getMessage()
            );
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
    private function import_meta_value($object_id, $meta_key, $meta_value, $type, $options, &$results)
    {
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
                                $img_id = $this->download_image($img_data['url'], $object_id);
                                if ($img_id) {
                                    $gallery_ids[] = $img_id;
                                    $results['images_imported']++;
                                }
                            }
                        }
                        if (!empty($gallery_ids)) {
                            'post' === $type ? update_post_meta($object_id, $sanitized_key, $gallery_ids)
                                : update_term_meta($object_id, $sanitized_key, $gallery_ids);
                            $results['meta_processed']++;
                        }
                    }
                    break;

                case 'acf_link':
                    $link = array(
                        'title' => $meta_value['value']['title'],
                        'url' => $meta_value['value']['url'],
                        'target' => $meta_value['value']['target'],
                    );
                    'post' === $type ? update_post_meta($object_id, $sanitized_key, $link)
                        : update_term_meta($object_id, $sanitized_key, $link);
                    $results['meta_processed']++;
                    break;

                case 'array':
                case 'acf_array':
                    $array_value = $meta_value['value'];

                    'post' === $type ? update_post_meta($object_id, $sanitized_key, $array_value)
                        : update_term_meta($object_id, $sanitized_key, $array_value);
                    $results['meta_processed']++;
                    break;

                case 'image':
                    if ($options['import_images'] && !empty($meta_value['url'])) {
                        $img_id = $this->download_image($meta_value['url'], $object_id);
                        if ($img_id) {
                            'post' === $type ? update_post_meta($object_id, $sanitized_key, $img_id)
                                : update_term_meta($object_id, $sanitized_key, $img_id);
                            $results['images_imported']++;
                            $results['meta_processed']++;
                        }
                    }
                    break;

                case 'file':
                    if ($options['import_images'] && !empty($meta_value['url'])) {
                        $file_id = $this->download_file($meta_value['url'], $object_id);
                        if ($file_id) {
                            'post' === $type ? update_post_meta($object_id, $sanitized_key, $file_id)
                                : update_term_meta($object_id, $sanitized_key, $file_id);
                            $results['meta_processed']++;
                        }
                    }
                    break;

                case 'serialized':
                    $data = $meta_value['value'];
                    $this->process_serialized_recursive($data, $object_id, $options, $results);
                    'post' === $type ? update_post_meta($object_id, $sanitized_key, $data)
                        : update_term_meta($object_id, $sanitized_key, $data);
                    $results['meta_processed']++;
                    break;

                default:
                    $value = $meta_value['value'] ?? $meta_value;
                    'post' === $type ? update_post_meta($object_id, $sanitized_key, $value)
                        : update_term_meta($object_id, $sanitized_key, $value);
                    $results['meta_processed']++;
            }
        } else {
            $value = is_array($meta_value) ? $meta_value : $meta_value;
            'post' === $type ? update_post_meta($object_id, $sanitized_key, $value)
                : update_term_meta($object_id, $sanitized_key, $value);
            $results['meta_processed']++;
        }
    }

    /**
     * Process serialized data recursively
     *
     * @param array $data      Data to process (passed by reference).
     * @param int   $object_id Object ID.
     * @param array $options   Import options.
     * @param array $results   Results array (passed by reference).
     */
    private function process_serialized_recursive(&$data, $object_id, $options, &$results)
    {
        if (!is_array($data)) {
            return;
        }

        foreach ($data as $key => &$value) {
            if (is_array($value) && isset($value['type'])) {
                if ('image' === $value['type'] && $options['import_images'] && !empty($value['url'])) {
                    $img_id = $this->download_image(sanitize_url($value['url']), $object_id);
                    if ($img_id) {
                        $value = $img_id;
                        $results['images_imported']++;
                    }
                } elseif ('file' === $value['type'] && !empty($value['url'])) {
                    $file_id = $this->download_file(sanitize_url($value['url']), $object_id);
                    if ($file_id) {
                        $value = $file_id;
                    }
                }
            } elseif (is_array($value)) {
                $this->process_serialized_recursive($value, $object_id, $options, $results);
            }
        }
    }

    /**
     * Import comment
     *
     * @param int   $post_id      Post ID.
     * @param array $comment_data Comment data.
     * @param array $results      Results array (passed by reference).
     */
    private function import_comment($post_id, $comment_data, &$results)
    {
        if (empty($comment_data['content']) || empty($comment_data['author'])) {
            return;
        }

        // Check duplicate
        $existing = get_comments(array(
            'post_id' => $post_id,
            'author_email' => sanitize_email($comment_data['email'] ?? ''),
            'content' => wp_kses_post($comment_data['content']),
            'number' => 1,
        ));

        if (!empty($existing)) {
            $results['comments_imported']++;
            return;
        }

        // Get user
        $user_id = 0;
        if (!empty($comment_data['user_id'])) {
            $user = get_user_by('ID', absint($comment_data['user_id']));
            if ($user) {
                $user_id = $user->ID;
            }
        }
        if (!$user_id && !empty($comment_data['email'])) {
            $user = get_user_by('email', sanitize_email($comment_data['email']));
            if ($user) {
                $user_id = $user->ID;
            }
        }

        $comment_args = array(
            'comment_post_ID' => $post_id,
            'comment_author' => sanitize_text_field($comment_data['author']),
            'comment_author_email' => sanitize_email($comment_data['email'] ?? ''),
            'comment_author_url' => esc_url_raw($comment_data['url'] ?? ''),
            'comment_content' => wp_kses_post($comment_data['content']),
            'comment_date' => sanitize_text_field($comment_data['date'] ?? current_time('mysql')),
            'comment_approved' => absint($comment_data['approved'] ?? 1),
            'comment_type' => sanitize_text_field($comment_data['type'] ?? 'comment'),
            'comment_parent' => absint($comment_data['parent_id'] ?? 0),
            'user_id' => $user_id,
        );

        $comment_id = wp_insert_comment($comment_args);

        if ($comment_id) {
            if (!empty($comment_data['meta'])) {
                foreach ($comment_data['meta'] as $key => $value) {
                    update_comment_meta($comment_id, sanitize_text_field($key), sanitize_text_field($value));
                }
            }
            $results['comments_imported']++;
        }
    }

    /**
     * Download image from URL
     *
     * @param string $url       Image URL.
     * @param int    $parent_id Parent post ID.
     * @return int|false Attachment ID or false on failure.
     */
    private function download_image($url, $parent_id = 0)
    {
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
    private function download_file($url, $parent_id = 0)
    {
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

// Initialize post types import module
new Spinexim_Post_Types_Import();