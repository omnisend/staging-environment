<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://github.com/omnisend/wp-easy-staging
 * @since      1.0.0
 *
 * @package    WP_Easy_Staging
 * @subpackage WP_Easy_Staging/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for
 * the public-facing side of the site.
 *
 * @package    WP_Easy_Staging
 * @subpackage WP_Easy_Staging/public
 * @author     CloudFest
 */
class WP_Easy_Staging_Public {

    /**
     * Flag to indicate if the current site is a staging site.
     *
     * @since    1.0.0
     * @access   private
     * @var      boolean    $is_staging_site    True if this is a staging site.
     */
    private $is_staging_site = false;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        // Check if we are in a staging site
        $this->is_staging_site = $this->check_if_staging();
        
        // If we are in a staging site, add the staging notification
        if ($this->is_staging_site) {
            add_action('wp_footer', array($this, 'add_staging_notification'));
            add_action('admin_bar_menu', array($this, 'add_staging_admin_bar_menu'), 100);
        }
    }

    /**
     * Check if the current site is a staging site.
     *
     * @since    1.0.0
     * @return   boolean  True if is a staging site, false otherwise.
     */
    private function check_if_staging() {
        // Check if STAGING_SITE constant is defined
        if (defined('WP_EASY_STAGING_IS_STAGING') && WP_EASY_STAGING_IS_STAGING === true) {
            return true;
        }
        
        // Otherwise check if the current URL matches any of the staging sites' URLs
        global $wpdb;
        
        // Get the current URL
        if (!isset($_SERVER['HTTP_HOST'])) {
            return false; // Not in a web context
        }
        
        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        
        // Check if the database tables exist before querying
        $table_name = $wpdb->prefix . 'wp_easy_staging_sites';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            // Fallback to the option check if DB table doesn't exist
            $options = get_option('wp_easy_staging_settings');
            $staging_url_pattern = isset($options['staging_url_pattern']) ? $options['staging_url_pattern'] : 'staging';
            return strpos($current_url, $staging_url_pattern) !== false;
        }
        
        // Get all staging sites
        $staging_sites = $wpdb->get_results("SELECT * FROM {$table_name} WHERE status = 'active'", ARRAY_A);
        
        if (empty($staging_sites)) {
            // Fallback to the option check if no staging sites are found
            $options = get_option('wp_easy_staging_settings');
            $staging_url_pattern = isset($options['staging_url_pattern']) ? $options['staging_url_pattern'] : 'staging';
            return strpos($current_url, $staging_url_pattern) !== false;
        }
        
        // Check if current URL matches any staging site URL
        foreach ($staging_sites as $site) {
            if (strpos($current_url, $site['staging_url']) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Add staging notification to the footer.
     *
     * @since    1.0.0
     */
    public function add_staging_notification() {
        echo '<div class="wp-easy-staging-notification">';
        echo '<span>' . __('This is a staging site. Changes made here will not affect the production site until pushed.', 'wp-easy-staging') . '</span>';
        echo '</div>';
        
        // Add staging notification style
        echo '<style>
            .wp-easy-staging-notification {
                position: fixed;
                bottom: 0;
                left: 0;
                width: 100%;
                background-color: #ff5722;
                color: #ffffff;
                text-align: center;
                padding: 10px;
                z-index: 9999;
                font-size: 14px;
                font-weight: bold;
            }
        </style>';
    }

    /**
     * Add staging menu to the admin bar.
     *
     * @since    1.0.0
     * @param    WP_Admin_Bar    $wp_admin_bar    The WordPress admin bar object.
     */
    public function add_staging_admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('manage_wp_easy_staging')) {
            return;
        }
        
        // Add the parent menu
        $wp_admin_bar->add_node(array(
            'id'    => 'wp-easy-staging',
            'title' => __('Staging', 'wp-easy-staging'),
            'href'  => admin_url('admin.php?page=wp-easy-staging'),
            'meta'  => array(
                'class' => 'wp-easy-staging-admin-bar',
                'title' => __('WP Easy Staging', 'wp-easy-staging')
            )
        ));
        
        // Add the child menus
        $wp_admin_bar->add_node(array(
            'id'     => 'wp-easy-staging-push',
            'parent' => 'wp-easy-staging',
            'title'  => __('Push to Production', 'wp-easy-staging'),
            'href'   => admin_url('admin.php?page=wp-easy-staging-push'),
            'meta'   => array(
                'title' => __('Push changes to production', 'wp-easy-staging')
            )
        ));
    }
} 