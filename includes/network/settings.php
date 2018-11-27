<?php

/**
 * Portier Network Settings Functions
 *
 * @package Portier
 * @subpackage Network
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Return the plugin network settings
 *
 * @since 1.0.0
 *
 * @uses apply_filters() Calls 'portier_network_settings'
 *
 * @return array Settings
 */
function portier_network_settings() {
	return apply_filters( 'portier_network_settings', array(

		/** Main Settings ************************************************/

		// Network only
		'_portier_network_only' => array(
			'label'             => esc_html__( 'Network only', 'portier' ),
			'callback'          => 'portier_network_setting_network_only',
			'section'           => 'portier-options-main',
			'page'              => 'portier_network',
			'sanitize_callback' => 'intval'
		),

		// Network redirect
		'_portier_network_redirect' => array(
			'label'             => esc_html__( 'Redirect to allowed site', 'portier' ),
			'callback'          => 'portier_network_setting_network_redirect',
			'section'           => 'portier-options-main',
			'page'              => 'portier_network',
			'sanitize_callback' => 'intval'
		),

		// Hide "My Sites"
		'_portier_network_hide_my_sites' => array(
			'label'             => esc_html__( 'Hide My Sites', 'portier' ),
			'callback'          => 'portier_network_setting_hide_my_sites',
			'section'           => 'portier-options-main',
			'page'              => 'portier_network',
			'sanitize_callback' => 'intval'
		),

		/** Access Settings **********************************************/

		// Network protect switch
		'_portier_network_protect' => array(
			'label'             => esc_html__( 'Protect this network', 'portier' ),
			'callback'          => 'portier_network_setting_network_protect',
			'section'           => 'portier-options-access',
			'page'              => 'portier_network',
			'sanitize_callback' => 'intval'
		),

		// Login message
		'_portier_network_login_message' => array(
			'label'             => esc_html__( 'Login message', 'portier' ),
			'callback'          => 'portier_network_setting_login_message',
			'section'           => 'portier-options-access',
			'page'              => 'portier_network',
			'sanitize_callback' => 'portier_setting_sanitize_message'
		),

		// Allowed users
		'_portier_network_allowed_users' => array(
			'label'             => esc_html__( 'Allowed users', 'portier' ),
			'callback'          => 'portier_network_setting_allowed_users',
			'section'           => 'portier-options-access',
			'page'              => 'portier_network',
			'sanitize_callback' => 'portier_setting_sanitize_ids'
		),

	) );
}

/**
 * Output network main settings section information header
 *
 * @since 1.0.0
 */
function portier_network_main_settings_info() { /* Nothing to show */ }

/**
 * Output network access settings section information header
 *
 * @since 1.0.0
 */
function portier_network_access_settings_info() { /* Nothing to show */ }

/**
 * Output the network only input field
 *
 * @since 1.0.0
 */
function portier_network_setting_network_only() { ?>

	<input type="checkbox" id="_portier_network_only" name="_portier_network_only" value="1" <?php checked( portier_is_network_only() ); ?>/>
	<label for="_portier_network_only"><?php esc_html_e( 'Disable this plugin for individual sites', 'portier' ); ?></label>

	<?php
}

/**
 * Output the enable network protection input field
 *
 * @since 1.0.0
 */
function portier_network_setting_network_protect() { ?>

	<input type="checkbox" id="_portier_network_protect" name="_portier_network_protect" value="1" <?php checked( portier_is_network_protected() ); ?>/>
	<label for="_portier_network_protect"><?php esc_html_e( 'Enable network protection', 'portier' ); ?></label>

	<?php
}

/**
 * Output the redirect to main site input field
 *
 * @since 1.0.0
 */
function portier_network_setting_network_redirect() { ?>

	<input type="checkbox" id="_portier_network_redirect" name="_portier_network_redirect" value="1" <?php checked( portier_network_redirect() ); ?>/>
	<label for="_portier_network_redirect"><?php esc_html_e( 'Instead of just logging the user out, try to redirect the user from protected sites to an allowed site or the network home, when available', 'portier' ); ?></label>

	<?php
}

/**
 * Output the hide my sites input field
 *
 * @since 1.0.0
 */
function portier_network_setting_hide_my_sites() { ?>

	<input type="checkbox" id="_portier_network_hide_my_sites" name="_portier_network_hide_my_sites" value="1" <?php checked( get_site_option( '_portier_network_hide_my_sites' ) ); ?>/>
	<label for="_portier_network_hide_my_sites"><?php esc_html_e( 'Hide the My Sites links and page when a user has access to only one site', 'portier' ); ?></label>

	<?php
}

/**
 * Output the allowed network users input field
 *
 * @since 1.0.0
 */
function portier_network_setting_allowed_users() {

	// Get the allowed network users
	$allowed = (array) get_site_option( '_portier_network_allowed_users', array() ); ?>

	<select id="_portier_network_allowed_users" class="chzn-select" name="_portier_network_allowed_users[]" multiple style="width:25em;" data-placeholder="<?php esc_html_e( 'Select a user', 'portier' ); ?>">

		<?php foreach ( portier_get_network_users() as $user ) : ?>
			<option value="<?php echo $user->ID; ?>" <?php selected( in_array( $user->ID, $allowed ) ); ?>><?php echo $user->user_login; ?></option>
		<?php endforeach; ?>

	</select>
	<label for="_portier_network_allowed_users"><?php esc_html_e( 'Select which users will have access', 'portier' ); ?></label>

	<?php
}

/**
 * Output the custom network message input field
 *
 * @since 1.0.0
 */
function portier_network_setting_login_message() { ?>

	<textarea name="_portier_network_login_message" id="_portier_network_login_message" style="width:25em;" rows="3"><?php echo esc_textarea( get_site_option( '_portier_network_login_message' ) ); ?></textarea>
	<label for="_portier_network_login_message">
		<?php esc_html_e( 'When network protection is active, this message will be shown at the login screen.', 'portier' ); ?>
		<?php printf( esc_html__( 'Allowed HTML tags are: %s, %s and %s.', 'portier' ), '<code>&#60;a&#62;</code>', '<code>&#60;em&#62;</code>', '<code>&#60;strong&#62;</code>' ); ?>
	</label>

	<?php
}
