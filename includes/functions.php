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

/** Protection ****************************************************************/

/**
 * Return whether the given site's protection is active
 *
 * @since 1.0.0
 *
 * @uses apply_filters() Calls 'portier_is_site_protected'
 * 
 * @param int $site_id Optional. Site ID. Defaults to the current site ID
 * @return bool Site protection is active
 */
function portier_is_site_protected( $site_id = 0 ) {

	// Network: switch to site
	if ( ! empty( $site_id ) && is_multisite() ) {
		$site_id = (int) $site_id;
		switch_to_blog( $site_id );
	}

	$protected = get_option( '_portier_site_protect' );

	// Network: reset the switched site
	if ( ! empty( $site_id ) && is_multisite() ) {
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
 * @return boolean The user is allowed
 */
function portier_is_user_allowed( $user_id = 0, $site_id = 0 ) {

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
	$users = (array) get_option( '_portier_allowed_users', array() );

	// Network: reset the switched site
	if ( ! empty( $site_id ) && is_multisite() ) {
		restore_current_blog();
	}

	// Is user selected to be allowed?
	$allowed = in_array( $user_id, $users );

	// Filter whether user is allowed
	return (bool) apply_filters( 'portier_is_user_allowed', $allowed, $user_id, $site_id );
}

/**
 * Return basic site protection details
 * 
 * @since 1.0.0
 *
 * @uses apply_filters() Calls 'portier_get_protection_details'
 * 
 * @return string Protection details
 */
function portier_get_protection_details() {

	// Setup basic protection details: allowed user count
	$allowed_user_count = count( get_option( '_portier_allowed_users' ) );
	$details = sprintf( _n( '%d allowed user', '%d allowed users', $allowed_user_count, 'portier' ), $allowed_user_count );

	return apply_filters( 'portier_get_protection_details', $details );
}
