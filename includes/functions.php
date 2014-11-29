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
 * Return whether the given site's protection is active
 *
 * @since 1.0.0
 *
 * @uses is_multisite()
 * @uses switch_to_blog()
 * @uses get_option()
 * @uses restor_current_blog()
 * @uses apply_filters() Calls 'guard_is_site_protected'
 * 
 * @param int $site_id Optional. Site ID. Defaults to the current site ID
 * @return bool Site protection is active
 */
function guard_is_site_protected( $site_id = 0 ) {

	// Network: switch to site
	if ( ! empty( $site_id ) && is_multisite() ) {
		$site_id = (int) $site_id;
		switch_to_blog( $site_id );
	}

	$protected = get_option( '_guard_site_protect' );

	// Network: reset the switched site
	if ( ! empty( $site_id ) && is_multisite() ) {
		restore_current_blog();
	}

	return (bool) apply_filters( 'guard_is_site_protected', $protected, $site_id );
}

/**
 * Return whether the network's protection is active
 *
 * @since 1.0.0
 *
 * @uses is_multisite()
 * @uses get_site_option()
 * @uses apply_filters() Calls 'guard_is_network_protected'
 * 
 * @return bool Network protection is active
 */
function guard_is_network_protected() {

	// Bail when not on multisite
	if ( ! is_multisite() )
		return false;

	$protected = get_site_option( '_guard_network_protect' );

	return (bool) apply_filters( 'guard_is_network_protected', $protected );
}

/**
 * Returns whether the given user is allowed access for the given site
 *
 * @since 1.0.0
 *
 * @uses get_current_user_id()
 * @uses is_super_admin() To check if the current user is a super admin
 * @uses is_multisite()
 * @uses switch_to_blog()
 * @uses get_option()
 * @uses restore_current_blog()
 * @uses apply_filters() To call 'guard_is_user_allowed' for
 *                        plugins to override the access granted
 *
 * @param int $user_id Optional. Defaults to current user
 * @param int $site_id Optional. Site ID. Defaults to the current site ID
 * @return boolean The user is allowed
 */
function guard_is_user_allowed( $user_id = 0, $site_id = 0 ) {

	// Default to current user ID
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	// Always allow (super) admins. For non-multisite defaults to has_cap( 'delete_users' )
	if ( is_super_admin( $user_id ) )
		return true;

	// Network: switch to site
	if ( ! empty( $site_id ) && is_multisite() ) {
		$site_id = (int) $site_id;
		switch_to_blog( $site_id );
	}

	// Get allowed users array
	$users = (array) get_option( '_guard_allowed_users', array() );

	// Network: reset the switched site
	if ( ! empty( $site_id ) && is_multisite() ) {
		restore_current_blog();
	}

	// Is user selected to be allowed?
	$allowed = ! empty( $users ) ? in_array( $user_id, $users ) : true;

	// Filter whether user is allowed
	return (bool) apply_filters( 'guard_is_user_allowed', $allowed, $user_id, $site_id );
}

/**
 * Returns whether the given user is allowed access for the network
 *
 * @since 0.2
 *
 * @uses get_current_user_id()
 * @uses is_super_admin() To check if the current user is a super admin
 * @uses get_site_option()
 * @uses apply_filters() Calls 'guard_network_is_user_allowed' hook
 *                        for plugins to override the access granted
 *
 * @param int $user_id Optional. Defaults to current user
 * @return boolean The user is allowed
 */
function guard_network_is_user_allowed( $user_id = 0 ) {

	// Default to current user ID
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	// Always allow super admins
	if ( is_super_admin( $user_id ) )
		return true;

	// Get allowed users array
	$users = (array) get_site_option( '_guard_network_allowed_users', array() );

	// Is user selected to be allowed?
	$allowed = ! empty( $users ) ? in_array( $user_id, $users ) : true;

	// Filter whether user is allowed
	return (bool) apply_filters( 'guard_network_is_user_allowed', $allowed, $user_id );
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

	return apply_filters( 'guard_network_hide_my_sites', 1 == count( $blogs ), $user_id, $blogs );
}
