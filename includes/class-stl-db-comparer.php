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
        'usermeta', // User session data changes frequently
        'sessions', // Session data changes frequently
        'term_taxonomy', // Term taxonomy data changes frequently
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
     * Get all changes between production and staging databases
     *
     * @param bool $force Force refresh of changes
     * @return array List of changed database entries, grouped by post ID when appropriate
     */
    public function get_changes( $force = false ) {
        global $wpdb;

        // Check if we have cached results and not forcing refresh
        // $cached_changes = get_transient( 'stl_db_changes' );
        // if ( false !== $cached_changes && ! $force ) {
        //     return $cached_changes;
        // }

        $changes = array();
        $grouped_changes = array();
        
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
        
        // Group changes by post_id or content relationship
        $grouped_changes = $this->group_related_changes($changes);
        
        // Cache results for 5 minutes
        set_transient( 'stl_db_changes', $grouped_changes, 5 * MINUTE_IN_SECONDS );
        
        return $grouped_changes;
    }

	private function is_excluded_name($name): bool {
		if (strpos($name, '_site_transient') === 0) {
			return true;
		}

		if (strpos($name, '_transient') === 0) {
			return true;
		}

		if ($name === 'wp_staging_user_roles') {
			return true;
		}

		return false;
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
			if ( $table_name == 'options' && ! empty($staging_row['option_name']) && $this->is_excluded_name($staging_row['option_name'])){
				continue;
			}

            $changes[] = array(
                'id' => $id,
                'type' => 'added' . (!empty($staging_row['option_name']) ? " > {$staging_row['option_name']}" : ''),
                'summary' => sprintf( __( 'New entry with ID %s', 'staging2live' ), $id ),
                'details' => $staging_row,
            );
        }
        
        // Find deleted entries (in production but not in staging)
        $deleted_ids = array_diff( $production_ids, $staging_ids );
        foreach ( $deleted_ids as $id ) {
            $production_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$production_table} WHERE {$primary_key} = %s", $id ), ARRAY_A );
			if ( $table_name == 'options' && ! empty($production_row['option_name']) && $this->is_excluded_name($production_row['option_name']))
				continue;

            $changes[] = array(
                'id' => $id,
                'type' => 'deleted'  . (!empty($production_row['option_name']) ? " > {$production_row['option_name']}" : ''),
                'summary' => sprintf( __( 'Entry with ID %s deleted', 'staging2live' ), $id ),
                'details' => $production_row,
            );
        }
        
        // Find modified entries (in both but with different values)
        $common_ids = array_intersect( $production_ids, $staging_ids );
        
        foreach ( $common_ids as $id ) {
            $production_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$production_table} WHERE {$primary_key} = %s", $id ), ARRAY_A );
            $staging_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$staging_table} WHERE {$primary_key} = %s", $id ), ARRAY_A );

			$option = '';
			if (!empty($production_row['option_name']))
				$option = $production_row['option_name'];
			if (!empty($staging_row['option_name']))
				$option = $staging_row['option_name'];

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

				if ( $table_name == 'options' && ! empty($option) && $this->is_excluded_name($option))
					continue;

                $changes[] = array(
                    'id' => $id,
                    'type' => 'modified' . ($option != '' ? " > $option" : ''),
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
     * Group related database changes together
     * 
     * @param array $changes All detected changes
     * @return array Changes grouped by relationship (post ID, etc)
     */
    private function group_related_changes($changes) {
        $grouped = array();
        $post_groups = array();
        $attachment_to_post_mapping = array();
        $child_to_parent_mapping = array();
        $attachment_ids = array(); // Store all attachment IDs for later reference
        $post_to_attachment_mapping = array(); // Map posts to their attachments
        $post_type_groups = array(); // New array to hold post type groups
        
        // First pass: identify posts with parent relationships
        if (isset($changes['posts'])) {
            // Step 1: Find attachments and child posts, create mappings
            foreach ($changes['posts'] as $change) {
                $post_id = $change['id'];
                $post_type = isset($change['details']['post_type']) ? $change['details']['post_type'] : '';
                $post_parent = isset($change['details']['post_parent']) && !empty($change['details']['post_parent']) 
                    ? $change['details']['post_parent'] 
                    : null;
                
                // If this is an attachment, track its ID for later
                if ($post_type === 'attachment') {
                    $attachment_ids[$post_id] = true;
                }
                
                // If this post has a parent, record the relationship
                if ($post_parent) {
                    // If it's an attachment, add to attachment mapping
                    if ($post_type === 'attachment') {
                        if (!isset($attachment_to_post_mapping[$post_parent])) {
                            $attachment_to_post_mapping[$post_parent] = array();
                        }
                        $attachment_to_post_mapping[$post_parent][] = $post_id;
                        
                        // Also track parent to attachment mapping
                        if (!isset($post_to_attachment_mapping[$post_parent])) {
                            $post_to_attachment_mapping[$post_parent] = array();
                        }
                        $post_to_attachment_mapping[$post_parent][] = $post_id;
                    }
                    
                    // Also add to the general child-to-parent mapping (for all post types)
                    if (!isset($child_to_parent_mapping[$post_parent])) {
                        $child_to_parent_mapping[$post_parent] = array();
                    }
                    $child_to_parent_mapping[$post_parent][] = array(
                        'id' => $post_id,
                        'type' => $post_type
                    );
                }
            }
            
            // Step 2: Create groups for parent posts first
            foreach ($changes['posts'] as $change) {
                $post_id = $change['id'];
                $group_key = 'post_' . $post_id;
                $post_type = isset($change['details']['post_type']) ? $change['details']['post_type'] : '';
                $post_parent = isset($change['details']['post_parent']) && !empty($change['details']['post_parent']) 
                    ? $change['details']['post_parent'] 
                    : null;
                
                // Skip posts that have a parent - we'll handle them later
                if ($post_parent) {
                    continue;
                }
                
                if (!isset($post_groups[$group_key])) {
                    $post_title = '';
                    
                    // Get a title for this group
                    if ($change['type'] === 'added' && isset($change['details']['post_title'])) {
                        $post_title = $change['details']['post_title'];
                    } elseif ($change['type'] === 'deleted' && isset($change['details']['post_title'])) {
                        $post_title = $change['details']['post_title'];
                    } elseif ($change['type'] === 'modified') {
                        $post_title = $change['summary'];
                    }
                    
                    $post_groups[$group_key] = array(
                        'group_id' => $group_key,
                        'post_id' => $post_id,
                        'title' => $post_title ? $post_title : sprintf(__('Post ID: %s', 'staging2live'), $post_id),
                        'type' => $change['type'],
                        'changes' => array(),
                    );
                }
                
                // Add this change to the group
                $post_groups[$group_key]['changes']['posts'][] = $change;
                
                // Check if this post has any attachments or child posts and add them to the group
                $this->add_children_to_group($post_id, $post_groups[$group_key], $changes, $attachment_to_post_mapping, $child_to_parent_mapping);
            }
            
            // Step 3: Handle child posts (including attachments)
            // Create a temp mapping to track which posts have been processed
            $processed_posts = array();
            
            foreach ($changes['posts'] as $change) {
                $post_id = $change['id'];
                
                // Skip if we already processed this post
                if (isset($processed_posts[$post_id])) {
                    continue;
                }
                
                $post_type = isset($change['details']['post_type']) ? $change['details']['post_type'] : '';
                $post_parent = isset($change['details']['post_parent']) && !empty($change['details']['post_parent']) 
                    ? $change['details']['post_parent'] 
                    : null;
                
                // If this is a child post (has a parent)
                if ($post_parent) {
                    $has_parent_group = false;
                    $parent_group_key = 'post_' . $post_parent;
                    
                    // Check if the parent post has a group
                    if (isset($post_groups[$parent_group_key])) {
                        // Check if this post has already been added to the parent group
                        $found_in_group = false;
                        
                        // For attachments, check in the attachments array
                        if ($post_type === 'attachment' && isset($post_groups[$parent_group_key]['changes']['attachments'])) {
                            foreach ($post_groups[$parent_group_key]['changes']['attachments'] as $attachment) {
                                if ($attachment['id'] == $post_id) {
                                    $found_in_group = true;
                                    break;
                                }
                            }
                        }
                        
                        // For other post types, check in child_posts array
                        if (!$found_in_group && isset($post_groups[$parent_group_key]['changes']['child_posts'])) {
                            foreach ($post_groups[$parent_group_key]['changes']['child_posts'] as $child_post) {
                                if ($child_post['id'] == $post_id) {
                                    $found_in_group = true;
                                    break;
                                }
                            }
                        }
                        
                        // If not found, add it to the appropriate section of the parent group
                        if (!$found_in_group) {
                            if ($post_type === 'attachment') {
                                if (!isset($post_groups[$parent_group_key]['changes']['attachments'])) {
                                    $post_groups[$parent_group_key]['changes']['attachments'] = array();
                                }
                                $post_groups[$parent_group_key]['changes']['attachments'][] = $change;
                            } else {
                                if (!isset($post_groups[$parent_group_key]['changes']['child_posts'])) {
                                    $post_groups[$parent_group_key]['changes']['child_posts'] = array();
                                }
                                $post_groups[$parent_group_key]['changes']['child_posts'][] = $change;
                            }
                        }
                        
                        $has_parent_group = true;
                        $processed_posts[$post_id] = true;
                    }
                    
                    // If no parent group found, create a new group (either for this post or its parent)
                    if (!$has_parent_group) {
                        // Check if the parent exists in the posts changes
                        $parent_exists = false;
                        foreach ($changes['posts'] as $parent_check) {
                            if ($parent_check['id'] == $post_parent) {
                                $parent_exists = true;
                                break;
                            }
                        }
                        
                        if ($parent_exists) {
                            // The parent exists but wasn't processed yet - we'll let it be handled in its turn
                            continue;
                        }
                        
                        // The parent doesn't exist in the changes, create a group for this child post
                        $group_key = 'post_' . $post_id;
                        
                        if (!isset($post_groups[$group_key])) {
                            $post_title = '';
                            
                            // Get a title for this group
                            if ($change['type'] === 'added' && isset($change['details']['post_title'])) {
                                $post_title = $change['details']['post_title'];
                            } elseif ($change['type'] === 'deleted' && isset($change['details']['post_title'])) {
                                $post_title = $change['details']['post_title'];
                            } elseif ($change['type'] === 'modified') {
                                $post_title = $change['summary'];
                            }
                            
                            // For attachments, use a special prefix
                            if ($post_type === 'attachment') {
                                $title_prefix = __('Media: ', 'staging2live');
                            } else {
                                $title_prefix = $post_type ? ucfirst($post_type) . ': ' : __('Child Post: ', 'staging2live');
                            }
                            
                            $post_groups[$group_key] = array(
                                'group_id' => $group_key,
                                'post_id' => $post_id,
                                'title' => $post_title ? $post_title : sprintf($title_prefix . '%s', $post_id),
                                'type' => $change['type'],
                                'changes' => array(),
                            );
                        }
                        
                        // Add this change to the group
                        $post_groups[$group_key]['changes']['posts'][] = $change;
                        $processed_posts[$post_id] = true;
                    }
                }
            }
        }
        
        // Second pass: Find all related metadata and group it
        if (isset($changes['postmeta'])) {
            foreach ($changes['postmeta'] as $key => $change) {
                // Skip if we don't have post_id in the details
                if (!isset($change['details']['post_id'])) {
                    continue;
                }
                
                $meta_post_id = $change['details']['post_id'];
                $is_grouped = false;
                
                // Handle featured images (thumbnail metadata)
                if (isset($change['details']['meta_key']) && $change['details']['meta_key'] === '_thumbnail_id') {
                    $post_id = $meta_post_id;
                    $thumbnail_id = null;
                    
                    // Get the thumbnail ID
                    if (isset($change['details']['meta_value'])) {
                        $thumbnail_id = $change['details']['meta_value'];
                    }
                    
                    // If we have both IDs, check if the thumbnail exists in the posts changes
                    if ($post_id && $thumbnail_id && isset($changes['posts'])) {
                        $post_group_key = 'post_' . $post_id;
                        
                        // If the post is in our groups, add the featured image relationship
                        if (isset($post_groups[$post_group_key])) {
                            // Find the thumbnail in posts changes
                            foreach ($changes['posts'] as $thumbnail_change) {
                                if ($thumbnail_change['id'] == $thumbnail_id) {
                                    if (!isset($post_groups[$post_group_key]['changes']['attachments'])) {
                                        $post_groups[$post_group_key]['changes']['attachments'] = array();
                                    }
                                    
                                    // Check if we already have this attachment
                                    $found = false;
                                    foreach ($post_groups[$post_group_key]['changes']['attachments'] as $attachment) {
                                        if ($attachment['id'] == $thumbnail_id) {
                                            $found = true;
                                            break;
                                        }
                                    }
                                    
                                    if (!$found) {
                                        $post_groups[$post_group_key]['changes']['attachments'][] = $thumbnail_change;
                                    }
                                    break;
                                }
                            }
                            
                            // Add this meta entry to the post group
                            if (!isset($post_groups[$post_group_key]['changes']['postmeta'])) {
                                $post_groups[$post_group_key]['changes']['postmeta'] = array();
                            }
                            $post_groups[$post_group_key]['changes']['postmeta'][] = $change;
                            $is_grouped = true;
                        }
                    }
                }
                
                // Check if the postmeta belongs to a post that's in a group
                if (!$is_grouped) {
                    $post_group_key = 'post_' . $meta_post_id;
                    if (isset($post_groups[$post_group_key])) {
                        if (!isset($post_groups[$post_group_key]['changes']['postmeta'])) {
                            $post_groups[$post_group_key]['changes']['postmeta'] = array();
                        }
                        $post_groups[$post_group_key]['changes']['postmeta'][] = $change;
                        $is_grouped = true;
                    }
                }
                
                // Check if the postmeta belongs to an attachment that's part of a group
                if (!$is_grouped && isset($attachment_ids[$meta_post_id])) {
                    // This metadata belongs to an attachment
                    $found_in_group = false;
                    
                    // Check all post groups to see if this attachment is part of any group
                    foreach ($post_groups as $group_key => $group) {
                        if (isset($group['changes']['attachments'])) {
                            foreach ($group['changes']['attachments'] as $attachment) {
                                if ($attachment['id'] == $meta_post_id) {
                                    // Found the attachment in this group
                                    if (!isset($post_groups[$group_key]['changes']['attachment_meta'])) {
                                        $post_groups[$group_key]['changes']['attachment_meta'] = array();
                                    }
                                    $post_groups[$group_key]['changes']['attachment_meta'][] = $change;
                                    $found_in_group = true;
                                    $is_grouped = true;
                                    break 2; // Break both loops
                                }
                            }
                        }
                    }
                    
                    // If not found in any group but this is an attachment metadata
                    // Create a group for this attachment if it doesn't exist
                    if (!$found_in_group) {
                        $attachment_group_key = 'post_' . $meta_post_id;
                        
                        if (!isset($post_groups[$attachment_group_key])) {
                            // Try to find the attachment details in posts changes
                            $attachment_details = null;
                            foreach ($changes['posts'] as $post_change) {
                                if ($post_change['id'] == $meta_post_id) {
                                    $attachment_details = $post_change;
                                    break;
                                }
                            }
                            
                            if ($attachment_details) {
                                $attachment_title = '';
                                
                                // Get a title for this group
                                if ($attachment_details['type'] === 'added' && isset($attachment_details['details']['post_title'])) {
                                    $attachment_title = $attachment_details['details']['post_title'];
                                } elseif ($attachment_details['type'] === 'deleted' && isset($attachment_details['details']['post_title'])) {
                                    $attachment_title = $attachment_details['details']['post_title'];
                                } elseif ($attachment_details['type'] === 'modified') {
                                    $attachment_title = $attachment_details['summary'];
                                }
                                
                                $post_groups[$attachment_group_key] = array(
                                    'group_id' => $attachment_group_key,
                                    'post_id' => $meta_post_id,
                                    'title' => $attachment_title ? $attachment_title : sprintf(__('Media: %s', 'staging2live'), $meta_post_id),
                                    'type' => $attachment_details['type'],
                                    'changes' => array(),
                                );
                                
                                // Add the attachment to its own group
                                $post_groups[$attachment_group_key]['changes']['posts'][] = $attachment_details;
                            } else {
                                // We couldn't find attachment details, create minimal group
                                $post_groups[$attachment_group_key] = array(
                                    'group_id' => $attachment_group_key,
                                    'post_id' => $meta_post_id,
                                    'title' => sprintf(__('Media: %s', 'staging2live'), $meta_post_id),
                                    'type' => 'modified', // Default to modified
                                    'changes' => array(),
                                );
                            }
                        }
                        
                        // Add this meta entry to the attachment group
                        if (!isset($post_groups[$attachment_group_key]['changes']['postmeta'])) {
                            $post_groups[$attachment_group_key]['changes']['postmeta'] = array();
                        }
                        $post_groups[$attachment_group_key]['changes']['postmeta'][] = $change;
                        $is_grouped = true;
                    }
                }
                
                // If the meta hasn't been grouped yet, keep it in the original table changes
                if (!$is_grouped) {
                    if (!isset($grouped['postmeta'])) {
                        $grouped['postmeta'] = array();
                    }
                    $grouped['postmeta'][] = $change;
                }
            }
        }
        
        // Third pass: associate remaining related content with post groups
        foreach ($changes as $table => $table_changes) {
            // Skip the tables we've already processed
            if ($table === 'posts' || $table === 'postmeta') {
                continue;
            }
            
            foreach ($table_changes as $change) {
                $associated_post_id = null;
                $group_assigned = false;
                
                // Try to associate with a post based on table and columns
                if ($table === 'term_relationships' && isset($change['details']['object_id'])) {
                    // For term_relationships, the object_id is the post ID
                    $associated_post_id = $change['details']['object_id'];
                } else if ($table === 'comments' && isset($change['details']['comment_post_ID'])) {
                    $associated_post_id = $change['details']['comment_post_ID'];
                } elseif ($table === 'commentmeta' && isset($change['details']['comment_id'])) {
                    // Try to find the associated post through the comment
                    $comment_id = $change['details']['comment_id'];
                    $associated_post = $this->get_post_id_for_comment($comment_id);
                    if ($associated_post) {
                        $associated_post_id = $associated_post;
                    }
                }
                
                // If we found an associated post, add this change to that group
                if ($associated_post_id) {
                    $group_key = 'post_' . $associated_post_id;
                    
                    if (isset($post_groups[$group_key])) {
                        if (!isset($post_groups[$group_key]['changes'][$table])) {
                            $post_groups[$group_key]['changes'][$table] = array();
                        }
                        $post_groups[$group_key]['changes'][$table][] = $change;
                        $group_assigned = true;
                    }
                    
                    // If change is associated with an attachment that's in a group
                    if (!$group_assigned && isset($attachment_ids[$associated_post_id])) {
                        // Check all post groups to see if this attachment is in any of them
                        foreach ($post_groups as $parent_group_key => $parent_group) {
                            if (isset($parent_group['changes']['attachments'])) {
                                foreach ($parent_group['changes']['attachments'] as $attachment) {
                                    if ($attachment['id'] == $associated_post_id) {
                                        // Found the attachment in this group
                                        if (!isset($post_groups[$parent_group_key]['changes'][$table])) {
                                            $post_groups[$parent_group_key]['changes'][$table] = array();
                                        }
                                        $post_groups[$parent_group_key]['changes'][$table][] = $change;
                                        $group_assigned = true;
                                        break 2; // Break both loops
                                    }
                                }
                            }
                        }
                    }
                }
                
                // If not assigned to a group, keep it as a standalone change
                if (!$group_assigned) {
                    if (!isset($grouped[$table])) {
                        $grouped[$table] = array();
                    }
                    $grouped[$table][] = $change;
                }
            }
        }
        
        // Merge the post groups into the final result
        foreach ($post_groups as $group) {
            // Get the post type from the first post in the group's changes
            $post_type = 'unknown';
            if (isset($group['changes']['posts']) && !empty($group['changes']['posts'])) {
                $first_post = $group['changes']['posts'][0];
                $post_type = isset($first_post['details']['post_type']) ? $first_post['details']['post_type'] : 'unknown';
            } elseif (isset($group['changes']['child_posts']) && !empty($group['changes']['child_posts'])) {
                $first_post = $group['changes']['child_posts'][0];
                $post_type = isset($first_post['details']['post_type']) ? $first_post['details']['post_type'] : 'unknown';
            } elseif (isset($group['changes']['attachments']) && !empty($group['changes']['attachments'])) {
                $post_type = 'attachment';
            }
            
            // Initialize post type group if it doesn't exist
            if (!isset($post_type_groups[$post_type])) {
                $post_type_groups[$post_type] = array();
            }
            
            // Add this group to the appropriate post type group
            $post_type_groups[$post_type][] = $group;
        }
        
        // Now add the post type groups to the final result
        if (!empty($post_type_groups)) {
            $grouped['post_type_groups'] = $post_type_groups;
        }
        
        return $grouped;
    }
    
    /**
     * Add child posts and attachments to a parent group
     *
     * @param int $parent_id The parent post ID
     * @param array &$parent_group The parent group to add children to
     * @param array $changes All detected changes
     * @param array $attachment_mapping Mapping of parents to attachment IDs
     * @param array $child_mapping Mapping of parents to child post IDs
     */
    private function add_children_to_group($parent_id, &$parent_group, $changes, $attachment_mapping, $child_mapping) {
        // Add attachments to the group
        if (isset($attachment_mapping[$parent_id])) {
            foreach ($attachment_mapping[$parent_id] as $attachment_id) {
                // Find the attachment change
                foreach ($changes['posts'] as $attachment_change) {
                    if ($attachment_change['id'] == $attachment_id) {
                        if (!isset($parent_group['changes']['attachments'])) {
                            $parent_group['changes']['attachments'] = array();
                        }
                        $parent_group['changes']['attachments'][] = $attachment_change;
                        break;
                    }
                }
            }
        }
        
        // Add other child posts to the group
        if (isset($child_mapping[$parent_id])) {
            foreach ($child_mapping[$parent_id] as $child) {
                // Skip attachments as we've already handled them
                if ($child['type'] === 'attachment') {
                    continue;
                }
                
                // Find the child post change
                foreach ($changes['posts'] as $child_change) {
                    if ($child_change['id'] == $child['id']) {
                        if (!isset($parent_group['changes']['child_posts'])) {
                            $parent_group['changes']['child_posts'] = array();
                        }
                        $parent_group['changes']['child_posts'][] = $child_change;
                        break;
                    }
                }
            }
        }
    }
    
    /**
     * Get the post ID associated with a comment
     *
     * @param int $comment_id The comment ID
     * @return int|null The post ID or null if not found
     */
    private function get_post_id_for_comment($comment_id) {
        global $wpdb;
        
        $production_comments_table = $this->production_prefix . 'comments';
        
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT comment_post_ID FROM {$production_comments_table} WHERE comment_ID = %d",
            $comment_id
        ));
        
        return $post_id ? (int) $post_id : null;
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
