<?php

/**
 * Guard Multisite Functions
 *
 * @package Guard
 * @subpackage Multisite
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Guard_MS' ) ) :
/**
 * Guard Multisite Class
 *
 * @since 1.0.0
 */
final class Guard_MS {

}

/**
 * Setup the Guard Multisite class
 *
 * @since 1.0.0
 *
 * @uses guard()
 * @uses Guard_MS
 */
function guard_ms() {
	guard()->ms = new Guard_MS;
}

endif; // class_exists
