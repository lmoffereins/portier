<?php

/**
 * Portier Network Functions
 *
 * @package Portier
 * @subpackage Network
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** Options *******************************************************************/

/**
 * Return whether redirection from unallowed sites is active
 *
 * @since 1.0.0
 *
 * @uses apply_filters() Calls 'portier_network_redirect'
 * @return bool Network redirect is active
 */
function portier_network_redirect() {
	return (bool) apply_filters( 'portier_network_redirect', get_site_option( '_portier_network_redirect' ) );
}

/**
 * Return whether the plugin is active for the network level only
 *
 * @since 1.0.0
 *
 * @uses apply_filters() Calls 'portier_is_network_only'
 * @return bool Portier is for the network level only
 */
function portier_is_network_only() {
	return (bool) apply_filters( 'portier_is_network_only', get_site_option( '_portier_network_only' ) );
}

/** Protection ****************************************************************/

/**
 * Return whether the network's protection is active
 *
 * @since 1.0.0
 *
 * @uses apply_filters() Calls 'portier_is_network_protected'
 * @return bool Network protection is active
 */
function portier_is_network_protected() {

	// Bail when not on multisite
	if ( ! is_multisite() )
		return false;

	return (bool) apply_filters( 'portier_is_network_protected', get_site_option( '_portier_network_protect' ) );
}

/**
 * Returns whether the given user is allowed access for the network
 *
 * @since 1.0.0
 *
 * @uses apply_filters() Calls 'portier_network_is_user_allowed' hook
 *                        for plugins to override the access granted
 *
 * @param int $user_id Optional. Defaults to current user
 * @return boolean The user is allowed
 */
function portier_network_is_user_allowed( $user_id = 0 ) {

	// Default to current user ID
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	// Always allow super admins
	if ( is_super_admin( $user_id ) )
		return true;

	// Get allowed users array
	$users = (array) get_site_option( '_portier_network_allowed_users', array() );

	// Is user selected to be allowed?
	$allowed = in_array( $user_id, $users );

	// Filter whether user is allowed
	return (bool) apply_filters( 'portier_network_is_user_allowed', $allowed, $user_id );
}

/**
 * Return array of all network users
 *
 * @since 1.0.0
 *
 * @uses apply_filters() Calls 'portier_get_network_users'
 * @return array Network users
 */
function portier_get_network_users() {

	// Define local variable(s)
	$users   = array();
	$user_id = get_current_user_id(); // Always super admin?

	foreach ( get_blogs_of_user( $user_id ) as $blog_id => $details ) {
		switch_to_blog( $blog_id );

		// array( 0 => WP_User ) becomes array( $user_id => WP_User )
		foreach ( get_users() as $user ) {
			$users[ $user->ID ] = $user;
		}

		restore_current_blog();
	}

	return apply_filters( 'portier_get_network_users', $users );
}

/**
 * Return whether to hide "My Sites" for the current user
 *
 * @since 1.0.0
 *
 * @uses apply_filters() Calls 'portier_network_hide_my_sites'
 * @return boolean Hide "My Sites"
 */
function portier_network_hide_my_sites() {

	// Define local variable(s)
	$user_id = get_current_user_id();
	$sites   = get_blogs_of_user( $user_id );
	$hide    = false;

	// Never hide for super admins
	if ( is_super_admin( $user_id ) )
		return false;

	// Hiding is active and user site count is less then two
	if ( get_site_option( '_portier_network_hide_my_sites' ) && count( $sites ) < 2 ) {
		$hide = true;
	}

	return apply_filters( 'portier_network_hide_my_sites', $hide, $user_id, $sites );
}

/** Admin ************************************************************/

/**
 * Return the plugin's sites list table class
 *
 * @since 1.1.0
 */
function _get_portier_network_sites_list_table( $args = array() ) {

	// Load list table classes
	require_once( ABSPATH . 'wp-admin/includes/class-wp-ms-sites-list-table.php' );
	require_once( portier()->includes_dir . 'classes/class-portier-network-sites-list-table.php' );

	// Setup the screen argument
	if ( isset( $args['screen'] ) ) {
		$args['screen'] = convert_to_screen( $args['screen'] );
	} elseif ( isset( $GLOBALS['hook_suffix'] ) ) {
		$args['screen'] = get_current_screen();
	} else {
		$args['screen'] = null;
	}

	return new Portier_Network_Sites_List_Table( $args );
}
