<?php
/**
 * File Comparer for Staging2Live
 *
 * @package Staging2Live
 * @subpackage FileComparer
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class STL_File_Comparer
 * 
 * Handles the file comparison between staging and live environments
 */
class STL_File_Comparer {
    /**
     * Instance of this class
     *
     * @var STL_File_Comparer
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
     * Staging directory name
     *
     * @var string
     */
    private $staging_dir_name = '';

    /**
     * Debug mode
     *
     * @var bool
     */
    private $debug_mode = true;

    /**
     * Excluded directories and files
     *
     * @var array
     */
    private $exclusions = array(
        '.git',
        'wp-config.php',
        'wp-content/cache',
        'wp-content/uploads/cache',
        'wp-content/debug.log',
        'wp-content/advanced-cache.php',
        'wp-content/object-cache.php',
        'wp-content/upgrade/',
        'wp-content/uploads/wp-staging/',
        '.htaccess',
        '.DS_Store',
        'error_log',
        'php_errorlog',
        'wp-content/plugins/staging2live/', // Ignore self
        'wpstg0/', // Ignore staging directory itself
    );

    /**
     * Binary file extensions
     * 
     * @var array
     */
    private $binary_extensions = array(
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico', 'svg',
        'zip', 'gz', 'tar', 'rar', '7z',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'exe', 'dll', 'so', 'bin',
        'mp3', 'mp4', 'avi', 'mov', 'wmv', 'flv',
        'woff', 'woff2', 'eot', 'ttf'
    );

    /**
     * Constructor
     */
    private function __construct() {
        $this->production_root = $this->normalize_path(ABSPATH);
        
        // Detect the correct staging directory
        $this->staging_root = $this->detect_staging_directory();
    
        if (empty($this->staging_root)) {
            // Fallback to hardcoded path if detection fails
            $this->staging_root = $this->normalize_path(ABSPATH . '../wpstg0/');
        }
        
        // Extract staging directory name for exclusion
        $this->staging_dir_name = basename(rtrim($this->staging_root, '/'));
        if (!empty($this->staging_dir_name)) {
            // Add the exact staging directory name to exclusions
            $this->exclusions[] = $this->staging_dir_name . '/';
        }
        
        // Log paths for debugging
        $this->log_message('Production path: ' . $this->production_root);
        $this->log_message('Staging path: ' . $this->staging_root);
        $this->log_message('Staging directory name: ' . $this->staging_dir_name);

        // Register AJAX handlers
        add_action( 'wp_ajax_stl_get_file_diff', array( $this, 'ajax_get_file_diff' ) );
    }

    /**
     * Log a message if debug mode is on
     *
     * @param string $message The message to log
     */
    private function log_message($message) {
        if ($this->debug_mode) {
            error_log('Staging2Live - ' . $message);
        }
    }

    /**
     * Attempt to detect the staging directory
     * 
     * @return string The detected staging directory path or empty string
     */
    private function detect_staging_directory() {
        // Common staging directory patterns to check
        $possible_dirs = array(
            // WP Staging plugin format - parent directory
            $this->normalize_path(dirname(ABSPATH) . '/wpstg0/'),
            $this->normalize_path(dirname(ABSPATH) . '/wp-staging/'),
            $this->normalize_path(dirname(ABSPATH) . '/staging/'),
            
            // Alternate common formats - peer directory
            $this->normalize_path(dirname(ABSPATH) . '/../wpstg0/'),
            $this->normalize_path(dirname(ABSPATH) . '/../wp-staging/'),
            $this->normalize_path(dirname(ABSPATH) . '/../staging/'),
            
            // Check for subdirectory cases
            $this->normalize_path(ABSPATH . 'wpstg0/'),
            $this->normalize_path(ABSPATH . 'wp-staging/'),
            $this->normalize_path(ABSPATH . 'staging/'),
        );
        
        foreach ($possible_dirs as $dir) {
            // Check if it's a valid WordPress install by looking for wp-includes
            if (is_dir($dir) && file_exists($dir . 'wp-includes/version.php')) {
                return $dir;
            }
        }
        
        return '';
    }

    /**
     * Normalize a path for consistent comparison
     * 
     * @param string $path The path to normalize
     * @return string Normalized path
     */
    private function normalize_path($path) {
        // Replace backslashes with forward slashes
        $path = str_replace('\\', '/', $path);
        // Ensure path ends with a trailing slash
        return rtrim($path, '/') . '/';
    }

    /**
     * Get instance of this class
     *
     * @return STL_File_Comparer
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Get changes between staging and production
     *
     * @param bool $force Force refresh of changes
     * @return array List of changed files with their status
     */
    public function get_changes( $force = false ) {
        // // Check if we have cached results and not forcing refresh
        // $cached_changes = get_transient( 'stl_file_changes' );
        // if ( false !== $cached_changes && ! $force ) {
        //     return $cached_changes;
        // }

        $changes = array();
        
        // Validate staging directory before scanning
        if (!is_dir($this->staging_root) || !file_exists($this->staging_root . 'wp-includes/version.php')) {
            $this->log_message('Error: Invalid staging directory: ' . $this->staging_root);
            return array('error' => 'Staging environment not found. Please check your configuration.');
        }

        // Get all files in staging
        $staging_files = $this->get_all_files( $this->staging_root );
        
        // Get all files in production
        $production_files = $this->get_all_files( $this->production_root );
        
        // Debug output
        $this->log_message('Staging files count: ' . count($staging_files));
        $this->log_message('Production files count: ' . count($production_files));
        
        // Check for files that are in staging but not in production (new files)
        foreach ( $staging_files as $file => $hash ) {
            $relative_path = $this->get_relative_path( $file, $this->staging_root );
            
            // Skip excluded files and staging directory files
            if ( $this->is_excluded( $relative_path ) ) {
                continue;
            }
            
            // Production path for this file
            $production_path = $this->production_root . $relative_path;
            
            if ( ! isset( $production_files[ $production_path ] ) ) {
                // File exists in staging but not in production
                $changes[ $relative_path ] = 'added';
            } elseif ( $hash !== $production_files[ $production_path ] ) {
                // File exists in both but has different content
                $changes[ $relative_path ] = 'modified';
            }
        }
        
        // Check for files that are in production but not in staging (deleted files)
        foreach ( $production_files as $file => $hash ) {
            $relative_path = $this->get_relative_path( $file, $this->production_root );
            
            // Skip excluded files and don't include the staging directory itself
            if ( $this->is_excluded( $relative_path ) ) {
                continue;
            }
            
            // Skip if the file path contains the staging directory name
            if (!empty($this->staging_dir_name) && strpos($relative_path, $this->staging_dir_name . '/') !== false) {
                continue;
            }
            
            // Staging path for this file
            $staging_path = $this->staging_root . $relative_path;
            
            if ( ! isset( $staging_files[ $staging_path ] ) ) {
                // File exists in production but not in staging
                $changes[ $relative_path ] = 'deleted';
            }
        }
        
        // Cache results for 5 minutes
        set_transient( 'stl_file_changes', $changes, 5 * MINUTE_IN_SECONDS );
        
        return $changes;
    }
    
    /**
     * Get file difference between staging and production
     *
     * @param string $file Relative file path
     * @return array|bool Diff information or false on error
     */
    public function get_file_diff( $file ) {
        // Skip excluded files
        if ( $this->is_excluded( $file ) ) {
            return false;
        }
        
        $staging_path = $this->staging_root . $file;
        $production_path = $this->production_root . $file;
        
        // Check if both files exist
        $staging_exists = file_exists( $staging_path );
        $production_exists = file_exists( $production_path );
        
        if ( ! $staging_exists && ! $production_exists ) {
            return false;
        }
        
        // Get file contents
        $staging_content = $staging_exists ? file_get_contents( $staging_path ) : '';
        $production_content = $production_exists ? file_get_contents( $production_path ) : '';
        
        // If file is binary, don't show diff
        if ( $this->is_binary_file( $file ) ) {
            return array(
                'is_binary' => true,
                'staging_exists' => $staging_exists,
                'production_exists' => $production_exists,
                'staging_size' => $staging_exists ? filesize( $staging_path ) : 0,
                'production_size' => $production_exists ? filesize( $production_path ) : 0,
            );
        }
        
        // Get diff using WordPress built-in diff function
        $diff_table = wp_text_diff(
            $production_content,
            $staging_content,
            array(
                'title_left' => __('Production', 'staging2live'),
                'title_right' => __('Staging', 'staging2live')
            )
        );

        if (empty($diff_table)) {
            // Fallback if Text_Diff is not available
            $diff_table = sprintf(
                '<table class="diff"><tr><th>%s</th><th>%s</th></tr><tr><td>%s</td><td>%s</td></tr></table>',
                __( 'Production', 'staging2live' ),
                __( 'Staging', 'staging2live' ),
                nl2br( esc_html( $production_content ) ),
                nl2br( esc_html( $staging_content ) )
            );
        }
        
        return array(
            'is_binary' => false,
            'staging_exists' => $staging_exists,
            'production_exists' => $production_exists,
            'diff' => $diff_table,
        );
    }
    
    /**
     * AJAX handler for getting file diff
     */
    public function ajax_get_file_diff() {
        // Check nonce
        if ( ! check_ajax_referer( 'stl_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'staging2live' ) ) );
        }
        
        // Check if user has permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'staging2live' ) ) );
        }
        
        // Get file path
        $file = isset( $_POST['file'] ) ? sanitize_text_field( wp_unslash( $_POST['file'] ) ) : '';
        if ( empty( $file ) ) {
            wp_send_json_error( array( 'message' => __( 'No file specified.', 'staging2live' ) ) );
        }
        
        // Get diff
        $diff = $this->get_file_diff( $file );
        if ( false === $diff ) {
            wp_send_json_error( array( 'message' => __( 'Could not get file difference.', 'staging2live' ) ) );
        }
        
        wp_send_json_success( $diff );
    }
    
    /**
     * Get all files in a directory recursively
     *
     * @param string $dir Directory path
     * @return array List of files with their MD5 hash
     */
    private function get_all_files( $dir ) {
        $files = array();
        
        if ( ! is_dir( $dir ) ) {
            $this->log_message('Not a directory: ' . $dir);
            return $files;
        }
        
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS )
            );
            
            foreach ( $iterator as $file ) {
                if ( $file->isFile() ) {
                    $path = $file->getPathname();
                    $relative_path = $this->get_relative_path( $path, $dir );
                    
                    // Skip excluded files
                    if ( $this->is_excluded( $relative_path ) ) {
                        continue;
                    }
                    
                    // Also skip any path containing the staging directory name
                    if (!empty($this->staging_dir_name) && strpos($relative_path, $this->staging_dir_name . '/') !== false) {
                        continue;
                    }
                    
                    // Check for file permissions
                    if (!is_readable($path)) {
                        $this->log_message('Permission denied: ' . $path);
                        continue;
                    }

                    try {
                        // Use content hash for comparison, not timestamp-dependent values
                        if ( $this->is_binary_file( $relative_path ) ) {
                            // For binary files, use only MD5 of contents, not timestamp
                            $files[ $path ] = md5_file( $path );
                            
                            // Debug log for uploads
                            if (strpos($relative_path, 'uploads/') === 0 || strpos($relative_path, 'wp-content/uploads/') === 0) {
                                $this->log_message('Found uploaded file: ' . $relative_path);
                            }
                        } else {
                            // For text files, normalize line endings before hashing
                            $content = file_get_contents( $path );
                            // Normalize line endings to LF
                            $content = str_replace( "\r\n", "\n", $content );
                            $files[ $path ] = md5( $content );
                        }
                    } catch (Exception $e) {
                        $this->log_message('Error processing file ' . $path . ': ' . $e->getMessage());
                    }
                }
            }
        } catch ( Exception $e ) {
            // Handle potential errors gracefully
            $this->log_message('File scan error: ' . $e->getMessage());
        }
        
        return $files;
    }
    
    /**
     * Check if a file is excluded
     *
     * @param string $file File path
     * @return bool True if excluded, false otherwise
     */
    private function is_excluded( $file ) {
        // Always exclude these file types
        $excluded_extensions = array('log', 'tmp', 'temp', 'swp', 'bak');
        $file_extension = pathinfo($file, PATHINFO_EXTENSION);
        
        if (in_array($file_extension, $excluded_extensions)) {
            return true;
        }
        
        // Check custom exclusions
        foreach ( $this->exclusions as $exclusion ) {
            if ( 0 === strpos( $file, $exclusion ) || fnmatch( $exclusion, $file ) ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get relative path
     *
     * @param string $path Absolute path
     * @param string $base Base path
     * @return string Relative path
     */
    private function get_relative_path( $path, $base ) {
        // Normalize directory separators
        $path = str_replace('\\', '/', $path);
        $base = str_replace('\\', '/', $base);
        
        return ltrim( str_replace( $base, '', $path ), '/' );
    }
    
    /**
     * Check if a file is binary based on extension
     *
     * @param string $file File path
     * @return bool True if binary, false otherwise
     */
    private function is_binary_file( $file ) {
        $extension = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
        return in_array( $extension, $this->binary_extensions );
    }
    
    /**
     * Check if content is binary
     *
     * @param string $content File content
     * @return bool True if binary, false otherwise
     */
    private function is_binary( $content ) {
        return false !== strpos( $content, "\0" );
    }
}

// Initialize the file comparer class
STL_File_Comparer::get_instance(); 