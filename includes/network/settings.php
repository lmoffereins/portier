<?php

/**
 * Portier Network Settings Functions
 *
 * @package Portier
 * @subpackage Multisite
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

		// Default access
		'_portier_network_default_access' => array(
			'label'             => esc_html__( 'Default access', 'portier' ),
			'callback'          => 'portier_network_setting_default_access',
			'section'           => 'portier-options-access',
			'page'              => 'portier_network',
			'sanitize_callback' => 'portier_network_setting_sanitize_access_level'
		),

		// Allow main site
		'_portier_network_allow_main_site' => array(
			'label'             => esc_html__( 'Main Site', 'portier' ),
			'callback'          => 'portier_network_setting_allow_main_site',
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
function portier_network_access_settings_info() { ?>

	<p><?php esc_html_e( "When you choose to enable network protection, set the default access level to use as the baseline for user access. Any other settings will increase site access rather than decrease it. For example, the 'Allowed users' setting adds selected users to the set of already allowed users. Plugins may add their own access settings here as well.", 'portier' ); ?></p>

	<p><?php esc_html_e( 'Please note that network-defined access restrictions are enforced before any site access restrictions are evaluated. This means that more strict network restrictions are favored over less strict site restrictions.', 'portier' ); ?></p>

	<?php
}

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
 * Output the allowed network level input field
 *
 * @since 1.3.0
 */
function portier_network_setting_default_access() {

	// Get the default access level
	$level = get_site_option( '_portier_network_default_access' );

	// Catch input in a variable
	ob_start(); ?>

	<select id="_portier_network_default_access" name="_portier_network_default_access" style="max-width:25em;">

		<option value="0"><?php echo portier_get_none_access_label(); ?></option>
		<?php foreach ( portier_network_default_access_levels() as $option => $label ) : ?>
			<option value="<?php echo $option; ?>" <?php selected( $option, $level ); ?>><?php echo $label; ?></option>
		<?php endforeach; ?>

	</select>

	<?php

	$select = ob_get_clean();

	?>

	<label for="_portier_network_default_access"><?php printf( esc_html__( 'By default, access to sites in the network should be allowed for %s', 'portier' ), $select ); ?></label>

	<?php
}

/**
 * Output the allow main site input field
 *
 * @since 1.3.0
 */
function portier_network_setting_allow_main_site() { ?>

	<input type="checkbox" id="_portier_network_allow_main_site" name="_portier_network_allow_main_site" value="1" <?php checked( get_site_option( '_portier_network_allow_main_site' ) ); ?>/>
	<label for="_portier_network_allow_main_site"><?php esc_html_e( 'When protecting the network, do not apply protection to the main site', 'portier' ); ?></label>

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

/**
 * Sanitize the default network access input field
 *
 * @since 1.3.0
 *
 * @param string $input The submitted value
 * @return string Sanitized input
 */
function portier_network_setting_sanitize_access_level( $input ) {

	// Get available access levels
	$levels = portier_network_default_access_levels();

	// Default to 0
	if ( ! in_array( $input, array_keys( $levels ), true ) ) {
		$input = 0;
	}

	return $input;
}
