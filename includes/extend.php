<?php

/**
 * Deurwachter Extend Functions
 * 
 * @package Deurwachter
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
 * @uses deurwachter()
 */
function deurwachter_setup_buddypress() {

	// Bail when BuddyPress is not active
	if ( ! function_exists( 'buddypress' ) )
		return;

	// Bail when in maintenance mode
	if ( ! buddypress() || buddypress()->maintenance_mode )
		return;

	// Include BuddyPress extension
	require( deurwachter()->includes_dir . 'extend/buddypress.php' );

	// Initiate BuddyPress for Deurwachter
	deurwachter()->extend->bp = new Deurwachter_BuddyPress();
}
