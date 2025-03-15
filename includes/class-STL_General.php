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

}