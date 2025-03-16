<?php
/**
 * Database Comparer for Staging2Live
 *
 * @package Staging2Live
 * @subpackage DBComparer
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class STL_DB_Comparer
 * 
 * Handles the database comparison between staging and live environments
 */
class STL_DB_Comparer {
    /**
     * Instance of this class
     *
     * @var STL_DB_Comparer
     */
    private static $instance = null;

    /**
     * Production database prefix
     *
     * @var string
     */
    private $production_prefix = 'wp_';

    /**
     * Staging database prefix
     *
     * @var string
     */
    private $staging_prefix = 'wp_staging_';

    /**
     * Debug mode
     *
     * @var bool
     */
    private $debug_mode = true;

    /**
     * Tables to exclude from comparison
     *
     * @var array
     */
    private $excluded_tables = array(
        'options', // Often contains transients and cache data
        'usermeta', // User session data changes frequently
        'sessions', // Session data changes frequently
    );

    /**
     * Columns to exclude from comparison
     *
     * @var array
     */
    private $excluded_columns = array(
        'post_modified',
        'post_modified_gmt',
        'comment_date',
        'comment_date_gmt',
        'user_registered',
        'session_expiry',
    );

    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;

        // Ensure we have the correct staging prefix
        $this->detect_staging_prefix();
        
        // Register AJAX handlers
        add_action( 'wp_ajax_stl_get_db_diff', array( $this, 'ajax_get_db_diff' ) );
    }

    /**
     * Detect the staging database prefix by looking at existing tables
     */
    private function detect_staging_prefix() {
        global $wpdb;
        
        // Log the initial staging prefix
        $this->log_message("Initial staging prefix: '{$this->staging_prefix}'");
        
        // Try to find tables with the current prefix
        $tables_count = $wpdb->get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE '{$this->staging_prefix}%'");
        
        if ($tables_count > 0) {
            $this->log_message("Found {$tables_count} tables with prefix '{$this->staging_prefix}'");
            return; // Current prefix is working
        }
        
        // Try alternative prefixes
        $alternative_prefixes = array(
            'wp_staging_',
            'wpstg_',
            'wpstg0_',
            'wp_stg_',
            'wp_staging',
            'wpstg',
            'wpstg0'
        );
        
        foreach ($alternative_prefixes as $prefix) {
            if ($prefix === $this->staging_prefix) {
                continue; // Skip if it's the same as current
            }
            
            $count = $wpdb->get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE '{$prefix}%'");
            
            if ($count > 0) {
                $this->log_message("Found alternative staging prefix: '{$prefix}' with {$count} tables");
                $this->staging_prefix = $prefix;
                return;
            }
        }
        
        // If we get here, we couldn't find a matching prefix
        $this->log_message("Warning: Could not auto-detect staging table prefix. Using default: '{$this->staging_prefix}'");
    }

    /**
     * Log a message if debug mode is on
     *
     * @param string $message The message to log
     */
    private function log_message($message) {
        if ($this->debug_mode) {
            //error_log('Staging2Live DB - ' . $message);
        }
    }

    /**
     * Get instance of this class
     *
     * @return STL_DB_Comparer
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Normalize database content for comparison
     * 
     * @param string $content The content to normalize
     * @return string Normalized content
     */
    private function normalize_content($content) {
        if (!is_string($content)) {
            return $content;
        }
        
        // Convert to string if it's not already
        $text = (string) $content;
        
        // Normalize line endings to LF
        $text = str_replace("\r\n", "\n", $text);
        
        // Trim whitespace from beginning and end
        $text = trim($text);
        
        return $text;
    }

    /**
     * Compare two values with normalization
     * 
     * @param mixed $value1 First value
     * @param mixed $value2 Second value
     * @return bool True if values are equal after normalization
     */
    private function compare_values($value1, $value2) {
        // If both are null or empty strings, consider them equal
        if (($value1 === null || $value1 === '') && ($value2 === null || $value2 === '')) {
            return true;
        }
        
        // If only one is null, they're different
        if ($value1 === null || $value2 === null) {
            return false;
        }
        
        // For string values, normalize before comparison
        if (is_string($value1) && is_string($value2)) {
            return $this->normalize_content($value1) === $this->normalize_content($value2);
        }
        
        // For non-string values, use normal comparison
        return $value1 === $value2;
    }

    /**
     * Get database changes between staging and production
     *
     * @param bool $force Force refresh of changes
     * @return array List of changed database entries
     */
    public function get_changes( $force = false ) {
        global $wpdb;

        // Check if we have cached results and not forcing refresh
        // $cached_changes = get_transient( 'stl_db_changes' );
        // if ( false !== $cached_changes && ! $force ) {
        //     return $cached_changes;
        // }

        $changes = array();
        
        // Log the prefixes being used
        $this->log_message("Using production prefix: '{$this->production_prefix}' and staging prefix: '{$this->staging_prefix}'");
        
        // Get all tables in the database
        $tables = $wpdb->get_results( "SHOW TABLES LIKE '{$this->production_prefix}%'", ARRAY_N );
        $this->log_message("Found " . count($tables) . " production tables");
        
        // Check for staging tables too
        $staging_tables_count = $wpdb->get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE '{$this->staging_prefix}%'");
        $this->log_message("Found approximately $staging_tables_count staging tables with prefix '{$this->staging_prefix}%'");
        
        foreach ( $tables as $table ) {
            $production_table = $table[0];
            $staging_table = str_replace( $this->production_prefix, $this->staging_prefix, $production_table );
            
            // Extract the table name without prefix
            $table_name = str_replace( $this->production_prefix, '', $production_table );
            
            // Log tables being compared
            $this->log_message("Comparing tables - Production: '$production_table', Staging: '$staging_table'");
            
            // Skip excluded tables
            if ( in_array( $table_name, $this->excluded_tables, true ) ) {
                $this->log_message("Skipping excluded table: $table_name");
                continue;
            }
            
            // Check if staging table exists
            $staging_table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $staging_table ) );
            
            if ( ! $staging_table_exists ) {
                $this->log_message("Staging table does not exist: $staging_table");
                
                // Try with underscore after prefix
                $alternate_staging_table = $this->staging_prefix . '_' . $table_name;
                $alternate_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $alternate_staging_table ) );
                
                if ($alternate_exists) {
                    $this->log_message("Found alternate staging table: $alternate_staging_table");
                    $staging_table = $alternate_staging_table;
                    $staging_table_exists = true;
                } else {
                    continue;
                }
            } else {
                $this->log_message("Staging table exists: $staging_table");
            }
            
            // Get changes for this table
            $table_changes = $this->compare_table( $production_table, $staging_table, $table_name );
            
            if ( ! empty( $table_changes ) ) {
                $changes[ $table_name ] = $table_changes;
                $this->log_message("Found " . count($table_changes) . " changes in table: $table_name");
            } else {
                $this->log_message("No changes found in table: $table_name");
            }
        }
        
        // Cache results for 5 minutes
        set_transient( 'stl_db_changes', $changes, 5 * MINUTE_IN_SECONDS );
        
        return $changes;
    }
    
    /**
     * Compare a single table between production and staging
     *
     * @param string $production_table Production table name
     * @param string $staging_table Staging table name
     * @param string $table_name Table name without prefix
     * @return array List of changes for this table
     */
    private function compare_table( $production_table, $staging_table, $table_name ) {
        global $wpdb;
        
        $changes = array();
        
        // Get the primary key column for this table
        $primary_key = $this->get_primary_key( $production_table );
        
        if ( ! $primary_key ) {
            // Can't compare tables without a primary key
            return $changes;
        }
        
        // Get columns for this table
        $columns = $this->get_table_columns( $production_table );
        
        // Remove excluded columns
        $columns = array_diff( $columns, $this->excluded_columns );
        
        // Get all IDs from both tables
        $production_ids = $wpdb->get_col( "SELECT {$primary_key} FROM {$production_table}" );
        $staging_ids = $wpdb->get_col( "SELECT {$primary_key} FROM {$staging_table}" );
        
        // Find new entries (in staging but not in production)
        $new_ids = array_diff( $staging_ids, $production_ids );
        foreach ( $new_ids as $id ) {
            $staging_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$staging_table} WHERE {$primary_key} = %s", $id ), ARRAY_A );
            
            $changes[] = array(
                'id' => $id,
                'type' => 'added',
                'summary' => sprintf( __( 'New entry with ID %s', 'staging2live' ), $id ),
                'details' => $staging_row,
            );
        }
        
        // Find deleted entries (in production but not in staging)
        $deleted_ids = array_diff( $production_ids, $staging_ids );
        foreach ( $deleted_ids as $id ) {
            $production_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$production_table} WHERE {$primary_key} = %s", $id ), ARRAY_A );
            
            $changes[] = array(
                'id' => $id,
                'type' => 'deleted',
                'summary' => sprintf( __( 'Entry with ID %s deleted', 'staging2live' ), $id ),
                'details' => $production_row,
            );
        }
        
        // Find modified entries (in both but with different values)
        $common_ids = array_intersect( $production_ids, $staging_ids );
        
        foreach ( $common_ids as $id ) {
            $production_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$production_table} WHERE {$primary_key} = %s", $id ), ARRAY_A );
            $staging_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$staging_table} WHERE {$primary_key} = %s", $id ), ARRAY_A );
            
            $row_changes = array();
            
            foreach ( $columns as $column ) {
                // Skip primary key column
                if ( $column === $primary_key ) {
                    continue;
                }
                
                // Skip excluded columns
                if ( in_array( $column, $this->excluded_columns, true ) ) {
                    continue;
                }
                
                // Compare column values using new normalized comparison
                if (isset($production_row[$column]) && isset($staging_row[$column]) && 
                    !$this->compare_values($production_row[$column], $staging_row[$column])) {
                    
                    // Debug log when differences are found
                    if ($this->debug_mode) {
                        $this->log_message(sprintf(
                            "Difference found in table %s, column %s, ID %s: [%s] vs [%s]",
                            $table_name,
                            $column,
                            $id,
                            substr((string)$production_row[$column], 0, 50),
                            substr((string)$staging_row[$column], 0, 50)
                        ));
                    }
                    
                    $row_changes[ $column ] = array(
                        'production' => $production_row[ $column ],
                        'staging' => $staging_row[ $column ],
                    );
                }
            }
            
            if ( ! empty( $row_changes ) ) {
                // Get a human-readable name for this entry if possible
                $entry_name = $this->get_entry_name( $table_name, $production_row );
                
                $changes[] = array(
                    'id' => $id,
                    'type' => 'modified',
                    'summary' => $entry_name 
                        ? sprintf( __( 'Changes to "%s"', 'staging2live' ), $entry_name )
                        : sprintf( __( 'Entry with ID %s modified', 'staging2live' ), $id ),
                    'changes' => $row_changes,
                );
            }
        }
        
        return $changes;
    }
    
    /**
     * Get database difference
     *
     * @param string $table Table name without prefix
     * @param string $id Entry ID
     * @return array|bool Diff information or false on error
     */
    public function get_db_diff( $table, $id ) {
        global $wpdb;
        
        // Skip excluded tables
        if ( in_array( $table, $this->excluded_tables, true ) ) {
            return false;
        }
        
        $production_table = $this->production_prefix . $table;
        $staging_table = $this->staging_prefix . $table;
        
        // Get the primary key column for this table
        $primary_key = $this->get_primary_key( $production_table );
        
        if ( ! $primary_key ) {
            // Can't compare tables without a primary key
            return false;
        }
        
        // Get rows from both tables
        $production_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$production_table} WHERE {$primary_key} = %s", $id ), ARRAY_A );
        $staging_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$staging_table} WHERE {$primary_key} = %s", $id ), ARRAY_A );
        
        // Check if both rows exist
        $production_exists = ! empty( $production_row );
        $staging_exists = ! empty( $staging_row );
        
        if ( ! $production_exists && ! $staging_exists ) {
            return false;
        }
        
        // Determine the type of change
        if ( $production_exists && ! $staging_exists ) {
            $type = 'deleted';
            $diff = array(
                'production_data' => $production_row,
                'staging_data' => null,
            );
        } elseif ( ! $production_exists && $staging_exists ) {
            $type = 'added';
            $diff = array(
                'production_data' => null,
                'staging_data' => $staging_row,
            );
        } else {
            $type = 'modified';
            $diff = array(
                'production_data' => $production_row,
                'staging_data' => $staging_row,
                'field_changes' => array(),
            );
            
            // Compare fields
            foreach ( $staging_row as $field => $value ) {
                // Skip excluded columns
                if ( in_array( $field, $this->excluded_columns, true ) ) {
                    continue;
                }
                
                // Use the normalized comparison
                if (isset($production_row[$field]) && !$this->compare_values($production_row[$field], $value)) {
                    $diff['field_changes'][ $field ] = array(
                        'production' => $production_row[ $field ],
                        'staging' => $value,
                    );
                }
            }
        }
        
        return array(
            'type' => $type,
            'table' => $table,
            'id' => $id,
            'diff' => $diff,
            'entry_name' => $this->get_entry_name( $table, $production_exists ? $production_row : $staging_row ),
        );
    }
    
    /**
     * AJAX handler for getting database diff
     */
    public function ajax_get_db_diff() {
        // Check nonce
        if ( ! check_ajax_referer( 'stl_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'staging2live' ) ) );
        }
        
        // Check if user has permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'staging2live' ) ) );
        }
        
        // Get table and ID
        $table = isset( $_POST['table'] ) ? sanitize_text_field( wp_unslash( $_POST['table'] ) ) : '';
        $id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
        
        if ( empty( $table ) || empty( $id ) ) {
            wp_send_json_error( array( 'message' => __( 'Table and ID are required.', 'staging2live' ) ) );
        }
        
        // Get diff
        $diff = $this->get_db_diff( $table, $id );
        
        if ( false === $diff ) {
            wp_send_json_error( array( 'message' => __( 'Could not get database difference.', 'staging2live' ) ) );
        }
        
        wp_send_json_success( $diff );
    }
    
    /**
     * Get primary key for a table
     *
     * @param string $table Table name
     * @return string|bool Primary key column name or false if not found
     */
    private function get_primary_key( $table ) {
        global $wpdb;
        
        $result = $wpdb->get_results( "SHOW KEYS FROM {$table} WHERE Key_name = 'PRIMARY'", ARRAY_A );
        
        if ( ! empty( $result ) ) {
            return $result[0]['Column_name'];
        }
        
        return false;
    }
    
    /**
     * Get columns for a table
     *
     * @param string $table Table name
     * @return array Column names
     */
    private function get_table_columns( $table ) {
        global $wpdb;
        
        $columns = array();
        $results = $wpdb->get_results( "SHOW COLUMNS FROM {$table}", ARRAY_A );
        
        if ( ! empty( $results ) ) {
            foreach ( $results as $result ) {
                $columns[] = $result['Field'];
            }
        }
        
        return $columns;
    }
    
    /**
     * Get a human-readable name for a database entry
     *
     * @param string $table Table name without prefix
     * @param array $row Row data
     * @return string Entry name or empty string if not found
     */
    private function get_entry_name( $table, $row ) {
        if ( empty( $row ) ) {
            return '';
        }
        
        switch ( $table ) {
            case 'posts':
                return isset( $row['post_title'] ) ? $row['post_title'] : '';
                
            case 'terms':
                return isset( $row['name'] ) ? $row['name'] : '';
                
            case 'users':
                if ( isset( $row['display_name'] ) && ! empty( $row['display_name'] ) ) {
                    return $row['display_name'];
                } elseif ( isset( $row['user_login'] ) ) {
                    return $row['user_login'];
                }
                return '';
                
            case 'comments':
                return isset( $row['comment_author'] ) ? $row['comment_author'] : '';
                
            default:
                // Look for common name columns
                $name_columns = array( 'name', 'title', 'label', 'description' );
                
                foreach ( $name_columns as $column ) {
                    if ( isset( $row[ $column ] ) && ! empty( $row[ $column ] ) ) {
                        return $row[ $column ];
                    }
                }
                
                return '';
        }
    }
}

// Initialize the database comparer class
STL_DB_Comparer::get_instance(); 