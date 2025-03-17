<?php

if ( !defined('ABSPATH' ) ) exit;

if ( ! class_exists('STL_URL_Replacer') ) {
	class STL_URL_Replacer {
        /**
         * @return void
         */
        public function init() {
            //add_filter( 'the_content', array( $this, 'replace_urls_in_content' ), PHP_INT_MAX );
        }

		/**
		 * Replaces URLs in the WordPress database.
		 *
		 * @return void
		 */
		public function replace_staging_url_in_live_database(): void {

			global $wpdb;

			$staging     = stl_get_staging_values();
			$staging_url = $staging[ 'domain' ]; // The staging URL to be replaced
			$live_url    = site_url(); // The live  URL to replace with.

			// Loop through all tables that may contain serialized data
			$tables = $wpdb->get_results( "SELECT table_name FROM INFORMATION_SCHEMA.TABLES 
                  WHERE table_schema = '{DB_NAME}'
                    AND table_name NOT LIKE '{$staging[ 'table_prefix' ]}%';", ARRAY_N );

			foreach ( $tables as $table ) {
				$table_name = $table[0];

				// Check if the table has fields that could contain serialized data
				$columns = $wpdb->get_results( "DESCRIBE {$table_name}", ARRAY_A );

				// Find the filed name of the primary key
				foreach ( $columns as $column ) {
					if( 'PRI' == $column[ 'Key' ] ) {
						$id_field = $column[ 'Field' ];
						break;
					}
				}

				if( empty( $id_field ) ) {
					continue;
				}

				foreach ( $columns as $column ) {
					$column_name = $column[ 'Field' ];
					$column_type = $column[ 'Type' ];

					// If it is a TEXT or LONGTEXT field, check its content
					if ( strpos( $column_type, 'text' ) !== false ) {

						// Fetch the data
						$query   = $wpdb->prepare( "SELECT `{$column_name}`, `{$id_field}` FROM {$table_name} WHERE `{$column_name}` LIKE %s",
						                           '%' . $wpdb->esc_like( $staging_url ) . '%' );
						$results = $wpdb->get_results( $query );

						foreach ( $results as $row ) {
							$original_value = $row->$column_name;

							// Check if the value is serialized
							if ( is_serialized( $original_value ) ) {
								// Unserialize the data
								$unserialized_data = unserialize( $original_value );

								// Replace old URL in unserialized data
								$updated_data = self::replace_url_in_serialized_data( $unserialized_data,
								                                                      $staging_url,
								                                                      $live_url );

								// Re-serialize the updated data
								$new_serialized_data = serialize( $updated_data );

								// Only update if the data has changed
								if ( $new_serialized_data !== $original_value ) {
									// Update the serialized data
									$wpdb->update(
										$table_name,
										array( $column_name => $new_serialized_data ),
										array( $id_field => $row->$id_field )
									);
								}
							} else {
								// If not serialized, perform regular string replacement
								$updated_value = str_replace( $staging_url, $live_url, $original_value );

								// Update if the value has changed
								if ( $updated_value !== $original_value ) {
									$wpdb->update(
										$table_name,
										array( $column_name => $updated_value ),
										array( $id_field => $row->$id_field )
									);
								}
							}
						}
					}
				}
			}
		}

        /**
         * @param $buffer
         *
         * @return string
         */
        public function replace_urls_in_content( $buffer ) {
            if ( ! defined( 'WP_HOME' ) && ! defined( 'WP_PRODUCTION_URL' ) ) {
                return $buffer;
            }

            return str_replace( WP_PRODUCTION_URL, WP_HOME, $buffer );
        }

		/**
		 * Replaces URLs in serialized data.
		 *
		 * @param mixed  $data    The serialized data.
		 * @param string $old_url The old URL.
		 * @param string $new_url The new URL.
		 *
		 * @return mixed The data with the replaced URL.
		 */
		private function replace_url_in_serialized_data( $data, string $old_url, string $new_url ) {
			// If the data is an array, loop through and replace URLs
			if ( is_array( $data ) ) {
				foreach ( $data as $key => $value ) {
					$data[ $key ] = self::replace_url_in_serialized_data( $value, $old_url, $new_url );
				}
			} // If the data is a string, replace the URL
			elseif ( is_string( $data ) ) {
				$data = str_replace( $old_url, $new_url, $data );
			}

			return $data;
		}
	}

}

(new STL_URL_Replacer())->init();
