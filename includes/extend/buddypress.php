<?php

/**
 * Guard BuddyPress Extension Class
 * 
 * @package Guard
 * @subpackage Extend
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Guard_BuddyPress' ) ) :
/**
 * BuddyPress extension
 *
 * @since 1.0.0
 */
class Guard_BuddyPress {
	public function __construct() {
		echo 'Hello World!';
	}
}

endif;
