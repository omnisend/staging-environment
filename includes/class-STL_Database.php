<?php
class STL_Database {
    private $wpdb;

	private $staging_name;

    public function __construct( $staging_name ) {
        global $wpdb;
        $this->wpdb = $wpdb;

		$this->staging_name = $staging_name;

    }

    public function clean_staging_tables(): void {
        $this->clean_tables_by_prefix("{$this->wpdb->prefix}{$this->staging_name}_");
        $this->clean_tables_by_prefix("{$this->wpdb->prefix}{$this->staging_name}_snapshot_");
    }
    private function clean_tables_by_prefix($prefix):bool {
        $tables = $this->wpdb->get_col( "SHOW TABLES LIKE '{$prefix}%'" );

        if ( empty( $tables ) ) {
            return false;
        }

        foreach ( $tables as $table ) {
            $this->wpdb->query( "DROP TABLE IF EXISTS $table" );
        }

        return true;
    }

    public function duplicate_tables(): bool {
        $this->clean_staging_tables();

        $tables = $this->wpdb->get_col( "SHOW TABLES LIKE '{$this->wpdb->prefix}%'" );

        if ( empty( $tables ) ) {
            return false;
        }

        $new_prefix = $this->wpdb->prefix . $this->staging_name . '_';
        $snapshot_prefix = $this->wpdb->prefix . $this->staging_name . '_snapshot_';

        foreach ( $tables as $table ) {
            $staging_table = str_replace( $this->wpdb->prefix, $new_prefix, $table );
            $snapshot_table = str_replace( $this->wpdb->prefix, $snapshot_prefix, $table );

            $this->wpdb->query( "DROP TABLE IF EXISTS $staging_table" );
            $this->wpdb->query( "DROP TABLE IF EXISTS $snapshot_table" );

            $create_table_sql = $this->wpdb->get_row( "SHOW CREATE TABLE $table", ARRAY_A );

            $create_sql = str_replace( "CREATE TABLE `{$table}`", "CREATE TABLE `{$staging_table}`", $create_table_sql['Create Table'] );
            $create_sql_snapshot = str_replace( "CREATE TABLE `{$table}`", "CREATE TABLE `{$snapshot_table}`", $create_table_sql['Create Table'] );

            $this->wpdb->query( $create_sql );
            $this->wpdb->query( $create_sql_snapshot );

            $this->wpdb->query( "INSERT INTO $staging_table SELECT * FROM $table" );
            $this->wpdb->query( "INSERT INTO $snapshot_table SELECT * FROM $table" );
        }

        $this->wpdb->query( "UPDATE " . $new_prefix . "usermeta SET meta_key = '" . $new_prefix. "capabilities' where meta_key = '" . $this->wpdb->prefix . "capabilities'" );

        $this->wpdb->query( "UPDATE " . $new_prefix . "usermeta SET meta_key = '" . $new_prefix. "user_level' where meta_key = '" . $this->wpdb->prefix . "user_level'" );

        $this->wpdb->query( "UPDATE " . $new_prefix . "usermeta SET meta_key = '" . $new_prefix. "autosave_draft_ids' where meta_key = '" . $this->wpdb->prefix . "autosave_draft_ids'" );

        $this->wpdb->query( "UPDATE " . $new_prefix . "options SET option_name = '" . $new_prefix. "user_roles' where option_name = '" . $this->wpdb->prefix . "user_roles'" );

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
