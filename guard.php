<?php

/**
 * Plugin Name: Guard
 * Description: Prevent visitors and users from seeing your site
 * Plugin URI:  http://www.offereinspictures.nl/wp-plugins/guard/
 * Author:      Laurens Offereins
 * Author URI:  http://www.offereinspictures.nl
 * Version:     0.1
 * Text Domain: guard
 * Domain Path: /languages/
 */

/**
 * Setup all plugin actions and filters
 */
add_action( 'plugins_loaded',      'guard_load_textdomain'      );
add_action( 'admin_init',          'guard_register_settings'    );
add_action( 'admin_menu',          'guard_admin_menu'           );
add_action( 'template_redirect',   'guard_redirect',      1     );
add_filter( 'plugin_action_links', 'guard_settings_link', 10, 2 );
add_action( 'login_message',       'guard_login_message', 1     );

/**
 * Redirect users on accessing a page of your site
 *
 * @since 0.1
 * 
 * @uses is_user_logged_in() To check if the user is logged in
 * @uses guard_users_is_allowed() To check if the user is allowed
 * @uses auth_redirect() To log the user out and redirect to wp-login.php
 */
function guard_redirect() {

	// Only redirect if site protection is activated
	if ( ! get_option( '_guard_protect_site' ) )
		return;

	// Redirect user if not logged in or if not allowed
	if ( ! is_user_logged_in() || ! guard_user_is_allowed() )
		auth_redirect();
}

/**
 * Returns whether the current user is allowed to enter
 *
 * @since 0.1
 * 
 * @uses apply_filters() To call 'guard_user_is_allowed' for
 *                        plugins to override the access granted
 * @uses current_user_can() To check if current user is admin
 * 
 * @return bool The user is allowed
 */
function guard_user_is_allowed() {
	global $current_user;

	// Get allowed users array
	$allowed = (array) get_option( '_guard_allowed_users', array() );

	// Filter if user is in it
	$allow = apply_filters( 'guard_user_is_allowed', in_array( $current_user->ID, $allowed ) );

	// Admins are ALLWAYS allowed
	return current_user_can( 'administrator' ) || $allow;
}

/**
 * Create the plugin admin page menu item
 *
 * @since 0.1
 * 
 * @uses add_options_page() To add the menu to the options pane
 * @uses add_action() To enable functions hooking into admin page
 *                     head en footer
 */
function guard_admin_menu() {
	$hook = add_options_page( 
		'Guard', 
		'Guard', 
		'manage_options', 
		'guard', 
		'guard_admin_page' 
	);

	add_action( "admin_head-$hook",   'guard_admin_head'   );
	add_action( "admin_footer-$hook", 'guard_admin_footer' );
}

/**
 * Output pluging admin page contents
 *
 * @since 0.1
 * 
 * @uses screen_icont() To output the screen icon
 * @uses settings_fields() To output the form validation inputs
 * @uses do_settings_section() To output all form fields
 * @uses submit_button() To output the form submit button
 */
function guard_admin_page() {
	?>
		<div class="wrap">
			<?php screen_icon(); ?>
			<h2>Guard</h2>

			<form method="post" action="options.php">
				<?php settings_fields( 'guard' ); ?>
				<?php do_settings_sections( 'guard' ); ?>
				<?php submit_button(); ?>
			</form>

		</div>
	<?php
}

/**
 * Enqueue script and style in plugin admin page head
 *
 * @since 0.1
 * 
 * @uses wp_script_is() To check if the script is already registered
 * @uses wp_style_is() To check if the style is already registered
 */
function guard_admin_head() {
	if ( ! wp_script_is( 'chosen', 'registered' ) )
		wp_register_script( 'chosen', plugins_url( 'js/chosen/jquery.chosen.min.js', __FILE__), array( 'jquery' ), '0.9.8' );

	if ( ! wp_style_is( 'chosen', 'registered' ) )
		wp_register_style( 'chosen', plugins_url( 'js/chosen/chosen.css', __FILE__ ) );

	wp_enqueue_script( 'chosen' );
	wp_enqueue_style(  'chosen' );
}

/**
 * Output plugin admin page footer contents
 *
 * Some restyling of chosen elements in the admin page and a
 * float class for description spans to display well with inputs
 * and a lines to initialize the chosen script.
 * 
 * @since 0.1
 */
function guard_admin_footer() {
	?>
<style type="text/css">
	.chzn-select,
	.chzn-container,
	textarea {
		float: left;
	}

	.chzn-container-multi .chzn-choices .search-field input {
		height: 25px;
		padding: 3px;
	}

	span.float {
		padding: 4px 6px;
		float: left;
	}
</style>

<script type="text/javascript">
	jQuery('.chzn-select').chosen();
</script>
	<?php
}

/**
 * Setup the plugin settings
 *
 * @since 0.1
 *
 * @uses add_settings_section() To create the settings sections
 * @uses add_settings_field() To create a setting with it's field
 * @uses register_setting() To enable the setting being saved to the DB
 */
function guard_register_settings() {
	add_settings_section( 'guard-options-access',     __('Access Settings',     'guard'), 'guard_access_settings_info',     'guard' );
	add_settings_section( 'guard-options-additional', __('Additional Settings', 'guard'), 'guard_additional_settings_info', 'guard' );

	add_settings_field( '_guard_protect_site',   __('Protect my site',      'guard'), 'guard_setting_protect_site',   'guard', 'guard-options-access'     );
	add_settings_field( '_guard_allowed_users',  __('Allowed users',        'guard'), 'guard_setting_allow_users',    'guard', 'guard-options-access'     );
	add_settings_field( '_guard_custom_message', __('Custom login message', 'guard'), 'guard_setting_custom_message', 'guard', 'guard-options-additional' );

	register_setting( 'guard', '_guard_protect_site',   'intval'                                );
	register_setting( 'guard', '_guard_allowed_users',  'guard_setting_allow_users_sanitize'    );
	register_setting( 'guard', '_guard_custom_message', 'guard_setting_custom_message_sanitize' );
}

/**
 * Output the first settings section information header
 * 
 * @since 0.1
 */
function guard_access_settings_info() {
	?>
		<p>
			<?php_e('Here you enable the Guard plugin. By checking the <em>Protect my site</em> input, this site will only be accessible for admins and allowed users, specified by you in the select option below. No other shall pass!', 'guard'); ?>
		</p>
	<?php
}

/**
 * Output the second settings section information header
 * 
 * @since 0.1
 */
function guard_additional_settings_info() {
	?>
		<p>
			<?php _e('Below you can set additional Guard options.', 'guard'); ?>
		</p>
	<?php
}

/**
 * Output the input field to enable site protection
 * 
 * @since 0.1
 */
function guard_setting_protect_site() {
	?>
		<p>
			<label>
				<input type="checkbox" name="_guard_protect_site" <?php checked( get_option( '_guard_protect_site' ), 1, false ) ?> value="1" />
				<span class="description"><?php _e('Enable site protection.', 'guard'); ?></span>
			</label>
		</p>
	<?php
}

/**
 * Output the input field to select the allowed users
 *
 * @since 0.1
 * 
 * @uses get_users() To get all users of the site
 */
function guard_setting_allow_users() {
	$users = get_option( '_guard_allowed_users' );

	if ( ! is_array( $users ) )
		$users = array();

	$retval  = '<select id="_guard_allowed_users" class="chzn-select" name="_guard_allowed_users[]" multiple style="width:25em;" data-placeholder="'. __('Select a user', 'guard') .'">';
	foreach ( get_users() as $user ) {
		$retval .= '<option value="'. $user->ID .'"'. selected( in_array( $user->ID, $users ), true, false ) .'>'. $user->user_login .'</option>';
	}
	$retval .= '</select>';
	$retval .= ' <span class="description float">'. __('Select which users you want to have access.', 'guard') .'</span>';

	echo $retval;
}

/**
 * Output the input field to get the custom message
 * 
 * @since 0.1
 */
function guard_setting_custom_message() {
	$value = get_option( '_guard_custom_message' );
	echo '<textarea name="_guard_custom_message" style="width:25em;" rows="3">'. esc_textarea( $value ) .'</textarea> ';
	echo '<span class="description float">'. sprintf( __('Serve site guests a nice heads up on the login page. Leave empty if not applicable.<br> This message will only be shown if <strong>Protect my site</strong> is activated.<br/>Allowed HTML tags %s, %s and %s.', 'guard'), '&#60;a&#62;', '&#60;em&#62;', '&#60;strong&#62;' ) .'</span>';
}

/**
 * Sanitize the input field to select the allowed users
 * 
 * @since 0.1
 * 
 * @param string $input The submitted value
 * @return array $input
 */
function guard_setting_allow_users_sanitize( $input ) {
	if ( empty( $input ) )
		return array();

	return array_map( 'intval', $input );
}

/**
 * Sanitize the input field to get the custom message
 *
 * @since 0.1
 *
 * @uses wp_kses() To filter out all non allowed HTML tags
 * 
 * @param string $input The submitted value
 * @return string $input
 */
function guard_setting_custom_message_sanitize( $input ) {
	return wp_kses( $input, array( 
		'a'      => array('href'), 
		'em'     => array(), 
		'strong' => array() 
	) );
}

/**
 * Add a settings link to the plugin actions on plugin.php
 *
 * @since 0.1
 *
 * @uses add_query_arg() To create the url to the settings page
 * 
 * @param array $links The current plugin action links
 * @param string $file The current plugin file
 * @return array $links All current plugin action links
 */
function guard_settings_link( $links, $file ) {

	// Only add settings link for our plugin
	if ( plugin_basename( __FILE__ ) == $file ) {
		$links['settings'] = '<a href="' . add_query_arg( 'page', 'guard', 'options-general.php' ) . '">' . __('Settings') . '</a>';
	}

	return $links;
}

/**
 * Appends our custom message to the login messages
 *
 * @since 0.1
 * 
 * @param string $message The current login messages
 * @return string $message
 */
function guard_login_message( $message ) {

	// Only display message if site protection is activated
	if ( ! get_option( '_guard_protect_site' ) )
		return $message;

	$custom_message = get_option( '_guard_custom_message' );

	// Do not add anything if no message is set
	if ( empty( $custom_message ) )
		return $message;

	$message .= '<p class="message">'. $custom_message .'<p>';

	return $message;
}

/**
 * Loads the textdomain file for this plugin
 *
 * @since 0.1
 *
 * @uses load_textdomain() To insert the matched language file
 * 
 * @return mixed Text domain if found, else false
 */
function guard_load_textdomain() {

	// Traditional WordPress plugin locale filter
	$mofile        = sprintf( 'guard-%s.mo', get_locale() );

	// Setup paths to current locale file
	$mofile_local  = plugin_dir_path( __FILE__ ) .'languages/'. $mofile;
	$mofile_global = WP_LANG_DIR . '/guard/' . $mofile;

	// Look in global /wp-content/languages/guard folder
	if ( file_exists( $mofile_global ) ) {
		return load_textdomain( 'guard', $mofile_global );

	// Look in local /wp-content/plugins/guard/languages/ folder
	} elseif ( file_exists( $mofile_local ) ) {
		return load_textdomain( 'guard', $mofile_local );
	}

	// Nothing found
	return false;
}
