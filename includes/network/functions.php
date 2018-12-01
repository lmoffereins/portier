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

/**
 * Return the levels for default network access
 *
 * @since 1.3.0
 *
 * @uses apply_filters() Calls 'portier_network_default_access_levels'
 * @return array Default network access levels
 */
function portier_network_default_access_levels() {

	// Define list of levels
	$levels = array(
		'site_users'    => esc_html__( 'Allow site users', 'portier' ),
		'network_users' => esc_html__( 'Allow network users', 'portier' )
	);

	return (array) apply_filters( 'portier_network_default_access_levels', $levels );
}

/**
 * Return the active default network access level
 *
 * @since 1.3.0
 *
 * @uses apply_filters() Calls 'portier_network_get_default_access'
 * @return string Default network access
 */
function portier_network_get_default_access() {
	return apply_filters( 'portier_network_get_default_access', get_site_option( '_portier_network_default_access' ) );
}

/**
 * Return whether the main site should be allowed for all
 *
 * @since 1.3.0
 *
 * @uses apply_filters() Calls 'portier_network_allow_main_site'
 * @return bool Should main site be allowed for all?
 */
function portier_network_allow_main_site() {
	return (bool) apply_filters( 'portier_network_allow_main_site', get_site_option( '_portier_network_allow_main_site' ) );
}

/**
 * Return the additionally selected allowed users for the network
 *
 * @since 1.3.0
 *
 * @uses apply_filters() Calls 'portier_network_get_allowed_users'
 * @return array Allowed users for the network
 */
function portier_network_get_allowed_users() {
	return array_filter( (array) apply_filters( 'portier_network_get_allowed_users', get_site_option( '_portier_network_allowed_users', array() ) ) );
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
 * @since 1.3.0 Added `$site_id` parameter
 *
 * @uses apply_filters() Calls 'portier_network_is_user_allowed' hook
 *                        for plugins to override the access granted
 *
 * @param int $user_id Optional. Defaults to current user
 * @param int $site_id Optional. Site ID. Defaults to the current site ID
 * @return bool Is the user allowed for the network?
 */
function portier_network_is_user_allowed( $user_id = 0, $site_id = 0 ) {

	// Default to current user ID
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	// Always allow super admins
	if ( is_super_admin( $user_id ) )
		return true;

	// Get default access
	$allowed = portier_network_is_user_allowed_by_default( $user_id, $site_id );

	// Try alternative means
	if ( ! $allowed ) {

		// Get allowed users
		$users = portier_network_get_allowed_users();

		// Is user selected to be allowed?
		$allowed = in_array( $user_id, $users );

		// Filter whether user is allowed
		$allowed = (bool) apply_filters( 'portier_network_is_user_allowed', $allowed, $user_id, $site_id );
	}

	return $allowed;
}

/**
 * Return whether the given user is allowed access by default for the network
 *
 * The network-defined access restrictions are enforced before any site access
 * restrictions are evaluated. This means that more strict network restrictions
 * are favored over less strict site restrictions.
 *
 * @since 1.3.0
 *
 * @uses apply_filters() Calls 'portier_network_is_user_allowed_by_default'
 *
 * @param int $user_id Optional. Defaults to current user
 * @param int $site_id Optional. Site ID. Defaults to the current site ID
 * @return bool Is the user allowed by default for the network?
 */
function portier_network_is_user_allowed_by_default( $user_id = 0, $site_id = 0 ) {

	// Default to current user ID
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	// Default to no-access
	$allowed = false;
	$level   = portier_network_get_default_access();

	switch ( $level ) {

		// Allow none
		case '0' :
			break;

		// Allow users of the blog/site
		case 'site_users' :

			// current_user_can( 'read' ) should be equivalent to is_user_member_of_blog()
			$allowed = current_user_can_for_blog( $site_id, 'read' );
			break;

		// Allow users of the network
		case 'network_users' :

			// A network user is any existing user
			$allowed = get_user_by( 'id', $user_id )->exists();
			break;

		// Custom level
		default :
			$allowed = (bool) apply_filters( "portier_network_is_user_allowed_by_default-{$level}", $allowed, $user_id, $site_id );
	}

	return $allowed;
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
