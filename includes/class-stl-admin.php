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

        $staging_domain = STL_Settings::get_staging_domain();
        $file_comparer = STL_File_Comparer::get_instance();
        $db_comparer = STL_DB_Comparer::get_instance();

        $file_changes = $file_comparer->get_changes();
        $db_changes = $db_comparer->get_changes();

        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            Staging site: <a target="_blank" href="<?php echo $staging_domain; ?>"><?php echo $staging_domain; ?></a>
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
                <button id="stl-sync-selected" class="button button-primary"><?php esc_html_e( 'Sync Selected Changes', 'staging2live' ); ?></button>
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

        ?>
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
                <?php foreach ( $changes as $table => $table_changes ) : ?>
                    <?php foreach ( $table_changes as $change ) : ?>
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
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
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
