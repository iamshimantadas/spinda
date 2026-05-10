<?php
/**
 * Plugin main class file
 * 
 * @since 1.0.0
 * @package Spinda
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Spinexim {
    
    /**
     * Singleton instance
     * 
     * @var Spinexim
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     * 
     * @since 1.0.0
     * @return Spinexim
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
        $this->spinexim_init_hooks();
    }
    
    /**
     * Initialize all hooks
     * 
     * @since 1.0.0
     * @return void
     */
    private function spinexim_init_hooks() {
        add_action('admin_menu', array($this, 'spinexim_add_admin_menus'));
        add_action('admin_enqueue_scripts', array($this, 'spinexim_enqueue_assets'));
        add_action('admin_post_spinexim_export_posts', array($this, 'spinexim_handle_post_export'));
        add_action('admin_post_spinexim_export_users', array($this, 'spinexim_handle_user_export'));
        add_action('admin_post_spinexim_import_posts', array($this, 'spinexim_handle_post_import'));
        add_action('admin_post_spinexim_import_users', array($this, 'spinexim_handle_user_import'));

        add_action('admin_post_spinexim_export_taxonomies', array($this, 'spinexim_handle_taxonomy_export'));
        add_action('admin_post_spinexim_import_taxonomies', array($this, 'spinexim_handle_taxonomy_import'));
        add_action('wp_ajax_spinexim_get_taxonomies', array($this, 'spinexim_ajax_get_taxonomies'));
    }
    
    /**
     * Add admin menus
     * 
     * @since 1.0.0
     * @return void
     */
    public function spinexim_add_admin_menus() {
        add_menu_page(
            __('Spinda Export/Import', 'spinda-exportimport-data'),
            __('Spinda', 'spinda-exportimport-data'),
            'manage_options',
            'spinexim-posts',
            array($this, 'spinexim_render_posts_page'),
            SPINEXIM_PLUGIN_URL . 'assets/icon.png',
            30
        );
        
        add_submenu_page(
            'spinexim-posts',
            __('Posts Export/Import', 'spinda-exportimport-data'),
            __('Posts', 'spinda-exportimport-data'),
            'manage_options',
            'spinexim-posts',
            array($this, 'spinexim_render_posts_page')
        );
        
        add_submenu_page(
            'spinexim-posts',
            __('Users Export/Import', 'spinda-exportimport-data'),
            __('Users', 'spinda-exportimport-data'),
            'manage_options',
            'spinexim-users',
            array($this, 'spinexim_render_users_page')
        );

        add_submenu_page(
            'spinexim-posts',
            __('Taxonomies Export/Import', 'spinda-exportimport-data'),
            __('Taxonomies', 'spinda-exportimport-data'),
            'manage_options',
            'spinexim-taxonomies',
            array($this, 'spinexim_render_taxonomies_page')
        );
    }
    
    /**
     * Enqueue admin assets
     * 
     * @since 1.0.0
     * @param string $hook Current page hook
     * @return void
     */
    public function spinexim_enqueue_assets($hook) {
        if (strpos($hook, 'spinexim-posts') === false && strpos($hook, 'spinexim-users') === false) {
            return;
        }
        
        wp_enqueue_style(
            'spinexim-admin-css',
            SPINEXIM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SPINEXIM_VERSION
        );
        
        wp_enqueue_script(
            'spinexim-admin-js',
            SPINEXIM_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            SPINEXIM_VERSION,
            true
        );
        
        wp_localize_script('spinexim-admin-js', 'spinexim_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spinexim_ajax_nonce'),
            'confirmExport' => __('Are you sure you want to export this data?', 'spinda-exportimport-data'),
            'confirmImport' => __('Import will add new content. Existing content will not be overwritten. Continue?', 'spinda-exportimport-data'),
            'selectFile' => __('Please select a JSON file to import.', 'spinda-exportimport-data')
        ));
    }

    /**
     * Render taxonomies admin page
     * 
     * @since 1.0.1
     * @return void
     */
    public function spinexim_render_taxonomies_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions.', 'spinda-exportimport-data'));
        }
        
        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'export';
        ?>
        <div class="wrap spinexim-container">
            <h1><?php esc_html_e('Spinda - Taxonomies Export/Import', 'spinda-exportimport-data'); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=spinexim-taxonomies&tab=export" class="nav-tab <?php echo $active_tab === 'export' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Export', 'spinda-exportimport-data'); ?>
                </a>
                <a href="?page=spinexim-taxonomies&tab=import" class="nav-tab <?php echo $active_tab === 'import' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Import', 'spinda-exportimport-data'); ?>
                </a>
            </h2>
            
            <div class="spinexim-tab-content">
                <?php
                if ('export' === $active_tab) {
                    $this->spinexim_render_taxonomy_export_form();
                } else {
                    $this->spinexim_render_taxonomy_import_form();
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render taxonomy export form
     * 
     * @since 1.0.1
     * @return void
     */
    private function spinexim_render_taxonomy_export_form() {
        $post_types = get_post_types( array( 'public' => true ), 'objects' );
        ?>
        <div class="spinexim-card">
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="spinexim-taxonomy-export-form">
                <?php wp_nonce_field( 'spinexim_taxonomy_export_action', 'spinexim_taxonomy_export_nonce' ); ?>
                <input type="hidden" name="action" value="spinexim_export_taxonomies">
                
                <div class="spinexim-form-group">
                    <label for="post_type"><?php esc_html_e( 'Select Post Type', 'spinda-exportimport-data' ); ?></label>
                    <select name="post_type" id="spinexim-post-type-select" class="spinexim-select" required>
                        <option value=""><?php esc_html_e( '-- Select Post Type --', 'spinda-exportimport-data' ); ?></option>
                        <?php foreach ( $post_types as $post_type_obj ) : 
                            if ( 'attachment' !== $post_type_obj->name ) : ?>
                            <option value="<?php echo esc_attr( $post_type_obj->name ); ?>">
                                <?php echo esc_html( $post_type_obj->label ); ?>
                            </option>
                            <?php endif; 
                        endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Select post type to export its associated taxonomies.', 'spinda-exportimport-data' ); ?></p>
                </div>
                
                <div class="spinexim-form-group" id="spinexim-taxonomies-container" style="display: none;">
                    <label><?php esc_html_e( 'Select Taxonomies to Export', 'spinda-exportimport-data' ); ?></label>
                    <div class="spinexim-checkbox-group" id="spinexim-taxonomies-list">
                        <!-- Taxonomies will be loaded via AJAX -->
                    </div>
                    <p class="description"><?php esc_html_e( 'Select specific taxonomies or leave all selected for full export.', 'spinda-exportimport-data' ); ?></p>
                </div>
                
                <div class="spinexim-form-group">
                    <label><?php esc_html_e( 'Include Term Meta', 'spinda-exportimport-data' ); ?></label>
                    <label class="spinexim-checkbox-label">
                        <input type="checkbox" name="include_term_meta" value="1" checked>
                        <?php esc_html_e( 'Include Term Meta Data', 'spinda-exportimport-data' ); ?>
                    </label>
                    <p class="description"><?php esc_html_e( 'Include custom meta data associated with terms.', 'spinda-exportimport-data' ); ?></p>
                </div>
                
                <div class="spinexim-form-group">
                    <label><?php esc_html_e( 'Include Empty Terms', 'spinda-exportimport-data' ); ?></label>
                    <label class="spinexim-checkbox-label">
                        <input type="checkbox" name="include_empty_terms" value="1">
                        <?php esc_html_e( 'Include terms with no posts assigned', 'spinda-exportimport-data' ); ?>
                    </label>
                    <p class="description"><?php esc_html_e( 'By default, only terms with assigned posts are exported.', 'spinda-exportimport-data' ); ?></p>
                </div>
                
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Export Taxonomies', 'spinda-exportimport-data' ); ?></button>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render taxonomy import form
     * 
     * @since 1.0.1
     * @return void
     */
    private function spinexim_render_taxonomy_import_form() {
        ?>
        <div class="spinexim-card">
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'spinexim_taxonomy_import_action', 'spinexim_taxonomy_import_nonce' ); ?>
                <input type="hidden" name="action" value="spinexim_import_taxonomies">
                
                <div class="spinexim-form-group">
                    <label for="json_file"><?php esc_html_e( 'Select JSON File', 'spinda-exportimport-data' ); ?></label>
                    <input type="file" name="json_file" id="json_file" class="spinexim-file-input" accept=".json" required>
                    <p class="description"><?php esc_html_e( 'Upload the taxonomies JSON file exported from Spinda plugin.', 'spinda-exportimport-data' ); ?></p>
                </div>
                
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Import Taxonomies', 'spinda-exportimport-data' ); ?></button>
            </form>
        </div>
        <?php
    }
    
    /**
     * Handle taxonomy export request
     * 
     * @since 1.0.1
     * @return void
     */
    public function spinexim_handle_taxonomy_export() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized access.', 'spinda-exportimport-data' ) );
        }
        
        if ( ! isset( $_POST['spinexim_taxonomy_export_nonce'] ) || 
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spinexim_taxonomy_export_nonce'] ) ), 'spinexim_taxonomy_export_action' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'spinda-exportimport-data' ) );
        }
        
        $post_type          = isset( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : '';
        $selected_taxonomies = isset( $_POST['taxonomies'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['taxonomies'] ) ) : array();
        $include_term_meta  = isset( $_POST['include_term_meta'] ) ? (bool) sanitize_text_field( wp_unslash( $_POST['include_term_meta'] ) ) : false;
        $include_empty_terms = isset( $_POST['include_empty_terms'] ) ? (bool) sanitize_text_field( wp_unslash( $_POST['include_empty_terms'] ) ) : false;
        
        require_once SPINEXIM_PLUGIN_DIR . 'includes/class-spinexim-export-taxonomies.php';
        
        $export = new Spinexim_Export_Taxonomies();
        $export->spinexim_export_taxonomies( $post_type, $selected_taxonomies, $include_term_meta, $include_empty_terms );
    }
    
    /**
     * Handle taxonomy import request
     * 
     * @since 1.0.1
     * @return void
     */
    public function spinexim_handle_taxonomy_import() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized access.', 'spinda-exportimport-data' ) );
        }
        
        if ( ! isset( $_POST['spinexim_taxonomy_import_nonce'] ) || 
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spinexim_taxonomy_import_nonce'] ) ), 'spinexim_taxonomy_import_action' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'spinda-exportimport-data' ) );
        }
        
        if ( ! isset( $_FILES['json_file'] ) || UPLOAD_ERR_OK !== $_FILES['json_file']['error'] ) {
            wp_die( esc_html__( 'File upload failed.', 'spinda-exportimport-data' ) );
        }
        
        require_once SPINEXIM_PLUGIN_DIR . 'includes/class-spinexim-import-taxonomies.php';
        
        $import = new Spinexim_Import_Taxonomies();
        $import->spinexim_import_taxonomies( $_FILES['json_file'] );
    }
    
    /**
     * AJAX handler to get taxonomies for a post type
     * 
     * @since 1.0.1
     * @return void
     */
    public function spinexim_ajax_get_taxonomies() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'spinexim_ajax_nonce' ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        
        $post_type = isset( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : '';
        
        if ( empty( $post_type ) ) {
            wp_send_json_error( 'Invalid post type' );
        }
        
        $taxonomies = get_object_taxonomies( $post_type, 'objects' );
        $taxonomies_data = array();
        
        foreach ( $taxonomies as $taxonomy ) {
            $terms = get_terms( array(
                'taxonomy'   => $taxonomy->name,
                'hide_empty' => false,
            ) );
            
            $taxonomies_data[] = array(
                'name'  => $taxonomy->name,
                'label' => $taxonomy->label,
                'count' => ! is_wp_error( $terms ) ? count( $terms ) : 0,
            );
        }
        
        wp_send_json_success( array( 'taxonomies' => $taxonomies_data ) );
    }
    
    /**
     * Render posts admin page with tabs
     * 
     * @since 1.0.0
     * @return void
     */
    public function spinexim_render_posts_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'spinda-exportimport-data'));
        }
        
        // Sanitize tab parameter
        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'export';
        ?>
        <div class="wrap spinexim-container">
            <h1><?php esc_html_e('Spinda - Posts Export/Import', 'spinda-exportimport-data'); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=spinexim-posts&tab=export" class="nav-tab <?php echo $active_tab === 'export' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Export', 'spinda-exportimport-data'); ?>
                </a>
                <a href="?page=spinexim-posts&tab=import" class="nav-tab <?php echo $active_tab === 'import' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Import', 'spinda-exportimport-data'); ?>
                </a>
            </h2>
            
            <div class="spinexim-tab-content">
                <?php
                if ($active_tab === 'export') {
                    $this->spinexim_render_post_export_form();
                } else {
                    $this->spinexim_render_post_import_form();
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
    private function spinexim_render_post_export_form() {
        $post_types = get_post_types(array('public' => true), 'objects');
        ?>
        <div class="spinexim-card">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('spinexim_post_export_action', 'spinexim_post_export_nonce'); ?>
                <input type="hidden" name="action" value="spinexim_export_posts">
                
                <div class="spinexim-form-group">
                    <label for="post_type"><?php esc_html_e('Select Post Type', 'spinda-exportimport-data'); ?></label>
                    <select name="post_type" id="post_type" class="spinexim-select" required>
                        <option value=""><?php esc_html_e('-- Select Post Type --', 'spinda-exportimport-data'); ?></option>
                        <?php foreach ($post_types as $post_type): 
                            if($post_type->name !== "attachment"){ ?>
                            <option value="<?php echo esc_attr($post_type->name); ?>">
                                <?php echo esc_html($post_type->label); ?>
                            </option>
                            <?php } ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="spinexim-form-group">
                    <label><?php esc_html_e('Taxonomies', 'spinda-exportimport-data'); ?></label>
                    <label class="spinexim-checkbox-label">
                        <input type="checkbox" name="include_taxonomies" value="1" checked>
                        <?php esc_html_e('Include Taxonomies & Terms', 'spinda-exportimport-data'); ?>
                    </label>
                </div>
                
                <div class="spinexim-form-group">
                    <label><?php esc_html_e('Taxonomy Meta', 'spinda-exportimport-data'); ?></label>
                    <label class="spinexim-checkbox-label">
                        <input type="checkbox" name="include_term_meta" value="1" checked>
                        <?php esc_html_e('Include Term Meta', 'spinda-exportimport-data'); ?>
                    </label>
                </div>
                
                <div class="spinexim-form-group">
                    <label><?php esc_html_e('Featured Media', 'spinda-exportimport-data'); ?></label>
                    <label class="spinexim-checkbox-label">
                        <input type="checkbox" name="include_featured_media" value="1" checked>
                        <?php esc_html_e('Include Featured Images', 'spinda-exportimport-data'); ?>
                    </label>
                </div>
                
                <div class="spinexim-form-group">
                    <label><?php esc_html_e('Post Meta', 'spinda-exportimport-data'); ?></label>
                    <label class="spinexim-checkbox-label">
                        <input type="checkbox" name="include_post_meta" value="1" checked>
                        <?php esc_html_e('Include Post Meta', 'spinda-exportimport-data'); ?>
                    </label>
                </div>
                
                <div class="spinexim-form-group">
                    <label><?php esc_html_e('Comments', 'spinda-exportimport-data'); ?></label>
                    <label class="spinexim-checkbox-label">
                        <input type="checkbox" name="include_comments" value="1">
                        <?php esc_html_e('Include Comments', 'spinda-exportimport-data'); ?>
                    </label>
                </div>
                
                <div class="spinexim-form-group">
                    <label><?php esc_html_e('Variations (WooCommerce)', 'spinda-exportimport-data'); ?></label>
                    <label class="spinexim-checkbox-label">
                        <input type="checkbox" name="include_variations" value="1" checked>
                        <?php esc_html_e('Include Product Variations', 'spinda-exportimport-data'); ?>
                    </label>
                </div>
                
                <button type="submit" class="button button-primary"><?php esc_html_e('Export Now', 'spinda-exportimport-data'); ?></button>
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
    private function spinexim_render_post_import_form() {
        ?>
        <div class="spinexim-card">
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('spinexim_post_import_action', 'spinexim_post_import_nonce'); ?>
                <input type="hidden" name="action" value="spinexim_import_posts">
                
                <div class="spinexim-form-group">
                    <label for="json_file"><?php esc_html_e('Select JSON File', 'spinda-exportimport-data'); ?></label>
                    <input type="file" name="json_file" id="json_file" class="spinexim-file-input" accept=".json" required>
                    <p class="description"><?php esc_html_e('Upload the JSON file exported from Spinda plugin.', 'spinda-exportimport-data'); ?></p>
                </div>
                
                <button type="submit" class="button button-primary"><?php esc_html_e('Import Now', 'spinda-exportimport-data'); ?></button>
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
    public function spinexim_render_users_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'spinda-exportimport-data'));
        }
        
        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'export';
        ?>
        <div class="wrap spinexim-container">
            <h1><?php esc_html_e('Spinda - Users Export/Import', 'spinda-exportimport-data'); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=spinexim-users&tab=export" class="nav-tab <?php echo $active_tab === 'export' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Export', 'spinda-exportimport-data'); ?>
                </a>
                <a href="?page=spinexim-users&tab=import" class="nav-tab <?php echo $active_tab === 'import' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Import', 'spinda-exportimport-data'); ?>
                </a>
            </h2>
            
            <div class="spinexim-tab-content">
                <?php
                if ($active_tab === 'export') {
                    $this->spinexim_render_user_export_form();
                } else {
                    $this->spinexim_render_user_import_form();
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
    private function spinexim_render_user_export_form() {
        global $wp_roles;
        $roles = $wp_roles->get_names();
        ?>
        <div class="spinexim-card">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('spinexim_user_export_action', 'spinexim_user_export_nonce'); ?>
                <input type="hidden" name="action" value="spinexim_export_users">
                
                <div class="spinexim-form-group">
                    <label for="user_role"><?php esc_html_e('Select User Role', 'spinda-exportimport-data'); ?></label>
                    <select name="user_role" id="user_role" class="spinexim-select">
                        <option value="all"><?php esc_html_e('-- All Roles --', 'spinda-exportimport-data'); ?></option>
                        <?php foreach ($roles as $role_key => $role_name): ?>
                            <option value="<?php echo esc_attr($role_key); ?>">
                                <?php echo esc_html($role_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e('Select specific role or export all users.', 'spinda-exportimport-data'); ?></p>
                </div>
                
                <div class="spinexim-form-group">
                    <label><?php esc_html_e('User Meta', 'spinda-exportimport-data'); ?></label>
                    <label class="spinexim-checkbox-label">
                        <input type="checkbox" name="include_user_meta" value="1" checked>
                        <?php esc_html_e('Include User Meta Data', 'spinda-exportimport-data'); ?>
                    </label>
                </div>
                
                <button type="submit" class="button button-primary"><?php esc_html_e('Export Users', 'spinda-exportimport-data'); ?></button>
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
    private function spinexim_render_user_import_form() {
        ?>
        <div class="spinexim-card">
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('spinexim_user_import_action', 'spinexim_user_import_nonce'); ?>
                <input type="hidden" name="action" value="spinexim_import_users">
                
                <div class="spinexim-form-group">
                    <label for="json_file"><?php esc_html_e('Select JSON File', 'spinda-exportimport-data'); ?></label>
                    <input type="file" name="json_file" id="json_file" class="spinexim-file-input" accept=".json" required>
                    <p class="description"><?php esc_html_e('Upload the users JSON file exported from Spinda plugin.', 'spinda-exportimport-data'); ?></p>
                </div>
                
                <button type="submit" class="button button-primary"><?php esc_html_e('Import Users', 'spinda-exportimport-data'); ?></button>
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
    public function spinexim_handle_post_export() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access.', 'spinda-exportimport-data'));
        }
        
        if (!isset($_POST['spinexim_post_export_nonce']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spinexim_post_export_nonce'])), 'spinexim_post_export_action')) {
            wp_die(__('Security check failed.', 'spinda-exportimport-data'));
        }
        
        $post_type = isset($_POST['post_type']) ? sanitize_text_field(wp_unslash($_POST['post_type'])) : 'post';
        $include_taxonomies = isset($_POST['include_taxonomies']) ? (bool) sanitize_text_field(wp_unslash($_POST['include_taxonomies'])) : false;
        $include_term_meta = isset($_POST['include_term_meta']) ? (bool) sanitize_text_field(wp_unslash($_POST['include_term_meta'])) : false;
        $include_featured_media = isset($_POST['include_featured_media']) ? (bool) sanitize_text_field(wp_unslash($_POST['include_featured_media'])) : false;
        $include_post_meta = isset($_POST['include_post_meta']) ? (bool) sanitize_text_field(wp_unslash($_POST['include_post_meta'])) : false;
        $include_comments = isset($_POST['include_comments']) ? (bool) sanitize_text_field(wp_unslash($_POST['include_comments'])) : false;
        $include_variations = isset($_POST['include_variations']) ? (bool) sanitize_text_field(wp_unslash($_POST['include_variations'])) : false;
        
        require_once SPINEXIM_PLUGIN_DIR . 'includes/class-spinexim-export.php';
        
        $export = new Spinexim_Export();
        $export->spinexim_export_posts(
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
    public function spinexim_handle_user_export() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access.', 'spinda-exportimport-data'));
        }
        
        if (!isset($_POST['spinexim_user_export_nonce']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spinexim_user_export_nonce'])), 'spinexim_user_export_action')) {
            wp_die(__('Security check failed.', 'spinda-exportimport-data'));
        }
        
        $user_role = isset($_POST['user_role']) ? sanitize_text_field(wp_unslash($_POST['user_role'])) : 'all';
        $include_user_meta = isset($_POST['include_user_meta']) ? (bool) sanitize_text_field(wp_unslash($_POST['include_user_meta'])) : false;
        
        require_once SPINEXIM_PLUGIN_DIR . 'includes/class-spinexim-export-users.php';
        
        $export = new Spinexim_Export_Users();
        $export->spinexim_export_users($user_role, $include_user_meta);
    }
    
    /**
     * Handle post import request
     * 
     * @since 1.0.0
     * @return void
     */
    public function spinexim_handle_post_import() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access.', 'spinda-exportimport-data'));
        }
        
        if (!isset($_POST['spinexim_post_import_nonce']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spinexim_post_import_nonce'])), 'spinexim_post_import_action')) {
            wp_die(__('Security check failed.', 'spinda-exportimport-data'));
        }
        
        if (!isset($_FILES['json_file']) || $_FILES['json_file']['error'] !== UPLOAD_ERR_OK) {
            wp_die(__('File upload failed.', 'spinda-exportimport-data'));
        }
        
        require_once SPINEXIM_PLUGIN_DIR . 'includes/class-spinexim-import.php';
        
        $import = new Spinexim_Import();
        $import->spinexim_import_posts($_FILES['json_file']);
    }
    
    /**
     * Handle user import request
     * 
     * @since 1.0.0
     * @return void
     */
    public function spinexim_handle_user_import() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access.', 'spinda-exportimport-data'));
        }
        
        if (!isset($_POST['spinexim_user_import_nonce']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spinexim_user_import_nonce'])), 'spinexim_user_import_action')) {
            wp_die(__('Security check failed.', 'spinda-exportimport-data'));
        }
        
        if (!isset($_FILES['json_file']) || $_FILES['json_file']['error'] !== UPLOAD_ERR_OK) {
            wp_die(__('File upload failed.', 'spinda-exportimport-data'));
        }
        
        require_once SPINEXIM_PLUGIN_DIR . 'includes/class-spinexim-import-users.php';
        
        $import = new Spinexim_Import_Users();
        $import->spinexim_import_users($_FILES['json_file']);
    }
}