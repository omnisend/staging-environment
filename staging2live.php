<?php
/**
 * Staging2Live
 *
 * @package Staging2Live
 * @subpackage Main
 * @since 1.0.0
 */

/**
 * Plugin Name: Staging2Live
 * Description: Core feature for Studenten Power Berlin
 * Author:      Staging2Live Team
 * Author URI:  https://github.com/omnisend/staging2live
 * Version:     0.0.1
 * Text Domain: staging2live
 * Domain Path: /languages/
 * License:     GPLv2 or later (license.txt)
 */

if ( !defined('STL_CORE_PLUGIN_URL' ) )
	define( 'STL_CORE_PLUGIN_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );

if ( !defined('STL_CORE_PLUGIN_PATH' ) )
	define( 'STL_CORE_PLUGIN_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );
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

// Autoload all PHP files in the includes/ folder.
foreach ( glob( STL_CORE_PLUGIN_PATH . 'includes/class-*.php' ) as $filename ) {
	include_once $filename;
}

if( class_exists( 'STL_General' ) ) {
	new STL_General();
}