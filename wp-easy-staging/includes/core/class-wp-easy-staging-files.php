<?php
/**
 * File operations for the plugin.
 *
 * @link       https://github.com/omnisend/wp-easy-staging
 * @since      1.0.0
 *
 * @package    WP_Easy_Staging
 * @subpackage WP_Easy_Staging/includes/core
 */

/**
 * File operations class.
 *
 * This class handles file operations for the plugin,
 * including copying, comparing, and syncing files.
 *
 * @since      1.0.0
 * @package    WP_Easy_Staging
 * @subpackage WP_Easy_Staging/includes/core
 * @author     CloudFest
 */
class WP_Easy_Staging_Files {

    /**
     * Directories to exclude from copying.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $exclude_dirs    Directories to exclude from copying.
     */
    private $exclude_dirs;

    /**
     * Directories to exclude from comparison.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $exclude_comparison_dirs    Directories to exclude from comparison.
     */
    private $exclude_comparison_dirs;

    /**
     * File extensions to exclude from copying.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $exclude_extensions    File extensions to exclude from copying.
     */
    private $exclude_extensions;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        // Get excluded directories from settings
        $options = get_option('wp_easy_staging_settings');
        $exclude_dirs_string = isset($options['exclude_directories']) ? $options['exclude_directories'] : '';
        
        // Default excluded directories
        $default_exclude = array(
            'wp-content/cache',
            'wp-content/upgrade',
            'wp-content/uploads/wp-easy-staging',
            'wp-content/plugins/wp-easy-staging/logs',
        );
        
        // Parse the excluded directories from settings
        if (!empty($exclude_dirs_string)) {
            $custom_exclude = explode("\n", $exclude_dirs_string);
            $custom_exclude = array_map('trim', $custom_exclude);
            $this->exclude_dirs = array_merge($default_exclude, $custom_exclude);
        } else {
            $this->exclude_dirs = $default_exclude;
        }
        
        // Set the exclude comparison directories
        $this->exclude_comparison_dirs = array_merge($this->exclude_dirs, array(
            'wp-content/debug.log',
            'wp-content/uploads/backups',
            '.htaccess',
            'wp-config.php',
        ));
        
        // Set the exclude extensions
        $this->exclude_extensions = array(
            'log',
            'tmp',
            'DS_Store',
        );
    }

    /**
     * Copy files from source to destination.
     *
     * @since    1.0.0
     * @param    string    $source        Source directory.
     * @param    string    $destination   Destination directory.
     * @param    boolean   $overwrite     Whether to overwrite existing files.
     * @return   array                    Results of the copy operation.
     */
    public function copy_files($source, $destination, $overwrite = true) {
        // Make sure source and destination end with a slash
        $source = trailingslashit($source);
        $destination = trailingslashit($destination);
        
        // Check if source directory exists
        if (!is_dir($source)) {
            return new WP_Error('source_not_found', __('Source directory not found.', 'wp-easy-staging'));
        }
        
        // Create destination directory if it doesn't exist
        if (!is_dir($destination)) {
            wp_mkdir_p($destination);
        }
        
        // Initialize results
        $results = array(
            'success' => array(),
            'failed' => array()
        );
        
        // Get all files and directories in the source
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            // Get the path relative to the source directory
            $relative_path = str_replace($source, '', $item->getPathname());
            
            // Skip excluded directories
            foreach ($this->exclude_dirs as $exclude_dir) {
                if (strpos($relative_path, $exclude_dir) === 0) {
                    continue 2; // Skip this item and continue with the next one
                }
            }
            
            // Skip excluded extensions
            $extension = pathinfo($item->getPathname(), PATHINFO_EXTENSION);
            if (in_array(strtolower($extension), $this->exclude_extensions)) {
                continue;
            }
            
            // Create the destination path
            $dest_path = $destination . $relative_path;
            
            if ($item->isDir()) {
                // Create directory
                if (!is_dir($dest_path)) {
                    wp_mkdir_p($dest_path);
                }
            } else {
                // Create directory if it doesn't exist
                $dir = dirname($dest_path);
                if (!is_dir($dir)) {
                    wp_mkdir_p($dir);
                }
                
                // Copy file
                if (!file_exists($dest_path) || $overwrite) {
                    if (copy($item->getPathname(), $dest_path)) {
                        $results['success'][] = $relative_path;
                    } else {
                        $results['failed'][] = $relative_path;
                    }
                }
            }
        }
        
        return $results;
    }

    /**
     * Compare files between source and destination.
     *
     * @since    1.0.0
     * @param    string    $source          Source directory.
     * @param    string    $destination     Destination directory.
     * @param    boolean   $compare_content Whether to compare file content.
     * @return   array                      Results of the comparison.
     */
    public function compare_files($source, $destination, $compare_content = true) {
        // Make sure source and destination end with a slash
        $source = trailingslashit($source);
        $destination = trailingslashit($destination);
        
        // Check if directories exist
        if (!is_dir($source) || !is_dir($destination)) {
            return new WP_Error('directory_not_found', __('Source or destination directory not found.', 'wp-easy-staging'));
        }
        
        // Initialize results
        $results = array(
            'added' => array(),
            'modified' => array(),
            'deleted' => array()
        );
        
        // Get all files and directories in the source
        $source_iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        // Get all files in the destination
        $destination_iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($destination, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        // Index destination files
        $destination_files = array();
        foreach ($destination_iterator as $item) {
            if (!$item->isDir()) {
                $relative_path = str_replace($destination, '', $item->getPathname());
                $destination_files[$relative_path] = $item->getPathname();
            }
        }
        
        // Compare source files to destination
        foreach ($source_iterator as $item) {
            if (!$item->isDir()) {
                $relative_path = str_replace($source, '', $item->getPathname());
                
                // Skip excluded directories
                foreach ($this->exclude_comparison_dirs as $exclude_dir) {
                    if (strpos($relative_path, $exclude_dir) === 0) {
                        continue 2; // Skip this item and continue with the next one
                    }
                }
                
                // Skip excluded extensions
                $extension = pathinfo($item->getPathname(), PATHINFO_EXTENSION);
                if (in_array(strtolower($extension), $this->exclude_extensions)) {
                    continue;
                }
                
                // Check if file exists in destination
                if (isset($destination_files[$relative_path])) {
                    // File exists in both, compare content
                    if ($compare_content) {
                        $source_content = file_get_contents($item->getPathname());
                        $destination_content = file_get_contents($destination_files[$relative_path]);
                        
                        if ($source_content !== $destination_content) {
                            $results['modified'][] = $relative_path;
                        }
                    } else {
                        // Compare file size and modification time
                        $source_size = filesize($item->getPathname());
                        $destination_size = filesize($destination_files[$relative_path]);
                        
                        $source_time = filemtime($item->getPathname());
                        $destination_time = filemtime($destination_files[$relative_path]);
                        
                        if ($source_size !== $destination_size || $source_time > $destination_time) {
                            $results['modified'][] = $relative_path;
                        }
                    }
                    
                    // Remove from destination files index
                    unset($destination_files[$relative_path]);
                } else {
                    // File only exists in source
                    $results['added'][] = $relative_path;
                }
            }
        }
        
        // Files that remain in the destination index are deleted in source
        foreach ($destination_files as $relative_path => $path) {
            // Skip excluded directories
            foreach ($this->exclude_comparison_dirs as $exclude_dir) {
                if (strpos($relative_path, $exclude_dir) === 0) {
                    continue 2; // Skip this item and continue with the next one
                }
            }
            
            // Skip excluded extensions
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            if (in_array(strtolower($extension), $this->exclude_extensions)) {
                continue;
            }
            
            $results['deleted'][] = $relative_path;
        }
        
        return $results;
    }

    /**
     * Sync files from source to destination.
     *
     * @since    1.0.0
     * @param    string    $source        Source directory.
     * @param    string    $destination   Destination directory.
     * @param    array     $changes       Changes to apply (default: all).
     * @return   array                    Results of the sync operation.
     */
    public function sync_files($source, $destination, $changes = null) {
        // Make sure source and destination end with a slash
        $source = trailingslashit($source);
        $destination = trailingslashit($destination);
        
        // Check if directories exist
        if (!is_dir($source) || !is_dir($destination)) {
            return new WP_Error('directory_not_found', __('Source or destination directory not found.', 'wp-easy-staging'));
        }
        
        // If no specific changes provided, get all changes
        if ($changes === null) {
            $changes = $this->compare_files($source, $destination);
        }
        
        // Initialize results
        $results = array(
            'added' => array(),
            'modified' => array(),
            'deleted' => array(),
            'failed' => array()
        );
        
        // Process added and modified files
        foreach (array('added', 'modified') as $change_type) {
            if (isset($changes[$change_type])) {
                foreach ($changes[$change_type] as $file) {
                    $source_file = $source . $file;
                    $destination_file = $destination . $file;
                    
                    // Create directory if it doesn't exist
                    $dir = dirname($destination_file);
                    if (!is_dir($dir)) {
                        wp_mkdir_p($dir);
                    }
                    
                    // Copy file
                    if (copy($source_file, $destination_file)) {
                        $results[$change_type][] = $file;
                    } else {
                        $results['failed'][] = $file;
                    }
                }
            }
        }
        
        // Process deleted files
        if (isset($changes['deleted'])) {
            foreach ($changes['deleted'] as $file) {
                $destination_file = $destination . $file;
                
                // Delete file
                if (unlink($destination_file)) {
                    $results['deleted'][] = $file;
                } else {
                    $results['failed'][] = $file;
                }
            }
        }
        
        return $results;
    }

    /**
     * Compare a specific file between source and destination.
     *
     * @since    1.0.0
     * @param    string    $source_file       Source file path.
     * @param    string    $destination_file  Destination file path.
     * @return   array                        Results of the comparison.
     */
    public function compare_file($source_file, $destination_file) {
        // Check if files exist
        if (!file_exists($source_file) || !file_exists($destination_file)) {
            return new WP_Error('file_not_found', __('Source or destination file not found.', 'wp-easy-staging'));
        }
        
        // Get file content
        $source_content = file_get_contents($source_file);
        $destination_content = file_get_contents($destination_file);
        
        // If content is identical, return empty diff
        if ($source_content === $destination_content) {
            return array();
        }
        
        // Split content into lines
        $source_lines = explode("\n", $source_content);
        $destination_lines = explode("\n", $destination_content);
        
        // Compute diff
        $diff = $this->compute_diff($source_lines, $destination_lines);
        
        return $diff;
    }

    /**
     * Compute diff between two sets of lines.
     *
     * @since    1.0.0
     * @param    array     $source_lines      Source lines.
     * @param    array     $destination_lines Destination lines.
     * @return   array                        Diff result.
     */
    private function compute_diff($source_lines, $destination_lines) {
        require_once(ABSPATH . 'wp-includes/class-wp-text-diff-renderer-table.php');
        
        // Create Text_Diff object
        $diff = new Text_Diff('auto', array($destination_lines, $source_lines));
        
        // Create renderer
        $renderer = new WP_Text_Diff_Renderer_Table();
        
        // Render diff
        $diff_html = $renderer->render($diff);
        
        return array(
            'source_lines' => $source_lines,
            'destination_lines' => $destination_lines,
            'diff_html' => $diff_html
        );
    }

    /**
     * Find conflicts between source and destination files.
     *
     * @since    1.0.0
     * @param    string    $source          Source directory.
     * @param    string    $destination     Destination directory.
     * @param    array     $original_state  Original state of files (when staging was created).
     * @return   array                      List of conflicts.
     */
    public function find_conflicts($source, $destination, $original_state) {
        // Get changes between source and destination
        $changes = $this->compare_files($source, $destination);
        
        if (is_wp_error($changes)) {
            return $changes;
        }
        
        // Initialize conflicts
        $conflicts = array();
        
        // Check for conflicts in modified files
        foreach ($changes['modified'] as $file) {
            // Check if the file was modified in both staging and production
            if (isset($original_state[$file])) {
                $source_file = $source . $file;
                $destination_file = $destination . $file;
                $original_content = $original_state[$file];
                
                // Get current content
                $source_content = file_get_contents($source_file);
                $destination_content = file_get_contents($destination_file);
                
                // If both have changed from original, it's a conflict
                if ($source_content !== $original_content && $destination_content !== $original_content) {
                    $conflicts[$file] = array(
                        'source_content' => $source_content,
                        'destination_content' => $destination_content,
                        'original_content' => $original_content
                    );
                }
            }
        }
        
        return $conflicts;
    }

    /**
     * Resolve a file conflict.
     *
     * @since    1.0.0
     * @param    string    $file           File path (relative to web root).
     * @param    string    $resolution     Resolution type (source, destination, custom).
     * @param    string    $custom_content Custom content (if resolution is custom).
     * @param    string    $source         Source directory.
     * @param    string    $destination    Destination directory.
     * @return   boolean                   True on success, false on failure.
     */
    public function resolve_file_conflict($file, $resolution, $custom_content, $source, $destination) {
        // Make sure source and destination end with a slash
        $source = trailingslashit($source);
        $destination = trailingslashit($destination);
        
        // Get file paths
        $source_file = $source . $file;
        $destination_file = $destination . $file;
        
        // Resolve based on resolution type
        switch ($resolution) {
            case 'source':
                // Use source version
                $content = file_get_contents($source_file);
                break;
                
            case 'destination':
                // Use destination version
                $content = file_get_contents($destination_file);
                break;
                
            case 'custom':
                // Use custom content
                $content = $custom_content;
                break;
                
            default:
                return false;
        }
        
        // Create directory if it doesn't exist
        $dir = dirname($destination_file);
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        
        // Write content to destination file
        $result = file_put_contents($destination_file, $content);
        
        return ($result !== false);
    }

    /**
     * Get file content for comparing.
     *
     * @since    1.0.0
     * @param    string    $file         File path (relative to web root).
     * @param    string    $source       Source directory.
     * @param    string    $destination  Destination directory.
     * @return   array                   File content for comparing.
     */
    public function get_file_content_for_comparing($file, $source, $destination) {
        // Make sure source and destination end with a slash
        $source = trailingslashit($source);
        $destination = trailingslashit($destination);
        
        // Get file paths
        $source_file = $source . $file;
        $destination_file = $destination . $file;
        
        // Check if files exist
        $source_exists = file_exists($source_file);
        $destination_exists = file_exists($destination_file);
        
        // Get content
        $source_content = $source_exists ? file_get_contents($source_file) : '';
        $destination_content = $destination_exists ? file_get_contents($destination_file) : '';
        
        // Compute diff if both files exist
        $diff = array();
        if ($source_exists && $destination_exists) {
            $diff = $this->compare_file($source_file, $destination_file);
        }
        
        return array(
            'source_exists' => $source_exists,
            'destination_exists' => $destination_exists,
            'source_content' => $source_content,
            'destination_content' => $destination_content,
            'diff' => $diff
        );
    }

    /**
     * Recursively copy a directory.
     *
     * @since    1.0.0
     * @param    string    $source        Source directory.
     * @param    string    $destination   Destination directory.
     * @return   boolean                  True on success, false on failure.
     */
    public function recursive_copy($source, $destination) {
        $this->log("Copying from {$source} to {$destination}");
        
        // Create destination directory
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        
        // Open the source directory
        $dir = opendir($source);
        
        // Loop through the files in the source directory
        while (($file = readdir($dir)) !== false) {
            if ($file != '.' && $file != '..') {
                $src = $source . '/' . $file;
                $dst = $destination . '/' . $file;
                
                if (is_dir($src)) {
                    // Recursively copy directory
                    $this->recursive_copy($src, $dst);
                } else {
                    // Copy file
                    copy($src, $dst);
                    // Match permissions
                    chmod($dst, fileperms($src));
                }
            }
        }
        
        closedir($dir);
        return true;
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
        
        $log_file = $log_dir . '/files.log';
        $date = date('Y-m-d H:i:s');
        $log_message = "[{$date}] {$message}\n";
        
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }
} 