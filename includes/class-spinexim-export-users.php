<?php
/**
 * User export handler class
 * 
 * @since 1.0.0
 * @package Spinda
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Spinexim_Export_Users {
    
    /**
     * Skip meta keys array
     * 
     * @var array
     */
    private $skip_meta = array('_edit_lock', '_edit_last');
    
    /**
     * Export users
     * 
     * @since 1.0.0
     * @param string $user_role User role to export (or 'all')
     * @param bool $include_user_meta Include user meta
     * @return void
     */
    public function spinexim_export_users($user_role, $include_user_meta) {
        $data = array(
            'media' => array(),
            'users' => array()
        );
        
        $media_map = array();
        
        // Build user query args
        $args = array('number' => -1);
        if ($user_role !== 'all') {
            $args['role'] = sanitize_text_field($user_role);
        }
        
        $users = get_users($args);
        
        foreach ($users as $user) {
            $user_data = array(
                'login' => sanitize_user($user->user_login),
                'email' => sanitize_email($user->user_email),
                'nicename' => sanitize_title($user->user_nicename),
                'display_name' => sanitize_text_field($user->display_name),
                'first_name' => sanitize_text_field($user->first_name),
                'last_name' => sanitize_text_field($user->last_name),
                'registered' => sanitize_text_field($user->user_registered),
                'roles' => array_map('sanitize_text_field', $user->roles),
                'meta' => array()
            );
            
            // Include user meta if requested
            if ($include_user_meta) {
                $meta_raw = get_user_meta($user->ID);
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
                
                $user_data['meta'] = $meta;
            }
            
            $data['users'][] = $user_data;
        }
        
        // Download JSON
        $this->spinexim_download_json($data);
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
                    $data['media'][] = array(
                        'url' => $url,
                        'meta' => array()
                    );
                }
                return $url;
            }
        }
        
        // WooCommerce gallery IDs
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
        if (is_string($value) && preg_match('/https?:\/\/.*\.(jpg|jpeg|png|gif|webp|svg|pdf|doc|docx|xls|xlsx|zip|mp4|mov|mp3)(\?.*)?$/i', $value)) {
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
     * @return void
     */
    private function spinexim_download_json($data) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="users-export.json"');
        
        if (empty($data['media'])) {
            unset($data['media']);
        }
        
        echo wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}