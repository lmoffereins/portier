<?php

/**
 * Portier Settings Functions
 *
 * @package Portier
 * @subpackage Settings
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Return the plugin settings
 *
 * @since 1.0.0
 *
 * @uses apply_filters() Calls 'portier_settings'
 *
 * @return array Settings
 */
function portier_settings() {
	return apply_filters( 'portier_settings', array(

		/** Access Settings **********************************************/

		// Site protect switch
		'_portier_site_protect' => array(
			'label'             => esc_html__( 'Protect my site', 'portier' ),
			'callback'          => 'portier_setting_protect_site',
			'section'           => 'portier-options-access',
			'page'              => 'portier',
			'sanitize_callback' => 'intval'
		),

		// Default access
		'_portier_default_access' => array(
			'label'             => esc_html__( 'Default access', 'portier' ),
			'callback'          => 'portier_setting_default_access',
			'section'           => 'portier-options-access',
			'page'              => 'portier',
			'sanitize_callback' => 'portier_setting_sanitize_access_level'
		),

		// Login message
		'_portier_login_message' => array(
			'label'             => esc_html__( 'Login message', 'portier' ),
			'callback'          => 'portier_setting_login_message',
			'section'           => 'portier-options-access',
			'page'              => 'portier',
			'sanitize_callback' => 'portier_setting_sanitize_message'
		),

		// Allowed users
		'_portier_allowed_users' => array(
			'label'             => esc_html__( 'Allowed users', 'portier' ),
			'callback'          => 'portier_setting_allowed_users',
			'section'           => 'portier-options-access',
			'page'              => 'portier',
			'sanitize_callback' => 'portier_setting_sanitize_ids'
		),
	) );
}

/**
 * Output access settings section information header
 *
 * @since 1.0.0
 */
function portier_access_settings_info() { ?>

	<p><?php esc_html_e( "When you choose to enable site protection, set the default access level to use as the baseline for user access. Any other settings will increase site access rather than decrease it. For example, the 'Allowed users' setting adds selected users to the set of already allowed users. Plugins may add their own access settings here as well.", 'portier' ); ?></p>

	<?php
}

/**
 * Output the enable site protection input field
 *
 * @since 1.0.0
 */
function portier_setting_protect_site() { ?>

	<input type="checkbox" id="_portier_site_protect" name="_portier_site_protect" value="1" <?php checked( get_option( '_portier_site_protect' ) ); ?>/>
	<label for="_portier_site_protect"><?php esc_html_e( 'Enable site protection', 'portier' ); ?></label>

	<?php
}

/**
 * Output the allowed level input field
 *
 * @since 1.3.0
 */
function portier_setting_default_access() {

	// Get the default access level
	$level = get_option( '_portier_default_access' );

	// Catch input in a variable
	ob_start(); ?>

	<select id="_portier_default_access" name="_portier_default_access" style="max-width:25em;">

		<option value="0"><?php echo portier_get_none_access_label(); ?></option>
		<?php foreach ( portier_default_access_levels() as $option => $label ) : ?>
			<option value="<?php echo $option; ?>" <?php selected( $option, $level ); ?>><?php echo $label; ?></option>
		<?php endforeach; ?>

	</select>

	<?php

	$select = ob_get_clean();

	?>

	<label for="_portier_default_access"><?php printf( esc_html__( 'By default, access to this site should be allowed for %s', 'portier' ), $select ); ?></label>

	<?php
}

/**
 * Output the allowed users input field
 *
 * @since 1.0.0
 */
function portier_setting_allowed_users() {

	// Get the allowed users
	$allowed = (array) get_option( '_portier_allowed_users', array() ); ?>

	<select id="_portier_allowed_users" class="chzn-select" name="_portier_allowed_users[]" multiple style="width:25em;" data-placeholder="<?php esc_html_e( 'Select a user', 'portier' ); ?>">

		<?php foreach ( get_users() as $user ) : ?>
			<option value="<?php echo $user->ID; ?>" <?php selected( in_array( $user->ID, $allowed ) ); ?>><?php echo $user->user_login; ?></option>
		<?php endforeach; ?>

	</select>
	<label for="_portier_allowed_users"><?php esc_html_e( 'Select which users will have access', 'portier' ); ?></label>

	<?php
}

/**
 * Output the custom message input field
 *
 * @since 1.0.0
 */
function portier_setting_login_message() { ?>

	<textarea name="_portier_login_message" id="_portier_login_message" style="width:25em;" rows="3"><?php echo esc_textarea( get_option( '_portier_login_message' ) ); ?></textarea>
	<label for="_portier_login_message">
		<?php esc_html_e( 'When site protection is active, this message will be shown at the login screen.', 'portier' ); ?>
		<?php printf( esc_html__( 'Allowed HTML tags are: %s, %s and %s.', 'portier' ), '<code>&#60;a&#62;</code>', '<code>&#60;em&#62;</code>', '<code>&#60;strong&#62;</code>' ); ?>
	</label>

	<?php
}

/**
 * Sanitize an input field's array of ids
 *
 * @since 1.0.0
 *
 * @param string $input The submitted value
 * @return string Sanitized input
 */
function portier_setting_sanitize_ids( $input ) {
	if ( ! empty( $input ) ) {
		$input = array_unique( array_map( 'absint', (array) $input ) );
	} else {
		$input = array();
	}

	return $input;
}

/**
 * Sanitize the default access input field
 *
 * @since 1.3.0
 *
 * @param string $input The submitted value
 * @return string Sanitized input
 */
function portier_setting_sanitize_access_level( $input ) {

	// Get available access levels
	$levels = portier_default_access_levels();

	// Default to 0
	if ( ! in_array( $input, array_keys( $levels ), true ) ) {
		$input = 0;
	}

	return $input;
}

/**
 * Sanitize the custom message input field
 *
 * @since 1.0.0
 *
 * @param string $input The submitted value
 * @return string Sanitized input
 */
function portier_setting_sanitize_message( $input ) {
	return wp_unslash( wp_kses( $input, array(
		'a'      => array( 'href' ),
		'em'     => array(),
		'strong' => array()
	) ) );
}
