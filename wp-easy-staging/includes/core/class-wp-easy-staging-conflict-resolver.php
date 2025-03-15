<?php
/**
 * Conflict resolution functionality for the plugin.
 *
 * @link       https://github.com/omnisend/wp-easy-staging
 * @since      1.0.0
 *
 * @package    WP_Easy_Staging
 * @subpackage WP_Easy_Staging/includes/core
 */

/**
 * Conflict resolution class.
 *
 * This class handles resolving conflicts between staging and production.
 *
 * @since      1.0.0
 * @package    WP_Easy_Staging
 * @subpackage WP_Easy_Staging/includes/core
 * @author     CloudFest
 */
class WP_Easy_Staging_Conflict_Resolver {

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
     * Staging instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      WP_Easy_Staging_Staging    $staging    Staging instance.
     */
    private $staging;

    /**
     * Conflict detector instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      WP_Easy_Staging_Conflict_Detector    $conflict_detector    Conflict detector instance.
     */
    private $conflict_detector;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->db = new WP_Easy_Staging_Database();
        $this->files = new WP_Easy_Staging_Files();
        $this->staging = new WP_Easy_Staging_Staging();
        $this->conflict_detector = new WP_Easy_Staging_Conflict_Detector();
    }

    /**
     * Resolve conflicts.
     *
     * @since    1.0.0
     * @param    array     $resolutions    Conflict resolutions.
     * @return   mixed                     Result of resolving conflicts.
     */
    public function resolve_conflicts($resolutions = array()) {
        // Get the active staging site
        $sites = $this->staging->get_staging_sites();
        $active_site = null;
        
        foreach ($sites as $site) {
            if ($site['status'] === 'active') {
                $active_site = $site;
                break;
            }
        }
        
        if (!$active_site) {
            return new WP_Error('no_active_staging', __('No active staging site found.', 'wp-easy-staging'));
        }
        
        // Start logging
        $this->log("Starting conflict resolution for staging site ID: {$active_site['id']}");
        
        // Initialize results array
        $results = array(
            'success' => true,
            'resolved_conflicts' => 0,
            'failed_conflicts' => 0,
            'items' => array()
        );
        
        // Process each resolution
        foreach ($resolutions as $resolution) {
            // Validate resolution data
            if (!isset($resolution['id']) || !isset($resolution['resolution'])) {
                $this->log("Invalid resolution data: " . print_r($resolution, true));
                continue;
            }
            
            $conflict_id = $resolution['id'];
            $resolution_type = $resolution['resolution']; // 'staging', 'production', or 'custom'
            $custom_data = isset($resolution['custom_data']) ? $resolution['custom_data'] : '';
            
            // Get conflict details
            $conflict = $this->conflict_detector->get_conflict_details($conflict_id);
            
            if (empty($conflict)) {
                $this->log("Conflict not found: {$conflict_id}");
                continue;
            }
            
            $this->log("Resolving conflict ID: {$conflict_id}, Type: {$conflict['item_type']}, Resolution: {$resolution_type}");
            
            // Resolve conflict based on type
            if ($conflict['item_type'] === 'database') {
                $success = $this->resolve_database_conflict($active_site, $conflict, $resolution_type, $custom_data);
            } else if ($conflict['item_type'] === 'file') {
                $success = $this->resolve_file_conflict($active_site, $conflict, $resolution_type, $custom_data);
            } else {
                $this->log("Unknown conflict type: {$conflict['item_type']}");
                $success = false;
            }
            
            // Update results
            if ($success) {
                $results['resolved_conflicts']++;
                
                // Add item to the list of items to push
                if ($conflict['item_type'] === 'database') {
                    // Extract table name from item_id
                    $parts = explode('.', $conflict['item_id']);
                    $table = $parts[0];
                    
                    $results['items'][] = 'db_' . $table;
                } else if ($conflict['item_type'] === 'file') {
                    $results['items'][] = 'modified_' . $conflict['item_id'];
                }
                
                // Mark conflict as resolved in the database
                $this->db->resolve_conflict($conflict_id, $resolution_type, $custom_data);
            } else {
                $results['failed_conflicts']++;
                $results['success'] = false;
            }
        }
        
        $this->log("Conflict resolution completed. Resolved: {$results['resolved_conflicts']}, Failed: {$results['failed_conflicts']}");
        
        return $results;
    }

    /**
     * Resolve a database conflict.
     *
     * @since    1.0.0
     * @param    array     $staging_site      Active staging site data.
     * @param    array     $conflict          Conflict data.
     * @param    string    $resolution_type   Resolution type (staging, production, custom).
     * @param    mixed     $custom_data       Custom resolution data.
     * @return   boolean                      Success or failure.
     */
    private function resolve_database_conflict($staging_site, $conflict, $resolution_type, $custom_data) {
        global $wpdb;
        
        // Get staging database prefix
        $staging_prefix = $staging_site['staging_prefix'];
        
        // Extract table name and primary key from item_id
        $parts = explode('.', $conflict['item_id']);
        
        if (count($parts) !== 2) {
            $this->log("Invalid database conflict item_id: {$conflict['item_id']}");
            return false;
        }
        
        $table = $parts[0];
        $primary_key_value = $parts[1];
        
        // Get primary key column name
        $primary_key_column = $this->get_primary_key_column($wpdb->prefix . $table);
        
        if (!$primary_key_column) {
            $this->log("Failed to get primary key column for table: {$table}");
            return false;
        }
        
        // Determine which data to use based on resolution type
        if ($resolution_type === 'staging') {
            // Use staging data
            $data = $conflict['staging_data'];
        } else if ($resolution_type === 'production') {
            // Use production data
            $data = $conflict['production_data'];
        } else if ($resolution_type === 'custom') {
            // Use custom data
            $data = $custom_data;
        } else {
            $this->log("Unknown resolution type: {$resolution_type}");
            return false;
        }
        
        // Update the record in the appropriate database
        if ($resolution_type === 'production') {
            // No need to update production, as we're keeping its data
            return true;
        } else {
            // Update production with either staging or custom data
            $where = array($primary_key_column => $primary_key_value);
            $result = $wpdb->update($wpdb->prefix . $table, $data, $where);
            
            if ($result === false) {
                $this->log("Failed to update production database: " . $wpdb->last_error);
                return false;
            }
            
            return true;
        }
    }

    /**
     * Resolve a file conflict.
     *
     * @since    1.0.0
     * @param    array     $staging_site      Active staging site data.
     * @param    array     $conflict          Conflict data.
     * @param    string    $resolution_type   Resolution type (staging, production, custom).
     * @param    mixed     $custom_data       Custom resolution data.
     * @return   boolean                      Success or failure.
     */
    private function resolve_file_conflict($staging_site, $conflict, $resolution_type, $custom_data) {
        // Get file paths
        $file_path = $conflict['item_id'];
        $staging_path = $staging_site['staging_path'];
        $production_path = ABSPATH;
        
        // Determine what content to use
        if ($resolution_type === 'staging') {
            // Use staging content
            $content = $conflict['staging_data'];
        } else if ($resolution_type === 'production') {
            // Use production content
            $content = $conflict['production_data'];
        } else if ($resolution_type === 'custom') {
            // Use custom content
            $content = $custom_data;
        } else {
            $this->log("Unknown resolution type: {$resolution_type}");
            return false;
        }
        
        // Resolve the conflict
        if ($resolution_type === 'production') {
            // If we're keeping production content, no action needed
            return true;
        } else {
            // Apply staging or custom content to production file
            $production_file = $production_path . '/' . $file_path;
            
            // Create directory if it doesn't exist
            $dir = dirname($production_file);
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }
            
            // Write content to file
            $result = file_put_contents($production_file, $content);
            
            if ($result === false) {
                $this->log("Failed to write to production file: {$production_file}");
                return false;
            }
            
            return true;
        }
    }

    /**
     * Get primary key column for a table.
     *
     * @since    1.0.0
     * @param    string    $table    Table name.
     * @return   string              Primary key column name.
     */
    private function get_primary_key_column($table) {
        global $wpdb;
        
        $result = $wpdb->get_results("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'");
        
        if (empty($result)) {
            return false;
        }
        
        return $result[0]->Column_name;
    }

    /**
     * Get conflict resolution options.
     *
     * @since    1.0.0
     * @param    int       $conflict_id    Conflict ID.
     * @return   array                     Resolution options.
     */
    public function get_resolution_options($conflict_id) {
        // Get conflict details
        $conflict = $this->conflict_detector->get_conflict_details($conflict_id);
        
        if (empty($conflict)) {
            return array();
        }
        
        // Basic resolution options
        $options = array(
            'staging' => __('Use staging version', 'wp-easy-staging'),
            'production' => __('Use production version', 'wp-easy-staging'),
            'custom' => __('Use custom resolution', 'wp-easy-staging')
        );
        
        // Add additional context based on conflict type
        if ($conflict['item_type'] === 'database') {
            // For database conflicts, we can show a comparison of the data
            $staging_data = $conflict['staging_data'];
            $production_data = $conflict['production_data'];
            
            // Extract differences
            $diff = array();
            
            foreach ($staging_data as $key => $value) {
                if (isset($production_data[$key]) && $value !== $production_data[$key]) {
                    $diff[$key] = array(
                        'staging' => $value,
                        'production' => $production_data[$key]
                    );
                }
            }
            
            $options['context'] = array(
                'diff' => $diff,
                'staging_data' => $staging_data,
                'production_data' => $production_data
            );
        } else if ($conflict['item_type'] === 'file') {
            // For file conflicts, we can show a diff of the content
            $options['context'] = array(
                'staging_content' => $conflict['staging_data'],
                'production_content' => $conflict['production_data'],
                'diff_html' => $this->generate_diff_html($conflict['staging_data'], $conflict['production_data'])
            );
        }
        
        return $options;
    }

    /**
     * Generate HTML diff between two strings.
     *
     * @since    1.0.0
     * @param    string    $content1    First content.
     * @param    string    $content2    Second content.
     * @return   string                 HTML diff.
     */
    private function generate_diff_html($content1, $content2) {
        require_once(ABSPATH . 'wp-includes/class-wp-text-diff-renderer-table.php');
        
        // Split content into lines
        $lines1 = explode("\n", $content1);
        $lines2 = explode("\n", $content2);
        
        // Create Text_Diff object
        $diff = new Text_Diff('auto', array($lines2, $lines1));
        
        // Create renderer
        $renderer = new WP_Text_Diff_Renderer_Table();
        
        // Render diff
        $diff_html = $renderer->render($diff);
        
        return $diff_html;
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
        
        $log_file = $log_dir . '/resolver.log';
        $date = date('Y-m-d H:i:s');
        $log_message = "[{$date}] {$message}\n";
        
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }
} 