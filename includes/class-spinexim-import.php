<?php
/**
 * Post import handler class
 * 
 * @since 1.0.0
 * @package Spinda
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Spinexim_Import {
    
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
    public function spinexim_import_posts($file) {
        // Validate file
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            wp_die(__('Invalid file upload.', 'spinda-exportimport-data'));
        }
        
        // Check file extension
        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (strtolower($file_ext) !== 'json') {
            wp_die(__('Only JSON files are allowed.', 'spinda-exportimport-data'));
        }
        
        ini_set('max_execution_time', 500);
        
        $json_content = file_get_contents($file['tmp_name']);
        if ($json_content === false) {
            wp_die(__('Failed to read file content.', 'spinda-exportimport-data'));
        }
        
        $data = json_decode($json_content, true);
        
        if (!$data || !isset($data['post_type'])) {
            wp_die(__('Invalid JSON file format.', 'spinda-exportimport-data'));
        }
        
        $post_type = sanitize_text_field($data['post_type']);
        
        // Validate post type exists
        $post_type_obj = get_post_type_object($post_type);
        if (!$post_type_obj) {
            wp_die(__('Invalid post type in import file.', 'spinda-exportimport-data'));
        }
        
        // Import media first
        if (isset($data['media']) && !empty($data['media'])) {
            foreach ($data['media'] as $media_item) {
                if (isset($media_item['url'])) {
                    $url = esc_url_raw($media_item['url']);
                    if (!empty($url) && !isset($this->media_map[$url])) {
                        $this->media_map[$url] = $this->spinexim_import_media($url);
                    }
                }
            }
        }
        
        // Import terms
        if (isset($data['terms']) && !empty($data['terms'])) {
            $this->spinexim_import_terms($data['terms']);
        }
        
        // Import posts
        if (isset($data['posts']) && !empty($data['posts'])) {
            $this->spinexim_import_posts_data($data['posts'], $post_type);
        }
        
        // Set parent relationships
        $this->spinexim_set_parent_relationships();
        
        echo '<div class="notice notice-success"><p>' . esc_html__('Import Completed Successfully!', 'spinda-exportimport-data') . '</p></div>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=spinexim-posts')) . '" class="button button-primary">' . esc_html__('Back to Spinda', 'spinda-exportimport-data') . '</a>';
        exit;
    }
    
    /**
     * Import media from URL
     * 
     * @since 1.0.0
     * @param string $url Media URL
     * @return int|string Attachment ID or original URL on failure
     */
    private function spinexim_import_media($url) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        
        // Check if media already exists
        $existing_id = attachment_url_to_postid($url);
        if ($existing_id) {
            return absint($existing_id);
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
        
        return absint($id);
    }
    
    /**
     * Convert media URLs to attachment IDs recursively
     * 
     * @since 1.0.0
     * @param mixed $value Value to convert
     * @return mixed Converted value
     */
    private function spinexim_convert_media($value) {
        if (is_string($value) && preg_match('/https?:\/\/.*\.(jpg|jpeg|png|gif|webp|svg|pdf|doc|docx|xls|xlsx|zip|mp4|mov|mp3|txt)(\?.*)?$/i', $value)) {
            if (isset($this->media_map[$value])) {
                return $this->media_map[$value];
            }
            return $value;
        }
        
        if (is_array($value)) {
            foreach ($value as $key => $val) {
                $value[$key] = $this->spinexim_convert_media($val);
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
    private function spinexim_import_terms($terms) {
        // Pass 1: Create all terms
        foreach ($terms as $term) {
            $taxonomy = sanitize_key($term['taxonomy']);
            
            // Check if taxonomy exists
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }
            
            $name = sanitize_text_field($term['name']);
            $slug = sanitize_title($term['slug']);
            
            $insert = wp_insert_term($name, $taxonomy, array('slug' => $slug));
            
            if (is_wp_error($insert)) {
                $existing = get_term_by('slug', $slug, $taxonomy);
                $term_id = $existing ? absint($existing->term_id) : 0;
            } else {
                $term_id = absint($insert['term_id']);
            }
            
            if ($term_id > 0) {
                $this->term_map[$slug] = $term_id;
            }
        }
        
        // Pass 2: Set parent and meta
        foreach ($terms as $term) {
            $slug = sanitize_title($term['slug']);
            if (empty($this->term_map[$slug])) {
                continue;
            }
            
            $term_id = absint($this->term_map[$slug]);
            $taxonomy = sanitize_key($term['taxonomy']);
            
            // Set parent
            if (!empty($term['parent_slug']) && isset($this->term_map[$term['parent_slug']])) {
                $parent_id = absint($this->term_map[$term['parent_slug']]);
                wp_update_term($term_id, $taxonomy, array('parent' => $parent_id));
            }
            
            // Set term meta
            if (!empty($term['meta']) && is_array($term['meta'])) {
                foreach ($term['meta'] as $meta_key => $meta_values) {
                    $meta_key = sanitize_key($meta_key);
                    if (is_array($meta_values)) {
                        foreach ($meta_values as $meta_value) {
                            $meta_value = $this->spinexim_convert_media($meta_value);
                            update_term_meta($term_id, $meta_key, $meta_value);
                        }
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
    private function spinexim_import_posts_data($posts, $post_type) {
        foreach ($posts as $post) {
            $post_data = array(
                'post_type' => $post_type,
                'post_title' => isset($post['title']) ? sanitize_text_field($post['title']) : '',
                'post_name' => isset($post['slug']) ? sanitize_title($post['slug']) : '',
                'post_content' => isset($post['content']) ? wp_kses_post($post['content']) : '',
                'post_excerpt' => isset($post['excerpt']) ? sanitize_textarea_field($post['excerpt']) : '',
                'post_status' => isset($post['status']) ? sanitize_key($post['status']) : 'draft',
                'post_date' => isset($post['date']) ? sanitize_text_field($post['date']) : current_time('mysql')
            );
            
            $post_id = wp_insert_post($post_data, true);
            
            if (is_wp_error($post_id) || !$post_id) {
                continue;
            }
            
            $post_id = absint($post_id);
            
            if (isset($post['slug'])) {
                $this->post_map[sanitize_title($post['slug'])] = $post_id;
            }
            
            // Store parent relation
            if (!empty($post['parent_slug'])) {
                $this->parent_queue[] = array(
                    'child' => sanitize_title($post['slug']),
                    'parent' => sanitize_title($post['parent_slug'])
                );
            }
            
            // Set featured image
            if (!empty($post['featured'])) {
                $media_id = $this->spinexim_convert_media($post['featured']);
                if (is_numeric($media_id)) {
                    set_post_thumbnail($post_id, absint($media_id));
                }
            }
            
            // Set post terms
            if (!empty($post['terms']) && is_array($post['terms'])) {
                foreach ($post['terms'] as $term_data) {
                    $taxonomy = sanitize_key($term_data['taxonomy']);
                    $slug = sanitize_title($term_data['slug']);
                    
                    if (isset($this->term_map[$slug])) {
                        wp_set_object_terms($post_id, absint($this->term_map[$slug]), $taxonomy, true);
                    }
                }
            }
            
            // Set post meta
            if (!empty($post['meta']) && is_array($post['meta'])) {
                foreach ($post['meta'] as $meta_key => $meta_values) {
                    $meta_key = sanitize_key($meta_key);
                    if (is_array($meta_values)) {
                        // Delete existing meta first to avoid duplicates
                        delete_post_meta($post_id, $meta_key);
                        
                        foreach ($meta_values as $meta_value) {
                            $meta_value = $this->spinexim_convert_media($meta_value);
                            
                            // Handle WooCommerce gallery
                            if ($meta_key === '_product_image_gallery' && is_array($meta_value)) {
                                $meta_value = implode(',', array_map('absint', $meta_value));
                            }
                            
                            add_post_meta($post_id, $meta_key, $meta_value);
                        }
                    }
                }
            }
            
            // Import variations
            if (!empty($post['variations']) && is_array($post['variations'])) {
                $this->spinexim_import_variations($post['variations'], $post_id);
            }
            
            // Import comments
            if (!empty($post['comments']) && is_array($post['comments'])) {
                $this->spinexim_import_comments($post['comments'], $post_id);
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
    private function spinexim_import_variations($variations, $parent_id) {
        foreach ($variations as $variation) {
            $variation_id = wp_insert_post(array(
                'post_type' => 'product_variation',
                'post_status' => 'publish',
                'post_parent' => absint($parent_id),
                'post_name' => isset($variation['slug']) ? sanitize_title($variation['slug']) : ''
            ));
            
            if (is_wp_error($variation_id) || !$variation_id) {
                continue;
            }
            
            $variation_id = absint($variation_id);
            
            if (!empty($variation['meta']) && is_array($variation['meta'])) {
                foreach ($variation['meta'] as $meta_key => $meta_values) {
                    $meta_key = sanitize_key($meta_key);
                    if (is_array($meta_values)) {
                        foreach ($meta_values as $meta_value) {
                            $meta_value = $this->spinexim_convert_media($meta_value);
                            update_post_meta($variation_id, $meta_key, $meta_value);
                        }
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
    private function spinexim_import_comments($comments, $post_id) {
        foreach ($comments as $comment) {
            wp_insert_comment(array(
                'comment_post_ID' => absint($post_id),
                'comment_author' => isset($comment['author']) ? sanitize_text_field($comment['author']) : '',
                'comment_author_email' => isset($comment['email']) ? sanitize_email($comment['email']) : '',
                'comment_content' => isset($comment['content']) ? sanitize_textarea_field($comment['content']) : '',
                'comment_date' => isset($comment['date']) ? sanitize_text_field($comment['date']) : current_time('mysql')
            ));
        }
    }
    
    /**
     * Set parent relationships for posts
     * 
     * @since 1.0.0
     * @return void
     */
    private function spinexim_set_parent_relationships() {
        foreach ($this->parent_queue as $relation) {
            if (isset($this->post_map[$relation['child']]) && isset($this->post_map[$relation['parent']])) {
                wp_update_post(array(
                    'ID' => absint($this->post_map[$relation['child']]),
                    'post_parent' => absint($this->post_map[$relation['parent']])
                ));
            }
        }
    }
}