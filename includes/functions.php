<?php

/**
 * Portier Common Functions
 *
 * @package Portier
 * @subpackage Functions
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** Versions ******************************************************************/

/**
 * Output the Portier version
 *
 * @since 1.2.0
 */
function portier_version() {
	echo portier_get_version();
}

	/**
	 * Return the Portier version
	 *
	 * @since 1.2.0
	 *
	 * @return string The Portier version
	 */
	function portier_get_version() {
		return portier()->version;
	}

/**
 * Output the Portier database version
 *
 * @since 1.2.0
 */
function portier_db_version() {
	echo portier_get_db_version();
}

	/**
	 * Return the Portier database version
	 *
	 * @since 1.2.0
	 *
	 * @return string The Portier version
	 */
	function portier_get_db_version() {
		return portier()->db_version;
	}

/**
 * Output the Portier database version directly from the database
 *
 * @since 1.2.0
 */
function portier_db_version_raw() {
	echo portier_get_db_version_raw();
}

	/**
	 * Return the Portier database version directly from the database
	 *
	 * @since 1.2.0
	 *
	 * @return string The current Portier version
	 */
	function portier_get_db_version_raw() {
		return get_option( '_portier_db_version', '' );
	}

/** Options *******************************************************************/

/**
 * Return the levels for default access
 *
 * @since 1.3.0
 *
 * @uses apply_filters() Calls 'portier_default_access_levels'
 * @return array Default access levels
 */
function portier_default_access_levels() {

	// Define list of levels
	$levels = array(
		'site_users' => esc_html__( 'Site users', 'portier' )
	);

	// Network levels
	if ( is_multisite() ) {
		$levels['network_users'] = esc_html__( 'Network users', 'portier' );
	}

	return (array) apply_filters( 'portier_default_access_levels', $levels );
}

/**
 * Return the active default access level
 *
 * @since 1.3.0
 *
 * @uses apply_filters() Calls 'portier_get_default_access'
 * @return string Default access
 */
function portier_get_default_access() {
	return apply_filters( 'portier_get_default_access', get_option( '_portier_default_access' ) );
}

/**
 * Return the label for the none access level
 *
 * @since 1.3.1
 *
 * @uses apply_filters() Calls 'portier_get_none_access_label'
 * @return string Label for the none access level
 */
function portier_get_none_access_label() {
	return apply_filters( 'portier_get_none_access_label', esc_html__( 'None', 'portier' ) );
}

/**
 * Return the additionally selected allowed users
 *
 * @since 1.3.0
 *
 * @uses apply_filters() Calls 'portier_get_allowed_users'
 * @return array Allowed users
 */
function portier_get_allowed_users() {
	return array_filter( (array) apply_filters( 'portier_get_allowed_users', get_option( '_portier_allowed_users', array() ) ) );
}

/** Protection ****************************************************************/

/**
 * Return whether the given site's protection is active
 *
 * @since 1.0.0
 *
 * @uses apply_filters() Calls 'portier_is_site_protected'
 * 
 * @param int $site_id Optional. Site ID. Defaults to the current site ID
 * @return bool Is site protection active?
 */
function portier_is_site_protected( $site_id = 0 ) {

	// Switch site?
	$switched = ! empty( $site_id ) && is_multisite() ? switch_to_blog( $site_id ) : false;

	// Get site protection
	$protected = get_option( '_portier_site_protect' );

	// Reset switched site
	if ( $switched ) {
		restore_current_blog();
	}

	return (bool) apply_filters( 'portier_is_site_protected', $protected, $site_id );
}

/**
 * Returns whether the given user is allowed access for the given site
 *
 * @since 1.0.0
 *
 * @uses apply_filters() Calls 'portier_is_user_allowed'
 *
 * @param int $user_id Optional. Defaults to current user
 * @param int $site_id Optional. Site ID. Defaults to the current site ID
 * @return bool Is the user allowed?
 */
function portier_is_user_allowed( $user_id = 0, $site_id = 0 ) {

	// Default to current user ID
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	// Always allow (super) admins. For non-multisite defaults to has_cap( 'delete_users' )
	if ( is_super_admin( $user_id ) )
		return true;

	// Get default access
	$allowed = portier_is_user_allowed_by_default( $user_id, $site_id );

	// Try alternative means
	if ( ! $allowed ) {

		// Switch site?
		$switched = ! empty( $site_id ) && is_multisite() ? switch_to_blog( $site_id ) : false;

		// Get allowed users
		$users = portier_get_allowed_users();

		// Reset switched site
		if ( $switched ) {
			restore_current_blog();
		}

		// Is user selected to be allowed?
		$allowed = in_array( $user_id, $users );

		// Filter whether user is allowed
		$allowed = (bool) apply_filters( 'portier_is_user_allowed', $allowed, $user_id, $site_id );
	}

	return $allowed;
}

/**
 * Return whether the given user is allowed access by default for the given site
 *
 * @since 1.3.0
 *
 * @uses apply_filters() Calls 'portier_is_user_allowed_by_default'
 *
 * @param int $user_id Optional. Defaults to current user
 * @param int $site_id Optional. Site ID. Defaults to the current site ID
 * @return bool Is the user allowed by default?
 */
function portier_is_user_allowed_by_default( $user_id = 0, $site_id = 0 ) {

	// Default to current user ID
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	// Default to no-access
	$allowed = false;
	$level   = portier_get_default_access();

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
			$allowed = (bool) apply_filters( "portier_is_user_allowed_by_default-{$level}", $allowed, $user_id, $site_id );
	}

	return $allowed;
}

/**
 * Return basic site protection details
 *
 * @since 1.3.0
 *
 * @param string $sep Optional. Detail separator. Defaults to '<br/>'.
 * @param int $site_id Optional. Site ID. Defaults to the current site ID
 * @return string Protection details
 */
function portier_the_protection_details( $sep = "<br/>", $site_id = 0 ) {
	echo implode( $sep, portier_get_protection_details( $site_id ) );
}

/**
 * Return basic site protection details
 *
 * @since 1.0.0
 *
 * @uses apply_filters() Calls 'portier_get_protection_details'
 *
 * @param int $site_id Optional. Site ID. Defaults to the current site ID
 * @return array Protection details
 */
function portier_get_protection_details( $site_id = 0 ) {

	// Switch site?
	$switched = ! empty( $site_id ) && is_multisite() ? switch_to_blog( $site_id ) : false;

	// Get default access level
	$levels = portier_default_access_levels();
	$level  = portier_get_default_access();

	$details = array(
		'default_access' => sprintf( esc_html__( 'Site: %s', 'portier' ), isset( $levels[ $level ] )
			? $levels[ $level ]
			: portier_get_none_access_label()
		)
	);

	// Setup basic protection details: allowed user count
	$user_count = count( portier_get_allowed_users() );
	if ( $user_count ) {
		$details['allowed_users'] = sprintf( _n( '%d allowed user', '%d allowed users', $user_count, 'portier' ), $user_count );
	}

	// Reset switched site
	if ( $switched ) {
		restore_current_blog();
	}

	return (array) apply_filters( 'portier_get_protection_details', $details, $site_id );
}

/**
 * Return whether to show the admin bar badge
 *
 * @since 1.3.0
 *
 * @uses apply_filters() Calls 'portier_show_admin_bar_badge'
 * @return bool Shouw the admin bar badge?
 */
function portier_show_admin_bar_badge() {
	return (bool) apply_filters( 'portier_show_admin_bar_badge', ! is_network_admin() && current_user_can( 'manage_options' ) );
}
