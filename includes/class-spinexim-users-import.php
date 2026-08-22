<?php
/**
 * Spinda - Users Import Module
 *
 * @package Spinda
 * @since 2.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Users Import Module Class
 */
class Spinexim_Users_Import {

    /**
     * Constructor
     */
    public function __construct() {
        // Handle import submission
        add_action('admin_init', array($this, 'handle_import'));
        
        // Process import batches
        add_action('spinexim_users_import_batch', array($this, 'process_import_batch'));
        
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
        $is_running = get_transient('spinexim_users_import_running');
        $progress = get_transient('spinexim_users_import_progress') ?: 0;
        $message = get_transient('spinexim_users_import_message') ?: '';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Spinda - Users Import', 'spinda-exportimport-data'); ?></h1>
            
            <?php if ($is_running): ?>
                <!-- Progress display when import is running -->
                <div class="notice notice-info spinexim-import-notice">
                    <h3><?php echo esc_html__('Users Import in Progress', 'spinda-exportimport-data'); ?></h3>
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
                <?php $results = get_transient('spinexim_users_import_results'); ?>
                <?php if ($results): ?>
                    <div class="spinexim-card spinexim-import-success">
                        <h2><?php echo esc_html__('Users Import Complete!', 'spinda-exportimport-data'); ?></h2>
                        <table class="widefat">
                            <tbody>
                                <tr>
                                    <td><strong><?php echo esc_html__('Users Processed:', 'spinda-exportimport-data'); ?></strong></td>
                                    <td><?php echo esc_html($results['users_processed'] ?? 0); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo esc_html__('Users Created:', 'spinda-exportimport-data'); ?></strong></td>
                                    <td><?php echo esc_html($results['users_created'] ?? 0); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo esc_html__('Users Updated:', 'spinda-exportimport-data'); ?></strong></td>
                                    <td><?php echo esc_html($results['users_updated'] ?? 0); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo esc_html__('Users Skipped:', 'spinda-exportimport-data'); ?></strong></td>
                                    <td><?php echo esc_html($results['users_skipped'] ?? 0); ?></td>
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
                                    <td><strong><?php echo esc_html__('Passwords Skipped:', 'spinda-exportimport-data'); ?></strong></td>
                                    <td><?php echo esc_html($results['passwords_skipped'] ?? 0); ?></td>
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
                    <?php delete_transient('spinexim_users_import_results'); ?>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- Normal import form -->
                <div class="spinexim-card">
                    <h2><?php echo esc_html__('Import Users Configuration', 'spinda-exportimport-data'); ?></h2>
                    <p><?php echo esc_html__('Upload a JSON export file to import users. The system will import all user data including metadata, ACF fields, and roles.', 'spinda-exportimport-data'); ?></p>
                    
                    <form method="post" enctype="multipart/form-data" id="spinexim-users-import-form">
                        <?php wp_nonce_field('spinexim_users_import_nonce', 'spinexim_users_import_nonce_field'); ?>
                        <input type="hidden" name="spinexim_users_import_action" value="1">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label><?php echo esc_html__('Export File (JSON)', 'spinda-exportimport-data'); ?></label></th>
                                <td>
                                    <input type="file" name="spinexim_import_file" accept=".json" required class="spinexim-file-input">
                                    <p class="spinexim-description"><?php echo esc_html__('Upload the JSON file generated by the Users Export module', 'spinda-exportimport-data'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Update Existing Users', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_overwrite" value="1" checked>
                                        <?php echo esc_html__('Update existing users (matched by email or username)', 'spinda-exportimport-data'); ?>
                                    </label>
                                    <p class="spinexim-description"><?php echo esc_html__('If unchecked, existing users will be skipped', 'spinda-exportimport-data'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Import User Meta', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_import_user_meta" value="1" checked>
                                        <?php echo esc_html__('Import all user metadata (custom fields, ACF data, etc.)', 'spinda-exportimport-data'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Import Roles', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_import_roles" value="1" checked>
                                        <?php echo esc_html__('Assign user roles from export file', 'spinda-exportimport-data'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Download Images', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_import_images" value="1" checked>
                                        <?php echo esc_html__('Download and import images from URLs (profile pictures, ACF images, etc.)', 'spinda-exportimport-data'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Send Welcome Email', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_send_email" value="1">
                                        <?php echo esc_html__('Send email notification to newly created users', 'spinda-exportimport-data'); ?>
                                    </label>
                                    <p class="spinexim-description"><?php echo esc_html__('Recommended to leave unchecked for bulk imports', 'spinda-exportimport-data'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Password Handling', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_skip_passwords" value="1" checked>
                                        <?php echo esc_html__('Skip password import (generate random passwords for new users)', 'spinda-exportimport-data'); ?>
                                    </label>
                                    <p class="spinexim-description"><?php echo esc_html__('For security, passwords cannot be directly imported. New users will get random passwords.', 'spinda-exportimport-data'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <p>
                            <button type="submit" class="button button-primary button-hero" id="spinexim-users-import-button">
                                <span class="dashicons dashicons-upload"></span>
                                <?php echo esc_html__('Start Users Import', 'spinda-exportimport-data'); ?>
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
                    admin_url('admin.php?page=spinexim-users-import&spinexim_reset_users_import=1'),
                    'spinexim_reset_users_import',
                    'spinexim_reset_nonce'
                );
                ?>
                <a href="<?php echo esc_url($reset_url); ?>" class="button button-secondary" id="spinexim-reset-users-import">
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
        if (!isset($_GET['spinexim_reset_users_import']) || !current_user_can('manage_options')) {
            return;
        }

        // Verify nonce
        $nonce = isset($_GET['spinexim_reset_nonce']) ? sanitize_text_field(wp_unslash($_GET['spinexim_reset_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'spinexim_reset_users_import')) {
            wp_die(esc_html__('Security check failed.', 'spinda-exportimport-data'));
        }

        global $wpdb;

        // Delete all import transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '%_transient_spinexim_users_import_%',
                '%_transient_timeout_spinexim_users_import_%'
            )
        );

        // Clear cron
        wp_clear_scheduled_hook('spinexim_users_import_batch');

        // Redirect
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page' => 'spinexim-users-import',
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
        if (!isset($_POST['spinexim_users_import_action'])) {
            return;
        }

        // Verify nonce
        if (!isset($_POST['spinexim_users_import_nonce_field']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spinexim_users_import_nonce_field'])), 'spinexim_users_import_nonce')) {
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
        if (get_transient('spinexim_users_import_running')) {
            $reset_url = wp_nonce_url(
                admin_url('admin.php?page=spinexim-users-import&spinexim_reset_users_import=1'),
                'spinexim_reset_users_import',
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
        if (empty($import_data['users']) || !is_array($import_data['users'])) {
            wp_die(esc_html__('Invalid or missing users data in export file.', 'spinda-exportimport-data'));
        }

        // Set options
        $options = array(
            'overwrite' => !empty($_POST['spinexim_overwrite']),
            'import_user_meta' => !empty($_POST['spinexim_import_user_meta']),
            'import_roles' => !empty($_POST['spinexim_import_roles']),
            'import_images' => !empty($_POST['spinexim_import_images']),
            'send_email' => !empty($_POST['spinexim_send_email']),
            'skip_passwords' => !empty($_POST['spinexim_skip_passwords']),
        );

        // Clear any old cron jobs
        wp_clear_scheduled_hook('spinexim_users_import_batch');

        // Store data in transients for batch processing
        set_transient('spinexim_users_import_options', $options, 3600);
        set_transient('spinexim_users_import_data', $import_data['users'], 3600);
        set_transient('spinexim_users_import_total', count($import_data['users']), 3600);
        set_transient('spinexim_users_import_current', 0, 3600);
        set_transient('spinexim_users_import_results', array(
            'users_processed' => 0,
            'users_created' => 0,
            'users_updated' => 0,
            'users_skipped' => 0,
            'meta_processed' => 0,
            'images_imported' => 0,
            'passwords_skipped' => 0,
            'errors' => array(),
        ), 3600);
        set_transient('spinexim_users_import_running', true, 3600);
        set_transient('spinexim_users_import_progress', 0, 3600);
        set_transient('spinexim_users_import_message', esc_html__('Starting users import...', 'spinda-exportimport-data'), 3600);

        // Clear memory
        unset($import_data, $file_content);

        // Schedule first batch
        wp_schedule_single_event(time() + 2, 'spinexim_users_import_batch');

        // Redirect to progress page
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page' => 'spinexim-users-import',
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
        if (!get_transient('spinexim_users_import_running')) {
            return;
        }

        // Increase limits
        wp_raise_memory_limit('admin');
        @set_time_limit(120);
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', 120);

        $options = get_transient('spinexim_users_import_options') ?: array();
        $all_users = get_transient('spinexim_users_import_data') ?: array();
        $current = intval(get_transient('spinexim_users_import_current') ?: 0);
        $total = intval(get_transient('spinexim_users_import_total') ?: 0);
        $results = get_transient('spinexim_users_import_results') ?: array();

        // Process batch of users (5 per batch)
        $batch_size = 5;
        $batch_end = min($current + $batch_size, $total);

        for ($i = $current; $i < $batch_end; $i++) {
            if (isset($all_users[$i])) {
                $this->import_single_user($all_users[$i], $options, $results);
                unset($all_users[$i]);
            }
        }

        $current = $batch_end;
        $progress = ($total > 0) ? round(($current / $total) * 100) : 100;

        // Update transients
        set_transient('spinexim_users_import_current', $current, 3600);
        set_transient('spinexim_users_import_results', $results, 3600);
        set_transient('spinexim_users_import_progress', $progress, 3600);
        set_transient(
            'spinexim_users_import_message',
            sprintf(
                /* translators: 1: Current users processed, 2: Total users */
                esc_html__('Processed %1$d of %2$d users', 'spinda-exportimport-data'),
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
            wp_schedule_single_event(time() + 3, 'spinexim_users_import_batch');
        } else {
            // Import complete
            delete_transient('spinexim_users_import_running');
            delete_transient('spinexim_users_import_data');
            delete_transient('spinexim_users_import_current');
            delete_transient('spinexim_users_import_total');
            delete_transient('spinexim_users_import_progress');
            delete_transient('spinexim_users_import_message');

            set_transient('spinexim_users_import_results', $results, 300);

            // Redirect to results
            wp_safe_redirect(
                add_query_arg(
                    array(
                        'page' => 'spinexim-users-import',
                        'import_done' => 1,
                    ),
                    admin_url('admin.php')
                )
            );
            exit;
        }
    }

    /**
     * Import single user
     *
     * @param array $user_data User data.
     * @param array $options   Import options.
     * @param array $results   Results array (passed by reference).
     */
    private function import_single_user($user_data, $options, &$results) {
        try {
            $existing_user = null;

            // Find existing user by email
            if (!empty($user_data['user_email'])) {
                $existing_user = get_user_by('email', sanitize_email($user_data['user_email']));
            }

            // Find by login if not found by email
            if (!$existing_user && !empty($user_data['user_login'])) {
                $existing_user = get_user_by('login', sanitize_user($user_data['user_login']));
            }

            // Find by ID if not found
            if (!$existing_user && !empty($user_data['ID'])) {
                $existing_user = get_user_by('ID', absint($user_data['ID']));
            }

            // Skip if exists and not overwriting
            if ($existing_user && !$options['overwrite']) {
                $results['users_skipped']++;
                return;
            }

            // Prepare user data
            $user_args = array(
                'user_login' => sanitize_user($user_data['user_login'] ?? ''),
                'user_email' => sanitize_email($user_data['user_email'] ?? ''),
                'user_nicename' => sanitize_title($user_data['user_nicename'] ?? ''),
                'first_name' => $user_data['first_name'] ?? '',
                'last_name' => $user_data['last_name'] ?? '',
            );

            // Display name
            if (!empty($user_data['display_name'])) {
                $user_args['display_name'] = $user_data['display_name'];
            }

            // Nickname
            if (!empty($user_data['nickname'])) {
                $user_args['nickname'] = $user_data['nickname'];
            }

            // Description/Bio
            if (!empty($user_data['description'])) {
                $user_args['description'] = wp_kses_post($user_data['description']);
            }

            // Website URL
            if (!empty($user_data['user_url'])) {
                $user_args['user_url'] = esc_url_raw($user_data['user_url']);
            }

            // Registration date
            if (!empty($user_data['user_registered'])) {
                $user_args['user_registered'] = sanitize_text_field($user_data['user_registered']);
            }

            // Role
            if (!empty($user_data['roles']) && $options['import_roles']) {
                $roles = (array) $user_data['roles'];
                // Get first valid role
                $valid_roles = array_keys(wp_roles()->roles);
                $user_role = 'subscriber'; // Default fallback
                foreach ($roles as $role) {
                    if (in_array($role, $valid_roles, true)) {
                        $user_role = $role;
                        break;
                    }
                }
                $user_args['role'] = $user_role;
            }

            if ($existing_user) {
                // Update existing user
                $user_args['ID'] = $existing_user->ID;
                $user_id = wp_update_user($user_args);
                if (is_wp_error($user_id)) {
                    throw new Exception(esc_html($user_id->get_error_message()));
                }
                $results['users_updated']++;
            } else {
                // Create new user with random password
                $user_args['user_pass'] = wp_generate_password(12, true);
                $user_id = wp_insert_user($user_args);
                if (is_wp_error($user_id)) {
                    throw new Exception(esc_html($user_id->get_error_message()));
                }
                $results['users_created']++;
                $results['passwords_skipped']++;

                // Send email notification
                if ($options['send_email']) {
                    wp_new_user_notification($user_id, null, 'user');
                }
            }

            $results['users_processed']++;

            // Assign multiple roles
            if (!empty($user_data['roles']) && $options['import_roles']) {
                $user = get_user_by('ID', $user_id);
                if ($user) {
                    // Remove default role
                    $user->set_role('');
                    // Add all roles from export
                    foreach ((array) $user_data['roles'] as $role) {
                        $user->add_role(sanitize_text_field($role));
                    }
                }
            }

            // Import user meta
            if (!empty($user_data['meta']) && $options['import_user_meta']) {
                foreach ($user_data['meta'] as $meta_key => $meta_value) {
                    $this->import_meta_value($user_id, $meta_key, $meta_value, $options, $results);
                }
            }

        } catch (Exception $e) {
            $results['errors'][] = sprintf(
                /* translators: 1: User email/login, 2: Error message */
                esc_html__("Error with user '%1\$s': %2\$s", 'spinda-exportimport-data'),
                $user_data['user_email'] ?? $user_data['user_login'] ?? 'Unknown',
                $e->getMessage()
            );
        }
    }

    /**
     * Import meta value for user
     *
     * @param int    $user_id    User ID.
     * @param string $meta_key   Meta key.
     * @param mixed  $meta_value Meta value.
     * @param array  $options    Import options.
     * @param array  $results    Results array (passed by reference).
     */
    private function import_meta_value($user_id, $meta_key, $meta_value, $options, &$results) {
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
                            update_user_meta($user_id, $sanitized_key, $gallery_ids);
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
                    update_user_meta($user_id, $sanitized_key, $link);
                    $results['meta_processed']++;
                    break;

                case 'array':
                    $array_value = $meta_value['value'];
                    update_user_meta($user_id, $sanitized_key, $array_value);
                    $results['meta_processed']++;
                    break;

                case 'image':
                    if ($options['import_images'] && !empty($meta_value['url'])) {
                        $img_id = $this->download_image(sanitize_url($meta_value['url']), 0);
                        if ($img_id) {
                            update_user_meta($user_id, $sanitized_key, $img_id);
                            $results['images_imported']++;
                            $results['meta_processed']++;
                        }
                    } elseif (!empty($meta_value['id'])) {
                        update_user_meta($user_id, $sanitized_key, intval($meta_value['id']));
                        $results['meta_processed']++;
                    }
                    break;

                case 'file':
                    if ($options['import_images'] && !empty($meta_value['url'])) {
                        $file_id = $this->download_file(sanitize_url($meta_value['url']), 0);
                        if ($file_id) {
                            update_user_meta($user_id, $sanitized_key, $file_id);
                            $results['meta_processed']++;
                        }
                    } elseif (!empty($meta_value['id'])) {
                        update_user_meta($user_id, $sanitized_key, intval($meta_value['id']));
                        $results['meta_processed']++;
                    }
                    break;

                case 'serialized':
                    $data = $meta_value['value'];
                    $this->process_serialized_recursive($data, 0, $options, $results);
                    update_user_meta($user_id, $sanitized_key, $data);
                    $results['meta_processed']++;
                    break;

                default:
                    $value = $meta_value['value'] ?? $meta_value;
                    update_user_meta($user_id, $sanitized_key, $value);
                    $results['meta_processed']++;
            }
        } else {
            $value = is_array($meta_value) ? $meta_value : $meta_value;
            update_user_meta($user_id, $sanitized_key, $value);
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

// Initialize users import module
new Spinexim_Users_Import();