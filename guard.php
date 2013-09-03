<?php

/**
 * The Guard Plugin
 *
 * @package Guard
 * @subpackage Main
 */

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

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Setup all plugin actions and filters
 */
add_action( 'plugins_loaded',      'guard_load_textdomain'      );
add_action( 'admin_init',          'guard_register_settings'    );
add_action( 'admin_menu',          'guard_admin_menu'           );
add_action( 'template_redirect',   'guard_site_protect',  1     );
add_filter( 'plugin_action_links', 'guard_settings_link', 10, 2 );
add_action( 'login_message',       'guard_login_message', 1     );
register_uninstall_hook( __FILE__, 'guard_uninstall'            );

/**
 * Setup Multisite actions and filters
 */
if ( is_multisite() ) {
	add_action( 'plugins_loaded',      'guard_network_only'                 );
	add_action( 'admin_init',          'guard_register_network_settings'    );
	add_action( 'network_admin_menu',  'guard_network_menu'                 );
	add_action( 'template_redirect',   'guard_network_protect',       0     );
	add_action( 'guard_site_protect',  'guard_network_redirect'             );
	add_action( 'get_blogs_of_user',   'guard_network_blogs_of_user', 10, 3 );
	add_action( 'admin_bar_menu',      'guard_network_admin_bar',     99    );
	add_action( 'admin_menu',          'guard_network_admin_menu',    99    );
	register_uninstall_hook( __FILE__, 'guard_network_uninstall'            );
}

/**
 * Redirect users on accessing a page of your site
 *
 * @since 0.1
 * 
 * @uses is_user_logged_in() To check if the user is logged in
 * @uses guard_users_is_allowed() To check if the user is allowed
 * @uses auth_redirect() To log the user out and redirect to wp-login.php
 */
function guard_site_protect() {

	// Only redirect if site protection is activated
	if ( ! get_option( '_guard_site_protect' ) )
		return;

	// Redirect user if not logged in or if not allowed
	if ( ! is_user_logged_in() || ! guard_user_is_allowed() ) {
		do_action( 'guard_site_protect' );
		auth_redirect();
	}
}

/**
 * Returns whether the current user is allowed to enter
 *
 * @since 0.1
 * 
 * @uses apply_filters() To call 'guard_user_is_allowed' for
 *                        plugins to override the access granted
 * @uses current_user_can() To check if the current user is admin
 * 
 * @return boolean The user is allowed
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
		__('Guard Settings', 'guard'), 
		'Guard', 
		'manage_options', 
		'guard', 
		'guard_admin_page' 
	);

	add_action( "admin_head-$hook",   'guard_admin_head'   );
	add_action( "admin_footer-$hook", 'guard_admin_footer' );
}

/**
 * Output plugin admin page contents
 *
 * @since 0.1
 * 
 * @uses screen_icon() To output the screen icon
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

	do_action( 'guard_admin_head' );
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
		do_action( 'guard_admin_footer' );
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

	add_settings_field( '_guard_site_protect',   __('Protect my site',      'guard'), 'guard_setting_protect_site',   'guard', 'guard-options-access'     );
	add_settings_field( '_guard_allowed_users',  __('Allowed users',        'guard'), 'guard_setting_allow_users',    'guard', 'guard-options-access'     );
	add_settings_field( '_guard_custom_message', __('Custom login message', 'guard'), 'guard_setting_custom_message', 'guard', 'guard-options-additional' );

	register_setting( 'guard', '_guard_site_protect',   'intval'                                );
	register_setting( 'guard', '_guard_allowed_users',  'guard_setting_allow_users_sanitize'    );
	register_setting( 'guard', '_guard_custom_message', 'guard_setting_custom_message_sanitize' );
}

/**
 * Output access settings section information header
 * 
 * @since 0.1
 */
function guard_access_settings_info() {
	?>
		<p>
			<?php _e('Here you enable the Guard plugin. By checking the <em>Protect my site</em> input, this site will only be accessible for admins and allowed users, specified by you in the select option below. No one else shall pass!', 'guard'); ?>
		</p>
	<?php
}

/**
 * Output additional settings section information header
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
 * Output the enable site protection input field
 * 
 * @since 0.1
 */
function guard_setting_protect_site() {
	?>
		<p>
			<label>
				<input type="checkbox" name="_guard_site_protect" <?php checked( get_option( '_guard_site_protect' ), 1 ) ?> value="1" />
				<span class="description"><?php _e('Enable site protection.', 'guard'); ?></span>
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
 * Output the custom message input field
 * 
 * @since 0.1
 */
function guard_setting_custom_message() {
	$value = get_option( '_guard_custom_message' );
	echo '<textarea name="_guard_custom_message" style="width:25em;" rows="3">'. esc_textarea( $value ) .'</textarea> ';
	echo '<span class="description float">'. sprintf( __('Serve site guests a nice heads up on the login page. Leave empty if not applicable. This message will only be shown if <strong>Protect my site</strong> is activated.<br/>Allowed HTML tags %s, %s and %s.', 'guard'), '&#60;a&#62;', '&#60;em&#62;', '&#60;strong&#62;' ) .'</span>';
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

	return array_unique( array_map( 'intval', $input ) );
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
		'a'      => array('href'), 
		'em'     => array(), 
		'strong' => array() 
	) ) );
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
	if ( ! get_option( '_guard_site_protect' ) )
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

/**
 * Clean up when this plugin is deleted
 *
 * @since 0.x
 *
 * @uses delete_option()
 */
function guard_uninstall() {
	$options = array(
		'_guard_site_protect',   
		'_guard_allowed_users',  
		'_guard_custom_message', 
	);

	foreach ( $options as $option )
		delete_option( $option );
}

/** Multisite ****************************************************/

/**
 * Ensure Guard is only used for the network
 *
 * @since 0.x
 *
 * @uses remove_action()
 * @uses remove_filter()
 */
function guard_network_only() {
	if ( ! get_site_option( '_guard_network_only' ) )
		return;

	// Unset all Guard single site actions and filters
	remove_action( 'admin_init',          'guard_register_settings' );
	remove_action( 'admin_menu',          'guard_admin_menu'        );
	remove_action( 'template_redirect',   'guard_site_protect',  1  );
	remove_action( 'login_message',       'guard_login_message', 1  );
}

/**
 * Redirect user if network is protected
 *
 * @since 0.x
 *
 * @uses is_user_logged_in() To check if the user is logged in
 * @uses guard_network_user_is_allowed() To check if the network user is allowed
 * @uses auth_redirect() To log the user out and redirect to wp-login.php
 */
function guard_network_protect() {

	// Only redirect if network protection is activated
	if ( ! get_site_option( '_guard_network_protect' ) )
		return;

	// Redirect user if not logged in or if not allowed
	if ( ! is_user_logged_in() || ! guard_network_user_is_allowed() )
		auth_redirect();
}

/**
 * Returns whether the current network user is allowed to enter
 *
 * @since 0.x
 * 
 * @uses apply_filters() Calls 'guard_network_user_is_allowed' hook 
 *                        for plugins to override the access granted
 * @uses is_super_admin() To check if the current user is super admin
 * 
 * @return boolean The user is allowed
 */
function guard_network_user_is_allowed() {
	global $current_user;

	// Get allowed users array
	$allowed = (array) get_site_option( '_guard_network_allowed_users', array() );

	// Filter if user is in it
	$allow = apply_filters( 'guard_network_user_is_allowed', in_array( $current_user->ID, $allowed ) );

	// Super admins are ALLWAYS allowed
	return is_super_admin( $current_user->ID ) || $allow;
}

/**
 * Redirect users to network main site if sub site is protected
 *
 * @since 0.x
 *
 * @uses get_site_option()
 */
function guard_network_redirect() {

	// Only alter redirection
	if ( ! get_site_option( '_guard_network_redirect' ) )
		return;

	// Redirect user to main site
	wp_redirect( get_site_url( 1 ) );
	exit;
}

/**
 * Remove user blogs that are not allowed for given user
 *
 * @since 0.x
 * 
 * @uses switch_to_blog()
 * @uses guard_user_is_allowed()
 * @uses restore_current_blog()
 * 
 * @param array $blogs Blogs of user
 * @param int $user_id User ID
 * @param boolean $all Whether to return also all hidden blogs
 * @return array $blogs
 */
function guard_network_blogs_of_user( $blogs, $user_id, $all ) {

	// All blogs requested
	if ( $all )
		return $blogs;

	foreach ( $blogs as $blog_id => $details ) {
		switch_to_blog( $blog_id );

		if ( get_option( '_guard_site_protect' ) ) {
			if ( ! guard_user_is_allowed() ) {
				unset( $blogs[$blog_id] );
			}
		}

		restore_current_blog();
	}

	return $blogs;
}

/**
 * Modify the admin bar for protected sites
 *
 * @since 0.x
 *
 * @uses guard_network_hide_my_sites()
 * @uses WP_Admin_Bar::remove_menu()
 * 
 * @param WP_Admin_Bar $wp_admin_bar
 */
function guard_network_admin_bar( $wp_admin_bar ) {
	if ( guard_network_hide_my_sites() ) {
		$wp_admin_bar->remove_menu( 'my-sites' );
	}
}

/**
 * Modify the admin menu for protected sites
 *
 * @since 0.x
 * 
 * @uses guard_network_hide_my_sites()
 * @uses remove_submenu_page()
 */
function guard_network_admin_menu() {
	if ( guard_network_hide_my_sites() ) {

		// Only removes menu item, not admin page itself
		remove_submenu_page( 'index.php', 'my-sites.php' );
	}
}

/**
 * Return whether to hide "My Sites" for the current user
 *
 * @since 0.x
 *
 * @uses get_site_option()
 * @uses get_blogs_of_user()
 * @uses get_current_user_id()
 * 
 * @return boolean Hide "My Sites"
 */
function guard_network_hide_my_sites() {
	if ( ! get_site_option( '_guard_network_hide_my_sites' ) )
		return false;

	$user_id = get_current_user_id();
	if ( is_super_admin( $user_id ) )
		return false;
		
	$blogs = get_blogs_of_user( $user_id );

	return apply_filters( 'guard_network_hide_my_sites', 1 == count( $blogs ) );
}

/**
 * Create the plugin network admin page menu item
 *
 * @since 0.x
 * 
 * @uses add_options_page() To add the menu to the options pane
 * @uses add_action() To enable functions hooking into admin page
 *                     head en footer
 */
function guard_network_menu() {
	$hook = add_submenu_page(
		'settings.php',
		__('Guard Network Settings', 'guard'),
		__('Guard Network',          'guard'),
		'manage_network',
		'guard_network',
		'guard_network_page'
	);

	add_action( "admin_head-$hook",   'guard_admin_head'   );
	add_action( "admin_footer-$hook", 'guard_admin_footer' );
}

/**
 * Output plugin network admin page contents
 *
 * @since 0.x
 * 
 * @uses screen_icon() To output the screen icon
 * @uses settings_fields() To output the form validation inputs
 * @uses do_settings_section() To output all form fields
 * @uses submit_button() To output the form submit button
 */
function guard_network_page() {
	?>
		<div class="wrap">
			<?php screen_icon('options-general'); ?>
			<h2><?php _e('Guard Network Settings', 'guard'); ?></h2>

			<form method="post" action="<?php echo network_admin_url( 'edit.php?action=guard_network' ); ?>">
				<?php settings_fields( 'guard_network' ); ?>
				<?php do_settings_sections( 'guard_network' ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
	<?php
}

/**
 * Setup the plugin network settings
 *
 * @since 0.x
 * 
 * @uses add_settings_section() To create the settings sections
 * @uses add_settings_field() To create a setting with it's field
 * @uses register_setting() To enable the setting being saved to the DB
 */
function guard_register_network_settings() {
	add_settings_section( 'guard-options-access',     __('Network Access Settings',     'guard'), 'guard_network_access_settings_info',     'guard_network' );
	add_settings_section( 'guard-options-additional', __('Additional Network Settings', 'guard'), 'guard_network_additional_settings_info', 'guard_network' );

	add_settings_field( '_guard_network_only',           __('Network only',          'guard'), 'guard_network_setting_network_only',          'guard_network', 'guard-options-access'     );
	add_settings_field( '_guard_network_redirect',       __('Redirect to main site', 'guard'), 'guard_network_setting_network_redirect',      'guard_network', 'guard-options-access'     );
	add_settings_field( '_guard_network_hide_my_sites',  __('Hide "My Sites"',       'guard'), 'guard_network_setting_network_hide_my_sites', 'guard_network', 'guard-options-access'     );
	add_settings_field( '_guard_network_protect',        __('Protect this network',  'guard'), 'guard_network_setting_network_protect',       'guard_network', 'guard-options-access'     );
	add_settings_field( '_guard_network_allowed_users',  __('Allowed network users', 'guard'), 'guard_network_setting_allow_users',           'guard_network', 'guard-options-access'     );
	add_settings_field( '_guard_network_custom_message', __('Custom login message',  'guard'), 'guard_network_setting_custom_message',        'guard_network', 'guard-options-additional' );

	/**
	 * There's no valid Network Settings API available so we'll have to
	 * do the sanitization and storing manually via the network's edit.php.
	 * 
	 * @link http://core.trac.wordpress.org/ticket/15691
	 */
	add_action( 'network_admin_edit_guard_network', 'guard_network_settings_api' );
	add_action( 'network_admin_notices',            'guard_network_admin_notice' );
}

/**
 * Output network access settings section information header
 * 
 * @since 0.x
 */
function guard_network_access_settings_info() {
	?>
		<p>
			<?php _e('Here you enable the Guard plugin for your network. By checking the <em>Protect this network</em> input, this network will only be accessible for admins and allowed users, specified by you in the select option below. No one else shall pass!', 'guard'); ?>
		</p>
	<?php
}

/**
 * Output network additional settings section information header
 * 
 * @since 0.x
 */
function guard_network_additional_settings_info() {
	?>
		<p>
			<?php _e('Below you can set additional Network Guard options.', 'guard'); ?>
		</p>
	<?php
}

/**
 * Output the network only input field
 * 
 * @since 0.x
 */
function guard_network_setting_network_only() {
	?>
		<p>
			<label>
				<input type="checkbox" name="_guard_network_only" <?php checked( get_site_option( '_guard_network_only' ), 1 ) ?> value="1" />
				<span class="description"><?php _e('Disable this plugin for individual sites.', 'guard'); ?></span>
			</label>
		</p>
	<?php
}

/**
 * Output the enable network protection input field
 * 
 * @since 0.x
 */
function guard_network_setting_network_protect() {
	?>
		<p>
			<label>
				<input type="checkbox" name="_guard_network_protect" <?php checked( get_site_option( '_guard_network_protect' ), 1 ) ?> value="1" />
				<span class="description"><?php _e('Enable network protection.', 'guard'); ?></span>
			</label>
		</p>
	<?php
}

/**
 * Output the redirect to main site input field
 * 
 * @since 0.x
 */
function guard_network_setting_network_redirect() {
	?>
		<p>
			<label>
				<input type="checkbox" name="_guard_network_redirect" <?php checked( get_site_option( '_guard_network_redirect' ), 1 ) ?> value="1" />
				<span class="description"><?php _e('Redirect users from protected sites to the main site.', 'guard'); ?></span>
			</label>
		</p>
	<?php
}

/**
 * Output the hide my sites input field
 * 
 * @since 0.x
 */
function guard_network_setting_network_hide_my_sites() {
	?>
		<p>
			<label>
				<input type="checkbox" name="_guard_network_hide_my_sites" <?php checked( get_site_option( '_guard_network_hide_my_sites' ), 1 ) ?> value="1" />
				<span class="description"><?php _e('Hide "My Sites" links and page when a user has access to only one site.', 'guard'); ?></span>
			</label>
		</p>
	<?php
}

/**
 * Output the allowed network users input field
 *
 * @since 0.x
 * 
 * @todo Does get_users() fetch all network users?
 * 
 * @uses guard_get_network_users() To get all users of the network
 */
function guard_network_setting_allow_users() {
	$users = get_site_option( '_guard_network_allowed_users' );

	if ( ! is_array( $users ) )
		$users = array();

	$retval  = '<select id="_guard_network_allowed_users" class="chzn-select" name="_guard_network_allowed_users[]" multiple style="width:25em;" data-placeholder="'. __('Select a user', 'guard') .'">';
	foreach ( guard_get_network_users() as $user ) {
		$retval .= '<option value="'. $user->ID .'"'. selected( in_array( $user->ID, $users ), true, false ) .'>'. $user->user_login .'</option>';
	}
	$retval .= '</select>';
	$retval .= ' <span class="description float">'. __('Select which network users you want to have access.', 'guard') .'</span>';

	echo $retval;
}

	/**
	 * Return array of all network users
	 *
	 * @since 0.x
	 *
	 * @uses get_current_user_id()
	 * @uses get_blogs_of_user()
	 * @uses switch_to_blog()
	 * @uses get_users()
	 * @uses restore_current_blog()
	 * 
	 * @return array Network users
	 */
	function guard_get_network_users() {
		$users = array();
		$user_id = get_current_user_id(); // Always super admin?

		foreach ( get_blogs_of_user( $user_id ) as $blog_id => $details ) {
			switch_to_blog( $blog_id );

			// array( 0 => WP_User ) becomes array( $user_id => WP_User )
			foreach ( get_users() as $user )
				$users[$user->ID] = $user;

			restore_current_blog();
		}

		return apply_filters( 'guard_get_network_users', $users );
	}

/**
 * Output the custom network message input field
 * 
 * @since 0.x
 */
function guard_network_setting_custom_message() {
	$value = get_site_option( '_guard_network_custom_message' );
	echo '<textarea name="_guard_network_custom_message" style="width:25em;" rows="3">'. esc_textarea( $value ) .'</textarea> ';
	echo '<span class="description float">'. sprintf( __('Serve network guests a nice heads up on the login page. Leave empty if not applicable. This message will only be shown if <strong>Protect this network</strong> is activated.<br/>Allowed HTML tags %s, %s and %s.', 'guard'), '&#60;a&#62;', '&#60;em&#62;', '&#60;strong&#62;' ) .'</span>';
}

/**
 * Handle updating network settings
 *
 * @since 0.x
 *
 * @uses update_site_option()
 */
function guard_network_settings_api() {
	if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'guard_network-options' ) )
		return;

	// array( $option_name => $option_sanitize_callback );
	$options = apply_filters( 'guard_network_settings', array(
		'_guard_network_only'           => 'intval',           
		'_guard_network_protect'        => 'intval',        
		'_guard_network_redirect'       => 'intval',        
		'_guard_network_hide_my_sites'  => 'intval',        
		'_guard_network_allowed_users'  => 'guard_setting_allow_users_sanitize',  
		'_guard_network_custom_message' => 'guard_setting_custom_message_sanitize', 
	) );

	foreach ( $options as $option => $sanitize_cb ) {
		if ( ! isset( $_POST[$option] ) )
			$_POST[$option] = apply_filters( 'guard_network_settings_default', 0, $option );

		$value = call_user_func_array( $sanitize_cb, array( $_POST[$option] ) );

		// Don't catch retval since both non-updates and errors return false
		update_site_option( $option, $value );
	}

	// Build redirect url string
	$args = array( 'page' => 'guard_network', 'settings-updated' => 'true' ); // Always true?
	wp_redirect( add_query_arg( $args, network_admin_url('settings.php') ) );
	exit;
}

/**
 * Output network settings update message
 *
 * @since 0.x
 */
function guard_network_admin_notice() {
	if ( ! guard_is_network_page() )
		return;

	if ( isset( $_GET['settings-updated'] ) ) {
		$type = 'true' == $_GET['settings-updated'] ? 'updated' : 'error';
		$message = 'updated' == $type ? __('Settings saved.') : apply_filters( 'guard_network_admin_notice', __('Something went wrong', 'guard'), $_GET['settings-updated'] );
		echo '<div class="message ' . $type . '"><p>' . $message . '</p></div>';
	}
}

/**
 * Return whether we are on the plugin network page
 *
 * @since 0.x
 *
 * @global string $hook_suffix
 * @return boolean This is the network page
 */
function guard_is_network_page() {
	global $hook_suffix;

	if ( isset( $hook_suffix ) && 'settings_page_guard_network' == $hook_suffix )
		return true;

	return false;
}

/**
 * Clean up when this plugin is deleted
 *
 * @since 0.x
 *
 * @uses delete_site_option()
 */
function guard_network_uninstall() {
	$options = array(
		'_guard_network_only',           
		'_guard_network_protect',        
		'_guard_network_redirect',        
		'_guard_network_hide_my_sites',        
		'_guard_network_allowed_users',  
		'_guard_network_custom_message', 
	);

	foreach ( $options as $option )
		delete_site_option( $option );
}

/** Multisite Manage Sites ***************************************/

