<?php

/**
 * Deurwachter Common Functions
 *
 * @package Deurwachter
 * @subpackage Functions
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Return whether the given site's protection is active
 *
 * @since 1.0.0
 *
 * @uses apply_filters() Calls 'deurwachter_is_site_protected'
 * 
 * @param int $site_id Optional. Site ID. Defaults to the current site ID
 * @return bool Site protection is active
 */
function deurwachter_is_site_protected( $site_id = 0 ) {

	// Network: switch to site
	if ( ! empty( $site_id ) && is_multisite() ) {
		$site_id = (int) $site_id;
		switch_to_blog( $site_id );
	}

	$protected = get_option( '_deurwachter_site_protect' );

	// Network: reset the switched site
	if ( ! empty( $site_id ) && is_multisite() ) {
		restore_current_blog();
	}

	return (bool) apply_filters( 'deurwachter_is_site_protected', $protected, $site_id );
}

/**
 * Returns whether the given user is allowed access for the given site
 *
 * @since 1.0.0
 *
 * @uses apply_filters() Calls 'deurwachter_is_user_allowed'
 *
 * @param int $user_id Optional. Defaults to current user
 * @param int $site_id Optional. Site ID. Defaults to the current site ID
 * @return boolean The user is allowed
 */
function deurwachter_is_user_allowed( $user_id = 0, $site_id = 0 ) {

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
	$users = (array) get_option( '_deurwachter_allowed_users', array() );

	// Network: reset the switched site
	if ( ! empty( $site_id ) && is_multisite() ) {
		restore_current_blog();
	}

	// Is user selected to be allowed?
	$allowed = in_array( $user_id, $users );

	// Filter whether user is allowed
	return (bool) apply_filters( 'deurwachter_is_user_allowed', $allowed, $user_id, $site_id );
}

/**
 * Return basic site protection details
 * 
 * @since 1.0.0
 *
 * @uses apply_filters() Calls 'deurwachter_get_protection_details'
 * 
 * @return string Protection details
 */
function deurwachter_get_protection_details() {

	// Setup basic protection details: allowed user count
	$allowed_user_count = count( get_option( '_deurwachter_allowed_users' ) );
	$details = sprintf( _n( '%d allowed user', '%d allowed users', $allowed_user_count, 'deurwachter' ), $allowed_user_count );

	return apply_filters( 'deurwachter_get_protection_details', $details );
}

/**
 * Return whether the network's protection is active
 *
 * @since 1.0.0
 *
 * @uses apply_filters() Calls 'deurwachter_is_network_protected'
 * 
 * @return bool Network protection is active
 */
function deurwachter_is_network_protected() {

	// Bail when not on multisite
	if ( ! is_multisite() )
		return false;

	return (bool) apply_filters( 'deurwachter_is_network_protected', get_site_option( '_deurwachter_network_protect' ) );
}

/**
 * Returns whether the given user is allowed access for the network
 *
 * @since 1.0.0
 *
 * @uses apply_filters() Calls 'deurwachter_network_is_user_allowed' hook
 *                        for plugins to override the access granted
 *
 * @param int $user_id Optional. Defaults to current user
 * @return boolean The user is allowed
 */
function deurwachter_network_is_user_allowed( $user_id = 0 ) {

	// Default to current user ID
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	// Always allow super admins
	if ( is_super_admin( $user_id ) )
		return true;

	// Get allowed users array
	$users = (array) get_site_option( '_deurwachter_network_allowed_users', array() );

	// Is user selected to be allowed?
	$allowed = in_array( $user_id, $users );

	// Filter whether user is allowed
	return (bool) apply_filters( 'deurwachter_network_is_user_allowed', $allowed, $user_id );
}

/**
 * Return whether redirection from unallowed sites is active
 *
 * @since 1.0.0
 *
 * @uses apply_filters() Calls 'deurwachter_network_redirect'
 * 
 * @return bool Network redirect is active
 */
function deurwachter_network_redirect() {
	return (bool) apply_filters( 'deurwachter_network_redirect', get_site_option( '_deurwachter_network_redirect' ) );
}

/**
 * Return whether the plugin is active for the network level only
 *
 * @since 1.0.0
 *
 * @uses apply_filters() Calls 'deurwachter_is_network_only'
 * 
 * @return bool Deurwachter is for the network level only
 */
function deurwachter_is_network_only() {
	return (bool) apply_filters( 'deurwachter_is_network_only', get_site_option( '_deurwachter_network_only' ) );
}

/**
 * Return array of all network users
 *
 * @since 1.0.0
 *
 * @uses apply_filters() Calls 'deurwachter_get_network_users'
 *
 * @return array Network users
 */
function deurwachter_get_network_users() {

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

	return apply_filters( 'deurwachter_get_network_users', $users );
}

/**
 * Return whether to hide "My Sites" for the current user
 *
 * @since 1.0.0
 *
 * @uses apply_filters() Calls 'deurwachter_network_hide_my_sites'
 *
 * @return boolean Hide "My Sites"
 */
function deurwachter_network_hide_my_sites() {

	// Define local variable(s)
	$user_id = get_current_user_id();
	$sites   = get_blogs_of_user( $user_id );
	$hide    = false;

	// Never hide for super admins
	if ( is_super_admin( $user_id ) )
		return false;

	// Hiding is active and user site count is less then two
	if ( get_site_option( '_deurwachter_network_hide_my_sites' ) && count( $sites ) < 2 ) {
		$hide = true;
	}

	return apply_filters( 'deurwachter_network_hide_my_sites', $hide, $user_id, $sites );
}
