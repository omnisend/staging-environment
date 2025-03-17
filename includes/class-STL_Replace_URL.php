<?php
class STL_Replace_URL {
    public function __construct() {
        add_filter( 'the_content', array( $this, 'replace_urls_in_content' ), PHP_INT_MAX );
    }

    public function replace_urls_in_content( $buffer ) {
        if ( ! defined( 'WP_HOME' ) && ! defined( 'WP_PRODUCTION_URL' ) ) {
            return $buffer;
        }

        return str_replace( WP_PRODUCTION_URL, WP_HOME, $buffer );
    }
}

new STL_Replace_URL();
