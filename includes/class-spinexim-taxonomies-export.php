<?php
/**
 * Spinda - Taxonomies Export Module
 *
 * @package Spinda
 * @since 2.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Taxonomies Export Module Class
 */
class Spinexim_Taxonomies_Export {

    /**
     * Constructor
     */
    public function __construct() {
        // Handle AJAX request for taxonomies
        add_action('wp_ajax_spinexim_get_taxonomies_for_export', array($this, 'ajax_get_taxonomies'));
        
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

        // Include product in post types list
        $excluded_post_types = array('attachment');
        $all_post_types = get_post_types(array('public' => true), 'objects');
        $post_types = array();

        foreach ($all_post_types as $name => $pt) {
            if (!in_array($name, $excluded_post_types, true)) {
                $post_types[$name] = $pt;
            }
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Spinda - Taxonomies Export', 'spinda-exportimport-data'); ?></h1>
            
            <div class="spinexim-card">
                <h2><?php echo esc_html__('Export Taxonomies Configuration', 'spinda-exportimport-data'); ?></h2>
                <p><?php echo esc_html__('Select post type and taxonomies to export. The system will generate a JSON file with all taxonomy terms, their metadata, and term-post relationships.', 'spinda-exportimport-data'); ?></p>
                
                <form method="post" id="spinexim-taxonomies-export-form">
                    <?php wp_nonce_field('spinexim_taxonomies_export_nonce', 'spinexim_taxonomies_export_nonce_field'); ?>
                    <input type="hidden" name="spinexim_taxonomies_export_action" value="1">
                    
                    <!-- Step 1: Post Type -->
                    <div class="spinexim-form-section">
                        <h3><?php echo esc_html__('Step 1: Select Post Type', 'spinda-exportimport-data'); ?></h3>
                        <select name="spinexim_export_post_type" id="spinexim-tax-export-post-type" class="widefat" style="max-width: 400px;">
                            <option value=""><?php echo esc_html__('— Select Post Type —', 'spinda-exportimport-data'); ?></option>
                            <?php 
                            foreach ($post_types as $pt): 
                                $taxonomies = get_object_taxonomies($pt->name, 'objects');
                                $tax_count = 0;
                                foreach ($taxonomies as $tax_name => $tax_obj) {
                                    if (!in_array($tax_name, array('post_format'), true)) {
                                        $tax_count++;
                                    }
                                }
                            ?>
                                <option value="<?php echo esc_attr($pt->name); ?>">
                                    <?php 
                                    printf(
                                        /* translators: 1: Post type label, 2: Post type name, 3: Number of taxonomies */
                                        esc_html__('%1$s (%2$s) - %3$d taxonomies', 'spinda-exportimport-data'),
                                        esc_html($pt->labels->name),
                                        esc_html($pt->name),
                                        intval($tax_count)
                                    ); 
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="spinexim-description"><?php echo esc_html__('Choose post type to see available taxonomies', 'spinda-exportimport-data'); ?></p>
                    </div>
                    
                    <!-- Step 2: Taxonomies -->
                    <div class="spinexim-form-section" id="spinexim-taxonomies-selection-section" style="display: none;">
                        <h3><?php echo esc_html__('Step 2: Select Taxonomies to Export', 'spinda-exportimport-data'); ?></h3>
                        <div id="spinexim-taxonomy-selection-checkboxes">
                            <p class="spinexim-loading"><?php echo esc_html__('Loading taxonomies...', 'spinda-exportimport-data'); ?></p>
                        </div>
                        <p class="spinexim-description"><?php echo esc_html__('Select which taxonomies (categories, tags, etc.) to export with their terms, metadata, and post relationships', 'spinda-exportimport-data'); ?></p>
                    </div>
                    
                    <!-- Step 3: Options -->
                    <div class="spinexim-form-section" id="spinexim-tax-export-options-section" style="display: none;">
                        <h3><?php echo esc_html__('Step 3: Export Options', 'spinda-exportimport-data'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php echo esc_html__('Include Term Meta', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_export_term_meta" value="1" checked>
                                        <?php echo esc_html__('Export all term metadata (custom fields, thumbnails, etc.)', 'spinda-exportimport-data'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Include Term Descriptions', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_export_descriptions" value="1" checked>
                                        <?php echo esc_html__('Export term descriptions', 'spinda-exportimport-data'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Include Term Hierarchy', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_export_hierarchy" value="1" checked>
                                        <?php echo esc_html__('Export parent-child relationships', 'spinda-exportimport-data'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Include Term Counts', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_export_counts" value="1" checked>
                                        <?php echo esc_html__('Export post count for each term', 'spinda-exportimport-data'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Include Empty Terms', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_export_empty_terms" value="1" checked>
                                        <?php echo esc_html__('Export terms with no posts assigned', 'spinda-exportimport-data'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Include Term-Post Relationships', 'spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_export_relationships" value="1" checked>
                                        <?php echo esc_html__('Export which posts are assigned to each term', 'spinda-exportimport-data'); ?>
                                    </label>
                                    <p class="spinexim-description"><?php echo esc_html__('This allows automatic term assignment when importing', 'spinda-exportimport-data'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Submit -->
                    <p id="spinexim-tax-export-submit-section" style="display: none;">
                        <button type="submit" class="button button-primary button-hero" id="spinexim-tax-export-button">
                            <span class="dashicons dashicons-download"></span>
                            <?php echo esc_html__('Download Taxonomies Export File (JSON)', 'spinda-exportimport-data'); ?>
                        </button>
                    </p>
                </form>
            </div>
            
            <!-- Export History -->
            <div class="spinexim-card">
                <h2><?php echo esc_html__('Taxonomies Export History', 'spinda-exportimport-data'); ?></h2>
                <?php
                $history = get_option('spinexim_taxonomies_export_history', array());
                if (empty($history)) {
                    echo '<p>' . esc_html__('No taxonomy exports yet.', 'spinda-exportimport-data') . '</p>';
                } else {
                    ?>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Date', 'spinda-exportimport-data'); ?></th>
                                <th><?php echo esc_html__('Post Type', 'spinda-exportimport-data'); ?></th>
                                <th><?php echo esc_html__('Taxonomies', 'spinda-exportimport-data'); ?></th>
                                <th><?php echo esc_html__('Total Terms', 'spinda-exportimport-data'); ?></th>
                                <th><?php echo esc_html__('Relationships', 'spinda-exportimport-data'); ?></th>
                                <th><?php echo esc_html__('File', 'spinda-exportimport-data'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_reverse($history) as $export): ?>
                                <tr>
                                    <td><?php echo esc_html($export['date']); ?></td>
                                    <td><?php echo esc_html($export['post_type']); ?></td>
                                    <td><?php echo esc_html($export['taxonomies_count']); ?></td>
                                    <td><?php echo esc_html($export['total_terms']); ?></td>
                                    <td><?php echo esc_html($export['total_relationships'] ?? 0); ?></td>
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
     * AJAX handler to get taxonomies for export
     */
    public function ajax_get_taxonomies() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'spinexim_tax_export_ajax')) {
            wp_send_json_error(array('message' => esc_html__('Security check failed.', 'spinda-exportimport-data')));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Unauthorized access.', 'spinda-exportimport-data')));
        }

        // Get and sanitize post type
        $post_type = isset($_POST['post_type']) ? sanitize_text_field(wp_unslash($_POST['post_type'])) : '';
        
        if (!post_type_exists($post_type)) {
            wp_send_json_error(array('message' => esc_html__('Invalid post type.', 'spinda-exportimport-data')));
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
                'public' => $tax_obj->public,
            );
        }

        wp_send_json_success($result);
    }

    /**
     * Handle export submission
     */
    public function handle_export() {
        // Check if export action is set
        if (!isset($_POST['spinexim_taxonomies_export_action'])) {
            return;
        }

        // Verify nonce
        if (!isset($_POST['spinexim_taxonomies_export_nonce_field']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spinexim_taxonomies_export_nonce_field'])), 'spinexim_taxonomies_export_nonce')) {
            wp_die(esc_html__('Security check failed. Please refresh the page and try again.', 'spinda-exportimport-data'));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'spinda-exportimport-data'));
        }

        // Validate post type
        $post_type = isset($_POST['spinexim_export_post_type']) ? sanitize_text_field(wp_unslash($_POST['spinexim_export_post_type'])) : '';
        if (!post_type_exists($post_type)) {
            wp_die(esc_html__('Invalid post type selected.', 'spinda-exportimport-data'));
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
        
        if (empty($selected_taxonomies)) {
            wp_die(esc_html__('Please select at least one taxonomy to export.', 'spinda-exportimport-data'));
        }

        $options = array(
            'export_term_meta' => !empty($_POST['spinexim_export_term_meta']),
            'export_descriptions' => !empty($_POST['spinexim_export_descriptions']),
            'export_hierarchy' => !empty($_POST['spinexim_export_hierarchy']),
            'export_counts' => !empty($_POST['spinexim_export_counts']),
            'export_empty_terms' => !empty($_POST['spinexim_export_empty_terms']),
            'export_relationships' => !empty($_POST['spinexim_export_relationships']),
        );

        // Generate export data
        $data = $this->generate_export($post_type, $selected_taxonomies, $options);

        // Save to export history
        $history = get_option('spinexim_taxonomies_export_history', array());
        $history[] = array(
            'date' => current_time('mysql'),
            'post_type' => $post_type,
            'taxonomies_count' => count($selected_taxonomies),
            'total_terms' => $data['stats']['total_terms'],
            'total_relationships' => $data['stats']['total_relationships'] ?? 0,
            'filename' => 'spinexim-taxonomies-export-' . $post_type . '-' . gmdate('Y-m-d-H-i-s') . '.json',
        );
        // Keep last 20 exports in history
        update_option('spinexim_taxonomies_export_history', array_slice($history, -20));

        // Generate filename
        $filename = 'spinexim-taxonomies-export-' . sanitize_file_name($post_type) . '-' . gmdate('Y-m-d-H-i-s') . '.json';
        
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
            'export_type' => 'taxonomies',
            'site_url' => esc_url(home_url()),
            'site_name' => get_bloginfo('name'),
            'post_type' => $post_type,
            'taxonomies' => array(),
            'term_relationships' => array(),
            'stats' => array(
                'total_taxonomies' => 0,
                'total_terms' => 0,
                'total_meta_fields' => 0,
                'total_relationships' => 0,
            ),
        );

        // Export taxonomies with their terms
        $data['taxonomies'] = $this->export_taxonomies($selected_taxonomies, $options, $data['stats']);
        $data['stats']['total_taxonomies'] = count($data['taxonomies']);

        // Export term-post relationships if enabled
        if ($options['export_relationships']) {
            $data['term_relationships'] = $this->export_term_post_relationships($selected_taxonomies, $options, $data['stats']);
            $data['stats']['total_relationships'] = $this->count_relationships($data['term_relationships']);
        }

        return $data;
    }

    /**
     * Count total relationships
     *
     * @param array $relationships Relationship data.
     * @return int
     */
    private function count_relationships($relationships) {
        $count = 0;
        foreach ($relationships as $tax_name => $tax_relationships) {
            foreach ($tax_relationships as $term_slug => $posts) {
                $count += count($posts);
            }
        }
        return $count;
    }

    /**
     * Export taxonomies with all metadata
     *
     * @param array $taxonomy_names Taxonomy names.
     * @param array $options        Export options.
     * @param array $stats          Stats array (passed by reference).
     * @return array
     */
    private function export_taxonomies($taxonomy_names, $options, &$stats) {
        $taxonomies = array();

        foreach ($taxonomy_names as $tax_name) {
            if (!taxonomy_exists($tax_name)) {
                continue;
            }

            $tax_obj = get_taxonomy($tax_name);
            
            // Get terms
            $term_args = array(
                'taxonomy' => $tax_name,
                'hide_empty' => !$options['export_empty_terms'],
                'orderby' => 'parent',
                'order' => 'ASC',
            );
            
            $terms = get_terms($term_args);

            if (empty($terms) || is_wp_error($terms)) {
                continue;
            }

            $taxonomies[$tax_name] = array(
                'name' => $tax_name,
                'label' => $tax_obj->labels->name,
                'singular_label' => $tax_obj->labels->singular_name,
                'description' => $tax_obj->description,
                'hierarchical' => $tax_obj->hierarchical,
                'public' => $tax_obj->public,
                'show_ui' => $tax_obj->show_ui,
                'show_in_menu' => $tax_obj->show_in_menu,
                'show_in_nav_menus' => $tax_obj->show_in_nav_menus,
                'show_tagcloud' => $tax_obj->show_tagcloud,
                'show_in_quick_edit' => $tax_obj->show_in_quick_edit,
                'show_admin_column' => $tax_obj->show_admin_column,
                'rewrite_slug' => isset($tax_obj->rewrite['slug']) ? $tax_obj->rewrite['slug'] : '',
                'query_var' => $tax_obj->query_var,
                'terms' => array(),
            );

            foreach ($terms as $term) {
                $term_data = array(
                    'term_id' => $term->term_id,
                    'slug' => $term->slug,
                    'name' => $term->name,
                );

                // Description
                if ($options['export_descriptions']) {
                    $term_data['description'] = $term->description;
                }

                // Hierarchy
                if ($options['export_hierarchy']) {
                    $term_data['parent_id'] = $term->parent;
                    $term_data['parent_slug'] = '';
                    
                    if ($term->parent) {
                        $parent = get_term($term->parent, $tax_name);
                        if ($parent && !is_wp_error($parent)) {
                            $term_data['parent_slug'] = $parent->slug;
                        }
                    }
                }

                // Count
                if ($options['export_counts']) {
                    $term_data['count'] = $term->count;
                }

                // Term Meta
                if ($options['export_term_meta']) {
                    $term_meta = $this->export_term_meta($term->term_id);
                    if (!empty($term_meta)) {
                        $term_data['meta'] = $term_meta;
                        $stats['total_meta_fields'] += count($term_meta);
                    }
                }

                $taxonomies[$tax_name]['terms'][] = $term_data;
                $stats['total_terms']++;
            }
        }

        return $taxonomies;
    }

    /**
     * Export term meta data
     *
     * @param int $term_id Term ID.
     * @return array
     */
    private function export_term_meta($term_id) {
        $all_meta = get_term_meta($term_id);
        $export_meta = array();

        $skip_meta = array(
            '_transient_', '_site_transient_',
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

            // Skip thumbnail_id if we're exporting it separately
            if ('thumbnail_id' === $meta_key) {
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
        $data = trim($data);
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
     * Export term-post relationships
     *
     * @param array $taxonomy_names Taxonomy names.
     * @param array $options        Export options.
     * @param array $stats          Stats array (passed by reference).
     * @return array
     */
    private function export_term_post_relationships($taxonomy_names, $options, &$stats) {
        $relationships = array();
        
        foreach ($taxonomy_names as $tax_name) {
            if (!taxonomy_exists($tax_name)) {
                continue;
            }
            
            // Get all terms in this taxonomy
            $terms = get_terms(array(
                'taxonomy' => $tax_name,
                'hide_empty' => false,
            ));
            
            if (empty($terms) || is_wp_error($terms)) {
                continue;
            }
            
            $relationships[$tax_name] = array();
            
            foreach ($terms as $term) {
                // Get posts assigned to this term
                $posts = get_posts(array(
                    'post_type' => 'any',
                    'posts_per_page' => -1,
                    'tax_query' => array(
                        array(
                            'taxonomy' => $tax_name,
                            'field' => 'term_id',
                            'terms' => $term->term_id,
                        )
                    ),
                    'fields' => 'ids',
                ));
                
                if (!empty($posts)) {
                    $relationships[$tax_name][$term->slug] = array();
                    foreach ($posts as $post_id) {
                        $post = get_post($post_id);
                        if ($post) {
                            $relationships[$tax_name][$term->slug][] = array(
                                'post_id' => $post_id,
                                'post_slug' => $post->post_name,
                                'post_type' => $post->post_type,
                                'post_title' => $post->post_title,
                            );
                        }
                    }
                }
            }
            
            // Remove empty term relationships if any
            if (empty($relationships[$tax_name])) {
                unset($relationships[$tax_name]);
            }
        }
        
        return $relationships;
    }
}

// Initialize taxonomies export module
new Spinexim_Taxonomies_Export();