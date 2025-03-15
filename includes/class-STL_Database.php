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

		error_log( "SHOW TABLES LIKE '{$this->wpdb->prefix}{$this->staging_name}_%'" );

        if ( empty( $staging_tables ) ) {
			error_log( 'nothing to clean');
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

			error_log( '$staging_table='.$staging_table);

            $this->wpdb->query( "DROP TABLE IF EXISTS $staging_table" );

            $create_table_sql = $this->wpdb->get_row( "SHOW CREATE TABLE $table", ARRAY_A );

            $create_sql = str_replace( "CREATE TABLE `{$table}`", "CREATE TABLE `{$staging_table}`", $create_table_sql['Create Table'] );

            $this->wpdb->query( $create_sql );

            $this->wpdb->query( "INSERT INTO $staging_table SELECT * FROM $table" );
        }

        return true;
    }
}
