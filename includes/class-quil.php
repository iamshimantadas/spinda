<?php
/**
 * Plugin main class file
 * 
 * @since 1.0.0
 * @package Quil
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Quil {
    
    /**
     * Singleton instance
     * 
     * @var Quil
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     * 
     * @since 1.0.0
     * @return Quil
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     * 
     * @since 1.0.0
     */
    private function __construct() {
        $this->mc_quil_init_hooks();
    }
    
    /**
     * Initialize all hooks
     * 
     * @since 1.0.0
     * @return void
     */
    private function mc_quil_init_hooks() {
        add_action('admin_menu', array($this, 'mc_quil_add_admin_menus'));
        add_action('admin_enqueue_scripts', array($this, 'mc_quil_enqueue_assets'));
        add_action('admin_post_mc_quil_export_posts', array($this, 'mc_quil_handle_post_export'));
        add_action('admin_post_mc_quil_export_users', array($this, 'mc_quil_handle_user_export'));
        add_action('admin_post_mc_quil_import_posts', array($this, 'mc_quil_handle_post_import'));
        add_action('admin_post_mc_quil_import_users', array($this, 'mc_quil_handle_user_import'));
    }
    
    /**
     * Add admin menus
     * 
     * @since 1.0.0
     * @return void
     */
    public function mc_quil_add_admin_menus() {
        add_menu_page(
            __('Quil Export/Import', 'quil'),
            __('Quil', 'quil'),
            'manage_options',
            'quil-posts',
            array($this, 'mc_quil_render_posts_page'),
            QUIL_PLUGIN_URL . 'assets/icon.png',
            30
        );
        
        add_submenu_page(
            'quil-posts',
            __('Posts Export/Import', 'quil'),
            __('Posts', 'quil'),
            'manage_options',
            'quil-posts',
            array($this, 'mc_quil_render_posts_page')
        );
        
        add_submenu_page(
            'quil-posts',
            __('Users Export/Import', 'quil'),
            __('Users', 'quil'),
            'manage_options',
            'quil-users',
            array($this, 'mc_quil_render_users_page')
        );
    }
    
    /**
     * Enqueue admin assets
     * 
     * @since 1.0.0
     * @param string $hook Current page hook
     * @return void
     */
    public function mc_quil_enqueue_assets($hook) {
        if (strpos($hook, 'quil-posts') === false && strpos($hook, 'quil-users') === false) {
            return;
        }
        
        wp_enqueue_style(
            'quil-admin-css',
            QUIL_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            QUIL_VERSION
        );
        
        wp_enqueue_script(
            'quil-admin-js',
            QUIL_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            QUIL_VERSION,
            true
        );
        
        wp_localize_script('quil-admin-js', 'mc_quil_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mc_quil_ajax_nonce')
        ));
    }
    
    /**
     * Render posts admin page with tabs
     * 
     * @since 1.0.0
     * @return void
     */
    public function mc_quil_render_posts_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'quil'));
        }
        
        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'export';
        ?>
        <div class="wrap quil-container">
            <h1><?php esc_html_e('Quil - Posts Export/Import', 'quil'); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=quil-posts&tab=export" class="nav-tab <?php echo $active_tab === 'export' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Export', 'quil'); ?>
                </a>
                <a href="?page=quil-posts&tab=import" class="nav-tab <?php echo $active_tab === 'import' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Import', 'quil'); ?>
                </a>
            </h2>
            
            <div class="quil-tab-content">
                <?php
                if ($active_tab === 'export') {
                    $this->mc_quil_render_post_export_form();
                } else {
                    $this->mc_quil_render_post_import_form();
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render post export form
     * 
     * @since 1.0.0
     * @return void
     */
    private function mc_quil_render_post_export_form() {
        $post_types = get_post_types(array('public' => true), 'objects');
        $taxonomies = get_taxonomies(array('public' => true), 'objects');
        ?>
        <div class="quil-card">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('mc_quil_post_export_action', 'mc_quil_post_export_nonce'); ?>
                <input type="hidden" name="action" value="mc_quil_export_posts">
                
                <div class="quil-form-group">
                    <label for="post_type"><?php esc_html_e('Select Post Type', 'quil'); ?></label>
                    <select name="post_type" id="post_type" class="quil-select" required>
                        <option value=""><?php esc_html_e('-- Select Post Type --', 'quil'); ?></option>
                        <?php foreach ($post_types as $post_type): 
                            if($post_type->name !== "attachment"){ ?>
                            <option value="<?php echo esc_attr($post_type->name); ?>">
                                <?php echo esc_html($post_type->label); ?>
                            </option>
                            <?php } ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="quil-form-group">
                    <label><?php esc_html_e('Taxonomies', 'quil'); ?></label>
                    <label class="quil-checkbox-label">
                        <input type="checkbox" name="include_taxonomies" value="1" checked>
                        <?php esc_html_e('Include Taxonomies & Terms', 'quil'); ?>
                    </label>
                </div>
                
                <div class="quil-form-group">
                    <label><?php esc_html_e('Taxonomy Meta', 'quil'); ?></label>
                    <label class="quil-checkbox-label">
                        <input type="checkbox" name="include_term_meta" value="1" checked>
                        <?php esc_html_e('Include Term Meta', 'quil'); ?>
                    </label>
                </div>
                
                <div class="quil-form-group">
                    <label><?php esc_html_e('Featured Media', 'quil'); ?></label>
                    <label class="quil-checkbox-label">
                        <input type="checkbox" name="include_featured_media" value="1" checked>
                        <?php esc_html_e('Include Featured Images', 'quil'); ?>
                    </label>
                </div>
                
                <div class="quil-form-group">
                    <label><?php esc_html_e('Post Meta', 'quil'); ?></label>
                    <label class="quil-checkbox-label">
                        <input type="checkbox" name="include_post_meta" value="1" checked>
                        <?php esc_html_e('Include Post Meta', 'quil'); ?>
                    </label>
                </div>
                
                <div class="quil-form-group">
                    <label><?php esc_html_e('Comments', 'quil'); ?></label>
                    <label class="quil-checkbox-label">
                        <input type="checkbox" name="include_comments" value="1">
                        <?php esc_html_e('Include Comments', 'quil'); ?>
                    </label>
                </div>
                
                <div class="quil-form-group">
                    <label><?php esc_html_e('Variations (WooCommerce)', 'quil'); ?></label>
                    <label class="quil-checkbox-label">
                        <input type="checkbox" name="include_variations" value="1" checked>
                        <?php esc_html_e('Include Product Variations', 'quil'); ?>
                    </label>
                </div>
                
                <button type="submit" class="button button-primary"><?php esc_html_e('Export Now', 'quil'); ?></button>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render post import form
     * 
     * @since 1.0.0
     * @return void
     */
    private function mc_quil_render_post_import_form() {
        ?>
        <div class="quil-card">
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('mc_quil_post_import_action', 'mc_quil_post_import_nonce'); ?>
                <input type="hidden" name="action" value="mc_quil_import_posts">
                
                <div class="quil-form-group">
                    <label for="json_file"><?php esc_html_e('Select JSON File', 'quil'); ?></label>
                    <input type="file" name="json_file" id="json_file" class="quil-file-input" accept=".json" required>
                    <p class="description"><?php esc_html_e('Upload the JSON file exported from Quil plugin.', 'quil'); ?></p>
                </div>
                
                <button type="submit" class="button button-primary"><?php esc_html_e('Import Now', 'quil'); ?></button>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render users admin page with tabs
     * 
     * @since 1.0.0
     * @return void
     */
    public function mc_quil_render_users_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'quil'));
        }
        
        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'export';
        ?>
        <div class="wrap quil-container">
            <h1><?php esc_html_e('Quil - Users Export/Import', 'quil'); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=quil-users&tab=export" class="nav-tab <?php echo $active_tab === 'export' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Export', 'quil'); ?>
                </a>
                <a href="?page=quil-users&tab=import" class="nav-tab <?php echo $active_tab === 'import' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Import', 'quil'); ?>
                </a>
            </h2>
            
            <div class="quil-tab-content">
                <?php
                if ($active_tab === 'export') {
                    $this->mc_quil_render_user_export_form();
                } else {
                    $this->mc_quil_render_user_import_form();
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render user export form
     * 
     * @since 1.0.0
     * @return void
     */
    private function mc_quil_render_user_export_form() {
        global $wp_roles;
        $roles = $wp_roles->get_names();
        ?>
        <div class="quil-card">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('mc_quil_user_export_action', 'mc_quil_user_export_nonce'); ?>
                <input type="hidden" name="action" value="mc_quil_export_users">
                
                <div class="quil-form-group">
                    <label for="user_role"><?php esc_html_e('Select User Role', 'quil'); ?></label>
                    <select name="user_role" id="user_role" class="quil-select">
                        <option value="all"><?php esc_html_e('-- All Roles --', 'quil'); ?></option>
                        <?php foreach ($roles as $role_key => $role_name): ?>
                            <option value="<?php echo esc_attr($role_key); ?>">
                                <?php echo esc_html($role_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e('Select specific role or export all users.', 'quil'); ?></p>
                </div>
                
                <div class="quil-form-group">
                    <label><?php esc_html_e('User Meta', 'quil'); ?></label>
                    <label class="quil-checkbox-label">
                        <input type="checkbox" name="include_user_meta" value="1" checked>
                        <?php esc_html_e('Include User Meta Data', 'quil'); ?>
                    </label>
                </div>
                
                <button type="submit" class="button button-primary"><?php esc_html_e('Export Users', 'quil'); ?></button>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render user import form
     * 
     * @since 1.0.0
     * @return void
     */
    private function mc_quil_render_user_import_form() {
        ?>
        <div class="quil-card">
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('mc_quil_user_import_action', 'mc_quil_user_import_nonce'); ?>
                <input type="hidden" name="action" value="mc_quil_import_users">
                
                <div class="quil-form-group">
                    <label for="json_file"><?php esc_html_e('Select JSON File', 'quil'); ?></label>
                    <input type="file" name="json_file" id="json_file" class="quil-file-input" accept=".json" required>
                    <p class="description"><?php esc_html_e('Upload the users JSON file exported from Quil plugin.', 'quil'); ?></p>
                </div>
                
                <button type="submit" class="button button-primary"><?php esc_html_e('Import Users', 'quil'); ?></button>
            </form>
        </div>
        <?php
    }
    
    /**
     * Handle post export request
     * 
     * @since 1.0.0
     * @return void
     */
    public function mc_quil_handle_post_export() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access.', 'quil'));
        }
        
        if (!isset($_POST['mc_quil_post_export_nonce']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mc_quil_post_export_nonce'])), 'mc_quil_post_export_action')) {
            wp_die(__('Security check failed.', 'quil'));
        }
        
        $post_type = isset($_POST['post_type']) ? sanitize_text_field(wp_unslash($_POST['post_type'])) : 'post';
        $include_taxonomies = isset($_POST['include_taxonomies']);
        $include_term_meta = isset($_POST['include_term_meta']);
        $include_featured_media = isset($_POST['include_featured_media']);
        $include_post_meta = isset($_POST['include_post_meta']);
        $include_comments = isset($_POST['include_comments']);
        $include_variations = isset($_POST['include_variations']);
        
        require_once QUIL_PLUGIN_DIR . 'includes/class-quil-export.php';
        
        $export = new Quil_Export();
        $export->mc_quil_export_posts(
            $post_type,
            $include_taxonomies,
            $include_term_meta,
            $include_featured_media,
            $include_post_meta,
            $include_comments,
            $include_variations
        );
    }
    
    /**
     * Handle user export request
     * 
     * @since 1.0.0
     * @return void
     */
    public function mc_quil_handle_user_export() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access.', 'quil'));
        }
        
        if (!isset($_POST['mc_quil_user_export_nonce']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mc_quil_user_export_nonce'])), 'mc_quil_user_export_action')) {
            wp_die(__('Security check failed.', 'quil'));
        }
        
        $user_role = isset($_POST['user_role']) ? sanitize_text_field(wp_unslash($_POST['user_role'])) : 'all';
        $include_user_meta = isset($_POST['include_user_meta']);
        
        require_once QUIL_PLUGIN_DIR . 'includes/class-quil-export-users.php';
        
        $export = new Quil_Export_Users();
        $export->mc_quil_export_users($user_role, $include_user_meta);
    }
    
    /**
     * Handle post import request
     * 
     * @since 1.0.0
     * @return void
     */
    public function mc_quil_handle_post_import() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access.', 'quil'));
        }
        
        if (!isset($_POST['mc_quil_post_import_nonce']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mc_quil_post_import_nonce'])), 'mc_quil_post_import_action')) {
            wp_die(__('Security check failed.', 'quil'));
        }
        
        if (!isset($_FILES['json_file']) || $_FILES['json_file']['error'] !== UPLOAD_ERR_OK) {
            wp_die(__('File upload failed.', 'quil'));
        }
        
        require_once QUIL_PLUGIN_DIR . 'includes/class-quil-import.php';
        
        $import = new Quil_Import();
        $import->mc_quil_import_posts($_FILES['json_file']);
    }
    
    /**
     * Handle user import request
     * 
     * @since 1.0.0
     * @return void
     */
    public function mc_quil_handle_user_import() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access.', 'quil'));
        }
        
        if (!isset($_POST['mc_quil_user_import_nonce']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mc_quil_user_import_nonce'])), 'mc_quil_user_import_action')) {
            wp_die(__('Security check failed.', 'quil'));
        }
        
        if (!isset($_FILES['json_file']) || $_FILES['json_file']['error'] !== UPLOAD_ERR_OK) {
            wp_die(__('File upload failed.', 'quil'));
        }
        
        require_once QUIL_PLUGIN_DIR . 'includes/class-quil-import-users.php';
        
        $import = new Quil_Import_Users();
        $import->mc_quil_import_users($_FILES['json_file']);
    }
}