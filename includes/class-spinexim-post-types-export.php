<?php
/**
 * Spinda - Post Types Export Module
 *
 * @package Spinda
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Post Types Export Module Class
 */
class Spinexim_Post_Types_Export {

    /**
     * Constructor
     */
    public function __construct() {
        // Handle AJAX request for taxonomies
        add_action('wp_ajax_spinexim_get_taxonomies', array($this, 'ajax_get_taxonomies'));
        
        // Handle export submission
        add_action('admin_init', array($this, 'spinexim_post_types_handle_export'));
    }

    /**
     * Render export page
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.','spinda-exportimport-data'));
        }

        // $post_types = get_post_types(array('public' => true), 'objects');
        $excluded_post_types = array('attachment', 'product');
        $all_post_types = get_post_types(array('public' => true), 'objects');
        $post_types = array();

        foreach ($all_post_types as $name => $pt) {
            if (!in_array($name, $excluded_post_types, true)) {
                $post_types[$name] = $pt;
            }
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Spinda - Post Types Export','spinda-exportimport-data'); ?></h1>
            
            <div class="spinexim-card">
                <h2><?php echo esc_html__('Export Configuration','spinda-exportimport-data'); ?></h2>
                <p><?php echo esc_html__('Select post type and taxonomies to export. The system will generate a JSON file.','spinda-exportimport-data'); ?></p>
                
                <form method="post" id="spinexim-post-export-form">
                    <?php wp_nonce_field('spinexim_post_export_nonce', 'spinexim_post_export_nonce_field'); ?>
                    <input type="hidden" name="spinexim_post_export_action" value="1">
                    
                    <!-- Step 1: Post Type -->
                    <div class="spinexim-form-section">
                        <h3><?php echo esc_html__('Step 1: Select Post Type','spinda-exportimport-data'); ?></h3>
                        <select name="spinexim_export_post_type" id="spinexim-export-post-type" class="widefat" style="max-width: 400px;">
                            <option value=""><?php echo esc_html__('— Select Post Type —','spinda-exportimport-data'); ?></option>
                            <?php 
                            foreach ($post_types as $pt): 
                                $count = wp_count_posts($pt->name);
                                $total = isset($count->publish) ? $count->publish : 0;
                                $total += isset($count->draft) ? $count->draft : 0;
                                $total += isset($count->pending) ? $count->pending : 0;
                                $total += isset($count->private) ? $count->private : 0;
                                $total += isset($count->future) ? $count->future : 0;
                            ?>
                                <option value="<?php echo esc_attr($pt->name); ?>">
                                    <?php 
                                    printf(
                                        /* translators: 1: Post type label, 2: Post type name, 3: Total items */
                                        esc_html__('%1$s (%2$s) - %3$d items','spinda-exportimport-data'),
                                        esc_html($pt->labels->name),
                                        esc_html($pt->name),
                                        intval($total)
                                    ); 
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="spinexim-description"><?php echo esc_html__('Choose which type of content to export','spinda-exportimport-data'); ?></p>
                    </div>
                    
                    <!-- Step 2: Taxonomies -->
                    <div class="spinexim-form-section" id="spinexim-taxonomies-section" style="display: none;">
                        <h3><?php echo esc_html__('Step 2: Select Taxonomies','spinda-exportimport-data'); ?></h3>
                        <div id="spinexim-taxonomy-checkboxes">
                            <p class="spinexim-loading"><?php echo esc_html__('Loading taxonomies...','spinda-exportimport-data'); ?></p>
                        </div>
                        <p class="spinexim-description"><?php echo esc_html__('Select which taxonomies (categories, tags, etc.) to include in export','spinda-exportimport-data'); ?></p>
                    </div>
                    
                    <!-- Step 3: Options -->
                    <div class="spinexim-form-section" id="spinexim-export-options-section" style="display: none;">
                        <h3><?php echo esc_html__('Step 3: Export Options','spinda-exportimport-data'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php echo esc_html__('Include Comments','spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_export_comments" value="1" checked>
                                        <?php echo esc_html__('Export all comments/reviews','spinda-exportimport-data'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Include Meta Fields','spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_export_meta" value="1" checked>
                                        <?php echo esc_html__('Export custom fields and ACF data','spinda-exportimport-data'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Include Featured Images','spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_export_featured_image" value="1" checked>
                                        <?php echo esc_html__('Include featured image URLs','spinda-exportimport-data'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Include Author Info','spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_export_author" value="1" checked>
                                        <?php echo esc_html__('Include author details','spinda-exportimport-data'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Include Dates','spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_export_dates" value="1" checked>
                                        <?php echo esc_html__('Include creation and modification dates','spinda-exportimport-data'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Post Status','spinda-exportimport-data'); ?></th>
                                <td>
                                    <label><input type="checkbox" name="spinexim_post_status[]" value="publish" checked> <?php echo esc_html__('Published','spinda-exportimport-data'); ?></label><br>
                                    <label><input type="checkbox" name="spinexim_post_status[]" value="draft"> <?php echo esc_html__('Draft','spinda-exportimport-data'); ?></label><br>
                                    <label><input type="checkbox" name="spinexim_post_status[]" value="pending"> <?php echo esc_html__('Pending','spinda-exportimport-data'); ?></label><br>
                                    <label><input type="checkbox" name="spinexim_post_status[]" value="private"> <?php echo esc_html__('Private','spinda-exportimport-data'); ?></label><br>
                                    <label><input type="checkbox" name="spinexim_post_status[]" value="future"> <?php echo esc_html__('Future','spinda-exportimport-data'); ?></label>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Submit -->
                    <p id="spinexim-export-submit-section" style="display: none;">
                        <button type="submit" class="button button-primary button-hero" id="spinexim-export-button">
                            <span class="dashicons dashicons-download"></span>
                            <?php echo esc_html__('Download Export File (JSON)','spinda-exportimport-data'); ?>
                        </button>
                    </p>
                </form>
            </div>
            
            <!-- Export History -->
            <div class="spinexim-card">
                <h2><?php echo esc_html__('Export History','spinda-exportimport-data'); ?></h2>
                <?php
                $history = get_option('spinexim_export_history', array());
                if (empty($history)) {
                    echo '<p>' . esc_html__('No exports yet.','spinda-exportimport-data') . '</p>';
                } else {
                    ?>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Date','spinda-exportimport-data'); ?></th>
                                <th><?php echo esc_html__('Post Type','spinda-exportimport-data'); ?></th>
                                <th><?php echo esc_html__('Items','spinda-exportimport-data'); ?></th>
                                <th><?php echo esc_html__('File','spinda-exportimport-data'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_reverse($history) as $export): ?>
                                <tr>
                                    <td><?php echo esc_html($export['date']); ?></td>
                                    <td><?php echo esc_html($export['post_type']); ?></td>
                                    <td><?php echo esc_html($export['items']); ?></td>
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
     * AJAX handler to get taxonomies
     */
    public function ajax_get_taxonomies() {
        // Verify nonce
        // if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'spinexim_post_export_ajax')) {
        //     wp_send_json_error(array('message' => esc_html__('Security check failed.','spinda-exportimport-data')));
        // }
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'spinexim_post_export_ajax')) {
            wp_send_json_error(array('message' => esc_html__('Security check failed.', 'spinda-exportimport-data')));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Unauthorized access.','spinda-exportimport-data')));
        }

        // Get and sanitize post type
        $post_type = isset($_POST['post_type']) ? sanitize_text_field(wp_unslash($_POST['post_type'])) : '';
        
        if (!post_type_exists($post_type)) {
            wp_send_json_error(array('message' => esc_html__('Invalid post type.','spinda-exportimport-data')));
        }

        $taxonomies = get_object_taxonomies($post_type, 'objects');
        $result = array();

        foreach ($taxonomies as $tax_name => $tax_obj) {
            // Skip WordPress internal taxonomies
            if (in_array($tax_name, array('post_format'), true)) {
                continue;
            }

            $terms = get_terms(array(
                'taxonomy' => $tax_name,
                'hide_empty' => false,
            ));

            $result[] = array(
                'name' => $tax_name,
                'label' => $tax_obj->labels->name,
                'count' => is_wp_error($terms) ? 0 : count($terms),
                'hierarchical' => $tax_obj->hierarchical,
            );
        }

        wp_send_json_success($result);
    }

    /**
     * Handle export submission
     */
    public function spinexim_post_types_handle_export() {
        // Check if export action is set
        if (!isset($_POST['spinexim_post_export_action'])) {
            return;
        }

        // Verify nonce
        if (!isset($_POST['spinexim_post_export_nonce_field']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spinexim_post_export_nonce_field'])), 'spinexim_post_export_nonce')) {
            wp_die(esc_html__('Security check failed. Please refresh the page and try again.','spinda-exportimport-data'));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.','spinda-exportimport-data'));
        }

        // Validate post type
        $post_type = isset($_POST['spinexim_export_post_type']) ? sanitize_text_field(wp_unslash($_POST['spinexim_export_post_type'])) : '';
        if (!post_type_exists($post_type)) {
            wp_die(esc_html__('Invalid post type selected.','spinda-exportimport-data'));
        }

        // Increase limits for large exports
        wp_raise_memory_limit('admin');
        @set_time_limit(0);
        @ini_set('memory_limit', '2048M');
        @ini_set('max_execution_time', 0);
        ignore_user_abort(true);

        // Get selected options with proper sanitization
        $selected_taxonomies = isset($_POST['spinexim_export_taxonomies']) ? 
            array_map('sanitize_text_field', wp_unslash($_POST['spinexim_export_taxonomies'])) : array();
        
        $post_statuses = isset($_POST['spinexim_post_status']) ? 
            array_map('sanitize_text_field', wp_unslash($_POST['spinexim_post_status'])) : array('publish');

        $options = array(
            'export_comments' => !empty($_POST['spinexim_export_comments']),
            'export_meta' => !empty($_POST['spinexim_export_meta']),
            'export_featured_image' => !empty($_POST['spinexim_export_featured_image']),
            'export_author' => !empty($_POST['spinexim_export_author']),
            'export_dates' => !empty($_POST['spinexim_export_dates']),
            'post_status' => $post_statuses,
        );

        // Generate export data
        $data = $this->generate_export($post_type, $selected_taxonomies, $options);

        // Save to export history
        $history = get_option('spinexim_export_history', array());
        $history[] = array(
            'date' => current_time('mysql'),
            'post_type' => $post_type,
            'items' => $data['stats']['total_items'],
            'filename' => 'spinexim-export-' . $post_type . '-' . gmdate('Y-m-d-H-i-s') . '.json',
        );
        // Keep last 20 exports in history
        update_option('spinexim_export_history', array_slice($history, -20));

        // Generate filename
        $filename = 'spinexim-export-' . sanitize_file_name($post_type) . '-' . gmdate('Y-m-d-H-i-s') . '.json';
        
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
     * @param string $post_type           Post type.
     * @param array  $selected_taxonomies Selected taxonomies.
     * @param array  $options             Export options.
     * @return array
     */
    private function generate_export($post_type, $selected_taxonomies, $options) {
        $data = array(
            'version' => SPINEXIM_VERSION,
            'export_date' => current_time('mysql'),
            'site_url' => esc_url(home_url()),
            'site_name' => get_bloginfo('name'),
            'post_type' => $post_type,
            'taxonomies' => array(),
            'items' => array(),
            'stats' => array(
                'total_items' => 0,
                'total_comments' => 0,
            ),
        );

        // Export taxonomies first
        if (!empty($selected_taxonomies)) {
            $data['taxonomies'] = $this->export_taxonomies($selected_taxonomies);
        }

        // Export items
        $items_result = $this->export_items($post_type, $selected_taxonomies, $options);
        $data['items'] = $items_result['items'];
        $data['stats']['total_items'] = $items_result['total_items'];
        $data['stats']['total_comments'] = $items_result['total_comments'];

        return $data;
    }

    /**
     * Export taxonomies
     *
     * @param array $taxonomy_names Taxonomy names.
     * @return array
     */
    private function export_taxonomies($taxonomy_names) {
        $taxonomies = array();

        foreach ($taxonomy_names as $tax_name) {
            if (!taxonomy_exists($tax_name)) {
                continue;
            }

            $tax_obj = get_taxonomy($tax_name);
            $terms = get_terms(array(
                'taxonomy' => $tax_name,
                'hide_empty' => false,
                'orderby' => 'parent',
                'order' => 'ASC',
            ));

            if (empty($terms) || is_wp_error($terms)) {
                continue;
            }

            $taxonomies[$tax_name] = array(
                'name' => $tax_name,
                'label' => $tax_obj->labels->name,
                'hierarchical' => $tax_obj->hierarchical,
                'terms' => array(),
            );

            foreach ($terms as $term) {
                $term_data = array(
                    'term_id' => $term->term_id,
                    'slug' => $term->slug,
                    'name' => $term->name,
                    'description' => $term->description,
                    'parent_id' => $term->parent,
                    'parent_slug' => '',
                    'term_order' => 0,
                    'thumbnail' => '',
                    'meta' => array(),
                );

                // Get parent slug
                if ($term->parent) {
                    $parent = get_term($term->parent, $tax_name);
                    if ($parent && !is_wp_error($parent)) {
                        $term_data['parent_slug'] = $parent->slug;
                    }
                }

                // Get term meta
                $term_meta = get_term_meta($term->term_id);
                foreach ($term_meta as $meta_key => $meta_values) {
                    if (empty($meta_values)) {
                        continue;
                    }

                    $value = count($meta_values) === 1 ? $meta_values[0] : $meta_values;

                    // Handle thumbnail
                    if ('thumbnail_id' === $meta_key) {
                        $thumb_url = wp_get_attachment_url($value);
                        if ($thumb_url) {
                            $term_data['thumbnail'] = $thumb_url;
                        }
                        continue;
                    }

                    // Handle order
                    if ('order' === $meta_key) {
                        $term_data['term_order'] = intval($value);
                        continue;
                    }

                    // Process other meta
                    $processed = $this->process_meta_for_export($value);
                    if (null !== $processed) {
                        $term_data['meta'][$meta_key] = $processed;
                    }
                }

                $taxonomies[$tax_name]['terms'][] = $term_data;
            }
        }

        return $taxonomies;
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

    /**
     * Export items
     *
     * @param string $post_type           Post type.
     * @param array  $selected_taxonomies Selected taxonomies.
     * @param array  $options             Export options.
     * @return array
     */
    private function export_items($post_type, $selected_taxonomies, $options) {
        $args = array(
            'post_type' => $post_type,
            'posts_per_page' => -1,
            'post_status' => $options['post_status'],
            'orderby' => 'ID',
            'order' => 'ASC',
        );

        $items = array();
        $total_comments = 0;

        $query = new WP_Query($args);

        foreach ($query->posts as $post) {
            $item = array(
                'slug' => $post->post_name,
                'title' => $post->post_title,
                'status' => $post->post_status,
                'content' => $post->post_content,
                'excerpt' => $post->post_excerpt,
                'menu_order' => intval($post->menu_order),
                'parent_id' => intval($post->post_parent),
                'parent_slug' => '',
                'comment_status' => $post->comment_status,
                'ping_status' => $post->ping_status,
                'password' => $post->post_password,
                'template' => get_page_template_slug($post->ID),
            );

            // Dates
            if ($options['export_dates']) {
                $item['date_created'] = $post->post_date;
                $item['date_modified'] = $post->post_modified;
            }

            // Author
            if ($options['export_author']) {
                $author = get_userdata($post->post_author);
                $item['author'] = array(
                    'id' => intval($post->post_author),
                    'username' => $author ? $author->user_login : '',
                    'display_name' => $author ? $author->display_name : '',
                    'email' => $author ? $author->user_email : '',
                );
            }

            // Parent slug
            if ($post->post_parent) {
                $parent = get_post($post->post_parent);
                if ($parent) {
                    $item['parent_slug'] = $parent->post_name;
                }
            }

            // Featured image
            if ($options['export_featured_image']) {
                $thumb_id = get_post_thumbnail_id($post->ID);
                if ($thumb_id) {
                    $item['featured_image'] = array(
                        'id' => intval($thumb_id),
                        'url' => wp_get_attachment_url($thumb_id),
                        'alt' => get_post_meta($thumb_id, '_wp_attachment_image_alt', true),
                    );
                }
            }

            // Taxonomies
            if (!empty($selected_taxonomies)) {
                $item['taxonomies'] = array();
                foreach ($selected_taxonomies as $tax_name) {
                    $terms = wp_get_object_terms($post->ID, $tax_name);
                    if (!empty($terms) && !is_wp_error($terms)) {
                        $item['taxonomies'][$tax_name] = array();
                        foreach ($terms as $term) {
                            $item['taxonomies'][$tax_name][] = array(
                                'term_id' => $term->term_id,
                                'slug' => $term->slug,
                                'name' => $term->name,
                            );
                        }
                    }
                }
            }

            // Meta fields
            if ($options['export_meta']) {
                $item['meta'] = $this->export_post_meta($post->ID);
            }

            // Comments
            if ($options['export_comments']) {
                $item['comments'] = $this->export_comments($post->ID);
                $total_comments += count($item['comments']);
            }

            $items[] = $item;

            // Free memory
            wp_cache_flush();
        }

        wp_reset_postdata();

        return array(
            'items' => $items,
            'total_items' => count($items),
            'total_comments' => $total_comments,
        );
    }

    /**
     * Export post meta
     *
     * @param int $post_id Post ID.
     * @return array
     */
    private function export_post_meta($post_id) {
        $all_meta = get_post_meta($post_id);
        $export_meta = array();

        $skip_meta = array(
            '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date',
            '_oembed_', '_transient_', '_site_transient_',
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
            if (strpos($meta_key, '_wp_') === 0) {
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
     * Export comments
     *
     * @param int $post_id Post ID.
     * @return array
     */
    private function export_comments($post_id) {
        $comments = get_comments(array(
            'post_id' => $post_id,
            'status' => 'all',
            'orderby' => 'comment_date',
            'order' => 'DESC',
        ));

        $comments_data = array();

        foreach ($comments as $comment) {
            $comment_data = array(
                'id' => intval($comment->comment_ID),
                'author' => $comment->comment_author,
                'email' => $comment->comment_author_email,
                'url' => $comment->comment_author_url,
                'ip' => $comment->comment_author_IP,
                'content' => $comment->comment_content,
                'date' => $comment->comment_date,
                'approved' => intval($comment->comment_approved),
                'user_id' => intval($comment->user_id),
                'type' => $comment->comment_type,
                'parent_id' => intval($comment->comment_parent),
                'meta' => array(),
            );

            // Get comment meta
            $comment_meta = get_comment_meta($comment->comment_ID);
            foreach ($comment_meta as $meta_key => $meta_values) {
                $comment_data['meta'][$meta_key] = count($meta_values) === 1 ? $meta_values[0] : $meta_values;
            }

            $comments_data[] = $comment_data;
        }

        return $comments_data;
    }
}

// Initialize post types export module
new Spinexim_Post_Types_Export();