<?php
/**
 * Post import handler class
 * 
 * @since 1.0.0
 * @package Quil
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Quil_Import {
    
    /**
     * Media map array
     * 
     * @var array
     */
    private $media_map = array();
    
    /**
     * Post map array
     * 
     * @var array
     */
    private $post_map = array();
    
    /**
     * Term map array
     * 
     * @var array
     */
    private $term_map = array();
    
    /**
     * Parent queue array
     * 
     * @var array
     */
    private $parent_queue = array();
    
    /**
     * Import posts from JSON file
     * 
     * @since 1.0.0
     * @param array $file Uploaded file array
     * @return void
     */
    public function mc_quil_import_posts($file) {
        ini_set('max_execution_time', 500);
        
        $json = file_get_contents($file['tmp_name']);
        $data = json_decode($json, true);
        
        if (!$data || !isset($data['post_type'])) {
            wp_die(__('Invalid JSON file format.', 'quil'));
        }
        
        $post_type = sanitize_text_field($data['post_type']);
        
        // Import media first
        if (isset($data['media']) && !empty($data['media'])) {
            foreach ($data['media'] as $media_item) {
                $url = esc_url_raw($media_item['url']);
                if (!isset($this->media_map[$url])) {
                    $this->media_map[$url] = $this->mc_quil_import_media($url);
                }
            }
        }
        
        // Import terms
        if (isset($data['terms']) && !empty($data['terms'])) {
            $this->mc_quil_import_terms($data['terms']);
        }
        
        // Import posts
        if (isset($data['posts']) && !empty($data['posts'])) {
            $this->mc_quil_import_posts_data($data['posts'], $post_type);
        }
        
        // Set parent relationships
        $this->mc_quil_set_parent_relationships();
        
        echo '<div class="notice notice-success"><p>' . esc_html__('Import Completed Successfully!', 'quil') . '</p></div>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=quil-posts')) . '" class="button button-primary">' . esc_html__('Back to Quil', 'quil') . '</a>';
        exit;
    }
    
    /**
     * Import media from URL
     * 
     * @since 1.0.0
     * @param string $url Media URL
     * @return int|string Attachment ID or original URL on failure
     */
    private function mc_quil_import_media($url) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        
        // Check if media already exists
        $existing_id = attachment_url_to_postid($url);
        if ($existing_id) {
            return $existing_id;
        }
        
        $tmp = download_url($url);
        
        if (is_wp_error($tmp)) {
            return $url;
        }
        
        $file_array = array(
            'name' => sanitize_file_name(basename($url)),
            'tmp_name' => $tmp
        );
        
        $id = media_handle_sideload($file_array, 0);
        
        if (is_wp_error($id)) {
            @unlink($tmp);
            return $url;
        }
        
        return $id;
    }
    
    /**
     * Convert media URLs to attachment IDs recursively
     * 
     * @since 1.0.0
     * @param mixed $value Value to convert
     * @return mixed Converted value
     */
    private function mc_quil_convert_media($value) {
        if (is_string($value) && preg_match('/https?:\/\/.*\.(jpg|jpeg|png|gif|webp|svg|pdf|doc|docx|xls|xlsx|zip|mp4|mov|mp3|txt)(\?.*)?$/i', $value)) {
            if (isset($this->media_map[$value])) {
                return $this->media_map[$value];
            }
            return $value;
        }
        
        if (is_array($value)) {
            foreach ($value as $key => $val) {
                $value[$key] = $this->mc_quil_convert_media($val);
            }
        }
        
        return $value;
    }
    
    /**
     * Import terms
     * 
     * @since 1.0.0
     * @param array $terms Terms data
     * @return void
     */
    private function mc_quil_import_terms($terms) {
        // Pass 1: Create all terms
        foreach ($terms as $term) {
            $taxonomy = sanitize_text_field($term['taxonomy']);
            $name = sanitize_text_field($term['name']);
            $slug = sanitize_title($term['slug']);
            
            $insert = wp_insert_term($name, $taxonomy, array('slug' => $slug));
            
            if (is_wp_error($insert)) {
                $existing = get_term_by('slug', $slug, $taxonomy);
                $term_id = $existing ? $existing->term_id : 0;
            } else {
                $term_id = $insert['term_id'];
            }
            
            $this->term_map[$slug] = $term_id;
        }
        
        // Pass 2: Set parent and meta
        foreach ($terms as $term) {
            $slug = sanitize_title($term['slug']);
            if (empty($this->term_map[$slug])) {
                continue;
            }
            
            $term_id = intval($this->term_map[$slug]);
            $taxonomy = sanitize_text_field($term['taxonomy']);
            
            // Set parent
            if (!empty($term['parent_slug']) && isset($this->term_map[$term['parent_slug']])) {
                $parent_id = intval($this->term_map[$term['parent_slug']]);
                wp_update_term($term_id, $taxonomy, array('parent' => $parent_id));
            }
            
            // Set term meta
            if (!empty($term['meta'])) {
                foreach ($term['meta'] as $meta_key => $meta_values) {
                    $meta_key = sanitize_key($meta_key);
                    foreach ($meta_values as $meta_value) {
                        $meta_value = $this->mc_quil_convert_media($meta_value);
                        update_term_meta($term_id, $meta_key, $meta_value);
                    }
                }
            }
        }
    }
    
    /**
     * Import posts data
     * 
     * @since 1.0.0
     * @param array $posts Posts data
     * @param string $post_type Post type
     * @return void
     */
    private function mc_quil_import_posts_data($posts, $post_type) {
        foreach ($posts as $post) {
            $post_data = array(
                'post_type' => $post_type,
                'post_title' => sanitize_text_field($post['title']),
                'post_name' => sanitize_title($post['slug']),
                'post_content' => wp_kses_post($post['content']),
                'post_excerpt' => sanitize_textarea_field($post['excerpt']),
                'post_status' => sanitize_text_field($post['status']),
                'post_date' => sanitize_text_field($post['date'])
            );
            
            $post_id = wp_insert_post($post_data);
            
            if (!$post_id) {
                continue;
            }
            
            $this->post_map[$post['slug']] = $post_id;
            
            // Store parent relation
            if (!empty($post['parent_slug'])) {
                $this->parent_queue[] = array(
                    'child' => $post['slug'],
                    'parent' => $post['parent_slug']
                );
            }
            
            // Set featured image
            if (!empty($post['featured'])) {
                $media_id = $this->mc_quil_convert_media($post['featured']);
                if (is_numeric($media_id)) {
                    set_post_thumbnail($post_id, intval($media_id));
                }
            }
            
            // Set post terms
            if (!empty($post['terms'])) {
                foreach ($post['terms'] as $term_data) {
                    $taxonomy = sanitize_text_field($term_data['taxonomy']);
                    $slug = sanitize_title($term_data['slug']);
                    
                    if (isset($this->term_map[$slug])) {
                        wp_set_object_terms($post_id, intval($this->term_map[$slug]), $taxonomy, false);
                    }
                }
            }
            
            // Set post meta
            if (!empty($post['meta'])) {
                foreach ($post['meta'] as $meta_key => $meta_values) {
                    $meta_key = sanitize_key($meta_key);
                    foreach ($meta_values as $meta_value) {
                        $meta_value = $this->mc_quil_convert_media($meta_value);
                        
                        // Handle WooCommerce gallery
                        if ($meta_key === '_product_image_gallery' && is_array($meta_value)) {
                            $meta_value = implode(',', array_map('intval', $meta_value));
                        }
                        
                        update_post_meta($post_id, $meta_key, $meta_value);
                    }
                }
            }
            
            // Import variations
            if (!empty($post['variations'])) {
                $this->mc_quil_import_variations($post['variations'], $post_id);
            }
            
            // Import comments
            if (!empty($post['comments'])) {
                $this->mc_quil_import_comments($post['comments'], $post_id);
            }
        }
    }
    
    /**
     * Import product variations
     * 
     * @since 1.0.0
     * @param array $variations Variations data
     * @param int $parent_id Parent post ID
     * @return void
     */
    private function mc_quil_import_variations($variations, $parent_id) {
        foreach ($variations as $variation) {
            $variation_id = wp_insert_post(array(
                'post_type' => 'product_variation',
                'post_status' => 'publish',
                'post_parent' => intval($parent_id),
                'post_name' => sanitize_title($variation['slug'])
            ));
            
            if (!$variation_id) {
                continue;
            }
            
            if (!empty($variation['meta'])) {
                foreach ($variation['meta'] as $meta_key => $meta_values) {
                    $meta_key = sanitize_key($meta_key);
                    foreach ($meta_values as $meta_value) {
                        $meta_value = $this->mc_quil_convert_media($meta_value);
                        update_post_meta($variation_id, $meta_key, $meta_value);
                    }
                }
            }
        }
    }
    
    /**
     * Import comments
     * 
     * @since 1.0.0
     * @param array $comments Comments data
     * @param int $post_id Post ID
     * @return void
     */
    private function mc_quil_import_comments($comments, $post_id) {
        foreach ($comments as $comment) {
            wp_insert_comment(array(
                'comment_post_ID' => intval($post_id),
                'comment_author' => sanitize_text_field($comment['author']),
                'comment_author_email' => sanitize_email($comment['email']),
                'comment_content' => sanitize_textarea_field($comment['content']),
                'comment_date' => sanitize_text_field($comment['date'])
            ));
        }
    }
    
    /**
     * Set parent relationships for posts
     * 
     * @since 1.0.0
     * @return void
     */
    private function mc_quil_set_parent_relationships() {
        foreach ($this->parent_queue as $relation) {
            if (isset($this->post_map[$relation['child']]) && isset($this->post_map[$relation['parent']])) {
                wp_update_post(array(
                    'ID' => intval($this->post_map[$relation['child']]),
                    'post_parent' => intval($this->post_map[$relation['parent']])
                ));
            }
        }
    }
}