<?php
class STL_Deletion {
    public function __construct() {
        add_action( 'init', array( $this, 'process_post' ) );
        add_action( 'admin_menu', array( $this, 'add_delete_page' ), 999 );
    }

    public function process_post() {
        if (isset($_POST['delete_staging'])) {
            $options = get_option( 'staging2live_settings' ); // Should be constant.
            $staging_name = empty( $options[ 'staging_name' ] ) ? STL_STAGING_NAME_DEFAULT : $options[ 'staging_name' ];
            $database = new STL_Database( $staging_name );
            $database->clean_staging_tables();

            $file_lister = new STL_File_Handling();
            $file_lister->delete_staging_files();

            wp_redirect(admin_url('admin.php?page=staging2live'));
            exit;
        }
    }

    public function add_delete_page() {
        if (!stl_staging_exists()) {
            return;
        }

        add_submenu_page(
            'staging2live',
            'Delete Staging',
            'Delete Staging',
            'manage_options',
            'staging2live-delete',
            array( $this, 'render_delete_page' )
        );
    }

    public function render_delete_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Staging Site Deletion Warning', 'staging2live'); ?></h1>
            <p><b><?php esc_html_e('Are you sure you want to proceed?', 'staging2live'); ?></b></p>
            <p><?php esc_html_e('Deleting the staging site is a permanent action. Please make sure all important data has been migrated to the live environment before continuing.', 'staging2live'); ?></p>
            <form method="post" action="">
                <input type="hidden" name="delete_staging" value="yes">
                <?php submit_button(__('Delete staging', 'staging2live')); ?>
            </form>
        </div>
        <?php
    }
}

new STL_Deletion();
