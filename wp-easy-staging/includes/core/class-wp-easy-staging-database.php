<?php
/**
 * Database operations for the plugin.
 *
 * @link       https://github.com/omnisend/wp-easy-staging
 * @since      1.0.0
 *
 * @package    WP_Easy_Staging
 * @subpackage WP_Easy_Staging/includes/core
 */

/**
 * Database operations class.
 *
 * This class handles database operations for the plugin,
 * including creating and syncing database tables.
 *
 * @since      1.0.0
 * @package    WP_Easy_Staging
 * @subpackage WP_Easy_Staging/includes/core
 * @author     CloudFest
 */
class WP_Easy_Staging_Database {

    /**
     * WordPress database instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      wpdb    $wpdb    WordPress database instance.
     */
    private $wpdb;

    /**
     * Tables to exclude from cloning.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $exclude_tables    Tables to exclude from cloning.
     */
    private $exclude_tables;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Get excluded tables from settings
        $options = get_option('wp_easy_staging_settings');
        $exclude_tables_string = isset($options['exclude_tables']) ? $options['exclude_tables'] : '';
        
        // Default excluded tables - keep this minimal for development
        if (defined('WP_EASY_STAGING_DOCKER_DEV') && WP_EASY_STAGING_DOCKER_DEV) {
            // In development mode, just exclude a few essential tables
            $default_exclude = array(
                $wpdb->prefix . 'wp_easy_staging_sites',
                $wpdb->prefix . 'wp_easy_staging_changes',
                $wpdb->prefix . 'wp_easy_staging_conflicts'
            );
        } else {
            // Default excluded tables for production
            $default_exclude = array(
                $wpdb->prefix . 'users',
                $wpdb->prefix . 'usermeta',
                $wpdb->prefix . 'termmeta',
                $wpdb->prefix . 'terms',
                $wpdb->prefix . 'term_taxonomy',
                $wpdb->prefix . 'term_relationships',
                $wpdb->prefix . 'commentmeta',
                $wpdb->prefix . 'comments',
                $wpdb->prefix . 'links',
                $wpdb->prefix . 'options',
                $wpdb->prefix . 'wp_wfconfig',
                $wpdb->prefix . 'wp_wfcrawlers',
                $wpdb->prefix . 'wp_wffilechanges',
                $wpdb->prefix . 'wp_wfhoover',
                $wpdb->prefix . 'wp_wflivetraffichuman',
                $wpdb->prefix . 'wp_wflocs',
                $wpdb->prefix . 'wp_wflogins',
                $wpdb->prefix . 'wp_wfnotifications',
                $wpdb->prefix . 'wp_wfpendingissues',
                $wpdb->prefix . 'wp_wfreversecache',
                $wpdb->prefix . 'wp_wfsnipcache',
                $wpdb->prefix . 'wp_wfstatus',
                $wpdb->prefix . 'wp_wftrafficrate'
            );
        }
        
        // Parse the excluded tables from settings
        if (!empty($exclude_tables_string)) {
            $custom_exclude = explode("\n", $exclude_tables_string);
            $custom_exclude = array_map('trim', $custom_exclude);
            $this->exclude_tables = array_merge($default_exclude, $custom_exclude);
        } else {
            $this->exclude_tables = $default_exclude;
        }
    }

    /**
     * Create necessary plugin tables.
     *
     * @since    1.0.0
     */
    public function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        // Staging sites table
        $table_name = $this->wpdb->prefix . 'wp_easy_staging_sites';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            staging_prefix varchar(100) NOT NULL,
            date_created datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            production_url varchar(255) NOT NULL,
            staging_url varchar(255) NOT NULL,
            staging_path varchar(255) NOT NULL,
            status varchar(20) NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Changes tracking table
        $table_name = $this->wpdb->prefix . 'wp_easy_staging_changes';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            staging_id mediumint(9) NOT NULL,
            change_type varchar(20) NOT NULL,
            item_type varchar(20) NOT NULL,
            item_id varchar(255) NOT NULL,
            date_changed datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            data longtext NOT NULL,
            pushed tinyint(1) DEFAULT 0 NOT NULL,
            PRIMARY KEY  (id),
            KEY staging_id (staging_id),
            KEY item_id (item_id)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Conflicts table
        $table_name = $this->wpdb->prefix . 'wp_easy_staging_conflicts';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            staging_id mediumint(9) NOT NULL,
            item_type varchar(20) NOT NULL,
            item_id varchar(255) NOT NULL,
            staging_data longtext NOT NULL,
            production_data longtext NOT NULL,
            resolved tinyint(1) DEFAULT 0 NOT NULL,
            resolution varchar(20) DEFAULT '' NOT NULL,
            custom_data longtext DEFAULT '' NOT NULL,
            date_detected datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            KEY staging_id (staging_id),
            KEY item_id (item_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    }

    /**
     * Get all tables from the database.
     *
     * @since    1.0.0
     * @param    string    $prefix    Optional. Table prefix to filter by.
     * @return   array                List of tables.
     */
    public function get_tables($prefix = '') {
        if (empty($prefix)) {
            $prefix = $this->wpdb->prefix;
        }
        
        $tables = $this->wpdb->get_results("SHOW TABLES LIKE '{$prefix}%'", ARRAY_N);
        $result = array();
        
        foreach ($tables as $table) {
            $result[] = $table[0];
        }
        
        return $result;
    }

    /**
     * Clone a database table.
     *
     * @since    1.0.0
     * @param    string    $source_table    Source table name.
     * @param    string    $target_table    Target table name.
     * @return   boolean                    True on success, false on failure.
     */
    public function clone_table($source_table, $target_table) {
        // Check if the source table exists
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$source_table}'");
        
        if (!$table_exists) {
            error_log("WP Easy Staging: Source table {$source_table} does not exist");
            return false;
        }
        
        // Drop the target table if it exists
        $result = $this->wpdb->query("DROP TABLE IF EXISTS `{$target_table}`");
        if ($result === false) {
            error_log("WP Easy Staging: Error dropping table {$target_table}: " . $this->wpdb->last_error);
        }
        
        // Create the structure
        $result = $this->wpdb->query("CREATE TABLE `{$target_table}` LIKE `{$source_table}`");
        if ($result === false) {
            error_log("WP Easy Staging: Error creating table structure for {$target_table}: " . $this->wpdb->last_error);
            return false;
        }
        
        // Copy the data
        $result = $this->wpdb->query("INSERT INTO `{$target_table}` SELECT * FROM `{$source_table}`");
        if ($result === false) {
            error_log("WP Easy Staging: Error copying data to {$target_table}: " . $this->wpdb->last_error);
            return false;
        }
        
        return true;
    }

    /**
     * Get all database tables to clone.
     *
     * @since    1.0.0
     * @return   array    List of tables to clone.
     */
    public function get_tables_to_clone() {
        $tables = $this->get_tables();
        
        // Exclude tables from the list
        $tables_to_clone = array_diff($tables, $this->exclude_tables);
        
        return $tables_to_clone;
    }

    /**
     * Clone all database tables to staging.
     *
     * @since    1.0.0
     * @param    string    $staging_prefix    The staging database prefix.
     * @return   array                        Results of the cloning operation.
     */
    public function clone_tables_to_staging($staging_prefix) {
        $tables_to_clone = $this->get_tables_to_clone();
        $results = array(
            'success' => array(),
            'failed' => array()
        );
        
        error_log("WP Easy Staging: Beginning database cloning. Tables to clone: " . count($tables_to_clone));
        
        foreach ($tables_to_clone as $table) {
            // Get the table name without the prefix
            $table_without_prefix = str_replace($this->wpdb->prefix, '', $table);
            
            // Create the new table name with the staging prefix
            $staging_table = $staging_prefix . $table_without_prefix;
            
            error_log("WP Easy Staging: Cloning table {$table} to {$staging_table}");
            
            // Clone the table
            $success = $this->clone_table($table, $staging_table);
            
            if ($success) {
                $results['success'][] = $table;
                error_log("WP Easy Staging: Successfully cloned table {$table}");
            } else {
                $results['failed'][] = $table;
                error_log("WP Easy Staging: Failed to clone table {$table}");
            }
        }
        
        error_log("WP Easy Staging: Database cloning completed. Success: " . count($results['success']) . ", Failed: " . count($results['failed']));
        
        return $results;
    }

    /**
     * Update URLs in the staging database.
     *
     * @since    1.0.0
     * @param    string    $staging_prefix       The staging database prefix.
     * @param    string    $production_url       The production site URL.
     * @param    string    $staging_url          The staging site URL.
     * @return   boolean                         True on success, false on failure.
     */
    public function update_staging_urls($staging_prefix, $production_url, $staging_url) {
        // Update the WordPress options table
        $options_table = $staging_prefix . 'options';
        
        // Update siteurl
        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE `{$options_table}` SET option_value = %s WHERE option_name = 'siteurl'",
                $staging_url
            )
        );
        
        // Update home
        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE `{$options_table}` SET option_value = %s WHERE option_name = 'home'",
                $staging_url
            )
        );
        
        // Update URLs in posts and postmeta
        $posts_table = $staging_prefix . 'posts';
        $postmeta_table = $staging_prefix . 'postmeta';
        
        // Replace URLs in post content
        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE `{$posts_table}` SET post_content = REPLACE(post_content, %s, %s)",
                $production_url,
                $staging_url
            )
        );
        
        // Replace URLs in post metadata
        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE `{$postmeta_table}` SET meta_value = REPLACE(meta_value, %s, %s) 
                WHERE meta_key NOT LIKE '\_%' AND meta_value LIKE %s",
                $production_url,
                $staging_url,
                '%' . $this->wpdb->esc_like($production_url) . '%'
            )
        );
        
        return true;
    }

    /**
     * Get table changes between staging and production.
     *
     * @since    1.0.0
     * @param    string    $staging_prefix    The staging database prefix.
     * @param    string    $table             The table name without prefix.
     * @return   array                        List of changes.
     */
    public function get_table_changes($staging_prefix, $table) {
        $production_table = $this->wpdb->prefix . $table;
        $staging_table = $staging_prefix . $table;
        
        // Get primary key
        $primary_key = $this->get_primary_key($production_table);
        
        if (!$primary_key) {
            return new WP_Error('no_primary_key', __('Could not determine primary key for table.', 'wp-easy-staging'));
        }
        
        // Get all rows from staging
        $staging_rows = $this->wpdb->get_results("SELECT * FROM `{$staging_table}`", ARRAY_A);
        
        // Get all rows from production
        $production_rows = $this->wpdb->get_results("SELECT * FROM `{$production_table}`", ARRAY_A);
        
        // Index rows by primary key
        $staging_indexed = array();
        foreach ($staging_rows as $row) {
            $staging_indexed[$row[$primary_key]] = $row;
        }
        
        $production_indexed = array();
        foreach ($production_rows as $row) {
            $production_indexed[$row[$primary_key]] = $row;
        }
        
        // Find inserted, updated and deleted rows
        $inserted = array_diff_key($staging_indexed, $production_indexed);
        $deleted = array_diff_key($production_indexed, $staging_indexed);
        
        // Find updated rows (rows that exist in both but have different values)
        $updated = array();
        foreach ($staging_indexed as $key => $staging_row) {
            if (isset($production_indexed[$key])) {
                $production_row = $production_indexed[$key];
                
                // Compare rows
                $row_diff = array_diff_assoc($staging_row, $production_row);
                
                if (!empty($row_diff)) {
                    $updated[$key] = array(
                        'production' => $production_row,
                        'staging' => $staging_row,
                        'changes' => $row_diff
                    );
                }
            }
        }
        
        return array(
            'inserted' => $inserted,
            'updated' => $updated,
            'deleted' => $deleted,
            'primary_key' => $primary_key
        );
    }

    /**
     * Get the primary key for a table.
     *
     * @since    1.0.0
     * @param    string    $table    The table name.
     * @return   string              The primary key column name, or false if not found.
     */
    private function get_primary_key($table) {
        $result = $this->wpdb->get_results("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'");
        
        if (!empty($result)) {
            return $result[0]->Column_name;
        }
        
        return false;
    }

    /**
     * Push table changes from staging to production.
     *
     * @since    1.0.0
     * @param    string    $staging_prefix    The staging database prefix.
     * @param    string    $table             The table name without prefix.
     * @param    array     $changes           The changes to push.
     * @return   boolean                      True on success, false on failure.
     */
    public function push_table_changes($staging_prefix, $table, $changes) {
        $production_table = $this->wpdb->prefix . $table;
        $staging_table = $staging_prefix . $table;
        $primary_key = $changes['primary_key'];
        
        // Process inserted rows
        foreach ($changes['inserted'] as $row) {
            $this->wpdb->insert($production_table, $row);
        }
        
        // Process updated rows
        foreach ($changes['updated'] as $key => $update) {
            $where = array($primary_key => $key);
            $this->wpdb->update($production_table, $update['staging'], $where);
        }
        
        // Process deleted rows
        foreach ($changes['deleted'] as $row) {
            $where = array($primary_key => $row[$primary_key]);
            $this->wpdb->delete($production_table, $where);
        }
        
        return true;
    }

    /**
     * Find conflicts between staging and production.
     *
     * @since    1.0.0
     * @param    string    $staging_prefix    The staging database prefix.
     * @param    string    $table             The table name without prefix.
     * @return   array                        List of conflicts.
     */
    public function find_conflicts($staging_prefix, $table) {
        $production_table = $this->wpdb->prefix . $table;
        $staging_table = $staging_prefix . $table;
        
        // Get table changes
        $changes = $this->get_table_changes($staging_prefix, $table);
        
        if (is_wp_error($changes)) {
            return $changes;
        }
        
        // Check for conflicts
        $conflicts = array();
        
        // For now, we'll only look for conflicts in updated rows
        // If the same row has been updated in both staging and production
        // since the staging was created, we consider it a conflict
        foreach ($changes['updated'] as $key => $update) {
            // Here we would need to check against the original state when staging was created
            // For now, we'll just return all updates as potential conflicts
            $conflicts[$key] = array(
                'production' => $update['production'],
                'staging' => $update['staging'],
                'changes' => $update['changes']
            );
        }
        
        return $conflicts;
    }

    /**
     * Register a conflict in the database.
     *
     * @since    1.0.0
     * @param    int       $staging_id         The staging site ID.
     * @param    string    $item_type          The item type (table, file, etc.).
     * @param    string    $item_id            The item ID (table name, file path, etc.).
     * @param    array     $staging_data       The staging data.
     * @param    array     $production_data    The production data.
     * @return   int                           The conflict ID.
     */
    public function register_conflict($staging_id, $item_type, $item_id, $staging_data, $production_data) {
        $conflicts_table = $this->wpdb->prefix . 'wp_easy_staging_conflicts';
        
        $this->wpdb->insert(
            $conflicts_table,
            array(
                'staging_id' => $staging_id,
                'item_type' => $item_type,
                'item_id' => $item_id,
                'staging_data' => maybe_serialize($staging_data),
                'production_data' => maybe_serialize($production_data),
                'resolved' => 0,
                'date_detected' => current_time('mysql')
            )
        );
        
        return $this->wpdb->insert_id;
    }

    /**
     * Resolve a conflict.
     *
     * @since    1.0.0
     * @param    int       $conflict_id    The conflict ID.
     * @param    string    $resolution     The resolution type (staging, production, custom).
     * @param    mixed     $custom_data    Custom resolution data (if applicable).
     * @return   boolean                   True on success, false on failure.
     */
    public function resolve_conflict($conflict_id, $resolution, $custom_data = '') {
        $conflicts_table = $this->wpdb->prefix . 'wp_easy_staging_conflicts';
        
        return $this->wpdb->update(
            $conflicts_table,
            array(
                'resolved' => 1,
                'resolution' => $resolution,
                'custom_data' => is_array($custom_data) ? maybe_serialize($custom_data) : $custom_data
            ),
            array('id' => $conflict_id)
        );
    }

    /**
     * Get unresolved conflicts.
     *
     * @since    1.0.0
     * @param    int       $staging_id    The staging site ID.
     * @return   array                    List of unresolved conflicts.
     */
    public function get_unresolved_conflicts($staging_id) {
        $conflicts_table = $this->wpdb->prefix . 'wp_easy_staging_conflicts';
        
        $conflicts = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM `{$conflicts_table}` WHERE staging_id = %d AND resolved = 0",
                $staging_id
            ),
            ARRAY_A
        );
        
        // Unserialize data
        foreach ($conflicts as &$conflict) {
            $conflict['staging_data'] = maybe_unserialize($conflict['staging_data']);
            $conflict['production_data'] = maybe_unserialize($conflict['production_data']);
            $conflict['custom_data'] = maybe_unserialize($conflict['custom_data']);
        }
        
        return $conflicts;
    }

    /**
     * Track a change in the staging site.
     *
     * @since    1.0.0
     * @param    int       $staging_id      The staging site ID.
     * @param    string    $change_type     The change type (insert, update, delete).
     * @param    string    $item_type       The item type (post, user, option, etc.).
     * @param    string    $item_id         The item ID.
     * @param    array     $data            The change data.
     * @return   int                        The change ID.
     */
    public function track_change($staging_id, $change_type, $item_type, $item_id, $data) {
        $changes_table = $this->wpdb->prefix . 'wp_easy_staging_changes';
        
        $this->wpdb->insert(
            $changes_table,
            array(
                'staging_id' => $staging_id,
                'change_type' => $change_type,
                'item_type' => $item_type,
                'item_id' => $item_id,
                'data' => maybe_serialize($data),
                'date_changed' => current_time('mysql'),
                'pushed' => 0
            )
        );
        
        return $this->wpdb->insert_id;
    }

    /**
     * Get tracked changes for a staging site.
     *
     * @since    1.0.0
     * @param    int       $staging_id    The staging site ID.
     * @param    boolean   $pushed        Optional. Whether to get pushed or unpushed changes.
     * @return   array                    List of changes.
     */
    public function get_tracked_changes($staging_id, $pushed = false) {
        $changes_table = $this->wpdb->prefix . 'wp_easy_staging_changes';
        
        $changes = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM `{$changes_table}` WHERE staging_id = %d AND pushed = %d ORDER BY date_changed DESC",
                $staging_id,
                $pushed ? 1 : 0
            ),
            ARRAY_A
        );
        
        // Unserialize data
        foreach ($changes as &$change) {
            $change['data'] = maybe_unserialize($change['data']);
        }
        
        return $changes;
    }

    /**
     * Mark changes as pushed.
     *
     * @since    1.0.0
     * @param    array     $change_ids    Array of change IDs.
     * @return   boolean                  True on success, false on failure.
     */
    public function mark_changes_pushed($change_ids) {
        $changes_table = $this->wpdb->prefix . 'wp_easy_staging_changes';
        
        if (empty($change_ids)) {
            return false;
        }
        
        // Convert array to comma-separated string
        $ids_string = implode(',', array_map('intval', $change_ids));
        
        $this->wpdb->query("UPDATE `{$changes_table}` SET pushed = 1 WHERE id IN ({$ids_string})");
        
        return true;
    }

    /**
     * Get tables with a specific prefix.
     *
     * @since    1.0.0
     * @param    string    $prefix    The table prefix.
     * @return   array                List of tables.
     */
    public function get_tables_with_prefix($prefix) {
        $tables = array();
        
        $all_tables = $this->wpdb->get_results("SHOW TABLES", ARRAY_N);
        
        foreach ($all_tables as $table) {
            $table_name = $table[0];
            
            if (strpos($table_name, $prefix) === 0) {
                $tables[] = $table_name;
            }
        }
        
        return $tables;
    }

    /**
     * Check if a table exists.
     *
     * @since    1.0.0
     * @param    string    $table    The table name.
     * @return   boolean             True if the table exists, false otherwise.
     */
    public function table_exists($table) {
        return $this->wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
    }
} 