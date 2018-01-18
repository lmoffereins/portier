<?php

/**
 * Portier Extend Functions
 * 
 * @package Portier
 * @subpackage Extend
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Setup BuddyPress pugin extension
 *
 * @since 1.0.0
 */
function portier_setup_buddypress() {

	// Bail when BuddyPress is not active
	if ( ! function_exists( 'buddypress' ) )
		return;

	// Bail when in maintenance mode
	if ( ! buddypress() || buddypress()->maintenance_mode )
		return;

	// Include BuddyPress extension
	require( portier()->includes_dir . 'extend/buddypress.php' );

	// Initiate BuddyPress for Portier
	portier()->extend->bp = new Portier_BuddyPress();
}
