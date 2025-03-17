<?php
/**
 * Plugin Name: Staging2Live
 * Description: Seamlessly migrate your changes between environments
 * Author:      Staging2Live Team
 * Author URI:  https://github.com/omnisend/staging2live
 * Version:     0.0.1
 * Text Domain: staging2live
 * Domain Path: /languages/
 * License:     GPLv2 or later (license.txt)
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( !defined('STL_CORE_PLUGIN_URL' ) )
	define( 'STL_CORE_PLUGIN_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );

if ( !defined('STL_PLUGIN_URL' ) )
	define( 'STL_PLUGIN_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );

if ( !defined('STL_PLUGIN_PATH' ) )
	define( 'STL_PLUGIN_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );

if ( !defined('STL_STAGING_NAME_DEFAULT' ) )
	define( 'STL_STAGING_NAME_DEFAULT', 'staging' );

/**
 * Helper functions
 */

/**
 * Returns plugin version or timestamp, depending on environment type
 *
 * @return int|string
 *
 * @since 0.0.1
 */
function stl_get_plugin_version() {

	switch ( wp_get_environment_type() ) {
		case 'development':
		case 'staging':
		case 'local':
			$version = time();
			break;
		default:

			$plugin_data = get_file_data(__FILE__, [
				'Version' => 'Version'
			], 'plugin');

			$version = $plugin_data[ 'Version' ];

			break;
	}

	return $version;

}

function stl_init(): void {
	// load textdomain
	load_plugin_textdomain( 'staging2live', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action ( 'plugins_loaded', 'stl_init' );

/**
 * Returns an array with variable regarding the staging site
 *
 * @return array
 */
function stl_staging_values(): array {

	global $wpdb;

	$options = get_option( 'staging2live_settings' );
	$name    = $options[ 'staging_name' ];

	return array(
		'name'         => $name,
		'table_prefix' => $name . $wpdb->prefix,
		'domain'       => site_url() . '/' . $name
	);
}

/**
 * Returns tur if a staging site exists
 *
 * @return bool
 */
function stl_staging_exists(): bool {

	global $wpdb;

	$staging = stl_staging_values();

	return $wpdb->get_var("SHOW TABLES LIKE '{$staging[ 'table_prefix' ]}_options'") == $staging[ 'table_prefix' ] . '_options';
}


// Autoload all PHP files in the includes/ folder.
foreach ( glob( STL_PLUGIN_PATH . 'includes/class-*.php' ) as $filename ) {
	include_once $filename;
}

if( class_exists( 'STL_General' ) && is_admin() ) {
	new STL_General();
}
