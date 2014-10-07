<?php

/**
 * Guard Common Functions
 *
 * @package Guard
 * @subpackage Functions
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Returns whether the current user is allowed to enter
 *
 * @since 0.1
 *
 * @uses apply_filters() To call 'guard_user_is_allowed' for
 *                        plugins to override the access granted
 * @uses current_user_can() To check if the current user is admin
 *
 * @return boolean The user is allowed
 */
function guard_user_is_allowed() {

	// Get current user ID
	$user_id = get_current_user_id();

	// Always allow admins
	if ( current_user_can( 'administrator' ) )
		return true;

	// Get allowed users array
	$allowed = (array) get_option( '_guard_allowed_users', array() );

	// Filter whether user is allowed
	return apply_filters( 'guard_user_is_allowed', in_array( $user_id, $allowed ), $user_id );
}

/**
 * Returns whether the current network user is allowed to enter
 *
 * @since 0.2
 *
 * @uses apply_filters() Calls 'guard_network_user_is_allowed' hook
 *                        for plugins to override the access granted
 * @uses is_super_admin() To check if the current user is super admin
 *
 * @return boolean The user is allowed
 */
function guard_network_user_is_allowed() {

	// Get current user ID
	$user_id = get_current_user_id();

	// Always allow super admins
	if ( is_super_admin( $user_id ) )
		return true;

	// Get allowed users array
	$allowed = (array) get_site_option( '_guard_network_allowed_users', array() );

	// Filter whether user is allowed
	return apply_filters( 'guard_network_user_is_allowed', in_array( $user_id, $allowed ), $user_id );
}

/**
 * Return array of all network users
 *
 * @since 0.2
 *
 * @uses get_current_user_id()
 * @uses get_blogs_of_user()
 * @uses switch_to_blog()
 * @uses get_users()
 * @uses restore_current_blog()
 *
 * @return array Network users
 */
function guard_get_network_users() {
	$users = array();
	$user_id = get_current_user_id(); // Always super admin?

	foreach ( get_blogs_of_user( $user_id ) as $blog_id => $details ) {
		switch_to_blog( $blog_id );

		// array( 0 => WP_User ) becomes array( $user_id => WP_User )
		foreach ( get_users() as $user ) {
			$users[$user->ID] = $user;
		}

		restore_current_blog();
	}

	return apply_filters( 'guard_get_network_users', $users );
}

/**
 * Return whether to hide "My Sites" page for the current user
 *
 * @since 0.2
 *
 * @uses get_site_option()
 * @uses get_current_user_id()
 * @uses is_super_admin()
 * @uses get_blogs_of_user()
 * @uses get_current_user_id()
 *
 * @return boolean Hide "My Sites" page
 */
function guard_network_hide_my_sites() {
	if ( ! get_site_option( '_guard_network_hide_my_sites' ) )
		return false;

	$user_id = get_current_user_id();

	// Never hide for super admins
	if ( is_super_admin( $user_id ) )
		return false;

	$blogs = get_blogs_of_user( $user_id );

	return apply_filters( 'guard_network_hide_my_sites', 1 == count( $blogs ), $user_id );
}
