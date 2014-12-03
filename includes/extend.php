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
 * Setup BuddyPress pugin extension
 *
 * @since 1.0.0
 * 
 * @uses buddypress()
 * @uses guard()
 */
function guard_setup_buddypress() {

	// Bail when BuddyPress is not active
	if ( ! function_exists( 'buddypress' ) )
		return;

	// Bail when in maintenance mode
	if ( ! buddypress() || buddypress()->maintenance_mode )
		return;

	// Include BuddyPress extension
	require( guard()->includes_dir . 'extend/buddypress.php' );

	// Initiate BuddyPress for Guard
	guard()->extend->bp = new Guard_BuddyPress();
}
