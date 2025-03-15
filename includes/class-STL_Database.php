<?php
class STL_Database {
    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function clean_staging_tables(): bool {
        $staging_tables = $this->wpdb->get_col( "SHOW TABLES LIKE '{$this->wpdb->prefix}staging_%'" );

        if ( empty( $staging_tables ) ) {
            return false;
        }

        foreach ( $staging_tables as $staging_table ) {
            $this->wpdb->query( "DROP TABLE IF EXISTS $staging_table" );
        }

        return true;
    }

    public function dublicate_tables(): bool {
        $this->clean_staging_tables();

        $tables = $this->wpdb->get_col( "SHOW TABLES LIKE '{$this->wpdb->prefix}%'" );

        if ( empty( $tables ) ) {
            return array();
        }

        foreach ( $tables as $table ) {
            $staging_table = str_replace( $this->wpdb->prefix, $this->wpdb->prefix . 'staging_', $table );

            $this->wpdb->query( "DROP TABLE IF EXISTS $staging_table" );

            $create_table_sql = $this->wpdb->get_row( "SHOW CREATE TABLE $table", ARRAY_A );

            $create_sql = str_replace( "CREATE TABLE `{$table}`", "CREATE TABLE `{$staging_table}`", $create_table_sql['Create Table'] );

            $this->wpdb->query( $create_sql );

            $this->wpdb->query( "INSERT INTO $staging_table SELECT * FROM $table" );
        }

        return true;
    }
}
