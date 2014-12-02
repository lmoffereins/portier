<?php

/**
 * Guard Extend Functions
 * 
 * @package Guard
 * @subpackage Extend
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Initiate the available extensions
 *
 * @since 1.0.0
 */
function guard_extend() {
	
	// Get Guard
	$guard = guard();

	// Extensions paths
	$guard->extend_dir = trailingslashit( $this->includes_dir . 'extend' );
	$guard->extend_url = trailingslashit( $this->includes_url . 'extend' );

	do_action( 'guard_extend' );
}
