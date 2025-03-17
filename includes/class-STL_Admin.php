<?php
/**
 * Admin page for Staging2Live
 *
 * @package Staging2Live
 * @subpackage Admin
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class STL_Admin
 *
 * Handles the admin page and UI of the plugin
 */
class STL_Admin {
    /**
     * Instance of this class
     *
     * @var STL_Admin
     */
    private static $instance = null;

    /**
     * Constructor
     */
    private function __construct() {
        if (!stl_staging_exists()) {
            return;
        }

        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

        // Disable emails for staging environments.
        if ( defined( 'WP_ENVIRONMENT_TYPE' ) && 'staging' === WP_ENVIRONMENT_TYPE ) {
            add_filter('pre_wp_mail', '__return_false', PHP_INT_MAX );
        }
    }

    /**
     * Get instance of this class
     *
     * @return STL_Admin
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'Staging2Live', 'staging2live' ),
            __( 'Staging2Live', 'staging2live' ),
            'manage_options',
            'staging2live',
            array( $this, 'render_admin_page' ),
            'dashicons-controls-repeat',
            90
        );

        add_submenu_page(
            'staging2live',
            'Sync',
            'Sync',
            'manage_options',
            'staging2live',
            array( $this, 'render_admin_page' )
        );
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook Hook suffix for the current admin page
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( 'toplevel_page_staging2live' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'staging2live-admin',
            STL_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            stl_get_plugin_version()
        );

        wp_enqueue_script(
            'staging2live-admin',
            STL_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            stl_get_plugin_version(),
            true
        );

        wp_localize_script(
            'staging2live-admin',
            'stl_admin',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'stl_admin_nonce' ),
                'i18n'     => array(
                    'show_details' => __( 'Show Details', 'staging2live' ),
                    'hide_details' => __( 'Hide Details', 'staging2live' ),
                ),
            )
        );
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $staging       = stl_get_staging_values();
        $file_comparer = STL_File_Comparer::get_instance();
        $db_comparer   = STL_DB_Comparer::get_instance();

        $file_changes  = $file_comparer->get_changes();
        $db_changes    = $db_comparer->get_changes();

        $push_to_button_text = ( defined('WP_ENVIRONMENT_TYPE') && 'staging' === WP_ENVIRONMENT_TYPE ) ? __( 'Push Selected Changes To Production', 'staging2live' ) : __( 'Push Selected Changes To Staging', 'staging2live' );

        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <p><?php esc_html_e( 'Staging site', 'staging2live' ); ?>: <a target="_blank" href="<?php echo $staging[ 'domain' ]; ?>"><?php echo $staging[ 'domain' ]; ?></a>
            <div class="stl-tabs">
                <ul class="stl-tabs-nav">
                    <li><a href="#stl-tab-files"><?php esc_html_e( 'File Changes', 'staging2live' ); ?></a></li>
                    <li><a href="#stl-tab-database"><?php esc_html_e( 'Database Changes', 'staging2live' ); ?></a></li>
                </ul>

                <div id="stl-tab-files" class="stl-tab-content">
                    <h2><?php esc_html_e( 'File Changes', 'staging2live' ); ?></h2>
                    <?php $this->render_file_changes( $file_changes ); ?>
                </div>

                <div id="stl-tab-database" class="stl-tab-content">
                    <h2><?php esc_html_e( 'Database Changes', 'staging2live' ); ?></h2>
                    <?php $this->render_db_changes( $db_changes ); ?>
                </div>
            </div>

            <div class="stl-actions">
                <button id="stl-sync-selected" class="button button-primary"><?php echo esc_html( $push_to_button_text ); ?></button>
            </div>
        </div>
        <?php
    }

    /**
     * Render file changes
     *
     * @param array $changes File changes
     */
    private function render_file_changes( $changes ) {
        if ( empty( $changes ) ) {
            echo '<p>' . esc_html__( 'No file changes detected.', 'staging2live' ) . '</p>';
            return;
        }

        ?>
        <table class="widefat stl-changes-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="stl-select-all-files"></th>
                    <th><?php esc_html_e( 'File', 'staging2live' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'staging2live' ); ?></th>
                    <th><?php esc_html_e( 'Action', 'staging2live' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $changes as $file => $status ) : ?>
                    <tr>
                        <td><input type="checkbox" class="stl-select-file" value="<?php echo esc_attr( $file ); ?>"></td>
                        <td><?php echo esc_html( $file ); ?></td>
                        <td><?php echo esc_html( $this->get_status_label( $status ) ); ?></td>
                        <td>
                            <button class="button stl-view-diff" data-file="<?php echo esc_attr( $file ); ?>">
                                <?php esc_html_e( 'View Diff', 'staging2live' ); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render database changes
     *
     * @param array $changes Database changes
     */
    private function render_db_changes( $changes ) {
        if ( empty( $changes ) ) {
            echo '<p>' . esc_html__( 'No database changes detected.', 'staging2live' ) . '</p>';
            return;
        }

        // First render content groups organized by post type
        if ( isset( $changes['post_type_groups'] ) && ! empty( $changes['post_type_groups'] ) ) {
            ?>
            <h3><?php esc_html_e( 'Content Changes', 'staging2live' ); ?></h3>
            <div class="stl-post-type-groups">
                <?php foreach ( $changes['post_type_groups'] as $post_type => $groups ) : ?>
                    <div class="stl-post-type-group">
                        <h3 class="stl-post-type-header">
                            <?php echo esc_html( ucfirst( $post_type ) ); ?>
                        </h3>
                        <div class="stl-content-groups">
                            <?php foreach ( $groups as $group ) : ?>
                                <div class="stl-content-group">
                                    <div class="stl-group-header">
                                        <input type="checkbox" class="stl-select-group" data-group-id="<?php echo esc_attr( $group['group_id'] ); ?>">
                                        <h4>
                                            <?php echo esc_html( $group['title'] ); ?>
                                            <span class="stl-change-type stl-type-<?php echo esc_attr( $group['type'] ); ?>">
                                                <?php echo esc_html( $this->get_status_label( $group['type'] ) ); ?>
                                            </span>
                                        </h4>
                                        <button class="button stl-toggle-group" data-group-id="<?php echo esc_attr( $group['group_id'] ); ?>">
                                            <?php esc_html_e( 'Show Details', 'staging2live' ); ?>
                                        </button>
                                    </div>
                                    <div class="stl-group-content" id="group-content-<?php echo esc_attr( $group['group_id'] ); ?>" style="display:none;">
                                        <?php foreach ( $group['changes'] as $table => $table_changes ) : ?>
                                            <?php 
                                            // Special handling for attachments to make them more visual
                                            if ($table === 'attachments') : 
                                            ?>
                                                <h5><?php esc_html_e( 'Media Attachments', 'staging2live' ); ?> (<?php echo count( $table_changes ); ?>)</h5>
                                                <div class="stl-attachments-grid">
                                                    <?php foreach ( $table_changes as $change ) : 
                                                        $attachment_title = isset($change['details']['post_title']) ? $change['details']['post_title'] : '';
                                                        $attachment_mime = isset($change['details']['post_mime_type']) ? $change['details']['post_mime_type'] : '';
                                                        $is_image = strpos($attachment_mime, 'image/') === 0;
                                                        
                                                        // Try to get the attachment URL from guid
                                                        $attachment_url = isset($change['details']['guid']) ? $change['details']['guid'] : '';
                                                    ?>
                                                        <div class="stl-attachment-item">
                                                            <div class="stl-attachment-preview">
                                                                <?php if ($is_image && $attachment_url) : ?>
                                                                    <img src="<?php echo esc_url($attachment_url); ?>" alt="<?php echo esc_attr($attachment_title); ?>">
                                                                <?php else : ?>
                                                                    <div class="stl-attachment-icon dashicons dashicons-media-default"></div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="stl-attachment-details">
                                                                <label>
                                                                    <input 
                                                                        type="checkbox" 
                                                                        class="stl-select-db stl-group-item" 
                                                                        data-group="<?php echo esc_attr( $group['group_id'] ); ?>"
                                                                        data-table="posts"
                                                                        value="<?php echo esc_attr( json_encode( array( 'table' => 'posts', 'id' => $change['id'] ) ) ); ?>"
                                                                    >
                                                                    <?php echo $attachment_title ? esc_html($attachment_title) : sprintf(esc_html__('Attachment ID: %s', 'staging2live'), $change['id']); ?>
                                                                </label>
                                                                <span class="stl-change-type stl-type-<?php echo esc_attr( $change['type'] ); ?>">
                                                                    <?php echo esc_html( $this->get_status_label( $change['type'] ) ); ?>
                                                                </span>
                                                                <button class="button stl-view-db-diff" data-table="posts" data-id="<?php echo esc_attr( $change['id'] ); ?>">
                                                                    <?php esc_html_e( 'View Details', 'staging2live' ); ?>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php 
                                            // Special handling for child posts
                                            elseif ($table === 'child_posts') : 
                                            ?>
                                                <h5><?php esc_html_e( 'Child Posts', 'staging2live' ); ?> (<?php echo count( $table_changes ); ?>)</h5>
                                                <div class="stl-child-posts">
                                                    <?php foreach ( $table_changes as $change ) : 
                                                        $post_title = isset($change['details']['post_title']) ? $change['details']['post_title'] : '';
                                                        $post_type = isset($change['details']['post_type']) ? $change['details']['post_type'] : 'post';
                                                    ?>
                                                        <div class="stl-child-post-item">
                                                            <div class="stl-child-post-header">
                                                                <label>
                                                                    <input 
                                                                        type="checkbox" 
                                                                        class="stl-select-db stl-group-item" 
                                                                        data-group="<?php echo esc_attr( $group['group_id'] ); ?>"
                                                                        data-table="posts"
                                                                        value="<?php echo esc_attr( json_encode( array( 'table' => 'posts', 'id' => $change['id'] ) ) ); ?>"
                                                                    >
                                                                    <span class="stl-child-post-title">
                                                                        <?php echo $post_title ? esc_html($post_title) : sprintf(esc_html__('Post ID: %s', 'staging2live'), $change['id']); ?>
                                                                    </span>
                                                                    <span class="stl-child-post-type"><?php echo esc_html(ucfirst($post_type)); ?></span>
                                                                </label>
                                                                <span class="stl-change-type stl-type-<?php echo esc_attr( $change['type'] ); ?>">
                                                                    <?php echo esc_html( $this->get_status_label( $change['type'] ) ); ?>
                                                                </span>
                                                            </div>
                                                            <div class="stl-child-post-actions">
                                                                <button class="button stl-view-db-diff" data-table="posts" data-id="<?php echo esc_attr( $change['id'] ); ?>">
                                                                    <?php esc_html_e( 'View Details', 'staging2live' ); ?>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else : ?>
                                                <h5><?php echo esc_html( ucfirst( $table ) ); ?> (<?php echo count( $table_changes ); ?>)</h5>
                                                <table class="widefat stl-changes-table stl-group-table">
                                                    <thead>
                                                        <tr>
                                                            <th><input type="checkbox" class="stl-select-all-table" data-table="<?php echo esc_attr( $table ); ?>" data-group="<?php echo esc_attr( $group['group_id'] ); ?>"></th>
                                                            <th><?php esc_html_e( 'Type', 'staging2live' ); ?></th>
                                                            <th><?php esc_html_e( 'Changes', 'staging2live' ); ?></th>
                                                            <th><?php esc_html_e( 'Action', 'staging2live' ); ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ( $table_changes as $change ) : ?>
                                                            <tr>
                                                                <td>
                                                                    <input 
                                                                        type="checkbox" 
                                                                        class="stl-select-db stl-group-item" 
                                                                        data-group="<?php echo esc_attr( $group['group_id'] ); ?>"
                                                                        data-table="<?php echo esc_attr( $table ); ?>"
                                                                        value="<?php echo esc_attr( json_encode( array( 'table' => $table, 'id' => $change['id'] ) ) ); ?>"
                                                                    >
                                                                </td>
                                                                <td><?php echo esc_html( $change['type'] ); ?></td>
                                                                <td><?php echo esc_html( $change['summary'] ); ?></td>
                                                                <td>
                                                                    <button class="button stl-view-db-diff" data-table="<?php echo esc_attr( $table ); ?>" data-id="<?php echo esc_attr( $change['id'] ); ?>">
                                                                        <?php esc_html_e( 'View Details', 'staging2live' ); ?>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php
        } else if ( isset( $changes['content_groups'] ) && ! empty( $changes['content_groups'] ) ) {
            // Fallback to old structure if post_type_groups not available
            ?>
            <h3><?php esc_html_e( 'Content Changes', 'staging2live' ); ?></h3>
            <div class="stl-content-groups">
                <?php foreach ( $changes['content_groups'] as $group ) : ?>
                    <div class="stl-content-group">
                        <div class="stl-group-header">
                            <input type="checkbox" class="stl-select-group" data-group-id="<?php echo esc_attr( $group['group_id'] ); ?>">
                            <h4>
                                <?php echo esc_html( $group['title'] ); ?>
                                <span class="stl-change-type stl-type-<?php echo esc_attr( $group['type'] ); ?>">
                                    <?php echo esc_html( $this->get_status_label( $group['type'] ) ); ?>
                                </span>
                            </h4>
                            <button class="button stl-toggle-group" data-group-id="<?php echo esc_attr( $group['group_id'] ); ?>">
                                <?php esc_html_e( 'Show Details', 'staging2live' ); ?>
                            </button>
                        </div>
                        <div class="stl-group-content" id="group-content-<?php echo esc_attr( $group['group_id'] ); ?>" style="display:none;">
                            <?php foreach ( $group['changes'] as $table => $table_changes ) : ?>
                                <?php 
                                // Special handling for attachments to make them more visual
                                if ($table === 'attachments') : 
                                ?>
                                    <h5><?php esc_html_e( 'Media Attachments', 'staging2live' ); ?> (<?php echo count( $table_changes ); ?>)</h5>
                                    <div class="stl-attachments-grid">
                                        <?php foreach ( $table_changes as $change ) : 
                                            $attachment_title = isset($change['details']['post_title']) ? $change['details']['post_title'] : '';
                                            $attachment_mime = isset($change['details']['post_mime_type']) ? $change['details']['post_mime_type'] : '';
                                            $is_image = strpos($attachment_mime, 'image/') === 0;
                                            
                                            // Try to get the attachment URL from guid
                                            $attachment_url = isset($change['details']['guid']) ? $change['details']['guid'] : '';
                                        ?>
                                            <div class="stl-attachment-item">
                                                <div class="stl-attachment-preview">
                                                    <?php if ($is_image && $attachment_url) : ?>
                                                        <img src="<?php echo esc_url($attachment_url); ?>" alt="<?php echo esc_attr($attachment_title); ?>">
                                                    <?php else : ?>
                                                        <div class="stl-attachment-icon dashicons dashicons-media-default"></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="stl-attachment-details">
                                                    <label>
                                                        <input 
                                                            type="checkbox" 
                                                            class="stl-select-db stl-group-item" 
                                                            data-group="<?php echo esc_attr( $group['group_id'] ); ?>"
                                                            data-table="posts"
                                                            value="<?php echo esc_attr( json_encode( array( 'table' => 'posts', 'id' => $change['id'] ) ) ); ?>"
                                                        >
                                                        <?php echo $attachment_title ? esc_html($attachment_title) : sprintf(esc_html__('Attachment ID: %s', 'staging2live'), $change['id']); ?>
                                                    </label>
                                                    <span class="stl-change-type stl-type-<?php echo esc_attr( $change['type'] ); ?>">
                                                        <?php echo esc_html( $this->get_status_label( $change['type'] ) ); ?>
                                                    </span>
                                                    <button class="button stl-view-db-diff" data-table="posts" data-id="<?php echo esc_attr( $change['id'] ); ?>">
                                                        <?php esc_html_e( 'View Details', 'staging2live' ); ?>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php 
                                // Special handling for child posts
                                elseif ($table === 'child_posts') : 
                                ?>
                                    <h5><?php esc_html_e( 'Child Posts', 'staging2live' ); ?> (<?php echo count( $table_changes ); ?>)</h5>
                                    <div class="stl-child-posts">
                                        <?php foreach ( $table_changes as $change ) : 
                                            $post_title = isset($change['details']['post_title']) ? $change['details']['post_title'] : '';
                                            $post_type = isset($change['details']['post_type']) ? $change['details']['post_type'] : 'post';
                                        ?>
                                            <div class="stl-child-post-item">
                                                <div class="stl-child-post-header">
                                                    <label>
                                                        <input 
                                                            type="checkbox" 
                                                            class="stl-select-db stl-group-item" 
                                                            data-group="<?php echo esc_attr( $group['group_id'] ); ?>"
                                                            data-table="posts"
                                                            value="<?php echo esc_attr( json_encode( array( 'table' => 'posts', 'id' => $change['id'] ) ) ); ?>"
                                                        >
                                                        <span class="stl-child-post-title">
                                                            <?php echo $post_title ? esc_html($post_title) : sprintf(esc_html__('Post ID: %s', 'staging2live'), $change['id']); ?>
                                                        </span>
                                                        <span class="stl-child-post-type"><?php echo esc_html(ucfirst($post_type)); ?></span>
                                                    </label>
                                                    <span class="stl-change-type stl-type-<?php echo esc_attr( $change['type'] ); ?>">
                                                        <?php echo esc_html( $this->get_status_label( $change['type'] ) ); ?>
                                                    </span>
                                                </div>
                                                <div class="stl-child-post-actions">
                                                    <button class="button stl-view-db-diff" data-table="posts" data-id="<?php echo esc_attr( $change['id'] ); ?>">
                                                        <?php esc_html_e( 'View Details', 'staging2live' ); ?>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else : ?>
                                    <h5><?php echo esc_html( ucfirst( $table ) ); ?> (<?php echo count( $table_changes ); ?>)</h5>
                                    <table class="widefat stl-changes-table stl-group-table">
                                        <thead>
                                            <tr>
                                                <th><input type="checkbox" class="stl-select-all-table" data-table="<?php echo esc_attr( $table ); ?>" data-group="<?php echo esc_attr( $group['group_id'] ); ?>"></th>
                                                <th><?php esc_html_e( 'Type', 'staging2live' ); ?></th>
                                                <th><?php esc_html_e( 'Changes', 'staging2live' ); ?></th>
                                                <th><?php esc_html_e( 'Action', 'staging2live' ); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ( $table_changes as $change ) : ?>
                                                <tr>
                                                    <td>
                                                        <input 
                                                            type="checkbox" 
                                                            class="stl-select-db stl-group-item" 
                                                            data-group="<?php echo esc_attr( $group['group_id'] ); ?>"
                                                            data-table="<?php echo esc_attr( $table ); ?>"
                                                            value="<?php echo esc_attr( json_encode( array( 'table' => $table, 'id' => $change['id'] ) ) ); ?>"
                                                        >
                                                    </td>
                                                    <td><?php echo esc_html( $change['type'] ); ?></td>
                                                    <td><?php echo esc_html( $change['summary'] ); ?></td>
                                                    <td>
                                                        <button class="button stl-view-db-diff" data-table="<?php echo esc_attr( $table ); ?>" data-id="<?php echo esc_attr( $change['id'] ); ?>">
                                                            <?php esc_html_e( 'View Details', 'staging2live' ); ?>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php
        }

        // Then render other standalone changes
        $standalone_changes = false;
        foreach ( $changes as $table => $table_changes ) {
            // Skip the content groups as we've already rendered them
            if ( $table === 'content_groups' || $table === 'post_type_groups' ) {
                continue;
            }
            
            if ( ! empty( $table_changes ) ) {
                $standalone_changes = true;
                break;
            }
        }

        if ( $standalone_changes ) {
            ?>
            <h3><?php esc_html_e( 'Other Database Changes', 'staging2live' ); ?></h3>
            <table class="widefat stl-changes-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="stl-select-all-db"></th>
                        <th><?php esc_html_e( 'Table', 'staging2live' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'staging2live' ); ?></th>
                        <th><?php esc_html_e( 'Changes', 'staging2live' ); ?></th>
                        <th><?php esc_html_e( 'Action', 'staging2live' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach ( $changes as $table => $table_changes ) : 
                        // Skip the content groups and post type groups
                        if ( $table === 'content_groups' || $table === 'post_type_groups' ) {
                            continue;
                        }
                        foreach ( $table_changes as $change ) : 
                    ?>
                        <tr>
                            <td><input type="checkbox" class="stl-select-db" value="<?php echo esc_attr( json_encode( array( 'table' => $table, 'id' => $change['id'] ) ) ); ?>"></td>
                            <td><?php echo esc_html( $table ); ?></td>
                            <td><?php echo esc_html( $change['type'] ); ?></td>
                            <td><?php echo esc_html( $change['summary'] ); ?></td>
                            <td>
                                <button class="button stl-view-db-diff" data-table="<?php echo esc_attr( $table ); ?>" data-id="<?php echo esc_attr( $change['id'] ); ?>">
                                    <?php esc_html_e( 'View Details', 'staging2live' ); ?>
                                </button>
                            </td>
                        </tr>
                    <?php 
                        endforeach; 
                    endforeach; 
                    ?>
                </tbody>
            </table>
            <?php
        }
    }

    /**
     * Get status label
     *
     * @param string $status Status code
     * @return string Status label
     */
    private function get_status_label( $status ) {
        $labels = array(
            'added'     => __( 'Added', 'staging2live' ),
            'modified'  => __( 'Modified', 'staging2live' ),
            'deleted'   => __( 'Deleted', 'staging2live' ),
        );

        return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
    }
}

// Initialize the admin class
STL_Admin::get_instance();
