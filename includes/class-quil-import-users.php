<?php
/**
 * User import handler class
 * 
 * @since 1.0.0
 * @package Quil
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Quil_Import_Users {
    
    /**
     * Media map array
     * 
     * @var array
     */
    private $media_map = array();
    
    /**
     * Import users from JSON file
     * 
     * @since 1.0.0
     * @param array $file Uploaded file array
     * @return void
     */
    public function mc_quil_import_users($file) {
        $json = file_get_contents($file['tmp_name']);
        $data = json_decode($json, true);
        
        if (!$data || !isset($data['users'])) {
            wp_die(__('Invalid JSON file format.', 'quil'));
        }
        
        // Import media first
        if (isset($data['media']) && !empty($data['media'])) {
            foreach ($data['media'] as $media_item) {
                $url = esc_url_raw($media_item['url']);
                if (!isset($this->media_map[$url])) {
                    $this->media_map[$url] = $this->mc_quil_import_media($url);
                }
            }
        }
        
        $created = 0;
        $skipped = 0;
        
        foreach ($data['users'] as $user) {
            $login = sanitize_user($user['login']);
            $email = sanitize_email($user['email']);
            
            // Check if user already exists
            if (username_exists($login) || email_exists($email)) {
                $skipped++;
                continue;
            }
            
            $password = wp_generate_password();
            
            $user_data = array(
                'user_login' => $login,
                'user_email' => $email,
                'display_name' => sanitize_text_field($user['display_name']),
                'first_name' => sanitize_text_field($user['first_name']),
                'last_name' => sanitize_text_field($user['last_name']),
                'user_pass' => $password,
                'role' => isset($user['roles'][0]) ? sanitize_text_field($user['roles'][0]) : 'subscriber'
            );
            
            $user_id = wp_insert_user($user_data);
            
            if (is_wp_error($user_id)) {
                continue;
            }
            
            // Import user meta
            if (!empty($user['meta'])) {
                foreach ($user['meta'] as $meta_key => $meta_values) {
                    $meta_key = sanitize_key($meta_key);
                    foreach ($meta_values as $meta_value) {
                        $meta_value = $this->mc_quil_convert_media($meta_value);
                        add_user_meta($user_id, $meta_key, $meta_value);
                    }
                }
            }
            
            $created++;
        }
        
        // Show results
        echo '<div class="notice notice-success">';
        echo '<p>' . esc_html__('Import Finished!', 'quil') . '</p>';
        echo '<p>' . sprintf(esc_html__('Users Created: %d', 'quil'), intval($created)) . '</p>';
        echo '<p>' . sprintf(esc_html__('Users Skipped (existing): %d', 'quil'), intval($skipped)) . '</p>';
        echo '</div>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=quil-users')) . '" class="button button-primary">' . esc_html__('Back to Quil', 'quil') . '</a>';
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
     * Convert media URLs to attachment IDs
     * 
     * @since 1.0.0
     * @param mixed $value Value to convert
     * @return mixed Converted value
     */
    private function mc_quil_convert_media($value) {
        if (is_string($value) && preg_match('/https?:\/\/.*\.(jpg|jpeg|png|gif|webp|svg|pdf|doc|docx|xls|xlsx|zip|mp4|mov|mp3)/i', $value)) {
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
}