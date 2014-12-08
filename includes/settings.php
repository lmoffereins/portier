<?php

/**
 * Guard Settings Functions
 *
 * @package Guard
 * @subpackage Settings
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** Single ***************************************************************/

/**
 * Return the plugin settings
 *
 * @since 1.0.0
 *
 * @uses apply_filters() Calls 'guard_settings'
 * @return array Settings
 */
function guard_settings() {
	return apply_filters( 'guard_settings', array(

		/** Access Settings **********************************************/

		// Site protect switch
		'_guard_site_protect' => array(
			'label'             => __( 'Protect my site', 'guard' ),
			'callback'          => 'guard_setting_protect_site',
			'section'           => 'guard-options-access',
			'page'              => 'guard',
			'sanitize_callback' => 'intval'
		),

		// Login message
		'_guard_login_message' => array(
			'label'             => __( 'Login message', 'guard' ),
			'callback'          => 'guard_setting_login_message',
			'section'           => 'guard-options-access',
			'page'              => 'guard',
			'sanitize_callback' => 'guard_setting_sanitize_message'
		),

		// Allowed users
		'_guard_allowed_users' => array(
			'label'             => __( 'Allowed users', 'guard' ),
			'callback'          => 'guard_setting_allow_users',
			'section'           => 'guard-options-access',
			'page'              => 'guard',
			'sanitize_callback' => 'guard_setting_sanitize_ids'
		),
	) );
}

/**
 * Output access settings section information header
 *
 * @since 1.0.0
 */
function guard_access_settings_info() { /* Nothing to show */ }

/**
 * Output additional settings section information header
 *
 * @since 1.0.0
 */
function guard_additional_settings_info() { /* Nothing to show */ }

/**
 * Output the enable site protection input field
 *
 * @since 1.0.0
 *
 * @uses guard_is_site_protected()
 */
function guard_setting_protect_site() { ?>

	<input type="checkbox" id="_guard_site_protect" name="_guard_site_protect" value="1" <?php checked( guard_is_site_protected() ); ?>/>
	<label for="_guard_site_protect"><?php _e( 'Enable site protection', 'guard' ); ?></label>

	<?php
}

/**
 * Output the allowed users input field
 *
 * @since 1.0.0
 *
 * @uses get_option()
 * @uses get_users() To get all users of the site
 */
function guard_setting_allow_users() {

	// Get the allowed users
	$allowed = (array) get_option( '_guard_allowed_users', array() );	?>

	<select id="_guard_allowed_users" class="chzn-select" name="_guard_allowed_users[]" multiple style="width:25em;" data-placeholder="<?php _e( 'Select a user', 'guard' ); ?>">

		<?php foreach ( get_users() as $user ) : ?>
			<option value="<?php echo $user->ID; ?>" <?php selected( in_array( $user->ID, $allowed ) ); ?>><?php echo $user->user_login; ?></option>
		<?php endforeach; ?>

	</select>
	<label for="_guard_allowed_groups"><?php _e( 'Select which users you want to have access', 'guard' ); ?></label>

	<?php
}

/**
 * Output the custom message input field
 *
 * @since 1.0.0
 *
 * @uses esc_textarea()
 * @uses get_option()
 */
function guard_setting_login_message() { ?>

	<textarea name="_guard_login_message" id="_guard_login_message" style="width:25em;" rows="3"><?php echo esc_textarea( get_option( '_guard_login_message' ) ); ?></textarea>
	<label for="_guard_login_message">
		<?php _e( 'When site protection is active, this message will be shown at the login screen.', 'guard' ); ?>
		<?php printf( __( 'Allowed HTML tags are: %s, %s and %s.', 'guard' ), '<code>&#60;a&#62;</code>', '<code>&#60;em&#62;</code>', '<code>&#60;strong&#62;</code>' ); ?>
	</label>

	<?php
}

/**
 * Sanitize an input field's array of ids
 *
 * @since 1.0.0
 *
 * @param string $input The submitted value
 * @return array $input
 */
function guard_setting_sanitize_ids( $input ) {
	if ( ! empty( $input ) ) {
		$input = array_unique( array_map( 'absint', (array) $input ) );
	} else {
		$input = array();
	}

	return $input;
}

/**
 * Sanitize the custom message input field
 *
 * @since 1.0.0
 *
 * @uses wp_unslash()
 * @uses wp_kses() To filter out all non allowed HTML tags
 *
 * @param string $input The submitted value
 * @return string $input
 */
function guard_setting_sanitize_message( $input ) {
	return wp_unslash( wp_kses( $input, array(
		'a'      => array( 'href' ),
		'em'     => array(),
		'strong' => array()
	) ) );
}

/** Multisite ************************************************************/

/**
 * Return the plugin network settings
 *
 * @since 1.0.0
 *
 * @uses apply_filters() Calls 'guard_network_settings'
 * @return array Settings
 */
function guard_network_settings() {
	return apply_filters( 'guard_network_settings', array(

		/** Main Settings ************************************************/

		// Network only
		'_guard_network_only' => array(
			'label'             => __( 'Network only', 'guard' ),
			'callback'          => 'guard_network_setting_network_only',
			'section'           => 'guard-options-main',
			'page'              => 'guard_network',
			'sanitize_callback' => 'intval'
		),

		// Network redirect
		'_guard_network_redirect' => array(
			'label'             => __( 'Redirect to allowed site', 'guard' ),
			'callback'          => 'guard_network_setting_network_redirect',
			'section'           => 'guard-options-main',
			'page'              => 'guard_network',
			'sanitize_callback' => 'intval'
		),

		// Hide "My Sites"
		'_guard_network_hide_my_sites' => array(
			'label'             => sprintf( _x( 'Hide %s', 'Setting label for hide-my-sites option', 'guard' ), '"' . __( 'My Sites' ) . '"' ),
			'callback'          => 'guard_network_setting_hide_my_sites',
			'section'           => 'guard-options-main',
			'page'              => 'guard_network',
			'sanitize_callback' => 'intval'
		),

		/** Access Settings **********************************************/

		// Network protect switch
		'_guard_network_protect' => array(
			'label'             => __( 'Protect this network', 'guard' ),
			'callback'          => 'guard_network_setting_network_protect',
			'section'           => 'guard-options-access',
			'page'              => 'guard_network',
			'sanitize_callback' => 'intval'
		),

		// Login message
		'_guard_network_login_message' => array(
			'label'             => __( 'Login message', 'guard' ),
			'callback'          => 'guard_network_setting_login_message',
			'section'           => 'guard-options-access',
			'page'              => 'guard_network',
			'sanitize_callback' => 'guard_setting_sanitize_message'
		),

		// Allowed users
		'_guard_network_allowed_users' => array(
			'label'             => __( 'Allowed users', 'guard' ),
			'callback'          => 'guard_network_setting_allowed_users',
			'section'           => 'guard-options-access',
			'page'              => 'guard_network',
			'sanitize_callback' => 'guard_setting_sanitize_ids'
		),

	) );
}

/**
 * Output network main settings section information header
 *
 * @since 1.0.0
 */
function guard_network_main_settings_info() { /* Nothing to show */ }

/**
 * Output network access settings section information header
 *
 * @since 1.0.0
 */
function guard_network_access_settings_info() { /* Nothing to show */ }

/**
 * Output network additional settings section information header
 *
 * @since 1.0.0
 */
function guard_network_additional_settings_info() { /* Nothing to show */ }

/**
 * Output the network only input field
 *
 * @since 1.0.0
 *
 * @uses guard_is_network_only()
 */
function guard_network_setting_network_only() { ?>

	<input type="checkbox" id="_guard_network_only" name="_guard_network_only" value="1" <?php checked( guard_is_network_only() ); ?>/>
	<label for="_guard_network_only"><?php _e( 'Disable this plugin for individual sites', 'guard' ); ?></label>

	<?php
}

/**
 * Output the enable network protection input field
 *
 * @since 1.0.0
 *
 * @uses guard_is_network_protected()
 */
function guard_network_setting_network_protect() { ?>

	<input type="checkbox" id="_guard_network_protect" name="_guard_network_protect" value="1" <?php checked( guard_is_network_protected() ); ?>/>
	<label for="_guard_network_protect"><?php _e( 'Enable network protection', 'guard' ); ?></label>

	<?php
}

/**
 * Output the redirect to main site input field
 *
 * @since 1.0.0
 *
 * @uses guard_network_redirect()
 */
function guard_network_setting_network_redirect() { ?>

	<input type="checkbox" id="_guard_network_redirect" name="_guard_network_redirect" value="1" <?php checked( guard_network_redirect() ); ?>/>
	<label for="_guard_network_redirect"><?php _e( 'Instead of just logging the user out, try to redirect the user from protected sites to an allowed site or the network home, when available', 'guard' ); ?></label>

	<?php
}

/**
 * Output the hide my sites input field
 *
 * @since 1.0.0
 *
 * @uses get_site_option() To get the field's value
 */
function guard_network_setting_hide_my_sites() { ?>

	<input type="checkbox" id="_guard_network_hide_my_sites" name="_guard_network_hide_my_sites" value="1" <?php checked( get_site_option( '_guard_network_hide_my_sites' ) ); ?>/>
	<label for="_guard_network_hide_my_sites"><?php printf( __( 'Hide the %s links and page when a user has access to only one site', 'guard' ), '"' . __( 'My Sites' ) . '"' ); ?></label>

	<?php
}

/**
 * Output the allowed network users input field
 *
 * @since 1.0.0
 *
 * @uses get_site_option() To get the field's value
 * @uses guard_get_network_users() To get all users of the network
 */
function guard_network_setting_allowed_users() {

	// Get the allowed network users
	$allowed = (array) get_site_option( '_guard_network_allowed_users', array() ); ?>

	<select id="_guard_network_allowed_users" class="chzn-select" name="_guard_network_allowed_users[]" multiple style="width:25em;" data-placeholder="<?php _e( 'Select a user', 'guard' ); ?>">

		<?php foreach ( guard_get_network_users() as $user ) : ?>
			<option value="<?php echo $user->ID; ?>" <?php selected( in_array( $user->ID, $allowed ) ); ?>><?php echo $user->user_login; ?></option>
		<?php endforeach; ?>

	</select>
	<label for="_guard_network_allowed_users"><?php _e( 'Select which users you want to have access', 'guard' ); ?></label>

	<?php
}

/**
 * Output the custom network message input field
 *
 * @since 1.0.0
 *
 * @uses get_site_option() To get the field's value
 */
function guard_network_setting_login_message() { ?>

	<textarea name="_guard_network_login_message" id="_guard_network_login_message" style="width:25em;" rows="3"><?php echo esc_textarea( get_site_option( '_guard_network_login_message' ) ); ?></textarea>
	<label for="_guard_network_login_message">
		<?php _e( 'When network protection is active, this message will be shown at the login screen.', 'guard' ); ?>
		<?php printf( __( 'Allowed HTML tags are: %s, %s and %s.', 'guard' ), '<code>&#60;a&#62;</code>', '<code>&#60;em&#62;</code>', '<code>&#60;strong&#62;</code>' ); ?>
	</label>

	<?php
}
