<?php
/**
 * Plugin Name: WP Easy Staging
 * Plugin URI: https://github.com/omnisend/wp-easy-staging
 * Description: A free, open-source WordPress plugin that allows users to quickly create staging environments, make changes, and push them back to production with conflict resolution.
 * Version: 1.0.0
 * Author: CloudFest
 * Author URI: https://github.com/omnisend
 * Text Domain: wp-easy-staging
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_EASY_STAGING_VERSION', '1.0.0');
define('WP_EASY_STAGING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_EASY_STAGING_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_EASY_STAGING_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Enable development mode when in Docker environment
define('WP_EASY_STAGING_DOCKER_DEV', true);

/**
 * Main WP_Easy_Staging Class.
 *
 * @since 1.0.0
 */
class WP_Easy_Staging {

    /**
     * Instance of this class.
     *
     * @since 1.0.0
     * @var object
     */
    protected static $instance = null;

    /**
     * The plugin name.
     *
     * @since 1.0.0
     * @var string
     */
    private $plugin_name;

    /**
     * The plugin version.
     *
     * @since 1.0.0
     * @var string
     */
    private $version;

    /**
     * Main WP_Easy_Staging Instance.
     *
     * Ensures only one instance of WP_Easy_Staging is loaded or can be loaded.
     *
     * @since 1.0.0
     * @static
     * @return WP_Easy_Staging - Main instance.
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->plugin_name = 'wp-easy-staging';
        $this->version = WP_EASY_STAGING_VERSION;
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Get the plugin name.
     *
     * @since 1.0.0
     * @return string The plugin name.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * Get the plugin version.
     *
     * @since 1.0.0
     * @return string The plugin version.
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since 1.0.0
     */
    private function load_dependencies() {
        // Core classes
        require_once WP_EASY_STAGING_PLUGIN_DIR . 'includes/class-wp-easy-staging-loader.php';
        require_once WP_EASY_STAGING_PLUGIN_DIR . 'includes/class-wp-easy-staging-i18n.php';
        
        // Admin and public classes
        require_once WP_EASY_STAGING_PLUGIN_DIR . 'admin/class-wp-easy-staging-admin.php';
        require_once WP_EASY_STAGING_PLUGIN_DIR . 'public/class-wp-easy-staging-public.php';
        
        // Core functionality classes
        require_once WP_EASY_STAGING_PLUGIN_DIR . 'includes/core/class-wp-easy-staging-database.php';
        require_once WP_EASY_STAGING_PLUGIN_DIR . 'includes/core/class-wp-easy-staging-files.php';
        require_once WP_EASY_STAGING_PLUGIN_DIR . 'includes/core/class-wp-easy-staging-staging.php';
        require_once WP_EASY_STAGING_PLUGIN_DIR . 'includes/core/class-wp-easy-staging-cloning.php';
        require_once WP_EASY_STAGING_PLUGIN_DIR . 'includes/core/class-wp-easy-staging-pushing.php';
        require_once WP_EASY_STAGING_PLUGIN_DIR . 'includes/core/class-wp-easy-staging-conflict-detector.php';
        require_once WP_EASY_STAGING_PLUGIN_DIR . 'includes/core/class-wp-easy-staging-conflict-resolver.php';
    }

    /**
     * Register all of the hooks related to the plugin functionality.
     *
     * @since 1.0.0
     */
    private function init_hooks() {
        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Load internationalization
        add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));
        
        // Init Admin and Public classes
        if (is_admin()) {
            new WP_Easy_Staging_Admin($this->get_plugin_name(), $this->get_version());
        }
        
        new WP_Easy_Staging_Public();
    }

    /**
     * The code that runs during plugin activation.
     *
     * @since 1.0.0
     */
    public function activate() {
        // Create necessary database tables
        $database = new WP_Easy_Staging_Database();
        $database->create_tables();
        
        // Add capabilities
        $this->add_capabilities();
        
        // Set version
        update_option('wp_easy_staging_version', WP_EASY_STAGING_VERSION);
        
        // Create plugin directories
        wp_mkdir_p(WP_CONTENT_DIR . '/uploads/wp-easy-staging');
        wp_mkdir_p(WP_CONTENT_DIR . '/uploads/wp-easy-staging/logs');
        wp_mkdir_p(WP_CONTENT_DIR . '/uploads/wp-easy-staging/backups');
        wp_mkdir_p(WP_CONTENT_DIR . '/uploads/wp-easy-staging/staging');
    }

    /**
     * The code that runs during plugin deactivation.
     *
     * @since 1.0.0
     */
    public function deactivate() {
        // Remove capabilities
        $this->remove_capabilities();
    }

    /**
     * Add capabilities to admin roles.
     *
     * @since 1.0.0
     */
    private function add_capabilities() {
        $role = get_role('administrator');
        
        if ($role) {
            $role->add_cap('manage_wp_easy_staging');
        }
    }

    /**
     * Remove capabilities from admin roles.
     *
     * @since 1.0.0
     */
    private function remove_capabilities() {
        $role = get_role('administrator');
        
        if ($role) {
            $role->remove_cap('manage_wp_easy_staging');
        }
    }

    /**
     * Load the plugin text domain for translation.
     *
     * @since 1.0.0
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'wp-easy-staging',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
}

/**
 * Returns the main instance of WP_Easy_Staging.
 *
 * @since 1.0.0
 * @return WP_Easy_Staging
 */
function WP_Easy_Staging() {
    return WP_Easy_Staging::instance();
}

// Initialize the plugin
WP_Easy_Staging(); 