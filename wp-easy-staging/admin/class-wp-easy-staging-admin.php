<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://github.com/omnisend/wp-easy-staging
 * @since      1.0.0
 *
 * @package    WP_Easy_Staging
 * @subpackage WP_Easy_Staging/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for
 * enqueuing the admin-specific stylesheet and JavaScript.
 *
 * @package    WP_Easy_Staging
 * @subpackage WP_Easy_Staging/admin
 * @author     Omnisend
 */
class WP_Easy_Staging_Admin {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Add admin menu
        add_action('admin_menu', array($this, 'register_admin_menu'));
        
        // Add scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Register AJAX handlers
        add_action('init', array($this, 'register_ajax_handlers'));
        
        // Add admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/wp-easy-staging-admin.css', array(), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts($hook) {
        // Only enqueue on our plugin pages
        $plugin_pages = array(
            'toplevel_page_wp-easy-staging',
            'wp-easy-staging_page_wp-easy-staging-create',
            'wp-easy-staging_page_wp-easy-staging-push',
            'wp-easy-staging_page_wp-easy-staging-settings'
        );
        
        if (!in_array($hook, $plugin_pages) && strpos($hook, 'wp-easy-staging') === false) {
            return;
        }
        
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/wp-easy-staging-admin.js', array('jquery'), $this->version, false);
        
        // Localize script with translations and variables
        wp_localize_script($this->plugin_name, 'wp_easy_staging_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_easy_staging_nonce')
        ));
        
        // Add translations and other variables
        wp_localize_script($this->plugin_name, 'wp_easy_staging_i18n', array(
            'admin_url' => admin_url('admin.php'),
            'show_advanced' => __('Show Advanced Options', 'wp-easy-staging'),
            'hide_advanced' => __('Hide Advanced Options', 'wp-easy-staging'),
            'creating_staging' => __('Creating staging site. This may take several minutes...', 'wp-easy-staging'),
            'deleting_staging' => __('Deleting staging site...', 'wp-easy-staging'),
            'pushing_changes' => __('Pushing changes to production...', 'wp-easy-staging'),
            'resolving_conflicts' => __('Resolving conflicts...', 'wp-easy-staging'),
            'processing' => __('Processing...', 'wp-easy-staging'),
            'confirm_delete' => __('Are you sure you want to delete this staging site? This action cannot be undone.', 'wp-easy-staging'),
            'confirm_push' => __('Are you sure you want to push these changes to production? This will overwrite data in your live site.', 'wp-easy-staging'),
            'confirm_settings' => __('Are you sure you want to save these settings?', 'wp-easy-staging'),
            'no_items_selected' => __('Please select at least one item to push.', 'wp-easy-staging'),
            'error_occurred' => __('An error occurred. Please try again.', 'wp-easy-staging')
        ));
    }
    
    /**
     * Register the admin menu pages.
     *
     * @since    1.0.0
     */
    public function register_admin_menu() {
        // Main menu page
        add_menu_page(
            __('WP Easy Staging', 'wp-easy-staging'),
            __('WP Easy Staging', 'wp-easy-staging'),
            'manage_options',
            'wp-easy-staging',
            array($this, 'display_admin_page'),
            'dashicons-admin-multisite',
            80
        );
        
        // Create staging subpage
        add_submenu_page(
            'wp-easy-staging',
            __('Create Staging', 'wp-easy-staging'),
            __('Create Staging', 'wp-easy-staging'),
            'manage_options',
            'wp-easy-staging-create',
            array($this, 'display_create_page')
        );
        
        // Push to production subpage
        add_submenu_page(
            'wp-easy-staging',
            __('Push to Production', 'wp-easy-staging'),
            __('Push to Production', 'wp-easy-staging'),
            'manage_options',
            'wp-easy-staging-push',
            array($this, 'display_push_page')
        );
        
        // Settings subpage
        add_submenu_page(
            'wp-easy-staging',
            __('Settings', 'wp-easy-staging'),
            __('Settings', 'wp-easy-staging'),
            'manage_options',
            'wp-easy-staging-settings',
            array($this, 'display_settings_page')
        );
    }
    
    /**
     * Register plugin settings.
     *
     * @since    1.0.0
     */
    public function register_settings() {
        register_setting(
            'wp_easy_staging_settings',
            'wp_easy_staging_settings',
            array($this, 'sanitize_settings')
        );
    }
    
    /**
     * Sanitize plugin settings.
     *
     * @since    1.0.0
     * @param    array    $input       The input array.
     * @return   array    The sanitized input array.
     */
    public function sanitize_settings($input) {
        $sanitized_input = array();
        
        // Staging directory
        if (isset($input['staging_directory'])) {
            $sanitized_input['staging_directory'] = sanitize_text_field($input['staging_directory']);
        }
        
        // Create subdomain
        if (isset($input['create_subdomain'])) {
            $sanitized_input['create_subdomain'] = ($input['create_subdomain'] === 'yes') ? 'yes' : 'no';
        }
        
        // Automatic backups
        if (isset($input['automatic_backups'])) {
            $sanitized_input['automatic_backups'] = ($input['automatic_backups'] === 'yes') ? 'yes' : 'no';
        }
        
        // Backup before push
        if (isset($input['backup_before_push'])) {
            $sanitized_input['backup_before_push'] = ($input['backup_before_push'] === 'yes') ? 'yes' : 'no';
        }
        
        // Email notifications
        if (isset($input['email_notifications'])) {
            $sanitized_input['email_notifications'] = ($input['email_notifications'] === 'yes') ? 'yes' : 'no';
        }
        
        // File copy method
        if (isset($input['file_copy_method'])) {
            $sanitized_input['file_copy_method'] = in_array($input['file_copy_method'], array('direct', 'chunked')) ? $input['file_copy_method'] : 'direct';
        }
        
        // DB query limit
        if (isset($input['db_query_limit'])) {
            $sanitized_input['db_query_limit'] = intval($input['db_query_limit']);
            if ($sanitized_input['db_query_limit'] < 100) {
                $sanitized_input['db_query_limit'] = 100;
            } elseif ($sanitized_input['db_query_limit'] > 10000) {
                $sanitized_input['db_query_limit'] = 10000;
            }
        }
        
        // Timeout
        if (isset($input['timeout'])) {
            $sanitized_input['timeout'] = intval($input['timeout']);
            if ($sanitized_input['timeout'] < 60) {
                $sanitized_input['timeout'] = 60;
            } elseif ($sanitized_input['timeout'] > 3600) {
                $sanitized_input['timeout'] = 3600;
            }
        }
        
        // Exclude directories
        if (isset($input['exclude_dirs'])) {
            $dirs = explode("\n", $input['exclude_dirs']);
            $sanitized_dirs = array();
            
            foreach ($dirs as $dir) {
                $dir = trim($dir);
                if (!empty($dir)) {
                    $sanitized_dirs[] = sanitize_text_field($dir);
                }
            }
            
            $sanitized_input['exclude_dirs'] = $sanitized_dirs;
        }
        
        // Exclude file extensions
        if (isset($input['exclude_extensions'])) {
            $extensions = explode("\n", $input['exclude_extensions']);
            $sanitized_extensions = array();
            
            foreach ($extensions as $ext) {
                $ext = trim($ext);
                if (!empty($ext)) {
                    $sanitized_extensions[] = sanitize_text_field($ext);
                }
            }
            
            $sanitized_input['exclude_extensions'] = $sanitized_extensions;
        }
        
        // Exclude database tables
        if (isset($input['exclude_tables'])) {
            $tables = explode("\n", $input['exclude_tables']);
            $sanitized_tables = array();
            
            foreach ($tables as $table) {
                $table = trim($table);
                if (!empty($table)) {
                    $sanitized_tables[] = sanitize_text_field($table);
                }
            }
            
            $sanitized_input['exclude_tables'] = $sanitized_tables;
        }
        
        return $sanitized_input;
    }
    
    /**
     * Display the admin page.
     *
     * @since    1.0.0
     */
    public function display_admin_page() {
        require_once plugin_dir_path(__FILE__) . 'partials/wp-easy-staging-admin-display.php';
    }
    
    /**
     * Display the create staging page.
     *
     * @since    1.0.0
     */
    public function display_create_page() {
        // Get staging info
        $staging_instance = new WP_Easy_Staging_Staging();
        $staging_info = $staging_instance->get_staging_status();
        
        // If staging exists, redirect to main page
        if ($staging_info['has_staging']) {
            wp_redirect(admin_url('admin.php?page=wp-easy-staging'));
            exit;
        }
        
        require_once plugin_dir_path(__FILE__) . 'partials/wp-easy-staging-admin-create.php';
    }
    
    /**
     * Display the push to production page.
     *
     * @since    1.0.0
     */
    public function display_push_page() {
        require_once plugin_dir_path(__FILE__) . 'partials/wp-easy-staging-admin-push.php';
    }
    
    /**
     * Display the settings page.
     *
     * @since    1.0.0
     */
    public function display_settings_page() {
        require_once plugin_dir_path(__FILE__) . 'partials/wp-easy-staging-admin-settings.php';
    }
    
    /**
     * Handle AJAX request to create a staging site.
     *
     * @since    1.0.0
     */
    public function ajax_create_staging() {
        // Log the AJAX request for debugging
        error_log('AJAX Create Staging request received');
        
        // Check nonce
        if (!isset($_POST['wp_easy_staging_nonce']) || !wp_verify_nonce($_POST['wp_easy_staging_nonce'], 'wp_easy_staging_nonce')) {
            error_log('WP Easy Staging: Security check failed');
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-easy-staging')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            error_log('WP Easy Staging: Permission check failed');
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'wp-easy-staging')));
        }
        
        // Get staging name
        $staging_name = isset($_POST['staging_name']) ? sanitize_text_field($_POST['staging_name']) : 'staging';
        error_log('WP Easy Staging: Creating staging site with name: ' . $staging_name);
        
        // Get exclusions
        $exclude_dirs = isset($_POST['exclude_dirs']) ? explode("\n", sanitize_textarea_field($_POST['exclude_dirs'])) : array();
        $exclude_extensions = isset($_POST['exclude_extensions']) ? explode("\n", sanitize_textarea_field($_POST['exclude_extensions'])) : array();
        $exclude_tables = isset($_POST['exclude_tables']) ? explode("\n", sanitize_textarea_field($_POST['exclude_tables'])) : array();
        
        // Sanitize exclusions
        $exclude_dirs = array_map('trim', $exclude_dirs);
        $exclude_dirs = array_filter($exclude_dirs);
        
        $exclude_extensions = array_map('trim', $exclude_extensions);
        $exclude_extensions = array_filter($exclude_extensions);
        
        $exclude_tables = array_map('trim', $exclude_tables);
        $exclude_tables = array_filter($exclude_tables);
        
        // Actually create the staging site instead of simulating it
        $staging = new WP_Easy_Staging_Staging();
        $result = $staging->create_staging_site($staging_name);
        
        if (!is_wp_error($result)) {
            error_log('WP Easy Staging: Staging site created successfully with ID: ' . $result['id']);
            wp_send_json_success(array('message' => __('Staging site created successfully.', 'wp-easy-staging')));
        } else {
            error_log('WP Easy Staging: Failed to create staging site: ' . $result->get_error_message());
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
    }
    
    /**
     * Handle AJAX request to delete a staging site.
     *
     * @since    1.0.0
     */
    public function ajax_delete_staging() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp_easy_staging_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-easy-staging')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'wp-easy-staging')));
        }
        
        // Get staging ID
        $staging_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if ($staging_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid staging ID.', 'wp-easy-staging')));
        }
        
        // Delete staging site
        $staging_instance = new WP_Easy_Staging_Staging();
        $result = $staging_instance->delete_staging_site($staging_id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Staging site deleted successfully.', 'wp-easy-staging')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete staging site.', 'wp-easy-staging')));
        }
    }
    
    /**
     * Handle AJAX request to push changes to production.
     *
     * @since    1.0.0
     */
    public function ajax_push_changes() {
        // Check nonce
        if (!isset($_POST['wp_easy_staging_nonce']) || !wp_verify_nonce($_POST['wp_easy_staging_nonce'], 'wp_easy_staging_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-easy-staging')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'wp-easy-staging')));
        }
        
        // Get selected items
        $selected_items = isset($_POST['selected_items']) ? (array) $_POST['selected_items'] : array();
        
        if (empty($selected_items)) {
            wp_send_json_error(array('message' => __('No items selected for pushing.', 'wp-easy-staging')));
        }
        
        // Check for conflicts first
        $conflict_detector = new WP_Easy_Staging_Conflict_Detector();
        $conflicts = $conflict_detector->detect_conflicts($selected_items);
        
        if (!empty($conflicts)) {
            wp_send_json_success(array(
                'message' => __('Conflicts detected. Please resolve them before pushing.', 'wp-easy-staging'),
                'redirect' => admin_url('admin.php?page=wp-easy-staging-push&resolve_conflicts=1')
            ));
            return;
        }
        
        // Push changes to production
        $pushing = new WP_Easy_Staging_Pushing();
        $result = $pushing->push_to_production($selected_items);
        
        if ($result['success']) {
            wp_send_json_success(array('message' => __('Changes pushed to production successfully.', 'wp-easy-staging')));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }
    
    /**
     * Handle AJAX request to resolve conflicts.
     *
     * @since    1.0.0
     */
    public function ajax_resolve_conflicts() {
        // Check nonce
        if (!isset($_POST['wp_easy_staging_nonce']) || !wp_verify_nonce($_POST['wp_easy_staging_nonce'], 'wp_easy_staging_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-easy-staging')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'wp-easy-staging')));
        }
        
        // Get resolutions
        $resolutions = isset($_POST['resolution']) ? (array) $_POST['resolution'] : array();
        $custom_merges = isset($_POST['custom_merge']) ? (array) $_POST['custom_merge'] : array();
        
        if (empty($resolutions)) {
            wp_send_json_error(array('message' => __('No conflict resolutions provided.', 'wp-easy-staging')));
        }
        
        // Process resolutions
        $final_resolutions = array();
        
        foreach ($resolutions as $conflict_id => $resolution_type) {
            $resolution = array(
                'conflict_id' => intval($conflict_id),
                'type' => sanitize_text_field($resolution_type)
            );
            
            // If custom merge, add the content
            if ($resolution_type === 'custom' && isset($custom_merges[$conflict_id])) {
                $resolution['custom_content'] = wp_kses_post($custom_merges[$conflict_id]);
            }
            
            $final_resolutions[] = $resolution;
        }
        
        // Resolve conflicts
        $resolver = new WP_Easy_Staging_Conflict_Resolver();
        $result = $resolver->resolve_conflicts($final_resolutions);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => __('Conflicts resolved successfully.', 'wp-easy-staging'),
                'redirect' => admin_url('admin.php?page=wp-easy-staging-push')
            ));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }
    
    /**
     * Register all the AJAX handlers.
     *
     * @since    1.0.0
     */
    public function register_ajax_handlers() {
        add_action('wp_ajax_wp_easy_staging_create_staging', array($this, 'ajax_create_staging'));
        add_action('wp_ajax_wp_easy_staging_delete_staging', array($this, 'ajax_delete_staging'));
        add_action('wp_ajax_wp_easy_staging_push_changes', array($this, 'ajax_push_changes'));
        add_action('wp_ajax_wp_easy_staging_resolve_conflicts', array($this, 'ajax_resolve_conflicts'));
    }
    
    /**
     * Add an admin notice.
     *
     * @since    1.0.0
     */
    public function admin_notices() {
        if (isset($_GET['page']) && $_GET['page'] === 'wp-easy-staging') {
            if (isset($_GET['created']) && $_GET['created'] == '1') {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Staging site created successfully!', 'wp-easy-staging'); ?></p>
                </div>
                <?php
            }
            
            if (isset($_GET['pushed']) && $_GET['pushed'] == '1') {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Changes pushed to production successfully!', 'wp-easy-staging'); ?></p>
                </div>
                <?php
            }
        }
    }
} 