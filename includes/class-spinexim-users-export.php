<?php
/**
 * Spinda - Users Export Module
 *
 * @package Spinda
 * @since 2.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Users Export Module Class
 */
class Spinexim_Users_Export {

    /**
     * Constructor
     */
    public function __construct() {
        // Handle AJAX request for roles
        add_action('wp_ajax_spinexim_get_roles_for_export', array($this, 'ajax_get_roles'));
        
        // Handle export submission
        add_action('admin_init', array($this, 'handle_export'));
    }

    /**
     * Render export page
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'spinda-exportimport-data'));
        }

        // Get all roles
        $all_roles = wp_roles()->roles;
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Spinda - Users Export', 'spinda-exportimport-data'); ?></h1>
            
            <div class="spinexim-card">
                <h2><?php echo esc_html__('Export Users Configuration', 'spinda-exportimport-data'); ?></h2>
                <p><?php echo esc_html__('Select user roles and options to export. The system will generate a JSON file with all user data and metadata.', 'spinda-exportimport-data'); ?></p>
                
                <form method="post" id="spinexim-users-export-form">
                    <?php wp_nonce_field('spinexim_users_export_nonce', 'spinexim_users_export_nonce_field'); ?>
                    <input type="hidden" name="spinexim_users_export_action" value="1">
                    
                    <!-- Step 1: User Roles -->
                    <div class="spinexim-form-section">
                        <h3><?php echo esc_html__('Step 1: Select User Roles', 'spinda-exportimport-data'); ?></h3>
                        <div id="spinexim-roles-checkboxes">
                            <label class="spinexim-tax-select-all">
                                <input type="checkbox" id="spinexim-select-all-roles" checked>
                                <strong><?php echo esc_html__('Select All', 'spinda-exportimport-data'); ?></strong>
                            </label><br><br>
                            <?php foreach ($all_roles as $role_name => $role_data): 
                                $user_count = count_users();
                                $count = isset($user_count['avail_roles'][$role_name]) ? $user_count['avail_roles'][$role_name] : 0;
                            ?>
                                <label class="spinexim-tax-label">
                                    <input type="checkbox" name="spinexim_export_roles[]" 
                                        value="<?php echo esc_attr($role_name); ?>" checked 
                                        class="spinexim-role-checkbox">
                                    <strong><?php echo esc_html($role_data['name']); ?></strong>
                                    <span class="spinexim-tax-count">(<?php echo intval($count); ?> <?php echo esc_html__('users', 'spinda-exportimport-data'); ?>)</span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="spinexim-description"><?php echo esc_html__('Choose which user roles to export', 'spinda-exportimport-data'); ?></p>
                    </div>
                    
                    <!-- Step 2: Options -->
                    <div class="spinexim-form-section">
                        <h3><?php echo esc_html__('Step 2: Export Options', 'spinda-exportimport-data'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php echo esc_html__('Include User Meta', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_export_user_meta" value="1" checked>
                                        <?php echo esc_html__('Export all user metadata (custom fields, ACF data, preferences, etc.)', 'spinda-exportimport-data'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Include User Capabilities', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_export_capabilities" value="1" checked>
                                        <?php echo esc_html__('Export user capabilities and roles', 'spinda-exportimport-data'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Include Registration Date', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_export_dates" value="1" checked>
                                        <?php echo esc_html__('Export user registration date', 'spinda-exportimport-data'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Include User Bio', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_export_bio" value="1" checked>
                                        <?php echo esc_html__('Export user biographical info and description', 'spinda-exportimport-data'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Include Display Name', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_export_display_name" value="1" checked>
                                        <?php echo esc_html__('Export display name and nickname', 'spinda-exportimport-data'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Include User URL', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_export_url" value="1" checked>
                                        <?php echo esc_html__('Export user website URL', 'spinda-exportimport-data'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Submit -->
                    <p>
                        <button type="submit" class="button button-primary button-hero" id="spinexim-users-export-button">
                            <span class="dashicons dashicons-download"></span>
                            <?php echo esc_html__('Download Users Export File (JSON)', 'spinda-exportimport-data'); ?>
                        </button>
                    </p>
                </form>
            </div>
            
            <!-- Export History -->
            <div class="spinexim-card">
                <h2><?php echo esc_html__('Users Export History', 'spinda-exportimport-data'); ?></h2>
                <?php
                $history = get_option('spinexim_users_export_history', array());
                if (empty($history)) {
                    echo '<p>' . esc_html__('No user exports yet.', 'spinda-exportimport-data') . '</p>';
                } else {
                    ?>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Date', 'spinda-exportimport-data'); ?></th>
                                <th><?php echo esc_html__('Roles', 'spinda-exportimport-data'); ?></th>
                                <th><?php echo esc_html__('Total Users', 'spinda-exportimport-data'); ?></th>
                                <th><?php echo esc_html__('File', 'spinda-exportimport-data'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_reverse($history) as $export): ?>
                                <tr>
                                    <td><?php echo esc_html($export['date']); ?></td>
                                    <td><?php echo esc_html($export['roles']); ?></td>
                                    <td><?php echo esc_html($export['total_users']); ?></td>
                                    <td><?php echo esc_html($export['filename']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler to get roles (for future use)
     */
    public function ajax_get_roles() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'spinexim_user_export_ajax')) {
            wp_send_json_error(array('message' => esc_html__('Security check failed.', 'spinda-exportimport-data')));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Unauthorized access.', 'spinda-exportimport-data')));
        }

        $all_roles = wp_roles()->roles;
        $result = array();

        foreach ($all_roles as $role_name => $role_data) {
            $user_count = count_users();
            $count = isset($user_count['avail_roles'][$role_name]) ? $user_count['avail_roles'][$role_name] : 0;
            
            $result[] = array(
                'name' => $role_name,
                'label' => $role_data['name'],
                'count' => $count,
            );
        }

        wp_send_json_success($result);
    }

    /**
     * Handle export submission
     */
    public function handle_export() {
        // Check if export action is set
        if (!isset($_POST['spinexim_users_export_action'])) {
            return;
        }

        // Verify nonce
        if (!isset($_POST['spinexim_users_export_nonce_field']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spinexim_users_export_nonce_field'])), 'spinexim_users_export_nonce')) {
            wp_die(esc_html__('Security check failed. Please refresh the page and try again.', 'spinda-exportimport-data'));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'spinda-exportimport-data'));
        }

        // Increase limits for large exports
        wp_raise_memory_limit('admin');
        @set_time_limit(0);
        @ini_set('memory_limit', '2048M');
        @ini_set('max_execution_time', 0);
        ignore_user_abort(true);

        // Get selected roles with proper sanitization
        $selected_roles = isset($_POST['spinexim_export_roles']) ? 
            array_map('sanitize_text_field', wp_unslash($_POST['spinexim_export_roles'])) : array();
        
        if (empty($selected_roles)) {
            wp_die(esc_html__('Please select at least one role to export.', 'spinda-exportimport-data'));
        }

        $options = array(
            'export_user_meta' => !empty($_POST['spinexim_export_user_meta']),
            'export_capabilities' => !empty($_POST['spinexim_export_capabilities']),
            'export_dates' => !empty($_POST['spinexim_export_dates']),
            'export_bio' => !empty($_POST['spinexim_export_bio']),
            'export_display_name' => !empty($_POST['spinexim_export_display_name']),
            'export_url' => !empty($_POST['spinexim_export_url']),
        );

        // Generate export data
        $data = $this->generate_export($selected_roles, $options);

        // Save to export history
        $history = get_option('spinexim_users_export_history', array());
        $history[] = array(
            'date' => current_time('mysql'),
            'roles' => implode(', ', $selected_roles),
            'total_users' => $data['stats']['total_users'],
            'filename' => 'spinexim-users-export-' . gmdate('Y-m-d-H-i-s') . '.json',
        );
        // Keep last 20 exports in history
        update_option('spinexim_users_export_history', array_slice($history, -20));

        // Generate filename
        $filename = 'spinexim-users-export-' . gmdate('Y-m-d-H-i-s') . '.json';
        
        // Convert to JSON
        $json_data = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Clean output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Send download headers
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        header('Content-Length: ' . strlen($json_data));
        header('X-Robots-Tag: noindex, nofollow');
        header('X-Content-Type-Options: nosniff');

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $json_data;
        exit;
    }

    /**
     * Generate export data
     *
     * @param array $selected_roles Selected roles.
     * @param array $options        Export options.
     * @return array
     */
    private function generate_export($selected_roles, $options) {
        $data = array(
            'version' => SPINEXIM_VERSION,
            'export_date' => current_time('mysql'),
            'export_type' => 'users',
            'site_url' => esc_url(home_url()),
            'site_name' => get_bloginfo('name'),
            'users' => array(),
            'stats' => array(
                'total_users' => 0,
                'total_meta_fields' => 0,
            ),
        );

        // Get users by roles
        $users = get_users(array(
            'role__in' => $selected_roles,
            'orderby' => 'ID',
            'order' => 'ASC',
        ));

        foreach ($users as $user) {
            $user_data = array(
                'ID' => $user->ID,
                'user_login' => $user->user_login,
                'user_email' => $user->user_email,
                'user_nicename' => $user->user_nicename,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
            );

            // Display name and nickname
            if ($options['export_display_name']) {
                $user_data['display_name'] = $user->display_name;
                $user_data['nickname'] = $user->nickname;
            }

            // Bio and description
            if ($options['export_bio']) {
                $user_data['description'] = $user->description;
            }

            // Website URL
            if ($options['export_url']) {
                $user_data['user_url'] = $user->user_url;
            }

            // Registration date
            if ($options['export_dates']) {
                $user_data['user_registered'] = $user->user_registered;
            }

            // Roles and capabilities
            if ($options['export_capabilities']) {
                $user_data['roles'] = $user->roles;
                $user_data['allcaps'] = $user->allcaps;
            }

            // User Meta
            if ($options['export_user_meta']) {
                $user_meta = $this->export_user_meta($user->ID);
                if (!empty($user_meta)) {
                    $user_data['meta'] = $user_meta;
                    $data['stats']['total_meta_fields'] += count($user_meta);
                }
            }

            $data['users'][] = $user_data;
        }

        $data['stats']['total_users'] = count($users);

        return $data;
    }

    /**
     * Export user meta data
     *
     * @param int $user_id User ID.
     * @return array
     */
    private function export_user_meta($user_id) {
        $all_meta = get_user_meta($user_id);
        $export_meta = array();

        $skip_meta = array(
            'session_tokens',
            'wp_user_level',
            'wp_capabilities',
            'wp_dashboard_quick_press_last_post_id',
            'managenav-menuscolumnshidden',
            'metaboxhidden_nav-menus',
            'nav_menu_recently_edited',
            'wp_user-settings',
            'wp_user-settings-time',
            'closedpostboxes_',
            'metaboxhidden_',
            'meta-box-order_',
            'screen_layout_',
            'dismissed_wp_pointers',
            'show_welcome_panel',
            'admin_color',
            'use_ssl',
            'rich_editing',
            'syntax_highlighting',
            'comment_shortcuts',
            'admin_email',
            'default_password_nag',
            'community-events-location',
            'wp_persisted_preferences',
        );

        foreach ($all_meta as $meta_key => $meta_values) {
            // Skip internal meta
            $skip = false;
            foreach ($skip_meta as $skip_pattern) {
                if (strpos($meta_key, $skip_pattern) === 0) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            $value = count($meta_values) === 1 ? $meta_values[0] : $meta_values;
            $processed = $this->process_meta_for_export($value);

            if (null !== $processed) {
                $export_meta[$meta_key] = $processed;
            }
        }

        return $export_meta;
    }

    /**
     * Process meta values for export
     *
     * @param mixed $value Meta value.
     * @return mixed
     */
    private function process_meta_for_export($value) {
        // Handle attachment IDs (images)
        if (is_numeric($value) && wp_attachment_is_image($value)) {
            $url = wp_get_attachment_url($value);
            if ($url) {
                return array(
                    'type' => 'image',
                    'id' => intval($value),
                    'url' => $url,
                );
            }
        }

        // Handle attachment IDs (files)
        if (is_numeric($value) && 'attachment' === get_post_type($value)) {
            $url = wp_get_attachment_url($value);
            if ($url) {
                return array(
                    'type' => 'file',
                    'id' => intval($value),
                    'url' => $url,
                );
            }
        }

        // Handle arrays
        if (is_array($value) && !empty($value)) {
            $is_simple = true;
            foreach ($value as $v) {
                if (!is_scalar($v)) {
                    $is_simple = false;
                    break;
                }
            }
            if ($is_simple) {
                return array(
                    'type' => 'array',
                    'value' => $value,
                );
            }
        }

        // Handle serialized data
        if (is_string($value) && $this->is_serialized($value)) {
            $unserialized = @unserialize($value);
            if (false === $unserialized) {
                return $value;
            }

            if (is_array($unserialized)) {
                // Check for ACF Gallery
                if ($this->is_acf_gallery($unserialized)) {
                    $gallery = array();
                    foreach ($unserialized as $img_id) {
                        $img_url = wp_get_attachment_url($img_id);
                        if ($img_url) {
                            $gallery[] = array(
                                'type' => 'image',
                                'id' => intval($img_id),
                                'url' => $img_url,
                            );
                        }
                    }
                    return array(
                        'type' => 'acf_gallery',
                        'value' => $gallery,
                    );
                }

                // Check for ACF Link
                if (isset($unserialized['title'], $unserialized['url']) && count($unserialized) <= 4) {
                    return array(
                        'type' => 'acf_link',
                        'value' => $unserialized,
                    );
                }

                // Check for simple array
                $is_simple = true;
                foreach ($unserialized as $item) {
                    if (!is_scalar($item)) {
                        $is_simple = false;
                        break;
                    }
                }
                if ($is_simple) {
                    return array(
                        'type' => 'array',
                        'value' => $unserialized,
                    );
                }

                // Deep process
                return array(
                    'type' => 'serialized',
                    'value' => $this->process_serialized_deep($unserialized),
                );
            }
        }

        return $value;
    }

    /**
     * Check if string is serialized
     *
     * @param string $data Data to check.
     * @return bool
     */
    private function is_serialized($data) {
        if (!is_string($data)) {
            return false;
        }
        // $data = trim($data);
        $data = $data;
        
        if ('N;' === $data) {
            return true;
        }
        if (strlen($data) < 4) {
            return false;
        }
        if (':' !== $data[1]) {
            return false;
        }
        $last_char = substr($data, -1);
        if (';' !== $last_char && '}' !== $last_char) {
            return false;
        }
        $token = $data[0];
        switch ($token) {
            case 's':
                return '"' === substr($data, -2, 1);
            case 'a':
            case 'O':
                return (bool) preg_match("/^{$token}:[0-9]+:/s", $data);
            case 'b':
            case 'i':
            case 'd':
                return (bool) preg_match("/^{$token}:[0-9.E+-]+;$/", $data);
        }
        return false;
    }

    /**
     * Check if array is ACF Gallery
     *
     * @param array $array Array to check.
     * @return bool
     */
    private function is_acf_gallery($array) {
        if (empty($array)) {
            return false;
        }
        foreach ($array as $item) {
            if (!is_numeric($item) || !wp_attachment_is_image($item)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Deep process serialized data
     *
     * @param mixed $data Data to process.
     * @return mixed
     */
    private function process_serialized_deep($data) {
        if (is_array($data)) {
            foreach ($data as $key => &$value) {
                if (is_array($value)) {
                    $value = $this->process_serialized_deep($value);
                } elseif (is_numeric($value) && wp_attachment_is_image($value)) {
                    $url = wp_get_attachment_url($value);
                    if ($url) {
                        $value = array(
                            'type' => 'image',
                            'id' => intval($value),
                            'url' => $url,
                        );
                    }
                } elseif (is_numeric($value) && 'attachment' === get_post_type($value)) {
                    $url = wp_get_attachment_url($value);
                    if ($url) {
                        $value = array(
                            'type' => 'file',
                            'id' => intval($value),
                            'url' => $url,
                        );
                    }
                }
            }
        }
        return $data;
    }
}

// Initialize users export module
new Spinexim_Users_Export();