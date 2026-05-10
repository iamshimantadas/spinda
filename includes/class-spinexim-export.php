<?php
/**
 * Post export handler class
 * 
 * @since 1.0.0
 * @package Spinda
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Spinexim_Export {
    
    /**
     * Skip meta keys array
     * 
     * @var array
     */
    private $skip_meta = array('_edit_lock', '_edit_last');
    
    /**
     * Export posts with all dependencies
     * 
     * @since 1.0.0
     * @param string $post_type Post type to export
     * @param bool $include_taxonomies Include taxonomies
     * @param bool $include_term_meta Include term meta
     * @param bool $include_featured_media Include featured images
     * @param bool $include_post_meta Include post meta
     * @param bool $include_comments Include comments
     * @param bool $include_variations Include product variations
     * @return void
     */
    public function spinexim_export_posts(
        $post_type,
        $include_taxonomies,
        $include_term_meta,
        $include_featured_media,
        $include_post_meta,
        $include_comments,
        $include_variations
    ) {
        // Validate post type exists
        $post_type_obj = get_post_type_object($post_type);
        if (!$post_type_obj) {
            wp_die(__('Invalid post type.', 'spinda-exportimport-data'));
        }
        
        $data = array(
            'post_type' => $post_type,
            'media' => array(),
            'terms' => array(),
            'posts' => array()
        );
        
        $media_map = array();
        $term_map = array();
        
        // Export taxonomies
        if ($include_taxonomies) {
            $this->spinexim_export_taxonomies($post_type, $include_term_meta, $data, $media_map, $term_map);
        }
        
        // Export posts
        $this->spinexim_export_posts_data(
            $post_type,
            $include_featured_media,
            $include_post_meta,
            $include_comments,
            $include_variations,
            $data,
            $media_map
        );
        
        // Download JSON
        $this->spinexim_download_json($data, $post_type);
    }
    
    /**
     * Export taxonomies and terms
     * 
     * @since 1.0.0
     * @param string $post_type Post type
     * @param bool $include_term_meta Include term meta
     * @param array &$data Reference to data array
     * @param array &$media_map Reference to media map
     * @param array &$term_map Reference to term map
     * @return void
     */
    private function spinexim_export_taxonomies($post_type, $include_term_meta, &$data, &$media_map, &$term_map) {
        $taxes = get_object_taxonomies($post_type);
        
        foreach ($taxes as $tax) {
            $terms = get_terms(array(
                'taxonomy' => $tax,
                'hide_empty' => false
            ));
            
            if (is_wp_error($terms)) {
                continue;
            }
            
            foreach ($terms as $term) {
                $key = $tax . ':' . $term->slug;
                if (isset($term_map[$key])) {
                    continue;
                }
                
                $parent_slug = '';
                if ($term->parent) {
                    $parent = get_term($term->parent);
                    if ($parent && !is_wp_error($parent)) {
                        $parent_slug = sanitize_title($parent->slug);
                    }
                }
                
                $term_data = array(
                    'taxonomy' => sanitize_key($tax),
                    'slug' => sanitize_title($term->slug),
                    'name' => sanitize_text_field($term->name),
                    'parent_slug' => $parent_slug,
                    'meta' => array()
                );
                
                // Include term meta if requested
                if ($include_term_meta) {
                    $meta_raw = get_term_meta($term->term_id);
                    $meta = array();
                    
                    foreach ($meta_raw as $meta_key => $meta_values) {
                        $meta_key = sanitize_key($meta_key);
                        
                        if (in_array($meta_key, $this->skip_meta)) {
                            continue;
                        }
                        if (strpos($meta_key, '_wp_') === 0) {
                            continue;
                        }
                        
                        foreach ($meta_values as $meta_value) {
                            $meta_value = maybe_unserialize($meta_value);
                            $meta_value = $this->spinexim_detect_media($meta_value, $media_map, $data);
                            $meta[$meta_key][] = $meta_value;
                        }
                    }
                    
                    $term_data['meta'] = $meta;
                }
                
                $data['terms'][] = $term_data;
                $term_map[$key] = true;
            }
        }
    }
    
    /**
     * Export posts data
     * 
     * @since 1.0.0
     * @param string $post_type Post type
     * @param bool $include_featured_media Include featured images
     * @param bool $include_post_meta Include post meta
     * @param bool $include_comments Include comments
     * @param bool $include_variations Include variations
     * @param array &$data Reference to data array
     * @param array &$media_map Reference to media map
     * @return void
     */
    private function spinexim_export_posts_data(
        $post_type,
        $include_featured_media,
        $include_post_meta,
        $include_comments,
        $include_variations,
        &$data,
        &$media_map
    ) {
        $query = new WP_Query(array(
            'post_type' => $post_type,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'no_found_rows' => true
        ));
        
        $taxes = get_object_taxonomies($post_type);
        
        while ($query->have_posts()) {
            $query->the_post();
            $post = get_post();
            
            $parent_slug = '';
            if ($post->post_parent) {
                $parent = get_post($post->post_parent);
                if ($parent) {
                    $parent_slug = sanitize_title($parent->post_name);
                }
            }
            
            // Featured image
            $featured = '';
            if ($include_featured_media && has_post_thumbnail($post->ID)) {
                $featured = wp_get_attachment_url(get_post_thumbnail_id($post->ID));
                $featured = esc_url_raw($featured);
                $this->spinexim_detect_media($featured, $media_map, $data);
            }
            
            // Post meta
            $meta = array();
            if ($include_post_meta) {
                $meta_raw = get_post_meta($post->ID);
                
                foreach ($meta_raw as $meta_key => $meta_values) {
                    $meta_key = sanitize_key($meta_key);
                    
                    if (in_array($meta_key, $this->skip_meta)) {
                        continue;
                    }
                    if (strpos($meta_key, '_wp_') === 0) {
                        continue;
                    }
                    
                    foreach ($meta_values as $meta_value) {
                        $meta_value = maybe_unserialize($meta_value);
                        $meta_value = $this->spinexim_detect_media($meta_value, $media_map, $data);
                        $meta[$meta_key][] = $meta_value;
                    }
                }
            }
            
            // Terms
            $terms = array();
            foreach ($taxes as $tax) {
                $post_terms = wp_get_post_terms($post->ID, $tax);
                if (!is_wp_error($post_terms)) {
                    foreach ($post_terms as $term) {
                        $terms[] = array(
                            'taxonomy' => sanitize_key($tax),
                            'slug' => sanitize_title($term->slug)
                        );
                    }
                }
            }
            
            // Comments
            $comment_data = array();
            if ($include_comments) {
                $comments = get_comments(array('post_id' => $post->ID));
                foreach ($comments as $comment) {
                    $comment_data[] = array(
                        'author' => sanitize_text_field($comment->comment_author),
                        'email' => sanitize_email($comment->comment_author_email),
                        'content' => sanitize_textarea_field($comment->comment_content),
                        'date' => sanitize_text_field($comment->comment_date)
                    );
                }
            }
            
            // Variations for WooCommerce products
            $variations = array();
            if ($include_variations && $post_type === 'product') {
                $children = get_posts(array(
                    'post_type' => 'product_variation',
                    'post_parent' => $post->ID,
                    'numberposts' => -1
                ));
                
                foreach ($children as $variation) {
                    $variation_meta = array();
                    $v_meta_raw = get_post_meta($variation->ID);
                    
                    foreach ($v_meta_raw as $meta_key => $meta_values) {
                        $meta_key = sanitize_key($meta_key);
                        
                        foreach ($meta_values as $meta_value) {
                            $meta_value = maybe_unserialize($meta_value);
                            $meta_value = $this->spinexim_detect_media($meta_value, $media_map, $data);
                            $variation_meta[$meta_key][] = $meta_value;
                        }
                    }
                    
                    $variations[] = array(
                        'slug' => sanitize_title($variation->post_name),
                        'meta' => $variation_meta
                    );
                }
            }
            
            // Build post data
            $post_data = array(
                'slug' => sanitize_title($post->post_name),
                'parent_slug' => $parent_slug,
                'title' => sanitize_text_field($post->post_title),
                'content' => wp_kses_post($post->post_content),
                'excerpt' => sanitize_textarea_field($post->post_excerpt),
                'status' => sanitize_key($post->post_status),
                'date' => sanitize_text_field($post->post_date),
                'featured' => $featured,
                'terms' => $terms,
                'meta' => $meta,
                'variations' => $variations,
                'comments' => $comment_data
            );
            
            $data['posts'][] = $post_data;
        }
        
        wp_reset_postdata();
    }
    
    /**
     * Detect and collect media URLs recursively
     * 
     * @since 1.0.0
     * @param mixed $value Value to check for media
     * @param array &$media_map Reference to media map
     * @param array &$data Reference to data array
     * @return mixed Processed value
     */
    private function spinexim_detect_media($value, &$media_map, &$data) {
        // Attachment ID
        if (is_numeric($value)) {
            $attachment_id = absint($value);
            $url = wp_get_attachment_url($attachment_id);
            if ($url) {
                $url = esc_url_raw($url);
                if (!isset($media_map[$url])) {
                    $media_map[$url] = $attachment_id;
                    
                    // Sanitize attachment meta
                    $attachment_meta = get_post_meta($attachment_id);
                    $sanitized_meta = array();
                    foreach ($attachment_meta as $meta_key => $meta_values) {
                        $sanitized_meta[sanitize_key($meta_key)] = $meta_values;
                    }
                    
                    $data['media'][] = array(
                        'url' => $url,
                        'meta' => $sanitized_meta
                    );
                }
                return $url;
            }
        }
        
        // WooCommerce gallery IDs (comma separated)
        if (is_string($value) && preg_match('/^\d+(,\d+)+$/', $value)) {
            $ids = array_map('absint', explode(',', $value));
            $urls = array();
            
            foreach ($ids as $id) {
                $url = wp_get_attachment_url($id);
                if ($url) {
                    $url = esc_url_raw($url);
                    $urls[] = $url;
                    if (!isset($media_map[$url])) {
                        $media_map[$url] = $id;
                        $data['media'][] = array(
                            'url' => $url,
                            'meta' => array()
                        );
                    }
                }
            }
            return $urls;
        }
        
        // Direct media URL
        if (is_string($value) && preg_match('/https?:\/\/.*\.(jpg|jpeg|png|gif|webp|svg|pdf|doc|docx|xls|xlsx|zip|mp4|mov|mp3|txt)(\?.*)?$/i', $value)) {
            $url = esc_url_raw($value);
            if (!isset($media_map[$url])) {
                $media_map[$url] = true;
                $data['media'][] = array(
                    'url' => $url,
                    'meta' => array()
                );
            }
            return $url;
        }
        
        // ACF image/file array
        if (is_array($value) && isset($value['url'])) {
            $url = esc_url_raw($value['url']);
            if (!isset($media_map[$url])) {
                $media_map[$url] = true;
                $data['media'][] = array(
                    'url' => $url,
                    'meta' => array()
                );
            }
            return $url;
        }
        
        // Recursive arrays
        if (is_array($value)) {
            foreach ($value as $key => $val) {
                $value[$key] = $this->spinexim_detect_media($val, $media_map, $data);
            }
        }
        
        return $value;
    }
    
    /**
     * Download JSON file
     * 
     * @since 1.0.0
     * @param array $data Export data
     * @param string $post_type Post type name
     * @return void
     */
    private function spinexim_download_json($data, $post_type) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . sanitize_title($post_type) . '-export.json"');
        
        // Clean up empty arrays for better readability
        if (empty($data['media'])) {
            unset($data['media']);
        }
        if (empty($data['terms'])) {
            unset($data['terms']);
        }
        
        echo wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}