<?php
/**
 * Cloning functionality for the plugin.
 *
 * @link       https://github.com/omnisend/wp-easy-staging
 * @since      1.0.0
 *
 * @package    WP_Easy_Staging
 * @subpackage WP_Easy_Staging/includes/core
 */

/**
 * Cloning functionality class.
 *
 * This class handles the cloning process for creating staging sites.
 *
 * @since      1.0.0
 * @package    WP_Easy_Staging
 * @subpackage WP_Easy_Staging/includes/core
 * @author     CloudFest
 */
class WP_Easy_Staging_Cloning {

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
     * Directories to exclude from copying.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $exclude_dirs    Directories to exclude from copying.
     */
    private $exclude_dirs;
    
    /**
     * File extensions to exclude from copying.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $exclude_extensions    File extensions to exclude from copying.
     */
    private $exclude_extensions;
    
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
        $this->db = new WP_Easy_Staging_Database();
        $this->files = new WP_Easy_Staging_Files();
        $this->staging = new WP_Easy_Staging_Staging();
        
        // Initialize exclude lists based on settings
        $options = get_option('wp_easy_staging_settings');
        
        // Default excluded directories
        $this->exclude_dirs = array(
            'wp-content/cache',
            'wp-content/upgrade',
            'wp-content/uploads/wp-easy-staging',
            'wp-content/plugins/wp-easy-staging/logs',
        );
        
        if (isset($options['exclude_directories']) && !empty($options['exclude_directories'])) {
            $custom_exclude = explode("\n", $options['exclude_directories']);
            $custom_exclude = array_map('trim', $custom_exclude);
            $this->exclude_dirs = array_merge($this->exclude_dirs, $custom_exclude);
        }
        
        // Set exclude extensions
        $this->exclude_extensions = array(
            'log',
            'tmp',
            'DS_Store',
        );
        
        // Set exclude tables
        global $wpdb;
        $this->exclude_tables = array(
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
        );
        
        if (isset($options['exclude_tables']) && !empty($options['exclude_tables'])) {
            $custom_exclude = explode("\n", $options['exclude_tables']);
            $custom_exclude = array_map('trim', $custom_exclude);
            $this->exclude_tables = array_merge($this->exclude_tables, $custom_exclude);
        }
    }

    /**
     * Start the cloning process.
     *
     * @since    1.0.0
     * @param    array     $options    Cloning options.
     * @return   mixed                 Result of the cloning process.
     */
    public function start_cloning($options = array()) {
        // Set default options
        $default_options = array(
            'name' => '',
            'exclude_tables' => array(),
            'exclude_files' => array(),
            'exclude_dirs' => array()
        );
        
        $options = wp_parse_args($options, $default_options);
        
        // Start logging
        $this->log("Starting cloning process with options: " . print_r($options, true));
        
        // Create staging site
        $result = $this->staging->create_staging_site($options['name']);
        
        if (is_wp_error($result)) {
            $this->log("Cloning failed: " . $result->get_error_message());
            return $result;
        }
        
        $this->log("Cloning completed successfully. Staging site ID: {$result['id']}");
        
        return $result;
    }

    /**
     * Backup the production site before cloning.
     *
     * @since    1.0.0
     * @return   boolean    True on success, false on failure.
     */
    public function backup_production() {
        $backup_dir = WP_CONTENT_DIR . '/uploads/wp-easy-staging/backups';
        
        if (!is_dir($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }
        
        $backup_file = $backup_dir . '/backup-' . date('Y-m-d-H-i-s') . '.zip';
        
        // Create backup of files
        $this->log("Creating backup of files: {$backup_file}");
        $result = $this->create_backup_zip(ABSPATH, $backup_file);
        
        if (!$result) {
            $this->log("Failed to create backup of files");
            return false;
        }
        
        // Export database
        $db_backup_file = $backup_dir . '/database-' . date('Y-m-d-H-i-s') . '.sql';
        $this->log("Exporting database: {$db_backup_file}");
        $result = $this->export_database($db_backup_file);
        
        if (!$result) {
            $this->log("Failed to export database");
            return false;
        }
        
        $this->log("Backup completed successfully");
        
        return true;
    }

    /**
     * Create a ZIP backup of files.
     *
     * @since    1.0.0
     * @param    string    $source        Source directory.
     * @param    string    $destination   Destination ZIP file.
     * @return   boolean                  True on success, false on failure.
     */
    private function create_backup_zip($source, $destination) {
        if (!class_exists('ZipArchive')) {
            $this->log("ZipArchive class not available");
            return false;
        }
        
        $zip = new ZipArchive();
        
        if (!$zip->open($destination, ZipArchive::CREATE)) {
            $this->log("Failed to create ZIP file");
            return false;
        }
        
        $source = str_replace('\\', '/', realpath($source));
        
        if (is_dir($source) === true) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($files as $file) {
                $file = str_replace('\\', '/', $file);
                
                // Skip excluded directories
                foreach ($this->exclude_dirs as $exclude_dir) {
                    if (strpos($file, $exclude_dir) !== false) {
                        continue 2;
                    }
                }
                
                // Skip excluded extensions
                $extension = pathinfo($file, PATHINFO_EXTENSION);
                if (in_array(strtolower($extension), $this->exclude_extensions)) {
                    continue;
                }
                
                // Get real and relative path for current file
                $real_file = str_replace('\\', '/', realpath($file));
                $relative_file = substr($real_file, strlen($source) + 1);
                
                if (is_dir($real_file) === true) {
                    $zip->addEmptyDir($relative_file);
                } else if (is_file($real_file) === true) {
                    $zip->addFile($real_file, $relative_file);
                }
            }
        } else if (is_file($source) === true) {
            $relative_file = basename($source);
            $zip->addFile($source, $relative_file);
        }
        
        return $zip->close();
    }

    /**
     * Export the database to a SQL file.
     *
     * @since    1.0.0
     * @param    string    $file    Destination SQL file.
     * @return   boolean            True on success, false on failure.
     */
    private function export_database($file) {
        global $wpdb;
        
        $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
        $output = '';
        
        // Add database export header
        $output .= "-- WordPress Database Backup\n";
        $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $output .= "-- Host: " . DB_HOST . "\n";
        $output .= "-- Database: " . DB_NAME . "\n";
        $output .= "-- -------------------------------------------------\n\n";
        
        foreach ($tables as $table) {
            $table_name = $table[0];
            
            // Skip excluded tables
            foreach ($this->exclude_tables as $exclude_table) {
                if ($table_name === $exclude_table) {
                    continue 2;
                }
            }
            
            // Get create table statement
            $result = $wpdb->get_row("SHOW CREATE TABLE `{$table_name}`", ARRAY_N);
            $output .= "DROP TABLE IF EXISTS `{$table_name}`;\n";
            $output .= $result[1] . ";\n\n";
            
            // Get table data
            $results = $wpdb->get_results("SELECT * FROM `{$table_name}`", ARRAY_A);
            
            if (!empty($results)) {
                $output .= "INSERT INTO `{$table_name}` VALUES\n";
                
                $values = array();
                foreach ($results as $result) {
                    $row_values = array();
                    
                    foreach ($result as $value) {
                        if (is_null($value)) {
                            $row_values[] = "NULL";
                        } else {
                            $row_values[] = "'" . $wpdb->_real_escape($value) . "'";
                        }
                    }
                    
                    $values[] = "(" . implode(", ", $row_values) . ")";
                }
                
                $output .= implode(",\n", $values) . ";\n\n";
            }
        }
        
        return file_put_contents($file, $output) !== false;
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
        
        $log_file = $log_dir . '/cloning.log';
        $date = date('Y-m-d H:i:s');
        $log_message = "[{$date}] {$message}\n";
        
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }
} 