<?php
class STL_File_Handling {

	private $directory; // Base directory (WordPress root)
	private $files = []; // Array to store file information
	private $table_staging_name;

	public function __construct() {

		$options = get_option( 'staging2live_settings' );

		$this->table_staging_name = empty( $options[ 'staging_name' ] ) ? STL_STAGING_NAME_DEFAULT : $options[ 'staging_name' ];

		// Get the absolute path to the WordPress directory, whether it's in the root or a subdirectory
		$this->directory = realpath( ABSPATH );

		// Make sure the directory path uses slashes (for consistency across OS)
		$this->directory = str_replace('\\', '/', $this->directory );

	}

	/**
	 * Loops through the directory and lists all files.
	 */
	public function list_files() {

		if ( ! is_dir( $this->directory ) ) {
			return new WP_Error( 'invalid_directory', esc_html__( 'The specified directory does not exist.', 'staging2live' ) );
		}

		// Recursively scan the directory and subdirectories
		$this->scan_directory( $this->directory );

		return $this->files;
	}

	/**
	 * Recursive method to scan files and create hashes.
	 *
	 * @param string $directory Directory to be scanned.
	 */
	private function scan_directory( string $directory ) {

		$items = new DirectoryIterator( $directory );

		foreach ( $items as $item ) {

			// Skip directories that are not files and skip hidden files and directories (starting with a dot)
			// Skip directories starting with "cache" but allow files like "cache.php"
			// But include the .htaccess file for copying
			if ( $item->isDot() || ( $item->isDir() && 0 === strpos( $item->getFilename(), 'cache' ) ) ) {
				continue;
			}

			$file_path     = $item->getRealPath();
			$relative_path = $this->get_relative_path( $file_path );

			// If it's a file, add it to the array
			if ( $item->isFile() ) {

				// Calculate the hash of the file
				$file_hash = hash_file( 'sha256', $file_path );

				// Add file information to the array
				$this->files[] = array(
					'file_path' => $relative_path,
					'hash'      => $file_hash,
				);

			}

			// If it's a directory, recurse further
			if ( $item->isDir() ) {
				$this->scan_directory( $file_path );
			}
		}
	}

	/**
	 * Gets the relative path of a file based on the installation directory.
	 *
	 * @param string $file_path Absolute file path.
	 *
	 * @return string Relative file path.
	 */
	private function get_relative_path( string $file_path ): string {

		// Ensure the file path uses slashes
		$file_path = str_replace('\\', '/', $file_path);

		// Remove the base directory part from the file path and return it
		return str_replace($this->directory . '/', '', $file_path);

	}

	/**
	 * Inserts the file data into the database table.
	 */
	public function insert_files_into_database() {

		global $wpdb;

		// Get the table name based on the WordPress table prefix
		$table_name = $wpdb->prefix . $this->table_staging_name . '_stl_filehash'; // Table name (prefix + table name of staging site)

		// Loop through the array of files and insert them into the database
		foreach ( $this->files as $file ) {

			// Check if the file already exists in the database
			$existing_file = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name} WHERE file_path = %s",
					$file[ 'file_path' ]
				)
			);

			// If the file doesn't already exist, insert it
			if ( 0 == $existing_file ) {

				$wpdb->insert(
					$table_name,
					array (
						'file_path' => $file[ 'file_path' ], // The file path
						'hash'      => $file[ 'hash' ], // The hash of the file
					)
				);

			}
		}
	}

	/**
	 * Copies all files found in the array $this->files to a folder named by $this->table_staging_name.
	 */
	public function copy_files_to_staging() {

		// Define the target directory based on the $this->table_staging_name
		$target_directory = $this->directory . '/' . $this->table_staging_name;

		// Check if the target directory exists, if not create it
		if ( ! file_exists( $target_directory ) ) {
			mkdir( $target_directory, 0755, true );
		}

		// Loop through the files array and copy each file to the target directory
		foreach ( $this->files as $file ) {

			$source_file = $this->directory . '/' . $file[ 'file_path' ];

			// Define the destination file path
			$destination_file = $target_directory . '/' . $file[ 'file_path' ];

			// Ensure the directory structure exists for the destination file
			$destination_dir = dirname( $destination_file );
			if ( ! file_exists( $destination_dir ) ) {
				mkdir( $destination_dir, 0755, true );
			}

			// Copy the file
			if ( file_exists( $source_file ) ) {
				copy( $source_file, $destination_file );
			}
		}

		// Copy the robots.txt to staging.
		if ( file_exists( STL_PLUGIN_PATH . 'assets/robots.txt' ) ) {
			copy( STL_PLUGIN_PATH . 'assets/robots.txt', $target_directory . '/robots.txt' );
		}

		// Copy & Update the .htaccess file to staging.
		if ( file_exists( STL_PLUGIN_PATH . 'assets/staging-htaccess' ) ) {
			$data = file_get_contents( STL_PLUGIN_PATH . 'assets/staging-htaccess' );

			if ( false !== $data ) {
				$data = str_replace( '{staging_name}', $this->table_staging_name, $data );
				file_put_contents( $target_directory . '/.htaccess', $data );
			}
		}

		// Update staging wp-config.php file table prefix.
		if ( file_exists( $target_directory . '/wp-config.php' ) ) {
			$data = file_get_contents( $target_directory . '/wp-config.php' );

			if ( false !== $data ) {
				$staging_url = site_url() . '/' . $this->table_staging_name;
				$production     = "define( 'WP_PRODUCTION_URL', '" . site_url() . "' );";
				$home     = "define( 'WP_HOME', '" . $staging_url . "' );";
				$siteurl  = "define( 'WP_SITEURL', '" . $staging_url . "' );";
				$env_type = "define( 'WP_ENVIRONMENT_TYPE', 'staging' );";
				$data     = str_replace( "'" . $GLOBALS['wpdb']->base_prefix . "'", "'" . $GLOBALS['wpdb']->base_prefix . $this->table_staging_name . "_'", $data );
				$data     = str_replace( "require_once ABSPATH . 'wp-settings.php';", $home . PHP_EOL . $siteurl . PHP_EOL . $env_type . PHP_EOL . $production . PHP_EOL . "require_once ABSPATH . 'wp-settings.php';", $data );

				file_put_contents( $target_directory . '/wp-config.php', $data );
			}
		}
	}

	/**
	 * Delete folder of previous staging site
	 */
	public function delete_staging_files(): void {

		// Define the target directory based on the $this->table_staging_name
		$target_directory = $this->directory . '/' . $this->table_staging_name;

		if ( is_dir( $target_directory ) ) {
			self::delete_directory_recursively( $target_directory );
		}

	}

	private function delete_directory_recursively( $directory ): void {

		// Get all files and subdirectories in the target directory
		$files_in_folder = scandir( $directory );

		if( ! is_array( $files_in_folder) ) {
			return;
		}

		$files = array_diff( $files_in_folder, array('.', '..') );

		// Loop through all files/subdirectories
		foreach ( $files as $file ) {
			$file_path = $directory . '/' . $file;

			// If it's a subdirectory, call the method recursively
			if ( is_dir( $file_path ) ) {
				self::delete_directory_recursively( $file_path );
			} else {
				// If it's a file, delete it
				unlink( $file_path );
			}
		}

		// Remove the empty directory
		rmdir( $directory );

	}
}
