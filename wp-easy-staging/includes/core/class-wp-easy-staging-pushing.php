<?php
/**
 * Pushing functionality for the plugin.
 *
 * @link       https://github.com/omnisend/wp-easy-staging
 * @since      1.0.0
 *
 * @package    WP_Easy_Staging
 * @subpackage WP_Easy_Staging/includes/core
 */

/**
 * Pushing functionality class.
 *
 * This class handles pushing changes from staging to production.
 *
 * @since      1.0.0
 * @package    WP_Easy_Staging
 * @subpackage WP_Easy_Staging/includes/core
 * @author     CloudFest
 */
class WP_Easy_Staging_Pushing {

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
     * Push changes from staging to production.
     *
     * @since    1.0.0
     * @param    array     $selected_items    Items selected for pushing.
     * @return   mixed                        Result of pushing changes.
     */
    public function push_to_production($selected_items) {
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
        $this->log("Starting push to production for staging site ID: {$active_site['id']}");

        // If we're in development mode, handle the push differently
        if (defined('WP_EASY_STAGING_DOCKER_DEV') && WP_EASY_STAGING_DOCKER_DEV) {
            $this->log("Development mode detected - simulating push to production");
            
            // Mark all changes as pushed in development mode
            $result = $this->mark_changes_pushed($active_site['id'], $selected_items);
            
            if ($result) {
                $this->log("Development mode: Successfully marked changes as pushed");
                
                return array(
                    'success' => true,
                    'message' => __('Changes pushed to production successfully in development mode.', 'wp-easy-staging'),
                    'db_changes' => count($selected_items),
                    'file_changes' => 0
                );
            } else {
                $this->log("Development mode: Failed to mark changes as pushed");
                
                return array(
                    'success' => false,
                    'message' => __('Failed to push changes in development mode.', 'wp-easy-staging')
                );
            }
        }
        
        // Check for conflicts first
        $conflicts = $this->conflict_detector->detect_conflicts($selected_items);
        
        if (!empty($conflicts)) {
            $this->log("Conflicts detected. Push aborted.");
            return new WP_Error('conflicts_detected', __('Conflicts detected. Please resolve them before pushing changes.', 'wp-easy-staging'));
        }
        
        // Proceed with pushing changes
        $this->log("No conflicts detected. Proceeding with push.");
        
        // Initialize results array
        $results = array(
            'success' => true,
            'db_changes' => 0,
            'file_changes' => 0,
            'failed_items' => array()
        );
        
        // Process database changes
        $db_result = $this->push_database_changes($active_site, $selected_items);
        
        if (is_wp_error($db_result)) {
            $this->log("Error pushing database changes: " . $db_result->get_error_message());
            $results['success'] = false;
            $results['failed_items'][] = 'database';
        } else {
            $results['db_changes'] = $db_result;
        }
        
        // Process file changes
        $file_result = $this->push_file_changes($active_site, $selected_items);
        
        if (is_wp_error($file_result)) {
            $this->log("Error pushing file changes: " . $file_result->get_error_message());
            $results['success'] = false;
            $results['failed_items'][] = 'files';
        } else {
            $results['file_changes'] = $file_result;
        }
        
        // Mark pushed changes as pushed in the database
        if ($results['success']) {
            $this->mark_changes_pushed($active_site['id'], $selected_items);
        }
        
        $this->log("Push completed. Success: " . ($results['success'] ? 'true' : 'false'));
        
        return $results;
    }

    /**
     * Push database changes from staging to production.
     *
     * @since    1.0.0
     * @param    array     $staging_site     Active staging site data.
     * @param    array     $selected_items   Items selected for pushing.
     * @return   mixed                       Result of pushing database changes.
     */
    private function push_database_changes($staging_site, $selected_items) {
        global $wpdb;
        
        $this->log("Starting database changes push");
        
        // Get the staging database prefix
        $staging_prefix = $staging_site['staging_prefix'];
        
        // Track the number of changed tables
        $changed_tables = 0;
        
        // Process each selected table change
        foreach ($selected_items as $item) {
            // Skip non-database items
            if (strpos($item, 'db_') !== 0) {
                continue;
            }
            
            // Extract table name from item ID
            $table = substr($item, 3); // Remove 'db_' prefix
            
            $this->log("Processing table: {$table}");
            
            // Get table changes
            $changes = $this->db->get_table_changes($staging_prefix, $table);
            
            if (is_wp_error($changes)) {
                $this->log("Error getting changes for table {$table}: " . $changes->get_error_message());
                continue;
            }
            
            // Push changes to production
            $result = $this->db->push_table_changes($staging_prefix, $table, $changes);
            
            if ($result) {
                $changed_tables++;
                $this->log("Successfully pushed changes for table {$table}");
            } else {
                $this->log("Failed to push changes for table {$table}");
            }
        }
        
        $this->log("Database changes push completed. Changed tables: {$changed_tables}");
        
        return $changed_tables;
    }

    /**
     * Push file changes from staging to production.
     *
     * @since    1.0.0
     * @param    array     $staging_site     Active staging site data.
     * @param    array     $selected_items   Items selected for pushing.
     * @return   mixed                       Result of pushing file changes.
     */
    private function push_file_changes($staging_site, $selected_items) {
        $this->log("Starting file changes push");
        
        // Get the staging path
        $staging_path = $staging_site['staging_path'];
        $production_path = ABSPATH;
        
        // Get file changes
        $changes = $this->files->compare_files($staging_path, $production_path);
        
        if (is_wp_error($changes)) {
            $this->log("Error comparing files: " . $changes->get_error_message());
            return $changes;
        }
        
        // Filter changes based on selected items
        $filtered_changes = array(
            'added' => array(),
            'modified' => array(),
            'deleted' => array()
        );
        
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
            
            // Add to filtered changes if it exists in original changes
            if (isset($changes[$change_type]) && in_array($file_path, $changes[$change_type])) {
                $filtered_changes[$change_type][] = $file_path;
            }
        }
        
        // Push filtered changes
        $result = $this->files->sync_files($staging_path, $production_path, $filtered_changes);
        
        if (is_wp_error($result)) {
            $this->log("Error syncing files: " . $result->get_error_message());
            return $result;
        }
        
        $total_changes = count($result['added']) + count($result['modified']) + count($result['deleted']);
        
        $this->log("File changes push completed. Total changed files: {$total_changes}");
        
        return $total_changes;
    }

    /**
     * Mark changes as pushed in the database.
     *
     * @since    1.0.0
     * @param    int       $staging_id       Staging site ID.
     * @param    array     $selected_items   Items selected for pushing.
     * @return   boolean                     Success or failure.
     */
    private function mark_changes_pushed($staging_id, $selected_items) {
        // Get all tracked changes for the staging site
        $changes = $this->db->get_tracked_changes($staging_id);
        
        // Extract change IDs
        $change_ids = array();
        
        foreach ($changes as $change) {
            $item_id = '';
            
            // Build item ID based on change type
            if ($change['item_type'] === 'database') {
                $item_id = 'db_' . $change['item_id'];
            } else {
                $item_id = $change['change_type'] . '_' . $change['item_id'];
            }
            
            // If the item was selected for push, add its ID
            if (in_array($item_id, $selected_items)) {
                $change_ids[] = $change['id'];
            }
        }
        
        // Mark changes as pushed
        if (!empty($change_ids)) {
            $this->log("Marking " . count($change_ids) . " changes as pushed");
            return $this->db->mark_changes_pushed($change_ids);
        }
        
        return true;
    }

    /**
     * Get changes available for pushing.
     *
     * @since    1.0.0
     * @return   array                      List of changes available for pushing.
     */
    public function get_available_changes() {
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
        
        // Get database changes
        $db_changes = $this->get_database_changes($active_site);
        
        // Get file changes
        $file_changes = $this->get_file_changes($active_site);
        
        return array(
            'database' => $db_changes,
            'files' => $file_changes
        );
    }

    /**
     * Get database changes between staging and production.
     *
     * @since    1.0.0
     * @param    array     $staging_site    Active staging site data.
     * @return   array                     List of database changes.
     */
    private function get_database_changes($staging_site) {
        global $wpdb;
        
        $changes = array();
        
        // Get tracking table changes
        $staging_id = $staging_site['id'];
        $changes_table = $wpdb->prefix . 'wp_easy_staging_changes';
        
        // First check if we have any test/simulated changes for development mode
        if (defined('WP_EASY_STAGING_DOCKER_DEV') && WP_EASY_STAGING_DOCKER_DEV) {
            $tracked_changes = $this->db->get_tracked_changes($staging_id, false);
            
            foreach ($tracked_changes as $change) {
                $item_id = $change['item_id'];
                $item_type = $change['item_type'];
                $change_type = $change['change_type'];
                
                $changes[] = array(
                    'id' => 'db_' . $item_id,
                    'type' => $change_type,
                    'name' => $item_type . ': ' . $item_id,
                    'details' => $this->get_change_details($change)
                );
            }
            
            return $changes;
        }
        
        // For production, we'll do the actual database comparison
        // Get the staging database prefix
        $staging_prefix = $staging_site['staging_prefix'];
        
        // Get list of tables in the staging database
        $tables = $this->db->get_tables_with_prefix($staging_prefix);
        
        if (empty($tables)) {
            return $changes;
        }
        
        // Check each table for changes
        foreach ($tables as $staging_table) {
            // Get the corresponding production table
            $production_table = $wpdb->prefix . substr($staging_table, strlen($staging_prefix));
            
            // Check if the production table exists
            if (!$this->db->table_exists($production_table)) {
                // New table in staging
                $changes[] = array(
                    'id' => 'db_' . $production_table,
                    'type' => 'added',
                    'name' => $production_table,
                    'details' => __('New table created in staging', 'wp-easy-staging')
                );
                continue;
            }
            
            // Check for row-level changes
            $table_changes = $this->db->get_table_changes($staging_prefix, substr($staging_table, strlen($staging_prefix)));
            
            if (!empty($table_changes)) {
                $changes[] = array(
                    'id' => 'db_' . $production_table,
                    'type' => 'modified',
                    'name' => $production_table,
                    'details' => sprintf(__('%d rows changed', 'wp-easy-staging'), count($table_changes))
                );
            }
        }
        
        return $changes;
    }
    
    /**
     * Get a human-readable description of a change.
     *
     * @since    1.0.0
     * @param    array     $change    The change data.
     * @return   string               Human-readable description.
     */
    private function get_change_details($change) {
        $type = $change['change_type'];
        $item_type = $change['item_type'];
        $data = maybe_unserialize($change['data']);
        
        if ($item_type === 'post') {
            if ($type === 'insert') {
                return sprintf(__('New %s: %s', 'wp-easy-staging'), 'post', isset($data['title']) ? $data['title'] : '');
            } elseif ($type === 'update') {
                return sprintf(__('Updated %s: %s', 'wp-easy-staging'), 'post', isset($data['title']) ? $data['title'] : '');
            } elseif ($type === 'delete') {
                return sprintf(__('Deleted %s', 'wp-easy-staging'), 'post');
            }
        } elseif ($item_type === 'option') {
            return sprintf(__('Updated options: %s', 'wp-easy-staging'), $change['item_id']);
        }
        
        return sprintf(__('%s %s: %s', 'wp-easy-staging'), 
            ucfirst($type), 
            $item_type, 
            $change['item_id']
        );
    }

    /**
     * Get file changes between staging and production.
     *
     * @since    1.0.0
     * @param    array     $staging_site    Active staging site data.
     * @return   array                     List of file changes.
     */
    private function get_file_changes($staging_site) {
        $changes = array();
        
        // Get the staging path
        $staging_path = $staging_site['staging_path'];
        $production_path = ABSPATH;
        
        // Compare files
        $file_changes = $this->files->compare_files($staging_path, $production_path);
        
        if (is_wp_error($file_changes)) {
            return $changes;
        }
        
        // Process added files
        foreach ($file_changes['added'] as $file) {
            $changes[] = array(
                'id' => 'added_' . $file,
                'type' => 'added',
                'name' => $file,
                'details' => __('New file', 'wp-easy-staging')
            );
        }
        
        // Process modified files
        foreach ($file_changes['modified'] as $file) {
            $changes[] = array(
                'id' => 'modified_' . $file,
                'type' => 'modified',
                'name' => $file,
                'details' => __('Modified file', 'wp-easy-staging')
            );
        }
        
        // Process deleted files
        foreach ($file_changes['deleted'] as $file) {
            $changes[] = array(
                'id' => 'deleted_' . $file,
                'type' => 'deleted',
                'name' => $file,
                'details' => __('Deleted file', 'wp-easy-staging')
            );
        }
        
        return $changes;
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
        
        $log_file = $log_dir . '/pushing.log';
        $date = date('Y-m-d H:i:s');
        $log_message = "[{$date}] {$message}\n";
        
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }
} 