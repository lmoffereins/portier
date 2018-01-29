<?php

/**
 * Portier Updater
 *
 * @package Portier
 * @subpackage Updater
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * If there is no raw DB version, this is the first installation
 *
 * @since 1.2.0
 *
 * @return bool True if update, False if not
 */
function portier_is_install() {
	return ! portier_get_db_version_raw();
}

/**
 * Compare the plugin version to the DB version to determine if updating
 *
 * @since 1.2.0
 *
 * @return bool True if update, False if not
 */
function portier_is_update() {
	$raw    = (int) portier_get_db_version_raw();
	$cur    = (int) portier_get_db_version();
	$retval = (bool) ( $raw < $cur );
	return $retval;
}

/**
 * Determine if the plugin is being activated
 *
 * Note that this function currently is not used in the plugin core and is here
 * for third party plugins to use to check for the plugin activation.
 *
 * @since 1.2.0
 *
 * @return bool True if activating the plugin, false if not
 */
function portier_is_activation( $basename = '' ) {
	global $pagenow;

	$plugin = portier();
	$action = false;

	// Bail when not in admin/plugins
	if ( ! ( is_admin() && ( 'plugins.php' === $pagenow ) ) ) {
		return false;
	}

	if ( ! empty( $_REQUEST['action'] ) && ( '-1' !== $_REQUEST['action'] ) ) {
		$action = $_REQUEST['action'];
	} elseif ( ! empty( $_REQUEST['action2'] ) && ( '-1' !== $_REQUEST['action2'] ) ) {
		$action = $_REQUEST['action2'];
	}

	// Bail when not activating
	if ( empty( $action ) || ! in_array( $action, array( 'activate', 'activate-selected' ) ) ) {
		return false;
	}

	// The plugin(s) being activated
	if ( $action === 'activate' ) {
		$plugins = isset( $_GET['plugin'] ) ? array( $_GET['plugin'] ) : array();
	} else {
		$plugins = isset( $_POST['checked'] ) ? (array) $_POST['checked'] : array();
	}

	// Set basename if empty
	if ( empty( $basename ) && ! empty( $plugin->basename ) ) {
		$basename = $plugin->basename;
	}

	// Bail when no basename
	if ( empty( $basename ) ) {
		return false;
	}

	// Is the plugin being activated?
	return in_array( $basename, $plugins );
}

/**
 * Determine if the plugin is being deactivated
 *
 * @since 1.2.0
 * 
 * @return bool True if deactivating the plugin, false if not
 */
function portier_is_deactivation( $basename = '' ) {
	global $pagenow;

	$plugin = portier();
	$action = false;

	// Bail when not in admin/plugins
	if ( ! ( is_admin() && ( 'plugins.php' === $pagenow ) ) ) {
		return false;
	}

	if ( ! empty( $_REQUEST['action'] ) && ( '-1' !== $_REQUEST['action'] ) ) {
		$action = $_REQUEST['action'];
	} elseif ( ! empty( $_REQUEST['action2'] ) && ( '-1' !== $_REQUEST['action2'] ) ) {
		$action = $_REQUEST['action2'];
	}

	// Bail when not deactivating
	if ( empty( $action ) || ! in_array( $action, array( 'deactivate', 'deactivate-selected' ) ) ) {
		return false;
	}

	// The plugin(s) being deactivated
	if ( $action === 'deactivate' ) {
		$plugins = isset( $_GET['plugin'] ) ? array( $_GET['plugin'] ) : array();
	} else {
		$plugins = isset( $_POST['checked'] ) ? (array) $_POST['checked'] : array();
	}

	// Set basename if empty
	if ( empty( $basename ) && ! empty( $plugin->basename ) ) {
		$basename = $plugin->basename;
	}

	// Bail when no basename
	if ( empty( $basename ) ) {
		return false;
	}

	// Is the plugin being deactivated?
	return in_array( $basename, $plugins );
}

/**
 * Update the DB to the latest version
 *
 * @since 1.2.0
 */
function portier_version_bump() {
	update_site_option( '_portier_db_version', portier_get_db_version() );
}

/**
 * Setup the plugin updater
 *
 * @since 1.2.0
 */
function portier_setup_updater() {

	// Bail when no update needed
	if ( ! portier_is_update() )
		return;

	// Call the automated updater
	portier_version_updater();
}

/**
 * Plugin's version updater looks at what the current database version is, and
 * runs whatever other code is needed.
 *
 * This is most-often used when the data schema changes, but should also be used
 * to correct issues with the plugin meta-data silently on software update.
 *
 * @since 1.2.0
 */
function portier_version_updater() {

	// Get the raw database version
	$raw_db_version = (int) portier_get_db_version_raw();

	/** 1.0 Branch ********************************************************/

	// 1.0, 1.1, 1.2
	if ( $raw_db_version < 120 ) {
		portier_update_to_120();
	}

	/** All done! *********************************************************/

	// Bump the version
	portier_version_bump();
}

/** Upgrade Routines ******************************************************/

/**
 * 1.2.0 update routine
 *
 * - Rename site and network settings.
 *
 * @since 1.2.0
 *
 * @global $wpdb WPDB
 */
function portier_update_to_120() {
	global $wpdb;

	// Renaming map for site options
	$options = array(
		'_guard_site_protect'  => '_portier_site_protect',
		'_guard_login_message' => '_portier_login_message',
		'_guard_allowed_users' => '_portier_allowed_users'
	);

	// Single site
	if ( ! is_multisite() ) {

		// Rename site options
		foreach ( $options as $prev => $next ) {
			$wpdb->update(
				$wpdb->options,
				array( 'option_name' => $next ), // Set data
				array( 'option_name' => $prev ), // Where
				array( '%s' ), // Set data format
				array( '%s' ) // Where format
			);
		}

	// Network 
	} else {

		// Update all active sites
		foreach ( get_sites( array( 'fields' => 'ids' ) ) as $site ) {
			switch_to_blog( $site );

			foreach ( $options as $prev => $next ) {
				$wpdb->update(
					$wpdb->options,
					array( 'option_name' => $next ), // Set data
					array( 'option_name' => $prev ), // Where
					array( '%s' ), // Set data format
					array( '%s' ) // Where format
				);
			}

			restore_current_blog();
		}

		// Renaming map for network options
		$network_options = array(
			'_guard_network_only'          => '_portier_network_only',
			'_guard_network_redirect'      => '_portier_network_redirect',
			'_guard_network_hide_my_sites' => '_portier_network_hide_my_sites',
			'_guard_network_protect'       => '_portier_network_protect',
			'_guard_network_login_message' => '_portier_network_login_message',
			'_guard_network_allowed_users' => '_portier_network_allowed_users'
		);

		// Rename network options
		foreach ( $network_options as $prev => $next ) {
			$wpdb->update(
				$wpdb->sitemeta,
				array( 'meta_key' => $next ), // Set data
				array( 'meta_key' => $prev ), // Where
				array( '%s' ), // Set data format
				array( '%s' ) // Where format
			);
		}
	}
}
