<?php
/**
 * User export handler class
 * 
 * @since 1.0.0
 * @package Quil
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Quil_Export_Users {
    
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
    public function mc_quil_export_users($user_role, $include_user_meta) {
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
                'login' => $user->user_login,
                'email' => $user->user_email,
                'nicename' => $user->user_nicename,
                'display_name' => $user->display_name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'registered' => $user->user_registered,
                'roles' => $user->roles,
                'meta' => array()
            );
            
            // Include user meta if requested
            if ($include_user_meta) {
                $meta_raw = get_user_meta($user->ID);
                $meta = array();
                
                foreach ($meta_raw as $meta_key => $meta_values) {
                    if (in_array($meta_key, $this->skip_meta)) {
                        continue;
                    }
                    if (strpos($meta_key, '_wp_') === 0) {
                        continue;
                    }
                    
                    foreach ($meta_values as $meta_value) {
                        $meta_value = maybe_unserialize($meta_value);
                        $meta_value = $this->mc_quil_detect_media($meta_value, $media_map, $data);
                        $meta[$meta_key][] = $meta_value;
                    }
                }
                
                $user_data['meta'] = $meta;
            }
            
            $data['users'][] = $user_data;
        }
        
        // Download JSON
        $this->mc_quil_download_json($data);
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
    private function mc_quil_detect_media($value, &$media_map, &$data) {
        // Attachment ID
        if (is_numeric($value)) {
            $url = wp_get_attachment_url(intval($value));
            if ($url) {
                if (!isset($media_map[$url])) {
                    $media_map[$url] = $value;
                    $data['media'][] = array(
                        'url' => $url,
                        'meta' => get_post_meta(intval($value))
                    );
                }
                return $url;
            }
        }
        
        // WooCommerce gallery IDs
        if (is_string($value) && preg_match('/^\d+(,\d+)+$/', $value)) {
            $ids = array_map('intval', explode(',', $value));
            $urls = array();
            
            foreach ($ids as $id) {
                $url = wp_get_attachment_url($id);
                if ($url) {
                    $urls[] = $url;
                    if (!isset($media_map[$url])) {
                        $media_map[$url] = $id;
                        $data['media'][] = array(
                            'url' => $url,
                            'meta' => get_post_meta($id)
                        );
                    }
                }
            }
            return $urls;
        }
        
        // Direct media URL
        if (is_string($value) && preg_match('/https?:\/\/.*\.(jpg|jpeg|png|gif|webp|svg|pdf|doc|docx|xls|xlsx|zip|mp4|mov|mp3)(\?.*)?$/i', $value)) {
            if (!isset($media_map[$value])) {
                $media_map[$value] = true;
                $data['media'][] = array(
                    'url' => $value,
                    'meta' => array()
                );
            }
            return $value;
        }
        
        // ACF image/file array
        if (is_array($value) && isset($value['url'])) {
            $url = $value['url'];
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
                $value[$key] = $this->mc_quil_detect_media($val, $media_map, $data);
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
    private function mc_quil_download_json($data) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="users-export.json"');
        
        if (empty($data['media'])) {
            unset($data['media']);
        }
        
        echo wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}