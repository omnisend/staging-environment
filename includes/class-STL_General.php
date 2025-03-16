<?php
if ( !defined('ABSPATH' ) ) exit;

class STL_General {

	/**
	 * A class constructor
	 *
	 * @since 0.0.1
	 *
	 */
	public function __construct() {

		// init plugin
		self::init();

	}

	private function init(): void {

		if( is_admin() ) {
			new STL_Settings();
		}

		// add action and hook here

	}

	public static function get_site_url(): string {

		// get the protocol
		$protocol = ( isset( $_SERVER[ 'HTTPS' ] ) && $_SERVER[ 'HTTPS' ] === 'on' ) ? 'https' : 'http';

		// get the host name
		$host = $_SERVER[ 'HTTP_HOST' ];

		// check if port is different from standard port (80 or 443)
		if ( ( $protocol === 'http' && $_SERVER[ 'SERVER_PORT' ] != 80) || ( $protocol === 'https' && $_SERVER[ 'SERVER_PORT' ] != 443 ) ) {
			$host .= ':' . $_SERVER[ 'SERVER_PORT '];  // add port if not standard port
		}

		return  $protocol . '://' . $host;

	}

}