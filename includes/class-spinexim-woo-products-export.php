<?php
/**
 * Spinda - WooCommerce Products Export Module
 *
 * @package Spinda
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Products Export Module Class
 */
class Spinexim_Woo_Products_Export {

    /**
     * Constructor
     */
    public function __construct() {
        // Handle export submission
        add_action('admin_init', array($this, 'handle_export'));
    }

    /**
     * Render export page
     */
    public function render_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.','spinda-exportimport-data'));
        }

        $product_count = wp_count_posts('product');
        $total_products = isset($product_count->publish) ? $product_count->publish : 0;
        $total_products += isset($product_count->draft) ? $product_count->draft : 0;
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Spinda - WooCommerce Products Export','spinda-exportimport-data'); ?></h1>
            
            <div class="spinexim-card">
                <h2><?php echo esc_html__('Export WooCommerce Products','spinda-exportimport-data'); ?></h2>
                <p><?php echo esc_html__('Export all products with complete data including variations, attributes, images, and meta fields.','spinda-exportimport-data'); ?></p>
                <p><strong><?php echo esc_html__('Total Products:','spinda-exportimport-data'); ?></strong> <?php echo esc_html($total_products); ?></p>
                
                <form method="post" id="spinexim-export-form">
                    <?php wp_nonce_field('spinexim_export_nonce', 'spinexim_export_nonce_field'); ?>
                    <input type="hidden" name="spinexim_export_action" value="1">
                    
                    <div class="spinexim-export-options">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php echo esc_html__('Export Options','spinda-exportimport-data'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="spinexim_export_images" value="1" checked>
                                        <?php echo esc_html__('Include images (featured, gallery, variations)','spinda-exportimport-data'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" name="spinexim_export_meta" value="1" checked>
                                        <?php echo esc_html__('Include all meta fields (including ACF)','spinda-exportimport-data'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" name="spinexim_export_reviews" value="1" checked>
                                        <?php echo esc_html__('Include product reviews','spinda-exportimport-data'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" name="spinexim_export_taxonomies" value="1" checked>
                                        <?php echo esc_html__('Include taxonomies (categories, tags, brands)','spinda-exportimport-data'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" name="spinexim_export_variations" value="1" checked>
                                        <?php echo esc_html__('Include variations (for variable products)','spinda-exportimport-data'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Product Status','spinda-exportimport-data'); ?></th>
                                <td>
                                    <label><input type="checkbox" name="spinexim_product_status[]" value="publish" checked> <?php echo esc_html__('Published','spinda-exportimport-data'); ?></label>
                                    <br>
                                    <label><input type="checkbox" name="spinexim_product_status[]" value="draft"> <?php echo esc_html__('Draft','spinda-exportimport-data'); ?></label>
                                    <br>
                                    <label><input type="checkbox" name="spinexim_product_status[]" value="private"> <?php echo esc_html__('Private','spinda-exportimport-data'); ?></label>
                                    <br>
                                    <label><input type="checkbox" name="spinexim_product_status[]" value="pending"> <?php echo esc_html__('Pending','spinda-exportimport-data'); ?></label>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <p>
                        <button type="submit" class="button button-primary button-hero" id="spinexim-export-button">
                            <span class="dashicons dashicons-download"></span>
                            <?php echo esc_html__('Export Products (JSON)','spinda-exportimport-data'); ?>
                        </button>
                    </p>
                    
                    <p class="spinexim-description">
                        <?php echo esc_html__('The export will generate a JSON file containing all selected product data.','spinda-exportimport-data'); ?>
                    </p>
                </form>
            </div>
            
            <!-- Export History -->
            <div class="spinexim-card">
                <h2><?php echo esc_html__('Export History','spinda-exportimport-data'); ?></h2>
                <?php
                $history = get_option('spinexim_woo_export_history', array());
                if (empty($history)) {
                    echo '<p>' . esc_html__('No exports yet.','spinda-exportimport-data') . '</p>';
                } else {
                    ?>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Date','spinda-exportimport-data'); ?></th>
                                <th><?php echo esc_html__('Products','spinda-exportimport-data'); ?></th>
                                <th><?php echo esc_html__('Variations','spinda-exportimport-data'); ?></th>
                                <th><?php echo esc_html__('File','spinda-exportimport-data'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_reverse($history) as $export): ?>
                                <tr>
                                    <td><?php echo esc_html($export['date']); ?></td>
                                    <td><?php echo esc_html($export['products']); ?></td>
                                    <td><?php echo esc_html($export['variations']); ?></td>
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
     * Handle export submission
     */
    public function handle_export() {
        // Check if export action is set
        if (!isset($_POST['spinexim_export_action'])) {
            return;
        }

        // Verify nonce
        if (!isset($_POST['spinexim_export_nonce_field']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spinexim_export_nonce_field'])), 'spinexim_export_nonce')) {
            wp_die(esc_html__('Security check failed. Please refresh the page and try again.','spinda-exportimport-data'));
        }

        // Check capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.','spinda-exportimport-data'));
        }

        // Increase limits for large exports
        wp_raise_memory_limit('admin');
        @set_time_limit(0);
        @ini_set('memory_limit', '2048M');
        @ini_set('max_execution_time', 0);
        ignore_user_abort(true);

        // Get export options
        $product_statuses = isset($_POST['spinexim_product_status']) ? array_map('sanitize_text_field', wp_unslash($_POST['spinexim_product_status'])) : array('publish');

        $options = array(
            'export_images' => !empty($_POST['spinexim_export_images']),
            'export_meta' => !empty($_POST['spinexim_export_meta']),
            'export_reviews' => !empty($_POST['spinexim_export_reviews']),
            'export_taxonomies' => !empty($_POST['spinexim_export_taxonomies']),
            'export_variations' => !empty($_POST['spinexim_export_variations']),
            'product_status' => $product_statuses,
        );

        // Generate export data
        $data = $this->generate_export($options);

        // Save to export history
        $history = get_option('spinexim_woo_export_history', array());
        $history[] = array(
            'date' => current_time('mysql'),
            'products' => $data['stats']['total_products'],
            'variations' => $data['stats']['total_variations'],
            'filename' => 'spinexim-woo-export-' . gmdate('Y-m-d-H-i-s') . '.json',
        );
        update_option('spinexim_woo_export_history', array_slice($history, -20));

        // Generate filename
        $filename = 'spinexim-woo-export-' . gmdate('Y-m-d-H-i-s') . '.json';
        
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
     * @param array $options Export options.
     * @return array
     */
    private function generate_export($options) {
        $data = array(
            'version' => SPINEXIM_VERSION,
            'export_date' => current_time('mysql'),
            'site_url' => esc_url(home_url()),
            'site_name' => get_bloginfo('name'),
            'export_type' => 'woocommerce_products',
            'taxonomies' => array(),
            'attributes' => array(),
            'products' => array(),
            'stats' => array(
                'total_products' => 0,
                'total_variations' => 0,
                'total_reviews' => 0,
            ),
        );

        // Export taxonomies
        if ($options['export_taxonomies']) {
            $data['taxonomies'] = $this->export_taxonomies();
        }

        // Export attributes
        $data['attributes'] = $this->export_attributes();

        // Export products
        $products_result = $this->export_products($options);
        $data['products'] = $products_result['products'];
        $data['stats']['total_products'] = $products_result['total_products'];
        $data['stats']['total_variations'] = $products_result['total_variations'];
        $data['stats']['total_reviews'] = $products_result['total_reviews'];

        return $data;
    }

    /**
     * Export taxonomies
     *
     * @return array
     */
    private function export_taxonomies() {
    $taxonomies = array();
    $product_taxonomies = get_object_taxonomies('product', 'objects');
    
    $skip_taxonomies = array('product_type', 'product_visibility', 'product_shipping_class');

    foreach ($product_taxonomies as $tax_name => $tax_obj) {
        if (in_array($tax_name, $skip_taxonomies, true)) {
            continue;
        }

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
            'label' => $tax_obj->labels->name,
            'hierarchical' => $tax_obj->hierarchical,
            'terms' => array(),
        );

        foreach ($terms as $term) {
            $term_data = array(
                'slug' => $term->slug,
                'name' => $term->name,
                'description' => $term->description,
                'parent_slug' => '',
                'term_order' => 0,
            );

            // Get parent slug
            if ($term->parent) {
                $parent = get_term($term->parent, $tax_name);
                if ($parent && !is_wp_error($parent)) {
                    $term_data['parent_slug'] = $parent->slug;
                }
            }

            // ===== NEW: Export all term meta =====
            $term_meta = get_term_meta($term->term_id);
            $term_meta_data = array();
            $skip_meta = array('thumbnail_id', 'order'); // These are handled separately
            
            foreach ($term_meta as $meta_key => $meta_values) {
                if (empty($meta_values) || in_array($meta_key, $skip_meta)) {
                    continue;
                }
                
                // Process meta values similar to product meta
                $value = count($meta_values) === 1 ? $meta_values[0] : $meta_values;
                $processed = $this->process_meta_for_export($value);
                
                if (null !== $processed) {
                    $term_meta_data[$meta_key] = $processed;
                }
            }
            
            // Store term meta in the term data
            $term_data['meta_data'] = $term_meta_data;

            // Handle thumbnail (existing code)
            if (isset($term_meta['thumbnail_id']) && !empty($term_meta['thumbnail_id'][0])) {
                $thumb_url = wp_get_attachment_url($term_meta['thumbnail_id'][0]);
                if ($thumb_url) {
                    $term_data['thumbnail'] = $thumb_url;
                }
            }

            // Handle order (existing code)
            if (isset($term_meta['order']) && !empty($term_meta['order'][0])) {
                $term_data['term_order'] = intval($term_meta['order'][0]);
            }

            $taxonomies[$tax_name]['terms'][] = $term_data;
        }
    }

    return $taxonomies;
}

    /**
     * Export attributes
     *
     * @return array
     */
    private function export_attributes() {
        global $wpdb;
        
        $attributes = array();
        $attribute_taxonomies = wc_get_attribute_taxonomies();
        
        foreach ($attribute_taxonomies as $attribute) {
            $taxonomy = wc_attribute_taxonomy_name($attribute->attribute_name);
            $terms = get_terms(array(
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
            ));
            
            $attr_data = array(
                'name' => $attribute->attribute_name,
                'label' => $attribute->attribute_label,
                'type' => $attribute->attribute_type,
                'orderby' => $attribute->attribute_orderby,
                'has_archives' => $attribute->attribute_public,
                'terms' => array(),
            );
            
            if (!empty($terms) && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $attr_data['terms'][] = array(
                        'slug' => $term->slug,
                        'name' => $term->name,
                        'description' => $term->description,
                    );
                }
            }
            
            $attributes[] = $attr_data;
        }
        
        return $attributes;
    }

    /**
     * Export products
     *
     * @param array $options Export options.
     * @return array
     */
    private function export_products($options) {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => $options['product_status'],
            'orderby' => 'ID',
            'order' => 'ASC',
        );

        $products = array();
        $total_variations = 0;
        $total_reviews = 0;

        $query = new WP_Query($args);

        foreach ($query->posts as $post) {
            $product = wc_get_product($post->ID);
            if (!$product) {
                continue;
            }

            $product_data = $this->export_single_product($product, $options);
            $products[] = $product_data;

            // Count variations
            if ($product->is_type('variable') && $options['export_variations']) {
                $total_variations += count($product->get_children());
            }

            // Count reviews
            if ($options['export_reviews']) {
                $reviews = get_comments(array(
                    'post_id' => $post->ID,
                    'type' => 'review',
                    'count' => true,
                ));
                $total_reviews += $reviews;
            }

            // Free memory
            wp_cache_flush();
        }

        wp_reset_postdata();

        return array(
            'products' => $products,
            'total_products' => count($products),
            'total_variations' => $total_variations,
            'total_reviews' => $total_reviews,
        );
    }

    /**
     * Export single product - COMPLETE FIX
     *
     * @param WC_Product $product Product object.
     * @param array      $options Export options.
     * @return array
     */
    private function export_single_product($product, $options) {
        $product_id = $product->get_id();
        
        // Get all product data
        $product_data = array(
            'slug' => $product->get_slug(),
            'name' => $product->get_name(),
            'type' => $product->get_type(),
            'sku' => $product->get_sku(),
            'description' => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'sale_price_from' => $product->get_date_on_sale_from() ? $product->get_date_on_sale_from()->date('Y-m-d H:i:s') : '',
            'sale_price_to' => $product->get_date_on_sale_to() ? $product->get_date_on_sale_to()->date('Y-m-d H:i:s') : '',
            'manage_stock' => $product->get_manage_stock(),
            'stock_quantity' => $product->get_stock_quantity(),
            'stock_status' => $product->get_stock_status(),
            'backorders' => $product->get_backorders(),
            'sold_individually' => $product->get_sold_individually(),
            'weight' => $product->get_weight(),
            'length' => $product->get_length(),
            'width' => $product->get_width(),
            'height' => $product->get_height(),
            'tax_status' => $product->get_tax_status(),
            'tax_class' => $product->get_tax_class(),
            'featured' => $product->get_featured(),
            'catalog_visibility' => $product->get_catalog_visibility(),
            'downloadable' => $product->get_downloadable(),
            'virtual' => $product->get_virtual(),
            'download_limit' => $product->get_download_limit(),
            'download_expiry' => $product->get_download_expiry(),
            'purchase_note' => $product->get_purchase_note(),
            'menu_order' => $product->get_menu_order(),
            'reviews_allowed' => $product->get_reviews_allowed(),
            'status' => $product->get_status(),
            'date_created' => $product->get_date_created() ? $product->get_date_created()->date('Y-m-d H:i:s') : '',
            'date_modified' => $product->get_date_modified() ? $product->get_date_modified()->date('Y-m-d H:i:s') : '',
            'author_id' => get_post_field('post_author', $product_id),
        );

        // Export product attributes (CRITICAL)
        $product_attributes = get_post_meta($product_id, '_product_attributes', true);
        if (!empty($product_attributes) && is_array($product_attributes)) {
            $product_data['product_attributes'] = $product_attributes;
        }

        // Export meta fields
        if ($options['export_meta']) {
            $product_data['meta_data'] = $this->export_post_meta($product_id);
        }

        // Export images
        if ($options['export_images']) {
            $product_data['featured_image'] = $this->get_image_data(get_post_thumbnail_id($product_id));
            $product_data['gallery_images'] = $this->get_gallery_images_data($product_id);
        }

        // Export taxonomies
        if ($options['export_taxonomies']) {
            $product_data['taxonomies'] = $this->get_product_taxonomies($product_id);
        }

        // Export reviews
        if ($options['export_reviews']) {
            $product_data['reviews'] = $this->get_product_reviews($product_id);
        }

        // Export variations for variable products - FIXED
        if ($product->is_type('variable') && $options['export_variations']) {
            $product_data['variation_attributes'] = $product->get_variation_attributes();
            $product_data['default_attributes'] = $product->get_default_attributes();
            $product_data['variations'] = $this->export_variations_complete($product, $options);
        }

        return $product_data;
    }

    /**
     * Export variations with complete data - FIXED VERSION
     *
     * @param WC_Product_Variable $product Variable product.
     * @param array               $options Export options.
     * @return array
     */
    private function export_variations_complete($product, $options) {
        $variations = array();
        $children = $product->get_children();
        
        // Get all variation attributes from parent
        $parent_attributes = $product->get_variation_attributes();
        
        foreach ($children as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation) {
                continue;
            }
            
            // Get variation attributes with values
            $variation_attributes = $variation->get_attributes();
            
            // Get variation description
            $variation_description = $variation->get_description();
            
            // Get variation meta
            $variation_meta = get_post_meta($variation_id);
            
            $variation_data = array(
                'id' => $variation_id,
                'sku' => $variation->get_sku(),
                'regular_price' => $variation->get_regular_price(),
                'sale_price' => $variation->get_sale_price(),
                'manage_stock' => $variation->get_manage_stock(),
                'stock_quantity' => $variation->get_stock_quantity(),
                'stock_status' => $variation->get_stock_status(),
                'backorders' => $variation->get_backorders(),
                'weight' => $variation->get_weight(),
                'length' => $variation->get_length(),
                'width' => $variation->get_width(),
                'height' => $variation->get_height(),
                'tax_class' => $variation->get_tax_class(),
                'downloadable' => $variation->get_downloadable(),
                'virtual' => $variation->get_virtual(),
                'download_limit' => $variation->get_download_limit(),
                'download_expiry' => $variation->get_download_expiry(),
                'description' => $variation_description,
                'menu_order' => $variation->get_menu_order(),
                'status' => $variation->get_status(),
                'date_created' => $variation->get_date_created() ? $variation->get_date_created()->date('Y-m-d H:i:s') : '',
                'date_modified' => $variation->get_date_modified() ? $variation->get_date_modified()->date('Y-m-d H:i:s') : '',
                // CRITICAL: Store complete attributes with their values
                'attributes' => $variation_attributes,
                // Store all meta including _variation_attributes
                'meta_data' => array(),
            );
            
            // Store all variation meta (including _variation_attributes)
            if ($options['export_meta']) {
                $variation_data['meta_data'] = $this->export_post_meta($variation_id);
            }
            
            // Export variation image
            if ($options['export_images']) {
                $image_id = get_post_thumbnail_id($variation_id);
                $variation_data['image'] = $this->get_image_data($image_id);
            }
            
            $variations[] = $variation_data;
        }
        
        return $variations;
    }

    /**
     * Export post meta - COMPLETE
     *
     * @param int $post_id Post ID.
     * @return array
     */
    private function export_post_meta($post_id) {
        $all_meta = get_post_meta($post_id);
        $export_meta = array();

        // Skip these meta keys
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
            
            // Don't skip variation attributes - they are needed
            if ($meta_key === '_variation_attributes') {
                // Process this meta but don't skip it
                $value = count($meta_values) === 1 ? $meta_values[0] : $meta_values;
                $processed = $this->process_meta_for_export($value);
                if (null !== $processed) {
                    $export_meta[$meta_key] = $processed;
                }
                continue;
            }
            
            // Skip WooCommerce internal meta that we already handle separately
            $woo_skip = array(
                '_regular_price', '_sale_price', '_sku', '_stock', '_stock_status',
                '_weight', '_length', '_width', '_height', '_tax_status', '_tax_class',
                '_variation_attributes', '_product_attributes', '_default_attributes',
                '_product_image_gallery'
            );
            
            if (in_array($meta_key, $woo_skip)) {
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

    /**
     * Get image data
     *
     * @param int $image_id Image ID.
     * @return array|null
     */
    private function get_image_data($image_id) {
        if (!$image_id) {
            return null;
        }
        
        $image_url = wp_get_attachment_url($image_id);
        if (!$image_url) {
            return null;
        }
        
        return array(
            'id' => intval($image_id),
            'url' => $image_url,
            'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true),
        );
    }

    /**
     * Get gallery images data
     *
     * @param int $product_id Product ID.
     * @return array
     */
    private function get_gallery_images_data($product_id) {
        $gallery_ids = get_post_meta($product_id, '_product_image_gallery', true);
        if (empty($gallery_ids)) {
            return array();
        }
        
        $gallery_ids = explode(',', $gallery_ids);
        $gallery_images = array();
        
        foreach ($gallery_ids as $image_id) {
            $image_data = $this->get_image_data($image_id);
            if ($image_data) {
                $gallery_images[] = $image_data;
            }
        }
        
        return $gallery_images;
    }

    /**
     * Get product taxonomies
     *
     * @param int $product_id Product ID.
     * @return array
     */
    private function get_product_taxonomies($product_id) {
        $taxonomies = array();
        $product_taxonomies = get_object_taxonomies('product', 'names');
        $skip_taxonomies = array('product_type', 'product_visibility', 'product_shipping_class');
        
        foreach ($product_taxonomies as $tax_name) {
            if (in_array($tax_name, $skip_taxonomies, true)) {
                continue;
            }
            
            $terms = wp_get_object_terms($product_id, $tax_name);
            if (!empty($terms) && !is_wp_error($terms)) {
                $taxonomies[$tax_name] = array();
                foreach ($terms as $term) {
                    $taxonomies[$tax_name][] = array(
                        'slug' => $term->slug,
                        'name' => $term->name,
                    );
                }
            }
        }
        
        return $taxonomies;
    }

    /**
     * Get product reviews
     *
     * @param int $product_id Product ID.
     * @return array
     */
    private function get_product_reviews($product_id) {
        $comments = get_comments(array(
            'post_id' => $product_id,
            'type' => 'review',
            'status' => 'all',
            'orderby' => 'comment_date',
            'order' => 'DESC',
        ));

        $reviews = array();

        foreach ($comments as $comment) {
            $review = array(
                'id' => intval($comment->comment_ID),
                'author' => $comment->comment_author,
                'author_email' => $comment->comment_author_email,
                'author_url' => $comment->comment_author_url,
                'author_ip' => $comment->comment_author_IP,
                'content' => $comment->comment_content,
                'date' => $comment->comment_date,
                'date_gmt' => $comment->comment_date_gmt,
                'approved' => intval($comment->comment_approved),
                'user_id' => intval($comment->user_id),
                'verified' => get_comment_meta($comment->comment_ID, 'verified', true),
                'rating' => intval(get_comment_meta($comment->comment_ID, 'rating', true)),
                'meta' => array(),
            );

            // Get comment meta
            $comment_meta = get_comment_meta($comment->comment_ID);
            foreach ($comment_meta as $meta_key => $meta_values) {
                if (!in_array($meta_key, array('rating', 'verified'))) {
                    $review['meta'][$meta_key] = count($meta_values) === 1 ? $meta_values[0] : $meta_values;
                }
            }

            $reviews[] = $review;
        }

        return $reviews;
    }
}

// Initialize products export module
new Spinexim_Woo_Products_Export();