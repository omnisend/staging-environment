<?php
/**
 * Synchronizer for Staging2Live
 *
 * @package Staging2Live
 * @subpackage Synchronizer
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class STL_Synchronizer
 * 
 * Handles the synchronization of changes from staging to production
 */
class STL_Synchronizer {
    /**
     * Instance of this class
     *
     * @var STL_Synchronizer
     */
    private static $instance = null;

    /**
     * Production site root path
     *
     * @var string
     */
    private $production_root;

    /**
     * Staging site root path
     *
     * @var string
     */
    private $staging_root;

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
    private $staging_prefix = 'wpstg0_';

    /**
     * File comparer instance
     *
     * @var STL_File_Comparer
     */
    private $file_comparer;

    /**
     * DB comparer instance
     *
     * @var STL_DB_Comparer
     */
    private $db_comparer;

    /**
     * Constructor
     */
    private function __construct() {
        $this->production_root = ABSPATH;
        $this->staging_root = ABSPATH . 'wpstg0/'; // Assuming staging is in wpstg0 directory
        
        $this->file_comparer = STL_File_Comparer::get_instance();
        $this->db_comparer = STL_DB_Comparer::get_instance();
        
        // Register AJAX handlers
        add_action( 'wp_ajax_stl_sync_changes', array( $this, 'ajax_sync_changes' ) );
    }

    /**
     * Get instance of this class
     *
     * @return STL_Synchronizer
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Sync file changes from staging to production
     *
     * @param array $files List of files to sync
     * @return array Results of sync operation
     */
    public function sync_files( $files ) {
        $results = array(
            'success' => array(),
            'error' => array(),
        );
        
        $file_changes = $this->file_comparer->get_changes();
        
        foreach ( $files as $file ) {
            if ( ! isset( $file_changes[ $file ] ) ) {
                $results['error'][ $file ] = __( 'File not found in change list.', 'staging2live' );
                continue;
            }
            
            $change_type = $file_changes[ $file ];
            $staging_path = $this->staging_root . $file;
            $production_path = $this->production_root . $file;
            
            switch ( $change_type ) {
                case 'added':
                case 'modified':
                    // Create the directory if it doesn't exist
                    $dir = dirname( $production_path );
                    if ( ! is_dir( $dir ) ) {
                        if ( ! wp_mkdir_p( $dir ) ) {
                            $results['error'][ $file ] = __( 'Could not create directory.', 'staging2live' );
                            continue 2; // Skip to next file
                        }
                    }
                    
                    // Copy the file
                    if ( ! copy( $staging_path, $production_path ) ) {
                        $results['error'][ $file ] = __( 'Could not copy file.', 'staging2live' );
                    } else {
                        $results['success'][ $file ] = __( 'File copied successfully.', 'staging2live' );
                    }
                    break;
                    
                case 'deleted':
                    // Delete the file
                    if ( file_exists( $production_path ) ) {
                        if ( ! unlink( $production_path ) ) {
                            $results['error'][ $file ] = __( 'Could not delete file.', 'staging2live' );
                        } else {
                            $results['success'][ $file ] = __( 'File deleted successfully.', 'staging2live' );
                        }
                    } else {
                        $results['success'][ $file ] = __( 'File already deleted.', 'staging2live' );
                    }
                    break;
                    
                default:
                    $results['error'][ $file ] = __( 'Unknown change type.', 'staging2live' );
                    break;
            }
        }
        
        // Clear cache
        delete_transient( 'stl_file_changes' );
        
        return $results;
    }
    
    /**
     * Sync database changes from staging to production
     *
     * @param array $tables List of tables and IDs to sync
     * @return array Results of sync operation
     */
    public function sync_db( $tables ) {
        global $wpdb;
        
        $results = array(
            'success' => array(),
            'error' => array(),
        );
        
        $db_changes = $this->db_comparer->get_changes();
        
        foreach ( $tables as $table_data ) {
            if ( ! isset( $table_data['table'] ) || ! isset( $table_data['id'] ) ) {
                $results['error'][] = __( 'Invalid table data.', 'staging2live' );
                continue;
            }
            
            $table = $table_data['table'];
            $id = $table_data['id'];
            
            if ( ! isset( $db_changes[ $table ] ) ) {
                $results['error'][] = sprintf( __( 'Table %s not found in change list.', 'staging2live' ), $table );
                continue;
            }
            
            // Find the change in the table changes
            $change = null;
            foreach ( $db_changes[ $table ] as $table_change ) {
                if ( $table_change['id'] == $id ) {
                    $change = $table_change;
                    break;
                }
            }
            
            if ( null === $change ) {
                $results['error'][] = sprintf( __( 'Entry with ID %s not found in change list for table %s.', 'staging2live' ), $id, $table );
                continue;
            }
            
            $production_table = $this->production_prefix . $table;
            $staging_table = $this->staging_prefix . $table;
            
            // Get the primary key column for this table
            $primary_key = $this->get_primary_key( $production_table );
            
            if ( ! $primary_key ) {
                $results['error'][] = sprintf( __( 'Could not find primary key for table %s.', 'staging2live' ), $table );
                continue;
            }
            
            switch ( $change['type'] ) {
                case 'added':
                    // Get all columns for this table
                    $columns = $this->get_table_columns( $staging_table );
                    
                    // Get the row from staging
                    $staging_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$staging_table} WHERE {$primary_key} = %s", $id ), ARRAY_A );
                    
                    if ( ! $staging_row ) {
                        $results['error'][] = sprintf( __( 'Could not find entry with ID %s in staging table %s.', 'staging2live' ), $id, $table );
                        continue 2; // Skip to next table
                    }
                    
                    // Build the query
                    $query_columns = array();
                    $query_values = array();
                    $query_placeholders = array();
                    
                    foreach ( $columns as $column ) {
                        if ( isset( $staging_row[ $column ] ) ) {
                            $query_columns[] = $column;
                            $query_values[] = $staging_row[ $column ];
                            $query_placeholders[] = '%s';
                        }
                    }
                    
                    // Insert the row
                    $query = "INSERT INTO {$production_table} (" . implode( ', ', $query_columns ) . ") VALUES (" . implode( ', ', $query_placeholders ) . ")";
                    $result = $wpdb->query( $wpdb->prepare( $query, $query_values ) );
                    
                    if ( false === $result ) {
                        $results['error'][] = sprintf( __( 'Could not insert entry with ID %s in table %s.', 'staging2live' ), $id, $table );
                    } else {
                        $results['success'][] = sprintf( __( 'Entry with ID %s inserted successfully in table %s.', 'staging2live' ), $id, $table );
                    }
                    break;
                    
                case 'modified':
                    // Get the row from staging
                    $staging_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$staging_table} WHERE {$primary_key} = %s", $id ), ARRAY_A );
                    
                    if ( ! $staging_row ) {
                        $results['error'][] = sprintf( __( 'Could not find entry with ID %s in staging table %s.', 'staging2live' ), $id, $table );
                        continue 2; // Skip to next table
                    }
                    
                    // Build the query
                    $query_sets = array();
                    $query_values = array();
                    
                    foreach ( $staging_row as $column => $value ) {
                        if ( $column !== $primary_key ) {
                            $query_sets[] = "{$column} = %s";
                            $query_values[] = $value;
                        }
                    }
                    
                    // Add the ID to the values
                    $query_values[] = $id;
                    
                    // Update the row
                    $query = "UPDATE {$production_table} SET " . implode( ', ', $query_sets ) . " WHERE {$primary_key} = %s";
                    $result = $wpdb->query( $wpdb->prepare( $query, $query_values ) );
                    
                    if ( false === $result ) {
                        $results['error'][] = sprintf( __( 'Could not update entry with ID %s in table %s.', 'staging2live' ), $id, $table );
                    } else {
                        $results['success'][] = sprintf( __( 'Entry with ID %s updated successfully in table %s.', 'staging2live' ), $id, $table );
                    }
                    break;
                    
                case 'deleted':
                    // Delete the row
                    $result = $wpdb->delete( $production_table, array( $primary_key => $id ), array( '%s' ) );
                    
                    if ( false === $result ) {
                        $results['error'][] = sprintf( __( 'Could not delete entry with ID %s from table %s.', 'staging2live' ), $id, $table );
                    } else {
                        $results['success'][] = sprintf( __( 'Entry with ID %s deleted successfully from table %s.', 'staging2live' ), $id, $table );
                    }
                    break;
                    
                default:
                    $results['error'][] = sprintf( __( 'Unknown change type for entry with ID %s in table %s.', 'staging2live' ), $id, $table );
                    break;
            }
        }
        
        // Clear cache
        delete_transient( 'stl_db_changes' );
        
        return $results;
    }
    
    /**
     * AJAX handler for syncing changes
     */
    public function ajax_sync_changes() {
        // Check nonce
        if ( ! check_ajax_referer( 'stl_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'staging2live' ) ) );
        }
        
        // Check if user has permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'staging2live' ) ) );
        }
        
        // Get files and tables to sync
        $files = isset( $_POST['files'] ) ? (array) json_decode( wp_unslash( $_POST['files'] ), true ) : array();
        $tables = isset( $_POST['tables'] ) ? (array) json_decode( wp_unslash( $_POST['tables'] ), true ) : array();
        
        $results = array(
            'files' => array(),
            'db' => array(),
        );
        
        // Sync files
        if ( ! empty( $files ) ) {
            $results['files'] = $this->sync_files( $files );
        }
        
        // Sync database
        if ( ! empty( $tables ) ) {
            $results['db'] = $this->sync_db( $tables );
        }
        
        wp_send_json_success( $results );
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
}

// Initialize the synchronizer class
STL_Synchronizer::get_instance(); 