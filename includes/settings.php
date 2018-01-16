<?php

/**
 * Deurwachter Settings Functions
 *
 * @package Deurwachter
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
 * @uses apply_filters() Calls 'deurwachter_settings'
 *
 * @return array Settings
 */
function deurwachter_settings() {
	return apply_filters( 'deurwachter_settings', array(

		/** Access Settings **********************************************/

		// Site protect switch
		'_deurwachter_site_protect' => array(
			'label'             => esc_html__( 'Protect my site', 'deurwachter' ),
			'callback'          => 'deurwachter_setting_protect_site',
			'section'           => 'deurwachter-options-access',
			'page'              => 'deurwachter',
			'sanitize_callback' => 'intval'
		),

		// Login message
		'_deurwachter_login_message' => array(
			'label'             => esc_html__( 'Login message', 'deurwachter' ),
			'callback'          => 'deurwachter_setting_login_message',
			'section'           => 'deurwachter-options-access',
			'page'              => 'deurwachter',
			'sanitize_callback' => 'deurwachter_setting_sanitize_message'
		),

		// Allowed users
		'_deurwachter_allowed_users' => array(
			'label'             => esc_html__( 'Allowed users', 'deurwachter' ),
			'callback'          => 'deurwachter_setting_allow_users',
			'section'           => 'deurwachter-options-access',
			'page'              => 'deurwachter',
			'sanitize_callback' => 'deurwachter_setting_sanitize_ids'
		),
	) );
}

/**
 * Output access settings section information header
 *
 * @since 1.0.0
 */
function deurwachter_access_settings_info() { /* Nothing to show */ }

/**
 * Output additional settings section information header
 *
 * @since 1.0.0
 */
function deurwachter_additional_settings_info() { /* Nothing to show */ }

/**
 * Output the enable site protection input field
 *
 * @since 1.0.0
 */
function deurwachter_setting_protect_site() { ?>

	<input type="checkbox" id="_deurwachter_site_protect" name="_deurwachter_site_protect" value="1" <?php checked( deurwachter_is_site_protected() ); ?>/>
	<label for="_deurwachter_site_protect"><?php esc_html_e( 'Enable site protection', 'deurwachter' ); ?></label>

	<?php
}

/**
 * Output the allowed users input field
 *
 * @since 1.0.0
 */
function deurwachter_setting_allow_users() {

	// Get the allowed users
	$allowed = (array) get_option( '_deurwachter_allowed_users', array() );	?>

	<select id="_deurwachter_allowed_users" class="chzn-select" name="_deurwachter_allowed_users[]" multiple style="width:25em;" data-placeholder="<?php esc_html_e( 'Select a user', 'deurwachter' ); ?>">

		<?php foreach ( get_users() as $user ) : ?>
			<option value="<?php echo $user->ID; ?>" <?php selected( in_array( $user->ID, $allowed ) ); ?>><?php echo $user->user_login; ?></option>
		<?php endforeach; ?>

	</select>
	<label for="_deurwachter_allowed_groups"><?php esc_html_e( 'Select which users will have access', 'deurwachter' ); ?></label>

	<?php
}

/**
 * Output the custom message input field
 *
 * @since 1.0.0
 */
function deurwachter_setting_login_message() { ?>

	<textarea name="_deurwachter_login_message" id="_deurwachter_login_message" style="width:25em;" rows="3"><?php echo esc_textarea( get_option( '_deurwachter_login_message' ) ); ?></textarea>
	<label for="_deurwachter_login_message">
		<?php esc_html_e( 'When site protection is active, this message will be shown at the login screen.', 'deurwachter' ); ?>
		<?php printf( esc_html__( 'Allowed HTML tags are: %s, %s and %s.', 'deurwachter' ), '<code>&#60;a&#62;</code>', '<code>&#60;em&#62;</code>', '<code>&#60;strong&#62;</code>' ); ?>
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
function deurwachter_setting_sanitize_ids( $input ) {
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
 * @param string $input The submitted value
 * @return string $input
 */
function deurwachter_setting_sanitize_message( $input ) {
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
 * @uses apply_filters() Calls 'deurwachter_network_settings'
 *
 * @return array Settings
 */
function deurwachter_network_settings() {
	return apply_filters( 'deurwachter_network_settings', array(

		/** Main Settings ************************************************/

		// Network only
		'_deurwachter_network_only' => array(
			'label'             => esc_html__( 'Network only', 'deurwachter' ),
			'callback'          => 'deurwachter_network_setting_network_only',
			'section'           => 'deurwachter-options-main',
			'page'              => 'deurwachter_network',
			'sanitize_callback' => 'intval'
		),

		// Network redirect
		'_deurwachter_network_redirect' => array(
			'label'             => esc_html__( 'Redirect to allowed site', 'deurwachter' ),
			'callback'          => 'deurwachter_network_setting_network_redirect',
			'section'           => 'deurwachter-options-main',
			'page'              => 'deurwachter_network',
			'sanitize_callback' => 'intval'
		),

		// Hide "My Sites"
		'_deurwachter_network_hide_my_sites' => array(
			'label'             => esc_html__( 'Hide My Sites', 'deurwachter' ),
			'callback'          => 'deurwachter_network_setting_hide_my_sites',
			'section'           => 'deurwachter-options-main',
			'page'              => 'deurwachter_network',
			'sanitize_callback' => 'intval'
		),

		/** Access Settings **********************************************/

		// Network protect switch
		'_deurwachter_network_protect' => array(
			'label'             => esc_html__( 'Protect this network', 'deurwachter' ),
			'callback'          => 'deurwachter_network_setting_network_protect',
			'section'           => 'deurwachter-options-access',
			'page'              => 'deurwachter_network',
			'sanitize_callback' => 'intval'
		),

		// Login message
		'_deurwachter_network_login_message' => array(
			'label'             => esc_html__( 'Login message', 'deurwachter' ),
			'callback'          => 'deurwachter_network_setting_login_message',
			'section'           => 'deurwachter-options-access',
			'page'              => 'deurwachter_network',
			'sanitize_callback' => 'deurwachter_setting_sanitize_message'
		),

		// Allowed users
		'_deurwachter_network_allowed_users' => array(
			'label'             => esc_html__( 'Allowed users', 'deurwachter' ),
			'callback'          => 'deurwachter_network_setting_allowed_users',
			'section'           => 'deurwachter-options-access',
			'page'              => 'deurwachter_network',
			'sanitize_callback' => 'deurwachter_setting_sanitize_ids'
		),

	) );
}

/**
 * Output network main settings section information header
 *
 * @since 1.0.0
 */
function deurwachter_network_main_settings_info() { /* Nothing to show */ }

/**
 * Output network access settings section information header
 *
 * @since 1.0.0
 */
function deurwachter_network_access_settings_info() { /* Nothing to show */ }

/**
 * Output network additional settings section information header
 *
 * @since 1.0.0
 */
function deurwachter_network_additional_settings_info() { /* Nothing to show */ }

/**
 * Output the network only input field
 *
 * @since 1.0.0
 */
function deurwachter_network_setting_network_only() { ?>

	<input type="checkbox" id="_deurwachter_network_only" name="_deurwachter_network_only" value="1" <?php checked( deurwachter_is_network_only() ); ?>/>
	<label for="_deurwachter_network_only"><?php esc_html_e( 'Disable this plugin for individual sites', 'deurwachter' ); ?></label>

	<?php
}

/**
 * Output the enable network protection input field
 *
 * @since 1.0.0
 */
function deurwachter_network_setting_network_protect() { ?>

	<input type="checkbox" id="_deurwachter_network_protect" name="_deurwachter_network_protect" value="1" <?php checked( deurwachter_is_network_protected() ); ?>/>
	<label for="_deurwachter_network_protect"><?php esc_html_e( 'Enable network protection', 'deurwachter' ); ?></label>

	<?php
}

/**
 * Output the redirect to main site input field
 *
 * @since 1.0.0
 */
function deurwachter_network_setting_network_redirect() { ?>

	<input type="checkbox" id="_deurwachter_network_redirect" name="_deurwachter_network_redirect" value="1" <?php checked( deurwachter_network_redirect() ); ?>/>
	<label for="_deurwachter_network_redirect"><?php esc_html_e( 'Instead of just logging the user out, try to redirect the user from protected sites to an allowed site or the network home, when available', 'deurwachter' ); ?></label>

	<?php
}

/**
 * Output the hide my sites input field
 *
 * @since 1.0.0
 */
function deurwachter_network_setting_hide_my_sites() { ?>

	<input type="checkbox" id="_deurwachter_network_hide_my_sites" name="_deurwachter_network_hide_my_sites" value="1" <?php checked( get_site_option( '_deurwachter_network_hide_my_sites' ) ); ?>/>
	<label for="_deurwachter_network_hide_my_sites"><?php esc_html_e( 'Hide the My Sites links and page when a user has access to only one site', 'deurwachter' ); ?></label>

	<?php
}

/**
 * Output the allowed network users input field
 *
 * @since 1.0.0
 */
function deurwachter_network_setting_allowed_users() {

	// Get the allowed network users
	$allowed = (array) get_site_option( '_deurwachter_network_allowed_users', array() ); ?>

	<select id="_deurwachter_network_allowed_users" class="chzn-select" name="_deurwachter_network_allowed_users[]" multiple style="width:25em;" data-placeholder="<?php esc_html_e( 'Select a user', 'deurwachter' ); ?>">

		<?php foreach ( deurwachter_get_network_users() as $user ) : ?>
			<option value="<?php echo $user->ID; ?>" <?php selected( in_array( $user->ID, $allowed ) ); ?>><?php echo $user->user_login; ?></option>
		<?php endforeach; ?>

	</select>
	<label for="_deurwachter_network_allowed_users"><?php esc_html_e( 'Select which users will have access', 'deurwachter' ); ?></label>

	<?php
}

/**
 * Output the custom network message input field
 *
 * @since 1.0.0
 */
function deurwachter_network_setting_login_message() { ?>

	<textarea name="_deurwachter_network_login_message" id="_deurwachter_network_login_message" style="width:25em;" rows="3"><?php echo esc_textarea( get_site_option( '_deurwachter_network_login_message' ) ); ?></textarea>
	<label for="_deurwachter_network_login_message">
		<?php esc_html_e( 'When network protection is active, this message will be shown at the login screen.', 'deurwachter' ); ?>
		<?php printf( esc_html__( 'Allowed HTML tags are: %s, %s and %s.', 'deurwachter' ), '<code>&#60;a&#62;</code>', '<code>&#60;em&#62;</code>', '<code>&#60;strong&#62;</code>' ); ?>
	</label>

	<?php
}
