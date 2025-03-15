<?php
/**
 * Conflict detection functionality for the plugin.
 *
 * @link       https://github.com/omnisend/wp-easy-staging
 * @since      1.0.0
 *
 * @package    WP_Easy_Staging
 * @subpackage WP_Easy_Staging/includes/core
 */

/**
 * Conflict detection class.
 *
 * This class handles detecting conflicts between staging and production.
 *
 * @since      1.0.0
 * @package    WP_Easy_Staging
 * @subpackage WP_Easy_Staging/includes/core
 * @author     CloudFest
 */
class WP_Easy_Staging_Conflict_Detector {

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
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->db = new WP_Easy_Staging_Database();
        $this->files = new WP_Easy_Staging_Files();
        $this->staging = new WP_Easy_Staging_Staging();
    }

    /**
     * Detect conflicts between staging and production.
     *
     * @since    1.0.0
     * @param    array     $selected_items    Items selected for pushing.
     * @return   array                        List of conflicts.
     */
    public function detect_conflicts($selected_items = array()) {
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
            return array();
        }
        
        // Start logging
        $this->log("Starting conflict detection for staging site ID: {$active_site['id']}");
        
        // Initialize conflicts array
        $conflicts = array();
        
        // Detect database conflicts
        $db_conflicts = $this->detect_database_conflicts($active_site, $selected_items);
        
        if (!empty($db_conflicts)) {
            $conflicts = array_merge($conflicts, $db_conflicts);
        }
        
        // Detect file conflicts
        $file_conflicts = $this->detect_file_conflicts($active_site, $selected_items);
        
        if (!empty($file_conflicts)) {
            $conflicts = array_merge($conflicts, $file_conflicts);
        }
        
        $this->log("Conflict detection completed. Conflicts found: " . count($conflicts));
        
        return $conflicts;
    }

    /**
     * Detect database conflicts.
     *
     * @since    1.0.0
     * @param    array     $staging_site     Active staging site data.
     * @param    array     $selected_items   Items selected for pushing.
     * @return   array                       List of database conflicts.
     */
    private function detect_database_conflicts($staging_site, $selected_items) {
        global $wpdb;
        
        $this->log("Detecting database conflicts");
        
        // Get the staging database prefix
        $staging_prefix = $staging_site['staging_prefix'];
        
        // Initialize conflicts array
        $conflicts = array();
        
        // Get the original state of the database when staging was created
        // In a real implementation, this should be retrieved from a stored record
        // For now, we'll just check for concurrent modifications
        
        // Process each selected table
        foreach ($selected_items as $item) {
            // Skip non-database items
            if (strpos($item, 'db_') !== 0) {
                continue;
            }
            
            // Extract table name from item ID
            $table = substr($item, 3); // Remove 'db_' prefix
            
            $this->log("Checking conflicts for table: {$table}");
            
            // Find conflicts for this table
            $table_conflicts = $this->db->find_conflicts($staging_prefix, $table);
            
            if (is_wp_error($table_conflicts)) {
                $this->log("Error checking conflicts for table {$table}: " . $table_conflicts->get_error_message());
                continue;
            }
            
            if (!empty($table_conflicts)) {
                $this->log("Found " . count($table_conflicts) . " conflicts in table {$table}");
                
                // Register conflicts in the database
                foreach ($table_conflicts as $primary_key => $conflict) {
                    $conflict_id = $this->db->register_conflict(
                        $staging_site['id'],
                        'database',
                        $table . '.' . $primary_key,
                        $conflict['staging'],
                        $conflict['production']
                    );
                    
                    $conflicts[] = array(
                        'id' => $conflict_id,
                        'type' => 'database',
                        'item_id' => $table . '.' . $primary_key,
                        'item_type' => 'database',
                        'staging_data' => $conflict['staging'],
                        'production_data' => $conflict['production'],
                        'description' => sprintf(
                            __('Conflict in table %s for record with ID %s', 'wp-easy-staging'),
                            $table,
                            $primary_key
                        )
                    );
                }
            }
        }
        
        $this->log("Database conflict detection completed. Conflicts found: " . count($conflicts));
        
        return $conflicts;
    }

    /**
     * Detect file conflicts.
     *
     * @since    1.0.0
     * @param    array     $staging_site     Active staging site data.
     * @param    array     $selected_items   Items selected for pushing.
     * @return   array                       List of file conflicts.
     */
    private function detect_file_conflicts($staging_site, $selected_items) {
        $this->log("Detecting file conflicts");
        
        // Get the staging path
        $staging_path = $staging_site['staging_path'];
        $production_path = ABSPATH;
        
        // Initialize conflicts array
        $conflicts = array();
        
        // Get the original state of files when staging was created
        // In a real implementation, this should be retrieved from a stored record
        // For now, we'll create a simplified approach
        
        // Get file changes from staging to production
        $changes = $this->files->compare_files($staging_path, $production_path);
        
        if (is_wp_error($changes)) {
            $this->log("Error comparing files: " . $changes->get_error_message());
            return $conflicts;
        }
        
        // We'll need to check if modified files have been modified in production as well
        // This requires comparing with the original state, which we don't have for this simplified implementation
        // In a real implementation, you would store hashes or timestamps of files when creating the staging
        
        // Filter changes based on selected items
        $selected_files = array();
        
        foreach ($selected_items as $item) {
            // Skip database items
            if (strpos($item, 'db_') === 0) {
                continue;
            }
            
            // Extract change type and file path from item ID
            $parts = explode('_', $item, 2);
            
            if (count($parts) !== 2) {
                continue;
            }
            
            $change_type = $parts[0];
            $file_path = $parts[1];
            
            if ($change_type === 'modified' && isset($changes['modified']) && in_array($file_path, $changes['modified'])) {
                $selected_files[] = $file_path;
            }
        }
        
        // For this simplified implementation, we'll check if the file modification time in production
        // is more recent than the staging creation time
        $staging_created_time = strtotime($staging_site['date_created']);
        
        foreach ($selected_files as $file) {
            $staging_file = $staging_path . '/' . $file;
            $production_file = $production_path . '/' . $file;
            
            if (file_exists($production_file)) {
                $production_mod_time = filemtime($production_file);
                
                // If the production file was modified after staging was created, it's a potential conflict
                if ($production_mod_time > $staging_created_time) {
                    $this->log("Potential conflict detected for file: {$file}");
                    
                    // Get file content for comparison
                    $file_content = $this->files->get_file_content_for_comparing($file, $staging_path, $production_path);
                    
                    // Register conflict
                    $conflict_id = $this->db->register_conflict(
                        $staging_site['id'],
                        'file',
                        $file,
                        $file_content['source_content'],
                        $file_content['destination_content']
                    );
                    
                    $conflicts[] = array(
                        'id' => $conflict_id,
                        'type' => 'file',
                        'item_id' => $file,
                        'item_type' => 'file',
                        'staging_data' => $file_content['source_content'],
                        'production_data' => $file_content['destination_content'],
                        'description' => sprintf(
                            __('Conflict in file %s', 'wp-easy-staging'),
                            $file
                        )
                    );
                }
            }
        }
        
        $this->log("File conflict detection completed. Conflicts found: " . count($conflicts));
        
        return $conflicts;
    }

    /**
     * Get details of a specific conflict.
     *
     * @since    1.0.0
     * @param    int       $conflict_id    Conflict ID.
     * @return   array                     Conflict details.
     */
    public function get_conflict_details($conflict_id) {
        global $wpdb;
        
        $conflicts_table = $wpdb->prefix . 'wp_easy_staging_conflicts';
        
        $conflict = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$conflicts_table}` WHERE id = %d",
                $conflict_id
            ),
            ARRAY_A
        );
        
        if (!$conflict) {
            return array();
        }
        
        // Unserialize data
        $conflict['staging_data'] = maybe_unserialize($conflict['staging_data']);
        $conflict['production_data'] = maybe_unserialize($conflict['production_data']);
        $conflict['custom_data'] = maybe_unserialize($conflict['custom_data']);
        
        return $conflict;
    }

    /**
     * Get all unresolved conflicts for a staging site.
     *
     * @since    1.0.0
     * @param    int       $staging_id    Staging site ID.
     * @return   array                    List of unresolved conflicts.
     */
    public function get_unresolved_conflicts($staging_id) {
        return $this->db->get_unresolved_conflicts($staging_id);
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
        
        $log_file = $log_dir . '/conflicts.log';
        $date = date('Y-m-d H:i:s');
        $log_message = "[{$date}] {$message}\n";
        
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }
} 