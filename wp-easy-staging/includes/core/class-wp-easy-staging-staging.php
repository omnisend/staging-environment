<?php
/**
 * Staging functionality for the plugin.
 *
 * @link       https://github.com/omnisend/wp-easy-staging
 * @since      1.0.0
 *
 * @package    WP_Easy_Staging
 * @subpackage WP_Easy_Staging/includes/core
 */

/**
 * Staging functionality class.
 *
 * This class handles creating and managing staging sites.
 *
 * @since      1.0.0
 * @package    WP_Easy_Staging
 * @subpackage WP_Easy_Staging/includes/core
 * @author     CloudFest
 */
class WP_Easy_Staging_Staging {

    /**
     * Database instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      WP_Easy_Staging_Database    $db    Database instance.
     */
    private $db;

    /**
     * Files instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      WP_Easy_Staging_Files    $files    Files instance.
     */
    private $files;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->db = new WP_Easy_Staging_Database();
        $this->files = new WP_Easy_Staging_Files();
    }

    /**
     * Create a new staging site.
     *
     * @since    1.0.0
     * @param    string    $name    Optional. The staging site name.
     * @return   mixed              Staging site information or WP_Error.
     */
    public function create_staging_site($name = '') {
        global $wpdb;
        
        // Start logging
        $this->log("Starting creation of new staging site");
        
        // Generate staging site name if not provided
        if (empty($name)) {
            $name = 'staging-' . date('Y-m-d-H-i-s');
        }
        
        // Generate staging prefix for database tables
        $staging_prefix = 'wpstg' . substr(md5(time()), 0, 6) . '_';
        
        // Get production site URL
        $production_url = get_site_url();
        
        // Get staging site URL
        $options = get_option('wp_easy_staging_settings');
        
        // Modified: Use the provided name as the URL pattern, falling back to settings only if necessary
        $staging_url_pattern = $name;
        
        // If name is empty or default staging name format, use the configured pattern
        if (empty($staging_url_pattern) || preg_match('/^staging-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}$/', $staging_url_pattern)) {
            $staging_url_pattern = isset($options['staging_url_pattern']) ? $options['staging_url_pattern'] : 'staging';
        }
        
        // Generate staging URL based on production URL
        $parsed_url = parse_url($production_url);
        $scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] : 'http';
        $host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        
        // Check if we should use a subdomain or a subfolder
        if (isset($options['staging_type']) && $options['staging_type'] === 'subdomain') {
            // Use subdomain
            $staging_url = $scheme . '://' . $staging_url_pattern . '.' . $host;
        } else {
            // Use subfolder
            $staging_url = $production_url . '/' . $staging_url_pattern;
        }
        
        // Determine if we're in Docker development mode
        $is_docker_dev = defined('WP_EASY_STAGING_DOCKER_DEV') && WP_EASY_STAGING_DOCKER_DEV;
        
        // Set staging path based on environment
        if ($is_docker_dev) {
            // In Docker, place staging directly in the web root for proper URL routing
            $staging_path = ABSPATH . $staging_url_pattern;
            $this->log("Docker development mode - setting staging path to web root at: {$staging_path}");
        } else {
            // Modified: Always use ABSPATH for proper URL routing in any environment
            $staging_path = ABSPATH . $staging_url_pattern;
            $this->log("Setting staging path to web root at: {$staging_path}");
        }
        
        // Create staging site record
        $sites_table = $wpdb->prefix . 'wp_easy_staging_sites';
        $wpdb->insert(
            $sites_table,
            array(
                'name' => $name,
                'staging_prefix' => $staging_prefix,
                'date_created' => current_time('mysql'),
                'production_url' => $production_url,
                'staging_url' => $staging_url,
                'staging_path' => $staging_path,
                'status' => 'creating'
            )
        );
        
        $staging_id = $wpdb->insert_id;
        
        if (!$staging_id) {
            $this->log("Failed to create staging site record");
            return new WP_Error('db_error', __('Failed to create staging site record.', 'wp-easy-staging'));
        }
        
        // Clone database tables - do this for both modes
        $this->log("Cloning database tables");
        $results = $this->db->clone_tables_to_staging($staging_prefix);
        
        // Update URLs in staging database
        $this->log("Updating URLs in staging database");
        $this->db->update_staging_urls($staging_prefix, $production_url, $staging_url);
        
        // Create staging directory if it doesn't exist
        if (!is_dir($staging_path)) {
            $this->log("Creating staging directory: {$staging_path}");
            $result = wp_mkdir_p($staging_path);
            if (!$result) {
                $this->log("Failed to create staging directory: {$staging_path}");
                // Try with system mkdir and higher permissions
                mkdir($staging_path, 0755, true);
            }
        }
        
        // If we're in Docker dev mode, we'll do a basic copy of essential files
        if ($is_docker_dev) {
            $this->log("Docker development mode - creating WordPress structure");
            
            // Create basic structure
            $wp_includes_dir = $staging_path . '/wp-includes';
            $wp_admin_dir = $staging_path . '/wp-admin';
            $wp_content_dir = $staging_path . '/wp-content';
            
            // Create essential directories
            if (!is_dir($wp_includes_dir)) mkdir($wp_includes_dir, 0755, true);
            if (!is_dir($wp_admin_dir)) mkdir($wp_admin_dir, 0755, true);
            if (!is_dir($wp_content_dir)) mkdir($wp_content_dir, 0755, true);
            
            // Copy core directories
            $this->log("Copying WordPress core directories and files");
            
            // Copy wp-includes
            $this->log("Copying wp-includes directory");
            $this->files->recursive_copy(ABSPATH . 'wp-includes', $wp_includes_dir);
            
            // Copy wp-admin
            $this->log("Copying wp-admin directory");
            $this->files->recursive_copy(ABSPATH . 'wp-admin', $wp_admin_dir);
            
            // Copy wp-content
            $this->log("Copying wp-content directory");
            $this->files->recursive_copy(ABSPATH . 'wp-content', $wp_content_dir);
            
            // Copy WordPress core files from root directory
            $core_files = array(
                'index.php',
                'wp-activate.php',
                'wp-blog-header.php',
                'wp-comments-post.php',
                'wp-config.php',
                'wp-cron.php',
                'wp-links-opml.php',
                'wp-load.php',
                'wp-login.php',
                'wp-mail.php',
                'wp-settings.php',
                'wp-signup.php',
                'wp-trackback.php',
                'xmlrpc.php'
            );
            
            foreach ($core_files as $file) {
                if (file_exists(ABSPATH . $file)) {
                    $this->log("Copying core file: {$file}");
                    copy(ABSPATH . $file, $staging_path . '/' . $file);
                    chmod($staging_path . '/' . $file, fileperms(ABSPATH . $file));
                }
            }
            
            // Modify wp-config.php for staging
            $config_file = $staging_path . '/wp-config.php';
            if (file_exists($config_file)) {
                $config_content = file_get_contents($config_file);
                
                // Replace table prefix
                $config_content = preg_replace(
                    "/\\\$table_prefix\s*=\s*'.*?';/",
                    "\$table_prefix = '{$staging_prefix}';",
                    $config_content
                );
                
                // Add staging flag
                $staging_config = "\n\n// Staging site configuration\n";
                $staging_config .= "define('WP_EASY_STAGING_IS_STAGING', true);\n";
                $staging_config .= "define('WP_DEBUG', true);\n";
                $staging_config .= "define('WP_DEBUG_LOG', true);\n";
                $staging_config .= "define('WP_DEBUG_DISPLAY', true);\n";
                
                // Insert staging config before "That's all" comment
                $config_content = preg_replace(
                    "/(\/\* That's all, stop editing!.*$)/sm",
                    $staging_config . "$1",
                    $config_content
                );
                
                file_put_contents($config_file, $config_content);
            }
            
            // Create .htaccess for proper URL handling
            $htaccess_content = "
# BEGIN WordPress Staging
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /{$staging_url_pattern}/
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /{$staging_url_pattern}/index.php [L]
</IfModule>
# END WordPress Staging
";
            file_put_contents($staging_path . '/.htaccess', $htaccess_content);
            
            // Create a test file to verify staging site
            $test_content = "This is a test file created by WP Easy Staging. Your staging site appears to be working!";
            file_put_contents($staging_path . '/staging-test.txt', $test_content);
            
            // Generate test changes that can be pushed to production
            $this->generate_test_changes($staging_id, $staging_prefix);
            
            $this->log("Docker development mode: Created staging structure at {$staging_path}");
        } else {
            // Modified: Always create the basic directory structure regardless of environment
            $this->log("Creating basic WordPress structure at {$staging_path}");
            
            // Create basic structure
            $wp_includes_dir = $staging_path . '/wp-includes';
            $wp_admin_dir = $staging_path . '/wp-admin';
            $wp_content_dir = $staging_path . '/wp-content';
            
            // Create essential directories
            if (!is_dir($wp_includes_dir)) mkdir($wp_includes_dir, 0755, true);
            if (!is_dir($wp_admin_dir)) mkdir($wp_admin_dir, 0755, true);
            if (!is_dir($wp_content_dir)) mkdir($wp_content_dir, 0755, true);
            
            // Copy necessary WordPress files
            $this->log("Copying files to staging directory");
            $root_directory = ABSPATH;
            $results = $this->files->copy_files($root_directory, $staging_path);
            
            if (is_wp_error($results)) {
                $this->log("Failed to copy files: " . $results->get_error_message());
                $this->update_staging_status($staging_id, 'failed');
                return $results;
            }
            
            // Create or update wp-config.php for staging
            $this->log("Creating wp-config.php for staging");
            $this->create_staging_config($staging_path, $staging_prefix);
            
            // Create or update .htaccess for staging
            $this->log("Creating .htaccess for staging");
            $this->create_staging_htaccess($staging_path);
        }
        
        // Update staging site record
        $this->update_staging_status($staging_id, 'active');
        
        $this->log("Staging site created successfully");
        
        // Return staging site information
        return array(
            'id' => $staging_id,
            'name' => $name,
            'url' => $staging_url,
            'path' => $staging_path,
            'prefix' => $staging_prefix
        );
    }

    /**
     * Create wp-config.php for staging.
     *
     * @since    1.0.0
     * @param    string    $staging_path     The staging site path.
     * @param    string    $staging_prefix   The staging database prefix.
     * @return   boolean                     True on success, false on failure.
     */
    private function create_staging_config($staging_path, $staging_prefix) {
        // Get path to wp-config.php
        $config_path = ABSPATH . 'wp-config.php';
        $staging_config_path = $staging_path . '/wp-config.php';
        
        // Read the original wp-config.php
        $config_content = file_get_contents($config_path);
        
        if (!$config_content) {
            return false;
        }
        
        // Replace database prefix
        $pattern = "/table_prefix\s*=\s*['\"].*?['\"]/";
        $replacement = "table_prefix = '{$staging_prefix}'";
        $config_content = preg_replace($pattern, $replacement, $config_content);
        
        // Add staging site flag
        $staging_flag = "
/* WP Easy Staging Configuration */
define('WP_EASY_STAGING_IS_STAGING', true);
";
        
        // Insert the staging flag before the WordPress absolute path definition
        $pattern = "/\/\* That's all, stop editing!.*?\*\//s";
        $replacement = $staging_flag . "$0";
        $config_content = preg_replace($pattern, $replacement, $config_content);
        
        // Write the modified content to the staging config file
        return file_put_contents($staging_config_path, $config_content) !== false;
    }

    /**
     * Create .htaccess for staging.
     *
     * @since    1.0.0
     * @param    string    $staging_path    The staging site path.
     * @return   boolean                    True on success, false on failure.
     */
    private function create_staging_htaccess($staging_path) {
        // Get path to .htaccess
        $htaccess_path = ABSPATH . '.htaccess';
        $staging_htaccess_path = $staging_path . '/.htaccess';
        
        // Check if the original .htaccess exists
        if (file_exists($htaccess_path)) {
            // Read the original .htaccess
            $htaccess_content = file_get_contents($htaccess_path);
            
            if (!$htaccess_content) {
                return false;
            }
            
            // Write the content to the staging .htaccess
            return file_put_contents($staging_htaccess_path, $htaccess_content) !== false;
        } else {
            // Create a default .htaccess
            $default_htaccess = "
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
";
            
            return file_put_contents($staging_htaccess_path, $default_htaccess) !== false;
        }
    }

    /**
     * Update staging site status.
     *
     * @since    1.0.0
     * @param    int       $staging_id    The staging site ID.
     * @param    string    $status        The new status.
     * @return   boolean                  True on success, false on failure.
     */
    private function update_staging_status($staging_id, $status) {
        global $wpdb;
        
        $sites_table = $wpdb->prefix . 'wp_easy_staging_sites';
        
        return $wpdb->update(
            $sites_table,
            array('status' => $status),
            array('id' => $staging_id)
        );
    }

    /**
     * Get staging site information.
     *
     * @since    1.0.0
     * @param    int       $staging_id    The staging site ID.
     * @return   array                    Staging site information.
     */
    public function get_staging_site($staging_id) {
        global $wpdb;
        
        $sites_table = $wpdb->prefix . 'wp_easy_staging_sites';
        
        $site = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$sites_table}` WHERE id = %d",
                $staging_id
            ),
            ARRAY_A
        );
        
        return $site;
    }

    /**
     * Get all staging sites.
     *
     * @since    1.0.0
     * @return   array    List of staging sites.
     */
    public function get_staging_sites() {
        global $wpdb;
        
        $sites_table = $wpdb->prefix . 'wp_easy_staging_sites';
        
        $sites = $wpdb->get_results(
            "SELECT * FROM `{$sites_table}` ORDER BY date_created DESC",
            ARRAY_A
        );
        
        return $sites;
    }

    /**
     * Delete a staging site.
     *
     * @since    1.0.0
     * @param    int       $staging_id    The staging site ID.
     * @return   boolean                  True on success, false on failure.
     */
    public function delete_staging_site($staging_id) {
        global $wpdb;
        
        // Get staging site information
        $site = $this->get_staging_site($staging_id);
        
        if (!$site) {
            return false;
        }
        
        // Log operation
        $this->log("Deleting staging site: {$site['name']}");
        
        // Delete staging directory
        $staging_path = $site['staging_path'];
        if (is_dir($staging_path)) {
            $this->rrmdir($staging_path);
        }
        
        // Delete staging database tables
        $staging_prefix = $site['staging_prefix'];
        $tables = $this->db->get_tables($staging_prefix);
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
        }
        
        // Delete staging site record
        $sites_table = $wpdb->prefix . 'wp_easy_staging_sites';
        
        $result = $wpdb->delete(
            $sites_table,
            array('id' => $staging_id)
        );
        
        // Delete related records
        $changes_table = $wpdb->prefix . 'wp_easy_staging_changes';
        $wpdb->delete(
            $changes_table,
            array('staging_id' => $staging_id)
        );
        
        $conflicts_table = $wpdb->prefix . 'wp_easy_staging_conflicts';
        $wpdb->delete(
            $conflicts_table,
            array('staging_id' => $staging_id)
        );
        
        $this->log("Staging site deleted successfully");
        
        return $result;
    }

    /**
     * Recursively remove a directory.
     *
     * @since    1.0.0
     * @param    string    $dir    Directory path.
     * @return   boolean           True on success, false on failure.
     */
    private function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . DIRECTORY_SEPARATOR . $object)) {
                        $this->rrmdir($dir . DIRECTORY_SEPARATOR . $object);
                    } else {
                        unlink($dir . DIRECTORY_SEPARATOR . $object);
                    }
                }
            }
            rmdir($dir);
            return true;
        }
        return false;
    }

    /**
     * Get staging status.
     *
     * @since    1.0.0
     * @return   array       Staging status information.
     */
    public function get_staging_status() {
        // Get all staging sites
        $sites = $this->get_staging_sites();
        
        // Get the active staging site
        $active_site = null;
        foreach ($sites as $site) {
            if ($site['status'] === 'active') {
                $active_site = $site;
                break;
            }
        }
        
        if (!$active_site) {
            return array(
                'has_staging' => false
            );
        }
        
        // Get tracked changes
        $changes = $this->db->get_tracked_changes($active_site['id']);
        
        return array(
            'has_staging' => true,
            'staging' => $active_site,
            'changes_count' => count($changes)
        );
    }

    /**
     * Log a message.
     *
     * @since    1.0.0
     * @param    string    $message    The message to log.
     * @return   void
     */
    private function log($message) {
        $log_dir = WP_CONTENT_DIR . '/uploads/wp-easy-staging/logs';
        
        if (!is_dir($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        $log_file = $log_dir . '/staging.log';
        $date = date('Y-m-d H:i:s');
        $log_message = "[{$date}] {$message}\n";
        
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }

    /**
     * Generate test changes for development mode.
     *
     * @since    1.0.0
     * @param    int       $staging_id    The staging site ID.
     * @param    string    $staging_prefix  The staging database prefix.
     * @return   void
     */
    private function generate_test_changes($staging_id, $staging_prefix) {
        global $wpdb;
        $this->log("Generating test changes for development mode");
        
        // Create a changes tracking table entry for a sample post
        $changes_table = $wpdb->prefix . 'wp_easy_staging_changes';
        
        // 1. Simulate a new post creation in staging
        $wpdb->insert(
            $changes_table,
            array(
                'staging_id' => $staging_id,
                'change_type' => 'insert',
                'item_type' => 'post',
                'item_id' => 'demo_post_1',
                'date_changed' => current_time('mysql'),
                'data' => json_encode(array(
                    'title' => 'Test Post Created in Staging',
                    'content' => 'This is a test post created to simulate content added in the staging environment.',
                    'status' => 'publish'
                )),
                'pushed' => 0
            )
        );
        
        // 2. Simulate a post modification in staging
        $wpdb->insert(
            $changes_table,
            array(
                'staging_id' => $staging_id,
                'change_type' => 'update',
                'item_type' => 'post',
                'item_id' => 'demo_post_2',
                'date_changed' => current_time('mysql'),
                'data' => json_encode(array(
                    'title' => 'Modified Post in Staging',
                    'content' => 'This post was modified in the staging environment.',
                    'status' => 'publish'
                )),
                'pushed' => 0
            )
        );
        
        // 3. Simulate a theme option change in staging
        $wpdb->insert(
            $changes_table,
            array(
                'staging_id' => $staging_id,
                'change_type' => 'update',
                'item_type' => 'option',
                'item_id' => 'theme_mods',
                'date_changed' => current_time('mysql'),
                'data' => json_encode(array(
                    'header_color' => '#3366cc',
                    'footer_text' => 'This is a custom footer text added in staging'
                )),
                'pushed' => 0
            )
        );
        
        // 4. Simulate a plugin option change
        $wpdb->insert(
            $changes_table,
            array(
                'staging_id' => $staging_id,
                'change_type' => 'update',
                'item_type' => 'option',
                'item_id' => 'plugin_settings',
                'date_changed' => current_time('mysql'),
                'data' => json_encode(array(
                    'enable_feature' => 'yes',
                    'api_key' => 'test_key_from_staging'
                )),
                'pushed' => 0
            )
        );
        
        // 5. Create a conflict scenario
        $conflicts_table = $wpdb->prefix . 'wp_easy_staging_conflicts';
        $wpdb->insert(
            $conflicts_table,
            array(
                'staging_id' => $staging_id,
                'item_type' => 'post',
                'item_id' => 'demo_conflict_post',
                'staging_data' => json_encode(array(
                    'title' => 'Staging Version of Post',
                    'content' => 'This content was modified in staging environment.'
                )),
                'production_data' => json_encode(array(
                    'title' => 'Production Version of Post',
                    'content' => 'This content was modified in production environment.'
                )),
                'resolved' => 0,
                'resolution' => '',
                'date_detected' => current_time('mysql')
            )
        );
        
        $this->log("Test changes generated successfully");
    }
} 