<?php
class STL_Database {
    private $wpdb;

	private $staging_name;

    public function __construct( $staging_name ) {
        global $wpdb;
        $this->wpdb = $wpdb;

		$this->staging_name = $staging_name;

    }

    public function clean_staging_tables(): bool {
        $staging_tables = $this->wpdb->get_col( "SHOW TABLES LIKE '{$this->wpdb->prefix}{$this->staging_name}_%'" );

        if ( empty( $staging_tables ) ) {
            return false;
        }

        foreach ( $staging_tables as $staging_table ) {
            $this->wpdb->query( "DROP TABLE IF EXISTS $staging_table" );
        }

        return true;
    }

    public function duplicate_tables(): bool {
        $this->clean_staging_tables();

        $tables = $this->wpdb->get_col( "SHOW TABLES LIKE '{$this->wpdb->prefix}%'" );

        if ( empty( $tables ) ) {
            return false;
        }

        foreach ( $tables as $table ) {
            $staging_table = str_replace( $this->wpdb->prefix, $this->wpdb->prefix . $this->staging_name . '_', $table );

            $this->wpdb->query( "DROP TABLE IF EXISTS $staging_table" );

            $create_table_sql = $this->wpdb->get_row( "SHOW CREATE TABLE $table", ARRAY_A );

            $create_sql = str_replace( "CREATE TABLE `{$table}`", "CREATE TABLE `{$staging_table}`", $create_table_sql['Create Table'] );

            $this->wpdb->query( $create_sql );

            $this->wpdb->query( "INSERT INTO $staging_table SELECT * FROM $table" );
        }

		$this->add_hash_table();

        return true;
    }

	private function add_hash_table() {

		global $wpdb;

		$hash_table_name = $wpdb->prefix . $this->staging_name . '_stl_filehash';

		// Check if the table exists
		$table_exists = $wpdb->get_var(
			"SHOW TABLES LIKE '{$hash_table_name}'"
		);

		// If the table doesn't exist, create it
		if ( ! $table_exists ) {
			$charset_collate = $wpdb->get_charset_collate();

			// SQL query to create the table
			$create_table_sql = "CREATE TABLE {$hash_table_name} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                file_path VARCHAR(255) NOT NULL,
                hash VARCHAR(64) NOT NULL,
                UNIQUE (file_path)
            ) $charset_collate;";

			// Include the required WordPress function for creating tables
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $create_table_sql );
		}
	}
}
