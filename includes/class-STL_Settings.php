<?php
if ( !defined('ABSPATH' ) ) exit;

if ( ! class_exists('STL_Settings') ) {

	class STL_Settings {

		private $admin_url;
		private $options_general;
		private $option_group_general;
		private $option_page_general;
		private $option_page_cli;

		public function __construct() {

			$this->admin_url            = 'admin.php?page=staging2live';
			$this->option_group_general = 'staging2live_settings';
			$this->option_page_general  = 'settings-general';

			$this->options_general      = get_option( $this->option_group_general );

			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ) );

			add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );

			add_action( 'wp_ajax_create_staging', array( $this, 'ajax_create_staging') );

		}

		public function admin_enqueue( $hook ) {

			if( 'toplevel_page_staging2live' != $hook )
				return;

			wp_enqueue_script( 'admin-script', STL_PLUGIN_URL . 'assets/js/admin.min.js', array( 'jquery' ), stl_get_plugin_version(), 'true' );

			wp_localize_script( 'admin-script', 'stl', array(
				'nonce'     => wp_create_nonce( 'stl_nonce' ), // Nonce erstellen
			) );

		}

		public function add_plugin_page(){
			if (stl_staging_exists()) {
                return;
			}
			add_menu_page(
				__( 'Staging2Live', 'staging2live' ),
				__( 'Staging2Live', 'staging2live' ),
				'manage_options',
				'staging2live',
				array( $this, 'create_admin_page' ),
				'dashicons-controls-repeat',
				90
			);

			add_action( 'admin_init', array( $this, 'options_init') );

		}

		public function create_admin_page() {

			$active_page = sanitize_text_field( ( isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'general' ) ); // set default tab ?>

            <div class="wrap">
                <h1><?php _e('Staging2Live', 'staging2live'); ?></h1>
				<?php settings_errors(); ?>
                <h2 class="nav-tab-wrapper">
                    <a href="<?php echo admin_url( $this->admin_url ); ?>" class="nav-tab<?php echo ( 'general' == $active_page ? ' nav-tab-active' : '' ); ?>"><?php esc_html_e('General', 'staging2live'); ?></a>
                    <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'cli' ), admin_url( $this->admin_url ) ) ); ?>" class="nav-tab<?php echo ( 'cli' == $active_page ? ' nav-tab-active' : '' ); ?>"><?php esc_html_e('WP-CLI', 'staging2live'); ?></a>
                </h2>

                <form method="post" action="options.php"><?php //   settings_fields( $this->option_group_general );
					switch ( $active_page ) {
						case 'cli':
							do_settings_sections( $this->option_page_cli );
							break;
						default:
							settings_fields( $this->option_group_general );
							do_settings_sections( $this->option_page_general );
							submit_button();
							break;
					} ?>
                </form>
            </div> <?php

		}

		/**
		 * Initialize Options on Settings Page
		 */
		public function options_init() {
			register_setting(
				$this->option_group_general, // Option group
				$this->option_group_general, // Option name
				array( $this, 'sanitize' ) // Sanitize
			);

			$this->options_general();

			$this->options_cli();

		}

		/**
		 * Page General, Section Settings
		 */
		public function options_general() {
			$section = 'general_settings';

			add_settings_section(
				$section, // ID
				esc_html__('Settings', 'staging2live'),
				'', // Callback
				$this->option_page_general // Page
			);

			$id = 'staging_name';
			add_settings_field(
				$id,
				esc_html__('Staging Name', 'staging2live'),
				array( $this, 'option_input_text_cb'), // general call back for input text
				$this->option_page_general,
				$section,
				array(
					'option_group' => $this->option_group_general,
					'id'           => $id,
					'value'        => $this->options_general[$id] ?? '',
					'description'  => esc_html__('Please add the name of the staging site. Please save it before pressing the button "Create Staging Site"', 'staging2live'),
					'placeholder'  => STL_STAGING_NAME_DEFAULT,
					'width'        => '90%'
				)
			);

			$id = 'create-staging';
			add_settings_field(
				$id,
				esc_html__('Action', 'staging2live'),
				array( $this, 'option_create_staging_button_cb' ),
				$this->option_page_general,
				$section
			);

		}

		/**
		 * Page General, Section CLI
		 */
		public function options_cli() {
			$section = 'general_cli';

			add_settings_section(
				$section, // ID
				esc_html__( 'Command Line Interface (wp cli)', 'staging2live' ),
				array( $this, 'options_cli_info'), // Callback
				$this->option_page_cli // Page
			);

		}

		public function options_cli_info() {

			echo '<p>' . esc_html__( 'In the future you can use Staging2Live with the command line interface from WordPress (wp-cli).', 'staging2live' ) . '</p>';

			echo '<p>' . esc_html__( 'Wehen this happen you will finde the wp-cli options on this page.', 'staging2live' ) . '</p>';
			echo '<ul>';
			echo '<li><code>wp staging2live</code>&nbsp;' . esc_html__( 'Shows all options', 'staging2live' ) . '</li>';
			echo '</ul>';
		}

		/**
		 * Button create staging
		 *
		 * @param array $args
		 */
		public function option_create_staging_button_cb( array $args ){

			$description  = ( isset( $args['description'] ) ) ? $args['description'] : '';

			printf(
				'<p><a href="#" id="create-staging" class="button-primary">%s</a>&nbsp;<span class="spinner spinner-create-staging"></p>',
				esc_html__('Create Staging Site', 'staging2live' )
			);

			if ( !empty( $description) )
				echo '<p class="description">' . $description . '</p>';

            echo '<div id="response"></div>';

		}

		/**
		 * General Input Field Text
		 *
		 * @param array $args
		 */
		public function option_input_text_cb( array $args ) {

			$option_group = ( isset( $args['option_group'] ) ) ? $args['option_group'] : '';
			$id           = ( isset( $args['id'] ) ) ? $args['id'] : '';
			$value        = ( isset( $args['value'] ) ) ? $args['value'] : '';
			$placeholder  = ( isset( $args['placeholder'] ) ) ? $args['placeholder'] : '';
			$description  = ( isset( $args['description'] ) ) ? $args['description'] : '';
			$password     = ( isset( $args['password'] ) ) ? $args[ 'password' ] : false;
			$width        = ( isset( $args['width'] ) ) ? $args['width'] : '';

			$type = 'text';
			if( true == $password ) $type = 'password';

			printf(
				'<input type="%6$s" id="%1$s" name="%3$s[%1$s]" value="%2$s" placeholder="%4$s" style="width: %5$s"/>',
				$id, $value, $option_group, $placeholder, $width, $type
			);

			if ( !empty( $description) )
				echo '<p class="description">' . $description . '</p>';
		}

		/**
		 * General Input Field Checkbox
		 *
		 * @param array $args
		 */
		public function option_input_checkbox_cb( array $args ){

			$option_group = ( isset( $args['option_group'] ) ) ? $args['option_group'] : '';
			$id           = ( isset( $args['id'] ) ) ? $args['id'] : '';
			$checked      = ( isset( $args['value'] ) && !empty( $args['value'] ) ) ? 'checked' : '';
			$description  = ( isset( $args['description'] ) ) ? $args['description'] : '';

			printf(
				'<input type="checkbox" id="%1$s" name="%3$s[%1$s]" value="1" %2$s />',
				$id, $checked, $option_group
			);

			if ( !empty( $description) )
				echo '<p class="description">' . $description . '</p>';

		}

		/**
		 * Sanitizes a string from user input
		 * Checks for invalid UTF-8, Converts single < characters to entities, Strips all tags, Removes line breaks, tabs, and extra whitespace, Strips octets
		 *
		 * @param array $input
		 *
		 * @return array
		 */
		public function sanitize( array $input ): array {

			$new_input = array();

			foreach ( $input as $key => $value ) {

				$new_input[ $key ] = sanitize_text_field( $value );

			}

			return $new_input;
		}

		/**
		 * WP ajax request for creating staging site
		 */
		public function ajax_create_staging() {

			// check if nonce is valid
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'stl_nonce' ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Invalid nonce.', 'staging2live' ) ) );
			}

			// check if user has the capability
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Not entitled.', 'staging2live' ) ) );
			}

			// check if class exists
			if ( ! class_exists( 'STL_Database' ) || ! class_exists( 'STL_File_Handling' ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Class is missing. Please contact plugin author.', 'staging2live' ) ) );
			}

			$staging_name = empty( $this->options_general[ 'staging_name' ] ) ? STL_STAGING_NAME_DEFAULT : $this->options_general[ 'staging_name' ];

			// Start the staging creation process
			$database = new STL_Database( $staging_name );

			// 1. Duplicate database tables
			$result = $database->duplicate_tables();
			if ( ! $result ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Error while duplicating the database.', 'staging2live' ) ) );
			}

			// 2. Delete previous staging environment
			$file_lister = new STL_File_Handling();
			$file_lister->delete_staging_files();

			// 3. List files for duplication
			$file_list = $file_lister->list_files();
			if ( is_wp_error( $file_list ) ) {
				wp_send_json_error( array( 'message' => sprintf( esc_html__( 'Error %s.', 'staging2live' ), $file_list->get_error_message() ) ) );
			} else {
				// Insert file data into the database
				$file_lister->insert_files_into_database();
			}

			// 3. Copy files to the staging environment
			$file_lister->copy_files_to_staging();
			// 4. Copy files to the staging environment

			// 5. Finish and generate URL
			$staging_domain = trailingslashit( STL_General::get_site_url() ) . trailingslashit( $staging_name );
			wp_send_json_success( array( 'message' => sprintf( esc_html__( 'Staging site successfully created. The URL is %s', 'staging2live' ), '<a href="' . $staging_domain . '" target="_blank">' . $staging_domain . '</a>' ) ) );
		}

	}

}

