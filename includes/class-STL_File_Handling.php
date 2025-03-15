<?php
class STL_File_Handling {

	private $directory; // Base directory (WordPress root)
	private $files = []; // Array to store file information
	/**
	 * @var string
	 */
	private $table_name;

	public function __construct() {

		$options = get_option( 'staging2live_settings' );

		$table_staging_name = empty( $options[ 'staging_name' ] ) ? STL_STAGING_NAME_DEFAULT : $options[ 'staging_name' ];

		// Get the absolute path to the WordPress directory, whether it's in the root or a subdirectory
		global $wpdb;
		$this->directory = realpath(get_home_path());

		// Make sure the directory path uses slashes (for consistency across OS)
		$this->directory = str_replace('\\', '/', $this->directory);

		// Get the table name based on the WordPress table prefix
		$this->table_name = $wpdb->prefix . $table_staging_name . '_stl_filehash'; // Table name (prefix + table name of staging site)

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
			if ( $item->isDot() || $item->getFilename()[0] === '.'  ) {
			// Skip directories starting with "cache"
			if ( $item->isDot() || '.' === $item->getFilename()[0] || 0 === strpos( $item->getFilename(), 'cache' ) ) {
			// Skip directories starting with "cache" but allow files like "cache.php"
			if ( $item->isDot() || '.' === $item->getFilename()[0] || ( $item->isDir() && 0 === strpos( $item->getFilename(), 'cache' ) ) ) {
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

		// Loop through the array of files and insert them into the database
		foreach ( $this->files as $file ) {

			// Check if the file already exists in the database
			$existing_file = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table_name} WHERE file_path = %s",
					$file[ 'file_path' ]
				)
			);

			// If the file doesn't already exist, insert it
			if ( 0 == $existing_file ) {

				$wpdb->insert(
					$this->table_name,
					[
						'file_path' => $file[ 'file_path' ], // The file path
						'hash'      => $file[ 'hash' ], // The hash of the file
					]
				);

			}
		}
	}

}