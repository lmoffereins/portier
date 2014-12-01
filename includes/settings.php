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
 * @since 0.x
 *
 * @uses apply_filters() Calls 'guard_settings' hook on the settings
 *
 * @return array $settings {
 *  @type array Setting ID {
 *   @type string $label Setting label
 *   @type string $field_cb Setting input field callback
 *   @type string $section Setting section name
 *   @type string $page Setting page name
 *   @type string $sanitize_cb Setting sanitization callback
 *  }
 * }
 */
function guard_settings() {
	return apply_filters( 'guard_settings', array(

		/** Access Settings **********************************************/

		// Site protect switch
		'_guard_site_protect' => array(
			'label'       => __( 'Protect my site', 'guard' ),
			'field_cb'    => 'guard_setting_protect_site',
			'section'     => 'guard-options-access',
			'page'        => 'guard',
			'sanitize_cb' => 'intval'
		),

		// Allowed users
		'_guard_allowed_users' => array(
			'label'       => __( 'Allowed users', 'guard' ),
			'field_cb'    => 'guard_setting_allow_users',
			'section'     => 'guard-options-access',
			'page'        => 'guard',
			'sanitize_cb' => 'guard_setting_allow_users_sanitize'
		),

		/** Additional Settings ******************************************/

		// Custom login message
		'_guard_custom_message' => array(
			'label'       => __( 'Custom login message', 'guard' ),
			'field_cb'    => 'guard_setting_custom_message',
			'section'     => 'guard-options-additional',
			'page'        => 'guard',
			'sanitize_cb' => 'guard_setting_custom_message_sanitize'
		)
	) );
}

/**
 * Output access settings section information header
 *
 * @since 0.1
 */
function guard_access_settings_info() {
	?>

		<p><?php _e( 'Here you enable the Guard plugin. By checking the <em>Protect my site</em> input, this site will only be accessible for admins and allowed users, specified by you in the select option below. No one else shall pass!', 'guard' ); ?></p>

	<?php
}

/**
 * Output additional settings section information header
 *
 * @since 0.1
 */
function guard_additional_settings_info() {
	?>

		<p><?php _e( 'Below you can set additional Guard options.', 'guard' ); ?></p>

	<?php
}

/**
 * Output the enable site protection input field
 *
 * @since 0.1
 */
function guard_setting_protect_site() {
	?>

		<p>
			<label>
				<input type="checkbox" name="_guard_site_protect" value="1" <?php checked( guard_is_site_protected() ); ?>/>
				<span class="description"><?php _e( 'Enable site protection.', 'guard' ); ?></span>
			</label>
		</p>

	<?php
}

/**
 * Output the allowed users input field
 *
 * @since 0.1
 *
 * @uses get_users() To get all users of the site
 */
function guard_setting_allow_users() {
	$allowed_users = (array) get_option( '_guard_allowed_users', array() );	?>

		<select id="_guard_allowed_users" class="chzn-select" name="_guard_allowed_users[]" multiple style="width:25em;" data-placeholder="<?php _e( 'Select a user', 'guard' ); ?>">

			<?php foreach ( get_users() as $user ) : ?>
				<option value="<?php echo $user->ID; ?>" <?php selected( in_array( $user->ID, $allowed_users ) ); ?>><?php echo $user->user_login; ?></option>
			<?php endforeach; ?>

		</select>
		<span class="description float"><?php _e( 'Select which users you want to have access.', 'guard' ); ?></span>

	<?php
}

/**
 * Output the custom message input field
 *
 * @since 0.1
 */
function guard_setting_custom_message() {
	?>

		<textarea name="_guard_custom_message" style="width:25em;" rows="3"><?php echo esc_textarea( get_option( '_guard_custom_message' ) ); ?></textarea>
		<span class="description float"><?php printf( __( 'Serve site guests a nice heads up on the login page. Leave empty if not applicable. This message will only be shown if <strong>Protect my site</strong> is activated.<br/>Allowed HTML tags %s, %s and %s.', 'guard' ), '&#60;a&#62;', '&#60;em&#62;', '&#60;strong&#62;' ); ?></span>

	<?php
}

/**
 * Sanitize the allowed users input field
 *
 * @since 0.1
 *
 * @param string $input The submitted value
 * @return array $input
 */
function guard_setting_allow_users_sanitize( $input ) {
	if ( empty( $input ) )
		return array();

	return array_unique( array_map( 'intval', (array) $input ) );
}

/**
 * Sanitize the custom message input field
 *
 * @since 0.1
 *
 * @uses wp_kses() To filter out all non allowed HTML tags
 *
 * @param string $input The submitted value
 * @return string $input
 */
function guard_setting_custom_message_sanitize( $input ) {
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
 * @since 0.x
 *
 * @uses apply_filters() Calls 'guard_network_settings' hook on the settings
 *
 * @return array $settings {
 *  @type array Setting ID {
 *   @type string $label Setting label
 *   @type string $field_cb Setting input field callback
 *   @type string $section Setting section name
 *   @type string $page Setting page name
 *   @type string $sanitize_cb Setting sanitization callback
 *  }
 * }
 */
function guard_network_settings() {
	return apply_filters( 'guard_network_settings', array(

		/** Main Settings ************************************************/

		// Network only
		'_guard_network_only' => array(
			'label'       => __( 'Network only', 'guard' ),
			'field_cb'    => 'guard_network_setting_network_only',
			'section'     => 'guard-options-main',
			'page'        => 'guard_network',
			'sanitize_cb' => 'intval'
		),

		// Network redirect
		'_guard_network_redirect' => array(
			'label'       => __( 'Redirect to main site', 'guard' ),
			'field_cb'    => 'guard_network_setting_network_redirect',
			'section'     => 'guard-options-main',
			'page'        => 'guard_network',
			'sanitize_cb' => 'intval'
		),

		// Hide "My Sites"
		'_guard_network_hide_my_sites' => array(
			'label'       => __( 'Hide "My Sites"', 'guard' ),
			'field_cb'    => 'guard_network_setting_network_hide_my_sites',
			'section'     => 'guard-options-main',
			'page'        => 'guard_network',
			'sanitize_cb' => 'intval'
		),

		/** Access Settings **********************************************/

		// Network protect switch
		'_guard_network_protect' => array(
			'label'       => __( 'Protect this network', 'guard' ),
			'field_cb'    => 'guard_network_setting_network_protect',
			'section'     => 'guard-options-access',
			'page'        => 'guard_network',
			'sanitize_cb' => 'intval'
		),

		// Allowed network users
		'_guard_network_allowed_users' => array(
			'label'       => __( 'Allowed network users', 'guard' ),
			'field_cb'    => 'guard_network_setting_allow_users',
			'section'     => 'guard-options-access',
			'page'        => 'guard_network',
			'sanitize_cb' => 'guard_setting_allow_users_sanitize'
		),

		/** Additional Settings ******************************************/

		// Custom network login message
		'_guard_network_custom_message' => array(
			'label'       => __( 'Custom login message', 'guard' ),
			'field_cb'    => 'guard_network_setting_custom_message',
			'section'     => 'guard-options-additional',
			'page'        => 'guard_network',
		    'sanitize_cb' => 'guard_setting_custom_message_sanitize'
		),

	) );
}

/**
 * Output network main settings section information header
 *
 * @since 0.x
 */
function guard_network_main_settings_info() {
	?>

		<p><?php _e( 'Here you activate the main network functionality of Guard. For activating the network protection, see the Network Access Settings.', 'guard' ); ?></p>

	<?php
}

/**
 * Output network access settings section information header
 *
 * @since 0.2
 */
function guard_network_access_settings_info() {
	?>

		<p><?php _e( 'Here you activate your network protection. By checking the <em>Protect this network</em> input, this network will only be accessible for admins and allowed users, specified by you in the select option below. No one else shall pass!', 'guard' ); ?></p>

	<?php
}

/**
 * Output network additional settings section information header
 *
 * @since 0.2
 */
function guard_network_additional_settings_info() {
	?>

		<p><?php _e( 'Below you can set additional Network Guard options.', 'guard' ); ?></p>

	<?php
}

/**
 * Output the network only input field
 *
 * @since 0.2
 */
function guard_network_setting_network_only() {
	?>

		<p>
			<label>
				<input type="checkbox" name="_guard_network_only" value="1" <?php checked( guard_is_network_only() ); ?> />
				<span class="description"><?php _e( 'Disable this plugin for individual sites.', 'guard' ); ?></span>
			</label>
		</p>

	<?php
}

/**
 * Output the enable network protection input field
 *
 * @since 0.2
 */
function guard_network_setting_network_protect() {
	?>

		<p>
			<label>
				<input type="checkbox" name="_guard_network_protect" value="1" <?php checked( guard_is_network_protected(); ); ?>/>
				<span class="description"><?php _e( 'Enable network protection.', 'guard' ); ?></span>
			</label>
		</p>

	<?php
}

/**
 * Output the redirect to main site input field
 *
 * @since 0.2
 */
function guard_network_setting_network_redirect() {
	?>

		<p>
			<label>
				<input type="checkbox" name="_guard_network_redirect" value="1" <?php checked( guard_network_redirect() ); ?> />
				<span class="description"><?php _e( 'Redirect users from protected sites to the main site.', 'guard' ); ?></span>
			</label>
		</p>

	<?php
}

/**
 * Output the hide my sites input field
 *
 * @since 0.2
 */
function guard_network_setting_network_hide_my_sites() {
	?>

		<p>
			<label>
				<input type="checkbox" name="_guard_network_hide_my_sites" value="1" <?php checked( guard_network_hide_my_sites() ); ?> />
				<span class="description"><?php _e( 'Hide "My Sites" links and page when a user has access to only one site.', 'guard' ); ?></span>
			</label>
		</p>

	<?php
}

/**
 * Output the allowed network users input field
 *
 * @since 0.2
 *
 * @todo Does get_users() fetch all network users?
 *
 * @uses Guard_MS::get_network_users() To get all users of the network
 */
function guard_network_setting_allow_users() {
	$users = (array) get_site_option( '_guard_network_allowed_users', array() ); ?>

		<select id="_guard_network_allowed_users" class="chzn-select" name="_guard_network_allowed_users[]" multiple style="width:25em;" data-placeholder="<?php _e( 'Select a user', 'guard' ); ?>">

			<?php foreach ( guard_get_network_users() as $user ) : ?>
				<option value="<?php echo $user->ID; ?>" <?php selected( in_array( $user->ID, $users ) ); ?>><?php echo $user->user_login; ?></option>
			<?php endforeach; ?>

		</select>
		<span class="description float"><?php _e( 'Select which network users you want to have access.', 'guard' ); ?></span>

	<?php
}

/**
 * Output the custom network message input field
 *
 * @since 0.2
 */
function guard_network_setting_custom_message() {
	?>

		<textarea name="_guard_network_custom_message" style="width:25em;" rows="3"><?php echo esc_textarea( get_site_option( '_guard_network_custom_message' ) ); ?></textarea>
		<span class="description float"><?php printf( __( 'Serve network guests a nice heads up on the login page. Leave empty if not applicable. This message will only be shown if <strong>Protect this network</strong> is activated.<br/>Allowed HTML tags %s, %s and %s.', 'guard' ), '&#60;a&#62;', '&#60;em&#62;', '&#60;strong&#62;' ); ?></span>

	<?php
}
