<?php
/**
 * User import handler class
 * 
 * @since 1.0.0
 * @package Spinda
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Spinexim_Import_Users {
    
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
    public function spinexim_import_users($file) {
        // Validate file
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            wp_die(__('Invalid file upload.', 'spinda-exportimport-data'));
        }
        
        // Check file extension
        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (strtolower($file_ext) !== 'json') {
            wp_die(__('Only JSON files are allowed.', 'spinda-exportimport-data'));
        }
        
        $json_content = file_get_contents($file['tmp_name']);
        if ($json_content === false) {
            wp_die(__('Failed to read file content.', 'spinda-exportimport-data'));
        }
        
        $data = json_decode($json_content, true);
        
        if (!$data || !isset($data['users'])) {
            wp_die(__('Invalid JSON file format.', 'spinda-exportimport-data'));
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
        
        $created = 0;
        $skipped = 0;
        
        foreach ($data['users'] as $user) {
            $login = isset($user['login']) ? sanitize_user($user['login']) : '';
            $email = isset($user['email']) ? sanitize_email($user['email']) : '';
            
            if (empty($login) || empty($email)) {
                $skipped++;
                continue;
            }
            
            // Check if user already exists
            if (username_exists($login) || email_exists($email)) {
                $skipped++;
                continue;
            }
            
            $password = wp_generate_password();
            
            $user_data = array(
                'user_login' => $login,
                'user_email' => $email,
                'display_name' => isset($user['display_name']) ? sanitize_text_field($user['display_name']) : $login,
                'first_name' => isset($user['first_name']) ? sanitize_text_field($user['first_name']) : '',
                'last_name' => isset($user['last_name']) ? sanitize_text_field($user['last_name']) : '',
                'user_pass' => $password,
                'role' => isset($user['roles'][0]) ? sanitize_text_field($user['roles'][0]) : 'subscriber'
            );
            
            $user_id = wp_insert_user($user_data);
            
            if (is_wp_error($user_id)) {
                continue;
            }
            
            $user_id = absint($user_id);
            
            // Import user meta
            if (!empty($user['meta']) && is_array($user['meta'])) {
                foreach ($user['meta'] as $meta_key => $meta_values) {
                    $meta_key = sanitize_key($meta_key);
                    if (is_array($meta_values)) {
                        foreach ($meta_values as $meta_value) {
                            $meta_value = $this->spinexim_convert_media($meta_value);
                            add_user_meta($user_id, $meta_key, $meta_value);
                        }
                    }
                }
            }
            
            $created++;
        }
        
        // Show results
        echo '<div class="notice notice-success">';
        echo '<p>' . esc_html__('Import Finished!', 'spinda-exportimport-data') . '</p>';
        echo '<p>' . sprintf(esc_html__('Users Created: %d', 'spinda-exportimport-data'), intval($created)) . '</p>';
        echo '<p>' . sprintf(esc_html__('Users Skipped (existing): %d', 'spinda-exportimport-data'), intval($skipped)) . '</p>';
        echo '</div>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=spinexim-users')) . '" class="button button-primary">' . esc_html__('Back to Spinda', 'spinda-exportimport-data') . '</a>';
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
     * Convert media URLs to attachment IDs
     * 
     * @since 1.0.0
     * @param mixed $value Value to convert
     * @return mixed Converted value
     */
    private function spinexim_convert_media($value) {
        if (is_string($value) && preg_match('/https?:\/\/.*\.(jpg|jpeg|png|gif|webp|svg|pdf|doc|docx|xls|xlsx|zip|mp4|mov|mp3)/i', $value)) {
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
}